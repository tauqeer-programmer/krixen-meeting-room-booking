<?php
/**
 * Email Functions
 *
 * Handles all email notifications for the booking system.
 *
 * @package Krixen
 * @since 1.0.0
 */

namespace Krixen;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Email Functions class
 *
 * Manages email templates and sending logic.
 */
class Email_Functions {

	/**
	 * Send confirmation email to user.
	 *
	 * @param string $to   Recipient email address.
	 * @param array  $data Booking data.
	 * @return bool Whether the email was sent successfully.
	 */
	public static function send_user_confirmation( string $to, array $data ): bool {
		$start   = self::format_time_ampm( $data['start'] );
		$end     = self::format_time_ampm( $data['end'] );
		$subject = sprintf( 
			__( 'Booking Confirmed — %s on %s at %s', 'krixen' ), 
			$data['room'], 
			self::format_date_pretty( $data['date'] ), 
			$start 
		);
		
		$message = self::build_email_template([
			'title'    => __( 'Booking Confirmed', 'krixen' ),
			'intro'    => sprintf( 
				__( 'Hey %s, your Krixen booking is confirmed.', 'krixen' ), 
				esc_html( $data['name'] ) 
			),
			'details'  => [
				__( 'Room', 'krixen' )     => esc_html( $data['room'] ),
				__( 'Date', 'krixen' )     => esc_html( self::format_date_pretty( $data['date'] ) ),
				__( 'Time', 'krixen' )     => esc_html( $start . ' - ' . $end ),
				__( 'Booking ID', 'krixen' ) => esc_html( $data['booking_id'] ?? 'N/A' ),
			],
			'cta_text' => __( 'Visit our site', 'krixen' ),
			'cta_url'  => home_url(),
		]);
		
		$headers = [ 'Content-Type: text/html; charset=UTF-8' ];
		
		return wp_mail( $to, $subject, $message, $headers );
	}

	/**
	 * Send alert email to admin.
	 *
	 * @param array $data Booking data.
	 * @return bool Whether the email was sent successfully.
	 */
	public static function send_admin_alert( array $data ): bool {
		$admin_email = get_option( 'krixen_admin_email', get_option( 'admin_email' ) );
		$start       = self::format_time_ampm( $data['start'] );
		$end         = self::format_time_ampm( $data['end'] );
		$subject     = sprintf( 
			__( 'New Booking: %s on %s at %s', 'krixen' ), 
			$data['room'], 
			self::format_date_pretty( $data['date'] ), 
			$start 
		);
		
		$message = self::build_email_template([
			'title'    => __( 'New Booking Received', 'krixen' ),
			'intro'    => __( 'A new room booking has been made.', 'krixen' ),
			'details'  => [
				__( 'Name', 'krixen' )  => esc_html( $data['name'] ),
				__( 'Email', 'krixen' ) => esc_html( $data['email'] ),
				__( 'Room', 'krixen' )  => esc_html( $data['room'] ),
				__( 'Date', 'krixen' )  => esc_html( self::format_date_pretty( $data['date'] ) ),
				__( 'Time', 'krixen' )  => esc_html( $start . ' - ' . $end ),
			],
			'cta_text' => __( 'Manage Bookings', 'krixen' ),
			'cta_url'  => admin_url( 'admin.php?page=krixen-bookings' ),
		]);
		
		return wp_mail( $admin_email, $subject, $message, [ 'Content-Type: text/html; charset=UTF-8' ] );
	}

	/**
	 * Build HTML email template.
	 *
	 * @param array $args Template arguments.
	 * @return string HTML email content.
	 */
	private static function build_email_template( array $args ): string {
		$title   = $args['title'] ?? '';
		$intro   = $args['intro'] ?? '';
		$details = $args['details'] ?? [];
		$ctaText = $args['cta_text'] ?? '';
		$ctaUrl  = $args['cta_url'] ?? '';

		ob_start();
		?>
		<!DOCTYPE html>
		<html lang="en">
		<head>
			<meta charset="UTF-8">
			<meta name="viewport" content="width=device-width, initial-scale=1.0">
			<title><?php echo esc_html( $title ); ?></title>
		</head>
		<body style="margin:0;padding:0;background:#f3f4f6;">
			<div style="background:#f3f4f6;padding:24px 0;width:100%;">
				<table role="presentation" cellspacing="0" cellpadding="0" border="0" align="center" width="600" style="background:#ffffff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif;color:#111827;">
					<tr>
						<td style="background:linear-gradient(135deg, #1E3A8A 0%, #3B82F6 100%);color:#ffffff;padding:24px 28px;font-size:22px;font-weight:700;letter-spacing:-0.5px;">
							<?php echo esc_html( $title ); ?>
						</td>
					</tr>
					<tr>
						<td style="padding:28px 28px;font-size:15px;line-height:1.7;color:#374151;">
							<p style="margin:0 0 16px;font-size:16px;"><?php echo esc_html( $intro ); ?></p>
							<?php if ( ! empty( $details ) ) : ?>
								<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-top:12px;background:#f9fafb;border-radius:8px;overflow:hidden;">
									<?php foreach ( $details as $label => $value ) : ?>
										<tr>
											<td style="padding:12px 16px;color:#6b7280;font-size:14px;width:140px;border-bottom:1px solid #e5e7eb;"><?php echo esc_html( $label ); ?></td>
											<td style="padding:12px 16px;color:#111827;font-weight:600;font-size:14px;border-bottom:1px solid #e5e7eb;"><?php echo $value; ?></td>
										</tr>
									<?php endforeach; ?>
								</table>
							<?php endif; ?>
							<?php if ( $ctaText && $ctaUrl ) : ?>
								<div style="margin-top:24px;text-align:center;">
									<a href="<?php echo esc_url( $ctaUrl ); ?>" style="display:inline-block;background:linear-gradient(135deg, #1E3A8A 0%, #3B82F6 100%);color:#ffffff;text-decoration:none;padding:12px 24px;border-radius:8px;font-weight:700;font-size:15px;box-shadow:0 4px 6px rgba(0,0,0,0.1);">
										<?php echo esc_html( $ctaText ); ?>
									</a>
								</div>
							<?php endif; ?>
							<p style="margin:28px 0 0;color:#9ca3af;font-size:13px;border-top:1px solid #e5e7eb;padding-top:20px;">
								&copy; <?php echo esc_html( gmdate('Y') ); ?> Krixen Meeting Room Booking
							</p>
						</td>
					</tr>
				</table>
			</div>
		</body>
		</html>
		<?php
		return ob_get_clean();
	}

	/**
	 * Format 24-hour time to 12-hour AM/PM format.
	 *
	 * @param string $time24 Time in H:i format.
	 * @return string Formatted time.
	 */
	private static function format_time_ampm( string $time24 ): string {
		$tz = wp_timezone();
		$d  = \DateTimeImmutable::createFromFormat( 'H:i', $time24, $tz );
		
		if ( ! $d ) {
			return $time24;
		}
		
		return $d->format( 'h:i A' );
	}

	/**
	 * Format date to pretty format.
	 *
	 * @param string $dateYmd Date in Y-m-d format.
	 * @return string Formatted date.
	 */
	private static function format_date_pretty( string $dateYmd ): string {
		$tz = wp_timezone();
		
		try {
			$d = new \DateTimeImmutable( $dateYmd . ' 00:00:00', $tz );
			return $d->format( 'F j, Y' );
		} catch ( \Exception $e ) {
			return $dateYmd;
		}
	}
}
