=== MantiLoad ===
Contributors: mantiload, mohsengham
Tags: search, woocommerce, fast search, ajax search, product search
Requires at least: 5.8
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.7.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

High-performance search engine for WordPress and WooCommerce powered by Manticore Search.

== Description ==

MantiLoad integrates Manticore Search into WordPress, delivering professional-grade search performance for content and WooCommerce products. Built for sites that need reliable, fast search at scale.

**Core Capabilities**

* Full-text search with BM25 relevance ranking
* Product search with SKU, category, and attribute filtering
* Real-time indexing with automatic updates
* AJAX search interface with keyboard navigation
* Synonym management for improved discoverability
* Search analytics and performance monitoring

**Performance**

Tested on a WooCommerce store with 4,400+ products:
* Single-word queries: 3-5ms
* Multi-word queries: 5-10ms
* Filtered queries: 10-20ms

Standard WordPress search on the same dataset: 200-1000ms

**Search Features**

* AJAX search with live results
* Keyboard shortcuts (Cmd/Ctrl+K)
* Wildcard and prefix matching
* Synonym support with bidirectional mapping
* Multi-word query handling
* SKU search for WooCommerce products

**WooCommerce Integration**

* Product filtering by price, category, attributes, stock status
* Related products algorithm
* Cart integration for quick add-to-order
* Variable product support
* Admin order search optimization

**Interface Options**

* Modal search overlay
* Dropdown search results
* Dedicated search results page
* Widget and shortcode support
* Mobile-responsive design
* RTL language support

**Administration**

* Search analytics dashboard
* Synonym management
* Index configuration
* Performance monitoring
* WP-CLI commands for bulk operations

= Requirements =

* WordPress 5.8 or higher
* PHP 7.4 or higher
* MySQL/MariaDB with MySQLi extension
* Manticore Search 13.0 or higher installed on server
* WooCommerce 6.0+ (optional, for product features)

= Developer API =

**WP-CLI Commands**

* wp mantiload create_indexes - Initialize search indexes
* wp mantiload reindex - Rebuild all indexes
* wp mantiload optimize - Optimize index performance
* wp mantiload stats - View index statistics
* wp mantiload search "query" - Test search queries

**Hooks and Filters**

* mantiload_index_schema - Modify index structure
* mantiload_post_data - Customize indexed data
* mantiload_search_results - Process search results
* mantiload_query_integration_args - Modify query parameters

**Programmatic Search**

Use the search API directly in your code:

`$search_engine = MantiLoad\Search\Search_Engine();
$results = $search_engine->search( 'laptop', array(
    'post_type' => 'product',
    'limit' => 20,
    'filters' => array(
        'min_price' => 500,
        'max_price' => 2000,
    ),
) );`

== Installation ==

= Automatic Installation =

1. Go to WordPress Admin > Plugins > Add New
2. Search for "MantiLoad"
3. Click "Install Now" and then "Activate"
4. Install Manticore Search (see below)
5. Go to MantiLoad > Indexing
6. Click "Create Indexes & Reindex All"

= Manual Installation =

1. Download the plugin zip file
2. Extract and upload `mantiload` folder to `/wp-content/plugins/`
3. Activate the plugin through WordPress Admin > Plugins
4. Install Manticore Search (see below)
5. Go to MantiLoad > Indexing
6. Click "Create Indexes & Reindex All"

= Installing Manticore Search =

**Debian/Ubuntu:**
```bash
wget https://repo.manticoresearch.com/manticore-repo.noarch.deb
sudo dpkg -i manticore-repo.noarch.deb
sudo apt update
sudo apt install manticore manticore-extra
sudo systemctl start manticore
sudo systemctl enable manticore
```

**CentOS/RHEL:**
```bash
yum install https://repo.manticoresearch.com/manticore-repo.noarch.rpm
yum install manticore manticore-extra
systemctl start manticore
systemctl enable manticore
```

**Docker:**
```bash
docker pull manticoresearch/manticore
docker run -d --name manticore -p 9306:9306 -p 9308:9308 manticoresearch/manticore
```

**Verify Installation:**
```bash
mysql -h127.0.0.1 -P9306 -e "SHOW TABLES"
```

= Post-Installation Setup =

1. Go to **MantiLoad > Settings**
2. Select post types to index (default: posts, pages, products)
3. Configure search fields and weights
4. Go to **MantiLoad > Indexing**
5. Click **"Create Indexes"** (one-time setup)
6. Click **"Reindex All Posts"**
7. Test search on your site!

**WP-CLI (Faster):**
```bash
wp mantiload create_indexes
wp mantiload reindex --batch-size=500
```

== Frequently Asked Questions ==

= What is Manticore Search? =

Manticore Search is an open-source search engine designed for speed. It's a modern fork of Sphinx Search with better performance and features. It's similar to Elasticsearch but faster and lighter.

= Do I need a separate server? =

No! Manticore runs on the same server as WordPress. It uses minimal resources (typically 50-100MB RAM).

= Will this work without Manticore? =

No, MantiLoad requires Manticore Search to be installed. However, installation is simple and free.

= What happens if Manticore goes down? =

Your site continues working perfectly! MantiLoad has a built-in graceful fallback system. If Manticore becomes unavailable, the plugin automatically switches to WordPress default search. You'll get an admin notice about the issue, but your visitors will never experience a broken site. Zero downtime guaranteed!

= Does it work with WooCommerce? =

Yes! MantiLoad is optimized for WooCommerce with special features for products, SKUs, prices, attributes, and more.

= How fast is it really? =

Real benchmarks from a store with 4,441 products:
* Simple search: 3-5ms
* Complex search with filters: 10-20ms
* 2000+ products: 5ms

That's 100-200x faster than default WordPress search!

= Can I use it with my theme? =

Yes! MantiLoad works with any WordPress theme. It includes multiple search interfaces (modal, dropdown, page, widget, shortcode).

= What about mobile devices? =

MantiLoad is fully responsive and mobile-optimized. The search UI adapts beautifully to all screen sizes.

= How does indexing work? =

MantiLoad automatically indexes new/updated posts in real-time. You can also manually reindex from the admin or via WP-CLI.

= Can I customize the search? =

Yes! MantiLoad includes:
* Field weight configuration
* Custom CSS settings
* Hooks and filters for developers
* Template overrides

= Is it compatible with WPML/Polylang? =

Yes! MantiLoad respects WordPress language settings and can index multilingual content.

= Can I search custom post types? =

Yes! Enable any post type in Settings > Search Fields.

= How do synonyms work? =

Create synonyms in MantiLoad > Synonyms. When users search "gown", they'll also see "dress" results automatically. Works bidirectionally!

= Does it support fuzzy search? =

Yes! MantiLoad includes typo tolerance and wildcard prefix matching.

= How do related products work? =

MantiLoad's Smart Related Products feature automatically replaces WooCommerce's default related products with intelligent, lightning-fast alternatives. Simply enable it in settings - no code changes needed! It works automatically with all themes (WoodMart, Flatsome, etc.), page builders (Elementor, Divi), and WooCommerce blocks. Choose from 3 matching algorithms: Combo (considers attributes, categories, and price), Attributes & Categories (product characteristics), or Price & Categories (price-based alternatives). Performance: 2-5ms vs WooCommerce's 50-150ms.

= What about SEO? =

MantiLoad doesn't affect SEO. It only powers the search functionality. Search engine crawlers still see your normal content.

= Can I export search data? =

Yes! Search analytics and logs can be exported from the admin dashboard.

= Is there a pro version? =

No! MantiLoad is 100% free forever. All features included. We believe in open source!

= Where can I get support? =

* WordPress.org support forum
* GitHub issues: https://github.com/mantiload/mantiload
* Documentation: https://docs.mantiload.com/

= Can I contribute? =

Yes! MantiLoad is open source. Contributions welcome on GitHub!

== Screenshots ==

1. Ultra-fast AJAX search with instant results
2. Admin dashboard with analytics and statistics
3. Indexing management interface
4. Search settings and field weights
5. Smart synonyms management
6. Modal search with Cmd/K shortcut
7. WooCommerce product filters
8. Mobile-responsive search interface

== Changelog ==

= 1.7.2 - 2025-12-07 =
* FIX: Product search queries now ALWAYS integrated regardless of main/secondary query status
* FIX: Shop search results page now works correctly with SKU searches
* IMPROVE: Search query integration forced for all product searches to ensure SKU matching works

= 1.7.1 - 2025-12-07 =
* FIX: Query Integration (posts_pre_query) now checks SKU before Manticore search
* FIX: Frontend shop search now correctly finds products by variation SKU
* IMPROVE: SKU search priority added to all query paths including auto-interception

= 1.7.0 - 2025-12-07 =
* FIX: SKU search now case-insensitive (zm-2961 and ZM-2961 both work)
* FIX: Frontend shop page search now finds products by variation SKU regardless of case
* IMPROVE: Added pre_get_posts hook for better query interception
* IMPROVE: All search contexts now handle SKU searches case-insensitively

= 1.6.9 - 2025-12-07 =
* FIX: Frontend shop page search (?s=zm-2961&post_type=product) now works correctly
* FIX: $_GET parameter fallback now works on both admin and frontend for consistent search behavior
* IMPROVE: All WP_Query-based searches now detect search parameters reliably

= 1.6.8 - 2025-12-07 =
* FIX: Frontend AJAX search box now finds products by variation SKU
* FIX: Searching for variation SKU (e.g., ZM-2961) now shows the parent product in frontend search results
* IMPROVE: All search contexts (admin, modal, frontend shop, AJAX) now handle variation SKUs correctly

= 1.6.7 - 2025-12-07 =
* FIX: Frontend shop search (?s=ZM-2961&post_type=product) now finds products by variation SKU
* IMPROVE: SKU search now works on both frontend and backend for all search contexts

= 1.6.6 - 2025-12-07 =
* FIX: Modal search (Ctrl+K) now finds product variations by SKU
* IMPROVE: Frontend and backend modal search now includes variations in SKU search results

= 1.6.5 - 2025-12-06 =
* FIX: Admin product search now finds parent products when searching by variation SKU
* IMPROVE: Searching for variation SKU (e.g., ZM-2961) now shows the parent product in admin list

= 1.6.4 - 2025-12-06 =
* FIX: Made "Hidden Gem Recommendation" string translatable
* IMPROVE: Regenerated translation template (POT file)

= 1.6.3 - 2025-12-06 =
* FIX: Exact SKU matching now works for alphanumeric SKUs (e.g., ZM-33751013)
* IMPROVE: Exact SKU matches are now prioritized at the top of search results for all SKU formats

= 1.6.2 - 2025-12-06 =
* FIX: Admin product search now prioritizes SKU over product ID for numeric searches
* FIX: Removed conflicting ID IN filters from other plugins that prevented search results
* FIX: Improved query_vars detection with $_GET fallback for admin searches
* IMPROVE: Clean admin search code with production-ready optimization

= 1.6.1 - 2025-11-30 =
* FIX: Fixed orderby=date showing no products on shop page
* FIX: Empty ORDER direction in SQL queries now defaults to DESC
* IMPROVE: Better handling of WooCommerce sort parameters

= 1.6.0 - 2025-11-29 =
* IMPROVE: Removed Author URI for WordPress.org compliance
* NEW: GitHub Actions automated release workflow
* NEW: Comprehensive .gitignore for repository management
* IMPROVE: Complete WordPress.org plugin review compliance
* IMPROVE: All CDN dependencies bundled locally

= 1.5.2 - 2025-11-29 =
* FIX: Admin product search SSL connection issue with Manticore
* FIX: Exact SKU matching for "Add to Order" functionality
* FIX: Variable products now return variations when searching by parent SKU
* FIX: Input sanitization improvements (json_encode to wp_json_encode)
* FIX: Added ABSPATH check to reset script for security
* IMPROVE: Bundled Select2 and Chart.js locally (removed CDN dependencies)
* IMPROVE: WordPress.org plugin review compliance
* IMPROVE: Database indexes feature for WooCommerce performance

= 1.5.1 - 2025-11-20 =
* FIX: Removed hardcoded PHP paths for better hosting compatibility
* FIX: Replaced shell exec with WordPress cron for safer background processing
* FIX: Prepared SQL queries for better security
* FIX: Removed debug logging statements for production
* FIX: Bundled external CDN dependencies locally (Select2, Chart.js)
* FIX: Cleaned up duplicate and unused code files
* IMPROVE: Better error handling and graceful fallbacks
* IMPROVE: WordPress.org plugin guidelines compliance
* IMPROVE: Code quality and documentation

= 1.2.0 - 2025-01-31 =
* NEW: Smart Related Products - 20-30x faster than WooCommerce (2-5ms vs 50-150ms)
* NEW: 3 intelligent matching algorithms (Combo, Attributes+Categories, Price+Categories)
* NEW: Automatic compatibility with all themes and page builders (Elementor, Divi, etc.)
* NEW: Test Connection button in settings - instantly verify Manticore connectivity
* NEW: Real-time Index Status widget showing document count and connection health
* NEW: One-click Rebuild Index button in admin
* NEW: Graceful Fallback System - site never breaks if Manticore is down
* NEW: Health monitoring with admin notices when Manticore is unavailable
* NEW: Comprehensive error handling and logging
* IMPROVE: FACET-based filter counts (15x faster)
* IMPROVE: Enhanced admin UI with black & white theme
* IMPROVE: Comprehensive shortcode documentation in admin
* IMPROVE: Zero-downtime guarantee with automatic WordPress search fallback
* FIX: Color consistency across admin interface

= 1.1.0 - 2025-01-25 =
* NEW: MantiCore connection settings in admin (host, port, index name)
* IMPROVE: Users can now configure MantiCore connection from Settings page
* IMPROVE: No hardcoded connection settings anymore
* FIX: Better flexibility for different server setups

= 1.0.9 - 2025-01-25 =
* NEW: Smart Synonyms System - prevent zero-result searches
* NEW: Wildcard prefix matching (3-4 char queries)
* NEW: View All Results link in AJAX dropdown
* FIX: Frontend URL search synonym expansion
* FIX: AJAX search total count for "View All" button
* FIX: Query builder preserves MantiCore operators
* IMPROVE: Query expansion with automatic wildcards
* IMPROVE: Search speed maintained under 8ms

= 1.0.8 - 2025-01-24 =
* NEW: Admin product list MantiCore integration
* NEW: Cmd/K modal search for admin
* FIX: Inline search interference with admin search
* FIX: Admin AJAX search endpoints
* IMPROVE: Separated frontend and admin search classes

= 1.0.7 - 2025-01-23 =
* NEW: Inline search box widget
* NEW: Shortcode support [mantiload_search]
* FIX: Search modal keyboard navigation
* IMPROVE: AJAX search performance
* IMPROVE: Mobile responsiveness

= 1.0.6 - 2025-01-22 =
* NEW: Real-time search analytics
* NEW: Popular searches tracking
* FIX: WooCommerce attribute filtering
* IMPROVE: Index creation performance

= 1.0.5 - 2025-01-21 =
* NEW: WP-CLI commands
* NEW: Background reindexing
* FIX: Large catalog timeout issues
* IMPROVE: Batch indexing speed

= 1.0.4 - 2025-01-20 =
* NEW: Advanced filters (price, stock, rating)
* NEW: Category facet counts
* FIX: Variable product indexing
* IMPROVE: Query builder optimization

= 1.0.3 - 2025-01-19 =
* NEW: Admin settings page
* NEW: Field weight configuration
* FIX: RTL language support
* IMPROVE: UI/UX enhancements

= 1.0.2 - 2025-01-18 =
* NEW: AJAX search dropdown
* NEW: Search modal (Cmd/K)
* FIX: Pagination issues
* IMPROVE: Relevance scoring

= 1.0.1 - 2025-01-17 =
* FIX: Plugin activation errors
* FIX: WooCommerce compatibility
* IMPROVE: Error handling

= 1.0.0 - 2025-01-15 =
* Initial release
* Core search functionality
* WooCommerce integration
* Basic admin interface

== Upgrade Notice ==

= 1.5.1 =
Security and compatibility improvements! Better prepared SQL queries, removed hardcoded paths, bundled dependencies locally. WordPress.org guidelines compliance. Recommended update!

= 1.0.9 =
Major update! Smart Synonyms System added to prevent zero-result searches. Wildcard prefix matching for better search experience. Update recommended!

= 1.0.8 =
Admin search improvements! Cmd/K modal and product list integration. Update recommended for admin users.

= 1.0.7 =
New inline search widget and shortcode support! Enhanced mobile experience.

== Privacy Policy ==

MantiLoad does not collect or transmit any user data. All search processing happens on your server. Search logs are stored locally in your WordPress database and can be deleted anytime.

== Technical Details ==

**Architecture:**
* Manticore Search 13.0+ (search engine)
* Real-time indexes (RT indexes)
* BM25 relevance scoring
* Proximity ranking
* Multi-valued attributes (MVA)

**Performance:**
* Query time: 2-10ms (typical)
* Indexing: 50-200 products/second
* Memory: ~50-100MB for Manticore
* CPU: Minimal impact

**Security:**
* All inputs sanitized
* SQL injection prevention
* XSS protection
* CSRF tokens on forms
* WordPress nonce verification

== Credits ==

**Developed by:**
MantiLoad Team
https://mantiload.com

**Powered by:**
* Manticore Search - https://manticoresearch.com/
* WordPress - https://wordpress.org/
* WooCommerce - https://woocommerce.com/

**Contributors:**
Want to contribute? Visit our GitHub repository!

== Support ==

Need help? We've got you covered:

* **Documentation**: https://docs.mantiload.com/
* **Support Forum**: https://wordpress.org/support/plugin/mantiload/
* **GitHub Issues**: https://github.com/mantiload/mantiload/issues
* **Email**: support@mantiload.com

== Links ==

* [Website](https://mantiload.com/)
* [Documentation](https://docs.mantiload.com/)
* [GitHub Repository](https://github.com/mantiload/mantiload)
* [Support Forum](https://wordpress.org/support/plugin/mantiload/)
