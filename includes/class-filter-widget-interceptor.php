<?php
/**
 * Filter Widget Interceptor
 *
 * Intercepts WoodMart/WooCommerce filter widgets and uses Manticore Search
 * to provide ultra-fast filter counts instead of slow MySQL COUNT queries.
 *
 * @package MantiLoad
 */

namespace MantiLoad\Filters;

defined( 'ABSPATH' ) || exit;

class Filter_Widget_Interceptor {

	/**
	 * Manticore client instance
	 */
	private $client;

	/**
	 * Cache duration in seconds
	 */
	private $cache_duration = 300; // 5 minutes

	/**
	 * Constructor
	 */
	public function __construct() {
		// Create Manticore client
		$this->client = new \MantiLoad\Manticore_Client();

		// Hook into WoodMart price filter widget
		\add_filter( 'woodmart_check_ranges_price_filter', array( $this, 'intercept_woodmart_price_check' ), 1 );

		// Hook into WooCommerce price filter queries
		\add_filter( 'woocommerce_price_filter_widget_min_amount', array( $this, 'get_min_price' ), 10 );
		\add_filter( 'woocommerce_price_filter_widget_max_amount', array( $this, 'get_max_price' ), 10 );

		// Intercept the actual database queries - use wpdb query filter
		\add_filter( 'query', array( $this, 'intercept_price_filter_sql' ), 1 );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		}
	}

	/**
	 * Intercept WoodMart price filter range check
	 *
	 * Returns false to disable the slow MySQL COUNT query
	 * WoodMart will then use cached data or skip the check
	 */
	public function intercept_woodmart_price_check( $check_ranges ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		}

		// Return false to disable the slow check_range_new() MySQL query
		// WoodMart will fall back to showing all price ranges
		return false;
	}

	/**
	 * Get price range counts from Manticore (for future use)
	 *
	 * This method can be used if we want to provide actual counts
	 * instead of just disabling the check.
	 */
	public function get_price_range_counts() {
		// Get current category
		$current_cat = null;
		if ( is_tax( 'product_cat' ) ) {
			$queried_object = get_queried_object();
			if ( $queried_object && isset( $queried_object->term_id ) ) {
				$current_cat = $queried_object->term_id;
			}
		}

		// Build cache key
		$cache_key = 'mantiload_price_ranges_' . ( $current_cat ? $current_cat : 'all' );

		// Try to get from cache
		$cached = \get_transient( $cache_key );
		if ( $cached !== false ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			}
			return $cached;
		}

		// Get from Manticore
		$ranges = $this->calculate_price_ranges_from_manticore( $current_cat );

		// Cache for 5 minutes
		\set_transient( $cache_key, $ranges, $this->cache_duration );

		return $ranges;
	}

	/**
	 * Calculate price ranges from Manticore
	 */
	private function calculate_price_ranges_from_manticore( $category_id = null ) {
		$index_name = \MantiLoad\MantiLoad::get_option( 'index_name', 'mantiload_fast_one' );

		try {
			// Build WHERE clause
			$where = "post_type='product' AND post_status='publish'";
			if ( $category_id ) {
				$where .= " AND category_ids={$category_id}";
			}

			// Get min and max prices
			$sql = "SELECT MIN(price) as min_price, MAX(price) as max_price FROM {$index_name} WHERE {$where}";

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			}

			$result = $this->client->query( $sql );

			if ( $result && $row = $result->fetch_assoc() ) {
				return array(
					'min' => (float) ( $row['min_price'] ?? 0 ),
					'max' => (float) ( $row['max_price'] ?? 0 ),
				);
			}
		} catch ( \Exception $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			}
		}

		return array( 'min' => 0, 'max' => 0 );
	}

	/**
	 * Get minimum price for current category/filters
	 */
	public function get_min_price( $min ) {
		$prices = $this->get_current_price_range();
		return $prices ? $prices['min'] : $min;
	}

	/**
	 * Get maximum price for current category/filters
	 */
	public function get_max_price( $max ) {
		$prices = $this->get_current_price_range();
		return $prices ? $prices['max'] : $max;
	}

	/**
	 * Get current price range from Manticore based on active filters
	 */
	private function get_current_price_range() {
		$index_name = \MantiLoad\MantiLoad::get_option( 'index_name', 'mantiload_fast_one' );

		try {
			// Build WHERE clause based on current category/filters
			$where = "post_type='product' AND post_status='publish'";

			// Add search query if present (CRITICAL for search pages!)
			global $wp_query;
			if ( isset( $wp_query ) && $wp_query->is_search() ) {
				$search_term = $wp_query->get( 's' );
				if ( ! empty( $search_term ) ) {
					$escaped_query = $this->client->escape( $search_term );
					$where .= " AND MATCH('{$escaped_query}')";
				}
			}

			// Add category filter if viewing a category
			if ( is_tax( 'product_cat' ) ) {
				$queried_object = get_queried_object();
				if ( $queried_object && isset( $queried_object->term_id ) ) {
					$where .= " AND category_ids=" . (int) $queried_object->term_id;
				}
			}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Processing WooCommerce filter parameters (filter_*) for product filtering, validated before use
			// Add attribute filters from query string
			foreach ( $_GET as $key => $value ) {
				if ( strpos( $key, 'filter_' ) === 0 && ! empty( $value ) ) {
					$attribute = str_replace( 'filter_', '', $key );
					$term_ids = array_map( 'intval', explode( ',', $value ) );
					if ( ! empty( $term_ids ) ) {
						$field_name = 'pa_' . $attribute . '_ids';
						$where .= " AND " . $field_name . " IN (" . implode( ',', $term_ids ) . ")";
					}
				}
			}

			// Get min/max prices from Manticore - FAST!
			$sql = "SELECT MIN(price) as min_price, MAX(price) as max_price FROM {$index_name} WHERE {$where}";

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			}

			$result = $this->client->query( $sql );

			if ( $result && $row = $result->fetch_assoc() ) {
				return array(
					'min' => (float) ( $row['min_price'] ?? 0 ),
					'max' => (float) ( $row['max_price'] ?? 0 ),
				);
			}
		} catch ( \Exception $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			}
		}

		return null;
	}

	/**
	 * Intercept slow price filter SQL queries and replace with fast dummy query
	 *
	 * This intercepts the actual get_filtered_price() queries from WooCommerce/WoodMart
	 * and replaces them with a fast dummy query, then injects Manticore results
	 */
	public function intercept_price_filter_sql( $query ) {
		// Only intercept on frontend
		if ( is_admin() ) {
			return $query;
		}

		// Check if this is a price filter query (contains wc_product_meta_lookup + min/max price)
		if ( strpos( $query, 'wc_product_meta_lookup' ) !== false &&
		     ( strpos( $query, 'min( min_price )' ) !== false || strpos( $query, 'MIN( min_price )' ) !== false ) ) {

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			}

			// Get prices from Manticore
			$prices = $this->get_current_price_range();

			if ( $prices ) {
				// Replace with a fast dummy query that returns the Manticore prices
				global $wpdb;
				$dummy_query = $wpdb->prepare(
					"SELECT %f as min_price, %f as max_price",
					$prices['min'],
					$prices['max']
				);

				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				}

				return $dummy_query;
			}
		}

		return $query;
	}

	/**
	 * Clear price range cache
	 *
	 * Called when products are updated
	 */
	public function clear_price_cache() {
		global $wpdb;

		// Delete all price range transients
		$wpdb->query(
			"DELETE FROM {$wpdb->options}
			WHERE option_name LIKE '_transient_mantiload_price_ranges_%'
			OR option_name LIKE '_transient_timeout_mantiload_price_ranges_%'"
		);

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		}
	}
}
