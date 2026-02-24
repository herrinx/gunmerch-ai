<?php
/**
 * AJAX handlers class.
 *
 * @package GunMerch_AI
 * @since 1.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GMA_AJAX
 *
 * Handles all AJAX requests for the plugin.
 *
 * @since 1.0.0
 */
class GMA_AJAX {

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->register_hooks();
	}

	/**
	 * Register AJAX hooks.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function register_hooks() {
		// Design actions.
		add_action( 'wp_ajax_gma_approve_design', array( $this, 'ajax_approve_design' ) );
		add_action( 'wp_ajax_gma_reject_design', array( $this, 'ajax_reject_design' ) );
		add_action( 'wp_ajax_gma_generate_image', array( $this, 'ajax_generate_image' ) );
		add_action( 'wp_ajax_gma_regenerate_image', array( $this, 'ajax_regenerate_image' ) );
		add_action( 'wp_ajax_gma_use_text_design', array( $this, 'ajax_use_text_design' ) );
		add_action( 'wp_ajax_gma_bulk_approve', array( $this, 'ajax_bulk_approve' ) );
		add_action( 'wp_ajax_gma_bulk_reject', array( $this, 'ajax_bulk_reject' ) );

		// Image editing actions.
		add_action( 'wp_ajax_gma_save_prompt', array( $this, 'ajax_save_prompt' ) );
		add_action( 'wp_ajax_gma_remove_background', array( $this, 'ajax_remove_background' ) );
		add_action( 'wp_ajax_gma_upscale_image', array( $this, 'ajax_upscale_image' ) );

		// Utility actions.
		add_action( 'wp_ajax_gma_scan_trends', array( $this, 'ajax_scan_trends' ) );
		add_action( 'wp_ajax_gma_generate_designs', array( $this, 'ajax_generate_designs' ) );
		add_action( 'wp_ajax_gma_test_printful', array( $this, 'ajax_test_printful' ) );
		add_action( 'wp_ajax_gma_clear_logs', array( $this, 'ajax_clear_logs' ) );
		add_action( 'wp_ajax_gma_dismiss_notice', array( $this, 'ajax_dismiss_notice' ) );

		// Fetch notifications.
		add_action( 'wp_ajax_gma_get_notifications', array( $this, 'ajax_get_notifications' ) );
	}

	/**
	 * Verify AJAX request.
	 *
	 * @since 1.0.0
	 * @param string $capability Required capability.
	 * @return bool True if verified.
	 */
	private function verify_request( $capability = 'manage_options' ) {
		// Check nonce.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'gma_admin_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'gunmerch-ai' ) ) );
			return false;
		}

		// Check capability.
		if ( ! current_user_can( $capability ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'gunmerch-ai' ) ) );
			return false;
		}

		return true;
	}

	/**
	 * AJAX: Approve design.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_approve_design() {
		if ( ! $this->verify_request( 'manage_options' ) ) {
			return;
		}

		$design_id = isset( $_POST['design_id'] ) ? absint( wp_unslash( $_POST['design_id'] ) ) : 0;

		if ( ! $design_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid design ID.', 'gunmerch-ai' ) ) );
			return;
		}

		$core = gunmerch_ai()->get_class( 'core' );

		if ( ! $core ) {
			wp_send_json_error( array( 'message' => __( 'Core functionality not available.', 'gunmerch-ai' ) ) );
			return;
		}

		$result = $core->update_design_status( $design_id, 'approved' );

		if ( $result ) {
			$design = $core->get_design( $design_id );

			// Check auto-publish setting.
			$settings = get_option( 'gma_settings', array() );
			$message  = __( 'Design approved successfully!', 'gunmerch-ai' );

			// Check if design has an image before auto-publishing.
			$has_image = has_post_thumbnail( $design_id );

			if ( ! empty( $settings['auto_publish_to_printful'] ) && $has_image ) {
				error_log('GMA: Auto-publish enabled with image, attempting to publish design ' . $design_id);
				$published = false;

				// Try Printful API store first (if store_id configured).
				$printfull = gunmerch_ai()->get_class( 'printfull' );
				if ( $printfull && $printfull->is_configured() ) {
					error_log('GMA: Printful is configured');
					$store_type = $printfull->is_api_platform_store();
					error_log('GMA: Printful store type = ' . ($store_type === true ? 'API platform' : ($store_type === false ? 'Ecommerce' : 'Error')));
					if ( true === $store_type ) {
						// This is an API platform store - can create products.
						error_log('GMA: Creating product in Printful API store');
						$pub_result = $printfull->create_product( $design_id );
						if ( ! is_wp_error( $pub_result ) ) {
							$published = true;
							$message   = sprintf(
								/* translators: %s: Design title */
								__( "Design '%s' approved and created in Printful API store (push to Shopify manually)", 'gunmerch-ai' ),
								esc_html( $design->post_title )
							);
							error_log('GMA: Printful product created successfully');
						} else {
							error_log('GMA: Printful create_product error: ' . $pub_result->get_error_message());
						}
					}
				} else {
					error_log('GMA: Printful not configured or not available');
				}

				// If Printful API store failed or not an API store, try Shopify.
				if ( ! $published ) {
					$shopify = gunmerch_ai()->get_class( 'shopify' );
					if ( $shopify && $shopify->is_configured() ) {
						error_log('GMA: Trying Shopify');
						$pub_result = $shopify->create_product( $design_id );
						if ( ! is_wp_error( $pub_result ) ) {
							$published = true;
							$message   = sprintf(
								/* translators: %s: Design title */
								__( "Design '%s' approved and sent to Shopify", 'gunmerch-ai' ),
								esc_html( $design->post_title )
							);
							error_log('GMA: Shopify product created successfully');
						} else {
							error_log('GMA: Shopify create_product error: ' . $pub_result->get_error_message());
						}
					} else {
						error_log('GMA: Shopify not configured or not available');
					}
				}
				
				if ( ! $published ) {
					error_log('GMA: Design approved but NOT published to any platform');
				}
			} elseif ( ! empty( $settings['auto_publish_to_printful'] ) && ! $has_image ) {
				// Auto-publish enabled but no image - skip publishing.
				error_log('GMA: Auto-publish skipped - no image for design ' . $design_id);
				$message = __( 'Design approved! Generate an image, then approve again to publish to Printful.', 'gunmerch-ai' );
			} else {
				error_log('GMA: Auto-publish is DISABLED in settings');
			}

			wp_send_json_success(
				array(
					'message'    => $message,
					'design_id'  => $design_id,
					'design_title' => $design ? $design->post_title : '',
				)
			);
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to approve design.', 'gunmerch-ai' ) ) );
		}
	}

	/**
	 * AJAX: Reject design.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_reject_design() {
		if ( ! $this->verify_request( 'manage_options' ) ) {
			return;
		}

		$design_id = isset( $_POST['design_id'] ) ? absint( wp_unslash( $_POST['design_id'] ) ) : 0;

		if ( ! $design_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid design ID.', 'gunmerch-ai' ) ) );
			return;
		}

		$core = gunmerch_ai()->get_class( 'core' );

		if ( ! $core ) {
			wp_send_json_error( array( 'message' => __( 'Core functionality not available.', 'gunmerch-ai' ) ) );
			return;
		}

		$result = $core->update_design_status( $design_id, 'rejected' );

		if ( $result ) {
			wp_send_json_success(
				array(
					'message'   => __( 'Design rejected.', 'gunmerch-ai' ),
					'design_id' => $design_id,
				)
			);
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to reject design.', 'gunmerch-ai' ) ) );
		}
	}

	/**
	 * AJAX: Generate image for design.
	 *
	 * @since 1.0.2
	 * @return void
	 */
	public function ajax_generate_image() {
		error_log('GMA: ajax_generate_image called');
		
		if ( ! $this->verify_request( 'manage_options' ) ) {
			error_log('GMA: verify_request failed');
			return;
		}

		$design_id = isset( $_POST['design_id'] ) ? absint( wp_unslash( $_POST['design_id'] ) ) : 0;
		error_log('GMA: design_id = ' . $design_id);

		if ( ! $design_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid design ID.', 'gunmerch-ai' ) ) );
			return;
		}

		$designer = gunmerch_ai()->get_class( 'designer' );

		if ( ! $designer ) {
			error_log('GMA: designer class not available');
			wp_send_json_error( array( 'message' => __( 'Designer not available.', 'gunmerch-ai' ) ) );
			return;
		}

		error_log('GMA: calling generate_image for design ' . $design_id);
		$result = $designer->generate_image( $design_id );
		error_log('GMA: generate_image result = ' . ($result ? 'true' : 'false'));

		if ( $result ) {
			wp_send_json_success(
				array(
					'message'   => __( 'Image generated successfully!', 'gunmerch-ai' ),
					'design_id' => $design_id,
				)
			);
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to generate image. Check API key settings.', 'gunmerch-ai' ) ) );
		}
	}

	/**
	 * AJAX: Save custom prompt for image generation.
	 *
	 * @since 1.0.4
	 * @return void
	 */
	public function ajax_save_prompt() {
		if ( ! $this->verify_request( 'manage_options' ) ) {
			return;
		}

		$design_id = isset( $_POST['design_id'] ) ? absint( wp_unslash( $_POST['design_id'] ) ) : 0;
		$prompt = isset( $_POST['prompt'] ) ? sanitize_textarea_field( wp_unslash( $_POST['prompt'] ) ) : '';
		$highlight_words = isset( $_POST['highlight_words'] ) ? sanitize_text_field( wp_unslash( $_POST['highlight_words'] ) ) : '';
		$highlight_color = isset( $_POST['highlight_color'] ) ? preg_replace( '/[^a-fA-F0-9#]/', '', wp_unslash( $_POST['highlight_color'] ) ) : '';

		if ( ! $design_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid design ID.', 'gunmerch-ai' ) ) );
			return;
		}

		$core = gunmerch_ai()->get_class( 'core' );
		if ( $core ) {
			$core->update_design_meta( $design_id, 'custom_prompt', $prompt );
			$core->update_design_meta( $design_id, 'highlight_words', $highlight_words );
			$core->update_design_meta( $design_id, 'highlight_color', $highlight_color );
		}

		wp_send_json_success(
			array(
				'message'   => __( 'Prompt saved!', 'gunmerch-ai' ),
				'design_id' => $design_id,
			)
		);
	}

	/**
	 * AJAX: Remove background from image.
	 *
	 * @since 1.0.4
	 * @return void
	 */
	public function ajax_remove_background() {
		if ( ! $this->verify_request( 'manage_options' ) ) {
			return;
		}

		$design_id = isset( $_POST['design_id'] ) ? absint( wp_unslash( $_POST['design_id'] ) ) : 0;

		if ( ! $design_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid design ID.', 'gunmerch-ai' ) ) );
			return;
		}

		$designer = gunmerch_ai()->get_class( 'designer' );
		if ( ! $designer ) {
			wp_send_json_error( array( 'message' => __( 'Designer not available.', 'gunmerch-ai' ) ) );
			return;
		}

		$result = $designer->remove_background( $design_id );

		if ( $result ) {
			// Get fresh image URL with cache buster.
			$thumbnail_id = get_post_thumbnail_id( $design_id );
			$image_url    = $thumbnail_id ? wp_get_attachment_image_url( $thumbnail_id, 'medium' ) : '';
			if ( $image_url ) {
				$image_url = add_query_arg( 't', time(), $image_url );
			}

			wp_send_json_success(
				array(
					'message'    => __( 'Background removed!', 'gunmerch-ai' ),
					'design_id'  => $design_id,
					'image_url'  => $image_url,
				)
			);
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to remove background.', 'gunmerch-ai' ) ) );
		}
	}

	/**
	 * AJAX: Upscale image resolution.
	 *
	 * @since 1.0.4
	 * @return void
	 */
	public function ajax_upscale_image() {
		if ( ! $this->verify_request( 'manage_options' ) ) {
			return;
		}

		$design_id = isset( $_POST['design_id'] ) ? absint( wp_unslash( $_POST['design_id'] ) ) : 0;

		if ( ! $design_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid design ID.', 'gunmerch-ai' ) ) );
			return;
		}

		$designer = gunmerch_ai()->get_class( 'designer' );
		if ( ! $designer ) {
			wp_send_json_error( array( 'message' => __( 'Designer not available.', 'gunmerch-ai' ) ) );
			return;
		}

		$result = $designer->upscale_image( $design_id );

		if ( $result ) {
			wp_send_json_success(
				array(
					'message'   => __( 'Image upscaled!', 'gunmerch-ai' ),
					'design_id' => $design_id,
				)
			);
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to upscale image.', 'gunmerch-ai' ) ) );
		}
	}

	/**
	 * AJAX: Regenerate image for design (delete old, create new).
	 *
	 * @since 1.0.2
	 * @return void
	 */
	public function ajax_regenerate_image() {
		if ( ! $this->verify_request( 'manage_options' ) ) {
			return;
		}

		$design_id = isset( $_POST['design_id'] ) ? absint( wp_unslash( $_POST['design_id'] ) ) : 0;

		if ( ! $design_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid design ID.', 'gunmerch-ai' ) ) );
			return;
		}

		// Delete existing featured image.
		$thumbnail_id = get_post_thumbnail_id( $design_id );
		if ( $thumbnail_id ) {
			wp_delete_attachment( $thumbnail_id, true );
			delete_post_thumbnail( $design_id );
		}

		// Generate new image.
		$designer = gunmerch_ai()->get_class( 'designer' );

		if ( ! $designer ) {
			wp_send_json_error( array( 'message' => __( 'Designer not available.', 'gunmerch-ai' ) ) );
			return;
		}

		$result = $designer->generate_image( $design_id );

		if ( $result ) {
			wp_send_json_success(
				array(
					'message'   => __( 'Image regenerated successfully!', 'gunmerch-ai' ),
					'design_id' => $design_id,
				)
			);
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to regenerate image.', 'gunmerch-ai' ) ) );
		}
	}

	/**
	 * AJAX: Generate text image for simple text designs.
	 *
	 * @since 1.0.2
	 * @return void
	 */
	public function ajax_use_text_design() {
		if ( ! $this->verify_request( 'manage_options' ) ) {
			return;
		}

		$design_id = isset( $_POST['design_id'] ) ? absint( wp_unslash( $_POST['design_id'] ) ) : 0;

		if ( ! $design_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid design ID.', 'gunmerch-ai' ) ) );
			return;
		}

		$core = gunmerch_ai()->get_class( 'core' );
		$design_text = $core ? $core->get_design_meta( $design_id, 'design_text' ) : '';
		$design = get_post( $design_id );

		if ( ! $design || empty( $design_text ) ) {
			wp_send_json_error( array( 'message' => __( 'No design text found.', 'gunmerch-ai' ) ) );
			return;
		}

		// Generate a text image using GD.
		$result = $this->generate_text_image_for_design( $design_id, $design_text, $design->post_title );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
			return;
		}

		// Mark as text design.
		update_post_meta( $design_id, '_gma_use_text_design', '1' );

		$logger = gunmerch_ai()->get_class( 'logger' );
		if ( $logger ) {
			$logger->log(
				'info',
				__( 'Text image generated for Printful', 'gunmerch-ai' ),
				$design_id
			);
		}

		wp_send_json_success(
			array(
				'message'     => __( 'Text image created and ready for Printful!', 'gunmerch-ai' ),
				'design_id'   => $design_id,
				'design_text' => $design_text,
			)
		);
	}

	/**
	 * Generate a simple text image for t-shirt printing and attach to design.
	 *
	 * @since 1.0.2
	 * @param int    $design_id Design post ID.
	 * @param string $text      The text to render.
	 * @param string $title     Design title for filename.
	 * @return string|WP_Error Image URL or error.
	 */
	private function generate_text_image_for_design( $design_id, $text, $title ) {
		// Check if GD is available.
		if ( ! function_exists( 'imagecreatetruecolor' ) ) {
			return new WP_Error( 'gd_not_available', __( 'Image generation requires GD library', 'gunmerch-ai' ) );
		}

		// Create image canvas (3000x3000 for high-res printing).
		$width = 3000;
		$height = 3000;
		$image = imagecreatetruecolor( $width, $height );

		if ( ! $image ) {
			return new WP_Error( 'gd_error', __( 'Failed to create image', 'gunmerch-ai' ) );
		}

		// Transparent background.
		imagealphablending( $image, false );
		imagesavealpha( $image, true );
		$transparent = imagecolorallocatealpha( $image, 0, 0, 0, 127 );
		imagefill( $image, 0, 0, $transparent );

		// White text.
		$white = imagecolorallocate( $image, 255, 255, 255 );

		// Font settings.
		$font_size = 120;
		$font_file = '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf';

		// Fallback if font doesn't exist.
		if ( ! file_exists( $font_file ) ) {
			// Try to find any TTF font.
			$possible_fonts = array(
				'/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
				'/usr/share/fonts/truetype/freefont/FreeSansBold.ttf',
				'/usr/share/fonts/truetype/noto/NotoSans-Bold.ttf',
			);
			foreach ( $possible_fonts as $font ) {
				if ( file_exists( $font ) ) {
					$font_file = $font;
					break;
				}
			}
		}

		// Wrap text to fit width.
		$max_width = 2600;
		$lines = $this->wrap_text( $text, $font_file, $font_size, $max_width );

		// Calculate total height and starting Y position (center vertically).
		$line_height = $font_size * 1.5;
		$total_height = count( $lines ) * $line_height;
		$start_y = ( $height - $total_height ) / 2 + $font_size;

		// Draw each line centered.
		$y = $start_y;
		foreach ( $lines as $line ) {
			$bbox = imagettfbbox( $font_size, 0, $font_file, $line );
			$line_width = $bbox[2] - $bbox[0];
			$x = ( $width - $line_width ) / 2;

			imagettftext( $image, $font_size, 0, intval( $x ), intval( $y ), $white, $font_file, $line );
			$y += $line_height;
		}

		// Save to uploads.
		$upload_dir = wp_upload_dir();
		$filename = 'gma-text-' . sanitize_title( $title ) . '-' . time() . '.png';
		$file_path = $upload_dir['path'] . '/' . $filename;

		if ( ! imagepng( $image, $file_path ) ) {
			imagedestroy( $image );
			return new WP_Error( 'save_error', __( 'Failed to save image', 'gunmerch-ai' ) );
		}

		imagedestroy( $image );

		// Attach to design post.
		$attachment = array(
			'post_title'     => sanitize_file_name( $title ),
			'post_content'   => '',
			'post_status'    => 'inherit',
			'post_mime_type' => 'image/png',
		);

		$attach_id = wp_insert_attachment( $attachment, $file_path, $design_id );

		if ( is_wp_error( $attach_id ) ) {
			return $attach_id;
		}

		require_once ABSPATH . 'wp-admin/includes/image.php';
		wp_update_attachment_metadata(
			$attach_id,
			wp_generate_attachment_metadata( $attach_id, $file_path )
		);

		// Set as featured image.
		set_post_thumbnail( $design_id, $attach_id );

		return $upload_dir['url'] . '/' . $filename;
	}

	/**
	 * Wrap text to fit within a maximum width.
	 *
	 * @since 1.0.2
	 * @param string $text      Text to wrap.
	 * @param string $font_file Path to font file.
	 * @param int    $font_size Font size.
	 * @param int    $max_width Maximum width in pixels.
	 * @return array Array of wrapped lines.
	 */
	private function wrap_text( $text, $font_file, $font_size, $max_width ) {
		$words = explode( ' ', $text );
		$lines = array();
		$current_line = '';

		foreach ( $words as $word ) {
			$test_line = $current_line ? $current_line . ' ' . $word : $word;
			$bbox = imagettfbbox( $font_size, 0, $font_file, $test_line );
			$line_width = $bbox[2] - $bbox[0];

			if ( $line_width > $max_width && $current_line ) {
				$lines[] = $current_line;
				$current_line = $word;
			} else {
				$current_line = $test_line;
			}
		}

		if ( $current_line ) {
			$lines[] = $current_line;
		}

		return $lines;
	}

	/**
	 * AJAX: Bulk approve designs.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_bulk_approve() {
		if ( ! $this->verify_request( 'manage_options' ) ) {
			return;
		}

		$design_ids = isset( $_POST['design_ids'] ) ? array_map( 'intval', wp_unslash( $_POST['design_ids'] ) ) : array();

		if ( empty( $design_ids ) ) {
			wp_send_json_error( array( 'message' => __( 'No designs selected.', 'gunmerch-ai' ) ) );
			return;
		}

		$core = gunmerch_ai()->get_class( 'core' );

		if ( ! $core ) {
			wp_send_json_error( array( 'message' => __( 'Core functionality not available.', 'gunmerch-ai' ) ) );
			return;
		}

		$approved = 0;
		$failed   = 0;

		foreach ( $design_ids as $design_id ) {
			$result = $core->update_design_status( $design_id, 'approved' );
			if ( $result ) {
				++$approved;
			} else {
				++$failed;
			}
		}

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: 1: Approved count, 2: Failed count */
					__( '%1$d designs approved. %2$d failed.', 'gunmerch-ai' ),
					$approved,
					$failed
				),
				'approved' => $approved,
				'failed'   => $failed,
			)
		);
	}

	/**
	 * AJAX: Bulk reject designs.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_bulk_reject() {
		if ( ! $this->verify_request( 'manage_options' ) ) {
			return;
		}

		$design_ids = isset( $_POST['design_ids'] ) ? array_map( 'intval', wp_unslash( $_POST['design_ids'] ) ) : array();

		if ( empty( $design_ids ) ) {
			wp_send_json_error( array( 'message' => __( 'No designs selected.', 'gunmerch-ai' ) ) );
			return;
		}

		$core = gunmerch_ai()->get_class( 'core' );

		if ( ! $core ) {
			wp_send_json_error( array( 'message' => __( 'Core functionality not available.', 'gunmerch-ai' ) ) );
			return;
		}

		$rejected = 0;
		$failed   = 0;

		foreach ( $design_ids as $design_id ) {
			$result = $core->update_design_status( $design_id, 'rejected' );
			if ( $result ) {
				++$rejected;
			} else {
				++$failed;
			}
		}

		wp_send_json_success(
			array(
				'message'  => sprintf(
					/* translators: 1: Rejected count, 2: Failed count */
					__( '%1$d designs rejected. %2$d failed.', 'gunmerch-ai' ),
					$rejected,
					$failed
				),
				'rejected' => $rejected,
				'failed'   => $failed,
			)
		);
	}

	/**
	 * AJAX: Manual trend scan.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_scan_trends() {
		if ( ! $this->verify_request( 'manage_options' ) ) {
			return;
		}

		$trends = gunmerch_ai()->get_class( 'trends' );

		if ( ! $trends ) {
			wp_send_json_error( array( 'message' => __( 'Trend scanner not available.', 'gunmerch-ai' ) ) );
			return;
		}

		$results = $trends->scan_all_sources();

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: %d: Number of trends found */
					__( 'Trend scan complete. Found %d trending topics.', 'gunmerch-ai' ),
					count( $results )
				),
				'count'   => count( $results ),
			)
		);
	}

	/**
	 * AJAX: Manual design generation.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_generate_designs() {
		if ( ! $this->verify_request( 'manage_options' ) ) {
			return;
		}

		$designer = gunmerch_ai()->get_class( 'designer' );

		if ( ! $designer ) {
			wp_send_json_error( array( 'message' => __( 'Design generator not available.', 'gunmerch-ai' ) ) );
			return;
		}

		$count   = isset( $_POST['count'] ) ? absint( wp_unslash( $_POST['count'] ) ) : 5;
		$results = $designer->generate_designs( $count );

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: %d: Number of designs generated */
					__( 'Generated %d new designs.', 'gunmerch-ai' ),
					count( $results )
				),
				'count'   => count( $results ),
			)
		);
	}

	/**
	 * AJAX: Test Printful connection.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_test_printful() {
		if ( ! $this->verify_request( 'gma_manage_settings' ) ) {
			return;
		}

		$printfull = gunmerch_ai()->get_class( 'printfull' );

		if ( ! $printfull ) {
			wp_send_json_error( array( 'message' => __( 'Printful integration not available.', 'gunmerch-ai' ) ) );
			return;
		}

		$result = $printfull->test_connection();

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		} else {
			wp_send_json_success(
				array(
					'message'     => sprintf(
						/* translators: %s: Store name */
						__( 'Connected to Printful store: %s', 'gunmerch-ai' ),
						esc_html( $result['store_name'] )
					),
					'store_name'  => $result['store_name'],
				)
			);
		}
	}

	/**
	 * AJAX: Clear logs.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_clear_logs() {
		if ( ! $this->verify_request( 'manage_options' ) ) {
			return;
		}

		$logger = gunmerch_ai()->get_class( 'logger' );

		if ( ! $logger ) {
			wp_send_json_error( array( 'message' => __( 'Logger not available.', 'gunmerch-ai' ) ) );
			return;
		}

		$deleted = $logger->delete_all_logs();

		if ( false !== $deleted ) {
			wp_send_json_success( array( 'message' => __( 'All logs cleared.', 'gunmerch-ai' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to clear logs.', 'gunmerch-ai' ) ) );
		}
	}

	/**
	 * AJAX: Dismiss notice.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_dismiss_notice() {
		// Verify nonce without dying.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'gma_admin_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'gunmerch-ai' ) ) );
			return;
		}

		// Check capability.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'gunmerch-ai' ) ) );
			return;
		}

		$notice_key = isset( $_POST['notice_key'] ) ? sanitize_key( wp_unslash( $_POST['notice_key'] ) ) : '';

		if ( $notice_key ) {
			delete_transient( $notice_key );
			wp_send_json_success();
		}

		wp_send_json_error( array( 'message' => __( 'Invalid notice key.', 'gunmerch-ai' ) ) );
	}

	/**
	 * AJAX: Get notifications.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_get_notifications() {
		// Verify nonce without dying.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'gma_admin_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'gunmerch-ai' ), 'notifications' => array() ) );
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'gunmerch-ai' ), 'notifications' => array() ) );
			return;
		}

		$notifications = array();

		// Check for various notification transients.
		$notification_keys = array(
			'gma_new_designs_notification',
			'gma_design_published_notification',
			'gma_sale_notification',
		);

		foreach ( $notification_keys as $key ) {
			$message = get_transient( $key );
			if ( $message ) {
				$notifications[] = array(
					'type' => $this->get_notification_type( $key ),
					'text' => $message,
					'key'  => $key,
				);
			}
		}

		wp_send_json_success( array( 'notifications' => $notifications ) );
	}

	/**
	 * Get notification type from key.
	 *
	 * @since 1.0.0
	 * @param string $key Notification transient key.
	 * @return string Notification type.
	 */
	private function get_notification_type( $key ) {
		if ( strpos( $key, 'sale' ) !== false ) {
			return 'success';
		}
		if ( strpos( $key, 'error' ) !== false ) {
			return 'error';
		}
		if ( strpos( $key, 'published' ) !== false ) {
			return 'success';
		}
		return 'info';
	}
}