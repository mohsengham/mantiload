<?php
/**
 * AJAX Search Class
 *
 * Handles instant search functionality with near-zero latency
 *
 * @package MantiLoad
 */

namespace MantiLoad\Search;

defined( 'ABSPATH' ) || exit;

/**
 * AJAX_Search class
 *
 * Provides blazing-fast AJAX search endpoints
 */
class AJAX_Search {

        /**
         * Search engine instance
         *
         * @var Search_Engine
         */
        private $search_engine;

        /**
         * Constructor
         */
        public function __construct() {
                $this->search_engine = new Search_Engine();
                $this->init_hooks();
        }

        /**
         * Initialize hooks
         */
        private function init_hooks() {
                // AJAX endpoints for both logged-in and non-logged-in users
                \add_action( 'wp_ajax_mantiload_search', array( $this, 'ajax_search' ) );
                \add_action( 'wp_ajax_nopriv_mantiload_search', array( $this, 'ajax_search' ) );

                // High-performance REST API endpoint
                \add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

                // Enqueue frontend scripts
                \add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

                // Intercept WoodMart AJAX search
                \add_filter( 'woodmart_ajax_search_args', array( $this, 'intercept_woodmart_search_args' ), 10, 1 );
                \add_filter( 'posts_pre_query', array( $this, 'intercept_woodmart_search_results' ), 10, 2 );

                // Add search modal to page (try wp_body_open first, fallback to wp_footer)
                \add_action( 'wp_body_open', array( $this, 'render_search_modal' ), 1 );
                \add_action( 'wp_footer', array( $this, 'render_search_modal' ), 1 );

                // Cache invalidation: Clear search cache when products change
                \add_action( 'save_post_product', array( $this, 'clear_search_cache' ) );
                \add_action( 'delete_post', array( $this, 'clear_search_cache' ) );
                \add_action( 'woocommerce_update_product', array( $this, 'clear_search_cache' ) );
                \add_action( 'woocommerce_new_product', array( $this, 'clear_search_cache' ) );
                \add_action( 'updated_post_meta', array( $this, 'clear_search_cache_on_meta_update' ), 10, 4 );

                // Cache invalidation: Clear PHP opcache when WooCommerce permalink settings change
                \add_action( 'update_option_woocommerce_permalinks', array( $this, 'clear_opcache_on_permalink_change' ), 10, 2 );
        }

        /**
         * Enqueue frontend scripts and styles
         */
        public function enqueue_scripts() {
                // Only load on frontend
                if ( is_admin() ) {
                        return;
                }

                // Enqueue CSS
                \wp_enqueue_style(
                        'mantiload-search',
                        MANTILOAD_PLUGIN_URL . 'assets/css/search.css',
                        array(),
                        MANTILOAD_VERSION
                );

                // Enqueue theme override CSS (loaded after main CSS for higher specificity)
                \wp_enqueue_style(
                        'mantiload-theme-overrides',
                        MANTILOAD_PLUGIN_URL . 'assets/css/theme-overrides.css',
                        array( 'mantiload-search' ), // Depends on main search CSS
                        MANTILOAD_VERSION
                );

                // Add custom CSS from settings
                $custom_css = \MantiLoad\MantiLoad::get_option( 'custom_css', '' );
                if ( ! empty( $custom_css ) ) {
                        wp_add_inline_style( 'mantiload-theme-overrides', $custom_css );
                }

                // Enqueue JavaScript
                \wp_enqueue_script(
                        'mantiload-search',
                        MANTILOAD_PLUGIN_URL . 'assets/js/search.js',
                        array( 'jquery' ),
                        MANTILOAD_VERSION,
                        true
                );

                // Localize script with AJAX URL and settings
                \wp_localize_script(
                        'mantiload-search',
                        'mantiloadSearch',
                        array(
                                'ajaxUrl'          => \admin_url( 'admin-ajax.php' ),
                                'nonce'            => \wp_create_nonce( 'mantiload_search_nonce' ),
                                'searchDelay'      => \MantiLoad\MantiLoad::get_option( 'search_delay', 300 ),
                                'minChars'         => \MantiLoad\MantiLoad::get_option( 'min_chars', 2 ),
                                'maxResults'       => \MantiLoad\MantiLoad::get_option( 'max_results', 10 ),
                                'showThumbnail'    => \MantiLoad\MantiLoad::get_option( 'show_thumbnail', true ),
                                'showPrice'        => \MantiLoad\MantiLoad::get_option( 'show_price', true ),
                                'showSKU'          => \MantiLoad\MantiLoad::get_option( 'show_sku', false ),
                                'showExcerpt'      => \MantiLoad\MantiLoad::get_option( 'show_excerpt', false ),
                                'showCategories'   => \MantiLoad\MantiLoad::get_option( 'show_categories_in_search', false ),
                                'maxCategories'    => \MantiLoad\MantiLoad::get_option( 'max_categories', 5 ),
                                'postTypes'        => \MantiLoad\MantiLoad::get_option( 'ajax_search_post_types', array( 'product' ) ),
                                'keyboardShortcut' => \MantiLoad\MantiLoad::get_option( 'keyboard_shortcut', 'ctrl+k' ),
                                'enableModal'      => false, // Always false, admin modal handled separately
                                'isRTL'            => is_rtl(),
                                 'strings'          => array(
                                         'placeholder'   => __( 'Search products...', 'mantiload' ),
                                         'searching'     => __( 'Searching...', 'mantiload' ),
                                         'noResults'     => __( 'No results found', 'mantiload' ),
                                         'viewAll'       => __( 'View all results', 'mantiload' ),
                                         'pressEnter'    => __( 'Press Enter to search', 'mantiload' ),
                                         'results'       => __( 'results', 'mantiload' ),
                                         'in'            => __( 'in', 'mantiload' ),
                                         'ms'            => __( 'ms', 'mantiload' ),
                                         'products'      => __( 'Products', 'mantiload' ),
                                         'categories'    => __( 'Categories', 'mantiload' ),
                                 ),
                        )
                );

                // For administrators, also load the admin search modal
                if ( current_user_can( 'administrator' ) ) {
                        \wp_enqueue_style(
                                'mantiload-admin-search',
                                MANTILOAD_PLUGIN_URL . 'assets/css/admin-search.css',
                                array(),
                                MANTILOAD_VERSION
                        );

                        \wp_enqueue_script(
                                'mantiload-admin-search',
                                MANTILOAD_PLUGIN_URL . 'assets/js/admin-search.js',
                                array( 'jquery' ),
                                MANTILOAD_VERSION,
                                true
                        );

                        \wp_localize_script(
                                'mantiload-admin-search',
                                'mantiloadAdmin',
                                array(
                                        'nonce'     => \wp_create_nonce( 'mantiload_admin_search' ),
                                        'adminUrl'  => \admin_url(),
                                        'strings'   => array(
                                                'noResults' => __( 'No results found', 'mantiload' ),
                                                'searching' => __( 'Searching...', 'mantiload' ),
                                        ),
                                )
                        );
                }
        }

        /**
         * AJAX search handler - ULTRA FAST (bypasses WordPress overhead)
         */
        public function ajax_search() {
                // START TIMER (measure EVERYTHING)
                $start_time = microtime( true );

                // FAST nonce check (use false to return boolean instead of dying)
                if ( ! \check_ajax_referer( 'mantiload_search_nonce', 'nonce', false ) ) {
                        \wp_send_json_error( array(
                                'message' => 'Security check failed',
                        ) );
                }

                // Get search query (fast sanitization)
                $query = isset( $_GET['q'] ) ? \sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';

                // Normalize Persian/Arabic numerals to Latin for consistent search
                $query = \MantiLoad\Manticore_Client::normalize_numerals( $query );

                // Minimum characters check (hardcoded for speed)
                if ( strlen( $query ) < 2 ) {
                        \wp_send_json_error( array(
                                'message' => 'Enter at least 2 characters',
                        ) );
                }

                // BEAST MODE: Use direct MantiCore search (bypasses WordPress Query)
                $results = $this->ultra_fast_search( $query );

                // Search categories if enabled (cached setting check)
                static $show_categories = null;
                static $max_categories = null;
                if ( $show_categories === null ) {
                        $show_categories = (bool) \MantiLoad\MantiLoad::get_option( 'show_categories_in_search', false );
                        $max_categories = (int) \MantiLoad\MantiLoad::get_option( 'max_categories', 5 );
                }

                $categories = array();
                if ( $show_categories ) {
                        $categories = $this->search_categories( $query, $max_categories );
                }

                // Calculate total execution time
                $total_time = ( microtime( true ) - $start_time ) * 1000;

                // Send JSON response (no translation overhead)
                \wp_send_json_success( array(
                        'results'       => $results['items'],
                        'categories'    => $categories,
                        'total'         => $results['total'],
                        'query_time'    => round( $results['manticore_time'] ), // Pure MantiCore time (rounded)
                        'total_time'    => round( $total_time, 2 ), // Total PHP + MantiCore
                        'query'         => $query,
                        'view_all_url'  => \home_url( '/?s=' . urlencode( $query ) . '&post_type=product' ),
                ) );
        }

        /**
         * Register REST API routes for ultra-fast search
         */
        public function register_rest_routes() {
                \register_rest_route( 'mantiload/v1', '/search', array(
                        'methods' => 'POST',
                        'callback' => array( $this, 'rest_search' ),
                        'permission_callback' => '__return_true',
                        'args' => array(
                                'query' => array(
                                        'required' => false,
                                        'sanitize_callback' => 'sanitize_text_field',
                                ),
                                'post_type' => array(
                                        'required' => false,
                                        'default' => 'product',
                                        'sanitize_callback' => 'sanitize_text_field',
                                ),
                                'limit' => array(
                                        'required' => false,
                                        'default' => 10,
                                        'sanitize_callback' => 'absint',
                                ),
                        ),
                ) );
        }

        /**
         * REST API search endpoint - Maximum performance
         */
        public function rest_search( $request ) {
                try {
                // Get all params (query + body)
                $params = $request->get_params();

                $query = sanitize_text_field( $params['q'] ?? '' );
                $post_type = sanitize_text_field( ($params['post_types'][0] ?? $params['post_type']) ?? 'product' );
                $limit = absint( $params['limit'] ?? 10 );

                        // Normalize Persian/Arabic numerals to Latin for consistent search
                        $query = \MantiLoad\Manticore_Client::normalize_numerals( $query );

                        // Minimum characters check - temporarily disabled for debugging
                        // if ( strlen( $query ) < 2 ) {
                        //         error_log( '[MantiLoad] REST: Query too short' );
                        //         return new \WP_Error( 'search_error', 'Enter at least 2 characters', array( 'status' => 400 ) );
                        // }

                        // START TIMER
                        $start_time = microtime( true );

                        // BEAST MODE: Use direct MantiCore search
                        $results = $this->ultra_fast_search( $query );

                        // Search categories if enabled
                        $categories = array();
                        if ( \MantiLoad\MantiLoad::get_option( 'show_categories_in_search', false ) ) {
                                $max_categories = \MantiLoad\MantiLoad::get_option( 'max_categories', 5 );
                                $categories = $this->search_categories( $query, $max_categories );
                        }

                        // Calculate total execution time
                        $total_time = ( microtime( true ) - $start_time ) * 1000;

                        $response_data = array(
                                'results'       => $results['items'],
                                'categories'    => $categories,
                                'total'         => $results['total'],
                                'query_time'    => round( $results['manticore_time'] ),
                                'total_time'    => round( $total_time, 2 ),
                                'query'         => $query,
                                'view_all_url'  => \home_url( '/?s=' . urlencode( $query ) . '&post_type=product' ),
                        );

                        return new \WP_REST_Response( $response_data, 200 );
                } catch ( \Exception $e ) {
                        return new \WP_Error( 'search_error', $e->getMessage(), array( 'status' => 500 ) );
                }
        }

        /**
         * ULTRA FAST search - Bypasses ALL WordPress overhead
         *
         * What we bypass:
         * - WP_Query and WordPress query system
         * - WooCommerce product objects (wc_get_product)
         * - \get_permalink() - we build URLs manually
         * - Translation functions (__) on every item
         * - \get_option() calls - we cache settings
         * - wp_get_attachment_url() - direct DB queries
         *
         * @param string $query Search query
         * @return array
         */
        private function ultra_fast_search( $query ) {
                global $wpdb;
                static $settings_cache = null;
                static $synonyms_manager = null;

                // Cache settings for entire session (avoid \get_option() calls)
                if ( $settings_cache === null ) {
                        // Get WooCommerce product base from permalink settings
                        $wc_permalinks = \get_option( 'woocommerce_permalinks', array() );
                        $product_base = isset( $wc_permalinks['product_base'] ) && $wc_permalinks['product_base'] !== ''
                                ? trim( $wc_permalinks['product_base'], '/' )
                                : 'product';

                        $settings_cache = array(
                                'max_results'         => (int) \MantiLoad\MantiLoad::get_option( 'max_results', 10 ),
                                'prioritize_in_stock' => (bool) \MantiLoad\MantiLoad::get_option( 'prioritize_in_stock', true ),
                                'currency_symbol'     => function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '$',
                                'home_url'            => \home_url( '/' ),
                                'product_base'        => $product_base,
                                'manticore_host'      => \MantiLoad\MantiLoad::get_option( 'manticore_host', MANTILOAD_HOST ),
                                'manticore_port'      => (int) \MantiLoad\MantiLoad::get_option( 'manticore_port', MANTILOAD_PORT ),
                                'index_name'          => \MantiLoad\MantiLoad::get_option( 'index_name', 'mantiload_fast_one' ),
                        );
                }

                $limit = $settings_cache['max_results'];

                // Reuse synonyms manager instance (saves object creation overhead)
                if ( $synonyms_manager === null ) {
                        $synonyms_manager = new \MantiLoad\Search\Synonyms();
                }
                $expanded_query = $synonyms_manager->expand_query( $query );

                // Normalize expanded query too (synonyms might add Persian/Arabic numerals)
                $expanded_query = \MantiLoad\Manticore_Client::normalize_numerals( $expanded_query );

                // CACHE: Check if we have cached results for this exact search
                // Cache key includes query + settings to ensure accuracy
                $cache_key = 'mantiload_search_' . md5( $expanded_query . '_' . $limit . '_' . (int) $settings_cache['prioritize_in_stock'] );
                $cached_result = \get_transient( $cache_key );

                if ( $cached_result !== false ) {
                        // ULTRA FAST! Return cached results (saves 4-7ms!)
                        // Cache is cleared automatically when products change
                        return $cached_result;
                }

                // PRIORITY: Check for exact SKU match FIRST (before Manticore) - case-insensitive
                // This handles both product and variation SKUs
                // For frontend, we want to show the PARENT product when a variation SKU matches
                $sku_results = $wpdb->get_results( $wpdb->prepare(
                        "SELECT p.ID, p.post_type, p.post_parent FROM {$wpdb->posts} p
                        INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                        WHERE p.post_type IN ('product', 'product_variation')
                        AND p.post_status = 'publish'
                        AND pm.meta_key = '_sku'
                        AND UPPER(pm.meta_value) = UPPER(%s)
                        LIMIT %d",
                        $query,
                        $limit
                ) );

                $exact_sku_ids = array();
                if ( ! empty( $sku_results ) ) {
                        foreach ( $sku_results as $result ) {
                                // If it's a variation, add the PARENT product ID for frontend display
                                // (frontend shop shows parent products, not individual variations)
                                if ( $result->post_type === 'product_variation' && $result->post_parent > 0 ) {
                                        $exact_sku_ids[] = $result->post_parent;
                                } else {
                                        $exact_sku_ids[] = $result->ID;
                                }
                        }
                }

                // TIME: MantiCore query start
                $manticore_start = microtime( true );

                // Initialize product IDs
                $product_ids = array();
                $total_found = 0;

                // Only query Manticore if we didn't find an exact SKU match
                if ( empty( $exact_sku_ids ) ) {
                        // Direct MantiCore connection (bypass WordPress)
                        // Use cached connection settings (saves 2x get_option calls per search!)
                        // phpcs:ignore WordPress.DB.RestrictedClasses.mysql__mysqli -- Direct mysqli required for Manticore Search connection
                        $manticore = new \mysqli( $settings_cache['manticore_host'], '', '', '', $settings_cache['manticore_port'] );

                        if ( $manticore->connect_error ) {
                                return array( 'items' => array(), 'total' => 0, 'manticore_time' => 0 );
                        }

                        // Use cached index name
                        $index_name = $settings_cache['index_name'];

                        // Escape expanded query for MantiCore
                        $safe_query = $manticore->real_escape_string( $expanded_query );

                        // Ultra-fast MantiCore query (pure speed + synonyms!)
                        // Apply proper filters: product type, published status, search visibility
                        $sql = "SELECT id, WEIGHT() as relevance
                                        FROM {$index_name}
                                        WHERE MATCH('$safe_query')
                                          AND post_type='product'
                                          AND post_status='publish'
                                          AND visibility != 'catalog'
                                          AND visibility != 'hidden'
                                        ORDER BY relevance DESC
                                        LIMIT $limit";

                        $manticore_result = $manticore->query( $sql );

                        if ( ! $manticore_result ) {
                                $manticore->close();
                                $manticore_time = ( microtime( true ) - $manticore_start ) * 1000;
                                return array( 'items' => array(), 'total' => 0, 'manticore_time' => $manticore_time );
                        }

                        while ( $row = $manticore_result->fetch_assoc() ) {
                                $product_ids[] = (int) $row['id'];
                        }

                        // Get TOTAL count using SHOW META (FAST! No duplicate search)
                        // SHOW META returns stats from the last query, including total_found
                        // This eliminates a second COUNT query (saves 1-2ms per search)
                        $meta_result = $manticore->query( "SHOW META" );
                        $total_count = 0;

                        if ( $meta_result ) {
                                // Parse meta variables to find total_found
                                while ( $meta_row = $meta_result->fetch_assoc() ) {
                                        if ( $meta_row['Variable_name'] === 'total_found' ) {
                                                $total_count = (int) $meta_row['Value'];
                                                break;
                                        }
                                }
                        }

                        // Safety fallback: if we got results but total_count is 0, use result count
                        // This handles edge cases where SHOW META might not return expected data
                        if ( $total_count === 0 && ! empty( $product_ids ) ) {
                                $total_count = count( $product_ids );
                        }

                        $manticore->close();
                } else {
                        // Use exact SKU match results
                        $product_ids = array_map( 'intval', $exact_sku_ids );
                        $total_count = count( $product_ids );
                }
                $manticore_time = ( microtime( true ) - $manticore_start ) * 1000;

                if ( empty( $product_ids ) ) {
                        return array( 'items' => array(), 'total' => 0, 'manticore_time' => $manticore_time );
                }

                // LIMIT results for performance (admin search shows 50 results)
                $max_display_results = 50;
                if ( count( $product_ids ) > $max_display_results ) {
                        $product_ids = array_slice( $product_ids, 0, $max_display_results );
                }

                // Get data from WordPress using IN query (simplified)
                // Note: $product_ids is already sanitized via (int) cast from Manticore results
                $ids_str = implode( ',', array_map( 'absint', $product_ids ) );

                // OPTIMIZATION: Combine posts and meta into single query with JOIN
                // This saves 1 round-trip to database (~0.5-1ms)
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $ids_str contains only integers from absint()
                $combined_data = $wpdb->get_results(
                        "SELECT p.ID, p.post_title, p.post_name, p.post_type,
                                MAX(CASE WHEN pm.meta_key = '_thumbnail_id' THEN pm.meta_value END) as thumbnail_id,
                                MAX(CASE WHEN pm.meta_key = '_price' THEN pm.meta_value END) as price,
                                MAX(CASE WHEN pm.meta_key = '_stock_status' THEN pm.meta_value END) as stock_status
                        FROM {$wpdb->posts} p
                        LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                                AND pm.meta_key IN ('_thumbnail_id', '_price', '_stock_status')
                        WHERE p.ID IN ({$ids_str}) AND p.post_type IN ('product', 'product_variation')
                        GROUP BY p.ID
                        ORDER BY p.ID",
                        OBJECT_K
                );

                // Build products and meta_by_post from combined result
                $products = array();
                $meta_by_post = array();
                foreach ( $combined_data as $id => $row ) {
                        $products[ $id ] = $row;
                        $meta_by_post[ $id ] = array(
                                '_thumbnail_id' => $row->thumbnail_id,
                                '_price' => $row->price,
                                '_stock_status' => $row->stock_status,
                        );
                }

                // Get thumbnail IDs
                $thumb_ids = array();
                foreach ( $meta_by_post as $post_id => $meta ) {
                        if ( ! empty( $meta['_thumbnail_id'] ) ) {
                                $thumb_ids[] = (int) $meta['_thumbnail_id'];
                        }
                }

                // Fetch thumbnail URLs
                $thumb_urls = array();
                if ( ! empty( $thumb_ids ) ) {
                        $thumb_ids_str = implode( ',', array_map( 'absint', $thumb_ids ) );

                        // Get attachment metadata
                        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $thumb_ids_str contains only integers from absint()
                        $thumb_meta = $wpdb->get_results(
                                $wpdb->prepare(
                                        "SELECT post_id, meta_value
                                        FROM {$wpdb->postmeta}
                                        WHERE post_id IN ({$thumb_ids_str}) AND meta_key = %s",
                                        '_wp_attachment_metadata'
                                ),
                                OBJECT_K
                        );

                        // Cache upload directory (wp_upload_dir is expensive - ~0.5ms)
                        static $upload_base_url = null;
                        if ( $upload_base_url === null ) {
                                $upload_dir = wp_upload_dir();
                                $upload_base_url = $upload_dir['baseurl'];
                        }
                        $base_url = $upload_base_url;

                        foreach ( $thumb_meta as $thumb_id => $meta_row ) {
                                $metadata = maybe_unserialize( $meta_row->meta_value );
                                if ( ! empty( $metadata['file'] ) ) {
                                        // Check if thumbnail size exists
                                        if ( ! empty( $metadata['sizes']['thumbnail']['file'] ) ) {
                                                $dirname = dirname( $metadata['file'] );
                                                $thumb_file = $metadata['sizes']['thumbnail']['file'];
                                                $thumb_urls[ $thumb_id ] = $base_url . '/' . $dirname . '/' . $thumb_file;
                                        } else {
                                                // Fallback to full size
                                                $thumb_urls[ $thumb_id ] = $base_url . '/' . $metadata['file'];
                                        }
                                }
                        }
                }

                // Build results ULTRA FAST (no WordPress functions!)
                $items = array();
                $currency = $settings_cache['currency_symbol'];
                $home_url = $settings_cache['home_url'];

                foreach ( $product_ids as $product_id ) {
                        if ( ! isset( $products[ $product_id ] ) ) {
                                continue;
                        }

                        $product = $products[ $product_id ];
                        $meta = isset( $meta_by_post[ $product_id ] ) ? $meta_by_post[ $product_id ] : array();

                        // Build permalink manually (MUCH faster than get_permalink)
                        // Supports all post types dynamically
                        $url = $this->build_permalink(
                                $product->post_type,
                                $product->post_name,
                                $home_url,
                                $settings_cache['product_base']
                        );

                        // Get actual price, stock, thumbnail from meta
                        $price_raw = ! empty( $meta['_price'] ) ? floatval( $meta['_price'] ) : 0;
                        $price_html = $price_raw > 0 ? $currency . ' ' . number_format( $price_raw, 0 ) : '';
                        $stock_status = ! empty( $meta['_stock_status'] ) ? $meta['_stock_status'] : 'outofstock';
                        $in_stock = ( $stock_status === 'instock' );
                        
                        // Get thumbnail URL
                        $thumbnail = '';
                        if ( ! empty( $meta['_thumbnail_id'] ) && isset( $thumb_urls[ $meta['_thumbnail_id'] ] ) ) {
                                $thumbnail = $thumb_urls[ $meta['_thumbnail_id'] ];
                        }

                        // Highlight search term in title (simple, fast)
                        $title = $this->fast_highlight( $product->post_title, $query );

                        $item = array(
                                'id'           => $product_id,
                                'title'        => $title,
                                'url'          => $url,
                                'type'         => 'product',
                                'thumbnail'    => $thumbnail,
                                'price'        => $price_html,
                                'price_raw'    => $price_raw,
                                'in_stock'     => $in_stock,
                                'stock_status' => $stock_status,
                        );

                         $items[] = $item;
                 }

                // Sort by stock (in-stock first) if enabled
                if ( $settings_cache['prioritize_in_stock'] ) {
                        usort( $items, function( $a, $b ) {
                                return (int) $b['in_stock'] - (int) $a['in_stock'];
                        } );
                }

                $result = array(
                        'items'          => $items,
                        'total'          => $total_count, // Total matching products in MantiCore
                        'manticore_time' => $manticore_time,
                );

                // CACHE: Store results for 24 hours (cleared when products change)
                // This makes repeat searches INSTANT! (<0.5ms vs 4-7ms)
                \set_transient( $cache_key, $result, DAY_IN_SECONDS );

                return $result;
        }

        /**
         * Fast highlight (no preg_replace overhead on critical path)
         *
         * @param string $text  Text to highlight
         * @param string $query Search query
         * @return string
         */
        private function fast_highlight( $text, $query ) {
                // Simple case-insensitive highlighting (faster than regex)
                $pos = stripos( $text, $query );
                if ( $pos !== false ) {
                        $len = strlen( $query );
                        $matched = substr( $text, $pos, $len );
                        return substr_replace( $text, '<mark>' . $matched . '</mark>', $pos, $len );
                }
                return $text;
        }

        /**
         * Build permalink manually for any post type (MUCH faster than get_permalink)
         *
         * @param string $post_type Post type
         * @param string $post_name Post slug/name
         * @param string $home_url  Home URL (with trailing slash)
         * @param string $product_base Product base for WooCommerce (if applicable)
         * @return string Permalink URL
         */
        private function build_permalink( $post_type, $post_name, $home_url, $product_base = 'product' ) {
                switch ( $post_type ) {
                        case 'product':
                                // WooCommerce product - use dynamic product base
                                return $home_url . $product_base . '/' . $post_name . '/';

                        case 'post':
                                // Regular post - use permalink structure
                                // For simplicity and speed, assume pretty permalinks (most common)
                                // If using default (?p=123), \get_permalink() should be used instead
                                return $home_url . $post_name . '/';

                        case 'page':
                                // Page - direct URL
                                return $home_url . $post_name . '/';

                        default:
                                // Custom post type - try to get rewrite slug
                                $post_type_object = get_post_type_object( $post_type );
                                if ( $post_type_object && isset( $post_type_object->rewrite['slug'] ) ) {
                                        $slug = $post_type_object->rewrite['slug'];
                                        return $home_url . $slug . '/' . $post_name . '/';
                                }

                                // Fallback: use post type as slug
                                return $home_url . $post_type . '/' . $post_name . '/';
                }
        }

        /**
         * Fast result formatter - optimized for AJAX
         *
         * @param array  $posts WP_Post objects
         * @param string $query Search query
         * @return array
         */
        private function format_results_fast( $posts, $query ) {
                if ( empty( $posts ) ) {
                        return array();
                }

                global $wpdb;
                $results = array();

                // Get WooCommerce product base from permalink settings
                $wc_permalinks = \get_option( 'woocommerce_permalinks', array() );
                $product_base = isset( $wc_permalinks['product_base'] ) && $wc_permalinks['product_base'] !== ''
                        ? trim( $wc_permalinks['product_base'], '/' )
                        : 'product';

                // Get all post IDs
                $post_ids = wp_list_pluck( $posts, 'ID' );
                $ids_str = implode( ',', array_map( 'intval', $post_ids ) );

                // Fetch ALL meta in ONE query (FAST!)
                $meta_data = $wpdb->get_results(
                        "SELECT post_id, meta_key, meta_value
                        FROM {$wpdb->postmeta}
                        WHERE post_id IN ($ids_str)
                        AND meta_key IN ('_thumbnail_id', '_price', '_stock_status')
                        ORDER BY post_id",
                        ARRAY_A
                );

                // Organize meta by post ID
                $meta_by_post = array();
                foreach ( $meta_data as $meta ) {
                        $post_id = $meta['post_id'];
                        if ( ! isset( $meta_by_post[ $post_id ] ) ) {
                                $meta_by_post[ $post_id ] = array();
                        }
                        $meta_by_post[ $post_id ][ $meta['meta_key'] ] = $meta['meta_value'];
                }

                // Get thumbnail IDs
                $thumb_ids = array();
                foreach ( $meta_by_post as $post_id => $meta ) {
                        if ( ! empty( $meta['_thumbnail_id'] ) ) {
                                $thumb_ids[] = (int) $meta['_thumbnail_id'];
                        }
                }

                // Fetch thumbnail URLs - get 150x150 size from wp_postmeta
                $thumb_urls = array();
                if ( ! empty( $thumb_ids ) ) {
                        $thumb_ids_str = implode( ',', $thumb_ids );

                        // Get attachment metadata (has sizes info)
                        $thumb_meta = $wpdb->get_results(
                                "SELECT post_id, meta_value
                                FROM {$wpdb->postmeta}
                                WHERE post_id IN ($thumb_ids_str) AND meta_key = '_wp_attachment_metadata'",
                                OBJECT_K
                        );

                        // Get upload directory
                        $upload_dir = wp_upload_dir();
                        $base_url = $upload_dir['baseurl'];

                        foreach ( $thumb_meta as $thumb_id => $meta_row ) {
                                $metadata = maybe_unserialize( $meta_row->meta_value );
                                if ( ! empty( $metadata['file'] ) ) {
                                        // Check if thumbnail size exists
                                        if ( ! empty( $metadata['sizes']['thumbnail']['file'] ) ) {
                                                $dirname = dirname( $metadata['file'] );
                                                $thumb_file = $metadata['sizes']['thumbnail']['file'];
                                                $thumb_urls[ $thumb_id ] = $base_url . '/' . $dirname . '/' . $thumb_file;
                                        } else {
                                                // Fallback to full size
                                                $thumb_urls[ $thumb_id ] = $base_url . '/' . $metadata['file'];
                                        }
                                }
                        }
                }

                // Get currency symbol once
                $currency_symbol = get_woocommerce_currency_symbol();

                // Build results FAST (no WooCommerce objects needed!)
                foreach ( $posts as $post ) {
                        $product_id = $post->ID;
                        $meta = isset( $meta_by_post[ $product_id ] ) ? $meta_by_post[ $product_id ] : array();

                        // Thumbnail URL (direct, no WordPress functions)
                        $thumbnail_url = '';
                        if ( ! empty( $meta['_thumbnail_id'] ) && isset( $thumb_urls[ $meta['_thumbnail_id'] ] ) ) {
                                $thumbnail_url = $thumb_urls[ $meta['_thumbnail_id'] ];
                        }

                        // Price (format manually - FAST!)
                        $price_raw = ! empty( $meta['_price'] ) ? floatval( $meta['_price'] ) : 0;
                        $price_html = $price_raw > 0 ? $currency_symbol . ' ' . number_format( $price_raw, 0 ) : '';

                        // Stock status
                        $stock_status = ! empty( $meta['_stock_status'] ) ? $meta['_stock_status'] : 'outofstock';
                        $in_stock = ( $stock_status === 'instock' );

                        // Build permalink manually (MUCH faster than get_permalink)
                        // Supports all post types dynamically
                        $url = $this->build_permalink(
                                $post->post_type,
                                $post->post_name,
                                \home_url( '/' ),
                                $product_base
                        );

                        $item = array(
                                'id'           => $product_id,
                                'title'        => $this->highlight_query( $post->post_title, $query ),
                                'url'          => $url,
                                'type'         => 'product',
                                'thumbnail'    => $thumbnail_url,
                                'price'        => $price_html,
                                'price_raw'    => $price_raw,
                                'in_stock'     => $in_stock,
                                'stock_status' => $stock_status,
                        );

                        $results[] = $item;
                }

                // Sort by stock if enabled
                if ( \MantiLoad\MantiLoad::get_option( 'prioritize_in_stock', true ) ) {
                        usort( $results, function( $a, $b ) {
                                return (int) $b['in_stock'] - (int) $a['in_stock'];
                        } );
                }

                return $results;
        }

        /**
         * Direct Manticore search - ULTRA FAST (bypasses WordPress)
         *
         * @param string $query Search query
         * @param int    $limit Max results
         * @return array
         */
        private function direct_manticore_search( $query, $limit = 10 ) {
                global $wpdb;

                // Connect directly to Manticore
                // Get connection details from settings
                $host = \MantiLoad\MantiLoad::get_option( 'manticore_host', MANTILOAD_HOST );
                $port = (int) \MantiLoad\MantiLoad::get_option( 'manticore_port', MANTILOAD_PORT );

                // phpcs:ignore WordPress.DB.RestrictedClasses.mysql__mysqli -- Direct mysqli required for Manticore Search connection
                $manticore = new \mysqli( $host, '', '', '', $port );

                if ( $manticore->connect_error ) {
                        return array( 'results' => array(), 'total' => 0 );
                }

                // Get index name from settings
                $index_name = \MantiLoad\MantiLoad::get_option( 'index_name', 'mantiload_fast_one' );

                // Normalize Persian/Arabic numerals to Latin
                $query = \MantiLoad\Manticore_Client::normalize_numerals( $query );

                // Escape query
                $safe_query = $manticore->real_escape_string( $query );

                // Direct Manticore SQL - ULTRA FAST
                $sql = "SELECT id, title, price, sku, stock_status, WEIGHT() as relevance
                                FROM {$index_name}
                                WHERE MATCH('$safe_query')
                                ORDER BY relevance DESC
                                LIMIT $limit";

                $manticore_result = $manticore->query( $sql );

                if ( ! $manticore_result ) {
                        $manticore->close();
                        return array( 'results' => array(), 'total' => 0 );
                }

                $product_ids = array();
                $manticore_data = array();

                while ( $row = $manticore_result->fetch_assoc() ) {
                        $product_ids[] = (int) $row['id'];
                        $manticore_data[ $row['id'] ] = $row;
                }

                $manticore->close();

                if ( empty( $product_ids ) ) {
                        return array( 'results' => array(), 'total' => 0 );
                }

                // Get ONLY what we need from WordPress in ONE query
                $ids_str = implode( ',', $product_ids );
                $posts = $wpdb->get_results(
                        "SELECT ID, post_title FROM {$wpdb->posts} WHERE ID IN ($ids_str)",
                        OBJECT_K
                );

                // Get thumbnails in ONE query
                $thumbnails = $wpdb->get_results(
                        "SELECT post_id, meta_value as thumb_id
                        FROM {$wpdb->postmeta}
                        WHERE post_id IN ($ids_str) AND meta_key = '_thumbnail_id'",
                        OBJECT_K
                );

                // Get thumbnail URLs in ONE query
                $thumb_ids = array_filter( wp_list_pluck( $thumbnails, 'thumb_id' ) );
                $thumb_urls = array();
                if ( ! empty( $thumb_ids ) ) {
                        $thumb_ids_str = implode( ',', array_map( 'intval', $thumb_ids ) );
                        $thumb_data = $wpdb->get_results(
                                "SELECT post_id, guid
                                FROM {$wpdb->posts}
                                WHERE ID IN ($thumb_ids_str) AND post_type = 'attachment'",
                                OBJECT_K
                        );
                        foreach ( $thumb_data as $thumb_id => $data ) {
                                $thumb_urls[ $thumb_id ] = $data->guid;
                        }
                }

                // Get prices in ONE query
                $prices = $wpdb->get_results(
                        "SELECT post_id, meta_value as price
                        FROM {$wpdb->postmeta}
                        WHERE post_id IN ($ids_str) AND meta_key = '_price'",
                        OBJECT_K
                );

                // Format results
                $results = array();
                $prioritize = \MantiLoad\MantiLoad::get_option( 'prioritize_in_stock', true );

                foreach ( $product_ids as $product_id ) {
                        if ( ! isset( $posts[ $product_id ] ) ) {
                                continue;
                        }

                        $manticore_row = $manticore_data[ $product_id ];
                        $post = $posts[ $product_id ];

                        // Get thumbnail URL
                        $thumbnail_url = '';
                        if ( isset( $thumbnails[ $product_id ] ) ) {
                                $thumb_id = $thumbnails[ $product_id ]->thumb_id;
                                if ( isset( $thumb_urls[ $thumb_id ] ) ) {
                                        $thumbnail_url = $thumb_urls[ $thumb_id ];
                                }
                        }
                        if ( empty( $thumbnail_url ) && function_exists( 'wc_placeholder_img_src' ) ) {
                                $thumbnail_url = wc_placeholder_img_src();
                        }

                        // Get price
                        $price_raw = isset( $prices[ $product_id ] ) ? floatval( $prices[ $product_id ]->price ) : 0;
                        $currency_symbol = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '';
                        $price_html = $price_raw > 0 ? $currency_symbol . ' ' . number_format( $price_raw, 0 ) : '';

                        // Stock status
                        $stock_status = $manticore_row['stock_status'];
                        $in_stock = ( $stock_status === 'instock' );

                        $item = array(
                                'id'           => $product_id,
                                'title'        => $this->highlight_query( $post->post_title, $query ),
                                'url'          => \get_permalink( $product_id ),
                                'type'         => 'product',
                                'thumbnail'    => $thumbnail_url,
                                'price'        => $price_html,
                                'price_raw'    => $price_raw,
                                'in_stock'     => $in_stock,
                                'stock_status' => $stock_status,
                        );

                        if ( ! empty( $manticore_row['sku'] ) ) {
                                $item['sku'] = $this->highlight_query( $manticore_row['sku'], $query );
                        }

                        $results[] = $item;
                }

                // Sort by stock status if enabled (in-stock first, then by relevance)
                $prioritize = \MantiLoad\MantiLoad::get_option( 'prioritize_in_stock', true );
                if ( $prioritize && ! empty( $results ) ) {
                        usort( $results, function( $a, $b ) {
                                // First sort by stock status (in-stock = 1, out of stock = 0)
                                $stock_diff = (int) $b['in_stock'] - (int) $a['in_stock'];
                                if ( $stock_diff !== 0 ) {
                                        return $stock_diff;
                                }
                                // If same stock status, maintain original relevance order
                                return 0;
                        } );
                }

                return array(
                        'results' => $results,
                        'total'   => count( $results ),
                );
        }

        /**
         * Format search results for AJAX response
         *
         * @param array  $posts Posts array
         * @param string $query Search query
         * @return array
         */
        private function format_results( $posts, $query ) {
                $results = array();

                $show_thumbnail = \MantiLoad\MantiLoad::get_option( 'show_thumbnail', true );
                $show_price = \MantiLoad\MantiLoad::get_option( 'show_price', true );
                $show_sku = \MantiLoad\MantiLoad::get_option( 'show_sku', false );
                $show_excerpt = \MantiLoad\MantiLoad::get_option( 'show_excerpt', false );

                // Pre-fetch all post IDs for bulk operations
                $post_ids = wp_list_pluck( $posts, 'ID' );

                // Pre-fetch thumbnails in one query
                $thumbnails = array();
                if ( $show_thumbnail && ! empty( $post_ids ) ) {
                        global $wpdb;
                        $ids_str = implode( ',', array_map( 'intval', $post_ids ) );
                        $thumbnail_data = $wpdb->get_results(
                                "SELECT post_id, meta_value FROM {$wpdb->postmeta}
                                WHERE post_id IN ($ids_str) AND meta_key = '_thumbnail_id'",
                                OBJECT_K
                        );
                        foreach ( $thumbnail_data as $post_id => $data ) {
                                $thumb_id = $data->meta_value;
                                $thumbnails[ $post_id ] = wp_get_attachment_image_url( $thumb_id, 'thumbnail' );
                        }
                }

                foreach ( $posts as $post ) {
                        $item = array(
                                'id'    => $post->ID,
                                'title' => $this->highlight_query( $post->post_title, $query ),
                                'url'   => \get_permalink( $post ),
                                'type'  => $post->post_type,
                        );

                        // Add thumbnail
                        if ( $show_thumbnail ) {
                                $item['thumbnail'] = isset( $thumbnails[ $post->ID ] ) ? $thumbnails[ $post->ID ] : '';
                                if ( empty( $item['thumbnail'] ) && function_exists( 'wc_placeholder_img_src' ) ) {
                                        $item['thumbnail'] = wc_placeholder_img_src();
                                }
                        }

                        // Add WooCommerce specific data
                        if ( $post->post_type === 'product' && function_exists( 'wc_get_product' ) ) {
                                $product = wc_get_product( $post->ID );

                                if ( $product ) {
                                        if ( $show_price ) {
                                                $price_raw = $product->get_price();
                                                $currency_symbol = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '';
                                                $item['price'] = $price_raw > 0 ? $currency_symbol . ' ' . number_format( $price_raw, 0 ) : '';
                                                $item['price_raw'] = $price_raw;
                                        }

                                        if ( $show_sku ) {
                                                $sku = $product->get_sku();
                                                if ( $sku ) {
                                                        $item['sku'] = $this->highlight_query( $sku, $query );
                                                }
                                        }

                                        // Stock status
                                        $item['in_stock'] = $product->is_in_stock();
                                        $item['stock_status'] = $product->get_stock_status();
                                }
                        }

                        // Add excerpt
                        if ( $show_excerpt && ! empty( $post->post_excerpt ) ) {
                                $excerpt = wp_trim_words( $post->post_excerpt, 15 );
                                $item['excerpt'] = $this->highlight_query( $excerpt, $query );
                        }

                        $results[] = $item;
                }

                // Sort by stock status if enabled (in-stock first)
                if ( \MantiLoad\MantiLoad::get_option( 'prioritize_in_stock', true ) ) {
                        usort( $results, function( $a, $b ) {
                                $a_in_stock = isset( $a['in_stock'] ) ? (int) $a['in_stock'] : 0;
                                $b_in_stock = isset( $b['in_stock'] ) ? (int) $b['in_stock'] : 0;
                                return $b_in_stock - $a_in_stock; // In stock first
                        } );
                }

                return $results;
        }

        /**
         * Highlight search query in text
         *
         * @param string $text  Text to highlight
         * @param string $query Search query
         * @return string
         */
        private function highlight_query( $text, $query ) {
                if ( empty( $query ) ) {
                        return $text;
                }

                $words = explode( ' ', $query );
                foreach ( $words as $word ) {
                        if ( strlen( $word ) < 2 ) {
                                continue;
                        }
                        $text = preg_replace(
                                '/(' . preg_quote( $word, '/' ) . ')/iu',
                                '<mark>$1</mark>',
                                $text
                        );
                }

                return $text;
        }

        /**
         * Get thumbnail URL
         *
         * @param \WP_Post $post Post object
         * @return string
         */
        private function get_thumbnail_url( $post ) {
                if ( has_post_thumbnail( $post ) ) {
                        $thumbnail = get_the_post_thumbnail_url( $post, 'thumbnail' );
                        if ( $thumbnail ) {
                                return $thumbnail;
                        }
                }

                // Fallback to WooCommerce placeholder
                if ( $post->post_type === 'product' && function_exists( 'wc_placeholder_img_src' ) ) {
                        return wc_placeholder_img_src();
                }

                return '';
        }

        /**
         * Get view all results URL
         *
         * @param string $query      Search query
         * @param array  $post_types Post types
         * @return string
         */
        private function get_view_all_url( $query, $post_types ) {
                $args = array( 's' => $query );

                if ( count( $post_types ) === 1 ) {
                        $args['post_type'] = $post_types[0];
                }

                return add_query_arg( $args, \home_url( '/' ) );
        }

        /**
         * Render search modal
         */
        public function render_search_modal() {
                // Prevent duplicate rendering (since we hook to both wp_body_open and wp_footer)
                static $rendered = false;
                if ( $rendered ) {
                        return;
                }

                // Check if search modal is enabled
                if ( ! \MantiLoad\MantiLoad::get_option( 'enable_search_modal', true ) ) {
                        return;
                }

                // Only show search modal for administrators
                if ( ! current_user_can( 'administrator' ) ) {
                        return;
                }

                $rendered = true;

                // Use admin search modal for administrators (same as backend)
                include MANTILOAD_PLUGIN_DIR . 'templates/admin-search-modal.php';
        }

        /**
         * Get search box HTML
         *
         * @param array $args Arguments
         * @return string
         */
        public static function get_search_box( $args = array() ) {
                $defaults = array(
                        'placeholder'   => __( 'Search products...', 'mantiload' ),
                        'post_types'    => array( 'product' ),
                        'show_button'   => true,
                        'button_text'   => __( 'Search', 'mantiload' ),
                        'button_icon'   => false, // Show icon instead of text
                        'hide_clear'    => false, // Hide clear (X) button for cleaner mobile UI
                        'view_all_text' => __( 'View all results', 'mantiload' ),
                        'width'         => '100%',
                        'class'         => '',
                        'show_price'    => true,  // Default to showing price
                        'show_stock'    => true,  // Default to showing stock status
                );

                $args = wp_parse_args( $args, $defaults );

                ob_start();
                include MANTILOAD_PLUGIN_DIR . 'templates/search-box.php';
                return ob_get_clean();
        }

        /**
         * Clear all search caches
         *
         * Called when products are added, updated, or deleted
         * Ensures search results are always fresh and accurate
         */
        public function clear_search_cache() {
                global $wpdb;

                // Delete all search cache transients
                // This is FAST! (~2-5ms) and happens only when products change
                $wpdb->query(
                        "DELETE FROM {$wpdb->options}
                        WHERE option_name LIKE '_transient_mantiload_search_%'
                           OR option_name LIKE '_transient_timeout_mantiload_search_%'"
                );

                // Also clear object cache if enabled
                if ( function_exists( 'wp_cache_flush_group' ) ) {
                        wp_cache_flush_group( 'mantiload_search' );
                }
        }

        /**
         * Clear search cache when important product meta changes
         *
         * @param int    $meta_id    Meta ID
         * @param int    $object_id  Post ID
         * @param string $meta_key   Meta key
         * @param mixed  $meta_value Meta value
         */
        public function clear_search_cache_on_meta_update( $meta_id, $object_id, $meta_key, $meta_value ) {
                // Only clear cache for important meta keys that affect search results
                $important_keys = array(
                        '_price',
                        '_regular_price',
                        '_sale_price',
                        '_stock_status',
                        '_visibility',
                        '_thumbnail_id',
                );

                if ( in_array( $meta_key, $important_keys, true ) ) {
                        // Check if this is a product
                        if ( get_post_type( $object_id ) === 'product' ) {
                                $this->clear_search_cache();
                        }
                }
        }

        /**
         * Clear PHP opcache when WooCommerce permalink settings change
         *
         * This ensures that the static $settings_cache in ultra_fast_search()
         * will be regenerated with the new product base on the next request.
         *
         * @param array $old_value Old permalink settings
         * @param array $new_value New permalink settings
         */
        public function clear_opcache_on_permalink_change( $old_value, $new_value ) {
                // Check if product_base actually changed
                $old_base = isset( $old_value['product_base'] ) ? $old_value['product_base'] : '';
                $new_base = isset( $new_value['product_base'] ) ? $new_value['product_base'] : '';

                if ( $old_base !== $new_base ) {
                        // Clear OPcache to reset static variables
                        if ( function_exists( 'opcache_reset' ) ) {
                                opcache_reset();
                        }

                        // Also clear search cache since URLs will be different
                        $this->clear_search_cache();
                }
        }

        /**
         * Search categories matching the query
         *
         * @param string $query Search query
         * @return array Matching categories
         */
        private function search_categories( $query, $max_categories = 5 ) {
                global $wpdb;

                if ( ! function_exists( 'wc_get_product_category_list' ) ) {
                        return array();
                }

                // OPTIMIZATION: Direct SQL query instead of get_terms() - saves ~1-2ms
                // get_terms() has overhead from sanitization, filters, and object creation
                $like_query = '%' . $wpdb->esc_like( $query ) . '%';
                $max_categories = (int) $max_categories;

                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Performance optimization for search
                $terms = $wpdb->get_results( $wpdb->prepare(
                        "SELECT t.term_id, t.name, t.slug, tt.taxonomy, tt.count
                        FROM {$wpdb->terms} t
                        INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
                        WHERE tt.taxonomy = 'product_cat'
                        AND tt.count > 0
                        AND t.name LIKE %s
                        ORDER BY tt.count DESC
                        LIMIT %d",
                        $like_query,
                        $max_categories
                ) );

                if ( empty( $terms ) ) {
                        return array();
                }

                // Build URLs manually for speed (avoid get_term_link overhead)
                static $term_base = null;
                if ( $term_base === null ) {
                        $permalinks = \get_option( 'woocommerce_permalinks', array() );
                        $term_base = array(
                                'product_cat' => ! empty( $permalinks['category_base'] ) ? $permalinks['category_base'] : 'product-category',
                                'product_tag' => ! empty( $permalinks['tag_base'] ) ? $permalinks['tag_base'] : 'product-tag',
                        );
                }

                $home_url = \home_url( '/' );
                $categories = array();

                foreach ( $terms as $term ) {
                        $base = isset( $term_base[ $term->taxonomy ] ) ? $term_base[ $term->taxonomy ] : 'product-category';
                        $url = $home_url . $base . '/' . $term->slug . '/';

                        $categories[] = array(
                                'id'       => (int) $term->term_id,
                                'name'     => $term->name,
                                'count'    => (int) $term->count,
                                'url'      => $url,
                                'type'     => $term->taxonomy === 'product_cat' ? 'category' : 'tag',
                                'taxonomy' => $term->taxonomy,
                        );
                }

                return $categories;
        }

        /**
         * Intercept WoodMart AJAX search query args
         * Mark the query so we can intercept results later
         *
         * @param array $args WP_Query arguments
         * @return array Modified arguments
         */
        public function intercept_woodmart_search_args( $args ) {
                // Mark this query for MantiLoad interception
                $args['mantiload_woodmart_search'] = true;
                return $args;
        }

        /**
         * Intercept WoodMart search results and use Manticore
         *
         * @param array|null $posts Posts array or null
         * @param \WP_Query $query WP_Query object
         * @return array|null Posts array or null
         */
        public function intercept_woodmart_search_results( $posts, $query ) {
                // Only intercept WoodMart search queries
                if ( ! $query->get( 'mantiload_woodmart_search' ) ) {
                        return $posts;
                }

                // Check if MantiLoad is enabled
                if ( ! \MantiLoad\MantiLoad::get_option( 'enabled', true ) ) {
                        return $posts;
                }

                $search_query = $query->get( 's' );
                if ( empty( $search_query ) ) {
                        return $posts;
                }

                // Normalize Persian/Arabic numerals
                $search_query = \MantiLoad\Manticore_Client::normalize_numerals( $search_query );

                $posts_per_page = $query->get( 'posts_per_page' ) ?: 10;

                // Use Manticore for search
                $results = $this->search_engine->search( $search_query, array(
                        'post_type' => 'product',
                        'limit'     => $posts_per_page,
                        'offset'    => 0,
                ) );

                if ( ! empty( $results['posts'] ) ) {
                        $query->found_posts = $results['total'];
                        $query->max_num_pages = ceil( $results['total'] / $posts_per_page );
                        return $results['posts'];
                }

                // Return empty array if no results
                return array();
        }
}