<?php
/**
 * Uninstall Script
 *
 * Fired when the plugin is uninstalled. Removes all plugin data if configured.
 *
 * @package Krixen
 * @since 1.0.0
 */

// Exit if not called from WordPress
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Delete all plugin data
 *
 * @return void
 */
function krixen_uninstall_plugin(): void {
	// Check if user wants to delete data
	if ( get_option( 'krixen_delete_on_uninstall', '0' ) !== '1' ) {
		return;
	}

	global $wpdb;

	// Drop database tables
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}krixen_rooms" );
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}krixen_bookings" );

	// Delete all plugin options
	$options = [
		'krixen_delete_on_uninstall',
		'krixen_logo_url',
		'krixen_admin_logo_url',
		'krixen_admin_email',
		'krixen_from_email',
	];

	foreach ( $options as $option ) {
		delete_option( $option );
	}

	// Delete transients
	delete_transient( 'krixen_last_booking_change' );

	// Delete pages created by the plugin
	$pages = get_posts( [
		'post_type'   => 'page',
		'meta_key'    => '_krixen_booking_page',
		'meta_value'  => '1',
		'numberposts' => -1,
	] );

	foreach ( $pages as $page ) {
		wp_delete_post( $page->ID, true );
	}

	// Clear any cached data
	wp_cache_flush();
}

// Execute uninstall
krixen_uninstall_plugin();
