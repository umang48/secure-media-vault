<?php
/**
 * Media Library Integration.
 *
 * Adds protection settings to the Media Library attachment edit screen,
 * handles bulk protect actions, and shows protection status in the grid view.
 *
 * @package SecureMediaVault
 * @since   1.0.0
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GPM_Media_Library
 */
class GPM_Media_Library {

	/**
	 * Single instance.
	 *
	 * @var GPM_Media_Library
	 */
	private static $instance = null;

	/**
	 * Get single instance.
	 *
	 * @return GPM_Media_Library
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

		$access_control = GPM_Access_Control::get_instance();
		$protection     = $access_control->get_protection( $post->ID );
		$type           = $protection['protection_type'] ?? GPM_Access_Control::TYPE_PUBLIC;
		$allowed_roles  = ! empty( $protection['allowed_roles'] ) ? json_decode( $protection['allowed_roles'], true ) : array();

		$all_roles = wp_roles()->get_names();

		ob_start();
		include GPM_PLUGIN_DIR . 'admin/views/attachment-fields.php';
		$html = ob_get_clean();

		$form_fields['gpm_protection'] = array(
			'label' => __( 'Protection Settings', 'guardify-private-media' ),
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
		if ( ! isset( $attachment['gpm_protection_type'] ) ) {
			return $post;
		}

		// WordPress core already handles nonce validation before applying this filter.
		// (It uses update-post_{$post_id} for AJAX, and update-post_{$post_id} or media-form for classic edits).

		if ( ! current_user_can( 'upload_files' ) ) {
			return $post;
		}

		$settings = array(
			'protection_type'  => sanitize_text_field( $attachment['gpm_protection_type'] ),
			'allowed_roles'    => isset( $attachment['gpm_allowed_roles'] ) ? (array) $attachment['gpm_allowed_roles'] : array(),
			'password'         => isset( $attachment['gpm_password'] ) ? $attachment['gpm_password'] : '',
			'allowed_post_ids' => isset( $attachment['gpm_allowed_post_ids'] )
				? array_filter( array_map( 'absint', explode( ',', $attachment['gpm_allowed_post_ids'] ) ) )
				: array(),
		);

		GPM_Access_Control::get_instance()->save_protection( $post['ID'], $settings );

		return $post;
	}

	/**
	 * Register custom bulk actions.
	 *
	 * @param array $actions Existing bulk actions.
	 * @return array
	 */
	public function register_bulk_actions( $actions ) {
		$actions['gpm_bulk_protect']           = __( 'SMV: Protect (Logged-in Only)', 'guardify-private-media' );
		$actions['gpm_bulk_protect_admin']     = __( 'SMV: Protect (Admins Only)', 'guardify-private-media' );
		$actions['gpm_bulk_make_public']       = __( 'SMV: Make Public', 'guardify-private-media' );
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
			case 'gpm_bulk_protect':
				$settings = array( 'protection_type' => GPM_Access_Control::TYPE_LOGGED_IN );
				break;

			case 'gpm_bulk_protect_admin':
				$settings = array(
					'protection_type' => GPM_Access_Control::TYPE_ROLES,
					'allowed_roles'   => array( 'administrator' ),
				);
				break;

			case 'gpm_bulk_make_public':
				$settings = array( 'protection_type' => GPM_Access_Control::TYPE_PUBLIC );
				break;
		}

		if ( null === $settings ) {
			return $redirect_to;
		}

		$count = GPM_Access_Control::get_instance()->bulk_protect( $post_ids, $settings );

		return add_query_arg(
			array(
				'gpm_bulk_done' => $count,
				'gpm_action'    => sanitize_text_field( $action ),
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

		if ( empty( $_GET['gpm_bulk_done'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$count = absint( $_GET['gpm_bulk_done'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		printf(
			'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
			/* translators: %d: number of files updated */
			esc_html( sprintf( __( 'Guardify Private Media: %d file(s) protection settings updated.', 'guardify-private-media' ), $count ) )
		);
	}

	/**
	 * Add Protection column to the Media Library list view.
	 *
	 * @param array $columns Existing columns.
	 * @return array
	 */
	public function add_media_columns( $columns ) {
		$columns['gpm_protection'] = __( 'Protection', 'guardify-private-media' );
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
		if ( 'gpm_protection' !== $column_name ) {
			return;
		}

		$access_control = GPM_Access_Control::get_instance();
		$protection     = $access_control->get_protection( $post_id );
		$type           = $protection['protection_type'] ?? GPM_Access_Control::TYPE_PUBLIC;

		$labels = array(
			GPM_Access_Control::TYPE_PUBLIC    => array(
				'label' => __( 'Public', 'guardify-private-media' ),
				'class' => 'gpm-status gpm-status--public',
				'icon'  => 'dashicons-unlock',
			),
			GPM_Access_Control::TYPE_LOGGED_IN => array(
				'label' => __( 'Logged-in', 'guardify-private-media' ),
				'class' => 'gpm-status gpm-status--protected',
				'icon'  => 'dashicons-lock',
			),
			GPM_Access_Control::TYPE_ROLES     => array(
				'label' => __( 'By Role', 'guardify-private-media' ),
				'class' => 'gpm-status gpm-status--protected',
				'icon'  => 'dashicons-groups',
			),
			GPM_Access_Control::TYPE_PASSWORD  => array(
				'label' => __( 'Password', 'guardify-private-media' ),
				'class' => 'gpm-status gpm-status--password',
				'icon'  => 'dashicons-key',
			),
			GPM_Access_Control::TYPE_POSTS     => array(
				'label' => __( 'By Post', 'guardify-private-media' ),
				'class' => 'gpm-status gpm-status--protected',
				'icon'  => 'dashicons-admin-page',
			),
		);

		$info = $labels[ $type ] ?? $labels[ GPM_Access_Control::TYPE_PUBLIC ];

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
		$protection = GPM_Access_Control::get_instance()->get_protection( $attachment->ID );
		$response['smvProtectionType'] = $protection['protection_type'] ?? GPM_Access_Control::TYPE_PUBLIC;
		$response['smvIsProtected']    = GPM_Access_Control::get_instance()->is_protected( $attachment->ID );
		return $response;
	}
}
