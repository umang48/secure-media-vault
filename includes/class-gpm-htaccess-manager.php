<?php
/**
 * .htaccess Manager.
 *
 * Utility class to write, update, and remove Guardify Private Media protection
 * rules from the uploads directory .htaccess file.
 *
 * @package SecureMediaVault
 * @since   1.0.0
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GPM_Htaccess_Manager
 */
class GPM_Htaccess_Manager {

	/**
	 * Marker for the SMV block begin.
	 *
	 * @var string
	 */
	const MARKER_BEGIN = '# BEGIN Guardify Private Media';

	/**
	 * Marker for the SMV block end.
	 *
	 * @var string
	 */
	const MARKER_END = '# END Guardify Private Media';

	/**
	 * Write or update the SMV rules in the uploads .htaccess.
	 *
	 * @param bool $protect Whether to add protection rules.
	 * @return bool True on success.
	 */
	public static function write_rules( $protect = true ) {
		$upload_dir = wp_upload_dir();
		$htaccess   = $upload_dir['basedir'] . '/.htaccess';

		$content = file_exists( $htaccess )
			? file_get_contents( $htaccess ) // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			: '';

		// Strip UTF-8 BOM if present (can appear in files edited on Windows).
		$content = str_replace( "\xEF\xBB\xBF", '', $content );

		// Remove existing SMV block.
		$content = self::remove_existing_block( $content );

		if ( $protect ) {
			$rules   = self::build_rules();
			$content = self::MARKER_BEGIN . "\n" . $rules . self::MARKER_END . "\n" . ltrim( $content );
		}

		$content = rtrim( $content ) . "\n";

		return (bool) file_put_contents( $htaccess, $content ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents
	}

	/**
	 * Remove SMV protection rules from .htaccess.
	 *
	 * @return bool
	 */
	public static function remove_rules() {
		return self::write_rules( false );
	}

	/**
	 * Build the .htaccess rule block.
	 *
	 * @return string
	 */
	private static function build_rules() {
		$rules  = "<IfModule mod_rewrite.c>\n";
		$rules .= "RewriteEngine On\n";
		// Prevent infinite loops: if the request is already going to index.php, stop.
		$rules .= "RewriteRule ^index\\.php$ - [L]\n";
		$rules .= "</IfModule>\n";
		// Disable directory listings.
		$rules .= "Options -Indexes\n";

		return $rules;
	}

	/**
	 * Remove the existing SMV block from .htaccess content.
	 *
	 * @param string $content Existing .htaccess content.
	 * @return string
	 */
	private static function remove_existing_block( $content ) {
		$pattern = '/' . preg_quote( self::MARKER_BEGIN, '/' ) . '.*?' . preg_quote( self::MARKER_END, '/' ) . '\n?/s';
		return preg_replace( $pattern, '', $content );
	}

	/**
	 * Check if Apache is being used.
	 *
	 * @return bool
	 */
	public static function is_apache() {
		global $is_apache;
		return $is_apache;
	}

	/**
	 * Generate Nginx rules for display in settings (not writeable by PHP).
	 *
	 * @return string Nginx configuration block.
	 */
	public static function get_nginx_rules() {
		$upload_dir  = wp_upload_dir();
		$upload_path = rtrim( wp_parse_url( $upload_dir['baseurl'], PHP_URL_PATH ), '/' );

		return "# Guardify Private Media – Nginx Rules\n"
			. "# Add these rules inside your server {} block:\n\n"
			. "location ~* ^{$upload_path}/(.+)$ {\n"
			. "    deny all;\n"
			. "    return 302 /protected-media-check/?gpm_direct=1&file=\$1;\n"
			. "}\n\n"
			. "# Disable directory listing\n"
			. "autoindex off;\n";
	}
}
