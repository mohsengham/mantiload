<?php
/**
 * MantiLoad Search Icon Widget
 *
 * Perfect for mobile menus - displays a clean search icon that opens the MantiLoad search modal
 *
 * @package MantiLoad
 */

namespace MantiLoad\Widgets;

defined( 'ABSPATH' ) || exit;

/**
 * Search_Icon_Widget class
 *
 * Displays a mobile-friendly search icon that triggers the MantiLoad search modal
 */
class Search_Icon_Widget extends \WP_Widget {

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct(
			'mantiload_search_icon',
			__( 'MantiLoad Search Icon', 'mantiload' ),
			array(
				'description' => __( 'Mobile-friendly search icon that opens MantiLoad search box (perfect for mobile menus)', 'mantiload' ),
				'classname' => 'mantiload-search-icon-widget',
			)
		);
	}

	/**
	 * Output widget
	 *
	 * @param array $args Widget arguments
	 * @param array $instance Widget instance
	 */
	public function widget( $args, $instance ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Widget args are escaped by WordPress core
		echo $args['before_widget'];

		// Get settings
		$icon_only = ! empty( $instance['icon_only'] ) ? $instance['icon_only'] : false;
		$show_label = ! empty( $instance['show_label'] ) ? $instance['show_label'] : true;
		$custom_label = ! empty( $instance['label'] ) ? $instance['label'] : __( 'Search', 'mantiload' );
		$icon_size = ! empty( $instance['icon_size'] ) ? $instance['icon_size'] : 'medium';
		$icon_style = ! empty( $instance['icon_style'] ) ? $instance['icon_style'] : 'default';

		// Size classes
		$size_class = 'mantiload-icon-' . sanitize_html_class( $icon_size );
		$style_class = 'mantiload-icon-style-' . sanitize_html_class( $icon_style );

		// Generate unique ID
		$unique_id = 'mantiload-icon-widget-' . uniqid();

		?>
		<div class="mantiload-search-icon-wrapper">
			<button
				type="button"
				class="mantiload-search-icon-trigger <?php echo \esc_attr( $size_class ); ?> <?php echo \esc_attr( $style_class ); ?>"
				data-toggle-search="<?php echo \esc_attr( $unique_id ); ?>"
				aria-label="<?php esc_attr_e( 'Open search', 'mantiload' ); ?>"
				title="<?php esc_attr_e( 'Search products', 'mantiload' ); ?>"
			>
				<svg class="mantiload-search-icon-svg" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
					<path d="M9 17A8 8 0 1 0 9 1a8 8 0 0 0 0 16z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
					<path d="M19 19l-4.35-4.35" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
				</svg>
				<?php if ( $show_label && ! $icon_only ) : ?>
					<span class="mantiload-search-icon-label"><?php echo \esc_html( $custom_label ); ?></span>
				<?php endif; ?>
			</button>

			<div id="<?php echo \esc_attr( $unique_id ); ?>" class="mantiload-icon-search-container">
				<?php
				// Output the EXISTING working search box with 100% width
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_search_box() method handles its own escaping
				echo \MantiLoad\Search\AJAX_Search::get_search_box( array(
					'placeholder'  => esc_attr__( 'Search products...', 'mantiload' ),
					'post_types'   => array( 'product' ),
					'show_button'  => false,
					'width'        => '100%',
					'class'        => 'mantiload-icon-search-box',
				) );
				?>
			</div>
		</div>
		<?php

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Widget args are escaped by WordPress core
		echo $args['after_widget'];
	}

	/**
	 * Widget form in admin
	 *
	 * @param array $instance Widget instance
	 */
	public function form( $instance ) {
		$icon_only = ! empty( $instance['icon_only'] ) ? (bool) $instance['icon_only'] : false;
		$show_label = ! empty( $instance['show_label'] ) ? (bool) $instance['show_label'] : true;
		$label = ! empty( $instance['label'] ) ? $instance['label'] : __( 'Search', 'mantiload' );
		$icon_size = ! empty( $instance['icon_size'] ) ? $instance['icon_size'] : 'medium';
		$icon_style = ! empty( $instance['icon_style'] ) ? $instance['icon_style'] : 'default';
		?>
		<p>
			<label for="<?php echo \esc_attr( $this->get_field_id( 'icon_only' ) ); ?>">
				<input
					type="checkbox"
					id="<?php echo \esc_attr( $this->get_field_id( 'icon_only' ) ); ?>"
					name="<?php echo \esc_attr( $this->get_field_name( 'icon_only' ) ); ?>"
					value="1"
					<?php checked( $icon_only ); ?>
				/>
				<?php esc_html_e( 'Icon only (no label)', 'mantiload' ); ?>
			</label>
			<br><small><?php esc_html_e( 'Perfect for mobile menus and navigation bars', 'mantiload' ); ?></small>
		</p>

		<?php if ( ! $icon_only ) : ?>
		<p>
			<label for="<?php echo \esc_attr( $this->get_field_id( 'show_label' ) ); ?>">
				<input
					type="checkbox"
					id="<?php echo \esc_attr( $this->get_field_id( 'show_label' ) ); ?>"
					name="<?php echo \esc_attr( $this->get_field_name( 'show_label' ) ); ?>"
					value="1"
					<?php checked( $show_label ); ?>
				/>
				<?php esc_html_e( 'Show text label next to icon', 'mantiload' ); ?>
			</label>
		</p>

		<p>
			<label for="<?php echo \esc_attr( $this->get_field_id( 'label' ) ); ?>">
				<?php esc_html_e( 'Label text:', 'mantiload' ); ?>
			</label>
			<input
				class="widefat"
				id="<?php echo \esc_attr( $this->get_field_id( 'label' ) ); ?>"
				name="<?php echo \esc_attr( $this->get_field_name( 'label' ) ); ?>"
				type="text"
				value="<?php echo \esc_attr( $label ); ?>"
			/>
		</p>
		<?php endif; ?>

		<p>
			<label for="<?php echo \esc_attr( $this->get_field_id( 'icon_size' ) ); ?>">
				<?php esc_html_e( 'Icon size:', 'mantiload' ); ?>
			</label>
			<select
				class="widefat"
				id="<?php echo \esc_attr( $this->get_field_id( 'icon_size' ) ); ?>"
				name="<?php echo \esc_attr( $this->get_field_name( 'icon_size' ) ); ?>"
			>
				<option value="small" <?php selected( $icon_size, 'small' ); ?>><?php esc_html_e( 'Small (20px)', 'mantiload' ); ?></option>
				<option value="medium" <?php selected( $icon_size, 'medium' ); ?>><?php esc_html_e( 'Medium (24px)', 'mantiload' ); ?></option>
				<option value="large" <?php selected( $icon_size, 'large' ); ?>><?php esc_html_e( 'Large (32px)', 'mantiload' ); ?></option>
			</select>
		</p>

		<p>
			<label for="<?php echo \esc_attr( $this->get_field_id( 'icon_style' ) ); ?>">
				<?php esc_html_e( 'Icon style:', 'mantiload' ); ?>
			</label>
			<select
				class="widefat"
				id="<?php echo \esc_attr( $this->get_field_id( 'icon_style' ) ); ?>"
				name="<?php echo \esc_attr( $this->get_field_name( 'icon_style' ) ); ?>"
			>
				<option value="default" <?php selected( $icon_style, 'default' ); ?>><?php esc_html_e( 'Default (no background)', 'mantiload' ); ?></option>
				<option value="circle" <?php selected( $icon_style, 'circle' ); ?>><?php esc_html_e( 'Circle background', 'mantiload' ); ?></option>
				<option value="rounded" <?php selected( $icon_style, 'rounded' ); ?>><?php esc_html_e( 'Rounded square', 'mantiload' ); ?></option>
			</select>
		</p>

		<p>
			<small>
				<strong><?php esc_html_e( 'Usage:', 'mantiload' ); ?></strong><br>
				<?php esc_html_e( 'Add this widget to your mobile menu or header. When clicked, it opens the MantiLoad search modal.', 'mantiload' ); ?>
			</small>
		</p>
		<?php
	}

	/**
	 * Update widget settings
	 *
	 * @param array $new_instance New settings
	 * @param array $old_instance Old settings
	 * @return array Updated settings
	 */
	public function update( $new_instance, $old_instance ) {
		$instance = array();
		$instance['icon_only'] = ! empty( $new_instance['icon_only'] ) ? 1 : 0;
		$instance['show_label'] = ! empty( $new_instance['show_label'] ) ? 1 : 0;
		$instance['label'] = ! empty( $new_instance['label'] ) ? \sanitize_text_field( $new_instance['label'] ) : __( 'Search', 'mantiload' );
		$instance['icon_size'] = ! empty( $new_instance['icon_size'] ) ? sanitize_key( $new_instance['icon_size'] ) : 'medium';
		$instance['icon_style'] = ! empty( $new_instance['icon_style'] ) ? sanitize_key( $new_instance['icon_style'] ) : 'default';
		return $instance;
	}
}
