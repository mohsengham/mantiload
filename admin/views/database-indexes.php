<?php
/**
 * Database Indexes Admin View
 *
 * @package MantiLoad
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get index manager instance.
require_once MANTILOAD_PLUGIN_DIR . 'includes/class-database-indexes.php';
$mantiload_index_manager = new MantiLoad_Database_Indexes();

// Get status and summary.
$mantiload_indexes_status = $mantiload_index_manager->get_indexes_status();
$mantiload_summary        = $mantiload_index_manager->get_summary();

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
			<?php esc_html_e( 'Database Indexes', 'mantiload' ); ?>
		</h1>
		<p><?php esc_html_e( 'Optimize MySQL query performance for admin panel and reliability', 'mantiload' ); ?></p>
	</div>

	<!-- Index Status Widget -->
	<div class="mantiload-card">
		<h2><i class="dashicons dashicons-chart-line"></i> <?php esc_html_e( 'Index Status', 'mantiload' ); ?></h2>
		<div class="mantiload-divider"></div>

		<div class="mantiload-status-grid-compact">
			<div class="mantiload-status-row">
				<div class="mantiload-status-compact">
					<span class="mantiload-status-label-inline"><?php esc_html_e( 'Installed:', 'mantiload' ); ?></span>
					<span class="mantiload-status-value-inline" style="color: #00a32a; font-weight: 600;">
						<?php echo esc_html( $mantiload_summary['installed'] ); ?> <?php esc_html_e( 'indexes', 'mantiload' ); ?>
					</span>
				</div>
				<div class="mantiload-status-compact">
					<span class="mantiload-status-label-inline"><?php esc_html_e( 'Available:', 'mantiload' ); ?></span>
					<span class="mantiload-status-value-inline" style="color: #2271b1; font-weight: 600;">
						<?php echo esc_html( $mantiload_summary['installable'] ); ?> <?php esc_html_e( 'ready to install', 'mantiload' ); ?>
					</span>
				</div>
				<div class="mantiload-status-compact">
					<span class="mantiload-status-label-inline"><?php esc_html_e( 'Unavailable:', 'mantiload' ); ?></span>
					<span class="mantiload-status-value-inline" style="color: #dba617;">
						<?php echo esc_html( $mantiload_summary['unavailable'] ); ?> <?php esc_html_e( '(table missing)', 'mantiload' ); ?>
					</span>
				</div>
			</div>
			<div class="mantiload-status-row">
				<div class="mantiload-status-compact">
					<span class="mantiload-status-label-inline"><?php esc_html_e( 'Total Indexes:', 'mantiload' ); ?></span>
					<span class="mantiload-status-value-inline">
						<?php echo esc_html( $mantiload_summary['total'] ); ?>
					</span>
				</div>
				<?php if ( $mantiload_summary['index_wp_mysql_active'] ) : ?>
				<div class="mantiload-status-compact">
					<span class="mantiload-status-label-inline"><?php esc_html_e( 'Index WP MySQL:', 'mantiload' ); ?></span>
					<span class="mantiload-status-value-inline" style="color: #00a32a;">
						✓ <?php esc_html_e( 'Detected', 'mantiload' ); ?>
					</span>
				</div>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<!-- Info Box -->
	<?php if ( $mantiload_summary['installable'] > 0 || ! $mantiload_summary['index_wp_mysql_active'] ) : ?>
	<div class="mantiload-card" style="background: #f0f6fc; border-left: 4px solid #2271b1;">
		<h3 style="margin-top: 0; color: #2271b1;">
			<i class="dashicons dashicons-info"></i>
			<?php esc_html_e( 'About Database Indexes', 'mantiload' ); ?>
		</h3>

		<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-top: 15px;">
			<div>
				<h4 style="margin-top: 0;"><?php esc_html_e( 'When Indexes Help', 'mantiload' ); ?></h4>
				<ul style="margin: 0; padding-left: 20px; color: #666;">
					<li><?php esc_html_e( 'WooCommerce admin panel (orders, products, reports)', 'mantiload' ); ?></li>
					<li><?php esc_html_e( 'Reliability when Manticore is unavailable', 'mantiload' ); ?></li>
					<li><?php esc_html_e( 'Secondary queries (widgets, sidebars)', 'mantiload' ); ?></li>
					<li><?php esc_html_e( 'Variable product variation loading', 'mantiload' ); ?></li>
				</ul>
			</div>

			<div>
				<h4 style="margin-top: 0;"><?php esc_html_e( 'How They Work With MantiLoad', 'mantiload' ); ?></h4>
				<ul style="margin: 0; padding-left: 20px; color: #666;">
					<li><strong><?php esc_html_e( 'Frontend:', 'mantiload' ); ?></strong> <?php esc_html_e( 'MantiLoad uses Manticore (indexes dormant)', 'mantiload' ); ?></li>
					<li><strong><?php esc_html_e( 'Admin:', 'mantiload' ); ?></strong> <?php esc_html_e( 'Indexes speed up MySQL (40-80% faster)', 'mantiload' ); ?></li>
					<li><strong><?php esc_html_e( 'Fallback:', 'mantiload' ); ?></strong> <?php esc_html_e( 'Essential safety net for reliability', 'mantiload' ); ?></li>
				</ul>
			</div>
		</div>

		<?php if ( ! $mantiload_summary['index_wp_mysql_active'] ) : ?>
		<div style="margin-top: 15px; padding: 12px; background: #fff3cd; border-left: 3px solid #f0b849; border-radius: 4px;">
			<strong>💡 <?php esc_html_e( 'Pro Tip:', 'mantiload' ); ?></strong>
			<?php esc_html_e( 'For maximum performance, also install "Index WP MySQL For Speed" plugin. It optimizes meta tables while MantiLoad handles taxonomy and WooCommerce queries.', 'mantiload' ); ?>
		</div>
		<?php endif; ?>
	</div>
	<?php endif; ?>

	<!-- One-Click Installation -->
	<?php if ( $mantiload_summary['installable'] > 0 ) : ?>
	<div class="mantiload-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; text-align: center;">
		<h2 style="color: white; margin: 0 0 10px 0; font-size: 20px;">
			<?php esc_html_e( 'Quick Installation', 'mantiload' ); ?>
		</h2>
		<p style="margin: 0 0 20px 0; opacity: 0.95; font-size: 14px;">
			<?php esc_html_e( 'Install all available indexes with one click for improved performance', 'mantiload' ); ?>
		</p>
		<button type="button" id="mantiload-install-all-indexes" class="button button-primary button-hero" style="background: white; color: #667eea; border: none; box-shadow: 0 2px 8px rgba(0,0,0,0.15); font-size: 14px; padding: 8px 24px;">
			<span class="dashicons dashicons-download" style="margin-top: 6px;"></span>
			<?php
			printf(
				/* translators: %d: number of indexes */
				esc_html__( 'Install All Indexes (%d)', 'mantiload' ),
				$mantiload_summary['installable']
			);
			?>
		</button>

		<div id="mantiload-install-progress" style="display: none; margin-top: 20px;">
			<div style="background: rgba(255,255,255,0.3); height: 24px; border-radius: 4px; overflow: hidden;">
				<div id="mantiload-install-progress-bar" style="background: rgba(255,255,255,0.9); height: 100%; width: 0%; transition: width 0.3s;"></div>
			</div>
			<p id="mantiload-install-status" style="margin-top: 10px; opacity: 0.95; font-size: 14px;"></p>
		</div>
	</div>
	<?php endif; ?>

	<!-- Tabbed Navigation -->
	<div class="mantiload-tabs-container">
		<div class="mantiload-tabs-nav">
			<button type="button" class="mantiload-tab-button active" data-tab="taxonomy">
				<i class="dashicons dashicons-category"></i>
				<span><?php esc_html_e( 'Taxonomy', 'mantiload' ); ?></span>
			</button>
			<button type="button" class="mantiload-tab-button" data-tab="woocommerce">
				<i class="dashicons dashicons-store"></i>
				<span><?php esc_html_e( 'WooCommerce', 'mantiload' ); ?></span>
			</button>
			<button type="button" class="mantiload-tab-button" data-tab="products">
				<i class="dashicons dashicons-products"></i>
				<span><?php esc_html_e( 'Products', 'mantiload' ); ?></span>
			</button>
			<button type="button" class="mantiload-tab-button" data-tab="about">
				<i class="dashicons dashicons-shield"></i>
				<span><?php esc_html_e( 'About', 'mantiload' ); ?></span>
			</button>
		</div>

		<!-- Taxonomy Indexes Tab -->
		<div class="mantiload-tab-panel active" id="tab-taxonomy">
			<div class="mantiload-card">
				<h2>
					<?php esc_html_e( 'Taxonomy Indexes', 'mantiload' ); ?>
					<span style="background: #2271b1; color: white; padding: 2px 10px; border-radius: 12px; font-size: 12px; margin-left: 10px; font-weight: normal;">
						<?php echo esc_html( $mantiload_summary['taxonomy_indexes_count'] ); ?>
					</span>
				</h2>
				<p style="color: #666; margin-top: 10px;">
					<?php esc_html_e( 'Optimizes category filtering, hierarchical queries, and product attribute filtering.', 'mantiload' ); ?>
				</p>
				<div class="mantiload-divider"></div>

				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th style="width: 30%;"><?php esc_html_e( 'Index Name', 'mantiload' ); ?></th>
							<th style="width: 15%;"><?php esc_html_e( 'Table', 'mantiload' ); ?></th>
							<th style="width: 35%;"><?php esc_html_e( 'Benefit', 'mantiload' ); ?></th>
							<th style="width: 10%;"><?php esc_html_e( 'Status', 'mantiload' ); ?></th>
							<th style="width: 10%;"><?php esc_html_e( 'Actions', 'mantiload' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						foreach ( $mantiload_indexes_status as $mantiload_index_name => $mantiload_index_data ) :
							if ( ! str_contains( $mantiload_index_name, 'term_' ) ) {
								continue;
							}
							?>
							<tr>
								<td><code style="font-size: 11px;"><?php echo esc_html( $mantiload_index_name ); ?></code></td>
								<td><code style="font-size: 11px;"><?php echo esc_html( $mantiload_index_data['table'] ); ?></code></td>
								<td style="font-size: 13px; color: #666;">
									<?php echo esc_html( $mantiload_index_data['benefit'] ); ?>
								</td>
								<td>
									<?php if ( ! $mantiload_index_data['table_exists'] ) : ?>
										<span style="color: #dba617; font-weight: 600; font-size: 12px;">⚠ <?php esc_html_e( 'N/A', 'mantiload' ); ?></span>
									<?php elseif ( $mantiload_index_data['index_exists'] ) : ?>
										<span style="color: #00a32a; font-weight: 600; font-size: 12px;">✓ <?php esc_html_e( 'Active', 'mantiload' ); ?></span>
									<?php else : ?>
										<span style="color: #999; font-size: 12px;">○ <?php esc_html_e( 'Not Installed', 'mantiload' ); ?></span>
									<?php endif; ?>
								</td>
								<td>
									<?php if ( $mantiload_index_data['installable'] ) : ?>
										<button type="button" class="button button-small mantiload-install-index" data-index="<?php echo esc_attr( $mantiload_index_name ); ?>">
											<?php esc_html_e( 'Install', 'mantiload' ); ?>
										</button>
									<?php elseif ( $mantiload_index_data['index_exists'] ) : ?>
										<button type="button" class="button button-small mantiload-remove-index" data-index="<?php echo esc_attr( $mantiload_index_name ); ?>">
											<?php esc_html_e( 'Remove', 'mantiload' ); ?>
										</button>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>

		<!-- WooCommerce Indexes Tab -->
		<div class="mantiload-tab-panel" id="tab-woocommerce">
			<div class="mantiload-card">
				<h2>
					<?php esc_html_e( 'WooCommerce HPOS Indexes', 'mantiload' ); ?>
					<span style="background: #96588a; color: white; padding: 2px 10px; border-radius: 12px; font-size: 12px; margin-left: 10px; font-weight: normal;">
						<?php echo esc_html( $mantiload_summary['woocommerce_indexes_count'] ); ?>
					</span>
				</h2>
				<p style="color: #666; margin-top: 10px;">
					<?php esc_html_e( 'Optimizes WooCommerce High-Performance Order Storage (HPOS) queries for faster order management.', 'mantiload' ); ?>
				</p>
				<div class="mantiload-divider"></div>

				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th style="width: 30%;"><?php esc_html_e( 'Index Name', 'mantiload' ); ?></th>
							<th style="width: 15%;"><?php esc_html_e( 'Table', 'mantiload' ); ?></th>
							<th style="width: 35%;"><?php esc_html_e( 'Benefit', 'mantiload' ); ?></th>
							<th style="width: 10%;"><?php esc_html_e( 'Status', 'mantiload' ); ?></th>
							<th style="width: 10%;"><?php esc_html_e( 'Actions', 'mantiload' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						foreach ( $mantiload_indexes_status as $mantiload_index_name => $mantiload_index_data ) :
							if ( ! str_contains( $mantiload_index_name, 'wc_' ) && ! str_contains( $mantiload_index_name, 'woocommerce_' ) ) {
								continue;
							}
							?>
							<tr>
								<td><code style="font-size: 11px;"><?php echo esc_html( $mantiload_index_name ); ?></code></td>
								<td><code style="font-size: 11px;"><?php echo esc_html( $mantiload_index_data['table'] ); ?></code></td>
								<td style="font-size: 13px; color: #666;">
									<?php echo esc_html( $mantiload_index_data['benefit'] ); ?>
								</td>
								<td>
									<?php if ( ! $mantiload_index_data['table_exists'] ) : ?>
										<span style="color: #dba617; font-weight: 600; font-size: 12px;">⚠ <?php esc_html_e( 'N/A', 'mantiload' ); ?></span>
									<?php elseif ( $mantiload_index_data['index_exists'] ) : ?>
										<span style="color: #00a32a; font-weight: 600; font-size: 12px;">✓ <?php esc_html_e( 'Active', 'mantiload' ); ?></span>
									<?php else : ?>
										<span style="color: #999; font-size: 12px;">○ <?php esc_html_e( 'Not Installed', 'mantiload' ); ?></span>
									<?php endif; ?>
								</td>
								<td>
									<?php if ( $mantiload_index_data['installable'] ) : ?>
										<button type="button" class="button button-small mantiload-install-index" data-index="<?php echo esc_attr( $mantiload_index_name ); ?>">
											<?php esc_html_e( 'Install', 'mantiload' ); ?>
										</button>
									<?php elseif ( $mantiload_index_data['index_exists'] ) : ?>
										<button type="button" class="button button-small mantiload-remove-index" data-index="<?php echo esc_attr( $mantiload_index_name ); ?>">
											<?php esc_html_e( 'Remove', 'mantiload' ); ?>
										</button>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>

		<!-- Product Indexes Tab -->
		<div class="mantiload-tab-panel" id="tab-products">
			<div class="mantiload-card">
				<h2>
					<?php esc_html_e( 'Product Optimization Indexes', 'mantiload' ); ?>
					<span style="background: #00a32a; color: white; padding: 2px 10px; border-radius: 12px; font-size: 12px; margin-left: 10px; font-weight: normal;">
						<?php echo esc_html( $mantiload_summary['product_indexes_count'] ); ?>
					</span>
				</h2>
				<p style="color: #666; margin-top: 10px;">
					<?php esc_html_e( 'Optimizes product catalog queries and variable product loading for faster shop pages.', 'mantiload' ); ?>
				</p>
				<div class="mantiload-divider"></div>

				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th style="width: 30%;"><?php esc_html_e( 'Index Name', 'mantiload' ); ?></th>
							<th style="width: 15%;"><?php esc_html_e( 'Table', 'mantiload' ); ?></th>
							<th style="width: 35%;"><?php esc_html_e( 'Benefit', 'mantiload' ); ?></th>
							<th style="width: 10%;"><?php esc_html_e( 'Status', 'mantiload' ); ?></th>
							<th style="width: 10%;"><?php esc_html_e( 'Actions', 'mantiload' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						foreach ( $mantiload_indexes_status as $mantiload_index_name => $mantiload_index_data ) :
							if ( ! str_contains( $mantiload_index_name, 'posts_product' ) ) {
								continue;
							}
							?>
							<tr>
								<td><code style="font-size: 11px;"><?php echo esc_html( $mantiload_index_name ); ?></code></td>
								<td><code style="font-size: 11px;"><?php echo esc_html( $mantiload_index_data['table'] ); ?></code></td>
								<td style="font-size: 13px; color: #666;">
									<?php echo esc_html( $mantiload_index_data['benefit'] ); ?>
								</td>
								<td>
									<?php if ( ! $mantiload_index_data['table_exists'] ) : ?>
										<span style="color: #dba617; font-weight: 600; font-size: 12px;">⚠ <?php esc_html_e( 'N/A', 'mantiload' ); ?></span>
									<?php elseif ( $mantiload_index_data['index_exists'] ) : ?>
										<span style="color: #00a32a; font-weight: 600; font-size: 12px;">✓ <?php esc_html_e( 'Active', 'mantiload' ); ?></span>
									<?php else : ?>
										<span style="color: #999; font-size: 12px;">○ <?php esc_html_e( 'Not Installed', 'mantiload' ); ?></span>
									<?php endif; ?>
								</td>
								<td>
									<?php if ( $mantiload_index_data['installable'] ) : ?>
										<button type="button" class="button button-small mantiload-install-index" data-index="<?php echo esc_attr( $mantiload_index_name ); ?>">
											<?php esc_html_e( 'Install', 'mantiload' ); ?>
										</button>
									<?php elseif ( $mantiload_index_data['index_exists'] ) : ?>
										<button type="button" class="button button-small mantiload-remove-index" data-index="<?php echo esc_attr( $mantiload_index_name ); ?>">
											<?php esc_html_e( 'Remove', 'mantiload' ); ?>
										</button>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>

		<!-- About Tab -->
		<div class="mantiload-tab-panel" id="tab-about">
			<div class="mantiload-card">
				<h2><?php esc_html_e( 'About Database Indexes', 'mantiload' ); ?></h2>
				<div class="mantiload-divider"></div>

				<h3><?php esc_html_e( 'What These Indexes Do', 'mantiload' ); ?></h3>
				<ul style="color: #666; line-height: 1.8;">
					<li><?php esc_html_e( 'Complement existing optimization plugins (Index WP MySQL For Speed, Scalability Pro)', 'mantiload' ); ?></li>
					<li><?php esc_html_e( 'Focus on taxonomy relationships and WooCommerce-specific queries', 'mantiload' ); ?></li>
					<li><?php esc_html_e( 'Avoid conflicts by using unique index names with "mantiload_" prefix', 'mantiload' ); ?></li>
					<li><?php esc_html_e( 'Target specific performance bottlenecks in category filtering and product queries', 'mantiload' ); ?></li>
				</ul>

				<h3><?php esc_html_e( 'Index Size Impact', 'mantiload' ); ?></h3>
				<p style="color: #666;">
					<?php esc_html_e( 'Each index: 5-50 MB depending on store size. Total for all 8 indexes on 10k product store: ~100-200 MB (negligible compared to typical WooCommerce database of 500MB-5GB).', 'mantiload' ); ?>
				</p>

				<h3><?php esc_html_e( 'Safety', 'mantiload' ); ?></h3>
				<p style="color: #666;">
					<?php esc_html_e( 'All indexes can be safely removed without affecting your data. Only database query performance will be impacted. MySQL automatically maintains indexes - no ongoing management needed.', 'mantiload' ); ?>
				</p>

				<?php if ( $mantiload_summary['installed'] > 0 ) : ?>
				<div style="margin-top: 30px; padding: 20px; background: #fcf0f1; border-left: 4px solid #d63638; border-radius: 4px;">
					<h3 style="margin-top: 0; color: #d63638;">
						<i class="dashicons dashicons-warning"></i>
						<?php esc_html_e( 'Remove All Indexes', 'mantiload' ); ?>
					</h3>
					<p style="margin: 0 0 15px 0; color: #666;">
						<?php esc_html_e( 'If you need to remove all MantiLoad indexes (for troubleshooting or uninstallation), use the button below.', 'mantiload' ); ?>
					</p>
					<button type="button" id="mantiload-remove-all-indexes" class="button">
						<span class="dashicons dashicons-trash"></span>
						<?php
						printf(
							/* translators: %d: number of installed indexes */
							esc_html__( 'Remove All Installed Indexes (%d)', 'mantiload' ),
							$mantiload_summary['installed']
						);
						?>
					</button>
				</div>
				<?php endif; ?>
			</div>
		</div>
	</div>
</div>

<script>
jQuery(document).ready(function($) {
	// Tab switching
	$('.mantiload-tab-button').on('click', function() {
		var tab = $(this).data('tab');

		$('.mantiload-tab-button').removeClass('active');
		$(this).addClass('active');

		$('.mantiload-tab-panel').removeClass('active');
		$('#tab-' + tab).addClass('active');
	});

	// Install all indexes
	$('#mantiload-install-all-indexes').on('click', function() {
		if (!confirm('<?php echo esc_js( __( 'This will install all available database indexes. Continue?', 'mantiload' ) ); ?>')) {
			return;
		}

		var $button = $(this);
		var $progress = $('#mantiload-install-progress');
		var $progressBar = $('#mantiload-install-progress-bar');
		var $status = $('#mantiload-install-status');

		$button.prop('disabled', true);
		$progress.show();
		$status.text('<?php echo esc_js( __( 'Installing indexes...', 'mantiload' ) ); ?>');

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'mantiload_install_all_indexes',
				nonce: '<?php echo esc_js( wp_create_nonce( 'mantiload-indexes' ) ); ?>'
			},
			success: function(response) {
				if (response.success) {
					$progressBar.css('width', '100%');
					$status.html('<span style="font-weight: 600;">✓ ' + response.data.message + '</span>');
					setTimeout(function() {
						location.reload();
					}, 2000);
				} else {
					$status.html('<span style="font-weight: 600;">✗ ' + response.data.message + '</span>');
					$button.prop('disabled', false);
				}
			},
			error: function() {
				$status.html('<span style="font-weight: 600;">✗ <?php echo esc_js( __( 'Installation failed. Please try again.', 'mantiload' ) ); ?></span>');
				$button.prop('disabled', false);
			}
		});
	});

	// Install single index
	$('.mantiload-install-index').on('click', function() {
		var $button = $(this);
		var indexName = $button.data('index');

		$button.prop('disabled', true).text('<?php echo esc_js( __( 'Installing...', 'mantiload' ) ); ?>');

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'mantiload_install_index',
				nonce: '<?php echo esc_js( wp_create_nonce( 'mantiload-indexes' ) ); ?>',
				index_name: indexName
			},
			success: function(response) {
				if (response.success) {
					location.reload();
				} else {
					alert(response.data.message);
					$button.prop('disabled', false).text('<?php echo esc_js( __( 'Install', 'mantiload' ) ); ?>');
				}
			},
			error: function() {
				alert('<?php echo esc_js( __( 'Installation failed. Please try again.', 'mantiload' ) ); ?>');
				$button.prop('disabled', false).text('<?php echo esc_js( __( 'Install', 'mantiload' ) ); ?>');
			}
		});
	});

	// Remove single index
	$('.mantiload-remove-index').on('click', function() {
		if (!confirm('<?php echo esc_js( __( 'Are you sure you want to remove this index?', 'mantiload' ) ); ?>')) {
			return;
		}

		var $button = $(this);
		var indexName = $button.data('index');

		$button.prop('disabled', true).text('<?php echo esc_js( __( 'Removing...', 'mantiload' ) ); ?>');

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'mantiload_remove_index',
				nonce: '<?php echo esc_js( wp_create_nonce( 'mantiload-indexes' ) ); ?>',
				index_name: indexName
			},
			success: function(response) {
				if (response.success) {
					location.reload();
				} else {
					alert(response.data.message);
					$button.prop('disabled', false).text('<?php echo esc_js( __( 'Remove', 'mantiload' ) ); ?>');
				}
			},
			error: function() {
				alert('<?php echo esc_js( __( 'Removal failed. Please try again.', 'mantiload' ) ); ?>');
				$button.prop('disabled', false).text('<?php echo esc_js( __( 'Remove', 'mantiload' ) ); ?>');
			}
		});
	});

	// Remove all indexes
	$('#mantiload-remove-all-indexes').on('click', function() {
		if (!confirm('<?php echo esc_js( __( 'Are you sure you want to remove ALL MantiLoad indexes? This cannot be undone.', 'mantiload' ) ); ?>')) {
			return;
		}

		var $button = $(this);
		$button.prop('disabled', true).text('<?php echo esc_js( __( 'Removing...', 'mantiload' ) ); ?>');

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'mantiload_remove_all_indexes',
				nonce: '<?php echo esc_js( wp_create_nonce( 'mantiload-indexes' ) ); ?>'
			},
			success: function(response) {
				if (response.success) {
					location.reload();
				} else {
					alert(response.data.message);
					$button.prop('disabled', false).text('<?php echo esc_js( __( 'Remove All Installed Indexes', 'mantiload' ) ); ?>');
				}
			},
			error: function() {
				alert('<?php echo esc_js( __( 'Removal failed. Please try again.', 'mantiload' ) ); ?>');
				$button.prop('disabled', false).text('<?php echo esc_js( __( 'Remove All Installed Indexes', 'mantiload' ) ); ?>');
			}
		});
	});
});
</script>
