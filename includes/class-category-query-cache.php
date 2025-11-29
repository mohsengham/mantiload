<?php
/**
 * Category Query Cache
 *
 * Caches WooCommerce category product query results (frontend only)
 *
 * @package MantiLoad
 */

namespace MantiLoad\Cache;

defined( 'ABSPATH' ) || exit;

class Category_Query_Cache {

	/**
	 * Frontend cache instance
	 */
	private $cache;

	/**
	 * Current cache key
	 */
	private $current_cache_key = null;

	/**
	 * Query start time (for performance tracking)
	 */
	private $query_start_time = 0;

	/**
	 * Constructor
	 *
	 * @param Frontend_Cache $cache Frontend cache instance
	 */
	public function __construct( $cache ) {
		$this->cache = $cache;

		// ONLY hook on frontend
		if ( ! is_admin() ) {
			// Check cache before query runs
			\add_filter( 'posts_pre_query', array( $this, 'check_cache' ), 10, 2 );

			// Store results after query runs
			// Priority 999 ensures found_posts has been set by Pagination Optimizer (priority 10)
			\add_filter( 'posts_results', array( $this, 'store_cache' ), 999, 2 );

			
		}
	}

	/**
	 * Check if cached results exist
	 *
	 * @param array|null $posts Array of posts or null
	 * @param \WP_Query  $query Query object
	 * @return array|null Cached posts or null to let query run
	 */
	public function check_cache( $posts, $query ) {
		// Only for main query on product category pages
		if ( ! $this->should_cache_query( $query ) ) {
			return $posts;
		}

		// Build cache key
		$cache_key              = $this->build_cache_key( $query );
		$this->current_cache_key = $cache_key;

		// Track query start time
		$this->query_start_time = microtime( true );

		// Try to get from cache
		$cached = $this->cache->get( $cache_key );

		if ( $cached === false ) {
			
			return $posts; // Let query run
		}

		// Cache HIT!
		// Set query properties from cache
		$query->found_posts   = $cached['found_posts'];
		$query->max_num_pages = $cached['max_num_pages'];

		// Get actual post objects
		// Using get_posts with post__in maintains the sort order
		if ( empty( $cached['product_ids'] ) ) {
			return array();
		}

		$posts = \get_posts(
			array(
				'post__in'       => $cached['product_ids'],
				'post_type'      => 'product',
				'orderby'        => 'post__in',
				'posts_per_page' => count( $cached['product_ids'] ),
				'post_status'    => 'publish',
			)
		);

		// Clear the current cache key since we're returning cached results
		$this->current_cache_key = null;

		return $posts;
	}

	/**
	 * Store query results in cache
	 *
	 * @param array     $posts Array of posts
	 * @param \WP_Query $query Query object
	 * @return array Posts (unchanged)
	 */
	public function store_cache( $posts, $query ) {
		// Only cache if we have a cache key set (from check_cache)
		if ( $this->current_cache_key === null ) {
			return $posts;
		}

		// Only for main query on product category pages
		if ( ! $this->should_cache_query( $query ) ) {
			return $posts;
		}

		if ( empty( $posts ) ) {
			// Cache empty results too (prevents repeated queries for empty categories)
			$this->cache_query_result( array(), $query );
			return $posts;
		}

		// Extract product IDs (maintain order)
		$product_ids = wp_list_pluck( $posts, 'ID' );

		// Store in cache
		$this->cache_query_result( $product_ids, $query );

		// Clear cache key
		$this->current_cache_key = null;

		return $posts;
	}

	/**
	 * Cache query result
	 *
	 * @param array     $product_ids Array of product IDs
	 * @param \WP_Query $query       Query object
	 */
	private function cache_query_result( $product_ids, $query ) {
		$cache_data = array(
			'product_ids'   => $product_ids,
			'found_posts'   => $query->found_posts,
			'max_num_pages' => $query->max_num_pages,
			'timestamp'     => time(),
		);

		// Cache for 5 minutes (300 seconds)
		$this->cache->set( $this->current_cache_key, $cache_data, 300 );
	}

	/**
	 * Check if query should be cached
	 *
	 * @param \WP_Query $query Query object
	 * @return bool
	 */
	private function should_cache_query( $query ) {
		// Must be main query
		if ( ! $query->is_main_query() ) {
			return false;
		}

		// Must be on frontend
		if ( is_admin() ) {
			return false;
		}

		// Must be product category or shop page
		if ( ! is_product_category() && ! is_shop() ) {
			return false;
		}

		// Don't cache search queries (they have different logic)
		if ( $query->is_search() ) {
			return false;
		}

		return true;
	}

	/**
	 * Build cache key for current query
	 *
	 * @param \WP_Query $query Query object
	 * @return string Cache key
	 */
	private function build_cache_key( $query ) {
		$parts = array( 'category_products' );

		// Get category ID
		$category_id = null;
		if ( is_product_category() ) {
			$queried_object = get_queried_object();
			if ( $queried_object && isset( $queried_object->term_id ) ) {
				$category_id = $queried_object->term_id;
				$parts[] = $category_id;
			} else {
				$parts[] = 'unknown';
			}
		} else {
			// Shop page
			$parts[] = 'shop';
		}

		// Get page number
		$paged = $query->get( 'paged' );
		if ( ! $paged ) {
			$paged = 1;
		}
		$parts[] = 'page';
		$parts[] = $paged;

		// Check if WooSort is active for this category
		// WooSort uses meta key: _mantisort_pos_{category_id}
		$using_woosort = false;
		if ( $category_id && class_exists( '\WooSort\WooSort' ) ) {
			// Check if ANY products in this category have WooSort positions
			global $wpdb;
			$meta_key = '_mantisort_pos_' . $category_id;
			$has_positions = $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s LIMIT 1",
				$meta_key
			) );
			$using_woosort = ( $has_positions > 0 );
		}

		// Add orderby to cache key
		$orderby = $query->get( 'orderby' );
		if ( $using_woosort ) {
			// WooSort is active for this category - add to cache key
			$parts[] = 'woosort';
			$parts[] = $category_id; // Include category ID in sort key for extra uniqueness
		} elseif ( $orderby && $orderby !== 'date' ) {
			// Non-default orderby
			$parts[] = 'order';
			$parts[] = $orderby;
		}

		// Add any active filters (for future compatibility)
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading WooCommerce filter parameters for cache key generation, not modifying data
		// This ensures different filter combinations get different cache keys
		if ( ! empty( $_GET ) ) {
			// Get WooCommerce filter parameters
			$filter_params = array();

			foreach ( $_GET as $key => $value ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Processing filter parameters for cache key, read-only operation
				// Only include WC filter params (not pagination, sorting already included)
				if ( strpos( $key, 'filter_' ) === 0 || strpos( $key, 'min_price' ) === 0 || strpos( $key, 'max_price' ) === 0 ) {
					$filter_params[ $key ] = $value;
				}
			}

			if ( ! empty( $filter_params ) ) {
				ksort( $filter_params );
				$parts[] = 'filter';
				$parts[] = md5( wp_json_encode( $filter_params ) );
			}
		}

		return implode( '_', $parts );
	}
}
