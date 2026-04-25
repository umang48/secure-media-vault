<?php
/**
 * Token Manager.
 *
 * Generates, validates, and purges HMAC-signed, time-limited tokens
 * used to serve protected media files securely.
 *
 * @package SecureMediaVault
 * @since   1.0.0
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SMV_Token_Manager
 */
class SMV_Token_Manager {

	/**
	 * Single instance.
	 *
	 * @var SMV_Token_Manager
	 */
	private static $instance = null;

	/**
	 * HMAC algorithm.
	 *
	 * @var string
	 */
	const HASH_ALGO = 'sha256';

	/**
	 * Get single instance.
	 *
	 * @return SMV_Token_Manager
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor: register cron for token cleanup.
	 */
	private function __construct() {
		add_action( 'smv_cleanup_expired_tokens', array( $this, 'cleanup_expired_tokens' ) );

		if ( ! wp_next_scheduled( 'smv_cleanup_expired_tokens' ) ) {
			wp_schedule_event( time(), 'hourly', 'smv_cleanup_expired_tokens' );
		}
	}

	/**
	 * Generate a new signed token for an attachment.
	 *
	 * @param int      $attachment_id Attachment post ID.
	 * @param int|null $user_id       WP user ID or null for anonymous.
	 * @param string   $ip_address    Requester IP address.
	 * @return string  URL-safe base64-encoded token.
	 */
	public function generate_token( $attachment_id, $user_id = null, $ip_address = '' ) {
		global $wpdb;

		$expiry_seconds = absint( get_option( 'smv_token_expiry', 3600 ) );
		$expires_at     = gmdate( 'Y-m-d H:i:s', time() + $expiry_seconds );

		// Build token payload.
		$nonce   = wp_generate_password( 16, false );
		$payload = implode(
			'|',
			array(
				$attachment_id,
				$user_id ?? 0,
				$nonce,
				$expires_at,
			)
		);

		// HMAC-sign the payload using WordPress secret keys.
		$secret = wp_salt( 'auth' );
		$token  = hash_hmac( self::HASH_ALGO, $payload, $secret );

		// Store in DB so we can revoke individual tokens.
		$table = $wpdb->prefix . 'smv_tokens';
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$table,
			array(
				'token'         => $token,
				'attachment_id' => $attachment_id,
				'user_id'       => $user_id,
				'ip_address'    => sanitize_text_field( $ip_address ),
				'expires_at'    => $expires_at,
			),
			array( '%s', '%d', '%d', '%s', '%s' )
		);

		return $token;
	}

	/**
	 * Validate a token for an attachment.
	 *
	 * @param string $token         The token to validate.
	 * @param int    $attachment_id Attachment post ID.
	 * @param string $ip_address    Requester IP.
	 * @return bool True if token is valid and unexpired.
	 */
	public function validate_token( $token, $attachment_id, $ip_address = '' ) {
		global $wpdb;

		$token         = sanitize_text_field( $token );
		$attachment_id = absint( $attachment_id );

		$table     = esc_sql( $wpdb->prefix . 'smv_tokens' );
		$cache_key = 'smv_token_' . $token . '_' . $attachment_id;
		$cached    = wp_cache_get( $cache_key, 'smv' );

		if ( false !== $cached ) {
			$row = $cached;
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM `{$table}` WHERE token = %s AND attachment_id = %d AND expires_at > %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$token,
					$attachment_id,
					current_time( 'mysql', true )
				)
			);
			wp_cache_set( $cache_key, $row, 'smv', 60 );
		}

		if ( ! $row ) {
			return false;
		}

		// Optional IP validation.
		if ( get_option( 'smv_ip_validation', false ) ) {
			if ( $row->ip_address && $row->ip_address !== sanitize_text_field( $ip_address ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Revoke a specific token.
	 *
	 * @param string $token Token string.
	 * @return bool
	 */
	public function revoke_token( $token ) {
		global $wpdb;

		$table = $wpdb->prefix . 'smv_tokens';

		$deleted = $wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$table,
			array( 'token' => sanitize_text_field( $token ) ),
			array( '%s' )
		);

		if ( $deleted ) {
			wp_cache_delete( 'smv_token_' . sanitize_text_field( $token ), 'smv' );
		}

		return (bool) $deleted;
	}

	/**
	 * Revoke all tokens for an attachment.
	 *
	 * @param int $attachment_id Attachment post ID.
	 * @return int Number of rows deleted.
	 */
	public function revoke_attachment_tokens( $attachment_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'smv_tokens';

		$count = (int) $wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$table,
			array( 'attachment_id' => absint( $attachment_id ) ),
			array( '%d' )
		);

		if ( $count ) {
			wp_cache_delete( 'smv_protection_' . absint( $attachment_id ), 'smv' );
		}

		return $count;
	}

	/**
	 * Generate a full secure URL for an attachment.
	 *
	 * @param int      $attachment_id Attachment post ID.
	 * @param int|null $user_id       WP user ID.
	 * @return string Secure URL.
	 */
	public function get_secure_url( $attachment_id, $user_id = null ) {
		$ip    = $this->get_client_ip();
		$token = $this->generate_token( $attachment_id, $user_id, $ip );

		return home_url( "/protected-media/{$attachment_id}/{$token}/" );
	}

	/**
	 * Delete all expired tokens from the database.
	 *
	 * @return void
	 */
	public function cleanup_expired_tokens() {
		global $wpdb;

		$table = esc_sql( $wpdb->prefix . 'smv_tokens' );
		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"DELETE FROM `{$table}` WHERE expires_at < %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				current_time( 'mysql', true )
			)
		);
		wp_cache_flush_group( 'smv' );
	}

	/**
	 * Get the client's real IP address.
	 *
	 * @return string
	 */
	public function get_client_ip() {
		$ip_keys = array(
			'HTTP_CF_CONNECTING_IP', // Cloudflare.
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_REAL_IP',
			'REMOTE_ADDR',
		);

		foreach ( $ip_keys as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$ip_list = explode( ',', sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) ) );
				$ip      = trim( $ip_list[0] );
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}

		return '0.0.0.0';
	}
}
