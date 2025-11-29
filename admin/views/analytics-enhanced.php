<?php
/**
 * MantiLoad - Enhanced Analytics Dashboard
 * Modern analytics with Chart.js visualizations
 */

defined( 'ABSPATH' ) || exit;

// Get insights data - period filter (read-only, no nonce needed for display filtering)
$mantiload_valid_periods = array( 'today', 'week', 'month', 'all' );
$mantiload_period = 'week'; // Default
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display filter, validated against strict whitelist
if ( isset( $_GET['period'] ) ) {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display filter, validated against strict whitelist
	$mantiload_requested_period = sanitize_text_field( wp_unslash( $_GET['period'] ) );
	if ( in_array( $mantiload_requested_period, $mantiload_valid_periods, true ) ) {
		$mantiload_period = $mantiload_requested_period;
	}
}
$mantiload_insights = \MantiLoad\Search_Insights::get_insights( $mantiload_period );

$mantiload_top_searches = $mantiload_insights['top_searches'];
$mantiload_zero_results = $mantiload_insights['zero_results'];
$mantiload_trending = $mantiload_insights['trending'];
$mantiload_performance = $mantiload_insights['performance'];
?>

<div class="wrap">
	<?php settings_errors( 'mantiload' ); ?>
</div>

<div class="mantiload-admin-wrap">
	<!-- Header -->
	<div class="mantiload-header">
		<h1>
			<span class="mantiload-logo">
				<img src="<?php echo esc_url( MANTILOAD_PLUGIN_URL . 'assets/img/logo.png' ); ?>" alt="MantiLoad">
			</span>
			Search Analytics
		</h1>
		<p>Advanced insights powered by Manticore Search</p>
	</div>

	<!-- Period Selector -->
	<div class="mantiload-card" style="margin-bottom: var(--ml-space-lg);">
		<div style="display: flex; gap: var(--ml-space-sm); align-items: center;">
			<strong style="color: var(--ml-gray-700); margin-right: var(--ml-space-sm);">Time Period:</strong>
			<a href="?page=mantiload-analytics-enhanced&period=today" 
			   class="ml-btn ml-btn-sm <?php echo $mantiload_period === 'today' ? 'ml-btn-primary' : 'ml-btn-secondary'; ?>">
				Today
			</a>
			<a href="?page=mantiload-analytics-enhanced&period=week" 
			   class="ml-btn ml-btn-sm <?php echo $mantiload_period === 'week' ? 'ml-btn-primary' : 'ml-btn-secondary'; ?>">
				Last 7 Days
			</a>
			<a href="?page=mantiload-analytics-enhanced&period=month" 
			   class="ml-btn ml-btn-sm <?php echo $mantiload_period === 'month' ? 'ml-btn-primary' : 'ml-btn-secondary'; ?>">
				Last 30 Days
			</a>
			<a href="?page=mantiload-analytics-enhanced&period=all" 
			   class="ml-btn ml-btn-sm <?php echo $mantiload_period === 'all' ? 'ml-btn-primary' : 'ml-btn-secondary'; ?>">
				All Time
			</a>

			<div style="margin-left: auto;">
				<a href="?page=mantiload-analytics-enhanced&period=<?php echo esc_attr( $mantiload_period ); ?>&export=csv" 
				   class="ml-btn ml-btn-secondary ml-btn-sm">
					<i data-lucide="download"></i>
					Export CSV
				</a>
			</div>
		</div>
	</div>

	<!-- Stats Cards Grid -->
	<div class="mantiload-stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); margin-bottom: var(--ml-space-lg);">
		<div class="mantiload-stat-card">
			<div class="stat-icon">
				<i data-lucide="search"></i>
			</div>
			<div class="stat-content">
				<h3>Total Searches</h3>
				<div class="stat-number"><?php echo esc_html( number_format( $mantiload_performance['total_searches'] ) ); ?></div>
				<div class="stat-label"><?php echo esc_html( ucfirst( $mantiload_period ) ); ?></div>
			</div>
		</div>

		<div class="mantiload-stat-card">
			<div class="stat-icon">
				<i data-lucide="zap"></i>
			</div>
			<div class="stat-content">
				<h3>Avg Response Time</h3>
				<div class="stat-number" style="color: <?php echo $mantiload_performance['avg_time'] < 10 ? '#10b981' : '#f59e0b'; ?>;">
					<?php echo esc_html( number_format( $mantiload_performance['avg_time'], 2 ) ); ?> ms
				</div>
				<div class="stat-label">Lightning fast ⚡</div>
			</div>
		</div>

		<div class="mantiload-stat-card">
			<div class="stat-icon">
				<i data-lucide="file-text"></i>
			</div>
			<div class="stat-content">
				<h3>Avg Results</h3>
				<div class="stat-number"><?php echo esc_html( number_format( $mantiload_performance['avg_results'], 1 ) ); ?></div>
				<div class="stat-label">Per search</div>
			</div>
		</div>

		<div class="mantiload-stat-card">
			<div class="stat-icon">
				<i data-lucide="check-circle"></i>
			</div>
			<div class="stat-content">
				<h3>Success Rate</h3>
				<div class="stat-number" style="color: <?php echo $mantiload_performance['success_rate'] > 80 ? '#10b981' : '#f59e0b'; ?>;">
					<?php echo esc_html( number_format( $mantiload_performance['success_rate'], 1 ) ); ?>%
				</div>
				<div class="stat-label">Queries with results</div>
			</div>
		</div>
	</div>

	<!-- Two Column Layout -->
	<div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--ml-space-lg); margin-bottom: var(--ml-space-lg);">
		
		<!-- Top Searches -->
		<div class="mantiload-card">
			<h2><i data-lucide="trending-up"></i> Top Searches</h2>
			
			<?php if ( empty( $mantiload_top_searches ) ): ?>
				<div style="text-align: center; padding: 40px; color: var(--ml-gray-500);">
					<i data-lucide="inbox" style="width: 32px; height: 32px; margin: 0 auto var(--ml-space-sm); display: block; opacity: 0.3;"></i>
					No search data for this period
				</div>
			<?php else: ?>
				<div style="margin-top: var(--ml-space-md);">
					<?php 
					$mantiload_max_count = max( array_column( $mantiload_top_searches, 'count' ) );
					foreach ( $mantiload_top_searches as $search ): 
						$mantiload_percentage = ( $search['count'] / $mantiload_max_count ) * 100;
					?>
					<div style="margin-bottom: var(--ml-space-md);">
						<div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
							<strong style="color: var(--ml-black);"><?php echo esc_html( $search['query'] ); ?></strong>
							<span style="color: var(--ml-gray-600); font-size: 13px;">
								<?php echo esc_html( number_format( $search['count'] ) ); ?> searches
							</span>
						</div>
						<div style="background: var(--ml-gray-200); height: 8px; border-radius: 4px; overflow: hidden;">
							<div style="background: linear-gradient(90deg, #6366f1, #8b5cf6); height: 100%; width: <?php echo esc_attr( $mantiload_percentage ); ?>%; transition: width 0.3s ease;"></div>
						</div>
						<div style="display: flex; gap: var(--ml-space-sm); margin-top: 4px; font-size: 12px; color: var(--ml-gray-600);">
							<span>✓ <?php echo esc_html( number_format( $search['success_rate'], 0 ) ); ?>% success</span>
							<span>⚡ <?php echo esc_html( number_format( $search['avg_time'], 1 ) ); ?>ms</span>
							<span>📄 <?php echo esc_html( number_format( $search['avg_results'], 0 ) ); ?> results</span>
						</div>
					</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>

		<!-- Trending Searches -->
		<div class="mantiload-card">
			<h2><i data-lucide="trending-up"></i> Trending Searches</h2>
			
			<?php if ( empty( $mantiload_trending ) ): ?>
				<div style="text-align: center; padding: 40px; color: var(--ml-gray-500);">
					<i data-lucide="inbox" style="width: 32px; height: 32px; margin: 0 auto var(--ml-space-sm); display: block; opacity: 0.3;"></i>
					No trending searches detected
				</div>
			<?php else: ?>
				<div style="margin-top: var(--ml-space-md);">
					<?php foreach ( $mantiload_trending as $mantiload_trend ): ?>
					<div style="padding: var(--ml-space-sm); background: var(--ml-gray-100); border-radius: var(--ml-radius); margin-bottom: var(--ml-space-sm); display: flex; justify-content: space-between; align-items: center;">
						<div>
							<strong style="color: var(--ml-black);"><?php echo esc_html( $mantiload_trend['query'] ); ?></strong>
							<div style="display: flex; align-items: center; gap: 10px;">
								<?php echo esc_html( number_format( $mantiload_trend['count'] ) ); ?> searches
								<span style="background: #10b981; color: white; padding: 2px 8px; border-radius: 10px; font-size: 12px; font-weight: 600;">
									+<?php echo esc_html( number_format( $mantiload_trend['growth'], 0 ) ); ?>%
								</span>
				</div>
				<div style="font-size: 12px; color: #7f1d1d; margin-top: var(--ml-space-xs);">
					💡 <?php echo esc_html( $query['suggestion'] ); ?>
				</div>
			</div>
			<?php endforeach; ?>
		</div>
	</div>
	<?php endif; ?>

	<!-- Charts Section -->
	<div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--ml-space-lg);">
		
		<!-- Top Searches Pie Chart -->
		<div class="mantiload-card">
			<h2><i data-lucide="pie-chart"></i> Search Distribution</h2>
			<canvas id="topSearchesChart" style="max-height: 300px;"></canvas>
		</div>

		<!-- Performance Over Time -->
		<div class="mantiload-card">
			<h2><i data-lucide="activity"></i> Performance Metrics</h2>
			<div style="text-align: center; padding: 40px; color: var(--ml-gray-500);">
				<i data-lucide="clock" style="width: 32px; height: 32px; margin: 0 auto var(--ml-space-sm); display: block; opacity: 0.3;"></i>
				Timeline charts coming soon
			</div>
		</div>
	</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
	// Initialize Lucide icons
	if (typeof lucide !== 'undefined') {
		lucide.createIcons();
	}

	// Top Searches Pie Chart
	<?php if ( ! empty( $mantiload_top_searches ) ): ?>
	const topSearchesData = {
		labels: <?php echo wp_json_encode( array_column( $mantiload_top_searches, 'query' ) ); ?>,
		datasets: [{
			data: <?php echo wp_json_encode( array_column( $mantiload_top_searches, 'count' ) ); ?>,
			backgroundColor: [
				'#6366f1', '#8b5cf6', '#ec4899', '#f59e0b', '#10b981',
				'#3b82f6', '#14b8a6', '#f97316', '#a855f7', '#22d3ee'
			],
			borderWidth: 0
		}]
	};

	const topSearchesCtx = document.getElementById('topSearchesChart');
	new Chart(topSearchesCtx, {
		type: 'doughnut',
		data: topSearchesData,
		options: {
			responsive: true,
			maintainAspectRatio: true,
			plugins: {
				legend: {
					position: 'right',
					labels: {
						boxWidth: 12,
						padding: 10,
						font: {
							size: 11
						}
					}
				},
				tooltip: {
					callbacks: {
						label: function(context) {
							const label = context.label || '';
							const value = context.parsed || 0;
							const total = context.dataset.data.reduce((a, b) => a + b, 0);
							const percentage = ((value / total) * 100).toFixed(1);
							return label + ': ' + value + ' (' + percentage + '%)';
						}
					}
				}
			}
		}
	});
	<?php endif; ?>
});
</script>

<style>
.ml-btn-sm {
	padding: 6px 12px;
	font-size: 13px;
}

.ml-btn-primary {
	background: linear-gradient(135deg, #6366f1, #8b5cf6);
	color: white;
	border: none;
}

.ml-btn-secondary {
	background: var(--ml-gray-200);
	color: var(--ml-gray-700);
	border: none;
}

.ml-btn-secondary:hover {
	background: var(--ml-gray-300);
}
</style>
