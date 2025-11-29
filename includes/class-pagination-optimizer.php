<?php
/**
 * Pagination Optimizer
 *
 * Eliminates SQL_CALC_FOUND_ROWS by using Manticore Search for pagination counts.
 * Based on WordPress VIP best practices.
 *
 * This class does NOT modify query results, only provides accurate pagination counts.
 *
 * @package MantiLoad
 */

namespace MantiLoad\Optimization;

defined( 'ABSPATH' ) || exit;

class Pagination_Optimizer {

	/**
	 * Manticore client instance
	 */
	private $client;

	/**
	 * Stored count for current query
	 */
	private $current_count = null;

	/**
	 * Cache duration in seconds (5 minutes)
	 */
	private $cache_duration = 300;

	/**
	 * Constructor
	 */
	public function __construct() {
		// Create Manticore client
		$this->client = new \MantiLoad\Manticore_Client();

		// Phase 1: Smart Pagination Control
		// Disable SQL_CALC_FOUND_ROWS where pagination isn't needed
		\add_action( 'pre_get_posts', array( $this, 'smart_pagination_control' ), 1 );

		// Phase 2: Manticore-Powered Counts
		// Prepare Manticore count before query runs
		// Priority 30 ensures WooCommerce and WooSort have already set posts_per_page
		\add_action( 'pre_get_posts', array( $this, 'prepare_manticore_count' ), 30 );

		// Inject Manticore count after query
		\add_filter( 'found_posts', array( $this, 'inject_manticore_count' ), 10, 2 );

		// Fix pagination after posts_results to account for filtered products
		// Priority 998 ensures this runs BEFORE Category_Query_Cache (999)
		\add_filter( 'posts_results', array( $this, 'fix_pagination_after_filters' ), 998, 2 );

		// DEBUG: Check if SQL_CALC_FOUND_ROWS is in the SQL
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			\add_filter( 'posts_request', array( $this, 'debug_sql_calc' ), 10, 2 );
		}
	}

	/**
	 * Debug: Check if SQL_CALC_FOUND_ROWS is in the SQL
	 */
	public function debug_sql_calc( $sql, $query ) {
		if ( ! is_admin() && $query->is_main_query() && $this->should_optimize_query( $query ) ) {
			if ( strpos( $sql, 'SQL_CALC_FOUND_ROWS' ) !== false ) {
				$no_found_rows = $query->get( 'no_found_rows' );
			} else {
			}
		}
		return $sql;
	}

	/**
	 * Phase 1: Smart Pagination Control
	 *
	 * Disable SQL_CALC_FOUND_ROWS on pages where pagination isn't displayed
	 */
	public function smart_pagination_control( $query ) {
		// Only for main query on frontend
		if ( is_admin() || ! $query->is_main_query() ) {
			return;
		}

		// Disable on single product pages (no pagination)
		if ( $query->is_singular( 'product' ) ) {
			$query->set( 'no_found_rows', true );

			
		}

		// Disable on cart/checkout/account pages
		if ( function_exists( 'is_cart' ) && ( is_cart() || is_checkout() || is_account_page() ) ) {
			$query->set( 'no_found_rows', true );

			
		}
	}

	/**
	 * Phase 2: Prepare Manticore Count
	 *
	 * Get count from Manticore BEFORE WordPress query runs,
	 * then disable SQL_CALC_FOUND_ROWS
	 */
	public function prepare_manticore_count( $query ) {
		// Only for main query on frontend
		if ( is_admin() || ! $query->is_main_query() ) {
			
			return;
		}

		// Only optimize product archives (category/tag pages)
		if ( ! $this->should_optimize_query( $query ) ) {
			
			return;
		}

		// Skip if filters are active - let Query_Integration handle filtered pagination
		if ( $this->has_active_filters() ) {
			
			return;
		}

		// CRITICAL: Disable Manticore count when WooSort is active with include_children=false
		// WooSort excludes subcategory products, but Manticore counts them
		// This causes pagination mismatch (count=957 but only ~750 actual products)
		// Solution: Let WordPress calculate found_posts naturally from query results
		if ( class_exists( 'WooSort\Frontend_Sort' ) && is_product_category() ) {
			
			// Don't set no_found_rows=true, let WordPress count naturally
			return;
		}

		

		// Check Manticore health first
		if ( ! $this->client->is_healthy() ) {
			
			return;
		}

		// Get count from Manticore
		$count = $this->get_count_from_manticore( $query );

		if ( $count !== false ) {
			// Store count for later injection
			$this->current_count = $count;

			// CRITICAL: Set found_posts directly on query object
			// This ensures Category_Query_Cache gets the correct value
			$query->found_posts = $count;
			$posts_per_page = absint( $query->get( 'posts_per_page' ) );
			if ( $posts_per_page < 1 ) {
				$posts_per_page = 1; // Prevent division by zero
			}
			$query->max_num_pages = $count > 0 ? ceil( $count / $posts_per_page ) : 0;

			// Disable SQL_CALC_FOUND_ROWS
			$query->set( 'no_found_rows', true );

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				$no_found_rows_value = $query->get( 'no_found_rows' );
			}
		} else {
			
		}
	}

	/**
	 * Inject Manticore Count into found_posts
	 */
	public function inject_manticore_count( $found_posts, $query ) {
		// Only for main query
		if ( ! $query->is_main_query() || is_admin() ) {
			return $found_posts;
		}

		// Only if we have a Manticore count prepared
		if ( $this->current_count === null ) {
			return $found_posts;
		}

		// Replace with Manticore count
		return $this->current_count;
	}

	/**
	 * Fix pagination after all filters have been applied
	 * Some products may be filtered out after the SQL query (visibility, stock, etc.)
	 * This ensures pagination matches actual displayed products
	 */
	public function fix_pagination_after_filters( $posts, $query ) {
		// Only for main query on product archives
		if ( ! $query->is_main_query() || is_admin() || ! $this->should_optimize_query( $query ) ) {
			return $posts;
		}

		// Only when WooSort is active (we're using MySQL count)
		if ( ! class_exists( 'WooSort\Frontend_Sort' ) || ! is_product_category() ) {
			
			return $posts;
		}

		// Get actual post count and posts_per_page
		$actual_count = count( $posts );
		$posts_per_page = absint( $query->get( 'posts_per_page' ) );
		$current_page = max( 1, absint( $query->get( 'paged' ) ) );

		// If this is NOT the last page and we got full page, assume found_posts is correct
		if ( $actual_count === $posts_per_page ) {
			return $posts;
		}

		// This might be the last page - recalculate found_posts based on actual results
		$estimated_found_posts = ( $current_page - 1 ) * $posts_per_page + $actual_count;

		// Only update if it's different from current found_posts
		if ( $estimated_found_posts !== $query->found_posts ) {
			$query->found_posts = $estimated_found_posts;
			$query->max_num_pages = $estimated_found_posts > 0 ? ceil( $estimated_found_posts / $posts_per_page ) : 0;
		}

		return $posts;
	}

	/**
	 * Check if there are active filters
	 */
	private function has_active_filters() {
		// Check for filter parameters in URL
		foreach ( $_GET as $key => $value ) {
			if ( strpos( $key, 'filter_' ) === 0 && ! empty( $value ) ) {
				return true;
			}
		}

		// Check for other filter parameters
		$filter_params = array( 'min_price', 'max_price', 'rating_filter', 'stock_status', 'on_sale' );
		foreach ( $filter_params as $param ) {
			if ( isset( $_GET[ $param ] ) && ! empty( $_GET[ $param ] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if query should be optimized
	 */
	private function should_optimize_query( $query ) {
		// Check if it's a product taxonomy first (product_cat, product_tag)
		// On taxonomy pages, post_type is often empty!
		if ( $query->is_tax( array( 'product_cat', 'product_tag' ) ) ) {
			
			return true;
		}

		// Check if it's a product post type archive
		if ( $query->is_post_type_archive( 'product' ) ) {
			
			return true;
		}

		// For other queries, check post_type explicitly
		$post_type = $query->get( 'post_type' );
		if ( $post_type === 'product' || in_array( 'product', (array) $post_type, true ) ) {
			
			return true;
		}

		

		return false;
	}

	/**
	 * Get product count from Manticore with caching
	 */
	private function get_count_from_manticore( $query ) {
		// Build cache key based on query parameters
		$cache_key = $this->build_cache_key( $query );

		// Try to get from cache first
		$cached_count = \get_transient( $cache_key );
		if ( $cached_count !== false ) {
			
			return (int) $cached_count;
		}

		// Get from Manticore
		$start_time = microtime( true );
		$count = $this->query_manticore_count( $query );
		$query_time = ( microtime( true ) - $start_time ) * 1000;

		if ( $count !== false ) {
			// Cache for 5 minutes
			\set_transient( $cache_key, $count, $this->cache_duration );
		}

		return $count;
	}

	/**
	 * Query Manticore for product count
	 */
	private function query_manticore_count( $query ) {
		$index_name = \MantiLoad\MantiLoad::get_option( 'index_name', 'mantiload_fast_one' );

		try {
			// Build WHERE clause
			$where_parts = array();
			$where_parts[] = "post_type='product'";
			$where_parts[] = "post_status='publish'";

			// CRITICAL: Match WooCommerce visibility - exclude hidden products
			// Only show products visible in catalog ('visible' or 'catalog')
			$where_parts[] = "(visibility = 'visible' OR visibility = 'catalog' OR visibility = '')";

			// Get current taxonomy
			if ( is_tax( 'product_cat' ) || is_category() ) {
				$queried_object = get_queried_object();
				if ( $queried_object && isset( $queried_object->term_id ) ) {
					$where_parts[] = 'category_ids=' . intval( $queried_object->term_id );
				}
			} elseif ( is_tax( 'product_tag' ) || is_tag() ) {
				$queried_object = get_queried_object();
				if ( $queried_object && isset( $queried_object->term_id ) ) {
					$where_parts[] = 'tag_ids=' . intval( $queried_object->term_id );
				}
			}

			$where_clause = implode( ' AND ', $where_parts );

			// Simple COUNT query
			$sql = "SELECT COUNT(*) as total FROM {$index_name} WHERE {$where_clause}";

			

			$result = $this->client->query( $sql );

			if ( $result && $row = $result->fetch_assoc() ) {
				return isset( $row['total'] ) ? (int) $row['total'] : 0;
			}

		} catch ( \Exception $e ) {
			
		}

		return false;
	}

	/**
	 * Build cache key for current query
	 */
	private function build_cache_key( $query ) {
		$key_parts = array( 'mantiload_count' );

		// Add post type
		$post_type = $query->get( 'post_type' );
		$key_parts[] = is_array( $post_type ) ? implode( '_', $post_type ) : $post_type;

		// Add taxonomy info
		if ( is_tax() || is_category() || is_tag() ) {
			$queried_object = get_queried_object();
			if ( $queried_object ) {
				$key_parts[] = $queried_object->taxonomy;
				$key_parts[] = $queried_object->term_id;
			}
		}

		return implode( '_', $key_parts );
	}

	/**
	 * Clear all cached counts
	 *
	 * Call this when products are updated
	 */
	public function clear_count_cache() {
		global $wpdb;

		$wpdb->query(
			"DELETE FROM {$wpdb->options}
			WHERE option_name LIKE '_transient_mantiload_count_%'
			OR option_name LIKE '_transient_timeout_mantiload_count_%'"
		);

		
	}
}
