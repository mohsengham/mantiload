<?php
/**
 * MantiLoad Search Modal Template
 *
 * @package MantiLoad
 */

defined( 'ABSPATH' ) || exit;
?>

<div id="mantiload-search-modal" class="mantiload-modal" role="dialog" aria-modal="true" aria-labelledby="mantiload-search-title">
	<div class="mantiload-modal-overlay" data-close-modal></div>

	<div class="mantiload-modal-content">
		<div class="mantiload-modal-header">
			<h2 id="mantiload-search-title" class="mantiload-sr-only"><?php esc_html_e( 'Search', 'mantiload' ); ?></h2>
			<button type="button" class="mantiload-modal-close" data-close-modal aria-label="<?php esc_attr_e( 'Close search', 'mantiload' ); ?>">
				<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
					<line x1="18" y1="6" x2="6" y2="18"></line>
					<line x1="6" y1="6" x2="18" y2="18"></line>
				</svg>
			</button>
		</div>

		<div class="mantiload-search-form-wrapper">
			<form class="mantiload-search-form" role="search">
				<div class="mantiload-search-input-wrapper">
					<svg class="mantiload-search-icon" width="20" height="20" viewBox="0 0 20 20" fill="none">
						<path d="M9 17A8 8 0 1 0 9 1a8 8 0 0 0 0 16z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
						<path d="M19 19l-4.35-4.35" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
					</svg>

					<input
						type="search"
						id="mantiload-search-input"
						class="mantiload-search-input"
						placeholder="<?php echo esc_attr( \MantiLoad\MantiLoad::get_option( 'search_placeholder', __( 'Search products...', 'mantiload' ) ) ); ?>"
						autocomplete="off"
						autocorrect="off"
						autocapitalize="off"
						spellcheck="false"
						aria-label="<?php esc_attr_e( 'Search', 'mantiload' ); ?>"
						aria-autocomplete="list"
						aria-controls="mantiload-search-results"
						aria-expanded="false"
					/>

					<button type="button" class="mantiload-search-clear" aria-label="<?php esc_attr_e( 'Clear search', 'mantiload' ); ?>">
						<svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
							<path d="M8 0a8 8 0 1 0 0 16A8 8 0 0 0 8 0zm3.5 10.5l-1 1L8 9l-2.5 2.5-1-1L7 8 4.5 5.5l1-1L8 7l2.5-2.5 1 1L9 8l2.5 2.5z"/>
						</svg>
					</button>

					<div class="mantiload-search-loader">
						<div class="mantiload-spinner"></div>
					</div>
				</div>

				<div class="mantiload-search-stats">
					<span class="mantiload-results-count"></span>
					<span class="mantiload-query-time"></span>
				</div>
			</form>
		</div>

		<div id="mantiload-search-results" class="mantiload-search-results" role="listbox" aria-label="<?php esc_attr_e( 'Search results', 'mantiload' ); ?>">
			<!-- Results will be injected here via JavaScript -->
		</div>

		<div class="mantiload-search-footer">
			<div class="mantiload-keyboard-shortcuts">
				<kbd>↑↓</kbd> <span><?php esc_html_e( 'Navigate', 'mantiload' ); ?></span>
				<kbd>Enter</kbd> <span><?php esc_html_e( 'Select', 'mantiload' ); ?></span>
				<kbd>Esc</kbd> <span><?php esc_html_e( 'Close', 'mantiload' ); ?></span>
			</div>
			<div class="mantiload-powered-by">
				<?php esc_html_e( 'Powered by', 'mantiload' ); ?> <strong>MantiLoad</strong>
			</div>
		</div>
	</div>
</div>

<!-- Search trigger button (can be customized) -->
<button
	type="button"
	class="mantiload-search-trigger"
	data-open-search
	aria-label="<?php esc_attr_e( 'Open search', 'mantiload' ); ?>"
>
	<svg width="20" height="20" viewBox="0 0 20 20" fill="none">
		<path d="M9 17A8 8 0 1 0 9 1a8 8 0 0 0 0 16z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
		<path d="M19 19l-4.35-4.35" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
	</svg>
	<span><?php esc_html_e( 'Search', 'mantiload' ); ?></span>
	<kbd class="mantiload-shortcut-hint">⌘K</kbd>
</button>
