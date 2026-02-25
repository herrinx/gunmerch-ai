<?php
/**
 * Admin Dashboard Template
 *
 * @package GunMerch_AI
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$core = gunmerch_ai()->get_class( 'core' );
$trends = gunmerch_ai()->get_class( 'trends' );
$printfull = gunmerch_ai()->get_class( 'printfull' );

$stats = $core ? $core->get_stats() : array(
	'total_designs_generated' => 0,
	'total_designs_approved'  => 0,
	'total_designs_rejected'  => 0,
	'total_sales'             => 0,
	'total_revenue'           => 0.00,
);

$pending_count = wp_count_posts( 'gma_design' )->pending;
$live_count = wp_count_posts( 'gma_design' )->live;
$current_trends = $trends ? $trends->get_current_trends( 5 ) : array();
$connection = $printfull ? $printfull->test_connection() : null;
$printfull_connected = ! is_wp_error( $connection );
?>

<div class="wrap gma-admin-wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<!-- Stats Overview -->
	<div class="gma-stats-grid">
		<div class="gma-stat-card">
			<div class="gma-stat-icon dashicons dashicons-art"></div>
			<div class="gma-stat-content">
				<h3><?php echo esc_html( number_format( $stats['total_designs_generated'] ) ); ?></h3>
				<p><?php esc_html_e( 'Total Designs Generated', 'gunmerch-ai' ); ?></p>
			</div>
		</div>

		<div class="gma-stat-card">
			<div class="gma-stat-icon dashicons dashicons-yes-alt"></div>
			<div class="gma-stat-content">
				<h3><?php echo esc_html( number_format( $stats['total_designs_approved'] ) ); ?></h3>
				<p><?php esc_html_e( 'Designs Approved', 'gunmerch-ai' ); ?></p>
			</div>
		</div>

		<div class="gma-stat-card gma-stat-pending">
			<div class="gma-stat-icon dashicons dashicons-clock"></div>
			<div class="gma-stat-content">
				<h3><?php echo esc_html( number_format( $pending_count ) ); ?></h3>
				<p><?php esc_html_e( 'Pending Review', 'gunmerch-ai' ); ?></p>
				<?php if ( $pending_count > 0 ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=gma-review' ) ); ?>" class="button button-small">
						<?php esc_html_e( 'Review Now', 'gunmerch-ai' ); ?>
					</a>
				<?php endif; ?>
			</div>
		</div>

		<div class="gma-stat-card gma-stat-sales">
			<div class="gma-stat-icon dashicons dashicons-cart"></div>
			<div class="gma-stat-content">
				<h3><?php echo esc_html( number_format( $stats['total_sales'] ) ); ?></h3>
				<p><?php esc_html_e( 'Total Sales', 'gunmerch-ai' ); ?></p>
			</div>
		</div>

		<div class="gma-stat-card gma-stat-revenue">
			<div class="gma-stat-icon dashicons dashicons-chart-line"></div>
			<div class="gma-stat-content">
				<h3>$<?php echo esc_html( number_format( $stats['total_revenue'], 2 ) ); ?></h3>
				<p><?php esc_html_e( 'Total Revenue', 'gunmerch-ai' ); ?></p>
			</div>
		</div>
	</div>

	<!-- Main Dashboard Grid -->
	<div class="gma-dashboard-grid">
		<!-- Left Column -->
		<div class="gma-dashboard-col">
			<!-- Quick Actions -->
			<div class="gma-card">
				<h2><?php esc_html_e( 'Quick Actions', 'gunmerch-ai' ); ?></h2>
				<div class="gma-actions">
					<button type="button" class="button button-primary gma-action-btn" data-action="scan_trends">
						<span class="dashicons dashicons-search"></span>
						<?php esc_html_e( 'Scan Trends Now', 'gunmerch-ai' ); ?>
					</button>
					<button type="button" class="button button-secondary gma-action-btn" data-action="generate_designs">
						<span class="dashicons dashicons-lightbulb"></span>
						<?php esc_html_e( 'Generate Designs', 'gunmerch-ai' ); ?>
					</button>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=gma-review' ) ); ?>" class="button button-secondary">
						<span class="dashicons dashicons-visibility"></span>
						<?php esc_html_e( 'Review Pending', 'gunmerch-ai' ); ?>
					</a>
				</div>
				<div class="gma-actions" style="margin-top: 10px; border-top: 1px solid #ccc; padding-top: 10px;">
					<button type="button" class="button button-link-delete gma-btn-clear-designs">
						<span class="dashicons dashicons-trash"></span>
						<?php esc_html_e( 'Clear All Designs', 'gunmerch-ai' ); ?>
					</button>
				</div>
			</div>

			<!-- Current Trends -->
			<div class="gma-card">
				<h2>
					<?php esc_html_e( 'Current Trends', 'gunmerch-ai' ); ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=gma-trends' ) ); ?>" class="gma-view-all">
						<?php esc_html_e( 'View All', 'gunmerch-ai' ); ?> &rarr;
					</a>
				</h2>
				<p style="margin-top: -10px; margin-bottom: 15px;">
					<button type="button" class="button button-secondary gma-btn-clear-trends">
						<span class="dashicons dashicons-trash"></span>
						<?php esc_html_e( 'Clear All Trends', 'gunmerch-ai' ); ?>
					</button>
				</p>
				<?php if ( ! empty( $current_trends ) ) : ?>
					<table class="wp-list-table widefat fixed striped gma-trends-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Topic', 'gunmerch-ai' ); ?></th>
								<th><?php esc_html_e( 'Source', 'gunmerch-ai' ); ?></th>
								<th><?php esc_html_e( 'Score', 'gunmerch-ai' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $current_trends as $trend ) : ?>
								<tr>
									<td>
										<?php if ( ! empty( $trend['source_url'] ) ) : ?>
											<a href="<?php echo esc_url( $trend['source_url'] ); ?>" target="_blank" rel="noopener noreferrer">
												<?php echo esc_html( $trend['topic'] ); ?>
												<span class="dashicons dashicons-external"></span>
											</a>
										<?php else : ?>
											<?php echo esc_html( $trend['topic'] ); ?>
										<?php endif; ?>
									</td>
									<td>
										<span class="gma-source gma-source-<?php echo esc_attr( $trend['source'] ); ?>">
											<?php echo esc_html( ucfirst( $trend['source'] ) ); ?>
										</span>
									</td>
									<td>
										<span class="gma-score"><?php echo esc_html( number_format( $trend['engagement_score'] ) ); ?></span>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php else : ?>
					<p class="gma-empty-state">
						<?php esc_html_e( 'No trends found. Run a scan to discover trending topics.', 'gunmerch-ai' ); ?>
					</p>
				<?php endif; ?>
			</div>
		</div>

		<!-- Right Column -->
		<div class="gma-dashboard-col">
			<!-- System Status -->
			<div class="gma-card">
				<h2><?php esc_html_e( 'System Status', 'gunmerch-ai' ); ?></h2>
				<ul class="gma-status-list">
					<li class="gma-status-item">
						<span class="gma-status-label"><?php esc_html_e( 'Printful API', 'gunmerch-ai' ); ?></span>
						<span class="gma-status-value gma-status-<?php echo $printfull_connected ? 'ok' : 'error'; ?>">
							<?php echo $printfull_connected ? '✓ ' . esc_html__( 'Connected', 'gunmerch-ai' ) : '✗ ' . esc_html__( 'Not Connected', 'gunmerch-ai' ); ?>
						</span>
					</li>
					<li class="gma-status-item">
						<span class="gma-status-label"><?php esc_html_e( 'OpenAI API', 'gunmerch-ai' ); ?></span>
						<span class="gma-status-value gma-status-<?php echo get_option( 'gma_openai_api_key' ) ? 'ok' : 'warning'; ?>">
							<?php echo get_option( 'gma_openai_api_key' ) ? '✓ ' . esc_html__( 'Configured', 'gunmerch-ai' ) : '○ ' . esc_html__( 'Not Configured', 'gunmerch-ai' ); ?>
						</span>
					</li>
					<li class="gma-status-item">
						<span class="gma-status-label"><?php esc_html_e( 'Next Trend Scan', 'gunmerch-ai' ); ?></span>
						<span class="gma-status-value">
							<?php
							$next_scan = wp_next_scheduled( 'gma_scan_trends' );
							if ( $next_scan ) {
								echo esc_html( human_time_diff( $next_scan, time() ) );
							} else {
								esc_html_e( 'Not scheduled', 'gunmerch-ai' );
							}
							?>
						</span>
					</li>
					<li class="gma-status-item">
						<span class="gma-status-label"><?php esc_html_e( 'Live Products', 'gunmerch-ai' ); ?></span>
						<span class="gma-status-value">
							<?php echo esc_html( number_format( $live_count ) ); ?>
						</span>
					</li>
				</ul>
			</div>

			<!-- Recent Activity -->
			<div class="gma-card">
				<h2><?php esc_html_e( 'Recent Activity', 'gunmerch-ai' ); ?></h2>
				<?php
				$logger = gunmerch_ai()->get_class( 'logger' );
				$logs = $logger ? $logger->get_logs( array( 'limit' => 5 ) ) : array();
				?>
				<?php if ( ! empty( $logs ) ) : ?>
					<ul class="gma-activity-list">
						<?php foreach ( $logs as $log ) : ?>
							<li class="gma-activity-item gma-activity-<?php echo esc_attr( $log->log_type ); ?>">
								<span class="gma-activity-time">
									<?php echo esc_html( human_time_diff( strtotime( $log->created_at ), time() ) ); ?> ago
								</span>
								<span class="gma-activity-text">
									<?php echo esc_html( $log->message ); ?>
								</span>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php else : ?>
					<p class="gma-empty-state">
						<?php esc_html_e( 'No recent activity.', 'gunmerch-ai' ); ?>
					</p>
				<?php endif; ?>
			</div>
		</div>
	</div>
</div>