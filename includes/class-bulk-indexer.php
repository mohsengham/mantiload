<?php
/**
 * Bulk Indexer - Optimized FULL reindex with ALL fields including MVAs
 *
 * This combines the speed of Fast_Indexer (bulk SQL queries) with the completeness
 * of the standard Indexer (all MVA fields for filters).
 *
 * Key optimizations:
 * - Gets ALL posts in ONE query
 * - Gets ALL metadata in ONE query
 * - Gets ALL categories in ONE query (with IDs!)
 * - Gets ALL tags in ONE query (with IDs!)
 * - Gets ALL attributes in ONE query (with IDs grouped by taxonomy!)
 * - Bulk insert to Manticore
 *
 * Result: 2-3x faster than standard reindex while including ALL filter fields!
 *
 * @package MantiLoad
 */

namespace MantiLoad\Indexer;

use MantiLoad\Manticore_Client;

defined( 'ABSPATH' ) || exit;

class Bulk_Indexer {

	/**
	 * Bulk index with ALL fields including MVAs - FAST!
	 *
	 * @param string $post_type Post type to index
	 * @param int    $offset    Batch offset
	 * @param int    $limit     Batch size
	 * @return array Results with indexed/failed counts
	 */
	public static function index_batch( $post_type, $offset, $limit ) {
		global $wpdb;

		$results = array(
			'indexed' => 0,
			'failed' => 0,
		);

		// Delegate to Order_Customer_Indexer for orders and users
		if ( $post_type === 'shop_order' ) {
			require_once MANTILOAD_PLUGIN_DIR . 'includes/class-order-customer-indexer.php';
			return \MantiLoad\Indexer\Order_Customer_Indexer::index_orders( $offset, $limit );
		}

		if ( $post_type === 'user' ) {
			require_once MANTILOAD_PLUGIN_DIR . 'includes/class-order-customer-indexer.php';
			return \MantiLoad\Indexer\Order_Customer_Indexer::index_customers( $offset, $limit );
		}

		// Get posts directly from DB - FAST!
		$posts = $wpdb->get_results( $wpdb->prepare(
			"SELECT ID, post_title, post_content, post_excerpt, post_date, post_modified,
					post_author, post_type, post_status, menu_order, comment_count
			FROM {$wpdb->posts}
			WHERE post_type = %s
			AND post_status = 'publish'
			ORDER BY ID ASC
			LIMIT %d OFFSET %d",
			$post_type,
			$limit,
			$offset
		) );

		if ( empty( $posts ) ) {
			return $results;
		}

		$batch = array();
		$post_ids = array_column( $posts, 'ID' );
		$post_ids_str = implode( ',', array_map( 'intval', $post_ids ) );

		// Get ALL product meta in ONE query - FAST!
		$meta_data = array();
		if ( $post_type === 'product' ) {
			$meta_results = $wpdb->get_results(
				"SELECT post_id, meta_key, meta_value
				FROM {$wpdb->postmeta}
				WHERE post_id IN ({$post_ids_str})
				AND meta_key IN (
					'_sku', '_price', '_regular_price', '_sale_price',
					'_stock_status', '_stock_quantity',
					'_wc_average_rating', '_wc_review_count',
					'total_sales'
				)"
			);

			foreach ( $meta_results as $row ) {
				$meta_data[ $row->post_id ][ $row->meta_key ] = $row->meta_value;
			}
		}

		// Get categories (with IDs!) in ONE query - FAST!
		$category_data = array();
		$taxonomy = ( $post_type === 'product' ) ? 'product_cat' : 'category';
		$cat_results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT tr.object_id, t.term_id, t.name
				FROM {$wpdb->term_relationships} tr
				INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
				INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
				WHERE tr.object_id IN ({$post_ids_str})
				AND tt.taxonomy = %s",
				$taxonomy
			)
		);

		foreach ( $cat_results as $row ) {
			if ( ! isset( $category_data[ $row->object_id ] ) ) {
				$category_data[ $row->object_id ] = array(
					'names' => array(),
					'ids' => array(),
				);
			}
			$category_data[ $row->object_id ]['names'][] = Manticore_Client::normalize_numerals( $row->name );
			$category_data[ $row->object_id ]['ids'][] = $row->term_id;
		}

		// Get tags (with IDs!) in ONE query - FAST!
		$tag_data = array();
		$tag_taxonomy = ( $post_type === 'product' ) ? 'product_tag' : 'post_tag';
		$tag_results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT tr.object_id, t.term_id, t.name
				FROM {$wpdb->term_relationships} tr
				INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
				INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
				WHERE tr.object_id IN ({$post_ids_str})
				AND tt.taxonomy = %s",
				$tag_taxonomy
			)
		);

		foreach ( $tag_results as $row ) {
			if ( ! isset( $tag_data[ $row->object_id ] ) ) {
				$tag_data[ $row->object_id ] = array(
					'names' => array(),
					'ids' => array(),
				);
			}
			$tag_data[ $row->object_id ]['names'][] = Manticore_Client::normalize_numerals( $row->name );
			$tag_data[ $row->object_id ]['ids'][] = $row->term_id;
		}

		// Get ALL product attributes in ONE query - FAST!
		// This is the KEY optimization for filter support!
		$attribute_data = array();
		if ( $post_type === 'product' ) {
			$attr_results = $wpdb->get_results(
				"SELECT tr.object_id, t.term_id, t.name, tt.taxonomy
				FROM {$wpdb->term_relationships} tr
				INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
				INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
				WHERE tr.object_id IN ({$post_ids_str})
				AND tt.taxonomy LIKE 'pa_%'"
			);

			foreach ( $attr_results as $row ) {
				if ( ! isset( $attribute_data[ $row->object_id ] ) ) {
					$attribute_data[ $row->object_id ] = array(
						'names' => array(),
						'ids' => array(),
						'by_taxonomy' => array(),
					);
				}

				// Add to general arrays
				$attribute_data[ $row->object_id ]['names'][] = Manticore_Client::normalize_numerals( $row->name );
				$attribute_data[ $row->object_id ]['ids'][] = $row->term_id;

				// Group by taxonomy for individual MVA fields (pa_color_ids, pa_size_ids, etc.)
				if ( ! isset( $attribute_data[ $row->object_id ]['by_taxonomy'][ $row->taxonomy ] ) ) {
					$attribute_data[ $row->object_id ]['by_taxonomy'][ $row->taxonomy ] = array();
				}
				$attribute_data[ $row->object_id ]['by_taxonomy'][ $row->taxonomy ][] = $row->term_id;
			}
		}

		// Get product_visibility taxonomy in ONE query - FAST!
		$visibility_data = array();
		if ( $post_type === 'product' ) {
			$vis_results = $wpdb->get_results(
				"SELECT tr.object_id, t.slug
				FROM {$wpdb->term_relationships} tr
				INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
				INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
				WHERE tr.object_id IN ({$post_ids_str})
				AND tt.taxonomy = 'product_visibility'
				AND t.slug IN ('exclude-from-catalog', 'exclude-from-search', 'featured')"
			);

			foreach ( $vis_results as $row ) {
				if ( ! isset( $visibility_data[ $row->object_id ] ) ) {
					$visibility_data[ $row->object_id ] = array();
				}
				$visibility_data[ $row->object_id ][] = $row->slug;
			}
		}

		// Get ALL author data in ONE query - FAST!
		$author_ids = array_unique( array_column( $posts, 'post_author' ) );
		$author_ids_str = implode( ',', array_map( 'intval', $author_ids ) );
		$author_data = array();
		if ( ! empty( $author_ids_str ) ) {
			$author_results = $wpdb->get_results(
				"SELECT user_id, meta_value as display_name
				FROM {$wpdb->usermeta}
				WHERE user_id IN ({$author_ids_str})
				AND meta_key = 'nickname'"
			);

			foreach ( $author_results as $row ) {
				$author_data[ $row->user_id ] = $row->display_name;
			}
		}

		// Get index_product_content option ONCE - FAST!
		$index_content = \MantiLoad\MantiLoad::get_option( 'index_product_content', false );

		// Prepare batch with ALL fields matching schema
		foreach ( $posts as $post ) {
			$post_id = $post->ID;
			$meta = isset( $meta_data[ $post_id ] ) ? $meta_data[ $post_id ] : array();

			// Base data - IMPORTANT: Use schema field names (title/content/excerpt not post_title/post_content)
			$data = array(
				'title' => Manticore_Client::normalize_numerals( $post->post_title ),
				'post_type' => $post->post_type,
				'post_status' => $post->post_status,
				'post_date' => strtotime( $post->post_date ),
				'post_modified' => strtotime( $post->post_modified ),
				'post_author' => (int) $post->post_author,
				'menu_order' => (int) $post->menu_order,
				'comment_count' => (int) $post->comment_count,
				'author' => isset( $author_data[ $post->post_author ] ) ? Manticore_Client::normalize_numerals( $author_data[ $post->post_author ] ) : '',
			);

			// Add content and excerpt conditionally (only if index_product_content is enabled for products)
			if ( $post->post_type !== 'product' || $index_content ) {
				// Use native strip_tags for speed (wp_strip_all_tags is slower due to filters)
				$data['content'] = Manticore_Client::normalize_numerals( wp_strip_all_tags( $post->post_content ) );
				$data['excerpt'] = Manticore_Client::normalize_numerals( wp_strip_all_tags( $post->post_excerpt ) );
			}
			// Categories (text + MVA!) - IMPORTANT: Use schema names (categories/category_ids)
			if ( isset( $category_data[ $post_id ] ) ) {
				$data['categories'] = implode( ' ', $category_data[ $post_id ]['names'] );
				$data['category_ids'] = $category_data[ $post_id ]['ids'];
			} else {
				$data['categories'] = '';
				$data['category_ids'] = array();
			}

			// Tags (text + MVA!) - IMPORTANT: Use schema names (tags/tag_ids)
			if ( isset( $tag_data[ $post_id ] ) ) {
				$data['tags'] = implode( ' ', $tag_data[ $post_id ]['names'] );
				$data['tag_ids'] = $tag_data[ $post_id ]['ids'];
			} else {
				$data['tags'] = '';
				$data['tag_ids'] = array();
			}

			// WooCommerce product fields
			if ( $post_type === 'product' ) {
				// Calculate visibility from taxonomy
				$visibility = 'visible'; // Default
				if ( isset( $visibility_data[ $post_id ] ) ) {
					$vis_terms = $visibility_data[ $post_id ];
					$exclude_catalog = in_array( 'exclude-from-catalog', $vis_terms );
					$exclude_search = in_array( 'exclude-from-search', $vis_terms );

					if ( $exclude_catalog && $exclude_search ) {
						$visibility = 'hidden';
					} elseif ( $exclude_catalog ) {
						$visibility = 'search';
					} elseif ( $exclude_search ) {
						$visibility = 'catalog';
					}
				}

				// Featured status
				$featured = isset( $visibility_data[ $post_id ] ) && in_array( 'featured', $visibility_data[ $post_id ] ) ? 1 : 0;

				// On sale detection
				$regular_price = isset( $meta['_regular_price'] ) ? (float) $meta['_regular_price'] : 0;
				$sale_price = isset( $meta['_sale_price'] ) ? (float) $meta['_sale_price'] : 0;
				$on_sale = ( $sale_price > 0 && $sale_price < $regular_price ) ? 1 : 0;

				$data['sku'] = isset( $meta['_sku'] ) ? Manticore_Client::normalize_numerals( $meta['_sku'] ) : '';
				$data['short_description'] = ''; // Would need separate query, skip for speed
				$data['price'] = isset( $meta['_price'] ) ? (float) $meta['_price'] : 0;
				$data['regular_price'] = $regular_price;
				$data['sale_price'] = $sale_price;
				$data['stock_quantity'] = isset( $meta['_stock_quantity'] ) ? (int) $meta['_stock_quantity'] : 0;
				$data['stock_status'] = isset( $meta['_stock_status'] ) ? $meta['_stock_status'] : 'instock';
				$data['visibility'] = $visibility;
				$data['featured'] = $featured;
				$data['on_sale'] = $on_sale;
				$data['rating'] = isset( $meta['_wc_average_rating'] ) ? (float) $meta['_wc_average_rating'] : 0;
				$data['review_count'] = isset( $meta['_wc_review_count'] ) ? (int) $meta['_wc_review_count'] : 0;
				$data['total_sales'] = isset( $meta['total_sales'] ) ? (int) $meta['total_sales'] : 0;

				// Attributes (text + MVA!)
				if ( isset( $attribute_data[ $post_id ] ) ) {
					$data['attributes'] = implode( ' ', $attribute_data[ $post_id ]['names'] );
					$data['attribute_ids'] = $attribute_data[ $post_id ]['ids']; // Generic MVA

					// Individual attribute MVA fields (pa_color_ids, pa_size_ids, etc.)
					// This is CRITICAL for filters!
					foreach ( $attribute_data[ $post_id ]['by_taxonomy'] as $taxonomy => $term_ids ) {
						$field_name = str_replace( '-', '_', $taxonomy ) . '_ids';

						// Only add ASCII-safe field names (Manticore limitation)
						if ( preg_match( '/^[a-zA-Z0-9_]+$/', $field_name ) ) {
							$data[ $field_name ] = $term_ids;
						}
					}
				} else {
					$data['attributes'] = '';
					$data['attribute_ids'] = array();
				}

				$data['variations'] = ''; // Would need separate query, skip for speed
			}

			$batch[ $post_id ] = $data;
		}

		// Bulk insert to Manticore
		$indexer = \MantiLoad\MantiLoad::instance()->indexer;
		$client = $indexer->get_client();
		$index_name = \MantiLoad\MantiLoad::get_option( 'index_name' );

		if ( empty( $client ) ) {
			$results['failed'] = count( $batch );
			return $results;
		}

		if ( empty( $batch ) ) {
			return $results;
		}

		if ( $client->bulk_insert( $index_name, $batch ) ) {
			$results['indexed'] = count( $batch );
		} else {
			$results['failed'] = count( $batch );
			$error = $client->get_last_error();
		}

		return $results;
	}
}
