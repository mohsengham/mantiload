/**
 * MantiLoad AJAX Search
 * Ultra-fast instant search with keyboard navigation
 *
 * @package MantiLoad
 * @version 1.0.0
 */

(function($) {
    'use strict';

    class MantiLoadSearch {
        constructor() {
            this.modal = $('#mantiload-search-modal');
            this.input = $('#mantiload-search-input');
            this.results = $('#mantiload-search-results');
            this.clearBtn = $('.mantiload-search-clear');
            this.loader = $('.mantiload-search-loader');
            this.statsCount = $('.mantiload-results-count');
            this.statsTime = $('.mantiload-query-time');

            this.searchTimeout = null;
            this.selectedIndex = -1;
            this.currentQuery = '';
            this.cache = {};
            this.activeRequest = null; // Track active AJAX request for cancellation
            this.requestCounter = 0; // Track request order to ignore stale responses

            this.init();
        }

        init() {
            this.bindEvents();
            this.setupKeyboardShortcuts();
        }

        bindEvents() {
            // Open modal
            $(document).on('click', '[data-open-search]', () => this.openModal());

            // Close modal
            $(document).on('click', '[data-close-modal]', () => this.closeModal());
            $(document).on('click', '.mantiload-modal-overlay', () => this.closeModal());

            // Handle search icon clicks
            $(document).on('click', '[data-toggle-search]', (e) => this.handleIconClick(e));

            // Search input
            this.input.on('input', (e) => this.handleInput(e));
            this.input.on('keydown', (e) => this.handleKeyDown(e));

            // Clear button
            this.clearBtn.on('click', () => this.clearSearch());

            // Result clicks
            $(document).on('click', '.mantiload-result-item', (e) => this.handleResultClick(e));
        }

        setupKeyboardShortcuts() {
            $(document).on('keydown', (e) => {
                // Configurable keyboard shortcut to open search
                if (this.checkKeyboardShortcut(e, mantiloadSearch.keyboardShortcut)) {
                    e.preventDefault();
                    this.openModal();
                }

                // Escape to close
                if (e.key === 'Escape' && this.modal.hasClass('is-active')) {
                    this.closeModal();
                }
            });
        }

        checkKeyboardShortcut(e, shortcut) {
            if (!shortcut) return false;

            const parts = shortcut.toLowerCase().split('+');
            let requireCtrl = false, requireAlt = false, requireShift = false, requireKey = '';

            parts.forEach(part => {
                if (part === 'ctrl') requireCtrl = true;
                else if (part === 'alt') requireAlt = true;
                else if (part === 'shift') requireShift = true;
                else requireKey = part;
            });

            // Handle special keys
            const keyPressed = e.key.toLowerCase();
            const matchKey = requireKey === 'space' ? ' ' : requireKey;

            return (
                (requireCtrl ? (e.ctrlKey || e.metaKey) : !e.ctrlKey && !e.metaKey) &&
                (requireAlt ? e.altKey : !e.altKey) &&
                (requireShift ? e.shiftKey : !e.shiftKey) &&
                keyPressed === matchKey
            );
        }

        openModal() {
            this.modal.addClass('is-active');
            $('body').css('overflow', 'hidden');

            // Focus input after animation
            setTimeout(() => {
                this.input.focus();
            }, 100);

            // Track opening
            this.trackEvent('search_modal_opened');
        }

        closeModal() {
            this.modal.removeClass('is-active');
            this.clearSearch();
        }

        handleIconClick(e) {
            const $btn = $(e.currentTarget);
            const targetId = $btn.data('toggle-search');
            const isFullscreen = $btn.data('fullscreen') === 'true';

            if (isFullscreen) {
                this.openFullscreen(targetId);
            } else {
                // Toggle the inline container
                const $container = $('#' + targetId);
                $container.slideToggle();
            }
        }

        openFullscreen(targetId) {
            // Create fullscreen overlay
            const $overlay = $('<div class="mantiload-fullscreen-overlay"></div>');
            const $container = $('#' + targetId).clone().removeAttr('id').addClass('mantiload-fullscreen-content');

            $overlay.append($container);
            $('body').append($overlay);

            // Show with animation
            setTimeout(() => {
                $overlay.addClass('is-active');
            }, 10);

            // Close on overlay click or ESC
            $overlay.on('click', (e) => {
                if (e.target === e.currentTarget) {
                    this.closeFullscreen($overlay);
                }
            });

            $(document).on('keydown.fullscreen', (e) => {
                if (e.keyCode === 27) { // ESC
                    this.closeFullscreen($overlay);
                }
            });
        }

        closeFullscreen($overlay) {
            $overlay.removeClass('is-active');
            setTimeout(() => {
                $overlay.remove();
            }, 300);
            $(document).off('keydown.fullscreen');
        }

        handleInput(e) {
            // Clear previous timeout
            clearTimeout(this.searchTimeout);

            // Debounce search - get the current value when timeout executes, not when it's set
            this.searchTimeout = setTimeout(() => {
                const query = this.input.val().trim();

                // Hide clear button if empty
                if (!query) {
                    this.clearBtn.hide();
                    this.results.empty();
                    this.hideStats();
                    return;
                }

                this.clearBtn.show();

                // Check minimum characters
                if (query.length < mantiloadSearch.minChars) {
                    this.showMessage(
                        mantiloadSearch.strings.pressEnter.replace('{min}', mantiloadSearch.minChars)
                    );
                    return;
                }

                // Check cache first
                if (this.cache[query]) {
                    this.renderResults(this.cache[query]);
                    return;
                }

                this.performSearch(query);
            }, mantiloadSearch.searchDelay);
        }

        handleKeyDown(e) {
            const items = $('.mantiload-result-item');

            switch(e.key) {
                case 'ArrowDown':
                    e.preventDefault();
                    this.selectedIndex = Math.min(this.selectedIndex + 1, items.length - 1);
                    this.updateSelection(items);
                    break;

                case 'ArrowUp':
                    e.preventDefault();
                    this.selectedIndex = Math.max(this.selectedIndex - 1, -1);
                    this.updateSelection(items);
                    break;

                case 'Enter':
                    e.preventDefault();
                    if (this.selectedIndex >= 0 && items.length > 0) {
                        items.eq(this.selectedIndex)[0].click();
                    } // Removed redirect to search page to avoid WooCommerce filter queries
                    break;
            }
        }

        updateSelection(items) {
            items.removeClass('is-selected');

            if (this.selectedIndex >= 0) {
                const selected = items.eq(this.selectedIndex);
                selected.addClass('is-selected');

                // Scroll into view
                const container = this.results[0];
                const element = selected[0];
                const elementTop = element.offsetTop;
                const elementBottom = elementTop + element.offsetHeight;
                const containerTop = container.scrollTop;
                const containerBottom = containerTop + container.clientHeight;

                if (elementTop < containerTop) {
                    container.scrollTop = elementTop;
                } else if (elementBottom > containerBottom) {
                    container.scrollTop = elementBottom - container.clientHeight;
                }
            }
        }

        performSearch(query) {
            // ABORT previous request if still running (prevents race conditions)
            if (this.activeRequest) {
                this.activeRequest.abort();
            }

            this.currentQuery = query;
            this.requestCounter++; // Increment request ID
            const currentRequestId = this.requestCounter;
            this.showLoader();

            const data = {
                action: 'mantiload_search',
                nonce: mantiloadSearch.nonce,
                q: query,
                post_types: mantiloadSearch.postTypes
            };

            // Store the active request so we can abort it
            this.activeRequest = $.ajax({
                url: '/wp-json/mantiload/v1/search',
                type: 'POST',
                data: data,
                success: (response) => {
                    // IGNORE stale responses! Only process the latest request
                    if (currentRequestId !== this.requestCounter) {
                        return; // Stale response, ignore it
                    }

                    this.activeRequest = null;
                    this.hideLoader();

                    // Handle REST API response
                    if (response.code) {
                        // Error response
                        this.showError(response.message || 'Search error. Please try again.');
                    } else if (response.results) {
                        // Success response
                        this.cache[query] = response;
                        this.renderResults(response);
                    } else {
                        this.showError('Unexpected response format');
                    }
                },
                error: (xhr, status, error) => {
                    // Don't show error for aborted requests (this is expected)
                    if (status === 'abort') {
                        return;
                    }

                    this.activeRequest = null;
                    this.hideLoader();
                    console.error('Search error:', error);
                    this.showError('Search error. Please try again.');
                }
            });
        }

        renderResults(data) {
            this.results.empty();
            this.selectedIndex = -1;

            // Update stats
            this.showStats(data.total, data.query_time);

            // Show empty state if no results and no categories
            if ((!data.results || data.results.length === 0) && (!data.categories || data.categories.length === 0)) {
                this.showEmptyState();
                return;
            }

            // Render categories section first (if enabled and has results)
            if (mantiloadSearch.showCategories && data.categories && data.categories.length > 0) {
                this.renderCategories(data.categories);
            }

            // Render each result
            if (data.results && data.results.length > 0) {
                // Add section header for products if categories exist
                if (data.categories && data.categories.length > 0) {
                    this.results.append($(`<div class="mantiload-section-header">${mantiloadSearch.strings.products}</div>`));
                }

                data.results.forEach((result) => {
                    const item = this.createResultItem(result);
                    this.results.append(item);
                });
            }

            // Add "View All" button if more results exist
            if (data.total > data.results.length) {
                const viewAllText = mantiloadSearch.strings.viewAll || 'View all results';
                const viewAllBtn = $('<a>', {
                    href: data.view_all_url,
                    class: 'mantiload-view-all',
                    text: `${viewAllText} (${data.total})`,
                    target: '_blank' // Open in new tab to avoid disrupting current page
                });
                this.results.append(viewAllBtn);
            }

            // Animate results
            this.animateResults();
        }

        createResultItem(result) {
            const item = $('<a>', {
                href: result.url,
                class: 'mantiload-result-item',
                'data-id': result.id,
                role: 'option'
            });

            // Thumbnail
            if (result.thumbnail && mantiloadSearch.showThumbnail) {
                item.append(
                    $('<img>', {
                        src: result.thumbnail,
                        alt: result.title,
                        class: 'mantiload-result-thumbnail',
                        loading: 'lazy'
                    })
                );
            }

            // Content wrapper
            const content = $('<div>', { class: 'mantiload-result-content' });

            // Title
            content.append(
                $('<h3>', {
                    class: 'mantiload-result-title',
                    html: result.title
                })
            );

            // Meta info
            const meta = $('<div>', { class: 'mantiload-result-meta' });

            if (result.price && mantiloadSearch.showPrice) {
                meta.append(
                    $('<span>', {
                        class: 'mantiload-result-price',
                        html: result.price
                    })
                );
            }

            if (result.sku && mantiloadSearch.showSKU) {
                meta.append(
                    $('<span>', {
                        class: 'mantiload-result-sku',
                        html: `SKU: ${result.sku}`
                    })
                );
            }

            if (result.stock_status) {
                const stockClass = result.in_stock ? '' : ' out-of-stock';
                meta.append(
                    $('<span>', {
                        class: `mantiload-result-stock${stockClass}`,
                        text: result.in_stock ? '✓ In Stock' : '✕ Out of Stock'
                    })
                );
            }

            content.append(meta);

            if (result.excerpt && mantiloadSearch.showExcerpt) {
                content.append(
                    $('<p>', {
                        class: 'mantiload-result-excerpt',
                        html: result.excerpt
                    })
                );
            }

            item.append(content);

            return item;
        }

        renderCategories(categories) {
            // Add section header
            const header = $(`<div class="mantiload-section-header">${mantiloadSearch.strings.categories}</div>`);
            this.results.append(header);

            // Create categories container
            const container = $('<div class="mantiload-categories"></div>');

            categories.forEach((category) => {
                const categoryItem = $('<a>', {
                    href: category.url,
                    class: 'mantiload-category-item',
                    'data-id': category.id
                });

                // Icon
                const icon = category.type === 'category'
                    ? '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16M4 12h16M4 17h16"/></svg>'
                    : '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>';

                categoryItem.html(`
                    <span class="mantiload-category-icon">${icon}</span>
                    <span class="mantiload-category-name">${category.name}</span>
                    <span class="mantiload-category-count">${category.count}</span>
                `);

                container.append(categoryItem);
            });

            this.results.append(container);
        }

        showEmptyState() {
            const emptyState = $(`
                <div class="mantiload-empty-state">
                    <svg class="mantiload-empty-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    <h3 class="mantiload-empty-title">${mantiloadSearch.strings.noResults}</h3>
                    <p class="mantiload-empty-description">Try different keywords or check your spelling</p>
                </div>
            `);

            this.results.html(emptyState);
        }

        showMessage(message) {
            this.results.html(`<div class="mantiload-message">${message}</div>`);
        }

        showError(message) {
            this.results.html(`<div class="mantiload-error">${message}</div>`);
        }

        showLoader() {
            this.loader.addClass('is-loading');
        }

        hideLoader() {
            this.loader.removeClass('is-loading');
        }

        showStats(total, time) {
            this.statsCount.text(`${total} ${mantiloadSearch.strings.results}`);
            this.statsTime.text(`in ${time}ms`);
        }

        hideStats() {
            this.statsCount.text('');
            this.statsTime.text('');
        }

        clearSearch() {
            this.input.val('');
            this.results.empty();
            this.clearBtn.hide();
            this.hideStats();
            this.selectedIndex = -1;
            this.currentQuery = '';
        }

        handleResultClick(e) {
            const $item = $(e.currentTarget);
            const id = $item.data('id');

            // Track click
            this.trackEvent('result_clicked', {
                product_id: id,
                query: this.currentQuery
            });

            // Allow default link behavior
        }

        animateResults() {
            $('.mantiload-result-item').each(function(index) {
                $(this).css({
                    opacity: 0,
                    transform: 'translateY(10px)'
                }).delay(index * 30).animate({
                    opacity: 1
                }, 200).css('transform', 'translateY(0)');
            });
        }

        getViewAllUrl(query) {
            const params = new URLSearchParams({
                s: query,
                post_type: mantiloadSearch.postTypes[0]
            });
            return `${window.location.origin}/?${params.toString()}`;
        }

        trackEvent(event, data = {}) {
            // Simple tracking - can be extended
            if (window.gtag) {
                gtag('event', event, {
                    event_category: 'MantiLoad Search',
                    ...data
                });
            }
        }
    }

    // Initialize on document ready
    $(document).ready(function() {
        // Only initialize modal search for administrators
        if (mantiloadSearch.enableModal) {
            window.mantiLoadSearchInstance = new MantiLoadSearch();
        }
    });

    /**
     * Inline Search Box Handler
     * For shortcode and widget instances
     */
    class MantiLoadInlineSearch {
        constructor(element) {
            this.$wrapper = $(element);
            this.$form = this.$wrapper.find('.mantiload-inline-form');
            this.$input = this.$wrapper.find('.mantiload-inline-search-input');
            this.$clearBtn = this.$wrapper.find('.mantiload-inline-clear');
            this.$loader = this.$wrapper.find('.mantiload-inline-loader');
            this.$dropdown = this.$wrapper.find('.mantiload-inline-results-dropdown');
            this.$container = this.$wrapper.find('.mantiload-inline-results-container');

            this.searchTimeout = null;
            this.activeRequest = null; // Track active AJAX request
            this.requestCounter = 0; // Track request order
            this.selectedIndex = -1;
            this.currentQuery = '';
            this.cache = {};

            // Read show/hide settings from data attributes (can be overridden per shortcode)
            this.showPrice = this.$wrapper.data('show-price') !== 0; // Default true unless explicitly 0
            this.showStock = this.$wrapper.data('show-stock') !== 0; // Default true unless explicitly 0

            this.init();
        }

        init() {
            this.bindEvents();
        }

        bindEvents() {
            // Input events
            this.$input.on('input', (e) => this.handleInput(e));
            this.$input.on('keydown', (e) => this.handleKeyDown(e));
            this.$input.on('focus', () => {
                if (this.currentQuery && this.$container.children().length > 0) {
                    this.$dropdown.show();
                }
            });

            // Clear button
            this.$clearBtn.on('click', () => this.clearSearch());

            // Form submit
            this.$form.on('submit', (e) => {
                const query = this.$input.val().trim();
                if (!query) {
                    e.preventDefault();
                }
            });

            // Click outside to close
            $(document).on('click', (e) => {
                if (!this.$wrapper.is(e.target) && this.$wrapper.has(e.target).length === 0) {
                    this.$dropdown.hide();
                }
            });
        }

        handleInput(e) {
            const query = e.target.value.trim();

            clearTimeout(this.searchTimeout);

            if (!query) {
                this.$clearBtn.removeClass('is-visible');
                this.$dropdown.hide();
                return;
            }

            this.$clearBtn.addClass('is-visible');

            // Check minimum characters
            if (query.length < mantiloadSearch.minChars) {
                return;
            }

            // Check cache - INSTANT if cached!
            if (this.cache[query]) {
                this.renderResults(this.cache[query]);
                return;
            }

            // INSTANT search with minimal debounce (100ms = barely noticeable, prevents excessive requests)
            // Previous requests are auto-aborted, so we can afford aggressive search
            const delay = Math.min(mantiloadSearch.searchDelay, 100);

            this.searchTimeout = setTimeout(() => {
                this.performSearch(query);
            }, delay);
        }

        handleKeyDown(e) {
            const items = this.$container.find('.mantiload-inline-result-item');

            switch(e.key) {
                case 'ArrowDown':
                    e.preventDefault();
                    this.selectedIndex = Math.min(this.selectedIndex + 1, items.length - 1);
                    this.updateSelection(items);
                    break;

                case 'ArrowUp':
                    e.preventDefault();
                    this.selectedIndex = Math.max(this.selectedIndex - 1, -1);
                    this.updateSelection(items);
                    break;

                case 'Enter':
                    if (this.selectedIndex >= 0 && items.length > 0) {
                        e.preventDefault();
                        window.location.href = items.eq(this.selectedIndex).attr('href');
                    }
                    break;

                case 'Escape':
                    this.$dropdown.hide();
                    break;
            }
        }

        updateSelection(items) {
            items.removeClass('is-selected');

            if (this.selectedIndex >= 0) {
                const selected = items.eq(this.selectedIndex);
                selected.addClass('is-selected');

                // Scroll into view
                const container = this.$container[0];
                const element = selected[0];
                const elementTop = element.offsetTop;
                const elementBottom = elementTop + element.offsetHeight;
                const containerTop = container.scrollTop;
                const containerBottom = containerTop + container.clientHeight;

                if (elementTop < containerTop) {
                    container.scrollTop = elementTop;
                } else if (elementBottom > containerBottom) {
                    container.scrollTop = elementBottom - container.clientHeight;
                }
            }
        }

        performSearch(query) {
            // ABORT previous request if still running (INSTANT search!)
            if (this.activeRequest) {
                this.activeRequest.abort();
            }

            this.currentQuery = query;
            this.requestCounter++; // Increment request ID
            const currentRequestId = this.requestCounter;

            this.showLoader();
            this.showSearching(); // Show "Searching..." instead of empty state

            const postTypes = this.$wrapper.data('post-types') || ['product'];

            // Check if mantiloadSearch is defined
            if (typeof mantiloadSearch === 'undefined') {
                console.error('MantiLoad: mantiloadSearch is not defined. Scripts may not be loaded properly.');
                this.hideLoader();
                this.showError('Search configuration error. Please refresh the page.');
                return;
            }

            const data = {
                action: 'mantiload_search',
                nonce: mantiloadSearch.nonce,
                q: query,
                post_types: postTypes
            };

            // Store the active request so we can abort it
            this.activeRequest = $.ajax({
                url: '/wp-json/mantiload/v1/search',
                type: 'POST',
                data: data,
                success: (response) => {
                    // IGNORE stale responses! Only process the latest request
                    if (currentRequestId !== this.requestCounter) {
                        return; // Stale response, ignore it
                    }

                    this.activeRequest = null;
                    this.hideLoader();

                    // Handle REST API response
                    if (response.code) {
                        // Error response
                        this.showError(response.message || 'Search error. Please try again.');
                    } else if (response.results) {
                        // Success response
                        this.cache[query] = response;
                        this.renderResults(response);
                    } else {
                        this.showError('Unexpected response format');
                    }
                },
                error: (xhr, status, error) => {
                    // Don't show error for aborted requests (this is expected)
                    if (status === 'abort') {
                        return;
                    }

                    this.activeRequest = null;
                    this.hideLoader();
                    console.error('Search error:', error);
                    this.showError('Search error. Please try again.');
                }
            });
        }

        renderResults(data) {
            this.$container.empty();
            this.selectedIndex = -1;

            // Show stats
            if (data.total > 0) {
                const stats = $(`
                    <div class="mantiload-inline-stats">
                        <span class="mantiload-inline-count">${data.total} ${mantiloadSearch.strings.results}</span>
                        <span class="mantiload-inline-time">${mantiloadSearch.strings.in} ${data.query_time} ${mantiloadSearch.strings.ms}</span>
                    </div>
                `);
                this.$container.append(stats);
            }

            // Empty state
            if ((!data.results || data.results.length === 0) && (!data.categories || data.categories.length === 0)) {
                this.showEmpty();
                this.$dropdown.show();
                return;
            }

            // Render categories first (if enabled and has results)
            if (mantiloadSearch.showCategories && data.categories && data.categories.length > 0) {
                this.renderCategories(data.categories);
            }

            // Render results
            if (data.results && data.results.length > 0) {
                // Add section header for products if categories exist
                if (data.categories && data.categories.length > 0) {
                    this.$container.append($(`<div class="mantiload-inline-section-header">${mantiloadSearch.strings.products}</div>`));
                }

                data.results.forEach((result) => {
                    const item = this.createResultItem(result);
                    this.$container.append(item);
                });
            }

            // View all link
            if (data.total > data.results.length) {
                const viewAllText = this.$wrapper.data('view-all-text') || 'View all results';
                const viewAll = $(`
                    <a href="${data.view_all_url}" class="mantiload-inline-view-all">
                        ${viewAllText} (${data.total})
                    </a>
                `);
                this.$container.append(viewAll);
            }

            this.$dropdown.show();
        }

        createResultItem(result) {
            const item = $('<a>', {
                href: result.url,
                class: 'mantiload-inline-result-item',
                'data-id': result.id
            });

            // Thumbnail
            if (result.thumbnail && mantiloadSearch.showThumbnail) {
                item.append(
                    $('<img>', {
                        src: result.thumbnail,
                        alt: result.title,
                        class: 'mantiload-inline-result-thumb',
                        loading: 'lazy'
                    })
                );
            }

            // Content
            const content = $('<div>', { class: 'mantiload-inline-result-content' });

            // Title
            content.append(
                $('<h3>', {
                    class: 'mantiload-inline-result-title',
                    html: result.title
                })
            );

            // Meta
            const meta = $('<div>', { class: 'mantiload-inline-result-meta' });

            if (result.price && this.showPrice) {
                meta.append(
                    $('<span>', {
                        class: 'mantiload-inline-result-price',
                        html: result.price
                    })
                );
            }

            if (result.sku && mantiloadSearch.showSKU) {
                meta.append(
                    $('<span>', {
                        class: 'mantiload-inline-result-sku',
                        html: `SKU: ${result.sku}`
                    })
                );
            }

            if (result.stock_status && this.showStock) {
                const stockClass = result.in_stock ? '' : ' out-of-stock';
                meta.append(
                    $('<span>', {
                        class: `mantiload-inline-result-stock${stockClass}`,
                        text: result.in_stock ? '✓ In Stock' : '✕ Out of Stock'
                    })
                );
            }

            content.append(meta);
            item.append(content);

            return item;
        }

        renderCategories(categories) {
            // Add section header
            const header = $(`<div class="mantiload-inline-section-header">${mantiloadSearch.strings.categories}</div>`);
            this.$container.append(header);

            // Create categories container
            const container = $('<div class="mantiload-inline-categories"></div>');

            categories.forEach((category) => {
                const categoryItem = $('<a>', {
                    href: category.url,
                    class: 'mantiload-inline-category-item',
                    'data-id': category.id
                });

                // Icon
                const icon = category.type === 'category'
                    ? '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16M4 12h16M4 17h16"/></svg>'
                    : '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>';

                categoryItem.html(`
                    <span class="mantiload-inline-category-icon">${icon}</span>
                    <span class="mantiload-inline-category-name">${category.name}</span>
                    <span class="mantiload-inline-category-count">${category.count}</span>
                `);

                container.append(categoryItem);
            });

            this.$container.append(container);
        }

        showEmpty() {
            const empty = $(`
                <div class="mantiload-inline-empty">
                    <svg class="mantiload-inline-empty-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    <p class="mantiload-inline-empty-text">No results found for "${this.currentQuery}"</p>
                </div>
            `);
            this.$container.html(empty);
        }

        showSearching() {
            const searching = $(`
                <div class="mantiload-inline-searching">
                    <div class="mantiload-inline-searching-spinner"></div>
                    <p class="mantiload-inline-searching-text">Searching...</p>
                </div>
            `);
            this.$container.html(searching);
            this.$dropdown.show();
        }

        showError(message) {
            this.$container.html(`<div class="mantiload-inline-empty"><p>${message}</p></div>`);
            this.$dropdown.show();
        }

        showLoader() {
            this.$loader.addClass('is-loading');
        }

        hideLoader() {
            this.$loader.removeClass('is-loading');
        }

        clearSearch() {
            this.$input.val('').focus();
            this.$container.empty();
            this.$clearBtn.removeClass('is-visible');
            this.$dropdown.hide();
            this.selectedIndex = -1;
            this.currentQuery = '';
        }
    }

    // Initialize all inline search boxes
    $(document).ready(function() {
        $('.mantiload-search-box-inline').each(function() {
            new MantiLoadInlineSearch(this);
        });
    });

})(jQuery);
