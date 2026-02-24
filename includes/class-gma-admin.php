<?php
/**
 * Admin dashboard class.
 *
 * @package GunMerch_AI
 * @since 1.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GMA_Admin
 *
 * Handles admin dashboard pages and functionality.
 *
 * @since 1.0.0
 */
class GMA_Admin {

	/**
	 * Admin page hooks.
	 *
	 * @since 1.0.0
	 * @var array
	 */
	private $page_hooks = array();

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->register_hooks();
	}

	/**
	 * Register WordPress hooks.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function register_hooks() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_init', array( $this, 'handle_settings' ) );
	}

	/**
	 * Add admin menu pages.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function add_admin_menu() {
		// Main menu.
		add_menu_page(
			__( 'GunMerch AI', 'gunmerch-ai' ),
			__( 'GunMerch AI', 'gunmerch-ai' ),
			'manage_options',
			'gunmerch-ai',
			array( $this, 'render_dashboard' ),
			'dashicons-art',
			30
		);

		// Dashboard submenu.
		$this->page_hooks['dashboard'] = add_submenu_page(
			'gunmerch-ai',
			__( 'Dashboard', 'gunmerch-ai' ),
			__( 'Dashboard', 'gunmerch-ai' ),
			'manage_options',
			'gunmerch-ai',
			array( $this, 'render_dashboard' )
		);

		// Review Designs submenu.
		$this->page_hooks['review'] = add_submenu_page(
			'gunmerch-ai',
			__( 'Review Designs', 'gunmerch-ai' ),
			__( 'Review Designs', 'gunmerch-ai' ),
			'manage_options',
			'gma-review',
			array( $this, 'render_review' )
		);

		// Trends submenu.
		$this->page_hooks['trends'] = add_submenu_page(
			'gunmerch-ai',
			__( 'Trends', 'gunmerch-ai' ),
			__( 'Trends', 'gunmerch-ai' ),
			'manage_options',
			'gma-trends',
			array( $this, 'render_trends' )
		);

		// Live Products submenu.
		$this->page_hooks['products'] = add_submenu_page(
			'gunmerch-ai',
			__( 'Live Products', 'gunmerch-ai' ),
			__( 'Live Products', 'gunmerch-ai' ),
			'manage_options',
			'gma-products',
			array( $this, 'render_products' )
		);

		// History submenu.
		$this->page_hooks['history'] = add_submenu_page(
			'gunmerch-ai',
			__( 'History', 'gunmerch-ai' ),
			__( 'History', 'gunmerch-ai' ),
			'manage_options',
			'gma-history',
			array( $this, 'render_history' )
		);

		// Settings submenu.
		$this->page_hooks['settings'] = add_submenu_page(
			'gunmerch-ai',
			__( 'Settings', 'gunmerch-ai' ),
			__( 'Settings', 'gunmerch-ai' ),
			'manage_options',
			'gma-settings',
			array( $this, 'render_settings' )
		);

		// Logs submenu.
		$this->page_hooks['logs'] = add_submenu_page(
			'gunmerch-ai',
			__( 'Logs', 'gunmerch-ai' ),
			__( 'Logs', 'gunmerch-ai' ),
			'manage_options',
			'gma-logs',
			array( $this, 'render_logs' )
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @since 1.0.0
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		// Only load on our pages.
		if ( ! in_array( $hook, $this->page_hooks, true ) && strpos( $hook, 'gma' ) === false ) {
			return;
		}

		// CSS.
		wp_enqueue_style(
			'gma-admin-css',
			GMA_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			GMA_VERSION
		);

		// JS.
		wp_enqueue_script(
			'gma-admin-js',
			GMA_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			GMA_VERSION,
			true
		);

		// Localize script.
		wp_localize_script(
			'gma-admin-js',
			'gma_admin',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'gma_admin_nonce' ),
				'i18n'     => array(
					'approve_confirm'  => __( 'Are you sure you want to approve this design?', 'gunmerch-ai' ),
					'reject_confirm'   => __( 'Are you sure you want to reject this design?', 'gunmerch-ai' ),
					'approve_success'  => __( 'Design approved successfully!', 'gunmerch-ai' ),
					'reject_success'   => __( 'Design rejected.', 'gunmerch-ai' ),
					'bulk_approve'     => __( 'Approve selected designs?', 'gunmerch-ai' ),
					'bulk_reject'      => __( 'Reject selected designs?', 'gunmerch-ai' ),
				),
			)
		);
	}

	/**
	 * Render dashboard page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_dashboard() {
		$core = gunmerch_ai()->get_class( 'core' );

		$stats         = $core ? $core->get_stats() : array();
		$pending_count = wp_count_posts( 'gma_design' )->pending;

		include GMA_PLUGIN_DIR . 'templates/admin-dashboard.php';
	}

	/**
	 * Render review page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_review() {
		$core = gunmerch_ai()->get_class( 'core' );

		// Get pending designs.
		$designs = $core ? $core->get_designs(
			array(
				'posts_per_page' => 20,
				'design_status'  => 'pending',
			)
		) : array();

		include GMA_PLUGIN_DIR . 'templates/admin-review.php';
	}

	/**
	 * Render trends page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_trends() {
		$trends_class = gunmerch_ai()->get_class( 'trends' );
		$trends       = $trends_class ? $trends_class->get_trends( array( 'limit' => 50 ) ) : array();
		
		// Debug: check if trends are loading.
		if ( empty( $trends ) ) {
			echo '<div class="notice notice-warning"><p>No trends found. Total in DB: ' . intval( $this->get_trend_count() ) . '</p></div>';
		}

		include GMA_PLUGIN_DIR . 'templates/admin-trends.php';
	}
	
	/**
	 * Get total trend count for debugging.
	 */
	private function get_trend_count() {
		global $wpdb;
		return $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}gma_trends" );
	}

	/**
	 * Render products page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_products() {
		$core = gunmerch_ai()->get_class( 'core' );

		$designs = $core ? $core->get_designs(
			array(
				'posts_per_page' => 20,
				'design_status'  => 'live',
			)
		) : array();

		include GMA_PLUGIN_DIR . 'templates/admin-products.php';
	}

	/**
	 * Render history page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_history() {
		$core = gunmerch_ai()->get_class( 'core' );

		$status_filter = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';

		$args = array(
			'posts_per_page' => 50,
		);

		if ( $status_filter ) {
			$args['design_status'] = $status_filter;
		}

		$designs = $core ? $core->get_designs( $args ) : array();

		include GMA_PLUGIN_DIR . 'templates/admin-history.php';
	}

	/**
	 * Render settings page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_settings() {
		$settings = get_option( 'gma_settings', array() );

		// Test Printful connection.
		$printfull  = gunmerch_ai()->get_class( 'printfull' );
		$connection = $printfull ? $printfull->test_connection() : null;

		include GMA_PLUGIN_DIR . 'templates/admin-settings.php';
	}

	/**
	 * Handle settings form submission.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function handle_settings() {
		if ( ! isset( $_POST['gma_save_settings'] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage settings.', 'gunmerch-ai' ) );
		}

		check_admin_referer( 'gma_settings', 'gma_settings_nonce' );

		// Save Printful API key.
		if ( isset( $_POST['gma_printful_api_key'] ) ) {
			update_option( 'gma_printful_api_key', sanitize_text_field( wp_unslash( $_POST['gma_printful_api_key'] ) ) );
		}

		// Save Printful Store ID.
		if ( isset( $_POST['gma_printful_store_id'] ) ) {
			update_option( 'gma_printful_store_id', sanitize_text_field( wp_unslash( $_POST['gma_printful_store_id'] ) ) );
		}

		// Save Shopify settings.
		if ( isset( $_POST['gma_shopify_store_url'] ) ) {
			$store_url = sanitize_text_field( wp_unslash( $_POST['gma_shopify_store_url'] ) );
			// Remove https:// and trailing slashes.
			$store_url = preg_replace( '#^https?://#', '', $store_url );
			$store_url = rtrim( $store_url, '/' );
			update_option( 'gma_shopify_store_url', $store_url );
		}
		if ( isset( $_POST['gma_shopify_access_token'] ) ) {
			update_option( 'gma_shopify_access_token', sanitize_text_field( wp_unslash( $_POST['gma_shopify_access_token'] ) ) );
		}

		// Save OpenAI API key.
		if ( isset( $_POST['gma_openai_api_key'] ) ) {
			update_option( 'gma_openai_api_key', sanitize_text_field( wp_unslash( $_POST['gma_openai_api_key'] ) ) );
		}

		// Save Gemini API key.
		if ( isset( $_POST['gma_gemini_api_key'] ) ) {
			update_option( 'gma_gemini_api_key', sanitize_text_field( wp_unslash( $_POST['gma_gemini_api_key'] ) ) );
		}

		// Save Reddit credentials.
		if ( isset( $_POST['gma_reddit_client_id'] ) ) {
			update_option( 'gma_reddit_client_id', sanitize_text_field( wp_unslash( $_POST['gma_reddit_client_id'] ) ) );
		}
		if ( isset( $_POST['gma_reddit_client_secret'] ) ) {
			update_option( 'gma_reddit_client_secret', sanitize_text_field( wp_unslash( $_POST['gma_reddit_client_secret'] ) ) );
		}

		// Save settings array.
		$settings = array(
			'scan_frequency'          => isset( $_POST['gma_scan_frequency'] ) ? sanitize_key( wp_unslash( $_POST['gma_scan_frequency'] ) ) : '6hours',
			'designs_per_scan'        => isset( $_POST['gma_designs_per_scan'] ) ? absint( wp_unslash( $_POST['gma_designs_per_scan'] ) ) : 10,
			'auto_approve'            => isset( $_POST['gma_auto_approve'] ) ? true : false,
			'auto_publish_to_printful' => isset( $_POST['gma_auto_publish_to_printful'] ) ? true : false,
			'printful_variant_id'     => isset( $_POST['gma_printful_variant_id'] ) ? sanitize_text_field( wp_unslash( $_POST['gma_printful_variant_id'] ) ) : '4012',
			'min_engagement'          => isset( $_POST['gma_min_engagement'] ) ? absint( wp_unslash( $_POST['gma_min_engagement'] ) ) : 50,
			'default_margin'          => isset( $_POST['gma_default_margin'] ) ? absint( wp_unslash( $_POST['gma_default_margin'] ) ) : 40,
			'notification_email'      => isset( $_POST['gma_notification_email'] ) ? sanitize_email( wp_unslash( $_POST['gma_notification_email'] ) ) : get_option( 'admin_email' ),
		);

		update_option( 'gma_settings', $settings );

		// Redirect with success message.
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'gma-settings',
					'updated' => 'true',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Render logs page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_logs() {
		$logger = gunmerch_ai()->get_class( 'logger' );

		$type_filter = isset( $_GET['type'] ) ? sanitize_key( wp_unslash( $_GET['type'] ) ) : '';
		$page        = isset( $_GET['log_page'] ) ? absint( wp_unslash( $_GET['log_page'] ) ) : 1;
		$per_page    = 50;
		$offset      = ( $page - 1 ) * $per_page;

		$logs = $logger ? $logger->get_logs(
			array(
				'type'   => $type_filter,
				'limit'  => $per_page,
				'offset' => $offset,
			)
		) : array();

		$total_logs = $logger ? $logger->get_log_count( $type_filter ) : 0;
		$total_pages = ceil( $total_logs / $per_page );

		include GMA_PLUGIN_DIR . 'templates/admin-logs.php';
	}
}