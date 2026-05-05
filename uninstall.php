<?php
/**
 * Uninstall – Guardify Private Media.
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
$gpm_options = array(
	'gpm_default_protection',
	'gpm_token_expiry',
	'gpm_hotlink_protection',
	'gpm_seo_noindex',
	'gpm_disable_attachments',
	'gpm_robots_txt',
	'gpm_debug_mode',
	'gpm_ip_validation',
	'gpm_stream_large_files',
	'gpm_stream_threshold',
	'gpm_log_access',
	'gpm_log_retention_days',
	'gpm_db_version',
);

foreach ( $gpm_options as $option ) {
	delete_option( $option );
}

// Drop custom tables.
$tables = array(
	esc_sql( $wpdb->prefix . 'gpm_protection' ),
	esc_sql( $wpdb->prefix . 'gpm_tokens' ),
	esc_sql( $wpdb->prefix . 'gpm_access_logs' ),
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
		$pattern = '/# BEGIN Guardify Private Media.*?# END Guardify Private Media\n?/s';
		$content = preg_replace( $pattern, '', $content );
		file_put_contents( $htaccess, $content ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents
	}
}

// Clear scheduled events.
wp_clear_scheduled_hook( 'gpm_cleanup_expired_tokens' );
wp_clear_scheduled_hook( 'gpm_cleanup_access_logs' );
