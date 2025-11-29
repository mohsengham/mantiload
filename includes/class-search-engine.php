<?php
/**
 * Search Engine Class
 *
 * @package MantiLoad
 */

namespace MantiLoad\Search;

use MantiLoad\Manticore_Client;
use MantiLoad\Query_Builder;

defined( 'ABSPATH' ) || exit;

/**
 * Search_Engine class
 *
 * Handles search queries and result processing
 */
class Search_Engine {

	/**
	 * Manticore client
	 *
	 * @var Manticore_Client
	 */
	private $client;

	/**
	 * Track if main query has been processed
	 *
	 * @var bool
	 */
	private static $main_query_processed = false;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->client = new Manticore_Client();
		$this->init_hooks();
	}

	/**
	 * Initialize hooks
	 */
	private function init_hooks() {
		// ElasticPress-inspired multi-hook approach for maximum compatibility

		// Step 1: Detect queries we should handle (runs early)
		\add_action( 'pre_get_posts', array( $this, 'detect_mantiload_query' ), 5 );

		// Step 2: Capture WooCommerce catalog orderby from URL parameters
		\add_action( 'pre_get_posts', array( $this, 'capture_woocommerce_orderby' ), 999 );

		// Step 3: Intercept the SQL query to prevent WordPress from running it
		\add_filter( 'posts_request', array( $this, 'intercept_posts_request' ), 10, 2 );

		// Step 4: Inject our Manticore results
		\add_filter( 'posts_pre_query', array( $this, 'pre_query_override' ), 10, 2 );

		// Step 5: Fallback if posts_pre_query doesn't work (inject via posts_results)
		\add_filter( 'posts_results', array( $this, 'inject_posts_results' ), 10, 2 );
	}

	/**
	 * Detect if this query should be handled by MantiLoad (ElasticPress approach)
	 *
	 * This runs EARLY in pre_get_posts to mark queries before any processing
	 *
	 * @param \WP_Query $query Query object
	 */
	public function detect_mantiload_query( $query ) {
		// Only process main query on frontend
		if ( ! $query->is_main_query() || is_admin() ) {
			return;
		}

		// Skip single pages
		if ( $query->is_singular() ) {
			return;
		}

		// Check if MantiLoad is enabled
		if ( ! \MantiLoad\MantiLoad::get_option( 'enabled', true ) ) {
			return;
		}

		// Determine if we should handle this query
		$is_search = $query->is_search();

		// Check for ANY attribute filter parameters (filter_*)
		// Let Query_Integration handle these - it has complete attribute filter support
		$has_attribute_filters = false;
		foreach ( $_GET as $key => $value ) {
			if ( strpos( $key, 'filter_' ) === 0 && ! empty( $value ) ) {
				$has_attribute_filters = true;
				break;
			}
		}

		// If there are attribute filters, let Query_Integration handle it
		if ( $has_attribute_filters ) {
			return;
		}

		$has_filters = ! empty( $_GET['min_price'] ) || ! empty( $_GET['max_price'] );
		$is_product_archive = \is_shop() || \is_product_category() || \is_product_tag() || \is_product_taxonomy();

		$tax_query = $query->get( 'tax_query' );
		$post_type = $query->get( 'post_type' );
		$is_product_query = $post_type === 'product' || ( ! empty( $tax_query ) && $this->has_product_taxonomy( $tax_query ) );

		// Check Archive Optimization setting
		$archive_optimization_enabled = \MantiLoad\MantiLoad::get_option( 'enable_archive_optimization', false );

		$should_handle = false;

		if ( $is_search ) {
			// Always handle search queries
			$should_handle = true;
		} elseif ( $has_filters || $is_product_archive || $is_product_query ) {
			// Handle archives only if Archive Optimization is enabled
			$should_handle = $archive_optimization_enabled;
		}

		if ( $should_handle ) {
			// Mark this query so we can identify it later
			$query->set( 'mantiload_integrate', true );
		}
	}

	/**
	 * Intercept posts_request to prevent WordPress from running the SQL query
	 *
	 * @param string $sql SQL query
	 * @param \WP_Query $query Query object
	 * @return string
	 */
	public function intercept_posts_request( $sql, $query ) {
		if ( ! $query->get( 'mantiload_integrate' ) ) {
			return $sql;
		}

		// Return a query that returns no results to prevent WordPress from overwriting our posts
		// We've already set posts in posts_pre_query
		global $wpdb;
		return "SELECT * FROM {$wpdb->posts} WHERE 1=0";
	}

	/**
	 * Inject results via posts_results (fallback if posts_pre_query doesn't work)
	 *
	 * @param array $posts Posts array
	 * @param \WP_Query $query Query object
	 * @return array
	 */
	public function inject_posts_results( $posts, $query ) {
		// Only inject if we marked this query
		if ( ! $query->get( 'mantiload_integrate' ) ) {
			return $posts;
		}

		// If we have cached results from pre_query_override, use them
		$cached_posts = $query->get( 'mantiload_posts' );
		if ( ! empty( $cached_posts ) ) {
			return $cached_posts;
		}

		// If already processed but no cached posts, run search
		if ( ! $query->get( 'mantiload_processed' ) ) {
			return $this->do_mantiload_search( $query );
		}

		return $posts;
	}

	/**
	 * Inject MantiLoad search results
	 *
	 * @param array $posts Posts array
	 * @param \WP_Query $query Query object
	 * @return array
	 */
	public function inject_results( $posts, $query ) {
		if ( ! $query->get( 'mantiload_search' ) ) {
			return $posts;
		}

		$search_query = $query->get( 's' );

		// Normalize Persian/Arabic numerals to Latin for consistent search
		$search_query = Manticore_Client::normalize_numerals( $search_query );

		$post_types = $query->get( 'post_type' ) ?: array( 'post', 'page', 'product' );
		$posts_per_page = $query->get( 'posts_per_page' ) ?: 20;
		$paged = $query->get( 'paged' ) ?: 1;
		$offset = ( $paged - 1 ) * $posts_per_page;

		$results = $this->search( $search_query, array(
			'post_type' => $post_types,
			'limit' => $posts_per_page,
			'offset' => $offset,
		) );

		if ( ! empty( $results['posts'] ) ) {
			$query->found_posts = $results['total'];
			$query->max_num_pages = ceil( $results['total'] / $posts_per_page );
			return $results['posts'];
		}

		return $posts;
	}

	/**
	 * Pre-query override - completely bypass WordPress query for search
	 *
	 * @param array|null $posts Posts array or null
	 * @param \WP_Query $query Query object
	 * @return array|null
	 */
	public function pre_query_override( $posts, $query ) {
		// Check if this query was marked for MantiLoad integration
		if ( ! $query->get( 'mantiload_integrate' ) ) {
			return $posts;
		}

		// Prevent processing multiple times
		if ( $query->get( 'mantiload_processed' ) ) {
			return $posts;
		}

		// Mark as processed
		$query->set( 'mantiload_processed', true );

		// Ensure orderby from URL is captured - URL parameter should ALWAYS take priority
		if ( isset( $_GET['orderby'] ) ) {
			$orderby = \sanitize_text_field( \wp_unslash( $_GET['orderby'] ) );
			$query->set( 'orderby', $orderby );

			// Set order direction for price
			if ( $orderby === 'price' || $orderby === 'price-desc' ) {
				$query->set( 'order', 'DESC' );
			} elseif ( $orderby === 'price-asc' ) {
				$query->set( 'order', 'ASC' );
			}
		}

		// Do the Manticore search and return posts
		return $this->do_mantiload_search( $query );
	}

	/**
	 * Perform the actual Manticore search (ElasticPress-inspired)
	 *
	 * This is called from either pre_query_override or inject_posts_results
	 *
	 * @param \WP_Query $query Query object
	 * @return array Posts array
	 */
	private function do_mantiload_search( $query ) {
		// Check if this is a query we should handle
		$is_search = $query->is_search();
		$has_filters = ! empty( $_GET['filter_color'] ) || ! empty( $_GET['filter_size'] ) || ! empty( $_GET['filter_neckline'] ) || ! empty( $_GET['filter_fabric'] ) || ! empty( $_GET['min_price'] ) || ! empty( $_GET['max_price'] );

		// Check if this is a product archive by checking multiple conditions
		$is_product_archive = \is_shop() || \is_product_category() || \is_product_tag() || \is_product_taxonomy();

		// Also check if query has product tax_query or is for product post type
		$tax_query = $query->get( 'tax_query' );
		$post_type = $query->get( 'post_type' );
		$is_product_query = $post_type === 'product' || ( ! empty( $tax_query ) && $this->has_product_taxonomy( $tax_query ) );

		$search_query = $query->get( 's' );

		// Normalize Persian/Arabic numerals to Latin for consistent search
		$search_query = Manticore_Client::normalize_numerals( $search_query );

		$post_types = $query->get( 'post_type' ) ?: 'product';

		// Get posts_per_page - it should already be set correctly by WooCommerce
		$posts_per_page = $query->get( 'posts_per_page' );

		// If somehow it's -1 or invalid, calculate from WooCommerce settings
		if ( $posts_per_page == -1 || $posts_per_page <= 0 ) {
			// Calculate from WooCommerce settings: rows × columns
			$rows = \get_option( 'woocommerce_catalog_rows' );
			$columns = \get_option( 'woocommerce_catalog_columns' );
			if ( $rows && $columns ) {
				$posts_per_page = absint( $rows * $columns );
			} else {
				// Last resort: use WordPress default
				$posts_per_page = \get_option( 'posts_per_page', 10 );
			}
		}

		$paged = $query->get( 'paged' ) ?: 1;
		$offset = ( $paged - 1 ) * $posts_per_page;

		// No longer need 5x multiplier - hidden products are now filtered in Manticore
		$manticore_limit = $posts_per_page;
		$manticore_offset = $offset;

		// Get WooCommerce orderby parameter
		$orderby = $query->get( 'orderby' );
		$order = $query->get( 'order' ) ?: 'DESC';

		// Parse URL attribute filters
		$filters = array();
		if ( $has_filters ) {
			$filters['attributes'] = array();
			foreach ( $_GET as $key => $value ) {
				if ( strpos( $key, 'filter_' ) === 0 ) {
					$taxonomy = 'pa_' . str_replace( 'filter_', '', $key );
					$filters['attributes'][ $taxonomy ] = explode( ',', \sanitize_text_field( $value ) );
				}
			}
		}

		// Parse price filters from URL
		if ( ! empty( $_GET['min_price'] ) || ! empty( $_GET['max_price'] ) ) {
			$filters['price'] = array(
				'min' => ! empty( $_GET['min_price'] ) ? floatval( $_GET['min_price'] ) : 0,
				'max' => ! empty( $_GET['max_price'] ) ? floatval( $_GET['max_price'] ) : 999999,
			);
		}

		// Add category filter if on category page
		if ( \is_product_category() ) {
			$current_category = get_queried_object();
			if ( $current_category && isset( $current_category->term_id ) ) {
				$filters['category'] = $current_category->term_id;
			}
		}

		// Also check tax_query for category/tag/attribute filters
		if ( ! empty( $tax_query ) && is_array( $tax_query ) ) {
			foreach ( $tax_query as $clause ) {
				if ( ! is_array( $clause ) || ! isset( $clause['taxonomy'] ) ) {
					continue;
				}
				// error_log( '[MantiLoad] Processing tax_query clause: taxonomy=' . $clause['taxonomy'] . ', field=' . ($clause['field'] ?? 'NOT_SET') . ', terms=' . json_encode($clause['terms'] ?? 'NOT_SET') );

				// Category filter
				if ( $clause['taxonomy'] === 'product_cat' && isset( $clause['terms'] ) ) {
					$terms = (array) $clause['terms'];
					if ( isset( $clause['field'] ) && $clause['field'] === 'term_id' ) {
						$filters['category'] = (int) $terms[0];
					} elseif ( isset( $clause['field'] ) && $clause['field'] === 'slug' ) {
						$term_obj = get_term_by( 'slug', $terms[0], 'product_cat' );
						if ( $term_obj ) {
							$filters['category'] = $term_obj->term_id;
						}
					}
				}

				// Tag filter
				if ( $clause['taxonomy'] === 'product_tag' && isset( $clause['terms'] ) ) {
					$terms = (array) $clause['terms'];
					if ( isset( $clause['field'] ) && $clause['field'] === 'term_id' ) {
						$filters['tag'] = (int) $terms[0];
					} elseif ( isset( $clause['field'] ) && $clause['field'] === 'slug' ) {
						$term_obj = get_term_by( 'slug', $terms[0], 'product_tag' );
						if ( $term_obj ) {
							$filters['tag'] = $term_obj->term_id;
						}
					}
				}

				// Attribute filters
				if ( strpos( $clause['taxonomy'], 'pa_' ) === 0 && isset( $clause['terms'] ) ) {
					$terms = (array) $clause['terms'];
					if ( ! isset( $filters['attributes'] ) ) {
						$filters['attributes'] = array();
					}

					// Convert term IDs to slugs if needed
					if ( isset( $clause['field'] ) && $clause['field'] === 'term_id' ) {
						$slugs = array();
						foreach ( $terms as $term_id ) {
							$term_obj = get_term( $term_id, $clause['taxonomy'] );
							if ( $term_obj && ! is_wp_error( $term_obj ) ) {
								$slugs[] = $term_obj->slug;
							}
						}
						$filters['attributes'][ $clause['taxonomy'] ] = $slugs;
					} else {
						$filters['attributes'][ $clause['taxonomy'] ] = $terms;
					}
				}
			}
		}

		// Add tag filter if on tag page
		if ( \is_product_tag() && ! isset( $filters['tag'] ) ) {
			$current_tag = get_queried_object();
			if ( $current_tag && isset( $current_tag->term_id ) ) {
				$filters['tag'] = $current_tag->term_id;
			}
		}

		// Add attribute taxonomy filter if on attribute archive
		if ( \is_product_taxonomy() ) {
			$current_term = get_queried_object();
			if ( $current_term && isset( $current_term->taxonomy ) && isset( $current_term->term_id ) ) {
				// This is an attribute archive (e.g., /pa_color/red/)
				// Only handle ATTRIBUTE taxonomies (pa_*), not product_cat or product_tag
				if ( strpos( $current_term->taxonomy, 'pa_' ) === 0 ) {
					// error_log( '[MantiLoad] \is_product_taxonomy() detected ATTRIBUTE taxonomy: taxonomy=' . $current_term->taxonomy . ', term_id=' . $current_term->term_id . ', slug=' . $current_term->slug );
					if ( ! isset( $filters['attributes'] ) ) {
						$filters['attributes'] = array();
					}
					if ( ! isset( $filters['attributes'][ $current_term->taxonomy ] ) ) {
						$filters['attributes'][ $current_term->taxonomy ] = array( $current_term->slug );
					}
				} else {
					// error_log( '[MantiLoad] \is_product_taxonomy() detected NON-ATTRIBUTE taxonomy: ' . $current_term->taxonomy . ' - SKIPPING (already handled as category/tag)' );
				}
			}
		}

		// CRITICAL: Tell WordPress to preserve our sort order BEFORE we return posts
		// This prevents WordPress from re-sorting the posts array
		$query->set( 'orderby', 'post__in' );
		$query->set( 'order', '' );

		// Perform MantiLoad search (hidden products already filtered in Manticore)
		$results = $this->search( $search_query, array(
			'post_type' => $post_types,
			'limit' => $manticore_limit,
			'offset' => $manticore_offset,
			'orderby' => $orderby,
			'order' => $order,
			'filters' => $filters,
		) );

		// Set query properties
		$query->found_posts = $results['total'];
		$query->max_num_pages = ceil( $results['total'] / $posts_per_page );
		$query->set( 'mantiload_used', true );

		// Cache posts in query object so inject_posts_results can use them
		$query->set( 'mantiload_posts', $results['posts'] );

		// Return posts array (bypasses database query completely)
		return $results['posts'];
	}

	/**
	 * Main search function
	 *
	 * @param string $query Search query
	 * @param array $args Search arguments
	 * @return array
	 */
	public function search( $query, $args = array() ) {
		global $wpdb;

		// Normalize Persian/Arabic numerals to Latin for consistent search
		$query = Manticore_Client::normalize_numerals( $query );

		$defaults = array(
			'post_type' => array( 'post', 'page', 'product' ),
			'post_status' => 'publish',
			'limit' => 20,
			'offset' => 0,
			'orderby' => 'relevance',
			'order' => 'DESC',
			'filters' => array(),
		);

		$args = wp_parse_args( $args, $defaults );

		// Get index name from settings
		$index_name = \MantiLoad\MantiLoad::get_option( 'index_name', 'mantiload_fast_one' );

		// Get primary post type for filtering
		$primary_post_type = is_array( $args['post_type'] ) ? $args['post_type'][0] : $args['post_type'];

		// Expand query with synonyms for better results!
		if ( ! empty( $query ) ) {
			$synonyms_manager = new Synonyms();
			$expanded_query = $synonyms_manager->expand_query( $query );
		} else {
			$expanded_query = $query;
		}

		// Build query
		$builder = new Query_Builder( $index_name );
		$builder->search( $expanded_query )
				->post_type( $args['post_type'] )
				->post_status( $args['post_status'] )
				->limit( $args['limit'] )
				->offset( $args['offset'] );

		// For products, apply visibility filter based on context
		if ( $primary_post_type === 'product' ) {
			// Determine if this is a search query or catalog browsing
			$is_search_context = ! empty( $expanded_query );
			$context = $is_search_context ? 'search' : 'catalog';
			$builder->visibility_filter( $context );
		}

		// Apply WooCommerce sorting
		$this->apply_woocommerce_sorting( $builder, $args['orderby'], $args['order'] );

		// Apply filters
		if ( ! empty( $args['filters'] ) ) {
			$this->apply_filters( $builder, $args['filters'] );
		}

		// Time BOTH queries (data + count) for accurate reporting
		$start_time = microtime( true );

		// CRITICAL: Graceful fallback if Manticore is down
		try {
			// Check if Manticore is healthy before executing
			if ( ! $this->client->is_healthy() ) {
				// Fallback to WordPress default search
				return $this->fallback_wordpress_search( $query, $args );
			}

			// Check if stock priority is enabled
			$prioritize_in_stock = \MantiLoad\MantiLoad::get_option( 'prioritize_in_stock', true );
			$post_ids = array();
			$relevance_scores = array();
			$total = 0;

			if ( $prioritize_in_stock && $primary_post_type === 'product' ) {
				// Two-query strategy: ALL in-stock products before ANY out-of-stock across all pages

				// Build base query without stock filter to get WHERE clause
				$base_sql = $builder->build();
				$base_count_sql = $builder->build_count();

				// Extract WHERE clause from base query
				$where_match = array();
				if ( preg_match( '/WHERE\s+(.+?)\s+ORDER BY/is', $base_sql, $where_match ) ) {
					$where_clause = 'WHERE ' . $where_match[1];
				} elseif ( preg_match( '/WHERE\s+(.+?)\s+LIMIT/is', $base_sql, $where_match ) ) {
					$where_clause = 'WHERE ' . $where_match[1];
				} else {
					$where_clause = '';
				}

				// Extract ORDER BY clause
				$order_match = array();
				if ( preg_match( '/ORDER BY\s+(.+?)\s+LIMIT/is', $base_sql, $order_match ) ) {
					$order_clause = 'ORDER BY ' . $order_match[1];
				} else {
					$order_clause = 'ORDER BY post_date DESC';
				}

				// Extract SELECT clause (for relevance)
				$is_search_query = ! empty( $expanded_query );
				$select_clause = $is_search_query ? "SELECT *, WEIGHT() as relevance" : "SELECT id";

				// Count in-stock products
				$instock_where = $where_clause ? $where_clause . " AND stock_status = 'instock'" : "WHERE stock_status = 'instock'";
				$instock_count_sql = "SELECT COUNT(*) as total FROM {$index_name} {$instock_where} OPTION max_matches=100000";
				$instock_count_result = $this->client->query( $instock_count_sql );
				$instock_count = 0;

				if ( $instock_count_result ) {
					$count_row = $instock_count_result->fetch_assoc();
					$instock_count = (int) ( $count_row['total'] ?? 0 );
				}

				// Get total count (all products)
				$count_result = $this->client->query( $base_count_sql );
				if ( $count_result ) {
					$count_row = $count_result->fetch_assoc();
					$total = $count_row ? (int) $count_row['total'] : 0;
				}

				$offset = $args['offset'];
				$limit = $args['limit'];

				// Determine which products to fetch based on pagination
				if ( $offset < $instock_count ) {
					// Still in the in-stock range - fetch in-stock products
					$instock_sql = "{$select_clause} FROM {$index_name} {$instock_where} {$order_clause} LIMIT {$offset}, {$limit} OPTION max_matches=100000";
					if ( $is_search_query ) {
						$instock_sql = str_replace( 'OPTION', "OPTION ranker=proximity_bm25, field_weights=(title=10, sku=5, content=1),", $instock_sql );
					}
					$instock_result = $this->client->query( $instock_sql );

					if ( $instock_result ) {
						while ( $row = $instock_result->fetch_assoc() ) {
							$post_ids[] = (int) $row['id'];
							if ( isset( $row['relevance'] ) ) {
								$relevance_scores[ $row['id'] ] = (float) $row['relevance'];
							}
						}
					}

					// If we don't have enough in-stock products to fill the page, add out-of-stock
					if ( count( $post_ids ) < $limit ) {
						$outofstock_where = $where_clause ? $where_clause . " AND stock_status != 'instock'" : "WHERE stock_status != 'instock'";
						$outofstock_needed = $limit - count( $post_ids );
						$outofstock_sql = "{$select_clause} FROM {$index_name} {$outofstock_where} {$order_clause} LIMIT 0, {$outofstock_needed} OPTION max_matches=100000";
						if ( $is_search_query ) {
							$outofstock_sql = str_replace( 'OPTION', "OPTION ranker=proximity_bm25, field_weights=(title=10, sku=5, content=1),", $outofstock_sql );
						}
						$outofstock_result = $this->client->query( $outofstock_sql );

						if ( $outofstock_result ) {
							while ( $row = $outofstock_result->fetch_assoc() ) {
								$post_ids[] = (int) $row['id'];
								if ( isset( $row['relevance'] ) ) {
									$relevance_scores[ $row['id'] ] = (float) $row['relevance'];
								}
							}
						}
					}
				} else {
					// Past all in-stock products, show only out-of-stock
					$outofstock_offset = $offset - $instock_count;
					$outofstock_where = $where_clause ? $where_clause . " AND stock_status != 'instock'" : "WHERE stock_status != 'instock'";
					$outofstock_sql = "{$select_clause} FROM {$index_name} {$outofstock_where} {$order_clause} LIMIT {$outofstock_offset}, {$limit} OPTION max_matches=100000";
					if ( $is_search_query ) {
						$outofstock_sql = str_replace( 'OPTION', "OPTION ranker=proximity_bm25, field_weights=(title=10, sku=5, content=1),", $outofstock_sql );
					}
					$outofstock_result = $this->client->query( $outofstock_sql );

					if ( $outofstock_result ) {
						while ( $row = $outofstock_result->fetch_assoc() ) {
							$post_ids[] = (int) $row['id'];
							if ( isset( $row['relevance'] ) ) {
								$relevance_scores[ $row['id'] ] = (float) $row['relevance'];
							}
						}
					}
				}

			} else {
				// Normal query without stock priority
				$sql = $builder->build();
				$count_sql = $builder->build_count();

				$result = $this->client->query( $sql );
				$count_result = $this->client->query( $count_sql );

				// If queries failed, fallback to WordPress
				if ( ! $result || ! $count_result ) {
					return $this->fallback_wordpress_search( $query, $args );
				}

				if ( $count_result ) {
					$count_row = $count_result->fetch_assoc();
					$total = $count_row ? (int) $count_row['total'] : 0;
				}

				if ( $result ) {
					while ( $row = $result->fetch_assoc() ) {
						$post_ids[] = (int) $row['id'];
						if ( isset( $row['relevance'] ) ) {
							$relevance_scores[ $row['id'] ] = (float) $row['relevance'];
						}
					}
				}
			}

			$query_time = ( microtime( true ) - $start_time ) * 1000;

		} catch ( \Exception $e ) {
			// Log the error and fallback gracefully
			return $this->fallback_wordpress_search( $query, $args );
		}

		// Log the search
		$this->log_search( $query, count( $post_ids ), $query_time );

		// Get WordPress posts (ONLY the ones Manticore returned - already filtered!)
		$posts = array();
		if ( ! empty( $post_ids ) ) {
			// No need for tax_query filtering - hidden products are already excluded in Manticore
			$posts = \get_posts( array(
				'post__in' => $post_ids,
				'post_type' => 'any',
				'posts_per_page' => count( $post_ids ),
				'orderby' => 'post__in',
				'no_found_rows' => true,
				'update_post_meta_cache' => true, // Load meta immediately
				'update_post_term_cache' => true, // Load terms immediately
			) );

			// CRITICAL FIX: WordPress doesn't always preserve post__in order
			// Manually sort posts to match the Manticore result order
			if ( count( $posts ) > 1 ) {
				$ordered_posts = array();
				$posts_by_id = array();

				// Create lookup array
				foreach ( $posts as $post ) {
					$posts_by_id[ $post->ID ] = $post;
				}

				// Re-order based on Manticore's order
				foreach ( $post_ids as $post_id ) {
					if ( isset( $posts_by_id[ $post_id ] ) ) {
						$ordered_posts[] = $posts_by_id[ $post_id ];
					}
				}

				$posts = $ordered_posts;
			}
		}

		return array(
			'posts' => $posts,
			'post_ids' => $post_ids,
			'total' => $total,
			'query_time' => round($query_time, 3),
			'relevance' => $relevance_scores,
		);
	}

	/**
	 * Fallback to WordPress default search when Manticore is unavailable
	 *
	 * @param string $query Search query
	 * @param array  $args  Search arguments
	 * @return array Search results
	 */
	private function fallback_wordpress_search( $query, $args ) {
		global $wpdb;

		$start_time = microtime( true );

		// Build WordPress query args
		$wp_args = array(
			'post_type'      => $args['post_type'],
			'post_status'    => $args['post_status'],
			'posts_per_page' => $args['limit'],
			'offset'         => $args['offset'],
			's'              => $query, // WordPress search query
			'orderby'        => 'relevance', // WordPress default relevance
			'order'          => $args['order'],
		);

		// For WooCommerce products, add visibility and stock meta
		if ( in_array( 'product', $args['post_type'] ) ) {
			$wp_args['meta_query'] = array(
				'relation' => 'AND',
				array(
					'key'     => '_visibility',
					'value'   => array( 'visible', 'catalog', 'search' ),
					'compare' => 'IN',
				),
			);

			// Exclude hidden products
			$wp_args['tax_query'] = array(
				array(
					'taxonomy' => 'product_visibility',
					'field'    => 'name',
					'terms'    => 'exclude-from-search',
					'operator' => 'NOT IN',
				),
			);
		}

		// Execute WordPress query
		$wp_query = new \WP_Query( $wp_args );

		$query_time = ( microtime( true ) - $start_time ) * 1000;

		$posts = $wp_query->posts;
		$post_ids = wp_list_pluck( $posts, 'ID' );
		$total = $wp_query->found_posts;

		// Log the fallback
		$this->log_search( $query . ' [WordPress Fallback]', count( $post_ids ), $query_time );

		return array(
			'posts'      => $posts,
			'post_ids'   => $post_ids,
			'total'      => $total,
			'query_time' => $query_time,
			'relevance'  => array(), // WordPress doesn't provide relevance scores
			'fallback'   => true, // Mark as fallback
		);
	}

	/**
	 * Apply WooCommerce sorting to query builder
	 *
	 * @param Query_Builder $builder Query builder
	 * @param string $orderby Order by parameter
	 * @param string $order Order direction
	 */
	private function apply_woocommerce_sorting( $builder, $orderby, $order ) {
		if ( empty( $orderby ) ) {
			return;
		}

		// Handle array orderby (WooCommerce sometimes passes arrays like ['meta_value' => 'ASC', 'title' => 'ASC'])
		if ( is_array( $orderby ) ) {
			// Get the first key as the primary orderby
			$orderby = array_key_first( $orderby );
		}

		// Normalize order direction
		$order = strtoupper( $order );

		// Note: Stock priority is handled separately via two-query strategy in search() method
		// This ensures ALL in-stock products appear before ANY out-of-stock across all pages

		// Map WooCommerce orderby to Manticore fields
		switch ( $orderby ) {
			case 'price':
				// Use the order parameter to determine direction
				$builder->order_by( 'price', $order );
				break;

			case 'price-desc':
				$builder->order_by( 'price', 'DESC' );
				break;

			case 'price-asc':
				$builder->order_by( 'price', 'ASC' );
				break;

			case 'popularity':
				$builder->order_by( 'total_sales', 'DESC' );
				break;

			case 'rating':
				$builder->order_by( 'average_rating', 'DESC' );
				break;

			case 'date':
				$builder->order_by( 'post_date', $order );
				break;

			case 'title':
				$builder->order_by( 'post_title', $order );
				break;

			case 'menu_order':
				$builder->order_by( 'menu_order', 'ASC' );
				break;

			case 'relevance':
			default:
				// For search queries, add relevance ordering
				// This ensures stock priority + relevance works together
				$builder->order_by( 'WEIGHT()', 'DESC' );
				$builder->order_by( 'post_date', 'DESC' );
				break;
		}
	}

	/**
	 * Apply filters to query builder
	 *
	 * @param Query_Builder $builder Query builder
	 * @param array $filters Filters array
	 */
	private function apply_filters( $builder, $filters ) {
		if ( isset( $filters['categories'] ) ) {
			$builder->categories( $filters['categories'] );
		}

		// Single category filter (for category pages)
		if ( isset( $filters['category'] ) ) {
			$builder->categories( array( $filters['category'] ) );
		}

		if ( isset( $filters['tags'] ) ) {
			$builder->tags( $filters['tags'] );
		}

		// Single tag filter (for tag pages)
		if ( isset( $filters['tag'] ) ) {
			$builder->tags( array( $filters['tag'] ) );
		}

		// Handle both formats: ['price' => ['min' => X, 'max' => Y]] and ['min_price' => X, 'max_price' => Y]
		if ( isset( $filters['price'] ) && is_array( $filters['price'] ) ) {
			$builder->price_range(
				$filters['price']['min'] ?? null,
				$filters['price']['max'] ?? null
			);
		} elseif ( isset( $filters['min_price'] ) || isset( $filters['max_price'] ) ) {
			$builder->price_range(
				$filters['min_price'] ?? null,
				$filters['max_price'] ?? null
			);
		}

		if ( isset( $filters['stock_status'] ) ) {
			$builder->stock_status( $filters['stock_status'] );
		}

		if ( isset( $filters['on_sale'] ) ) {
			$builder->on_sale( $filters['on_sale'] );
		}

		if ( isset( $filters['featured'] ) ) {
			$builder->featured( $filters['featured'] );
		}

		if ( isset( $filters['min_rating'] ) ) {
			$builder->min_rating( $filters['min_rating'] );
		}

		if ( isset( $filters['attributes'] ) ) {
			$builder->attributes( $filters['attributes'] );
		}
	}

	/**
	 * Log search query
	 *
	 * @param string $query Search query
	 * @param int $results Result count
	 * @param float $time Query time
	 */
	private function log_search( $query, $results, $time ) {
		if ( ! \MantiLoad\MantiLoad::get_option( 'log_searches', true ) ) {
			return;
		}

		$logs = \get_option( 'mantiload_search_logs', array() );
		
		$logs[] = array(
			'query' => $query,
			'results' => $results,
			'time' => $time,
			'timestamp' => current_time( 'timestamp' ),
			'user_id' => get_current_user_id(),
		);

		// Keep only last 1000 searches
		if ( count( $logs ) > 1000 ) {
			$logs = array_slice( $logs, -1000 );
		}

		\update_option( 'mantiload_search_logs', $logs );
	}

	/**
	 * WooCommerce product search override
	 *
	 * @param \WP_Query $query Query object
	 * @param \WC_Query $wc_query WooCommerce query object
	 */
	public function woocommerce_search( $query, $wc_query ) {
		if ( ! $query->is_search() || is_admin() ) {
			return;
		}

		$query->set( 'mantiload_search', true );
	}

	/**
	 * Capture WooCommerce catalog orderby parameter from URL
	 *
	 * @param \WP_Query $query Query object
	 */
	public function capture_woocommerce_orderby( $query ) {
		if ( is_admin() || ! $query->is_main_query() ) {
			return;
		}

		// Only handle product archive pages
		if ( ! \is_shop() && ! \is_product_category() && ! \is_product_tag() && ! \is_product_taxonomy() && ! $query->is_search() ) {
			return;
		}

		// Get orderby from URL parameter if set
		if ( isset( $_GET['orderby'] ) ) {
			$orderby = sanitize_text_field( wp_unslash( $_GET['orderby'] ) );
			$query->set( 'orderby', $orderby );

			// Also set order direction if applicable
			if ( $orderby === 'price' || $orderby === 'price-desc' ) {
				$query->set( 'order', 'DESC' );
			} elseif ( $orderby === 'price-asc' ) {
				$query->set( 'order', 'ASC' );
			}
		} else {
			// Default ordering for product archives
			if ( \is_shop() || \is_product_category() || \is_product_tag() || \is_product_taxonomy() ) {
				// Use post_date DESC as default (newest products first)
				if ( ! $query->get( 'orderby' ) ) {
					$query->set( 'orderby', 'date' );
					$query->set( 'order', 'DESC' );
				}
			}
		}
	}

	/**
	 * Check if tax_query contains product taxonomies
	 *
	 * @param array $tax_query Tax query array
	 * @return bool
	 */
	private function has_product_taxonomy( $tax_query ) {
		if ( empty( $tax_query ) || ! is_array( $tax_query ) ) {
			return false;
		}

		$product_taxonomies = array( 'product_cat', 'product_tag' );

		// Add all product attribute taxonomies
		$attribute_taxonomies = \wc_get_attribute_taxonomies();
		foreach ( $attribute_taxonomies as $tax ) {
			$product_taxonomies[] = wc_attribute_taxonomy_name( $tax->attribute_name );
		}

		// Check each tax query clause
		foreach ( $tax_query as $clause ) {
			if ( is_array( $clause ) && isset( $clause['taxonomy'] ) ) {
				if ( in_array( $clause['taxonomy'], $product_taxonomies ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Get facet counts for MVA fields using ManticoreSearch FACET
	 *
	 * @param string $facet_field The MVA field name (e.g., 'pa_color_ids')
	 * @param array  $args Additional search args (query, filters, etc.)
	 * @return array Array of term_id => count
	 */
	public function get_facet_counts( $facet_field, $args = array() ) {
		$counts = array();

		try {
			// Get index name from settings
			$index_name = \MantiLoad\MantiLoad::get_option( 'index_name', 'mantiload_fast_one' );

			// Start with SELECT query for the main search
			// IMPORTANT: Only count published, visible products to match what users see
			// Note: Facets are typically used for catalog filtering, so we use catalog visibility rules
			$sql = "SELECT id FROM {$index_name} WHERE post_type='product' AND post_status='publish' AND visibility != 'search' AND visibility != 'hidden'";

			// Add search query if provided
			if ( ! empty( $args['query'] ) ) {
				// Expand query with synonyms
				$synonyms_manager = new Synonyms();
				$expanded_query = $synonyms_manager->expand_query( $args['query'] );
				$escaped_query = $this->client->escape( $expanded_query );
				// Use correct field name: title (not post_title)
				$sql .= " AND MATCH('@title {$escaped_query}')";
			}

			// Add filters if provided
			if ( ! empty( $args['filters'] ) ) {
				foreach ( $args['filters'] as $filter ) {
					if ( isset( $filter['field'] ) && isset( $filter['value'] ) ) {
						$field = $filter['field'];
						$value = is_array( $filter['value'] ) ? implode( ',', array_map( 'intval', $filter['value'] ) ) : intval( $filter['value'] );
						$sql .= " AND {$field} IN ({$value})";
					}
				}
			}

			$sql .= " LIMIT 1"; // We don't need results, just facets

			// Add FACET clause - ManticoreSearch will aggregate MVA values properly
			$sql .= " FACET {$facet_field} ORDER BY COUNT(*) DESC LIMIT 1000";

			// Execute query
			$result = $this->client->query( $sql );

			// Parse facet results - ManticoreSearch returns facets as a separate result set
			if ( $result ) {
				// Skip the main result set (we only need facets)
				$result->fetch_all();

				// Get mysqli connection to handle multiple result sets
				$connection = $this->client->get_connection();

				// Move to next result set (the FACET results)
				if ( $connection && $connection->more_results() ) {
					$connection->next_result();
					$facet_result = $connection->store_result();

					if ( $facet_result ) {
						// Fetch all facet rows
						while ( $row = $facet_result->fetch_assoc() ) {
							if ( isset( $row[ $facet_field ] ) && isset( $row['count(*)'] ) ) {
								$term_id = intval( $row[ $facet_field ] );
								$count = intval( $row['count(*)'] );

								if ( $term_id > 0 && $count > 0 ) {
									// MVA fields return individual term IDs automatically
									if ( ! isset( $counts[ $term_id ] ) ) {
										$counts[ $term_id ] = 0;
									}
									$counts[ $term_id ] += $count;
								}
							}
						}
						$facet_result->free();
					}
				}
			}

		} catch ( \Exception $e ) {
			// Silently fail - counts will be 0
			// error_log( '[MantiLoad] Facet count error: ' . $e->getMessage() );
		}

		return $counts;
	}
}
