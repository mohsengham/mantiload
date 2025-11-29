<?php
/**
 * MantiLoad Admin Search Modal
 * The FIRST EVER instant admin search for WordPress!
 *
 * @package MantiLoad
 */

defined( 'ABSPATH' ) || exit;
?>

<div id="mantiload-admin-modal" class="mantiload-admin-modal">
	<div class="mantiload-admin-overlay"></div>

	<div class="mantiload-admin-search-container">
		<div class="mantiload-admin-search-header">
			<div class="mantiload-admin-search-icon">
				<svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
					<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
				</svg>
			</div>
			<input type="text"
				id="mantiload-admin-search-input"
				class="mantiload-admin-search-input"
				placeholder="Search products, orders, customers... (or type 'help' for shortcuts)"
				autocomplete="off"
				spellcheck="false">
			<button class="mantiload-admin-search-close" data-close-admin-modal>
				<svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
					<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
				</svg>
			</button>
		</div>

		<div class="mantiload-admin-search-filters">
			<button class="mantiload-admin-filter-btn active" data-filter="all">
				<span>All</span>
			</button>
			<button class="mantiload-admin-filter-btn" data-filter="products">
				<svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20">
					<path d="M3 1a1 1 0 000 2h1.22l.305 1.222a.997.997 0 00.01.042l1.358 5.43-.893.892C3.74 11.846 4.632 14 6.414 14H15a1 1 0 000-2H6.414l1-1H14a1 1 0 00.894-.553l3-6A1 1 0 0017 3H6.28l-.31-1.243A1 1 0 005 1H3zM16 16.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zM6.5 18a1.5 1.5 0 100-3 1.5 1.5 0 000 3z"/>
				</svg>
				<span>Products</span>
			</button>
			<button class="mantiload-admin-filter-btn" data-filter="orders">
				<svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20">
					<path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"/>
					<path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z"/>
				</svg>
				<span>Orders</span>
			</button>
			<button class="mantiload-admin-filter-btn" data-filter="customers">
				<svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20">
					<path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"/>
				</svg>
				<span>Customers</span>
			</button>
			<button class="mantiload-admin-filter-btn" data-filter="posts">
				<svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20">
					<path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z"/>
				</svg>
				<span>Posts</span>
			</button>
		</div>

		<div class="mantiload-admin-search-results-wrapper">
			<div class="mantiload-admin-search-loader" style="display: none;">
				<div class="mantiload-admin-spinner"></div>
				<p>Searching...</p>
			</div>

			<div id="mantiload-admin-search-results" class="mantiload-admin-search-results">
				<!-- Results will be inserted here via AJAX -->
			</div>

			<div class="mantiload-admin-search-footer">
				<div class="mantiload-admin-search-stats">
					<span id="mantiload-admin-stats"></span>
				</div>
				<div class="mantiload-admin-search-hints">
					<kbd>↑↓</kbd> Navigate
					<kbd>↵</kbd> Open
					<kbd>Esc</kbd> Close
				</div>
			</div>
		</div>
	</div>
</div>


