<?php
/**
 * Plugin Name:       Guardify Private Media
 * Plugin URI:        https://github.com/umang48/secure-media-vault
 * Description:       Protect WordPress media files from direct public access with token-based secure delivery, fine-grained access control, and SEO indexing protection.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Umang Prajapati
 * Author URI:        https://phptutorialpoints.in/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       guardify-private-media
 * Domain Path:       /languages
 *
 * @package SecureMediaVault
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin version constant.
define( 'GPM_VERSION', '1.0.0' );
define( 'GPM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'GPM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'GPM_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Returns the uploads base directory.
 *
 * Replaces the old GPM_UPLOADS_DIR constant. Calling wp_upload_dir() at
 * file-load time (e.g. during activation) can trigger PHP notices and produce
 * unexpected output before headers are sent. Using a helper function means
 * wp_upload_dir() is only called when actually needed.
 *
 * @return string Absolute path to the uploads base directory.
 */
function gpm_uploads_dir() {
	return wp_upload_dir()['basedir'];
}

/**
 * Returns the uploads base URL.
 *
 * @return string URL of the uploads base directory.
 */
function gpm_uploads_url() {
	return wp_upload_dir()['baseurl'];
}

/**
 * Main plugin class.
 *
 * @since 1.0.0
 */
final class Guardify_Private_Media {

	/**
	 * Single instance.
	 *
	 * @var Guardify_Private_Media
	 */
	private static $instance = null;

	/**
	 * Get single instance.
	 *
	 * @return Guardify_Private_Media
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
		require_once GPM_PLUGIN_DIR . 'includes/class-gpm-activator.php';
		require_once GPM_PLUGIN_DIR . 'includes/class-gpm-deactivator.php';
		require_once GPM_PLUGIN_DIR . 'includes/class-gpm-token-manager.php';
		require_once GPM_PLUGIN_DIR . 'includes/class-gpm-access-control.php';
		require_once GPM_PLUGIN_DIR . 'includes/class-gpm-file-handler.php';
		require_once GPM_PLUGIN_DIR . 'includes/class-gpm-rewrite-rules.php';
		require_once GPM_PLUGIN_DIR . 'includes/class-gpm-seo-protection.php';
		require_once GPM_PLUGIN_DIR . 'includes/class-gpm-htaccess-manager.php';
		require_once GPM_PLUGIN_DIR . 'admin/class-gpm-admin.php';
		require_once GPM_PLUGIN_DIR . 'admin/class-gpm-media-library.php';
		require_once GPM_PLUGIN_DIR . 'admin/class-gpm-settings.php';
	}

	/**
	 * Register all hooks.
	 */
	private function init_hooks() {
		add_action( 'plugins_loaded', array( $this, 'init_components' ) );

		// Activation / deactivation hooks.
		register_activation_hook( __FILE__, array( 'GPM_Activator', 'activate' ) );
		register_deactivation_hook( __FILE__, array( 'GPM_Deactivator', 'deactivate' ) );
	}

	/**
	 * Initialize all plugin components.
	 */
	public function init_components() {
		// Core components.
		GPM_Token_Manager::get_instance();
		GPM_Access_Control::get_instance();
		GPM_File_Handler::get_instance();
		GPM_Rewrite_Rules::get_instance();
		GPM_SEO_Protection::get_instance();

		// Admin components (only in admin context).
		if ( is_admin() ) {
			GPM_Admin::get_instance();
			GPM_Media_Library::get_instance();
			GPM_Settings::get_instance();
		}
	}
}

/**
 * Returns the main instance of Guardify_Private_Media.
 *
 * @return Guardify_Private_Media
 */
function gpm() {
	return Guardify_Private_Media::get_instance();
}

// Kick off the plugin.
gpm();
