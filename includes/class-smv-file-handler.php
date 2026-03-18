<?php
/**
 * File Handler.
 *
 * Intercepts protected-media requests routed through WordPress rewrite rules,
 * validates access tokens, streams the file securely, and adds X-Robots-Tag
 * headers. Supports chunked streaming for large files.
 *
 * @package SecureMediaVault
 * @since   1.0.0
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SMV_File_Handler
 */
class SMV_File_Handler {

	/**
	 * Single instance.
	 *
	 * @var SMV_File_Handler
	 */
	private static $instance = null;

	/**
	 * Get single instance.
	 *
	 * @return SMV_File_Handler
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
		add_action( 'template_redirect', array( $this, 'handle_secure_request' ), 1 );
		add_filter( 'wp_get_attachment_url', array( $this, 'maybe_replace_url' ), 10, 2 );
	}

	/**
	 * Handle the secure media request once WordPress routing runs.
	 *
	 * @return void
	 */
	public function handle_secure_request() {
		$file_id = get_query_var( 'smv_file_id' );
		$token   = get_query_var( 'smv_token' );

		if ( empty( $file_id ) || empty( $token ) ) {
			return;
		}

		$attachment_id = absint( $file_id );
		$token         = sanitize_text_field( $token );

		// Log access attempt.
		$this->log_access( $attachment_id, 'attempt' );

		// Validate token.
		$token_manager = SMV_Token_Manager::get_instance();
		$ip            = $token_manager->get_client_ip();

		if ( ! $token_manager->validate_token( $token, $attachment_id, $ip ) ) {
			$this->log_access( $attachment_id, 'denied_invalid_token' );
			$this->deny_access( __( 'Invalid or expired access token.', 'secure-media-vault' ) );
			return;
		}

		// Check access control rules.
		$password      = isset( $_POST['smv_password'] ) ? sanitize_text_field( wp_unslash( $_POST['smv_password'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$access_control = SMV_Access_Control::get_instance();

		if ( ! $access_control->is_access_allowed( $attachment_id, $password ) ) {
			$protection = $access_control->get_protection( $attachment_id );
			if ( 'password' === ( $protection['protection_type'] ?? '' ) ) {
				$this->serve_password_form( $attachment_id, $token );
				return;
			}
			$this->log_access( $attachment_id, 'denied_access_control' );
			$this->deny_access( __( 'You do not have permission to access this file.', 'secure-media-vault' ) );
			return;
		}

		// Check hotlink protection.
		if ( get_option( 'smv_hotlink_protection', true ) ) {
			if ( $this->is_hotlink() ) {
				$this->log_access( $attachment_id, 'denied_hotlink' );
				$this->deny_access( __( 'Hotlinking is not allowed.', 'secure-media-vault' ) );
				return;
			}
		}

		// Get attachment file path.
		$file_path = get_attached_file( $attachment_id );
		if ( ! $file_path || ! file_exists( $file_path ) ) {
			$this->log_access( $attachment_id, 'denied_file_not_found' );
			wp_die( esc_html__( 'File not found.', 'secure-media-vault' ), '', array( 'response' => 404 ) );
			return;
		}

		$this->log_access( $attachment_id, 'granted' );

		// Serve the file.
		$this->serve_file( $file_path, $attachment_id );
	}

	/**
	 * Serve the file to the browser, using chunked streaming for large files.
	 *
	 * @param string $file_path     Absolute path to the file.
	 * @param int    $attachment_id Attachment post ID.
	 * @return void
	 */
	private function serve_file( $file_path, $attachment_id ) {
		// Ensure no output buffering.
		while ( ob_get_level() ) {
			ob_end_clean();
		}

		$mime_type = get_post_mime_type( $attachment_id );
		$file_size = filesize( $file_path );
		$file_name = basename( $file_path );

		// Security and SEO headers.
		header( 'X-Robots-Tag: noindex, nofollow', true );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Content-Security-Policy: default-src \'none\'' );
		header( 'Cache-Control: private, no-store, no-cache, must-revalidate' );
		header( 'Pragma: no-cache' );
		header( 'Content-Type: ' . $mime_type );
		header( 'Content-Disposition: inline; filename="' . esc_attr( $file_name ) . '"' );

		$stream_threshold_mb = absint( get_option( 'smv_stream_threshold', 10 ) );
		$use_streaming       = get_option( 'smv_stream_large_files', true );

		if ( $use_streaming && $file_size > $stream_threshold_mb * 1024 * 1024 ) {
			$this->stream_file( $file_path, $file_size );
		} else {
			header( 'Content-Length: ' . $file_size );
			readfile( $file_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_readfile
		}

		exit;
	}

	/**
	 * Stream a large file using HTTP Range support.
	 *
	 * @param string $file_path Absolute path to the file.
	 * @param int    $file_size File size in bytes.
	 * @return void
	 */
	private function stream_file( $file_path, $file_size ) {
		$start  = 0;
		$end    = $file_size - 1;
		$length = $file_size;

		// Support HTTP Range requests (for video/audio seeking).
		if ( isset( $_SERVER['HTTP_RANGE'] ) ) {
			$range = sanitize_text_field( wp_unslash( $_SERVER['HTTP_RANGE'] ) );
			list( , $range_spec ) = explode( '=', $range, 2 );
			list( $start, $range_end ) = explode( '-', $range_spec, 2 );
			$start    = (int) $start;
			$end      = $range_end !== '' ? (int) $range_end : $file_size - 1;
			$length   = $end - $start + 1;

			header( 'HTTP/1.1 206 Partial Content' );
			header( "Content-Range: bytes {$start}-{$end}/{$file_size}" );
		}

		header( 'Accept-Ranges: bytes' );
		header( 'Content-Length: ' . $length );

		$chunk_size = 1024 * 1024; // 1 MB chunks.
		$fp         = fopen( $file_path, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fopen

		if ( ! $fp ) {
			wp_die( esc_html__( 'Unable to read file.', 'secure-media-vault' ), '', array( 'response' => 500 ) );
		}

		fseek( $fp, $start );
		$bytes_remaining = $length;

		while ( ! feof( $fp ) && $bytes_remaining > 0 && ! connection_aborted() ) {
			$buffer           = fread( $fp, min( $chunk_size, $bytes_remaining ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fread
			$bytes_remaining -= strlen( $buffer );
			echo $buffer; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			flush();
		}

		fclose( $fp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fclose
	}

	/**
	 * Maybe replace the attachment URL with a secure one.
	 *
	 * Hooks into wp_get_attachment_url so all WordPress-generated attachment
	 * URLs for protected files automatically become secure URLs.
	 *
	 * @param string $url           Original attachment URL.
	 * @param int    $attachment_id Attachment post ID.
	 * @return string
	 */
	public function maybe_replace_url( $url, $attachment_id ) {
		if ( ! SMV_Access_Control::get_instance()->is_protected( $attachment_id ) ) {
			return $url;
		}

		$user_id = is_user_logged_in() ? get_current_user_id() : null;

		return SMV_Token_Manager::get_instance()->get_secure_url( $attachment_id, $user_id );
	}

	/**
	 * Detect hotlink attempts by checking the HTTP Referer.
	 *
	 * @return bool True if request is a hotlink from an external domain.
	 */
	private function is_hotlink() {
		if ( empty( $_SERVER['HTTP_REFERER'] ) ) {
			// No referer could be a direct request OR browser not sending referer; allow it.
			return false;
		}

		$referer     = esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) );
		$referer_host = wp_parse_url( $referer, PHP_URL_HOST );
		$site_host    = wp_parse_url( home_url(), PHP_URL_HOST );

		return $referer_host && $referer_host !== $site_host;
	}

	/**
	 * Send a 403 Forbidden response with an error message.
	 *
	 * @param string $message Human-readable error message.
	 * @return void
	 */
	private function deny_access( $message ) {
		wp_die(
			esc_html( $message ),
			esc_html__( 'Access Denied', 'secure-media-vault' ),
			array( 'response' => 403 )
		);
	}

	/**
	 * Render a simple inline password form for password-protected files.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $token         Current token.
	 * @return void
	 */
	private function serve_password_form( $attachment_id, $token ) {
		$action_url = home_url( "/protected-media/{$attachment_id}/{$token}/" );
		?>
		<!DOCTYPE html>
		<html lang="<?php echo esc_attr( get_bloginfo( 'language' ) ); ?>">
		<head>
			<meta charset="UTF-8">
			<meta name="robots" content="noindex, nofollow">
			<title><?php esc_html_e( 'Protected File – Enter Password', 'secure-media-vault' ); ?></title>
			<style>
				body { font-family: -apple-system, BlinkMacSystemFont, sans-serif; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; background: #f0f0f1; }
				.smv-form { background: #fff; padding: 2rem; border-radius: 8px; box-shadow: 0 2px 20px rgba(0,0,0,.12); max-width: 360px; width: 100%; }
				h2 { margin: 0 0 1rem; font-size: 1.25rem; color: #1d2327; }
				label { display: block; font-size: .875rem; margin-bottom: .35rem; color: #3c434a; }
				input[type=password] { width: 100%; padding: .5rem .75rem; border: 1px solid #c3c4c7; border-radius: 4px; font-size: 1rem; box-sizing: border-box; }
				button { margin-top: 1rem; width: 100%; padding: .6rem; background: #2271b1; color: #fff; border: none; border-radius: 4px; font-size: 1rem; cursor: pointer; }
				button:hover { background: #135e96; }
				.smv-error { color: #d63638; font-size: .875rem; margin-top: .5rem; }
			</style>
		</head>
		<body>
		<div class="smv-form">
			<h2><?php esc_html_e( 'This file is password protected', 'secure-media-vault' ); ?></h2>
			<form method="post" action="<?php echo esc_url( $action_url ); ?>">
				<?php wp_nonce_field( 'smv_password_access_' . $attachment_id, 'smv_nonce' ); ?>
				<label for="smv_password"><?php esc_html_e( 'Enter password to access this file:', 'secure-media-vault' ); ?></label>
				<input type="password" id="smv_password" name="smv_password" required autocomplete="current-password">
				<button type="submit"><?php esc_html_e( 'Access File', 'secure-media-vault' ); ?></button>
			</form>
		</div>
		</body>
		</html>
		<?php
		exit;
	}

	/**
	 * Log a file access event.
	 *
	 * @param int    $attachment_id Attachment post ID.
	 * @param string $status        Access status string.
	 * @return void
	 */
	private function log_access( $attachment_id, $status ) {
		if ( ! get_option( 'smv_log_access', true ) ) {
			return;
		}

		global $wpdb;

		$table = $wpdb->prefix . 'smv_access_logs';
		$ip    = SMV_Token_Manager::get_instance()->get_client_ip();

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$table,
			array(
				'attachment_id' => absint( $attachment_id ),
				'user_id'       => is_user_logged_in() ? get_current_user_id() : null,
				'ip_address'    => $ip,
				'status'        => sanitize_text_field( $status ),
			),
			array( '%d', '%d', '%s', '%s' )
		);
	}
}
