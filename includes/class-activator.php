<?php
namespace Krixen;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Activator {
	public static function activate() {
		// Create tables
		Room_Manager::create_table();
		Booking_Manager::create_table();

		// Seed default rooms if not exist
		self::seed_default_rooms();

		// Create booking page with shortcode if not exists
		self::maybe_create_booking_page();
	}

	private static function seed_default_rooms() {
		global $wpdb;
		$table = $wpdb->prefix . Room_Manager::TABLE;
		$defaults = [
			[ 'name' => 'Conference Room', 'capacity' => 12, 'description' => '' ],
			[ 'name' => 'Meeting Room', 'capacity' => 5, 'description' => '' ],
			[ 'name' => 'Discussion Room', 'capacity' => 3, 'description' => '' ],
		];

		foreach ( $defaults as $room ) {
			$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE name = %s LIMIT 1", $room['name'] ) );
			if ( ! $exists ) {
				$wpdb->insert( $table, $room );
			}
		}
	}

	private static function maybe_create_booking_page() {
		// Check for existing page by title or shortcode content
		$existing = get_page_by_title( 'Krixen Booking' );
		if ( $existing && $existing->post_status !== 'trash' ) {
			return;
		}
		$page_id = wp_insert_post( [
			'post_title'   => 'Krixen Booking',
			'post_status'  => 'publish',
			'post_type'    => 'page',
			'post_content' => "[krixen_meeting_booking]",
		] );
		if ( $page_id && ! is_wp_error( $page_id ) ) {
			update_post_meta( $page_id, '_krixen_booking_page', 1 );
		}
	}
}

<?php
namespace Krixen;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Activator {
    /**
     * Run on plugin activation.
     */
    public static function activate() {
        // Create DB tables.
        Room_Manager::create_table();
        Booking_Manager::create_table();
        // Seed default rooms.
        self::seed_default_rooms();
    }

    /**
     * Seed default rooms if not present.
     */
    private static function seed_default_rooms() {
        global $wpdb;
        $table = Room_Manager::TABLE;

        $defaults = [
            [ 'name' => 'Conference Room', 'capacity' => 12, 'description' => '' ],
            [ 'name' => 'Meeting Room',     'capacity' => 5,  'description' => '' ],
            [ 'name' => 'Discussion Room',  'capacity' => 3,  'description' => '' ],
        ];

        foreach ( $defaults as $room ) {
            $exists = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE name = %s", $room['name'] ) );
            if ( ! $exists ) {
                $wpdb->insert( $table, $room );
            }
        }
    }
}