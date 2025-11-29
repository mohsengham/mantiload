<?php
/**
 * Query Integration - Auto-intercepts WordPress queries
 *
 * Based on ElasticPress pattern - uses posts_pre_query to bypass MySQL
 *
 * @package MantiLoad
 */

namespace MantiLoad;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Query Integration Class
 *
 * Automatically intercepts WooCommerce product queries and routes them to Manticore
 *
 * phpcs:disable WordPress.Security.NonceVerification.Recommended
 * This class processes frontend WooCommerce filter parameters ($_GET) for product filtering.
 * Nonce verification is not required for read-only product filtering operations.
 * All inputs are validated and sanitized before use.
 */
class Query_Integration {

	/**
	 * Whether interception is enabled
	 *
	 * @var bool
	 */
	private $enabled = false;

	/**
	 * Manticore client instance
	 *
	 * @var Manticore_Client
	 */
	private $client;


	/**
	 * Constructor
	 */
	public function __construct() {
		// Check if auto-interception is enabled
		$this->enabled = MantiLoad::get_option( 'enable_query_interception', false );

		if ( ! $this->enabled ) {
			return;
		}

		// Initialize Manticore client
		$this->client = new Manticore_Client();

		// Hook into WordPress query system
		\add_action( 'pre_get_posts', array( $this, 'maybe_integrate' ), 5 );
		\add_filter( 'posts_pre_query', array( $this, 'get_manticore_posts' ), 10, 2 );

		// Also hook into 'the_posts' to override any theme/plugin modifications
		\add_filter( 'the_posts', array( $this, 'ensure_manticore_posts' ), 999, 2 );

		
	}

	/**
	 * Ensure Manticore posts are not replaced by theme/plugins
	 *
	 * This runs late (priority 999) to override any theme modifications
	 *
	 * @param array     $posts Array of posts.
	 * @param \WP_Query $query WordPress query object.
	 * @return array
	 */
	public function ensure_manticore_posts( $posts, $query ) {
		// Only for queries we integrated with
		if ( ! $query->get( 'mantiload_integrate' ) ) {
			return $posts;
		}

		// If query has mantiload_posts stored, use those instead
		$mantiload_posts = $query->get( 'mantiload_posts' );
		if ( ! empty( $mantiload_posts ) ) {
			// Debug: Check if posts were replaced
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && $query->is_main_query() ) {
				if ( $posts !== $mantiload_posts ) {
					$current_ids = array_map( function( $p ) { return $p->ID; }, array_slice( $posts, 0, 3 ) );
					$correct_ids = array_map( function( $p ) { return $p->ID; }, array_slice( $mantiload_posts, 0, 3 ) );
// error_log( 'MantiLoad: Posts were replaced! Current: ' . implode( ',', $current_ids ) . ' | Correct: ' . implode( ',', $correct_ids ) );
				}
			}
			return $mantiload_posts;
		}

		return $posts;
	}

	/**
	 * Decide if we should integrate with this query
	 *
	 * Runs early in pre_get_posts to set integration flag
	 *
	 * @param \WP_Query $query WordPress query object.
	 */
	public function maybe_integrate( $query ) {
		// Skip if explicitly disabled (e.g., for AJAX filter requests)
		if ( $query->get( 'mantiload_disable_integration' ) ) {
			return;
		}

		// Skip if already marked
		if ( $query->get( 'mantiload_integrate' ) ) {
			return;
		}

		// Check if we should integrate
		if ( $this->should_integrate( $query ) ) {
			$query->set( 'mantiload_integrate', true );

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				$query_type = $this->get_query_type( $query );
			}
		}
	}

	/**
	 * Intercept query and return Manticore results
	 *
	 * This is where the magic happens - returning non-null bypasses MySQL!
	 *
	 * @param mixed     $posts Array of posts or null.
	 * @param \WP_Query $query WordPress query object.
	 * @return mixed Array of posts to bypass MySQL, or null to fall back.
	 */
	public function get_manticore_posts( $posts, $query ) {
		// Not marked for integration? Check if we should integrate now
		// (post__in might have been set by BeRocket after pre_get_posts)
		if ( ! $query->get( 'mantiload_integrate' ) ) {
			// Late check for large post__in arrays (BeRocket, AJAX Filters)
			$post_in = $query->get( 'post__in' );
			if ( ! empty( $post_in ) && is_array( $post_in ) && count( $post_in ) > 50 ) {
				// Verify it's a product query
				if ( $this->is_product_query( $query ) ) {
					$query->set( 'mantiload_integrate', true );
				}
			}
		}

		// Still not marked for integration? Skip
		if ( ! $query->get( 'mantiload_integrate' ) ) {
			return null;
		}

		// Allow custom opt-out filter
		if ( \apply_filters( 'mantiload_skip_query_integration', false, $query ) ) {
			
			return null;
		}

		// Check Manticore connection
		if ( ! $this->client->is_healthy() ) {
			
			return null;
		}

		try {
			// Build Manticore query from WP_Query
			$manticore_query = $this->build_manticore_query( $query );

			if ( ! $manticore_query ) {
				
				return null;
			}

			// Execute Manticore query
			$results = $this->execute_manticore_query( $manticore_query, $query );

			if ( ! $results ) {
				
				return null;
			}

			// Set query properties (required for pagination)
			$query->found_posts   = $results['total'];
			$query->max_num_pages = $results['total'] > 0 ? ceil( $results['total'] / $query->get( 'posts_per_page' ) ) : 0;

			// Store posts in query object so we can restore them if they get replaced
			$query->set( 'mantiload_posts', $results['posts'] );

			// Debug: Log that we're returning posts
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && ! empty( $results['posts'] ) ) {
				$post_ids = array_map( function( $post ) { return $post->ID; }, $results['posts'] );
				$is_main = $query->is_main_query() ? 'MAIN' : 'secondary';
// error_log( 'MantiLoad RETURN (' . $is_main . '): Returning ' . count( $results['posts'] ) . ' post objects: ' . implode( ', ', array_slice( $post_ids, 0, 5 ) ) . '...' );
			}

			// Return posts - WordPress will skip SQL query!
			return $results['posts'];

		} catch ( \Exception $e ) {
			
			return null; // Fall back on error
		}
	}

	/**
	 * Check if we should integrate with this query
	 *
	 * @param \WP_Query $query WordPress query object.
	 * @return bool
	 */
	private function should_integrate( $query ) {
		// Explicit opt-in via query var
		if ( $query->get( 'mantiload_force_integrate' ) ) {
			return true;
		}

		// Explicit opt-out via query var
		if ( $query->get( 'mantiload_skip_integrate' ) ) {
			return false;
		}

		// Skip admin (unless explicitly enabled or AJAX)
		if ( is_admin() && ! wp_doing_ajax() && ! \apply_filters( 'mantiload_integrate_admin', false ) ) {
			return false;
		}

		// Skip single product pages
		if ( $query->is_single() ) {
			return false;
		}

		// Skip preview pages
		if ( $query->is_preview() ) {
			return false;
		}

		// Check if it's a product query
		if ( ! $this->is_product_query( $query ) ) {
			return false;
		}

		// For AJAX requests (WoodMart Load More, AJAX Filters, etc.)
		// Allow secondary queries if they are product queries with significant IN clause
		if ( wp_doing_ajax() ) {
			// Check for large post__in arrays (Load More scenario)
			$post_in = $query->get( 'post__in' );
			if ( ! empty( $post_in ) && is_array( $post_in ) && count( $post_in ) > 50 ) {
				return true;
			}

			// Check for product taxonomy queries during AJAX
			$tax_query = $query->get( 'tax_query' );
			if ( ! empty( $tax_query ) ) {
				return true;
			}

			// Check for WoodMart/WooCommerce AJAX actions
			$ajax_action = isset( $_REQUEST['action'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['action'] ) ) : '';
			$allowed_ajax_actions = array(
				'woodmart_get_products_shortcode_ajax',
				'woodmart_shop_page_fragments',
				'woodmart_ajax_search',
				'woocommerce_get_refreshed_fragments',
			);

			if ( in_array( $ajax_action, $allowed_ajax_actions, true ) ) {
				return true;
			}
		}

		// For non-AJAX requests, check for main query OR large post__in arrays
		if ( ! wp_doing_ajax() ) {
			// Allow main query
			if ( $query->is_main_query() ) {
				return true;
			}

			// Also allow secondary queries with large post__in (BeRocket, AJAX Filters, etc.)
			$post_in = $query->get( 'post__in' );
			if ( ! empty( $post_in ) && is_array( $post_in ) && count( $post_in ) > 50 ) {
				return true;
			}

			// Otherwise skip secondary queries
			return false;
		}

		return true;
	}

	/**
	 * Check if this is a product query we should intercept
	 *
	 * @param \WP_Query $query WordPress query object.
	 * @return bool
	 */
	private function is_product_query( $query ) {
		// Direct product post type query
		$post_type = $query->get( 'post_type' );
		if ( 'product' === $post_type || ( is_array( $post_type ) && in_array( 'product', $post_type, true ) ) ) {
			return true;
		}

		// Product taxonomy queries
		if ( $query->is_tax( array( 'product_cat', 'product_tag' ) ) ) {
			return true;
		}

		// Check tax_query for product taxonomies
		$tax_query = $query->get( 'tax_query' );
		if ( ! empty( $tax_query ) && is_array( $tax_query ) ) {
			foreach ( $tax_query as $tax ) {
				if ( is_array( $tax ) && isset( $tax['taxonomy'] ) ) {
					if ( in_array( $tax['taxonomy'], array( 'product_cat', 'product_tag' ), true ) ) {
						return true;
					}
				}
			}
		}

		// WooCommerce conditional tags (only work in web context)
		if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
			// Shop page
			if ( function_exists( 'is_shop' ) && is_shop() ) {
				return true;
			}

			// Product category
			if ( function_exists( 'is_product_category' ) && is_product_category() ) {
				return true;
			}

			// Product tag
			if ( function_exists( 'is_product_tag' ) && is_product_tag() ) {
				return true;
			}


		}

		return false;
	}

	/**
	 * Build Manticore query from WP_Query
	 *
	 * @param \WP_Query $query WordPress query object.
	 * @return array|false Manticore query array or false on failure.
	 */
	private function build_manticore_query( $query ) {
		// Handle WooCommerce orderby parameter from URL
		// WooCommerce uses ?orderby=price but doesn't pass it through to WP_Query properly
		$orderby_param = '';
		if ( isset( $_GET['orderby'] ) ) {
			$orderby_param = \sanitize_text_field( wp_unslash( $_GET['orderby'] ) );
		}

		$manticore_query = array(
			'post_type'      => 'product',
			'posts_per_page' => $query->get( 'posts_per_page' ),
			'paged'          => max( 1, $query->get( 'paged' ) ),
			'orderby'        => ! empty( $orderby_param ) ? $orderby_param : $query->get( 'orderby' ),
			'order'          => $query->get( 'order' ),
		);

		// Add search query if present
		$search_query = $query->get( 's' );
		if ( ! empty( $search_query ) ) {
			$manticore_query['s'] = $search_query;
		}

		// Add taxonomy filters
		$tax_query = $query->get( 'tax_query' );
		if ( ! empty( $tax_query ) ) {
			$manticore_query['tax_query'] = $tax_query;
		}

		// Add category from query var
		// Use get_queried_object() first - it returns the actual category being viewed
		// This works better with multilingual plugins (WPML, Polylang, etc.)
		if ( is_product_category() ) {
			$queried_object = get_queried_object();
			if ( $queried_object && isset( $queried_object->term_id ) ) {
				$manticore_query['category_id'] = $queried_object->term_id;

				// Debug: Log category resolution
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
// error_log( 'MantiLoad Category (from queried_object): ID=' . $queried_object->term_id . ' (name=' . $queried_object->name . ', slug=' . $queried_object->slug . ')' );
				}
			}
		} else {
			// Fallback to query var method for non-standard scenarios
			$category_name = get_query_var( 'product_cat' );
			if ( ! empty( $category_name ) ) {
				$category = get_term_by( 'slug', $category_name, 'product_cat' );
				if ( $category ) {
					$manticore_query['category_id'] = $category->term_id;

					// Debug: Log category resolution
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
// error_log( 'MantiLoad Category (from query_var): slug=' . $category_name . ' => ID=' . $category->term_id . ' (name=' . $category->name . ')' );
					}
				}
			}
		}

		// Add tag from query var
		$tag_name = get_query_var( 'product_tag' );
		if ( ! empty( $tag_name ) ) {
			$tag = get_term_by( 'slug', $tag_name, 'product_tag' );
			if ( $tag ) {
				$manticore_query['tag_id'] = $tag->term_id;
			}
		}

		// Add meta query if present
		$meta_query = $query->get( 'meta_query' );
		if ( ! empty( $meta_query ) ) {
			$manticore_query['meta_query'] = $meta_query;
		}

		// Add post__in filter (for Load More, AJAX Filters, etc.)
		// This replaces the slow MySQL IN() clause with fast Manticore filtering
		$post_in = $query->get( 'post__in' );
		if ( ! empty( $post_in ) && is_array( $post_in ) ) {
			$manticore_query['post__in'] = array_map( 'intval', $post_in );
		}

		// Add post__not_in filter
		$post_not_in = $query->get( 'post__not_in' );
		if ( ! empty( $post_not_in ) && is_array( $post_not_in ) ) {
			$manticore_query['post__not_in'] = array_map( 'intval', $post_not_in );
		}

		// Add price filter (from WooCommerce price slider widget)
		if ( isset( $_GET['min_price'] ) && is_numeric( $_GET['min_price'] ) ) {
			$manticore_query['min_price'] = floatval( $_GET['min_price'] );
		}
		if ( isset( $_GET['max_price'] ) && is_numeric( $_GET['max_price'] ) ) {
			$manticore_query['max_price'] = floatval( $_GET['max_price'] );
		}

		// Add attribute filters (from WooCommerce layered nav widgets)
		// Format: ?filter_pa_color=red,blue&filter_brand=seiko (WoodMart format)
		$attribute_filters = array();

		foreach ( $_GET as $key => $value ) {
			if ( strpos( $key, 'filter_' ) === 0 && ! empty( $value ) ) {
				// Extract attribute name (e.g., filter_brand -> brand, filter_pa_color -> pa_color)
				$attribute = str_replace( 'filter_', '', $key );

				// WoodMart uses 'filter_brand' but taxonomy is 'pa_brand'
				// Try both formats: with and without 'pa_' prefix
				$attribute_variations = array( $attribute );
				if ( strpos( $attribute, 'pa_' ) !== 0 ) {
					$attribute_variations[] = 'pa_' . $attribute;
				}

				// Get term slugs (comma-separated)
				// Don't use sanitize_title - it mangles URL-encoded Persian/Arabic slugs
				$term_slugs = array_map( 'trim', explode( ',', $value ) );

				// Convert slugs to term IDs to determine correct taxonomy
				$resolved_taxonomy = null;
				$resolved_term_ids = array();

				foreach ( $term_slugs as $slug ) {
					foreach ( $attribute_variations as $tax_name ) {
						// Try getting term by slug directly (handles URL-encoded slugs)
						$term = get_term_by( 'slug', $slug, $tax_name );

						// If not found, try URL-decoding first (for Persian/Arabic slugs)
						if ( ! $term || is_wp_error( $term ) ) {
							$decoded_slug = urldecode( $slug );
							$term = get_term_by( 'slug', $decoded_slug, $tax_name );
						}

						if ( $term && ! is_wp_error( $term ) ) {
							$resolved_taxonomy = $tax_name;
							$resolved_term_ids[] = $term->term_id;
							break;
						}
					}
				}

				if ( $resolved_taxonomy && ! empty( $resolved_term_ids ) ) {
					// Get query type for this attribute (AND or OR)
					$query_type_param = 'query_type_' . $attribute;
					$query_type = 'or'; // Default to OR
					if ( isset( $_GET[ $query_type_param ] ) ) {
						$query_type_value = sanitize_text_field( wp_unslash( $_GET[ $query_type_param ] ) );
						// Whitelist validation for security
						if ( in_array( $query_type_value, array( 'and', 'or' ), true ) ) {
							$query_type = $query_type_value;
						}
					}

					$attribute_filters[ $resolved_taxonomy ] = array(
						'terms'      => $resolved_term_ids,
						'query_type' => $query_type,
					);

					// Debug: Log what we found
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
// error_log( 'MantiLoad Attribute Filter: ' . $resolved_taxonomy . ' => ' . implode( ',', $resolved_term_ids ) . ' (query_type=' . $query_type . ')' );
					}
				}
			}
		}

		if ( ! empty( $attribute_filters ) ) {
			$manticore_query['attribute_filters'] = $attribute_filters;


		}

		// Add stock status filter (from MantiLoad Stock Filter widget)
		if ( isset( $_GET['filter_stock'] ) && ! empty( $_GET['filter_stock'] ) ) {
			$stock_status = sanitize_text_field( wp_unslash( $_GET['filter_stock'] ) );

			// Validate stock status
			$valid_statuses = array( 'instock', 'outofstock', 'onbackorder' );
			if ( in_array( $stock_status, $valid_statuses, true ) ) {
				$manticore_query['stock_status'] = $stock_status;

				// Debug: Log stock filter
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
// error_log( 'MantiLoad Stock Filter: ' . $stock_status );
				}
			}
		}

		return \apply_filters( 'mantiload_query_integration_args', $manticore_query, $query );
	}

	/**
	 * Execute Manticore query
	 *
	 * @param array     $manticore_query Query args.
	 * @param \WP_Query $query           WordPress query object.
	 * @return array|false Results array or false on failure.
	 */
	private function execute_manticore_query( $manticore_query, $query ) {
		$start_time = microtime( true );

		// Build Manticore search query
		$index_name = MantiLoad::get_option( 'index_name', MantiLoad::get_default_index_name() );

		// Get pagination
		$page           = max( 1, $manticore_query['paged'] ?? 1 );
		$posts_per_page = $manticore_query['posts_per_page'] ?? 12;
		$offset         = ( $page - 1 ) * $posts_per_page;

		// Build WHERE clause for category/tag filtering
		$where_clauses = array();

		// CRITICAL: Filter by post type and status - only published products
		$where_clauses[] = "post_type = 'product'";
		$where_clauses[] = "post_status = 'publish'";

		// CRITICAL: WooCommerce visibility filter
		// For product archives/categories, show products that are visible in catalog
		// Exclude: hidden (exclude-from-catalog AND exclude-from-search) and search-only
		// Include empty visibility (fallback for products without visibility set - treat as visible)
		$where_clauses[] = "(visibility = 'visible' OR visibility = 'catalog' OR visibility = '')";

		// WooCommerce stock status filter
		// Check if "hide out of stock items" is enabled
		if ( 'yes' === \get_option( 'woocommerce_hide_out_of_stock_items', 'no' ) ) {
			$where_clauses[] = "stock_status = 'instock'";
		}

		// MantiLoad Stock Filter (from Stock Filter widget)
		// This takes precedence over the WooCommerce setting above
		if ( ! empty( $manticore_query['stock_status'] ) ) {
			// Remove the WooCommerce stock filter if it was added above
			$where_clauses = array_filter( $where_clauses, function( $clause ) {
				return strpos( $clause, "stock_status = 'instock'" ) === false;
			} );

			// Add the user-selected stock filter
			// Already validated in build_manticore_query(), so safe to use directly
			$where_clauses[] = "stock_status = '" . $manticore_query['stock_status'] . "'";
		}

		// Category filter
		if ( ! empty( $manticore_query['category_id'] ) ) {
			$where_clauses[] = 'category_ids = ' . (int) $manticore_query['category_id'];
		}

		// Tag filter
		if ( ! empty( $manticore_query['tag_id'] ) ) {
			$where_clauses[] = 'tag_ids = ' . (int) $manticore_query['tag_id'];
		}

		// Tax query support
		if ( ! empty( $manticore_query['tax_query'] ) ) {
			foreach ( $manticore_query['tax_query'] as $tax ) {
				if ( ! is_array( $tax ) || ! isset( $tax['taxonomy'] ) ) {
					continue;
				}

				if ( 'product_cat' === $tax['taxonomy'] && ! empty( $tax['terms'] ) ) {
					$term_ids = (array) $tax['terms'];
					$where_clauses[] = 'category_ids = ' . (int) $term_ids[0];
				} elseif ( 'product_tag' === $tax['taxonomy'] && ! empty( $tax['terms'] ) ) {
					$term_ids = (array) $tax['terms'];
					$where_clauses[] = 'tag_ids = ' . (int) $term_ids[0];
				}
			}
		}

		// Price filter (WooCommerce price slider widget)
		if ( ! empty( $manticore_query['min_price'] ) ) {
			$min_price = floatval( $manticore_query['min_price'] );
			$where_clauses[] = "price >= {$min_price}";
		}
		if ( ! empty( $manticore_query['max_price'] ) ) {
			$max_price = floatval( $manticore_query['max_price'] );
			$where_clauses[] = "price <= {$max_price}";
		}

		// post__in filter (for Load More, AJAX Filters with pre-filtered IDs)
		// This replaces the slow MySQL IN() clause with fast Manticore filtering
		if ( ! empty( $manticore_query['post__in'] ) && is_array( $manticore_query['post__in'] ) ) {
			$post_ids = array_filter( array_map( 'intval', $manticore_query['post__in'] ) );
			if ( ! empty( $post_ids ) ) {
				// For very large arrays, Manticore handles IN() much better than MySQL
				$ids_str = implode( ',', $post_ids );
				$where_clauses[] = "id IN ({$ids_str})";
			}
		}

		// post__not_in filter (exclude specific products)
		if ( ! empty( $manticore_query['post__not_in'] ) && is_array( $manticore_query['post__not_in'] ) ) {
			$exclude_ids = array_filter( array_map( 'intval', $manticore_query['post__not_in'] ) );
			if ( ! empty( $exclude_ids ) ) {
				$exclude_str = implode( ',', $exclude_ids );
				$where_clauses[] = "id NOT IN ({$exclude_str})";
			}
		}

		// Attribute filters (WooCommerce layered nav widgets)
		// Format: pa_brand = ['terms' => [5807, 2663], 'query_type' => 'or']
		if ( ! empty( $manticore_query['attribute_filters'] ) ) {
			foreach ( $manticore_query['attribute_filters'] as $taxonomy => $filter_data ) {
				// Support both old format (array of IDs) and new format (array with 'terms' and 'query_type')
				if ( isset( $filter_data['terms'] ) ) {
					$term_ids = $filter_data['terms'];
					$query_type = isset( $filter_data['query_type'] ) ? $filter_data['query_type'] : 'or';
				} else {
					// Backward compatibility: old format was just array of term IDs
					$term_ids = $filter_data;
					$query_type = 'or';
				}

				if ( ! empty( $term_ids ) ) {
					// Use the generic attribute_ids MVA field (contains all attribute term IDs)
					// All attributes are stored together in this single field
					$field_name = 'attribute_ids';

					if ( count( $term_ids ) > 1 ) {
						$ids_str = implode( ',', array_map( 'intval', $term_ids ) );

						if ( 'and' === $query_type ) {
							// AND logic: Product must have ALL selected attributes
							// In Manticore: attribute_ids ALL (id1, id2, id3)
							$where_clauses[] = "{$field_name} ALL ({$ids_str})";
						} else {
							// OR logic: Product must have ANY selected attribute
							// In Manticore: attribute_ids IN (id1, id2, id3)
							$where_clauses[] = "{$field_name} IN ({$ids_str})";
						}
					} else {
						// Single value: no difference between AND/OR
						$where_clauses[] = "{$field_name} = " . (int) $term_ids[0];
					}
				}
			}
		}

		// Build MATCH clause for full-text search
		$match_clause = '';
		if ( ! empty( $manticore_query['s'] ) ) {
			// Normalize Persian/Arabic numerals to Latin for consistent search
			$search_term = Manticore_Client::normalize_numerals( $manticore_query['s'] );

			// Get Manticore connection for escaping
			$host = MantiLoad::get_option( 'manticore_host', MANTILOAD_HOST );
			$port = (int) MantiLoad::get_option( 'manticore_port', MANTILOAD_PORT );
			$manticore = new \mysqli( $host, '', '', '', $port ); // phpcs:ignore WordPress.DB.RestrictedClasses.mysql__mysqli -- Direct mysqli required for Manticore Search connection

			if ( ! $manticore->connect_error ) {
				$safe_query = $manticore->real_escape_string( $search_term );
				$match_clause = " WHERE MATCH('$safe_query')";
				$manticore->close();
			}
		}

		// Build WHERE clause for filters
		$where = '';
		if ( ! empty( $where_clauses ) ) {
			if ( ! empty( $match_clause ) ) {
				// We have MATCH clause, add WHERE clauses with AND
				$where = $match_clause . ' AND ' . implode( ' AND ', $where_clauses );
			} else {
				// No MATCH clause, just WHERE clauses
				$where = 'WHERE ' . implode( ' AND ', $where_clauses );
			}
		} else {
			// No WHERE clauses, just MATCH clause if any
			$where = $match_clause;
		}

		// Debug logging
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && ! empty( $manticore_query['attribute_filters'] ) ) {
		}

		// Check if WooSort custom ordering should be used
		// WooSort takes priority over default ordering
		$use_woosort = false;
		if ( class_exists( '\WooSort\WooSort' ) && is_product_category() ) {
			$queried_object = get_queried_object();
			if ( $queried_object && isset( $queried_object->term_id ) ) {
				$category_id = $queried_object->term_id;
				$meta_key    = '_mantisort_pos_' . $category_id;

				// Check if any products in this category have WooSort positions
				global $wpdb;
				$has_positions = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s LIMIT 1",
						$meta_key
					)
				);

				if ( $has_positions > 0 ) {
					$use_woosort = true;
					
				}
			}
		}

		// Priority: 1) Search relevance, 2) WooSort, 3) User-specified order
		if ( ! empty( $manticore_query['s'] ) ) {
			// For search queries, always order by relevance (WEIGHT)
			$order_clause = 'ORDER BY WEIGHT() DESC';
		} elseif ( $use_woosort ) {
			// Fetch with default order, will be re-sorted by WooSort positions later
			$order_clause = 'ORDER BY post_date DESC';
		} else {
			$orderby = $manticore_query['orderby'] ?? 'date';
			$order   = strtoupper( $manticore_query['order'] ?? 'DESC' );

			// PHP 7.4+ compatible switch instead of match
			// Support WooCommerce orderby values: https://woocommerce.com/document/woocommerce-customizer/
			switch ( $orderby ) {
				case 'title':
					$order_clause = 'ORDER BY post_title ' . $order;
					break;
				case 'popularity':
					$order_clause = 'ORDER BY total_sales DESC';
					break;
				case 'rating':
					$order_clause = 'ORDER BY average_rating DESC';
					break;
				case 'price':
				case 'price-desc':
					$order_clause = 'ORDER BY price DESC';
					break;
				case 'price-asc':
					$order_clause = 'ORDER BY price ASC';
					break;
				case 'date':
					$order_clause = 'ORDER BY post_date ' . $order . ', id ' . $order;
					break;
				case 'id':
				case 'ID':
					$order_clause = 'ORDER BY id ' . $order;
					break;
				case 'menu_order':
				case 'menu_order title':
					$order_clause = 'ORDER BY menu_order ASC, post_title ASC';
					break;
				case 'relevance':
					// Relevance is handled above for search queries
					$order_clause = 'ORDER BY post_date DESC';
					break;
				case 'rand':
					$order_clause = 'ORDER BY RAND()';
					break;
				default:
					// Default to date DESC (most recent first)
					$order_clause = 'ORDER BY post_date DESC, id DESC';
					break;
			}
		}

		// Debug: Log the WHERE clause
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && ! empty( $manticore_query['attribute_filters'] ) ) {
// error_log( 'MantiLoad WHERE clause: ' . $where );
// error_log( 'MantiLoad Index: ' . $index_name );
		}

		// Check if stock priority is enabled
		$prioritize_in_stock = MantiLoad::get_option( 'prioritize_in_stock', false );

		// Execute query with stock priority
		// Stock priority works across ALL pages (in-stock products fill pages 1-N, out-of-stock fill remaining pages)
		if ( $prioritize_in_stock ) {
			// Strategy: Count in-stock products, then fetch the right slice

			// Count in-stock products for this query
			$instock_where = $where . ( strpos( $where, 'WHERE' ) !== false ? ' AND' : ' WHERE' ) . " stock_status='instock'";
			$instock_count_sql = "SELECT COUNT(*) as total FROM {$index_name} {$instock_where} OPTION max_matches=100000";
			$instock_count_result = $this->client->query( $instock_count_sql );
			$instock_count = 0;

			if ( $instock_count_result ) {
				$count_row = $instock_count_result->fetch_assoc();
				$instock_count = (int) ( $count_row['total'] ?? 0 );
			}

			$post_ids = array();

			// Determine which products to show based on pagination
			if ( $offset < $instock_count ) {
				// We're still in the in-stock range - fetch in-stock products
				$instock_sql = "SELECT id FROM {$index_name} {$instock_where} {$order_clause} LIMIT {$offset}, {$posts_per_page} OPTION max_matches=100000";
				$instock_result = $this->client->query( $instock_sql );

				if ( $instock_result ) {
					while ( $row = $instock_result->fetch_assoc() ) {
						$post_ids[] = (int) $row['id'];
					}
				}

				// If we don't have enough in-stock products to fill the page, add out-of-stock
				if ( count( $post_ids ) < $posts_per_page ) {
					$outofstock_where = $where . ( strpos( $where, 'WHERE' ) !== false ? ' AND' : ' WHERE' ) . " stock_status='outofstock'";
					$outofstock_needed = $posts_per_page - count( $post_ids );
					$outofstock_sql = "SELECT id FROM {$index_name} {$outofstock_where} {$order_clause} LIMIT {$outofstock_needed} OPTION max_matches=100000";
					$outofstock_result = $this->client->query( $outofstock_sql );

					if ( $outofstock_result ) {
						while ( $row = $outofstock_result->fetch_assoc() ) {
							$post_ids[] = (int) $row['id'];
						}
					}
				}
			} else {
				// We're past all in-stock products, show only out-of-stock
				$outofstock_offset = $offset - $instock_count;
				$outofstock_where = $where . ( strpos( $where, 'WHERE' ) !== false ? ' AND' : ' WHERE' ) . " stock_status='outofstock'";
				$outofstock_sql = "SELECT id FROM {$index_name} {$outofstock_where} {$order_clause} LIMIT {$outofstock_offset}, {$posts_per_page} OPTION max_matches=100000";
				$outofstock_result = $this->client->query( $outofstock_sql );

				if ( $outofstock_result ) {
					while ( $row = $outofstock_result->fetch_assoc() ) {
						$post_ids[] = (int) $row['id'];
					}
				}
			}

			
		} else {
			// Normal query without stock priority
			$sql = "SELECT id FROM {$index_name} {$where} {$order_clause} LIMIT {$offset}, {$posts_per_page} OPTION max_matches=100000";
			$result = $this->client->query( $sql );

			if ( ! $result ) {
				
				return false;
			}

			$post_ids = array();
			while ( $row = $result->fetch_assoc() ) {
				$post_ids[] = (int) $row['id'];
			}
		}

		// Apply WooSort custom ordering if plugin is active
		// WooSort saves custom positions as postmeta: _mantisort_pos_{category_id}
		if ( class_exists( '\WooSort\WooSort' ) && ! empty( $post_ids ) ) {
			// Check if we're on a category page
			if ( is_product_category() ) {
				$queried_object = get_queried_object();
				if ( $queried_object && isset( $queried_object->term_id ) ) {
					$category_id = $queried_object->term_id;
					$meta_key    = '_mantisort_pos_' . $category_id;

					// Get WooSort positions for these products
					global $wpdb;
					$ids_string = implode( ',', array_map( 'intval', $post_ids ) );

					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $ids_string contains sanitized integers via array_map(intval)
					$positions = $wpdb->get_results(
						$wpdb->prepare(
							"SELECT post_id, meta_value
							FROM {$wpdb->postmeta}
							WHERE meta_key = %s
							AND post_id IN ({$ids_string})",
							$meta_key
						),
						OBJECT_K
					);

					// Sort product IDs by WooSort position
					// Products with positions come first, sorted by position
					// Products without positions come last, keep original order
					usort( $post_ids, function( $a, $b ) use ( $positions ) {
						$pos_a = isset( $positions[ $a ] ) ? (int) $positions[ $a ]->meta_value : PHP_INT_MAX;
						$pos_b = isset( $positions[ $b ] ) ? (int) $positions[ $b ]->meta_value : PHP_INT_MAX;
						return $pos_a - $pos_b;
					} );

					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						$sorted_count = count( $positions );
					}
				}
			}
		}

		// Apply gbs-custom-category-sorting custom ordering (ACF repeater field)
		// Saves positions as: category_sort_orders_{index}_category and category_sort_orders_{index}_sort_order
		if ( function_exists( 'custom_woocommerce_product_query' ) && ! empty( $post_ids ) ) {
			// Check if we're on a category page
			if ( is_product_category() ) {
				$queried_object = get_queried_object();
				if ( $queried_object && isset( $queried_object->term_id ) ) {
					$category_id = $queried_object->term_id;

					// Get custom sort positions for this category
					global $wpdb;
					$ids_string = implode( ',', array_map( 'intval', $post_ids ) );

					// Query for products that have this category's sort order
					// Format: category_sort_orders_{n}_category = {category_id}
					//         category_sort_orders_{n}_sort_order = {position}
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $ids_string contains sanitized integers via array_map(intval)
					$positions = $wpdb->get_results(
						$wpdb->prepare(
							"SELECT pm_sort.post_id, pm_sort.meta_value as sort_order
							FROM {$wpdb->postmeta} pm_cat
							INNER JOIN {$wpdb->postmeta} pm_sort ON (
								pm_cat.post_id = pm_sort.post_id
								AND pm_sort.meta_key = REPLACE(pm_cat.meta_key, '_category', '_sort_order')
							)
							WHERE pm_cat.post_id IN ({$ids_string})
							AND pm_cat.meta_key LIKE CONCAT( %s, %s, %s )
							AND pm_cat.meta_value = %d",
							'category_sort_orders_', '%', '_category', $category_id
						),
						OBJECT_K
					);

					if ( ! empty( $positions ) ) {
						// Sort product IDs by custom position
						// Products with positions come first, sorted by position
						// Products without positions come last, keep Manticore order
						usort( $post_ids, function( $a, $b ) use ( $positions ) {
							$pos_a = isset( $positions[ $a ] ) ? (int) $positions[ $a ]->sort_order : PHP_INT_MAX;
							$pos_b = isset( $positions[ $b ] ) ? (int) $positions[ $b ]->sort_order : PHP_INT_MAX;
							return $pos_a - $pos_b;
						} );

						if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
							$sorted_count = count( $positions );
						}
					}
				}
			}
		}

		// Get total count
		$count_sql    = "SELECT COUNT(*) as total FROM {$index_name} {$where} OPTION max_matches=100000";
		$count_result = $this->client->query( $count_sql );
		$total        = 0;

		if ( $count_result ) {
			$count_row = $count_result->fetch_assoc();
			$total     = (int) ( $count_row['total'] ?? 0 );
		}

		$execution_time = microtime( true ) - $start_time;

		// Convert product IDs to post objects
		$posts = array();
		if ( ! empty( $post_ids ) ) {
			foreach ( $post_ids as $post_id ) {
				$post = \get_post( $post_id );
				if ( $post ) {
					$posts[] = $post;
				}
			}
		}

		// Debug: Log the product IDs being returned
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && ! empty( $post_ids ) ) {
			$is_main = $query->is_main_query() ? 'MAIN' : 'secondary';
			$has_filters = ! empty( $manticore_query['attribute_filters'] );
			$is_ajax = wp_doing_ajax() ? 'AJAX' : 'normal';
			$ajax_action = isset( $_REQUEST['action'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['action'] ) ) : 'none';

// error_log( 'MantiLoad (' . $is_main . ', filters=' . ( $has_filters ? 'YES' : 'NO' ) . ', ' . $is_ajax . ', action=' . $ajax_action . '): Returning ' . count( $post_ids ) . ' of ' . $total . ' products: ' . implode( ', ', array_slice( $post_ids, 0, 10 ) ) . ( count( $post_ids ) > 10 ? '...' : '' ) );
		}

		return array(
			'posts' => $posts,
			'total' => $total,
			'time'  => $execution_time,
		);
	}

	/**
	 * Get ORDER BY clause based on orderby value
	 *
	 * @param string $orderby Orderby value (date_desc, price_asc, etc.)
	 * @param string $order   Order direction (ASC/DESC) - only used for legacy 'date' orderby
	 * @return string ORDER BY clause
	 */
	private function get_order_clause( $orderby, $order = 'DESC' ) {
		// Check if stock priority is enabled
		$prioritize_in_stock = MantiLoad::get_option( 'prioritize_in_stock', false );

		// Build stock priority clause if enabled
		// This pushes out-of-stock items to the end regardless of sort order
		$stock_clause = '';
		if ( $prioritize_in_stock ) {
			$stock_clause = "CASE WHEN stock_status = 'instock' THEN 0 ELSE 1 END ASC, ";
		}

		$order = strtoupper( $order );

		// Support WooCommerce orderby values: https://woocommerce.com/document/woocommerce-customizer/
		switch ( $orderby ) {
			case 'title':
				return "ORDER BY {$stock_clause}post_title {$order}";
			case 'popularity':
				return "ORDER BY {$stock_clause}total_sales DESC";
			case 'rating':
				return "ORDER BY {$stock_clause}average_rating DESC";
			case 'price':
			case 'price-desc':
			case 'price_desc':
				return "ORDER BY {$stock_clause}price DESC";
			case 'price-asc':
			case 'price_asc':
				return "ORDER BY {$stock_clause}price ASC";
			case 'date_desc':
				return "ORDER BY {$stock_clause}post_date DESC, id DESC";
			case 'date_asc':
				return "ORDER BY {$stock_clause}post_date ASC, id ASC";
			case 'date':
				return "ORDER BY {$stock_clause}post_date {$order}, id {$order}";
			case 'id':
			case 'ID':
				return "ORDER BY {$stock_clause}id {$order}";
			case 'menu_order':
			case 'menu_order title':
				return "ORDER BY {$stock_clause}menu_order ASC, post_title ASC";
			case 'relevance':
				// Relevance only makes sense for search queries
				return "ORDER BY {$stock_clause}post_date DESC";
			case 'rand':
				return "ORDER BY {$stock_clause}RAND()";
			default:
				// Default to date DESC (most recent first)
				return "ORDER BY {$stock_clause}post_date DESC, id DESC";
		}
	}

	/**
	 * Get human-readable query type for logging
	 *
	 * @param \WP_Query $query WordPress query object.
	 * @return string
	 */
	private function get_query_type( $query ) {
		if ( function_exists( 'is_shop' ) && is_shop() ) {
			return 'Shop Page';
		}

		if ( function_exists( 'is_product_category' ) && is_product_category() ) {
			$category = get_queried_object();
			return 'Product Category: ' . ( $category->name ?? 'Unknown' );
		}

		if ( function_exists( 'is_product_tag' ) && is_product_tag() ) {
			$tag = get_queried_object();
			return 'Product Tag: ' . ( $tag->name ?? 'Unknown' );
		}

		if ( $query->is_search() ) {
			return 'Product Search: ' . $query->get( 's' );
		}

		return 'Product Archive';
	}
}
