<?php
namespace Krixen;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Email_Functions {

    public static function send_user_confirmation( $to, $data ) {
        $start = self::format_time_ampm($data['start']);
        $end   = self::format_time_ampm($data['end']);
        $subject = sprintf( __( 'Booking Confirmed — %s on %s at %s', 'krixen' ), $data['room'], self::format_date_pretty($data['date']), $start );
        $message = self::build_email_template([
            'title' => __( 'Booking Confirmed', 'krixen' ),
            'intro' => sprintf( __( 'Hey %s, your Krixen booking is confirmed.', 'krixen' ), esc_html($data['name']) ),
            'details' => [
                __( 'Room', 'krixen' ) => esc_html($data['room']),
                __( 'Date', 'krixen' ) => esc_html(self::format_date_pretty($data['date'])),
                __( 'Time', 'krixen' ) => esc_html($start . ' - ' . $end),
            ],
            'cta_text' => __( 'Visit our site', 'krixen' ),
            'cta_url'  => home_url(),
        ]);
        $headers = [ 'Content-Type: text/html; charset=UTF-8' ];
        $logo = get_option('krixen_logo_url','');
        if ( $logo ) { $headers[] = 'List-Unsubscribe: <'.esc_url(home_url()).'>'; }
        wp_mail( $to, $subject, $message, $headers );
    }

    public static function send_admin_alert( $data ) {
        $admin_email = get_option( 'krixen_admin_email', get_option( 'admin_email' ) );
        $start = self::format_time_ampm($data['start']);
        $end   = self::format_time_ampm($data['end']);
        $subject     = sprintf( __( 'New Booking: %s on %s at %s', 'krixen' ), $data['room'], self::format_date_pretty($data['date']), $start );
        $message     = self::build_email_template([
            'title' => __( 'New Booking Received', 'krixen' ),
            'intro' => __( 'A new room booking has been made.', 'krixen' ),
            'details' => [
                __( 'Name', 'krixen' ) => esc_html($data['name']),
                __( 'Email', 'krixen' ) => esc_html($data['email']),
                __( 'Room', 'krixen' ) => esc_html($data['room']),
                __( 'Date', 'krixen' ) => esc_html(self::format_date_pretty($data['date'])),
                __( 'Time', 'krixen' ) => esc_html($start . ' - ' . $end),
            ],
            'cta_text' => __( 'Manage Bookings', 'krixen' ),
            'cta_url'  => admin_url('admin.php?page=krixen-bookings'),
        ]);
        wp_mail( $admin_email, $subject, $message, [ 'Content-Type: text/html; charset=UTF-8' ] );
    }

    private static function build_email_template( $args ) {
        $title   = $args['title'] ?? '';
        $intro   = $args['intro'] ?? '';
        $details = $args['details'] ?? [];
        $ctaText = $args['cta_text'] ?? '';
        $ctaUrl  = $args['cta_url'] ?? '';

        ob_start();
        ?>
        <div style="background:#f3f4f6;padding:24px 0;width:100%;">
            <table role="presentation" cellspacing="0" cellpadding="0" border="0" align="center" width="600" style="background:#ffffff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;font-family:Arial,Helvetica,sans-serif;color:#111827;">
                <tr>
                    <td style="background:#1E3A8A;color:#ffffff;padding:20px 24px;font-size:20px;font-weight:700;">
                        <?php echo esc_html( $title ); ?>
                    </td>
                </tr>
                <tr>
                    <td style="padding:20px 24px;font-size:14px;line-height:1.6;">
                        <p style="margin:0 0 12px;"><?php echo esc_html( $intro ); ?></p>
                        <?php if ( ! empty( $details ) ) : ?>
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-top:8px;">
                                <?php foreach ( $details as $label => $value ) : ?>
                                    <tr>
                                        <td style="padding:6px 0;color:#6b7280;width:140px;">&nbsp;<?php echo esc_html( $label ); ?></td>
                                        <td style="padding:6px 0;color:#111827;font-weight:600;">&nbsp;<?php echo $value; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </table>
                        <?php endif; ?>
                        <?php if ( $ctaText && $ctaUrl ) : ?>
                            <div style="margin-top:16px;">
                                <a href="<?php echo esc_url( $ctaUrl ); ?>" style="display:inline-block;background:#1E3A8A;color:#ffffff;text-decoration:none;padding:10px 16px;border-radius:6px;font-weight:700;">
                                    <?php echo esc_html( $ctaText ); ?>
                                </a>
                            </div>
                        <?php endif; ?>
                        <p style="margin:20px 0 0;color:#6b7280;font-size:12px;">&copy; <?php echo esc_html( date('Y') ); ?> Krixen</p>
                    </td>
                </tr>
            </table>
        </div>
        <?php
        return ob_get_clean();
    }

    private static function format_time_ampm( $time24 ) {
        $tz = wp_timezone();
        $d = \DateTimeImmutable::createFromFormat('H:i', $time24, $tz);
        if ( ! $d ) { return $time24; }
        return $d->format('h:i A');
    }

    private static function format_date_pretty( $dateYmd ) {
        $tz = wp_timezone();
        try {
            $d = new \DateTimeImmutable($dateYmd.' 00:00:00', $tz);
            return $d->format('F j, Y');
        } catch ( \Exception $e ) { return $dateYmd; }
    }
}