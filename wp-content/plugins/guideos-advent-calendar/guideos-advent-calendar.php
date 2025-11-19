<?php
/**
 * Plugin Name: GuideOS Advent Calendar
 * Description: Custom Gutenberg block that renders an advent calendar with animated doors and modal surprises.
 * Version: 1.0.0
 * Plugin URI: https://rueegger.me
 * Author: Samuel Rüeggger
 * Author URI: https://rueegger.me
 * License: GPLv2
 * Text Domain: guideos-advent
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'GUIDEOS_ADVENT_VERSION', '1.0.0' );
define( 'GUIDEOS_ADVENT_FILE', __FILE__ );
define( 'GUIDEOS_ADVENT_PATH', plugin_dir_path( GUIDEOS_ADVENT_FILE ) );
define( 'GUIDEOS_ADVENT_URL', plugin_dir_url( GUIDEOS_ADVENT_FILE ) );

require_once GUIDEOS_ADVENT_PATH . 'includes/class-guideos-advent-calendar.php';

add_action( 'plugins_loaded', function () {
    GuideOS\AdventCalendar\Plugin::get_instance();
} );
