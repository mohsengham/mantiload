/**
 * MantiLoad Filters - Revolutionary AJAX filtering
 *
 * Fast code = Green code 🌱
 * Sub-50ms queries for a better planet
 *
 * @package MantiLoad
 */

(function($) {
	'use strict';

	/**
	 * MantiLoad Filters Class
	 */
	class MantiLoadFilters {
		constructor() {
			this.filters = {};
			this.isLoading = false;
			this.debounceTimer = null;
			this.cacheKey = 'mantiload_filters_cache';

			this.init();
		}

		/**
		 * Initialize filter system
		 */
		init() {
			this.log('Initializing MantiLoad Filters 🚀');

			// Bind event handlers
			this.bindEvents();

			// Parse initial filters from URL
			this.parseUrlFilters();

			// Load initial products if filters are active
			if (Object.keys(this.filters).length > 0) {
				this.applyFilters();
			}

			this.log('Filters initialized:', this.filters);
		}

		/**
		 * Bind event handlers
		 */
		bindEvents() {
			const self = this;

			// Filter input changes (checkboxes, radio)
			$(document).on('change', '.mantiload-filter-input', function(e) {
				self.handleFilterChange($(this));
			});

			// Price slider changes (with debounce)
			$(document).on('input', '.mantiload-price-slider__input', function(e) {
				self.handlePriceSliderChange($(this));
			});

			// Clear all filters
			$(document).on('click', '[data-action="clear-all"]', function(e) {
				e.preventDefault();
				self.clearAllFilters();
			});

			// Clear single filter
			$(document).on('click', '.mantiload-active-filter__remove', function(e) {
				e.preventDefault();
				const filterType = $(this).data('filter-type');
				const filterValue = $(this).data('filter-value');
				self.removeFilter(filterType, filterValue);
			});

			// Pagination clicks
			$(document).on('click', '.woocommerce-pagination a', function(e) {
				e.preventDefault();
				const page = self.getPageFromUrl($(this).attr('href'));
				self.applyFilters(page);
			});

			// Handle browser back/forward
			window.addEventListener('popstate', function(e) {
				if (e.state && e.state.filters) {
					self.filters = e.state.filters;
					self.applyFilters(e.state.page || 1, false);
				}
			});
		}

		/**
		 * Handle filter input change
		 */
		handleFilterChange($input) {
			const filterType = $input.data('filter-type');
			const filterValue = $input.data('filter-value');
			const attribute = $input.data('attribute');
			const isChecked = $input.is(':checked');

			this.log('Filter changed:', filterType, filterValue, isChecked);

			if (filterType === 'attribute') {
				this.updateAttributeFilter(attribute, filterValue, isChecked);
			} else if (filterType === 'category') {
				this.updateCategoryFilter(filterValue, isChecked);
			} else if (filterType === 'rating' || filterType === 'stock') {
				// Radio button - single selection
				if (isChecked) {
					this.filters[filterType] = filterValue;
				} else {
					delete this.filters[filterType];
				}
			} else if (filterType === 'on_sale') {
				if (isChecked) {
					this.filters.on_sale = '1';
				} else {
					delete this.filters.on_sale;
				}
			}

			// Apply filters immediately
			this.applyFilters();
		}

		/**
		 * Handle price slider change (with debounce)
		 */
		handlePriceSliderChange($input) {
			const self = this;
			const type = $input.hasClass('mantiload-price-slider__input--min') ? 'min' : 'max';
			const value = parseFloat($input.val());

			// Update displayed value
			const $value = $input.closest('.mantiload-price-slider').find(`.mantiload-price-slider__value--${type}`);
			// Format as currency (simplified - could use Intl.NumberFormat)
			$value.text(this.formatPrice(value));

			// Debounce the actual filter application
			clearTimeout(this.debounceTimer);
			this.debounceTimer = setTimeout(function() {
				if (!self.filters.price) {
					self.filters.price = {};
				}
				self.filters.price[type] = value;
				self.applyFilters();
			}, 300); // 300ms debounce
		}

		/**
		 * Update category filter
		 */
		updateCategoryFilter(categoryId, isChecked) {
			if (!this.filters.categories) {
				this.filters.categories = [];
			}

			categoryId = parseInt(categoryId);

			if (isChecked) {
				if (!this.filters.categories.includes(categoryId)) {
					this.filters.categories.push(categoryId);
				}
			} else {
				this.filters.categories = this.filters.categories.filter(id => id !== categoryId);
				if (this.filters.categories.length === 0) {
					delete this.filters.categories;
				}
			}
		}

		/**
		 * Update attribute filter
		 */
		updateAttributeFilter(attribute, value, isChecked) {
			if (!this.filters.attributes) {
				this.filters.attributes = {};
			}

			if (!this.filters.attributes[attribute]) {
				this.filters.attributes[attribute] = [];
			}

			if (isChecked) {
				if (!this.filters.attributes[attribute].includes(value)) {
					this.filters.attributes[attribute].push(value);
				}
			} else {
				this.filters.attributes[attribute] = this.filters.attributes[attribute].filter(v => v !== value);
				if (this.filters.attributes[attribute].length === 0) {
					delete this.filters.attributes[attribute];
				}
			}

			// Clean up empty attributes object
			if (Object.keys(this.filters.attributes).length === 0) {
				delete this.filters.attributes;
			}
		}

		/**
		 * Apply filters - Make AJAX request
		 */
		applyFilters(page = 1, updateHistory = true) {
			if (this.isLoading) {
				this.log('Already loading, skipping...');
				return;
			}

			this.isLoading = true;
			this.showLoading();

			const self = this;
			const startTime = performance.now();

			// Prepare AJAX data
			const data = {
				action: 'mantiload_filter_products',
				nonce: mantiloadFilters.nonce,
				page: page,
				per_page: this.getPerPage(),
				...this.filters
			};

			this.log('Applying filters:', data);

			// Make AJAX request
			$.ajax({
				url: mantiloadFilters.ajaxUrl,
				type: 'POST',
				data: data,
				success: function(response) {
					const endTime = performance.now();
					const queryTime = (endTime - startTime).toFixed(2);

					self.log(`✓ Filters applied in ${queryTime}ms`, response);

					if (response.success) {
						// Update products HTML
						self.updateProducts(response.data.html);

						// Update product count
						self.updateProductCount(response.data.total);

						// Update URL (SEO-friendly)
						if (updateHistory) {
							self.updateUrl(page);
						}

						// Show query time (if debug mode)
						if (mantiloadFilters.debugMode) {
							self.showQueryTime(response.data.query_time);
						}

						// Update active filters display
						self.updateActiveFilters();

						// Scroll to results
						self.scrollToResults();
					} else {
						self.showError(response.data.message || 'Failed to load products');
					}
				},
				error: function(xhr, status, error) {
					self.log('✗ AJAX error:', error);
					self.showError('Network error. Please try again.');
				},
				complete: function() {
					self.isLoading = false;
					self.hideLoading();
				}
			});
		}

		/**
		 * Clear all filters
		 */
		clearAllFilters() {
			this.filters = {};

			// Uncheck all inputs
			$('.mantiload-filter-input').prop('checked', false);

			// Reset price sliders
			$('.mantiload-price-slider__input--min').each(function() {
				$(this).val($(this).attr('min'));
			});
			$('.mantiload-price-slider__input--max').each(function() {
				$(this).val($(this).attr('max'));
			});

			// Apply (will load all products)
			this.applyFilters();
		}

		/**
		 * Remove single filter
		 */
		removeFilter(filterType, filterValue) {
			if (filterType === 'category' && this.filters.categories) {
				this.filters.categories = this.filters.categories.filter(id => id != filterValue);
				if (this.filters.categories.length === 0) {
					delete this.filters.categories;
				}
			} else if (filterType === 'attribute') {
				const [attribute, value] = filterValue.split(':');
				if (this.filters.attributes && this.filters.attributes[attribute]) {
					this.filters.attributes[attribute] = this.filters.attributes[attribute].filter(v => v !== value);
					if (this.filters.attributes[attribute].length === 0) {
						delete this.filters.attributes[attribute];
					}
				}
			} else {
				delete this.filters[filterType];
			}

			// Uncheck corresponding input
			$(`.mantiload-filter-input[data-filter-type="${filterType}"][data-filter-value="${filterValue}"]`).prop('checked', false);

			this.applyFilters();
		}

		/**
		 * Update products HTML
		 */
		updateProducts(html) {
			const $productsContainer = $('.products, ul.products');
			if ($productsContainer.length) {
				$productsContainer.fadeOut(200, function() {
					$(this).html(html).fadeIn(200);
				});
			}
		}

		/**
		 * Update product count display
		 */
		updateProductCount(count) {
			$('.mantiload-filters__count-number').text(count);
			$('.woocommerce-result-count').text(`Showing ${count} products`);
		}

		/**
		 * Update active filters display
		 */
		updateActiveFilters() {
			// TODO: Implement active filter chips UI
			if (Object.keys(this.filters).length > 0) {
				$('.mantiload-filters__clear').show();
			} else {
				$('.mantiload-filters__clear').hide();
			}
		}

		/**
		 * Update URL without page reload (SEO + UX)
		 */
		updateUrl(page) {
			const params = new URLSearchParams();

			// Add filters to URL
			if (this.filters.categories) {
				params.set('categories', this.filters.categories.join(','));
			}
			if (this.filters.price) {
				if (this.filters.price.min) params.set('min_price', this.filters.price.min);
				if (this.filters.price.max) params.set('max_price', this.filters.price.max);
			}
			if (this.filters.rating) {
				params.set('rating_filter', this.filters.rating);
			}
			if (this.filters.stock) {
				params.set('stock_status', this.filters.stock);
			}
			if (this.filters.on_sale) {
				params.set('on_sale', '1');
			}
			if (this.filters.attributes) {
				for (const [attribute, values] of Object.entries(this.filters.attributes)) {
					params.set(`filter_${attribute}`, values.join(','));
				}
			}
			if (page > 1) {
				params.set('paged', page);
			}

			// Build URL
			const url = params.toString() ? `${window.location.pathname}?${params.toString()}` : window.location.pathname;

			// Update browser history
			history.pushState({ filters: this.filters, page: page }, '', url);
		}

		/**
		 * Parse filters from URL
		 */
		parseUrlFilters() {
			const params = new URLSearchParams(window.location.search);

			if (params.has('categories')) {
				this.filters.categories = params.get('categories').split(',').map(id => parseInt(id));
			}
			if (params.has('min_price') || params.has('max_price')) {
				this.filters.price = {};
				if (params.has('min_price')) this.filters.price.min = parseFloat(params.get('min_price'));
				if (params.has('max_price')) this.filters.price.max = parseFloat(params.get('max_price'));
			}
			if (params.has('rating_filter')) {
				this.filters.rating = params.get('rating_filter');
			}
			if (params.has('stock_status')) {
				this.filters.stock = params.get('stock_status');
			}
			if (params.has('on_sale')) {
				this.filters.on_sale = '1';
			}

			// Parse attribute filters (filter_pa_color, filter_brand, etc.)
			for (const [key, value] of params) {
				if (key.startsWith('filter_')) {
					const attribute = key.replace('filter_', '');
					if (!this.filters.attributes) {
						this.filters.attributes = {};
					}
					this.filters.attributes[attribute] = value.split(',');
				}
			}
		}

		/**
		 * Show loading state
		 */
		showLoading() {
			$('.products, ul.products').addClass('is-loading').css('opacity', '0.5');
			$('body').addClass('mantiload-filtering');
		}

		/**
		 * Hide loading state
		 */
		hideLoading() {
			$('.products, ul.products').removeClass('is-loading').css('opacity', '1');
			$('body').removeClass('mantiload-filtering');
		}

		/**
		 * Show error message
		 */
		showError(message) {
			// TODO: Better error UI
			console.error('MantiLoad Filter Error:', message);
			alert(message);
		}

		/**
		 * Show query time (debug mode)
		 */
		showQueryTime(time) {
			// Query time is available for debugging if needed
		}

		/**
		 * Scroll to results after filtering
		 */
		scrollToResults() {
			const $results = $('.products, ul.products').first();
			if ($results.length) {
				$('html, body').animate({
					scrollTop: $results.offset().top - 100
				}, 300);
			}
		}

		/**
		 * Get page number from pagination URL
		 */
		getPageFromUrl(url) {
			const match = url.match(/[?&]paged=(\d+)/);
			return match ? parseInt(match[1]) : 1;
		}

		/**
		 * Get products per page
		 */
		getPerPage() {
			// Could be dynamic from settings
			return 12;
		}

		/**
		 * Format price (simple version)
		 */
		formatPrice(value) {
			// TODO: Use proper currency formatting from WooCommerce
			return value.toLocaleString();
		}

		/**
		 * Log (only in debug mode)
		 */
		log(...args) {
			if (mantiloadFilters.debugMode) {
				console.log('[MantiLoad Filters]', ...args);
			}
		}
	}

	/**
	 * Initialize on document ready
	 */
	$(function() {
		// Only initialize on shop/archive pages
		if ($('.mantiload-filters').length || $('.woocommerce-page').length) {
			window.mantiLoadFilters = new MantiLoadFilters();
		}
	});

})(jQuery);
