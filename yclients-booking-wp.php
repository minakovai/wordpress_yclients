<?php
/**
 * Plugin Name: YCLIENTS Booking WP
 * Description: Booking form integration with YCLIENTS REST API.
 * Version: 1.0.0
 * Author: Yclients Booking WP
 * Text Domain: yclients-booking-wp
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'YCLIENTS_BOOKING_WP_VERSION', '1.0.0' );
define( 'YCLIENTS_BOOKING_WP_PATH', plugin_dir_path( __FILE__ ) );
define( 'YCLIENTS_BOOKING_WP_URL', plugin_dir_url( __FILE__ ) );
define( 'YCLIENTS_BOOKING_WP_TEXT_DOMAIN', 'yclients-booking-wp' );

define( 'YCLIENTS_BOOKING_WP_OPTION_KEY', 'yclients_booking_wp_settings' );

define( 'YCLIENTS_BOOKING_WP_LOG_OPTION_KEY', 'yclients_booking_wp_log_notice' );

define( 'YCLIENTS_BOOKING_WP_REST_NAMESPACE', 'yclients-booking/v1' );

require_once YCLIENTS_BOOKING_WP_PATH . 'includes/class-logger.php';
require_once YCLIENTS_BOOKING_WP_PATH . 'includes/class-api-client.php';
require_once YCLIENTS_BOOKING_WP_PATH . 'includes/class-settings.php';
require_once YCLIENTS_BOOKING_WP_PATH . 'includes/class-rest.php';
require_once YCLIENTS_BOOKING_WP_PATH . 'includes/class-shortcodes.php';
require_once YCLIENTS_BOOKING_WP_PATH . 'includes/class-plugin.php';

add_action(
	'plugins_loaded',
	static function (): void {
		YclientsBookingWp\Plugin::get_instance();
	}
);
