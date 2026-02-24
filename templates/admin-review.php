<?php
/**
 * Admin Review Template
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

	<!-- Toolbar -->
	<div class="gma-toolbar">
		<div class="gma-bulk-actions">
			<select id="gma-bulk-action">
				<option value=""><?php esc_html_e( 'Bulk Actions', 'gunmerch-ai' ); ?></option>
				<option value="approve"><?php esc_html_e( 'Approve Selected', 'gunmerch-ai' ); ?></option>
				<option value="reject"><?php esc_html_e( 'Reject Selected', 'gunmerch-ai' ); ?></option>
			</select>
			<button type="button" class="button" id="gma-bulk-apply">
				<?php esc_html_e( 'Apply', 'gunmerch-ai' ); ?>
			</button>
		</div>
		<div class="gma-toolbar-right">
			<span class="gma-count">
				<?php
				printf(
					/* translators: %d: Number of pending designs */
					esc_html__( '%d designs pending review', 'gunmerch-ai' ),
					count( $designs )
				);
				?>
			</span>
		</div>
	</div>

	<?php if ( ! empty( $designs ) ) : ?>
		<!-- DEBUG: Found <?php echo count( $designs ); ?> designs -->
		<!-- Designs Grid -->
		<div class="gma-designs-grid">
			<?php foreach ( $designs as $design ) : ?>
				<?php
				$design_text = $core ? $core->get_design_meta( $design->ID, 'design_text' ) : '';
				$design_type = $core ? $core->get_design_meta( $design->ID, 'design_type' ) : 'text';
				$trend_topic = $core ? $core->get_design_meta( $design->ID, 'trend_topic' ) : '';
				$margin = $core ? $core->get_design_meta( $design->ID, 'estimated_margin' ) : 40;
				$trend_source = $core ? $core->get_design_meta( $design->ID, 'trend_source' ) : '';
				$mockup_url = $core ? $core->get_design_meta( $design->ID, 'mockup_url' ) : '';
				?>
				<div class="gma-design-card" data-design-id="<?php echo esc_attr( $design->ID ); ?>">
					<div class="gma-design-header">
						<label class="gma-design-checkbox">
							<input type="checkbox" name="design_ids[]" value="<?php echo esc_attr( $design->ID ); ?>">
						</label>
						<span class="gma-design-type gma-design-type-<?php echo esc_attr( $design_type ); ?>">
							<?php echo esc_html( ucfirst( $design_type ) ); ?>
						</span>
					</div>

					<div class="gma-design-mockup">
						<?php if ( $mockup_url ) : ?>
							<img src="<?php echo esc_url( $mockup_url ); ?>" alt="<?php echo esc_attr( $design->post_title ); ?>">
						<?php elseif ( has_post_thumbnail( $design->ID ) ) : ?>
							<?php echo get_the_post_thumbnail( $design->ID, 'medium', array( 'alt' => $design->post_title ) ); ?>
							<div class="gma-image-actions">
								<button type="button" class="button gma-btn-regenerate-image" data-design-id="<?php echo esc_attr( $design->ID ); ?>">
									<span class="dashicons dashicons-update"></span>
									<?php esc_html_e( 'Regenerate', 'gunmerch-ai' ); ?>
								</button>
							</div>
						<?php else : ?>
							<div class="gma-mockup-placeholder">
								<div class="gma-tshirt-preview">
									<div class="gma-tshirt-body"></div>
									<div class="gma-tshirt-text">
										<?php echo esc_html( $design_text ); ?>
									</div>
								</div>
							</div>
							<div class="gma-placeholder-actions">
								<button type="button" class="button button-primary gma-btn-use-text-design" data-design-id="<?php echo esc_attr( $design->ID ); ?>">
									<span class="dashicons dashicons-yes"></span>
									<?php esc_html_e( 'Use This Text Design', 'gunmerch-ai' ); ?>
								</button>
								<button type="button" class="button gma-btn-generate-image" data-design-id="<?php echo esc_attr( $design->ID ); ?>">
									<span class="dashicons dashicons-format-image"></span>
									<?php esc_html_e( 'Generate AI Image', 'gunmerch-ai' ); ?>
								</button>
							</div>
						<?php endif; ?>
					</div>

					<div class="gma-design-content">
						<h3 class="gma-design-title"><?php echo esc_html( $design->post_title ); ?></h3>
						
						<?php if ( $design_text ) : ?>
							<p class="gma-design-slogan">"<?php echo esc_html( $design_text ); ?>"</p>
						<?php endif; ?>

						<?php if ( $design->post_content ) : ?>
							<p class="gma-design-concept"><?php echo esc_html( wp_trim_words( $design->post_content, 20 ) ); ?></p>
						<?php endif; ?>

						<div class="gma-design-meta">
							<?php if ( $trend_topic ) : ?>
								<div class="gma-meta-item">
									<span class="gma-meta-label"><?php esc_html_e( 'Trend:', 'gunmerch-ai' ); ?></span>
									<span class="gma-meta-value">
										<?php if ( $trend_source ) : ?>
											<a href="<?php echo esc_url( $trend_source ); ?>" target="_blank" rel="noopener noreferrer">
												<?php echo esc_html( $trend_topic ); ?>
												<span class="dashicons dashicons-external"></span>
											</a>
										<?php else : ?>
											<?php echo esc_html( $trend_topic ); ?>
										<?php endif; ?>
									</span>
								</div>
							<?php endif; ?>

							<div class="gma-meta-item">
								<span class="gma-meta-label"><?php esc_html_e( 'Est. Margin:', 'gunmerch-ai' ); ?></span>
								<span class="gma-meta-value"><?php echo esc_html( $margin ); ?>%</span>
							</div>

							<div class="gma-meta-item">
								<span class="gma-meta-label"><?php esc_html_e( 'Created:', 'gunmerch-ai' ); ?></span>
								<span class="gma-meta-value"><?php echo esc_html( human_time_diff( strtotime( $design->post_date ), time() ) ); ?> ago</span>
							</div>
						</div>
					</div>

					<div class="gma-design-actions">
						<button type="button" class="button button-primary gma-btn-approve" data-design-id="<?php echo esc_attr( $design->ID ); ?>">
							<span class="dashicons dashicons-yes"></span>
							<?php esc_html_e( 'Approve', 'gunmerch-ai' ); ?>
						</button>
						<button type="button" class="button gma-btn-reject" data-design-id="<?php echo esc_attr( $design->ID ); ?>">
							<span class="dashicons dashicons-no"></span>
							<?php esc_html_e( 'Reject', 'gunmerch-ai' ); ?>
						</button>
					</div>
				</div>
			<?php endforeach; ?>
		</div>

		<!-- Pagination placeholder -->
		<?php if ( count( $designs ) >= 20 ) : ?>
			<div class="gma-pagination">
				<span class="gma-pagination-info">
					<?php esc_html_e( 'Showing 20 designs', 'gunmerch-ai' ); ?>
				</span>
			</div>
		<?php endif; ?>

	<?php else : ?>
		<div class="gma-empty-state-large">
			<div class="gma-empty-icon dashicons dashicons-smiley"></div>
			<h2><?php esc_html_e( 'All Caught Up!', 'gunmerch-ai' ); ?></h2>
			<p><?php esc_html_e( 'No designs pending review. Generate new designs or wait for the next automatic scan.', 'gunmerch-ai' ); ?></p>
			<p>
				<button type="button" class="button button-primary gma-action-btn" data-action="generate_designs">
					<?php esc_html_e( 'Generate Designs Now', 'gunmerch-ai' ); ?>
				</button>
			</p>
		</div>
	<?php endif; ?>
</div>
