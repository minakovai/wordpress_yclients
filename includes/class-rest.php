<?php

declare(strict_types=1);

namespace YclientsBookingWp;

use WP_REST_Request;
use WP_REST_Response;

final class Rest {
	private const RATE_LIMIT = 30;
	private const RATE_WINDOW = 300;

	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	public function register_routes(): void {
		register_rest_route(
			YCLIENTS_BOOKING_WP_REST_NAMESPACE,
			'/services',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_services' ],
				'permission_callback' => '__return_true',
			]
		);

		register_rest_route(
			YCLIENTS_BOOKING_WP_REST_NAMESPACE,
			'/staff',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_staff' ],
				'permission_callback' => '__return_true',
				'args'                => [
					'service_id' => [
						'type'              => 'integer',
						'required'          => false,
						'sanitize_callback' => 'absint',
					],
				],
			]
		);

		register_rest_route(
			YCLIENTS_BOOKING_WP_REST_NAMESPACE,
			'/available-dates',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_available_dates' ],
				'permission_callback' => '__return_true',
				'args'                => [
					'service_id' => [
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
					],
					'staff_id'   => [
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
					],
				],
			]
		);

		register_rest_route(
			YCLIENTS_BOOKING_WP_REST_NAMESPACE,
			'/available-times',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_available_times' ],
				'permission_callback' => '__return_true',
				'args'                => [
					'service_id' => [
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
					],
					'staff_id'   => [
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
					],
					'date'       => [
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => [ $this, 'sanitize_date' ],
					],
				],
			]
		);

		register_rest_route(
			YCLIENTS_BOOKING_WP_REST_NAMESPACE,
			'/book',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'book' ],
				'permission_callback' => '__return_true',
			]
		);
	}

	public function get_services( WP_REST_Request $request ): WP_REST_Response {
		if ( $this->is_rate_limited( 'services' ) ) {
			return $this->error_response( esc_html__( 'Too many requests.', 'yclients-booking-wp' ), 429 );
		}

		$client = new ApiClient();
		return $this->handle_response( $client->get_services() );
	}

	public function get_staff( WP_REST_Request $request ): WP_REST_Response {
		if ( $this->is_rate_limited( 'staff' ) ) {
			return $this->error_response( esc_html__( 'Too many requests.', 'yclients-booking-wp' ), 429 );
		}

		$service_id = $request->get_param( 'service_id' );
		$service_id = null !== $service_id ? (int) $service_id : null;

		$client = new ApiClient();
		return $this->handle_response( $client->get_staff( $service_id ) );
	}

	public function get_available_dates( WP_REST_Request $request ): WP_REST_Response {
		if ( $this->is_rate_limited( 'dates' ) ) {
			return $this->error_response( esc_html__( 'Too many requests.', 'yclients-booking-wp' ), 429 );
		}

		$service_id = (int) $request->get_param( 'service_id' );
		$staff_id   = (int) $request->get_param( 'staff_id' );

		if ( $service_id <= 0 || $staff_id <= 0 ) {
			return $this->error_response( esc_html__( 'Invalid parameters.', 'yclients-booking-wp' ), 400 );
		}

		$client = new ApiClient();
		return $this->handle_response( $client->get_available_dates( $service_id, $staff_id ) );
	}

	public function get_available_times( WP_REST_Request $request ): WP_REST_Response {
		if ( $this->is_rate_limited( 'times' ) ) {
			return $this->error_response( esc_html__( 'Too many requests.', 'yclients-booking-wp' ), 429 );
		}

		$service_id = (int) $request->get_param( 'service_id' );
		$staff_id   = (int) $request->get_param( 'staff_id' );
		$date       = (string) $request->get_param( 'date' );

		if ( $service_id <= 0 || $staff_id <= 0 || '' === $date ) {
			return $this->error_response( esc_html__( 'Invalid parameters.', 'yclients-booking-wp' ), 400 );
		}

		$client = new ApiClient();
		return $this->handle_response( $client->get_available_times( $service_id, $staff_id, $date ) );
	}

	public function book( WP_REST_Request $request ): WP_REST_Response {
		if ( $this->is_rate_limited( 'book' ) ) {
			return $this->error_response( esc_html__( 'Too many requests.', 'yclients-booking-wp' ), 429 );
		}

		if ( ! $this->verify_nonce( $request ) ) {
			return $this->error_response( esc_html__( 'Invalid nonce.', 'yclients-booking-wp' ), 403 );
		}

		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			return $this->error_response( esc_html__( 'Invalid payload.', 'yclients-booking-wp' ), 400 );
		}

		$payload = $this->sanitize_booking_payload( $params );
		if ( isset( $payload['error'] ) ) {
			return $this->error_response( $payload['error'], 400 );
		}

		$consent = $payload['consent'] ?? false;
		if ( ! $consent ) {
			return $this->error_response( esc_html__( 'Consent is required.', 'yclients-booking-wp' ), 400 );
		}

		Logger::log( sprintf( 'Consent stored: %s', gmdate( 'c' ) ) );

		unset( $payload['consent'] );
		$client = new ApiClient();
		return $this->handle_response( $client->create_booking( $payload ) );
	}

	private function sanitize_booking_payload( array $params ): array {
		$required = [ 'name', 'phone', 'service_id', 'staff_id', 'date', 'time' ];
		foreach ( $required as $field ) {
			if ( empty( $params[ $field ] ) ) {
				return [ 'error' => esc_html__( 'Missing required fields.', 'yclients-booking-wp' ) ];
			}
		}

		$name  = sanitize_text_field( wp_unslash( (string) $params['name'] ) );
		$phone = sanitize_text_field( wp_unslash( (string) $params['phone'] ) );
		$email = isset( $params['email'] ) ? sanitize_email( wp_unslash( (string) $params['email'] ) ) : '';
		$comment = isset( $params['comment'] ) ? sanitize_textarea_field( wp_unslash( (string) $params['comment'] ) ) : '';

		$service_id = absint( $params['service_id'] );
		$staff_id   = absint( $params['staff_id'] );
		$date       = $this->sanitize_date( (string) $params['date'] );
		$time       = sanitize_text_field( wp_unslash( (string) $params['time'] ) );

		if ( '' === $name || '' === $phone || $service_id <= 0 || $staff_id <= 0 || '' === $date || '' === $time ) {
			return [ 'error' => esc_html__( 'Invalid fields.', 'yclients-booking-wp' ) ];
		}

		$settings = Settings::get_settings();
		$payload  = [
			'client' => [
				'name'  => $name,
				'phone' => $phone,
				'email' => $email,
			],
			'service_id' => $service_id,
			'staff_id'   => $staff_id,
			'date'       => $date,
			'time'       => $time,
			'comment'    => $comment,
			'consent'    => ! empty( $params['consent'] ),
		];

		if ( ! empty( $settings['yclients_default_branch_id'] ) ) {
			$payload['branch_id'] = (int) $settings['yclients_default_branch_id'];
		}

		return $payload;
	}

	private function handle_response( array $response ): WP_REST_Response {
		if ( ! empty( $response['success'] ) ) {
			return new WP_REST_Response( $response['body'], 200 );
		}

		$code = $response['code'] ?? 500;
		return $this->error_response( $response['message'] ?? esc_html__( 'API error.', 'yclients-booking-wp' ), (int) $code );
	}

	private function error_response( string $message, int $status ): WP_REST_Response {
		return new WP_REST_Response(
			[
				'message' => $message,
			],
			$status
		);
	}

	private function verify_nonce( WP_REST_Request $request ): bool {
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! $nonce ) {
			$nonce = $request->get_param( '_wpnonce' );
		}

		return is_string( $nonce ) && wp_verify_nonce( $nonce, 'wp_rest' );
	}

	private function sanitize_date( string $value ): string {
		$value = sanitize_text_field( wp_unslash( $value ) );
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
			return '';
		}

		return $value;
	}

	private function is_rate_limited( string $context ): bool {
		$ip    = $this->get_client_ip();
		$key   = 'yclients_booking_rate_' . md5( $context . '|' . $ip );
		$count = (int) get_transient( $key );
		$count++;

		set_transient( $key, $count, self::RATE_WINDOW );

		return $count > self::RATE_LIMIT;
	}

	private function get_client_ip(): string {
		$ip = $_SERVER['REMOTE_ADDR'] ?? '';
		return sanitize_text_field( wp_unslash( (string) $ip ) );
	}
}
