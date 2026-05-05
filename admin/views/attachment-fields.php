<?php
/**
 * Admin view: Attachment Protection Fields.
 *
 * Rendered inside the Media Library attachment edit screen.
 *
 * @package SecureMediaVault
 * @var WP_Post $post         Attachment post.
 * @var string  $type         Current protection type.
 * @var array   $allowed_roles Currently allowed roles.
 * @var array   $all_roles    All registered WP roles.
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$protection_options = array(
	GPM_Access_Control::TYPE_PUBLIC    => __( 'Public (WordPress default)', 'guardify-private-media' ),
	GPM_Access_Control::TYPE_LOGGED_IN => __( 'Logged-in users only', 'guardify-private-media' ),
	GPM_Access_Control::TYPE_ROLES     => __( 'Specific user roles', 'guardify-private-media' ),
	GPM_Access_Control::TYPE_PASSWORD  => __( 'Password protected', 'guardify-private-media' ),
	GPM_Access_Control::TYPE_POSTS     => __( 'Restrict to specific posts/pages', 'guardify-private-media' ),
);
?>
<div class="gpm-attachment-fields" id="gpm-attachment-<?php echo esc_attr( $post->ID ); ?>">

	<p>
		<label for="gpm-protection-type-<?php echo esc_attr( $post->ID ); ?>">
			<strong><?php esc_html_e( 'Access Type:', 'guardify-private-media' ); ?></strong>
		</label><br>
		<select
			name="attachments[<?php echo esc_attr( $post->ID ); ?>][gpm_protection_type]"
			id="gpm-protection-type-<?php echo esc_attr( $post->ID ); ?>"
			class="gpm-protection-select"
			data-attachment-id="<?php echo esc_attr( $post->ID ); ?>"
		>
			<?php foreach ( $protection_options as $value => $label ) : ?>
				<option value="<?php echo esc_attr( $value ); ?>"<?php selected( $type, $value ); ?>>
					<?php echo esc_html( $label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
	</p>

	<!-- Roles sub-field -->
	<div class="gpm-field-roles" style="display:<?php echo GPM_Access_Control::TYPE_ROLES === $type ? 'block' : 'none'; ?>">
		<label><strong><?php esc_html_e( 'Allowed Roles:', 'guardify-private-media' ); ?></strong></label><br>
		<?php foreach ( $all_roles as $role_slug => $role_name ) : ?>
			<label style="display:inline-block;margin-right:12px;">
				<input
					type="checkbox"
					name="attachments[<?php echo esc_attr( $post->ID ); ?>][gpm_allowed_roles][]"
					value="<?php echo esc_attr( $role_slug ); ?>"
					<?php checked( in_array( $role_slug, (array) $allowed_roles, true ) ); ?>
				>
				<?php echo esc_html( translate_user_role( $role_name ) ); ?>
			</label>
		<?php endforeach; ?>
	</div>

	<!-- Password sub-field -->
	<div class="gpm-field-password" style="display:<?php echo GPM_Access_Control::TYPE_PASSWORD === $type ? 'block' : 'none'; ?>">
		<label for="gpm-password-<?php echo esc_attr( $post->ID ); ?>">
			<strong><?php esc_html_e( 'New Password (leave blank to keep current):', 'guardify-private-media' ); ?></strong>
		</label><br>
		<input
			type="password"
			name="attachments[<?php echo esc_attr( $post->ID ); ?>][gpm_password]"
			id="gpm-password-<?php echo esc_attr( $post->ID ); ?>"
			autocomplete="new-password"
			style="width:100%;"
		>
	</div>

	<!-- Post IDs sub-field -->
	<div class="gpm-field-posts" style="display:<?php echo GPM_Access_Control::TYPE_POSTS === $type ? 'block' : 'none'; ?>">
		<label for="gpm-post-ids-<?php echo esc_attr( $post->ID ); ?>">
			<strong><?php esc_html_e( 'Allowed Post/Page IDs (comma-separated):', 'guardify-private-media' ); ?></strong>
		</label><br>
		<?php
		$existing_post_ids = '';
		$protection_data   = GPM_Access_Control::get_instance()->get_protection( $post->ID );
		if ( ! empty( $protection_data['allowed_post_ids'] ) ) {
			$ids               = json_decode( $protection_data['allowed_post_ids'], true );
			$existing_post_ids = is_array( $ids ) ? implode( ', ', $ids ) : '';
		}
		?>
		<input
			type="text"
			name="attachments[<?php echo esc_attr( $post->ID ); ?>][gpm_allowed_post_ids]"
			id="gpm-post-ids-<?php echo esc_attr( $post->ID ); ?>"
			value="<?php echo esc_attr( $existing_post_ids ); ?>"
			placeholder="e.g. 42, 100, 205"
			style="width:100%;"
		>
	</div>

	<!-- Secure URL generator -->
	<?php if ( GPM_Access_Control::get_instance()->is_protected( $post->ID ) ) : ?>
	<div class="gpm-secure-url-section" style="margin-top:10px;padding-top:10px;border-top:1px solid #ddd;">
		<strong><?php esc_html_e( 'Secure URL:', 'guardify-private-media' ); ?></strong><br>
		<div style="display:flex;gap:6px;align-items:center;margin-top:4px;">
			<input
				type="text"
				id="gpm-secure-url-<?php echo esc_attr( $post->ID ); ?>"
				readonly
				value=""
				style="flex:1;font-size:11px;"
				placeholder="<?php esc_attr_e( 'Click Generate to create a secure URL', 'guardify-private-media' ); ?>"
			>
			<button
				type="button"
				class="button gpm-generate-url"
				data-attachment-id="<?php echo esc_attr( $post->ID ); ?>"
				data-target="gpm-secure-url-<?php echo esc_attr( $post->ID ); ?>"
			><?php esc_html_e( 'Generate', 'guardify-private-media' ); ?></button>
			<button
				type="button"
				class="button gpm-copy-url"
				data-target="gpm-secure-url-<?php echo esc_attr( $post->ID ); ?>"
			><?php esc_html_e( 'Copy', 'guardify-private-media' ); ?></button>
		</div>
		<button
			type="button"
			class="button-link gpm-revoke-tokens"
			data-attachment-id="<?php echo esc_attr( $post->ID ); ?>"
			style="color:#d63638;margin-top:4px;font-size:11px;"
		><?php esc_html_e( 'Revoke all tokens for this file', 'guardify-private-media' ); ?></button>
	</div>
	<?php endif; ?>
</div>
