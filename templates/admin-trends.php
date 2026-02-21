<?php
/**
 * Admin Trends Template
 *
 * @package GunMerch_AI
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wrap gma-admin-wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<div class="gma-toolbar">
		<button type="button" class="button button-primary gma-action-btn" data-action="scan_trends">
			<span class="dashicons dashicons-search"></span>
			<?php esc_html_e( 'Scan for New Trends', 'gunmerch-ai' ); ?>
		</button>
	</div>

	<?php if ( ! empty( $trends ) ) : ?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Topic', 'gunmerch-ai' ); ?></th>
					<th><?php esc_html_e( 'Source', 'gunmerch-ai' ); ?></th>
					<th><?php esc_html_e( 'Engagement Score', 'gunmerch-ai' ); ?></th>
					<th><?php esc_html_e( 'Discovered', 'gunmerch-ai' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $trends as $trend ) : ?>
					<tr>
						<td>
							<?php if ( ! empty( $trend['source_url'] ) ) : ?>
								<a href="<?php echo esc_url( $trend['source_url'] ); ?>" target="_blank" rel="noopener noreferrer">
									<strong><?php echo esc_html( $trend['topic'] ); ?></strong>
									<span class="dashicons dashicons-external"></span>
								</a>
							<?php else : ?>
								<strong><?php echo esc_html( $trend['topic'] ); ?></strong>
							<?php endif; ?>
						</td>
						<td>
							<span class="gma-source gma-source-<?php echo esc_attr( $trend['source'] ); ?>">
								<?php echo esc_html( ucfirst( $trend['source'] ) ); ?>
							</span>
						</td>
						<td>
							<span class="gma-score-bar">
								<span class="gma-score-fill" style="width: <?php echo esc_attr( min( 100, $trend['engagement_score'] / 10 ) ); ?>%"></span>
								<span class="gma-score-value"><?php echo esc_html( number_format( $trend['engagement_score'] ) ); ?></span>
							</span>
						</td>
						<td>
							<?php echo esc_html( human_time_diff( strtotime( $trend['discovered_at'] ), time() ) ); ?> ago
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php else : ?>
		<div class="gma-empty-state-large">
			<div class="gma-empty-icon dashicons dashicons-rss"></div>
			<h2><?php esc_html_e( 'No Trends Found', 'gunmerch-ai' ); ?></h2>
			<p><?php esc_html_e( 'Run a trend scan to discover trending topics from gun news and communities.', 'gunmerch-ai' ); ?></p>
		</div>
	<?php endif; ?>
</div>