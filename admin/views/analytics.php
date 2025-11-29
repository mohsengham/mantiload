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
			Analytics
		</h1>
		<p>Track search performance and user behavior</p>
	</div>

	<!-- Stats Summary -->
	<div class="mantiload-stats-grid">
		<div class="mantiload-stat-card">
			<div class="stat-icon">
				<i data-lucide="search"></i>
			</div>
			<div class="stat-content">
				<h3>Total Searches</h3>
				<div class="stat-number"><?php echo number_format( $total_searches ); ?></div>
				<div class="stat-label">All time</div>
			</div>
		</div>

		<div class="mantiload-stat-card">
			<div class="stat-icon">
				<i data-lucide="zap"></i>
			</div>
			<div class="stat-content">
				<h3>Avg Response Time</h3>
				<div class="stat-number"><?php echo number_format( $avg_time, 2 ); ?> ms</div>
				<div class="stat-label">Lightning fast</div>
			</div>
		</div>

		<div class="mantiload-stat-card">
			<div class="stat-icon">
				<i data-lucide="file-text"></i>
			</div>
			<div class="stat-content">
				<h3>Avg Results</h3>
				<div class="stat-number"><?php echo number_format( $avg_results, 1 ); ?></div>
				<div class="stat-label">Per search</div>
			</div>
		</div>
	</div>

	<!-- Recent Searches -->
	<div class="mantiload-card">
		<h2><i data-lucide="clock"></i> Recent Searches</h2>

		<table class="widefat striped" style="margin-top: var(--ml-space-md);">
			<thead>
				<tr>
					<th>Query</th>
					<th style="width: 100px;">Results</th>
					<th style="width: 100px;">Time (ms)</th>
					<th style="width: 180px;">Date</th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $logs ) ): ?>
				<tr>
					<td colspan="4" style="text-align: center; padding: 40px; color: var(--ml-gray-500);">
						<i data-lucide="inbox" style="width: 32px; height: 32px; margin: 0 auto var(--ml-space-sm); display: block; opacity: 0.3;"></i>
						No search logs found
					</td>
				</tr>
				<?php else: ?>
					<?php foreach ( $logs as $mantiload_log ): ?>
					<tr>
						<td><strong style="color: var(--ml-black);"><?php echo esc_html( $mantiload_log['query'] ); ?></strong></td>
						<td><?php echo number_format( $mantiload_log['results'] ); ?></td>
						<td>
							<span style="color: <?php echo $mantiload_log['time'] < 10 ? 'var(--ml-black)' : 'var(--ml-gray-600)'; ?>">
								<?php echo number_format( $mantiload_log['time'], 2 ); ?>
							</span>
						</td>
						<td style="color: var(--ml-gray-600); font-size: 12px;">
							<?php echo esc_html( gmdate( 'Y-m-d H:i:s', $mantiload_log['timestamp'] ) ); ?>
						</td>
					</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>

		<?php if ( !empty( $logs ) ): ?>
		<div style="margin-top: var(--ml-space-lg);">
			<form method="post" style="display: inline;">
				<?php wp_nonce_field( 'mantiload-action' ); ?>
				<button type="submit" name="mantiload_action" value="clear_logs" class="ml-btn ml-btn-danger" onclick="return confirm('Clear all search logs?');">
					<i data-lucide="trash-2"></i>
					Clear All Logs
				</button>
			</form>
		</div>
		<?php endif; ?>
	</div>

	<!-- Performance Insights -->
	<div class="mantiload-card">
		<h2><i data-lucide="trending-up"></i> Performance Insights</h2>

		<div style="margin-top: var(--ml-space-md);">
			<?php if ( $avg_time < 10 ): ?>
				<div style="padding: var(--ml-space-md); background: var(--ml-gray-100); border-radius: var(--ml-radius); margin-bottom: var(--ml-space-sm);">
					<strong style="color: var(--ml-black);">Excellent Performance</strong>
					<p style="margin: var(--ml-space-xs) 0 0 0; color: var(--ml-gray-700); font-size: 13px;">
						Your average search time is under 10ms - that's lightning fast!
					</p>
				</div>
			<?php elseif ( $avg_time < 50 ): ?>
				<div style="padding: var(--ml-space-md); background: var(--ml-gray-100); border-radius: var(--ml-radius); margin-bottom: var(--ml-space-sm);">
					<strong style="color: var(--ml-black);">Good Performance</strong>
					<p style="margin: var(--ml-space-xs) 0 0 0; color: var(--ml-gray-700); font-size: 13px;">
						Your searches are performing well. Consider enabling search cache for even better results.
					</p>
				</div>
			<?php else: ?>
				<div style="padding: var(--ml-space-md); background: var(--ml-gray-100); border-radius: var(--ml-radius); margin-bottom: var(--ml-space-sm);">
					<strong style="color: var(--ml-black);">Room for Improvement</strong>
					<p style="margin: var(--ml-space-xs) 0 0 0; color: var(--ml-gray-700); font-size: 13px;">
						Consider optimizing your indexes or enabling search cache to improve performance.
					</p>
				</div>
			<?php endif; ?>

			<?php if ( $avg_results < 1 ): ?>
				<div style="padding: var(--ml-space-md); background: var(--ml-gray-100); border-radius: var(--ml-radius);">
					<strong style="color: var(--ml-black);">Low Result Count</strong>
					<p style="margin: var(--ml-space-xs) 0 0 0; color: var(--ml-gray-700); font-size: 13px;">
						Consider adding synonyms to help users find more relevant results.
					</p>
				</div>
			<?php endif; ?>
		</div>
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
