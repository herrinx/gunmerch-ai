<?php
/**
 * Admin History Template
 *
 * @package GunMerch_AI
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$core = gunmerch_ai()->get_class( 'core' );
$statuses = $core ? $core->get_design_statuses() : array();
?>

<div class="wrap gma-admin-wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<!-- Filters -->
	<div class="gma-toolbar">
		<form method="get" class="gma-filter-form">
			<input type="hidden" name="page" value="gma-history">
			<select name="status">
				<option value=""><?php esc_html_e( 'All Statuses', 'gunmerch-ai' ); ?></option>
				<?php foreach ( $statuses as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $status_filter, $key ); ?>>
						<?php echo esc_html( $label ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<button type="submit" class="button"><?php esc_html_e( 'Filter', 'gunmerch-ai' ); ?></button>
		</form>
	</div>

	<?php if ( ! empty( $designs ) ) : ?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Title', 'gunmerch-ai' ); ?></th>
					<th><?php esc_html_e( 'Status', 'gunmerch-ai' ); ?></th>
					<th><?php esc_html_e( 'Type', 'gunmerch-ai' ); ?></th>
					<th><?php esc_html_e( 'Trend Topic', 'gunmerch-ai' ); ?></th>
					<th><?php esc_html_e( 'Sales', 'gunmerch-ai' ); ?></th>
					<th><?php esc_html_e( 'Created', 'gunmerch-ai' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $designs as $design ) : ?>
					<?php
					$status       = $core ? $core->get_design_meta( $design->ID, 'status' ) : 'pending';
					$design_type  = $core ? $core->get_design_meta( $design->ID, 'design_type' ) : 'text';
					$trend_topic  = $core ? $core->get_design_meta( $design->ID, 'trend_topic' ) : '';
					$sales_count  = $core ? $core->get_design_meta( $design->ID, 'sales_count' ) : 0;
					$status_label = $core ? $core->get_design_status_label( $status ) : $status;
					?>
					<tr>
						<td>
							<strong>
								<a href="<?php echo esc_url( get_edit_post_link( $design->ID ) ); ?>">
									<?php echo esc_html( $design->post_title ); ?>
								</a>
							</strong>
						</td>
						<td>
							<span class="gma-status gma-status-<?php echo esc_attr( $status ); ?>">
								<?php echo esc_html( $status_label ); ?>
							</span>
						</td>
						<td><?php echo esc_html( ucfirst( $design_type ) ); ?></td>
						<td><?php echo esc_html( $trend_topic ); ?></td>
						<td><?php echo esc_html( number_format( intval( $sales_count ) ) ); ?></td>
						<td><?php echo esc_html( mysql2date( get_option( 'date_format' ), $design->post_date ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php else : ?>
		<div class="gma-empty-state-large">
			<div class="gma-empty-icon dashicons dashicons-backup"></div>
			<h2><?php esc_html_e( 'No Design History', 'gunmerch-ai' ); ?></h2>
			<p><?php esc_html_e( 'Once designs are generated, they will appear here.', 'gunmerch-ai' ); ?></p>
		</div>
	<?php endif; ?>
</div>