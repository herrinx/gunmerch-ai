<?php
/**
 * Logging class.
 *
 * @package GunMerch_AI
 * @since 1.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GMA_Logger
 *
 * Handles all plugin logging to database and optional file.
 *
 * @since 1.0.0
 */
class GMA_Logger {

	/**
	 * Log levels.
	 *
	 * @since 1.0.0
	 * @var array
	 */
	private $log_levels = array( 'debug', 'info', 'warning', 'error', 'system' );

	/**
	 * Whether to also log to file.
	 *
	 * @since 1.0.0
	 * @var bool
	 */
	private $log_to_file;

	/**
	 * Log file path.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private $log_file;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$settings            = get_option( 'gma_settings', array() );
		$this->log_to_file   = ! empty( $settings['log_to_file'] );
		$this->log_file      = WP_CONTENT_DIR . '/uploads/gunmerch-ai/logs/debug.log';
	}

	/**
	 * Log a message.
	 *
	 * @since 1.0.0
	 * @param string $type      Log type (debug, info, warning, error, system).
	 * @param string $message   Log message.
	 * @param int    $design_id Optional design ID.
	 * @param array  $meta      Optional metadata.
	 * @return int|false Log ID or false on failure.
	 */
	public function log( $type, $message, $design_id = 0, $meta = array() ) {
		global $wpdb;

		// Validate log type.
		if ( ! in_array( $type, $this->log_levels, true ) ) {
			$type = 'info';
		}

		// Sanitize inputs.
		$type      = sanitize_key( $type );
		$message   = sanitize_textarea_field( $message );
		$design_id = absint( $design_id );
		$meta_json = ! empty( $meta ) ? wp_json_encode( $meta ) : null;

		// Insert into database.
		$result = $wpdb->insert(
			$wpdb->prefix . 'gma_logs',
			array(
				'log_type'   => $type,
				'message'    => $message,
				'design_id'  => $design_id > 0 ? $design_id : null,
				'meta'       => $meta_json,
				'created_at' => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%d', '%s', '%s' )
		);

		$log_id = $result ? $wpdb->insert_id : false;

		// Also log to file if enabled.
		if ( $this->log_to_file ) {
			$this->log_to_file( $type, $message, $design_id, $meta );
		}

		// Trigger action for real-time notifications.
		if ( 'error' === $type || 'warning' === $type ) {
			do_action( 'gma_log_error', $type, $message, $design_id );
		}

		return $log_id;
	}

	/**
	 * Log API call.
	 *
	 * @since 1.0.0
	 * @param string $api_name API name (printfull, openai, reddit, etc).
	 * @param string $endpoint API endpoint.
	 * @param array  $request  Request data.
	 * @param mixed  $response Response data.
	 * @param bool   $success  Whether the call succeeded.
	 * @return int|false Log ID or false on failure.
	 */
	public function log_api_call( $api_name, $endpoint, $request, $response, $success = true ) {
		$type = $success ? 'info' : 'error';

		// Don't log sensitive data like API keys.
		if ( isset( $request['headers'] ) ) {
			$headers = $request['headers'];
			if ( is_array( $headers ) ) {
				foreach ( $headers as $key => $value ) {
					if ( stripos( $key, 'authorization' ) !== false || stripos( $key, 'api-key' ) !== false ) {
						$headers[ $key ] = '***REDACTED***';
					}
				}
				$request['headers'] = $headers;
			}
		}

		$message = sprintf(
			/* translators: 1: API name, 2: Endpoint */
			__( 'API Call: %1$s - %2$s', 'gunmerch-ai' ),
			esc_html( $api_name ),
			esc_html( $endpoint )
		);

		$meta = array(
			'api_name' => sanitize_key( $api_name ),
			'endpoint' => sanitize_text_field( $endpoint ),
			'request'  => $this->sanitize_for_log( $request ),
			'response' => $this->sanitize_for_log( $response ),
			'success'  => (bool) $success,
		);

		return $this->log( $type, $message, 0, $meta );
	}

	/**
	 * Log design action.
	 *
	 * @since 1.0.0
	 * @param int    $design_id Design ID.
	 * @param string $action    Action performed (created, approved, rejected, etc).
	 * @param int    $user_id   User who performed the action.
	 * @param array  $data      Additional data.
	 * @return int|false Log ID or false on failure.
	 */
	public function log_design_action( $design_id, $action, $user_id = 0, $data = array() ) {
		$design_id = absint( $design_id );
		$user_id   = $user_id ? absint( $user_id ) : get_current_user_id();
		$action    = sanitize_key( $action );

		$message = sprintf(
			/* translators: 1: Action, 2: Design ID */
			__( 'Design %1$s: ID %2$d', 'gunmerch-ai' ),
			esc_html( $action ),
			$design_id
		);

		$meta = array(
			'action'  => $action,
			'user_id' => $user_id,
			'data'    => $this->sanitize_for_log( $data ),
		);

		return $this->log( 'info', $message, $design_id, $meta );
	}

	/**
	 * Get logs.
	 *
	 * @since 1.0.0
	 * @param array $args Query arguments.
	 * @return array Array of log objects.
	 */
	public function get_logs( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'type'       => '',
			'design_id'  => 0,
			'limit'      => 50,
			'offset'     => 0,
			'order_by'   => 'created_at',
			'order'      => 'DESC',
			'date_from'  => '',
			'date_to'    => '',
		);

		$args = wp_parse_args( $args, $defaults );

		$where   = array( '1=1' );
		$prepare = array();

		if ( ! empty( $args['type'] ) ) {
			$where[]   = 'log_type = %s';
			$prepare[] = sanitize_key( $args['type'] );
		}

		if ( ! empty( $args['design_id'] ) ) {
			$where[]   = 'design_id = %d';
			$prepare[] = absint( $args['design_id'] );
		}

		if ( ! empty( $args['date_from'] ) ) {
			$where[]   = 'created_at >= %s';
			$prepare[] = sanitize_text_field( $args['date_from'] );
		}

		if ( ! empty( $args['date_to'] ) ) {
			$where[]   = 'created_at <= %s';
			$prepare[] = sanitize_text_field( $args['date_to'] );
		}

		$order_by = in_array( $args['order_by'], array( 'id', 'created_at', 'log_type' ), true )
			? sanitize_key( $args['order_by'] )
			: 'created_at';
		$order    = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';
		$limit    = absint( $args['limit'] );
		$offset   = absint( $args['offset'] );

		$where_clause = implode( ' AND ', $where );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$logs = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}gma_logs WHERE {$where_clause} ORDER BY {$order_by} {$order} LIMIT %d OFFSET %d",
				array_merge( $prepare, array( $limit, $offset ) )
			)
		);

		// Decode meta JSON.
		foreach ( $logs as &$log ) {
			if ( ! empty( $log->meta ) ) {
				$log->meta = json_decode( $log->meta, true );
			}
		}

		return $logs;
	}

	/**
	 * Get log count.
	 *
	 * @since 1.0.0
	 * @param string $type Optional log type filter.
	 * @return int Log count.
	 */
	public function get_log_count( $type = '' ) {
		global $wpdb;

		$where = '';
		if ( ! empty( $type ) ) {
			$where = $wpdb->prepare( ' WHERE log_type = %s', sanitize_key( $type ) );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}gma_logs{$where}" );
	}

	/**
	 * Clear old logs.
	 *
	 * @since 1.0.0
	 * @param int $days Number of days to keep.
	 * @return int Number of deleted rows.
	 */
	public function clear_old_logs( $days = 30 ) {
		global $wpdb;

		$days  = absint( $days );
		$date  = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}gma_logs WHERE created_at < %s",
				$date
			)
		);
	}

	/**
	 * Delete all logs.
	 *
	 * @since 1.0.0
	 * @return int Number of deleted rows.
	 */
	public function delete_all_logs() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}gma_logs" );
	}

	/**
	 * Sanitize data for logging.
	 *
	 * @since 1.0.0
	 * @param mixed $data Data to sanitize.
	 * @return mixed Sanitized data.
	 */
	private function sanitize_for_log( $data ) {
		if ( is_array( $data ) ) {
			$sanitized = array();
			foreach ( $data as $key => $value ) {
				$sanitized[ sanitize_key( $key ) ] = $this->sanitize_for_log( $value );
			}
			return $sanitized;
		}

		if ( is_object( $data ) ) {
			return $this->sanitize_for_log( (array) $data );
		}

		if ( is_string( $data ) ) {
			return sanitize_textarea_field( $data );
		}

		if ( is_numeric( $data ) ) {
			return is_float( $data ) ? floatval( $data ) : intval( $data );
		}

		if ( is_bool( $data ) ) {
			return $data;
		}

		return null;
	}

	/**
	 * Log to file.
	 *
	 * @since 1.0.0
	 * @param string $type      Log type.
	 * @param string $message   Log message.
	 * @param int    $design_id Design ID.
	 * @param array  $meta      Metadata.
	 * @return void
	 */
	private function log_to_file( $type, $message, $design_id, $meta ) {
		$log_dir = dirname( $this->log_file );

		// Create directory if it doesn't exist.
		if ( ! file_exists( $log_dir ) ) {
			wp_mkdir_p( $log_dir );
		}

		// Protect log directory.
		$htaccess_file = $log_dir . '/.htaccess';
		if ( ! file_exists( $htaccess_file ) ) {
			file_put_contents( $htaccess_file, "deny from all\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents
		}

		$line = sprintf(
			"[%s] [%s] %s%s\n",
			gmdate( 'Y-m-d H:i:s' ),
			strtoupper( $type ),
			sanitize_textarea_field( $message ),
			$design_id ? " (Design ID: {$design_id})" : ''
		);

		error_log( $line, 3, $this->log_file ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}
}