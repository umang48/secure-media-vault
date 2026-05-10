<?php
/**
 * Plugin Activator.
 *
 * Handles activation tasks: creating database tables, default options,
 * and writing .htaccess protection rules.
 *
 * @package UmangRestrictedMediaAccess
 * @since   1.0.0
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class URMA_Activator
 */
class URMA_Activator {

	/**
	 * Plugin activation routine.
	 *
	 * @return void
	 */
	public static function activate() {
		self::create_tables();
		self::set_default_options();
		self::add_rewrite_rules();
		URMA_Htaccess_Manager::write_rules( true );
		flush_rewrite_rules();
	}

	/**
	 * Create custom database tables.
	 *
	 * @return void
	 */
	private static function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		// Table for per-attachment protection settings.
		$table_protection = $wpdb->prefix . 'urma_protection';
		$sql_protection    = "CREATE TABLE {$table_protection} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			attachment_id BIGINT(20) UNSIGNED NOT NULL,
			protection_type VARCHAR(50) NOT NULL DEFAULT 'public',
			allowed_roles LONGTEXT DEFAULT NULL,
			password_hash VARCHAR(255) DEFAULT NULL,
			allowed_post_ids LONGTEXT DEFAULT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY attachment_id (attachment_id),
			KEY protection_type (protection_type)
		) {$charset_collate};";

		// Table for signed token records.
		$table_tokens = $wpdb->prefix . 'urma_tokens';
		$sql_tokens   = "CREATE TABLE {$table_tokens} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			token VARCHAR(64) NOT NULL,
			attachment_id BIGINT(20) UNSIGNED NOT NULL,
			user_id BIGINT(20) UNSIGNED DEFAULT NULL,
			ip_address VARCHAR(45) DEFAULT NULL,
			expires_at DATETIME NOT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY token (token),
			KEY attachment_id (attachment_id),
			KEY expires_at (expires_at)
		) {$charset_collate};";

		// Table for access log.
		$table_logs = $wpdb->prefix . 'urma_access_logs';
		$sql_logs   = "CREATE TABLE {$table_logs} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			attachment_id BIGINT(20) UNSIGNED NOT NULL,
			user_id BIGINT(20) UNSIGNED DEFAULT NULL,
			ip_address VARCHAR(45) DEFAULT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'granted',
			accessed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY attachment_id (attachment_id),
			KEY status (status),
			KEY accessed_at (accessed_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql_protection );
		dbDelta( $sql_tokens );
		dbDelta( $sql_logs );

		update_option( 'urma_db_version', URMA_VERSION );
	}

	/**
	 * Set default plugin options.
	 *
	 * @return void
	 */
	private static function set_default_options() {
		$defaults = array(
			'urma_default_protection'   => 'public',
			'urma_token_expiry'         => 3600,   // 1 hour in seconds.
			'urma_hotlink_protection'   => true,
			'urma_seo_noindex'          => true,
			'urma_disable_attachments'  => true,
			'urma_robots_txt'           => false,
			'urma_debug_mode'           => false,
			'urma_ip_validation'        => false,
			'urma_stream_large_files'   => true,
			'urma_stream_threshold'     => 10,     // MB threshold for streaming.
			'urma_log_access'           => true,
			'urma_log_retention_days'   => 30,
		);

		foreach ( $defaults as $key => $value ) {
			if ( false === get_option( $key ) ) {
				add_option( $key, $value );
			}
		}
	}

	/**
	 * Register rewrite rules so they are flushed on activation.
	 *
	 * @return void
	 */
	private static function add_rewrite_rules() {
		add_rewrite_rule(
			'^restricted-media/([0-9]+)/([a-zA-Z0-9_-]+)/?$',
			'index.php?urma_file_id=$matches[1]&urma_token=$matches[2]',
			'top'
		);
		add_rewrite_tag( '%urma_file_id%', '([0-9]+)' );
		add_rewrite_tag( '%urma_token%', '([a-zA-Z0-9_-]+)' );
	}

}
