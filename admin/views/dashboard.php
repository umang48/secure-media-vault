<?php
/**
 * Admin view: Dashboard.
 *
 * @package PTPPrivateMedia
 * @var int $total_protected
 * @var int $total_tokens
 * @var int $total_granted
 * @var int $total_denied
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap urma-wrap">
	<h1 class="urma-page-title">
		<span class="dashicons dashicons-lock"></span>
		<?php esc_html_e( 'PTP Private Media – Dashboard', 'ptp-private-media' ); ?>
	</h1>

	<div class="urma-stats-grid">
		<div class="urma-stat-card urma-stat-card--blue">
			<div class="urma-stat-icon dashicons dashicons-lock"></div>
			<div class="urma-stat-content">
				<span class="urma-stat-number"><?php echo esc_html( number_format_i18n( $total_protected ) ); ?></span>
				<span class="urma-stat-label"><?php esc_html_e( 'Protected Files', 'ptp-private-media' ); ?></span>
			</div>
		</div>
		<div class="urma-stat-card urma-stat-card--green">
			<div class="urma-stat-icon dashicons dashicons-tickets-alt"></div>
			<div class="urma-stat-content">
				<span class="urma-stat-number"><?php echo esc_html( number_format_i18n( $total_tokens ) ); ?></span>
				<span class="urma-stat-label"><?php esc_html_e( 'Active Tokens', 'ptp-private-media' ); ?></span>
			</div>
		</div>
		<div class="urma-stat-card urma-stat-card--teal">
			<div class="urma-stat-icon dashicons dashicons-yes-alt"></div>
			<div class="urma-stat-content">
				<span class="urma-stat-number"><?php echo esc_html( number_format_i18n( $total_granted ) ); ?></span>
				<span class="urma-stat-label"><?php esc_html_e( 'Granted (7 days)', 'ptp-private-media' ); ?></span>
			</div>
		</div>
		<div class="urma-stat-card urma-stat-card--red">
			<div class="urma-stat-icon dashicons dashicons-dismiss"></div>
			<div class="urma-stat-content">
				<span class="urma-stat-number"><?php echo esc_html( number_format_i18n( $total_denied ) ); ?></span>
				<span class="urma-stat-label"><?php esc_html_e( 'Denied (7 days)', 'ptp-private-media' ); ?></span>
			</div>
		</div>
	</div>

	<div class="urma-quick-links">
		<h2><?php esc_html_e( 'Quick Actions', 'ptp-private-media' ); ?></h2>
		<div class="urma-quick-links-grid">
			<a href="<?php echo esc_url( admin_url( 'upload.php' ) ); ?>" class="urma-quick-link">
				<span class="dashicons dashicons-admin-media"></span>
				<?php esc_html_e( 'Media Library', 'ptp-private-media' ); ?>
			</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=urma-settings' ) ); ?>" class="urma-quick-link">
				<span class="dashicons dashicons-admin-settings"></span>
				<?php esc_html_e( 'Settings', 'ptp-private-media' ); ?>
			</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=urma-logs' ) ); ?>" class="urma-quick-link">
				<span class="dashicons dashicons-list-view"></span>
				<?php esc_html_e( 'Access Logs', 'ptp-private-media' ); ?>
			</a>
			<a href="<?php echo esc_url( admin_url( 'options-permalink.php' ) ); ?>" class="urma-quick-link">
				<span class="dashicons dashicons-admin-links"></span>
				<?php esc_html_e( 'Flush Permalinks', 'ptp-private-media' ); ?>
			</a>
		</div>
	</div>

	<?php if ( ! URMA_Htaccess_Manager::is_apache() ) : ?>
	<div class="urma-notice urma-notice--warning">
		<h3><?php esc_html_e( 'Nginx Detected – Manual Configuration Required', 'ptp-private-media' ); ?></h3>
		<p><?php esc_html_e( 'This server appears to use Nginx. Add the following rules to your Nginx server block to block direct file access:', 'ptp-private-media' ); ?></p>
		<pre class="urma-code-block"><?php echo esc_html( URMA_Htaccess_Manager::get_nginx_rules() ); ?></pre>
	</div>
	<?php endif; ?>

	<div class="urma-info-box">
		<h3><?php esc_html_e( 'How it Works', 'ptp-private-media' ); ?></h3>
		<ol>
			<li><?php esc_html_e( 'Set protection rules per file in the Media Library (edit any attachment).', 'ptp-private-media' ); ?></li>
			<li><?php esc_html_e( 'WordPress rewrites media URLs to secure token-based URLs.', 'ptp-private-media' ); ?></li>
			<li><?php esc_html_e( 'Tokens are HMAC-signed and expire after the configured duration.', 'ptp-private-media' ); ?></li>
			<li><?php esc_html_e( 'Access is validated on every request before the file is served.', 'ptp-private-media' ); ?></li>
		</ol>
	</div>
</div>
