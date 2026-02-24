<?php
/**
 * AI Design Generator class.
 *
 * @package GunMerch_AI
 * @since 1.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GMA_Designer
 *
 * Handles AI-generated t-shirt design concepts.
 *
 * @since 1.0.0
 */
class GMA_Designer {

	/**
	 * Logger instance.
	 *
	 * @since 1.0.0
	 * @var GMA_Logger|null
	 */
	private $logger;

	/**
	 * OpenAI API key.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private $openai_api_key;

	/**
	 * Design templates for fallback generation.
	 *
	 * @since 1.0.0
	 * @var array
	 */
	private $design_templates;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		// Lazy load logger to avoid infinite loop during singleton construction.
		$this->logger         = null;
		$this->openai_api_key = get_option( 'gma_openai_api_key', '' );

		$this->design_templates = array(
			'ammo'        => array(
				'I dont always reload, but when I do... I have anxiety.',
				'Keep calm and carry 9mm.',
				'Ammo is the new Bitcoin.',
			),
			'rights'      => array(
				'Shall not be infringed.',
				'Molon Labe.',
				'Come and take it.',
			),
			'humor'       => array(
				'Boating accident survivor.',
				'My other car is a tactical golf cart.',
				'I work out so I can carry more ammo.',
			),
			'politics'    => array(
				'ATF: Always Taxing Freedom.',
				'Concealed is concealed.',
				'The Second Amendment is my permit.',
			),
			'enthusiast'  => array(
				'Guns and coffee.',
				'Veteran owned, American made.',
				'Life, liberty, and the pursuit of suppressors.',
			),
		);

		$this->register_hooks();
	}

	/**
	 * Get logger instance lazily to avoid infinite loop during construction.
	 *
	 * @since 1.0.2
	 * @return GMA_Logger|null
	 */
	private function get_logger() {
		if ( null === $this->logger && function_exists( 'gunmerch_ai' ) ) {
			$plugin = gunmerch_ai();
			if ( method_exists( $plugin, 'get_class' ) ) {
				$this->logger = $plugin->get_class( 'logger' );
			}
		}
		return $this->logger;
	}

	/**
	 * Register WordPress hooks.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function register_hooks() {
		// No additional hooks needed currently.
	}

	/**
	 * Generate designs from current trends.
	 *
	 * @since 1.0.0
	 * @param int $count Number of designs to generate.
	 * @return array Array of created design IDs.
	 */
	public function generate_designs( $count = 10 ) {
		$trends_class = gunmerch_ai()->get_class( 'trends' );
		$core         = gunmerch_ai()->get_class( 'core' );

		if ( ! $trends_class || ! $core ) {
			return array();
		}

		// Get current trends.
		$trends = $trends_class->get_current_trends( $count );

		// If no trends found, use mock data.
		if ( empty( $trends ) ) {
			$trends = $trends_class->get_mock_trends();
		}

		$created_designs = array();

		foreach ( array_slice( $trends, 0, $count ) as $trend ) {
			// Generate concept from trend.
			$design_data = $this->create_design_from_trend( $trend );

			if ( $design_data ) {
				$design_id = $core->create_design( $design_data );

				if ( $design_id ) {
					$created_designs[] = $design_id;
				}
			}
		}

		// Store notification for admin.
		if ( ! empty( $created_designs ) ) {
			set_transient(
				'gma_new_designs_notification',
				sprintf(
					/* translators: %d: Number of new designs */
					__( '%d new designs generated from trending topics', 'gunmerch-ai' ),
					count( $created_designs )
				),
				HOUR_IN_SECONDS
			);
		}

		$logger = $this->get_logger();
		if ( $logger ) {
			$logger->log(
				'info',
				sprintf(
					/* translators: %d: Number of designs generated */
					__( 'Generated %d designs from trends', 'gunmerch-ai' ),
					count( $created_designs )
				)
			);
		}

		return $created_designs;
	}

	/**
	 * Create design data from a trend.
	 *
	 * @since 1.0.0
	 * @param array $trend Trend data.
	 * @return array|false Design data or false.
	 */
	private function create_design_from_trend( $trend ) {
		$topic = $trend['topic'];

		// Try AI generation if API key is available.
		if ( ! empty( $this->openai_api_key ) ) {
			$ai_design = $this->generate_with_openai( $topic );
			if ( $ai_design ) {
				return array(
					'title'            => sanitize_text_field( $ai_design['title'] ),
					'concept'          => sanitize_textarea_field( $ai_design['concept'] ),
					'design_text'      => sanitize_text_field( $ai_design['design_text'] ),
					'design_type'      => 'text',
					'trend_topic'      => sanitize_text_field( $topic ),
					'trend_source'     => ! empty( $trend['source_url'] ) ? esc_url_raw( $trend['source_url'] ) : '',
					'estimated_margin' => 40,
					'mockup_url'       => '',
				);
			}
		}

		// Fallback to template-based generation.
		return $this->generate_from_template( $topic, $trend );
	}

	/**
	 * Generate design using OpenAI API.
	 *
	 * @since 1.0.0
	 * @param string $topic Trend topic.
	 * @return array|false Generated design or false.
	 */
	private function generate_with_openai( $topic ) {
		$api_url = 'https://api.openai.com/v1/chat/completions';

		$prompt = $this->build_prompt( $topic );

		$response = wp_remote_post(
			$api_url,
			array(
				'timeout' => 60,
				'headers' => array(
					'Authorization' => 'Bearer ' . sanitize_text_field( $this->openai_api_key ),
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'model'       => 'gpt-3.5-turbo',
						'messages'    => array(
							array(
								'role'    => 'system',
								'content' => 'You are a creative t-shirt designer specializing in gun culture, 2A rights, and firearm enthusiast apparel. Create funny, clever, and engaging t-shirt designs.',
							),
							array(
								'role'    => 'user',
								'content' => $prompt,
							),
						),
						'temperature' => 0.8,
						'max_tokens'  => 200,
					)
				),
			)
		);

		// Log API call.
		$success = ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response );

		$logger = $this->get_logger();
		if ( $logger ) {
			$logger->log_api_call(
				'openai',
				'/v1/chat/completions',
				array( 'prompt' => $prompt ),
				is_wp_error( $response ) ? $response->get_error_message() : wp_remote_retrieve_body( $response ),
				$success
			);
		}

		if ( ! $success ) {
			return false;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $body['choices'][0]['message']['content'] ) ) {
			return false;
		}

		$content = $body['choices'][0]['message']['content'];

		// Parse the response.
		return $this->parse_ai_response( $content, $topic );
	}

	/**
	 * Build the AI prompt.
	 *
	 * @since 1.0.0
	 * @param string $topic Trend topic.
	 * @return string The prompt.
	 */
	private function build_prompt( $topic ) {
		return sprintf(
			"Create a t-shirt design concept based on this trending topic: '%s'

Please provide:
1. A catchy title for the design (max 5 words)
2. The main text/slogan for the t-shirt (keep it short, punchy, max 10 words)
3. A brief concept description explaining the joke/reference

Format your response like this:
Title: [Your Title Here]
Slogan: [Your Slogan Here]
Concept: [Your Concept Description]",
			sanitize_text_field( $topic )
		);
	}

	/**
	 * Parse AI response into structured data.
	 *
	 * @since 1.0.0
	 * @param string $content AI response content.
	 * @param string $topic   Original topic.
	 * @return array Parsed design data.
	 */
	private function parse_ai_response( $content, $topic ) {
		$design = array(
			'title'       => '',
			'design_text' => '',
			'concept'     => '',
		);

		// Parse title.
		if ( preg_match( '/Title:\s*(.+)/i', $content, $matches ) ) {
			$design['title'] = trim( $matches[1] );
		}

		// Parse slogan.
		if ( preg_match( '/Slogan:\s*(.+)/i', $content, $matches ) ) {
			$design['design_text'] = trim( $matches[1] );
		}

		// Parse concept.
		if ( preg_match( '/Concept:\s*(.+)/is', $content, $matches ) ) {
			$design['concept'] = trim( $matches[1] );
		}

		// Fallbacks if parsing failed.
		if ( empty( $design['title'] ) ) {
			$design['title'] = sanitize_text_field( $topic );
		}
		if ( empty( $design['design_text'] ) ) {
			$design['design_text'] = sanitize_text_field( $topic );
		}
		if ( empty( $design['concept'] ) ) {
			$design['concept'] = sprintf(
				/* translators: %s: Trend topic */
				__( 'Design inspired by trending topic: %s', 'gunmerch-ai' ),
				sanitize_text_field( $topic )
			);
		}

		return $design;
	}

	/**
	 * Generate design from template fallback.
	 *
	 * @since 1.0.0
	 * @param string $topic Trend topic.
	 * @param array  $trend Full trend data.
	 * @return array Design data.
	 */
	private function generate_from_template( $topic, $trend ) {
		// Determine category from topic keywords.
		$category = $this->categorize_topic( $topic );

		// Get templates for category.
		$templates = isset( $this->design_templates[ $category ] )
			? $this->design_templates[ $category ]
			: $this->design_templates['humor'];

		// Pick random template.
		$design_text = $templates[ array_rand( $templates ) ];

		return array(
			'title'            => sanitize_text_field( $topic ),
			'concept'          => sprintf(
				/* translators: %s: Trend topic */
				__( 'AI-generated concept based on trending topic: %s', 'gunmerch-ai' ),
				sanitize_text_field( $topic )
			),
			'design_text'      => sanitize_text_field( $design_text ),
			'design_type'      => 'text',
			'trend_topic'      => sanitize_text_field( $topic ),
			'trend_source'     => ! empty( $trend['source_url'] ) ? esc_url_raw( $trend['source_url'] ) : '',
			'estimated_margin' => 40,
			'mockup_url'       => '',
		);
	}

	/**
	 * Categorize a topic.
	 *
	 * @since 1.0.0
	 * @param string $topic Trend topic.
	 * @return string Category key.
	 */
	private function categorize_topic( $topic ) {
		$topic_lower = strtolower( $topic );

		$keywords = array(
			'ammo'       => array( 'ammo', 'ammunition', '9mm', '5.56', '223', 'bullet', 'reload' ),
			'rights'     => array( 'second amendment', '2a', 'right', 'freedom', 'constitution', 'infringed' ),
			'politics'   => array( 'atf', 'law', 'bill', 'legislation', 'ban', 'control', 'regulation' ),
			'enthusiast' => array( 'veteran', 'military', 'tactical', 'edc', 'concealed' ),
		);

		foreach ( $keywords as $category => $words ) {
			foreach ( $words as $word ) {
				if ( strpos( $topic_lower, $word ) !== false ) {
					return $category;
				}
			}
		}

		return 'humor';
	}

	/**
	 * Regenerate a design (creates new variation).
	 *
	 * @since 1.0.0
	 * @param int $design_id Original design ID.
	 * @return int|false New design ID or false.
	 */
	public function regenerate_design( $design_id ) {
		$core   = gunmerch_ai()->get_class( 'core' );
		$design = $core ? $core->get_design( $design_id ) : null;

		if ( ! $design ) {
			return false;
		}

		$topic = $core->get_design_meta( $design_id, 'trend_topic' );

		if ( empty( $topic ) ) {
			$topic = $design->post_title;
		}

		$design_data = $this->create_design_from_trend(
			array(
				'topic'       => $topic,
				'source_url'  => $core->get_design_meta( $design_id, 'trend_source' ),
			)
		);

		if ( ! $design_data ) {
			return false;
		}

		// Mark as regenerated.
		$design_data['regenerated_from'] = $design_id;

		return $core->create_design( $design_data );
	}

	/**
	 * Generate image for design (future feature).
	 *
	 * @since 1.0.0
	 * @param int $design_id Design ID.
	 * @return string|false Image URL or false.
	 */
	public function generate_image( $design_id ) {
		// Try Gemini/Imagen API first, fallback to OpenAI DALL-E.
		$gemini_key = get_option( 'gma_gemini_api_key', '' );

		if ( ! empty( $gemini_key ) ) {
			return $this->generate_image_gemini( $design_id, $gemini_key );
		}

		// Fallback to OpenAI if no Gemini key.
		if ( ! empty( $this->openai_api_key ) ) {
			return $this->generate_image_openai( $design_id );
		}

		$logger = $this->get_logger();
		if ( $logger ) {
			$logger->log(
				'error',
				__( 'Image generation failed - no API key configured', 'gunmerch-ai' ),
				$design_id
			);
		}

		return false;
	}

	/**
	 * Generate image using Google Gemini/Imagen API.
	 *
	 * @since 1.0.2
	 * @param int    $design_id Design ID.
	 * @param string $api_key   Gemini API key.
	 * @return string|false Image URL or false.
	 */
	private function generate_image_gemini( $design_id, $api_key ) {
		error_log('GMA: generate_image_gemini called for design ' . $design_id);
		
		$design = get_post( $design_id );
		if ( ! $design ) {
			error_log('GMA: design not found');
			return false;
		}

		$concept = $design->post_content;
		$title   = $design->post_title;
		
		// Get the actual design text (slogan) - this is usually better than the title.
		$design_text = $this->get_design_text( $design_id );
		error_log('GMA: design_text: ' . substr($design_text, 0, 100));
		
		// Use design_text if available, otherwise fall back to title.
		$main_text = ! empty( $design_text ) ? $design_text : $title;

		// Check for custom prompt.
		$custom_prompt = $this->get_custom_prompt( $design_id );

		// Build prompt for t-shirt design.
		if ( ! empty( $custom_prompt ) ) {
			$prompt = sprintf(
				'T-shirt design featuring the text: "%s". %sAdditional details: %s Style: bold vector graphic, suitable for screen printing, solid colors on transparent background, high resolution.',
				sanitize_text_field( $main_text ),
				! empty( $concept ) ? 'Concept: ' . sanitize_text_field( $concept ) . '. ' : '',
				sanitize_text_field( $custom_prompt )
			);
		} else {
			$prompt = sprintf(
				'T-shirt design featuring the text: "%s". %sStyle: bold vector graphic, suitable for screen printing, solid colors on transparent background, high resolution.',
				sanitize_text_field( $main_text ),
				! empty( $concept ) ? 'Concept: ' . sanitize_text_field( $concept ) . '. ' : ''
			);
		}

		// Call Gemini API using gemini-1.5-flash for image generation.
		// Uses the new unified API format with generateContent endpoint.
		error_log('GMA: Calling Gemini API...');
		$response = wp_remote_post(
			'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash-exp-image-generation:generateContent?key=' . urlencode( $api_key ),
			array(
				'timeout' => 60,
				'headers' => array(
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'contents' => array(
							array(
								'parts' => array(
									array( 'text' => $prompt ),
								),
							),
						),
						'generationConfig' => array(
							'responseModalities' => array('Text', 'Image'),
						),
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			error_log('GMA: Gemini API error: ' . $response->get_error_message());
			$logger = $this->get_logger();
			if ( $logger ) {
				$logger->log(
					'error',
					__( 'Gemini image generation failed: ', 'gunmerch-ai' ) . $response->get_error_message(),
					$design_id
				);
			}
			return false;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		error_log('GMA: Gemini response: ' . wp_json_encode($body));

		// Parse the new Gemini image generation response format.
		$image_data = null;
		if ( isset( $body['candidates'][0]['content']['parts'] ) ) {
			foreach ( $body['candidates'][0]['content']['parts'] as $part ) {
				if ( isset( $part['inlineData']['data'] ) ) {
					$image_data = base64_decode( $part['inlineData']['data'] );
					break;
				}
			}
		}

		if ( $image_data ) {

			// Save to uploads.
			$upload_dir = wp_upload_dir();
			$filename   = 'gma-design-' . $design_id . '-' . time() . '.png';
			$file_path  = $upload_dir['path'] . '/' . $filename;

			if ( file_put_contents( $file_path, $image_data ) ) {
				// Attach to design post.
				$attachment = array(
					'post_title'     => sanitize_file_name( $title ),
					'post_content'   => '',
					'post_status'    => 'inherit',
					'post_mime_type' => 'image/png',
				);

				$attach_id = wp_insert_attachment( $attachment, $file_path, $design_id );

				if ( ! is_wp_error( $attach_id ) ) {
					require_once ABSPATH . 'wp-admin/includes/image.php';
					wp_update_attachment_metadata(
						$attach_id,
						wp_generate_attachment_metadata( $attach_id, $file_path )
					);

					// Set as featured image.
					set_post_thumbnail( $design_id, $attach_id );

					$logger = $this->get_logger();
					if ( $logger ) {
						$logger->log(
							'info',
							__( 'Image generated successfully with Gemini', 'gunmerch-ai' ),
							$design_id
						);
					}

					return $upload_dir['url'] . '/' . $filename;
				}
			}
		}

		$logger = $this->get_logger();
		if ( $logger ) {
			$logger->log(
				'error',
				__( 'Gemini image generation failed - invalid response', 'gunmerch-ai' ),
				$design_id
			);
		}

		return false;
	}

	/**
	 * Get the design text (slogan) for a design.
	 *
	 * @since 1.0.2
	 * @param int $design_id Design ID.
	 * @return string Design text or empty string.
	 */
	private function get_design_text( $design_id ) {
		$core = gunmerch_ai()->get_class( 'core' );
		if ( $core ) {
			return $core->get_design_meta( $design_id, 'design_text' );
		}
		return '';
	}

	/**
	 * Get custom prompt for image generation.
	 *
	 * @since 1.0.4
	 * @param int $design_id Design ID.
	 * @return string Custom prompt or empty string.
	 */
	private function get_custom_prompt( $design_id ) {
		$core = gunmerch_ai()->get_class( 'core' );
		if ( $core ) {
			return $core->get_design_meta( $design_id, 'custom_prompt' );
		}
		return '';
	}

	/**
	 * Remove background from image using remove.bg API or GD.
	 *
	 * @since 1.0.4
	 * @param int $design_id Design ID.
	 * @return bool True on success.
	 */
	public function remove_background( $design_id ) {
		$logger = $this->get_logger();
		$thumbnail_id = get_post_thumbnail_id( $design_id );

		if ( ! $thumbnail_id ) {
			if ( $logger ) $logger->log( 'error', 'No image found to remove background', $design_id );
			return false;
		}

		$image_path = get_attached_file( $thumbnail_id );
		if ( ! $image_path || ! file_exists( $image_path ) ) {
			if ( $logger ) $logger->log( 'error', 'Image file not found', $design_id );
			return false;
		}

		// Try remove.bg API if key available.
		$removebg_key = get_option( 'gma_removebg_api_key', '' );
		if ( ! empty( $removebg_key ) ) {
			return $this->remove_background_api( $design_id, $image_path, $removebg_key );
		}

		// Fallback: Use PHP to make white/light backgrounds transparent.
		return $this->remove_background_gd( $design_id, $image_path );
	}

	/**
	 * Remove background using remove.bg API.
	 *
	 * @since 1.0.4
	 * @param int    $design_id  Design ID.
	 * @param string $image_path Path to image.
	 * @param string $api_key    remove.bg API key.
	 * @return bool True on success.
	 */
	private function remove_background_api( $design_id, $image_path, $api_key ) {
		$logger = $this->get_logger();

		$response = wp_remote_post(
			'https://api.remove.bg/v1.0/removebg',
			array(
				'timeout' => 60,
				'headers' => array(
					'X-Api-Key' => $api_key,
				),
				'body'    => array(
					'image_file' => new CURLFile( $image_path ),
					'size'       => 'auto',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			if ( $logger ) $logger->log( 'error', 'remove.bg API error: ' . $response->get_error_message(), $design_id );
			return false;
		}

		$image_data = wp_remote_retrieve_body( $response );
		if ( empty( $image_data ) ) {
			if ( $logger ) $logger->log( 'error', 'remove.bg returned empty response', $design_id );
			return false;
		}

		// Save the processed image.
		if ( file_put_contents( $image_path, $image_data ) ) {
			if ( $logger ) $logger->log( 'info', 'Background removed via remove.bg API', $design_id );
			return true;
		}

		return false;
	}

	/**
	 * Remove background using GD library (make white/light pixels transparent).
	 *
	 * @since 1.0.4
	 * @param int    $design_id  Design ID.
	 * @param string $image_path Path to image.
	 * @return bool True on success.
	 */
	private function remove_background_gd( $design_id, $image_path ) {
		$logger = $this->get_logger();

		if ( ! function_exists( 'imagecreatefrompng' ) ) {
			if ( $logger ) $logger->log( 'error', 'GD library not available', $design_id );
			return false;
		}

		$info = getimagesize( $image_path );
		if ( ! $info ) {
			return false;
		}

		// Create image from source.
		switch ( $info['mime'] ) {
			case 'image/png':
				$src = imagecreatefrompng( $image_path );
				break;
			case 'image/jpeg':
				$src = imagecreatefromjpeg( $image_path );
				break;
			default:
				return false;
		}

		if ( ! $src ) {
			return false;
		}

		$width  = imagesx( $src );
		$height = imagesy( $src );

		// Create new image with transparency.
		$dst = imagecreatetruecolor( $width, $height );
		imagealphablending( $dst, false );
		imagesavealpha( $dst, true );
		$transparent = imagecolorallocatealpha( $dst, 255, 255, 255, 127 );
		imagefill( $dst, 0, 0, $transparent );

		// Copy pixels, making near-white pixels transparent.
		for ( $x = 0; $x < $width; $x++ ) {
			for ( $y = 0; $y < $height; $y++ ) {
				$color = imagecolorat( $src, $x, $y );
				$r = ( $color >> 16 ) & 0xFF;
				$g = ( $color >> 8 ) & 0xFF;
				$b = $color & 0xFF;

				// If pixel is light (near white), make transparent.
				$threshold = 240;
				if ( $r > $threshold && $g > $threshold && $b > $threshold ) {
					imagesetpixel( $dst, $x, $y, $transparent );
				} else {
					imagesetpixel( $dst, $x, $y, $color );
				}
			}
		}

		// Save as PNG.
		$result = imagepng( $dst, $image_path );

		imagedestroy( $src );
		imagedestroy( $dst );

		if ( $result ) {
			if ( $logger ) $logger->log( 'info', 'Background removed via GD library', $design_id );
			return true;
		}

		return false;
	}

	/**
	 * Upscale image resolution.
	 *
	 * @since 1.0.4
	 * @param int $design_id Design ID.
	 * @return bool True on success.
	 */
	public function upscale_image( $design_id ) {
		$logger = $this->get_logger();
		$thumbnail_id = get_post_thumbnail_id( $design_id );

		if ( ! $thumbnail_id ) {
			if ( $logger ) $logger->log( 'error', 'No image found to upscale', $design_id );
			return false;
		}

		$image_path = get_attached_file( $thumbnail_id );
		if ( ! $image_path || ! file_exists( $image_path ) ) {
			if ( $logger ) $logger->log( 'error', 'Image file not found', $design_id );
			return false;
		}

		// Use WordPress image editor.
		$editor = wp_get_image_editor( $image_path );
		if ( is_wp_error( $editor ) ) {
			if ( $logger ) $logger->log( 'error', 'Image editor error: ' . $editor->get_error_message(), $design_id );
			return false;
		}

		// Get current size.
		$size = $editor->get_size();
		if ( ! $size ) {
			return false;
		}

		// Double the size.
		$new_width  = $size['width'] * 2;
		$new_height = $size['height'] * 2;

		// Resize (upscale).
		$result = $editor->resize( $new_width, $new_height, false );
		if ( is_wp_error( $result ) ) {
			if ( $logger ) $logger->log( 'error', 'Resize error: ' . $result->get_error_message(), $design_id );
			return false;
		}

		// Save.
		$result = $editor->save( $image_path );
		if ( is_wp_error( $result ) ) {
			if ( $logger ) $logger->log( 'error', 'Save error: ' . $result->get_error_message(), $design_id );
			return false;
		}

		// Regenerate thumbnails.
		wp_update_attachment_metadata( $thumbnail_id, wp_generate_attachment_metadata( $thumbnail_id, $image_path ) );

		if ( $logger ) $logger->log( 'info', 'Image upscaled to ' . $new_width . 'x' . $new_height, $design_id );
		return true;
	}

	/**
	 * Generate image using OpenAI DALL-E API.
	 *
	 * @since 1.0.2
	 * @param int $design_id Design ID.
	 * @return string|false Image URL or false.
	 */
	private function generate_image_openai( $design_id ) {
		$design = get_post( $design_id );
		if ( ! $design ) {
			return false;
		}

		$concept = $design->post_content;
		$title   = $design->post_title;
		
		// Get the actual design text (slogan) - this is usually better than the title.
		$design_text = $this->get_design_text( $design_id );
		
		// Use design_text if available, otherwise fall back to title.
		$main_text = ! empty( $design_text ) ? $design_text : $title;

		$prompt = sprintf(
			'T-shirt design featuring the text: "%s". %sStyle: bold vector graphic, suitable for screen printing, solid colors on transparent background.',
			sanitize_text_field( $main_text ),
			! empty( $concept ) ? 'Concept: ' . sanitize_text_field( $concept ) . '. ' : ''
		);

		$response = wp_remote_post(
			'https://api.openai.com/v1/images/generations',
			array(
				'timeout' => 60,
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $this->openai_api_key,
				),
				'body'    => wp_json_encode(
					array(
						'model'  => 'dall-e-3',
						'prompt' => $prompt,
						'size'   => '1024x1024',
						'n'      => 1,
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			$logger = $this->get_logger();
			if ( $logger ) {
				$logger->log(
					'error',
					__( 'OpenAI image generation failed: ', 'gunmerch-ai' ) . $response->get_error_message(),
					$design_id
				);
			}
			return false;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['data'][0]['url'] ) ) {
			$image_url = $body['data'][0]['url'];

			// Download and attach.
			$upload = media_sideload_image( $image_url, $design_id, $title, 'id' );

			if ( ! is_wp_error( $upload ) ) {
				set_post_thumbnail( $design_id, $upload );

				$logger = $this->get_logger();
				if ( $logger ) {
					$logger->log(
						'info',
						__( 'Image generated successfully with OpenAI', 'gunmerch-ai' ),
						$design_id
					);
				}

				return $image_url;
			}
		}

		return false;
	}
}