<?php
namespace Krixen;

if ( ! defined( 'ABSPATH' ) ) { exit; }

// Lightweight Elementor widget wrapper
add_action('elementor/widgets/register', function( $widgets_manager ){
    if ( ! class_exists( '\\Elementor\\Widget_Base' ) ) { return; }

    class Krixen_Elementor_Booking_Widget extends \Elementor\Widget_Base {
        public function get_name(){ return 'krixen_booking_form'; }
        public function get_title(){ return __( 'Krixen Booking Form', 'krixen' ); }
        public function get_icon(){ return 'eicon-calendar'; }
        public function get_categories(){ return [ 'general' ]; }
        protected function render(){ echo do_shortcode('[krixen_meeting_booking]'); }
    }

    $widgets_manager->register( new \Krixen\Krixen_Elementor_Booking_Widget() );
});


