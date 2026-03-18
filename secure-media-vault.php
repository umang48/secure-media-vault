<?php
/**
 * Plugin Name:       Secure Media Vault
 * Plugin URI:        https://github.com/umang48/secure-media-vault
 * Description:       Protect WordPress media files from direct public access with token-based secure delivery, fine-grained access control, and SEO indexing protection.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Umang Prajapati
 * Author URI:        https://phptutorialpoints.in/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       secure-media-vault
 * Domain Path:       /languages
 *
 * @package SecureMediaVault
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin version constant.
define( 'SMV_VERSION', '1.0.0' );
define( 'SMV_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SMV_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SMV_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'SMV_UPLOADS_DIR', wp_upload_dir()['basedir'] );
define( 'SMV_UPLOADS_URL', wp_upload_dir()['baseurl'] );

/**
 * Main plugin class.
 *
 * @since 1.0.0
 */
final class Secure_Media_Vault {

	/**
	 * Single instance.
	 *
	 * @var Secure_Media_Vault
	 */
	private static $instance = null;

	/**
	 * Get single instance.
	 *
	 * @return Secure_Media_Vault
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
		require_once SMV_PLUGIN_DIR . 'includes/class-smv-activator.php';
		require_once SMV_PLUGIN_DIR . 'includes/class-smv-deactivator.php';
		require_once SMV_PLUGIN_DIR . 'includes/class-smv-token-manager.php';
		require_once SMV_PLUGIN_DIR . 'includes/class-smv-access-control.php';
		require_once SMV_PLUGIN_DIR . 'includes/class-smv-file-handler.php';
		require_once SMV_PLUGIN_DIR . 'includes/class-smv-rewrite-rules.php';
		require_once SMV_PLUGIN_DIR . 'includes/class-smv-seo-protection.php';
		require_once SMV_PLUGIN_DIR . 'includes/class-smv-htaccess-manager.php';
		require_once SMV_PLUGIN_DIR . 'admin/class-smv-admin.php';
		require_once SMV_PLUGIN_DIR . 'admin/class-smv-media-library.php';
		require_once SMV_PLUGIN_DIR . 'admin/class-smv-settings.php';
	}

	/**
	 * Register all hooks.
	 */
	private function init_hooks() {
		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'init', array( $this, 'init_components' ) );

		// Activation / deactivation hooks.
		register_activation_hook( __FILE__, array( 'SMV_Activator', 'activate' ) );
		register_deactivation_hook( __FILE__, array( 'SMV_Deactivator', 'deactivate' ) );
	}

	/**
	 * Load plugin text domain.
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'secure-media-vault',
			false,
			dirname( SMV_PLUGIN_BASENAME ) . '/languages'
		);
	}

	/**
	 * Initialize all plugin components.
	 */
	public function init_components() {
		// Core components.
		SMV_Token_Manager::get_instance();
		SMV_Access_Control::get_instance();
		SMV_File_Handler::get_instance();
		SMV_Rewrite_Rules::get_instance();
		SMV_SEO_Protection::get_instance();

		// Admin components (only in admin context).
		if ( is_admin() ) {
			SMV_Admin::get_instance();
			SMV_Media_Library::get_instance();
			SMV_Settings::get_instance();
		}
	}
}

/**
 * Returns the main instance of Secure_Media_Vault.
 *
 * @return Secure_Media_Vault
 */
function smv() {
	return Secure_Media_Vault::get_instance();
}

// Kick off the plugin.
smv();
