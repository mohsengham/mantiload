<?php
/**
 * MantiLoad Search Widget
 *
 * @package MantiLoad
 */

namespace MantiLoad\Widgets;

defined( 'ABSPATH' ) || exit;

/**
 * Search_Widget class
 */
class Search_Widget extends \WP_Widget {

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct(
			'mantiload_search_widget',
			__( 'MantiLoad Search', 'mantiload' ),
			array(
				'description' => __( 'Ultra-fast AJAX search powered by Manticore', 'mantiload' ),
				'classname'   => 'mantiload-search-widget',
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
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Widget args are escaped by WordPress core
		echo $args['before_widget'];

		if ( ! empty( $instance['title'] ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Widget args and apply_filters output are escaped by WordPress core
			echo $args['before_title'] . \apply_filters( 'widget_title', $instance['title'] ) . $args['after_title'];
		}

		$search_args = array(
			'placeholder'  => ! empty( $instance['placeholder'] ) ? $instance['placeholder'] : __( 'Search products...', 'mantiload' ),
			'post_types'   => ! empty( $instance['post_types'] ) ? $instance['post_types'] : array( 'product' ),
			'show_button'  => isset( $instance['show_button'] ) ? (bool) $instance['show_button'] : true,
			'button_text'  => ! empty( $instance['button_text'] ) ? $instance['button_text'] : __( 'Search', 'mantiload' ),
			'class'        => 'mantiload-widget-search',
		);

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_search_box() method handles its own escaping
		echo \MantiLoad\Search\AJAX_Search::get_search_box( $search_args );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Widget args are escaped by WordPress core
		echo $args['after_widget'];
	}

	/**
	 * Back-end widget form
	 *
	 * @param array $instance Previously saved values from database.
	 */
	public function form( $instance ) {
		$title = ! empty( $instance['title'] ) ? $instance['title'] : '';
		$placeholder = ! empty( $instance['placeholder'] ) ? $instance['placeholder'] : __( 'Search products...', 'mantiload' );
		$show_button = isset( $instance['show_button'] ) ? (bool) $instance['show_button'] : true;
		$button_text = ! empty( $instance['button_text'] ) ? $instance['button_text'] : __( 'Search', 'mantiload' );

		$post_types = ! empty( $instance['post_types'] ) ? $instance['post_types'] : array( 'product' );
		if ( ! is_array( $post_types ) ) {
			$post_types = array( $post_types );
		}

		$available_post_types = get_post_types( array( 'public' => true ), 'objects' );
		?>
		<p>
			<label for="<?php echo \esc_attr( $this->get_field_id( 'title' ) ); ?>">
				<?php esc_html_e( 'Title:', 'mantiload' ); ?>
			</label>
			<input class="widefat" id="<?php echo \esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo \esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo \esc_attr( $title ); ?>">
		</p>

		<p>
			<label for="<?php echo \esc_attr( $this->get_field_id( 'placeholder' ) ); ?>">
				<?php esc_html_e( 'Placeholder Text:', 'mantiload' ); ?>
			</label>
			<input class="widefat" id="<?php echo \esc_attr( $this->get_field_id( 'placeholder' ) ); ?>" name="<?php echo \esc_attr( $this->get_field_name( 'placeholder' ) ); ?>" type="text" value="<?php echo \esc_attr( $placeholder ); ?>">
		</p>

		<p>
			<label><?php esc_html_e( 'Search Post Types:', 'mantiload' ); ?></label><br>
			<?php foreach ( $available_post_types as $post_type ) : ?>
				<label style="display: block; margin: 5px 0;">
					<input type="checkbox" name="<?php echo \esc_attr( $this->get_field_name( 'post_types' ) ); ?>[]" value="<?php echo \esc_attr( $post_type->name ); ?>" <?php checked( in_array( $post_type->name, $post_types ) ); ?>>
					<?php echo \esc_html( $post_type->label ); ?>
				</label>
			<?php endforeach; ?>
		</p>

		<p>
			<label>
				<input type="checkbox" id="<?php echo \esc_attr( $this->get_field_id( 'show_button' ) ); ?>" name="<?php echo \esc_attr( $this->get_field_name( 'show_button' ) ); ?>" <?php checked( $show_button ); ?>>
				<?php esc_html_e( 'Show search button', 'mantiload' ); ?>
			</label>
		</p>

		<p>
			<label for="<?php echo \esc_attr( $this->get_field_id( 'button_text' ) ); ?>">
				<?php esc_html_e( 'Button Text:', 'mantiload' ); ?>
			</label>
			<input class="widefat" id="<?php echo \esc_attr( $this->get_field_id( 'button_text' ) ); ?>" name="<?php echo \esc_attr( $this->get_field_name( 'button_text' ) ); ?>" type="text" value="<?php echo \esc_attr( $button_text ); ?>">
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
		$instance = array();

		$instance['title'] = ! empty( $new_instance['title'] ) ? \sanitize_text_field( $new_instance['title'] ) : '';
		$instance['placeholder'] = ! empty( $new_instance['placeholder'] ) ? \sanitize_text_field( $new_instance['placeholder'] ) : '';
		$instance['show_button'] = isset( $new_instance['show_button'] );
		$instance['button_text'] = ! empty( $new_instance['button_text'] ) ? \sanitize_text_field( $new_instance['button_text'] ) : '';

		$instance['post_types'] = array();
		if ( ! empty( $new_instance['post_types'] ) && is_array( $new_instance['post_types'] ) ) {
			$instance['post_types'] = array_map( 'sanitize_text_field', $new_instance['post_types'] );
		}

		return $instance;
	}
}

/**
 * Register widget
 */
function register_search_widget() {
	register_widget( 'MantiLoad\Widgets\Search_Widget' );
}
add_action( 'widgets_init', 'MantiLoad\Widgets\register_search_widget' );
