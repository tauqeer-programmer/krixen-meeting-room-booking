<?php
namespace Krixen;

use WP_List_Table;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Room_Manager {

    const TABLE = 'krixen_rooms';

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'register_menu' ] );
        add_action( 'admin_init', [ $this, 'maybe_setup' ] );
    }

    /**
     * Create DB table on activation
     */
    public static function create_table() {
        global $wpdb;
        $table_name      = $wpdb->prefix . self::TABLE;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(191) NOT NULL,
            capacity INT UNSIGNED NOT NULL DEFAULT 1,
            description TEXT NULL,
            image_url VARCHAR(255) NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            PRIMARY KEY  (id)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /**
     * Ensure table exists and seed defaults if empty (in case activation didn't run)
     */
    public function maybe_setup() {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;
        $exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) );
        if ( $exists !== $table ) {
            self::create_table();
        }
        $count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
        if ( $count === 0 ) {
            $defaults = [
                [ 'name' => 'Conference Room', 'capacity' => 12, 'description' => '' ],
                [ 'name' => 'Meeting Room', 'capacity' => 5, 'description' => '' ],
                [ 'name' => 'Discussion Room', 'capacity' => 3, 'description' => '' ],
            ];
            foreach ( $defaults as $room ) {
                $wpdb->insert( $table, $room );
            }
        }
    }

    /**
     * Register admin menu
     */
    public function register_menu() {
        add_menu_page( 
            __( 'Krixen Booking', 'krixen' ),
            __( 'Krixen Booking', 'krixen' ),
            'manage_options',
            'krixen-booking',
            [ $this, 'rooms_page' ],
            'dashicons-calendar',
            26
        );
        add_submenu_page( 'krixen-booking', __( 'Rooms', 'krixen' ), __( 'Rooms', 'krixen' ), 'manage_options', 'krixen-booking', [ $this, 'rooms_page' ] );
    }

    /**
     * Handle CRUD operations and render page
     */
    public function rooms_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;

        // Add / Update room
        if ( isset( $_POST['krixen_room_nonce'] ) && wp_verify_nonce( $_POST['krixen_room_nonce'], 'save_room' ) ) {
            $id         = isset( $_POST['room_id'] ) ? absint( $_POST['room_id'] ) : 0;
            $name       = sanitize_text_field( $_POST['name'] );
            $capacity   = absint( $_POST['capacity'] );
            $desc       = sanitize_textarea_field( $_POST['description'] );
            $image_url  = isset($_POST['image_url']) ? esc_url_raw($_POST['image_url']) : '';
            $status     = isset($_POST['status']) && in_array($_POST['status'], ['active','inactive'], true ) ? $_POST['status'] : 'active';

            if ( empty( $name ) || $capacity < 1 ) {
                echo '<div class="error notice"><p>' . esc_html__( 'Please enter a room name and a capacity of at least 1.', 'krixen' ) . '</p></div>';
            } else {
                // Prevent duplicate names (case-insensitive)
                $dupe = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE LOWER(name)=LOWER(%s) AND id <> %d", $name, $id ) );
                if ( $dupe ) {
                    echo '<div class="error notice"><p>' . esc_html__( 'A room with this name already exists.', 'krixen' ) . '</p></div>';
                } else {
                if ( $id ) {
                    $wpdb->update( $table, [ 'name' => $name, 'capacity' => $capacity, 'description' => $desc, 'image_url' => $image_url, 'status' => $status ], [ 'id' => $id ] );
                    wp_redirect( add_query_arg( [ 'page' => 'krixen-booking', 'updated' => 1 ], admin_url( 'admin.php' ) ) );
                    exit;
                } else {
                    $wpdb->insert( $table, [ 'name' => $name, 'capacity' => $capacity, 'description' => $desc, 'image_url' => $image_url, 'status' => $status ] );
                    wp_redirect( add_query_arg( [ 'page' => 'krixen-booking', 'added' => 1 ], admin_url( 'admin.php' ) ) );
                    exit;
                }
                }
            }
        }

        // Delete room
        if ( isset( $_GET['action'] ) && $_GET['action'] === 'delete' && isset( $_GET['room'] ) ) {
            $room_id = absint( $_GET['room'] );
            if ( wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'krixen_delete_room_' . $room_id ) ) {
                $wpdb->delete( $table, [ 'id' => $room_id ] );
                wp_redirect( add_query_arg( [ 'page' => 'krixen-booking', 'deleted' => 1 ], admin_url( 'admin.php' ) ) );
                exit;
            } else {
                echo '<div class="error notice"><p>' . esc_html__( 'Security check failed for deletion.', 'krixen' ) . '</p></div>';
            }
        }

        // Fetch rooms
            $rooms     = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id DESC" );
        $editing   = false;
        $edit_room = null;
        if ( isset( $_GET['action'] ) && $_GET['action'] === 'edit' && isset( $_GET['room'] ) ) {
            $editing   = true;
            $edit_room = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", absint( $_GET['room'] ) ) );
        }

        ?>
        <div class="wrap">
            <h1><?php echo esc_html__( 'Rooms', 'krixen' ); ?></h1>
            <?php if ( isset($_GET['updated']) ): ?>
                <div class="updated notice"><p><?php _e('Room updated.','krixen'); ?></p></div>
            <?php endif; ?>
            <?php if ( isset($_GET['added']) ): ?>
                <div class="updated notice"><p><?php _e('Room added.','krixen'); ?></p></div>
            <?php endif; ?>
            <?php if ( isset($_GET['deleted']) ): ?>
                <div class="updated notice"><p><?php _e('Room deleted.','krixen'); ?></p></div>
            <?php endif; ?>
            <div class="krixen-room-form" style="margin-top:20px;">
                <h2><?php echo $editing ? __( 'Edit Room', 'krixen' ) : __( 'Add New Room', 'krixen' ); ?></h2>
                <form method="post">
                    <?php wp_nonce_field( 'save_room', 'krixen_room_nonce' ); ?>
                    <?php if ( $editing ) : ?>
                        <input type="hidden" name="room_id" value="<?php echo esc_attr( $edit_room->id ); ?>" />
                    <?php endif; ?>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="name"><?php _e( 'Room Name', 'krixen' ); ?></label></th>
                            <td><input name="name" type="text" id="name" value="<?php echo esc_attr( $editing ? $edit_room->name : '' ); ?>" class="regular-text" required></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="capacity"><?php _e( 'Capacity', 'krixen' ); ?></label></th>
                            <td><input name="capacity" type="number" id="capacity" min="1" value="<?php echo esc_attr( $editing ? $edit_room->capacity : '1' ); ?>" required></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="description"><?php _e( 'Description', 'krixen' ); ?></label></th>
                            <td><textarea name="description" id="description" rows="5" class="large-text"><?php echo esc_textarea( $editing ? $edit_room->description : '' ); ?></textarea></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="image_url"><?php _e( 'Image', 'krixen' ); ?></label></th>
                            <td>
                                <input name="image_url" type="text" id="image_url" value="<?php echo esc_attr( $editing ? ($edit_room->image_url ?? '') : '' ); ?>" class="regular-text">
                                <button type="button" class="button" id="krixen_room_image_button"><?php _e('Upload','krixen'); ?></button>
                                <div><img id="krixen_room_image_preview" src="<?php echo esc_url( $editing && ! empty($edit_room->image_url) ? $edit_room->image_url : '' ); ?>" style="max-width:200px;height:auto;margin-top:8px;<?php echo $editing && ! empty($edit_room->image_url) ? '' : 'display:none;'; ?>"/></div>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="status"><?php _e( 'Status', 'krixen' ); ?></label></th>
                            <td>
                                <select name="status" id="status">
                                    <option value="active" <?php selected( $editing ? ($edit_room->status ?? 'active') : 'active', 'active' ); ?>><?php _e('Active','krixen'); ?></option>
                                    <option value="inactive" <?php selected( $editing ? ($edit_room->status ?? 'active') : 'active', 'inactive' ); ?>><?php _e('Inactive','krixen'); ?></option>
                                </select>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button( $editing ? __( 'Update Room', 'krixen' ) : __( 'Add Room', 'krixen' ) ); ?>
                </form>
                <script>
                jQuery(function($){
                    $('#krixen_room_image_button').on('click', function(e){
                        e.preventDefault();
                        var frame = wp.media({title:'Select Room Image', button:{text:'Use this image'}, library:{type:['image']}, multiple:false});
                        frame.on('select', function(){ var att=frame.state().get('selection').first().toJSON(); $('#image_url').val(att.url); $('#krixen_room_image_preview').attr('src', att.url).show(); });
                        frame.open();
                    });
                });
                </script>
            </div>

            <h2 style="margin-top:40px;"><?php _e( 'Existing Rooms', 'krixen' ); ?></h2>
            <table class="wp-list-table widefat striped">
                <thead>
                    <tr>
                        <th><?php _e( 'ID', 'krixen' ); ?></th>
                        <th><?php _e( 'Name', 'krixen' ); ?></th>
                        <th><?php _e( 'Capacity', 'krixen' ); ?></th>
                        <th><?php _e( 'Description', 'krixen' ); ?></th>
                        <th><?php _e( 'Status', 'krixen' ); ?></th>
                        <th><?php _e( 'Actions', 'krixen' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( $rooms ) : ?>
                        <?php foreach ( $rooms as $room ) : ?>
                            <tr>
                                <td><?php echo esc_html( $room->id ); ?></td>
                                <td><?php echo esc_html( $room->name ); ?></td>
                                <td><?php echo esc_html( $room->capacity ); ?></td>
                                <td><?php echo esc_html( $room->description ); ?></td>
                                <td><?php echo esc_html( ucfirst( $room->status ?? 'active' ) ); ?></td>
                                <td>
                                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=krixen-booking&action=edit&room=' . $room->id ) ); ?>">Edit</a> | 
                                    <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=krixen-booking&action=delete&room=' . $room->id ), 'krixen_delete_room_' . $room->id ) ); ?>" onclick="return confirm('Are you sure?');">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr><td colspan="5">No rooms found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Helper to get rooms
     */
    public static function get_rooms() {
        global $wpdb;
        return $wpdb->get_results( "SELECT * FROM " . $wpdb->prefix . self::TABLE );
    }

    /**
     * Get a room by id
     */
    public static function get_room( $id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . $wpdb->prefix . self::TABLE . " WHERE id = %d", $id ) );
    }
}