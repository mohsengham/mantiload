<?php
/**
 * Filter AJAX Handler - Handles AJAX requests for filters
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
 * Filter_Ajax Class
 *
 * Handles AJAX operations:
 * - Get filter options
 * - Get filtered products
 * - Get product counts
 */
class Filter_Ajax {

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
	 * Constructor
	 *
	 * @param Filter_Manager $manager Filter manager instance.
	 */
	public function __construct( $manager ) {
		$this->manager = $manager;
		$this->client  = new Manticore_Client();

		$this->init_hooks();
	}

	/**
	 * Initialize AJAX hooks
	 */
	private function init_hooks() {
		// Get filtered products (main AJAX endpoint)
		\add_action( 'wp_ajax_mantiload_filter_products', array( $this, 'ajax_filter_products' ) );
		\add_action( 'wp_ajax_nopriv_mantiload_filter_products', array( $this, 'ajax_filter_products' ) );

		// Get filter options with counts
		\add_action( 'wp_ajax_mantiload_get_filter_options', array( $this, 'ajax_get_filter_options' ) );
		\add_action( 'wp_ajax_nopriv_mantiload_get_filter_options', array( $this, 'ajax_get_filter_options' ) );

		// Get product count for current filters
		\add_action( 'wp_ajax_mantiload_get_product_count', array( $this, 'ajax_get_product_count' ) );
		\add_action( 'wp_ajax_nopriv_mantiload_get_product_count', array( $this, 'ajax_get_product_count' ) );
	}

	/**
	 * AJAX: Get filtered products
	 *
	 * Returns HTML of filtered products + pagination
	 */
	public function ajax_filter_products() {
		// Verify nonce
		\check_ajax_referer( 'mantiload_filters', 'nonce' );

		$start_time = microtime( true );

		// Parse filter parameters from request
		$filters = $this->parse_ajax_filters();

		// Get current page
		$paged = isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1;

		// Get posts per page
		$posts_per_page = isset( $_POST['per_page'] ) ? absint( $_POST['per_page'] ) : wc_get_default_products_per_row() * wc_get_default_product_rows_per_page();

		// Build query using Filter_Query
		$query_builder = $this->manager->get_query_builder();
		$products      = $query_builder->get_filtered_products( $filters, $paged, $posts_per_page );

		if ( ! $products ) {
			\wp_send_json_error(
				array(
					'message' => __( 'Failed to retrieve products', 'mantiload' ),
				)
			);
		}

		// Render products HTML
		$renderer = $this->manager->get_renderer();
		$html     = $renderer->render_products( $products['posts'] );

		// Calculate pagination
		$max_pages = ceil( $products['total'] / $posts_per_page );

		$response = array(
			'success'    => true,
			'html'       => $html,
			'total'      => $products['total'],
			'page'       => $paged,
			'max_pages'  => $max_pages,
			'query_time' => round( ( microtime( true ) - $start_time ) * 1000, 2 ) . 'ms',
			'filters'    => $filters,
		);

		// Add debug info if WP_DEBUG is on
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$response['debug'] = array(
				'sql'            => $products['sql'] ?? '',
				'manticore_time' => $products['time'] ?? 0,
			);
		}

		\wp_send_json_success( $response );
	}

	/**
	 * AJAX: Get filter options with product counts
	 *
	 * Returns available filter values and how many products match each
	 */
	public function ajax_get_filter_options() {
		\check_ajax_referer( 'mantiload_filters', 'nonce' );

		$filter_type = isset( $_POST['filter_type'] ) ? sanitize_text_field( wp_unslash( $_POST['filter_type'] ) ) : '';
		$filters     = $this->parse_ajax_filters();

		if ( empty( $filter_type ) ) {
			\wp_send_json_error( array( 'message' => 'Filter type required' ) );
		}

		$options = array();

		switch ( $filter_type ) {
			case 'category':
				$options = $this->get_category_options( $filters );
				break;

			case 'attribute':
				$attribute = isset( $_POST['attribute'] ) ? sanitize_text_field( wp_unslash( $_POST['attribute'] ) ) : '';
				$options   = $this->get_attribute_options( $attribute, $filters );
				break;

			case 'rating':
				$options = $this->get_rating_options( $filters );
				break;

			case 'stock':
				$options = $this->get_stock_options( $filters );
				break;

			default:
				$options = \apply_filters( "mantiload_filter_options_{$filter_type}", array(), $filters );
				break;
		}

		\wp_send_json_success( array( 'options' => $options ) );
	}

	/**
	 * AJAX: Get product count for current filters
	 */
	public function ajax_get_product_count() {
		\check_ajax_referer( 'mantiload_filters', 'nonce' );

		$filters = $this->parse_ajax_filters();

		$query_builder = $this->manager->get_query_builder();
		$count         = $query_builder->get_product_count( $filters );

		\wp_send_json_success( array( 'count' => $count ) );
	}

	/**
	 * Parse filters from AJAX request
	 *
	 * @return array
	 */
	private function parse_ajax_filters() {
		$filters = array();

		// Price range
		if ( isset( $_POST['min_price'] ) || isset( $_POST['max_price'] ) ) {
			$filters['price'] = array(
				'min' => isset( $_POST['min_price'] ) ? floatval( $_POST['min_price'] ) : null,
				'max' => isset( $_POST['max_price'] ) ? floatval( $_POST['max_price'] ) : null,
			);
		}

		// Categories
		if ( ! empty( $_POST['categories'] ) ) {
			$filters['categories'] = array_map( 'absint', wp_unslash( $_POST['categories'] ) );
		}

		// Attributes
		if ( ! empty( $_POST['attributes'] ) && is_array( $_POST['attributes'] ) ) {
			$filters['attributes'] = array();
			foreach ( wp_unslash( $_POST['attributes'] ) as $attribute => $terms ) {
				$attribute_clean = sanitize_text_field( $attribute );
				$filters['attributes'][ $attribute_clean ] = array_map( 'sanitize_text_field', (array) $terms );
			}
		}

		// Rating
		if ( isset( $_POST['rating'] ) ) {
			$filters['rating'] = absint( wp_unslash( $_POST['rating'] ) );
		}

		// Stock status
		if ( ! empty( $_POST['stock_status'] ) ) {
			$filters['stock'] = sanitize_text_field( wp_unslash( $_POST['stock_status'] ) );
		}

		// On sale
		if ( isset( $_POST['on_sale'] ) && $_POST['on_sale'] === '1' ) {
			$filters['on_sale'] = true;
		}

		// Search query
		if ( ! empty( $_POST['search'] ) ) {
			$filters['search'] = sanitize_text_field( wp_unslash( $_POST['search'] ) );
		}

		// Orderby
		if ( ! empty( $_POST['orderby'] ) ) {
			$filters['orderby'] = sanitize_text_field( wp_unslash( $_POST['orderby'] ) );
		}

		return \apply_filters( 'mantiload_ajax_filters', $filters );
	}

	/**
	 * Get category filter options with product counts
	 *
	 * @param array $active_filters Currently active filters.
	 * @return array
	 */
	private function get_category_options( $active_filters = array() ) {
		$categories = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => true,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);

		if ( is_wp_error( $categories ) ) {
			return array();
		}

		$options = array();

		foreach ( $categories as $category ) {
			// Get count from Manticore for this category
			$count = $this->get_category_product_count( $category->term_id, $active_filters );

			if ( $count > 0 ) {
				$options[] = array(
					'id'       => $category->term_id,
					'slug'     => $category->slug,
					'name'     => $category->name,
					'count'    => $count,
					'parent'   => $category->parent,
					'disabled' => false,
				);
			}
		}

		return $options;
	}

	/**
	 * Get attribute filter options with product counts
	 *
	 * @param string $attribute      Attribute name (e.g., 'pa_color').
	 * @param array  $active_filters Currently active filters.
	 * @return array
	 */
	private function get_attribute_options( $attribute, $active_filters = array() ) {
		if ( empty( $attribute ) ) {
			return array();
		}

		// Ensure attribute has 'pa_' prefix
		if ( strpos( $attribute, 'pa_' ) !== 0 ) {
			$attribute = 'pa_' . $attribute;
		}

		$terms = get_terms(
			array(
				'taxonomy'   => $attribute,
				'hide_empty' => true,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return array();
		}

		$options = array();

		foreach ( $terms as $term ) {
			// Get count from Manticore
			$count = $this->get_attribute_product_count( $attribute, $term->term_id, $active_filters );

			if ( $count > 0 ) {
				$options[] = array(
					'id'       => $term->term_id,
					'slug'     => $term->slug,
					'name'     => $term->name,
					'count'    => $count,
					'disabled' => false,
					'color'    => get_term_meta( $term->term_id, 'color', true ), // For color swatches
				);
			}
		}

		return $options;
	}

	/**
	 * Get rating filter options
	 *
	 * @param array $active_filters Currently active filters.
	 * @return array
	 */
	private function get_rating_options( $active_filters = array() ) {
		$options = array();

		for ( $rating = 5; $rating >= 1; $rating-- ) {
			$count = $this->get_rating_product_count( $rating, $active_filters );

			if ( $count > 0 ) {
				$options[] = array(
					'rating'   => $rating,
					/* translators: %d: star rating */
					'label'    => sprintf( __( '%d stars & up', 'mantiload' ), $rating ),
					'count'    => $count,
					'disabled' => false,
				);
			}
		}

		return $options;
	}

	/**
	 * Get stock status filter options
	 *
	 * @param array $active_filters Currently active filters.
	 * @return array
	 */
	private function get_stock_options( $active_filters = array() ) {
		$statuses = array(
			'instock'     => __( 'In Stock', 'mantiload' ),
			'outofstock'  => __( 'Out of Stock', 'mantiload' ),
			'onbackorder' => __( 'On Backorder', 'mantiload' ),
		);

		$options = array();

		foreach ( $statuses as $status => $label ) {
			$count = $this->get_stock_product_count( $status, $active_filters );

			if ( $count > 0 ) {
				$options[] = array(
					'status'   => $status,
					'label'    => $label,
					'count'    => $count,
					'disabled' => false,
				);
			}
		}

		return $options;
	}

	/**
	 * Get product count for a category
	 *
	 * @param int   $category_id     Category term ID.
	 * @param array $active_filters  Currently active filters.
	 * @return int
	 */
	private function get_category_product_count( $category_id, $active_filters = array() ) {
		// Add this category to filters temporarily
		$filters               = $active_filters;
		$filters['categories'] = array( $category_id );

		$query_builder = $this->manager->get_query_builder();
		return $query_builder->get_product_count( $filters );
	}

	/**
	 * Get product count for an attribute value
	 *
	 * @param string $attribute      Attribute name.
	 * @param int    $term_id        Term ID.
	 * @param array  $active_filters Currently active filters.
	 * @return int
	 */
	private function get_attribute_product_count( $attribute, $term_id, $active_filters = array() ) {
		$filters                              = $active_filters;
		$filters['attributes'][ $attribute ]  = array( $term_id );

		$query_builder = $this->manager->get_query_builder();
		return $query_builder->get_product_count( $filters );
	}

	/**
	 * Get product count for a rating
	 *
	 * @param int   $rating          Star rating (1-5).
	 * @param array $active_filters  Currently active filters.
	 * @return int
	 */
	private function get_rating_product_count( $rating, $active_filters = array() ) {
		$filters           = $active_filters;
		$filters['rating'] = $rating;

		$query_builder = $this->manager->get_query_builder();
		return $query_builder->get_product_count( $filters );
	}

	/**
	 * Get product count for a stock status
	 *
	 * @param string $status         Stock status.
	 * @param array  $active_filters Currently active filters.
	 * @return int
	 */
	private function get_stock_product_count( $status, $active_filters = array() ) {
		$filters         = $active_filters;
		$filters['stock'] = $status;

		$query_builder = $this->manager->get_query_builder();
		return $query_builder->get_product_count( $filters );
	}
}
