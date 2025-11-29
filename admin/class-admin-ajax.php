<?php
/**
 * Simple, Clean AJAX Handler for Indexing
 * No bullshit, just works
 */

namespace MantiLoad\Admin;

defined( 'ABSPATH' ) || exit;

class Admin_AJAX {

	public function __construct() {

		// Register AJAX handlers - simple and direct
		\add_action( 'wp_ajax_mantiload_start_index', array( $this, 'start_index' ) );
		\add_action( 'wp_ajax_mantiload_index_batch', array( $this, 'index_batch' ) );
		\add_action( 'wp_ajax_mantiload_create_index', array( $this, 'create_index' ) );
		\add_action( 'wp_ajax_mantiload_truncate_index', array( $this, 'truncate_index' ) );
		\add_action( 'wp_ajax_mantiload_optimize_index', array( $this, 'optimize_index' ) );

		// New v1.0 features
		\add_action( 'wp_ajax_mantiload_test_connection', array( $this, 'test_connection' ) );
		\add_action( 'wp_ajax_mantiload_index_status', array( $this, 'index_status' ) );
		\add_action( 'wp_ajax_mantiload_rebuild_index', array( $this, 'rebuild_index' ) );
		\add_action( 'wp_ajax_mantiload_rebuild_progress', array( $this, 'rebuild_progress' ) );
	}

	/**
	 * Start indexing - get total count
	 */
	public function start_index() {
		// Debug: Log that we reached this function

		// Security check
		\check_ajax_referer( 'mantiload-admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			\wp_send_json_error( esc_html__( 'Unauthorized', 'mantiload' ) );
		}


		// Get post types from settings
		$post_types = \MantiLoad\MantiLoad::get_option( 'post_types', array( 'post', 'page', 'product' ) );

		// Add orders and customers if enabled
		$index_orders_customers = \MantiLoad\MantiLoad::get_option( 'index_orders_customers', false );
		if ( $index_orders_customers ) {
			$post_types[] = 'shop_order';
			$post_types[] = 'user';
		}

		$total = 0;

		foreach ( $post_types as $post_type ) {
			if ( $post_type === 'user' ) {
				// Count users (customers)
				$users = get_users( array( 'role__in' => array( 'customer', 'administrator', 'shop_manager' ), 'fields' => 'ID' ) );
				$total += count( $users );
			} elseif ( $post_type === 'shop_order' ) {
				// Count orders (all non-trash)
				global $wpdb;
				$order_count = $wpdb->get_var(
					"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'shop_order' AND post_status != 'trash'"
				);
				$total += (int) $order_count;
			} else {
				$count = wp_count_posts( $post_type );
				$total += isset( $count->publish ) ? $count->publish : 0;
			}
		}

		// Get batch size from settings (default 500 for speed)
		$batch_size = (int) \MantiLoad\MantiLoad::get_option( 'index_batch_size', 500 );

		\wp_send_json_success( array(
			'total' => $total,
			'post_types' => $post_types,
			'batch_size' => $batch_size,
		) );
	}

	/**
	 * Index a batch of posts
	 */
	public function index_batch() {
		// Security check
		\check_ajax_referer( 'mantiload-admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			\wp_send_json_error( esc_html__( 'Unauthorized', 'mantiload' ) );
		}

		// Increase PHP timeout
// set_time_limit( 120 );

		$post_type = sanitize_text_field( wp_unslash( $_POST['post_type'] ?? 'product' ) );
		$offset = absint( wp_unslash( $_POST['offset'] ?? 0 ) );
		// Use batch_size from POST (sent by JavaScript) or fallback to setting
		$batch_size = isset( $_POST['batch_size'] ) ? absint( wp_unslash( $_POST['batch_size'] ) ) : (int) \MantiLoad\MantiLoad::get_option( 'index_batch_size', 500 );

		// Use Bulk indexer - includes ALL fields + proper schema handling
		require_once MANTILOAD_PLUGIN_DIR . 'includes/class-bulk-indexer.php';

		// Log batch start for debugging
		$batch_start_time = microtime( true );

		$results = \MantiLoad\Indexer\Bulk_Indexer::index_batch( $post_type, $offset, $batch_size );

		// Log batch result with timing
		$batch_time = microtime( true ) - $batch_start_time;
		$posts_per_sec = $results['indexed'] > 0 ? round( $results['indexed'] / $batch_time ) : 0;

		// Clear index status cache after indexing
		\delete_transient( 'mantiload_index_status' );

		\wp_send_json_success( array(
			'indexed' => $results['indexed'],
			'failed' => $results['failed'],
			'has_more' => $results['indexed'] >= $batch_size, // Stop when batch is incomplete
		) );
	}

	/**
	 * Create index - create new Manticore index structure
	 */
	public function create_index() {
		// Security check
		\check_ajax_referer( 'mantiload-admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			\wp_send_json_error( esc_html__( 'Unauthorized', 'mantiload' ) );
		}

		// Get indexer
		$indexer = \MantiLoad\MantiLoad::instance()->indexer;

		// Create indexes for all configured post types
		$results = $indexer->create_indexes();

		if ( $results ) {
			// Clear index status cache
			\delete_transient( 'mantiload_index_status' );

			\wp_send_json_success( array(
				'message' => __( 'Index created successfully with multi-language support! Now you can start indexing.', 'mantiload' ),
			) );
		} else {
			\wp_send_json_error( __( 'Failed to create index. Check that Manticore is running and accessible.', 'mantiload' ) );
		}
	}

	/**
	 * Truncate index - remove all documents
	 */
	public function truncate_index() {
		// Security check
		\check_ajax_referer( 'mantiload-admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			\wp_send_json_error( esc_html__( 'Unauthorized', 'mantiload' ) );
		}

		// Get index name from settings
		$index_name = \MantiLoad\MantiLoad::get_option( 'index_name', 'mantiload_fast_one' );

		// Get Manticore client
		$indexer = \MantiLoad\MantiLoad::instance()->indexer;
		$client = $indexer->get_client();

		if ( ! $client ) {
			\wp_send_json_error( __( 'Manticore client not available', 'mantiload' ) );
		}

		// Truncate the index
		$result = $client->truncate( $index_name );

		if ( $result ) {
			// Clear index status cache
			\delete_transient( 'mantiload_index_status' );

			\wp_send_json_success( array(
				/* translators: %s: index name */
				'message' => sprintf( __( 'Index "%s" truncated successfully', 'mantiload' ), $index_name ),
			) );
		} else {
			/* translators: %s: index name */
			\wp_send_json_error( sprintf( __( 'Failed to truncate index "%s"', 'mantiload' ), $index_name ) );
		}
	}

	/**
	 * Optimize index - improve query performance
	 */
	public function optimize_index() {
		// Security check
		\check_ajax_referer( 'mantiload-admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			\wp_send_json_error( esc_html__( 'Unauthorized', 'mantiload' ) );
		}

		// Get index name from settings
		$index_name = \MantiLoad\MantiLoad::get_option( 'index_name', 'mantiload_fast_one' );

		// Get Manticore client
		$indexer = \MantiLoad\MantiLoad::instance()->indexer;
		$client = $indexer->get_client();

		if ( ! $client ) {
			\wp_send_json_error( __( 'Manticore client not available', 'mantiload' ) );
		}

		// Optimize the index
		$result = $client->optimize( $index_name );

		if ( $result ) {
			\wp_send_json_success( array(
				/* translators: %s: index name */
				'message' => sprintf( __( 'Index "%s" optimized successfully', 'mantiload' ), $index_name ),
			) );
		} else {
			/* translators: %s: index name */
			\wp_send_json_error( sprintf( __( 'Failed to optimize index "%s"', 'mantiload' ), $index_name ) );
		}
	}

	/**
	 * Test Connection - Check if Manticore is available
	 */
	public function test_connection() {
		// Security check
		\check_ajax_referer( 'mantiload-admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			\wp_send_json_error( array( 'message' => esc_html__( 'Unauthorized', 'mantiload' ) ) );
		}

		// Create Manticore client
		$client = new \MantiLoad\Manticore_Client();
		$status = $client->get_status();

		if ( $status['connected'] ) {
			$message = sprintf(
				'Connected successfully to %s:%s',
				$status['host'],
				$status['port']
			);

			if ( ! empty( $status['tables'] ) ) {
				$message .= ' | ' . count( $status['tables'] ) . ' index(es) found';
			}

			\wp_send_json_success( array(
				'message' => $message,
				'status' => $status,
			) );
		} else {
			\wp_send_json_error( array(
				'message' => sprintf(
					'Cannot connect to %s:%s - %s',
					$status['host'],
					$status['port'],
					$status['error'] ?: 'Connection failed'
				),
				'status' => $status,
			) );
		}
	}

	/**
	 * Index Status - Get current index information
	 */
	public function index_status() {
		// Security check
		\check_ajax_referer( 'mantiload-admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			\wp_send_json_error( array( 'message' => esc_html__( 'Unauthorized', 'mantiload' ) ) );
		}

		// Check cache first (30 seconds cache for admin page loads)
		$cache_key = 'mantiload_index_status';
		$cached = \get_transient( $cache_key );
		if ( false !== $cached ) {
			\wp_send_json_success( $cached );
			return;
		}

		// Create Manticore client
		$client = new \MantiLoad\Manticore_Client();
		$status = $client->get_status();

		// Get document count if connected
		$document_count = 0;
		if ( $status['connected'] ) {
			$index_name = \MantiLoad\MantiLoad::get_option( 'index_name', 'mantiload_fast_one' );

			try {
				// Query document count
				$result = $client->query( "SELECT COUNT(*) as total FROM {$index_name}" );
				if ( $result ) {
					$row = $result->fetch_assoc();
					$document_count = (int) $row['total'];
				}
			} catch ( \Exception $e ) {
				// Index may not exist yet
				$document_count = 0;
			}
		}

		// Get index size
		$index_size = 0;
		$index_size_mb = 0;
		if ( $status['connected'] ) {
			$size_data = $client->get_index_size();
			$index_size = $size_data['total_bytes'];
			$index_size_mb = $size_data['total_mb'];
		}

		$response = array(
			'connected' => $status['connected'],
			'host' => $status['host'],
			'port' => $status['port'],
			'document_count' => $document_count,
			'index_size' => $index_size,
			'index_size_mb' => $index_size_mb,
			'index_name' => \MantiLoad\MantiLoad::get_option( 'index_name', 'mantiload_fast_one' ),
			'error' => $status['error'],
		);

		// Cache for 30 seconds to speed up admin page loads
		\set_transient( $cache_key, $response, 30 );

		\wp_send_json_success( $response );
	}

	/**
	 * Rebuild Index - Full reindex of all content
	 */
	public function rebuild_index() {
		// Security check
		\check_ajax_referer( 'mantiload-admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			\wp_send_json_error( array( 'message' => esc_html__( 'Unauthorized', 'mantiload' ) ) );
		}

		// Mark rebuild as started
		\set_transient( 'mantiload_rebuild_status', 'in_progress', 300 );
		\set_transient( 'mantiload_rebuild_started_time', time(), 300 );

		// Use WP-CLI for reliability (doesn't timeout)
		$wp_cli = '/usr/bin/php8.3 /usr/local/bin/wp';
		$wp_path = ABSPATH;

		// Create a temporary shell script to ensure proper command substitution
		$script_path = sys_get_temp_dir() . '/mantiload_rebuild_' . time() . '.sh';
		$log_path = sys_get_temp_dir() . '/mantiload_rebuild_' . time() . '.log';

		$script_content = "#!/bin/sh\n";
		// No sudo needed - PHP already runs as wordjo user
		$script_content .= "$wp_cli mantiload create_indexes --path=$wp_path 2>&1 | grep -v 'Deprecated' && ";
		$script_content .= "$wp_cli mantiload reindex --path=$wp_path 2>&1 | grep -v 'Deprecated' && ";
		$script_content .= $wp_cli . ' option update mantiload_last_rebuild_time $(date +%s) --path=' . $wp_path . ' 2>&1 | grep -v \'Deprecated\'';

		file_put_contents( $script_path, $script_content );
		global $wp_filesystem;
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		WP_Filesystem();
		$wp_filesystem->chmod( $script_path, 0755 );

		// Execute in background with logging
		exec( "nohup $script_path > $log_path 2>&1 &" );

		\wp_send_json_success( array(
			'message' => '⚡ Index rebuild started in background! Refresh page in 7 seconds to see completion message.',
		) );
	}

	public function rebuild_index_OLD_BROKEN() {
		// OLD SYNCHRONOUS VERSION - CAUSES TIMEOUT
		// Security check
		\check_ajax_referer( 'mantiload-admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			\wp_send_json_error( array( 'message' => esc_html__( 'Unauthorized', 'mantiload' ) ) );
		}

		// Increase PHP timeout for large indexes
// set_time_limit( 300 );

		// Get indexer
		$indexer = \MantiLoad\MantiLoad::instance()->indexer;
		$client = $indexer->get_client();

		if ( ! $client ) {
			\wp_send_json_error( array(
				'message' => 'Manticore client not available. Please check connection settings.',
			) );
		}

		// Get index name
		$index_name = \MantiLoad\MantiLoad::get_option( 'index_name', 'mantiload_fast_one' );

		// Truncate existing index
		try {
			$client->truncate( $index_name );
		} catch ( \Exception $e ) {
			// Index may not exist, that's ok - we'll create it
		}

		// Create index if it doesn't exist
		$indexer->create_indexes();

		// Start indexing - use Fast Indexer for performance
		require_once MANTILOAD_PLUGIN_DIR . 'includes/class-fast-indexer.php';

		$post_types = array( 'product' );
		$batch_size = 100;
		$total_indexed = 0;
		$total_failed = 0;

		foreach ( $post_types as $post_type ) {
			$offset = 0;
			$has_more = true;

			while ( $has_more ) {
				$results = \MantiLoad\Indexer\Fast_Indexer::index_batch( $post_type, $offset, $batch_size );
				$total_indexed += $results['indexed'];
				$total_failed += $results['failed'];

				$has_more = $results['indexed'] >= $batch_size;
				$offset += $batch_size;

				// Safety break after 10000 posts
				if ( $offset > 10000 ) {
					break;
				}
			}
		}

		// Optimize the index after rebuild
		try {
			$client->optimize( $index_name );
		} catch ( \Exception $e ) {
			// Optimization failure is not critical
		}

		\wp_send_json_success( array(
			'message' => sprintf(
				'Index rebuilt successfully! %d products indexed, %d failed',
				$total_indexed,
				$total_failed
			),
			'indexed' => $total_indexed,
			'failed' => $total_failed,
		) );
	}

	/**
	 * Check rebuild progress
	 */
	public function rebuild_progress() {
		\check_ajax_referer( 'mantiload-admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			\wp_send_json_error( array( 'message' => esc_html__( 'Unauthorized', 'mantiload' ) ) );
		}

		$status = \get_transient( 'mantiload_rebuild_status' );
		$start_time = \get_transient( 'mantiload_rebuild_started_time' );
		$completion_time = \get_option( 'mantiload_last_rebuild_time', 0 );

		// Check if rebuild is complete
		if ( $completion_time && $start_time && $completion_time >= $start_time ) {
			// Get final stats
			$indexer = \MantiLoad\MantiLoad::instance()->indexer;
			$client = $indexer->get_client();
			$index_name = \MantiLoad\MantiLoad::get_option( 'index_name', 'mantiload_fast_one' );

			try {
				$count = $client->query( "SELECT COUNT(*) as total FROM $index_name" )->fetch_assoc()['total'] ?? 0;
				$duration = $completion_time - $start_time;
				$speed = $duration > 0 ? round( $count / $duration ) : 0;

				// Clear transients
				\delete_transient( 'mantiload_rebuild_status' );
				\delete_transient( 'mantiload_rebuild_started_time' );

				\wp_send_json_success( array(
					'status' => 'complete',
					'message' => sprintf(
						'✅ Index rebuild completed! Indexed %s items in %s seconds (%s items/sec)',
						number_format( $count ),
						number_format( $duration, 2 ),
						number_format( $speed )
					),
					'indexed' => $count,
					'duration' => $duration,
					'speed' => $speed,
				) );
			} catch ( \Exception $e ) {
				\wp_send_json_success( array(
					'status' => 'complete',
					'message' => '✅ Index rebuild completed successfully!',
				) );
			}
		} elseif ( $status === 'in_progress' && $start_time ) {
			// Still in progress
			$elapsed = time() - $start_time;
			\wp_send_json_success( array(
				'status' => 'in_progress',
				'message' => sprintf( 'Rebuilding index... (%d seconds elapsed)', $elapsed ),
				'elapsed' => $elapsed,
			) );
		} else {
			// Not started or status unknown
			\wp_send_json_success( array(
				'status' => 'idle',
				'message' => 'No rebuild in progress',
			) );
		}
	}
}
