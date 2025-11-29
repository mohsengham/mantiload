<?php defined( 'ABSPATH' ) || exit; ?>

<div class="wrap">
	<?php settings_errors( 'mantiload' ); ?>
</div>

<div class="mantiload-admin-wrap">
	<div class="mantiload-header">
		<h1>
			<span class="mantiload-logo">
				<img src="<?php echo esc_url( MANTILOAD_PLUGIN_URL . 'assets/img/logo.png' ); ?>" alt="MantiLoad">
			</span>
			<?php esc_html_e( 'Indexing', 'mantiload' ); ?>
		</h1>
		<p><?php esc_html_e( 'Lightning-fast search indexing powered by Manticore Search', 'mantiload' ); ?></p>
	</div>

	<!-- Start Indexing Card -->
	<div class="mantiload-card">
		<h2><i data-lucide="refresh-cw"></i> <?php esc_html_e( 'Full Reindex', 'mantiload' ); ?></h2>
		<p class="mantiload-description"><?php esc_html_e( 'Reindex all products with categories, attributes, and filter data', 'mantiload' ); ?></p>

		<div class="mantiload-divider"></div>

		<div class="ml-btn-group">
			<button id="mantiload-start-index" class="ml-btn ml-btn-primary">
				<i data-lucide="refresh-cw"></i>
				<span><?php esc_html_e( 'Start Full Reindex', 'mantiload' ); ?></span>
			</button>
			<button id="mantiload-abort-index" class="ml-btn ml-btn-danger" style="display:none;">
				<i data-lucide="square"></i>
				<span><?php esc_html_e( 'Abort', 'mantiload' ); ?></span>
			</button>
		</div>

		<!-- Progress Container -->
		<div id="mantiload-progress" class="mantiload-progress-container" style="display:none; margin-top: var(--ml-space-lg);">
			<div class="mantiload-progress-text" id="mantiload-text" style="font-size: 13px; color: var(--ml-gray-700); margin-bottom: var(--ml-space-sm);">
				<?php esc_html_e( 'Starting...', 'mantiload' ); ?>
			</div>
			<div class="mantiload-progress-bar-wrapper" style="background: var(--ml-gray-200); height: 32px; border-radius: var(--ml-radius); overflow: hidden; position: relative;">
				<div id="mantiload-bar" class="mantiload-progress-bar" style="width:0%; background: var(--ml-black); height: 100%; transition: width 0.3s ease; display: flex; align-items: center; justify-content: center;">
					<span id="mantiload-percentage" style="color: var(--ml-white); font-size: 13px; font-weight: 600;">0%</span>
				</div>
			</div>
		</div>
	</div>

	<!-- Index Management Card -->
	<div class="mantiload-card">
		<h2><i data-lucide="settings"></i> <?php esc_html_e( 'Index Management', 'mantiload' ); ?></h2>
		<p class="mantiload-description">
			<?php esc_html_e( 'Manage your Manticore search index with these powerful tools', 'mantiload' ); ?>
		</p>

		<div class="mantiload-divider"></div>

		<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: var(--ml-space-lg);">
			<div>
				<h3 style="font-size: 14px; font-weight: 600; color: var(--ml-black); margin: 0 0 var(--ml-space-xs) 0; display: flex; align-items: center; gap: var(--ml-space-xs);">
					<i data-lucide="plus-square" style="width: 16px; height: 16px;"></i>
					<?php esc_html_e( 'Create Index', 'mantiload' ); ?>
				</h3>
				<p class="mantiload-description">
					<?php esc_html_e( 'Create a new search index with multi-language support', 'mantiload' ); ?>
				</p>
				<button id="mantiload-create-index" class="ml-btn ml-btn-primary" style="margin-top: var(--ml-space-sm);">
					<i data-lucide="plus"></i>
					<span><?php esc_html_e( 'Create Index', 'mantiload' ); ?></span>
				</button>
			</div>

			<div>
				<h3 style="font-size: 14px; font-weight: 600; color: var(--ml-black); margin: 0 0 var(--ml-space-xs) 0; display: flex; align-items: center; gap: var(--ml-space-xs);">
					<i data-lucide="trash-2" style="width: 16px; height: 16px;"></i>
					<?php esc_html_e( 'Truncate Index', 'mantiload' ); ?>
				</h3>
				<p class="mantiload-description">
					<?php esc_html_e( 'Remove all indexed data and clear the entire search index', 'mantiload' ); ?>
				</p>
				<button id="mantiload-truncate-index" class="ml-btn ml-btn-danger" style="margin-top: var(--ml-space-sm);">
					<i data-lucide="x-circle"></i>
					<span><?php esc_html_e( 'Truncate Index', 'mantiload' ); ?></span>
				</button>
			</div>

			<div>
				<h3 style="font-size: 14px; font-weight: 600; color: var(--ml-black); margin: 0 0 var(--ml-space-xs) 0; display: flex; align-items: center; gap: var(--ml-space-xs);">
					<i data-lucide="zap" style="width: 16px; height: 16px;"></i>
					<?php esc_html_e( 'Optimize Index', 'mantiload' ); ?>
				</h3>
				<p class="mantiload-description">
					<?php esc_html_e( 'Optimize the index for better query performance and reduced disk usage', 'mantiload' ); ?>
				</p>
				<button id="mantiload-optimize-index" class="ml-btn" style="margin-top: var(--ml-space-sm);">
					<i data-lucide="zap"></i>
					<span><?php esc_html_e( 'Optimize Index', 'mantiload' ); ?></span>
				</button>
			</div>
		</div>
	</div>

	<!-- Statistics Card -->
	<div class="mantiload-card">
		<h2><i data-lucide="bar-chart-2"></i> <?php esc_html_e( 'Indexing Statistics', 'mantiload' ); ?></h2>

		<?php
		$mantiload_stats = \MantiLoad\MantiLoad::instance()->indexer->get_stats();
		?>

		<table class="widefat striped" style="margin-top: var(--ml-space-md);">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Post Type', 'mantiload' ); ?></th>
					<th style="width: 150px;"><?php esc_html_e( 'Indexed', 'mantiload' ); ?></th>
					<th style="width: 150px;"><?php esc_html_e( 'Total', 'mantiload' ); ?></th>
					<th style="width: 120px; text-align: center;"><?php esc_html_e( 'Progress', 'mantiload' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $mantiload_stats as $type => $mantiload_stat ): ?>
				<tr>
					<td><strong style="color: var(--ml-black);"><?php echo esc_html( ucfirst( $type ) ); ?></strong></td>
					<td><?php echo number_format( $mantiload_stat['indexed'] ); ?></td>
					<td><?php echo number_format( $mantiload_stat['total'] ); ?></td>
					<td style="text-align: center;">
						<span style="display: inline-block; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600; <?php echo $mantiload_stat['percentage'] >= 100 ? 'background: var(--ml-black); color: var(--ml-white);' : 'background: var(--ml-gray-200); color: var(--ml-gray-700);'; ?>">
							<?php echo esc_html( $mantiload_stat['percentage'] ); ?>%
						</span>
					</td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</div>

<script>
// Initialize Lucide icons after DOM load
document.addEventListener('DOMContentLoaded', function() {
	if (typeof lucide !== 'undefined') {
		lucide.createIcons();
	}
});
</script>
