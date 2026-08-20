<?php
/**
 * Optimization log view.
 *
 * @package BltImageOptimizer
 *
 * @var array $result   Query result {rows,total}.
 * @var array $stats    Aggregate stats.
 * @var string $status  Active status filter.
 * @var int   $paged    Current page.
 * @var int   $per_page Rows per page.
 */

namespace BltImageOptimizer;

defined( 'ABSPATH' ) || exit;

$rows        = $result['rows'];
$total       = (int) $result['total'];
$total_pages = (int) ceil( $total / max( 1, $per_page ) );
$base_url    = admin_url( 'admin.php?page=blt-optimizer-log' );

$filters = array(
	''        => __( 'All', 'blt-image-optimizer' ),
	'done'    => __( 'Done', 'blt-image-optimizer' ),
	'error'   => __( 'Errors', 'blt-image-optimizer' ),
	'skipped' => __( 'Skipped', 'blt-image-optimizer' ),
	'pending' => __( 'Pending', 'blt-image-optimizer' ),
);

// Log status -> shared badge variant.
$badge_classes = array(
	'done'       => 'blt-badge-on',
	'error'      => 'blt-badge-cancelled',
	'skipped'    => 'blt-badge-off',
	'pending'    => 'blt-badge-pending',
	'processing' => 'blt-badge-pending',
);
?>
<div class="wrap blt-ui blt-ui-wide blt-optimizer-wrap">
	<div class="blt-admin-page-header">
		<h1>
			<?php
			// Already run through wp_kses() with an explicit SVG subset inside
			// BLT_Family_Brand::inline_mark(), and echoed as-is on purpose:
			// wp_kses_post() would strip the <svg> outright, since core's
			// allowed post tags have no SVG in them.
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo \BLT_Family_Brand::inline_mark( BLT_OPTIMIZER_DIR );
			?>
			<?php esc_html_e( 'BLT Image Optimizer', 'blt-image-optimizer' ); ?>
			<span class="blt-admin-page-header-sub"><?php esc_html_e( 'Optimization Log', 'blt-image-optimizer' ); ?></span>
		</h1>
		<div class="blt-admin-page-actions">
			<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=blt-optimizer' ) ); ?>">
				<?php esc_html_e( 'Bulk Optimizer', 'blt-image-optimizer' ); ?>
			</a>
		</div>
	</div>

	<div class="blt-card">
		<div class="blt-toolbar">
			<ul class="subsubsub">
				<?php foreach ( $filters as $key => $label ) : ?>
					<li>
						<a href="<?php echo esc_url( add_query_arg( 'status', $key, $base_url ) ); ?>"
							class="<?php echo $status === $key ? 'current' : ''; ?>">
							<?php echo esc_html( $label ); ?>
							<?php if ( '' !== $key && isset( $stats['by_status'][ $key ] ) ) : ?>
								<span class="count">(<?php echo esc_html( (string) $stats['by_status'][ $key ] ); ?>)</span>
							<?php endif; ?>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>

		<?php if ( empty( $rows ) ) : ?>
			<div class="blt-card-body">
				<div class="blt-empty">
					<span class="blt-empty-title"><?php esc_html_e( 'Nothing logged yet', 'blt-image-optimizer' ); ?></span>
					<span><?php esc_html_e( 'Run the bulk optimizer, or upload an image, and each size will appear here.', 'blt-image-optimizer' ); ?></span>
				</div>
			</div>
		<?php else : ?>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Attachment', 'blt-image-optimizer' ); ?></th>
						<th><?php esc_html_e( 'Size', 'blt-image-optimizer' ); ?></th>
						<th><?php esc_html_e( 'Status', 'blt-image-optimizer' ); ?></th>
						<th><?php esc_html_e( 'Original', 'blt-image-optimizer' ); ?></th>
						<th><?php esc_html_e( 'Optimized', 'blt-image-optimizer' ); ?></th>
						<th><?php esc_html_e( 'Savings', 'blt-image-optimizer' ); ?></th>
						<th><?php esc_html_e( 'Processed', 'blt-image-optimizer' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $rows as $row ) : ?>
						<tr>
							<td>
								<a href="<?php echo esc_url( get_edit_post_link( (int) $row->attachment_id ) ); ?>">
									#<?php echo esc_html( (string) $row->attachment_id ); ?>
								</a>
								<?php
								$title = get_the_title( (int) $row->attachment_id );
								if ( $title ) {
									echo ' — ' . esc_html( $title );
								}
								?>
							</td>
							<td><code><?php echo esc_html( $row->size_name ); ?></code></td>
							<td>
								<span class="blt-badge <?php echo esc_attr( $badge_classes[ $row->status ] ?? 'blt-badge-off' ); ?>">
									<?php echo esc_html( ucfirst( $row->status ) ); ?>
								</span>
								<?php if ( 'error' === $row->status && $row->error_message ) : ?>
									<span class="blt-error-msg" title="<?php echo esc_attr( $row->error_message ); ?>">ⓘ</span>
								<?php endif; ?>
							</td>
							<td><?php echo $row->original_size ? esc_html( size_format( (int) $row->original_size, 1 ) ) : '—'; ?></td>
							<td><?php echo $row->optimized_size ? esc_html( size_format( (int) $row->optimized_size, 1 ) ) : '—'; ?></td>
							<td><?php echo null !== $row->savings_pct ? esc_html( (string) $row->savings_pct ) . '%' : '—'; ?></td>
							<td><?php echo $row->processed_at ? esc_html( $row->processed_at ) : '—'; ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>

	<?php if ( $total_pages > 1 ) : ?>
		<div class="tablenav">
			<div class="tablenav-pages">
				<?php
				echo wp_kses_post(
					paginate_links(
						array(
							'base'      => add_query_arg( 'paged', '%#%', add_query_arg( 'status', $status, $base_url ) ),
							'format'    => '',
							'current'   => $paged,
							'total'     => $total_pages,
							'prev_text' => '‹',
							'next_text' => '›',
						)
					)
				);
				?>
			</div>
		</div>
	<?php endif; ?>
</div>
