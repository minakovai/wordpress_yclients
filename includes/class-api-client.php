<?php

declare(strict_types=1);

namespace YclientsBookingWp;

final class ApiClient {
	private const BASE_URL = 'https://api.yclients.com/api/v1';
	private const TIMEOUT  = 15;

	public function check_connection(): array {
		$settings   = Settings::get_settings();
		$company_id = (int) $settings['yclients_company_id'];
		if ( $company_id <= 0 ) {
			return [
				'status'  => 'error',
				'message' => esc_html__( 'Company ID is required.', 'yclients-booking-wp' ),
			];
		}

		$response = $this->request(
			'GET',
			'/company/' . $company_id,
			[],
			[],
			true
		);

		if ( $response['success'] ) {
			return [
				'status'     => 'success',
				'message'    => esc_html__( 'Connection successful.', 'yclients-booking-wp' ),
				'code'       => $response['code'],
				'request_id' => $response['request_id'],
			];
		}

		return [
			'status'     => 'error',
			'message'    => $response['message'],
			'code'       => $response['code'],
			'request_id' => $response['request_id'],
		];
	}

	/**
	 * Получить услуги.
	 * Документация: https://yclientsru.docs.apiary.io/ (Services /company/{company_id}/services)
	 */
	public function get_services(): array {
		$settings   = Settings::get_settings();
		$company_id = (int) $settings['yclients_company_id'];

		return $this->request(
			'GET',
			'/company/' . $company_id . '/services',
			[],
			[],
			true
		);
	}

	/**
	 * Получить сотрудников.
	 * Документация: https://yclientsru.docs.apiary.io/ (Staff /company/{company_id}/staff)
	 */
	public function get_staff( ?int $service_id = null ): array {
		$settings   = Settings::get_settings();
		$company_id = (int) $settings['yclients_company_id'];
		$query      = [];
		if ( null !== $service_id ) {
			$query['service_id'] = $service_id;
		}

		return $this->request(
			'GET',
			'/company/' . $company_id . '/staff',
			$query,
			[],
			true
		);
	}

	/**
	 * Получить доступные даты.
	 * Документация: https://yclientsru.docs.apiary.io/ (Booking dates /book_dates/{company_id})
	 */
	public function get_available_dates( int $service_id, int $staff_id ): array {
		$settings   = Settings::get_settings();
		$company_id = (int) $settings['yclients_company_id'];

		$query = [
			'service_id' => $service_id,
			'staff_id'   => $staff_id,
		];

		return $this->request(
			'GET',
			'/book_dates/' . $company_id,
			$query,
			[],
			true
		);
	}

	/**
	 * Получить доступные слоты времени.
	 * Документация: https://yclientsru.docs.apiary.io/ (Booking times /book_times/{company_id})
	 */
	public function get_available_times( int $service_id, int $staff_id, string $date ): array {
		$settings   = Settings::get_settings();
		$company_id = (int) $settings['yclients_company_id'];

		$query = [
			'service_id' => $service_id,
			'staff_id'   => $staff_id,
			'date'       => $date,
		];

		return $this->request(
			'GET',
			'/book_times/' . $company_id,
			$query,
			[],
			true
		);
	}

	/**
	 * Создать запись.
	 * Документация: https://yclientsru.docs.apiary.io/ (Booking create /book_record/{company_id})
	 */
	public function create_booking( array $payload ): array {
		$settings   = Settings::get_settings();
		$company_id = (int) $settings['yclients_company_id'];

		return $this->request(
			'POST',
			'/book_record/' . $company_id,
			[],
			$payload,
			true
		);
	}

	private function request( string $method, string $path, array $query, array $body, bool $needs_partner_token ): array {
		$settings = Settings::get_settings();
		$url      = self::BASE_URL . $path;
		if ( ! empty( $query ) ) {
			$url = add_query_arg( $query, $url );
		}

		$headers = [
			'Accept'       => 'application/json',
			'Content-Type' => 'application/json',
		];

		if ( $needs_partner_token && ! empty( $settings['yclients_partner_token'] ) ) {
			$headers['Authorization'] = 'Bearer ' . $settings['yclients_partner_token'];
		}

		if ( ! empty( $settings['yclients_user_token'] ) ) {
			$headers['User'] = $settings['yclients_user_token'];
		}

		$args = [
			'headers' => $headers,
			'timeout' => self::TIMEOUT,
			'method'  => $method,
		];

		if ( 'POST' === $method ) {
			$args['body'] = wp_json_encode( $body );
		}

		$start   = microtime( true );
		$result  = wp_remote_request( $url, $args );
		$elapsed = microtime( true ) - $start;

		if ( is_wp_error( $result ) ) {
			Logger::log( sprintf( 'Request error %s %s: %s', $method, $url, $result->get_error_message() ) );
			return [
				'success'    => false,
				'message'    => $result->get_error_message(),
				'code'       => 0,
				'request_id' => '',
				'body'       => [],
			];
		}

		$code       = (int) wp_remote_retrieve_response_code( $result );
		$raw_body   = (string) wp_remote_retrieve_body( $result );
		$request_id = (string) wp_remote_retrieve_header( $result, 'X-Request-Id' );
		$decoded    = json_decode( $raw_body, true );
		$body       = is_array( $decoded ) ? $decoded : [];
		$logged_body = $this->mask_sensitive_data( $raw_body );

		Logger::log(
			sprintf(
				'%s %s -> %d in %.2fs, body: %s',
				$method,
				$url,
				$code,
				$elapsed,
				Logger::truncate_body( $logged_body )
			)
		);

		if ( $code >= 200 && $code < 300 ) {
			return [
				'success'    => true,
				'code'       => $code,
				'body'       => $body,
				'request_id' => $request_id,
			];
		}

		$message = isset( $body['message'] ) ? (string) $body['message'] : esc_html__( 'API error.', 'yclients-booking-wp' );

		return [
			'success'    => false,
			'code'       => $code,
			'body'       => $body,
			'message'    => $message,
			'request_id' => $request_id,
		];
	}

	private function mask_sensitive_data( string $body ): string {
		return (string) preg_replace_callback(
			'/\\b\\d{10,15}\\b/',
			static function ( array $matches ): string {
				return Logger::mask_phone( $matches[0] );
			},
			$body
		);
	}
}
