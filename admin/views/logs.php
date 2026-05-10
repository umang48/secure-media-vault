<?php
/**
 * Admin view: Access Logs.
 *
 * @package UmangRestrictedMediaAccess
 * @var array  $urma_logs      Array of log rows.
 * @var int    $total     Total number of log rows.
 * @var int    $per_page  Rows per page.
 * @var int    $page      Current page.
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$urma_total_pages = (int) ceil( $total / $per_page );
?>
<div class="wrap urma-wrap">
	<h1 class="urma-page-title">
		<span class="dashicons dashicons-list-view"></span>
		<?php esc_html_e( 'Umang Restricted Media Access – Access Logs', 'secure-media-vault' ); ?>
	</h1>

	<?php if ( empty( $urma_logs ) ) : ?>
		<p><?php esc_html_e( 'No access log entries found.', 'secure-media-vault' ); ?></p>
	<?php else : ?>
	<table class="wp-list-table widefat fixed striped urma-logs-table">
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
			<?php foreach ( $urma_logs as $urma_log ) : ?>
			<tr>
				<td><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $urma_log->accessed_at ) ) ); ?></td>
				<td>
					<?php if ( $urma_log->post_title ) : ?>
						<a href="<?php echo esc_url( get_edit_post_link( $urma_log->attachment_id ) ); ?>">
							<?php echo esc_html( $urma_log->post_title ); ?>
						</a>
					<?php else : ?>
						<em><?php echo esc_html( sprintf( '#%d', $urma_log->attachment_id ) ); ?></em>
					<?php endif; ?>
				</td>
				<td>
					<?php
					if ( $urma_log->user_id ) {
						$urma_user = get_userdata( $urma_log->user_id );
						echo $urma_user ? esc_html( $urma_user->user_login ) : esc_html( sprintf( '#%d', $urma_log->user_id ) );
					} else {
						esc_html_e( 'Guest', 'secure-media-vault' );
					}
					?>
				</td>
				<td><?php echo esc_html( $urma_log->ip_address ); ?></td>
				<td>
					<span class="urma-log-status urma-log-status--<?php echo esc_attr( strpos( $urma_log->status, 'denied' ) === 0 ? 'denied' : $urma_log->status ); ?>">
						<?php echo esc_html( $urma_log->status ); ?>
					</span>
				</td>
			</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<?php if ( $urma_total_pages > 1 ) : ?>
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
						'total'     => $urma_total_pages,
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
