<?php
namespace MantiLoad\Widgets;
defined( 'ABSPATH' ) || exit;

class Search_Widget extends \WP_Widget {
	public function __construct() {
		parent::__construct(
			'mantiload_search',
			\esc_html__( 'MantiLoad Search', 'mantiload' ),
			array( 'description' => \esc_html__( 'Ultra-fast search powered by Manticore', 'mantiload' ) )
		);
	}
	
	public function widget( $args, $instance ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Widget args are escaped by WordPress core
		echo $args['before_widget'];
		if ( ! empty( $instance['title'] ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Widget args and apply_filters output are escaped by WordPress core
			echo $args['before_title'] . \apply_filters( 'widget_title', $instance['title'] ) . $args['after_title'];
		}
		?>
		<form role="search" method="get" class="mantiload-search-form" action="<?php echo \esc_url( \home_url( '/' ) ); ?>">
			<input type="search" class="search-field" placeholder="<?php echo esc_attr_x( 'Search...', 'placeholder', 'mantiload' ); ?>" value="<?php echo get_search_query(); ?>" name="s" />
			<button type="submit" class="search-submit">🔍</button>
		</form>
		<?php
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Widget args are escaped by WordPress core
		echo $args['after_widget'];
	}
	
	public function form( $instance ) {
		$title = ! empty( $instance['title'] ) ? $instance['title'] : \esc_html__( 'Search', 'mantiload' );
		?>
		<p>
			<label for="<?php echo \esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php \esc_html_e( 'Title:', 'mantiload' ); ?></label> 
			<input class="widefat" id="<?php echo \esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo \esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo \esc_attr( $title ); ?>">
		</p>
		<?php 
	}
	
	public function update( $new_instance, $old_instance ) {
		$instance = array();
		$instance['title'] = ( ! empty( $new_instance['title'] ) ) ? \sanitize_text_field( $new_instance['title'] ) : '';
		return $instance;
	}
}
