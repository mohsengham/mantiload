<?php
defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- Admin settings view with WordPress core functions
?>

<div class="wrap">
	<?php settings_errors( 'mantiload' ); ?>
</div>

<div class="mantiload-admin-wrap">
	<!-- Header -->
	<div class="mantiload-header">
		<h1>
			<span class="mantiload-logo">
				<img src="<?php echo esc_url( plugins_url( 'assets/images/logo.jpg', MANTILOAD_PLUGIN_FILE ) ); ?>" alt="MantiLoad">
			</span>
			<?php esc_html_e( 'Settings', 'mantiload' ); ?>
		</h1>
		<p><?php esc_html_e( 'Configure your search engine for optimal performance', 'mantiload' ); ?></p>
	</div>

	<!-- Index Status Widget -->
	<div class="mantiload-card" id="mantiload-status-widget">
		<h2><i class="dashicons dashicons-chart-line"></i> <?php esc_html_e( 'Index Status', 'mantiload' ); ?></h2>
		<div class="mantiload-divider"></div>

		<div class="mantiload-status-grid-compact">
			<div class="mantiload-status-row">
				<div class="mantiload-status-compact">
					<span class="mantiload-status-label-inline"><?php esc_html_e( 'Connection:', 'mantiload' ); ?></span>
					<span class="mantiload-status-value-inline" id="connection-status">
						<span class="mantiload-spinner"></span> <?php esc_html_e( 'Checking...', 'mantiload' ); ?>
					</span>
				</div>
				<div class="mantiload-status-compact">
					<span class="mantiload-status-label-inline"><?php esc_html_e( 'Documents:', 'mantiload' ); ?></span>
					<span class="mantiload-status-value-inline" id="document-count">
						<span class="mantiload-spinner"></span> <?php esc_html_e( 'Loading...', 'mantiload' ); ?>
					</span>
				</div>
				<div class="mantiload-status-compact">
					<span class="mantiload-status-label-inline"><?php esc_html_e( 'Index Size:', 'mantiload' ); ?></span>
					<span class="mantiload-status-value-inline" id="index-size">
						<span class="mantiload-spinner"></span> <?php esc_html_e( 'Loading...', 'mantiload' ); ?>
					</span>
				</div>
			</div>
			<div class="mantiload-status-row">
				<div class="mantiload-status-compact">
					<span class="mantiload-status-label-inline"><?php esc_html_e( 'Index:', 'mantiload' ); ?></span>
					<span class="mantiload-status-value-inline">
						<code><?php echo esc_html( $settings['index_name'] ?? \MantiLoad\MantiLoad::get_default_index_name() ); ?></code>
					</span>
				</div>
				<div class="mantiload-status-compact">
					<span class="mantiload-status-label-inline"><?php esc_html_e( 'Server:', 'mantiload' ); ?></span>
					<span class="mantiload-status-value-inline">
						<?php echo esc_html( $settings['manticore_host'] ?? '127.0.0.1' ); ?>:<?php echo esc_html( $settings['manticore_port'] ?? 9306 ); ?>
					</span>
				</div>
			</div>
		</div>
	</div>

	<!-- Tabbed Navigation -->
	<div class="mantiload-tabs-container">
		<div class="mantiload-tabs-nav">
			<button type="button" class="mantiload-tab-button active" data-tab="connection">
				<i class="dashicons dashicons-admin-plugins"></i>
				<span><?php esc_html_e( 'Connection', 'mantiload' ); ?></span>
			</button>
			<button type="button" class="mantiload-tab-button" data-tab="general">
				<i class="dashicons dashicons-admin-settings"></i>
				<span><?php esc_html_e( 'General', 'mantiload' ); ?></span>
			</button>
			<button type="button" class="mantiload-tab-button" data-tab="relevance">
				<i class="dashicons dashicons-sort"></i>
				<span><?php esc_html_e( 'Relevance', 'mantiload' ); ?></span>
			</button>
			<button type="button" class="mantiload-tab-button" data-tab="ajax">
				<i class="dashicons dashicons-update-alt"></i>
				<span><?php esc_html_e( 'AJAX Search', 'mantiload' ); ?></span>
			</button>
			<button type="button" class="mantiload-tab-button" data-tab="performance">
				<i class="dashicons dashicons-performance"></i>
				<span><?php esc_html_e( 'Performance', 'mantiload' ); ?></span>
			</button>
			<button type="button" class="mantiload-tab-button" data-tab="admin">
				<i class="dashicons dashicons-admin-tools"></i>
				<span><?php esc_html_e( 'Admin Features', 'mantiload' ); ?></span>
			</button>
			<button type="button" class="mantiload-tab-button" data-tab="related">
				<i class="dashicons dashicons-star-filled"></i>
				<span><?php esc_html_e( 'Related Products', 'mantiload' ); ?></span>
			</button>
			<button type="button" class="mantiload-tab-button" data-tab="integration">
				<i class="dashicons dashicons-editor-code"></i>
				<span><?php esc_html_e( 'Integration', 'mantiload' ); ?></span>
			</button>
		</div>

		<form method="post" action="">
			<?php wp_nonce_field( 'mantiload-settings' ); ?>

			<div class="mantiload-tabs-content">

				<!-- Tab 1: Connection & Index Management -->
				<div class="mantiload-tab-panel active" data-tab="connection">
					<!-- Manticore Connection Card -->
					<div class="mantiload-card">
						<h2><i class="dashicons dashicons-admin-plugins"></i> <?php esc_html_e( 'Manticore Connection', 'mantiload' ); ?></h2>
						<p class="mantiload-description"><?php esc_html_e( 'Configure connection to your Manticore Search server', 'mantiload' ); ?></p>

						<div class="mantiload-divider"></div>

						<table class="mantiload-form-table">
							<tr>
								<th><label for="manticore_host"><?php esc_html_e( 'Host Address', 'mantiload' ); ?></label></th>
								<td>
									<input type="text" name="manticore_host" id="manticore_host"
										value="<?php echo esc_attr( $settings['manticore_host'] ?? '127.0.0.1' ); ?>"
										class="mantiload-input">
									<span class="mantiload-description"><?php esc_html_e( 'IP address of your Manticore Search server', 'mantiload' ); ?></span>
								</td>
							</tr>

							<tr>
								<th><label for="manticore_port"><?php esc_html_e( 'Port Number', 'mantiload' ); ?></label></th>
								<td>
									<input type="number" name="manticore_port" id="manticore_port"
										value="<?php echo esc_attr( $settings['manticore_port'] ?? 9306 ); ?>"
										min="1" max="65535" class="mantiload-input">
									<span class="mantiload-description"><?php esc_html_e( 'MySQL protocol port (default: 9306)', 'mantiload' ); ?></span>
								</td>
							</tr>

							<tr>
								<th><label for="index_name"><?php esc_html_e( 'Index Name', 'mantiload' ); ?></label></th>
								<td>
									<input type="text" name="index_name" id="index_name"
										value="<?php echo esc_attr( $settings['index_name'] ?? \MantiLoad\MantiLoad::get_default_index_name() ); ?>"
										class="mantiload-input"
										placeholder="<?php echo esc_attr( \MantiLoad\MantiLoad::get_default_index_name() ); ?>">
									<span class="mantiload-description">
										<?php
										printf(
											/* translators: %s: auto-generated index name */
											esc_html__( 'Name of the Manticore search index. Auto-generated unique name: %s', 'mantiload' ),
											'<code>' . esc_html( \MantiLoad\MantiLoad::get_default_index_name() ) . '</code>'
										);
										?>
									</span>
								</td>
							</tr>
						</table>

						<div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #e5e7eb;">
							<button type="button" id="test-connection-btn" class="ml-btn ml-btn-secondary">
								<i class="dashicons dashicons-bolt"></i>
								<span><?php esc_html_e( 'Test Connection', 'mantiload' ); ?></span>
							</button>
							<span id="test-connection-result" style="margin-left: 12px;"></span>
						</div>
					</div>

					<!-- Index Management Card -->
					<div class="mantiload-card">
						<h2><i class="dashicons dashicons-database"></i> <?php esc_html_e( 'Index Management', 'mantiload' ); ?></h2>
						<p class="mantiload-description"><?php esc_html_e( 'Manage your search index and reindex products', 'mantiload' ); ?></p>

						<div class="mantiload-divider"></div>

						<div style="display: flex; gap: 12px; align-items: center;">
							<button type="button" id="rebuild-index-btn" class="ml-btn ml-btn-primary">
								<i class="dashicons dashicons-update"></i>
								<span><?php esc_html_e( 'Rebuild Index Now', 'mantiload' ); ?></span>
							</button>
							<button type="button" id="rebuild-abort-btn" class="ml-btn ml-btn-danger" style="display:none;">
								<i class="dashicons dashicons-dismiss"></i>
								<span><?php esc_html_e( 'Abort Rebuild', 'mantiload' ); ?></span>
							</button>
						</div>

						<!-- Beautiful Real-Time Progress Container -->
						<div id="rebuild-progress-container" style="display:none; margin-top: 20px; padding: 20px; background: #fff; border: 1px solid #ccd0d4; border-radius: 4px;">
							<div style="margin-bottom: 10px;">
								<div id="rebuild-progress-text" style="font-size: 14px; font-weight: 600; margin-bottom: 8px;">Starting...</div>
								<div style="background: #f0f0f1; height: 30px; border-radius: 4px; overflow: hidden;">
									<div id="rebuild-progress-bar" style="background: linear-gradient(90deg, #00a32a 0%, #008a20 100%); height: 100%; width: 0%; transition: width 0.3s; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 12px;"></div>
								</div>
								<div id="rebuild-progress-stats" style="font-size: 12px; color: #646970; margin-top: 8px;"></div>
							</div>
						</div>

						<div class="mantiload-alert mantiload-alert-info" style="margin-top: 16px;">
							<div>
								<strong><?php esc_html_e( 'When to rebuild:', 'mantiload' ); ?></strong>
								<ul style="margin: 8px 0 0; padding-left: 20px;">
									<li><?php esc_html_e( 'After changing index settings', 'mantiload' ); ?></li>
									<li><?php esc_html_e( 'If search results seem outdated', 'mantiload' ); ?></li>
									<li><?php esc_html_e( 'After bulk product imports', 'mantiload' ); ?></li>
								</ul>
								<p style="margin-top: 8px;">
									<?php esc_html_e( 'Note: Auto-indexing keeps your index up-to-date automatically. Manual rebuilds are rarely needed!', 'mantiload' ); ?>
								</p>
							</div>
						</div>
					</div>
				</div>

				<!-- Tab 2: General Settings -->
				<div class="mantiload-tab-panel" data-tab="general">
					<div class="mantiload-card">
						<h2><i class="dashicons dashicons-admin-settings"></i> <?php esc_html_e( 'General Settings', 'mantiload' ); ?></h2>

						<table class="mantiload-form-table">
							<tr>
								<th><label for="enabled"><?php esc_html_e( 'Enable MantiLoad', 'mantiload' ); ?></label></th>
								<td>
									<label style="display: flex; align-items: center; gap: 10px;">
										<input type="checkbox" name="enabled" id="enabled" value="1"
											<?php checked( $settings['enabled'] ?? true ); ?> class="mantiload-checkbox">
										<span><?php esc_html_e( 'Activate search functionality', 'mantiload' ); ?></span>
									</label>
								</td>
							</tr>

							<tr>
								<th><label><?php esc_html_e( 'Post Types', 'mantiload' ); ?></label></th>
								<td>
									<div style="display: flex; flex-direction: column; gap: 8px;">
										<?php foreach ( $post_types as $post_type ): ?>
										<label style="display: flex; align-items: center; gap: 10px;">
											<input type="checkbox" name="post_types[]"
												value="<?php echo esc_attr( $post_type->name ); ?>"
												<?php checked( in_array( $post_type->name, $settings['post_types'] ?? array() ) ); ?>
												class="mantiload-checkbox">
											<span><?php echo esc_html( $post_type->label ); ?></span>
										</label>
										<?php endforeach; ?>
									</div>
									<span class="mantiload-description"><?php esc_html_e( 'Select which content types to include in search', 'mantiload' ); ?></span>
								</td>
							</tr>

							<tr>
								<th><label for="instant_search"><?php esc_html_e( 'Instant Search', 'mantiload' ); ?></label></th>
								<td>
									<label style="display: flex; align-items: center; gap: 10px;">
										<input type="checkbox" name="instant_search" id="instant_search" value="1"
											<?php checked( $settings['instant_search'] ?? true ); ?> class="mantiload-checkbox">
										<span><?php esc_html_e( 'Show results as user types', 'mantiload' ); ?></span>
									</label>
								</td>
							</tr>

							<tr>
								<th><label for="keyboard_shortcut"><?php esc_html_e( 'Keyboard Shortcut', 'mantiload' ); ?></label></th>
								<td>
									<select name="keyboard_shortcut" id="keyboard_shortcut" class="mantiload-select" style="max-width: 200px;">
										<option value="ctrl+k" <?php selected( $settings['keyboard_shortcut'] ?? 'ctrl+k', 'ctrl+k' ); ?>>Ctrl+K (Default)</option>
										<option value="ctrl+/" <?php selected( $settings['keyboard_shortcut'] ?? 'ctrl+k', 'ctrl+/' ); ?>>Ctrl+/</option>
										<option value="ctrl+shift+k" <?php selected( $settings['keyboard_shortcut'] ?? 'ctrl+k', 'ctrl+shift+k' ); ?>>Ctrl+Shift+K</option>
										<option value="ctrl+shift+f" <?php selected( $settings['keyboard_shortcut'] ?? 'ctrl+k', 'ctrl+shift+f' ); ?>>Ctrl+Shift+F</option>
										<option value="alt+k" <?php selected( $settings['keyboard_shortcut'] ?? 'ctrl+k', 'alt+k' ); ?>>Alt+K</option>
										<option value="ctrl+space" <?php selected( $settings['keyboard_shortcut'] ?? 'ctrl+k', 'ctrl+space' ); ?>>Ctrl+Space</option>
									</select>
									<span class="mantiload-description">
										<?php esc_html_e( 'Choose keyboard shortcut to open search modal (works on admin & frontend)', 'mantiload' ); ?>
										<br><em><?php esc_html_e( 'Tip: Change this if WordPress or another plugin uses Ctrl+K in the future', 'mantiload' ); ?></em>
									</span>
								</td>
							</tr>


							<tr>
								<th><label for="prioritize_in_stock"><?php esc_html_e( 'Stock Priority', 'mantiload' ); ?></label></th>
								<td>
									<label style="display: flex; align-items: center; gap: 10px;">
										<input type="checkbox" name="prioritize_in_stock" id="prioritize_in_stock" value="1"
											<?php checked( $settings['prioritize_in_stock'] ?? true ); ?> class="mantiload-checkbox">
										<span><?php esc_html_e( 'Show in-stock products first', 'mantiload' ); ?></span>
									</label>
									<span class="mantiload-description"><?php esc_html_e( 'Push out-of-stock items to the end regardless of sort order', 'mantiload' ); ?></span>
								</td>
							</tr>

							<tr>
								<th><label for="log_searches"><?php esc_html_e( 'Search Logging', 'mantiload' ); ?></label></th>
								<td>
									<label style="display: flex; align-items: center; gap: 10px;">
										<input type="checkbox" name="log_searches" id="log_searches" value="1"
											<?php checked( $settings['log_searches'] ?? true ); ?> class="mantiload-checkbox">
										<span><?php esc_html_e( 'Log queries for analytics', 'mantiload' ); ?></span>
									</label>
								</td>
							</tr>

							<tr>
								<th><label for="index_batch_size"><?php esc_html_e( 'Batch Size', 'mantiload' ); ?></label></th>
								<td>
									<input type="number" name="index_batch_size" id="index_batch_size"
										value="<?php echo esc_attr( $settings['index_batch_size'] ?? 100 ); ?>"
										min="10" max="500" class="mantiload-input">
									<span class="mantiload-description"><?php esc_html_e( 'Posts per batch (higher = faster, more memory)', 'mantiload' ); ?></span>
								</td>
							</tr>

							<tr>
								<th><label for="index_product_content"><?php esc_html_e( 'Index Product Content', 'mantiload' ); ?></label></th>
								<td>
									<label style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
										<input type="checkbox" name="index_product_content" id="index_product_content" value="1"
											<?php checked( $settings['index_product_content'] ?? false ); ?> class="mantiload-checkbox">
										<span><strong><?php esc_html_e( 'Index product descriptions and excerpts in search', 'mantiload' ); ?></strong></span>
									</label>

									<div class="mantiload-alert mantiload-alert-info">
										<div>
											<strong>🚀 Nuclear Performance Option</strong><br><br>

											<strong>✓ Enabled (Default):</strong><br>
											• Searches product title, SKU, <strong>AND full descriptions</strong><br>
											• Larger index size (~30-50% more storage)<br>
											• Slightly slower searches<br>
											• Use if customers search for detailed product features<br><br>

											<strong>✗ Disabled (Faster):</strong><br>
											• Searches <strong>only</strong> product title + SKU<br>
											• Smaller index (~30-50% less storage)<br>
											• <strong>20-40% faster search queries</strong><br>
											• Recommended for most stores - customers rarely search descriptions<br><br>

											<strong>💡 Tip:</strong> For e-commerce, most searches match product names/SKUs.
											Disabling content indexing gives significantly faster searches with minimal loss of relevance.
											<strong>Requires reindex after changing.</strong>
										</div>
									</div>
								</td>
							</tr>
						</table>
					</div>
				</div>

				<!-- Tab 3: Search Relevance -->
				<div class="mantiload-tab-panel" data-tab="relevance">
					<div class="mantiload-card">
						<h2><i class="dashicons dashicons-performance"></i> <?php esc_html_e( 'Search Relevance Weights', 'mantiload' ); ?></h2>
						<p class="mantiload-description"><?php esc_html_e( 'Control how different fields affect search ranking (higher = more important)', 'mantiload' ); ?></p>

						<div class="mantiload-divider"></div>

						<div class="mantiload-grid">
							<div>
								<label for="weight_title" style="font-weight: 600; display: block; margin-bottom: 8px;">
									<?php esc_html_e( 'Title Weight', 'mantiload' ); ?>
								</label>
								<input type="number" name="weight_title" id="weight_title"
									value="<?php echo esc_attr( $settings['weight_title'] ?? 10 ); ?>"
									min="1" max="100" class="mantiload-input">
							</div>

							<div>
								<label for="weight_sku" style="font-weight: 600; display: block; margin-bottom: 8px;">
									<?php esc_html_e( 'SKU Weight', 'mantiload' ); ?>
								</label>
								<input type="number" name="weight_sku" id="weight_sku"
									value="<?php echo esc_attr( $settings['weight_sku'] ?? 15 ); ?>"
									min="1" max="100" class="mantiload-input">
							</div>

							<div>
								<label for="weight_content" style="font-weight: 600; display: block; margin-bottom: 8px;">
									<?php esc_html_e( 'Content Weight', 'mantiload' ); ?>
								</label>
								<input type="number" name="weight_content" id="weight_content"
									value="<?php echo esc_attr( $settings['weight_content'] ?? 5 ); ?>"
									min="1" max="100" class="mantiload-input">
							</div>

							<div>
								<label for="weight_categories" style="font-weight: 600; display: block; margin-bottom: 8px;">
									<?php esc_html_e( 'Categories Weight', 'mantiload' ); ?>
								</label>
								<input type="number" name="weight_categories" id="weight_categories"
									value="<?php echo esc_attr( $settings['weight_categories'] ?? 3 ); ?>"
									min="1" max="100" class="mantiload-input">
							</div>

							<div>
								<label for="weight_tags" style="font-weight: 600; display: block; margin-bottom: 8px;">
									<?php esc_html_e( 'Tags Weight', 'mantiload' ); ?>
								</label>
								<input type="number" name="weight_tags" id="weight_tags"
									value="<?php echo esc_attr( $settings['weight_tags'] ?? 2 ); ?>"
									min="1" max="100" class="mantiload-input">
							</div>

							<div>
								<label for="weight_attributes" style="font-weight: 600; display: block; margin-bottom: 8px;">
									<?php esc_html_e( 'Attributes Weight', 'mantiload' ); ?>
								</label>
								<input type="number" name="weight_attributes" id="weight_attributes"
									value="<?php echo esc_attr( $settings['weight_attributes'] ?? 4 ); ?>"
									min="1" max="100" class="mantiload-input">
							</div>
						</div>

						<div class="mantiload-divider"></div>

						<table class="mantiload-form-table">
							<tr>
								<th><label for="excluded_attributes"><?php esc_html_e( 'Exclude Attributes from Search', 'mantiload' ); ?></label></th>
								<td>
									<?php
									// Get all product attributes
									$mantiload_attribute_taxonomies = wc_get_attribute_taxonomies();
									$mantiload_current_excluded = isset( $settings['excluded_attributes'] ) ? explode( ',', $settings['excluded_attributes'] ) : array();
									$mantiload_current_excluded = array_map( 'trim', $mantiload_current_excluded );
									?>

									<select name="excluded_attributes[]" id="excluded_attributes" multiple="multiple"
										class="mantiload-select2" style="width: 100%; max-width: 500px;">
										<?php if ( ! empty( $mantiload_attribute_taxonomies ) ) : ?>
											<?php foreach ( $mantiload_attribute_taxonomies as $mantiload_attribute ) : ?>
												<?php
												$mantiload_taxonomy_name = wc_attribute_taxonomy_name( $mantiload_attribute->attribute_name );
												$mantiload_is_selected = in_array( $mantiload_taxonomy_name, $mantiload_current_excluded, true );
												?>
												<option value="<?php echo esc_attr( $mantiload_taxonomy_name ); ?>"
													<?php selected( $mantiload_is_selected ); ?>>
													<?php echo esc_html( $mantiload_attribute->attribute_label ); ?>
													(<?php echo esc_html( $mantiload_taxonomy_name ); ?>)
												</option>
											<?php endforeach; ?>
										<?php else : ?>
											<option value="" disabled><?php esc_html_e( 'No product attributes found', 'mantiload' ); ?></option>
										<?php endif; ?>
									</select>

									<p class="mantiload-help-text">
										<?php esc_html_e( 'Select attributes to exclude from search results. These attributes will still be indexed for filters but won\'t affect search relevance.', 'mantiload' ); ?>
									</p>
									<div class="mantiload-alert mantiload-alert-info" style="margin-top: 12px;">
										<div>
											<strong><?php esc_html_e( 'Use Case:', 'mantiload' ); ?></strong><br>
											<?php esc_html_e( 'Exclude attributes like "color" or "size" if you don\'t want customers searching for "red" or "large" to get results based on those attributes. Perfect for stores where attribute values might conflict with product names.', 'mantiload' ); ?>
										</div>
									</div>
								</td>
							</tr>
						</table>
					</div>
				</div>

				<!-- Tab 4: AJAX Search -->
				<div class="mantiload-tab-panel" data-tab="ajax">
					<div class="mantiload-card">
						<h2><i class="dashicons dashicons-bolt"></i> <?php esc_html_e( 'AJAX Search Settings', 'mantiload' ); ?></h2>

						<table class="mantiload-form-table">
							<tr>
								<th><label for="search_delay"><?php esc_html_e( 'Search Delay', 'mantiload' ); ?></label></th>
								<td>
									<input type="number" name="search_delay" id="search_delay"
										value="<?php echo esc_attr( $settings['search_delay'] ?? 300 ); ?>"
										min="0" max="1000" step="50" class="mantiload-input">
									<span class="mantiload-description"><?php esc_html_e( 'Milliseconds to wait before searching (debounce)', 'mantiload' ); ?></span>
								</td>
							</tr>

							<tr>
								<th><label for="min_chars"><?php esc_html_e( 'Min Characters', 'mantiload' ); ?></label></th>
								<td>
									<input type="number" name="min_chars" id="min_chars"
										value="<?php echo esc_attr( $settings['min_chars'] ?? 2 ); ?>"
										min="1" max="5" class="mantiload-input">
									<span class="mantiload-description"><?php esc_html_e( 'Minimum characters to trigger search', 'mantiload' ); ?></span>
								</td>
							</tr>

							<tr>
								<th><label for="max_results"><?php esc_html_e( 'Max Results', 'mantiload' ); ?></label></th>
								<td>
									<input type="number" name="max_results" id="max_results"
										value="<?php echo esc_attr( $settings['max_results'] ?? 10 ); ?>"
										min="5" max="50" class="mantiload-input">
									<span class="mantiload-description"><?php esc_html_e( 'Maximum results in dropdown', 'mantiload' ); ?></span>
								</td>
							</tr>

							<tr>
								<th><label for="show_categories_in_search"><?php esc_html_e( 'Show Categories', 'mantiload' ); ?></label></th>
								<td>
									<label style="display: flex; align-items: center; gap: 10px;">
										<input type="checkbox" name="show_categories_in_search" id="show_categories_in_search" value="1"
											<?php checked( $settings['show_categories_in_search'] ?? true ); ?> class="mantiload-checkbox">
										<span><?php esc_html_e( 'Show matching categories in search dropdown', 'mantiload' ); ?></span>
									</label>
									<span class="mantiload-description"><?php esc_html_e( 'Display product categories and tags above search results (like Amazon)', 'mantiload' ); ?></span>
								</td>
							</tr>

							<tr>
								<th><label for="max_categories"><?php esc_html_e( 'Max Categories', 'mantiload' ); ?></label></th>
								<td>
									<input type="number" name="max_categories" id="max_categories"
										value="<?php echo esc_attr( $settings['max_categories'] ?? 5 ); ?>"
										min="1" max="20" class="mantiload-input">
									<span class="mantiload-description"><?php esc_html_e( 'Maximum categories to show in dropdown', 'mantiload' ); ?></span>
								</td>
							</tr>
						</table>
					</div>
				</div>

				<!-- Tab 5: Performance & Caching -->
				<div class="mantiload-tab-panel" data-tab="performance">
					<div class="mantiload-card">
						<h2><i class="dashicons dashicons-dashboard"></i> <?php esc_html_e( 'Performance & Caching', 'mantiload' ); ?></h2>
						<p class="mantiload-description"><?php esc_html_e( 'Redis-powered caching for lightning-fast category pages', 'mantiload' ); ?></p>

						<div class="mantiload-divider"></div>

						<table class="mantiload-form-table">
							<tr>
								<th><label for="enable_redis_cache"><?php esc_html_e( 'Redis Query Cache', 'mantiload' ); ?></label></th>
								<td>
									<label style="display: flex; align-items: center; gap: 10px;">
										<input type="checkbox" name="enable_redis_cache" id="enable_redis_cache" value="1"
											<?php checked( $settings['enable_redis_cache'] ?? true ); ?> class="mantiload-checkbox">
										<span><strong><?php esc_html_e( 'Enable category query caching', 'mantiload' ); ?></strong></span>
									</label>
									<div class="mantiload-alert mantiload-alert-success" style="margin-top: 12px;">
										<div>
											<strong><?php esc_html_e( '433x Faster Category Pages!', 'mantiload' ); ?></strong><br>
											<?php esc_html_e( 'Caches category product queries in Redis. Reduces query time from ~130ms to ~0.3ms. Automatically invalidates when products are updated.', 'mantiload' ); ?>
										</div>
									</div>
									<span class="mantiload-description" style="margin-top: 8px;">
										<?php
										// Check Redis status
										try {
											$mantiload_redis = new \Redis();
											$mantiload_connected = $mantiload_redis->connect( '127.0.0.1', 6379, 1 );
											if ( $mantiload_connected ) {
												echo '<span style="color: #10b981;">● ' . esc_html__( 'Redis connected', 'mantiload' ) . '</span>';
											} else {
												echo '<span style="color: #ef4444;">● ' . esc_html__( 'Redis not available', 'mantiload' ) . '</span>';
											}
										} catch ( \Exception $e ) {
											echo '<span style="color: #ef4444;">● ' . esc_html__( 'Redis not available', 'mantiload' ) . '</span>';
										}
										?>
									</span>
								</td>
							</tr>

							<tr>
								<th><label for="enable_query_interception"><?php esc_html_e( 'Auto Query Interception', 'mantiload' ); ?></label></th>
								<td>
									<label style="display: flex; align-items: center; gap: 10px;">
										<input type="checkbox" name="enable_query_interception" id="enable_query_interception" value="1"
											<?php checked( $settings['enable_query_interception'] ?? false ); ?> class="mantiload-checkbox">
										<span><strong><?php esc_html_e( 'Automatically intercept product archive queries', 'mantiload' ); ?></strong></span>
									</label>
									<div class="mantiload-alert mantiload-alert-info" style="margin-top: 12px;">
										<div>
											<strong><?php esc_html_e( 'Drop-In ElasticPress-Style Integration!', 'mantiload' ); ?></strong><br>
											<?php esc_html_e( 'Automatically routes WooCommerce product category, tag, and shop pages to Manticore. No theme modifications needed! Gracefully falls back to MySQL on errors.', 'mantiload' ); ?>
										</div>
									</div>
									<span class="mantiload-description" style="margin-top: 8px; display: block;">
										<strong><?php esc_html_e( 'How it works:', 'mantiload' ); ?></strong><br>
										• <?php esc_html_e( 'Intercepts queries using WordPress posts_pre_query hook', 'mantiload' ); ?><br>
										• <?php esc_html_e( 'Only affects main product archive queries (shop, categories, tags)', 'mantiload' ); ?><br>
										• <?php esc_html_e( 'Works alongside Redis cache for maximum performance', 'mantiload' ); ?><br>
										• <?php esc_html_e( 'If disabled, you can still use manual template integration', 'mantiload' ); ?>
									</span>
								</td>
							</tr>

							<tr>
								<th><label for="enable_woocommerce_filter_integration"><?php esc_html_e( 'WooCommerce Filter Integration', 'mantiload' ); ?></label></th>
								<td>
									<label style="display: flex; align-items: center; gap: 10px;">
										<input type="checkbox" name="enable_woocommerce_filter_integration" id="enable_woocommerce_filter_integration" value="1"
											<?php checked( $settings['enable_woocommerce_filter_integration'] ?? true ); ?> class="mantiload-checkbox">
										<span><strong><?php esc_html_e( 'Turbocharge layered nav filters', 'mantiload' ); ?></strong></span>
									</label>
									<div class="mantiload-alert mantiload-alert-success" style="margin-top: 12px;">
										<div>
											<?php esc_html_e( 'Use Manticore to speed up WooCommerce & WoodMart layered nav filters (color, size, attributes, etc.). Works with existing filter widgets!', 'mantiload' ); ?>
										</div>
									</div>
								</td>
							</tr>
						</table>
					</div>

					<!-- Turbo Mode Section -->
					<div class="mantiload-card" style="margin-top: 20px;">
						<h2><i class="dashicons dashicons-superhero"></i> <?php esc_html_e( 'Turbo Mode', 'mantiload' ); ?></h2>
						<p class="mantiload-description"><?php esc_html_e( 'Disable unnecessary plugins during AJAX search for 5-10x faster responses', 'mantiload' ); ?></p>

						<div class="mantiload-divider"></div>

						<table class="mantiload-form-table">
							<tr>
								<th><label for="enable_turbo_mode"><?php esc_html_e( 'Enable Turbo Mode', 'mantiload' ); ?></label></th>
								<td>
									<label style="display: flex; align-items: center; gap: 10px;">
										<input type="checkbox" name="enable_turbo_mode" id="enable_turbo_mode" value="1"
											<?php checked( $settings['enable_turbo_mode'] ?? false ); ?> class="mantiload-checkbox">
										<span><strong><?php esc_html_e( 'Selective plugin loading for AJAX search', 'mantiload' ); ?></strong></span>
									</label>
									<div class="mantiload-alert mantiload-alert-warning" style="margin-top: 12px;">
										<div>
											<strong><?php esc_html_e( '🚀 5-10x Faster Search!', 'mantiload' ); ?></strong><br>
											<?php esc_html_e( 'Only loads essential plugins during search requests. Other plugins are temporarily disabled for that request only.', 'mantiload' ); ?>
										</div>
									</div>
									<?php
									// Check if mu-plugin exists
									$mantiload_mu_plugin_path = WPMU_PLUGIN_DIR . '/mantiload-turbo.php';
									if ( ! file_exists( $mantiload_mu_plugin_path ) ) {
										echo '<div class="mantiload-alert mantiload-alert-error" style="margin-top: 12px;">';
										echo '<div><strong>' . esc_html__( 'MU-Plugin Required', 'mantiload' ) . '</strong><br>';
										echo esc_html__( 'Copy mantiload-turbo.php to wp-content/mu-plugins/ for Turbo Mode to work.', 'mantiload' );
										echo '</div></div>';
									}
									?>
								</td>
							</tr>

							<tr id="turbo_actions_row" style="<?php echo empty( $settings['enable_turbo_mode'] ) ? 'display: none;' : ''; ?>">
								<th><label><?php esc_html_e( 'Apply Turbo Mode To', 'mantiload' ); ?></label></th>
								<td>
									<?php
									$mantiload_turbo_action_groups = $settings['turbo_action_groups'] ?? array( 'frontend_search' );
									?>
									<div style="display: flex; flex-direction: column; gap: 12px;">
										<label style="display: flex; align-items: flex-start; gap: 10px;">
											<input type="checkbox" name="turbo_action_groups[]" value="frontend_search"
												<?php checked( in_array( 'frontend_search', $mantiload_turbo_action_groups, true ) ); ?> class="mantiload-checkbox">
											<div>
												<strong><?php esc_html_e( 'Frontend Search', 'mantiload' ); ?></strong>
												<span style="color: #6b7280; display: block; font-size: 12px;">
													<?php esc_html_e( 'AJAX search box, instant search dropdown', 'mantiload' ); ?>
												</span>
											</div>
										</label>
										<label style="display: flex; align-items: flex-start; gap: 10px;">
											<input type="checkbox" name="turbo_action_groups[]" value="frontend_filters"
												<?php checked( in_array( 'frontend_filters', $mantiload_turbo_action_groups, true ) ); ?> class="mantiload-checkbox">
											<div>
												<strong><?php esc_html_e( 'Frontend Filters', 'mantiload' ); ?></strong>
												<span style="color: #6b7280; display: block; font-size: 12px;">
													<?php esc_html_e( 'Product filtering, layered navigation, load more', 'mantiload' ); ?>
												</span>
											</div>
										</label>
										<label style="display: flex; align-items: flex-start; gap: 10px;">
											<input type="checkbox" name="turbo_action_groups[]" value="admin_search"
												<?php checked( in_array( 'admin_search', $mantiload_turbo_action_groups, true ) ); ?> class="mantiload-checkbox">
											<div>
												<strong><?php esc_html_e( 'Admin Search (Cmd/Ctrl+K)', 'mantiload' ); ?></strong>
												<span style="color: #6b7280; display: block; font-size: 12px;">
													<?php esc_html_e( 'Quick search in WordPress admin', 'mantiload' ); ?>
												</span>
											</div>
										</label>
									</div>
									<p class="mantiload-description" style="margin-top: 10px;">
										<?php esc_html_e( 'Select which MantiLoad actions should use Turbo Mode. Frontend Search is recommended for most sites.', 'mantiload' ); ?>
									</p>
								</td>
							</tr>

							<tr id="turbo_plugins_row" style="<?php echo empty( $settings['enable_turbo_mode'] ) ? 'display: none;' : ''; ?>">
								<th><label><?php esc_html_e( 'Additional Plugins to Keep', 'mantiload' ); ?></label></th>
								<td>
									<p class="mantiload-description" style="margin-bottom: 12px;">
										<?php esc_html_e( 'MantiLoad and WooCommerce are always kept. Select any additional plugins needed for search:', 'mantiload' ); ?>
									</p>
									<div style="max-height: 300px; overflow-y: auto; border: 1px solid #ddd; border-radius: 8px; padding: 15px; background: #f9f9f9;">
										<?php
										$mantiload_all_plugins = get_plugins();
										$mantiload_keep_plugins = $settings['turbo_keep_plugins'] ?? array();

										// Categorize plugins
										$mantiload_essential = array( 'mantiload/mantiload.php', 'woocommerce/woocommerce.php' );
										$mantiload_recommended = array();
										$mantiload_optional = array();

										foreach ( $mantiload_all_plugins as $mantiload_plugin_file => $mantiload_plugin_data ) {
											// Skip essential plugins (shown separately)
											if ( in_array( $mantiload_plugin_file, $mantiload_essential, true ) ) {
												continue;
											}

											// Categorize based on plugin name/type
											$mantiload_name_lower = strtolower( $mantiload_plugin_data['Name'] );
											if ( strpos( $mantiload_name_lower, 'multilingual' ) !== false ||
												 strpos( $mantiload_name_lower, 'wpml' ) !== false ||
												 strpos( $mantiload_name_lower, 'polylang' ) !== false ||
												 strpos( $mantiload_name_lower, 'currency' ) !== false ||
												 strpos( $mantiload_name_lower, 'membership' ) !== false ) {
												$mantiload_recommended[] = array( 'file' => $mantiload_plugin_file, 'data' => $mantiload_plugin_data );
											} else {
												$mantiload_optional[] = array( 'file' => $mantiload_plugin_file, 'data' => $mantiload_plugin_data );
											}
										}
										?>

										<!-- Essential plugins (always kept) -->
										<div style="margin-bottom: 15px;">
											<strong style="color: #10b981;"><?php esc_html_e( '✓ Always Loaded:', 'mantiload' ); ?></strong>
											<div style="margin-top: 8px; padding-left: 10px; color: #666;">
												• MantiLoad<br>
												• WooCommerce
											</div>
										</div>

										<?php if ( ! empty( $mantiload_recommended ) ) : ?>
										<!-- Recommended to keep -->
										<div style="margin-bottom: 15px;">
											<strong style="color: #f59e0b;"><?php esc_html_e( '⚠ Recommended to Keep:', 'mantiload' ); ?></strong>
											<div style="margin-top: 8px;">
												<?php foreach ( $mantiload_recommended as $plugin ) : ?>
													<label style="display: block; padding: 5px 0; padding-left: 10px;">
														<input type="checkbox" name="turbo_keep_plugins[]"
															value="<?php echo esc_attr( $plugin['file'] ); ?>"
															<?php checked( in_array( $plugin['file'], $mantiload_keep_plugins, true ) ); ?>>
														<?php echo esc_html( $plugin['data']['Name'] ); ?>
													</label>
												<?php endforeach; ?>
											</div>
										</div>
										<?php endif; ?>

										<!-- Optional plugins -->
										<div>
											<strong><?php esc_html_e( 'Other Plugins:', 'mantiload' ); ?></strong>
											<div style="margin-top: 8px;">
												<?php foreach ( $mantiload_optional as $plugin ) : ?>
													<label style="display: block; padding: 5px 0; padding-left: 10px;">
														<input type="checkbox" name="turbo_keep_plugins[]"
															value="<?php echo esc_attr( $plugin['file'] ); ?>"
															<?php checked( in_array( $plugin['file'], $mantiload_keep_plugins, true ) ); ?>>
														<?php echo esc_html( $plugin['data']['Name'] ); ?>
													</label>
												<?php endforeach; ?>
											</div>
										</div>
									</div>
									<p class="mantiload-description" style="margin-top: 10px;">
										<strong><?php esc_html_e( 'Tip:', 'mantiload' ); ?></strong>
										<?php esc_html_e( 'Start with none selected. If search breaks, add plugins one by one to find which is needed.', 'mantiload' ); ?>
									</p>
								</td>
							</tr>
						</table>
					</div>

					<script>
					jQuery(document).ready(function($) {
						$('#enable_turbo_mode').on('change', function() {
							if ($(this).is(':checked')) {
								$('#turbo_actions_row').slideDown();
								$('#turbo_plugins_row').slideDown();
							} else {
								$('#turbo_actions_row').slideUp();
								$('#turbo_plugins_row').slideUp();
							}
						});
					});
					</script>
				</div>

				<!-- Tab 6: Admin Features -->
				<div class="mantiload-tab-panel" data-tab="admin">
					<div class="mantiload-card">
						<h2><i class="dashicons dashicons-performance"></i> <?php esc_html_e( 'Admin Superpowers', 'mantiload' ); ?></h2>
						<p class="mantiload-description"><?php esc_html_e( 'Revolutionary features that make WordPress admin blazing fast', 'mantiload' ); ?></p>

						<div class="mantiload-divider"></div>

						<table class="mantiload-form-table">
							<tr>
								<th><label for="enable_admin_search"><?php esc_html_e( 'Admin Search (Cmd/Ctrl+K)', 'mantiload' ); ?></label></th>
								<td>
									<label style="display: flex; align-items: center; gap: 10px;">
										<input type="checkbox" name="enable_admin_search" id="enable_admin_search" value="1"
											<?php checked( $settings['enable_admin_search'] ?? true ); ?> class="mantiload-checkbox">
										<span><strong><?php esc_html_e( 'Enable lightning-fast admin search', 'mantiload' ); ?></strong></span>
									</label>
									<div class="mantiload-alert mantiload-alert-info" style="margin-top: 12px;">
										<div>
											<strong><?php esc_html_e( 'The FIRST EVER WordPress Admin Search!', 'mantiload' ); ?></strong><br>
											<?php esc_html_e( 'Press Cmd+K (Mac) or Ctrl+K (Windows) to instantly search products, orders, and posts. Sub-50ms response times!', 'mantiload' ); ?>
										</div>
									</div>
								</td>
							</tr>

							<tr>
								<th><label for="index_orders_customers"><?php esc_html_e( 'Index Orders & Customers', 'mantiload' ); ?></label></th>
								<td>
									<label style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
										<input type="checkbox" name="index_orders_customers" id="index_orders_customers" value="1"
											<?php checked( $settings['index_orders_customers'] ?? false ); ?> class="mantiload-checkbox">
										<span><strong><?php esc_html_e( 'Enable fast order & customer search in admin', 'mantiload' ); ?></strong></span>
									</label>
									<div class="mantiload-alert mantiload-alert-success" style="margin-top: 12px;">
										<div>
											<strong><?php esc_html_e( 'Game Changer!', 'mantiload' ); ?></strong><br>
											<?php esc_html_e( 'Index WooCommerce orders and customers for lightning-fast admin search. Find any order by number, customer name, email, or phone instantly (5-15ms vs 500-2000ms with WordPress). Press Cmd+K/Ctrl+K and search for orders #12345 or customer email!', 'mantiload' ); ?>
										</div>
									</div>
									<div class="mantiload-alert mantiload-alert-warning" style="margin-top: 12px;">
										<div>
											<strong><?php esc_html_e( '⚠️ Important:', 'mantiload' ); ?></strong> <?php esc_html_e( 'After enabling this, you must rebuild the index to add orders and customers to Manticore.', 'mantiload' ); ?>
										</div>
									</div>
								</td>
							</tr>

							<tr>
								<th><label for="enable_admin_product_search_optimization"><?php esc_html_e( 'Product List Optimization', 'mantiload' ); ?></label></th>
								<td>
									<label style="display: flex; align-items: center; gap: 10px;">
										<input type="checkbox" name="enable_admin_product_search_optimization" id="enable_admin_product_search_optimization" value="1"
											<?php checked( $settings['enable_admin_product_search_optimization'] ?? true ); ?> class="mantiload-checkbox">
										<span><strong><?php esc_html_e( 'Optimize product admin search', 'mantiload' ); ?></strong></span>
									</label>
									<div class="mantiload-alert mantiload-alert-success" style="margin-top: 12px;">
										<div>
											<?php esc_html_e( 'Replace slow LIKE queries (300-500ms) with instant Manticore search (5-30ms) in Products > All Products', 'mantiload' ); ?>
										</div>
									</div>
								</td>
							</tr>


						</table>
					</div>
				</div>

				<!-- Tab 7: Related Products -->
				<div class="mantiload-tab-panel" data-tab="related">
					<div class="mantiload-card">
						<h2><i class="dashicons dashicons-star-filled"></i> <?php esc_html_e( 'Smart Related Products', 'mantiload' ); ?></h2>
						<p class="mantiload-description"><?php esc_html_e( 'Lightning-fast, intelligent related products that understand attributes, categories, and price ranges', 'mantiload' ); ?></p>

						<div class="mantiload-divider"></div>

						<table class="mantiload-form-table">
							<tr>
								<th><label for="enable_related_products"><?php esc_html_e( 'Enable Related Products', 'mantiload' ); ?></label></th>
								<td>
									<label style="display: flex; align-items: center; gap: 10px;">
										<input type="checkbox" name="enable_related_products" id="enable_related_products" value="1"
											<?php checked( $settings['enable_related_products'] ?? false ); ?> class="mantiload-checkbox">
										<span><strong><?php esc_html_e( 'Replace WooCommerce related products with MantiLoad', 'mantiload' ); ?></strong></span>
									</label>
									<div class="mantiload-alert mantiload-alert-success" style="margin-top: 12px;">
										<div>
											<strong><?php esc_html_e( 'Performance:', 'mantiload' ); ?></strong> <?php esc_html_e( '20-30x faster than WooCommerce default (2-5ms vs 50-150ms)', 'mantiload' ); ?><br>
											<strong><?php esc_html_e( 'Intelligence:', 'mantiload' ); ?></strong> <?php esc_html_e( 'Matches product attributes, categories, price range, and more', 'mantiload' ); ?><br>
											<strong><?php esc_html_e( 'Compatibility:', 'mantiload' ); ?></strong> <?php esc_html_e( 'Works automatically with all themes, Elementor, Divi, and page builders', 'mantiload' ); ?>
										</div>
									</div>
								</td>
							</tr>

							<tr>
								<th><label for="related_products_algorithm"><?php esc_html_e( 'Matching Algorithm', 'mantiload' ); ?></label></th>
								<td>
									<select name="related_products_algorithm" id="related_products_algorithm" class="mantiload-select" style="width: 100%; max-width: 400px;">
										<option value="combo" <?php selected( $settings['related_products_algorithm'] ?? 'combo', 'combo' ); ?>>
											<?php esc_html_e( 'Combo (Attributes + Categories + Price) - Recommended', 'mantiload' ); ?>
										</option>
										<option value="attributes_categories" <?php selected( $settings['related_products_algorithm'] ?? 'combo', 'attributes_categories' ); ?>>
											<?php esc_html_e( 'Attributes & Categories (Ignores Price)', 'mantiload' ); ?>
										</option>
										<option value="price_categories" <?php selected( $settings['related_products_algorithm'] ?? 'combo', 'price_categories' ); ?>>
											<?php esc_html_e( 'Price & Categories (Ignores Attributes)', 'mantiload' ); ?>
										</option>
										<option value="fabric_category" <?php selected( $settings['related_products_algorithm'] ?? 'combo', 'fabric_category' ); ?>>
											<?php esc_html_e( 'Fabric + Category - Perfect for Fashion', 'mantiload' ); ?>
										</option>
									</select>
									<p class="mantiload-description" style="margin-top: 8px;">
										<strong><?php esc_html_e( 'Combo:', 'mantiload' ); ?></strong> <?php esc_html_e( 'Finds products with similar attributes (color, brand, size), same category, and similar price (±30%). Most intelligent!', 'mantiload' ); ?><br>
										<strong><?php esc_html_e( 'Attributes & Categories:', 'mantiload' ); ?></strong> <?php esc_html_e( 'Focuses on product characteristics. Great for fashion/apparel stores.', 'mantiload' ); ?><br>
										<strong><?php esc_html_e( 'Price & Categories:', 'mantiload' ); ?></strong> <?php esc_html_e( 'Finds alternatives at similar price point. Perfect for competitive products.', 'mantiload' ); ?><br>
										<strong><?php esc_html_e( 'Fabric + Category:', 'mantiload' ); ?></strong> <?php esc_html_e( 'Prioritizes fabric attribute matching (satin, lace, chiffon, etc.) within same category. Custom algorithm for fashion stores like Jovani.', 'mantiload' ); ?>
									</p>
								</td>
							</tr>

							<tr>
								<th><label for="related_products_limit"><?php esc_html_e( 'Number of Products', 'mantiload' ); ?></label></th>
								<td>
									<input type="number" name="related_products_limit" id="related_products_limit"
										value="<?php echo esc_attr( $settings['related_products_limit'] ?? 10 ); ?>"
										min="3" max="50" class="mantiload-input" style="width: 100px;">
									<span class="mantiload-description"><?php esc_html_e( 'Number of related products to show (if your theme supports it)', 'mantiload' ); ?></span>
								</td>
							</tr>
						</table>

						<div class="mantiload-alert mantiload-alert-info" style="margin-top: 20px;">
							<div>
								<strong><?php esc_html_e( 'How it works:', 'mantiload' ); ?></strong><br>
								<?php esc_html_e( 'MantiLoad automatically hooks into WooCommerce\'s related products system. No code changes needed! Works with:', 'mantiload' ); ?>
								<ul style="margin: 8px 0 0; padding-left: 20px;">
									<li><?php esc_html_e( 'All WooCommerce themes (WoodMart, Flatsome, Storefront, Astra, etc.)', 'mantiload' ); ?></li>
									<li><?php esc_html_e( 'Elementor Pro, Divi, WPBakery, Beaver Builder', 'mantiload' ); ?></li>
									<li><?php esc_html_e( 'WooCommerce blocks (Gutenberg)', 'mantiload' ); ?></li>
									<li><?php esc_html_e( 'Any plugin using woocommerce_related_products()', 'mantiload' ); ?></li>
								</ul>
							</div>
						</div>
					</div>
				</div>

				<!-- Tab 8: Integration (Shortcodes & Custom CSS) -->
				<div class="mantiload-tab-panel" data-tab="integration">
					<!-- Shortcode Documentation Card -->
					<div class="mantiload-card">
						<h2><i class="dashicons dashicons-editor-code"></i> <?php esc_html_e( 'Shortcodes for Header Builders', 'mantiload' ); ?></h2>
						<p class="mantiload-description"><?php esc_html_e( 'Use these shortcodes in Elementor, WPBakery, WoodMart Header Builder, or any page builder', 'mantiload' ); ?></p>

						<div class="mantiload-divider"></div>

						<!-- Search Icon Shortcode -->
						<div style="margin-bottom: 30px;">
							<h3 style="font-size: 15px; font-weight: 600; margin-bottom: 12px;">
								<?php esc_html_e( '1. Search Icon (Mobile Modal Style)', 'mantiload' ); ?>
							</h3>
							<p style="color: #6b7280; font-size: 13px; margin-bottom: 12px;">
								<?php esc_html_e( 'Displays a search icon that opens a full-screen modal. Perfect for headers!', 'mantiload' ); ?>
							</p>

							<div class="mantiload-code-block">
								<code>[mantiload_search_icon]</code>
							</div>

							<div style="margin-top: 16px;">
								<h4 style="font-size: 13px; font-weight: 600; margin-bottom: 8px; color: #374151;">
									<?php esc_html_e( 'Available Parameters:', 'mantiload' ); ?>
								</h4>
								<table class="mantiload-params-table">
									<tr>
										<td><code>size</code></td>
										<td><?php esc_html_e( 'Icon size: small, medium, large', 'mantiload' ); ?></td>
										<td><code>medium</code></td>
									</tr>
									<tr>
										<td><code>style</code></td>
										<td><?php esc_html_e( 'Icon style: default, circle, rounded', 'mantiload' ); ?></td>
										<td><code>default</code></td>
									</tr>
									<tr>
										<td><code>label</code></td>
										<td><?php esc_html_e( 'Custom label text', 'mantiload' ); ?></td>
										<td><code>Search</code></td>
									</tr>
									<tr>
										<td><code>show_label</code></td>
										<td><?php esc_html_e( 'Show label next to icon: true/false', 'mantiload' ); ?></td>
										<td><code>false</code></td>
									</tr>
									<tr>
										<td><code>show_price</code></td>
										<td><?php esc_html_e( 'Show product prices in results: true/false', 'mantiload' ); ?></td>
										<td><code>true</code></td>
									</tr>
									<tr>
										<td><code>show_stock</code></td>
										<td><?php esc_html_e( 'Show stock status in results: true/false', 'mantiload' ); ?></td>
										<td><code>true</code></td>
									</tr>
									<tr>
										<td><code>class</code></td>
										<td><?php esc_html_e( 'Custom CSS class', 'mantiload' ); ?></td>
										<td><code>-</code></td>
									</tr>
								</table>
							</div>

							<div style="margin-top: 16px;">
								<h4 style="font-size: 13px; font-weight: 600; margin-bottom: 8px; color: #374151;">
									<?php esc_html_e( 'Examples:', 'mantiload' ); ?>
								</h4>
								<div class="mantiload-code-block">
									<code>[mantiload_search_icon size="large" style="circle"]</code>
								</div>
								<div class="mantiload-code-block" style="margin-top: 8px;">
									<code>[mantiload_search_icon size="medium" style="rounded" show_label="true" label="Find Products"]</code>
								</div>
							</div>
						</div>

						<!-- Full Search Box Shortcode -->
						<div style="margin-bottom: 20px;">
							<h3 style="font-size: 15px; font-weight: 600; margin-bottom: 12px;">
								<?php esc_html_e( '2. Full Search Box', 'mantiload' ); ?>
							</h3>
							<p style="color: #6b7280; font-size: 13px; margin-bottom: 12px;">
								<?php esc_html_e( 'Displays a complete search box with autocomplete dropdown. Great for search pages or sidebars!', 'mantiload' ); ?>
							</p>

							<div class="mantiload-code-block">
								<code>[mantiload_search]</code>
							</div>

							<div style="margin-top: 16px;">
								<h4 style="font-size: 13px; font-weight: 600; margin-bottom: 8px; color: #374151;">
									<?php esc_html_e( 'Available Parameters:', 'mantiload' ); ?>
								</h4>
								<table class="mantiload-params-table">
									<tr>
										<td><code>placeholder</code></td>
										<td><?php esc_html_e( 'Placeholder text', 'mantiload' ); ?></td>
										<td><code>Search products...</code></td>
									</tr>
									<tr>
										<td><code>post_types</code></td>
										<td><?php esc_html_e( 'Post types to search (comma-separated)', 'mantiload' ); ?></td>
										<td><code>product</code></td>
									</tr>
									<tr>
										<td><code>show_button</code></td>
										<td><?php esc_html_e( 'Show search button: true/false', 'mantiload' ); ?></td>
										<td><code>true</code></td>
									</tr>
									<tr>
										<td><code>button_text</code></td>
										<td><?php esc_html_e( 'Search button text', 'mantiload' ); ?></td>
										<td><code>Search</code></td>
									</tr>
									<tr>
										<td><code>button_icon</code></td>
										<td><?php esc_html_e( 'Show icon instead of text on button: true/false', 'mantiload' ); ?></td>
										<td><code>false</code></td>
									</tr>
									<tr>
										<td><code>hide_clear</code></td>
										<td><?php esc_html_e( 'Hide clear (X) button: true/false', 'mantiload' ); ?></td>
										<td><code>false</code></td>
									</tr>
									<tr>
										<td><code>view_all_text</code></td>
										<td><?php esc_html_e( '"View all" link text', 'mantiload' ); ?></td>
										<td><code>View all results</code></td>
									</tr>
									<tr>
										<td><code>width</code></td>
										<td><?php esc_html_e( 'Search box width (px, %, em, rem, vw)', 'mantiload' ); ?></td>
										<td><code>100%</code></td>
									</tr>
									<tr>
										<td><code>show_price</code></td>
										<td><?php esc_html_e( 'Show product prices: true/false', 'mantiload' ); ?></td>
										<td><code>true</code></td>
									</tr>
									<tr>
										<td><code>show_stock</code></td>
										<td><?php esc_html_e( 'Show stock status: true/false', 'mantiload' ); ?></td>
										<td><code>true</code></td>
									</tr>
									<tr>
										<td><code>class</code></td>
										<td><?php esc_html_e( 'Custom CSS class', 'mantiload' ); ?></td>
										<td><code>-</code></td>
									</tr>
								</table>
							</div>

							<div style="margin-top: 16px;">
								<h4 style="font-size: 13px; font-weight: 600; margin-bottom: 8px; color: #374151;">
									<?php esc_html_e( 'Examples:', 'mantiload' ); ?>
								</h4>
								<div class="mantiload-code-block">
									<code>[mantiload_search width="400px" placeholder="Search our store..."]</code>
								</div>
								<div class="mantiload-code-block" style="margin-top: 8px;">
									<code>[mantiload_search show_button="false" width="100%" post_types="product,post"]</code>
								</div>
								<div class="mantiload-code-block" style="margin-top: 8px;">
									<code>[mantiload_search button_icon="true" hide_clear="true"]</code>
								</div>
							</div>
						</div>

						<!-- Usage Tips -->
						<div class="mantiload-alert mantiload-alert-info" style="margin-top: 20px;">
							<div>
								<strong><?php esc_html_e( 'Usage Tips:', 'mantiload' ); ?></strong><br>
								<ul style="margin: 8px 0 0; padding-left: 20px;">
									<li><?php esc_html_e( 'For WoodMart Header Builder: Use "HTML Block" element and paste shortcode', 'mantiload' ); ?></li>
									<li><?php esc_html_e( 'For Elementor: Use "Shortcode" widget', 'mantiload' ); ?></li>
									<li><?php esc_html_e( 'For PHP templates: <?php echo do_shortcode(\'[mantiload_search_icon]\'); ?>', 'mantiload' ); ?></li>
									<li><?php esc_html_e( 'Recommended for headers: [mantiload_search_icon] for mobile-friendly experience', 'mantiload' ); ?></li>
								</ul>
							</div>
						</div>
					</div>

					<!-- Custom CSS Card -->
					<div class="mantiload-card">
						<h2><i class="dashicons dashicons-editor-code"></i> <?php esc_html_e( 'Custom CSS', 'mantiload' ); ?></h2>

						<table class="mantiload-form-table">
							<tr>
								<th style="vertical-align: top; padding-top: 12px;">
									<label for="custom_css"><?php esc_html_e( 'Custom Styles', 'mantiload' ); ?></label>
								</th>
								<td>
									<textarea name="custom_css" id="custom_css" rows="12"
										style="width: 100%; max-width: 800px; font-family: 'Courier New', monospace; font-size: 13px; padding: 12px; border: 1px solid #ddd; border-radius: 4px;"
										placeholder="/* Add your custom CSS here */&#10;.mantiload-inline-form {&#10;    width: 600px !important;&#10;}"><?php echo esc_textarea( $settings['custom_css'] ?? '' ); ?></textarea>
									<p class="mantiload-help-text">
										<?php esc_html_e( 'Add custom CSS to style the search box and results. These styles will be loaded on the frontend and survive plugin updates.', 'mantiload' ); ?>
									</p>
									<div class="mantiload-alert mantiload-alert-info" style="margin-top: 12px;">
										<div>
											<strong><?php esc_html_e( 'Tips:', 'mantiload' ); ?></strong><br>
											• <?php esc_html_e( 'Use high specificity selectors (e.g., body .mantiload-inline-form) to override default styles', 'mantiload' ); ?><br>
											• <?php esc_html_e( 'Add !important to ensure your styles take priority', 'mantiload' ); ?><br>
											• <?php esc_html_e( 'Changes apply immediately after saving - no cache clearing needed', 'mantiload' ); ?>
										</div>
									</div>
								</td>
							</tr>
						</table>
					</div>
				</div>

			</div>

			<!-- Save Button - Sticky at bottom -->
			<div style="position: sticky; bottom: 0; background: white; padding: 20px 0; text-align: center; border-top: 1px solid #e5e7eb; margin-top: 30px; box-shadow: 0 -2px 10px rgba(0,0,0,0.05); z-index: 10;">
				<button type="submit" name="mantiload_settings_submit" class="ml-btn ml-btn-primary" style="font-size: 14px; padding: 12px 32px;">
					<i class="dashicons dashicons-saved"></i>
					<span><?php esc_html_e( 'Save All Settings', 'mantiload' ); ?></span>
				</button>
			</div>
		</form>
	</div>
</div>

<script>
// Initialize Lucide icons after DOM load
document.addEventListener('DOMContentLoaded', function() {

	// Load Index Status on page load
	loadIndexStatus();

	// Test Connection button
	const testBtn = document.getElementById('test-connection-btn');
	if (testBtn) {
		testBtn.addEventListener('click', testConnection);
	}

	// Rebuild Index button
	const rebuildBtn = document.getElementById('rebuild-index-btn');
	if (rebuildBtn) {
		rebuildBtn.addEventListener('click', rebuildIndex);
	}

	// Tab switching
	initializeTabs();
});

// Tab switching functionality
function initializeTabs() {
	const tabButtons = document.querySelectorAll('.mantiload-tab-button');
	const tabPanels = document.querySelectorAll('.mantiload-tab-panel');

	tabButtons.forEach(button => {
		button.addEventListener('click', function() {
			const targetTab = this.getAttribute('data-tab');

			// Remove active class from all buttons and panels
			tabButtons.forEach(btn => btn.classList.remove('active'));
			tabPanels.forEach(panel => panel.classList.remove('active'));

			// Add active class to clicked button and corresponding panel
			this.classList.add('active');
			document.querySelector(`.mantiload-tab-panel[data-tab="${targetTab}"]`).classList.add('active');

			// Reinitialize Lucide icons in the active tab
		});
	});
}

// Load Index Status
function loadIndexStatus() {
	const connectionEl = document.getElementById('connection-status');
	const countEl = document.getElementById('document-count');
	const sizeEl = document.getElementById('index-size');

	// Use WordPress admin-ajax URL
	const ajaxurl = '<?php echo admin_url( "admin-ajax.php" ); ?>';

	fetch(ajaxurl, {
		method: 'POST',
		headers: {'Content-Type': 'application/x-www-form-urlencoded'},
		body: 'action=mantiload_index_status&nonce=<?php echo wp_create_nonce( "mantiload-admin" ); ?>'
	})
	.then(res => res.json())
	.then(data => {
		if (data.success) {
			const status = data.data;

			// Update connection status
			if (status.connected) {
				connectionEl.innerHTML = '<span style="color: #10b981; font-weight: 600;">✓ Connected</span>';
			} else {
				connectionEl.innerHTML = '<span style="color: #ef4444; font-weight: 600;">✗ Not Connected</span>';
			}

			// Update document count
			if (status.document_count !== undefined) {
				countEl.innerHTML = '<strong style="color: #1f2937;">' + status.document_count.toLocaleString() + '</strong>';
			} else {
				countEl.innerHTML = '<span style="color: #9ca3af;">-</span>';
			}

			// Update index size
			if (status.index_size_mb !== undefined) {
				sizeEl.innerHTML = '<strong style="color: #1f2937;">' + status.index_size_mb + ' MB</strong>';
			} else {
				sizeEl.innerHTML = '<span style="color: #9ca3af;">-</span>';
			}
		} else {
			connectionEl.innerHTML = '<span style="color: #ef4444;">✗ Error</span>';
			countEl.innerHTML = '<span style="color: #9ca3af;">-</span>';
			sizeEl.innerHTML = '<span style="color: #9ca3af;">-</span>';
		}
	})
	.catch(err => {
		connectionEl.innerHTML = '<span style="color: #ef4444;">✗ Error</span>';
		countEl.innerHTML = '<span style="color: #9ca3af;">-</span>';
		sizeEl.innerHTML = '<span style="color: #9ca3af;">-</span>';
	});
}

// Test Connection
function testConnection() {
	const btn = document.getElementById('test-connection-btn');
	const result = document.getElementById('test-connection-result');

	btn.disabled = true;
	btn.innerHTML = '<span class="mantiload-spinner"></span> <span>Testing...</span>';
	result.innerHTML = '';

	// Use WordPress admin-ajax URL
	const ajaxurl = '<?php echo admin_url( "admin-ajax.php" ); ?>';

	fetch(ajaxurl, {
		method: 'POST',
		headers: {'Content-Type': 'application/x-www-form-urlencoded'},
		body: 'action=mantiload_test_connection&nonce=<?php echo wp_create_nonce( "mantiload-admin" ); ?>'
	})
	.then(res => res.json())
	.then(data => {
		btn.disabled = false;
		btn.innerHTML = '<i class="dashicons dashicons-bolt"></i> <span>Test Connection</span>';

		if (data.success) {
			result.innerHTML = '<span style="color: #10b981; font-weight: 600;">✓ ' + data.data.message + '</span>';
			// Reload index status
			setTimeout(loadIndexStatus, 500);
		} else {
			result.innerHTML = '<span style="color: #ef4444; font-weight: 600;">✗ ' + data.data.message + '</span>';
		}
	})
	.catch(err => {
		btn.disabled = false;
		btn.innerHTML = '<i class="dashicons dashicons-bolt"></i> <span>Test Connection</span>';
		result.innerHTML = '<span style="color: #ef4444;">✗ Connection failed</span>';
	});
}

// Beautiful Real-Time Rebuild with Batch Progress (like fast-reindex)
let rebuildAbortFlag = false;

function rebuildIndex() {
	if (!confirm('Are you sure you want to rebuild the search index?\n\nThis will reindex all products with full categories, attributes, and filter data.')) {
		return;
	}

	const btn = document.getElementById('rebuild-index-btn');
	const abortBtn = document.getElementById('rebuild-abort-btn');
	const progressContainer = document.getElementById('rebuild-progress-container');
	const progressBar = document.getElementById('rebuild-progress-bar');
	const progressText = document.getElementById('rebuild-progress-text');
	const progressStats = document.getElementById('rebuild-progress-stats');

	// Reset abort flag
	rebuildAbortFlag = false;

	// Disable start button, enable abort button
	btn.disabled = true;
	btn.innerHTML = '<span class="mantiload-spinner"></span> <span>Rebuilding...</span>';
	abortBtn.style.display = 'inline-flex';
	abortBtn.disabled = false;

	// Show progress container
	progressContainer.style.display = 'block';
	progressBar.style.width = '0%';
	progressBar.style.background = 'linear-gradient(90deg, #00a32a 0%, #008a20 100%)';
	progressText.textContent = 'Starting...';
	progressStats.innerHTML = '';

	// Get total posts first
	fetch(ajaxurl, {
		method: 'POST',
		headers: {'Content-Type': 'application/x-www-form-urlencoded'},
		body: 'action=mantiload_start_index&nonce=<?php echo wp_create_nonce( "mantiload-admin" ); ?>'
	})
	.then(res => res.json())
	.then(response => {
		if (response.success) {
			const totalPosts = response.data.total;
			const postTypes = response.data.post_types;
			let indexed = 0;
			let failed = 0;
			const startTime = Date.now();
			const batchSize = response.data.batch_size || 500;

			progressText.textContent = '0 / ' + totalPosts.toLocaleString() + ' posts';

			// Process each post type
			processPostTypes(postTypes, 0);

			function processPostTypes(postTypes, typeIndex) {
				// Check if aborted
				if (rebuildAbortFlag) {
					const totalTime = ((Date.now() - startTime) / 1000).toFixed(2);
					progressBar.style.background = 'linear-gradient(90deg, #d63638 0%, #b32d2e 100%)';
					progressText.innerHTML = '<strong>⚠️ Aborted!</strong>';
					progressStats.innerHTML =
						'<strong>Indexed:</strong> ' + indexed.toLocaleString() + ' | ' +
						'<strong>Failed:</strong> ' + failed + ' | ' +
						'<strong>Time:</strong> ' + totalTime + 's | ' +
						'<span style="color: #d63638;"><strong>Status:</strong> Stopped by user</span>';
					btn.disabled = false;
					btn.innerHTML = '<i class="dashicons dashicons-update"></i> <span>Rebuild Index Now</span>';
					abortBtn.style.display = 'none';
					return;
				}

				if (typeIndex >= postTypes.length) {
					// All done!
					const totalTime = ((Date.now() - startTime) / 1000).toFixed(2);
					progressBar.style.width = '100%';
					progressText.innerHTML = '<strong>✅ Complete!</strong>';
					progressStats.innerHTML =
						'<strong>Indexed:</strong> ' + indexed.toLocaleString() + ' | ' +
						'<strong>Failed:</strong> ' + failed + ' | ' +
						'<strong>Time:</strong> ' + totalTime + 's | ' +
						'<strong>Speed:</strong> ' + Math.round(indexed / totalTime) + ' posts/sec';
					btn.disabled = false;
					btn.innerHTML = '<i class="dashicons dashicons-update"></i> <span>Rebuild Index Now</span>';
					abortBtn.style.display = 'none';

					// Reload index status after 2 seconds
					setTimeout(() => {
						if (typeof loadIndexStatus === 'function') loadIndexStatus();
					}, 2000);
					return;
				}

				const postType = postTypes[typeIndex];
				let offset = 0;

				processBatch();

				function processBatch() {
					// Check if aborted before each batch
					if (rebuildAbortFlag) {
						processPostTypes(postTypes, postTypes.length); // Jump to end
						return;
					}

					fetch(ajaxurl, {
						method: 'POST',
						headers: {'Content-Type': 'application/x-www-form-urlencoded'},
						body: 'action=mantiload_index_batch&nonce=<?php echo wp_create_nonce( "mantiload-admin" ); ?>&post_type=' + postType + '&offset=' + offset + '&batch_size=' + batchSize
					})
					.then(res => res.json())
					.then(batchResponse => {
						if (batchResponse.success) {
							indexed += batchResponse.data.indexed;
							failed += batchResponse.data.failed;

							const percentage = Math.min(100, (indexed / totalPosts) * 100);
							const elapsed = ((Date.now() - startTime) / 1000);
							const speed = indexed / elapsed;
							const remaining = Math.round((totalPosts - indexed) / speed);

							progressBar.style.width = percentage.toFixed(1) + '%';
							progressBar.textContent = percentage.toFixed(0) + '%';
							progressText.textContent = indexed.toLocaleString() + ' / ' + totalPosts.toLocaleString() + ' posts (' + percentage.toFixed(1) + '%)';
							progressStats.innerHTML =
								'<strong>Post Type:</strong> ' + postType + ' | ' +
								'<strong>Speed:</strong> ' + Math.round(speed) + ' posts/sec | ' +
								'<strong>ETA:</strong> ' + remaining + 's';

							// If this batch had results, continue with next batch
							if (batchResponse.data.indexed > 0 || batchResponse.data.failed > 0) {
								offset += batchSize;
								processBatch();
							} else {
								// No more posts for this type, move to next type
								processPostTypes(postTypes, typeIndex + 1);
							}
						} else {
							progressBar.style.background = 'linear-gradient(90deg, #d63638 0%, #b32d2e 100%)';
							progressText.innerHTML = '<strong>❌ Error:</strong> ' + (batchResponse.data || 'Unknown error');
							btn.disabled = false;
							btn.innerHTML = '<i class="dashicons dashicons-update"></i> <span>Rebuild Index Now</span>';
							abortBtn.style.display = 'none';
						}
					})
					.catch(err => {
						console.error('Batch error:', err);
						progressBar.style.background = 'linear-gradient(90deg, #d63638 0%, #b32d2e 100%)';
						progressText.innerHTML = '<strong>❌ Error:</strong> Network error';
						btn.disabled = false;
						btn.innerHTML = '<i class="dashicons dashicons-update"></i> <span>Rebuild Index Now</span>';
						abortBtn.style.display = 'none';
					});
				}
			}
		} else {
			progressBar.style.background = 'linear-gradient(90deg, #d63638 0%, #b32d2e 100%)';
			progressText.innerHTML = '<strong>❌ Error:</strong> Could not start rebuild';
			btn.disabled = false;
			btn.innerHTML = '<i class="dashicons dashicons-update"></i> <span>Rebuild Index Now</span>';
			abortBtn.style.display = 'none';
		}
	})
	.catch(err => {
		console.error('Start error:', err);
		progressBar.style.background = 'linear-gradient(90deg, #d63638 0%, #b32d2e 100%)';
		progressText.innerHTML = '<strong>❌ Error:</strong> Failed to start';
		btn.disabled = false;
		btn.innerHTML = '<i class="dashicons dashicons-update"></i> <span>Rebuild Index Now</span>';
		abortBtn.style.display = 'none';
	});
}

// Abort handler for rebuild
document.addEventListener('DOMContentLoaded', function() {
	const abortBtn = document.getElementById('rebuild-abort-btn');
	if (abortBtn) {
		abortBtn.addEventListener('click', function() {
			if (confirm('Are you sure you want to abort the rebuild?')) {
				rebuildAbortFlag = true;
				this.disabled = true;
				this.innerHTML = '<span class="mantiload-spinner"></span> <span>Aborting...</span>';
			}
		});
	}
});
</script>
