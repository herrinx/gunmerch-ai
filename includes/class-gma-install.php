<?php
/**
 * Installation and upgrade class.
 *
 * @package GunMerch_AI
 * @since 1.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GMA_Install
 *
 * Handles plugin installation, database tables, and upgrades.
 *
 * @since 1.0.0
 */
class GMA_Install {

	/**
	 * Database version.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const DB_VERSION = '1.0.0';

	/**
	 * Plugin activation.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function activate() {
		self::create_tables();
		self::create_options();
		self::register_capabilities();
		self::schedule_cron_jobs();

		// Store activation time.
		update_option( 'gma_activated_at', current_time( 'mysql' ) );
		update_option( 'gma_db_version', self::DB_VERSION );

		// Skip logging during activation - tables may not be ready
		// and logging can cause issues with some setups.

		// Don't flush rewrite rules here - causes issues on some systems
	}

	/**
	 * Plugin deactivation.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function deactivate() {
		self::clear_scheduled_hooks();

		// Skip logging during deactivation to avoid issues.

		// Flush rewrite rules.
		flush_rewrite_rules();
	}

	/**
	 * Create database tables.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private static function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$prefix          = $wpdb->prefix;

		// Trends table.
		$sql_trends = "CREATE TABLE IF NOT EXISTS {$prefix}gma_trends (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			topic varchar(255) NOT NULL,
			source varchar(100) NOT NULL,
			source_url text,
			engagement_score int(11) DEFAULT 0,
			discovered_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY discovered_at (discovered_at),
			KEY engagement_score (engagement_score),
			KEY source (source)
		) {$charset_collate};";

		// Design logs table.
		$sql_logs = "CREATE TABLE IF NOT EXISTS {$prefix}gma_logs (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			log_type varchar(50) NOT NULL,
			message text,
			design_id bigint(20) unsigned DEFAULT NULL,
			meta longtext,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY log_type (log_type),
			KEY created_at (created_at),
			KEY design_id (design_id)
		) {$charset_collate};";

		// Designs metadata table (for extended design data).
		$sql_design_meta = "CREATE TABLE IF NOT EXISTS {$prefix}gma_design_meta (
			meta_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			design_id bigint(20) unsigned NOT NULL,
			meta_key varchar(255) DEFAULT NULL,
			meta_value longtext,
			PRIMARY KEY (meta_id),
			KEY design_id (design_id),
			KEY meta_key (meta_key)
		) {$charset_collate};";

		// API rate limits tracking.
		$sql_rate_limits = "CREATE TABLE IF NOT EXISTS {$prefix}gma_rate_limits (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			api_name varchar(50) NOT NULL,
			request_count int(11) DEFAULT 0,
			window_start datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY api_name (api_name),
			KEY window_start (window_start)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		dbDelta( $sql_trends );
		dbDelta( $sql_logs );
		dbDelta( $sql_design_meta );
		dbDelta( $sql_rate_limits );
	}

	/**
	 * Create default options.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private static function create_options() {
		$default_options = array(
			'gma_settings'              => array(
				'scan_frequency'     => '6hours',
				'designs_per_scan'   => 10,
				'auto_approve'       => false,
				'min_engagement'     => 50,
				'default_margin'     => 40,
				'notification_email' => get_option( 'admin_email' ),
			),
			'gma_printful_api_key'      => '',
			'gma_openai_api_key'        => '',
			'gma_reddit_client_id'      => '',
			'gma_reddit_client_secret'  => '',
			'gma_x_api_key'             => '',
			'gma_x_api_secret'          => '',
			'gma_version'               => defined('GMA_VERSION') ? GMA_VERSION : '1.0.0',
			'gma_stats'                 => array(
				'total_designs_generated' => 0,
				'total_designs_approved'  => 0,
				'total_designs_rejected'  => 0,
				'total_sales'             => 0,
				'total_revenue'           => 0.00,
			),
		);

		foreach ( $default_options as $option_name => $default_value ) {
			if ( false === get_option( $option_name ) ) {
				add_option( $option_name, $default_value );
			}
		}
	}

	/**
	 * Register custom capabilities.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private static function register_capabilities() {
		$admin_role = get_role( 'administrator' );

		if ( $admin_role ) {
			$admin_role->add_cap( 'gma_manage_designs' );
			$admin_role->add_cap( 'gma_manage_settings' );
			$admin_role->add_cap( 'gma_view_logs' );
			$admin_role->add_cap( 'gma_approve_designs' );
		}
	}

	/**
	 * Schedule cron jobs.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private static function schedule_cron_jobs() {
		// Trend scanner - every 6 hours.
		if ( ! wp_next_scheduled( 'gma_scan_trends' ) ) {
			wp_schedule_event( time(), 'gma_6hours', 'gma_scan_trends' );
		}

		// Design generator - every 6 hours (offset by 1 hour from trends).
		if ( ! wp_next_scheduled( 'gma_generate_designs' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'gma_6hours', 'gma_generate_designs' );
		}

		// Sales sync - daily.
		if ( ! wp_next_scheduled( 'gma_sync_sales' ) ) {
			wp_schedule_event( time(), 'daily', 'gma_sync_sales' );
		}
	}

	/**
	 * Clear scheduled cron hooks.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private static function clear_scheduled_hooks() {
		$hooks = array( 'gma_scan_trends', 'gma_generate_designs', 'gma_sync_sales' );

		foreach ( $hooks as $hook ) {
			$timestamp = wp_next_scheduled( $hook );
			if ( $timestamp ) {
				wp_unschedule_event( $timestamp, $hook );
			}
		}
	}

	/**
	 * Check and perform database upgrades.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function check_upgrade() {
		$current_db_version = get_option( 'gma_db_version', '0.0.0' );

		if ( version_compare( $current_db_version, self::DB_VERSION, '<' ) ) {
			self::create_tables();
			update_option( 'gma_db_version', self::DB_VERSION );

			// Log upgrade.
			if ( class_exists( 'GMA_Logger' ) ) {
				$logger = new GMA_Logger();
				$logger->log(
					'system',
					sprintf(
						/* translators: %s: Database version */
						__( 'Database upgraded to version %s', 'gunmerch-ai' ),
						esc_html( self::DB_VERSION )
					)
				);
			}
		}
	}
}