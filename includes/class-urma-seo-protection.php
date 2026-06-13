<?php
/**
 * SEO Protection.
 *
 * Adds X-Robots-Tag headers, disables attachment page indexing,
 * and modifies robots.txt for restricted media.
 *
 * @package PTPPrivateMedia
 * @since   1.0.0
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class URMA_SEO_Protection
 */
class URMA_SEO_Protection {

	/**
	 * Single instance.
	 *
	 * @var URMA_SEO_Protection
	 */
	private static $instance = null;

	/**
	 * Get single instance.
	 *
	 * @return URMA_SEO_Protection
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
		add_action( 'send_headers', array( $this, 'add_seo_headers' ) );
		add_action( 'do_robots', array( $this, 'add_robots_txt_rules' ) );
		add_filter( 'wp_robots', array( $this, 'noindex_attachment_pages' ) );
	}

	/**
	 * Add X-Robots-Tag headers to restricted-media requests.
	 *
	 * @return void
	 */
	public function add_seo_headers() {
		if ( ! get_option( 'urma_seo_noindex', true ) ) {
			return;
		}

		$file_id = get_query_var( 'urma_file_id' );
		if ( ! empty( $file_id ) ) {
			header( 'X-Robots-Tag: noindex, nofollow', true );
		}
	}

	/**
	 * Add Disallow rules for the uploads directory in robots.txt.
	 *
	 * @return void
	 */
	public function add_robots_txt_rules() {
		if ( ! get_option( 'urma_robots_txt', false ) ) {
			return;
		}

		$upload_dir    = wp_upload_dir();
		$uploads_path  = wp_parse_url( $upload_dir['baseurl'], PHP_URL_PATH );

		echo "\n# PTP Private Media\n";
		echo 'User-agent: *' . "\n";
		echo 'Disallow: ' . esc_url( $uploads_path ) . '/' . "\n";
		echo 'Disallow: /restricted-media/' . "\n";
	}

	/**
	 * Add noindex, nofollow to attachment pages in the wp_robots API.
	 *
	 * @param array $robots Existing robots directives.
	 * @return array
	 */
	public function noindex_attachment_pages( $robots ) {
		if ( is_attachment() && get_option( 'urma_seo_noindex', true ) ) {
			$robots['noindex']  = true;
			$robots['nofollow'] = true;
		}
		return $robots;
	}
}
