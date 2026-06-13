<?php
/**
 * Plugin Deactivator.
 *
 * Handles cleanup on plugin deactivation: flush rewrite rules, remove
 * upload directory .htaccess protection rules, and clean up scheduled events.
 *
 * @package PTPPrivateMedia
 * @since   1.0.0
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class URMA_Deactivator
 */
class URMA_Deactivator {

	/**
	 * Plugin deactivation routine.
	 *
	 * @return void
	 */
	public static function deactivate() {
		self::remove_htaccess_rules();
		self::clear_scheduled_events();
		flush_rewrite_rules();
	}

	/**
	 * Remove the PTP Private Media rules from the uploads .htaccess file.
	 *
	 * @return void
	 */
	private static function remove_htaccess_rules() {
		$upload_dir = wp_upload_dir();
		$htaccess   = $upload_dir['basedir'] . '/.htaccess';

		if ( ! file_exists( $htaccess ) ) {
			return;
		}

		$content = file_get_contents( $htaccess ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( false === $content ) {
			return;
		}

		// Remove the SMV block between markers.
		$pattern = '/# BEGIN PTP Private Media.*?# END PTP Private Media\n?/s';
		$content = preg_replace( $pattern, '', $content );

		file_put_contents( $htaccess, $content ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents
	}

	/**
	 * Clear any scheduled cron events.
	 *
	 * @return void
	 */
	private static function clear_scheduled_events() {
		wp_clear_scheduled_hook( 'urma_cleanup_expired_tokens' );
		wp_clear_scheduled_hook( 'urma_cleanup_access_logs' );
	}
}
