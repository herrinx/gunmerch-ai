<?php
/**
 * Plugin Name: GunMerch AI
 * Plugin URI: https://github.com/herrinx/gunmerch-ai
 * Description: Automated t-shirt factory - generates gun-themed designs from trending topics, review and auto-publish to Printful.
 * Version: 1.0.0
 * Author: GunMerch
 * Author URI: https://gunmerch.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: gunmerch-ai
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 *
 * @package GunMerch_AI
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants.
define( 'GMA_VERSION', '1.0.1' );
define( 'GMA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'GMA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'GMA_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Main plugin class.
 *
 * @since 1.0.0
 */
class GunMerch_AI {

	/**
	 * Single instance of the class.
	 *
	 * @since 1.0.0
	 * @var GunMerch_AI|null
	 */
	private static $instance = null;

	/**
	 * Plugin classes.
	 *
	 * @since 1.0.0
	 * @var array
	 */
	private $classes = array();

	/**
	 * Get single instance of the class.
	 *
	 * @since 1.0.0
	 * @return GunMerch_AI
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		$this->includes();
		$this->init();
		$this->register_hooks();
	}

	/**
	 * Include required files.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function includes() {
		require_once GMA_PLUGIN_DIR . 'includes/class-gma-install.php';
		require_once GMA_PLUGIN_DIR . 'includes/class-gma-logger.php';
		require_once GMA_PLUGIN_DIR . 'includes/class-gma-core.php';
		require_once GMA_PLUGIN_DIR . 'includes/class-gma-admin.php';
		require_once GMA_PLUGIN_DIR . 'includes/class-gma-trends.php';
		require_once GMA_PLUGIN_DIR . 'includes/class-gma-designer.php';
		require_once GMA_PLUGIN_DIR . 'includes/class-gma-printfull.php';
		require_once GMA_PLUGIN_DIR . 'includes/class-gma-ajax.php';
	}

	/**
	 * Initialize plugin classes.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function init() {
		$this->classes['logger']    = new GMA_Logger();
		$this->classes['core']      = new GMA_Core();
		$this->classes['admin']     = new GMA_Admin();
		$this->classes['trends']    = new GMA_Trends();
		$this->classes['designer']  = new GMA_Designer();
		$this->classes['printfull'] = new GMA_Printful();
		$this->classes['ajax']      = new GMA_AJAX();
	}

	/**
	 * Register WordPress hooks.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function register_hooks() {
		register_activation_hook( __FILE__, array( 'GMA_Install', 'activate' ) );
		register_deactivation_hook( __FILE__, array( 'GMA_Install', 'deactivate' ) );

		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		add_action( 'init', array( $this, 'register_post_types' ) );
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );

		// Register cron schedules.
		add_filter( 'cron_schedules', array( $this, 'add_cron_schedules' ) );
	}

	/**
	 * Load plugin textdomain.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'gunmerch-ai',
			false,
			dirname( GMA_PLUGIN_BASENAME ) . '/languages/'
		);
	}

	/**
	 * Register custom post types.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_post_types() {
		$labels = array(
			'name'                  => _x( 'Designs', 'Post type general name', 'gunmerch-ai' ),
			'singular_name'         => _x( 'Design', 'Post type singular name', 'gunmerch-ai' ),
			'menu_name'             => _x( 'GunMerch AI', 'Admin Menu text', 'gunmerch-ai' ),
			'name_admin_bar'        => _x( 'Design', 'Add New on Toolbar', 'gunmerch-ai' ),
			'add_new'               => __( 'Add New', 'gunmerch-ai' ),
			'add_new_item'          => __( 'Add New Design', 'gunmerch-ai' ),
			'new_item'              => __( 'New Design', 'gunmerch-ai' ),
			'edit_item'             => __( 'Edit Design', 'gunmerch-ai' ),
			'view_item'             => __( 'View Design', 'gunmerch-ai' ),
			'all_items'             => __( 'All Designs', 'gunmerch-ai' ),
			'search_items'          => __( 'Search Designs', 'gunmerch-ai' ),
			'parent_item_colon'     => __( 'Parent Designs:', 'gunmerch-ai' ),
			'not_found'             => __( 'No designs found.', 'gunmerch-ai' ),
			'not_found_in_trash'    => __( 'No designs found in Trash.', 'gunmerch-ai' ),
			'featured_image'        => _x( 'Design Mockup', 'Overrides the "Featured Image" phrase', 'gunmerch-ai' ),
			'set_featured_image'    => _x( 'Set mockup image', 'Overrides the "Set featured image" phrase', 'gunmerch-ai' ),
			'remove_featured_image' => _x( 'Remove mockup image', 'Overrides the "Remove featured image" phrase', 'gunmerch-ai' ),
			'use_featured_image'    => _x( 'Use as mockup image', 'Overrides the "Use as featured image" phrase', 'gunmerch-ai' ),
			'archives'              => _x( 'Design archives', 'The post type archive label', 'gunmerch-ai' ),
			'insert_into_item'      => _x( 'Insert into design', 'Overrides the "Insert into post" phrase', 'gunmerch-ai' ),
			'uploaded_to_this_item' => _x( 'Uploaded to this design', 'Overrides the "Uploaded to this post" phrase', 'gunmerch-ai' ),
			'filter_items_list'     => _x( 'Filter designs list', 'Screen reader text', 'gunmerch-ai' ),
			'items_list_navigation' => _x( 'Designs list navigation', 'Screen reader text', 'gunmerch-ai' ),
			'items_list'            => _x( 'Designs list', 'Screen reader text', 'gunmerch-ai' ),
		);

		$args = array(
			'labels'             => $labels,
			'public'             => false,
			'publicly_queryable' => false,
			'show_ui'            => true,
			'show_in_menu'       => 'gunmerch-ai',
			'query_var'          => true,
			'rewrite'            => array( 'slug' => 'gma-design' ),
			'capability_type'    => 'post',
			'has_archive'        => false,
			'hierarchical'       => false,
			'menu_position'      => null,
			'supports'           => array( 'title', 'editor', 'thumbnail' ),
			'show_in_rest'       => true,
		);

		register_post_type( 'gma_design', $args );
	}

	/**
	 * Add custom cron schedules.
	 *
	 * @since 1.0.0
	 * @param array $schedules Existing cron schedules.
	 * @return array Modified cron schedules.
	 */
	public function add_cron_schedules( $schedules ) {
		$schedules['gma_6hours'] = array(
			'interval' => 21600,
			'display'  => __( 'Every 6 Hours', 'gunmerch-ai' ),
		);
		return $schedules;
	}

	/**
	 * Display admin notices.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function admin_notices() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Check if Printful API key is configured.
		$api_key = get_option( 'gma_printful_api_key' );
		if ( empty( $api_key ) ) {
			printf(
				'<div class="notice notice-warning is-dismissible"><p>%s <a href="%s">%s</a></p></div>',
				esc_html__( 'GunMerch AI: Printful API key not configured.', 'gunmerch-ai' ),
				esc_url( admin_url( 'admin.php?page=gma-settings' ) ),
				esc_html__( 'Configure now', 'gunmerch-ai' )
			);
		}

		// Check for pending designs.
		$pending_count = wp_count_posts( 'gma_design' )->pending;
		if ( $pending_count > 0 ) {
			printf(
				'<div class="notice notice-info is-dismissible"><p>%s <a href="%s">%s</a></p></div>',
				esprintf(
					/* translators: %d: Number of pending designs */
					__( 'GunMerch AI: %d designs awaiting review.', 'gunmerch-ai' ),
					absint( $pending_count )
				),
				esc_url( admin_url( 'admin.php?page=gma-review' ) ),
				esc_html__( 'Review designs', 'gunmerch-ai' )
			);
		}
	}

	/**
	 * Get plugin class instance.
	 *
	 * @since 1.0.0
	 * @param string $class Class name.
	 * @return object|null
	 */
	public function get_class( $class ) {
		return isset( $this->classes[ $class ] ) ? $this->classes[ $class ] : null;
	}
}

/**
 * Initialize the plugin.
 *
 * @since 1.0.0
 * @return GunMerch_AI
 */
function gunmerch_ai() {
	return GunMerch_AI::instance();
}

// Initialize.
gunmerch_ai();