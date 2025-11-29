<?php
/**
 * Query Builder Class
 *
 * @package MantiLoad
 */

namespace MantiLoad;

defined( 'ABSPATH' ) || exit;

/**
 * Query_Builder class
 *
 * Builds Manticore Search queries with proper escaping and syntax
 */
class Query_Builder {

	/**
	 * Index name
	 *
	 * @var string
	 */
	private $index;

	/**
	 * Search query
	 *
	 * @var string
	 */
	private $query = '';

	/**
	 * WHERE conditions
	 *
	 * @var array
	 */
	private $where = array();

	/**
	 * ORDER BY clauses
	 *
	 * @var array
	 */
	private $order_by = array();

	/**
	 * LIMIT value
	 *
	 * @var int
	 */
	private $limit = 20;

	/**
	 * OFFSET value
	 *
	 * @var int
	 */
	private $offset = 0;

	/**
	 * Field weights
	 *
	 * @var array
	 */
	private $field_weights = array();

	/**
	 * Constructor
	 *
	 * @param string $index Index name
	 */
	public function __construct( $index ) {
		$this->index = $index;
		$this->load_field_weights();
	}

	/**
	 * Load field weights from settings
	 */
	private function load_field_weights() {
		$fields = MantiLoad::get_option( 'search_fields', array() );
		foreach ( $fields as $field => $config ) {
			if ( isset( $config['enabled'] ) && $config['enabled'] && isset( $config['weight'] ) ) {
				$this->field_weights[ $field ] = (int) $config['weight'];
			}
		}
	}

	/**
	 * Set search query
	 *
	 * @param string $query Search query
	 * @return self
	 */
	public function search( $query ) {
		$this->query = $query;
		return $this;
	}

	/**
	 * Add WHERE condition
	 *
	 * @param string $condition Condition
	 * @return self
	 */
	public function where( $condition ) {
		$this->where[] = $condition;
		return $this;
	}

	/**
	 * Add post type filter
	 *
	 * @param string|array $post_types Post type(s)
	 * @return self
	 */
	public function post_type( $post_types ) {
		if ( is_array( $post_types ) ) {
			$escaped = array_map( function( $pt ) {
				return "'" . esc_sql( $pt ) . "'";
			}, $post_types );
			$this->where[] = 'post_type IN (' . implode( ', ', $escaped ) . ')';
		} else {
			$this->where[] = "post_type = '" . esc_sql( $post_types ) . "'";
		}
		return $this;
	}

	/**
	 * Add post status filter
	 *
	 * @param string|array $statuses Post status(es)
	 * @return self
	 */
	public function post_status( $statuses ) {
		if ( is_array( $statuses ) ) {
			$escaped = array_map( function( $status ) {
				return "'" . esc_sql( $status ) . "'";
			}, $statuses );
			$this->where[] = 'post_status IN (' . implode( ', ', $escaped ) . ')';
		} else {
			$this->where[] = "post_status = '" . esc_sql( $statuses ) . "'";
		}
		return $this;
	}

	/**
	 * Filter by category IDs
	 *
	 * @param array $category_ids Category IDs
	 * @return self
	 */
	public function categories( $category_ids ) {
		if ( ! empty( $category_ids ) ) {
			$ids = array_map( 'intval', $category_ids );
			// Use category_ids (actual field name in index)
			$this->where[] = 'category_ids IN (' . implode( ', ', $ids ) . ')';
		}
		return $this;
	}

	/**
	 * Filter by tag IDs
	 *
	 * @param array $tag_ids Tag IDs
	 * @return self
	 */
	public function tags( $tag_ids ) {
		if ( ! empty( $tag_ids ) ) {
			$ids = array_map( 'intval', $tag_ids );
			// Use tag_ids (actual field name in index)
			$this->where[] = 'tag_ids IN (' . implode( ', ', $ids ) . ')';
		}
		return $this;
	}

	/**
	 * Filter by price range (WooCommerce)
	 *
	 * @param float $min Minimum price
	 * @param float $max Maximum price
	 * @return self
	 */
	public function price_range( $min = null, $max = null ) {
		if ( $min !== null ) {
			$this->where[] = 'price >= ' . floatval( $min );
		}
		if ( $max !== null ) {
			$this->where[] = 'price <= ' . floatval( $max );
		}
		return $this;
	}

	/**
	 * Filter by stock status
	 *
	 * @param string $status Stock status
	 * @return self
	 */
	public function stock_status( $status ) {
		$this->where[] = "stock_status = '" . esc_sql( $status ) . "'";
		return $this;
	}

	/**
	 * Filter by on sale status
	 *
	 * @param bool $on_sale On sale status
	 * @return self
	 */
	public function on_sale( $on_sale = true ) {
		$this->where[] = 'on_sale = ' . ( $on_sale ? 1 : 0 );
		return $this;
	}

	/**
	 * Filter by featured status
	 *
	 * @param bool $featured Featured status
	 * @return self
	 */
	public function featured( $featured = true ) {
		$this->where[] = 'featured = ' . ( $featured ? 1 : 0 );
		return $this;
	}

	/**
	 * Exclude hidden products from catalog
	 *
	 * By default, excludes products with visibility = 'hidden'
	 * WooCommerce visibility values: 'visible', 'catalog', 'search', 'hidden'
	 *
	 * @param bool $exclude_hidden Whether to exclude hidden products (default: true)
	 * @return self
	 */
	public function exclude_hidden( $exclude_hidden = true ) {
		if ( $exclude_hidden ) {
			// Exclude products that are completely hidden from both catalog and search
			$this->where[] = "visibility != 'hidden'";
		}
		return $this;
	}

	/**
	 * Apply visibility filter based on context (search vs catalog)
	 *
	 * WooCommerce visibility values:
	 * - 'visible': Show in both search and catalog
	 * - 'catalog': Show in catalog only, NOT in search
	 * - 'search': Show in search only, NOT in catalog
	 * - 'hidden': Hidden from both
	 *
	 * @param string $context Either 'search' or 'catalog'
	 * @return self
	 */
	public function visibility_filter( $context = 'search' ) {
		if ( $context === 'search' ) {
			// For search: only show 'visible' (both) and '' (default/empty) products
			// Excludes 'catalog' (catalog-only), 'search' (shouldn't exist for search), and 'hidden'
			// Using IN() is more compatible with all Manticore versions than !=
			$this->where[] = "visibility IN ('visible', '')";
		} else {
			// For catalog: only show 'visible' (both) and 'catalog' (catalog-only) products
			// Excludes 'search' (search-only) and 'hidden'
			$this->where[] = "visibility IN ('visible', 'catalog', '')";
		}
		return $this;
	}

	/**
	 * Filter by rating
	 *
	 * @param float $min_rating Minimum rating
	 * @return self
	 */
	public function min_rating( $min_rating ) {
		$this->where[] = 'average_rating >= ' . floatval( $min_rating );
		return $this;
	}

	/**
	 * Filter by date range
	 *
	 * @param int $start Start timestamp
	 * @param int $end End timestamp
	 * @return self
	 */
	public function date_range( $start = null, $end = null ) {
		if ( $start !== null ) {
			$this->where[] = 'post_date >= ' . intval( $start );
		}
		if ( $end !== null ) {
			$this->where[] = 'post_date <= ' . intval( $end );
		}
		return $this;
	}

	/**
	 * Add custom attribute filter
	 *
	 * @param array $attribute_ids Attribute IDs
	 * @return self
	 */
	public function attributes( $attributes ) {
		global $wpdb;

		if ( empty( $attributes ) || ! is_array( $attributes ) ) {
			return $this;
		}

		// Handle attribute filtering - attributes is [taxonomy => [term_slugs]]
		// New optimized index has individual MVA fields (pa_color_ids, pa_size_ids, etc.)
		foreach ( $attributes as $taxonomy => $term_slugs ) {
			if ( empty( $term_slugs ) ) {
				continue;
			}

			// Get term IDs from slugs
			$placeholders = implode( ', ', array_fill( 0, count( $term_slugs ), '%s' ) );
			$term_ids = $wpdb->get_col( $wpdb->prepare(
				"SELECT term_id FROM {$wpdb->terms} WHERE slug IN ($placeholders)",
				...$term_slugs
			) );

			if ( ! empty( $term_ids ) ) {
				// Map taxonomy to MVA field name (pa_color => pa_color_ids)
				$mva_field = $taxonomy . '_ids';

				// Use IN clause for MVA field filtering
				$term_ids_escaped = array_map( 'intval', $term_ids );
				$this->where[] = $mva_field . ' IN (' . implode( ',', $term_ids_escaped ) . ')';
			}
		}

		return $this;
	}

	/**
	 * Order by field
	 *
	 * @param string $field Field name
	 * @param string $direction Direction (ASC or DESC)
	 * @return self
	 */
	public function order_by( $field, $direction = 'DESC' ) {
		$direction = strtoupper( $direction ) === 'ASC' ? 'ASC' : 'DESC';
		$this->order_by[] = $field . ' ' . $direction;
		return $this;
	}

	/**
	 * Set limit
	 *
	 * @param int $limit Limit
	 * @return self
	 */
	public function limit( $limit ) {
		$this->limit = (int) $limit;
		return $this;
	}

	/**
	 * Set offset
	 *
	 * @param int $offset Offset
	 * @return self
	 */
	public function offset( $offset ) {
		$this->offset = (int) $offset;
		return $this;
	}

	/**
	 * Build the search query with field weights
	 *
	 * @return string
	 */
	private function build_match_query() {
		if ( empty( $this->query ) ) {
			return '';
		}

		// Escape query for Manticore
		$escaped_query = $this->escape_query( $this->query );

		// Get excluded attributes from settings
		$excluded_attrs_setting = MantiLoad::get_option( 'excluded_attributes', '' );
		$excluded_attributes = array();
		if ( ! empty( $excluded_attrs_setting ) ) {
			// Parse comma-separated list and trim whitespace
			$excluded_attributes = array_map( 'trim', explode( ',', $excluded_attrs_setting ) );
			// Remove empty values
			$excluded_attributes = array_filter( $excluded_attributes );
		}

		// Build weighted field matches
		$matches = array();

		// Sort fields by weight (highest first)
		arsort( $this->field_weights );

		// FIX: Check if query contains synonym operators that need special handling
		$has_synonyms = ( strpos( $escaped_query, '(' ) !== false && strpos( $escaped_query, '|' ) !== false );

		// FIX: For multi-word queries WITHOUT synonyms, split into individual words for AND logic
		// This ensures "beaded halter" searches for @field beaded @field halter (both words required)
		$query_words = array();
		if ( ! $has_synonyms && strpos( $escaped_query, ' ' ) !== false ) {
			// Split by spaces and filter out empty strings
			$query_words = array_filter( explode( ' ', $escaped_query ) );
		}

		foreach ( $this->field_weights as $field => $weight ) {
			// Map setting field names to actual index field names
			$field_map = array(
				'post_title' => 'title',
				'post_content' => 'content',
				'post_excerpt' => 'excerpt',
			);

			// Use mapped field name if available, otherwise use as-is
			$index_field = isset( $field_map[ $field ] ) ? $field_map[ $field ] : $field;

			// Check if this is an excluded attribute field
			// Attribute fields in settings are like 'pa_color', 'pa_size'
			// We need to check both the original field name and without 'pa_' prefix
			$is_excluded = false;
			if ( ! empty( $excluded_attributes ) ) {
				// Check if field matches excluded attribute (e.g., 'pa_color' matches 'pa_color')
				if ( in_array( $field, $excluded_attributes, true ) ) {
					$is_excluded = true;
				}
				// Also check if field is like 'attributes' which contains all attribute text
				// This would be too broad, so we skip it
			}

			// Only add field to search if not excluded
			if ( ! $is_excluded ) {
				// FIX: For queries with synonyms, wrap in parentheses to ensure proper grouping
				if ( $has_synonyms ) {
					$matches[] = "@{$index_field} ({$escaped_query})";
				} elseif ( ! empty( $query_words ) ) {
					// Multi-word query: search each word separately in the same field (AND logic)
					$word_matches = array();
					foreach ( $query_words as $word ) {
						$word_matches[] = "@{$index_field} {$word}";
					}
					$matches[] = '(' . implode( ' ', $word_matches ) . ')';
				} else {
					// Single word query
					$matches[] = "@{$index_field} {$escaped_query}";
				}
			}
		}

		if ( empty( $matches ) ) {
			return $escaped_query;
		}

		return '(' . implode( ' | ', $matches ) . ')';
	}

	/**
	 * Escape query for Manticore Search
	 *
	 * UNICODE-SAFE: Preserves ALL languages (Arabic, Persian, Chinese, Japanese, Korean, etc.)
	 *
	 * @param string $query Query string
	 * @return string
	 */
	private function escape_query( $query ) {
		// SYNONYM SUPPORT: Preserve MantiCore operators (parentheses and pipe)
		// If query contains synonym operators like "(term1 | term2)", preserve them
		if ( strpos( $query, '(' ) !== false && strpos( $query, '|' ) !== false ) {
			// This is a synonym-expanded query - preserve operators
			return $query;
		}

		// UNICODE-SAFE escaping: Only escape Manticore special operators, preserve all Unicode text
		// Special operators that need escaping: ! @ ~ / \ < > = ( ) [ ] { }
		// But preserve: letters (ALL languages), numbers, spaces, hyphens, quotes
		$query = str_replace(
			array( '\\', '!', '@', '~', '/', '<', '>', '=', '{', '}', '[', ']' ),
			array( ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ' ),
			$query
		);

		// Trim and normalize whitespace
		$query = trim( preg_replace( '/\s+/', ' ', $query ) );

		return $query;
	}

	/**
	 * Build the complete SQL query
	 *
	 * @return string
	 */
	public function build() {
		// Optimize SELECT for non-search queries (10x faster!)
		// For search queries: need WEIGHT() for relevance scoring
		// For archives/filters: only need id, no scoring needed
		$is_search_query = ! empty( $this->query );

		if ( $is_search_query ) {
			$query = "SELECT *, WEIGHT() as relevance FROM {$this->index}";
		} else {
			// For non-search: only fetch id (much faster)
			$query = "SELECT id FROM {$this->index}";
		}

		// Add MATCH clause if there's a search query
		if ( $is_search_query ) {
			$match_query = $this->build_match_query();
			$query .= " WHERE MATCH('{$match_query}')";

			// Add additional WHERE conditions with AND
			if ( ! empty( $this->where ) ) {
				$query .= ' AND ' . implode( ' AND ', $this->where );
			}
		} elseif ( ! empty( $this->where ) ) {
			$query .= ' WHERE ' . implode( ' AND ', $this->where );
		}

		// Add ORDER BY
		if ( ! empty( $this->order_by ) ) {
			$order_clause = ' ORDER BY ' . implode( ', ', $this->order_by );
			$query .= $order_clause;
		} elseif ( $is_search_query ) {
			// Default to relevance ordering for search queries
			$query .= ' ORDER BY relevance DESC, post_date DESC';
		} else {
			// Default to date ordering for non-search queries
			$query .= ' ORDER BY post_date DESC';
		}

		// Add LIMIT and OFFSET
		$query .= ' LIMIT ' . $this->offset . ', ' . $this->limit;

		// Add query options - only for search queries
		// For non-search: skip expensive ranker and field_weights (5x faster!)
		if ( $is_search_query ) {
			// Using proximity_bm25 for best balance of speed and relevance quality
			// Field weights: title gets 10x priority, SKU 5x, content 1x
			// NOTE: Field names must match actual index schema (title, content, not post_title, post_content)
			$query .= " OPTION ranker=proximity_bm25, field_weights=(title=10, sku=5, content=1), max_matches=5000";
		} else {
			// For non-search: only need max_matches for pagination
			$query .= " OPTION max_matches=5000";
		}

		return $query;
	}

	/**
	 * Build count query
	 *
	 * @return string
	 */
	public function build_count() {
		$query = "SELECT COUNT(*) as total FROM {$this->index}";

		if ( ! empty( $this->query ) ) {
			$match_query = $this->build_match_query();
			$query .= " WHERE MATCH('{$match_query}')";

			if ( ! empty( $this->where ) ) {
				$query .= ' AND ' . implode( ' AND ', $this->where );
			}
		} elseif ( ! empty( $this->where ) ) {
			$query .= ' WHERE ' . implode( ' AND ', $this->where );
		}

		return $query;
	}

	/**
	 * Reset the query builder
	 *
	 * @return self
	 */
	public function reset() {
		$this->query = '';
		$this->where = array();
		$this->order_by = array();
		$this->limit = 20;
		$this->offset = 0;
		return $this;
	}
}
