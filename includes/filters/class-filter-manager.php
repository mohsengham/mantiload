<?php
/**
 * Filter Manager - Core controller for MantiLoad revolutionary filters
 *
 * @package MantiLoad
 * @subpackage Filters
 */

namespace MantiLoad\Filters;

use MantiLoad\MantiLoad;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Filter_Manager Class
 *
 * Coordinates all filter functionality:
 * - Registers filters
 * - Handles AJAX requests
 * - Manages filter state
 * - Integrates with Query_Integration
 */
class Filter_Manager {

	/**
	 * Filter AJAX handler
	 *
	 * @var Filter_Ajax
	 */
	private $ajax;

	/**
	 * Filter query builder
	 *
	 * @var Filter_Query
	 */
	private $query;

	/**
	 * Filter renderer
	 *
	 * @var Filter_Renderer
	 */
	private $renderer;

	/**
	 * Available filter types
	 *
	 * @var array
	 */
	private $filter_types = array(
		'category'   => 'Product Categories',
		'attribute'  => 'Product Attributes (color, size, brand, etc.)',
		'price'      => 'Price Range',
		'rating'     => 'Average Rating',
		'stock'      => 'Stock Status',
		'on_sale'    => 'On Sale',
		'search'     => 'Search Within Results',
		'tag'        => 'Product Tags',
	);

	/**
	 * Active filters for current request
	 *
	 * @var array
	 */
	private $active_filters = array();

	/**
	 * Constructor
	 */
	public function __construct() {
		// Load dependencies
		$this->load_dependencies();

		// Initialize hooks first (lightweight)
		$this->init_hooks();

		// Initialize components
		$this->ajax     = new Filter_Ajax( $this );
		$this->query    = new Filter_Query( $this );
		$this->renderer = new Filter_Renderer( $this );

		// Parse active filters from URL/request (only on frontend)
		if ( ! is_admin() || wp_doing_ajax() ) {
			$this->parse_active_filters();
		}
	}

	/**
	 * Load filter component classes
	 */
	private function load_dependencies() {
		require_once MANTILOAD_PLUGIN_DIR . 'includes/filters/class-filter-ajax.php';
		require_once MANTILOAD_PLUGIN_DIR . 'includes/filters/class-filter-query.php';
		require_once MANTILOAD_PLUGIN_DIR . 'includes/filters/class-filter-renderer.php';
		require_once MANTILOAD_PLUGIN_DIR . 'includes/filters/class-filter-widget.php';
	}

	/**
	 * Initialize WordPress hooks
	 */
	private function init_hooks() {
		// Enqueue frontend assets
		\add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		// Register widget
		\add_action( 'widgets_init', array( $this, 'register_widget' ) );

		// Register shortcode
		add_shortcode( 'mantiload_filters', array( $this->renderer, 'render_shortcode' ) );

		// Integrate with Query_Integration to apply filters
		\add_filter( 'mantiload_query_integration_args', array( $this, 'apply_filters_to_query' ), 10, 2 );

		// Add filter data to localized JS
		\add_filter( 'mantiload_localize_script', array( $this, 'add_filter_data' ) );
	}

	/**
	 * Register filter widget
	 */
	public function register_widget() {
		register_widget( 'MantiLoad\Filters\Filter_Widget' );
	}

	/**
	 * Enqueue frontend assets (JS + CSS)
	 */
	public function enqueue_assets() {
		// Only load on product archives and shop pages
		if ( ! is_shop() && ! is_product_category() && ! is_product_tag() && ! is_search() ) {
			return;
		}

		// CSS
		\wp_enqueue_style(
			'mantiload-filters',
			MANTILOAD_PLUGIN_URL . 'assets/css/mantiload-filters.css',
			array(),
			MANTILOAD_VERSION
		);

		// JavaScript
		\wp_enqueue_script(
			'mantiload-filters',
			MANTILOAD_PLUGIN_URL . 'assets/js/mantiload-filters.js',
			array( 'jquery' ),
			MANTILOAD_VERSION,
			true
		);

		// Localize script
		\wp_localize_script(
			'mantiload-filters',
			'mantiloadFilters',
			array(
				'ajaxUrl'        => \admin_url( 'admin-ajax.php' ),
				'nonce'          => \wp_create_nonce( 'mantiload_filters' ),
				'activeFilters'  => $this->active_filters,
				'debugMode'      => defined( 'WP_DEBUG' ) && WP_DEBUG,
				'strings'        => array(
					'loading'     => __( 'Loading...', 'mantiload' ),
					'noResults'   => __( 'No products found', 'mantiload' ),
					'clearAll'    => __( 'Clear All Filters', 'mantiload' ),
					'apply'       => __( 'Apply Filters', 'mantiload' ),
					'showing'     => __( 'Showing', 'mantiload' ),
					'of'          => __( 'of', 'mantiload' ),
					'products'    => __( 'products', 'mantiload' ),
				),
			)
		);
	}

	/**
	 * Parse active filters from request
	 *
	 * Supports both clean URLs and query parameters
	 */
	private function parse_active_filters() {
		$this->active_filters = array();

		// Price range
		if ( isset( $_GET['min_price'] ) || isset( $_GET['max_price'] ) ) {
			$this->active_filters['price'] = array(
				'min' => isset( $_GET['min_price'] ) ? floatval( $_GET['min_price'] ) : null,
				'max' => isset( $_GET['max_price'] ) ? floatval( $_GET['max_price'] ) : null,
			);
		}

		// Rating filter
		if ( isset( $_GET['rating_filter'] ) ) {
			$this->active_filters['rating'] = absint( $_GET['rating_filter'] );
		}

		// Stock status
		if ( isset( $_GET['stock_status'] ) ) {
			$this->active_filters['stock'] = sanitize_text_field( wp_unslash( $_GET['stock_status'] ) );
		}

		// On sale
		if ( isset( $_GET['on_sale'] ) && $_GET['on_sale'] === '1' ) {
			$this->active_filters['on_sale'] = true;
		}

		// Attribute filters (filter_pa_color, filter_brand, etc.)
		foreach ( $_GET as $key => $value ) {
			if ( strpos( $key, 'filter_' ) === 0 && ! empty( $value ) ) {
				$attribute = str_replace( 'filter_', '', $key );

				// Parse comma-separated values
				$terms = array_map( 'sanitize_text_field', explode( ',', $value ) );

				$this->active_filters['attributes'][ $attribute ] = $terms;
			}
		}

		// Allow plugins to modify active filters
		$this->active_filters = \apply_filters( 'mantiload_active_filters', $this->active_filters );
	}

	/**
	 * Get active filters
	 *
	 * @return array
	 */
	public function get_active_filters() {
		return $this->active_filters;
	}

	/**
	 * Get available filter types
	 *
	 * @return array
	 */
	public function get_filter_types() {
		return \apply_filters( 'mantiload_filter_types', $this->filter_types );
	}

	/**
	 * Get filter query builder
	 *
	 * @return Filter_Query
	 */
	public function get_query_builder() {
		return $this->query;
	}

	/**
	 * Get filter renderer
	 *
	 * @return Filter_Renderer
	 */
	public function get_renderer() {
		return $this->renderer;
	}

	/**
	 * Apply active filters to MantiLoad query
	 *
	 * Integrates with Query_Integration class
	 *
	 * @param array     $manticore_query Query args.
	 * @param \WP_Query $wp_query        WordPress query object.
	 * @return array Modified query args.
	 */
	public function apply_filters_to_query( $manticore_query, $wp_query ) {
		// Don't apply on admin or single products
		if ( is_admin() || is_singular( 'product' ) ) {
			return $manticore_query;
		}

		// Let Filter_Query handle the logic
		return $this->query->apply_to_query( $manticore_query, $this->active_filters );
	}

	/**
	 * Add filter data to localized script
	 *
	 * @param array $data Localization data.
	 * @return array
	 */
	public function add_filter_data( $data ) {
		$data['filters'] = array(
			'active' => $this->active_filters,
			'types'  => $this->get_filter_types(),
		);

		return $data;
	}

	/**
	 * Check if filters are active
	 *
	 * @return bool
	 */
	public function has_active_filters() {
		return ! empty( $this->active_filters );
	}

	/**
	 * Get clean filter URL
	 *
	 * Generates SEO-friendly URLs for filtered pages
	 *
	 * @param array $filters Filter parameters.
	 * @return string
	 */
	public function get_filter_url( $filters = array() ) {
		// If no filters provided, use active filters
		if ( empty( $filters ) ) {
			$filters = $this->active_filters;
		}

		// Start with current page URL
		$base_url = is_shop() ? \get_permalink( wc_get_page_id( 'shop' ) ) : \get_permalink();

		// Build query parameters
		$query_params = array();

		foreach ( $filters as $type => $value ) {
			switch ( $type ) {
				case 'price':
					if ( isset( $value['min'] ) && $value['min'] > 0 ) {
						$query_params['min_price'] = $value['min'];
					}
					if ( isset( $value['max'] ) && $value['max'] > 0 ) {
						$query_params['max_price'] = $value['max'];
					}
					break;

				case 'rating':
					$query_params['rating_filter'] = absint( $value );
					break;

				case 'stock':
					$query_params['stock_status'] = \sanitize_text_field( $value );
					break;

				case 'on_sale':
					if ( $value ) {
						$query_params['on_sale'] = '1';
					}
					break;

				case 'attributes':
					foreach ( $value as $attribute => $terms ) {
						$query_params[ 'filter_' . $attribute ] = implode( ',', $terms );
					}
					break;
			}
		}

		// Build final URL
		return add_query_arg( $query_params, $base_url );
	}

	/**
	 * Clear all filters
	 *
	 * @return string URL without filters.
	 */
	public function get_clear_filters_url() {
		if ( is_shop() ) {
			return \get_permalink( wc_get_page_id( 'shop' ) );
		}

		return \get_permalink();
	}
}
