<?php
/**
 * Uninstall cleanup (spec §5): deletes data ONLY when the site opted in via
 * the "Delete all plugin data on uninstall" setting.
 *
 * @package Alovio\Calculator
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

function alovio_calc_uninstall_site(): void {
	if ( ! get_option( 'alovio_calc_delete_on_uninstall' ) ) {
		return;
	}
	global $wpdb;
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}alovio_calc_entries" ); // phpcs:ignore WordPress.DB

	// 'any' would skip trash/auto-draft (exclude_from_search statuses) — query ids directly by post_type.
	$ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s", 'alovio_calculator' ) );
	foreach ( $ids as $id ) {
		wp_delete_post( (int) $id, true );
	}
	foreach ( array( 'alovio_calc_version', 'alovio_calc_entry_count', 'alovio_calc_review_dismissed', 'alovio_calc_delete_on_uninstall' ) as $opt ) {
		delete_option( $opt );
	}
	// Sweep any not-yet-expired rate-limiter transients.
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient%alovio\_calc\_rl\_%'" ); // phpcs:ignore WordPress.DB

	// Quote-file upload artifacts: tokens, upload rate-limit transients, stored files and the GC cron.
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient%alovio\_calc\_uplrl\_%'" ); // phpcs:ignore WordPress.DB
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'alovio\_calc\_upload\_%'" ); // phpcs:ignore WordPress.DB
	wp_clear_scheduled_hook( 'alovio_calc_file_gc' );
	$alovio_calc_uploads = wp_upload_dir();
	$alovio_calc_dir     = $alovio_calc_uploads['basedir'] . '/alovio-calc';
	if ( is_dir( $alovio_calc_dir ) ) {
		foreach ( (array) glob( $alovio_calc_dir . '/{,.}*', GLOB_BRACE ) as $alovio_calc_file ) {
			if ( is_file( $alovio_calc_file ) ) {
				wp_delete_file( $alovio_calc_file );
			}
		}
		@rmdir( $alovio_calc_dir ); // phpcs:ignore
	}
}

if ( is_multisite() ) {
	foreach ( get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	) as $alovio_calc_site_id ) {
		switch_to_blog( (int) $alovio_calc_site_id );
		alovio_calc_uninstall_site();
		restore_current_blog();
	}
} else {
	alovio_calc_uninstall_site();
}
