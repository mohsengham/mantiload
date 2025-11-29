<?php
/**
 * MantiLoad Related Products
 *
 * Lightning-fast, intelligent related products using Manticore Search
 * 20-30x faster than WooCommerce default
 */

namespace MantiLoad;

defined( 'ABSPATH' ) || exit;

class Related_Products {

	/**
	 * Get related products using MantiLoad
	 *
	 * @param int $product_id Current product ID
	 * @param int $limit Number of related products to return
	 * @param string $algorithm Matching algorithm: 'combo', 'attributes_categories', 'price_categories'
	 * @return array Product IDs
	 */
	public function get_related( $product_id, $limit = 10, $algorithm = null ) {
		// Get algorithm from settings if not specified
		if ( ! $algorithm ) {
			$algorithm = \MantiLoad\MantiLoad::get_option( 'related_products_algorithm', 'combo' );
		}

		// Get product data
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return array();
		}

		// Execute algorithm
		switch ( $algorithm ) {
			case 'combo':
				return $this->get_related_combo( $product, $limit );

			case 'attributes_categories':
				return $this->get_related_attributes_categories( $product, $limit );

			case 'price_categories':
				return $this->get_related_price_categories( $product, $limit );

			default:
				return $this->get_related_combo( $product, $limit );
		}
	}

	/**
	 * Algorithm 1: COMBO (Attributes + Categories + Price)
	 * Most intelligent - considers everything
	 */
	private function get_related_combo( $product, $limit ) {
		$product_id = $product->get_id();
		$categories = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'ids' ) );
		$tags = wp_get_post_terms( $product_id, 'product_tag', array( 'fields' => 'ids' ) );
		$price = (float) $product->get_price();

		// Price range (±30%)
		$price_min = $price * 0.7;
		$price_max = $price * 1.3;

		// Get product attributes
		$attributes = $this->get_product_attribute_ids( $product );

		// Build scoring query
		$sql = "SELECT id, WEIGHT() * 10";

		// Category match bonus (100 points)
		if ( ! empty( $categories ) ) {
			$cats = implode( ',', array_map( 'intval', $categories ) );
			$sql .= " + IF(category_ids IN ({$cats}), 100, 0)";
		}

		// Tag match bonus (30 points)
		if ( ! empty( $tags ) ) {
			$tag_ids = implode( ',', array_map( 'intval', $tags ) );
			$sql .= " + IF(tag_ids IN ({$tag_ids}), 30, 0)";
		}

		// Attribute match bonuses - dynamically match ANY product attributes
		// Common attributes get higher weights (brand > color > size > others)
		$attribute_weights = array(
			'pa_brand_ids' => 75,  // Brand match is most important
			'pa_color_ids' => 50,  // Color match is important for fashion
			'pa_size_ids'  => 40,  // Size is moderately important
		);

		foreach ( $attributes as $attr_field => $attr_values ) {
			if ( empty( $attr_values ) || ! is_array( $attr_values ) ) {
				continue;
			}

			// Get weight for this attribute (default 30 for unknown attributes)
			$weight = isset( $attribute_weights[ $attr_field ] ) ? $attribute_weights[ $attr_field ] : 30;

			$attr_ids = implode( ',', array_map( 'intval', $attr_values ) );
			$sql .= " + IF({$attr_field} IN ({$attr_ids}), {$weight}, 0)";
		}

		// Price similarity bonus (closer price = higher score)
		$sql .= " + (100 - ABS(price - {$price}) / {$price} * 100)";

		$index_name = \MantiLoad\MantiLoad::get_option( 'index_name', 'mantiload_fast_one' );
		$sql .= " AS similarity FROM {$index_name}";

		// WHERE conditions
		$sql .= " WHERE id != {$product_id}";
		$sql .= " AND post_type = 'product'";
		$sql .= " AND post_status = 'publish'";

		// Stock and visibility filters
		$this->add_stock_visibility_filters( $sql );

		// Price range filter
		if ( $price > 0 ) {
			$sql .= " AND price BETWEEN {$price_min} AND {$price_max}";
		}

		// Require at least category OR attribute match
		$conditions = array();
		if ( ! empty( $categories ) ) {
			$conditions[] = "category_ids IN ({$cats})";
		}
		if ( ! empty( $attributes ) ) {
			foreach ( $attributes as $attr_key => $attr_values ) {
				if ( ! empty( $attr_values ) ) {
					$values = implode( ',', array_map( 'intval', $attr_values ) );
					$conditions[] = "{$attr_key} IN ({$values})";
				}
			}
		}
		if ( ! empty( $conditions ) ) {
			$sql .= " AND (" . implode( ' OR ', $conditions ) . ")";
		}

		$sql .= " ORDER BY similarity DESC";
		$sql .= " LIMIT " . (int) $limit;

		// Execute primary query
		$product_ids = $this->execute_query( $sql );

		// FALLBACK: If we don't have enough products, fill with category-only matches
		if ( count( $product_ids ) < $limit && ! empty( $categories ) ) {
			$remaining = $limit - count( $product_ids );

			// Build fallback query - category-only matches, exclude already found IDs
			$fallback_sql = "SELECT id FROM {$index_name}";
			$fallback_sql .= " WHERE id != {$product_id}";
			$fallback_sql .= " AND post_type = 'product'";
			$fallback_sql .= " AND post_status = 'publish'";
			$this->add_stock_visibility_filters( $fallback_sql );
			$fallback_sql .= " AND category_ids IN ({$cats})";

			// Exclude already found products
			if ( ! empty( $product_ids ) ) {
				$exclude_ids = implode( ',', array_map( 'intval', $product_ids ) );
				$fallback_sql .= " AND id NOT IN ({$exclude_ids})";
			}

			$fallback_sql .= " ORDER BY RAND()"; // Random selection from same category
			$fallback_sql .= " LIMIT " . (int) $remaining;

			$fallback_ids = $this->execute_query( $fallback_sql );

			// Merge primary results with fallback
			$product_ids = array_merge( $product_ids, $fallback_ids );
		}

		return $product_ids;
	}

	/**
	 * Algorithm 2: ATTRIBUTES & CATEGORIES
	 * Focuses on product characteristics, ignores price
	 */
	private function get_related_attributes_categories( $product, $limit ) {
		$product_id = $product->get_id();
		$categories = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'ids' ) );
		$attributes = $this->get_product_attribute_ids( $product );

		// Build scoring query
		$sql = "SELECT id, WEIGHT() * 10";

		// Category match bonus (150 points)
		if ( ! empty( $categories ) ) {
			$cats = implode( ',', array_map( 'intval', $categories ) );
			$sql .= " + IF(category_ids IN ({$cats}), 150, 0)";
		}

		// Attribute match bonuses - dynamically match ANY product attributes (higher weights than combo)
		// Common attributes get higher weights (brand > color > size > others)
		$attribute_weights = array(
			'pa_brand_ids' => 120,  // Brand match is most important
			'pa_color_ids' => 80,   // Color match is important
			'pa_size_ids'  => 60,   // Size is moderately important
		);

		foreach ( $attributes as $attr_field => $attr_values ) {
			if ( empty( $attr_values ) || ! is_array( $attr_values ) ) {
				continue;
			}

			// Get weight for this attribute (default 50 for unknown attributes)
			$weight = isset( $attribute_weights[ $attr_field ] ) ? $attribute_weights[ $attr_field ] : 50;

			$attr_ids = implode( ',', array_map( 'intval', $attr_values ) );
			$sql .= " + IF({$attr_field} IN ({$attr_ids}), {$weight}, 0)";
		}

		$index_name = \MantiLoad\MantiLoad::get_option( 'index_name', 'mantiload_fast_one' );
		$sql .= " AS similarity FROM {$index_name}";

		// WHERE conditions
		$sql .= " WHERE id != {$product_id}";
		$sql .= " AND post_type = 'product'";
		$sql .= " AND post_status = 'publish'";

		// Stock and visibility filters
		$this->add_stock_visibility_filters( $sql );

		// Require category OR attribute match
		$conditions = array();
		if ( ! empty( $categories ) ) {
			$conditions[] = "category_ids IN ({$cats})";
		}
		if ( ! empty( $attributes ) ) {
			foreach ( $attributes as $attr_key => $attr_values ) {
				if ( ! empty( $attr_values ) ) {
					$values = implode( ',', array_map( 'intval', $attr_values ) );
					$conditions[] = "{$attr_key} IN ({$values})";
				}
			}
		}
		if ( ! empty( $conditions ) ) {
			$sql .= " AND (" . implode( ' OR ', $conditions ) . ")";
		}

		$sql .= " ORDER BY similarity DESC";
		$sql .= " LIMIT " . (int) $limit;

		// Execute primary query
		$product_ids = $this->execute_query( $sql );

		// FALLBACK: If we don't have enough products, fill with category-only matches
		if ( count( $product_ids ) < $limit && ! empty( $categories ) ) {
			$remaining = $limit - count( $product_ids );

			$index_name = \MantiLoad\MantiLoad::get_option( 'index_name', 'mantiload_fast_one' );

			// Build fallback query - category-only matches, exclude already found IDs
			$fallback_sql = "SELECT id FROM {$index_name}";
			$fallback_sql .= " WHERE id != {$product_id}";
			$fallback_sql .= " AND post_type = 'product'";
			$fallback_sql .= " AND post_status = 'publish'";
			$this->add_stock_visibility_filters( $fallback_sql );
			$fallback_sql .= " AND category_ids IN ({$cats})";

			// Exclude already found products
			if ( ! empty( $product_ids ) ) {
				$exclude_ids = implode( ',', array_map( 'intval', $product_ids ) );
				$fallback_sql .= " AND id NOT IN ({$exclude_ids})";
			}

			$fallback_sql .= " ORDER BY RAND()"; // Random selection from same category
			$fallback_sql .= " LIMIT " . (int) $remaining;

			$fallback_ids = $this->execute_query( $fallback_sql );

			// Merge primary results with fallback
			$product_ids = array_merge( $product_ids, $fallback_ids );
		}

		return $product_ids;
	}

	/**
	 * Algorithm 3: PRICE & CATEGORIES
	 * Great for finding alternatives in same category at similar price
	 */
	private function get_related_price_categories( $product, $limit ) {
		$product_id = $product->get_id();
		$categories = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'ids' ) );
		$price = (float) $product->get_price();

		// Price range (±30%)
		$price_min = $price * 0.7;
		$price_max = $price * 1.3;

		// Build scoring query
		$sql = "SELECT id, WEIGHT() * 10";

		// Category match bonus (200 points - very important)
		if ( ! empty( $categories ) ) {
			$cats = implode( ',', array_map( 'intval', $categories ) );
			$sql .= " + IF(category_ids IN ({$cats}), 200, 0)";
		}

		// Price similarity bonus (very important)
		if ( $price > 0 ) {
			$sql .= " + (200 - ABS(price - {$price}) / {$price} * 200)";
		}

		$index_name = \MantiLoad\MantiLoad::get_option( 'index_name', 'mantiload_fast_one' );
		$sql .= " AS similarity FROM {$index_name}";

		// WHERE conditions
		$sql .= " WHERE id != {$product_id}";
		$sql .= " AND post_type = 'product'";
		$sql .= " AND post_status = 'publish'";

		// Stock and visibility filters
		$this->add_stock_visibility_filters( $sql );

		// Price range filter (strict)
		if ( $price > 0 ) {
			$sql .= " AND price BETWEEN {$price_min} AND {$price_max}";
		}

		// Require category match
		if ( ! empty( $categories ) ) {
			$sql .= " AND category_ids IN ({$cats})";
		}

		$sql .= " ORDER BY similarity DESC";
		$sql .= " LIMIT " . (int) $limit;

		// Execute primary query
		$product_ids = $this->execute_query( $sql );

		// FALLBACK: If we don't have enough products, fill with category-only matches (ignore price)
		if ( count( $product_ids ) < $limit && ! empty( $categories ) ) {
			$remaining = $limit - count( $product_ids );

			// Build fallback query - category-only matches without price restriction
			$fallback_sql = "SELECT id FROM {$index_name}";
			$fallback_sql .= " WHERE id != {$product_id}";
			$fallback_sql .= " AND post_type = 'product'";
			$fallback_sql .= " AND post_status = 'publish'";
			$fallback_sql .= " AND category_ids IN ({$cats})";

			// Exclude already found products
			if ( ! empty( $product_ids ) ) {
				$exclude_ids = implode( ',', array_map( 'intval', $product_ids ) );
				$fallback_sql .= " AND id NOT IN ({$exclude_ids})";
			}

			$fallback_sql .= " ORDER BY RAND()"; // Random selection from same category
			$fallback_sql .= " LIMIT " . (int) $remaining;

			$fallback_ids = $this->execute_query( $fallback_sql );

			// Merge primary results with fallback
			$product_ids = array_merge( $product_ids, $fallback_ids );
		}

		return $product_ids;
	}

	/**
	 * Execute Manticore query and return product IDs
	 */
	private function execute_query( $sql ) {
		try {
			$client = new Manticore_Client();

			// Check health
			if ( ! $client->is_healthy() ) {
				return array();
			}

			// Execute query
			$result = $client->query( $sql );

			if ( ! $result ) {
				return array();
			}

			// Extract IDs
			$product_ids = array();
			while ( $row = $result->fetch_assoc() ) {
				$product_ids[] = (int) $row['id'];
			}

			return $product_ids;

		} catch ( \Exception $e ) {
			return array();
		}
	}

	/**
	 * Add stock and visibility filters to SQL query
	 * Excludes out-of-stock and hidden products from related products
	 */
	private function add_stock_visibility_filters( &$sql ) {
		// Only show in-stock products
		$sql .= " AND stock_status = 'instock'";

		// Only show visible products (exclude 'hidden' and 'search')
		$sql .= " AND visibility IN ('visible', 'catalog')";
	}

	/**
	 * Get product attribute IDs (extracted from indexed data)
	 */
	private function get_product_attribute_ids( $product ) {
		$product_id = $product->get_id();
		$attributes = array();

		// Get all product attributes
		$product_attributes = $product->get_attributes();

		foreach ( $product_attributes as $attribute ) {
			if ( ! $attribute->is_taxonomy() ) {
				continue;
			}

			$taxonomy = $attribute->get_name();
			$terms = wp_get_post_terms( $product_id, $taxonomy, array( 'fields' => 'ids' ) );

			if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
				// Convert taxonomy name to indexed field name (replace hyphens with underscores)
				$field_name = str_replace( '-', '_', $taxonomy ) . '_ids';
				$attributes[ $field_name ] = $terms;
			}
		}

		return $attributes;
	}
}
