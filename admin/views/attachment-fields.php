<?php
/**
 * Admin view: Attachment Protection Fields.
 *
 * Rendered inside the Media Library attachment edit screen.
 *
 * @package PTPPrivateMedia
 * @var WP_Post $post         Attachment post.
 * @var string  $type         Current protection type.
 * @var array   $allowed_roles Currently allowed roles.
 * @var array   $all_roles    All registered WP roles.
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$urma_protection_options = array(
	URMA_Access_Control::TYPE_PUBLIC    => __( 'Public (WordPress default)', 'ptp-private-media' ),
	URMA_Access_Control::TYPE_LOGGED_IN => __( 'Logged-in users only', 'ptp-private-media' ),
	URMA_Access_Control::TYPE_ROLES     => __( 'Specific user roles', 'ptp-private-media' ),
	URMA_Access_Control::TYPE_PASSWORD  => __( 'Password protected', 'ptp-private-media' ),
	URMA_Access_Control::TYPE_POSTS     => __( 'Restrict to specific posts/pages', 'ptp-private-media' ),
);
?>
<div class="urma-attachment-fields" id="urma-attachment-<?php echo esc_attr( $post->ID ); ?>">

	<p>
		<label for="urma-protection-type-<?php echo esc_attr( $post->ID ); ?>">
			<strong><?php esc_html_e( 'Access Type:', 'ptp-private-media' ); ?></strong>
		</label><br>
		<select
			name="attachments[<?php echo esc_attr( $post->ID ); ?>][urma_protection_type]"
			id="urma-protection-type-<?php echo esc_attr( $post->ID ); ?>"
			class="urma-protection-select"
			data-attachment-id="<?php echo esc_attr( $post->ID ); ?>"
		>
			<?php foreach ( $urma_protection_options as $urma_value => $urma_label ) : ?>
				<option value="<?php echo esc_attr( $urma_value ); ?>"<?php selected( $type, $urma_value ); ?>>
					<?php echo esc_html( $urma_label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
	</p>

	<!-- Roles sub-field -->
	<div class="urma-field-roles" style="display:<?php echo URMA_Access_Control::TYPE_ROLES === $type ? 'block' : 'none'; ?>">
		<label><strong><?php esc_html_e( 'Allowed Roles:', 'ptp-private-media' ); ?></strong></label><br>
		<?php foreach ( $all_roles as $urma_role_slug => $urma_role_name ) : ?>
			<label style="display:inline-block;margin-right:12px;">
				<input
					type="checkbox"
					name="attachments[<?php echo esc_attr( $post->ID ); ?>][urma_allowed_roles][]"
					value="<?php echo esc_attr( $urma_role_slug ); ?>"
					<?php checked( in_array( $urma_role_slug, (array) $allowed_roles, true ) ); ?>
				>
				<?php echo esc_html( translate_user_role( $urma_role_name ) ); ?>
			</label>
		<?php endforeach; ?>
	</div>

	<!-- Password sub-field -->
	<div class="urma-field-password" style="display:<?php echo URMA_Access_Control::TYPE_PASSWORD === $type ? 'block' : 'none'; ?>">
		<label for="urma-password-<?php echo esc_attr( $post->ID ); ?>">
			<strong><?php esc_html_e( 'New Password (leave blank to keep current):', 'ptp-private-media' ); ?></strong>
		</label><br>
		<input
			type="password"
			name="attachments[<?php echo esc_attr( $post->ID ); ?>][urma_password]"
			id="urma-password-<?php echo esc_attr( $post->ID ); ?>"
			autocomplete="new-password"
			style="width:100%;"
		>
	</div>

	<!-- Post IDs sub-field -->
	<div class="urma-field-posts" style="display:<?php echo URMA_Access_Control::TYPE_POSTS === $type ? 'block' : 'none'; ?>">
		<label for="urma-post-ids-<?php echo esc_attr( $post->ID ); ?>">
			<strong><?php esc_html_e( 'Allowed Post/Page IDs (comma-separated):', 'ptp-private-media' ); ?></strong>
		</label><br>
		<?php
		$urma_existing_post_ids = '';
		$urma_protection_data   = URMA_Access_Control::get_instance()->get_protection( $post->ID );
		if ( ! empty( $urma_protection_data['allowed_post_ids'] ) ) {
			$urma_ids               = json_decode( $urma_protection_data['allowed_post_ids'], true );
			$urma_existing_post_ids = is_array( $urma_ids ) ? implode( ', ', $urma_ids ) : '';
		}
		?>
		<input
			type="text"
			name="attachments[<?php echo esc_attr( $post->ID ); ?>][urma_allowed_post_ids]"
			id="urma-post-ids-<?php echo esc_attr( $post->ID ); ?>"
			value="<?php echo esc_attr( $urma_existing_post_ids ); ?>"
			placeholder="e.g. 42, 100, 205"
			style="width:100%;"
		>
	</div>

	<!-- Secure URL generator -->
	<?php if ( URMA_Access_Control::get_instance()->is_protected( $post->ID ) ) : ?>
	<div class="urma-secure-url-section" style="margin-top:10px;padding-top:10px;border-top:1px solid #ddd;">
		<strong><?php esc_html_e( 'Secure URL:', 'ptp-private-media' ); ?></strong><br>
		<div style="display:flex;gap:6px;align-items:center;margin-top:4px;">
			<input
				type="text"
				id="urma-secure-url-<?php echo esc_attr( $post->ID ); ?>"
				readonly
				value=""
				style="flex:1;font-size:11px;"
				placeholder="<?php esc_attr_e( 'Click Generate to create a secure URL', 'ptp-private-media' ); ?>"
			>
			<button
				type="button"
				class="button urma-generate-url"
				data-attachment-id="<?php echo esc_attr( $post->ID ); ?>"
				data-target="urma-secure-url-<?php echo esc_attr( $post->ID ); ?>"
			><?php esc_html_e( 'Generate', 'ptp-private-media' ); ?></button>
			<button
				type="button"
				class="button urma-copy-url"
				data-target="urma-secure-url-<?php echo esc_attr( $post->ID ); ?>"
			><?php esc_html_e( 'Copy', 'ptp-private-media' ); ?></button>
		</div>
		<button
			type="button"
			class="button-link urma-revoke-tokens"
			data-attachment-id="<?php echo esc_attr( $post->ID ); ?>"
			style="color:#d63638;margin-top:4px;font-size:11px;"
		><?php esc_html_e( 'Revoke all tokens for this file', 'ptp-private-media' ); ?></button>
	</div>
	<?php endif; ?>
</div>
