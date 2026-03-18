<?php
/**
 * Admin Bootstrap.
 *
 * Registers admin menus, enqueues admin assets, and handles plugin action links.
 *
 * @package SecureMediaVault
 * @since   1.0.0
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SMV_Admin
 */
class SMV_Admin {

	/**
	 * Single instance.
	 *
	 * @var SMV_Admin
	 */
	private static $instance = null;

	/**
	 * Get single instance.
	 *
	 * @return SMV_Admin
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
		add_action( 'admin_menu', array( $this, 'register_menus' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'plugin_action_links_' . SMV_PLUGIN_BASENAME, array( $this, 'plugin_action_links' ) );
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
		add_action( 'wp_ajax_smv_get_secure_url', array( $this, 'ajax_get_secure_url' ) );
		add_action( 'wp_ajax_smv_revoke_tokens', array( $this, 'ajax_revoke_tokens' ) );
	}

	/**
	 * Register admin menu pages.
	 *
	 * @return void
	 */
	public function register_menus() {
		add_menu_page(
			__( 'Secure Media Vault', 'secure-media-vault' ),
			__( 'Media Vault', 'secure-media-vault' ),
			'manage_options',
			'secure-media-vault',
			array( $this, 'render_dashboard_page' ),
			'dashicons-lock',
			81
		);

		add_submenu_page(
			'secure-media-vault',
			__( 'Dashboard', 'secure-media-vault' ),
			__( 'Dashboard', 'secure-media-vault' ),
			'manage_options',
			'secure-media-vault',
			array( $this, 'render_dashboard_page' )
		);

		add_submenu_page(
			'secure-media-vault',
			__( 'Settings', 'secure-media-vault' ),
			__( 'Settings', 'secure-media-vault' ),
			'manage_options',
			'smv-settings',
			array( 'SMV_Settings', 'render_page' )
		);

		add_submenu_page(
			'secure-media-vault',
			__( 'Access Logs', 'secure-media-vault' ),
			__( 'Access Logs', 'secure-media-vault' ),
			'manage_options',
			'smv-logs',
			array( $this, 'render_logs_page' )
		);
	}

	/**
	 * Enqueue admin-only stylesheets and scripts.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		$smv_pages = array(
			'toplevel_page_secure-media-vault',
			'media-vault_page_smv-settings',
			'media-vault_page_smv-logs',
		);

		$is_smv_page       = in_array( $hook, $smv_pages, true );
		$is_media_page     = in_array( $hook, array( 'upload.php', 'post.php' ), true );
		$is_attachment_page = $is_media_page && isset( $_GET['post'] ) && 'attachment' === get_post_type( absint( $_GET['post'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( ! $is_smv_page && ! $is_media_page && ! $is_attachment_page ) {
			return;
		}

		wp_enqueue_style(
			'smv-admin',
			SMV_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			SMV_VERSION
		);

		wp_enqueue_script(
			'smv-admin',
			SMV_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery', 'wp-util' ),
			SMV_VERSION,
			true
		);

		wp_localize_script(
			'smv-admin',
			'smvAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'smv_admin_nonce' ),
				'i18n'    => array(
					'confirmRevoke'  => __( 'Revoke all tokens for this file? Existing secure links will stop working.', 'secure-media-vault' ),
					'tokenCopied'    => __( 'Secure URL copied to clipboard!', 'secure-media-vault' ),
					'generating'     => __( 'Generating…', 'secure-media-vault' ),
					'error'          => __( 'An error occurred. Please try again.', 'secure-media-vault' ),
				),
			)
		);
	}

	/**
	 * Add "Settings" link to plugin action links.
	 *
	 * @param array $links Existing action links.
	 * @return array
	 */
	public function plugin_action_links( $links ) {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'admin.php?page=smv-settings' ) ),
			esc_html__( 'Settings', 'secure-media-vault' )
		);
		array_unshift( $links, $settings_link );
		return $links;
	}

	/**
	 * Display admin notices (e.g., rewrite rule flush reminder).
	 *
	 * @return void
	 */
	public function admin_notices() {
		if ( get_transient( 'smv_flush_notice' ) ) {
			?>
			<div class="notice notice-warning is-dismissible">
				<p>
					<?php
					printf(
						/* translators: 1: Link open tag, 2: Link close tag */
						esc_html__( 'Secure Media Vault: Permalink settings may need to be re-saved. %1$sGo to Permalinks Settings%2$s.', 'secure-media-vault' ),
						'<a href="' . esc_url( admin_url( 'options-permalink.php' ) ) . '">',
						'</a>'
					);
					?>
				</p>
			</div>
			<?php
			delete_transient( 'smv_flush_notice' );
		}
	}

	/**
	 * Render the main dashboard page.
	 *
	 * @return void
	 */
	public function render_dashboard_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions.', 'secure-media-vault' ) );
		}

		global $wpdb;

		$table_protection = $wpdb->prefix . 'smv_protection';
		$table_tokens     = $wpdb->prefix . 'smv_tokens';
		$table_logs       = $wpdb->prefix . 'smv_access_logs';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$total_protected = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_protection} WHERE protection_type != 'public'" );
		$total_tokens    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_tokens} WHERE expires_at > NOW()" );
		$total_granted   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_logs} WHERE status = 'granted' AND accessed_at > DATE_SUB(NOW(), INTERVAL 7 DAY)" );
		$total_denied    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_logs} WHERE status LIKE 'denied%' AND accessed_at > DATE_SUB(NOW(), INTERVAL 7 DAY)" );
		// phpcs:enable

		include SMV_PLUGIN_DIR . 'admin/views/dashboard.php';
	}

	/**
	 * Render the access logs page.
	 *
	 * @return void
	 */
	public function render_logs_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions.', 'secure-media-vault' ) );
		}

		global $wpdb;
		$table_logs = $wpdb->prefix . 'smv_access_logs';
		$per_page   = 20;
		$page       = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$offset     = ( $page - 1 ) * $per_page;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$logs  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT l.*, p.post_title FROM {$table_logs} l
				LEFT JOIN {$wpdb->posts} p ON l.attachment_id = p.ID
				ORDER BY l.accessed_at DESC LIMIT %d OFFSET %d",
				$per_page,
				$offset
			)
		);
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_logs}" );
		// phpcs:enable

		include SMV_PLUGIN_DIR . 'admin/views/logs.php';
	}

	/**
	 * AJAX: Generate and return a secure URL for an attachment.
	 *
	 * @return void
	 */
	public function ajax_get_secure_url() {
		check_ajax_referer( 'smv_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'secure-media-vault' ) ) );
			return;
		}

		$attachment_id = isset( $_POST['attachment_id'] ) ? absint( $_POST['attachment_id'] ) : 0;
		if ( ! $attachment_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid attachment ID.', 'secure-media-vault' ) ) );
			return;
		}

		$url = SMV_Token_Manager::get_instance()->get_secure_url( $attachment_id, get_current_user_id() );

		wp_send_json_success( array( 'url' => $url ) );
	}

	/**
	 * AJAX: Revoke all tokens for an attachment.
	 *
	 * @return void
	 */
	public function ajax_revoke_tokens() {
		check_ajax_referer( 'smv_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'secure-media-vault' ) ) );
			return;
		}

		$attachment_id = isset( $_POST['attachment_id'] ) ? absint( $_POST['attachment_id'] ) : 0;
		if ( ! $attachment_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid attachment ID.', 'secure-media-vault' ) ) );
			return;
		}

		$count = SMV_Token_Manager::get_instance()->revoke_attachment_tokens( $attachment_id );

		wp_send_json_success(
			array(
				/* translators: %d: number of revoked tokens */
				'message' => sprintf( __( '%d token(s) revoked successfully.', 'secure-media-vault' ), $count ),
			)
		);
	}
}
