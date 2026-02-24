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

TEXT REQUIREMENTS:
- Main text/slogan should read as a continuous sentence
- Text should primarily be white (you can emphasize 1-2 key words with color/size, but DON'T split the sentence with graphic elements)
- Keep it short and punchy (max 10 words)
- The text should flow naturally when read left-to-right, top-to-bottom
- BAD EXAMPLE: 'ATF: Always [GRAPHIC] Taxing Freedom' (interrupts reading)
- GOOD EXAMPLE: 'ATF: Always Taxing Freedom' with 'Taxing' emphasized

Please provide:
1. A catchy title for the design (max 5 words)
2. The main text/slogan for the t-shirt (continuous readable text)
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

		// Get highlight options.
		$highlight_words = $this->get_highlight_words( $design_id );
		$highlight_color = $this->get_highlight_color( $design_id );

		// Build color instruction.
		if ( ! empty( $highlight_words ) && ! empty( $highlight_color ) ) {
			$color_instruction = sprintf(
				'TEXT COLOR RULE: The entire text must be WHITE, EXCEPT the word(s) "%s" which must be colored %s. The %s word(s) should be in %s TEXT COLOR (not background, not artistic highlights, not spots - change the actual text color of those specific words only). All other text stays WHITE.',
				sanitize_text_field( $highlight_words ),
				sanitize_text_field( $highlight_color ),
				sanitize_text_field( $highlight_words ),
				sanitize_text_field( $highlight_color )
			);
		} else {
			$color_instruction = 'The text MUST be WHITE and clearly readable.';
		}

		// Get prompt template from settings.
		$settings = get_option( 'gma_settings', array() );
		$prompt_template = ! empty( $settings['image_prompt_template'] ) 
			? $settings['image_prompt_template'] 
			: 'Vector graphic design artwork featuring the text: "{text}" in WHITE. {concept} Style: bold typography, minimalist vector illustration, 2-3 flat colors, centered composition, transparent background, suitable for DTG printing. {color_instruction} Any graphic elements or illustrations should be RELEVANT to the text content and should NOT interrupt or split the text - keep the text as one continuous readable sentence. NO t-shirt mockup, NO fabric texture, NO background, just the design artwork itself.';

		// Build prompt using template.
		$prompt = str_replace(
			array( '{text}', '{concept}', '{custom}', '{color_instruction}' ),
			array(
				sanitize_text_field( $main_text ),
				! empty( $concept ) ? sanitize_text_field( $concept ) : '',
				sanitize_text_field( $custom_prompt ),
				$color_instruction
			),
			$prompt_template
		);

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
	 * Get highlight words for text emphasis.
	 *
	 * @since 1.1.5
	 * @param int $design_id Design ID.
	 * @return string Highlight words or empty string.
	 */
	private function get_highlight_words( $design_id ) {
		$core = gunmerch_ai()->get_class( 'core' );
		if ( $core ) {
			return $core->get_design_meta( $design_id, 'highlight_words' );
		}
		return '';
	}

	/**
	 * Get highlight color for text emphasis.
	 *
	 * @since 1.1.5
	 * @param int $design_id Design ID.
	 * @return string Highlight color or empty string.
	 */
	private function get_highlight_color( $design_id ) {
		$core = gunmerch_ai()->get_class( 'core' );
		if ( $core ) {
			return $core->get_design_meta( $design_id, 'highlight_color' );
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

		$is_jpeg = ( $info['mime'] === 'image/jpeg' );

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

		// Sample edge pixels to determine background color.
		$edge_colors = array();
		$sample_positions = array(
			array( 0, 0 ),
			array( $width - 1, 0 ),
			array( 0, $height - 1 ),
			array( $width - 1, $height - 1 ),
			array( intval( $width / 2 ), 0 ),
			array( intval( $width / 2 ), $height - 1 ),
			array( 0, intval( $height / 2 ) ),
			array( $width - 1, intval( $height / 2 ) ),
		);
		foreach ( $sample_positions as $pos ) {
			$edge_colors[] = imagecolorat( $src, $pos[0], $pos[1] );
		}

		// Get average background color from edges.
		$bg_r = $bg_g = $bg_b = 0;
		foreach ( $edge_colors as $color ) {
			$bg_r += ( $color >> 16 ) & 0xFF;
			$bg_g += ( $color >> 8 ) & 0xFF;
			$bg_b += $color & 0xFF;
		}
		$bg_r = intval( $bg_r / count( $edge_colors ) );
		$bg_g = intval( $bg_g / count( $edge_colors ) );
		$bg_b = intval( $bg_b / count( $edge_colors ) );
		$bg_luminance = ( 0.299 * $bg_r ) + ( 0.587 * $bg_g ) + ( 0.114 * $bg_b );

		// Determine tolerance based on background luminance.
		// Darker backgrounds need wider tolerance for gradients/shadows.
		$tolerance = ( $bg_luminance < 50 ) ? 60 : 40;

		// Create new image with transparency.
		$dst = imagecreatetruecolor( $width, $height );
		imagealphablending( $dst, false );
		imagesavealpha( $dst, true );
		$transparent = imagecolorallocatealpha( $dst, 255, 255, 255, 127 );
		imagefill( $dst, 0, 0, $transparent );

		// Copy pixels, making background pixels transparent.
		for ( $x = 0; $x < $width; $x++ ) {
			for ( $y = 0; $y < $height; $y++ ) {
				$color = imagecolorat( $src, $x, $y );
				$r = ( $color >> 16 ) & 0xFF;
				$g = ( $color >> 8 ) & 0xFF;
				$b = $color & 0xFF;

				// Calculate color distance from background (Euclidean distance in RGB space).
				$distance = sqrt(
					pow( $r - $bg_r, 2 ) +
					pow( $g - $bg_g, 2 ) +
					pow( $b - $bg_b, 2 )
				);

				// If pixel is similar to background color, make transparent.
				// Otherwise keep it (including white text).
				if ( $distance < $tolerance ) {
					imagesetpixel( $dst, $x, $y, $transparent );
				} else {
					imagesetpixel( $dst, $x, $y, $color );
				}
			}
		}

		imagedestroy( $src );

		// Auto-crop: find bounds of non-transparent content.
		$min_x = $width;
		$min_y = $height;
		$max_x = 0;
		$max_y = 0;

		for ( $x = 0; $x < $width; $x++ ) {
			for ( $y = 0; $y < $height; $y++ ) {
				$alpha = ( imagecolorat( $dst, $x, $y ) >> 24 ) & 0x7F;
				if ( $alpha < 127 ) { // Not fully transparent.
					if ( $x < $min_x ) $min_x = $x;
					if ( $x > $max_x ) $max_x = $x;
					if ( $y < $min_y ) $min_y = $y;
					if ( $y > $max_y ) $max_y = $y;
				}
			}
		}

		// Check if we found any content.
		if ( $max_x <= $min_x || $max_y <= $min_y ) {
			imagedestroy( $dst );
			if ( $logger ) $logger->log( 'error', 'No content found to crop', $design_id );
			return false;
		}

		// Add 10px padding.
		$padding = 10;
		$min_x = max( 0, $min_x - $padding );
		$min_y = max( 0, $min_y - $padding );
		$max_x = min( $width - 1, $max_x + $padding );
		$max_y = min( $height - 1, $max_y + $padding );

		$crop_width  = $max_x - $min_x + 1;
		$crop_height = $max_y - $min_y + 1;

		// Create cropped image.
		$cropped = imagecreatetruecolor( $crop_width, $crop_height );
		imagealphablending( $cropped, false );
		imagesavealpha( $cropped, true );
		$transparent_bg = imagecolorallocatealpha( $cropped, 255, 255, 255, 127 );
		imagefill( $cropped, 0, 0, $transparent_bg );

		// Copy cropped region.
		imagecopy( $cropped, $dst, 0, 0, $min_x, $min_y, $crop_width, $crop_height );
		imagedestroy( $dst );

		// Determine output path.
		if ( $is_jpeg ) {
			$png_path = preg_replace( '/\.jpe?g$/i', '.png', $image_path );
			if ( $png_path === $image_path ) {
				$png_path .= '.png';
			}
			$result = imagepng( $cropped, $png_path, 6 );
			imagedestroy( $cropped );

			if ( ! $result ) {
				if ( $logger ) $logger->log( 'error', 'Failed to save PNG', $design_id );
				return false;
			}

			// Update WordPress attachment to point to new PNG file.
			$thumbnail_id = get_post_thumbnail_id( $design_id );
			if ( $thumbnail_id ) {
				update_attached_file( $thumbnail_id, $png_path );
				wp_update_post(
					array(
						'ID'             => $thumbnail_id,
						'post_mime_type' => 'image/png',
					)
				);
				wp_update_attachment_metadata( $thumbnail_id, wp_generate_attachment_metadata( $thumbnail_id, $png_path ) );
			}

			if ( file_exists( $image_path ) && $image_path !== $png_path ) {
				unlink( $image_path );
			}

			if ( $logger ) $logger->log( 'info', 'Background removed, auto-cropped to ' . $crop_width . 'x' . $crop_height, $design_id );
			return true;
		}

		// Save as PNG (original was PNG).
		$result = imagepng( $cropped, $image_path, 6 );
		imagedestroy( $cropped );

		if ( ! $result ) {
			if ( $logger ) $logger->log( 'error', 'Failed to save PNG', $design_id );
			return false;
		}

		// Regenerate all thumbnail sizes.
		$thumbnail_id = get_post_thumbnail_id( $design_id );
		if ( $thumbnail_id ) {
			wp_update_attachment_metadata( $thumbnail_id, wp_generate_attachment_metadata( $thumbnail_id, $image_path ) );
		}

		if ( $logger ) $logger->log( 'info', 'Background removed, auto-cropped to ' . $crop_width . 'x' . $crop_height, $design_id );
		return true;
	}

	/**
	 * Upscale image resolution using best available method.
	 *
	 * Tries Imagick first (better quality), falls back to GD with sharpening.
	 * 4x upscaling for Printful-ready resolution.
	 *
	 * @since 1.1.2
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

		// Try Imagick first for best quality.
		if ( extension_loaded( 'imagick' ) ) {
			return $this->upscale_image_imagick( $design_id, $image_path, $thumbnail_id );
		}

		// Fallback to GD with sharpening.
		return $this->upscale_image_gd( $design_id, $image_path, $thumbnail_id );
	}

	/**
	 * Upscale using ImageMagick (best quality).
	 *
	 * @since 1.1.2
	 * @param int    $design_id    Design ID.
	 * @param string $image_path   Image file path.
	 * @param int    $thumbnail_id Attachment ID.
	 * @return bool True on success.
	 */
	private function upscale_image_imagick( $design_id, $image_path, $thumbnail_id ) {
		$logger = $this->get_logger();

		try {
			$imagick = new Imagick( $image_path );

			// Get original dimensions.
			$width  = $imagick->getImageWidth();
			$height = $imagick->getImageHeight();

			// 4x upscaling for Printful-ready resolution.
			$new_width  = $width * 4;
			$new_height = $height * 4;

			// Use high-quality scaling filter.
			$imagick->resizeImage( $new_width, $new_height, Imagick::FILTER_LANCZOS, 1 );

			// Sharpen to counteract blur from upscaling.
			$imagick->unsharpMaskImage( 0, 0.5, 1, 0.05 );

			// Set PNG quality for transparency support.
			$imagick->setImageFormat( 'PNG32' );
			$imagick->writeImage( $image_path );

			$imagick->destroy();

			// Update metadata and regenerate thumbnails.
			$metadata = wp_generate_attachment_metadata( $thumbnail_id, $image_path );
			wp_update_attachment_metadata( $thumbnail_id, $metadata );

			if ( $logger ) $logger->log( 'info', 'Image upscaled 4x using ImageMagick to ' . $new_width . 'x' . $new_height, $design_id );
			return true;
		} catch ( Exception $e ) {
			if ( $logger ) $logger->log( 'error', 'ImageMagick error: ' . $e->getMessage(), $design_id );
			// Fallback to GD.
			return $this->upscale_image_gd( $design_id, $image_path, $thumbnail_id );
		}
	}

	/**
	 * Upscale using GD library with sharpening.
	 *
	 * @since 1.1.2
	 * @param int    $design_id    Design ID.
	 * @param string $image_path   Image file path.
	 * @param int    $thumbnail_id Attachment ID.
	 * @return bool True on success.
	 */
	private function upscale_image_gd( $design_id, $image_path, $thumbnail_id ) {
		$logger = $this->get_logger();

		$info = getimagesize( $image_path );
		if ( ! $info ) {
			if ( $logger ) $logger->log( 'error', 'Could not get image info', $design_id );
			return false;
		}

		// Create source image.
		switch ( $info['mime'] ) {
			case 'image/png':
				$src = imagecreatefrompng( $image_path );
				break;
			case 'image/jpeg':
				$src = imagecreatefromjpeg( $image_path );
				break;
			case 'image/gif':
				$src = imagecreatefromgif( $image_path );
				break;
			default:
				if ( $logger ) $logger->log( 'error', 'Unsupported image type: ' . $info['mime'], $design_id );
				return false;
		}

		if ( ! $src ) {
			if ( $logger ) $logger->log( 'error', 'Failed to create image from file', $design_id );
			return false;
		}

		// Get original dimensions.
		$width  = imagesx( $src );
		$height = imagesy( $src );

		// 4x upscaling.
		$new_width  = $width * 4;
		$new_height = $height * 4;

		// Create new image with transparency support.
		$dst = imagecreatetruecolor( $new_width, $new_height );

		// Handle transparency for PNG images.
		if ( $info['mime'] === 'image/png' ) {
			imagealphablending( $dst, false );
			imagesavealpha( $dst, true );
			$transparent = imagecolorallocatealpha( $dst, 0, 0, 0, 127 );
			imagefill( $dst, 0, 0, $transparent );
		}

		// High quality resize.
		imagecopyresampled( $dst, $src, 0, 0, 0, 0, $new_width, $new_height, $width, $height );

		// Apply sharpening convolution matrix.
		// This is a basic sharpen kernel to counteract blur from upscaling.
		$sharpen_matrix = array(
			array( 0, -1, 0 ),
			array( -1, 5, -1 ),
			array( 0, -1, 0 ),
		);
		$divisor = 1;
		$offset  = 0;
		imageconvolution( $dst, $sharpen_matrix, $divisor, $offset );

		// Clean up source.
		imagedestroy( $src );

		// Save based on format.
		$result = false;
		switch ( $info['mime'] ) {
			case 'image/png':
				$result = imagepng( $dst, $image_path, 3 ); // Lower compression for better quality.
				break;
			case 'image/jpeg':
				$result = imagejpeg( $dst, $image_path, 98 ); // High JPEG quality.
				break;
			case 'image/gif':
				$result = imagegif( $dst, $image_path );
				break;
		}

		imagedestroy( $dst );

		if ( ! $result ) {
			if ( $logger ) $logger->log( 'error', 'Failed to save upscaled image', $design_id );
			return false;
		}

		// Update metadata.
		$metadata = wp_generate_attachment_metadata( $thumbnail_id, $image_path );
		wp_update_attachment_metadata( $thumbnail_id, $metadata );

		if ( $logger ) $logger->log( 'info', 'Image upscaled 4x using GD to ' . $new_width . 'x' . $new_height, $design_id );
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
		$custom_prompt = $this->get_custom_prompt( $design_id );
		
		// Use design_text if available, otherwise fall back to title.
		$main_text = ! empty( $design_text ) ? $design_text : $title;

		// Get highlight options.
		$highlight_words = $this->get_highlight_words( $design_id );
		$highlight_color = $this->get_highlight_color( $design_id );

		// Build color instruction.
		if ( ! empty( $highlight_words ) && ! empty( $highlight_color ) ) {
			$color_instruction = sprintf(
				'TEXT COLOR RULE: The entire text must be WHITE, EXCEPT the word(s) "%s" which must be colored %s. The %s word(s) should be in %s TEXT COLOR (not background, not artistic highlights, not spots - change the actual text color of those specific words only). All other text stays WHITE.',
				sanitize_text_field( $highlight_words ),
				sanitize_text_field( $highlight_color ),
				sanitize_text_field( $highlight_words ),
				sanitize_text_field( $highlight_color )
			);
		} else {
			$color_instruction = 'The text MUST be WHITE and clearly readable.';
		}

		// Get prompt template from settings.
		$settings = get_option( 'gma_settings', array() );
		$prompt_template = ! empty( $settings['image_prompt_template'] ) 
			? $settings['image_prompt_template'] 
			: 'Vector graphic design artwork featuring the text: "{text}" in WHITE. {concept} Style: bold typography, minimalist vector illustration, 2-3 flat colors, centered composition, transparent background, suitable for DTG printing. {color_instruction} Any graphic elements or illustrations should be RELEVANT to the text content and should NOT interrupt or split the text - keep the text as one continuous readable sentence. NO t-shirt mockup, NO fabric texture, NO background, just the design artwork itself.';

		// Build prompt using template.
		$prompt = str_replace(
			array( '{text}', '{concept}', '{custom}', '{color_instruction}' ),
			array(
				sanitize_text_field( $main_text ),
				! empty( $concept ) ? sanitize_text_field( $concept ) : '',
				sanitize_text_field( $custom_prompt ),
				$color_instruction
			),
			$prompt_template
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