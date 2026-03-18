<?php
/**
 * Rewrite Rules.
 *
 * Registers custom WordPress rewrite rules and query vars for the
 * protected-media endpoint. Also disables media attachment pages
 * when configured.
 *
 * @package SecureMediaVault
 * @since   1.0.0
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SMV_Rewrite_Rules
 */
class SMV_Rewrite_Rules {

	/**
	 * Single instance.
	 *
	 * @var SMV_Rewrite_Rules
	 */
	private static $instance = null;

	/**
	 * Get single instance.
	 *
	 * @return SMV_Rewrite_Rules
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
		add_action( 'template_redirect', array( $this, 'disable_attachment_pages' ) );
	}

	/**
	 * Register custom rewrite rules.
	 *
	 * @return void
	 */
	public function add_rewrite_rules() {
		add_rewrite_rule(
			'^protected-media/([0-9]+)/([a-zA-Z0-9_-]+)/?$',
			'index.php?smv_file_id=$matches[1]&smv_token=$matches[2]',
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
		$vars[] = 'smv_file_id';
		$vars[] = 'smv_token';
		return $vars;
	}

	/**
	 * Redirect media attachment pages to their parent post (or 404).
	 *
	 * @return void
	 */
	public function disable_attachment_pages() {
		if ( ! get_option( 'smv_disable_attachments', true ) ) {
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
