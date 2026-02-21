<?php
/**
 * Trend scanner class.
 *
 * @package GunMerch_AI
 * @since 1.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GMA_Trends
 *
 * Handles trend scanning from various sources.
 *
 * @since 1.0.0
 */
class GMA_Trends {

	/**
	 * Available trend sources.
	 *
	 * @since 1.0.0
	 * @var array
	 */
	private $sources;

	/**
	 * Logger instance.
	 *
	 * @since 1.0.0
	 * @var GMA_Logger|null
	 */
	private $logger;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->logger = gunmerch_ai()->get_class( 'logger' );

		$this->sources = array(
			'reddit'    => array(
				'name'    => __( 'Reddit r/guns', 'gunmerch-ai' ),
				'enabled' => true,
				'url'     => 'https://www.reddit.com/r/guns/hot.json',
			),
			'news'      => array(
				'name'    => __( 'Gun News Feeds', 'gunmerch-ai' ),
				'enabled' => true,
				'feeds'   => array(
					'https://www.ammoland.com/feed/',
					'https://www.thefirearmblog.com/feed/',
				),
			),
			'mock'      => array(
				'name'    => __( 'Mock Data', 'gunmerch-ai' ),
				'enabled' => true,
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
	 * Scan all enabled sources.
	 *
	 * @since 1.0.0
	 * @return array Array of discovered trends.
	 */
	public function scan_all_sources() {
		$all_trends = array();

		foreach ( $this->sources as $source_key => $source_config ) {
			if ( empty( $source_config['enabled'] ) ) {
				continue;
			}

			$trends = $this->scan_source( $source_key, $source_config );
			$all_trends = array_merge( $all_trends, $trends );
		}

		// Sort by engagement score.
		usort(
			$all_trends,
			function( $a, $b ) {
				return $b['engagement_score'] - $a['engagement_score'];
			}
		);

		// Store in database.
		foreach ( $all_trends as $trend ) {
			$this->store_trend( $trend );
		}

		// Cache in transient.
		set_transient( 'gma_current_trends', $all_trends, HOUR_IN_SECONDS );

		if ( $this->logger ) {
			$this->logger->log(
				'info',
				sprintf(
					/* translators: %d: Number of trends found */
					__( 'Scanned all sources: %d trends found', 'gunmerch-ai' ),
					count( $all_trends )
				)
			);
		}

		return $all_trends;
	}

	/**
	 * Scan a specific source.
	 *
	 * @since 1.0.0
	 * @param string $source_key   Source key.
	 * @param array  $source_config Source configuration.
	 * @return array Array of trends.
	 */
	private function scan_source( $source_key, $source_config ) {
		switch ( $source_key ) {
			case 'reddit':
				return $this->scan_reddit( $source_config );
			case 'news':
				return $this->scan_news_feeds( $source_config );
			case 'mock':
				return $this->get_mock_trends();
			default:
				return array();
		}
	}

	/**
	 * Scan Reddit for trends.
	 *
	 * @since 1.0.0
	 * @param array $config Source configuration.
	 * @return array Array of trends.
	 */
	private function scan_reddit( $config ) {
		$trends = array();

		// Check if we have Reddit API credentials.
		$client_id     = get_option( 'gma_reddit_client_id' );
		$client_secret = get_option( 'gma_reddit_client_secret' );

		if ( ! empty( $client_id ) && ! empty( $client_secret ) ) {
			// Use Reddit API.
			$response = wp_remote_get(
				$config['url'],
				array(
					'headers' => array(
						'User-Agent' => 'GunMerchAI/1.0 (by /u/gunmerch)',
					),
					'timeout' => 30,
				)
			);

			if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
				$body = json_decode( wp_remote_retrieve_body( $response ), true );

				if ( isset( $body['data']['children'] ) ) {
					foreach ( $body['data']['children'] as $post ) {
						$post_data = $post['data'];

						// Skip low engagement posts.
						if ( $post_data['score'] < 10 ) {
							continue;
						}

						$trends[] = array(
							'topic'            => sanitize_text_field( $post_data['title'] ),
							'source'           => 'reddit',
							'source_url'       => esc_url_raw( 'https://reddit.com' . $post_data['permalink'] ),
							'engagement_score' => absint( $post_data['score'] ) + absint( $post_data['num_comments'] ),
						);
					}
				}
			}
		}

		return $trends;
	}

	/**
	 * Scan news feeds for trends.
	 *
	 * @since 1.0.0
	 * @param array $config Source configuration.
	 * @return array Array of trends.
	 */
	private function scan_news_feeds( $config ) {
		$trends = array();

		if ( ! function_exists( 'fetch_feed' ) ) {
			require_once ABSPATH . WPINC . '/feed.php';
		}

		foreach ( $config['feeds'] as $feed_url ) {
			$feed = fetch_feed( $feed_url );

			if ( is_wp_error( $feed ) ) {
				if ( $this->logger ) {
					$this->logger->log( 'warning', 'Failed to fetch feed: ' . esc_url( $feed_url ) );
				}
				continue;
			}

			$items = $feed->get_items( 0, 10 );

			foreach ( $items as $item ) {
				$trends[] = array(
					'topic'            => sanitize_text_field( $item->get_title() ),
					'source'           => 'news',
					'source_url'       => esc_url_raw( $item->get_permalink() ),
					'engagement_score' => 50, // Default score for news items.
				);
			}
		}

		return $trends;
	}

	/**
	 * Get mock trends for testing.
	 *
	 * @since 1.0.0
	 * @return array Array of mock trends.
	 */
	public function get_mock_trends() {
		$mock_trends = array(
			array(
				'topic'            => 'New ATF pistol brace rule controversy',
				'source'           => 'mock',
				'source_url'       => 'https://example.com/atf-brace-rule',
				'engagement_score' => 850,
			),
			array(
				'topic'            => 'Best concealed carry holsters 2024',
				'source'           => 'mock',
				'source_url'       => 'https://example.com/best-holsters',
				'engagement_score' => 620,
			),
			array(
				'topic'            => '9mm vs .45 ACP debate heats up again',
				'source'           => 'mock',
				'source_url'       => 'https://example.com/9mm-vs-45',
				'engagement_score' => 540,
			),
			array(
				'topic'            => 'Boating accident meme goes viral',
				'source'           => 'mock',
				'source_url'       => 'https://example.com/boating-accident',
				'engagement_score' => 920,
			),
			array(
				'topic'            => 'New concealed carry reciprocity bill',
				'source'           => 'mock',
				'source_url'       => 'https://example.com/reciprocity',
				'engagement_score' => 780,
			),
			array(
				'topic'            => 'Glock vs Sig Sauer reliability test',
				'source'           => 'mock',
				'source_url'       => 'https://example.com/glock-vs-sig',
				'engagement_score' => 430,
			),
			array(
				'topic'            => 'Ammo shortage tips and tricks',
				'source'           => 'mock',
				'source_url'       => 'https://example.com/ammo-shortage',
				'engagement_score' => 390,
			),
			array(
				'topic'            => 'First time gun buyer guide',
				'source'           => 'mock',
				'source_url'       => 'https://example.com/first-gun',
				'engagement_score' => 510,
			),
		);

		return $mock_trends;
	}

	/**
	 * Store trend in database.
	 *
	 * @since 1.0.0
	 * @param array $trend Trend data.
	 * @return int|false Trend ID or false on failure.
	 */
	public function store_trend( $trend ) {
		global $wpdb;

		// Check if trend already exists (same topic within last 24 hours).
		$existing = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}gma_trends 
				WHERE topic = %s AND discovered_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
				LIMIT 1",
				sanitize_text_field( $trend['topic'] )
			)
		);

		if ( $existing ) {
			// Update engagement score if higher.
			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->prefix . 'gma_trends',
				array( 'engagement_score' => absint( $trend['engagement_score'] ) ),
				array( 'id' => $existing ),
				array( '%d' ),
				array( '%d' )
			);
			return $existing;
		}

		// Insert new trend.
		$result = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prefix . 'gma_trends',
			array(
				'topic'            => sanitize_text_field( $trend['topic'] ),
				'source'           => sanitize_key( $trend['source'] ),
				'source_url'       => ! empty( $trend['source_url'] ) ? esc_url_raw( $trend['source_url'] ) : '',
				'engagement_score' => absint( $trend['engagement_score'] ),
				'discovered_at'    => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%d', '%s' )
		);

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Get trends from database.
	 *
	 * @since 1.0.0
	 * @param array $args Query arguments.
	 * @return array Array of trends.
	 */
	public function get_trends( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'limit'     => 20,
			'offset'    => 0,
			'source'    => '',
			'hours'     => 24,
			'order_by'  => 'engagement_score',
			'order'     => 'DESC',
		);

		$args = wp_parse_args( $args, $defaults );

		$where   = array( '1=1' );
		$prepare = array();

		if ( ! empty( $args['source'] ) ) {
			$where[]   = 'source = %s';
			$prepare[] = sanitize_key( $args['source'] );
		}

		if ( ! empty( $args['hours'] ) ) {
			$where[]   = 'discovered_at > DATE_SUB(NOW(), INTERVAL %d HOUR)';
			$prepare[] = absint( $args['hours'] );
		}

		$order_by = in_array( $args['order_by'], array( 'id', 'engagement_score', 'discovered_at' ), true )
			? sanitize_key( $args['order_by'] )
			: 'engagement_score';
		$order    = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';
		$limit    = absint( $args['limit'] );
		$offset   = absint( $args['offset'] );

		$where_clause = implode( ' AND ', $where );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}gma_trends WHERE {$where_clause} ORDER BY {$order_by} {$order} LIMIT %d OFFSET %d",
				array_merge( $prepare, array( $limit, $offset ) )
			),
			ARRAY_A
		);
	}

	/**
	 * Get a single trend.
	 *
	 * @since 1.0.0
	 * @param int $trend_id Trend ID.
	 * @return array|null Trend data or null.
	 */
	public function get_trend( $trend_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}gma_trends WHERE id = %d",
				absint( $trend_id )
			),
			ARRAY_A
		);
	}

	/**
	 * Get current trends (from transient or scan).
	 *
	 * @since 1.0.0
	 * @param int $limit Number of trends to return.
	 * @return array Array of trends.
	 */
	public function get_current_trends( $limit = 10 ) {
		$trends = get_transient( 'gma_current_trends' );

		if ( false === $trends ) {
			$trends = $this->get_trends( array( 'limit' => $limit ) );
		}

		return array_slice( $trends, 0, $limit );
	}

	/**
	 * Clear old trends.
	 *
	 * @since 1.0.0
	 * @param int $days Days to keep.
	 * @return int Number of deleted rows.
	 */
	public function clear_old_trends( $days = 7 ) {
		global $wpdb;

		$days = absint( $days );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}gma_trends WHERE discovered_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
				$days
			)
		);
	}
}