<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package GunMerch_AI
 * @since 1.0.0
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Check if we should keep data (respect user preference).
$keep_data = get_option( 'gma_keep_data_on_uninstall', false );

if ( $keep_data ) {
	return;
}

// Load WordPress database functions.
global $wpdb;

/**
 * Delete plugin custom tables.
 *
 * @since 1.0.0
 */
function gma_delete_custom_tables() {
	global $wpdb;

	$tables = array(
		$wpdb->prefix . 'gma_trends',
		$wpdb->prefix . 'gma_logs',
		$wpdb->prefix . 'gma_design_meta',
		$wpdb->prefix . 'gma_rate_limits',
	);

	foreach ( $tables as $table ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
	}
}

/**
 * Delete plugin custom post type posts and meta.
 *
 * @since 1.0.0
 */
function gma_delete_custom_posts() {
	global $wpdb;

	// Get all gma_design posts.
	$post_ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s",
			'gma_design'
		)
	);

	if ( ! empty( $post_ids ) ) {
		// Delete post meta.
		$post_ids_string = implode( ',', array_map( 'intval', $post_ids ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE post_id IN ({$post_ids_string})" );

		// Delete posts.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DELETE FROM {$wpdb->posts} WHERE post_type = 'gma_design'" );
	}
}

/**
 * Delete plugin options.
 *
 * @since 1.0.0
 */
function gma_delete_options() {
	$options = array(
		'gma_settings',
		'gma_printful_api_key',
		'gma_openai_api_key',
		'gma_reddit_client_id',
		'gma_reddit_client_secret',
		'gma_x_api_key',
		'gma_x_api_secret',
		'gma_version',
		'gma_db_version',
		'gma_stats',
		'gma_activated_at',
		'gma_keep_data_on_uninstall',
	);

	foreach ( $options as $option ) {
		delete_option( $option );
	}
}

/**
 * Delete plugin transients.
 *
 * @since 1.0.0
 */
function gma_delete_transients() {
	$transients = array(
		'gma_current_trends',
		'gma_new_designs_notification',
		'gma_design_published_notification',
		'gma_sale_notification',
	);

	foreach ( $transients as $transient ) {
		delete_transient( $transient );
	}
}

/**
 * Clear scheduled cron events.
 *
 * @since 1.0.0
 */
function gma_clear_cron_events() {
	$hooks = array(
		'gma_scan_trends',
		'gma_generate_designs',
		'gma_sync_sales',
	);

	foreach ( $hooks as $hook ) {
		$timestamp = wp_next_scheduled( $hook );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, $hook );
		}
	}
}

/**
 * Remove plugin capabilities.
 *
 * @since 1.0.0
 */
function gma_remove_capabilities() {
	$admin_role = get_role( 'administrator' );

	if ( $admin_role ) {
		$admin_role->remove_cap( 'gma_manage_designs' );
		$admin_role->remove_cap( 'gma_manage_settings' );
		$admin_role->remove_cap( 'gma_view_logs' );
		$admin_role->remove_cap( 'gma_approve_designs' );
	}
}

// Execute cleanup.
gma_delete_custom_tables();
gma_delete_custom_posts();
gma_delete_options();
gma_delete_transients();
gma_clear_cron_events();
gma_remove_capabilities();

// Flush rewrite rules.
flush_rewrite_rules();