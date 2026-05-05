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
<div class="wrap gpm-wrap">
	<h1 class="gpm-page-title">
		<span class="dashicons dashicons-admin-settings"></span>
		<?php esc_html_e( 'Guardify Private Media – Settings', 'guardify-private-media' ); ?>
	</h1>

	<?php settings_errors( 'gpm_settings' ); ?>

	<form method="post" action="options.php">
		<?php settings_fields( GPM_Settings::OPTION_GROUP ); ?>

		<div class="gpm-settings-grid">
			<div class="gpm-settings-section">
				<h2><?php esc_html_e( 'General Settings', 'guardify-private-media' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php do_settings_fields( GPM_Settings::OPTION_GROUP, 'gpm_general' ); ?>
				</table>
			</div>

			<div class="gpm-settings-section">
				<h2><?php esc_html_e( 'Token & URL Settings', 'guardify-private-media' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php do_settings_fields( GPM_Settings::OPTION_GROUP, 'gpm_token' ); ?>
				</table>
			</div>

			<div class="gpm-settings-section">
				<h2><?php esc_html_e( 'SEO & Indexing Protection', 'guardify-private-media' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php do_settings_fields( GPM_Settings::OPTION_GROUP, 'gpm_seo' ); ?>
				</table>
			</div>

			<div class="gpm-settings-section">
				<h2><?php esc_html_e( 'Performance', 'guardify-private-media' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php do_settings_fields( GPM_Settings::OPTION_GROUP, 'gpm_performance' ); ?>
				</table>
			</div>

			<div class="gpm-settings-section">
				<h2><?php esc_html_e( 'Access Logging', 'guardify-private-media' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php do_settings_fields( GPM_Settings::OPTION_GROUP, 'gpm_logs' ); ?>
				</table>
			</div>
		</div>

		<?php submit_button( __( 'Save Settings', 'guardify-private-media' ) ); ?>
	</form>
</div>
