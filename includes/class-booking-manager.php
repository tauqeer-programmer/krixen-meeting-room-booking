<?php
namespace Krixen;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Booking_Manager {

    const TABLE = 'krixen_bookings';

    public function __construct() {
        add_shortcode( 'krixen_meeting_booking', [ $this, 'render_booking_form' ] );
        add_action( 'wp_ajax_nopriv_krixen_submit_booking', [ $this, 'handle_booking' ] );
        add_action( 'wp_ajax_krixen_submit_booking', [ $this, 'handle_booking' ] );
        add_action( 'wp_ajax_nopriv_krixen_check_availability', [ $this, 'ajax_check_availability' ] );
        add_action( 'wp_ajax_krixen_check_availability', [ $this, 'ajax_check_availability' ] );
        add_action( 'wp_ajax_nopriv_get_krixen_time_slots', [ $this, 'ajax_get_time_slots' ] );
        add_action( 'wp_ajax_get_krixen_time_slots', [ $this, 'ajax_get_time_slots' ] );

        add_action( 'admin_menu', [ $this, 'register_submenu' ] );
    }

    public static function create_table() {
        global $wpdb;
        $table_name      = $wpdb->prefix . self::TABLE;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            full_name VARCHAR(191) NOT NULL,
            email VARCHAR(191) NOT NULL,
            room_id BIGINT UNSIGNED NOT NULL,
            date DATE NOT NULL,
            start_time TIME NOT NULL,
            end_time TIME NOT NULL,
            attendees INT UNSIGNED NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'booked',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            INDEX room_date (room_id, date)
        ) {$charset_collate};";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    public function enqueue_assets() {
        // Tailwind CDN and Google Fonts for the new design
        wp_enqueue_script( 'krixen-tailwind', 'https://cdn.tailwindcss.com', [], null, false );
        wp_enqueue_style( 'krixen-font-inter', 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap', [], null );

        // Our base styles (also holds a few utility classes for animations)
        wp_enqueue_style( 'krixen-style', KRIXEN_PLUGIN_URL . 'assets/css/style.css', [], '1.0.0' );

        // Frontend logic for the new Tailwind UI
        wp_enqueue_script( 'krixen-js', KRIXEN_PLUGIN_URL . 'assets/js/form.js', [ 'jquery' ], '1.1.0', true );
        wp_localize_script( 'krixen-js', 'KrixenBooking', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'krixen_booking_nonce' ),
            'logo_url' => get_option('krixen_logo_url',''),
            'site_tz'  => wp_timezone_string(),
        ] );
    }

    /**
     * Render booking form via shortcode
     */
    public function render_booking_form() {
        // Enqueue assets only when shortcode renders
        $this->enqueue_assets();
        ob_start();
        $rooms = Room_Manager::get_rooms();
        // Prefill defaults: today + next 30 min rounded
        $timestamp = current_time('timestamp');
        $minutes   = (int) date('i', $timestamp);
        $add       = ( $minutes % 30 === 0 ) ? 0 : (30 - ($minutes % 30));
        $rounded   = $timestamp + ($add * 60);
        $default_start = date('H:i', $rounded);
        $default_end   = date('H:i', $rounded + 60 * 60);
        $default_date  = date('Y-m-d', $timestamp);
        ?>
        <div class="krixen-tw font-[Inter,sans-serif]">
            <div class="min-h-screen flex flex-col items-center justify-center p-4 lg:p-8">
                <div class="w-full max-w-5xl mx-auto">
                    <header class="text-center mb-10">
                        <h1 class="text-4xl md:text-5xl font-bold text-gray-800">
                            K<span class="text-orange-600">C</span>W
                        </h1>
                        <p class="text-lg text-gray-600 mt-2">Krixen Conference Workspace</p>
                        <h2 class="text-3xl font-bold text-gray-800 mt-4"><?php echo esc_html__('Krixen Booking','krixen'); ?></h2>
                    </header>

                    <main>
                        <section id="roomSelection">
                            <h2 class="text-2xl font-semibold text-center mb-8 text-gray-700"><?php echo esc_html__('Select a Room to Begin','krixen'); ?></h2>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 md:gap-8">
                                <?php foreach ( $rooms as $room ) : ?>
                                    <div class="room-card bg-white p-6 rounded-xl shadow-md hover:shadow-xl transition-all duration-300 cursor-pointer border-2 border-transparent hover:border-orange-500" data-room-id="<?php echo esc_attr($room->id); ?>" data-room-name="<?php echo esc_attr($room->name); ?>" data-capacity="<?php echo esc_attr((int)$room->capacity); ?>">
                                        <h3 class="text-xl font-bold text-gray-900 mb-2"><?php echo esc_html($room->name); ?></h3>
                                        <div class="flex items-center text-gray-600 mb-6">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-2 text-orange-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                            </svg>
                                            <span class="font-medium"><?php echo esc_html( sprintf( __('%d People','krixen'), (int)$room->capacity ) ); ?></span>
                                        </div>
                                        <button class="select-room-btn w-full bg-orange-600 text-white py-2 rounded-lg font-semibold hover:bg-orange-700 transition-colors" type="button"><?php echo esc_html__('View Availability','krixen'); ?></button>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </section>

                        <section id="bookingSection" class="max-h-0 overflow-hidden transition-height duration-700 ease-in-out mt-12" aria-hidden="true">
                            <div class="bg-white p-6 md:p-8 rounded-xl shadow-lg">
                                <div class="flex justify-between items-start mb-6">
                                    <h2 id="bookingHeader" class="text-2xl font-semibold text-gray-800"></h2>
                                    <button id="closeBookingBtn" class="text-gray-500 hover:text-gray-800 transition-colors" type="button" aria-label="<?php echo esc_attr__('Close booking form','krixen'); ?>">
                                        <span class="text-3xl font-bold">&times;</span>
                                    </button>
                                </div>

                                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 lg:gap-12">
                                    <form id="bookingForm" class="space-y-6" method="post" novalidate>
                                        <?php wp_nonce_field( 'krixen_booking_action', 'krixen_booking_nonce_field' ); ?>
                                        <input type="hidden" name="room_id" id="room_id" value="" />
                                        <div>
                                            <label for="full_name" class="block text-sm font-medium text-gray-700 mb-1"><?php echo esc_html__('Full Name','krixen'); ?> *</label>
                                            <input type="text" id="full_name" name="full_name" required class="w-full p-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition shadow-sm" />
                                        </div>
                                        <div>
                                            <label for="email" class="block text-sm font-medium text-gray-700 mb-1"><?php echo esc_html__('Email','krixen'); ?> *</label>
                                            <input type="email" id="email" name="email" required class="w-full p-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition shadow-sm" />
                                        </div>
                                        <div>
                                            <label for="bookingDate" class="block text-sm font-medium text-gray-700 mb-1"><?php echo esc_html__('Date','krixen'); ?> *</label>
                                            <input type="date" id="bookingDate" name="date" required class="w-full p-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition shadow-sm" />
                                        </div>
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                            <div>
                                                <label for="startTime" class="block text-sm font-medium text-gray-700 mb-1"><?php echo esc_html__('Start Time','krixen'); ?> *</label>
                                                <input type="time" id="startTime" name="start_time" required class="w-full p-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-orange-500 shadow-sm" />
                                            </div>
                                            <div>
                                                <label for="endTime" class="block text-sm font-medium text-gray-700 mb-1"><?php echo esc_html__('End Time','krixen'); ?> *</label>
                                                <input type="time" id="endTime" name="end_time" required class="w-full p-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-orange-500 shadow-sm" />
                                            </div>
                                        </div>
                                        <div class="pt-4">
                                            <button id="bookNowBtn" type="submit" disabled class="w-full bg-orange-600 text-white py-3 px-6 rounded-lg font-semibold text-lg hover:bg-orange-700 disabled:bg-gray-400 disabled:cursor-not-allowed transition-all duration-300 transform hover:scale-105 disabled:scale-100">
                                                <span class="btn-text"><?php echo esc_html__('Book Now','krixen'); ?></span>
                                                <span class="btn-loader hidden">
                                                    <svg class="animate-spin h-5 w-5 mx-auto" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                    </svg>
                                                </span>
                                            </button>
                                            <p id="formError" class="text-red-500 text-sm mt-3 h-5 text-center"></p>
                                        </div>
                                    </form>

                                    <div>
                                        <h3 class="text-lg font-semibold text-gray-800 mb-4 text-center lg:text-left"><?php echo esc_html__('Availability for','krixen'); ?> <span id="timelineDate">Today</span></h3>
                                        <div id="bookingTimeline" class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3" id="timeline-slots">
                                                <div class="text-center text-gray-500 py-10 col-span-2 sm:col-span-3">
                                                    <p><?php echo esc_html__('Select a room to see availability.','krixen'); ?></p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </section>
                    </main>
                </div>
            </div>

            <div id="toast" class="fixed top-5 right-5 bg-green-500 text-white py-3 px-6 rounded-lg shadow-xl translate-x-[120%] transform transition-transform duration-500 ease-in-out">
                <p>🎉 <?php echo esc_html__('Booking Confirmed!','krixen'); ?></p>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function ajax_check_availability() {
        check_ajax_referer( 'krixen_booking_nonce', 'nonce' );
        $room_id = isset( $_POST['room_id'] ) ? absint( $_POST['room_id'] ) : 0;
        $date    = isset( $_POST['date'] ) ? sanitize_text_field( $_POST['date'] ) : '';
        if ( ! $room_id || ! $date ) {
            wp_send_json_error( __( 'Missing room or date.', 'krixen' ) );
        }
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;
        $rows  = $wpdb->get_results( $wpdb->prepare( "SELECT start_time, end_time FROM {$table} WHERE room_id=%d AND date=%s ORDER BY start_time ASC", $room_id, $date ) );
        wp_send_json_success( $rows );
    }

    public function ajax_get_time_slots() {
        check_ajax_referer( 'krixen_booking_nonce', 'nonce' );
        $room_id = isset($_POST['room_id']) ? absint($_POST['room_id']) : 0;
        $date    = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '';
        $duration= isset($_POST['duration']) ? max(1, min(3, absint($_POST['duration']))) : 1;
        if ( ! $room_id || ! $date ) {
            wp_send_json_error( __( 'Missing parameters.', 'krixen' ) );
        }
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;
        $bookings = $wpdb->get_results( $wpdb->prepare( "SELECT start_time, end_time FROM {$table} WHERE room_id=%d AND date=%s", $room_id, $date ) );

        $tz = wp_timezone();
        $open  = new \DateTimeImmutable($date.' 08:00:00', $tz);
        $close = new \DateTimeImmutable($date.' 21:00:00', $tz);
        $now   = new \DateTimeImmutable('now', $tz);
        $start = $open > $now ? $open : $now;
        // Round up to next 30 min
        $minute = (int) $start->format('i');
        $add = ($minute % 30 === 0) ? 0 : (30 - ($minute % 30));
        $start = $start->modify("+{$add} minutes");

        $slots = [];
        for ( $t = $start; $t < $close; $t = $t->modify('+30 minutes') ) {
            $end = $t->modify("+{$duration} hours");
            if ( $end > $close ) { break; }
            $overlaps = false;
            foreach ( $bookings as $b ) {
                $bs = new \DateTimeImmutable($date.' '.$b->start_time, $tz);
                $be = new \DateTimeImmutable($date.' '.$b->end_time, $tz);
                if ( $t < $be && $end > $bs ) { $overlaps = true; break; }
            }
            $slots[] = [
                'start_24' => $t->format('H:i'),
                'end_24'   => $end->format('H:i'),
                'label'    => $t->format('h:i A').' - '.$end->format('h:i A'),
                'available'=> ! $overlaps,
            ];
        }
        wp_send_json_success($slots);
    }

    /**
     * Handle booking ajax
     */
    public function handle_booking() {
        check_ajax_referer( 'krixen_booking_nonce', 'nonce' );

        $full_name = sanitize_text_field( $_POST['full_name'] );
        $email     = sanitize_email( $_POST['email'] );
        $room_id   = absint( $_POST['room_id'] );
        $date      = sanitize_text_field( $_POST['date'] );
        $start     = sanitize_text_field( $_POST['start_time'] );
        $end       = sanitize_text_field( $_POST['end_time'] );
        // attendees removed from form; keep DB-compatible default
        $attendees = 1;

        // Validate
        if ( ! $full_name || ! is_email( $email ) || ! $room_id || ! $date || ! $start || ! $end ) {
            wp_send_json_error( __( 'Please fill all mandatory fields correctly.', 'krixen' ) );
        }

        $room = Room_Manager::get_room( $room_id );
        if ( ! $room ) {
            wp_send_json_error( __( 'Room not found.', 'krixen' ) );
        }

        // Capacity check removed with attendees field.

        // Check overlapping
        if ( $this->is_overlapping( $room_id, $date, $start, $end ) ) {
            wp_send_json_error( __( 'This room is already booked for the selected time.', 'krixen' ) );
        }

        global $wpdb;
        $wpdb->insert( $wpdb->prefix . self::TABLE, [
            'full_name'  => $full_name,
            'email'      => $email,
            'room_id'    => $room_id,
            'date'       => $date,
            'start_time' => $start,
            'end_time'   => $end,
            'attendees'  => $attendees,
            'status'     => 'booked',
        ] );
        $booking_id = $wpdb->insert_id;

        // Send emails
        Email_Functions::send_user_confirmation( $email, [
            'booking_id'=> $booking_id,
            'name'      => $full_name,
            'room'      => $room->name,
            'date'      => $date,
            'start'     => $start,
            'end'       => $end,
        ] );
        Email_Functions::send_admin_alert( [
            'name'      => $full_name,
            'email'     => $email,
            'room'      => $room->name,
            'date'      => $date,
            'start'     => $start,
            'end'       => $end,
        ] );

        // Notify admin UI to refresh via transient
        set_transient('krixen_last_booking_change', time(), 60);
        wp_send_json_success( __( 'Your meeting room has been successfully booked. A confirmation email has been sent.', 'krixen' ) );
    }

    /**
     * Check overlapping booking
     */
    private function is_overlapping( $room_id, $date, $start, $end ) {
        global $wpdb;
        $query = $wpdb->prepare( "SELECT COUNT(*) FROM " . $wpdb->prefix . self::TABLE . " WHERE room_id = %d AND date = %s AND ( (start_time < %s AND end_time > %s) OR (start_time >= %s AND start_time < %s) )", $room_id, $date, $end, $start, $start, $end );
        $count = $wpdb->get_var( $query );
        return $count > 0;
    }

    /**
     * Register submenu for bookings list
     */
    public function register_submenu() {
        add_submenu_page( 'krixen-booking', __( 'Bookings', 'krixen' ), __( 'Bookings', 'krixen' ), 'manage_options', 'krixen-bookings', [ $this, 'bookings_page' ] );
    }

    /**
     * Render bookings list
     */
    public function bookings_page() {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;
        // Handle actions approve/cancel
        if ( isset($_GET['action'], $_GET['booking']) && in_array($_GET['action'], ['approve','cancel'], true) ) {
            $bid = absint($_GET['booking']);
            if ( wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'krixen_booking_action_'.$bid ) ) {
                $new = $_GET['action'] === 'approve' ? 'approved' : 'cancelled';
                $wpdb->update( $table, ['status'=>$new], ['id'=>$bid] );
                echo '<div class="updated notice"><p>'.esc_html__( 'Booking updated.', 'krixen' ).'</p></div>';
            }
        }
        // Filters
        $where = [];
        $params = [];
        if ( isset($_GET['filter_room']) && $_GET['filter_room'] !== '' ) {
            $where[] = 'b.room_id = %d';
            $params[] = absint($_GET['filter_room']);
        }
        if ( isset($_GET['filter_date']) && $_GET['filter_date'] !== '' ) {
            $where[] = 'b.date = %s';
            $params[] = sanitize_text_field($_GET['filter_date']);
        }
        if ( isset($_GET['filter_status']) && $_GET['filter_status'] !== '' ) {
            $where[] = 'b.status = %s';
            $params[] = sanitize_text_field($_GET['filter_status']);
        }
        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
        $sql = "SELECT b.*, r.name AS room_name FROM {$table} b JOIN " . $wpdb->prefix . Room_Manager::TABLE . " r ON r.id = b.room_id {$whereSql} ORDER BY b.date ASC, b.start_time ASC";
        $rows = $params ? $wpdb->get_results( $wpdb->prepare($sql, $params) ) : $wpdb->get_results($sql);

        // CSV export
        if ( isset($_GET['export']) && $_GET['export'] === 'csv' ) {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment;filename="krixen_bookings.csv"');
            $out = fopen('php://output', 'w');
            fputcsv($out, ['ID','Name','Email','Room','Date','Start','End','Attendees','Status']);
            foreach($rows as $row){
                fputcsv($out, [$row->id,$row->full_name,$row->email,$row->room_name,$row->date,$row->start_time,$row->end_time,$row->attendees,$row->status]);
            }
            fclose($out);
            exit;
        }
        ?>
        <div class="wrap">
            <h1><?php _e( 'Bookings', 'krixen' ); ?></h1>
            <form method="get" style="margin:10px 0;">
                <input type="hidden" name="page" value="krixen-bookings" />
                <select name="filter_room">
                    <option value=""><?php _e('All Rooms','krixen'); ?></option>
                    <?php foreach ( Room_Manager::get_rooms() as $room ): ?>
                        <option value="<?php echo esc_attr($room->id); ?>" <?php selected(isset($_GET['filter_room'])?intval($_GET['filter_room']):'', $room->id); ?>><?php echo esc_html($room->name); ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="date" name="filter_date" value="<?php echo isset($_GET['filter_date'])?esc_attr($_GET['filter_date']):''; ?>" />
                <select name="filter_status">
                    <option value=""><?php _e('All Statuses','krixen'); ?></option>
                    <?php foreach ( ['pending','approved','cancelled'] as $st ): ?>
                        <option value="<?php echo esc_attr($st); ?>" <?php selected(isset($_GET['filter_status'])?$_GET['filter_status']:'', $st); ?>><?php echo esc_html(ucfirst($st)); ?></option>
                    <?php endforeach; ?>
                </select>
                <?php submit_button(__('Filter','krixen'), 'secondary', '', false); ?>
                <a class="button" href="<?php echo esc_url( add_query_arg( array_merge($_GET, ['export'=>'csv']) ) ); ?>"><?php _e('Export CSV','krixen'); ?></a>
            </form>
            <div id="krixen-bookings-filters" style="margin:10px 0 6px;">
                <a href="<?php echo esc_url( add_query_arg(['scope'=>'today']) ); ?>" class="button"><?php _e('Today','krixen'); ?></a>
                <a href="<?php echo esc_url( add_query_arg(['scope'=>'upcoming']) ); ?>" class="button"><?php _e('Upcoming','krixen'); ?></a>
                <a href="<?php echo esc_url( remove_query_arg('scope') ); ?>" class="button"><?php _e('All','krixen'); ?></a>
            </div>
            <table class="wp-list-table widefat striped" id="krixen-bookings-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Room</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $rows as $row ) : ?>
                        <tr>
                            <td><?php echo esc_html( $row->full_name ); ?></td>
                            <td><?php echo esc_html( $row->email ); ?></td>
                            <td><?php echo esc_html( $row->room_name ); ?></td>
                            <td><?php echo esc_html( date_i18n('F j, Y', strtotime($row->date)) ); ?></td>
                            <td><?php echo esc_html( date_i18n('h:i A', strtotime($row->start_time)) . ' - ' . date_i18n('h:i A', strtotime($row->end_time)) ); ?></td>
                            <td><?php echo esc_html( ucfirst($row->status) ); ?></td>
                            <td>
                                <a href="#" class="krixen-view-booking" data-id="<?php echo esc_attr($row->id); ?>"><?php _e('View','krixen'); ?></a> |
                                <a href="<?php echo esc_url( wp_nonce_url( add_query_arg(['action'=>'cancel','booking'=>$row->id]), 'krixen_booking_action_'.$row->id ) ); ?>" onclick="return confirm('Are you sure?');"><?php _e('Cancel','krixen'); ?></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div id="krixen-booking-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.4);z-index:10000;">
                <div style="background:#fff;max-width:560px;margin:10% auto;padding:16px;border-radius:8px;">
                    <h2><?php _e('Booking Details','krixen'); ?></h2>
                    <div id="krixen-booking-modal-body"></div>
                    <p><button class="button" id="krixen-booking-modal-close"><?php _e('Close','krixen'); ?></button></p>
                </div>
            </div>
            <script>
            jQuery(function($){
                // Poll for updates
                setInterval(function(){ $.post(ajaxurl,{action:'krixen_fetch_bookings_table', scope:'<?php echo esc_js(isset($_GET['scope'])?$_GET['scope']:''); ?>'}, function(html){ $('#krixen-bookings-table tbody').html(html); }); }, 15000);
                $(document).on('click','.krixen-view-booking', function(e){ e.preventDefault(); var id=$(this).data('id'); $.post(ajaxurl,{action:'krixen_get_booking', id:id}, function(resp){ if(resp.success){ $('#krixen-booking-modal-body').html(resp.data); $('#krixen-booking-modal').show(); } }); });
                $('#krixen-booking-modal-close').on('click', function(){ $('#krixen-booking-modal').hide(); });
            });
            </script>
        </div>
        <?php
    }
}