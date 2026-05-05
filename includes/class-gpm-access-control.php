<?php
/**
 * Access Control.
 *
 * Manages per-attachment protection settings, evaluates whether a given
 * request is authorized, and handles password-protected file access.
 *
 * @package SecureMediaVault
 * @since   1.0.0
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GPM_Access_Control
 */
class GPM_Access_Control {

	/**
	 * Single instance.
	 *
	 * @var GPM_Access_Control
	 */
	private static $instance = null;

	/**
	 * Protection type constants.
	 */
	const TYPE_PUBLIC        = 'public';
	const TYPE_LOGGED_IN     = 'logged_in';
	const TYPE_ROLES         = 'roles';
	const TYPE_PASSWORD      = 'password';
	const TYPE_POSTS         = 'posts';

	/**
	 * Get single instance.
	 *
	 * @return GPM_Access_Control
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
	private function __construct() {}

	/**
	 * Save protection settings for an attachment.
	 *
	 * @param int   $attachment_id   Attachment post ID.
	 * @param array $settings        Protection settings array.
	 * @return bool
	 */
	public function save_protection( $attachment_id, $settings ) {
		global $wpdb;

		$attachment_id = absint( $attachment_id );

		$protection_type = isset( $settings['protection_type'] )
			? sanitize_text_field( $settings['protection_type'] )
			: self::TYPE_PUBLIC;

		$allowed_roles = isset( $settings['allowed_roles'] ) && is_array( $settings['allowed_roles'] )
			? wp_json_encode( array_map( 'sanitize_text_field', $settings['allowed_roles'] ) )
			: null;

		$password_hash = null;
		if ( self::TYPE_PASSWORD === $protection_type && ! empty( $settings['password'] ) ) {
			$password_hash = wp_hash_password( $settings['password'] );
		}

		$allowed_post_ids = isset( $settings['allowed_post_ids'] ) && is_array( $settings['allowed_post_ids'] )
			? wp_json_encode( array_map( 'absint', $settings['allowed_post_ids'] ) )
			: null;

		$table = esc_sql( $wpdb->prefix . 'gpm_protection' );
		$data  = array(
			'attachment_id'   => $attachment_id,
			'protection_type' => $protection_type,
			'allowed_roles'   => $allowed_roles,
			'password_hash'   => $password_hash,
			'allowed_post_ids' => $allowed_post_ids,
		);
		$format = array( '%d', '%s', '%s', '%s', '%s' );

		// Check if a record already exists.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM `{$table}` WHERE attachment_id = %d LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$attachment_id
			)
		);

		if ( $existing ) {
			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$table,
				$data,
				array( 'attachment_id' => $attachment_id ),
				$format,
				array( '%d' )
			);
		} else {
			$wpdb->insert( $table, $data, $format ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		}

		// Invalidate cached protection for this attachment.
		wp_cache_delete( 'gpm_protection_' . $attachment_id, 'smv' );

		// Revoke all previously issued tokens when settings change.
		GPM_Token_Manager::get_instance()->revoke_attachment_tokens( $attachment_id );

		return true;
	}

	/**
	 * Get protection settings for an attachment.
	 *
	 * @param int $attachment_id Attachment post ID.
	 * @return array|null Protection row or null.
	 */
	public function get_protection( $attachment_id ) {
		$attachment_id = absint( $attachment_id );
		$cache_key     = 'gpm_protection_' . $attachment_id;

		$cached = wp_cache_get( $cache_key, 'smv' );
		if ( false !== $cached ) {
			return $cached;
		}

		global $wpdb;
		$table = esc_sql( $wpdb->prefix . 'gpm_protection' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM `{$table}` WHERE attachment_id = %d LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$attachment_id
			),
			ARRAY_A
		);

		$result = $row ?: array( 'protection_type' => self::TYPE_PUBLIC );

		wp_cache_set( $cache_key, $result, 'smv', 300 );

		return $result;
	}

	/**
	 * Determine if the current user / request is allowed access.
	 *
	 * @param int    $attachment_id Attachment post ID.
	 * @param string $password      Optional submitted password.
	 * @return bool
	 */
	public function is_access_allowed( $attachment_id, $password = '' ) {
		$protection = $this->get_protection( $attachment_id );
		$type       = $protection['protection_type'] ?? self::TYPE_PUBLIC;

		switch ( $type ) {
			case self::TYPE_PUBLIC:
				return true;

			case self::TYPE_LOGGED_IN:
				return is_user_logged_in();

			case self::TYPE_ROLES:
				return $this->check_role_access( $protection );

			case self::TYPE_PASSWORD:
				return $this->check_password_access( $protection, $password );

			case self::TYPE_POSTS:
				return $this->check_post_access( $protection );

			default:
				return false;
		}
	}

	/**
	 * Check if current user has at least one of the required roles.
	 *
	 * @param array $protection Protection record.
	 * @return bool
	 */
	private function check_role_access( $protection ) {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		if ( empty( $protection['allowed_roles'] ) ) {
			return true;
		}

		$allowed_roles = json_decode( $protection['allowed_roles'], true );
		if ( empty( $allowed_roles ) ) {
			return true;
		}

		$current_user = wp_get_current_user();
		foreach ( $allowed_roles as $role ) {
			if ( in_array( $role, (array) $current_user->roles, true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if the submitted password matches the stored hash.
	 *
	 * @param array  $protection Protection record.
	 * @param string $password   Submitted plain-text password.
	 * @return bool
	 */
	private function check_password_access( $protection, $password ) {
		if ( empty( $protection['password_hash'] ) ) {
			return true;
		}

		if ( empty( $password ) ) {
			// Allow already-authenticated sessions via transient.
			$transient_key = 'gpm_pw_' . get_current_user_id() . '_' . $protection['attachment_id'];
			return (bool) get_transient( $transient_key );
		}

		$valid = wp_check_password( $password, $protection['password_hash'] );
		if ( $valid ) {
			// Remember for 30 minutes.
			$transient_key = 'gpm_pw_' . get_current_user_id() . '_' . $protection['attachment_id'];
			set_transient( $transient_key, true, 30 * MINUTE_IN_SECONDS );
		}

		return $valid;
	}

	/**
	 * Check if user accessed a file via one of the allowed posts.
	 *
	 * @param array $protection Protection record.
	 * @return bool
	 */
	private function check_post_access( $protection ) {
		if ( empty( $protection['allowed_post_ids'] ) ) {
			return false;
		}

		$allowed = json_decode( $protection['allowed_post_ids'], true );
		if ( empty( $allowed ) ) {
			return false;
		}

		// Check referer post or current post.
		$referer = wp_get_referer();
		if ( $referer ) {
			$post_id = url_to_postid( $referer );
			if ( $post_id && in_array( $post_id, $allowed, true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if an attachment is protected (non-public).
	 *
	 * @param int $attachment_id Attachment post ID.
	 * @return bool
	 */
	public function is_protected( $attachment_id ) {
		$protection = $this->get_protection( $attachment_id );
		return self::TYPE_PUBLIC !== ( $protection['protection_type'] ?? self::TYPE_PUBLIC );
	}

	/**
	 * Bulk update protection for multiple attachments.
	 *
	 * @param array $attachment_ids Array of attachment post IDs.
	 * @param array $settings       Protection settings to apply.
	 * @return int Number of records updated.
	 */
	public function bulk_protect( $attachment_ids, $settings ) {
		$count = 0;
		foreach ( $attachment_ids as $id ) {
			if ( $this->save_protection( absint( $id ), $settings ) ) {
				++$count;
			}
		}
		return $count;
	}
}
