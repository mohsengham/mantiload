<?php
defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- Admin dashboard view with trusted data from database

// Start output buffering to convert Lucide icons to WordPress-compatible icons
ob_start();
?>

<div class="wrap">
	<?php settings_errors( 'mantiload' ); ?>
</div>

<div class="wrap mantiload-dashboard">
	<h1 class="mantiload-title">
		<span class="mantiload-logo">
			<img src="<?php echo esc_url( MANTILOAD_PLUGIN_URL . 'assets/img/logo.png' ); ?>" alt="MantiLoad">
		</span>
		MantiLoad
	</h1>

	<div class="mantiload-hero">
		<h2>Ultra-Fast Search Powered by Manticore</h2>
		<p>Lightning-fast full-text search with sub-millisecond response times</p>
	</div>

	<div class="mantiload-stats-grid">
		<?php foreach ( $stats as $post_type => $mantiload_stat ): ?>
		<div class="mantiload-stat-card">
			<div class="stat-icon">
				<i data-lucide="file-text"></i>
			</div>
			<div class="stat-content">
				<h3><?php echo esc_html( ucfirst( $post_type ) ); ?></h3>
				<div class="stat-number"><?php echo number_format( $mantiload_stat['indexed'] ); ?> / <?php echo number_format( $mantiload_stat['total'] ); ?></div>
				<div class="stat-progress">
					<div class="progress-bar" style="width: <?php echo esc_attr( $mantiload_stat['percentage'] ); ?>%"></div>
				</div>
				<div class="stat-percentage"><?php echo esc_html( $mantiload_stat['percentage'] ); ?>%</div>
			</div>
		</div>
		<?php endforeach; ?>
	</div>

	<?php
	// Get search insights
	$mantiload_insights = \MantiLoad\Search_Insights::get_insights( 'week' );
	$mantiload_top_searches = $mantiload_insights['top_searches'];
	$mantiload_zero_results = $mantiload_insights['zero_results'];
	$mantiload_trending = $mantiload_insights['trending'];
	$mantiload_performance = $mantiload_insights['performance'];
	?>

	<?php if ( ! empty( $mantiload_top_searches ) || ! empty( $mantiload_zero_results ) ): ?>
	<!-- Top Searches & Insights Widget -->
	<div class="mantiload-card" style="margin: 30px 0; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; box-shadow: 0 10px 40px rgba(102, 126, 234, 0.3);">
		<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 25px;">
			<div style="display: flex; align-items: center; gap: 15px;">
				<div style="font-size: 42px;">
					<i data-lucide="trending-up" style="color: white;"></i>
				</div>
				<div>
					<h2 style="color: white; margin: 0 0 5px 0; font-size: 28px; font-weight: 700;">
						Top Searches & Insights
					</h2>
					<p style="margin: 0; opacity: 0.9; font-size: 15px;">
						Last 7 days • <?php echo number_format( $performance['total_searches'] ); ?> total searches
					</p>
				</div>
			</div>
			<div style="display: flex; gap: 10px;">
				<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'mantiload_export_insights', 'week' ), 'mantiload_export_insights' ) ); ?>"
				   class="ml-btn"
				   style="background: rgba(255,255,255,0.2); color: white; border: 1px solid rgba(255,255,255,0.3); padding: 8px 16px; font-size: 13px;">
					<i data-lucide="download"></i> Export CSV
				</a>
			</div>
		</div>

		<!-- Performance Metrics -->
		<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 30px;">
			<div style="background: rgba(255,255,255,0.15); padding: 20px; border-radius: 12px; text-align: center;">
				<div style="font-size: 13px; opacity: 0.9; margin-bottom: 8px;">Average Time</div>
				<div style="font-size: 32px; font-weight: 700; margin-bottom: 5px;"><?php echo number_format( $performance['avg_time'], 1 ); ?>ms</div>
				<div style="font-size: 12px; opacity: 0.8;">⚡ Lightning fast</div>
			</div>
			<div style="background: rgba(255,255,255,0.15); padding: 20px; border-radius: 12px; text-align: center;">
				<div style="font-size: 13px; opacity: 0.9; margin-bottom: 8px;">Success Rate</div>
				<div style="font-size: 32px; font-weight: 700; margin-bottom: 5px;"><?php echo $performance['success_rate']; ?>%</div>
				<div style="font-size: 12px; opacity: 0.8;">Searches with results</div>
			</div>
			<div style="background: rgba(255,255,255,0.15); padding: 20px; border-radius: 12px; text-align: center;">
				<div style="font-size: 13px; opacity: 0.9; margin-bottom: 8px;">Avg Results</div>
				<div style="font-size: 32px; font-weight: 700; margin-bottom: 5px;"><?php echo number_format( $performance['avg_results'], 1 ); ?></div>
				<div style="font-size: 12px; opacity: 0.8;">Products per search</div>
			</div>
		</div>

		<!-- Top Searches -->
		<?php if ( ! empty( $mantiload_top_searches ) ): ?>
		<div style="background: white; color: #1a1a1a; border-radius: 16px; padding: 30px; margin-bottom: 20px;">
			<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 25px;">
				<h3 style="margin: 0; font-size: 20px; font-weight: 700; color: #1a1a1a;">
					<i data-lucide="search" style="width: 20px; height: 20px; vertical-align: middle;"></i>
					Top Search Terms
				</h3>
				<span style="font-size: 13px; color: #666; background: #f0f0f0; padding: 6px 12px; border-radius: 20px;">
					<?php echo count( $mantiload_top_searches ); ?> terms
				</span>
			</div>

			<div style="display: flex; flex-direction: column; gap: 12px;">
				<?php
				$mantiload_max_count = max( array_column( $mantiload_top_searches, 'count' ) );
				$mantiload_rank = 0;
				foreach ( $mantiload_top_searches as $search ):
					$mantiload_rank++;
					$mantiload_width = ( $search['count'] / $mantiload_max_count ) * 100;
					$mantiload_is_trending = isset( $mantiload_trending[ strtolower( $search['query'] ) ] );
					$mantiload_success_color = $search['success_rate'] >= 90 ? '#10b981' : ( $search['success_rate'] >= 70 ? '#f59e0b' : '#ef4444' );
				?>
				<div style="position: relative;">
					<div style="display: flex; align-items: center; gap: 15px;">
						<div style="min-width: 30px; text-align: center; font-weight: 700; font-size: 16px; color: #999;">
							<?php echo $mantiload_rank; ?>.
						</div>
						<div style="flex: 1;">
							<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px;">
								<div style="display: flex; align-items: center; gap: 8px;">
									<strong style="font-size: 15px; color: #1a1a1a;"><?php echo esc_html( $search['query'] ); ?></strong>
									<?php if ( $mantiload_is_trending ): ?>
									<span style="background: #ef4444; color: white; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 600;">
										🔥 +<?php echo round( $trending[ strtolower( $search['query'] ) ]['growth'] ); ?>%
									</span>
									<?php endif; ?>
								</div>
								<div style="display: flex; align-items: center; gap: 15px; font-size: 13px;">
									<span style="color: #666;">
										<strong style="color: #1a1a1a;"><?php echo number_format( $search['count'] ); ?></strong> searches
									</span>
									<span style="color: <?php echo $mantiload_success_color; ?>; font-weight: 600;">
										<?php echo round( $search['success_rate'] ); ?>% found
									</span>
									<span style="color: #999;">
										<?php echo round( $search['avg_time'], 1 ); ?>ms
									</span>
								</div>
							</div>
							<div style="background: #f0f0f0; height: 8px; border-radius: 4px; overflow: hidden;">
								<div style="background: linear-gradient(90deg, #667eea 0%, #764ba2 100%); height: 100%; width: <?php echo $mantiload_width; ?>%; transition: width 0.3s ease;"></div>
							</div>
						</div>
					</div>
				</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php endif; ?>

		<!-- Zero Results Alert -->
		<?php if ( ! empty( $mantiload_zero_results ) ): ?>
		<div style="background: #fef3c7; color: #92400e; border-radius: 16px; padding: 25px; border-left: 4px solid #f59e0b;">
			<h3 style="margin: 0 0 15px 0; font-size: 18px; font-weight: 700; color: #92400e; display: flex; align-items: center; gap: 10px;">
				<i data-lucide="alert-triangle" style="color: #f59e0b;"></i>
				Zero Results Queries (Fix These!)
			</h3>
			<p style="margin: 0 0 20px 0; font-size: 14px; color: #78350f;">
				These searches found no results. Fix them to improve customer experience and capture lost sales.
			</p>

			<div style="display: flex; flex-direction: column; gap: 12px;">
				<?php foreach ( array_slice( $mantiload_zero_results, 0, 5 ) as $mantiload_query ): ?>
				<div style="background: white; padding: 15px 20px; border-radius: 10px; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
					<div style="flex: 1;">
						<div style="display: flex; align-items: center; gap: 10px; margin-bottom: 5px;">
							<strong style="font-size: 15px; color: #1a1a1a;">"<?php echo esc_html( $mantiload_query['query'] ); ?>"</strong>
							<span style="background: #fee2e2; color: #991b1b; padding: 3px 10px; border-radius: 12px; font-size: 12px; font-weight: 600;">
								<?php echo $mantiload_query['count']; ?> searches
							</span>
						</div>
						<div style="font-size: 13px; color: #78350f;">
							💡 <?php echo esc_html( $query['suggestion'] ); ?>
						</div>
					</div>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=mantiload-synonyms' ) ); ?>"
					   class="ml-btn ml-btn-sm"
					   style="background: #667eea; color: white; padding: 8px 16px; font-size: 13px; text-decoration: none; border-radius: 8px; white-space: nowrap;">
						<i data-lucide="plus" style="width: 14px; height: 14px;"></i> Add Synonym
					</a>
				</div>
				<?php endforeach; ?>
			</div>

			<?php if ( count( $mantiload_zero_results ) > 5 ): ?>
			<div style="text-align: center; margin-top: 15px;">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=mantiload-analytics' ) ); ?>"
				   style="color: #92400e; text-decoration: underline; font-size: 14px; font-weight: 600;">
					View all <?php echo esc_html( count( $mantiload_zero_results ) ); ?> zero-result queries →
				</a>
			</div>
			<?php endif; ?>
		</div>
		<?php endif; ?>
	</div>
	<?php endif; ?>

	<?php
	// Get search cache statistics
	global $wpdb;
	$mantiload_cache_count = $wpdb->get_var(
		"SELECT COUNT(*) FROM {$wpdb->options}
		WHERE option_name LIKE '_transient_mantiload_search_%'
		AND option_name NOT LIKE '_transient_timeout_%'"
	);
	$mantiload_cache_size = $wpdb->get_var(
		"SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options}
		WHERE option_name LIKE '_transient_mantiload_search_%'
		AND option_name NOT LIKE '_transient_timeout_%'"
	);
	$mantiload_cache_size_kb = $mantiload_cache_size ? round($mantiload_cache_size / 1024, 2) : 0;
	?>

	<?php if ($mantiload_cache_count > 0): ?>
	<div class="mantiload-cache-info">
		<h3>
			<i data-lucide="database"></i>
			Search Cache Status
		</h3>
		<div style="display: flex; gap: 30px; flex-wrap: wrap;">
			<div>
				<strong style="font-size: 20px; color: var(--ml-black);"><?php echo number_format($mantiload_cache_count); ?></strong>
				<div style="color: var(--ml-gray-600); font-size: 12px;">Cached Searches</div>
			</div>
			<div>
				<strong style="font-size: 20px; color: var(--ml-black);"><?php echo $mantiload_cache_size_kb; ?> KB</strong>
				<div style="color: var(--ml-gray-600); font-size: 12px;">Cache Size</div>
			</div>
			<div style="flex: 1;">
				<p style="margin: 0; color: var(--ml-gray-600); font-size: 13px;">
					Cached searches are 95% faster. Cache is automatically cleared when products are updated.
				</p>
			</div>
		</div>
	</div>
	<?php endif; ?>

	<div class="mantiload-quick-actions">
		<h2>Quick Actions</h2>
		<div class="ml-btn-group">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=mantiload-indexing' ) ); ?>" class="ml-btn ml-btn-primary">
				<i data-lucide="refresh-cw"></i>
				Reindex All
			</a>

			<button type="button" id="dashboard-optimize-btn" class="ml-btn">
				<i data-lucide="zap"></i>
				Optimize Indexes
			</button>

			<button type="button" id="dashboard-clear-cache-btn" class="ml-btn">
				<i data-lucide="trash-2"></i>
				Clear Cache
			</button>

			<a href="<?php echo esc_url( admin_url( 'admin.php?page=mantiload-analytics' ) ); ?>" class="ml-btn">
				<i data-lucide="bar-chart-2"></i>
				Analytics
			</a>
		</div>

		<!-- Beautiful Real-Time Progress Container for Dashboard Reindex -->
		<div id="dashboard-progress-container" style="display:none; margin-top: 20px; padding: 20px; background: #fff; border: 1px solid #ccd0d4; border-radius: 4px;">
			<div style="margin-bottom: 10px;">
				<div id="dashboard-progress-text" style="font-size: 14px; font-weight: 600; margin-bottom: 8px;">Starting...</div>
				<div style="background: #f0f0f1; height: 30px; border-radius: 4px; overflow: hidden;">
					<div id="dashboard-progress-bar" style="background: linear-gradient(90deg, #00a32a 0%, #008a20 100%); height: 100%; width: 0%; transition: width 0.3s; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 12px;"></div>
				</div>
				<div id="dashboard-progress-stats" style="font-size: 12px; color: #646970; margin-top: 8px;"></div>
			</div>
		</div>

		<!-- Status notifications container -->
		<div id="dashboard-status-container"></div>
	</div>

	<div class="mantiload-shortcode-docs">
		<h2>Search Box Shortcode</h2>
		<p>Add the MantiLoad AJAX search box anywhere on your site using the <code>[mantiload_search]</code> shortcode.</p>

		<div class="shortcode-examples">
			<h3>Basic Usage</h3>
			<div class="code-example">
				<code>[mantiload_search]</code>
				<p class="description">Default search box with all default settings</p>
			</div>

			<h3>Available Parameters</h3>
			<table class="widefat striped">
				<thead>
					<tr>
						<th>Parameter</th>
						<th>Default</th>
						<th>Description</th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><code>placeholder</code></td>
						<td>"Search products..."</td>
						<td>Placeholder text in search input</td>
					</tr>
					<tr>
						<td><code>post_types</code></td>
						<td>"product"</td>
						<td>Comma-separated post types to search</td>
					</tr>
					<tr>
						<td><code>show_button</code></td>
						<td>"true"</td>
						<td>Show search button (true/false)</td>
					</tr>
					<tr>
						<td><code>button_text</code></td>
						<td>"Search"</td>
						<td>Text for search button</td>
					</tr>
					<tr>
						<td><code>view_all_text</code></td>
						<td>"View all results"</td>
						<td>Text for "view all" link in dropdown</td>
					</tr>
					<tr>
						<td><code>width</code></td>
						<td>"100%"</td>
						<td>Width of search box (px, %, em, rem, vw)</td>
					</tr>
					<tr>
						<td><code>class</code></td>
						<td>""</td>
						<td>Custom CSS class for styling</td>
					</tr>
				</tbody>
			</table>

			<h3>Examples</h3>

			<div class="code-example">
				<strong>Custom Placeholder & Button Text</strong>
				<code>[mantiload_search placeholder="Search our store..." button_text="Go"]</code>
			</div>

			<div class="code-example">
				<strong>Search Multiple Post Types</strong>
				<code>[mantiload_search post_types="product,post,page"]</code>
			</div>

			<div class="code-example">
				<strong>Custom Width</strong>
				<code>[mantiload_search width="500px"]</code>
			</div>

			<div class="code-example">
				<strong>No Button (Instant Search Only)</strong>
				<code>[mantiload_search show_button="false"]</code>
			</div>

			<div class="code-example">
				<strong>Full Example</strong>
				<code>[mantiload_search placeholder="Find products..." width="600px" class="header-search"]</code>
			</div>
		</div>

		<div class="shortcode-tips">
			<h3>Tips</h3>
			<ul>
				<li><strong>Page Builders:</strong> Use in Elementor, Gutenberg, or any page builder that supports shortcodes</li>
				<li><strong>Widgets:</strong> Add to sidebars using the "Custom HTML" widget</li>
				<li><strong>PHP Templates:</strong> Use <code>&lt;?php echo do_shortcode('[mantiload_search]'); ?&gt;</code></li>
				<li><strong>Multiple Instances:</strong> You can add multiple search boxes on the same page</li>
				<li><strong>Styling:</strong> Use the <code>class</code> parameter to add custom CSS classes</li>
			</ul>
		</div>
	</div>

	<?php
	// Check if the recommended plugin is installed
	$mantiload_plugin_slug = 'index-wp-mysql-for-speed';
	$mantiload_plugin_file = 'index-wp-mysql-for-speed/index-wp-mysql-for-speed.php';
	$mantiload_plugin_installed = file_exists( WP_PLUGIN_DIR . '/' . $mantiload_plugin_file );
	$mantiload_plugin_active = is_plugin_active( $mantiload_plugin_file );
	?>

	<!-- Plugin Recommendation Widget -->
	<div class="mantiload-card" style="margin: 30px 0; background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; border: none; box-shadow: 0 10px 40px rgba(240, 147, 251, 0.3);">
		<div style="display: flex; align-items: center; gap: 20px;">
			<div style="flex-shrink: 0;">
				<div style="width: 80px; height: 80px; background: white; border-radius: 16px; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
					<span class="dashicons dashicons-database" style="font-size: 48px; width: 48px; height: 48px; color: #f5576c;"></span>
				</div>
			</div>

			<div style="flex: 1;">
				<div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">
					<h2 style="color: white; margin: 0; font-size: 24px; font-weight: 700;">
						<?php echo esc_html__( '💎 Hidden Gem Recommendation', 'mantiload' ); ?>
					</h2>
					<div style="background: rgba(255,255,255,0.25); padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600;">
						⭐ 4.8 Rating
					</div>
				</div>

				<h3 style="color: white; margin: 0 0 12px 0; font-size: 20px; font-weight: 600;">
					Index WP MySQL For Speed
				</h3>

				<p style="margin: 0 0 15px 0; opacity: 0.95; font-size: 15px; line-height: 1.6;">
					<strong>Perfect companion to MantiLoad!</strong> While MantiLoad supercharges your search, this plugin optimizes MySQL database indexes to speed up ALL WordPress queries - admin panel, page loads, and WooCommerce operations.
				</p>

				<div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; margin-bottom: 20px;">
					<div style="background: rgba(255,255,255,0.15); padding: 12px 15px; border-radius: 10px; font-size: 14px;">
						<span style="opacity: 0.9;">✓</span> <strong>One-click database optimization</strong>
					</div>
					<div style="background: rgba(255,255,255,0.15); padding: 12px 15px; border-radius: 10px; font-size: 14px;">
						<span style="opacity: 0.9;">✓</span> <strong>Monitors slow queries</strong>
					</div>
					<div style="background: rgba(255,255,255,0.15); padding: 12px 15px; border-radius: 10px; font-size: 14px;">
						<span style="opacity: 0.9;">✓</span> <strong>WooCommerce optimized</strong>
					</div>
					<div style="background: rgba(255,255,255,0.15); padding: 12px 15px; border-radius: 10px; font-size: 14px;">
						<span style="opacity: 0.9;">✓</span> <strong>531K+ downloads</strong>
					</div>
				</div>

				<div style="display: flex; align-items: center; gap: 12px;">
					<?php if ( ! $mantiload_plugin_installed ): ?>
						<a href="<?php echo esc_url( admin_url( 'plugin-install.php?s=index-wp-mysql-for-speed&tab=search&type=term' ) ); ?>"
						   class="ml-btn"
						   style="background: white; color: #f5576c; font-weight: 600; padding: 12px 24px; border-radius: 10px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);">
							<i data-lucide="download" style="width: 18px; height: 18px;"></i>
							Install Plugin
						</a>
					<?php elseif ( ! $mantiload_plugin_active ): ?>
						<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'plugins.php?action=activate&plugin=' . $mantiload_plugin_file ), 'activate-plugin_' . $mantiload_plugin_file ) ); ?>"
						   class="ml-btn"
						   style="background: white; color: #f5576c; font-weight: 600; padding: 12px 24px; border-radius: 10px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);">
							<i data-lucide="zap" style="width: 18px; height: 18px;"></i>
							Activate Plugin
						</a>
					<?php else: ?>
						<span style="background: rgba(255,255,255,0.25); padding: 12px 24px; border-radius: 10px; font-weight: 600; display: inline-flex; align-items: center; gap: 8px;">
							<i data-lucide="check-circle" style="width: 18px; height: 18px;"></i>
							Already Installed & Active
						</span>
					<?php endif; ?>

					<a href="https://wordpress.org/plugins/index-wp-mysql-for-speed/"
					   target="_blank"
					   style="color: white; text-decoration: underline; font-size: 14px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px;">
						Learn More
						<i data-lucide="external-link" style="width: 14px; height: 14px;"></i>
					</a>
				</div>
			</div>
		</div>
	</div>

	<div class="mantiload-features">
		<h2>Features</h2>
		<div class="features-grid">
			<div class="feature-card">
				<span class="feature-icon"><i data-lucide="zap"></i></span>
				<h3>Lightning Fast</h3>
				<p>Sub-millisecond search response times with Manticore Search engine</p>
			</div>
			<div class="feature-card">
				<span class="feature-icon"><i data-lucide="target"></i></span>
				<h3>Relevance Scoring</h3>
				<p>Advanced BM25 ranking algorithm for the most relevant results</p>
			</div>
			<div class="feature-card">
				<span class="feature-icon"><i data-lucide="search"></i></span>
				<h3>Fuzzy Search</h3>
				<p>Typo tolerance and approximate matching for better user experience</p>
			</div>
			<div class="feature-card">
				<span class="feature-icon"><i data-lucide="filter"></i></span>
				<h3>Advanced Filters</h3>
				<p>Filter by categories, tags, price, attributes, and custom fields</p>
			</div>
			<div class="feature-card">
				<span class="feature-icon"><i data-lucide="bar-chart"></i></span>
				<h3>Analytics</h3>
				<p>Track search queries, performance metrics, and user behavior</p>
			</div>
			<div class="feature-card">
				<span class="feature-icon"><i data-lucide="shopping-cart"></i></span>
				<h3>WooCommerce</h3>
				<p>Optimized for WooCommerce with product-specific search fields</p>
			</div>
		</div>
	</div>
</div>

<?php
// Convert Lucide icons to WordPress-compatible icons (Dashicons + SVG)
$mantiload_html = ob_get_clean();
echo \MantiLoad\Icons::replace_in_html( $mantiload_html );

// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
?>
