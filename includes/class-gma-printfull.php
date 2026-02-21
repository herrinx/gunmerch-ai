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
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->api_key = get_option( 'gma_printful_api_key', '' );
		$this->logger  = gunmerch_ai()->get_class( 'logger' );

		$this->register_hooks();
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
		$core   = gunmerch_ai()->get_class( 'core' );
		$design = $core ? $core->get_design( $design_id ) : null;

		if ( ! $design ) {
			return new WP_Error( 'design_not_found', __( 'Design not found', 'gunmerch-ai' ) );
		}

		if ( empty( $this->api_key ) ) {
			return new WP_Error( 'no_api_key', __( 'Printful API key not configured', 'gunmerch-ai' ) );
		}

		$design_text = $core->get_design_meta( $design_id, 'design_text' );

		// Prepare product data.
		$product_data = array(
			'name'         => $design->post_title,
			'sync_product' => array(
				'name'        => $design->post_title,
				'thumbnail'   => '', // Would be mockup image.
				'external_id' => (string) $design_id,
			),
			'sync_variants' => array(
				array(
					'variant_id'    => 4012, // Bella + Canvas 3001 - Black
					'external_id'   => "{$design_id}-black",
					'files'         => array(
						array(
							'url'    => '', // Design file URL would go here.
							'type'   => 'front',
							'opts'   => array(
								'position' => array(
									'area_width'  => 1800,
									'area_height' => 2400,
									'width'       => 1200,
									'height'      => 800,
									'top'         => 400,
									'left'        => 300,
								),
							),
						),
					),
					'retail_price'  => '24.99',
				),
			),
		);

		$response = $this->api_request( '/store/products', 'POST', $product_data );

		$success = ! is_wp_error( $response ) && isset( $response['result']['id'] );

		// Log the API call.
		if ( $this->logger ) {
			$this->logger->log_api_call(
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

		if ( is_wp_error( $response ) ) {
			if ( $this->logger ) {
				$this->logger->log(
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

		if ( $this->logger ) {
			$this->logger->log(
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
		if ( $this->logger ) {
			$this->logger->log(
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
		$url = $this->api_base . $endpoint;

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

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $response_code < 200 || $response_code >= 300 ) {
			$error_message = isset( $response_body['error']['message'] )
				? $response_body['error']['message']
				: __( 'Unknown API error', 'gunmerch-ai' );

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
		return ! empty( $this->api_key );
	}
}