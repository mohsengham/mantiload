<?php
/**
 * Filter Renderer - Renders filter UI elements
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
 * Filter_Renderer Class
 *
 * Handles UI rendering for all filter types:
 * - Checkboxes
 * - Radio buttons
 * - Dropdowns
 * - Range sliders
 * - Color swatches
 * - Image swatches
 */
class Filter_Renderer {

	/**
	 * Filter manager instance
	 *
	 * @var Filter_Manager
	 */
	private $manager;

	/**
	 * Constructor
	 *
	 * @param Filter_Manager $manager Filter manager instance.
	 */
	public function __construct( $manager ) {
		$this->manager = $manager;
	}

	/**
	 * Render filters shortcode
	 *
	 * Usage: [mantiload_filters type="category,price,attribute" style="sidebar"]
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'type'  => 'category,price,attribute,rating,stock,on_sale', // Comma-separated filter types
				'style' => 'sidebar', // sidebar, horizontal, slide-out
			),
			$atts,
			'mantiload_filters'
		);

		$filter_types = array_map( 'trim', explode( ',', $atts['type'] ) );
		$style        = \sanitize_text_field( $atts['style'] );

		ob_start();
		?>
		<div class="mantiload-filters mantiload-filters--<?php echo \esc_attr( $style ); ?>" data-style="<?php echo \esc_attr( $style ); ?>">
			<div class="mantiload-filters__header">
				<h3 class="mantiload-filters__title"><?php esc_html_e( 'Filter Products', 'mantiload' ); ?></h3>
				<?php if ( $this->manager->has_active_filters() ) : ?>
					<button type="button" class="mantiload-filters__clear" data-action="clear-all">
						<?php esc_html_e( 'Clear All', 'mantiload' ); ?>
					</button>
				<?php endif; ?>
			</div>

			<div class="mantiload-filters__body">
				<?php foreach ( $filter_types as $filter_type ) : ?>
					<?php $this->render_filter( $filter_type ); ?>
				<?php endforeach; ?>
			</div>

			<div class="mantiload-filters__footer">
				<div class="mantiload-filters__results-count">
					<span class="mantiload-filters__count-number">0</span>
					<?php esc_html_e( 'products found', 'mantiload' ); ?>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render a single filter
	 *
	 * @param string $filter_type Filter type (category, price, etc.).
	 */
	public function render_filter( $filter_type ) {
		switch ( $filter_type ) {
			case 'category':
				$this->render_category_filter();
				break;

			case 'price':
				$this->render_price_filter();
				break;

			case 'attribute':
			case 'color':
			case 'size':
			case 'brand':
				// Get attribute name
				$attribute = $filter_type === 'attribute' ? '' : 'pa_' . $filter_type;
				$this->render_attribute_filter( $attribute );
				break;

			case 'rating':
				$this->render_rating_filter();
				break;

			case 'stock':
				$this->render_stock_filter();
				break;

			case 'on_sale':
				$this->render_on_sale_filter();
				break;

			default:
				\do_action( "mantiload_render_filter_{$filter_type}" );
				break;
		}
	}

	/**
	 * Render category filter (hierarchical checkboxes)
	 */
	public function render_category_filter() {
		$categories = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => true,
				'parent'     => 0, // Top-level only for now
			)
		);

		if ( empty( $categories ) || is_wp_error( $categories ) ) {
			return;
		}

		$active_filters = $this->manager->get_active_filters();
		$active_cats    = isset( $active_filters['categories'] ) ? $active_filters['categories'] : array();
		?>
		<div class="mantiload-filter mantiload-filter--category" data-filter-type="category">
			<h4 class="mantiload-filter__title">
				<?php esc_html_e( 'Categories', 'mantiload' ); ?>
			</h4>
			<div class="mantiload-filter__content">
				<ul class="mantiload-filter-list">
					<?php foreach ( $categories as $category ) : ?>
						<?php
						$is_active = in_array( $category->term_id, $active_cats, true );
						?>
						<li class="mantiload-filter-item <?php echo $is_active ? 'is-active' : ''; ?>">
							<label class="mantiload-filter-label">
								<input
									type="checkbox"
									class="mantiload-filter-input"
									data-filter-type="category"
									data-filter-value="<?php echo \esc_attr( $category->term_id ); ?>"
									value="<?php echo \esc_attr( $category->term_id ); ?>"
									<?php checked( $is_active ); ?>
								/>
								<span class="mantiload-filter-name"><?php echo \esc_html( $category->name ); ?></span>
								<span class="mantiload-filter-count">(<?php echo \esc_html( $category->count ); ?>)</span>
							</label>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		</div>
		<?php
	}

	/**
	 * Render price range filter (slider)
	 */
	public function render_price_filter() {
		// Get min/max prices from WooCommerce
		global $wpdb;

		$prices = $wpdb->get_row(
			"SELECT MIN(CAST(meta_value AS DECIMAL)) as min_price, MAX(CAST(meta_value AS DECIMAL)) as max_price
			FROM {$wpdb->postmeta}
			WHERE meta_key = '_price'"
		);

		$min_price = floor( $prices->min_price ?? 0 );
		$max_price = ceil( $prices->max_price ?? 1000 );

		$active_filters = $this->manager->get_active_filters();
		$current_min    = isset( $active_filters['price']['min'] ) ? $active_filters['price']['min'] : $min_price;
		$current_max    = isset( $active_filters['price']['max'] ) ? $active_filters['price']['max'] : $max_price;
		?>
		<div class="mantiload-filter mantiload-filter--price" data-filter-type="price">
			<h4 class="mantiload-filter__title">
				<?php esc_html_e( 'Price Range', 'mantiload' ); ?>
			</h4>
			<div class="mantiload-filter__content">
				<div class="mantiload-price-slider">
					<input
						type="range"
						class="mantiload-price-slider__input mantiload-price-slider__input--min"
						data-filter-type="price-min"
						min="<?php echo \esc_attr( $min_price ); ?>"
						max="<?php echo \esc_attr( $max_price ); ?>"
						value="<?php echo \esc_attr( $current_min ); ?>"
						step="1"
					/>
					<input
						type="range"
						class="mantiload-price-slider__input mantiload-price-slider__input--max"
						data-filter-type="price-max"
						min="<?php echo \esc_attr( $min_price ); ?>"
						max="<?php echo \esc_attr( $max_price ); ?>"
						value="<?php echo \esc_attr( $current_max ); ?>"
						step="1"
					/>
					<div class="mantiload-price-slider__values">
						<span class="mantiload-price-slider__value mantiload-price-slider__value--min">
							<?php echo esc_html( wc_price( $current_min ) ); ?>
						</span>
						<span class="mantiload-price-slider__separator"> - </span>
						<span class="mantiload-price-slider__value mantiload-price-slider__value--max">
							<?php echo esc_html( wc_price( $current_max ) ); ?>
						</span>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render attribute filter (color, size, brand, etc.)
	 *
	 * @param string $attribute Attribute name (e.g., 'pa_color'). If empty, renders all attributes.
	 */
	public function render_attribute_filter( $attribute ) {
		// If no specific attribute, render all available product attributes
		if ( empty( $attribute ) ) {
			$this->render_all_attributes_filter();
			return;
		}

		// Get attribute terms
		$terms = get_terms(
			array(
				'taxonomy'   => $attribute,
				'hide_empty' => true,
			)
		);

		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return;
		}

		// Get attribute label
		$attribute_label = wc_attribute_label( $attribute );

		$active_filters = $this->manager->get_active_filters();
		$active_terms   = isset( $active_filters['attributes'][ $attribute ] ) ? $active_filters['attributes'][ $attribute ] : array();

		// Determine render style (checkboxes vs color swatches)
		$is_color = strpos( $attribute, 'color' ) !== false || strpos( $attribute, 'colour' ) !== false;
		?>
		<div class="mantiload-filter mantiload-filter--attribute mantiload-filter--<?php echo \esc_attr( $attribute ); ?>" data-filter-type="attribute" data-attribute="<?php echo \esc_attr( $attribute ); ?>">
			<h4 class="mantiload-filter__title">
				<?php echo \esc_html( $attribute_label ); ?>
			</h4>
			<div class="mantiload-filter__content">
				<?php if ( $is_color ) : ?>
					<div class="mantiload-color-swatches">
						<?php foreach ( $terms as $term ) : ?>
							<?php
							$is_active = in_array( $term->slug, $active_terms, true ) || in_array( $term->term_id, $active_terms, true );
							$color     = get_term_meta( $term->term_id, 'color', true );
							?>
							<label class="mantiload-color-swatch <?php echo $is_active ? 'is-active' : ''; ?>" title="<?php echo \esc_attr( $term->name ); ?>">
								<input
									type="checkbox"
									class="mantiload-filter-input"
									data-filter-type="attribute"
									data-attribute="<?php echo \esc_attr( $attribute ); ?>"
									data-filter-value="<?php echo \esc_attr( $term->slug ); ?>"
									value="<?php echo \esc_attr( $term->slug ); ?>"
									<?php checked( $is_active ); ?>
								/>
								<span class="mantiload-color-swatch__color" style="background-color: <?php echo \esc_attr( $color ?: $term->slug ); ?>"></span>
								<span class="mantiload-color-swatch__name"><?php echo \esc_html( $term->name ); ?></span>
							</label>
						<?php endforeach; ?>
					</div>
				<?php else : ?>
					<ul class="mantiload-filter-list">
						<?php foreach ( $terms as $term ) : ?>
							<?php
							$is_active = in_array( $term->slug, $active_terms, true ) || in_array( $term->term_id, $active_terms, true );
							?>
							<li class="mantiload-filter-item <?php echo $is_active ? 'is-active' : ''; ?>">
								<label class="mantiload-filter-label">
									<input
										type="checkbox"
										class="mantiload-filter-input"
										data-filter-type="attribute"
										data-attribute="<?php echo \esc_attr( $attribute ); ?>"
										data-filter-value="<?php echo \esc_attr( $term->slug ); ?>"
										value="<?php echo \esc_attr( $term->slug ); ?>"
										<?php checked( $is_active ); ?>
									/>
									<span class="mantiload-filter-name"><?php echo \esc_html( $term->name ); ?></span>
									<span class="mantiload-filter-count">(<?php echo \esc_html( $term->count ); ?>)</span>
								</label>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render all available product attributes as separate filter sections
	 */
	public function render_all_attributes_filter() {
		// Get all product attributes that have terms
		$attribute_taxonomies = wc_get_attribute_taxonomies();

		if ( empty( $attribute_taxonomies ) ) {
			return;
		}

		foreach ( $attribute_taxonomies as $attribute ) {
			$taxonomy = 'pa_' . $attribute->attribute_name;

			// Check if this attribute has any terms used in products
			$terms = get_terms(
				array(
					'taxonomy'   => $taxonomy,
					'hide_empty' => true,
					'number'     => 1, // Just check if any exist
				)
			);

			if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
				$this->render_attribute_filter( $taxonomy );
			}
		}
	}

	/**
	 * Render rating filter (star ratings)
	 */
	public function render_rating_filter() {
		$active_filters = $this->manager->get_active_filters();
		$active_rating  = isset( $active_filters['rating'] ) ? $active_filters['rating'] : 0;
		?>
		<div class="mantiload-filter mantiload-filter--rating" data-filter-type="rating">
			<h4 class="mantiload-filter__title">
				<?php esc_html_e( 'Customer Rating', 'mantiload' ); ?>
			</h4>
			<div class="mantiload-filter__content">
				<ul class="mantiload-filter-list">
					<?php for ( $rating = 5; $rating >= 1; $rating-- ) : ?>
						<?php $is_active = $active_rating === $rating; ?>
						<li class="mantiload-filter-item <?php echo $is_active ? 'is-active' : ''; ?>">
							<label class="mantiload-filter-label">
								<input
									type="radio"
									class="mantiload-filter-input"
									data-filter-type="rating"
									data-filter-value="<?php echo \esc_attr( $rating ); ?>"
									value="<?php echo \esc_attr( $rating ); ?>"
									name="mantiload_rating"
									<?php checked( $is_active ); ?>
								/>
								<span class="mantiload-filter-stars">
									<?php echo esc_html( str_repeat( '★', $rating ) . str_repeat( '☆', 5 - $rating ) ); ?>
								</span>
								<span class="mantiload-filter-name">
									<?php
									/* translators: %d: star rating */
									echo esc_html( sprintf( __( '%d & Up', 'mantiload' ), $rating ) );
									?>
								</span>
							</label>
						</li>
					<?php endfor; ?>
				</ul>
			</div>
		</div>
		<?php
	}

	/**
	 * Render stock status filter
	 */
	public function render_stock_filter() {
		$active_filters = $this->manager->get_active_filters();
		$active_stock   = isset( $active_filters['stock'] ) ? $active_filters['stock'] : '';

		$statuses = array(
			'instock'     => __( 'In Stock', 'mantiload' ),
			'outofstock'  => __( 'Out of Stock', 'mantiload' ),
			'onbackorder' => __( 'On Backorder', 'mantiload' ),
		);
		?>
		<div class="mantiload-filter mantiload-filter--stock" data-filter-type="stock">
			<h4 class="mantiload-filter__title">
				<?php esc_html_e( 'Availability', 'mantiload' ); ?>
			</h4>
			<div class="mantiload-filter__content">
				<ul class="mantiload-filter-list">
					<?php foreach ( $statuses as $status => $label ) : ?>
						<?php $is_active = $active_stock === $status; ?>
						<li class="mantiload-filter-item <?php echo $is_active ? 'is-active' : ''; ?>">
							<label class="mantiload-filter-label">
								<input
									type="radio"
									class="mantiload-filter-input"
									data-filter-type="stock"
									data-filter-value="<?php echo \esc_attr( $status ); ?>"
									value="<?php echo \esc_attr( $status ); ?>"
									name="mantiload_stock"
									<?php checked( $is_active ); ?>
								/>
								<span class="mantiload-filter-name"><?php echo \esc_html( $label ); ?></span>
							</label>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		</div>
		<?php
	}

	/**
	 * Render on sale filter (toggle)
	 */
	public function render_on_sale_filter() {
		$active_filters = $this->manager->get_active_filters();
		$is_active      = ! empty( $active_filters['on_sale'] );
		?>
		<div class="mantiload-filter mantiload-filter--on-sale" data-filter-type="on_sale">
			<label class="mantiload-filter-toggle">
				<input
					type="checkbox"
					class="mantiload-filter-input"
					data-filter-type="on_sale"
					data-filter-value="1"
					value="1"
					<?php checked( $is_active ); ?>
				/>
				<span class="mantiload-filter-name"><?php esc_html_e( 'On Sale', 'mantiload' ); ?></span>
			</label>
		</div>
		<?php
	}

	/**
	 * Render products HTML (used by AJAX)
	 *
	 * @param array $products Array of WP_Post objects.
	 * @return string
	 */
	public function render_products( $products ) {
		if ( empty( $products ) ) {
			return '<p class="woocommerce-info">' . esc_html__( 'No products found matching your selection.', 'mantiload' ) . '</p>';
		}

		ob_start();

		woocommerce_product_loop_start();

		foreach ( $products as $post ) {
			setup_postdata( $GLOBALS['post'] =& $post ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

			// Use WooCommerce template
			wc_get_template_part( 'content', 'product' );
		}

		wp_reset_postdata();

		woocommerce_product_loop_end();

		return ob_get_clean();
	}
}
