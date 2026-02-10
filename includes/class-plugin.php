<?php

declare(strict_types=1);

namespace YclientsBookingWp;

final class Plugin {
	private static ?Plugin $instance = null;

	private Settings $settings;
	private Rest $rest;
	private Shortcodes $shortcodes;

	private function __construct() {
		$this->settings  = new Settings();
		$this->rest      = new Rest();
		$this->shortcodes = new Shortcodes();

		add_action( 'init', [ $this, 'load_textdomain' ] );
	}

	public static function get_instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new Plugin();
		}

		return self::$instance;
	}

	public function load_textdomain(): void {
		load_plugin_textdomain(
			YCLIENTS_BOOKING_WP_TEXT_DOMAIN,
			false,
			dirname( plugin_basename( YCLIENTS_BOOKING_WP_PATH ) ) . '/languages'
		);
	}
}
