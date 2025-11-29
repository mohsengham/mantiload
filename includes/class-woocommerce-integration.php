<?php
/**
 * WooCommerce Filter Integration
 *
 * Makes WooCommerce/WoodMart layered nav filters blazing fast with Manticore
 *
 * @package MantiLoad
 */

namespace MantiLoad\Integrations;

defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce_Integration class
 *
 * Intercepts WooCommerce product queries and uses Manticore for filtering
 */
class WooCommerce_Integration {

	/**
	 * Search engine instance
	 *
	 * @var \MantiLoad\Search\Search_Engine
	 */
	private $search_engine;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->search_engine = new \MantiLoad\Search\Search_Engine();
		$this->init_hooks();
	}

	/**
	 * Initialize hooks
	 */
	private function init_hooks() {
		// Only hook if integration is enabled
		if ( ! \MantiLoad\MantiLoad::get_option( 'enable_woocommerce_filter_integration', true ) ) {
			return;
		}

		// Hook into WooCommerce product queries
		// Priority 999 to run after WooCommerce sets up tax_query
		\add_action( 'pre_get_posts', array( $this, 'optimize_product_query' ), 999 );

		// Hook into filter counts (the SLOW query you're seeing!)
		// Priority 5 to run BEFORE WooCommerce's default (priority 10)
		\add_filter( 'woocommerce_product_loop_get_filtered_term_product_counts', array( $this, 'get_fast_filter_counts' ), 5, 4 );

		// WooCommerce 10.x uses a new filter system - intercept the query before it runs
		\add_filter( 'woocommerce_get_filtered_term_product_counts_query', array( $this, 'intercept_filter_query' ), 1, 1 );

	}

	/**
	 * Optimize product query with Manticore
	 *
	 * @param \WP_Query $query The query object
	 */
	public function optimize_product_query( $query ) {
		// Only run on main query, product archives, and when filters are active
		if ( ! $query->is_main_query() || is_admin() || ! is_woocommerce() ) {
			return;
		}

		// Check if this is a product query
		$post_type = $query->get( 'post_type' );
		if ( 'product' !== $post_type ) {
			return;
		}

		// Check if MantiLoad is enabled
		if ( ! \MantiLoad\MantiLoad::get_option( 'enabled', true ) ) {
			return;
		}

		// Check if filters are active (layered nav, search, etc.)
		$has_filters = $this->has_active_filters( $query );
		$has_search = ! empty( $query->get( 's' ) );

		if ( ! $has_filters && ! $has_search ) {
			return; // No filters, let WooCommerce handle it normally
		}

		// CRITICAL: Graceful fallback if Manticore is down
		try {
			// Check if Manticore is healthy
			$client = new \MantiLoad\Manticore_Client();
			if ( ! $client->is_healthy() ) {
				// Log and let WooCommerce handle normally
				return; // Let WooCommerce handle it
			}

			// Build Manticore query from WP_Query
			$manticore_ids = $this->get_filtered_product_ids( $query );

			if ( $manticore_ids === false ) {
				// Query failed, let WooCommerce handle it
				return;
			}

			if ( empty( $manticore_ids ) ) {
				// No results - set impossible condition
				$query->set( 'post__in', array( 0 ) );
				return;
			}

			// Inject Manticore results into query
			// This makes WooCommerce use our pre-filtered IDs
			$query->set( 'post__in', $manticore_ids );

			// Remove tax_query since we already filtered by Manticore
			// This prevents slow taxonomy joins
			$query->set( 'tax_query', array() );

		} catch ( \Exception $e ) {
			// Log error and let WooCommerce handle normally
			return; // Graceful fallback - let WooCommerce do its thing
		}
	}

	/**
	 * Check if query has active filters
	 *
	 * @param \WP_Query $query Query object
	 * @return bool
	 */
	private function has_active_filters( $query ) {
		// Check for layered nav filters (?filter_pa_color=red)
		foreach ( $_GET as $key => $value ) {
			if ( strpos( $key, 'filter_' ) === 0 && ! empty( $value ) ) {
				return true;
			}
		}

		// Check for meta_query (price filters, stock filters, etc.)
		$meta_query = $query->get( 'meta_query' );
		if ( ! empty( $meta_query ) ) {
			return true;
		}

		// DON'T intercept tax_query for basic category browsing
		// Only intercept when actual layered nav filters are applied
		// This prevents breaking category archives when category_ids aren't indexed yet

		return false;
	}

	/**
	 * Get filtered product IDs from Manticore
	 *
	 * @param \WP_Query $query Query object
	 * @return array Product IDs
	 */
	private function get_filtered_product_ids( $query ) {
		$start_time = microtime( true );

		// Create Manticore client
		$client = new \MantiLoad\Manticore_Client();

		// Build Manticore filters
		$filters = array();

		// 1. Handle search query
		$search_query = $query->get( 's' );

		// 2. Handle layered nav attribute filters
		$attribute_filters = $this->parse_attribute_filters();
		if ( ! empty( $attribute_filters ) ) {
			$filters = array_merge( $filters, $attribute_filters );
		}

		// 3. Handle tax_query (categories, tags, attributes set by widgets)
		$tax_query = $query->get( 'tax_query' );
		if ( ! empty( $tax_query ) ) {
			$tax_filters = $this->parse_tax_query( $tax_query );
			if ( ! empty( $tax_filters ) ) {
				$filters = array_merge( $filters, $tax_filters );
			}
		}

		// 4. Handle meta_query (price, stock, rating, etc.)
		$meta_query = $query->get( 'meta_query' );
		if ( ! empty( $meta_query ) ) {
			$meta_filters = $this->parse_meta_query( $meta_query );
			if ( ! empty( $meta_filters ) ) {
				$filters = array_merge( $filters, $meta_filters );
			}
		}

		// 5. Handle price URL parameters (?min_price=X&max_price=Y)
		$price_filters = $this->parse_price_filters();
		if ( ! empty( $price_filters ) ) {
			$filters = array_merge( $filters, $price_filters );
		}

		// Build Manticore SQL
		$index_name = \MantiLoad\MantiLoad::get_option( 'index_name' );

		// CRITICAL: Always filter by product post type in WooCommerce queries
		$sql = "SELECT id FROM {$index_name} WHERE post_type='product' AND post_status='publish'";

		// Add search condition
		if ( ! empty( $search_query ) ) {
			$escaped_query = $client->escape( $search_query );
			$sql .= " AND MATCH('{$escaped_query}')";
		}

		// Add filters
		foreach ( $filters as $filter ) {
			$sql .= " AND {$filter}";
		}

		// Get ALL matching products (Manticore is fast!)
		// We need all IDs because WordPress will filter them further
		$limit = 100000; // Fetch all results (Manticore can handle this easily)

		$sql .= " LIMIT {$limit}";

		// Execute query
		try {
			$results = $client->query( $sql );

			$product_ids = array();
			if ( ! empty( $results ) ) {
				foreach ( $results as $row ) {
					$product_ids[] = (int) $row['id'];
				}
			}

			return $product_ids;

		} catch ( \Exception $e ) {
			// Return false to signal error (calling code will fallback to WordPress)
			return false;
		}
	}

	/**
	 * Parse layered nav attribute filters from URL
	 *
	 * Examples:
	 * - ?filter_pa_color=red,blue
	 * - ?filter_color=red
	 *
	 * @return array Manticore filter conditions
	 */
	private function parse_attribute_filters() {
		$filters = array();

		foreach ( $_GET as $key => $value ) {
			if ( strpos( $key, 'filter_' ) !== 0 || empty( $value ) ) {
				continue;
			}

			// Extract attribute name (e.g., 'color' from 'filter_color' or 'pa_color' from 'filter_pa_color')
			$attribute = str_replace( 'filter_', '', $key );

			// WoodMart uses 'filter_color' but taxonomy is 'pa_color'
			// Try both formats: with and without 'pa_' prefix
			$attribute_variations = array( $attribute );
			if ( strpos( $attribute, 'pa_' ) !== 0 ) {
				$attribute_variations[] = 'pa_' . $attribute; // Try with pa_ prefix
			}

			// Get term slugs
			$term_slugs = array_map( 'sanitize_title', explode( ',', $value ) );

			// Get term IDs from slugs (try all attribute variations)
			$term_ids = array();
			foreach ( $term_slugs as $slug ) {
				foreach ( $attribute_variations as $attr_name ) {
					$term = get_term_by( 'slug', $slug, $attr_name );
					if ( $term && ! is_wp_error( $term ) ) {
						$term_ids[] = $term->term_id;
						$attribute = $attr_name; // Use the one that worked
						break; // Found it, stop checking variations
					}
				}
			}

			if ( empty( $term_ids ) ) {
				continue;
			}

			// Build Manticore filter
			// Use attribute-specific field if available (e.g., pa_color_ids)
			$field_name = str_replace( '-', '_', $attribute ) . '_ids';

			// Check query type (AND or OR)
			$query_type = isset( $_GET[ 'query_type_' . $attribute ] ) ? sanitize_text_field( wp_unslash( $_GET[ 'query_type_' . $attribute ] ) ) : 'or';

			if ( $query_type === 'and' ) {
				// ALL filter - product must have ALL selected terms
				foreach ( $term_ids as $term_id ) {
					$filters[] = "{$field_name} = {$term_id}";
				}
			} else {
				// ANY filter - product must have ANY selected term
				$term_list = implode( ',', $term_ids );
				$filters[] = "{$field_name} IN ({$term_list})";
			}
		}

		return $filters;
	}

	/**
	 * Parse WP_Query tax_query to Manticore filters
	 *
	 * @param array $tax_query Tax query array
	 * @return array Manticore filter conditions
	 */
	private function parse_tax_query( $tax_query ) {
		$filters = array();

		foreach ( $tax_query as $tax ) {
			// Skip relation keys
			if ( ! is_array( $tax ) || ! isset( $tax['taxonomy'] ) ) {
				continue;
			}

			$taxonomy = $tax['taxonomy'];
			$terms = isset( $tax['terms'] ) ? (array) $tax['terms'] : array();
			$field = isset( $tax['field'] ) ? $tax['field'] : 'term_id';
			$operator = isset( $tax['operator'] ) ? $tax['operator'] : 'IN';

			if ( empty( $terms ) ) {
				continue;
			}

			// Convert to term IDs if needed
			$term_ids = array();
			foreach ( $terms as $term ) {
				if ( $field === 'term_id' ) {
					$term_ids[] = (int) $term;
				} elseif ( $field === 'slug' ) {
					$term_obj = get_term_by( 'slug', $term, $taxonomy );
					if ( $term_obj && ! is_wp_error( $term_obj ) ) {
						$term_ids[] = $term_obj->term_id;
					}
				} elseif ( $field === 'name' ) {
					$term_obj = get_term_by( 'name', $term, $taxonomy );
					if ( $term_obj && ! is_wp_error( $term_obj ) ) {
						$term_ids[] = $term_obj->term_id;
					}
				}
			}

			if ( empty( $term_ids ) ) {
				continue;
			}

			// Determine field name
			$field_name = 'category_ids'; // Default for product_cat
			if ( $taxonomy === 'product_tag' ) {
				$field_name = 'tag_ids';
			} elseif ( strpos( $taxonomy, 'pa_' ) === 0 ) {
				// Product attribute (e.g., pa_color)
				$field_name = str_replace( '-', '_', $taxonomy ) . '_ids';
			}

			// Build filter based on operator
			if ( $operator === 'IN' ) {
				$term_list = implode( ',', $term_ids );
				$filters[] = "{$field_name} IN ({$term_list})";
			} elseif ( $operator === 'AND' ) {
				foreach ( $term_ids as $term_id ) {
					$filters[] = "{$field_name} = {$term_id}";
				}
			} elseif ( $operator === 'NOT IN' ) {
				$term_list = implode( ',', $term_ids );
				$filters[] = "{$field_name} NOT IN ({$term_list})";
			}
		}

		return $filters;
	}

	/**
	 * Parse price URL parameters to Manticore filters
	 *
	 * Handles WooCommerce/WoodMart price filters:
	 * - ?min_price=100
	 * - ?max_price=500
	 * - ?min_price=100&max_price=500
	 *
	 * @return array Manticore filter conditions
	 */
	private function parse_price_filters() {
		$filters = array();

		$min_price = isset( $_GET['min_price'] ) ? floatval( $_GET['min_price'] ) : 0;
		$max_price = isset( $_GET['max_price'] ) ? floatval( $_GET['max_price'] ) : 0;

		if ( $min_price > 0 ) {
			$filters[] = "price >= {$min_price}";
		}

		if ( $max_price > 0 ) {
			$filters[] = "price <= {$max_price}";
		}

		return $filters;
	}

	/**
	 * Parse WP_Query meta_query to Manticore filters
	 *
	 * @param array $meta_query Meta query array
	 * @return array Manticore filter conditions
	 */
	private function parse_meta_query( $meta_query ) {
		$filters = array();

		foreach ( $meta_query as $meta ) {
			// Skip relation keys
			if ( ! is_array( $meta ) || ! isset( $meta['key'] ) ) {
				continue;
			}

			$key = $meta['key'];
			$value = isset( $meta['value'] ) ? $meta['value'] : '';
			$compare = isset( $meta['compare'] ) ? $meta['compare'] : '=';

			// Map meta keys to Manticore fields
			$field_map = array(
				'_price' => 'price',
				'_regular_price' => 'regular_price',
				'_sale_price' => 'sale_price',
				'_stock_status' => 'in_stock',
				'_stock' => 'stock_quantity',
				'_featured' => 'featured',
				'total_sales' => 'total_sales',
				'_wc_average_rating' => 'rating',
			);

			if ( ! isset( $field_map[ $key ] ) ) {
				continue; // Unsupported meta key
			}

			$field_name = $field_map[ $key ];

			// Build filter based on comparison
			switch ( $compare ) {
				case '=':
				case '==':
					$filters[] = "{$field_name} = " . floatval( $value );
					break;
				case '!=':
					$filters[] = "{$field_name} != " . floatval( $value );
					break;
				case '>':
					$filters[] = "{$field_name} > " . floatval( $value );
					break;
				case '>=':
					$filters[] = "{$field_name} >= " . floatval( $value );
					break;
				case '<':
					$filters[] = "{$field_name} < " . floatval( $value );
					break;
				case '<=':
					$filters[] = "{$field_name} <= " . floatval( $value );
					break;
				case 'BETWEEN':
					if ( is_array( $value ) && count( $value ) === 2 ) {
						$min = floatval( $value[0] );
						$max = floatval( $value[1] );
						$filters[] = "{$field_name} >= {$min} AND {$field_name} <= {$max}";
					}
					break;
			}
		}

		return $filters;
	}

	/**
	 * Get fast filter counts using Manticore
	 *
	 * Replaces the SLOW WooCommerce query for filter counts
	 *
	 * @param array  $term_counts Original counts (we'll replace these)
	 * @param string $taxonomy Taxonomy name (e.g., 'pa_color')
	 * @param string $query_type 'or' or 'and'
	 * @param array  $filtered_term_ids Currently selected terms
	 * @return array Fast counts from Manticore
	 */
	public function get_fast_filter_counts( $term_counts, $taxonomy, $query_type, $filtered_term_ids ) {
		$start_time = microtime( true );

		try {
			$client = new \MantiLoad\Manticore_Client();

			// CRITICAL: Check if Manticore is healthy before attempting FACET
			if ( ! $client->is_healthy() ) {
				return $term_counts; // Graceful fallback
			}

			$index_name = \MantiLoad\MantiLoad::get_option( 'index_name' );

			// Get the Manticore field name (e.g., pa_color → pa_color_ids)
			$field_name = str_replace( '-', '_', $taxonomy ) . '_ids';

			// Base query - filter by category if on category page
			$base_where = "post_type='product' AND post_status='publish'";

			// Add search query if present (CRITICAL for search pages!)
			global $wp_query;
			$search_query = $wp_query->get( 's' );
			if ( ! empty( $search_query ) ) {
				$escaped_query = $client->escape( $search_query );
				$base_where .= " AND MATCH('{$escaped_query}')";
			}

			// Add category filter if we're on a category page
			if ( is_product_category() ) {
				$current_cat = get_queried_object();
				if ( $current_cat && isset( $current_cat->term_id ) ) {
					$base_where .= " AND category_ids = {$current_cat->term_id}";
				}
			}

			// Add currently selected filters (excluding this taxonomy)
			if ( ! empty( $filtered_term_ids ) && $query_type !== 'or' ) {
				// When using AND logic, filter by currently selected terms
				$filtered_ids_str = implode( ',', array_map( 'intval', $filtered_term_ids ) );
				$base_where .= " AND {$field_name} IN ({$filtered_ids_str})";
			}

			// 🚀 USE FACET - Get ALL counts in ONE query instead of looping!
			// Old way: SELECT COUNT for each term (20 queries for 20 colors!)
			// New way: ONE query with FACET returns all counts!

			$sql = "SELECT * FROM {$index_name}
			        WHERE {$base_where}
			        LIMIT 0
			        FACET {$field_name} ORDER BY COUNT(*) DESC";

			$conn = $client->get_connection();
			if ( ! $conn ) {
				return $term_counts; // Fallback
			}

			$new_counts = array();

			// Execute FACET query
			if ( $conn->multi_query( $sql ) ) {
				$result_num = 0;

				do {
					if ( $result = $conn->store_result() ) {
						$result_num++;

						if ( $result_num == 2 ) {
							// Second result set is the FACET data!
							while ( $row = $result->fetch_assoc() ) {
								// Extract term_id and count
								foreach ( $row as $key => $value ) {
									if ( $key === 'count(*)' ) {
										$count = (int) $value;
									} elseif ( $key === $field_name ) {
										$term_id = (int) $value;
									}
								}

								if ( isset( $term_id ) && isset( $count ) && $count > 0 ) {
									$new_counts[ $term_id ] = $count;
								}
							}
						}

						$result->free();
					}

					if ( $conn->more_results() ) {
						$conn->next_result();
					} else {
						break;
					}

				} while ( true );
			}

			return $new_counts;

		} catch ( \Exception $e ) {
			// Fallback to original counts on error
			
			return $term_counts;
		}
	}

	/**
	 * Intercept WooCommerce filter query and make it faster (WooCommerce 10.x)
	 * This hook fires BEFORE the slow SQL query runs
	 */
	public function intercept_filter_query( $query ) {
		// This filter receives the SQL query array that WooCommerce will use to count filter terms
		// We need to extract the taxonomy being queried, run a fast Manticore FACET, and inject results

		try {
			// Extract the taxonomy from the WHERE clause
			// WooCommerce 10.x uses: wp_xxx_wc_product_attributes_lookup.term_id IN (...)
			// Older versions used: terms.term_id IN (...)
			if ( ! isset( $query['where'] ) || empty( $query['where'] ) ) {
				return $query; // Can't determine taxonomy
			}

			// Try to extract term_ids from the WHERE clause - handle multiple formats
			$patterns = array(
				'/term_id IN \(([0-9,]+)\)/',           // Generic pattern (works for both)
				'/terms\.term_id IN \(([0-9,]+)\)/',    // Old format
				'/_wc_product_attributes_lookup\.term_id IN \(([0-9,]+)\)/', // New WooCommerce format
			);

			$term_ids_str = null;
			foreach ( $patterns as $pattern ) {
				if ( preg_match( $pattern, $query['where'], $matches ) ) {
					$term_ids_str = $matches[1];
					break;
				}
			}

			if ( ! $term_ids_str ) {
				return $query; // Can't parse term IDs
			}

			$term_ids = array_map( 'intval', explode( ',', $term_ids_str ) );

			if ( empty( $term_ids ) ) {
				return $query;
			}

			// Get the taxonomy from the first term
			$first_term = get_term( $term_ids[0] );
			if ( ! $first_term || is_wp_error( $first_term ) ) {
				return $query;
			}

			$taxonomy = $first_term->taxonomy;

			// Check if this is a search query (contains LIKE patterns)
			// If so, we need to use Manticore to get the matching product IDs first
			// Note: wpdb->prepare() escapes % to {hash} so we check for both patterns
			$is_search_query = preg_match( '/LIKE\s+[\'"]\{?[0-9a-f%]/', $query['where'] );

			if ( $is_search_query ) {
				// Get search query from global wp_query
				global $wp_query;
				$search_term = isset( $wp_query ) ? $wp_query->get( 's' ) : '';

				if ( ! empty( $search_term ) ) {
					// Get matching product IDs from Manticore
					$client = new \MantiLoad\Manticore_Client();
					if ( $client->is_healthy() ) {
						$index_name = \MantiLoad\MantiLoad::get_option( 'index_name' );
						$escaped_query = $client->escape( $search_term );

						// Get all matching product IDs
						$sql = "SELECT id FROM {$index_name} WHERE post_type='product' AND post_status='publish' AND MATCH('{$escaped_query}') LIMIT 100000";
						$results = $client->query( $sql );

						if ( ! empty( $results ) ) {
							$product_ids = array();
							foreach ( $results as $row ) {
								$product_ids[] = (int) $row['id'];
							}

							if ( ! empty( $product_ids ) ) {
								// Replace the LIKE patterns with ID IN clause
								$ids_str = implode( ',', $product_ids );

								// Store original for comparison
								$original_where = $query['where'];

								// Remove LIKE patterns from WHERE clause
								// Pattern matches: AND ((table.field LIKE '{hash}...{hash}') OR ...)
								// Note: wpdb->prepare() escapes % to {64-char-hash}
								// The pattern looks for: AND ((anything LIKE 'anything') OR (anything LIKE 'anything') ...)
								$query['where'] = preg_replace(
									'/\s*AND\s*\(\s*\([^)]+LIKE\s+\'[^\']+\'\s*\)(?:\s*OR\s*\([^)]+LIKE\s+\'[^\']+\'\s*\))*\s*\)/is',
									" AND product_or_parent_id IN ({$ids_str})",
									$query['where']
								);

								// Return the optimized query - let WooCommerce execute it
								// This is still faster than LIKE patterns
								return $query;
							}
						}
					}
				}
			}

			// Now run Manticore FACET query to get counts
			$counts = $this->get_fast_filter_counts_for_query( $taxonomy, $term_ids );

			if ( empty( $counts ) ) {
				return $query; // Fallback to MySQL
			}

			// Build a super-fast dummy query that returns the Manticore results
			// We'll use a UNION of SELECT statements with hardcoded values
			$select_parts = array();
			foreach ( $counts as $term_id => $count ) {
				$select_parts[] = "SELECT {$count} as term_count, {$term_id} as term_count_id";
			}

			if ( empty( $select_parts ) ) {
				return $query;
			}

			// Replace the entire query with our pre-calculated results
			$fast_query = array(
				'select' => implode( ' UNION ALL ', $select_parts ),
				'from'   => '',
				'join'   => '',
				'where'  => '',
				'group'  => '',
			);

			return $fast_query;

		} catch ( \Exception $e ) {
			
			return $query; // Fallback to original query
		}
	}

	/**
	 * Get fast filter counts using Manticore FACET
	 *
	 * @param string $taxonomy The taxonomy to get counts for.
	 * @param array  $term_ids The term IDs to count.
	 * @return array Array of term_id => count
	 */
	private function get_fast_filter_counts_for_query( $taxonomy, $term_ids ) {
		try {
			$client = new \MantiLoad\Manticore_Client();

			if ( ! $client->is_healthy() ) {
				return array();
			}

			$index_name = \MantiLoad\MantiLoad::get_option( 'index_name' );

			// Get the Manticore field name (e.g., pa_color → pa_color_ids)
			$field_name = str_replace( '-', '_', $taxonomy ) . '_ids';

			// Base query - filter by post type and status
			$base_where = "post_type='product' AND post_status='publish'";

			// Add search query if present (CRITICAL for search pages!)
			global $wp_query;
			$search_query = $wp_query->get( 's' );
			if ( ! empty( $search_query ) ) {
				$escaped_query = $client->escape( $search_query );
				$base_where .= " AND MATCH('{$escaped_query}')";
			}

			// Add category filter if we're on a category page
			if ( is_product_category() ) {
				$current_cat = get_queried_object();
				if ( $current_cat && isset( $current_cat->term_id ) ) {
					$base_where .= " AND category_ids = {$current_cat->term_id}";
				}
			}

			// Build FACET query
			$sql = "SELECT * FROM {$index_name}
					WHERE {$base_where}
					LIMIT 0
					FACET {$field_name} ORDER BY COUNT(*) DESC";

			$conn = $client->get_connection();
			if ( ! $conn ) {
				return array();
			}

			$counts = array();

			// Execute FACET query
			if ( $conn->multi_query( $sql ) ) {
				$result_num = 0;

				do {
					if ( $result = $conn->store_result() ) {
						$result_num++;

						if ( $result_num == 2 ) {
							// Second result set is the FACET data!
							while ( $row = $result->fetch_assoc() ) {
								$term_id = null;
								$count = null;

								foreach ( $row as $key => $value ) {
									if ( $key === 'count(*)' ) {
										$count = (int) $value;
									} elseif ( $key === $field_name ) {
										$term_id = (int) $value;
									}
								}

								if ( isset( $term_id ) && isset( $count ) && $count > 0 ) {
									// Only include terms that were requested
									if ( in_array( $term_id, $term_ids, true ) ) {
										$counts[ $term_id ] = $count;
									}
								}
							}
						}

						$result->free();
					}

					if ( $conn->more_results() ) {
						$conn->next_result();
					} else {
						break;
					}

				} while ( true );
			}

			return $counts;

		} catch ( \Exception $e ) {
			
			return array();
		}
	}

}
