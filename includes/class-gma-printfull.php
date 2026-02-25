<?php
/**
 * Printful API integration class.
 *
 * @package GunMerch_AI
 * @since 1.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GMA_Printful
 *
 * Handles Printful API integration for product creation and order sync.
 *
 * @since 1.0.0
 */
class GMA_Printful {

	/**
	 * Printful API base URL.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private $api_base = 'https://api.printful.com';

	/**
	 * Printful API key.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private $api_key;

	/**
	 * Logger instance.
	 *
	 * @since 1.0.0
	 * @var GMA_Logger|null
	 */
	private $logger;

	/**
	 * Printful store ID.
	 *
	 * @since 1.0.2
	 * @var string
	 */
	private $store_id;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->api_key = get_option( 'gma_printful_api_key', '' );
		$this->store_id = get_option( 'gma_printful_store_id', '' );
		// Lazy load logger to avoid infinite loop during singleton construction.
		$this->logger  = null;

		$this->register_hooks();
	}

	/**
	 * Get logger instance lazily to avoid infinite loop during construction.
	 *
	 * @since 1.0.2
	 * @return GMA_Logger|null
	 */
	private function get_logger() {
		if ( null === $this->logger && function_exists( 'gunmerch_ai' ) ) {
			$plugin = gunmerch_ai();
			if ( method_exists( $plugin, 'get_class' ) ) {
				$this->logger = $plugin->get_class( 'logger' );
			}
		}
		return $this->logger;
	}

	/**
	 * Register WordPress hooks.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function register_hooks() {
		// Hook for when design is approved.
		add_action( 'gma_design_status_changed', array( $this, 'on_design_approved' ), 10, 3 );
	}

	/**
	 * Handle design approval.
	 *
	 * @since 1.0.0
	 * @param int    $design_id Design ID.
	 * @param string $status    New status.
	 * @param array  $meta      Meta data.
	 * @return void
	 */
	public function on_design_approved( $design_id, $status, $meta ) {
		if ( 'approved' !== $status ) {
			return;
		}

		// Check if auto-publish is enabled.
		$settings = get_option( 'gma_settings', array() );
		if ( ! empty( $settings['auto_publish_to_printful'] ) ) {
			$this->create_product( $design_id );
		}
	}

	/**
	 * Test API connection.
	 *
	 * @since 1.0.0
	 * @return array|WP_Error Response or error.
	 */
	public function test_connection() {
		if ( empty( $this->api_key ) ) {
			return new WP_Error( 'no_api_key', __( 'Printful API key not configured', 'gunmerch-ai' ) );
		}

		$response = $this->api_request( '/store' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return array(
			'success'     => true,
			'store_name'  => isset( $response['result']['name'] ) ? $response['result']['name'] : '',
			'store_email' => isset( $response['result']['email'] ) ? $response['result']['email'] : '',
		);
	}

	/**
	 * Create product in Printful.
	 *
	 * @since 1.0.0
	 * @param int $design_id Design ID.
	 * @return array|WP_Error Product data or error.
	 */
	public function create_product( $design_id ) {
		$logger = $this->get_logger();
		$core   = gunmerch_ai()->get_class( 'core' );
		$design = $core ? $core->get_design( $design_id ) : null;

		if ( ! $design ) {
			if ( $logger ) $logger->log( 'error', 'Printful create_product: Design not found', $design_id );
			return new WP_Error( 'design_not_found', __( 'Design not found', 'gunmerch-ai' ) );
		}

		if ( empty( $this->api_key ) ) {
			if ( $logger ) $logger->log( 'error', 'Printful create_product: No API key configured', $design_id );
			return new WP_Error( 'no_api_key', __( 'Printful API key not configured', 'gunmerch-ai' ) );
		}

		// Check if product already exists in Printful.
		$existing_product = $this->get_product_by_external_id( $design_id );
		if ( $existing_product && ! is_wp_error( $existing_product ) ) {
			if ( $logger ) $logger->log( 'info', 'Printful: Product already exists with ID ' . $existing_product['id'], $design_id );
			// Update local meta with existing Printful ID.
			update_post_meta( $design_id, '_gma_printful_product_id', sanitize_text_field( $existing_product['id'] ) );
			update_post_meta( $design_id, '_gma_printful_synced', current_time( 'mysql' ) );
			$core->update_design_status( $design_id, 'live', array( 'printful_id' => $existing_product['id'] ) );
			return array(
				'success'       => true,
				'product_id'    => $existing_product['id'],
				'design_id'     => $design_id,
				'existing'      => true,
			);
		}

		// Use design text as the product title (not trend title).
		$design_text = $core ? $core->get_design_meta( $design_id, 'design_text' ) : '';
		$product_title = ! empty( $design_text ) ? $design_text : $design->post_title;
		
		if ( $logger ) $logger->log( 'info', 'Printful: Starting product creation for design "' . $product_title . '"', $design_id );

		// Get design image - upload to Printful file library first.
		$image_url = '';
		if ( has_post_thumbnail( $design_id ) ) {
			$image_url = get_the_post_thumbnail_url( $design_id, 'full' );
			if ( $logger ) $logger->log( 'info', 'Printful: Found image ' . $image_url, $design_id );
		} else {
			if ( $logger ) $logger->log( 'error', 'Printful: NO featured image found', $design_id );
		}

		// Check if this is a text-only design (no image required).
		$is_text_only = get_post_meta( $design_id, '_gma_use_text_design', true ) === '1';

		// If no image and not text-only, we can't create the product.
		if ( empty( $image_url ) && ! $is_text_only ) {
			if ( $logger ) $logger->log( 'error', 'Printful: No image and not text-only', $design_id );
			return new WP_Error( 'no_image', __( 'Design needs an image before publishing to Printful', 'gunmerch-ai' ) );
		}

		// Upload image to Printful file library.
		if ( $logger ) $logger->log( 'info', 'Printful: Uploading image to file library...', $design_id );
		$file_id = $this->upload_file_to_library( $image_url, $design->post_title );

		if ( is_wp_error( $file_id ) ) {
			if ( $logger ) $logger->log( 'error', 'Printful: Upload failed - ' . $file_id->get_error_message(), $design_id );
			return $file_id;
		}
		if ( $logger ) $logger->log( 'info', 'Printful: File uploaded, ID: ' . $file_id, $design_id );

		// Get the file URL from Printful.
		$file_url = $this->get_file_url( $file_id );
		if ( is_wp_error( $file_url ) ) {
			if ( $logger ) $logger->log( 'error', 'Printful: Get file URL failed - ' . $file_url->get_error_message(), $design_id );
			return $file_url;
		}
		if ( $logger ) $logger->log( 'info', 'Printful: File URL retrieved: ' . $file_url, $design_id );

		// Find template product and clone its variants.
		$template_variants = $this->get_template_variants( $design_id );
		if ( is_wp_error( $template_variants ) ) {
			if ( $logger ) $logger->log( 'error', 'Printful: ' . $template_variants->get_error_message(), $design_id );
			return $template_variants;
		}

		// Replace template file ID with new design file ID.
		foreach ( $template_variants as &$variant ) {
			if ( isset( $variant['files'] ) && is_array( $variant['files'] ) ) {
				foreach ( $variant['files'] as &$file ) {
					if ( isset( $file['type'] ) && 'front' === $file['type'] ) {
						$file['id'] = $file_id; // Use new design file.
					}
				}
			}
		}

		// Prepare product data with template variants.
		$product_data = array(
			'name'         => $product_title,
			'sync_product' => array(
				'name'        => $product_title,
				'thumbnail'   => $image_url,
				'external_id' => (string) $design_id,
			),
			'sync_variants' => $template_variants,
		);

		$response = $this->api_request( '/store/products', 'POST', $product_data );

		$success = ! is_wp_error( $response ) && isset( $response['result']['id'] );

		// Log the API call.
		$logger = $this->get_logger();
		if ( $logger ) {
			$logger->log_api_call(
				'printfull',
				'/store/products',
				$product_data,
				is_wp_error( $response ) ? $response->get_error_message() : $response,
				$success
			);
		}

		if ( ! $success ) {
			if ( is_wp_error( $response ) ) {
				return $response;
			}
			return new WP_Error( 'create_failed', __( 'Failed to create product in Printful', 'gunmerch-ai' ) );
		}

		// Store Printful product ID.
		$printful_id = $response['result']['id'];
		update_post_meta( $design_id, '_gma_printful_product_id', sanitize_text_field( $printful_id ) );
		update_post_meta( $design_id, '_gma_printful_synced', current_time( 'mysql' ) );

		// Update status to live.
		$core->update_design_status( $design_id, 'live', array( 'printful_id' => $printful_id ) );

		// Set notification.
		set_transient(
			'gma_design_published_notification',
			sprintf(
				/* translators: %s: Design title */
				__( "Design '%s' approved and sent to Printful", 'gunmerch-ai' ),
				esc_html( $design->post_title )
			),
			HOUR_IN_SECONDS
		);

		return array(
			'success'       => true,
			'product_id'    => $printful_id,
			'design_id'     => $design_id,
		);
	}

	/**
	 * Get product from Printful.
	 *
	 * @since 1.0.0
	 * @param int $printful_id Printful product ID.
	 * @return array|WP_Error Product data or error.
	 */
	public function get_product( $printful_id ) {
		return $this->api_request( '/store/products/' . absint( $printful_id ) );
	}

	/**
	 * Get product by external ID (design ID).
	 *
	 * @since 1.0.3
	 * @param string $external_id External ID to search for.
	 * @return array|null Product data if found, null if not found.
	 */
	public function get_product_by_external_id( $external_id ) {
		$logger = $this->get_logger();
		$response = $this->api_request( '/store/products', 'GET', array(), array( 'limit' => 100 ) );

		if ( is_wp_error( $response ) || empty( $response['result'] ) ) {
			return null;
		}

		foreach ( $response['result'] as $product ) {
			if ( isset( $product['external_id'] ) && $product['external_id'] === (string) $external_id ) {
				if ( $logger ) $logger->log( 'debug', 'Printful: Found existing product ' . $product['id'] . ' for external_id ' . $external_id );
				return $product;
			}
		}

		return null;
	}

	/**
	 * Delete product from Printful.
	 *
	 * @since 1.0.0
	 * @param int $printful_id Printful product ID.
	 * @return bool|WP_Error True on success, error on failure.
	 */
	public function delete_product( $printful_id ) {
		$response = $this->api_request( '/store/products/' . absint( $printful_id ), 'DELETE' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return true;
	}

	/**
	 * Sync sales data from Printful.
	 *
	 * @since 1.0.0
	 * @return array Array of synced orders.
	 */
	public function sync_sales() {
		if ( empty( $this->api_key ) ) {
			return array();
		}

		// Get orders from last 7 days.
		$date_from = gmdate( 'Y-m-d', strtotime( '-7 days' ) );

		$response = $this->api_request(
			'/orders',
			'GET',
			array(),
			array(
				'status' => 'fulfilled',
				'created_after' => $date_from,
			)
		);

		$logger = $this->get_logger();
		if ( is_wp_error( $response ) ) {
			if ( $logger ) {
				$logger->log(
					'error',
					__( 'Failed to sync sales from Printful: ', 'gunmerch-ai' ) . $response->get_error_message()
				);
			}
			return array();
		}

		$orders = isset( $response['result'] ) ? $response['result'] : array();
		$synced = array();

		foreach ( $orders as $order ) {
			$synced[] = $this->process_order( $order );
		}

		if ( $logger ) {
			$logger->log(
				'info',
				sprintf(
					/* translators: %d: Number of orders synced */
					__( 'Synced %d orders from Printful', 'gunmerch-ai' ),
					count( $synced )
				)
			);
		}

		return $synced;
	}

	/**
	 * Process a Printful order.
	 *
	 * @since 1.0.0
	 * @param array $order Order data.
	 * @return array Processed order data.
	 */
	private function process_order( $order ) {
		$items = array();

		if ( isset( $order['items'] ) ) {
			foreach ( $order['items'] as $item ) {
				$external_id = isset( $item['external_id'] ) ? $item['external_id'] : '';

				// Try to find matching design.
				$design_id = 0;
				if ( $external_id ) {
					$design_id = $this->get_design_by_external_id( $external_id );
				}

				$items[] = array(
					'design_id'   => $design_id,
					'external_id' => $external_id,
					'quantity'    => $item['quantity'],
					'price'       => $item['retail_price'],
				);

				// Update design sales count.
				if ( $design_id ) {
					$this->record_sale( $design_id, $item['quantity'], $item['retail_price'] );
				}
			}
		}

		return array(
			'order_id'    => $order['id'],
			'created'     => $order['created'],
			'total'       => $order['total'],
			'items'       => $items,
		);
	}

	/**
	 * Find design by external ID.
	 *
	 * @since 1.0.0
	 * @param string $external_id Printful external ID.
	 * @return int Design ID or 0.
	 */
	private function get_design_by_external_id( $external_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$post_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} 
				WHERE meta_key = '_gma_printful_product_id' 
				AND meta_value = %s 
				LIMIT 1",
				sanitize_text_field( $external_id )
			)
		);

		return $post_id ? absint( $post_id ) : 0;
	}

	/**
	 * Record a sale for a design.
	 *
	 * @since 1.0.0
	 * @param int    $design_id Design ID.
	 * @param int    $quantity  Quantity sold.
	 * @param float  $price     Sale price.
	 * @return void
	 */
	private function record_sale( $design_id, $quantity, $price ) {
		$sales_count = get_post_meta( $design_id, '_gma_sales_count', true );
		$sales_count = $sales_count ? absint( $sales_count ) + absint( $quantity ) : absint( $quantity );
		update_post_meta( $design_id, '_gma_sales_count', $sales_count );

		$revenue = get_post_meta( $design_id, '_gma_revenue', true );
		$revenue = $revenue ? floatval( $revenue ) + ( floatval( $price ) * absint( $quantity ) ) : ( floatval( $price ) * absint( $quantity ) );
		update_post_meta( $design_id, '_gma_revenue', $revenue );

		// Update plugin stats.
		$core = gunmerch_ai()->get_class( 'core' );
		if ( $core ) {
			$core->update_stats( 'sale' );
		}

		// Update design status if first sale.
		if ( $sales_count === absint( $quantity ) ) {
			if ( $core ) {
				$core->update_design_status( $design_id, 'sold' );
			}
		}

		// Log the sale.
		$logger = $this->get_logger();
		if ( $logger ) {
			$logger->log(
				'info',
				sprintf(
					/* translators: 1: Quantity, 2: Price */
					__( 'Sale recorded: %1$d units at $%2$s', 'gunmerch-ai' ),
					$quantity,
					number_format( $price, 2 )
				),
				$design_id
			);
		}

		// Set sale notification.
		$design = get_post( $design_id );
		if ( $design ) {
			set_transient(
				'gma_sale_notification',
				sprintf(
					/* translators: %s: Design title */
					__( "New sale: '%s' shirt sold!", 'gunmerch-ai' ),
					esc_html( $design->post_title )
				),
				HOUR_IN_SECONDS
			);
		}
	}

	/**
	 * Make API request to Printful.
	 *
	 * @since 1.0.0
	 * @param string $endpoint API endpoint.
	 * @param string $method   HTTP method.
	 * @param array  $body     Request body.
	 * @param array  $params   Query parameters.
	 * @return array|WP_Error Response data or error.
	 */
	private function api_request( $endpoint, $method = 'GET', $body = array(), $params = array() ) {
		$logger = $this->get_logger();
		$url = $this->api_base . $endpoint;

		// Add store_id if configured.
		if ( ! empty( $this->store_id ) ) {
			$params['store_id'] = $this->store_id;
		}

		if ( ! empty( $params ) ) {
			$url = add_query_arg( $params, $url );
		}

		$args = array(
			'method'  => strtoupper( $method ),
			'timeout' => 60,
			'headers' => array(
				'Authorization' => 'Bearer ' . sanitize_text_field( $this->api_key ),
				'Content-Type'  => 'application/json',
			),
		);

		if ( ! empty( $body ) && in_array( strtoupper( $method ), array( 'POST', 'PUT', 'PATCH' ), true ) ) {
			$args['body'] = wp_json_encode( $body );
		}
		
		if ( $logger ) {
			$logger->log( 'debug', 'Printful API Request: ' . $method . ' ' . $endpoint . ' | Body: ' . wp_json_encode( $body ) );
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			if ( $logger ) $logger->log( 'error', 'Printful API Error: ' . $response->get_error_message() );
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = json_decode( wp_remote_retrieve_body( $response ), true );
		
		if ( $logger ) {
			$logger->log( 'debug', 'Printful API Response: HTTP ' . $response_code . ' | Body: ' . wp_json_encode( $response_body ) );
		}

		if ( $response_code < 200 || $response_code >= 300 ) {
			$error_message = isset( $response_body['error']['message'] )
				? $response_body['error']['message']
				: __( 'Unknown API error', 'gunmerch-ai' );
			
			if ( $logger ) $logger->log( 'error', 'Printful API Error ' . $response_code . ': ' . $error_message );

			return new WP_Error( 'api_error', $error_message, array( 'status' => $response_code ) );
		}

		return $response_body;
	}

	/**
	 * Check if API is configured.
	 *
	 * @since 1.0.0
	 * @return bool True if configured.
	 */
	public function is_configured() {
		// Only need API key for API platform stores (store_id optional).
		return ! empty( $this->api_key );
	}

	/**
	 * Check if this is an API platform store (can create products).
	 *
	 * @since 1.0.2
	 * @return bool|WP_Error True if API store, WP_Error if ecommerce platform.
	 */
	public function is_api_platform_store() {
		if ( ! $this->is_configured() ) {
			return new WP_Error( 'not_configured', __( 'Printful not configured', 'gunmerch-ai' ) );
		}

		// Try to get store info - API platform stores return full data.
		$response = $this->api_request( '/store' );

		if ( is_wp_error( $response ) ) {
			// Check if error message indicates ecommerce platform.
			$error_msg = $response->get_error_message();
			if ( strpos( $error_msg, 'Manual Order' ) !== false || strpos( $error_msg, 'ecommerce platform' ) !== false ) {
				return false; // This is an ecommerce platform store.
			}
			return $response;
		}

		// Store info returned - this is an API platform store.
		return true;
	}

	/**
	 * Upload a file to Printful file library.
	 *
	 * @since 1.0.2
	 * @param string $image_url URL of the image to upload.
	 * @param string $filename  Name for the file.
	 * @return int|WP_Error File ID or error.
	 */
	private function upload_file_to_library( $image_url, $filename ) {
		error_log('GMA Printful: Using URL for file: ' . $image_url);

		// Printful accepts a URL to download the file directly.
		$file_data = array(
			'url' => $image_url,
		);
		
		error_log('GMA Printful: Uploading to Printful file library via URL...');

		$response = $this->api_request( '/files', 'POST', $file_data );

		if ( is_wp_error( $response ) ) {
			error_log('GMA Printful: Upload API error: ' . $response->get_error_message());
			return $response;
		}
		
		error_log('GMA Printful: Upload response: ' . wp_json_encode($response));

		if ( ! isset( $response['result']['id'] ) ) {
			error_log('GMA Printful: No file ID in response');
			return new WP_Error( 'upload_failed', __( 'Failed to upload file to Printful', 'gunmerch-ai' ) );
		}
		
		error_log('GMA Printful: File uploaded successfully, ID: ' . $response['result']['id']);

		return intval( $response['result']['id'] );
	}

	/**
	 * Get file URL from Printful file library.
	 *
	 * @since 1.0.2
	 * @param int $file_id Printful file ID.
	 * @return string|WP_Error File URL or error.
	 */
	private function get_file_url( $file_id ) {
		$response = $this->api_request( '/files/' . intval( $file_id ) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( ! isset( $response['result']['url'] ) ) {
			return new WP_Error( 'no_url', __( 'File URL not available', 'gunmerch-ai' ) );
		}

		return $response['result']['url'];
	}

	/**
	 * Get variants from the template product.
	 *
	 * @since 1.0.2
	 * @param int $design_id Design ID to use for unique external_ids.
	 * @return array|WP_Error Array of variant data or error.
	 */
	public function get_template_variants( $design_id = 0 ) {
		$logger = $this->get_logger();

		// Find template product in store.
		$response = $this->api_request( '/store/products', 'GET', array(), array( 'limit' => 100 ) );

		if ( is_wp_error( $response ) ) {
			if ( $logger ) $logger->log( 'error', 'Printful: Failed to get products - ' . $response->get_error_message() );
			return $response;
		}

		if ( empty( $response['result'] ) || ! is_array( $response['result'] ) ) {
			return new WP_Error( 'no_products', __( 'No products found in store', 'gunmerch-ai' ) );
		}

		// Find template product.
		$template_id = null;
		foreach ( $response['result'] as $product ) {
			if ( isset( $product['name'] ) && strtolower( $product['name'] ) === 'template' ) {
				$template_id = $product['id'];
				break;
			}
		}

		if ( ! $template_id ) {
			return new WP_Error( 'template_not_found', __( 'Template product not found in store', 'gunmerch-ai' ) );
		}

		if ( $logger ) $logger->log( 'info', 'Printful: Found template product ID: ' . $template_id );

		// Get template product details with variants.
		$product_response = $this->api_request( '/store/products/' . intval( $template_id ) );

		if ( is_wp_error( $product_response ) ) {
			return $product_response;
		}

		if ( empty( $product_response['result']['sync_variants'] ) ) {
			return new WP_Error( 'no_variants', __( 'Template has no variants', 'gunmerch-ai' ) );
		}

		// Extract variant data needed for new product - use hardcoded placement based on template's print area.
		$variants = array();

		// Get placement settings (top position controls vertical placement, lower = higher on shirt).
		$settings = get_option( 'gma_settings', array() );
		$print_top = isset( $settings['print_top_position'] ) ? absint( $settings['print_top_position'] ) : 100;

		// Standard placement for 3000x3000px designs on Gildan 64000.
		// top: 0 = very top of chest, 300 = center, 600 = lower chest
		$standard_placement = array(
			'area_width'  => 1800,
			'area_height' => 2400,
			'width'       => 1200,
			'height'      => 1200,
			'top'         => $print_top,
			'left'        => 300,
		);

		foreach ( $product_response['result']['sync_variants'] as $index => $variant ) {
			// Build variant data with unique external_id based on design ID.
			$external_id = $design_id ? $design_id . '-v' . $index : 'v' . $index;
			$variants[] = array(
				'variant_id'   => $variant['variant_id'],
				'external_id'  => $external_id,
				'retail_price' => $variant['retail_price'],
				'files'        => array(
					array(
						'type'     => 'front',
						'position' => $standard_placement,
					),
				),
			);
		}

		if ( $logger ) $logger->log( 'info', 'Printful: Found ' . count( $variants ) . ' template variants' );

		return $variants;
	}
}