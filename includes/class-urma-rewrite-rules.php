<?php
/**
 * Rewrite Rules.
 *
 * Registers custom WordPress rewrite rules and query vars for the
 * restricted-media endpoint. Also disables media attachment pages
 * when configured.
 *
 * @package UmangRestrictedMediaAccess
 * @since   1.0.0
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class URMA_Rewrite_Rules
 */
class URMA_Rewrite_Rules {

	/**
	 * Single instance.
	 *
	 * @var URMA_Rewrite_Rules
	 */
	private static $instance = null;

	/**
	 * Get single instance.
	 *
	 * @return URMA_Rewrite_Rules
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'init', array( $this, 'add_rewrite_rules' ) );
		add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
		add_action( 'template_redirect', array( $this, 'handle_direct_access_check' ), 0 );
		add_action( 'template_redirect', array( $this, 'disable_attachment_pages' ) );
	}

	/**
	 * Register custom rewrite rules.
	 *
	 * @return void
	 */
	public function add_rewrite_rules() {
		// Secure file delivery endpoint.
		add_rewrite_rule(
			'^restricted-media/([0-9]+)/([a-zA-Z0-9_-]+)/?$',
			'index.php?urma_file_id=$matches[1]&urma_token=$matches[2]',
			'top'
		);
		// Diagnostic endpoint: handles redirects from .htaccess for blocked direct access.
		add_rewrite_rule(
			'^restricted-media-check/?$',
			'index.php?urma_direct=1',
			'top'
		);
	}

	/**
	 * Add custom query vars.
	 *
	 * @param array $vars Existing query vars.
	 * @return array Modified query vars.
	 */
	public function add_query_vars( $vars ) {
		$vars[] = 'urma_file_id';
		$vars[] = 'urma_token';
		$vars[] = 'urma_direct';
		$vars[] = 'file';
		return $vars;
	}

	/**
	 * Handle the /restricted-media-check/ diagnostic endpoint.
	 *
	 * When .htaccess redirects a direct file request here, this method:
	 * - Resolves the attachment from the requested file URI.
	 * - If the attachment is PUBLIC (or not found in the DB), serves the file
	 *   directly so the browser receives the expected response.
	 * - If the attachment is PROTECTED, returns a JSON error so the caller
	 *   knows direct access is blocked.
	 *
	 * @return void
	 */
	public function handle_direct_access_check() {
		// Only fire on requests that carry ?urma_direct=1.
		if ( '1' !== get_query_var( 'urma_direct' ) ) {
			// Also support plain $_GET for non-rewrite requests.
			if ( empty( $_GET['urma_direct'] ) || '1' !== $_GET['urma_direct'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				return;
			}
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$file_uri = isset( $_GET['file'] ) ? sanitize_text_field( wp_unslash( $_GET['file'] ) ) : '';

		// Convert the request URI to an absolute filesystem path.
		$upload_dir  = wp_upload_dir();
		$upload_url  = rtrim( $upload_dir['baseurl'], '/' );
		$upload_path = rtrim( $upload_dir['basedir'], '/\\' );

		// Build the absolute path from the URI.
		// $file_uri may look like /wp-content/uploads/2026/04/image.jpg
		$abs_path = '';
		if ( $file_uri ) {
			$site_path  = rtrim( wp_parse_url( site_url(), PHP_URL_PATH ), '/' );
			$rel        = ltrim( str_replace( $site_path, '', $file_uri ), '/' );
			$upload_rel = ltrim( str_replace( rtrim( wp_parse_url( $upload_url, PHP_URL_PATH ), '/' ), '', wp_parse_url( $upload_url, PHP_URL_PATH ) . '/' . $rel ), '/' );
			$abs_path   = $upload_path . '/' . ltrim( substr( $file_uri, strlen( $site_path ) + strlen( '/wp-content/uploads' ) ), '/' );
		}

		// Try to locate the attachment by its GUID / URL.
		$attachment_id = 0;
		if ( $file_uri ) {
			$file_url      = $upload_url . '/' . ltrim( substr( $file_uri, strlen( $site_path ?? '' ) + strlen( '/wp-content/uploads/' ) ), '/' );
			$attachment_id = attachment_url_to_postid( $file_url );
			// Fallback: strip query string and retry.
			if ( ! $attachment_id ) {
				$attachment_id = attachment_url_to_postid( strtok( $file_url, '?' ) );
			}
		}

		// Check protection status.
		$access_control = URMA_Access_Control::get_instance();
		$is_protected   = $attachment_id ? $access_control->is_protected( $attachment_id ) : false;

		if ( ! $is_protected ) {
			// Public file — serve it directly.
			$real_path = $abs_path ? realpath( $abs_path ) : false;

			// Security: make sure the resolved path is inside the uploads directory.
			if ( $real_path && 0 === strpos( $real_path, realpath( $upload_path ) ) && file_exists( $real_path ) ) {
				$mime = wp_check_filetype( $real_path );
				while ( ob_get_level() ) {
					ob_end_clean();
				}
				header( 'Content-Type: ' . ( $mime['type'] ?: 'application/octet-stream' ) );
				header( 'Content-Length: ' . filesize( $real_path ) );
				header( 'Cache-Control: public, max-age=31536000' );
				readfile( $real_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
				exit;
			}

			// File not found on disk.
			wp_die( esc_html__( 'File not found.', 'secure-media-vault' ), '', array( 'response' => 404 ) );
		}

		// Protected file — inform the client.
		wp_send_json(
			array(
				'smv'       => true,
				'protected' => true,
				'file'      => $file_uri,
				'message'   => __( 'Direct access is blocked. Use the secure URL to access this file.', 'secure-media-vault' ),
			),
			403
		);
	}

	/**
	 * Redirect media attachment pages to their parent post (or 404).
	 *
	 * @return void
	 */
	public function disable_attachment_pages() {
		if ( ! get_option( 'urma_disable_attachments', true ) ) {
			return;
		}

		if ( ! is_attachment() ) {
			return;
		}

		global $post;

		if ( $post && $post->post_parent ) {
			wp_safe_redirect( get_permalink( $post->post_parent ), 301 );
		} else {
			wp_safe_redirect( home_url( '/' ), 301 );
		}

		exit;
	}
}
