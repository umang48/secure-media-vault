<?php
/**
 * Admin view: Settings Page.
 *
 * @package SecureMediaVault
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap smv-wrap">
	<h1 class="smv-page-title">
		<span class="dashicons dashicons-admin-settings"></span>
		<?php esc_html_e( 'Secure Media Vault – Settings', 'secure-media-vault' ); ?>
	</h1>

	<?php settings_errors( 'smv_settings' ); ?>

	<form method="post" action="options.php">
		<?php settings_fields( SMV_Settings::OPTION_GROUP ); ?>

		<div class="smv-settings-grid">
			<div class="smv-settings-section">
				<h2><?php esc_html_e( 'General Settings', 'secure-media-vault' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php do_settings_fields( SMV_Settings::OPTION_GROUP, 'smv_general' ); ?>
				</table>
			</div>

			<div class="smv-settings-section">
				<h2><?php esc_html_e( 'Token & URL Settings', 'secure-media-vault' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php do_settings_fields( SMV_Settings::OPTION_GROUP, 'smv_token' ); ?>
				</table>
			</div>

			<div class="smv-settings-section">
				<h2><?php esc_html_e( 'SEO & Indexing Protection', 'secure-media-vault' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php do_settings_fields( SMV_Settings::OPTION_GROUP, 'smv_seo' ); ?>
				</table>
			</div>

			<div class="smv-settings-section">
				<h2><?php esc_html_e( 'Performance', 'secure-media-vault' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php do_settings_fields( SMV_Settings::OPTION_GROUP, 'smv_performance' ); ?>
				</table>
			</div>

			<div class="smv-settings-section">
				<h2><?php esc_html_e( 'Access Logging', 'secure-media-vault' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php do_settings_fields( SMV_Settings::OPTION_GROUP, 'smv_logs' ); ?>
				</table>
			</div>
		</div>

		<?php submit_button( __( 'Save Settings', 'secure-media-vault' ) ); ?>
	</form>
</div>
