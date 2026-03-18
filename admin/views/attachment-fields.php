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
	SMV_Access_Control::TYPE_PUBLIC    => __( 'Public (WordPress default)', 'secure-media-vault' ),
	SMV_Access_Control::TYPE_LOGGED_IN => __( 'Logged-in users only', 'secure-media-vault' ),
	SMV_Access_Control::TYPE_ROLES     => __( 'Specific user roles', 'secure-media-vault' ),
	SMV_Access_Control::TYPE_PASSWORD  => __( 'Password protected', 'secure-media-vault' ),
	SMV_Access_Control::TYPE_POSTS     => __( 'Restrict to specific posts/pages', 'secure-media-vault' ),
);
?>
<div class="smv-attachment-fields" id="smv-attachment-<?php echo esc_attr( $post->ID ); ?>">

	<p>
		<label for="smv-protection-type-<?php echo esc_attr( $post->ID ); ?>">
			<strong><?php esc_html_e( 'Access Type:', 'secure-media-vault' ); ?></strong>
		</label><br>
		<select
			name="attachments[<?php echo esc_attr( $post->ID ); ?>][smv_protection_type]"
			id="smv-protection-type-<?php echo esc_attr( $post->ID ); ?>"
			class="smv-protection-select"
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
	<div class="smv-field-roles" style="display:<?php echo SMV_Access_Control::TYPE_ROLES === $type ? 'block' : 'none'; ?>">
		<label><strong><?php esc_html_e( 'Allowed Roles:', 'secure-media-vault' ); ?></strong></label><br>
		<?php foreach ( $all_roles as $role_slug => $role_name ) : ?>
			<label style="display:inline-block;margin-right:12px;">
				<input
					type="checkbox"
					name="attachments[<?php echo esc_attr( $post->ID ); ?>][smv_allowed_roles][]"
					value="<?php echo esc_attr( $role_slug ); ?>"
					<?php checked( in_array( $role_slug, (array) $allowed_roles, true ) ); ?>
				>
				<?php echo esc_html( translate_user_role( $role_name ) ); ?>
			</label>
		<?php endforeach; ?>
	</div>

	<!-- Password sub-field -->
	<div class="smv-field-password" style="display:<?php echo SMV_Access_Control::TYPE_PASSWORD === $type ? 'block' : 'none'; ?>">
		<label for="smv-password-<?php echo esc_attr( $post->ID ); ?>">
			<strong><?php esc_html_e( 'New Password (leave blank to keep current):', 'secure-media-vault' ); ?></strong>
		</label><br>
		<input
			type="password"
			name="attachments[<?php echo esc_attr( $post->ID ); ?>][smv_password]"
			id="smv-password-<?php echo esc_attr( $post->ID ); ?>"
			autocomplete="new-password"
			style="width:100%;"
		>
	</div>

	<!-- Post IDs sub-field -->
	<div class="smv-field-posts" style="display:<?php echo SMV_Access_Control::TYPE_POSTS === $type ? 'block' : 'none'; ?>">
		<label for="smv-post-ids-<?php echo esc_attr( $post->ID ); ?>">
			<strong><?php esc_html_e( 'Allowed Post/Page IDs (comma-separated):', 'secure-media-vault' ); ?></strong>
		</label><br>
		<?php
		$existing_post_ids = '';
		$protection_data   = SMV_Access_Control::get_instance()->get_protection( $post->ID );
		if ( ! empty( $protection_data['allowed_post_ids'] ) ) {
			$ids               = json_decode( $protection_data['allowed_post_ids'], true );
			$existing_post_ids = is_array( $ids ) ? implode( ', ', $ids ) : '';
		}
		?>
		<input
			type="text"
			name="attachments[<?php echo esc_attr( $post->ID ); ?>][smv_allowed_post_ids]"
			id="smv-post-ids-<?php echo esc_attr( $post->ID ); ?>"
			value="<?php echo esc_attr( $existing_post_ids ); ?>"
			placeholder="e.g. 42, 100, 205"
			style="width:100%;"
		>
	</div>

	<!-- Secure URL generator -->
	<?php if ( SMV_Access_Control::get_instance()->is_protected( $post->ID ) ) : ?>
	<div class="smv-secure-url-section" style="margin-top:10px;padding-top:10px;border-top:1px solid #ddd;">
		<strong><?php esc_html_e( 'Secure URL:', 'secure-media-vault' ); ?></strong><br>
		<div style="display:flex;gap:6px;align-items:center;margin-top:4px;">
			<input
				type="text"
				id="smv-secure-url-<?php echo esc_attr( $post->ID ); ?>"
				readonly
				value=""
				style="flex:1;font-size:11px;"
				placeholder="<?php esc_attr_e( 'Click Generate to create a secure URL', 'secure-media-vault' ); ?>"
			>
			<button
				type="button"
				class="button smv-generate-url"
				data-attachment-id="<?php echo esc_attr( $post->ID ); ?>"
				data-target="smv-secure-url-<?php echo esc_attr( $post->ID ); ?>"
			><?php esc_html_e( 'Generate', 'secure-media-vault' ); ?></button>
			<button
				type="button"
				class="button smv-copy-url"
				data-target="smv-secure-url-<?php echo esc_attr( $post->ID ); ?>"
			><?php esc_html_e( 'Copy', 'secure-media-vault' ); ?></button>
		</div>
		<button
			type="button"
			class="button-link smv-revoke-tokens"
			data-attachment-id="<?php echo esc_attr( $post->ID ); ?>"
			style="color:#d63638;margin-top:4px;font-size:11px;"
		><?php esc_html_e( 'Revoke all tokens for this file', 'secure-media-vault' ); ?></button>
	</div>
	<?php endif; ?>
</div>
