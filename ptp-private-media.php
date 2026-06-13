<?php
/**
 * Plugin Name:       PTP Private Media
 * Plugin URI:        https://wordpress.org/plugins/ptp-private-media/
 * Description:       Protect WordPress media files from direct public access with token-based secure delivery, fine-grained access control, and SEO indexing protection.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Umang Prajapati
 * Author URI:        https://phptutorialpoints.in/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ptp-private-media
 * Domain Path:       /languages
 *
 * @package PTPPrivateMedia
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin version constant.
define( 'URMA_VERSION', '1.0.0' );
define( 'URMA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'URMA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'URMA_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Returns the uploads base directory.
 *
 * Replaces the old URMA_UPLOADS_DIR constant. Calling wp_upload_dir() at
 * file-load time (e.g. during activation) can trigger PHP notices and produce
 * unexpected output before headers are sent. Using a helper function means
 * wp_upload_dir() is only called when actually needed.
 *
 * @return string Absolute path to the uploads base directory.
 */
function urma_uploads_dir() {
	return wp_upload_dir()['basedir'];
}

/**
 * Returns the uploads base URL.
 *
 * @return string URL of the uploads base directory.
 */
function urma_uploads_url() {
	return wp_upload_dir()['baseurl'];
}

/**
 * Main plugin class.
 *
 * @since 1.0.0
 */
final class PTP_Private_Media {

	/**
	 * Single instance.
	 *
	 * @var PTP_Private_Media
	 */
	private static $instance = null;

	/**
	 * Get single instance.
	 *
	 * @return PTP_Private_Media
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor: load required files and hooks.
	 */
	private function __construct() {
		$this->load_dependencies();
		$this->init_hooks();
	}

	/**
	 * Load all required class files.
	 */
	private function load_dependencies() {
		require_once URMA_PLUGIN_DIR . 'includes/class-urma-activator.php';
		require_once URMA_PLUGIN_DIR . 'includes/class-urma-deactivator.php';
		require_once URMA_PLUGIN_DIR . 'includes/class-urma-token-manager.php';
		require_once URMA_PLUGIN_DIR . 'includes/class-urma-access-control.php';
		require_once URMA_PLUGIN_DIR . 'includes/class-urma-file-handler.php';
		require_once URMA_PLUGIN_DIR . 'includes/class-urma-rewrite-rules.php';
		require_once URMA_PLUGIN_DIR . 'includes/class-urma-seo-protection.php';
		require_once URMA_PLUGIN_DIR . 'includes/class-urma-htaccess-manager.php';
		require_once URMA_PLUGIN_DIR . 'admin/class-urma-admin.php';
		require_once URMA_PLUGIN_DIR . 'admin/class-urma-media-library.php';
		require_once URMA_PLUGIN_DIR . 'admin/class-urma-settings.php';
	}

	/**
	 * Register all hooks.
	 */
	private function init_hooks() {
		add_action( 'plugins_loaded', array( $this, 'init_components' ) );

		// Activation / deactivation hooks.
		register_activation_hook( __FILE__, array( 'URMA_Activator', 'activate' ) );
		register_deactivation_hook( __FILE__, array( 'URMA_Deactivator', 'deactivate' ) );
	}

	/**
	 * Initialize all plugin components.
	 */
	public function init_components() {
		// Core components.
		URMA_Token_Manager::get_instance();
		URMA_Access_Control::get_instance();
		URMA_File_Handler::get_instance();
		URMA_Rewrite_Rules::get_instance();
		URMA_SEO_Protection::get_instance();

		// Admin components (only in admin context).
		if ( is_admin() ) {
			URMA_Admin::get_instance();
			URMA_Media_Library::get_instance();
			URMA_Settings::get_instance();
		}
	}
}

/**
 * Returns the main instance of PTP_Private_Media.
 *
 * @return PTP_Private_Media
 */
function urma() {
	return PTP_Private_Media::get_instance();
}

// Kick off the plugin.
urma();
