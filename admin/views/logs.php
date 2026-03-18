<?php
/**
 * Admin view: Access Logs.
 *
 * @package SecureMediaVault
 * @var array  $logs      Array of log rows.
 * @var int    $total     Total number of log rows.
 * @var int    $per_page  Rows per page.
 * @var int    $page      Current page.
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$total_pages = (int) ceil( $total / $per_page );
?>
<div class="wrap smv-wrap">
	<h1 class="smv-page-title">
		<span class="dashicons dashicons-list-view"></span>
		<?php esc_html_e( 'Secure Media Vault – Access Logs', 'secure-media-vault' ); ?>
	</h1>

	<?php if ( empty( $logs ) ) : ?>
		<p><?php esc_html_e( 'No access log entries found.', 'secure-media-vault' ); ?></p>
	<?php else : ?>
	<table class="wp-list-table widefat fixed striped smv-logs-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Time', 'secure-media-vault' ); ?></th>
				<th><?php esc_html_e( 'File', 'secure-media-vault' ); ?></th>
				<th><?php esc_html_e( 'User', 'secure-media-vault' ); ?></th>
				<th><?php esc_html_e( 'IP Address', 'secure-media-vault' ); ?></th>
				<th><?php esc_html_e( 'Status', 'secure-media-vault' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $logs as $log ) : ?>
			<tr>
				<td><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $log->accessed_at ) ) ); ?></td>
				<td>
					<?php if ( $log->post_title ) : ?>
						<a href="<?php echo esc_url( get_edit_post_link( $log->attachment_id ) ); ?>">
							<?php echo esc_html( $log->post_title ); ?>
						</a>
					<?php else : ?>
						<em><?php echo esc_html( sprintf( '#%d', $log->attachment_id ) ); ?></em>
					<?php endif; ?>
				</td>
				<td>
					<?php
					if ( $log->user_id ) {
						$user = get_userdata( $log->user_id );
						echo $user ? esc_html( $user->user_login ) : esc_html( sprintf( '#%d', $log->user_id ) );
					} else {
						esc_html_e( 'Guest', 'secure-media-vault' );
					}
					?>
				</td>
				<td><?php echo esc_html( $log->ip_address ); ?></td>
				<td>
					<span class="smv-log-status smv-log-status--<?php echo esc_attr( strpos( $log->status, 'denied' ) === 0 ? 'denied' : $log->status ); ?>">
						<?php echo esc_html( $log->status ); ?>
					</span>
				</td>
			</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<?php if ( $total_pages > 1 ) : ?>
	<div class="tablenav bottom">
		<div class="tablenav-pages">
			<?php
			echo wp_kses_post(
				paginate_links(
					array(
						'base'      => add_query_arg( 'paged', '%#%' ),
						'format'    => '',
						'prev_text' => '&laquo;',
						'next_text' => '&raquo;',
						'total'     => $total_pages,
						'current'   => $page,
					)
				)
			);
			?>
		</div>
	</div>
	<?php endif; ?>
	<?php endif; ?>
</div>
