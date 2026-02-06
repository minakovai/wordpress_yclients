<?php

declare(strict_types=1);

namespace YclientsBookingWp;

final class Logger {
	private const MAX_BODY_LENGTH = 2000;

	public static function log( string $message ): void {
		$settings = Settings::get_settings();
		if ( empty( $settings['yclients_debug_logging'] ) ) {
			return;
		}

		$uploads_dir = wp_upload_dir();
		$log_file    = trailingslashit( $uploads_dir['basedir'] ) . 'yclients-booking.log';

		$timestamp = gmdate( 'c' );
		error_log( '[' . $timestamp . '] ' . $message . PHP_EOL, 3, $log_file );
	}

	public static function mask_phone( string $phone ): string {
		$digits = preg_replace( '/\D+/', '', $phone );
		if ( null === $digits || '' === $digits ) {
			return '';
		}

		$last4 = substr( $digits, -4 );

		return str_repeat( '*', max( 0, strlen( $digits ) - 4 ) ) . $last4;
	}

	public static function truncate_body( string $body ): string {
		if ( strlen( $body ) <= self::MAX_BODY_LENGTH ) {
			return $body;
		}

		return substr( $body, 0, self::MAX_BODY_LENGTH ) . '...';
	}
}
