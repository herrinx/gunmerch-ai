<?php
/**
 * Admin Settings Template
 *
 * @package GunMerch_AI
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$printfull_api_key   = get_option( 'gma_printful_api_key', '' );
$printfull_store_id  = get_option( 'gma_printful_store_id', '' );
$shopify_store_url   = get_option( 'gma_shopify_store_url', '' );
$shopify_access_token = get_option( 'gma_shopify_access_token', '' );
$openai_api_key      = get_option( 'gma_openai_api_key', '' );
$gemini_api_key      = get_option( 'gma_gemini_api_key', '' );
$reddit_client_id    = get_option( 'gma_reddit_client_id', '' );
$reddit_client_secret = get_option( 'gma_reddit_client_secret', '' );

$settings = get_option( 'gma_settings', array() );
$scan_frequency        = isset( $settings['scan_frequency'] ) ? $settings['scan_frequency'] : '6hours';
$designs_per_scan      = isset( $settings['designs_per_scan'] ) ? $settings['designs_per_scan'] : 10;
$auto_approve          = ! empty( $settings['auto_approve'] );
$auto_publish          = ! empty( $settings['auto_publish_to_printful'] );
$printful_variant_id   = isset( $settings['printful_variant_id'] ) ? $settings['printful_variant_id'] : '4012';
$min_engagement        = isset( $settings['min_engagement'] ) ? $settings['min_engagement'] : 50;
$default_margin        = isset( $settings['default_margin'] ) ? $settings['default_margin'] : 40;
$notification_email    = isset( $settings['notification_email'] ) ? $settings['notification_email'] : get_option( 'admin_email' );
$image_prompt_template = isset( $settings['image_prompt_template'] ) ? $settings['image_prompt_template'] : 'Vector graphic design artwork featuring the text: "{text}" in WHITE. {concept} Style: bold typography, minimalist vector illustration, 2-3 flat colors, centered composition, transparent background, suitable for DTG printing. {color_instruction} Any graphic elements or illustrations should be RELEVANT to the text content and should NOT interrupt or split the text - keep the text as one continuous readable sentence. NO t-shirt mockup, NO fabric texture, NO background, just the design artwork itself.';

$connection = $printfull ? $printfull->test_connection() : null;
$printfull_connected = ! is_wp_error( $connection );
?>

<div class="wrap gma-admin-wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<?php if ( isset( $_GET['updated'] ) ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Settings saved successfully.', 'gunmerch-ai' ); ?></p>
		</div>
	<?php endif; ?>

	<form method="post" action="">
		<?php wp_nonce_field( 'gma_settings', 'gma_settings_nonce' ); ?>

		<h2><?php esc_html_e( 'API Keys', 'gunmerch-ai' ); ?></h2>

		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="gma_printful_api_key"><?php esc_html_e( 'Printful API Key', 'gunmerch-ai' ); ?></label>
				</th>
				<td>
					<input type="password" 
						name="gma_printful_api_key" 
						id="gma_printful_api_key" 
						value="<?php echo esc_attr( $printfull_api_key ); ?>" 
						class="regular-text">
					<p class="description">
						<?php esc_html_e( 'Your Printful API key for product creation.', 'gunmerch-ai' ); ?>
						<a href="https://www.printful.com/dashboard/settings/api" target="_blank" rel="noopener noreferrer">
							<?php esc_html_e( 'Get your API key', 'gunmerch-ai' ); ?> →
						</a>
					</p>
					<?php
					$store_type = $printfull ? $printfull->is_api_platform_store() : null;
					$is_api_store = true === $store_type;
					$is_ecommerce_store = false === $store_type;
					?>
					<?php if ( $printfull_connected && $is_api_store ) : ?>
						<p class="gma-connection-status gma-status-ok">
							✓ <?php esc_html_e( 'Connected - API Platform Store (Product creation enabled)', 'gunmerch-ai' ); ?>
							<?php if ( isset( $connection['store_name'] ) ) : ?>
								(<?php echo esc_html( $connection['store_name'] ); ?>)
							<?php endif; ?>
						</p>
					<?php elseif ( $printfull_connected && $is_ecommerce_store ) : ?>
						<p class="gma-connection-status gma-status-warning">
							⚠ <?php esc_html_e( 'Connected - Ecommerce Platform Store', 'gunmerch-ai' ); ?>
							<?php if ( isset( $connection['store_name'] ) ) : ?>
								(<?php echo esc_html( $connection['store_name'] ); ?>)
							<?php endif; ?>
						</p>
						<p class="description">
							<?php esc_html_e( 'This store type cannot create products via API. Products will be created in Shopify instead (if configured).', 'gunmerch-ai' ); ?>
						</p>
					<?php elseif ( is_wp_error( $connection ) ) : ?>
						<p class="gma-connection-status gma-status-error">
							✗ <?php echo esc_html( $connection->get_error_message() ); ?>
						</p>
					<?php endif; ?>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="gma_printful_store_id"><?php esc_html_e( 'Printful Store ID (Optional)', 'gunmerch-ai' ); ?></label>
				</th>
				<td>
					<input type="text" 
						name="gma_printful_store_id" 
						id="gma_printful_store_id" 
						value="<?php echo esc_attr( $printfull_store_id ); ?>" 
						class="regular-text">
					<p class="description">
						<strong><?php esc_html_e( 'For API Platform Stores Only:', 'gunmerch-ai' ); ?></strong><br>
						<?php esc_html_e( 'If you have a separate "Manual Order / API" store for product creation, enter its Store ID here. Products will be created via API in this store, then you push them to your Shopify-connected store in Printful\'s dashboard.', 'gunmerch-ai' ); ?>
					</p>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="gma_shopify_store_url"><?php esc_html_e( 'Shopify Store URL', 'gunmerch-ai' ); ?></label>
				</th>
				<td>
					<input type="text" 
						name="gma_shopify_store_url" 
						id="gma_shopify_store_url" 
						value="<?php echo esc_attr( $shopify_store_url ); ?>" 
						class="regular-text"
						placeholder="your-store.myshopify.com">
					<p class="description">
						<?php esc_html_e( 'Your Shopify store URL (e.g., your-store.myshopify.com). Used for auto-publishing when Printful is connected to Shopify.', 'gunmerch-ai' ); ?>
					</p>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="gma_shopify_access_token"><?php esc_html_e( 'Shopify Access Token', 'gunmerch-ai' ); ?></label>
				</th>
				<td>
					<input type="password" 
						name="gma_shopify_access_token" 
						id="gma_shopify_access_token" 
						value="<?php echo esc_attr( $shopify_access_token ); ?>" 
						class="regular-text">
					<p class="description">
						<?php esc_html_e( 'Your Shopify Admin API access token. Create a custom app in your Shopify admin to get this.', 'gunmerch-ai' ); ?>
						<a href="https://admin.shopify.com/store/YOUR_STORE/settings/apps/development" target="_blank" rel="noopener noreferrer">
							<?php esc_html_e( 'Create custom app', 'gunmerch-ai' ); ?> →
						</a>
					</p>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="gma_openai_api_key"><?php esc_html_e( 'OpenAI API Key', 'gunmerch-ai' ); ?></label>
				</th>
				<td>
					<input type="password" 
						name="gma_openai_api_key" 
						id="gma_openai_api_key" 
						value="<?php echo esc_attr( $openai_api_key ); ?>" 
						class="regular-text">
					<p class="description">
						<?php esc_html_e( 'Your OpenAI API key for AI-generated designs.', 'gunmerch-ai' ); ?>
						<a href="https://platform.openai.com/api-keys" target="_blank" rel="noopener noreferrer">
							<?php esc_html_e( 'Get your API key', 'gunmerch-ai' ); ?> →
						</a>
					</p>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="gma_gemini_api_key"><?php esc_html_e( 'Google Gemini API Key', 'gunmerch-ai' ); ?></label>
				</th>
				<td>
					<input type="password" 
						name="gma_gemini_api_key" 
						id="gma_gemini_api_key" 
						value="<?php echo esc_attr( $gemini_api_key ); ?>" 
						class="regular-text">
					<p class="description">
						<?php esc_html_e( 'Your Google Gemini/Imagen API key for AI-generated images. If set, will be used instead of OpenAI.', 'gunmerch-ai' ); ?>
						<a href="https://aistudio.google.com/app/apikey" target="_blank" rel="noopener noreferrer">
							<?php esc_html_e( 'Get your API key', 'gunmerch-ai' ); ?> →
						</a>
					</p>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="gma_reddit_client_id"><?php esc_html_e( 'Reddit Client ID', 'gunmerch-ai' ); ?></label>
				</th>
				<td>
					<input type="text" 
						name="gma_reddit_client_id" 
						id="gma_reddit_client_id" 
						value="<?php echo esc_attr( $reddit_client_id ); ?>" 
						class="regular-text">
					<p class="description"><?php esc_html_e( 'Optional. For Reddit trend scanning.', 'gunmerch-ai' ); ?></p>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="gma_reddit_client_secret"><?php esc_html_e( 'Reddit Client Secret', 'gunmerch-ai' ); ?></label>
				</th>
				<td>
					<input type="password" 
						name="gma_reddit_client_secret" 
						id="gma_reddit_client_secret" 
						value="<?php echo esc_attr( $reddit_client_secret ); ?>" 
						class="regular-text">
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Automation Settings', 'gunmerch-ai' ); ?></h2>

		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="gma_scan_frequency"><?php esc_html_e( 'Scan Frequency', 'gunmerch-ai' ); ?></label>
				</th>
				<td>
					<select name="gma_scan_frequency" id="gma_scan_frequency">
						<option value="hourly" <?php selected( $scan_frequency, 'hourly' ); ?>>
							<?php esc_html_e( 'Every Hour', 'gunmerch-ai' ); ?>
						</option>
						<option value="gma_6hours" <?php selected( $scan_frequency, 'gma_6hours' ); ?>>
							<?php esc_html_e( 'Every 6 Hours', 'gunmerch-ai' ); ?>
						</option>
						<option value="twicedaily" <?php selected( $scan_frequency, 'twicedaily' ); ?>>
							<?php esc_html_e( 'Twice Daily', 'gunmerch-ai' ); ?>
						</option>
						<option value="daily" <?php selected( $scan_frequency, 'daily' ); ?>>
							<?php esc_html_e( 'Daily', 'gunmerch-ai' ); ?>
						</option>
					</select>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="gma_designs_per_scan"><?php esc_html_e( 'Designs Per Scan', 'gunmerch-ai' ); ?></label>
				</th>
				<td>
					<input type="number" 
						name="gma_designs_per_scan" 
						id="gma_designs_per_scan" 
						value="<?php echo esc_attr( $designs_per_scan ); ?>" 
						min="1" 
						max="50" 
						class="small-text">
					<p class="description"><?php esc_html_e( 'Number of designs to generate per scan (1-50).', 'gunmerch-ai' ); ?></p>
				</td>
			</tr>

			<tr>
				<th scope="row"><?php esc_html_e( 'Auto-Approve', 'gunmerch-ai' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="gma_auto_approve" value="1" <?php checked( $auto_approve ); ?>>
						<?php esc_html_e( 'Automatically approve generated designs', 'gunmerch-ai' ); ?>
					</label>
				</td>
			</tr>

			<tr>
				<th scope="row"><?php esc_html_e( 'Auto-Publish', 'gunmerch-ai' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="gma_auto_publish_to_printful" value="1" <?php checked( $auto_publish ); ?>>
						<?php esc_html_e( 'Automatically publish approved designs to Printful', 'gunmerch-ai' ); ?>
					</label>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="gma_printful_variant_id"><?php esc_html_e( 'Printful Product', 'gunmerch-ai' ); ?></label>
				</th>
				<td>
					<select name="gma_printful_variant_id" id="gma_printful_variant_id">
						<option value="51630074396973" <?php selected( $printful_variant_id, '51630074396973' ); ?>>
							<?php esc_html_e( 'Gildan 64000 - Black XL (51630074396973)', 'gunmerch-ai' ); ?>
						</option>
					</select>
					<p class="description">
						<?php esc_html_e( 'Gildan 64000 Softstyle T-Shirt Black XL. Add more colors/sizes in Printful dashboard after products are created.', 'gunmerch-ai' ); ?>
					</p>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="gma_min_engagement"><?php esc_html_e( 'Minimum Engagement', 'gunmerch-ai' ); ?></label>
				</th>
				<td>
					<input type="number" 
						name="gma_min_engagement" 
						id="gma_min_engagement" 
						value="<?php echo esc_attr( $min_engagement ); ?>" 
						min="0" 
						class="small-text">
					<p class="description"><?php esc_html_e( 'Minimum engagement score for trends to be considered.', 'gunmerch-ai' ); ?></p>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="gma_default_margin"><?php esc_html_e( 'Default Margin %', 'gunmerch-ai' ); ?></label>
				</th>
				<td>
					<input type="number" 
						name="gma_default_margin" 
						id="gma_default_margin" 
						value="<?php echo esc_attr( $default_margin ); ?>" 
						min="0" 
						max="100" 
						class="small-text">%
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="gma_notification_email"><?php esc_html_e( 'Notification Email', 'gunmerch-ai' ); ?></label>
				</th>
				<td>
					<input type="email" 
						name="gma_notification_email" 
						id="gma_notification_email" 
						value="<?php echo esc_attr( $notification_email ); ?>" 
						class="regular-text">
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Image Generation', 'gunmerch-ai' ); ?></h2>

		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="gma_image_prompt_template"><?php esc_html_e( 'Image Prompt Template', 'gunmerch-ai' ); ?></label>
				</th>
				<td>
					<textarea name="gma_image_prompt_template" 
						id="gma_image_prompt_template" 
						rows="5" 
						class="large-text code"><?php echo esc_textarea( $image_prompt_template ); ?></textarea>
					<p class="description">
						<?php esc_html_e( 'Template for AI image generation prompts. Use placeholders:', 'gunmerch-ai' ); ?><br>
						<code>{text}</code> - <?php esc_html_e( 'The design text/slogan', 'gunmerch-ai' ); ?><br>
						<code>{concept}</code> - <?php esc_html_e( 'The design concept description', 'gunmerch-ai' ); ?><br>
						<code>{custom}</code> - <?php esc_html_e( 'Custom prompt added per-design', 'gunmerch-ai' ); ?><br>
						<code>{color_instruction}</code> - <?php esc_html_e( 'Auto-generated color/highlight instructions', 'gunmerch-ai' ); ?>
					</p>
					<p class="description">
						<strong><?php esc_html_e( 'Important:', 'gunmerch-ai' ); ?></strong> 
						<?php esc_html_e( 'Include "NO t-shirt mockup" and "NO fabric texture" to avoid generating unusable mockup images.', 'gunmerch-ai' ); ?>
					</p>
				</td>
			</tr>
		</table>

		<p class="submit">
			<input type="submit" name="gma_save_settings" class="button button-primary" value="<?php esc_attr_e( 'Save Settings', 'gunmerch-ai' ); ?>">
		</p>
	</form>
</div>