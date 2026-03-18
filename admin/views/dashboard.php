<?php
/**
 * Admin view: Dashboard.
 *
 * @package SecureMediaVault
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
<div class="wrap smv-wrap">
	<h1 class="smv-page-title">
		<span class="dashicons dashicons-lock"></span>
		<?php esc_html_e( 'Secure Media Vault – Dashboard', 'secure-media-vault' ); ?>
	</h1>

	<div class="smv-stats-grid">
		<div class="smv-stat-card smv-stat-card--blue">
			<div class="smv-stat-icon dashicons dashicons-lock"></div>
			<div class="smv-stat-content">
				<span class="smv-stat-number"><?php echo esc_html( number_format_i18n( $total_protected ) ); ?></span>
				<span class="smv-stat-label"><?php esc_html_e( 'Protected Files', 'secure-media-vault' ); ?></span>
			</div>
		</div>
		<div class="smv-stat-card smv-stat-card--green">
			<div class="smv-stat-icon dashicons dashicons-tickets-alt"></div>
			<div class="smv-stat-content">
				<span class="smv-stat-number"><?php echo esc_html( number_format_i18n( $total_tokens ) ); ?></span>
				<span class="smv-stat-label"><?php esc_html_e( 'Active Tokens', 'secure-media-vault' ); ?></span>
			</div>
		</div>
		<div class="smv-stat-card smv-stat-card--teal">
			<div class="smv-stat-icon dashicons dashicons-yes-alt"></div>
			<div class="smv-stat-content">
				<span class="smv-stat-number"><?php echo esc_html( number_format_i18n( $total_granted ) ); ?></span>
				<span class="smv-stat-label"><?php esc_html_e( 'Granted (7 days)', 'secure-media-vault' ); ?></span>
			</div>
		</div>
		<div class="smv-stat-card smv-stat-card--red">
			<div class="smv-stat-icon dashicons dashicons-dismiss"></div>
			<div class="smv-stat-content">
				<span class="smv-stat-number"><?php echo esc_html( number_format_i18n( $total_denied ) ); ?></span>
				<span class="smv-stat-label"><?php esc_html_e( 'Denied (7 days)', 'secure-media-vault' ); ?></span>
			</div>
		</div>
	</div>

	<div class="smv-quick-links">
		<h2><?php esc_html_e( 'Quick Actions', 'secure-media-vault' ); ?></h2>
		<div class="smv-quick-links-grid">
			<a href="<?php echo esc_url( admin_url( 'upload.php' ) ); ?>" class="smv-quick-link">
				<span class="dashicons dashicons-admin-media"></span>
				<?php esc_html_e( 'Media Library', 'secure-media-vault' ); ?>
			</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=smv-settings' ) ); ?>" class="smv-quick-link">
				<span class="dashicons dashicons-admin-settings"></span>
				<?php esc_html_e( 'Settings', 'secure-media-vault' ); ?>
			</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=smv-logs' ) ); ?>" class="smv-quick-link">
				<span class="dashicons dashicons-list-view"></span>
				<?php esc_html_e( 'Access Logs', 'secure-media-vault' ); ?>
			</a>
			<a href="<?php echo esc_url( admin_url( 'options-permalink.php' ) ); ?>" class="smv-quick-link">
				<span class="dashicons dashicons-admin-links"></span>
				<?php esc_html_e( 'Flush Permalinks', 'secure-media-vault' ); ?>
			</a>
		</div>
	</div>

	<?php if ( ! SMV_Htaccess_Manager::is_apache() ) : ?>
	<div class="smv-notice smv-notice--warning">
		<h3><?php esc_html_e( 'Nginx Detected – Manual Configuration Required', 'secure-media-vault' ); ?></h3>
		<p><?php esc_html_e( 'This server appears to use Nginx. Add the following rules to your Nginx server block to block direct file access:', 'secure-media-vault' ); ?></p>
		<pre class="smv-code-block"><?php echo esc_html( SMV_Htaccess_Manager::get_nginx_rules() ); ?></pre>
	</div>
	<?php endif; ?>

	<div class="smv-info-box">
		<h3><?php esc_html_e( 'How it Works', 'secure-media-vault' ); ?></h3>
		<ol>
			<li><?php esc_html_e( 'Set protection rules per file in the Media Library (edit any attachment).', 'secure-media-vault' ); ?></li>
			<li><?php esc_html_e( 'WordPress rewrites media URLs to secure token-based URLs.', 'secure-media-vault' ); ?></li>
			<li><?php esc_html_e( 'Tokens are HMAC-signed and expire after the configured duration.', 'secure-media-vault' ); ?></li>
			<li><?php esc_html_e( 'Access is validated on every request before the file is served.', 'secure-media-vault' ); ?></li>
		</ol>
	</div>
</div>
