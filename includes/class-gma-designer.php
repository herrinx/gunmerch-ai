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
		$this->logger         = gunmerch_ai()->get_class( 'logger' );
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

		if ( $this->logger ) {
			$this->logger->log(
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

		if ( $this->logger ) {
			$this->logger->log_api_call(
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
		// Placeholder for future image generation.
		// This would use DALL-E or Stable Diffusion API.

		if ( $this->logger ) {
			$this->logger->log(
				'info',
				__( 'Image generation requested - feature coming in v2.0', 'gunmerch-ai' ),
				$design_id
			);
		}

		return false;
	}
}