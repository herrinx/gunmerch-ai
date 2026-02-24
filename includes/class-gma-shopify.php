<?php
/**
 * Shopify API integration class.
 *
 * @package GunMerch_AI
 * @since 1.0.2
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GMA_Shopify
 *
 * Handles Shopify API integration for product creation.
 * Used when Printful store is connected to Shopify (ecommerce platform mode).
 *
 * @since 1.0.2
 */
class GMA_Shopify {

	/**
	 * Shopify store URL.
	 *
	 * @since 1.0.2
	 * @var string
	 */
	private $store_url;

	/**
	 * Shopify API access token.
	 *
	 * @since 1.0.2
	 * @var string
	 */
	private $access_token;

	/**
	 * API version.
	 *
	 * @since 1.0.2
	 * @var string
	 */
	private $api_version = '2024-01';

	/**
	 * Logger instance.
	 *
	 * @since 1.0.2
	 * @var GMA_Logger|null
	 */
	private $logger;

	/**
	 * Constructor.
	 *
	 * @since 1.0.2
	 */
	public function __construct() {
		$this->store_url    = get_option( 'gma_shopify_store_url', '' );
		$this->access_token = get_option( 'gma_shopify_access_token', '' );
		$this->logger       = null;
	}

	/**
	 * Get logger instance lazily.
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
	 * Check if Shopify is configured.
	 *
	 * @since 1.0.2
	 * @return bool
	 */
	public function is_configured() {
		return ! empty( $this->store_url ) && ! empty( $this->access_token );
	}

	/**
	 * Test API connection.
	 *
	 * @since 1.0.2
	 * @return array|WP_Error
	 */
	public function test_connection() {
		if ( ! $this->is_configured() ) {
			return new WP_Error( 'not_configured', __( 'Shopify not configured', 'gunmerch-ai' ) );
		}

		$response = $this->api_request( 'shop.json' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return array(
			'success'    => true,
			'shop_name'  => isset( $response['shop']['name'] ) ? $response['shop']['name'] : '',
			'shop_email' => isset( $response['shop']['email'] ) ? $response['shop']['email'] : '',
		);
	}

	/**
	 * Create product in Shopify.
	 *
	 * @since 1.0.2
	 * @param int $design_id Design ID.
	 * @return array|WP_Error Product data or error.
	 */
	public function create_product( $design_id ) {
		$core   = gunmerch_ai()->get_class( 'core' );
		$design = $core ? $core->get_design( $design_id ) : null;

		if ( ! $design ) {
			return new WP_Error( 'design_not_found', __( 'Design not found', 'gunmerch-ai' ) );
		}

		if ( ! $this->is_configured() ) {
			return new WP_Error( 'not_configured', __( 'Shopify not configured', 'gunmerch-ai' ) );
		}

		$design_text = $core->get_design_meta( $design_id, 'design_text' );
		$margin      = $core->get_design_meta( $design_id, 'estimated_margin' );
		$margin      = $margin ? intval( $margin ) : 40;

		// Calculate price with margin (base cost ~$15).
		$base_cost    = 15.00;
		$retail_price = round( $base_cost * ( 1 + $margin / 100 ), 2 );

		// Get mockup image if exists.
		$image_url = '';
		if ( has_post_thumbnail( $design_id ) ) {
			$image_url = get_the_post_thumbnail_url( $design_id, 'full' );
		}

		// Build product data for Shopify.
		$product_data = array(
			'product' => array(
				'title'        => $design->post_title,
				'body_html'    => '<p>' . esc_html( $design->post_content ) . '</p>',
				'product_type' => 'T-Shirt',
				'tags'         => 'gunmerch-ai, ' . sanitize_title( $core->get_design_meta( $design_id, 'trend_topic' ) ),
				'variants'     => array(
					array(
						'option1'      => 'Black',
						'price'        => number_format( $retail_price, 2 ),
						'sku'          => 'GMA-' . $design_id . '-BLK',
						'inventory_quantity' => 100,
						'inventory_management' => null, // Printful manages inventory.
						'fulfillment_service'  => 'manual', // Will be changed by Printful sync.
					),
					array(
						'option1'      => 'Navy',
						'price'        => number_format( $retail_price, 2 ),
						'sku'          => 'GMA-' . $design_id . '-NVY',
						'inventory_quantity' => 100,
						'inventory_management' => null,
						'fulfillment_service'  => 'manual',
					),
					array(
						'option1'      => 'Heather Grey',
						'price'        => number_format( $retail_price, 2 ),
						'sku'          => 'GMA-' . $design_id . '-GRY',
						'inventory_quantity' => 100,
						'inventory_management' => null,
						'fulfillment_service'  => 'manual',
					),
				),
				'options'      => array(
					array(
						'name'   => 'Color',
						'values' => array( 'Black', 'Navy', 'Heather Grey' ),
					),
				),
				'images'       => array(),
				'published'    => true,
				'published_scope' => 'global',
			),
		);

		// Add image if available.
		if ( $image_url ) {
			$product_data['product']['images'][] = array(
				'src' => $image_url,
				'alt' => $design->post_title,
			);
		}

		// Create product.
		$response = $this->api_request( 'products.json', 'POST', $product_data );

		if ( is_wp_error( $response ) ) {
			$logger = $this->get_logger();
			if ( $logger ) {
				$logger->log(
					'error',
					__( 'Failed to create Shopify product: ', 'gunmerch-ai' ) . $response->get_error_message(),
					$design_id
				);
			}
			return $response;
		}

		$shopify_product_id = isset( $response['product']['id'] ) ? $response['product']['id'] : 0;

		// Store Shopify product ID.
		if ( $shopify_product_id ) {
			update_post_meta( $design_id, '_gma_shopify_product_id', $shopify_product_id );
			update_post_meta( $design_id, '_gma_printful_product_id', $shopify_product_id ); // For compatibility.

			$logger = $this->get_logger();
			if ( $logger ) {
				$logger->log(
					'info',
					sprintf(
						/* translators: %s: Shopify product ID */
						__( 'Product created in Shopify: ID %s', 'gunmerch-ai' ),
						$shopify_product_id
					),
					$design_id
				);
			}
		}

		return array(
			'success'    => true,
			'product_id' => $shopify_product_id,
			'shopify_id' => $shopify_product_id,
		);
	}

	/**
	 * Make Shopify API request.
	 *
	 * @since 1.0.2
	 * @param string $endpoint API endpoint.
	 * @param string $method   HTTP method.
	 * @param array  $body     Request body.
	 * @return array|WP_Error Response or error.
	 */
	private function api_request( $endpoint, $method = 'GET', $body = array() ) {
		$url = 'https://' . $this->store_url . '/admin/api/' . $this->api_version . '/' . $endpoint;

		$args = array(
			'method'  => strtoupper( $method ),
			'timeout' => 60,
			'headers' => array(
				'X-Shopify-Access-Token' => $this->access_token,
				'Content-Type'          => 'application/json',
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
			$error_message = isset( $response_body['errors'] )
				? wp_json_encode( $response_body['errors'] )
				: __( 'Unknown API error', 'gunmerch-ai' );

			return new WP_Error( 'api_error', $error_message, array( 'status' => $response_code ) );
		}

		return $response_body;
	}
}
