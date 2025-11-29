<?php
namespace MantiLoad\Widgets;
defined( 'ABSPATH' ) || exit;

class Popular_Searches_Widget extends \WP_Widget {
	public function __construct() {
		parent::__construct(
			'mantiload_popular',
			\esc_html__( 'MantiLoad Popular Searches', 'mantiload' ),
			array( 'description' => \esc_html__( 'Show popular search queries', 'mantiload' ) )
		);
	}
	
	public function widget( $args, $instance ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Widget args are escaped by WordPress core
		echo $args['before_widget'];
		if ( ! empty( $instance['title'] ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Widget args and apply_filters output are escaped by WordPress core
			echo $args['before_title'] . \apply_filters( 'widget_title', $instance['title'] ) . $args['after_title'];
		}

		$logs = \get_option( 'mantiload_search_logs', array() );
		if ( empty( $logs ) ) {
			echo '<p>' . \esc_html__( 'No searches yet', 'mantiload' ) . '</p>';
		} else {
			$queries = array_column( $logs, 'query' );
			$popular = array_count_values( $queries );
			arsort( $popular );
			$popular = array_slice( $popular, 0, 10, true );

			echo '<ul class="mantiload-popular-searches">';
			foreach ( $popular as $query => $count ) {
				$url = \home_url( '/?s=' . urlencode( $query ) );
				echo '<li><a href="' . \esc_url( $url ) . '">' . \esc_html( $query ) . '</a> <span class="count">(' . absint( $count ) . ')</span></li>';
			}
			echo '</ul>';
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Widget args are escaped by WordPress core
		echo $args['after_widget'];
	}
	
	public function form( $instance ) {
		$title = ! empty( $instance['title'] ) ? $instance['title'] : \esc_html__( 'Popular Searches', 'mantiload' );
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
