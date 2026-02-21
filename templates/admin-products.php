<?php
/**
 * Admin Products Template
 *
 * @package GunMerch_AI
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$core = gunmerch_ai()->get_class( 'core' );
?>

<div class="wrap gma-admin-wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<?php if ( ! empty( $designs ) ) : ?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Product', 'gunmerch-ai' ); ?></th>
					<th><?php esc_html_e( 'Design', 'gunmerch-ai' ); ?></th>
					<th><?php esc_html_e( 'Sales', 'gunmerch-ai' ); ?></th>
					<th><?php esc_html_e( 'Revenue', 'gunmerch-ai' ); ?></th>
					<th><?php esc_html_e( 'Status', 'gunmerch-ai' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'gunmerch-ai' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $designs as $design ) : ?>
					<?php
					$sales_count  = $core ? $core->get_design_meta( $design->ID, 'sales_count' ) : 0;
					$revenue      = $core ? $core->get_design_meta( $design->ID, 'revenue' ) : 0;
					$printful_id  = $core ? $core->get_design_meta( $design->ID, 'printfull_product_id' ) : '';
					$design_text  = $core ? $core->get_design_meta( $design->ID, 'design_text' ) : '';
					?>
					<tr>
						<td>
							<strong><?php echo esc_html( $design->post_title ); ?></strong>
							<?php if ( $printful_id ) : ?>
								<br><small class="gma-meta">Printful ID: <?php echo esc_html( $printful_id ); ?></small>
							<?php endif; ?>
						</td>
						<td>
							<em>"<?php echo esc_html( $design_text ); ?>"</em>
						</td>
						<td>
							<?php echo esc_html( number_format( $sales_count ) ); ?>
						</td>
						<td>
							$<?php echo esc_html( number_format( $revenue, 2 ) ); ?>
						</td>
						<td>
							<span class="gma-status gma-status-live"><?php esc_html_e( 'Live', 'gunmerch-ai' ); ?></span>
						</td>
						<td>
							<a href="<?php echo esc_url( get_edit_post_link( $design->ID ) ); ?>" class="button button-small">
								<?php esc_html_e( 'Edit', 'gunmerch-ai' ); ?>
							</a>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php else : ?>
		<div class="gma-empty-state-large">
			<div class="gma-empty-icon dashicons dashicons-store"></div>
			<h2><?php esc_html_e( 'No Live Products', 'gunmerch-ai' ); ?></h2>
			<p><?php esc_html_e( 'Approve designs to publish them to Printful and make them live.', 'gunmerch-ai' ); ?></p>
			<p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=gma-review' ) ); ?>" class="button button-primary">
					<?php esc_html_e( 'Review Designs', 'gunmerch-ai' ); ?>
				</a>
			</p>
		</div>
	<?php endif; ?>
</div>