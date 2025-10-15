<?php
/*
Plugin Name: Krixen Meeting Room Booking
Description: A simple yet powerful meeting room booking plugin.
Version: 1.0.0
Author: UGRO
License: GPL2
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Autoloader (simple PSR-4–ish)
spl_autoload_register( function ( $class ) {
    // Only load classes from our namespace.
    if ( strpos( $class, 'Krixen\\' ) !== 0 ) {
        return;
    }

    // Remove namespace and convert to file name.
    $relative_class = str_replace( 'Krixen\\', '', $class );
    // Convert namespace separators and underscores to hyphens, make lowercase, and prepend "class-".
    $file = 'class-' . strtolower( str_replace( [ '\\', '_' ], '-', $relative_class ) ) . '.php';

    $path = plugin_dir_path( __FILE__ ) . 'includes/' . $file;

    if ( file_exists( $path ) ) {
        require_once $path;
    }
} );

// Constants
define( 'KRIXEN_PLUGIN_FILE', __FILE__ );

define( 'KRIXEN_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

define( 'KRIXEN_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );

// Activation hook
register_activation_hook( __FILE__, function () {
    if ( class_exists( '\\Krixen\\Activator' ) ) {
        \Krixen\Activator::activate();
    } else {
        // Fallback: try loading the activator explicitly
        $path = plugin_dir_path( __FILE__ ) . 'includes/class-activator.php';
        if ( file_exists( $path ) ) {
            require_once $path;
            if ( class_exists( '\\Krixen\\Activator' ) ) {
                \Krixen\Activator::activate();
            }
        }
    }
} );

// Init plugin
add_action( 'plugins_loaded', function () {
    new Krixen\Room_Manager();
    new Krixen\Booking_Manager();
    // Ensure Elementor widget file is loaded so the widget registers
    $el_widget = plugin_dir_path( __FILE__ ) . 'includes/class-elementor-widget.php';
    if ( file_exists( $el_widget ) ) {
        require_once $el_widget;
    }
    // Admin: Overview and Settings menu stubs
    add_action('admin_menu', function(){
        add_submenu_page('krixen-booking', __( 'Overview', 'krixen'), __( 'Overview', 'krixen'), 'manage_options', 'krixen-overview', function(){
            global $wpdb;
            $rooms_tbl = $wpdb->prefix . 'krixen_rooms';
            $book_tbl  = $wpdb->prefix . 'krixen_bookings';
            $total_rooms = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$rooms_tbl}");
            $total_bookings = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$book_tbl}");
            $today = current_time('Y-m-d');
            $now   = current_time('H:i:s');
            $upcoming = (int) $wpdb->get_var( $wpdb->prepare("SELECT COUNT(*) FROM {$book_tbl} WHERE date > %s OR (date = %s AND end_time > %s)", $today, $today, $now) );
            $occupied_room_ids = $wpdb->get_col( $wpdb->prepare("SELECT DISTINCT room_id FROM {$book_tbl} WHERE date = %s AND start_time <= %s AND end_time > %s", $today, $now, $now) );
            $available_rooms = $total_rooms - count($occupied_room_ids);
            $logo = get_option('krixen_admin_logo_url','');
            echo '<div class="wrap"><h1>'.esc_html__('Overview','krixen').'</h1>';
            if($logo){ echo '<div style="margin:8px 0;"><img src="'.esc_url($logo).'" alt="Krixen" style="max-width:160px;height:auto;"/></div>'; }
            echo '<div class="krixen-stats" style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;">';
            $card = function($title,$value){ echo '<div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:16px;"><div style="font-size:12px;color:#6b7280;">'.esc_html($title).'</div><div style="font-size:24px;font-weight:700;">'.esc_html($value).'</div></div>'; };
            $card(__('Total Rooms','krixen'), $total_rooms);
            $card(__('Total Bookings','krixen'), $total_bookings);
            $card(__('Upcoming Bookings','krixen'), $upcoming);
            $card(__('Currently Available Rooms','krixen'), $available_rooms);
            echo '</div></div>';
            // Simple polling to refresh stats
            echo '<script>setInterval(function(){jQuery.post(ajaxurl,{action:"krixen_overview_stats"},function(html){jQuery(".krixen-stats").replaceWith(html);});},15000);</script>';
        });
        add_submenu_page('krixen-booking', __( 'Settings', 'krixen'), __( 'Settings', 'krixen'), 'manage_options', 'krixen-settings', function(){
            if ( isset($_POST['krixen_settings_nonce']) && wp_verify_nonce($_POST['krixen_settings_nonce'], 'krixen_save_settings') ) {
                update_option('krixen_delete_on_uninstall', isset($_POST['delete_on_uninstall']) ? '1' : '0');
                if ( isset($_POST['krixen_logo_url']) ) { update_option('krixen_logo_url', esc_url_raw($_POST['krixen_logo_url']) ); }
                if ( isset($_POST['krixen_admin_logo_url']) ) { update_option('krixen_admin_logo_url', esc_url_raw($_POST['krixen_admin_logo_url']) ); }
                if ( isset($_POST['krixen_admin_email']) ) { update_option('krixen_admin_email', sanitize_email($_POST['krixen_admin_email']) ); }
                echo '<div class="updated notice"><p>'.esc_html__('Settings saved.','krixen').'</p></div>';
            }
            $del = get_option('krixen_delete_on_uninstall','0');
            $logo = get_option('krixen_logo_url','');
            $admin_logo = get_option('krixen_admin_logo_url','');
            $admin_email = get_option('krixen_admin_email', get_option('admin_email'));
            echo '<div class="wrap"><h1>'.esc_html__('Settings','krixen').'</h1>';
            echo '<form method="post">';
            wp_nonce_field('krixen_save_settings','krixen_settings_nonce');
            echo '<label><input type="checkbox" name="delete_on_uninstall" value="1" '.checked('1',$del,false).'/> '.esc_html__('Delete data on uninstall','krixen').'</label>';
            echo '<h2 style="margin-top:20px;">'.esc_html__('Branding','krixen').'</h2>';
            echo '<p>'.esc_html__('Site Logo (optional)','krixen').'</p>';
            echo '<input type="text" name="krixen_logo_url" id="krixen_logo_url" value="'.esc_attr($logo).'" class="regular-text" /> ';
            echo '<button type="button" class="button" id="krixen_logo_button">'.esc_html__('Upload','krixen').'</button>';
            echo '<div><img id="krixen_logo_preview" src="'.esc_url($logo).'" style="max-width:180px;height:auto;margin-top:8px;'.($logo?'':'display:none;').'"/></div>';
            echo '<p style="margin-top:16px;">'.esc_html__('Admin Dashboard Logo (optional)','krixen').'</p>';
            echo '<input type="text" name="krixen_admin_logo_url" id="krixen_admin_logo_url" value="'.esc_attr($admin_logo).'" class="regular-text" /> ';
            echo '<button type="button" class="button" id="krixen_admin_logo_button">'.esc_html__('Upload','krixen').'</button>';
            echo '<div><img id="krixen_admin_logo_preview" src="'.esc_url($admin_logo).'" style="max-width:180px;height:auto;margin-top:8px;'.($admin_logo?'':'display:none;').'"/></div>';
            echo '<h2 style="margin-top:20px;">'.esc_html__('Notifications','krixen').'</h2>';
            echo '<p>'.esc_html__('Admin notification email','krixen').'</p>';
            echo '<input type="email" name="krixen_admin_email" value="'.esc_attr($admin_email).'" class="regular-text" />';
            submit_button();
            echo '</form></div>';
            // Media uploader
            echo '<script>jQuery(function($){
                function openPicker(target, preview){ var frame = wp.media({title:"Select Logo",button:{text:"Use this logo"},library:{type:["image"]},multiple:false}); frame.on("select", function(){ var att=frame.state().get("selection").first().toJSON(); $(target).val(att.url); if(preview){ $(preview).attr("src", att.url).show(); } }); frame.open(); }
                $("#krixen_logo_button").on("click", function(e){ e.preventDefault(); openPicker("#krixen_logo_url", "#krixen_logo_preview"); });
                $("#krixen_admin_logo_button").on("click", function(e){ e.preventDefault(); openPicker("#krixen_admin_logo_url", "#krixen_admin_logo_preview"); });
            });</script>';
        });
    });
    // AJAX for overview refresh
    add_action('wp_ajax_krixen_overview_stats', function(){
        global $wpdb;
        $rooms_tbl = $wpdb->prefix . 'krixen_rooms';
        $book_tbl  = $wpdb->prefix . 'krixen_bookings';
        $total_rooms = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$rooms_tbl}");
        $total_bookings = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$book_tbl}");
        $today = current_time('Y-m-d');
        $now   = current_time('H:i:s');
        $upcoming = (int) $wpdb->get_var( $wpdb->prepare("SELECT COUNT(*) FROM {$book_tbl} WHERE date > %s OR (date = %s AND end_time > %s)", $today, $today, $now) );
        $occupied_room_ids = $wpdb->get_col( $wpdb->prepare("SELECT DISTINCT room_id FROM {$book_tbl} WHERE date = %s AND start_time <= %s AND end_time > %s", $today, $now, $now) );
        $available_rooms = $total_rooms - count($occupied_room_ids);
        ob_start();
        echo '<div class="krixen-stats" style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;">';
        $card = function($title,$value){ echo '<div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:16px;"><div style="font-size:12px;color:#6b7280;">'.esc_html($title).'</div><div style="font-size:24px;font-weight:700;">'.esc_html($value).'</div></div>'; };
        $card(__('Total Rooms','krixen'), $total_rooms);
        $card(__('Total Bookings','krixen'), $total_bookings);
        $card(__('Upcoming Bookings','krixen'), $upcoming);
        $card(__('Currently Available Rooms','krixen'), $available_rooms);
        echo '</div>';
        wp_die(ob_get_clean());
    });
    // Admin bookings AJAX fragments
    add_action('wp_ajax_krixen_fetch_bookings_table', function(){
        if ( ! current_user_can('manage_options') ) { wp_die(''); }
        global $wpdb; $table = $wpdb->prefix . 'krixen_bookings';
        $scope = isset($_POST['scope']) ? sanitize_text_field($_POST['scope']) : '';
        $where = [];$params=[]; $today = current_time('Y-m-d'); $now = current_time('H:i:s');
        if($scope==='today'){ $where[]='date=%s'; $params[]=$today; }
        if($scope==='upcoming'){ $where[]='(date > %s OR (date=%s AND end_time > %s))'; $params[]=$today; $params[]=$today; $params[]=$now; }
        $whereSql = $where?('WHERE '.implode(' AND ',$where)) : '';
        $rows = $params? $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} {$whereSql} ORDER BY date ASC, start_time ASC", $params)) : $wpdb->get_results("SELECT * FROM {$table} {$whereSql} ORDER BY date ASC, start_time ASC");
        foreach($rows as $row){
            echo '<tr>';
            echo '<td>'.esc_html($row->full_name).'</td>';
            echo '<td>'.esc_html($row->email).'</td>';
            $room = $wpdb->get_var( $wpdb->prepare('SELECT name FROM '.$wpdb->prefix.'krixen_rooms WHERE id=%d', $row->room_id) );
            echo '<td>'.esc_html($room).'</td>';
            echo '<td>'.esc_html(date_i18n('F j, Y', strtotime($row->date))).'</td>';
            echo '<td>'.esc_html(date_i18n('h:i A', strtotime($row->start_time)).' - '.date_i18n('h:i A', strtotime($row->end_time))).'</td>';
            echo '<td>'.esc_html(ucfirst($row->status)).'</td>';
            echo '<td><a href="#" class="krixen-view-booking" data-id="'.esc_attr($row->id).'">'.esc_html__('View','krixen').'</a></td>';
            echo '</tr>';
        }
        wp_die();
    });
    add_action('wp_ajax_krixen_get_booking', function(){
        if ( ! current_user_can('manage_options') ) { wp_send_json_error(); }
        global $wpdb; $table = $wpdb->prefix.'krixen_bookings';
        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
        $row = $wpdb->get_row( $wpdb->prepare("SELECT * FROM {$table} WHERE id=%d", $id) );
        if(!$row){ wp_send_json_error(); }
        $room = $wpdb->get_var( $wpdb->prepare('SELECT name FROM '.$wpdb->prefix.'krixen_rooms WHERE id=%d', $row->room_id) );
        ob_start();
        echo '<table class="widefat">';
        echo '<tr><th>'.esc_html__('Name','krixen').'</th><td>'.esc_html($row->full_name).'</td></tr>';
        echo '<tr><th>'.esc_html__('Email','krixen').'</th><td>'.esc_html($row->email).'</td></tr>';
        echo '<tr><th>'.esc_html__('Room','krixen').'</th><td>'.esc_html($room).'</td></tr>';
        echo '<tr><th>'.esc_html__('Date','krixen').'</th><td>'.esc_html(date_i18n('F j, Y', strtotime($row->date))).'</td></tr>';
        echo '<tr><th>'.esc_html__('Time','krixen').'</th><td>'.esc_html(date_i18n('h:i A', strtotime($row->start_time)).' - '.date_i18n('h:i A', strtotime($row->end_time))).'</td></tr>';
        echo '<tr><th>'.esc_html__('Status','krixen').'</th><td>'.esc_html(ucfirst($row->status)).'</td></tr>';
        echo '</table>';
        wp_send_json_success(ob_get_clean());
    });
    // Email sender configuration
    add_filter('wp_mail_from_name', function($name){ return 'Krixen'; });
    add_filter('wp_mail_from', function($email){ return 'no-reply@krixen.com'; });
} );

// Enqueue media scripts for our admin pages (Rooms and Settings)
add_action('admin_enqueue_scripts', function($hook){
    if ( isset($_GET['page']) && in_array($_GET['page'], ['krixen-booking','krixen-settings'], true) ) {
        wp_enqueue_media();
    }
});