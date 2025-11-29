<?php
/**
 * FAST Indexer - Optimized for speed
 * Skips all the slow stuff, just gets the essentials
 */

namespace MantiLoad\Indexer;

defined( 'ABSPATH' ) || exit;

/**
 * Fast_Indexer class
 *
 * phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
 * Direct database queries are required for fast indexing performance.
 * This is a performance-critical indexer - direct queries are optimized and necessary.
 */
class Fast_Indexer {

	/**
	 * Fast bulk index - no bullshit
	 */
	public static function index_batch( $post_type, $offset, $limit ) {
		global $wpdb;

		$results = array(
			'indexed' => 0,
			'failed' => 0,
		);

		// Get posts directly from DB - FAST
		$posts = $wpdb->get_results( $wpdb->prepare(
			"SELECT ID, post_title, post_content, post_excerpt, post_date, post_type
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

		// Get all product meta in ONE query - FAST
		$meta_data = array();
		if ( $post_type === 'product' ) {
			// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- $post_ids sanitized with array_map('intval')
			$meta_results = $wpdb->get_results(
				"SELECT post_id, meta_key, meta_value
				FROM {$wpdb->postmeta}
				WHERE post_id IN (" . implode( ',', array_map( 'intval', $post_ids ) ) . ")
				AND meta_key IN ('_sku', '_price', '_regular_price', '_sale_price', '_stock_status', '_visibility')"
			);
			// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared

			foreach ( $meta_results as $row ) {
				$meta_data[ $row->post_id ][ $row->meta_key ] = $row->meta_value;
			}
		}

		// Get categories in ONE query - FAST
		$categories = array();
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- $post_ids sanitized with array_map('intval')
		$cat_results = $wpdb->get_results(
			"SELECT tr.object_id, t.name
			FROM {$wpdb->term_relationships} tr
			INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
			INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
			WHERE tr.object_id IN (" . implode( ',', array_map( 'intval', $post_ids ) ) . ")
			AND tt.taxonomy = 'product_cat'"
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared

		foreach ( $cat_results as $row ) {
			if ( ! isset( $categories[ $row->object_id ] ) ) {
				$categories[ $row->object_id ] = array();
			}
			$categories[ $row->object_id ][] = $row->name;
		}

		// Get product_visibility taxonomy in ONE query - FAST
		// WooCommerce uses 'exclude-from-catalog' and 'exclude-from-search' terms
		$visibility_data = array();
		if ( $post_type === 'product' ) {
			// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- $post_ids sanitized with array_map('intval')
			$vis_results = $wpdb->get_results(
				"SELECT tr.object_id, t.slug
				FROM {$wpdb->term_relationships} tr
				INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
				INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
				WHERE tr.object_id IN (" . implode( ',', array_map( 'intval', $post_ids ) ) . ")
				AND tt.taxonomy = 'product_visibility'
				AND t.slug IN ('exclude-from-catalog', 'exclude-from-search')"
			);
			// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared

			foreach ( $vis_results as $row ) {
				if ( ! isset( $visibility_data[ $row->object_id ] ) ) {
					$visibility_data[ $row->object_id ] = array();
				}
				$visibility_data[ $row->object_id ][] = $row->slug;
			}
		}

		// Check if we should index product content
		$index_content = \MantiLoad\MantiLoad::get_option( 'index_product_content', false );

		// Prepare batch
		foreach ( $posts as $post ) {
			$meta = isset( $meta_data[ $post->ID ] ) ? $meta_data[ $post->ID ] : array();

			// Calculate WooCommerce catalog visibility from taxonomy
			$visibility = 'visible'; // Default
			if ( $post_type === 'product' && isset( $visibility_data[ $post->ID ] ) ) {
				$vis_terms = $visibility_data[ $post->ID ];
				$exclude_catalog = in_array( 'exclude-from-catalog', $vis_terms );
				$exclude_search = in_array( 'exclude-from-search', $vis_terms );

				if ( $exclude_catalog && $exclude_search ) {
					$visibility = 'hidden'; // Hidden from both
				} elseif ( $exclude_catalog ) {
					$visibility = 'search'; // Visible in search only
				} elseif ( $exclude_search ) {
					$visibility = 'catalog'; // Visible in catalog only
				}
			}

			// Build the full data array with all required fields
			// Use same field names as main indexer (title, content, excerpt - NOT post_title etc)
			$data = array(
				'title'       => \MantiLoad\Manticore_Client::normalize_numerals( $post->post_title ),
				'post_type'   => $post->post_type,
				'post_status' => 'publish',
				'post_date'   => strtotime( $post->post_date ),
			);

			// Add content and excerpt conditionally
			if ( $post_type !== 'product' || $index_content ) {
				$data['content'] = \MantiLoad\Manticore_Client::normalize_numerals( wp_strip_all_tags( $post->post_content ) );
				$data['excerpt'] = \MantiLoad\Manticore_Client::normalize_numerals( wp_strip_all_tags( $post->post_excerpt ) );
			}

			// Add product-specific fields
			if ( $post_type === 'product' ) {
				$data['sku']          = isset( $meta['_sku'] ) ? \MantiLoad\Manticore_Client::normalize_numerals( $meta['_sku'] ) : '';
				$data['price']        = isset( $meta['_price'] ) ? (float) $meta['_price'] : 0;
				$data['regular_price'] = isset( $meta['_regular_price'] ) ? (float) $meta['_regular_price'] : 0;
				$data['sale_price']   = isset( $meta['_sale_price'] ) ? (float) $meta['_sale_price'] : 0;
				$data['stock_status'] = isset( $meta['_stock_status'] ) ? $meta['_stock_status'] : 'instock';
				$data['visibility']   = $visibility;

				// Add categories as text for full-text search
				$cats = isset( $categories[ $post->ID ] ) ? $categories[ $post->ID ] : array();
				$data['categories'] = implode( ' ', array_map( array( '\MantiLoad\Manticore_Client', 'normalize_numerals' ), $cats ) );
			}

			$batch[ $post->ID ] = $data;
		}

		// Bulk insert to Manticore
		$indexer = \MantiLoad\MantiLoad::instance()->indexer;
		$client = $indexer->get_client();
		$index_name = \MantiLoad\MantiLoad::get_option( 'index_name', 'mantiload_fast_one' );

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
