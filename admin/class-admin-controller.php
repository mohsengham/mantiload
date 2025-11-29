<?php
namespace MantiLoad\Admin;
defined( 'ABSPATH' ) || exit;

class Admin_Controller {
	public function __construct() {
		// AJAX endpoints MUST be registered always (even on frontend for admin-ajax.php)
		$this->register_ajax_actions();

		// Admin-only hooks
		if ( is_admin() ) {
			\add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
			\add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
			\add_action( 'admin_init', array( $this, 'handle_actions' ) );
			\add_action( 'admin_init', array( $this, 'redirect_after_activation' ) );
			\add_action( 'admin_init', array( $this, 'handle_csv_export' ) );
		}
	}

	/**
	 * Register AJAX actions
	 */
	private function register_ajax_actions() {
		\add_action( 'wp_ajax_mantiload_reindex_batch', array( $this, 'ajax_reindex_batch' ) );
		\add_action( 'wp_ajax_mantiload_get_total_posts', array( $this, 'ajax_get_total_posts' ) );
		\add_action( 'wp_ajax_mantiload_install_index', array( $this, 'ajax_install_index' ) );
		\add_action( 'wp_ajax_mantiload_install_all_indexes', array( $this, 'ajax_install_all_indexes' ) );
		\add_action( 'wp_ajax_mantiload_remove_index', array( $this, 'ajax_remove_index' ) );
		\add_action( 'wp_ajax_mantiload_remove_all_indexes', array( $this, 'ajax_remove_all_indexes' ) );
	}
	
	public function add_admin_menu() {
		add_menu_page(
			\__( 'MantiLoad', 'mantiload' ),
			\__( 'MantiLoad', 'mantiload' ),
			'manage_options',
			'mantiload',
			array( $this, 'dashboard_page' ),
			'dashicons-search',
			56
		);

		add_submenu_page( 'mantiload', \__( 'Dashboard', 'mantiload' ), \__( 'Dashboard', 'mantiload' ), 'manage_options', 'mantiload', array( $this, 'dashboard_page' ) );
		add_submenu_page( 'mantiload', \__( 'Indexing', 'mantiload' ), \__( 'Indexing', 'mantiload' ), 'manage_options', 'mantiload-indexing', array( $this, 'indexing_page' ) );
		add_submenu_page( 'mantiload', \__( 'DB Indexes', 'mantiload' ), \__( 'DB Indexes', 'mantiload' ), 'manage_options', 'mantiload-database-indexes', array( $this, 'database_indexes_page' ) );
		add_submenu_page( 'mantiload', \__( 'Synonyms', 'mantiload' ), \__( 'Synonyms', 'mantiload' ), 'manage_options', 'mantiload-synonyms', array( $this, 'synonyms_page' ) );
		add_submenu_page( 'mantiload', \__( 'Settings', 'mantiload' ), \__( 'Settings', 'mantiload' ), 'manage_options', 'mantiload-settings', array( $this, 'settings_page' ) );
		add_submenu_page( 'mantiload', \__( 'Analytics', 'mantiload' ), \__( 'Analytics', 'mantiload' ), 'manage_options', 'mantiload-analytics-enhanced', array( $this, 'analytics_enhanced_page' ) );
	}
	
	public function enqueue_scripts( $hook ) {
		if ( strpos( $hook, 'mantiload' ) === false ) {
			return;
		}

		// Minimal admin UI CSS - Clean, black/white/gray design
		\wp_enqueue_style( 'mantiload-admin-minimal', MANTILOAD_PLUGIN_URL . 'assets/css/admin-minimal.css', array(), MANTILOAD_VERSION . '-' . time() );

		// WordPress Dashicons (built-in icon font)
		\wp_enqueue_style( 'dashicons' );

		// Select2 for searchable multi-select dropdowns (bundled locally for WordPress.org compliance)
		\wp_enqueue_style( 'select2', MANTILOAD_PLUGIN_URL . 'assets/vendor/select2.min.css', array(), '4.1.0' );
		\wp_enqueue_script( 'select2', MANTILOAD_PLUGIN_URL . 'assets/vendor/select2.min.js', array( 'jquery' ), '4.1.0', true );

		// Admin JS (Note: Lucide icons removed for WordPress.org compliance - using Dashicons instead)
		\wp_enqueue_script( 'mantiload-admin', MANTILOAD_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery', 'select2' ), MANTILOAD_VERSION, true );

		\wp_localize_script( 'mantiload-admin', 'mantiloadAdmin', array(
			'ajaxurl' => \admin_url( 'admin-ajax.php' ),
			'nonce' => \wp_create_nonce( 'mantiload-admin' ),
			'i18n' => array(
				'confirmAbort' => \esc_html__( 'Are you sure you want to abort indexing?\n\nProgress will be saved, but remaining posts will not be indexed.', 'mantiload' ),
				'aborting' => \esc_html__( 'Aborting...', 'mantiload' ),
				'confirmReindex' => \esc_html__( 'Are you sure you want to reindex all products?\n\nThis will reindex all products with full categories, attributes, and filter data.', 'mantiload' ),
				'confirmClearCache' => \esc_html__( 'Are you sure you want to clear all cached search results?', 'mantiload' ),
				'error' => \esc_html__( 'Error', 'mantiload' ),
				'unknownError' => \esc_html__( 'Unknown error', 'mantiload' ),
				'ajaxError' => \esc_html__( 'AJAX Error', 'mantiload' ),
				'networkError' => \esc_html__( 'Network error', 'mantiload' ),
				'indexOptimized' => \esc_html__( 'Index optimized successfully!', 'mantiload' ),
				'optimizationFailed' => \esc_html__( 'Optimization failed', 'mantiload' ),
				'errorLoadingNotices' => \esc_html__( 'Error loading hidden notices', 'mantiload' ),
			),
		) );
	}
	
	public function redirect_after_activation() {
		if ( \get_transient( 'mantiload_activation_redirect' ) ) {
			\delete_transient( 'mantiload_activation_redirect' );
			wp_safe_redirect( \admin_url( 'admin.php?page=mantiload' ) );
			exit;
		}
	}
	
	public function handle_actions() {
		if ( ! isset( $_POST['mantiload_action'] ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		
		check_admin_referer( 'mantiload-action' );
		
		$action = sanitize_text_field( wp_unslash( $_POST['mantiload_action'] ) );
		
		switch ( $action ) {
			case 'create_indexes':
				$indexer = \MantiLoad\MantiLoad::instance()->indexer;
				$results = $indexer->create_indexes();

				// Check if any indexes failed and show specific errors
				$failed = array();
				$success = array();
				foreach ( $results as $post_type => $result ) {
					if ( $result['success'] ) {
						$success[] = $post_type;
					} else {
						$failed[] = $post_type . ': ' . $result['error'];
					}
				}

				if ( ! empty( $failed ) ) {
					add_settings_error( 'mantiload', 'indexes_failed', \__( 'Failed to create indexes: ', 'mantiload' ) . implode( '; ', $failed ), 'error' );
				}
				if ( ! empty( $success ) ) {
					add_settings_error( 'mantiload', 'indexes_created', \__( 'Indexes created successfully for: ', 'mantiload' ) . implode( ', ', $success ), 'success' );
				}
				break;

			case 'reindex_all':
				// Mark reindex as started
				\set_transient( 'mantiload_reindex_status', 'in_progress', 600 );
				\set_transient( 'mantiload_reindex_started_time', time(), 600 );

				// Schedule background reindex using WordPress cron
				// This is safer than shell exec and works on all hosting environments
				if ( ! wp_next_scheduled( 'mantiload_background_reindex' ) ) {
					wp_schedule_single_event( time(), 'mantiload_background_reindex' );
				}

				// Also try to run immediately if possible (for better UX)
				$indexer = \MantiLoad\MantiLoad::instance()->indexer;
				if ( $indexer ) {
					// Run reindex directly (works for smaller sites)
					// For larger sites, the scheduled event will handle it
					$results = $indexer->reindex_all();
					\update_option( 'mantiload_last_reindex_time', time() );

					add_settings_error( 'mantiload', 'reindex_complete',
						sprintf(
							/* translators: 1: number of items indexed, 2: time in seconds */
							\__( 'Reindex completed! Indexed %1$d items in %2$.2f seconds.', 'mantiload' ),
							$results['indexed'],
							$results['time']
						),
						'success'
					);

					// Clear the transients since we completed immediately
					\delete_transient( 'mantiload_reindex_status' );
					\delete_transient( 'mantiload_reindex_started_time' );
				} else {
					add_settings_error( 'mantiload', 'reindex_error', \__( 'Indexer not available. Please check Manticore connection.', 'mantiload' ), 'error' );
				}
				break;

			case 'truncate_indexes':
				$indexer = \MantiLoad\MantiLoad::instance()->indexer;
				$results = $indexer->truncate_indexes();
				$total_cleared = 0;
				foreach ( $results as $post_type => $success ) {
					if ( $success ) {
						$total_cleared++;
					}
				}
				/* translators: %d: number of indexes cleared */
				add_settings_error( 'mantiload', 'truncate_complete', sprintf( \__( 'Cleared %d indexes! All indexed data removed.', 'mantiload' ), $total_cleared ), 'success' );
				break;

			case 'optimize_indexes':
				$indexer = \MantiLoad\MantiLoad::instance()->indexer;
				$indexer->optimize_indexes();
				add_settings_error( 'mantiload', 'optimize_complete', \__( 'Indexes optimized!', 'mantiload' ), 'success' );
				break;

			case 'clear_logs':
				\update_option( 'mantiload_search_logs', array() );
				add_settings_error( 'mantiload', 'logs_cleared', \__( 'Search logs cleared!', 'mantiload' ), 'success' );
				break;

			case 'clear_search_cache':
				// Clear all search result caches
				$ajax_search = new \MantiLoad\Search\AJAX_Search();
				$ajax_search->clear_search_cache();
				add_settings_error( 'mantiload', 'cache_cleared', \__( 'Search cache cleared successfully! Next searches will rebuild cache.', 'mantiload' ), 'success' );
				break;
		}
	}

	public function dashboard_page() {
		// Check if reindex just completed
		$last_reindex_time = \get_option( 'mantiload_last_reindex_time', 0 );
		$reindex_started_time = \get_transient( 'mantiload_reindex_started_time' );

		// If reindex completed within last 15 seconds, show success message
		if ( $last_reindex_time && $reindex_started_time && ( $last_reindex_time >= $reindex_started_time ) && ( time() - $last_reindex_time < 15 ) ) {
			// Get stats to show in message
			$indexer = \MantiLoad\MantiLoad::instance()->indexer;
			$client = $indexer->get_client();
			$index_name = \MantiLoad\MantiLoad::get_option( 'index_name', 'mantiload_fast_one' );

			try {
				$count = $client->query( "SELECT COUNT(*) as total FROM $index_name" )->fetch_assoc()['total'] ?? 0;
				$duration = $last_reindex_time - $reindex_started_time;
				$speed = $duration > 0 ? round( $count / $duration ) : 0;

				add_settings_error( 'mantiload', 'reindex_complete',
					sprintf( '✅ Reindex completed successfully! Indexed %s products in %s seconds (%s posts/sec)',
						number_format( $count ),
						number_format( $duration, 2 ),
						number_format( $speed )
					), 'success' );
			} catch ( \Exception $e ) {
				add_settings_error( 'mantiload', 'reindex_complete', '✅ Reindex completed successfully!', 'success' );
			}

			// Clear the transients
			\delete_transient( 'mantiload_reindex_status' );
			\delete_transient( 'mantiload_reindex_started_time' );
		}

		// Get stats safely with null check
		$stats = array();
		$mantiload_instance = \MantiLoad\MantiLoad::instance();
		if ( $mantiload_instance && $mantiload_instance->indexer ) {
			$stats = $mantiload_instance->indexer->get_stats();
		}

		include MANTILOAD_PLUGIN_DIR . 'admin/views/dashboard.php';
	}

	public function indexing_page() {
		// Load new simple JS
		\wp_enqueue_script( 'mantiload-indexing', MANTILOAD_PLUGIN_URL . 'assets/js/admin-new.js', array( 'jquery' ), time(), true );

		// Localize script with AJAX URL and nonce
		\wp_localize_script( 'mantiload-indexing', 'mantiloadAjax', array(
			'ajaxurl' => \admin_url( 'admin-ajax.php' ),
			'nonce' => \wp_create_nonce( 'mantiload-admin' ),
		) );

		// Get stats safely with null check
		$stats = array();
		$mantiload_instance = \MantiLoad\MantiLoad::instance();
		if ( $mantiload_instance && $mantiload_instance->indexer ) {
			$stats = $mantiload_instance->indexer->get_stats();
		}

		include MANTILOAD_PLUGIN_DIR . 'admin/views/indexing-new.php';
	}
	
	public function settings_page() {
		// Check if rebuild just completed
		$last_rebuild_time = \get_option( 'mantiload_last_rebuild_time', 0 );
		$rebuild_started_time = \get_transient( 'mantiload_rebuild_started_time' );

		// If rebuild completed within last 15 seconds, show success message
		if ( $last_rebuild_time && $rebuild_started_time && ( $last_rebuild_time >= $rebuild_started_time ) && ( time() - $last_rebuild_time < 15 ) ) {
			// Get stats to show in message
			$indexer = \MantiLoad\MantiLoad::instance()->indexer;
			$client = $indexer->get_client();
			$index_name = \MantiLoad\MantiLoad::get_option( 'index_name', 'mantiload_fast_one' );

			try {
				$count = $client->query( "SELECT COUNT(*) as total FROM $index_name" )->fetch_assoc()['total'] ?? 0;
				$duration = $last_rebuild_time - $rebuild_started_time;
				$speed = $duration > 0 ? round( $count / $duration ) : 0;

				add_settings_error( 'mantiload', 'rebuild_complete',
					sprintf( '✅ Index rebuild completed successfully! Indexed %s products in %s seconds (%s posts/sec)',
						number_format( $count ),
						number_format( $duration, 2 ),
						number_format( $speed )
					), 'success' );
			} catch ( \Exception $e ) {
				add_settings_error( 'mantiload', 'rebuild_complete', '✅ Index rebuild completed successfully!', 'success' );
			}

			// Clear the transients
			\delete_transient( 'mantiload_rebuild_status' );
			\delete_transient( 'mantiload_rebuild_started_time' );
		}

		if ( isset( $_POST['mantiload_settings_submit'] ) ) {
			check_admin_referer( 'mantiload-settings' );

			// Get existing settings - DON'T OVERWRITE EVERYTHING!
			$settings = \get_option( 'mantiload_settings', array() );

			// MantiCore connection settings
			$settings['manticore_host'] = sanitize_text_field( wp_unslash( $_POST['manticore_host'] ?? '127.0.0.1' ) );
			$settings['manticore_port'] = absint( wp_unslash( $_POST['manticore_port'] ?? 9306 ) );
			$settings['index_name'] = sanitize_text_field( wp_unslash( $_POST['index_name'] ?? \MantiLoad\MantiLoad::get_default_index_name() ) );

			// Update only submitted fields
			$settings['enabled'] = isset( $_POST['enabled'] );
			$settings['post_types'] = array_map( 'sanitize_text_field', wp_unslash( $_POST['post_types'] ?? array() ) );
			$settings['instant_search'] = isset( $_POST['instant_search'] );
			$settings['log_searches'] = isset( $_POST['log_searches'] );
			$settings['results_per_page'] = absint( wp_unslash( $_POST['results_per_page'] ?? 20 ) );
			$settings['index_batch_size'] = absint( wp_unslash( $_POST['index_batch_size'] ?? 100 ) );
			$settings['index_product_content'] = isset( $_POST['index_product_content'] );

			// Search field weights
			$settings['weight_title'] = absint( wp_unslash( $_POST['weight_title'] ?? 10 ) );
			$settings['weight_content'] = absint( wp_unslash( $_POST['weight_content'] ?? 5 ) );
			$settings['weight_sku'] = absint( wp_unslash( $_POST['weight_sku'] ?? 15 ) );
			$settings['weight_categories'] = absint( wp_unslash( $_POST['weight_categories'] ?? 3 ) );
			$settings['weight_tags'] = absint( wp_unslash( $_POST['weight_tags'] ?? 2 ) );
			$settings['weight_attributes'] = absint( wp_unslash( $_POST['weight_attributes'] ?? 4 ) );

			// Excluded attributes (array from multi-select, convert to comma-separated string)
			$excluded_array = isset( $_POST['excluded_attributes'] ) && is_array( $_POST['excluded_attributes'] )
				? array_map( 'sanitize_text_field', wp_unslash( $_POST['excluded_attributes'] ) )
				: array();
			$settings['excluded_attributes'] = implode( ', ', $excluded_array );

			// AJAX search options
			$settings['prioritize_in_stock'] = isset( $_POST['prioritize_in_stock'] );
			$settings['search_delay'] = absint( wp_unslash( $_POST['search_delay'] ?? 300 ) );
			$settings['min_chars'] = absint( wp_unslash( $_POST['min_chars'] ?? 2 ) );
			$settings['max_results'] = absint( wp_unslash( $_POST['max_results'] ?? 10 ) );
			$settings['show_categories_in_search'] = isset( $_POST['show_categories_in_search'] );
			$settings['max_categories'] = absint( wp_unslash( $_POST['max_categories'] ?? 5 ) );

			// Filter options
			$settings['filter_display_method'] = sanitize_text_field( wp_unslash( $_POST['filter_display_method'] ?? 'parameter' ) );
			$settings['filter_update_url'] = isset( $_POST['filter_update_url'] );

			// Admin optimization options
			$settings['enable_admin_search'] = isset( $_POST['enable_admin_search'] );
			$settings['index_orders_customers'] = isset( $_POST['index_orders_customers'] );
			$settings['enable_admin_product_search_optimization'] = isset( $_POST['enable_admin_product_search_optimization'] );
			$settings['enable_archive_optimization'] = isset( $_POST['enable_archive_optimization'] );

			// Related Products options
			$settings['enable_related_products'] = isset( $_POST['enable_related_products'] );
			$settings['related_products_algorithm'] = sanitize_text_field( wp_unslash( $_POST['related_products_algorithm'] ?? 'combo' ) );
			$settings['related_products_limit'] = absint( wp_unslash( $_POST['related_products_limit'] ?? 10 ) );

			// Custom CSS
			$settings['custom_css'] = isset( $_POST['custom_css'] ) ? wp_strip_all_tags( wp_unslash( $_POST['custom_css'] ) ) : '';

			// Performance & Caching options
			$settings['enable_redis_cache'] = isset( $_POST['enable_redis_cache'] );
			$settings['enable_query_interception'] = isset( $_POST['enable_query_interception'] );
			$settings['enable_woocommerce_filter_integration'] = isset( $_POST['enable_woocommerce_filter_integration'] );

			// Turbo Mode options
			$settings['enable_turbo_mode'] = isset( $_POST['enable_turbo_mode'] );
			$settings['turbo_action_groups'] = isset( $_POST['turbo_action_groups'] ) && is_array( $_POST['turbo_action_groups'] )
				? array_map( 'sanitize_text_field', wp_unslash( $_POST['turbo_action_groups'] ) )
				: array( 'frontend_search' ); // Default to frontend search only
			$settings['turbo_keep_plugins'] = isset( $_POST['turbo_keep_plugins'] ) && is_array( $_POST['turbo_keep_plugins'] )
				? array_map( 'sanitize_text_field', wp_unslash( $_POST['turbo_keep_plugins'] ) )
				: array();

			\update_option( 'mantiload_settings', $settings );
			add_settings_error( 'mantiload', 'settings_saved', 'Settings saved!', 'success' );
		}

		$settings = \get_option( 'mantiload_settings', array() );
		$post_types = get_post_types( array( 'public' => true ), 'objects' );
		include MANTILOAD_PLUGIN_DIR . 'admin/views/settings.php';
	}

	public function synonyms_page() {
		// Handle synonym actions
		if ( isset( $_POST['action'] ) && current_user_can( 'manage_options' ) ) {
			check_admin_referer( 'mantiload-synonyms' );

			$synonyms_manager = new \MantiLoad\Search\Synonyms();

			if ( $_POST['action'] === 'add_synonym' && isset( $_POST['term'] ) && isset( $_POST['synonyms'] ) ) {
				$term = sanitize_text_field( wp_unslash( $_POST['term'] ) );
				$synonyms = sanitize_textarea_field( wp_unslash( $_POST['synonyms'] ) );

				if ( $synonyms_manager->save( $term, $synonyms ) ) {
					add_settings_error( 'mantiload_synonyms', 'synonym_added', 'Synonym added successfully!', 'success' );
				} else {
					add_settings_error( 'mantiload_synonyms', 'synonym_error', 'Error adding synonym.', 'error' );
				}
			} elseif ( $_POST['action'] === 'delete_synonym' && isset( $_POST['synonym_id'] ) ) {
				$id = absint( wp_unslash( $_POST['synonym_id'] ) );

				if ( $synonyms_manager->delete( $id ) ) {
					add_settings_error( 'mantiload_synonyms', 'synonym_deleted', 'Synonym deleted successfully!', 'success' );
				} else {
					add_settings_error( 'mantiload_synonyms', 'synonym_error', 'Error deleting synonym.', 'error' );
				}
			} elseif ( $_POST['action'] === 'bulk_delete_synonyms' ) {
				$ids = isset( $_POST['synonym_ids'] ) ? array_map( 'absint', wp_unslash( (array) $_POST['synonym_ids'] ) ) : array();

				if ( ! empty( $ids ) ) {
					$deleted = 0;
					foreach ( $ids as $id ) {
						if ( $synonyms_manager->delete( $id ) ) {
							$deleted++;
						}
					}

					if ( $deleted > 0 ) {
						add_settings_error( 'mantiload_synonyms', 'synonyms_deleted', sprintf( '%d synonym(s) deleted successfully!', $deleted ), 'success' );
					} else {
						add_settings_error( 'mantiload_synonyms', 'synonym_error', 'Error deleting synonyms.', 'error' );
					}
				}
			}
		}

		$synonyms_manager = new \MantiLoad\Search\Synonyms();
		$synonyms = $synonyms_manager->get_all();

		include MANTILOAD_PLUGIN_DIR . 'admin/views/synonyms.php';
	}

	public function analytics_page() {
		$logs = \get_option( 'mantiload_search_logs', array() );
		$logs = array_reverse( $logs ); // Most recent first
		$logs = array_slice( $logs, 0, 100 ); // Show last 100

		// Calculate stats
		$total_searches = count( \get_option( 'mantiload_search_logs', array() ) );
		$avg_time = 0;
		$avg_results = 0;

		if ( ! empty( $logs ) ) {
			$total_time = array_sum( array_column( $logs, 'time' ) );
			$total_results = array_sum( array_column( $logs, 'results' ) );
			$avg_time = $total_time / count( $logs );
			$avg_results = $total_results / count( $logs );
		}

		include MANTILOAD_PLUGIN_DIR . 'admin/views/analytics.php';
	}

	/**
	 * Enhanced Analytics Page with Chart.js visualizations
	 */
	public function analytics_enhanced_page() {
		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'You do not have sufficient permissions to access this page.' );
		}

		// Chart.js for analytics charts (bundled locally for WordPress.org compliance)
		\wp_enqueue_script( 'chart-js', MANTILOAD_PLUGIN_URL . 'assets/vendor/chart.umd.min.js', array(), '4.4.0', true );

		include MANTILOAD_PLUGIN_DIR . 'admin/views/analytics-enhanced.php';
	}

	public function database_indexes_page() {
		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'You do not have sufficient permissions to access this page.' );
		}

		include MANTILOAD_PLUGIN_DIR . 'admin/views/database-indexes.php';
	}

	/**
	 * AJAX: Get total posts count for progress calculation
	 */
	public function ajax_get_total_posts() {
		// Verify nonce
		if ( ! \check_ajax_referer( 'mantiload-admin', 'nonce', false ) ) {
			\wp_send_json_error( 'Nonce verification failed' );
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			\wp_send_json_error( 'Unauthorized' );
			return;
		}

		// Check if indexer is available
		$indexer = \MantiLoad\MantiLoad::instance()->indexer;
		if ( ! $indexer ) {
			\wp_send_json_error( 'MantiLoad indexer not initialized. Please check Manticore Search connection in MantiLoad > Settings.' );
			return;
		}

		$post_types = \MantiLoad\MantiLoad::get_option( 'post_types', array( 'post', 'page', 'product' ) );
		$total = 0;

		foreach ( $post_types as $post_type ) {
			$count = wp_count_posts( $post_type );
			$total += $count->publish;
		}

		\wp_send_json_success( array(
			'total' => $total,
			'post_types' => $post_types,
		) );
	}

	/**
	 * AJAX: Reindex a batch of posts
	 */
	public function ajax_reindex_batch() {
		\check_ajax_referer( 'mantiload-admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			\wp_send_json_error( 'Unauthorized' );
		}

		$post_type = sanitize_text_field( wp_unslash( $_POST['post_type'] ?? '' ) );
		$offset = absint( wp_unslash( $_POST['offset'] ?? 0 ) );
		$batch_size = absint( wp_unslash( $_POST['batch_size'] ?? 100 ) );

		if ( empty( $post_type ) ) {
			\wp_send_json_error( 'Missing post_type' );
		}

		$indexer = \MantiLoad\MantiLoad::instance()->indexer;

		// Check if indexer is initialized
		if ( ! $indexer ) {
			\wp_send_json_error( 'MantiLoad indexer not initialized. Please check Manticore Search connection in MantiLoad > Settings.' );
		}

		// Reindex this batch
		$results = $indexer->reindex_all( array( $post_type ), $offset, $batch_size );

		\wp_send_json_success( array(
			'indexed' => $results['indexed'],
			'failed' => $results['failed'],
			'time' => $results['time'],
			'offset' => $offset,
			'batch_size' => $batch_size,
		) );
	}

	/**
	 * Handle CSV export of search insights
	 */
	public function handle_csv_export() {
		if ( ! isset( $_GET['mantiload_export_insights'] ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Verify nonce for export action
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'mantiload_export_insights' ) ) {
			wp_die( esc_html__( 'Security verification failed. Please try again.', 'mantiload' ) );
		}

		$period = sanitize_text_field( wp_unslash( $_GET['mantiload_export_insights'] ) );
		\MantiLoad\Search_Insights::export_csv( $period );
	}

	/**
	 * AJAX: Install a single database index
	 */
	public function ajax_install_index() {
		// Verify nonce
		if ( ! \check_ajax_referer( 'mantiload-indexes', 'nonce', false ) ) {
			\wp_send_json_error( array( 'message' => 'Nonce verification failed' ) );
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			\wp_send_json_error( array( 'message' => 'Unauthorized' ) );
			return;
		}

		$index_name = isset( $_POST['index_name'] ) ? sanitize_text_field( wp_unslash( $_POST['index_name'] ) ) : '';

		if ( empty( $index_name ) ) {
			\wp_send_json_error( array( 'message' => 'Index name is required' ) );
			return;
		}

		require_once MANTILOAD_PLUGIN_DIR . 'includes/class-database-indexes.php';
		$index_manager = new \MantiLoad_Database_Indexes();

		$result = $index_manager->create_index( $index_name );

		if ( $result['success'] ) {
			\wp_send_json_success( $result );
		} else {
			\wp_send_json_error( $result );
		}
	}

	/**
	 * AJAX: Install all database indexes
	 */
	public function ajax_install_all_indexes() {
		// Verify nonce
		if ( ! \check_ajax_referer( 'mantiload-indexes', 'nonce', false ) ) {
			\wp_send_json_error( array( 'message' => 'Nonce verification failed' ) );
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			\wp_send_json_error( array( 'message' => 'Unauthorized' ) );
			return;
		}

		require_once MANTILOAD_PLUGIN_DIR . 'includes/class-database-indexes.php';
		$index_manager = new \MantiLoad_Database_Indexes();

		$results = $index_manager->create_all_indexes();

		$installed = 0;
		$failed = 0;
		$skipped = 0;

		foreach ( $results as $result ) {
			if ( isset( $result['skipped'] ) && $result['skipped'] ) {
				$skipped++;
			} elseif ( $result['success'] ) {
				$installed++;
			} else {
				$failed++;
			}
		}

		if ( $failed > 0 ) {
			\wp_send_json_error( array(
				'message' => sprintf(
					/* translators: %1$d: installed count, %2$d: failed count */
					__( 'Installed %1$d indexes, %2$d failed', 'mantiload' ),
					$installed,
					$failed
				),
				'installed' => $installed,
				'failed' => $failed,
				'skipped' => $skipped,
				'results' => $results,
			) );
		} else {
			\wp_send_json_success( array(
				'message' => sprintf(
					/* translators: %d: installed count */
					__( 'Successfully installed %d indexes', 'mantiload' ),
					$installed
				),
				'installed' => $installed,
				'skipped' => $skipped,
				'results' => $results,
			) );
		}
	}

	/**
	 * AJAX: Remove a single database index
	 */
	public function ajax_remove_index() {
		// Verify nonce
		if ( ! \check_ajax_referer( 'mantiload-indexes', 'nonce', false ) ) {
			\wp_send_json_error( array( 'message' => 'Nonce verification failed' ) );
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			\wp_send_json_error( array( 'message' => 'Unauthorized' ) );
			return;
		}

		$index_name = isset( $_POST['index_name'] ) ? sanitize_text_field( wp_unslash( $_POST['index_name'] ) ) : '';

		if ( empty( $index_name ) ) {
			\wp_send_json_error( array( 'message' => 'Index name is required' ) );
			return;
		}

		require_once MANTILOAD_PLUGIN_DIR . 'includes/class-database-indexes.php';
		$index_manager = new \MantiLoad_Database_Indexes();

		$result = $index_manager->remove_index( $index_name );

		if ( $result['success'] ) {
			\wp_send_json_success( $result );
		} else {
			\wp_send_json_error( $result );
		}
	}

	/**
	 * AJAX: Remove all database indexes
	 */
	public function ajax_remove_all_indexes() {
		// Verify nonce
		if ( ! \check_ajax_referer( 'mantiload-indexes', 'nonce', false ) ) {
			\wp_send_json_error( array( 'message' => 'Nonce verification failed' ) );
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			\wp_send_json_error( array( 'message' => 'Unauthorized' ) );
			return;
		}

		require_once MANTILOAD_PLUGIN_DIR . 'includes/class-database-indexes.php';
		$index_manager = new \MantiLoad_Database_Indexes();

		$results = $index_manager->remove_all_indexes();

		$removed = 0;
		$failed = 0;
		$skipped = 0;

		foreach ( $results as $result ) {
			if ( isset( $result['skipped'] ) && $result['skipped'] ) {
				$skipped++;
			} elseif ( $result['success'] ) {
				$removed++;
			} else {
				$failed++;
			}
		}

		if ( $failed > 0 ) {
			\wp_send_json_error( array(
				'message' => sprintf(
					/* translators: %1$d: removed count, %2$d: failed count */
					__( 'Removed %1$d indexes, %2$d failed', 'mantiload' ),
					$removed,
					$failed
				),
				'removed' => $removed,
				'failed' => $failed,
				'skipped' => $skipped,
				'results' => $results,
			) );
		} else {
			\wp_send_json_success( array(
				'message' => sprintf(
					/* translators: %d: removed count */
					__( 'Successfully removed %d indexes', 'mantiload' ),
					$removed
				),
				'removed' => $removed,
				'skipped' => $skipped,
				'results' => $results,
			) );
		}
	}
}
