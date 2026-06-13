<?php
/**
 * Uninstall – PTP Private Media.
 *
 * Fired when the user clicks "Delete" from the Plugins screen.
 * Removes all plugin data: options, database tables, and .htaccess rules.
 *
 * @package PTPPrivateMedia
 */

// Only run when WordPress calls this file during uninstall.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Remove all plugin options.
$urma_options = array(
	'urma_default_protection',
	'urma_token_expiry',
	'urma_hotlink_protection',
	'urma_seo_noindex',
	'urma_disable_attachments',
	'urma_robots_txt',
	'urma_debug_mode',
	'urma_ip_validation',
	'urma_stream_large_files',
	'urma_stream_threshold',
	'urma_log_access',
	'urma_log_retention_days',
	'urma_db_version',
);

foreach ( $urma_options as $urma_option ) {
	delete_option( $urma_option );
}

// Drop custom tables.
$urma_tables = array(
	esc_sql( $wpdb->prefix . 'urma_protection' ),
	esc_sql( $wpdb->prefix . 'urma_tokens' ),
	esc_sql( $wpdb->prefix . 'urma_access_logs' ),
);

foreach ( $urma_tables as $urma_table ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$wpdb->query( "DROP TABLE IF EXISTS `{$urma_table}`" );
}

// Remove uploads .htaccess rules.
$urma_upload_dir = wp_upload_dir();
$urma_htaccess   = $urma_upload_dir['basedir'] . '/.htaccess';

if ( file_exists( $urma_htaccess ) ) {
	$urma_content = file_get_contents( $urma_htaccess ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	if ( false !== $urma_content ) {
		$urma_pattern = '/# BEGIN PTP Private Media.*?# END PTP Private Media\n?/s';
		$urma_content = preg_replace( $urma_pattern, '', $urma_content );
		file_put_contents( $urma_htaccess, $urma_content ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents
	}
}

// Clear scheduled events.
wp_clear_scheduled_hook( 'urma_cleanup_expired_tokens' );
wp_clear_scheduled_hook( 'urma_cleanup_access_logs' );
