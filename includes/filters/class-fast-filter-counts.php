<?php
/**
 * Fast Filter Counts - Ultra-fast attribute and price filter counts for search pages
 *
 * Intercepts slow WooCommerce filter count queries on search pages and routes them
 * through MantiLoad for sub-millisecond performance.
 *
 * @package MantiLoad
 */

namespace MantiLoad\Filters;

defined( 'ABSPATH' ) || exit;

/**
 * Fast_Filter_Counts Class
 *
 * Provides lightning-fast filter counts by intercepting WooCommerce's slow queries
 * and using MantiLoad's indexed data instead.
 */
class Fast_Filter_Counts {

	/**
	 * Cache for current search term
	 */
	private $current_search_term = '';

	/**
	 * Get Filter_Query instance on demand
	 */
	private function get_filter_query() {
		static $filter_query = null;
		if ( $filter_query === null ) {
			// Disabled - causing display issues
			$filter_query = new class() {
				public function get_attribute_term_counts_for_search( $taxonomy, $term_ids, $search_term ) {
					return array_fill_keys( $term_ids, 0 );
				}
				public function get_price_range_for_search( $search_term ) {
					return array( 'min' => 0, 'max' => 0 );
				}
			};
		}
		return $filter_query;
	}

	/**
	 * Constructor
	 */
	public function __construct() {
		// Hook into WooCommerce filter count queries
		// Disabled - causing display issues
		// \add_filter( 'woocommerce_get_filtered_term_product_counts_query', array( $this, 'intercept_term_counts_query' ), 10, 2 );
		// \add_filter( 'woocommerce_get_filtered_term_product_counts', array( $this, 'intercept_term_counts_result' ), 10, 3 );
		// \add_filter( 'woocommerce_layered_nav_count', array( $this, 'intercept_layered_nav_count' ), 10, 3 );

		// Intercept price filter queries
		// Disabled - causing display issues
		// \add_filter( 'woocommerce_price_filter_widget_min_amount', array( $this, 'get_fast_min_price' ), 10, 1 );
		// \add_filter( 'woocommerce_price_filter_widget_max_amount', array( $this, 'get_fast_max_price' ), 10, 1 );

		// Intercept the actual slow SQL queries (very conservative)
		// Disabled - causing display issues
		// \add_filter( 'query', array( $this, 'intercept_slow_term_count_queries' ), 9999 ); // Very low priority

		// Cache current search term
		\add_action( 'wp', array( $this, 'cache_current_search_term' ) );

		// Ensure search queries include the search term for filter calculations
		\add_action( 'pre_get_posts', array( $this, 'modify_search_query_for_filters' ), 20 );

		
	}

	/**
	 * Intercept slow term count queries and replace with fast MantiLoad queries
	 *
	 * This catches queries like:
	 * - SELECT COUNT(*) FROM wp_term_relationships WHERE term_taxonomy_id = X AND object_id IN (...)
	 * - SELECT COUNT(DISTINCT product_or_parent_id) FROM wp_wc_product_attributes_lookup WHERE ... LIKE '%search%'
	 */
	public function intercept_slow_term_count_queries( $query ) {
		// Only intercept on search pages with very specific conditions
		if ( ! is_search() || empty( $this->current_search_term ) ) {
			return $query;
		}

		// Check if MantiLoad is healthy
		$client = new \MantiLoad\Manticore_Client();
		if ( ! $client->is_healthy() ) {
			return $query; // Fallback to WooCommerce
		}

		// ONLY intercept the exact price filter query pattern
		// This is the most common slow query on search pages
		if ( strpos( $query, 'wp_wc_product_meta_lookup' ) !== false &&
		     strpos( $query, 'min_price' ) !== false &&
		     strpos( $query, 'max_price' ) !== false &&
		     strpos( $query, 'product_id IN' ) !== false &&
		     strpos( $query, 'LIKE' ) !== false ) {

			

			try {
				$price_range = $this->get_filter_query()->get_price_range_for_search( $this->current_search_term );

				if ( $price_range['min'] > 0 || $price_range['max'] > 0 ) {
					// Return fast prices from MantiLoad
					global $wpdb;
					$result = $wpdb->prepare(
						"SELECT %f as min_price, %f as max_price",
						$price_range['min'],
						$price_range['max']
					);

					return $result;
				}
			} catch ( Exception $e ) {
				
			}
		}

		// Don't intercept anything else to avoid breaking display
		return $query;
	}

	/**
	 * Intercept WooCommerce's filtered term product counts query
	 *
	 * This replaces the slow subquery with a fast MantiLoad query
	 *
	 * @param array $query      The query array
	 * @param array $attributes Array of taxonomy => term_ids
	 * @return array Modified query or empty array to skip
	 */
	public function intercept_term_counts_query( $query, $attributes ) {
		// Only intercept on search pages
		if ( ! is_search() || empty( $this->current_search_term ) ) {
			return $query;
		}

		// Check if MantiLoad is healthy
		$client = new \MantiLoad\Manticore_Client();
		if ( ! $client->is_healthy() ) {
			return $query; // Fallback to WooCommerce
		}

		

		// Instead of modifying the query, we'll intercept the results
		// Return empty array to prevent the slow query, then hook into the count function
		return array();
	}

	/**
	 * Intercept the final term counts result array
	 *
	 * @param array  $counts    The counts array
	 * @param string $taxonomy  The taxonomy
	 * @param array  $query     The original query
	 * @return array Modified counts
	 */
	public function intercept_term_counts_result( $counts, $taxonomy, $query ) {
		// Only intercept on search pages
		if ( ! is_search() || empty( $this->current_search_term ) ) {
			return $counts;
		}

		// Check if MantiLoad is healthy
		$client = new \MantiLoad\Manticore_Client();
		if ( ! $client->is_healthy() ) {
			return $counts; // Fallback to WooCommerce
		}

		// If we have term IDs, get fast counts from MantiLoad
		if ( ! empty( $counts ) ) {
			$term_ids = array_keys( $counts );
			$fast_counts = $this->get_filter_query()->get_attribute_term_counts_for_search(
				$taxonomy,
				$term_ids,
				$this->current_search_term
			);

			// Merge fast counts with existing counts
			foreach ( $fast_counts as $term_id => $count ) {
				if ( isset( $counts[ $term_id ] ) ) {
					$counts[ $term_id ] = $count;
				}
			}
		}

		return $counts;
	}

	/**
	 * Intercept layered nav count calls
	 *
	 * @param int    $count      The count
	 * @param object $term       The term object
	 * @param string $taxonomy   The taxonomy
	 * @return int Modified count
	 */
	public function intercept_layered_nav_count( $count, $term, $taxonomy ) {
		// Only intercept on search pages with search term
		if ( ! is_search() || empty( $this->current_search_term ) ) {
			return $count;
		}

		// Only intercept product attributes
		if ( strpos( $taxonomy, 'pa_' ) !== 0 ) {
			return $count;
		}

		// Check if MantiLoad is healthy
		$client = new \MantiLoad\Manticore_Client();
		if ( ! $client->is_healthy() ) {
			return $count; // Fallback to WooCommerce
		}

		// Get fast count from MantiLoad
		$counts = $this->get_filter_query()->get_attribute_term_counts_for_search(
			$taxonomy,
			array( $term->term_id ),
			$this->current_search_term
		);

		$new_count = isset( $counts[ $term->term_id ] ) ? $counts[ $term->term_id ] : 0;

		return $new_count;
	}

	/**
	 * Get fast minimum price for search results
	 *
	 * @param float $min_price Current min price
	 * @return float Fast min price from MantiLoad
	 */
	public function get_fast_min_price( $min_price ) {
		// Only on search pages
		if ( ! is_search() || empty( $this->current_search_term ) ) {
			return $min_price;
		}

		// Check if MantiLoad is healthy
		$client = new \MantiLoad\Manticore_Client();
		if ( ! $client->is_healthy() ) {
			return $min_price;
		}

		$price_range = $this->get_filter_query()->get_price_range_for_search( $this->current_search_term );

		return $price_range['min'] > 0 ? $price_range['min'] : $min_price;
	}

	/**
	 * Get fast maximum price for search results
	 *
	 * @param float $max_price Current max price
	 * @return float Fast max price from MantiLoad
	 */
	public function get_fast_max_price( $max_price ) {
		// Only on search pages
		if ( ! is_search() || empty( $this->current_search_term ) ) {
			return $max_price;
		}

		// Check if MantiLoad is healthy
		$client = new \MantiLoad\Manticore_Client();
		if ( ! $client->is_healthy() ) {
			return $max_price;
		}

		$price_range = $this->get_filter_query()->get_price_range_for_search( $this->current_search_term );

		return $price_range['max'] > 0 ? $price_range['max'] : $max_price;
	}

	/**
	 * Cache the current search term for use in filter queries
	 */
	public function cache_current_search_term() {
		if ( is_search() && isset( $_GET['s'] ) ) {
			$this->current_search_term = sanitize_text_field( wp_unslash( $_GET['s'] ) );

			
		}
	}

	/**
	 * Modify search queries to ensure filters work properly
	 *
	 * @param WP_Query $query
	 */
	public function modify_search_query_for_filters( $query ) {
		// Only modify main product search queries
		if ( ! $query->is_main_query() || ! $query->is_search() ) {
			return;
		}

		// Ensure the search term is available for filter calculations
		if ( isset( $_GET['s'] ) ) {
			$this->current_search_term = sanitize_text_field( wp_unslash( $_GET['s'] ) );

			
		}
	}
}