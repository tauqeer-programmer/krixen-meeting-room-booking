<?php
declare(strict_types=1);

namespace Krixen;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Activator {
    /**
     * Run on plugin activation.
     */
    public static function activate(): void {
        // Create DB tables.
        Room_Manager::create_table();
        Booking_Manager::create_table();

        // Seed default rooms.
        self::seed_default_rooms();

        // Ensure a booking page exists and store its ID.
        self::maybe_create_booking_page();
    }

    /**
     * Seed default rooms if not present.
     */
    private static function seed_default_rooms(): void {
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
     * Create booking page with shortcode if not exists and save its ID.
     */
    private static function maybe_create_booking_page(): void {
        // Check for existing page by title or stored option
        $page_id = (int) get_option( 'krixen_booking_page_id', 0 );
        if ( $page_id > 0 && get_post_status( $page_id ) && get_post_type( $page_id ) === 'page' ) {
            return;
        }

        $existing = get_page_by_title( 'Krixen Booking' );
        if ( $existing && $existing->post_status !== 'trash' ) {
            update_option( 'krixen_booking_page_id', (int) $existing->ID );
            return;
        }

        $new_page_id = wp_insert_post( [
            'post_title'   => 'Krixen Booking',
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_content' => '[krixen_meeting_booking]',
        ] );
        if ( $new_page_id && ! is_wp_error( $new_page_id ) ) {
            update_post_meta( $new_page_id, '_krixen_booking_page', 1 );
            update_option( 'krixen_booking_page_id', (int) $new_page_id );
        }
    }
}