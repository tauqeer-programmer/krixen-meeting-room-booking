<?php
// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Respect option to delete data
$delete = get_option('krixen_delete_on_uninstall','0');
if ( $delete !== '1' ) {
	return;
}

global $wpdb;
$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'krixen_bookings' );
$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'krixen_rooms' );
delete_option('krixen_delete_on_uninstall');


Replace the non-working room tabs with a modern, responsive room card grid and ensure booking form and booking saving works perfectly. The front-end must display room image, capacity, description, and a working “Book This Room” button. When a booking is submitted, it must save to wp_krixen_bookings and immediately appear in the admin Bookings tab. The shortcode must render the room grid and booking form, be Elementor-compatible, and the plugin must create a full “Krixen Booking” page on activation if not present.

Requirements:

Front-end Room Grid (replace tabs)

Remove the old static tab UI. Replace it with responsive room cards showing:

Room image (from admin-uploaded image URL or default placeholder)

Room name/title

Capacity (text: “Capacity: X people”)

Short description (trim to around 120 characters with a “Read more” modal)

A blue primary button: “Book This Room” (#1E3A8A)

Layout:

Desktop: 3 cards per row, Tablet: 2 per row, Mobile: 1 per row

Card style: white background, rounded corners (12px), box shadow, hover scale effect

Image behavior: object-fit: cover; fixed height (around 200px) and fully responsive

Accessibility: semantic HTML, ARIA labels for buttons and modals

Booking Form Behavior

When “Book This Room” is clicked:

Dynamically reveal the booking form under the selected card with a smooth slide-down animation

Auto-populate the selected room ID/name and show capacity

Only show one form at a time; clicking another card hides the previous form

Form fields (aligned, no duration or attendees):

Full Name (required)

Email (required)

Date (datepicker, must be a future date)

Start Time (select dropdown in 12-hour AM/PM format showing only upcoming times for the selected date)

End Time auto-calculated (Start + 3 hours) and displayed read-only

Availability display below the form:

Show time slots or selected time range status with green “Available” or red “Booked” indicators

Disable selection of booked slots

Submission:

Use AJAX to submit to admin-ajax.php (nonce-protected) and save booking to wp_krixen_bookings

Show loading state on button, then success message: “Room booked successfully! A confirmation email has been sent.” and clear/hide the form or show booking summary

On success, update the UI to mark the slot as booked without full page reload

Time Slot Generation and Validation

Generate time options for the selected date starting from the current time (rounded up to next 30 minutes) through a closing hour (default 9:00 PM)

Display times in AM/PM format (example: “02:30 PM”)

Server-side validation must check for overlaps and race conditions before inserting booking

Use the convention [start_time, end_time) and handle edge cases

Room Image Handling

Ensure admin room image upload works using the WordPress Media Uploader and store image URL in the image_url column of wp_krixen_rooms

Front-end must display the image using the stored URL and proper alt text

If image is missing, display a default placeholder image from assets/img/no-room.jpg

Admin Booking Management (fix missing bookings)

Ensure booking insertions are saved correctly to wp_krixen_bookings with fields: booking_id, name, email, room_id, room_name, date, start_time, end_time, status, created_at

Dashboard → Krixen Booking → Bookings must list bookings with columns: Name, Email, Room, Date, Start Time, End Time, Status, Created On

Sort by upcoming bookings first (earliest date/time ascending)

Add filters for Today, Upcoming, and All

Clicking a row shows full booking details in a modal or detail view

Implement an AJAX endpoint or polling mechanism so new bookings appear in the admin list without manual reload

Email Notifications and Branding

Send HTML emails to the user and admin on successful booking

Use From: Krixen no-reply@krixen.com
 (via wp_mail_from_name and wp_mail_from filters)

Admin recipient should be the configured admin email in plugin settings (fallback to site admin email)

Emails must include: Booking ID, Room, Date (formatted), Start Time and End Time (AM/PM), customer name and email, and a “View Booking” link for the admin

Include a responsive HTML template with inline styles and display the logo if uploaded

Settings and Branding

Settings page options:

Frontend logo upload (krixen_logo_url)

Admin dashboard logo upload (krixen_admin_logo_url)

Admin notification email (krixen_admin_email)

Booking page ID (auto-saved on creation)

Show uploaded logo in both front booking form and admin dashboard header

Activation Behavior

On activation, create required database tables (wp_krixen_rooms and wp_krixen_bookings) using dbDelta

Seed default rooms if empty

Auto-create a page titled “Krixen Booking” containing the shortcode [krixen_meeting_booking]

If the page already exists, do not duplicate; instead, save its ID in the plugin options

Security and Compatibility

All AJAX endpoints must verify nonces and sanitize inputs

Use $wpdb prepared statements for database operations

Make all text strings translatable (load_plugin_textdomain)

Compatible with WordPress 6.x and PHP 8+

Deliverables

Updated shortcode that renders room grid and dynamic booking flow

Updated front-end assets (CSS and JS) implementing responsive cards, animation, and AJAX

Updated admin pages for Rooms, Bookings, and Settings with working image upload, CRUD, and booking list

Placeholder image located in assets/img/no-room.jpg

Bookings appear instantly in admin Bookings after submission

Confirmation emails sent to both admin and user with Krixen branding
