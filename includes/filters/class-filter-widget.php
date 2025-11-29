<?php
/**
 * MantiLoad Filters Widget
 *
 * @package MantiLoad
 * @subpackage Filters
 */

namespace MantiLoad\Filters;

use WP_Widget;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Filter Widget Class
 */
class Filter_Widget extends WP_Widget {

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct(
			'mantiload_filters_widget',
			__( 'MantiLoad Filters', 'mantiload' ),
			array(
				'description' => __( 'Revolutionary product filters powered by Manticore Search', 'mantiload' ),
			)
		);
	}

	/**
	 * Front-end display of widget
	 *
	 * @param array $args     Widget arguments.
	 * @param array $instance Saved values from database.
	 */
	public function widget( $args, $instance ) {
		// Only show on shop/archive pages
		if ( ! is_shop() && ! is_product_category() && ! is_product_tag() && ! is_product_taxonomy() ) {
			return;
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $args['before_widget'];

		if ( ! empty( $instance['title'] ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $args['before_title'] . \apply_filters( 'widget_title', $instance['title'] ) . $args['after_title'];
		}

		// Get filter types
		$filter_types = ! empty( $instance['filter_types'] ) ? $instance['filter_types'] : 'category,price,attribute,rating,stock,on_sale';

		// Render filters
		echo do_shortcode( "[mantiload_filters type='{$filter_types}' style='sidebar']" );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $args['after_widget'];
	}

	/**
	 * Back-end widget form
	 *
	 * @param array $instance Previously saved values from database.
	 */
	public function form( $instance ) {
		$title        = ! empty( $instance['title'] ) ? $instance['title'] : __( 'Filter Products', 'mantiload' );
		$filter_types = ! empty( $instance['filter_types'] ) ? $instance['filter_types'] : 'category,price,attribute,rating,stock,on_sale';
		?>
		<p>
			<label for="<?php echo \esc_attr( $this->get_field_id( 'title' ) ); ?>">
				<?php esc_html_e( 'Title:', 'mantiload' ); ?>
			</label>
			<input
				class="widefat"
				id="<?php echo \esc_attr( $this->get_field_id( 'title' ) ); ?>"
				name="<?php echo \esc_attr( $this->get_field_name( 'title' ) ); ?>"
				type="text"
				value="<?php echo \esc_attr( $title ); ?>"
			>
		</p>
		<p>
			<label for="<?php echo \esc_attr( $this->get_field_id( 'filter_types' ) ); ?>">
				<?php esc_html_e( 'Filter Types (comma-separated):', 'mantiload' ); ?>
			</label>
			<input
				class="widefat"
				id="<?php echo \esc_attr( $this->get_field_id( 'filter_types' ) ); ?>"
				name="<?php echo \esc_attr( $this->get_field_name( 'filter_types' ) ); ?>"
				type="text"
				value="<?php echo \esc_attr( $filter_types ); ?>"
			>
			<small><?php esc_html_e( 'Examples: category, price, pa_brand, pa_color, rating, stock, on_sale', 'mantiload' ); ?></small>
		</p>
		<?php
	}

	/**
	 * Sanitize widget form values as they are saved
	 *
	 * @param array $new_instance Values just sent to be saved.
	 * @param array $old_instance Previously saved values from database.
	 * @return array Updated safe values to be saved.
	 */
	public function update( $new_instance, $old_instance ) {
		$instance                 = array();
		$instance['title']        = ! empty( $new_instance['title'] ) ? \sanitize_text_field( $new_instance['title'] ) : '';
		$instance['filter_types'] = ! empty( $new_instance['filter_types'] ) ? \sanitize_text_field( $new_instance['filter_types'] ) : 'category,price,attribute,rating,stock,on_sale';

		return $instance;
	}
}
