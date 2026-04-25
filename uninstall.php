<?php
/**
 * Uninstall – Secure Media Vault.
 *
 * Fired when the user clicks "Delete" from the Plugins screen.
 * Removes all plugin data: options, database tables, and .htaccess rules.
 *
 * @package SecureMediaVault
 */

// Only run when WordPress calls this file during uninstall.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Remove all plugin options.
$smv_options = array(
	'smv_default_protection',
	'smv_token_expiry',
	'smv_hotlink_protection',
	'smv_seo_noindex',
	'smv_disable_attachments',
	'smv_robots_txt',
	'smv_debug_mode',
	'smv_ip_validation',
	'smv_stream_large_files',
	'smv_stream_threshold',
	'smv_log_access',
	'smv_log_retention_days',
	'smv_db_version',
);

foreach ( $smv_options as $option ) {
	delete_option( $option );
}

// Drop custom tables.
$tables = array(
	esc_sql( $wpdb->prefix . 'smv_protection' ),
	esc_sql( $wpdb->prefix . 'smv_tokens' ),
	esc_sql( $wpdb->prefix . 'smv_access_logs' ),
);

foreach ( $tables as $table ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );
}

// Remove uploads .htaccess rules.
$upload_dir = wp_upload_dir();
$htaccess   = $upload_dir['basedir'] . '/.htaccess';

if ( file_exists( $htaccess ) ) {
	$content = file_get_contents( $htaccess ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	if ( false !== $content ) {
		$pattern = '/# BEGIN Secure Media Vault.*?# END Secure Media Vault\n?/s';
		$content = preg_replace( $pattern, '', $content );
		file_put_contents( $htaccess, $content ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents
	}
}

// Clear scheduled events.
wp_clear_scheduled_hook( 'smv_cleanup_expired_tokens' );
wp_clear_scheduled_hook( 'smv_cleanup_access_logs' );
