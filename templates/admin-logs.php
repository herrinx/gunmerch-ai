<?php
/**
 * Admin Logs Template
 *
 * @package GunMerch_AI
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$log_types = array( 'debug', 'info', 'warning', 'error', 'system' );
?>

<div class="wrap gma-admin-wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<div class="gma-toolbar">
		<form method="get" class="gma-filter-form">
			<input type="hidden" name="page" value="gma-logs">
			<select name="type">
				<option value=""><?php esc_html_e( 'All Types', 'gunmerch-ai' ); ?></option>
				<?php foreach ( $log_types as $type ) : ?>
					<option value="<?php echo esc_attr( $type ); ?>" <?php selected( $type_filter, $type ); ?>>
						<?php echo esc_html( ucfirst( $type ) ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<button type="submit" class="button"><?php esc_html_e( 'Filter', 'gunmerch-ai' ); ?></button>
		</form>

		<button type="button" class="button gma-clear-logs">
			<?php esc_html_e( 'Clear All Logs', 'gunmerch-ai' ); ?>
		</button>
	</div>

	<?php if ( ! empty( $logs ) ) : ?>
		<table class="wp-list-table widefat fixed striped gma-logs-table">
			<thead>
				<tr>
					<th style="width: 120px;"><?php esc_html_e( 'Type', 'gunmerch-ai' ); ?></th>
					<th style="width: 160px;"><?php esc_html_e( 'Time', 'gunmerch-ai' ); ?></th>
					<th><?php esc_html_e( 'Message', 'gunmerch-ai' ); ?></th>
					<th style="width: 100px;"><?php esc_html_e( 'Design', 'gunmerch-ai' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $logs as $log ) : ?>
					<tr class="gma-log-row gma-log-<?php echo esc_attr( $log->log_type ); ?>">
						<td>
							<span class="gma-log-badge gma-log-badge-<?php echo esc_attr( $log->log_type ); ?>">
								<?php echo esc_html( ucfirst( $log->log_type ) ); ?>
							</span>
						</td>
						<td>
							<?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $log->created_at ) ); ?>
						</td>
						<td>
							<?php echo esc_html( $log->message ); ?>
							<?php if ( ! empty( $log->meta ) && is_array( $log->meta ) ) : ?>
								<button type="button" class="button-link gma-toggle-meta" data-log-id="<?php echo esc_attr( $log->id ); ?>">
									<?php esc_html_e( 'Show Details', 'gunmerch-ai' ); ?>
								</button>
								<pre class="gma-log-meta" id="gma-log-meta-<?php echo esc_attr( $log->id ); ?>" style="display: none;">
<?php echo esc_html( wp_json_encode( $log->meta, JSON_PRETTY_PRINT ) ); ?></pre>
							<?php endif; ?>
						</td>
						<td>
							<?php if ( $log->design_id ) : ?>
								<a href="<?php echo esc_url( get_edit_post_link( $log->design_id ) ); ?>">
									#<?php echo esc_html( $log->design_id ); ?>
								</a>
							<?php else : ?>
								—
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php if ( $total_pages > 1 ) : ?>
			<div class="gma-pagination">
				<?php
				echo paginate_links( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					array(
						'base'      => add_query_arg( 'log_page', '%#%' ),
						'format'    => '',
						'prev_text' => __( '&laquo; Previous', 'gunmerch-ai' ),
						'next_text' => __( 'Next &raquo;', 'gunmerch-ai' ),
						'total'     => $total_pages,
						'current'   => $page,
					)
				);
				?>
			</div>
		<?php endif; ?>

	<?php else : ?>
		<div class="gma-empty-state-large">
			<div class="gma-empty-icon dashicons dashicons-text-page"></div>
			<h2><?php esc_html_e( 'No Logs', 'gunmerch-ai' ); ?></h2>
			<p><?php esc_html_e( 'Activity logs will appear here once the plugin starts operating.', 'gunmerch-ai' ); ?></p>
		</div>
	<?php endif; ?>
</div>