<?php
/**
 * Plugin Activator
 *
 * Handles plugin activation tasks including table creation and initial setup.
 *
 * @package Krixen
 * @since 1.0.0
 */

namespace Krixen;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Activator class
 *
 * Manages plugin activation logic.
 */
class Activator {
	/**
	 * Run on plugin activation.
	 *
	 * @return void
	 */
	public static function activate(): void {
		// Create database tables
		Room_Manager::create_table();
		Booking_Manager::create_table();

		// Seed default rooms if not exist
		self::seed_default_rooms();

		// Create booking page with shortcode if not exists
		self::maybe_create_booking_page();
		
		// Flush rewrite rules
		flush_rewrite_rules();
	}

	/**
	 * Seed default rooms if not present.
	 *
	 * @return void
	 */
	private static function seed_default_rooms(): void {
		global $wpdb;
		$table = $wpdb->prefix . Room_Manager::TABLE;
		
		$defaults = [
			[ 'name' => 'Conference Room', 'capacity' => 12, 'description' => 'Large conference room for team meetings' ],
			[ 'name' => 'Meeting Room', 'capacity' => 5, 'description' => 'Intimate meeting space for small groups' ],
			[ 'name' => 'Discussion Room', 'capacity' => 3, 'description' => 'Cozy room for focused discussions' ],
		];

		foreach ( $defaults as $room ) {
			$exists = $wpdb->get_var( 
				$wpdb->prepare( "SELECT id FROM {$table} WHERE name = %s LIMIT 1", $room['name'] ) 
			);
			
			if ( ! $exists ) {
				$wpdb->insert( $table, $room );
			}
		}
	}

	/**
	 * Create booking page with shortcode if it doesn't exist.
	 *
	 * @return void
	 */
	private static function maybe_create_booking_page(): void {
		// Check for existing page by title or shortcode content
		$existing = get_page_by_title( 'Krixen Booking' );
		
		if ( $existing && $existing->post_status !== 'trash' ) {
			return;
		}
		
		$page_id = wp_insert_post( [
			'post_title'   => 'Krixen Booking',
			'post_status'  => 'publish',
			'post_type'    => 'page',
			'post_content' => '[krixen_meeting_booking]',
		] );
		
		if ( $page_id && ! is_wp_error( $page_id ) ) {
			update_post_meta( $page_id, '_krixen_booking_page', 1 );
		}
	}
}