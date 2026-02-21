<?php
/**
 * Core plugin functionality.
 *
 * @package GunMerch_AI
 * @since 1.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GMA_Core
 *
 * Handles core plugin functionality and utilities.
 *
 * @since 1.0.0
 */
class GMA_Core {

	/**
	 * Design statuses.
	 *
	 * @since 1.0.0
	 * @var array
	 */
	private $design_statuses;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->design_statuses = array(
			'pending'   => __( 'Pending Review', 'gunmerch-ai' ),
			'approved'  => __( 'Approved', 'gunmerch-ai' ),
			'rejected'  => __( 'Rejected', 'gunmerch-ai' ),
			'live'      => __( 'Live', 'gunmerch-ai' ),
			'sold'      => __( 'Sold', 'gunmerch-ai' ),
		);

		$this->register_hooks();
	}

	/**
	 * Register WordPress hooks.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function register_hooks() {
		add_action( 'gma_scan_trends', array( $this, 'run_trend_scan' ) );
		add_action( 'gma_generate_designs', array( $this, 'run_design_generation' ) );
		add_action( 'gma_sync_sales', array( $this, 'run_sales_sync' ) );
		add_filter( 'manage_gma_design_posts_columns', array( $this, 'add_design_columns' ) );
		add_action( 'manage_gma_design_posts_custom_column', array( $this, 'render_design_columns' ), 10, 2 );
	}

	/**
	 * Get design statuses.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	public function get_design_statuses() {
		return $this->design_statuses;
	}

	/**
	 * Get design status label.
	 *
	 * @since 1.0.0
	 * @param string $status Status key.
	 * @return string Status label.
	 */
	public function get_design_status_label( $status ) {
		return isset( $this->design_statuses[ $status ] )
			? $this->design_statuses[ $status ]
			: __( 'Unknown', 'gunmerch-ai' );
	}

	/**
	 * Update design status.
	 *
	 * @since 1.0.0
	 * @param int    $design_id Design ID.
	 * @param string $status    New status.
	 * @param array  $meta      Additional meta to update.
	 * @return bool True on success, false on failure.
	 */
	public function update_design_status( $design_id, $status, $meta = array() ) {
		$design_id = absint( $design_id );
		$status    = sanitize_key( $status );

		if ( ! array_key_exists( $status, $this->design_statuses ) ) {
			return false;
		}

		// Update post status.
		$result = wp_update_post(
			array(
				'ID'          => $design_id,
				'post_status' => $status,
			),
			true
		);

		if ( is_wp_error( $result ) ) {
			return false;
		}

		// Update meta.
		update_post_meta( $design_id, '_gma_status', $status );
		update_post_meta( $design_id, '_gma_status_updated', current_time( 'mysql' ) );

		if ( ! empty( $meta ) && is_array( $meta ) ) {
			foreach ( $meta as $key => $value ) {
				update_post_meta( $design_id, '_gma_' . sanitize_key( $key ), $value );
			}
		}

		// Update stats.
		$this->update_stats( $status );

		// Log the action.
		$logger = gunmerch_ai()->get_class( 'logger' );
		if ( $logger ) {
			$logger->log_design_action( $design_id, $status, 0, $meta );
		}

		do_action( 'gma_design_status_changed', $design_id, $status, $meta );

		return true;
	}

	/**
	 * Create a new design.
	 *
	 * @since 1.0.0
	 * @param array $data Design data.
	 * @return int|false Design ID or false on failure.
	 */
	public function create_design( $data ) {
		$defaults = array(
			'title'            => '',
			'concept'          => '',
			'design_text'      => '',
			'design_type'      => 'text',
			'trend_topic'      => '',
			'trend_source'     => '',
			'estimated_margin' => 40,
			'mockup_url'       => '',
		);

		$data = wp_parse_args( $data, $defaults );

		// Create the post.
		$post_data = array(
			'post_title'   => sanitize_text_field( $data['title'] ),
			'post_content' => sanitize_textarea_field( $data['concept'] ),
			'post_status'  => 'pending',
			'post_type'    => 'gma_design',
		);

		$design_id = wp_insert_post( $post_data, true );

		if ( is_wp_error( $design_id ) ) {
			return false;
		}

		// Store design metadata.
		update_post_meta( $design_id, '_gma_design_text', sanitize_text_field( $data['design_text'] ) );
		update_post_meta( $design_id, '_gma_design_type', sanitize_key( $data['design_type'] ) );
		update_post_meta( $design_id, '_gma_trend_topic', sanitize_text_field( $data['trend_topic'] ) );
		update_post_meta( $design_id, '_gma_trend_source', esc_url_raw( $data['trend_source'] ) );
		update_post_meta( $design_id, '_gma_estimated_margin', floatval( $data['estimated_margin'] ) );
		update_post_meta( $design_id, '_gma_mockup_url', esc_url_raw( $data['mockup_url'] ) );
		update_post_meta( $design_id, '_gma_status', 'pending' );
		update_post_meta( $design_id, '_gma_created_at', current_time( 'mysql' ) );
		update_post_meta( $design_id, '_gma_printful_product_id', '' );

		// Log the creation.
		$logger = gunmerch_ai()->get_class( 'logger' );
		if ( $logger ) {
			$logger->log_design_action( $design_id, 'created' );
		}

		// Update stats.
		$this->update_stats( 'generated' );

		do_action( 'gma_design_created', $design_id, $data );

		return $design_id;
	}

	/**
	 * Get designs.
	 *
	 * @since 1.0.0
	 * @param array $args Query arguments.
	 * @return array Array of design posts.
	 */
	public function get_designs( $args = array() ) {
		$defaults = array(
			'post_type'      => 'gma_design',
			'posts_per_page' => 20,
			'post_status'    => 'any',
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		$args = wp_parse_args( $args, $defaults );

		// Filter by status meta.
		if ( ! empty( $args['design_status'] ) ) {
			$args['meta_query'] = array(
				array(
					'key'   => '_gma_status',
					'value' => sanitize_key( $args['design_status'] ),
				),
			);
		}

		$query = new WP_Query( $args );

		return $query->posts;
	}

	/**
	 * Get single design.
	 *
	 * @since 1.0.0
	 * @param int $design_id Design ID.
	 * @return WP_Post|null Design post or null.
	 */
	public function get_design( $design_id ) {
		$design = get_post( $design_id );
		return ( $design && 'gma_design' === $design->post_type ) ? $design : null;
	}

	/**
	 * Get design meta.
	 *
	 * @since 1.0.0
	 * @param int    $design_id Design ID.
	 * @param string $key       Meta key (without prefix).
	 * @param bool   $single    Whether to return single value.
	 * @return mixed Meta value(s).
	 */
	public function get_design_meta( $design_id, $key, $single = true ) {
		return get_post_meta( $design_id, '_gma_' . sanitize_key( $key ), $single );
	}

	/**
	 * Run trend scan (cron handler).
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function run_trend_scan() {
		$trends = gunmerch_ai()->get_class( 'trends' );
		if ( $trends ) {
			$results = $trends->scan_all_sources();

			$logger = gunmerch_ai()->get_class( 'logger' );
			if ( $logger ) {
				$logger->log(
					'info',
					sprintf(
						/* translators: %d: Number of trends found */
						__( 'Trend scan completed: %d trends found', 'gunmerch-ai' ),
						count( $results )
					)
				);
			}
		}
	}

	/**
	 * Run design generation (cron handler).
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function run_design_generation() {
		$designer = gunmerch_ai()->get_class( 'designer' );
		if ( $designer ) {
			$settings      = get_option( 'gma_settings', array() );
			$designs_count = isset( $settings['designs_per_scan'] ) ? absint( $settings['designs_per_scan'] ) : 10;

			$results = $designer->generate_designs( $designs_count );

			$logger = gunmerch_ai()->get_class( 'logger' );
			if ( $logger ) {
				$logger->log(
					'info',
					sprintf(
						/* translators: %d: Number of designs generated */
						__( 'Design generation completed: %d designs created', 'gunmerch-ai' ),
						count( $results )
					)
				);
			}
		}
	}

	/**
	 * Run sales sync (cron handler).
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function run_sales_sync() {
		$printfull = gunmerch_ai()->get_class( 'printfull' );
		if ( $printfull ) {
			$results = $printfull->sync_sales();

			$logger = gunmerch_ai()->get_class( 'logger' );
			if ( $logger ) {
				$logger->log(
					'info',
					sprintf(
						/* translators: %d: Number of sales synced */
						__( 'Sales sync completed: %d orders synced', 'gunmerch-ai' ),
						count( $results )
					)
				);
			}
		}
	}

	/**
	 * Update plugin statistics.
	 *
	 * @since 1.0.0
	 * @param string $type Type of stat to update.
	 * @return void
	 */
	public function update_stats( $type ) {
		$stats = get_option( 'gma_stats', array() );

		switch ( $type ) {
			case 'generated':
				$stats['total_designs_generated'] = isset( $stats['total_designs_generated'] )
					? $stats['total_designs_generated'] + 1
					: 1;
				break;

			case 'approved':
				$stats['total_designs_approved'] = isset( $stats['total_designs_approved'] )
					? $stats['total_designs_approved'] + 1
					: 1;
				break;

			case 'rejected':
				$stats['total_designs_rejected'] = isset( $stats['total_designs_rejected'] )
					? $stats['total_designs_rejected'] + 1
					: 1;
				break;

			case 'sale':
				$stats['total_sales'] = isset( $stats['total_sales'] )
					? $stats['total_sales'] + 1
					: 1;
				break;
		}

		update_option( 'gma_stats', $stats );
	}

	/**
	 * Get plugin statistics.
	 *
	 * @since 1.0.0
	 * @return array Statistics array.
	 */
	public function get_stats() {
		$stats = get_option( 'gma_stats', array() );

		// Ensure all keys exist.
		$defaults = array(
			'total_designs_generated' => 0,
			'total_designs_approved'  => 0,
			'total_designs_rejected'  => 0,
			'total_sales'             => 0,
			'total_revenue'           => 0.00,
		);

		return wp_parse_args( $stats, $defaults );
	}

	/**
	 * Add custom columns to designs list.
	 *
	 * @since 1.0.0
	 * @param array $columns Existing columns.
	 * @return array Modified columns.
	 */
	public function add_design_columns( $columns ) {
		$new_columns = array();

		foreach ( $columns as $key => $value ) {
			$new_columns[ $key ] = $value;

			if ( 'title' === $key ) {
				$new_columns['gma_status']   = __( 'Status', 'gunmerch-ai' );
				$new_columns['gma_type']     = __( 'Type', 'gunmerch-ai' );
				$new_columns['gma_margin']   = __( 'Margin %', 'gunmerch-ai' );
				$new_columns['gma_trend']    = __( 'Trend Topic', 'gunmerch-ai' );
			}
		}

		return $new_columns;
	}

	/**
	 * Render custom column content.
	 *
	 * @since 1.0.0
	 * @param string $column  Column name.
	 * @param int    $post_id Post ID.
	 * @return void
	 */
	public function render_design_columns( $column, $post_id ) {
		switch ( $column ) {
			case 'gma_status':
				$status = $this->get_design_meta( $post_id, 'status' );
				$label  = $this->get_design_status_label( $status );
				echo '<span class="gma-status gma-status-' . esc_attr( $status ) . '">' . esc_html( $label ) . '</span>';
				break;

			case 'gma_type':
				$type = $this->get_design_meta( $post_id, 'design_type' );
				echo esc_html( ucfirst( $type ) );
				break;

			case 'gma_margin':
				$margin = $this->get_design_meta( $post_id, 'estimated_margin' );
				echo esc_html( $margin ) . '%';
				break;

			case 'gma_trend':
				$trend = $this->get_design_meta( $post_id, 'trend_topic' );
				echo esc_html( $trend );
				break;
		}
	}
}