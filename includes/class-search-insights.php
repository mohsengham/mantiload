<?php
/**
 * MantiLoad Search Insights
 *
 * Analyzes search logs to provide actionable insights:
 * - Top searches with volume
 * - Zero-result queries
 * - Trending searches
 * - Performance metrics
 */

namespace MantiLoad;

defined( 'ABSPATH' ) || exit;

class Search_Insights {

	/**
	 * Get comprehensive search insights
	 *
	 * @param string $period 'today', 'week', 'month', 'all'
	 * @return array
	 */
	public static function get_insights( $period = 'week' ) {
		$logs = \get_option( 'mantiload_search_logs', array() );

		if ( empty( $logs ) ) {
			return array(
				'top_searches'     => array(),
				'zero_results'     => array(),
				'trending'         => array(),
				'performance'      => array(
					'total_searches'   => 0,
					'avg_time'         => 0,
					'avg_results'      => 0,
					'success_rate'     => 0,
				),
				'period'           => $period,
			);
		}

		// Filter logs by period
		$filtered_logs = self::filter_by_period( $logs, $period );
		$previous_logs = self::filter_by_period( $logs, self::get_previous_period( $period ) );

		// Analyze data
		$top_searches = self::get_top_searches( $filtered_logs, 10 );
		$zero_results = self::get_zero_result_queries( $filtered_logs );
		$trending = self::get_trending_searches( $filtered_logs, $previous_logs );
		$performance = self::get_performance_metrics( $filtered_logs );

		return array(
			'top_searches'     => $top_searches,
			'zero_results'     => $zero_results,
			'trending'         => $trending,
			'performance'      => $performance,
			'period'           => $period,
			'total_logs'       => count( $logs ),
		);
	}

	/**
	 * Filter logs by time period
	 */
	private static function filter_by_period( $logs, $period ) {
		$now = current_time( 'timestamp' );
		$cutoff = 0;

		switch ( $period ) {
			case 'today':
				$cutoff = strtotime( 'today', $now );
				break;
			case 'yesterday':
				$cutoff = strtotime( 'yesterday', $now );
				$end = strtotime( 'today', $now );
				return array_filter( $logs, function( $log ) use ( $cutoff, $end ) {
					return $log['timestamp'] >= $cutoff && $log['timestamp'] < $end;
				} );
			case 'week':
				$cutoff = strtotime( '-7 days', $now );
				break;
			case 'month':
				$cutoff = strtotime( '-30 days', $now );
				break;
			case 'all':
			default:
				return $logs;
		}

		return array_filter( $logs, function( $log ) use ( $cutoff ) {
			return $log['timestamp'] >= $cutoff;
		} );
	}

	/**
	 * Get previous period for comparison
	 */
	private static function get_previous_period( $period ) {
		switch ( $period ) {
			case 'today':
				return 'yesterday';
			case 'week':
				return 'previous_week';
			case 'month':
				return 'previous_month';
			default:
				return 'yesterday';
		}
	}

	/**
	 * Get top searches with stats
	 */
	private static function get_top_searches( $logs, $limit = 10 ) {
		$searches = array();

		foreach ( $logs as $log ) {
			$query = trim( strtolower( $log['query'] ) );

			// Skip empty queries
			if ( empty( $query ) ) {
				continue;
			}

			if ( ! isset( $searches[ $query ] ) ) {
				$searches[ $query ] = array(
					'query'        => $log['query'], // Keep original case
					'count'        => 0,
					'results'      => array(),
					'times'        => array(),
					'zero_results' => 0,
				);
			}

			$searches[ $query ]['count']++;
			$searches[ $query ]['results'][] = $log['results'];
			$searches[ $query ]['times'][] = $log['time'];

			if ( $log['results'] == 0 ) {
				$searches[ $query ]['zero_results']++;
			}
		}

		// Calculate averages and success rates
		foreach ( $searches as $query => &$data ) {
			$data['avg_results'] = array_sum( $data['results'] ) / count( $data['results'] );
			$data['avg_time'] = array_sum( $data['times'] ) / count( $data['times'] );
			$data['success_rate'] = ( ( $data['count'] - $data['zero_results'] ) / $data['count'] ) * 100;

			// Clean up
			unset( $data['results'], $data['times'] );
		}

		// Sort by count (descending)
		uasort( $searches, function( $a, $b ) {
			return $b['count'] - $a['count'];
		} );

		return array_slice( $searches, 0, $limit, true );
	}

	/**
	 * Get zero-result queries with suggestions
	 */
	private static function get_zero_result_queries( $logs ) {
		$zero_results = array();

		foreach ( $logs as $log ) {
			$query = trim( $log['query'] );

			// Only track queries with 0 results
			if ( $log['results'] == 0 && ! empty( $query ) ) {
				$key = strtolower( $query );

				if ( ! isset( $zero_results[ $key ] ) ) {
					$zero_results[ $key ] = array(
						'query'      => $query,
						'count'      => 0,
						'suggestion' => self::suggest_fix( $query ),
					);
				}

				$zero_results[ $key ]['count']++;
			}
		}

		// Sort by count (descending)
		uasort( $zero_results, function( $a, $b ) {
			return $b['count'] - $a['count'];
		} );

		return array_slice( $zero_results, 0, 10, true );
	}

	/**
	 * Suggest fix for zero-result queries
	 */
	private static function suggest_fix( $query ) {
		// Check for common typos
		$typos = array(
			'cheep'  => 'cheap',
			'promm'  => 'prom',
			'dreses' => 'dresses',
			'ballgown' => 'ball gown',
			'evenig' => 'evening',
			'formol' => 'formal',
		);

		$lower = strtolower( $query );
		foreach ( $typos as $typo => $correct ) {
			if ( strpos( $lower, $typo ) !== false ) {
				return 'Did you mean "' . str_ireplace( $typo, $correct, $query ) . '"?';
			}
		}

		// Check if it's a compound word that should be separated
		if ( strpos( $query, ' ' ) === false && strlen( $query ) > 10 ) {
			return 'Try adding spaces between words';
		}

		// Suggest creating synonym
		return 'Add as synonym or check product titles';
	}

	/**
	 * Get trending searches (compared to previous period)
	 */
	private static function get_trending_searches( $current_logs, $previous_logs ) {
		$current = array();
		$previous = array();

		// Count current period
		foreach ( $current_logs as $log ) {
			$query = trim( strtolower( $log['query'] ) );
			if ( empty( $query ) ) continue;
			$current[ $query ] = isset( $current[ $query ] ) ? $current[ $query ] + 1 : 1;
		}

		// Count previous period
		foreach ( $previous_logs as $log ) {
			$query = trim( strtolower( $log['query'] ) );
			if ( empty( $query ) ) continue;
			$previous[ $query ] = isset( $previous[ $query ] ) ? $previous[ $query ] + 1 : 1;
		}

		$trending = array();

		// Calculate growth
		foreach ( $current as $query => $count ) {
			$prev_count = isset( $previous[ $query ] ) ? $previous[ $query ] : 0;

			if ( $prev_count > 0 ) {
				$growth = ( ( $count - $prev_count ) / $prev_count ) * 100;
			} else {
				$growth = 100; // New search term
			}

			// Only include if trending up significantly (>50% growth)
			if ( $growth >= 50 ) {
				$trending[ $query ] = array(
					'query'  => $query,
					'count'  => $count,
					'growth' => $growth,
				);
			}
		}

		// Sort by growth (descending)
		uasort( $trending, function( $a, $b ) {
			return $b['growth'] - $a['growth'];
		} );

		return array_slice( $trending, 0, 5, true );
	}

	/**
	 * Get performance metrics
	 */
	private static function get_performance_metrics( $logs ) {
		if ( empty( $logs ) ) {
			return array(
				'total_searches'   => 0,
				'avg_time'         => 0,
				'avg_results'      => 0,
				'success_rate'     => 0,
			);
		}

		$total = count( $logs );
		$total_time = 0;
		$total_results = 0;
		$successful = 0;

		foreach ( $logs as $log ) {
			$total_time += $log['time'];
			$total_results += $log['results'];
			if ( $log['results'] > 0 ) {
				$successful++;
			}
		}

		return array(
			'total_searches'   => $total,
			'avg_time'         => round( $total_time / $total, 2 ),
			'avg_results'      => round( $total_results / $total, 1 ),
			'success_rate'     => round( ( $successful / $total ) * 100, 1 ),
		);
	}

	/**
	 * Export search data to CSV
	 */
	public static function export_csv( $period = 'all' ) {
		$insights = self::get_insights( $period );

		header( 'Content-Type: text/csv' );
		header( 'Content-Disposition: attachment; filename="mantiload-search-insights-' . gmdate( 'Y-m-d' ) . '.csv"' );

		$output = fopen( 'php://output', 'w' );

		// Header
		fputcsv( $output, array( 'MantiLoad Search Insights - ' . ucfirst( $period ) ) );
		fputcsv( $output, array() );

		// Top Searches
		fputcsv( $output, array( 'Top Searches' ) );
		fputcsv( $output, array( 'Query', 'Count', 'Avg Results', 'Success Rate %', 'Avg Time (ms)' ) );
		foreach ( $insights['top_searches'] as $search ) {
			fputcsv( $output, array(
				$search['query'],
				$search['count'],
				round( $search['avg_results'], 1 ),
				round( $search['success_rate'], 1 ),
				round( $search['avg_time'], 2 ),
			) );
		}

		fputcsv( $output, array() );

		// Zero Results
		fputcsv( $output, array( 'Zero Result Queries' ) );
		fputcsv( $output, array( 'Query', 'Count', 'Suggestion' ) );
		foreach ( $insights['zero_results'] as $query ) {
			fputcsv( $output, array(
				$query['query'],
				$query['count'],
				$query['suggestion'],
			) );
		}

		fputcsv( $output, array() );

		// Performance
		fputcsv( $output, array( 'Performance Metrics' ) );
		fputcsv( $output, array( 'Metric', 'Value' ) );
		fputcsv( $output, array( 'Total Searches', $insights['performance']['total_searches'] ) );
		fputcsv( $output, array( 'Avg Time (ms)', $insights['performance']['avg_time'] ) );
		fputcsv( $output, array( 'Avg Results', $insights['performance']['avg_results'] ) );
		fputcsv( $output, array( 'Success Rate %', $insights['performance']['success_rate'] ) );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Using php://output for CSV export
		fclose( $output );
		exit;
	}
}
