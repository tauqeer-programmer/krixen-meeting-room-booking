<?php
// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Respect option to delete data
$delete = get_option('krixen_delete_on_uninstall','0');
if ( $delete !== '1' ) {
	return;
}

global $wpdb;
$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'krixen_bookings' );
$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'krixen_rooms' );
delete_option('krixen_delete_on_uninstall');
delete_option('krixen_logo_url');
delete_option('krixen_admin_logo_url');
delete_option('krixen_admin_email');
delete_option('krixen_booking_page_id');

