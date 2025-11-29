<?php
/**
 * Filter Query Builder - Builds Manticore queries for filters
 *
 * @package MantiLoad
 * @subpackage Filters
 */

namespace MantiLoad\Filters;

use MantiLoad\MantiLoad;
use MantiLoad\Manticore_Client;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Filter_Query Class
 *
 * Builds optimized Manticore Search queries for product filters:
 * - Categories
 * - Attributes (color, size, brand, etc.)
 * - Price ranges
 * - Ratings
 * - Stock status
 * - On sale products
 */
class Filter_Query {

	/**
	 * Filter manager instance
	 *
	 * @var Filter_Manager
	 */
	private $manager;

	/**
	 * Manticore client
	 *
	 * @var Manticore_Client
	 */
	private $client;

	/**
	 * Index name
	 *
	 * @var string
	 */
	private $index_name;

	/**
	 * Constructor
	 *
	 * @param Filter_Manager|null $manager Filter manager instance (optional for standalone use).
	 */
	public function __construct( $manager = null ) {
		$this->manager    = $manager;
		$this->client     = new Manticore_Client();
		$this->index_name = MantiLoad::get_option( 'index_name', MantiLoad::get_default_index_name() );
	}

	/**
	 * Get filtered products from Manticore
	 *
	 * @param array $filters        Filter parameters.
	 * @param int   $paged          Current page.
	 * @param int   $posts_per_page Posts per page.
	 * @return array|false Array with 'posts', 'total', 'sql', 'time' or false on error.
	 */
	public function get_filtered_products( $filters, $paged = 1, $posts_per_page = 12 ) {
		$start_time = microtime( true );

		// Build WHERE clause
		$where_clauses = $this->build_where_clauses( $filters );

		// Build MATCH clause for search
		$match_clause = '';
		if ( ! empty( $filters['search'] ) ) {
			$safe_query   = $this->client->get_connection()->real_escape_string( $filters['search'] );
			$match_clause = " WHERE MATCH('$safe_query')";
		}

		// Combine MATCH and WHERE
		$where = $this->combine_clauses( $match_clause, $where_clauses );

		// Build ORDER BY clause
		$order_clause = $this->build_order_clause( $filters );

		// Calculate pagination
		$page   = max( 1, $paged );
		$offset = ( $page - 1 ) * $posts_per_page;

		// Build final SQL
		$sql = "SELECT id FROM {$this->index_name} {$where} {$order_clause} LIMIT {$offset}, {$posts_per_page} OPTION max_matches=100000";

		// Execute query
		$result = $this->client->query( $sql );

		if ( ! $result ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			}
			return false;
		}

		// Get product IDs
		$post_ids = array();
		while ( $row = $result->fetch_assoc() ) {
			$post_ids[] = (int) $row['id'];
		}

		// Get total count (separate query for accuracy)
		$total = $this->get_total_count( $where );

		// Get WP_Post objects in correct order
		$posts = array();
		if ( ! empty( $post_ids ) ) {
			$posts = \get_posts(
				array(
					'post_type'      => 'product',
					'post__in'       => $post_ids,
					'orderby'        => 'post__in',
					'posts_per_page' => -1,
					'post_status'    => 'publish',
				)
			);
		}

		$query_time = microtime( true ) - $start_time;

		return array(
			'posts' => $posts,
			'total' => $total,
			'sql'   => $sql,
			'time'  => $query_time,
		);
	}

	/**
	 * Get product count for filters
	 *
	 * @param array $filters Filter parameters.
	 * @return int
	 */
	public function get_product_count( $filters ) {
		// Build WHERE clause
		$where_clauses = $this->build_where_clauses( $filters );

		// Build MATCH clause
		$match_clause = '';
		if ( ! empty( $filters['search'] ) ) {
			$safe_query   = $this->client->get_connection()->real_escape_string( $filters['search'] );
			$match_clause = " WHERE MATCH('$safe_query')";
		}

		// Combine clauses
		$where = $this->combine_clauses( $match_clause, $where_clauses );

		return $this->get_total_count( $where );
	}

	/**
	 * Apply filters to Query_Integration query args
	 *
	 * @param array $manticore_query Existing query args.
	 * @param array $filters         Filter parameters.
	 * @return array Modified query args.
	 */
	public function apply_to_query( $manticore_query, $filters ) {
		if ( empty( $filters ) ) {
			return $manticore_query;
		}

		// Categories
		if ( ! empty( $filters['categories'] ) ) {
			$manticore_query['category_id'] = $filters['categories'][0]; // First category
		}

		// Attributes
		if ( ! empty( $filters['attributes'] ) ) {
			// Preserve existing attribute_filters instead of overwriting
			if ( empty( $manticore_query['attribute_filters'] ) ) {
				$manticore_query['attribute_filters'] = array();
			}

			foreach ( $filters['attributes'] as $attribute => $terms ) {
				// Ensure attribute taxonomy exists
				if ( taxonomy_exists( $attribute ) ) {
					// Convert slugs to IDs if needed
					$term_ids = array();
					foreach ( $terms as $term_slug_or_id ) {
						if ( is_numeric( $term_slug_or_id ) ) {
							$term_ids[] = (int) $term_slug_or_id;
						} else {
							$term = get_term_by( 'slug', $term_slug_or_id, $attribute );
							if ( $term ) {
								$term_ids[] = $term->term_id;
							}
						}
					}

					if ( ! empty( $term_ids ) ) {
						$manticore_query['attribute_filters'][ $attribute ] = $term_ids;
					}
				}
			}
		}

		// Price range
		if ( ! empty( $filters['price'] ) ) {
			if ( isset( $filters['price']['min'] ) && $filters['price']['min'] > 0 ) {
				$manticore_query['min_price'] = $filters['price']['min'];
			}
			if ( isset( $filters['price']['max'] ) && $filters['price']['max'] > 0 ) {
				$manticore_query['max_price'] = $filters['price']['max'];
			}
		}

		// Rating
		if ( ! empty( $filters['rating'] ) ) {
			$manticore_query['min_rating'] = absint( $filters['rating'] );
		}

		// Stock status
		if ( ! empty( $filters['stock'] ) ) {
			$manticore_query['stock_status'] = \sanitize_text_field( $filters['stock'] );
		}

		// On sale
		if ( ! empty( $filters['on_sale'] ) ) {
			$manticore_query['on_sale'] = true;
		}

		// Search
		if ( ! empty( $filters['search'] ) ) {
			$manticore_query['s'] = \sanitize_text_field( $filters['search'] );
		}

		// Orderby
		if ( ! empty( $filters['orderby'] ) ) {
			$manticore_query['orderby'] = \sanitize_text_field( $filters['orderby'] );
		}

		return $manticore_query;
	}

	/**
	 * Build WHERE clauses for filters
	 *
	 * @param array $filters Filter parameters.
	 * @return array Array of WHERE conditions.
	 */
	private function build_where_clauses( $filters ) {
		$where_clauses = array();

		// Always filter by post type and status
		$where_clauses[] = "post_type = 'product'";
		$where_clauses[] = "post_status = 'publish'";

		// WooCommerce visibility
		$where_clauses[] = "(visibility = 'visible' OR visibility = 'catalog' OR visibility = '')";

		// Hide out of stock if configured
		if ( 'yes' === \get_option( 'woocommerce_hide_out_of_stock_items', 'no' ) ) {
			$where_clauses[] = "stock_status = 'instock'";
		}

		// Categories
		if ( ! empty( $filters['categories'] ) ) {
			$category_ids = array_map( 'absint', (array) $filters['categories'] );
			if ( count( $category_ids ) === 1 ) {
				$where_clauses[] = 'category_ids = ' . $category_ids[0];
			} else {
				$ids_str         = implode( ',', $category_ids );
				$where_clauses[] = "category_ids IN ({$ids_str})";
			}
		}

		// Attributes
		if ( ! empty( $filters['attributes'] ) ) {
			foreach ( $filters['attributes'] as $attribute => $terms ) {
				if ( empty( $terms ) ) {
					continue;
				}

				// Get field name (e.g., pa_color -> pa_color_ids)
				$field_name = str_replace( '-', '_', $attribute ) . '_ids';

				// Convert to term IDs if they're slugs
				$term_ids = array();
				foreach ( $terms as $term_slug_or_id ) {
					if ( is_numeric( $term_slug_or_id ) ) {
						$term_ids[] = (int) $term_slug_or_id;
					} else {
						$term = get_term_by( 'slug', $term_slug_or_id, $attribute );
						if ( $term ) {
							$term_ids[] = $term->term_id;
						}
					}
				}

				if ( ! empty( $term_ids ) ) {
					if ( count( $term_ids ) === 1 ) {
						$where_clauses[] = "{$field_name} = " . $term_ids[0];
					} else {
						$ids_str         = implode( ',', $term_ids );
						$where_clauses[] = "{$field_name} IN ({$ids_str})";
					}
				}
			}
		}

		// Price range
		if ( ! empty( $filters['price'] ) ) {
			if ( isset( $filters['price']['min'] ) && $filters['price']['min'] > 0 ) {
				$min_price       = floatval( $filters['price']['min'] );
				$where_clauses[] = "price >= {$min_price}";
			}
			if ( isset( $filters['price']['max'] ) && $filters['price']['max'] > 0 ) {
				$max_price       = floatval( $filters['price']['max'] );
				$where_clauses[] = "price <= {$max_price}";
			}
		}

		// Rating (X stars and up)
		if ( ! empty( $filters['rating'] ) ) {
			$rating          = absint( $filters['rating'] );
			$where_clauses[] = "average_rating >= {$rating}";
		}

		// Stock status
		if ( ! empty( $filters['stock'] ) ) {
			$stock_status    = $this->client->get_connection()->real_escape_string( $filters['stock'] );
			$where_clauses[] = "stock_status = '{$stock_status}'";
		}

		// On sale
		if ( ! empty( $filters['on_sale'] ) ) {
			// Products with sale price set
			$where_clauses[] = "sale_price > 0";
		}

		return \apply_filters( 'mantiload_filter_where_clauses', $where_clauses, $filters );
	}

	/**
	 * Build ORDER BY clause
	 *
	 * @param array $filters Filter parameters.
	 * @return string
	 */
	private function build_order_clause( $filters ) {
		// Custom orderby parameter - check this FIRST before defaulting to relevance
		$orderby = ! empty( $filters['orderby'] ) ? $filters['orderby'] : '';

		// If search query and no explicit orderby, default to relevance (WEIGHT)
		if ( ! empty( $filters['search'] ) && empty( $orderby ) ) {
			return 'ORDER BY WEIGHT() DESC';
		}

		// Default to date if no orderby specified
		if ( empty( $orderby ) ) {
			$orderby = 'date';
		}
		$order   = ! empty( $filters['order'] ) ? strtoupper( $filters['order'] ) : 'DESC';

		switch ( $orderby ) {
			case 'price':
			case 'price-desc':
				return 'ORDER BY price DESC';

			case 'price-asc':
				return 'ORDER BY price ASC';

			case 'popularity':
				return 'ORDER BY total_sales DESC';

			case 'rating':
				return 'ORDER BY average_rating DESC';

			case 'title':
				return 'ORDER BY post_title ASC';

			case 'menu_order':
				return 'ORDER BY menu_order ASC';

			case 'relevance':
				// Relevance requires WEIGHT() which needs a MATCH query
				if ( ! empty( $filters['search'] ) ) {
					return 'ORDER BY WEIGHT() DESC';
				}
				// Fall through to date if no search query
				return 'ORDER BY post_date DESC';

			case 'date':
			default:
				return 'ORDER BY post_date DESC';
		}
	}

	/**
	 * Combine MATCH clause with WHERE clauses
	 *
	 * @param string $match_clause  MATCH clause.
	 * @param array  $where_clauses WHERE conditions.
	 * @return string
	 */
	private function combine_clauses( $match_clause, $where_clauses ) {
		if ( ! empty( $match_clause ) ) {
			// We have MATCH clause
			if ( ! empty( $where_clauses ) ) {
				// MATCH + WHERE conditions
				return $match_clause . ' AND ' . implode( ' AND ', $where_clauses );
			} else {
				// Just MATCH clause
				return $match_clause;
			}
		} else {
			// No MATCH clause
			if ( ! empty( $where_clauses ) ) {
				return 'WHERE ' . implode( ' AND ', $where_clauses );
			} else {
				return '';
			}
		}
	}

	/**
	 * Get total count of products matching WHERE clause
	 *
	 * @param string $where WHERE clause.
	 * @return int
	 */
	private function get_total_count( $where ) {
		$count_sql = "SELECT COUNT(*) as total FROM {$this->index_name} {$where}";

		$result = $this->client->query( $count_sql );

		if ( ! $result ) {
			return 0;
		}

		$row = $result->fetch_assoc();
		return isset( $row['total'] ) ? (int) $row['total'] : 0;
	}

	/**
	 * Get attribute term counts for search results (FAST!)
	 *
	 * Replaces slow WooCommerce queries like:
	 * SELECT COUNT(*) FROM wp_term_relationships WHERE term_taxonomy_id = X
	 * AND object_id IN (SELECT ID FROM wp_posts WHERE ... LIKE '%search%' ...)
	 *
	 * @param string $taxonomy    Attribute taxonomy (e.g., 'pa_color')
	 * @param array  $term_ids    Term IDs to count
	 * @param string $search_term Current search term
	 * @return array Array of term_id => count
	 */
	public function get_attribute_term_counts_for_search( $taxonomy, $term_ids, $search_term = '' ) {
		if ( empty( $term_ids ) || ! taxonomy_exists( $taxonomy ) ) {
			return array();
		}

		// Build base WHERE clause (published products)
		$where_clauses = array(
			"post_type = 'product'",
			"post_status = 'publish'",
			"(visibility = 'visible' OR visibility = 'catalog' OR visibility = '')"
		);

		// Hide out of stock if configured
		if ( 'yes' === \get_option( 'woocommerce_hide_out_of_stock_items', 'no' ) ) {
			$where_clauses[] = "stock_status = 'instock'";
		}

		// Add search condition if provided
		$match_clause = '';
		if ( ! empty( $search_term ) ) {
			$safe_query   = $this->client->get_connection()->real_escape_string( $search_term );
			$match_clause = "MATCH('$safe_query')";
		}

		// Get field name for this attribute (e.g., pa_color -> pa_color_ids)
		$field_name = str_replace( '-', '_', $taxonomy ) . '_ids';

		$counts = array();

		// Query each term individually for accuracy (Manticore is fast!)
		foreach ( $term_ids as $term_id ) {
			$term_where = $where_clauses;
			$term_where[] = "{$field_name} = " . (int) $term_id;

			$where = $this->combine_clauses( $match_clause, $term_where );
			$count_sql = "SELECT COUNT(*) as total FROM {$this->index_name} {$where}";

			$result = $this->client->query( $count_sql );

			if ( $result ) {
				$row = $result->fetch_assoc();
				$counts[ $term_id ] = isset( $row['total'] ) ? (int) $row['total'] : 0;
			} else {
				$counts[ $term_id ] = 0;
			}
		}

		return $counts;
	}

	/**
	 * Get price range for search results (FAST!)
	 *
	 * Replaces slow WC_Widget_Price_Filter->get_filtered_price() query
	 *
	 * @param string $search_term Current search term
	 * @return array Array with 'min' and 'max' prices
	 */
	public function get_price_range_for_search( $search_term = '' ) {
		// Build base WHERE clause
		$where_clauses = array(
			"post_type = 'product'",
			"post_status = 'publish'",
			"(visibility = 'visible' OR visibility = 'catalog' OR visibility = '')",
			"price > 0" // Only products with prices
		);

		// Hide out of stock if configured
		if ( 'yes' === \get_option( 'woocommerce_hide_out_of_stock_items', 'no' ) ) {
			$where_clauses[] = "stock_status = 'instock'";
		}

		// Add search condition
		$match_clause = '';
		if ( ! empty( $search_term ) ) {
			$safe_query   = $this->client->get_connection()->real_escape_string( $search_term );
			$match_clause = "MATCH('$safe_query')";
		}

		$where = $this->combine_clauses( $match_clause, $where_clauses );
		$sql = "SELECT MIN(price) as min_price, MAX(price) as max_price FROM {$this->index_name} {$where}";

		$result = $this->client->query( $sql );

		if ( $result ) {
			$row = $result->fetch_assoc();
			return array(
				'min' => isset( $row['min_price'] ) ? (float) $row['min_price'] : 0,
				'max' => isset( $row['max_price'] ) ? (float) $row['max_price'] : 0,
			);
		}

		return array( 'min' => 0, 'max' => 0 );
	}
}
