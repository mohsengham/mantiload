<?php
/**
 * Uninstall MantiLoad
 *
 * Removes all plugin data from the database when the plugin is deleted.
 */

// If uninstall not called from WordPress, exit
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Delete plugin options
delete_option( 'mantiload_settings' );
delete_option( 'mantiload_version' );
delete_option( 'mantiload_db_version' );

// Delete transients
delete_transient( 'mantiload_health_status' );
delete_transient( 'mantiload_index_stats' );

// Clear any scheduled cron jobs
wp_clear_scheduled_hook( 'mantiload_cleanup' );

// Drop Manticore index if configured to do so
$mantiload_drop_index_on_uninstall = false; // Set to true if you want to drop the Manticore index

if ( $mantiload_drop_index_on_uninstall ) {
	// Get Manticore connection settings
	$mantiload_settings = get_option( 'mantiload_settings', array() );
	$mantiload_host = isset( $mantiload_settings['manticore_host'] ) ? $mantiload_settings['manticore_host'] : '127.0.0.1';
	$mantiload_port = isset( $mantiload_settings['manticore_port'] ) ? (int) $mantiload_settings['manticore_port'] : 9306;
	$mantiload_index_name = isset( $mantiload_settings['index_name'] ) ? $mantiload_settings['index_name'] : '';

	if ( ! empty( $mantiload_index_name ) ) {
		try {
			// phpcs:ignore WordPress.DB.RestrictedClasses.mysql__mysqli -- Direct mysqli required for Manticore Search connection
			$mantiload_mysqli = new mysqli( $mantiload_host, '', '', '', $mantiload_port );

			if ( ! $mantiload_mysqli->connect_error ) {
				// Drop the index
				$mantiload_mysqli->query( "DROP TABLE IF EXISTS " . $mantiload_mysqli->real_escape_string( $mantiload_index_name ) );
				$mantiload_mysqli->close();
			}
		} catch ( Exception $e ) {
			// Silently fail - index may not exist or Manticore may not be running
		}
	}
}
