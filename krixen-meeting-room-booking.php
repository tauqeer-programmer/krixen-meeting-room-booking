<?php
/**
 * Plugin Name: Krixen Meeting Room Booking
 * Description: A modern, powerful meeting room booking system with real-time availability.
 * Version: 2.0.0
 * Author: UGRO
 * Author URI: https://krixen.com
 * License: GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: krixen
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 *
 * @package Krixen
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Define plugin constants
define( 'KRIXEN_VERSION', '2.0.0' );
define( 'KRIXEN_PLUGIN_FILE', __FILE__ );
define( 'KRIXEN_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'KRIXEN_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'KRIXEN_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * PSR-4 Autoloader for Krixen classes
 *
 * @param string $class The fully-qualified class name.
 * @return void
 */
spl_autoload_register( function ( string $class ): void {
	// Only load classes from our namespace
	if ( strpos( $class, 'Krixen\\' ) !== 0 ) {
		return;
	}

	// Remove namespace and convert to file name
	$relative_class = str_replace( 'Krixen\\', '', $class );
	
	// Convert namespace separators and underscores to hyphens, make lowercase, and prepend "class-"
	$file = 'class-' . strtolower( str_replace( [ '\\', '_' ], '-', $relative_class ) ) . '.php';

	$path = KRIXEN_PLUGIN_PATH . 'includes/' . $file;

	if ( file_exists( $path ) ) {
		require_once $path;
	}
} );

/**
 * Plugin activation hook
 */
register_activation_hook( __FILE__, function (): void {
	if ( class_exists( '\\Krixen\\Activator' ) ) {
		\Krixen\Activator::activate();
	} else {
		// Fallback: try loading the activator explicitly
		$path = KRIXEN_PLUGIN_PATH . 'includes/class-activator.php';
		if ( file_exists( $path ) ) {
			require_once $path;
			if ( class_exists( '\\Krixen\\Activator' ) ) {
				\Krixen\Activator::activate();
			}
		}
	}
} );

/**
 * Initialize plugin
 */
add_action( 'plugins_loaded', function (): void {
	// Load text domain for translations
	load_plugin_textdomain( 'krixen', false, dirname( KRIXEN_PLUGIN_BASENAME ) . '/languages' );
	
	// Initialize core managers
	new Krixen\Room_Manager();
	new Krixen\Booking_Manager();
	
	// Ensure Elementor widget file is loaded
	$el_widget = KRIXEN_PLUGIN_PATH . 'includes/class-elementor-widget.php';
	if ( file_exists( $el_widget ) ) {
		require_once $el_widget;
	}
	
	// Register admin pages
	add_action( 'admin_menu', 'krixen_register_admin_pages' );
	
	// Register AJAX handlers
	add_action( 'wp_ajax_krixen_overview_stats', 'krixen_ajax_overview_stats' );
	add_action( 'wp_ajax_krixen_fetch_bookings_table', 'krixen_ajax_fetch_bookings_table' );
	add_action( 'wp_ajax_krixen_get_booking', 'krixen_ajax_get_booking' );
	
	// Configure email settings
	add_filter( 'wp_mail_from_name', fn( $name ) => 'Krixen Booking' );
	add_filter( 'wp_mail_from', fn( $email ) => get_option( 'krixen_from_email', 'no-reply@' . wp_parse_url( home_url(), PHP_URL_HOST ) ) );
} );

/**
 * Enqueue media scripts for admin pages
 */
add_action( 'admin_enqueue_scripts', function ( string $hook ): void {
	if ( isset( $_GET['page'] ) && in_array( $_GET['page'], [ 'krixen-booking', 'krixen-settings' ], true ) ) {
		wp_enqueue_media();
		wp_enqueue_style( 'krixen-admin', KRIXEN_PLUGIN_URL . 'assets/css/admin.css', [], KRIXEN_VERSION );
	}
} );

/**
 * Register admin pages
 *
 * @return void
 */
function krixen_register_admin_pages(): void {
	// Overview page
	add_submenu_page(
		'krixen-booking',
		__( 'Overview', 'krixen' ),
		__( 'Overview', 'krixen' ),
		'manage_options',
		'krixen-overview',
		'krixen_render_overview_page'
	);
	
	// Settings page
	add_submenu_page(
		'krixen-booking',
		__( 'Settings', 'krixen' ),
		__( 'Settings', 'krixen' ),
		'manage_options',
		'krixen-settings',
		'krixen_render_settings_page'
	);
}

/**
 * Render overview page
 *
 * @return void
 */
function krixen_render_overview_page(): void {
	global $wpdb;
	
	$rooms_table    = $wpdb->prefix . 'krixen_rooms';
	$bookings_table = $wpdb->prefix . 'krixen_bookings';
	
	$total_rooms    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$rooms_table}" );
	$total_bookings = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$bookings_table}" );
	
	$today = current_time( 'Y-m-d' );
	$now   = current_time( 'H:i:s' );
	
	$upcoming = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM {$bookings_table} WHERE date > %s OR (date = %s AND end_time > %s)",
		$today,
		$today,
		$now
	) );
	
	$occupied_room_ids = $wpdb->get_col( $wpdb->prepare(
		"SELECT DISTINCT room_id FROM {$bookings_table} WHERE date = %s AND start_time <= %s AND end_time > %s",
		$today,
		$now,
		$now
	) );
	
	$available_rooms = $total_rooms - count( $occupied_room_ids );
	$logo            = get_option( 'krixen_admin_logo_url', '' );
	
	?>
	<div class="wrap krixen-admin-wrap">
		<h1><?php echo esc_html__( 'Overview', 'krixen' ); ?></h1>
		
		<?php if ( $logo ) : ?>
			<div class="krixen-logo-container">
				<img src="<?php echo esc_url( $logo ); ?>" alt="Krixen" class="krixen-admin-logo" />
			</div>
		<?php endif; ?>
		
		<div class="krixen-stats" id="krixen-stats">
			<?php krixen_render_stat_card( __( 'Total Rooms', 'krixen' ), $total_rooms, 'dashicons-building' ); ?>
			<?php krixen_render_stat_card( __( 'Total Bookings', 'krixen' ), $total_bookings, 'dashicons-calendar-alt' ); ?>
			<?php krixen_render_stat_card( __( 'Upcoming Bookings', 'krixen' ), $upcoming, 'dashicons-clock' ); ?>
			<?php krixen_render_stat_card( __( 'Available Rooms', 'krixen' ), $available_rooms, 'dashicons-yes-alt' ); ?>
		</div>
	</div>
	
	<script>
	// Auto-refresh stats every 15 seconds
	setInterval(function() {
		jQuery.post(ajaxurl, {action: 'krixen_overview_stats'}, function(html) {
			jQuery('#krixen-stats').html(html);
		});
	}, 15000);
	</script>
	<?php
}

/**
 * Render stat card
 *
 * @param string $title Stat title.
 * @param int    $value Stat value.
 * @param string $icon  Dashicon class.
 * @return void
 */
function krixen_render_stat_card( string $title, int $value, string $icon = '' ): void {
	?>
	<div class="krixen-stat-card">
		<?php if ( $icon ) : ?>
			<span class="dashicons <?php echo esc_attr( $icon ); ?> krixen-stat-icon"></span>
		<?php endif; ?>
		<div class="krixen-stat-content">
			<div class="krixen-stat-title"><?php echo esc_html( $title ); ?></div>
			<div class="krixen-stat-value"><?php echo esc_html( number_format_i18n( $value ) ); ?></div>
		</div>
	</div>
	<?php
}

/**
 * AJAX handler for overview stats refresh
 *
 * @return void
 */
function krixen_ajax_overview_stats(): void {
	global $wpdb;
	
	$rooms_table    = $wpdb->prefix . 'krixen_rooms';
	$bookings_table = $wpdb->prefix . 'krixen_bookings';
	
	$total_rooms    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$rooms_table}" );
	$total_bookings = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$bookings_table}" );
	
	$today = current_time( 'Y-m-d' );
	$now   = current_time( 'H:i:s' );
	
	$upcoming = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM {$bookings_table} WHERE date > %s OR (date = %s AND end_time > %s)",
		$today,
		$today,
		$now
	) );
	
	$occupied_room_ids = $wpdb->get_col( $wpdb->prepare(
		"SELECT DISTINCT room_id FROM {$bookings_table} WHERE date = %s AND start_time <= %s AND end_time > %s",
		$today,
		$now,
		$now
	) );
	
	$available_rooms = $total_rooms - count( $occupied_room_ids );
	
	ob_start();
	krixen_render_stat_card( __( 'Total Rooms', 'krixen' ), $total_rooms, 'dashicons-building' );
	krixen_render_stat_card( __( 'Total Bookings', 'krixen' ), $total_bookings, 'dashicons-calendar-alt' );
	krixen_render_stat_card( __( 'Upcoming Bookings', 'krixen' ), $upcoming, 'dashicons-clock' );
	krixen_render_stat_card( __( 'Available Rooms', 'krixen' ), $available_rooms, 'dashicons-yes-alt' );
	wp_die( ob_get_clean() );
}

/**
 * Render settings page
 *
 * @return void
 */
function krixen_render_settings_page(): void {
	// Handle form submission
	if ( isset( $_POST['krixen_settings_nonce'] ) && wp_verify_nonce( $_POST['krixen_settings_nonce'], 'krixen_save_settings' ) ) {
		update_option( 'krixen_delete_on_uninstall', isset( $_POST['delete_on_uninstall'] ) ? '1' : '0' );
		update_option( 'krixen_logo_url', isset( $_POST['krixen_logo_url'] ) ? esc_url_raw( $_POST['krixen_logo_url'] ) : '' );
		update_option( 'krixen_admin_logo_url', isset( $_POST['krixen_admin_logo_url'] ) ? esc_url_raw( $_POST['krixen_admin_logo_url'] ) : '' );
		update_option( 'krixen_admin_email', isset( $_POST['krixen_admin_email'] ) ? sanitize_email( $_POST['krixen_admin_email'] ) : get_option( 'admin_email' ) );
		update_option( 'krixen_from_email', isset( $_POST['krixen_from_email'] ) ? sanitize_email( $_POST['krixen_from_email'] ) : '' );
		
		echo '<div class="updated notice"><p>' . esc_html__( 'Settings saved successfully.', 'krixen' ) . '</p></div>';
	}
	
	$delete_on_uninstall = get_option( 'krixen_delete_on_uninstall', '0' );
	$logo_url            = get_option( 'krixen_logo_url', '' );
	$admin_logo_url      = get_option( 'krixen_admin_logo_url', '' );
	$admin_email         = get_option( 'krixen_admin_email', get_option( 'admin_email' ) );
	$from_email          = get_option( 'krixen_from_email', 'no-reply@' . wp_parse_url( home_url(), PHP_URL_HOST ) );
	
	?>
	<div class="wrap krixen-admin-wrap">
		<h1><?php echo esc_html__( 'Settings', 'krixen' ); ?></h1>
		
		<form method="post" class="krixen-settings-form">
			<?php wp_nonce_field( 'krixen_save_settings', 'krixen_settings_nonce' ); ?>
			
			<table class="form-table">
				<tr>
					<th scope="row"><?php echo esc_html__( 'Data Management', 'krixen' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="delete_on_uninstall" value="1" <?php checked( '1', $delete_on_uninstall ); ?> />
							<?php echo esc_html__( 'Delete all data on plugin uninstall', 'krixen' ); ?>
						</label>
						<p class="description"><?php echo esc_html__( 'Check this to remove all rooms and bookings when the plugin is uninstalled.', 'krixen' ); ?></p>
					</td>
				</tr>
			</table>
			
			<h2><?php echo esc_html__( 'Branding', 'krixen' ); ?></h2>
			<table class="form-table">
				<tr>
					<th scope="row"><label for="krixen_logo_url"><?php echo esc_html__( 'Site Logo (Frontend)', 'krixen' ); ?></label></th>
					<td>
						<input type="text" name="krixen_logo_url" id="krixen_logo_url" value="<?php echo esc_attr( $logo_url ); ?>" class="regular-text" />
						<button type="button" class="button" id="krixen_logo_button"><?php echo esc_html__( 'Upload', 'krixen' ); ?></button>
						<div class="krixen-logo-preview">
							<img id="krixen_logo_preview" src="<?php echo esc_url( $logo_url ); ?>" style="<?php echo $logo_url ? '' : 'display:none;'; ?>" />
						</div>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="krixen_admin_logo_url"><?php echo esc_html__( 'Admin Logo', 'krixen' ); ?></label></th>
					<td>
						<input type="text" name="krixen_admin_logo_url" id="krixen_admin_logo_url" value="<?php echo esc_attr( $admin_logo_url ); ?>" class="regular-text" />
						<button type="button" class="button" id="krixen_admin_logo_button"><?php echo esc_html__( 'Upload', 'krixen' ); ?></button>
						<div class="krixen-logo-preview">
							<img id="krixen_admin_logo_preview" src="<?php echo esc_url( $admin_logo_url ); ?>" style="<?php echo $admin_logo_url ? '' : 'display:none;'; ?>" />
						</div>
					</td>
				</tr>
			</table>
			
			<h2><?php echo esc_html__( 'Email Notifications', 'krixen' ); ?></h2>
			<table class="form-table">
				<tr>
					<th scope="row"><label for="krixen_admin_email"><?php echo esc_html__( 'Admin Email', 'krixen' ); ?></label></th>
					<td>
						<input type="email" name="krixen_admin_email" id="krixen_admin_email" value="<?php echo esc_attr( $admin_email ); ?>" class="regular-text" />
						<p class="description"><?php echo esc_html__( 'Receive booking notifications at this email address.', 'krixen' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="krixen_from_email"><?php echo esc_html__( 'From Email', 'krixen' ); ?></label></th>
					<td>
						<input type="email" name="krixen_from_email" id="krixen_from_email" value="<?php echo esc_attr( $from_email ); ?>" class="regular-text" />
						<p class="description"><?php echo esc_html__( 'Email address to use in the "From" field for booking emails.', 'krixen' ); ?></p>
					</td>
				</tr>
			</table>
			
			<?php submit_button(); ?>
		</form>
	</div>
	
	<script>
	jQuery(function($) {
		function openMediaPicker(targetInput, previewImg) {
			var frame = wp.media({
				title: '<?php echo esc_js( __( 'Select Logo', 'krixen' ) ); ?>',
				button: { text: '<?php echo esc_js( __( 'Use this logo', 'krixen' ) ); ?>' },
				library: { type: ['image'] },
				multiple: false
			});
			
			frame.on('select', function() {
				var attachment = frame.state().get('selection').first().toJSON();
				$(targetInput).val(attachment.url);
				$(previewImg).attr('src', attachment.url).show();
			});
			
			frame.open();
		}
		
		$('#krixen_logo_button').on('click', function(e) {
			e.preventDefault();
			openMediaPicker('#krixen_logo_url', '#krixen_logo_preview');
		});
		
		$('#krixen_admin_logo_button').on('click', function(e) {
			e.preventDefault();
			openMediaPicker('#krixen_admin_logo_url', '#krixen_admin_logo_preview');
		});
	});
	</script>
	<?php
}

/**
 * AJAX handler for fetching bookings table
 *
 * @return void
 */
function krixen_ajax_fetch_bookings_table(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( '' );
	}
	
	global $wpdb;
	$table = $wpdb->prefix . 'krixen_bookings';
	$scope = isset( $_POST['scope'] ) ? sanitize_text_field( $_POST['scope'] ) : '';
	
	$where  = [];
	$params = [];
	$today  = current_time( 'Y-m-d' );
	$now    = current_time( 'H:i:s' );
	
	if ( $scope === 'today' ) {
		$where[]  = 'date=%s';
		$params[] = $today;
	} elseif ( $scope === 'upcoming' ) {
		$where[]  = '(date > %s OR (date=%s AND end_time > %s))';
		$params[] = $today;
		$params[] = $today;
		$params[] = $now;
	}
	
	$whereSql = $where ? ( 'WHERE ' . implode( ' AND ', $where ) ) : '';
	$rows     = $params 
		? $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} {$whereSql} ORDER BY date ASC, start_time ASC", $params ) )
		: $wpdb->get_results( "SELECT * FROM {$table} {$whereSql} ORDER BY date ASC, start_time ASC" );
	
	foreach ( $rows as $row ) {
		$room = $wpdb->get_var( $wpdb->prepare( 'SELECT name FROM ' . $wpdb->prefix . 'krixen_rooms WHERE id=%d', $row->room_id ) );
		
		echo '<tr>';
		echo '<td>' . esc_html( $row->full_name ) . '</td>';
		echo '<td>' . esc_html( $row->email ) . '</td>';
		echo '<td>' . esc_html( $room ) . '</td>';
		echo '<td>' . esc_html( date_i18n( 'F j, Y', strtotime( $row->date ) ) ) . '</td>';
		echo '<td>' . esc_html( date_i18n( 'h:i A', strtotime( $row->start_time ) ) . ' - ' . date_i18n( 'h:i A', strtotime( $row->end_time ) ) ) . '</td>';
		echo '<td>' . esc_html( ucfirst( $row->status ) ) . '</td>';
		echo '<td><a href="#" class="krixen-view-booking" data-id="' . esc_attr( $row->id ) . '">' . esc_html__( 'View', 'krixen' ) . '</a></td>';
		echo '</tr>';
	}
	
	wp_die();
}

/**
 * AJAX handler for getting single booking
 *
 * @return void
 */
function krixen_ajax_get_booking(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error();
	}
	
	global $wpdb;
	$table = $wpdb->prefix . 'krixen_bookings';
	$id    = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
	
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id=%d", $id ) );
	
	if ( ! $row ) {
		wp_send_json_error();
	}
	
	$room = $wpdb->get_var( $wpdb->prepare( 'SELECT name FROM ' . $wpdb->prefix . 'krixen_rooms WHERE id=%d', $row->room_id ) );
	
	ob_start();
	?>
	<table class="widefat">
		<tr>
			<th><?php echo esc_html__( 'Name', 'krixen' ); ?></th>
			<td><?php echo esc_html( $row->full_name ); ?></td>
		</tr>
		<tr>
			<th><?php echo esc_html__( 'Email', 'krixen' ); ?></th>
			<td><?php echo esc_html( $row->email ); ?></td>
		</tr>
		<tr>
			<th><?php echo esc_html__( 'Room', 'krixen' ); ?></th>
			<td><?php echo esc_html( $room ); ?></td>
		</tr>
		<tr>
			<th><?php echo esc_html__( 'Date', 'krixen' ); ?></th>
			<td><?php echo esc_html( date_i18n( 'F j, Y', strtotime( $row->date ) ) ); ?></td>
		</tr>
		<tr>
			<th><?php echo esc_html__( 'Time', 'krixen' ); ?></th>
			<td><?php echo esc_html( date_i18n( 'h:i A', strtotime( $row->start_time ) ) . ' - ' . date_i18n( 'h:i A', strtotime( $row->end_time ) ) ); ?></td>
		</tr>
		<tr>
			<th><?php echo esc_html__( 'Status', 'krixen' ); ?></th>
			<td><?php echo esc_html( ucfirst( $row->status ) ); ?></td>
		</tr>
	</table>
	<?php
	wp_send_json_success( ob_get_clean() );
}
