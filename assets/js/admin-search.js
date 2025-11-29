/**
 * MantiLoad Admin Search
 * THE FIRST EVER instant admin search for WordPress!
 *
 * Features:
 * - Cmd/Ctrl + K to open
 * - Search products, orders, customers, posts
 * - Keyboard navigation (arrow keys)
 * - Ultra-fast results (<50ms)
 * - Beautiful UI
 *
 * @package MantiLoad
 */

(function($) {
    'use strict';

    class MantiLoadAdminSearch {
        constructor() {
            this.modal = $('#mantiload-admin-modal');
            this.input = $('#mantiload-admin-search-input');
            this.results = $('#mantiload-admin-search-results');
            this.loader = $('.mantiload-admin-search-loader');
            this.stats = $('#mantiload-admin-stats');

            this.searchTimeout = null;
            this.activeRequest = null;
            this.requestCounter = 0;
            this.selectedIndex = -1;
            this.currentFilter = 'all';
            this.cache = {};

            this.init();
        }

        init() {
            this.bindEvents();
            this.setupKeyboardShortcuts();
        }

        bindEvents() {
            // Filter buttons
            $('.mantiload-admin-filter-btn').on('click', (e) => {
                const $btn = $(e.currentTarget);
                const filter = $btn.data('filter');
                this.setFilter(filter);
            });

            // Close modal
            $('[data-close-admin-modal]').on('click', () => this.closeModal());
            $('.mantiload-admin-overlay').on('click', () => this.closeModal());

            // Search input
            this.input.on('input', (e) => this.handleInput(e));
            this.input.on('keydown', (e) => this.handleKeyDown(e));

            // Result clicks - close modal after navigation
            $(document).on('click', '.mantiload-admin-result-item', () => {
                // Close modal after clicking result (navigation happens in createResultItem)
                setTimeout(() => this.closeModal(), 100);
            });
        }

        setupKeyboardShortcuts() {
            $(document).on('keydown', (e) => {
                // Cmd/Ctrl + K to open
                if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
                    e.preventDefault();
                    this.openModal();
                }

                // Escape to close
                if (e.key === 'Escape' && this.modal.hasClass('is-active')) {
                    e.preventDefault();
                    this.closeModal();
                }
            });
        }

        openModal() {
            this.modal.addClass('is-active');
            $('body').css('overflow', 'hidden');

            // Focus input
            setTimeout(() => {
                this.input.focus();
            }, 100);

            // Show welcome message
            if (!this.input.val()) {
                this.showWelcome();
            }
        }

        closeModal() {
            this.modal.removeClass('is-active');
            $('body').css('overflow', '');
            this.input.val('');
            this.results.empty();
            this.selectedIndex = -1;
        }

        setFilter(filter) {
            this.currentFilter = filter;

            // Update button states
            $('.mantiload-admin-filter-btn').removeClass('active');
            $(`.mantiload-admin-filter-btn[data-filter="${filter}"]`).addClass('active');

            // Re-search if there's a query (cache will be used if available)
            const query = this.input.val().trim();
            if (query && query.length >= 2) {
                // Check cache first
                const cacheKey = this.currentFilter + ':' + query;
                if (this.cache[cacheKey]) {
                    this.renderResults(this.cache[cacheKey]);
                } else {
                    this.performSearch(query);
                }
            }
        }

        handleInput(e) {
            const query = e.target.value.trim();

            // Clear previous timeout
            clearTimeout(this.searchTimeout);

            if (!query) {
                this.showWelcome();
                return;
            }

            // Minimum 2 characters required
            if (query.length < 2) {
                this.stats.html('Type at least 2 characters to search');
                return;
            }

            // Check cache (only for successful results)
            const cacheKey = this.currentFilter + ':' + query;
            if (this.cache[cacheKey]) {
                this.renderResults(this.cache[cacheKey]);
                return;
            }

            // INSTANT search with minimal debounce
            this.searchTimeout = setTimeout(() => {
                this.performSearch(query);
            }, 100);
        }

        handleKeyDown(e) {
            const items = $('.mantiload-admin-result-item');

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
                        const url = items.eq(this.selectedIndex).data('edit-url');
                        if (url) {
                            window.location.href = url;
                        }
                    }
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
                if (container && element) {
                    const elementTop = element.offsetTop;
                    const elementBottom = elementTop + element.offsetHeight;
                    const containerTop = container.scrollTop;
                    const containerBottom = containerTop + container.clientHeight;

                    if (elementTop < containerTop) {
                        container.scrollTop = elementTop - 50;
                    } else if (elementBottom > containerBottom) {
                        container.scrollTop = elementBottom - container.clientHeight + 50;
                    }
                }
            }
        }

        performSearch(query) {
            // Abort previous request
            if (this.activeRequest) {
                this.activeRequest.abort();
            }

            this.requestCounter++;
            const currentRequestId = this.requestCounter;

            this.showLoader();

            const data = {
                action: 'mantiload_admin_search',
                nonce: mantiloadAdmin.nonce,
                q: query,
                filter: this.currentFilter
            };

            this.activeRequest = $.ajax({
                url: mantiloadAdmin.adminUrl + 'admin-ajax.php',
                type: 'GET',
                data: data,
                success: (response) => {
                    // Ignore stale responses
                    if (currentRequestId !== this.requestCounter) {
                        return;
                    }

                    this.activeRequest = null;
                    this.hideLoader();

                    if (response.success) {
                        // Cache ONLY successful results with actual data
                        if (response.data && response.data.results) {
                            const cacheKey = this.currentFilter + ':' + query;
                            this.cache[cacheKey] = response.data;
                        }

                        this.renderResults(response.data);
                    } else {
                        // Don't cache errors
                        this.showError(response.data.message || 'Search error');
                    }
                },
                error: (xhr, status, error) => {
                    // Ignore aborted requests
                    if (status === 'abort') {
                        return;
                    }

                    if (currentRequestId !== this.requestCounter) {
                        return;
                    }

                    this.activeRequest = null;
                    this.hideLoader();
                    this.showError('Search error. Please try again.');
                }
            });
        }

        renderResults(data) {
            this.results.empty();
            this.selectedIndex = -1;

            // Update stats
            const totalTime = data.total_time || data.query_time;
            this.stats.html(`Found <strong>${data.total}</strong> results in <strong>${totalTime}ms</strong>`);

            if (data.total === 0) {
                this.showEmpty();
                return;
            }

            // Group results by type
            const groups = {
                products: [],
                orders: [],
                customers: [],
                posts: []
            };

            data.results.forEach(item => {
                if (groups[item.type]) {
                    groups[item.type].push(item);
                }
            });

            // Render each group
            const groupTitles = {
                products: 'Products',
                orders: 'Orders',
                customers: 'Customers',
                posts: 'Posts & Pages'
            };

            // URLs for "View All" links
            const adminUrl = mantiloadAdmin.adminUrl;
            const viewAllUrls = {
                products: adminUrl + 'edit.php?post_type=product&s=' + encodeURIComponent(data.query),
                orders: adminUrl + 'edit.php?post_type=shop_order&s=' + encodeURIComponent(data.query),
                customers: adminUrl + 'users.php?s=' + encodeURIComponent(data.query),
                posts: adminUrl + 'edit.php?s=' + encodeURIComponent(data.query)
            };

            Object.keys(groups).forEach(type => {
                if (groups[type].length > 0) {
                    const $group = $('<div>', { class: 'mantiload-admin-result-group' });

                    // Show total count if available
                    let groupTitle = groupTitles[type];
                    if (data.totals_by_type && data.totals_by_type[type]) {
                        const displayed = groups[type].length;
                        const total = data.totals_by_type[type];
                        if (total > displayed) {
                            groupTitle = `${groupTitles[type]} (${displayed} of ${total})`;
                        } else {
                            groupTitle = `${groupTitles[type]} (${total})`;
                        }
                    } else {
                        groupTitle = `${groupTitles[type]} (${groups[type].length})`;
                    }

                    $group.append(
                        $('<div>', {
                            class: 'mantiload-admin-result-group-title',
                            text: groupTitle
                        })
                    );

                    groups[type].forEach(item => {
                        $group.append(this.createResultItem(item));
                    });

                    // Add "View All Results" link
                    const $viewAll = $('<a>', {
                        href: viewAllUrls[type],
                        class: 'mantiload-admin-view-all',
                        html: `<svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-8.707l-3-3a1 1 0 00-1.414 1.414L10.586 9H7a1 1 0 100 2h3.586l-1.293 1.293a1 1 0 101.414 1.414l3-3a1 1 0 000-1.414z" clip-rule="evenodd"/></svg> View all ${groupTitles[type].toLowerCase()} results`
                    });
                    $group.append($viewAll);

                    this.results.append($group);
                }
            });
        }

        createResultItem(item) {
            // Use DIV instead of A, add click handler manually
            const $item = $('<div>', {
                class: 'mantiload-admin-result-item',
                'data-id': item.id,
                'data-type': item.type,
                'data-edit-url': item.url,
                click: function(e) {
                    // Only navigate if not clicking the copy button
                    if (!$(e.target).closest('.mantiload-admin-copy-link').length) {
                        window.location.href = item.url;
                    }
                }
            });

            // Icon/Thumbnail
            const $icon = $('<div>', { class: 'mantiload-admin-result-icon' });

            if (item.thumbnail) {
                $icon.append(
                    $('<img>', {
                        src: item.thumbnail,
                        alt: item.title,
                        loading: 'lazy'
                    })
                );
            } else {
                $icon.append(this.getTypeIcon(item.type));
            }

            $item.append($icon);

            // Content
            const $content = $('<div>', { class: 'mantiload-admin-result-content' });

            $content.append(
                $('<h3>', {
                    class: 'mantiload-admin-result-title',
                    html: item.title
                })
            );

            // Meta
            const $meta = $('<div>', { class: 'mantiload-admin-result-meta' });

            if (item.price) {
                $meta.append($('<span>', { text: item.price }));
            }

            if (item.sku) {
                $meta.append($('<span>', { html: `SKU: ${item.sku}` }));
            }

            if (item.stock_status) {
                $meta.append(
                    $('<span>', {
                        class: `mantiload-admin-result-badge status-${item.stock_status}`,
                        text: item.stock_status === 'instock' ? 'In Stock' : 'Out of Stock'
                    })
                );
            }

            if (item.status) {
                $meta.append(
                    $('<span>', {
                        class: `mantiload-admin-result-badge status-${item.status}`,
                        text: item.status_label || item.status
                    })
                );
            }

            if (item.meta) {
                $meta.append($('<span>', { text: item.meta }));
            }

            $content.append($meta);

            // Add Copy Link button for frontend URL
            if (item.product_url || item.post_url) {
                const frontendUrl = item.product_url || item.post_url;
                const $copyBtn = $('<button>', {
                    class: 'mantiload-admin-copy-link',
                    title: 'Copy frontend link: ' + frontendUrl,
                    html: '<svg width="14" height="14" fill="currentColor" viewBox="0 0 20 20"><path d="M8 3a1 1 0 011-1h2a1 1 0 110 2H9a1 1 0 01-1-1z"/><path d="M6 3a2 2 0 00-2 2v11a2 2 0 002 2h8a2 2 0 002-2V5a2 2 0 00-2-2 3 3 0 01-3 3H9a3 3 0 01-3-3z"/></svg>',
                });

                // Attach click handler separately to ensure proper closure
                $copyBtn.on('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();

                    // Copy to clipboard
                    navigator.clipboard.writeText(frontendUrl).then(() => {
                        // Visual feedback
                        const $btn = $(this);
                        const originalHTML = $btn.html();
                        $btn.html('✓ Copied!').css('color', '#10b981');
                        setTimeout(() => {
                            $btn.html(originalHTML).css('color', '');
                        }, 1500);
                    }).catch(err => {
                        console.error('Copy failed:', err);
                        alert('Failed to copy: ' + err);
                    });
                });

                $content.append($copyBtn);
            }

            $item.append($content);

            return $item;
        }

        getTypeIcon(type) {
            const icons = {
                products: '<svg fill="currentColor" viewBox="0 0 20 20"><path d="M3 1a1 1 0 000 2h1.22l.305 1.222a.997.997 0 00.01.042l1.358 5.43-.893.892C3.74 11.846 4.632 14 6.414 14H15a1 1 0 000-2H6.414l1-1H14a1 1 0 00.894-.553l3-6A1 1 0 0017 3H6.28l-.31-1.243A1 1 0 005 1H3zM16 16.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zM6.5 18a1.5 1.5 0 100-3 1.5 1.5 0 000 3z"/></svg>',
                orders: '<svg fill="currentColor" viewBox="0 0 20 20"><path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"/><path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z"/></svg>',
                customers: '<svg fill="currentColor" viewBox="0 0 20 20"><path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"/></svg>',
                posts: '<svg fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z"/></svg>'
            };

            return icons[type] || icons.products;
        }

        showWelcome() {
            this.results.html(`
                <div class="mantiload-admin-search-empty">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <h3>Search anything...</h3>
                    <p>Products, orders, customers, posts - all in one place!</p>
                </div>
            `);
            this.stats.html('Type to start searching');
        }

        showEmpty() {
            this.results.html(`
                <div class="mantiload-admin-search-empty">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <h3>No results found</h3>
                    <p>Try different keywords or use filters</p>
                </div>
            `);
        }

        showError(message) {
            this.results.html(`
                <div class="mantiload-admin-search-empty">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <h3>Oops!</h3>
                    <p>${message}</p>
                </div>
            `);
        }

        showLoader() {
            this.loader.show();
        }

        hideLoader() {
            this.loader.hide();
        }
    }

    // Initialize when DOM is ready
    $(document).ready(function() {
        window.mantiLoadAdminSearch = new MantiLoadAdminSearch();
    });

})(jQuery);
