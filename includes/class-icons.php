<?php
/**
 * MantiLoad Icon Helper
 *
 * Provides icon functionality without external dependencies
 * Uses WordPress Dashicons and inline SVG for WordPress.org compliance
 */

namespace MantiLoad;

defined( 'ABSPATH' ) || exit;

class Icons {

	/**
	 * Get icon HTML
	 *
	 * @param string $icon Icon name (Lucide-style name)
	 * @param array  $args Icon arguments (style, class, width, height)
	 * @return string Icon HTML
	 */
	public static function get( $icon, $args = array() ) {
		$defaults = array(
			'style'  => '',
			'class'  => '',
			'width'  => '20',
			'height' => '20',
		);

		$args = wp_parse_args( $args, $defaults );

		// Map Lucide icons to WordPress Dashicons where possible
		$dashicon_map = array(
			'check-circle'   => 'dashicons-yes-alt',
			'alert-triangle' => 'dashicons-warning',
			'info'           => 'dashicons-info',
			'database'       => 'dashicons-database',
			'download'       => 'dashicons-download',
			'search'         => 'dashicons-search',
			'plus'           => 'dashicons-plus-alt',
			'plus-circle'    => 'dashicons-plus-alt2',
			'refresh-cw'     => 'dashicons-update',
			'trash-2'        => 'dashicons-trash',
			'settings'       => 'dashicons-admin-generic',
			'save'           => 'dashicons-saved',
			'filter'         => 'dashicons-filter',
			'list'           => 'dashicons-list-view',
			'inbox'          => 'dashicons-archive',
			'clock'          => 'dashicons-clock',
			'shopping-cart'  => 'dashicons-cart',
			'x-circle'       => 'dashicons-dismiss',
			'lightbulb'      => 'dashicons-lightbulb',
			'book-open'      => 'dashicons-book',
			'play-circle'    => 'dashicons-controls-play',
			'external-link'  => 'dashicons-external',
		);

		// Use Dashicon if available
		if ( isset( $dashicon_map[ $icon ] ) ) {
			$class = $dashicon_map[ $icon ];
			if ( ! empty( $args['class'] ) ) {
				$class .= ' ' . \esc_attr( $args['class'] );
			}

			return sprintf(
				'<span class="dashicons %s" style="%s"></span>',
				\esc_attr( $class ),
				\esc_attr( $args['style'] )
			);
		}

		// Otherwise use inline SVG
		return self::get_svg( $icon, $args );
	}

	/**
	 * Get inline SVG icon
	 *
	 * @param string $icon Icon name
	 * @param array  $args Icon arguments
	 * @return string SVG HTML
	 */
	private static function get_svg( $icon, $args ) {
		$width  = (int) $args['width'];
		$height = (int) $args['height'];
		$style  = $args['style'];
		$class  = $args['class'];

		$svg_icons = array(
			'trending-up'   => '<path d="M23 6l-9.5 9.5-5-5L1 18"/><polyline points="17 6 23 6 23 12"/>',
			'bar-chart-2'   => '<line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>',
			'bar-chart'     => '<line x1="12" y1="20" x2="12" y2="10"/><line x1="18" y1="20" x2="18" y2="4"/><line x1="6" y1="20" x2="6" y2="16"/>',
			'rocket'        => '<path d="M4.5 16.5c-1.5 1.26-2 5-2 5s3.74-.5 5-2c.71-.84.7-2.13-.09-2.91a2.18 2.18 0 0 0-2.91-.09z"/><path d="m12 15-3-3a22 22 0 0 1 2-3.95A12.88 12.88 0 0 1 22 2c0 2.72-.78 7.5-6 11a22.35 22.35 0 0 1-4 2z"/><path d="M9 12H4s.55-3.03 2-4c1.62-1.08 5 0 5 0"/><path d="M12 15v5s3.03-.55 4-2c1.08-1.62 0-5 0-5"/>',
			'zap'           => '<polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>',
			'file-text'     => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/>',
			'sparkles'      => '<path d="m12 3-1.912 5.813a2 2 0 0 1-1.275 1.275L3 12l5.813 1.912a2 2 0 0 1 1.275 1.275L12 21l1.912-5.813a2 2 0 0 1 1.275-1.275L21 12l-5.813-1.912a2 2 0 0 1-1.275-1.275L12 3Z"/><path d="M5 3v4"/><path d="M19 17v4"/><path d="M3 5h4"/><path d="M17 19h4"/>',
			'target'        => '<circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/>',
			'activity'      => '<polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>',
			'type'          => '<polyline points="4 7 4 4 20 4 20 7"/><line x1="9" y1="20" x2="15" y2="20"/><line x1="12" y1="4" x2="12" y2="20"/>',
			'play'          => '<polygon points="5 3 19 12 5 21 5 3"/>',
			'square'        => '<rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>',
			'plus-square'   => '<rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/>',
		);

		if ( ! isset( $svg_icons[ $icon ] ) ) {
			// Return empty span for unknown icons
			return '<span class="mantiload-icon-placeholder"></span>';
		}

		return sprintf(
			'<svg class="mantiload-icon mantiload-icon-%s %s" xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="%s">%s</svg>',
			\esc_attr( $icon ),
			\esc_attr( $class ),
			$width,
			$height,
			\esc_attr( $style ),
			$svg_icons[ $icon ] // SVG paths are safe, from internal array
		);
	}

	/**
	 * Replace Lucide icons in HTML
	 *
	 * @param string $html HTML content with data-lucide attributes
	 * @return string HTML with icons replaced
	 */
	public static function replace_in_html( $html ) {
		return preg_replace_callback(
			'/<i\s+data-lucide="([^"]+)"([^>]*)><\/i>/',
			function( $matches ) {
				$icon_name = $matches[1];
				$attributes = $matches[2];

				// Extract style attribute
				$style = '';
				if ( preg_match( '/style="([^"]*)"/', $attributes, $style_match ) ) {
					$style = $style_match[1];
				}

				// Extract width/height
				$width = 20;
				$height = 20;
				if ( preg_match( '/width:\s*(\d+)px/', $style, $width_match ) ) {
					$width = $width_match[1];
					$height = $width_match[1];
				}

				return self::get( $icon_name, array(
					'style'  => $style,
					'width'  => $width,
					'height' => $height,
				) );
			},
			$html
		);
	}
}
