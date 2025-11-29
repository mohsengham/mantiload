<?php
/**
 * MantiLoad Admin Search
 * THE FIRST EVER instant admin search for WordPress!
 *
 * @package MantiLoad
 */

namespace MantiLoad\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Admin_Search class
 *
 * Provides instant search for WordPress admin
 *
 * phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
 * Direct database queries are necessary for performance-critical search operations.
 * This is a search performance plugin - direct queries are optimized and required.
 */
class Admin_Search {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Stored search term for display purposes
	 *
	 * @var string
	 */
	private $preserved_search_term = '';

	/**
	 * Initialize hooks
	 */
	private function init_hooks() {
		$settings = \get_option( 'mantiload_settings', array() );

		// Admin Search Modal (Cmd/Ctrl+K)
		if ( $settings['enable_admin_search'] ?? true ) {
			// Add search modal to admin footer
			\add_action( 'admin_footer', array( $this, 'render_search_modal' ) );

			// Enqueue admin scripts
			\add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

			// AJAX endpoint
			\add_action( 'wp_ajax_mantiload_admin_search', array( $this, 'ajax_search' ) );
		}

		// Product List Search Optimization
		if ( $settings['enable_admin_product_search_optimization'] ?? true ) {
			// INTERCEPT SLOW PRODUCT LIST SEARCH! Multiple approaches for different contexts
			\add_filter( 'posts_search', array( $this, 'bypass_slow_search' ), 999, 2 );
			\add_filter( 'posts_where', array( $this, 'add_manticore_ids' ), 999, 2 );

			// Intercept WooCommerce product search at the source (MOST EFFECTIVE!)
			\add_filter( 'woocommerce_product_pre_search_products', array( $this, 'pre_search_products' ), 10, 7 );

			// Also intercept WooCommerce-specific product search
			\add_filter( 'woocommerce_product_data_store_cpt_get_products_query', array( $this, 'intercept_wc_search' ), 10, 2 );
		}

		// Order List Search Optimization - INTERCEPT SLOW ORDER SEARCH!
		if ( $settings['index_orders_customers'] ?? false ) {
			\add_filter( 'woocommerce_shop_order_search_fields', array( $this, 'bypass_order_search_fields' ), 10, 1 );
			\add_filter( 'woocommerce_order_table_search_query_meta_keys', array( $this, 'bypass_order_meta_search' ), 10, 1 );
			\add_action( 'pre_get_posts', array( $this, 'intercept_order_search' ), 999 );

			// Restore search term for display in "Search Results for:" message
			\add_filter( 'get_search_query', array( $this, 'restore_search_term_for_display' ), 10, 1 );

			// User List Search Optimization - INTERCEPT SLOW USER SEARCH!
			\add_action( 'pre_user_query', array( $this, 'intercept_user_search' ), 999 );
		}
	}

	/**
	 * Enqueue admin scripts
	 */
	public function enqueue_scripts() {
		// Enqueue CSS
		\wp_enqueue_style(
			'mantiload-admin-search',
			MANTILOAD_PLUGIN_URL . 'assets/css/admin-search.css',
			array(),
			MANTILOAD_VERSION
		);

		// Enqueue JavaScript
		\wp_enqueue_script(
			'mantiload-admin-search',
			MANTILOAD_PLUGIN_URL . 'assets/js/admin-search.js',
			array( 'jquery' ),
			MANTILOAD_VERSION,
			true
		);

		// Localize script
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

	/**
	 * Render search modal
	 */
	public function render_search_modal() {
		include MANTILOAD_PLUGIN_DIR . 'templates/admin-search-modal.php';
	}

	/**
	 * AJAX search handler - ULTRA FAST
	 */
	public function ajax_search() {
		// START TIMER
		$start_time = microtime( true );

		// Verify nonce
		if ( ! \check_ajax_referer( 'mantiload_admin_search', 'nonce', false ) ) {
			\wp_send_json_error( array( 'message' => 'Security check failed' ) );
		}

		// Get search query
		$query = isset( $_GET['q'] ) ? trim( sanitize_text_field( wp_unslash( $_GET['q'] ) ) ) : '';
		$filter = isset( $_GET['filter'] ) ? sanitize_text_field( wp_unslash( $_GET['filter'] ) ) : 'all';

		// Normalize Persian/Arabic numerals to Latin for consistent search
		$query = \MantiLoad\Manticore_Client::normalize_numerals( $query );

		// Validate query length after trimming
		if ( strlen( $query ) < 2 ) {
			\wp_send_json_error( array( 'message' => 'Enter at least 2 characters' ) );
		}

		// Perform search based on filter
		$results = array();
		$total = 0;

		switch ( $filter ) {
			case 'products':
				$search_results = $this->search_products( $query );
				$search_results['totals_by_type'] = array( 'products' => $search_results['total'] );
				break;

			case 'orders':
				$search_results = $this->search_orders( $query );
				$search_results['totals_by_type'] = array( 'orders' => $search_results['total'] );
				break;

			case 'customers':
				$search_results = $this->search_customers( $query );
				$search_results['totals_by_type'] = array( 'customers' => $search_results['total'] );
				break;

			case 'posts':
				$search_results = $this->search_posts( $query );
				$search_results['totals_by_type'] = array( 'posts' => $search_results['total'] );
				break;

			case 'all':
			default:
				// Search ALL types (limited results per type)
				$search_results = $this->search_all( $query );
				break;
		}

		$results = $search_results['results'];
		$total = $search_results['total'];
		$totals_by_type = isset( $search_results['totals_by_type'] ) ? $search_results['totals_by_type'] : array();

		// Calculate total time
		$total_time = ( microtime( true ) - $start_time ) * 1000;

		// Send response
		\wp_send_json_success( array(
			'results'        => $results,
			'total'          => $total,
			'query'          => $query,
			'filter'         => $filter,
			'total_time'     => round( $total_time, 2 ),
			'totals_by_type' => $totals_by_type,
		) );
	}

	/**
	 * Search all types
	 *
	 * @param string $query Search query
	 * @return array
	 */
	private function search_all( $query ) {
		$all_results = array();

		// Search products (top 10)
		$products = $this->search_products( $query, 10 );
		$all_results = array_merge( $all_results, $products['results'] );

		// Search orders (top 5)
		$orders = $this->search_orders( $query, 5 );
		$all_results = array_merge( $all_results, $orders['results'] );

		// Search customers (top 5)
		$customers = $this->search_customers( $query, 5 );
		$all_results = array_merge( $all_results, $customers['results'] );

		// Search posts (top 5)
		$posts = $this->search_posts( $query, 5 );
		$all_results = array_merge( $all_results, $posts['results'] );

		return array(
			'results'    => $all_results,
			'total'      => $products['total'] + $orders['total'] + $customers['total'] + $posts['total'],
			'totals_by_type' => array(
				'products'  => $products['total'],
				'orders'    => $orders['total'],
				'customers' => $customers['total'],
				'posts'     => $posts['total'],
			),
		);
	}

	/**
	 * Search products using MantiCore
	 *
	 * @param string $query Search query
	 * @param int    $limit Result limit
	 * @return array
	 */
	private function search_products( $query, $limit = 10 ) {
		global $wpdb;

		$product_ids = array();
		$total_count = 0;

		// Expand query with synonyms
		$synonyms_manager = new \MantiLoad\Search\Synonyms();
		$expanded_query = $synonyms_manager->expand_query( $query );

		// Normalize expanded query too (synonyms might add Persian/Arabic numerals)
		$expanded_query = \MantiLoad\Manticore_Client::normalize_numerals( $expanded_query );

		// PRIORITY 1: Check for EXACT SKU match first (avoid fuzzy matching issues)
		$exact_sku_products = $wpdb->get_col( $wpdb->prepare(
			"SELECT p.ID FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
			WHERE p.post_type = 'product'
			AND p.post_status = 'publish'
			AND pm.meta_key = '_sku'
			AND pm.meta_value = %s
			LIMIT %d",
			$query,
			$limit
		) );

		if ( ! empty( $exact_sku_products ) ) {
			// Exact SKU match found!
			$product_ids = array_map( 'intval', $exact_sku_products );
			$total_count = count( $product_ids );
		}

		// Try MantiCore search first (with synonyms!) only if no exact SKU match
		if ( empty( $product_ids ) ) {
			// Get connection details from settings
			$host = \MantiLoad\MantiLoad::get_option( 'manticore_host', MANTILOAD_HOST );
			$port = (int) \MantiLoad\MantiLoad::get_option( 'manticore_port', MANTILOAD_PORT );

		// phpcs:ignore WordPress.DB.RestrictedClasses.mysql__mysqli -- Direct mysqli required for Manticore Search connection
		\mysqli_report( MYSQLI_REPORT_OFF );
		// phpcs:ignore WordPress.DB.RestrictedFunctions.mysql_mysqli_init -- Direct mysqli required for Manticore Search connection
		$manticore = \mysqli_init();
		if ( $manticore ) {
			// Disable SSL - Manticore doesn't support it
			$manticore->options( MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, false );
			$manticore->options( MYSQLI_CLIENT_SSL, false );

			// Connect to Manticore
			$manticore->real_connect(
				$host,
				'', // Manticore doesn't use username
				'', // Manticore doesn't use password
				'', // No database selection needed
				$port,
				null,
				MYSQLI_CLIENT_SSL_DONT_VERIFY_SERVER_CERT
			);
		}

		if ( $manticore && ! $manticore->connect_error ) {
			// Get index name from settings (NOT hardcoded!)
			$index_name = \MantiLoad\MantiLoad::get_option( 'index_name', 'mantiload_fast_one' );

			$safe_query = $manticore->real_escape_string( $expanded_query );

			// Get results
			$sql = "SELECT id FROM {$index_name} WHERE MATCH('$safe_query') AND post_type='product' ORDER BY WEIGHT() DESC LIMIT $limit";
			$manticore_result = $manticore->query( $sql );

			if ( $manticore_result ) {
				while ( $row = $manticore_result->fetch_assoc() ) {
					$product_ids[] = (int) $row['id'];
				}
			}

			// Get TOTAL count
			$count_sql = "SELECT COUNT(*) as total FROM {$index_name} WHERE MATCH('$safe_query') AND post_type='product'";
			$count_result = $manticore->query( $count_sql );

			if ( $count_result ) {
				$count_row = $count_result->fetch_assoc();
				$total_count = $count_row ? (int) $count_row['total'] : 0;
			}

			$manticore->close();
		}
		} // End if ( empty( $product_ids ) ) - Manticore search only if no exact SKU match

		// FALLBACK: If MantiCore returns nothing, use optimized WordPress search with synonyms
		if ( empty( $product_ids ) ) {
			// Get all search terms (original + synonyms)
			$search_terms = array( $query );
			$synonyms = $synonyms_manager->get_synonyms( $query );
			if ( ! empty( $synonyms ) ) {
				$search_terms = array_merge( $search_terms, $synonyms );
			}

			// Build UNION query for each search term
			$union_queries = array();
			$prepare_args = array();

			foreach ( $search_terms as $term ) {
				$like = '%' . $wpdb->esc_like( $term ) . '%';

				// Search in title
				$union_queries[] = "(SELECT ID FROM {$wpdb->posts}
				  WHERE post_type = 'product'
				  AND post_status IN ('publish', 'private', 'draft', 'pending')
				  AND post_title LIKE %s
				  LIMIT %d)";
				$prepare_args[] = $like;
				$prepare_args[] = $limit;

				// Search in SKU
				$union_queries[] = "(SELECT post_id as ID FROM {$wpdb->postmeta}
				  WHERE meta_key = '_sku'
				  AND meta_value LIKE %s
				  LIMIT %d)";
				$prepare_args[] = $like;
				$prepare_args[] = $limit;
			}

			$sql = implode( ' UNION ', $union_queries );
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- $sql contains prepared statements with placeholders, using $wpdb->prepare() below
			$product_ids = $wpdb->get_col( $wpdb->prepare( $sql, ...$prepare_args ) );

			$product_ids = array_map( 'intval', array_unique( $product_ids ) );
			$product_ids = array_slice( $product_ids, 0, $limit );
		}

		if ( empty( $product_ids ) ) {
			return array( 'results' => array(), 'total' => 0 );
		}

		// Get product data
		// Note: IDs are already sanitized as integers from Manticore, safe to use directly
		$ids_str = implode( ',', array_map( 'intval', $product_ids ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- IDs are sanitized as integers above
		$products = $wpdb->get_results(
			"SELECT
				p.ID,
				p.post_title,
				MAX(CASE WHEN pm.meta_key = '_price' THEN pm.meta_value END) as price,
				MAX(CASE WHEN pm.meta_key = '_stock_status' THEN pm.meta_value END) as stock_status,
				MAX(CASE WHEN pm.meta_key = '_thumbnail_id' THEN pm.meta_value END) as thumbnail_id,
				MAX(CASE WHEN pm.meta_key = '_sku' THEN pm.meta_value END) as sku
			FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
			WHERE p.ID IN ($ids_str)
			GROUP BY p.ID
			ORDER BY FIELD(p.ID, $ids_str)",
			ARRAY_A
		);

		// Get thumbnails (thumbnail size, not full image)
		$thumb_ids = array_filter( array_column( $products, 'thumbnail_id' ) );
		$thumbnails = array();

		if ( ! empty( $thumb_ids ) ) {
			$thumb_ids_str = implode( ',', array_map( 'intval', $thumb_ids ) );

			// Get thumbnail metadata to build proper thumbnail URLs
			$thumb_meta = $wpdb->get_results(
				"SELECT post_id, meta_value
				FROM {$wpdb->postmeta}
				WHERE post_id IN ($thumb_ids_str)
				AND meta_key = '_wp_attachment_metadata'",
				OBJECT_K
			);

			$upload_dir = wp_upload_dir();
			$base_url = $upload_dir['baseurl'];

			foreach ( $thumb_meta as $thumb_id => $meta ) {
				$metadata = maybe_unserialize( $meta->meta_value );
				if ( ! empty( $metadata['file'] ) ) {
					$file_path = $metadata['file'];

					// Try to get thumbnail size
					if ( ! empty( $metadata['sizes']['thumbnail']['file'] ) ) {
						$dir_path = dirname( $file_path );
						$thumb_file = $metadata['sizes']['thumbnail']['file'];
						$thumbnails[ $thumb_id ] = $base_url . '/' . $dir_path . '/' . $thumb_file;
					} else {
						// Fallback to full image if no thumbnail
						$thumbnails[ $thumb_id ] = $base_url . '/' . $file_path;
					}
				}
			}
		}

		// Format results
		$results = array();
		// Decode HTML entities (WooCommerce returns &#36; for $)
		$currency = html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' );

		foreach ( $products as $product ) {
			$price_raw = ! empty( $product['price'] ) ? floatval( $product['price'] ) : 0;
			$price_html = $price_raw > 0 ? $currency . number_format( $price_raw, 2 ) : '';

			$thumbnail = '';
			if ( ! empty( $product['thumbnail_id'] ) && isset( $thumbnails[ $product['thumbnail_id'] ] ) ) {
				$thumbnail = $thumbnails[ $product['thumbnail_id'] ];
			}

			$title = $this->highlight( $product['post_title'], $query );
			$sku = ! empty( $product['sku'] ) ? $this->highlight( $product['sku'], $query ) : '';

			// Get actual product URL (frontend link)
			$product_url = \get_permalink( $product['ID'] );

			$results[] = array(
				'id'           => $product['ID'],
				'title'        => $title,
				'url'          => \admin_url( 'post.php?post=' . $product['ID'] . '&action=edit' ),
				'product_url'  => $product_url, // Actual frontend link
				'type'         => 'products',
				'thumbnail'    => $thumbnail,
				'price'        => $price_html,
				'sku'          => $sku,
				'stock_status' => $product['stock_status'] ?: 'outofstock',
			);
		}

		return array(
			'results' => $results,
			'total'   => $total_count > 0 ? $total_count : count( $results ),
		);
	}

	/**
	 * Search orders
	 *
	 * @param string $query Search query
	 * @param int    $limit Result limit
	 * @return array
	 */
	private function search_orders( $query, $limit = 10 ) {
		global $wpdb;

		// Check if Manticore indexing is enabled for orders
		$index_orders_customers = \MantiLoad\MantiLoad::get_option( 'index_orders_customers', false );

		if ( $index_orders_customers ) {
			return $this->search_orders_manticore( $query, $limit );
		}

		// Fallback to slow MySQL search
		// Search orders by order number, customer name, email
		$like = '%' . $wpdb->esc_like( $query ) . '%';

		$order_ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT DISTINCT p.ID
			FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
			WHERE p.post_type = 'shop_order'
			  AND p.post_status != 'trash'
			  AND (
				p.ID LIKE %s
				OR pm.meta_key IN ('_billing_first_name', '_billing_last_name', '_billing_email')
				AND pm.meta_value LIKE %s
			  )
			ORDER BY p.ID DESC
			LIMIT %d",
			$like,
			$like,
			$limit
		) );

		if ( empty( $order_ids ) ) {
			return array( 'results' => array(), 'total' => 0 );
		}

		$results = array();

		foreach ( $order_ids as $order_id ) {
			$order = wc_get_order( $order_id );

			if ( ! $order ) {
				continue;
			}

			$results[] = array(
				'id'           => $order_id,
				'title'        => sprintf( 'Order #%s - %s', $order_id, $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
				'url'          => \admin_url( 'post.php?post=' . $order_id . '&action=edit' ),
				'type'         => 'orders',
				'thumbnail'    => '',
				'price'        => $order->get_formatted_order_total(),
				'status'       => $order->get_status(),
				'status_label' => wc_get_order_status_name( $order->get_status() ),
				'meta'         => $order->get_billing_email(),
			);
		}

		return array(
			'results' => $results,
			'total'   => count( $results ),
		);
	}

	/**
	 * Search customers
	 *
	 * @param string $query Search query
	 * @param int    $limit Result limit
	 * @return array
	 */
	private function search_customers( $query, $limit = 10 ) {
		// Check if Manticore indexing is enabled for customers
		$index_orders_customers = \MantiLoad\MantiLoad::get_option( 'index_orders_customers', false );

		if ( $index_orders_customers ) {
			return $this->search_customers_manticore( $query, $limit );
		}

		// Fallback to slow WordPress search
		$users = get_users( array(
			'search'         => '*' . $query . '*',
			'search_columns' => array( 'user_login', 'user_email', 'display_name' ),
			'number'         => $limit,
			'role__in'       => array( 'customer', 'administrator', 'shop_manager' ),
		) );

		$results = array();

		foreach ( $users as $user ) {
			$customer = new \WC_Customer( $user->ID );

			$results[] = array(
				'id'        => $user->ID,
				'title'     => $this->highlight( $user->display_name, $query ),
				'url'       => \admin_url( 'user-edit.php?user_id=' . $user->ID ),
				'type'      => 'customers',
				'thumbnail' => get_avatar_url( $user->ID ),
				'meta'      => $this->highlight( $user->user_email, $query ),
			);
		}

		return array(
			'results' => $results,
			'total'   => count( $results ),
		);
	}

	/**
	 * Search posts/pages
	 *
	 * @param string $query Search query
	 * @param int    $limit Result limit
	 * @return array
	 */
	private function search_posts( $query, $limit = 10 ) {
		$posts = \get_posts( array(
			's'              => $query,
			'post_type'      => array( 'post', 'page' ),
			'posts_per_page' => $limit,
			'post_status'    => 'any',
		) );

		$results = array();

		foreach ( $posts as $post ) {
			$results[] = array(
				'id'        => $post->ID,
				'title'     => $this->highlight( $post->post_title, $query ),
				'url'       => \admin_url( 'post.php?post=' . $post->ID . '&action=edit' ),
				'post_url'  => \get_permalink( $post->ID ), // Frontend link
				'type'      => 'posts',
				'thumbnail' => get_the_post_thumbnail_url( $post, 'thumbnail' ),
				'meta'      => ucfirst( $post->post_type ) . ' - ' . ucfirst( $post->post_status ),
			);
		}

		return array(
			'results' => $results,
			'total'   => count( $results ),
		);
	}

	/**
	 * Highlight search term
	 *
	 * @param string $text  Text to highlight
	 * @param string $query Search query
	 * @return string
	 */
	private function highlight( $text, $query ) {
		$pos = stripos( $text, $query );
		if ( $pos !== false ) {
			$len = strlen( $query );
			$matched = substr( $text, $pos, $len );
			return substr_replace( $text, '<mark>' . $matched . '</mark>', $pos, $len );
		}
		return $text;
	}

	/**
	 * Pre-search products using Manticore - intercepts WooCommerce's search_products() method
	 * This is called BEFORE WooCommerce runs its slow LIKE query
	 *
	 * @param bool|array $custom_results False to use default, array of IDs to override
	 * @param string $term Search term
	 * @param string $type Product type (simple, variable, etc)
	 * @param bool $include_variations Include variations in search
	 * @param bool $all_statuses Search all post statuses
	 * @param int $limit Result limit
	 * @return bool|array Array of product IDs or false to use default
	 */
	public function pre_search_products( $custom_results, $term, $type = '', $include_variations = false, $all_statuses = false, $limit = null ) {
		// Only intercept if we have a search term
		if ( empty( $term ) ) {
			return $custom_results;
		}

		global $wpdb;

		$search_query = \sanitize_text_field( $term );
		$product_ids = array();

		// Expand query with synonyms
		$synonyms_manager = new \MantiLoad\Search\Synonyms();
		$expanded_query = $synonyms_manager->expand_query( $search_query );

		// Normalize Persian/Arabic numerals
		$expanded_query = \MantiLoad\Manticore_Client::normalize_numerals( $expanded_query );

		// PRIORITY 1: Check for EXACT SKU match first (avoid fuzzy matching issues)
		// This is critical for "Add to Order" functionality where users search by exact SKU
		// Search both products AND variations depending on $include_variations parameter
		if ( $include_variations ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- post types are hardcoded strings
			$exact_sku_products = $wpdb->get_col( $wpdb->prepare(
				"SELECT p.ID FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
				WHERE p.post_type IN ('product', 'product_variation')
				AND p.post_status = 'publish'
				AND pm.meta_key = '_sku'
				AND pm.meta_value = %s
				LIMIT 10",
				$search_query
			) );
		} else {
			$exact_sku_products = $wpdb->get_col( $wpdb->prepare(
				"SELECT p.ID FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
				WHERE p.post_type = 'product'
				AND p.post_status = 'publish'
				AND pm.meta_key = '_sku'
				AND pm.meta_value = %s
				LIMIT 10",
				$search_query
			) );
		}

		if ( ! empty( $exact_sku_products ) ) {
			// Exact SKU match found!
			$product_ids = array_map( 'intval', $exact_sku_products );

			// If $include_variations is true and we found variable products, also include their variations
			// This is critical for "Add to Order" which requires variations, not variable products
			if ( $include_variations ) {
				$final_ids = array();
				foreach ( $product_ids as $product_id ) {
					// Check if this is a variable product
					$product = \wc_get_product( $product_id );
					if ( $product && $product->is_type( 'variable' ) ) {
						// Get all variations for this product
						$variations = $product->get_children();
						if ( ! empty( $variations ) ) {
							$final_ids = array_merge( $final_ids, $variations );
						}
					} else {
						// Simple product or variation - add as-is
						$final_ids[] = $product_id;
					}
				}
				$product_ids = array_unique( $final_ids );
			}
		}

		// Try MantiCore for INSTANT search (with synonyms!) only if no exact SKU match
		if ( empty( $product_ids ) ) {
			// Get connection details from settings
			$host = \MantiLoad\MantiLoad::get_option( 'manticore_host', MANTILOAD_HOST );
			$port = (int) \MantiLoad\MantiLoad::get_option( 'manticore_port', MANTILOAD_PORT );

		// phpcs:ignore WordPress.DB.RestrictedClasses.mysql__mysqli -- Direct mysqli required for Manticore Search connection
		\mysqli_report( MYSQLI_REPORT_OFF );
		// phpcs:ignore WordPress.DB.RestrictedFunctions.mysql_mysqli_init -- Direct mysqli required for Manticore Search connection
		$manticore = \mysqli_init();
		if ( $manticore ) {
			// Disable SSL - Manticore doesn't support it
			$manticore->options( MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, false );
			$manticore->options( MYSQLI_CLIENT_SSL, false );

			// Connect to Manticore
			$manticore->real_connect(
				$host,
				'', // Manticore doesn't use username
				'', // Manticore doesn't use password
				'', // No database selection needed
				$port,
				null,
				MYSQLI_CLIENT_SSL_DONT_VERIFY_SERVER_CERT
			);
		}

		if ( $manticore && ! $manticore->connect_error ) {
			// Get index name from settings (NOT hardcoded!)
			$index_name = \MantiLoad\MantiLoad::get_option( 'index_name', 'mantiload_fast_one' );
			$safe_query = $manticore->real_escape_string( $expanded_query );
			// Return enough results for sorting, but not so many that the IN clause becomes slow
			// WordPress needs to post-process these for stock status sorting, so keep it reasonable
			$search_limit = $limit ? intval( $limit ) : 2000;
			// Search both products and product_variations for admin searches
			$sql = "SELECT id FROM {$index_name} WHERE MATCH('$safe_query') AND post_type IN ('product', 'product_variation') ORDER BY WEIGHT() DESC LIMIT $search_limit OPTION max_matches=$search_limit";

			$manticore_result = $manticore->query( $sql );
			$manticore->close();

			if ( $manticore_result ) {
				while ( $row = $manticore_result->fetch_assoc() ) {
					$product_ids[] = (int) $row['id'];
				}
			}
		}
		} // End if ( empty( $product_ids ) ) - Manticore search only if no exact SKU match

		// PRIORITY 2: Also search for variations by EXACT SKU in WordPress database
		// This is important for Add Order page which excludes variable products
		// Variations often inherit SKU from parent, so we need to find parent first then get variations
		// Only do this if we haven't already found products via exact SKU match above
		if ( empty( $product_ids ) && ! empty( $search_query ) ) {
			// First, find variations that have their own SKU
			$variation_ids = $wpdb->get_col( $wpdb->prepare(
				"SELECT p.ID FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
				WHERE p.post_type = 'product_variation'
				AND p.post_status = 'publish'
				AND pm.meta_key = '_sku'
				AND pm.meta_value = %s
				LIMIT 100",
				$search_query
			) );

			// Second, find variations of products that match the SKU
			// (variations inherit parent SKU when they don't have their own)
			$parent_variation_ids = $wpdb->get_col( $wpdb->prepare(
				"SELECT v.ID FROM {$wpdb->posts} v
				INNER JOIN {$wpdb->posts} p ON v.post_parent = p.ID
				INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
				WHERE v.post_type = 'product_variation'
				AND v.post_status = 'publish'
				AND p.post_type = 'product'
				AND pm.meta_key = '_sku'
				AND pm.meta_value = %s
				LIMIT 100",
				$search_query
			) );

			$all_variation_ids = array_merge( $variation_ids, $parent_variation_ids );

			if ( ! empty( $all_variation_ids ) ) {
				$product_ids = array_merge( $product_ids, array_map( 'intval', $all_variation_ids ) );
				$product_ids = array_unique( $product_ids );
			}
		}

		// FALLBACK: If MantiCore returns nothing, use optimized WordPress search
		if ( empty( $product_ids ) ) {
			$like = '%' . $wpdb->esc_like( $search_query ) . '%';

			// OPTIMIZED: Use UNION instead of LEFT JOIN with OR - 10x faster!
			// Include both products and product_variation for admin searches
			$product_ids = $wpdb->get_col( $wpdb->prepare(
				"(SELECT ID FROM {$wpdb->posts}
				  WHERE post_type IN ('product', 'product_variation')
				  AND post_title LIKE %s
				  LIMIT 500)
				UNION
				(SELECT ID FROM {$wpdb->posts}
				  WHERE post_type IN ('product', 'product_variation')
				  AND post_content LIKE %s
				  LIMIT 500)
				UNION
				(SELECT post_id as ID FROM {$wpdb->postmeta}
				  WHERE meta_key = '_sku'
				  AND meta_value LIKE %s
				  LIMIT 500)",
				$like,
				$like,
				$like
			) );

			$product_ids = array_map( 'intval', array_unique( $product_ids ) );

			if ( $limit ) {
				$product_ids = array_slice( $product_ids, 0, intval( $limit ) );
			}
		}

		// Return product IDs (WooCommerce expects an array, not false)
		return $product_ids;
	}

	/**
	 * Bypass slow LIKE search - KILL THE 0.5413s MONSTER!
	 *
	 * This removes the slow LIKE queries from WordPress search
	 *
	 * @param string $search SQL search string
	 * @param WP_Query $query Query object
	 * @return string
	 */
	public function bypass_slow_search( $search, $query ) {
		global $wpdb;

		// Only in admin
		if ( ! is_admin() ) {
			return $search;
		}

		// Only for product searches with search term
		if ( empty( $query->query_vars['s'] ) ) {
			return $search;
		}

		// Check if this is a product query
		$post_type = isset( $query->query_vars['post_type'] ) ? $query->query_vars['post_type'] : '';
		if ( $post_type !== 'product' ) {
			return $search;
		}

		// KILL the slow LIKE search! We'll use MantiCore IDs instead
		return '';
	}

	/**
	 * Add MantiCore IDs to WHERE clause
	 *
	 * @param string $where SQL WHERE clause
	 * @param WP_Query $query Query object
	 * @return string
	 */
	public function add_manticore_ids( $where, $query ) {
		global $wpdb;

		// Only in admin
		if ( ! is_admin() ) {
			return $where;
		}

		// Only for product searches with search term
		if ( empty( $query->query_vars['s'] ) ) {
			return $where;
		}

		// Check if this is a product query
		$post_type = isset( $query->query_vars['post_type'] ) ? $query->query_vars['post_type'] : '';
		if ( $post_type !== 'product' ) {
			return $where;
		}

		$search_query = \sanitize_text_field( $query->query_vars['s'] );
		$product_ids = array();

		// Expand query with synonyms
		$synonyms_manager = new \MantiLoad\Search\Synonyms();
		$expanded_query = $synonyms_manager->expand_query( $search_query );

		// Try MantiCore for INSTANT search (with synonyms!)
		// Get connection details from settings
		$host = \MantiLoad\MantiLoad::get_option( 'manticore_host', MANTILOAD_HOST );
		$port = (int) \MantiLoad\MantiLoad::get_option( 'manticore_port', MANTILOAD_PORT );

		// phpcs:ignore WordPress.DB.RestrictedClasses.mysql__mysqli -- Direct mysqli required for Manticore Search connection
		\mysqli_report( MYSQLI_REPORT_OFF );
		// phpcs:ignore WordPress.DB.RestrictedFunctions.mysql_mysqli_init -- Direct mysqli required for Manticore Search connection
		$manticore = \mysqli_init();
		if ( $manticore ) {
			// Disable SSL - Manticore doesn't support it
			$manticore->options( MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, false );
			$manticore->options( MYSQLI_CLIENT_SSL, false );

			// Connect to Manticore
			$manticore->real_connect(
				$host,
				'', // Manticore doesn't use username
				'', // Manticore doesn't use password
				'', // No database selection needed
				$port,
				null,
				MYSQLI_CLIENT_SSL_DONT_VERIFY_SERVER_CERT
			);
		}

		if ( $manticore && ! $manticore->connect_error ) {
			// Get index name from settings (NOT hardcoded!)
			$index_name = \MantiLoad\MantiLoad::get_option( 'index_name', 'mantiload_fast_one' );
			$safe_query = $manticore->real_escape_string( $expanded_query );
			// Return top 2000 most relevant products (WordPress/WooCommerce handles pagination and sorting)
			// Limiting to 2000 prevents huge IN clauses in stock status queries
			$sql = "SELECT id FROM {$index_name} WHERE MATCH('$safe_query') AND post_type='product' ORDER BY WEIGHT() DESC LIMIT 2000 OPTION max_matches=2000";
			$manticore_result = $manticore->query( $sql );
			$manticore->close();

			if ( $manticore_result ) {
				while ( $row = $manticore_result->fetch_assoc() ) {
					$product_ids[] = (int) $row['id'];
				}
			}
		}

		// FALLBACK: If MantiCore returns nothing, use optimized WordPress search
		// This ensures products not in MantiCore index are still searchable in admin
		if ( empty( $product_ids ) ) {

			$like = '%' . $wpdb->esc_like( $search_query ) . '%';

			// OPTIMIZED: Use UNION instead of LEFT JOIN with OR - 10x faster!
			$product_ids = $wpdb->get_col( $wpdb->prepare(
				"(SELECT ID FROM {$wpdb->posts}
				  WHERE post_type = 'product'
				  AND post_title LIKE %s
				  LIMIT 500)
				UNION
				(SELECT ID FROM {$wpdb->posts}
				  WHERE post_type = 'product'
				  AND post_content LIKE %s
				  LIMIT 500)
				UNION
				(SELECT post_id as ID FROM {$wpdb->postmeta}
				  WHERE meta_key = '_sku'
				  AND meta_value LIKE %s
				  LIMIT 500)",
				$like,
				$like,
				$like
			) );

			$product_ids = array_map( 'intval', array_unique( $product_ids ) );
			$product_ids = array_slice( $product_ids, 0, 1000 );
		}

		if ( empty( $product_ids ) ) {
			// No results found at all
			$where .= " AND {$wpdb->posts}.ID = 0";
		} else {
			// Add matching IDs to WHERE clause
			$ids_str = implode( ',', $product_ids );
			$where .= " AND {$wpdb->posts}.ID IN ($ids_str)";
		}

		return $where;
	}

	/**
	 * Intercept WooCommerce product search queries
	 *
	 * @param array $query Query args
	 * @param array $query_vars Query vars
	 * @return array
	 */
	public function intercept_wc_search( $query, $query_vars ) {
		global $wpdb;

		// Only intercept when there's a search term
		if ( empty( $query_vars['s'] ) || ! is_admin() ) {
			return $query;
		}

		$search_query = \sanitize_text_field( $query_vars['s'] );
		$product_ids = array();

		// Expand query with synonyms
		$synonyms_manager = new \MantiLoad\Search\Synonyms();
		$expanded_query = $synonyms_manager->expand_query( $search_query );

		// Try MantiCore first (with synonyms!)
		// Get connection details from settings
		$host = \MantiLoad\MantiLoad::get_option( 'manticore_host', MANTILOAD_HOST );
		$port = (int) \MantiLoad\MantiLoad::get_option( 'manticore_port', MANTILOAD_PORT );

		// phpcs:ignore WordPress.DB.RestrictedClasses.mysql__mysqli -- Direct mysqli required for Manticore Search connection
		\mysqli_report( MYSQLI_REPORT_OFF );
		// phpcs:ignore WordPress.DB.RestrictedFunctions.mysql_mysqli_init -- Direct mysqli required for Manticore Search connection
		$manticore = \mysqli_init();
		if ( $manticore ) {
			// Disable SSL - Manticore doesn't support it
			$manticore->options( MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, false );
			$manticore->options( MYSQLI_CLIENT_SSL, false );

			// Connect to Manticore
			$manticore->real_connect(
				$host,
				'', // Manticore doesn't use username
				'', // Manticore doesn't use password
				'', // No database selection needed
				$port,
				null,
				MYSQLI_CLIENT_SSL_DONT_VERIFY_SERVER_CERT
			);
		}

		if ( $manticore && ! $manticore->connect_error ) {
			// Get index name from settings (NOT hardcoded!)
			$index_name = \MantiLoad\MantiLoad::get_option( 'index_name', 'mantiload_fast_one' );
			$safe_query = $manticore->real_escape_string( $expanded_query );
			// Return top 2000 most relevant products (WordPress/WooCommerce handles pagination and sorting)
			// Limiting to 2000 prevents huge IN clauses in stock status queries
			$sql = "SELECT id FROM {$index_name} WHERE MATCH('$safe_query') AND post_type='product' ORDER BY WEIGHT() DESC LIMIT 2000 OPTION max_matches=2000";
			$manticore_result = $manticore->query( $sql );
			$manticore->close();

			if ( $manticore_result ) {
				while ( $row = $manticore_result->fetch_assoc() ) {
					$product_ids[] = (int) $row['id'];
				}
			}
		}

		// FALLBACK: If MantiCore returns nothing, use optimized WordPress search
		if ( empty( $product_ids ) ) {
			$like = '%' . $wpdb->esc_like( $search_query ) . '%';

			// OPTIMIZED: Use UNION instead of LEFT JOIN with OR - 10x faster!
			$product_ids = $wpdb->get_col( $wpdb->prepare(
				"(SELECT ID FROM {$wpdb->posts}
				  WHERE post_type = 'product'
				  AND post_title LIKE %s
				  LIMIT 500)
				UNION
				(SELECT ID FROM {$wpdb->posts}
				  WHERE post_type = 'product'
				  AND post_content LIKE %s
				  LIMIT 500)
				UNION
				(SELECT post_id as ID FROM {$wpdb->postmeta}
				  WHERE meta_key = '_sku'
				  AND meta_value LIKE %s
				  LIMIT 500)",
				$like,
				$like,
				$like
			) );

			$product_ids = array_map( 'intval', array_unique( $product_ids ) );
			$product_ids = array_slice( $product_ids, 0, 1000 );
		}

		if ( ! empty( $product_ids ) ) {
			// Override the search - force these IDs
			$query['post__in'] = $product_ids;
			// Preserve orderby from relevance
			$query['orderby'] = 'post__in';
		} else {
			// No results - force empty result
			$query['post__in'] = array( 0 );
		}

		return $query;
	}

	/**
	 * Search orders using Manticore (FAST!)
	 */
	private function search_orders_manticore( $query, $limit = 10 ) {
		$indexer = \MantiLoad\MantiLoad::instance()->indexer;
		$client = $indexer->get_client();
		$index_name = \MantiLoad\MantiLoad::get_option( 'index_name', 'mantiload_fast_one' );

		if ( ! $client ) {
			return array( 'results' => array(), 'total' => 0 );
		}

		// Escape query for Manticore and add wildcard for prefix matching
		$safe_query = $client->escape( $query );
		// Add wildcard (*) to match partial words (e.g., "john" matches "johnson")
		$wildcard_query = $safe_query . '*';

		try {
			// Search orders with wildcard support
			$sql = "SELECT id FROM {$index_name} WHERE MATCH('$wildcard_query') AND post_type='shop_order' ORDER BY WEIGHT() DESC, post_date DESC LIMIT $limit";
			$result = $client->query( $sql );

			$order_ids = array();
			while ( $row = $result->fetch_assoc() ) {
				$order_ids[] = $row['id'];
			}

			if ( empty( $order_ids ) ) {
				return array( 'results' => array(), 'total' => 0 );
			}

			$results = array();
			foreach ( $order_ids as $order_id ) {
				$order = wc_get_order( $order_id );

				if ( ! $order ) {
					continue;
				}

				$results[] = array(
					'id'           => $order_id,
					'title'        => sprintf( 'Order #%s - %s', $order_id, $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
					'url'          => \admin_url( 'post.php?post=' . $order_id . '&action=edit' ),
					'type'         => 'orders',
					'thumbnail'    => '',
					'price'        => $order->get_formatted_order_total(),
					'status'       => $order->get_status(),
					'status_label' => wc_get_order_status_name( $order->get_status() ),
					'meta'         => $order->get_billing_email(),
				);
			}

			return array(
				'results' => $results,
				'total'   => count( $results ),
			);
		} catch ( \Exception $e ) {
			return array( 'results' => array(), 'total' => 0 );
		}
	}

	/**
	 * Search customers using Manticore (FAST!)
	 *
	 * Note: Customers aren't indexed separately. We search through orders and extract unique customers.
	 */
	private function search_customers_manticore( $query, $limit = 10 ) {
		global $wpdb;

		$indexer = \MantiLoad\MantiLoad::instance()->indexer;
		$client = $indexer->get_client();
		$index_name = \MantiLoad\MantiLoad::get_option( 'index_name', 'mantiload_fast_one' );

		if ( ! $client ) {
			return array( 'results' => array(), 'total' => 0 );
		}

		// Escape query for Manticore and add wildcard for prefix matching
		$safe_query = $client->escape( $query );
		// Add wildcard (*) to match partial words (e.g., "word" matches "wordup")
		$wildcard_query = $safe_query . '*';

		try {
			// Search orders with customer info - customers are indexed within orders
			$sql = "SELECT id FROM {$index_name} WHERE MATCH('$wildcard_query') AND post_type='shop_order' ORDER BY WEIGHT() DESC LIMIT 100";
			$result = $client->query( $sql );

			$order_ids = array();
			while ( $row = $result->fetch_assoc() ) {
				$order_ids[] = $row['id'];
			}

			if ( empty( $order_ids ) ) {
				return array( 'results' => array(), 'total' => 0 );
			}

			// Get unique customer user IDs from these orders
			$ids_str = implode( ',', array_map( 'intval', $order_ids ) );
			$customer_ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT DISTINCT meta_value
					FROM {$wpdb->postmeta}
					WHERE post_id IN ($ids_str)
					AND meta_key = '_customer_user'
					AND meta_value > 0
					LIMIT %d",
					$limit
				)
			);

			if ( empty( $customer_ids ) ) {
				return array( 'results' => array(), 'total' => 0 );
			}

			$results = array();
			foreach ( $customer_ids as $user_id ) {
				$user = get_userdata( $user_id );

				if ( ! $user ) {
					continue;
				}

				$results[] = array(
					'id'        => $user_id,
					'title'     => $this->highlight( $user->display_name, $query ),
					'url'       => \admin_url( 'user-edit.php?user_id=' . $user_id ),
					'type'      => 'customers',
					'thumbnail' => get_avatar_url( $user_id ),
					'meta'      => $this->highlight( $user->user_email, $query ),
				);
			}

			return array(
				'results' => $results,
				'total'   => count( $results ),
			);
		} catch ( \Exception $e ) {
			return array( 'results' => array(), 'total' => 0 );
		}
	}

	/**
	 * Bypass order search fields to prevent slow queries
	 */
	public function bypass_order_search_fields( $search_fields ) {
		// Return empty array to disable default search fields
		// We'll handle search via Manticore in intercept_order_search
		return array();
	}

	/**
	 * Bypass order meta search to prevent slow postmeta queries
	 */
	public function bypass_order_meta_search( $meta_keys ) {
		// Return empty array to disable meta search
		// We'll handle search via Manticore in intercept_order_search
		return array();
	}

	/**
	 * Intercept order search and use Manticore instead of slow MySQL queries
	 */
	public function intercept_order_search( $query ) {
		// Only intercept on order list page with search
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || $screen->id !== 'edit-shop_order' ) {
			return;
		}

		$search_term = $query->get( 's' );
		if ( empty( $search_term ) ) {
			return;
		}

		// Preserve search term for display in "Search Results for:" message
		$this->preserved_search_term = $search_term;

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		}

		// Search orders in Manticore - ULTRA FAST!
		$order_ids = $this->search_orders_get_ids( $search_term );

		if ( ! empty( $order_ids ) ) {
			// Replace query with our order IDs
			$query->set( 'post__in', $order_ids );
			$query->set( 's', '' ); // Clear search to prevent slow MySQL queries

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			}
		} else {
			// No results - set impossible condition
			$query->set( 'post__in', array( 0 ) );
			$query->set( 's', '' );

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			}
		}
	}

	/**
	 * Restore search term for display purposes
	 *
	 * Fixes the "Search Results for:" message that shows empty when we clear
	 * the search term to prevent slow MySQL queries
	 *
	 * @param string $search_query Current search query
	 * @return string Restored search term if available
	 */
	public function restore_search_term_for_display( $search_query ) {
		// Only restore on order list page
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && $screen->id === 'edit-shop_order' && ! empty( $this->preserved_search_term ) ) {
			return $this->preserved_search_term;
		}

		return $search_query;
	}

	/**
	 * Search orders in Manticore and return order IDs
	 */
	private function search_orders_get_ids( $search_term, $limit = 10000 ) {
		$index_name = \MantiLoad\MantiLoad::get_option( 'index_name', 'mantiload_fast_one' );
		$client = new \MantiLoad\Manticore_Client();

		try {
			// Escape search term for Manticore full-text search
			$search_term = $client->escape( $search_term );

			// Add wildcard for prefix matching (like AJAX search)
			$wildcard_query = $search_term . '*';

			// Build search query - search in all order text fields including shipping method
			$sql = "SELECT id FROM {$index_name}
					WHERE post_type='shop_order'
					AND MATCH('@(customer_name,customer_email,customer_phone,billing_company,shipping_address,shipping_method,order_items,order_number) {$wildcard_query}')
					ORDER BY WEIGHT() DESC
					LIMIT {$limit} OPTION max_matches={$limit}";

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			}

			$result = $client->query( $sql );

			$order_ids = array();
			if ( $result ) {
				while ( $row = $result->fetch_assoc() ) {
					$order_ids[] = (int) $row['id'];
				}
			}

			return $order_ids;
		} catch ( \Exception $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			}
			return array();
		}
	}

	/**
	 * Intercept user search on users.php page and use Manticore instead of slow LIKE queries
	 *
	 * @param WP_User_Query $query User query object
	 */
	public function intercept_user_search( $query ) {
		// Only intercept on admin user list page with search
		if ( ! is_admin() ) {
			return;
		}

		// Check if this is a search query
		$search_term = $query->query_vars['search'] ?? '';
		if ( empty( $search_term ) ) {
			return;
		}

		// Remove the leading/trailing wildcards that WordPress adds
		$search_term = trim( $search_term, '*' );
		if ( empty( $search_term ) ) {
			return;
		}

		global $wpdb;

		// Search for users in Manticore via orders
		$index_name = \MantiLoad\MantiLoad::get_option( 'index_name', 'mantiload_fast_one' );
		$client = new \MantiLoad\Manticore_Client();

		try {
			// Escape search term for Manticore
			$safe_search = $client->escape( $search_term );

			// Search orders with customer info
			$sql = "SELECT id FROM {$index_name}
					WHERE post_type='shop_order'
					AND MATCH('@(customer_name,customer_email,customer_phone) {$safe_search}*')
					LIMIT 200";

			$result = $client->query( $sql );

			$order_ids = array();
			if ( $result ) {
				while ( $row = $result->fetch_assoc() ) {
					$order_ids[] = (int) $row['id'];
				}
			}

			if ( ! empty( $order_ids ) ) {
				// Get unique customer user IDs from these orders
				$ids_str = implode( ',', array_map( 'intval', $order_ids ) );
				$user_ids = $wpdb->get_col(
					"SELECT DISTINCT meta_value
					FROM {$wpdb->postmeta}
					WHERE post_id IN ($ids_str)
					AND meta_key = '_customer_user'
					AND meta_value > 0"
				);

				if ( ! empty( $user_ids ) ) {
					// Override the search query to use our user IDs
					$query->query_vars['include'] = $user_ids;
					$query->query_vars['search'] = ''; // Clear search to prevent slow LIKE queries
				} else {
					// No users found - return empty result
					$query->query_vars['include'] = array( 0 );
					$query->query_vars['search'] = '';
				}
			} else {
				// No orders found - return empty result
				$query->query_vars['include'] = array( 0 );
				$query->query_vars['search'] = '';
			}
		} catch ( \Exception $e ) {
			// On error, let WordPress handle the search normally
		}
	}
}
