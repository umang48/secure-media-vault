<?php
/**
 * Plugin Activator.
 *
 * Handles activation tasks: creating database tables, default options,
 * and writing .htaccess protection rules.
 *
 * @package SecureMediaVault
 * @since   1.0.0
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SMV_Activator
 */
class SMV_Activator {

	/**
	 * Plugin activation routine.
	 *
	 * @return void
	 */
	public static function activate() {
		self::create_tables();
		self::set_default_options();
		self::add_rewrite_rules();
		self::protect_uploads_directory();
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
		$table_protection = $wpdb->prefix . 'smv_protection';
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
		$table_tokens = $wpdb->prefix . 'smv_tokens';
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
		$table_logs = $wpdb->prefix . 'smv_access_logs';
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

		update_option( 'smv_db_version', SMV_VERSION );
	}

	/**
	 * Set default plugin options.
	 *
	 * @return void
	 */
	private static function set_default_options() {
		$defaults = array(
			'smv_default_protection'   => 'public',
			'smv_token_expiry'         => 3600,   // 1 hour in seconds.
			'smv_hotlink_protection'   => true,
			'smv_seo_noindex'          => true,
			'smv_disable_attachments'  => true,
			'smv_robots_txt'           => false,
			'smv_debug_mode'           => false,
			'smv_ip_validation'        => false,
			'smv_stream_large_files'   => true,
			'smv_stream_threshold'     => 10,     // MB threshold for streaming.
			'smv_log_access'           => true,
			'smv_log_retention_days'   => 30,
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
			'^protected-media/([0-9]+)/([a-zA-Z0-9_-]+)/?$',
			'index.php?smv_file_id=$matches[1]&smv_token=$matches[2]',
			'top'
		);
		add_rewrite_tag( '%smv_file_id%', '([0-9]+)' );
		add_rewrite_tag( '%smv_token%', '([a-zA-Z0-9_-]+)' );
	}

	/**
	 * Write .htaccess rules to block direct access to uploads directory.
	 *
	 * @return void
	 */
	private static function protect_uploads_directory() {
		$upload_dir = wp_upload_dir();
		$htaccess   = $upload_dir['basedir'] . '/.htaccess';

		$rules = "# BEGIN Secure Media Vault\n";
		$rules .= "<IfModule mod_rewrite.c>\n";
		$rules .= "RewriteEngine On\n";
		$rules .= "RewriteCond %{REQUEST_FILENAME} -f\n";
		$rules .= "RewriteCond %{REQUEST_URI} ^/wp-content/uploads/\n";
		$rules .= "RewriteRule ^(.*)$ " . home_url( '/protected-media-check/?smv_direct=1&file=$1' ) . " [L,R=302]\n";
		$rules .= "</IfModule>\n";
		$rules .= "Options -Indexes\n";
		$rules .= "# END Secure Media Vault\n";

		// Only write if protection is enabled.
		if ( ! file_exists( $htaccess ) ) {
			file_put_contents( $htaccess, $rules ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents
		}
	}
}
