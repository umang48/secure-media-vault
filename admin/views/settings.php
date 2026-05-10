<?php
/**
 * Admin view: Settings Page.
 *
 * @package UmangRestrictedMediaAccess
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap urma-wrap">
	<h1 class="urma-page-title">
		<span class="dashicons dashicons-admin-settings"></span>
		<?php esc_html_e( 'Umang Restricted Media Access – Settings', 'secure-media-vault' ); ?>
	</h1>

	<?php settings_errors( 'urma_settings' ); ?>

	<form method="post" action="options.php">
		<?php settings_fields( URMA_Settings::OPTION_GROUP ); ?>

		<div class="urma-settings-grid">
			<div class="urma-settings-section">
				<h2><?php esc_html_e( 'General Settings', 'secure-media-vault' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php do_settings_fields( URMA_Settings::OPTION_GROUP, 'urma_general' ); ?>
				</table>
			</div>

			<div class="urma-settings-section">
				<h2><?php esc_html_e( 'Token & URL Settings', 'secure-media-vault' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php do_settings_fields( URMA_Settings::OPTION_GROUP, 'urma_token' ); ?>
				</table>
			</div>

			<div class="urma-settings-section">
				<h2><?php esc_html_e( 'SEO & Indexing Protection', 'secure-media-vault' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php do_settings_fields( URMA_Settings::OPTION_GROUP, 'urma_seo' ); ?>
				</table>
			</div>

			<div class="urma-settings-section">
				<h2><?php esc_html_e( 'Performance', 'secure-media-vault' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php do_settings_fields( URMA_Settings::OPTION_GROUP, 'urma_performance' ); ?>
				</table>
			</div>

			<div class="urma-settings-section">
				<h2><?php esc_html_e( 'Access Logging', 'secure-media-vault' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php do_settings_fields( URMA_Settings::OPTION_GROUP, 'urma_logs' ); ?>
				</table>
			</div>
		</div>

		<?php submit_button( __( 'Save Settings', 'secure-media-vault' ) ); ?>
	</form>
</div>
