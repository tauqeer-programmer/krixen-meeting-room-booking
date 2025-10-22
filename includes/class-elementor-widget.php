<?php
/**
 * Elementor Widget Integration
 *
 * Lightweight wrapper to integrate Krixen booking form with Elementor.
 *
 * @package Krixen
 * @since 1.0.0
 */

namespace Krixen;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register Elementor widget
 */
add_action( 'elementor/widgets/register', function ( $widgets_manager ): void {
	if ( ! class_exists( '\\Elementor\\Widget_Base' ) ) {
		return;
	}

	/**
	 * Krixen Booking Widget for Elementor
	 */
	class Krixen_Elementor_Booking_Widget extends \Elementor\Widget_Base {
		
		/**
		 * Get widget name
		 *
		 * @return string Widget name.
		 */
		public function get_name(): string {
			return 'krixen_booking_form';
		}

		/**
		 * Get widget title
		 *
		 * @return string Widget title.
		 */
		public function get_title(): string {
			return __( 'Krixen Booking Form', 'krixen' );
		}

		/**
		 * Get widget icon
		 *
		 * @return string Widget icon.
		 */
		public function get_icon(): string {
			return 'eicon-calendar';
		}

		/**
		 * Get widget categories
		 *
		 * @return array Widget categories.
		 */
		public function get_categories(): array {
			return [ 'general' ];
		}

		/**
		 * Get widget keywords
		 *
		 * @return array Widget keywords.
		 */
		public function get_keywords(): array {
			return [ 'booking', 'room', 'meeting', 'calendar', 'reservation' ];
		}

		/**
		 * Render widget output
		 *
		 * @return void
		 */
		protected function render(): void {
			echo do_shortcode( '[krixen_meeting_booking]' );
		}

		/**
		 * Render widget output in the editor
		 *
		 * @return void
		 */
		protected function content_template(): void {
			?>
			<div style="padding: 20px; background: #f0f0f0; border: 2px dashed #ccc; text-align: center;">
				<h3><?php echo esc_html__( 'Krixen Booking Form', 'krixen' ); ?></h3>
				<p><?php echo esc_html__( 'The booking form will appear here on the frontend.', 'krixen' ); ?></p>
			</div>
			<?php
		}
	}

	$widgets_manager->register( new \Krixen\Krixen_Elementor_Booking_Widget() );
} );
