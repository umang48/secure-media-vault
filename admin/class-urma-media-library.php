<?php
/**
 * Media Library Integration.
 *
 * Adds protection settings to the Media Library attachment edit screen,
 * handles bulk protect actions, and shows protection status in the grid view.
 *
 * @package UmangRestrictedMediaAccess
 * @since   1.0.0
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class URMA_Media_Library
 */
class URMA_Media_Library {

	/**
	 * Single instance.
	 *
	 * @var URMA_Media_Library
	 */
	private static $instance = null;

	/**
	 * Get single instance.
	 *
	 * @return URMA_Media_Library
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
		// Attachment fields in Classic editor / attachment edit screen.
		add_filter( 'attachment_fields_to_edit', array( $this, 'add_protection_fields' ), 10, 2 );
		add_filter( 'attachment_fields_to_save', array( $this, 'save_protection_fields' ), 10, 2 );

		// Bulk actions.
		add_filter( 'bulk_actions-upload', array( $this, 'register_bulk_actions' ) );
		add_filter( 'handle_bulk_actions-upload', array( $this, 'handle_bulk_actions' ), 10, 3 );

		// Protection status column.
		add_filter( 'manage_media_columns', array( $this, 'add_media_columns' ) );
		add_action( 'manage_media_custom_column', array( $this, 'render_media_column' ), 10, 2 );

		// Media modal (Gutenberg / block editor).
		add_filter( 'wp_prepare_attachment_for_js', array( $this, 'add_protection_to_js' ), 10, 3 );

		// Admin notice for bulk action result.
		add_action( 'admin_notices', array( $this, 'bulk_action_notice' ) );
	}

	/**
	 * Add protection fields to the attachment edit screen.
	 *
	 * @param array   $form_fields Form fields array.
	 * @param WP_Post $post        Attachment post object.
	 * @return array
	 */
	public function add_protection_fields( $form_fields, $post ) {
		if ( 'attachment' !== $post->post_type ) {
			return $form_fields;
		}

		$access_control = URMA_Access_Control::get_instance();
		$protection     = $access_control->get_protection( $post->ID );
		$type           = $protection['protection_type'] ?? URMA_Access_Control::TYPE_PUBLIC;
		$allowed_roles  = ! empty( $protection['allowed_roles'] ) ? json_decode( $protection['allowed_roles'], true ) : array();

		$all_roles = wp_roles()->get_names();

		ob_start();
		include URMA_PLUGIN_DIR . 'admin/views/attachment-fields.php';
		$html = ob_get_clean();

		$form_fields['urma_protection'] = array(
			'label' => __( 'Protection Settings', 'secure-media-vault' ),
			'input' => 'html',
			'html'  => $html,
		);

		return $form_fields;
	}

	/**
	 * Save protection settings when attachment is updated.
	 *
	 * @param array $post       Post data.
	 * @param array $attachment Attachment data from request.
	 * @return array
	 */
	public function save_protection_fields( $post, $attachment ) {
		if ( ! isset( $attachment['urma_protection_type'] ) ) {
			return $post;
		}

		// WordPress core already handles nonce validation before applying this filter.
		// (It uses update-post_{$post_id} for AJAX, and update-post_{$post_id} or media-form for classic edits).

		if ( ! current_user_can( 'upload_files' ) ) {
			return $post;
		}

		$settings = array(
			'protection_type'  => sanitize_text_field( $attachment['urma_protection_type'] ),
			'allowed_roles'    => isset( $attachment['urma_allowed_roles'] ) ? (array) $attachment['urma_allowed_roles'] : array(),
			'password'         => isset( $attachment['urma_password'] ) ? $attachment['urma_password'] : '',
			'allowed_post_ids' => isset( $attachment['urma_allowed_post_ids'] )
				? array_filter( array_map( 'absint', explode( ',', $attachment['urma_allowed_post_ids'] ) ) )
				: array(),
		);

		URMA_Access_Control::get_instance()->save_protection( $post['ID'], $settings );

		return $post;
	}

	/**
	 * Register custom bulk actions.
	 *
	 * @param array $actions Existing bulk actions.
	 * @return array
	 */
	public function register_bulk_actions( $actions ) {
		$actions['urma_bulk_protect']           = __( 'SMV: Protect (Logged-in Only)', 'secure-media-vault' );
		$actions['urma_bulk_protect_admin']     = __( 'SMV: Protect (Admins Only)', 'secure-media-vault' );
		$actions['urma_bulk_make_public']       = __( 'SMV: Make Public', 'secure-media-vault' );
		return $actions;
	}

	/**
	 * Handle custom bulk actions.
	 *
	 * @param string $redirect_to Redirect URL.
	 * @param string $action      Bulk action name.
	 * @param array  $post_ids    Selected post IDs.
	 * @return string
	 */
	public function handle_bulk_actions( $redirect_to, $action, $post_ids ) {
		if ( ! current_user_can( 'upload_files' ) ) {
			return $redirect_to;
		}

		$settings = null;

		switch ( $action ) {
			case 'urma_bulk_protect':
				$settings = array( 'protection_type' => URMA_Access_Control::TYPE_LOGGED_IN );
				break;

			case 'urma_bulk_protect_admin':
				$settings = array(
					'protection_type' => URMA_Access_Control::TYPE_ROLES,
					'allowed_roles'   => array( 'administrator' ),
				);
				break;

			case 'urma_bulk_make_public':
				$settings = array( 'protection_type' => URMA_Access_Control::TYPE_PUBLIC );
				break;
		}

		if ( null === $settings ) {
			return $redirect_to;
		}

		$count = URMA_Access_Control::get_instance()->bulk_protect( $post_ids, $settings );

		return add_query_arg(
			array(
				'urma_bulk_done' => $count,
				'urma_action'    => sanitize_text_field( $action ),
			),
			$redirect_to
		);
	}

	/**
	 * Display bulk action result notice.
	 *
	 * @return void
	 */
	public function bulk_action_notice() {
		$screen = get_current_screen();
		if ( 'upload' !== $screen->id ) {
			return;
		}

		if ( empty( $_GET['urma_bulk_done'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$count = absint( $_GET['urma_bulk_done'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		printf(
			'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
			/* translators: %d: number of files updated */
			esc_html( sprintf( __( 'Restricted Media Access: %d file(s) protection settings updated.', 'secure-media-vault' ), $count ) )
		);
	}

	/**
	 * Add Protection column to the Media Library list view.
	 *
	 * @param array $columns Existing columns.
	 * @return array
	 */
	public function add_media_columns( $columns ) {
		$columns['urma_protection'] = __( 'Protection', 'secure-media-vault' );
		return $columns;
	}

	/**
	 * Render protection status in the custom column.
	 *
	 * @param string $column_name Column name.
	 * @param int    $post_id     Attachment post ID.
	 * @return void
	 */
	public function render_media_column( $column_name, $post_id ) {
		if ( 'urma_protection' !== $column_name ) {
			return;
		}

		$access_control = URMA_Access_Control::get_instance();
		$protection     = $access_control->get_protection( $post_id );
		$type           = $protection['protection_type'] ?? URMA_Access_Control::TYPE_PUBLIC;

		$labels = array(
			URMA_Access_Control::TYPE_PUBLIC    => array(
				'label' => __( 'Public', 'secure-media-vault' ),
				'class' => 'urma-status urma-status--public',
				'icon'  => 'dashicons-unlock',
			),
			URMA_Access_Control::TYPE_LOGGED_IN => array(
				'label' => __( 'Logged-in', 'secure-media-vault' ),
				'class' => 'urma-status urma-status--protected',
				'icon'  => 'dashicons-lock',
			),
			URMA_Access_Control::TYPE_ROLES     => array(
				'label' => __( 'By Role', 'secure-media-vault' ),
				'class' => 'urma-status urma-status--protected',
				'icon'  => 'dashicons-groups',
			),
			URMA_Access_Control::TYPE_PASSWORD  => array(
				'label' => __( 'Password', 'secure-media-vault' ),
				'class' => 'urma-status urma-status--password',
				'icon'  => 'dashicons-key',
			),
			URMA_Access_Control::TYPE_POSTS     => array(
				'label' => __( 'By Post', 'secure-media-vault' ),
				'class' => 'urma-status urma-status--protected',
				'icon'  => 'dashicons-admin-page',
			),
		);

		$info = $labels[ $type ] ?? $labels[ URMA_Access_Control::TYPE_PUBLIC ];

		printf(
			'<span class="%s"><span class="dashicons %s" aria-hidden="true"></span> %s</span>',
			esc_attr( $info['class'] ),
			esc_attr( $info['icon'] ),
			esc_html( $info['label'] )
		);
	}

	/**
	 * Add to the JS object prepared for the media modal.
	 *
	 * @param array   $response   Response data.
	 * @param WP_Post $attachment Attachment post.
	 * @return array
	 */
	public function add_protection_to_js( $response, $attachment ) {
		$protection = URMA_Access_Control::get_instance()->get_protection( $attachment->ID );
		$response['smvProtectionType'] = $protection['protection_type'] ?? URMA_Access_Control::TYPE_PUBLIC;
		$response['smvIsProtected']    = URMA_Access_Control::get_instance()->is_protected( $attachment->ID );
		return $response;
	}
}
