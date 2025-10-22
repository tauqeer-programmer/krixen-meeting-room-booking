<?php
namespace Krixen;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles one-time plugin activation tasks.
 */
class Activator {
    /**
     * Run on plugin activation.
     */
    public static function activate() {
        // Ensure DB tables exist.
        Room_Manager::create_table();
        Booking_Manager::create_table();

        // Seed defaults and ensure a booking page exists.
        self::seed_default_rooms();
        self::maybe_create_booking_page();
    }

    /**
     * Seed default rooms if not present.
     */
    private static function seed_default_rooms() {
        global $wpdb;
        $table = $wpdb->prefix . Room_Manager::TABLE;

        $defaults = [
            [ 'name' => 'Conference Room', 'capacity' => 12, 'description' => '' ],
            [ 'name' => 'Meeting Room',     'capacity' => 5,  'description' => '' ],
            [ 'name' => 'Discussion Room',  'capacity' => 3,  'description' => '' ],
        ];

        foreach ( $defaults as $room ) {
            $exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE name = %s LIMIT 1", $room['name'] ) );
            if ( ! $exists ) {
                $wpdb->insert( $table, $room );
            }
        }
    }

    /**
     * Ensure a public booking page exists and store its ID.
     */
    private static function maybe_create_booking_page() {
        $stored_id = (int) get_option( 'krixen_booking_page_id', 0 );
        if ( $stored_id > 0 ) {
            $page = get_post( $stored_id );
            if ( $page && 'trash' !== $page->post_status ) {
                return; // Already set up.
            }
        }

        // Try to find an existing page by title first.
        $existing = get_page_by_title( 'Krixen Booking' );
        if ( $existing && 'trash' !== $existing->post_status ) {
            update_option( 'krixen_booking_page_id', (int) $existing->ID );
            return;
        }

        // Create a new page with the shortcode.
        $page_id = wp_insert_post( [
            'post_title'   => 'Krixen Booking',
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_content' => '[krixen_meeting_booking]',
        ] );
        if ( $page_id && ! is_wp_error( $page_id ) ) {
            update_option( 'krixen_booking_page_id', (int) $page_id );
        }
    }
}