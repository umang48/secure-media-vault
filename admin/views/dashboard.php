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
<div class="wrap gpm-wrap">
	<h1 class="gpm-page-title">
		<span class="dashicons dashicons-lock"></span>
		<?php esc_html_e( 'Guardify Private Media – Dashboard', 'guardify-private-media' ); ?>
	</h1>

	<div class="gpm-stats-grid">
		<div class="gpm-stat-card gpm-stat-card--blue">
			<div class="gpm-stat-icon dashicons dashicons-lock"></div>
			<div class="gpm-stat-content">
				<span class="gpm-stat-number"><?php echo esc_html( number_format_i18n( $total_protected ) ); ?></span>
				<span class="gpm-stat-label"><?php esc_html_e( 'Protected Files', 'guardify-private-media' ); ?></span>
			</div>
		</div>
		<div class="gpm-stat-card gpm-stat-card--green">
			<div class="gpm-stat-icon dashicons dashicons-tickets-alt"></div>
			<div class="gpm-stat-content">
				<span class="gpm-stat-number"><?php echo esc_html( number_format_i18n( $total_tokens ) ); ?></span>
				<span class="gpm-stat-label"><?php esc_html_e( 'Active Tokens', 'guardify-private-media' ); ?></span>
			</div>
		</div>
		<div class="gpm-stat-card gpm-stat-card--teal">
			<div class="gpm-stat-icon dashicons dashicons-yes-alt"></div>
			<div class="gpm-stat-content">
				<span class="gpm-stat-number"><?php echo esc_html( number_format_i18n( $total_granted ) ); ?></span>
				<span class="gpm-stat-label"><?php esc_html_e( 'Granted (7 days)', 'guardify-private-media' ); ?></span>
			</div>
		</div>
		<div class="gpm-stat-card gpm-stat-card--red">
			<div class="gpm-stat-icon dashicons dashicons-dismiss"></div>
			<div class="gpm-stat-content">
				<span class="gpm-stat-number"><?php echo esc_html( number_format_i18n( $total_denied ) ); ?></span>
				<span class="gpm-stat-label"><?php esc_html_e( 'Denied (7 days)', 'guardify-private-media' ); ?></span>
			</div>
		</div>
	</div>

	<div class="gpm-quick-links">
		<h2><?php esc_html_e( 'Quick Actions', 'guardify-private-media' ); ?></h2>
		<div class="gpm-quick-links-grid">
			<a href="<?php echo esc_url( admin_url( 'upload.php' ) ); ?>" class="gpm-quick-link">
				<span class="dashicons dashicons-admin-media"></span>
				<?php esc_html_e( 'Media Library', 'guardify-private-media' ); ?>
			</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=gpm-settings' ) ); ?>" class="gpm-quick-link">
				<span class="dashicons dashicons-admin-settings"></span>
				<?php esc_html_e( 'Settings', 'guardify-private-media' ); ?>
			</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=gpm-logs' ) ); ?>" class="gpm-quick-link">
				<span class="dashicons dashicons-list-view"></span>
				<?php esc_html_e( 'Access Logs', 'guardify-private-media' ); ?>
			</a>
			<a href="<?php echo esc_url( admin_url( 'options-permalink.php' ) ); ?>" class="gpm-quick-link">
				<span class="dashicons dashicons-admin-links"></span>
				<?php esc_html_e( 'Flush Permalinks', 'guardify-private-media' ); ?>
			</a>
		</div>
	</div>

	<?php if ( ! GPM_Htaccess_Manager::is_apache() ) : ?>
	<div class="gpm-notice gpm-notice--warning">
		<h3><?php esc_html_e( 'Nginx Detected – Manual Configuration Required', 'guardify-private-media' ); ?></h3>
		<p><?php esc_html_e( 'This server appears to use Nginx. Add the following rules to your Nginx server block to block direct file access:', 'guardify-private-media' ); ?></p>
		<pre class="gpm-code-block"><?php echo esc_html( GPM_Htaccess_Manager::get_nginx_rules() ); ?></pre>
	</div>
	<?php endif; ?>

	<div class="gpm-info-box">
		<h3><?php esc_html_e( 'How it Works', 'guardify-private-media' ); ?></h3>
		<ol>
			<li><?php esc_html_e( 'Set protection rules per file in the Media Library (edit any attachment).', 'guardify-private-media' ); ?></li>
			<li><?php esc_html_e( 'WordPress rewrites media URLs to secure token-based URLs.', 'guardify-private-media' ); ?></li>
			<li><?php esc_html_e( 'Tokens are HMAC-signed and expire after the configured duration.', 'guardify-private-media' ); ?></li>
			<li><?php esc_html_e( 'Access is validated on every request before the file is served.', 'guardify-private-media' ); ?></li>
		</ol>
	</div>
</div>
