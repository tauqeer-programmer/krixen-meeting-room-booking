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
        wp_enqueue_style( 'krixen-style', KRIXEN_PLUGIN_URL . 'assets/css/style.css', [], '1.0.0' );
        wp_enqueue_script( 'krixen-js', KRIXEN_PLUGIN_URL . 'assets/js/form.js', [ 'jquery' ], '1.0.0', true );
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
        <div class="krixen-brand">
            <?php $logo = get_option('krixen_logo_url',''); if ( $logo ) : ?>
                <img src="<?php echo esc_url($logo); ?>" alt="Krixen Booking" class="krixen-logo" />
            <?php else : ?>
                <div class="krixen-logo-text">Krixen Booking</div>
            <?php endif; ?>
        </div>
        <div id="krixen-room-status" class="krixen-room-status" style="display:none;"></div>
        <div class="krixen-rooms-grid">
            <?php foreach ( $rooms as $room ) : ?>
                <?php $img_url = ! empty( $room->image_url ) ? $room->image_url : KRIXEN_PLUGIN_URL . 'assets/img/no-room.svg'; ?>
                <div class="krixen-room-card" data-room-id="<?php echo esc_attr($room->id); ?>">
                    <img src="<?php echo esc_url($img_url); ?>" alt="<?php echo esc_attr($room->name); ?>" />
                    <div class="krixen-room-card-body">
                        <div class="krixen-room-name"><?php echo esc_html($room->name); ?></div>
                        <div class="krixen-room-capacity"><?php echo esc_html( sprintf( __( 'Capacity: %d', 'krixen' ), (int)$room->capacity ) ); ?></div>
                        <?php if ( ! empty($room->description) ) : ?><div class="krixen-room-desc"><?php echo esc_html($room->description); ?></div><?php endif; ?>
                        <div class="krixen-room-status-badge" data-status="unknown" aria-live="polite">&nbsp;</div>
                        <button type="button" class="krixen-btn krixen-book-room" data-room-id="<?php echo esc_attr($room->id); ?>" data-capacity="<?php echo esc_attr( (int) $room->capacity ); ?>" data-room-name="<?php echo esc_attr($room->name); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Book %s', 'krixen' ), $room->name ) ); ?>"><?php _e('Book This Room','krixen'); ?></button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <form id="krixen-booking-form" class="krixen-booking-form" method="post" style="display:none;">
            <?php wp_nonce_field( 'krixen_booking_action', 'krixen_booking_nonce_field' ); ?>
            <div class="krixen-field"><label><?php _e( 'Full Name', 'krixen' ); ?>*</label><input type="text" name="full_name" required></div>
            <div class="krixen-field"><label><?php _e( 'Email', 'krixen' ); ?>*</label><input type="email" name="email" required></div>
            <div class="krixen-field"><label><?php _e( 'Select Room', 'krixen' ); ?>*</label>
                <select name="room_id" required>
                    <option value=""><?php _e( 'Select', 'krixen' ); ?></option>
                    <?php foreach ( $rooms as $room ) : ?>
                        <option value="<?php echo esc_attr( $room->id ); ?>" data-capacity="<?php echo esc_attr( $room->capacity ); ?>"><?php echo esc_html( $room->name . ' — ' . $room->capacity . ' person' ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="krixen-two-cols">
                <div class="krixen-field"><label><?php _e( 'Date', 'krixen' ); ?>*</label><input type="date" name="date" value="<?php echo esc_attr($default_date); ?>" min="<?php echo esc_attr($default_date); ?>" required></div>
                <div class="krixen-field"><label><?php _e( 'Start Time', 'krixen' ); ?>*</label>
                    <select name="start_time" required></select>
                </div>
                <div class="krixen-field"><label><?php _e( 'End Time', 'krixen' ); ?></label><input type="text" name="end_time" readonly></div>
            </div>
            <div class="krixen-field">
                <label><?php _e( 'Availability', 'krixen' ); ?></label>
                <div id="krixen-availability" class="krixen-availability" aria-live="polite" role="list"></div>
            </div>
            
            <button type="submit" class="krixen-btn"><?php _e( 'Book Now', 'krixen' ); ?></button>
            <p class="krixen-message" style="display:none;"></p>
        </form>
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