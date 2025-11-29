<?php
/**
 * MantiLoad Search Box Template (for shortcode/widget)
 *
 * @package MantiLoad
 */

defined( 'ABSPATH' ) || exit;

$mantiload_post_types_json = esc_attr( wp_json_encode( $args['post_types'] ) );
$mantiload_unique_id = 'mantiload-search-' . uniqid();
$mantiload_inline_style = 'max-width: ' . esc_attr( $args['width'] ) . ';';
?>

 <div class="mantiload-search-box-inline <?php echo esc_attr( $args['class'] ); ?>"
     id="<?php echo esc_attr( $mantiload_unique_id ); ?>"
     data-post-types='<?php echo esc_attr( $mantiload_post_types_json ); ?>'
     data-view-all-text="<?php echo esc_attr( $args['view_all_text'] ); ?>"
     data-show-price="<?php echo $args['show_price'] ? '1' : '0'; ?>"
     data-show-stock="<?php echo $args['show_stock'] ? '1' : '0'; ?>"
     style="<?php echo esc_attr( $mantiload_inline_style ); ?>">
	<form class="mantiload-inline-form" role="search" action="<?php echo esc_url( home_url( '/' ) ); ?>" method="get">
		<div class="mantiload-inline-input-group">
			<?php if ( ! $args['show_button'] ) : ?>
			<span class="mantiload-inline-search-icon">
				<svg width="18" height="18" viewBox="0 0 20 20" fill="none">
					<path d="M9 17A8 8 0 1 0 9 1a8 8 0 0 0 0 16z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
					<path d="M19 19l-4.35-4.35" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
				</svg>
			</span>
			<?php endif; ?>

			<input
				type="search"
				name="s"
				class="mantiload-inline-search-input"
				placeholder="<?php echo esc_attr( $args['placeholder'] ); ?>"
				autocomplete="off"
				aria-label="<?php esc_attr_e( 'Search', 'mantiload' ); ?>"
			/>

			<input type="hidden" name="post_type" value="product" />

			<?php if ( empty( $args['hide_clear'] ) ) : ?>
			<button type="button" class="mantiload-inline-clear" aria-label="<?php esc_attr_e( 'Clear search', 'mantiload' ); ?>">
				<svg width="16" height="16" viewBox="0 0 20 20" fill="none">
					<path d="M15 5L5 15M5 5l10 10" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
				</svg>
			</button>
			<?php endif; ?>

			<?php if ( $args['show_button'] ) : ?>
				<button type="submit" class="mantiload-inline-submit-btn">
					<?php if ( ! empty( $args['button_icon'] ) ) : ?>
						<svg width="18" height="18" viewBox="0 0 20 20" fill="none">
							<path d="M9 17A8 8 0 1 0 9 1a8 8 0 0 0 0 16z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
							<path d="M19 19l-4.35-4.35" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
						</svg>
					<?php else : ?>
						<?php echo esc_html( $args['button_text'] ); ?>
					<?php endif; ?>
				</button>
			<?php endif; ?>

			<span class="mantiload-inline-loader" style="display: none;">
				<svg class="mantiload-inline-spinner" width="18" height="18" viewBox="0 0 20 20">
					<circle cx="10" cy="10" r="8" stroke="currentColor" stroke-width="2" fill="none" stroke-dasharray="50" stroke-linecap="round"/>
				</svg>
			</span>
		</div>

		<div class="mantiload-inline-results-dropdown" style="display: none;">
			<div class="mantiload-inline-results-container">
				<!-- Results will be injected here via AJAX -->
			</div>
		</div>
	</form>
</div>
