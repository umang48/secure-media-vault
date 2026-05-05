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
 * Class GPM_File_Handler
 */
class GPM_File_Handler {

	/**
	 * Single instance.
	 *
	 * @var GPM_File_Handler
	 */
	private static $instance = null;

	/**
	 * Get single instance.
	 *
	 * @return GPM_File_Handler
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
		$file_id = get_query_var( 'gpm_file_id' );
		$token   = get_query_var( 'gpm_token' );

		if ( empty( $file_id ) || empty( $token ) ) {
			return;
		}

		$attachment_id = absint( $file_id );
		$token         = sanitize_text_field( $token );

		// Log access attempt.
		$this->log_access( $attachment_id, 'attempt' );

		// Validate token.
		$token_manager = GPM_Token_Manager::get_instance();
		$ip            = $token_manager->get_client_ip();

		if ( ! $token_manager->validate_token( $token, $attachment_id, $ip ) ) {
			$this->log_access( $attachment_id, 'denied_invalid_token' );
			$this->deny_access( __( 'Invalid or expired access token.', 'guardify-private-media' ) );
			return;
		}

		// Check access control rules.
		$password      = isset( $_POST['gpm_password'] ) ? sanitize_text_field( wp_unslash( $_POST['gpm_password'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$access_control = GPM_Access_Control::get_instance();

		if ( ! $access_control->is_access_allowed( $attachment_id, $password ) ) {
			$protection = $access_control->get_protection( $attachment_id );
			if ( 'password' === ( $protection['protection_type'] ?? '' ) ) {
				$this->serve_password_form( $attachment_id, $token );
				return;
			}
			$this->log_access( $attachment_id, 'denied_access_control' );
			$this->deny_access( __( 'You do not have permission to access this file.', 'guardify-private-media' ) );
			return;
		}

		// Check hotlink protection.
		if ( get_option( 'gpm_hotlink_protection', true ) ) {
			if ( $this->is_hotlink() ) {
				$this->log_access( $attachment_id, 'denied_hotlink' );
				$this->deny_access( __( 'Hotlinking is not allowed.', 'guardify-private-media' ) );
				return;
			}
		}

		// Get attachment file path.
		$file_path = get_attached_file( $attachment_id );
		if ( ! $file_path || ! file_exists( $file_path ) ) {
			$this->log_access( $attachment_id, 'denied_file_not_found' );
			wp_die( esc_html__( 'File not found.', 'guardify-private-media' ), '', array( 'response' => 404 ) );
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

		$stream_threshold_mb = absint( get_option( 'gpm_stream_threshold', 10 ) );
		$use_streaming       = get_option( 'gpm_stream_large_files', true );

		if ( $use_streaming && $file_size > $stream_threshold_mb * 1024 * 1024 ) {
			$this->stream_file( $file_path, $file_size );
		} else {
			header( 'Content-Length: ' . $file_size );
			readfile( $file_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
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
		$fp         = fopen( $file_path, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen

		if ( ! $fp ) {
			wp_die( esc_html__( 'Unable to read file.', 'guardify-private-media' ), '', array( 'response' => 500 ) );
		}

		fseek( $fp, $start );
		$bytes_remaining = $length;

		while ( ! feof( $fp ) && $bytes_remaining > 0 && ! connection_aborted() ) {
			$buffer           = fread( $fp, min( $chunk_size, $bytes_remaining ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread
			$bytes_remaining -= strlen( $buffer );
			echo $buffer; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			flush();
		}

		fclose( $fp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
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
		if ( ! GPM_Access_Control::get_instance()->is_protected( $attachment_id ) ) {
			return $url;
		}

		$user_id = is_user_logged_in() ? get_current_user_id() : null;

		return GPM_Token_Manager::get_instance()->get_secure_url( $attachment_id, $user_id );
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
			esc_html__( 'Access Denied', 'guardify-private-media' ),
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
			<title><?php esc_html_e( 'Protected File – Enter Password', 'guardify-private-media' ); ?></title>
			<?php
			wp_enqueue_style( 'gpm-password-form', GPM_PLUGIN_URL . 'assets/css/password-form.css', array(), GPM_VERSION );
			wp_print_styles( 'gpm-password-form' );
			?>
		</head>
		<body>
		<div class="gpm-form">
			<h2><?php esc_html_e( 'This file is password protected', 'guardify-private-media' ); ?></h2>
			<form method="post" action="<?php echo esc_url( $action_url ); ?>">
				<?php wp_nonce_field( 'gpm_password_access_' . $attachment_id, 'gpm_nonce' ); ?>
				<label for="gpm_password"><?php esc_html_e( 'Enter password to access this file:', 'guardify-private-media' ); ?></label>
				<input type="password" id="gpm_password" name="gpm_password" required autocomplete="current-password">
				<button type="submit"><?php esc_html_e( 'Access File', 'guardify-private-media' ); ?></button>
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
		if ( ! get_option( 'gpm_log_access', true ) ) {
			return;
		}

		global $wpdb;

		$table = $wpdb->prefix . 'gpm_access_logs';
		$ip    = GPM_Token_Manager::get_instance()->get_client_ip();

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
