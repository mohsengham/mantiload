<?php
namespace MantiLoad\CLI;

use MantiLoad\MantiLoad;

defined( 'ABSPATH' ) || exit;

/**
 * MantiLoad WP-CLI Commands
 */
class CLI_Commands {

	public function __construct() {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'mantiload', $this );
		}
	}

	/**
	 * Create Manticore indexes
	 *
	 * ## EXAMPLES
	 *
	 *     wp mantiload create-indexes
	 *
	 * @when after_wp_load
	 */
	public function create_indexes( $args, $assoc_args ) {
		\WP_CLI::line( 'Creating Manticore indexes...' );
		
		$indexer = \MantiLoad\MantiLoad::instance()->indexer;
		$results = $indexer->create_indexes();
		
		foreach ( $results as $post_type => $result ) {
			if ( $result['success'] ) {
				\WP_CLI::success( "Created index for {$post_type}: {$result['index']}" );
			} else {
				\WP_CLI::error( "Failed to create index for {$post_type}: {$result['error']}" );
			}
		}
	}

	/**
	 * Reindex all posts or specific post type with ALL fields (including MVA fields for filters)
	 *
	 * ## OPTIONS
	 *
	 * [--post-type=<post_type>]
	 * : Post type to reindex
	 *
	 * [--batch-size=<size>]
	 * : Batch size for indexing (default: 500)
	 *
	 * [--offset=<offset>]
	 * : Offset for batch processing
	 *
	 * ## EXAMPLES
	 *
	 *     wp mantiload reindex
	 *     wp mantiload reindex --post-type=product
	 *     wp mantiload reindex --batch-size=500
	 *
	 * @when after_wp_load
	 */
	public function reindex( $args, $assoc_args ) {
		// Load Bulk_Indexer for optimized full reindex
		require_once MANTILOAD_PLUGIN_DIR . 'includes/class-bulk-indexer.php';

		$post_types = isset( $assoc_args['post-type'] ) ? array( $assoc_args['post-type'] ) : null;
		$batch_size = isset( $assoc_args['batch-size'] ) ? (int) $assoc_args['batch-size'] : 500;
		$offset = isset( $assoc_args['offset'] ) ? (int) $assoc_args['offset'] : 0;

		if ( $post_types === null ) {
			$post_types = \MantiLoad\MantiLoad::get_option( 'post_types', array( 'post', 'page', 'product' ) );

			// Add orders and customers if enabled
			$index_orders_customers = \MantiLoad\MantiLoad::get_option( 'index_orders_customers', false );
			if ( $index_orders_customers ) {
				$post_types[] = 'shop_order';
				$post_types[] = 'user';
			}
		}

		\WP_CLI::line( '🚀 Starting OPTIMIZED full reindex (with ALL fields + MVA for filters)...' );
		$start_time = microtime( true );

		// Get total count first
		$total_count = 0;
		foreach ( $post_types as $post_type ) {
			if ( $post_type === 'user' ) {
				$users = get_users( array( 'role__in' => array( 'customer', 'administrator', 'shop_manager' ), 'fields' => 'ID' ) );
				$total_count += count( $users );
			} elseif ( $post_type === 'shop_order' ) {
				// Orders use custom statuses, count all non-trash orders
				global $wpdb;
				$order_count = $wpdb->get_var(
					"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'shop_order' AND post_status != 'trash'"
				);
				$total_count += (int) $order_count;
			} else {
				$count = wp_count_posts( $post_type );
				$total_count += $count->publish;
			}
		}

		// Create progress bar
		$progress = \WP_CLI\Utils\make_progress_bar( 'Full Reindex', $total_count );

		$total_indexed = 0;
		$total_failed = 0;

		// Process each post type using optimized Bulk_Indexer
		foreach ( $post_types as $post_type ) {
			if ( $post_type === 'user' ) {
				$users = get_users( array( 'role__in' => array( 'customer', 'administrator', 'shop_manager' ), 'fields' => 'ID' ) );
				$post_type_total = count( $users );
			} elseif ( $post_type === 'shop_order' ) {
				// Orders use custom statuses, count all non-trash orders
				global $wpdb;
				$post_type_total = (int) $wpdb->get_var(
					"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'shop_order' AND post_status != 'trash'"
				);
			} else {
				$count = wp_count_posts( $post_type );
				$post_type_total = $count->publish;
			}

			// Process in batches using Bulk_Indexer (includes ALL fields + MVAs!)
			for ( $current_offset = $offset; $current_offset < $post_type_total; $current_offset += $batch_size ) {
				$results = \MantiLoad\Indexer\Bulk_Indexer::index_batch( $post_type, $current_offset, $batch_size );

				$total_indexed += $results['indexed'];
				$total_failed += $results['failed'];

				// Update progress bar
				$progress->tick( $results['indexed'] + $results['failed'] );
			}
		}

		$progress->finish();

		$total_time = microtime( true ) - $start_time;

		\WP_CLI::success( sprintf(
			'⚡ Indexed %d of %d posts in %.2f seconds (Failed: %d) - %.0f posts/sec - ALL fields included!',
			$total_indexed,
			$total_count,
			$total_time,
			$total_failed,
			$total_indexed / $total_time
		) );
	}

	/**
	 * FAST reindex - uses optimized bulk indexing (10x faster!)
	 *
	 * ## OPTIONS
	 *
	 * [--post-type=<post_type>]
	 * : Post type to reindex (default: product)
	 *
	 * [--batch-size=<size>]
	 * : Batch size for indexing (default: 500)
	 *
	 * [--offset=<offset>]
	 * : Offset for batch processing
	 *
	 * ## EXAMPLES
	 *
	 *     wp mantiload fast-reindex
	 *     wp mantiload fast-reindex --post-type=product
	 *     wp mantiload fast-reindex --batch-size=1000
	 *
	 * @when after_wp_load
	 */
	public function fast_reindex( $args, $assoc_args ) {
		// Load Fast_Indexer if not already loaded
		require_once MANTILOAD_PLUGIN_DIR . 'includes/class-fast-indexer.php';

		$post_types = isset( $assoc_args['post-type'] ) ? array( $assoc_args['post-type'] ) : array( 'product' );
		$batch_size = isset( $assoc_args['batch-size'] ) ? (int) $assoc_args['batch-size'] : 500;
		$offset = isset( $assoc_args['offset'] ) ? (int) $assoc_args['offset'] : 0;

		\WP_CLI::line( '🚀 Starting FAST reindex (bulk mode)...' );
		$start_time = microtime( true );

		// Get total count first
		$total_count = 0;
		foreach ( $post_types as $post_type ) {
			$count = wp_count_posts( $post_type );
			$total_count += $count->publish;
		}

		// Create progress bar
		$progress = \WP_CLI\Utils\make_progress_bar( 'Fast Indexing', $total_count );

		$total_indexed = 0;
		$total_failed = 0;

		// Process each post type
		foreach ( $post_types as $post_type ) {
			$count = wp_count_posts( $post_type );
			$post_type_total = $count->publish;

			// Process in batches using Fast_Indexer
			for ( $current_offset = $offset; $current_offset < $post_type_total; $current_offset += $batch_size ) {
				$results = \MantiLoad\Indexer\Fast_Indexer::index_batch( $post_type, $current_offset, $batch_size );

				$total_indexed += $results['indexed'];
				$total_failed += $results['failed'];

				// Update progress bar
				$progress->tick( $results['indexed'] + $results['failed'] );
			}
		}

		$progress->finish();

		$total_time = microtime( true ) - $start_time;

		\WP_CLI::success( sprintf(
			'⚡ FAST indexed %d of %d posts in %.2f seconds (Failed: %d) - %.0f posts/sec',
			$total_indexed,
			$total_count,
			$total_time,
			$total_failed,
			$total_indexed / $total_time
		) );
	}

	/**
	 * Clear/truncate all Manticore indexes
	 *
	 * ## EXAMPLES
	 *
	 *     wp mantiload truncate
	 *     wp mantiload clear
	 *
	 * @when after_wp_load
	 */
	public function truncate( $args, $assoc_args ) {
		\WP_CLI::confirm( '⚠️  WARNING: This will DELETE ALL indexed data from MantiCore! Are you sure?' );

		\WP_CLI::line( 'Clearing all indexes...' );

		$indexer = \MantiLoad\MantiLoad::instance()->indexer;
		$results = $indexer->truncate_indexes();

		$cleared = 0;
		foreach ( $results as $post_type => $success ) {
			if ( $success ) {
				\WP_CLI::success( "Cleared {$post_type} index" );
				$cleared++;
			} else {
				\WP_CLI::error( "Failed to clear {$post_type} index" );
			}
		}

		\WP_CLI::success( "Cleared {$cleared} indexes successfully!" );
	}

	/**
	 * Clear/truncate all Manticore indexes (alias)
	 *
	 * @when after_wp_load
	 */
	public function clear( $args, $assoc_args ) {
		$this->truncate( $args, $assoc_args );
	}

	/**
	 * Optimize Manticore indexes
	 *
	 * ## EXAMPLES
	 *
	 *     wp mantiload optimize
	 *
	 * @when after_wp_load
	 */
	public function optimize( $args, $assoc_args ) {
		\WP_CLI::line( 'Optimizing indexes...' );

		$indexer = \MantiLoad\MantiLoad::instance()->indexer;
		$results = $indexer->optimize_indexes();

		foreach ( $results as $post_type => $success ) {
			if ( $success ) {
				\WP_CLI::success( "Optimized {$post_type}" );
			} else {
				\WP_CLI::error( "Failed to optimize {$post_type}" );
			}
		}
	}

	/**
	 * Show indexing statistics
	 *
	 * ## EXAMPLES
	 *
	 *     wp mantiload stats
	 *
	 * @when after_wp_load
	 */
	public function stats( $args, $assoc_args ) {
		$indexer = \MantiLoad\MantiLoad::instance()->indexer;
		$stats = $indexer->get_stats();
		
		$table_data = array();
		foreach ( $stats as $post_type => $stat ) {
			$table_data[] = array(
				'Post Type' => $post_type,
				'Index' => $stat['index'],
				'Indexed' => $stat['indexed'],
				'Total' => $stat['total'],
				'Progress' => $stat['percentage'] . '%',
			);
		}
		
		\WP_CLI\Utils\format_items( 'table', $table_data, array( 'Post Type', 'Index', 'Indexed', 'Total', 'Progress' ) );
	}

	/**
	 * Search for content
	 *
	 * ## OPTIONS
	 *
	 * <query>
	 * : Search query
	 *
	 * [--post-type=<post_type>]
	 * : Post type to search (default: product)
	 *
	 * [--limit=<limit>]
	 * : Number of results (default: 10)
	 *
	 * ## EXAMPLES
	 *
	 *     wp mantiload search "laptop"
	 *     wp mantiload search "laptop" --post-type=product --limit=20
	 *
	 * @when after_wp_load
	 */
	public function search( $args, $assoc_args ) {
		$query = $args[0];
		$post_type = $assoc_args['post-type'] ?? 'product';
		$limit = (int) ( $assoc_args['limit'] ?? 10 );
		
		$search_engine = \MantiLoad\MantiLoad::instance()->search;
		$results = $search_engine->search( $query, array(
			'post_type' => $post_type,
			'limit' => $limit,
		) );
		
		\WP_CLI::line( sprintf( 'Found %d results in %.2f ms', $results['total'], $results['query_time'] ) );
		
		if ( ! empty( $results['posts'] ) ) {
			$table_data = array();
			foreach ( $results['posts'] as $post ) {
				$relevance = $results['relevance'][ $post->ID ] ?? 0;
				$table_data[] = array(
					'ID' => $post->ID,
					'Title' => $post->post_title,
					'Type' => $post->post_type,
					'Relevance' => number_format( $relevance, 2 ),
				);
			}
			
			\WP_CLI\Utils\format_items( 'table', $table_data, array( 'ID', 'Title', 'Type', 'Relevance' ) );
		}
	}

	/**
	 * Clear search logs
	 *
	 * ## EXAMPLES
	 *
	 *     wp mantiload clear-logs
	 *
	 * @when after_wp_load
	 */
	public function clear_logs( $args, $assoc_args ) {
		\update_option( 'mantiload_search_logs', array() );
		\WP_CLI::success( 'Search logs cleared!' );
	}

	/**
	 * Clear frontend cache (category pages, menus)
	 *
	 * ## OPTIONS
	 *
	 * [--category=<category_id>]
	 * : Clear cache for specific category ID only
	 *
	 * ## EXAMPLES
	 *
	 *     # Clear all frontend cache
	 *     wp mantiload clear-cache
	 *
	 *     # Clear cache for specific category (e.g., prom-dresses = 393)
	 *     wp mantiload clear-cache --category=393
	 *
	 * @when after_wp_load
	 */
	public function clear_cache( $args, $assoc_args ) {
		$category_id = isset( $assoc_args['category'] ) ? intval( $assoc_args['category'] ) : null;

		try {
			$redis = new \Redis();
			$connected = $redis->connect( '127.0.0.1', 6379, 1 );

			if ( ! $connected ) {
				\WP_CLI::error( 'Could not connect to Redis' );
				return;
			}

			if ( $category_id ) {
				// Clear specific category
				$pattern = 'mantiload_fe:category_products_' . $category_id . '_*';
				$keys = $redis->keys( $pattern );

				if ( empty( $keys ) ) {
					\WP_CLI::warning( "No cache found for category {$category_id}" );
					return;
				}

				$deleted = $redis->del( $keys );
				\WP_CLI::success( "Cleared cache for category {$category_id} ({$deleted} keys deleted)" );
			} else {
				// Clear all frontend cache
				$patterns = array(
					'mantiload_fe:category_products_*',
					'mantiload_fe:menu_*',
				);

				$total_deleted = 0;
				foreach ( $patterns as $pattern ) {
					$keys = $redis->keys( $pattern );
					if ( ! empty( $keys ) ) {
						$deleted = $redis->del( $keys );
						$total_deleted += $deleted;
					}
				}

				if ( $total_deleted > 0 ) {
					\WP_CLI::success( "Cleared all frontend cache ({$total_deleted} keys deleted)" );
				} else {
					\WP_CLI::warning( 'No cache keys found' );
				}
			}
		} catch ( \Exception $e ) {
			\WP_CLI::error( 'Redis error: ' . $e->getMessage() );
		}
	}
}
