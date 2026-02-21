<?php
/**
 * AJAX handlers class.
 *
 * @package GunMerch_AI
 * @since 1.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GMA_AJAX
 *
 * Handles all AJAX requests for the plugin.
 *
 * @since 1.0.0
 */
class GMA_AJAX {

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->register_hooks();
	}

	/**
	 * Register AJAX hooks.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function register_hooks() {
		// Design actions.
		add_action( 'wp_ajax_gma_approve_design', array( $this, 'ajax_approve_design' ) );
		add_action( 'wp_ajax_gma_reject_design', array( $this, 'ajax_reject_design' ) );
		add_action( 'wp_ajax_gma_bulk_approve', array( $this, 'ajax_bulk_approve' ) );
		add_action( 'wp_ajax_gma_bulk_reject', array( $this, 'ajax_bulk_reject' ) );

		// Utility actions.
		add_action( 'wp_ajax_gma_scan_trends', array( $this, 'ajax_scan_trends' ) );
		add_action( 'wp_ajax_gma_generate_designs', array( $this, 'ajax_generate_designs' ) );
		add_action( 'wp_ajax_gma_test_printful', array( $this, 'ajax_test_printful' ) );
		add_action( 'wp_ajax_gma_clear_logs', array( $this, 'ajax_clear_logs' ) );
		add_action( 'wp_ajax_gma_dismiss_notice', array( $this, 'ajax_dismiss_notice' ) );

		// Fetch notifications.
		add_action( 'wp_ajax_gma_get_notifications', array( $this, 'ajax_get_notifications' ) );
	}

	/**
	 * Verify AJAX request.
	 *
	 * @since 1.0.0
	 * @param string $capability Required capability.
	 * @return bool True if verified.
	 */
	private function verify_request( $capability = 'gma_manage_designs' ) {
		// Check nonce.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'gma_admin_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'gunmerch-ai' ) ) );
			return false;
		}

		// Check capability.
		if ( ! current_user_can( $capability ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'gunmerch-ai' ) ) );
			return false;
		}

		return true;
	}

	/**
	 * AJAX: Approve design.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_approve_design() {
		if ( ! $this->verify_request( 'gma_approve_designs' ) ) {
			return;
		}

		$design_id = isset( $_POST['design_id'] ) ? absint( wp_unslash( $_POST['design_id'] ) ) : 0;

		if ( ! $design_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid design ID.', 'gunmerch-ai' ) ) );
			return;
		}

		$core = gunmerch_ai()->get_class( 'core' );

		if ( ! $core ) {
			wp_send_json_error( array( 'message' => __( 'Core functionality not available.', 'gunmerch-ai' ) ) );
			return;
		}

		$result = $core->update_design_status( $design_id, 'approved' );

		if ( $result ) {
			$design = $core->get_design( $design_id );

			// Check auto-publish setting.
			$settings = get_option( 'gma_settings', array() );
			$message  = __( 'Design approved successfully!', 'gunmerch-ai' );

			if ( ! empty( $settings['auto_publish_to_printful'] ) ) {
				$printfull = gunmerch_ai()->get_class( 'printfull' );
				if ( $printfull ) {
					$pub_result = $printfull->create_product( $design_id );
					if ( ! is_wp_error( $pub_result ) ) {
						$message = sprintf(
							/* translators: %s: Design title */
							__( "Design '%s' approved and sent to Printful", 'gunmerch-ai' ),
							esc_html( $design->post_title )
						);
					}
				}
			}

			wp_send_json_success(
				array(
					'message'    => $message,
					'design_id'  => $design_id,
					'design_title' => $design ? $design->post_title : '',
				)
			);
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to approve design.', 'gunmerch-ai' ) ) );
		}
	}

	/**
	 * AJAX: Reject design.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_reject_design() {
		if ( ! $this->verify_request( 'gma_approve_designs' ) ) {
			return;
		}

		$design_id = isset( $_POST['design_id'] ) ? absint( wp_unslash( $_POST['design_id'] ) ) : 0;

		if ( ! $design_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid design ID.', 'gunmerch-ai' ) ) );
			return;
		}

		$core = gunmerch_ai()->get_class( 'core' );

		if ( ! $core ) {
			wp_send_json_error( array( 'message' => __( 'Core functionality not available.', 'gunmerch-ai' ) ) );
			return;
		}

		$result = $core->update_design_status( $design_id, 'rejected' );

		if ( $result ) {
			wp_send_json_success(
				array(
					'message'   => __( 'Design rejected.', 'gunmerch-ai' ),
					'design_id' => $design_id,
				)
			);
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to reject design.', 'gunmerch-ai' ) ) );
		}
	}

	/**
	 * AJAX: Bulk approve designs.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_bulk_approve() {
		if ( ! $this->verify_request( 'gma_approve_designs' ) ) {
			return;
		}

		$design_ids = isset( $_POST['design_ids'] ) ? array_map( 'intval', wp_unslash( $_POST['design_ids'] ) ) : array();

		if ( empty( $design_ids ) ) {
			wp_send_json_error( array( 'message' => __( 'No designs selected.', 'gunmerch-ai' ) ) );
			return;
		}

		$core = gunmerch_ai()->get_class( 'core' );

		if ( ! $core ) {
			wp_send_json_error( array( 'message' => __( 'Core functionality not available.', 'gunmerch-ai' ) ) );
			return;
		}

		$approved = 0;
		$failed   = 0;

		foreach ( $design_ids as $design_id ) {
			$result = $core->update_design_status( $design_id, 'approved' );
			if ( $result ) {
				++$approved;
			} else {
				++$failed;
			}
		}

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: 1: Approved count, 2: Failed count */
					__( '%1$d designs approved. %2$d failed.', 'gunmerch-ai' ),
					$approved,
					$failed
				),
				'approved' => $approved,
				'failed'   => $failed,
			)
		);
	}

	/**
	 * AJAX: Bulk reject designs.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_bulk_reject() {
		if ( ! $this->verify_request( 'gma_approve_designs' ) ) {
			return;
		}

		$design_ids = isset( $_POST['design_ids'] ) ? array_map( 'intval', wp_unslash( $_POST['design_ids'] ) ) : array();

		if ( empty( $design_ids ) ) {
			wp_send_json_error( array( 'message' => __( 'No designs selected.', 'gunmerch-ai' ) ) );
			return;
		}

		$core = gunmerch_ai()->get_class( 'core' );

		if ( ! $core ) {
			wp_send_json_error( array( 'message' => __( 'Core functionality not available.', 'gunmerch-ai' ) ) );
			return;
		}

		$rejected = 0;
		$failed   = 0;

		foreach ( $design_ids as $design_id ) {
			$result = $core->update_design_status( $design_id, 'rejected' );
			if ( $result ) {
				++$rejected;
			} else {
				++$failed;
			}
		}

		wp_send_json_success(
			array(
				'message'  => sprintf(
					/* translators: 1: Rejected count, 2: Failed count */
					__( '%1$d designs rejected. %2$d failed.', 'gunmerch-ai' ),
					$rejected,
					$failed
				),
				'rejected' => $rejected,
				'failed'   => $failed,
			)
		);
	}

	/**
	 * AJAX: Manual trend scan.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_scan_trends() {
		if ( ! $this->verify_request( 'gma_manage_designs' ) ) {
			return;
		}

		$trends = gunmerch_ai()->get_class( 'trends' );

		if ( ! $trends ) {
			wp_send_json_error( array( 'message' => __( 'Trend scanner not available.', 'gunmerch-ai' ) ) );
			return;
		}

		$results = $trends->scan_all_sources();

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: %d: Number of trends found */
					__( 'Trend scan complete. Found %d trending topics.', 'gunmerch-ai' ),
					count( $results )
				),
				'count'   => count( $results ),
			)
		);
	}

	/**
	 * AJAX: Manual design generation.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_generate_designs() {
		if ( ! $this->verify_request( 'gma_manage_designs' ) ) {
			return;
		}

		$designer = gunmerch_ai()->get_class( 'designer' );

		if ( ! $designer ) {
			wp_send_json_error( array( 'message' => __( 'Design generator not available.', 'gunmerch-ai' ) ) );
			return;
		}

		$count   = isset( $_POST['count'] ) ? absint( wp_unslash( $_POST['count'] ) ) : 5;
		$results = $designer->generate_designs( $count );

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: %d: Number of designs generated */
					__( 'Generated %d new designs.', 'gunmerch-ai' ),
					count( $results )
				),
				'count'   => count( $results ),
			)
		);
	}

	/**
	 * AJAX: Test Printful connection.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_test_printful() {
		if ( ! $this->verify_request( 'gma_manage_settings' ) ) {
			return;
		}

		$printfull = gunmerch_ai()->get_class( 'printfull' );

		if ( ! $printfull ) {
			wp_send_json_error( array( 'message' => __( 'Printful integration not available.', 'gunmerch-ai' ) ) );
			return;
		}

		$result = $printfull->test_connection();

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		} else {
			wp_send_json_success(
				array(
					'message'     => sprintf(
						/* translators: %s: Store name */
						__( 'Connected to Printful store: %s', 'gunmerch-ai' ),
						esc_html( $result['store_name'] )
					),
					'store_name'  => $result['store_name'],
				)
			);
		}
	}

	/**
	 * AJAX: Clear logs.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_clear_logs() {
		if ( ! $this->verify_request( 'gma_view_logs' ) ) {
			return;
		}

		$logger = gunmerch_ai()->get_class( 'logger' );

		if ( ! $logger ) {
			wp_send_json_error( array( 'message' => __( 'Logger not available.', 'gunmerch-ai' ) ) );
			return;
		}

		$deleted = $logger->delete_all_logs();

		if ( false !== $deleted ) {
			wp_send_json_success( array( 'message' => __( 'All logs cleared.', 'gunmerch-ai' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to clear logs.', 'gunmerch-ai' ) ) );
		}
	}

	/**
	 * AJAX: Dismiss notice.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_dismiss_notice() {
		check_ajax_referer( 'gma_admin_nonce', 'nonce' );

		$notice_key = isset( $_POST['notice_key'] ) ? sanitize_key( wp_unslash( $_POST['notice_key'] ) ) : '';

		if ( $notice_key ) {
			delete_transient( $notice_key );
			wp_send_json_success();
		}

		wp_send_json_error();
	}

	/**
	 * AJAX: Get notifications.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_get_notifications() {
		check_ajax_referer( 'gma_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'gma_manage_designs' ) ) {
			wp_send_json_error();
			return;
		}

		$notifications = array();

		// Check for various notification transients.
		$notification_keys = array(
			'gma_new_designs_notification',
			'gma_design_published_notification',
			'gma_sale_notification',
		);

		foreach ( $notification_keys as $key ) {
			$message = get_transient( $key );
			if ( $message ) {
				$notifications[] = array(
					'type' => $this->get_notification_type( $key ),
					'text' => $message,
					'key'  => $key,
				);
			}
		}

		wp_send_json_success( array( 'notifications' => $notifications ) );
	}

	/**
	 * Get notification type from key.
	 *
	 * @since 1.0.0
	 * @param string $key Notification transient key.
	 * @return string Notification type.
	 */
	private function get_notification_type( $key ) {
		if ( strpos( $key, 'sale' ) !== false ) {
			return 'success';
		}
		if ( strpos( $key, 'error' ) !== false ) {
			return 'error';
		}
		if ( strpos( $key, 'published' ) !== false ) {
			return 'success';
		}
		return 'info';
	}
}