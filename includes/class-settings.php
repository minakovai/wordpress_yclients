<?php

declare(strict_types=1);

namespace YclientsBookingWp;

final class Settings {
	private const MENU_SLUG = 'yclients-booking-settings';
	private const OPTION_GROUP = 'yclients_booking_wp_group';
	private const NONCE_ACTION = 'yclients_booking_wp_check_connection';

	public function __construct() {
		add_action( 'admin_menu', [ $this, 'register_menu' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'admin_post_yclients_booking_check_connection', [ $this, 'handle_check_connection' ] );
		add_action( 'admin_notices', [ $this, 'render_notice' ] );
	}

	public static function get_settings(): array {
		$defaults = [
			'yclients_partner_token'      => '',
			'yclients_user_token'         => '',
			'yclients_company_id'         => 0,
			'yclients_default_branch_id'  => 0,
			'yclients_timezone'           => 'Europe/Moscow',
			'yclients_debug_logging'      => false,
		];

		$settings = get_option( YCLIENTS_BOOKING_WP_OPTION_KEY, [] );
		if ( ! is_array( $settings ) ) {
			$settings = [];
		}

		return array_merge( $defaults, $settings );
	}

	public function register_menu(): void {
		add_options_page(
			esc_html__( 'YCLIENTS Booking', 'yclients-booking-wp' ),
			esc_html__( 'YCLIENTS Booking', 'yclients-booking-wp' ),
			'manage_options',
			self::MENU_SLUG,
			[ $this, 'render_page' ]
		);
	}

	public function register_settings(): void {
		register_setting(
			self::OPTION_GROUP,
			YCLIENTS_BOOKING_WP_OPTION_KEY,
			[ $this, 'sanitize_settings' ]
		);

		add_settings_section(
			'yclients_booking_main',
			esc_html__( 'API Settings', 'yclients-booking-wp' ),
			'__return_false',
			self::MENU_SLUG
		);

		$this->add_field( 'yclients_partner_token', esc_html__( 'Partner token', 'yclients-booking-wp' ) );
		$this->add_field( 'yclients_user_token', esc_html__( 'User token', 'yclients-booking-wp' ) );
		$this->add_field( 'yclients_company_id', esc_html__( 'Company ID', 'yclients-booking-wp' ), 'number' );
		$this->add_field( 'yclients_default_branch_id', esc_html__( 'Default branch ID', 'yclients-booking-wp' ), 'number' );
		$this->add_field( 'yclients_timezone', esc_html__( 'Timezone', 'yclients-booking-wp' ) );
		$this->add_field( 'yclients_debug_logging', esc_html__( 'Debug logging', 'yclients-booking-wp' ), 'checkbox' );
	}

	private function add_field( string $key, string $label, string $type = 'text' ): void {
		add_settings_field(
			$key,
			$label,
			[ $this, 'render_field' ],
			self::MENU_SLUG,
			'yclients_booking_main',
			[
				'key'  => $key,
				'type' => $type,
			]
		);
	}

	public function sanitize_settings( array $input ): array {
		$sanitized = self::get_settings();

		if ( isset( $input['yclients_partner_token'] ) ) {
			$partner_token = sanitize_text_field( wp_unslash( $input['yclients_partner_token'] ) );
			if ( '' !== $partner_token ) {
				$sanitized['yclients_partner_token'] = $partner_token;
			}
		}

		if ( isset( $input['yclients_user_token'] ) ) {
			$user_token = sanitize_text_field( wp_unslash( $input['yclients_user_token'] ) );
			if ( '' !== $user_token ) {
				$sanitized['yclients_user_token'] = $user_token;
			}
		}

		$sanitized['yclients_company_id'] = isset( $input['yclients_company_id'] )
			? absint( $input['yclients_company_id'] )
			: 0;

		$sanitized['yclients_default_branch_id'] = isset( $input['yclients_default_branch_id'] )
			? absint( $input['yclients_default_branch_id'] )
			: 0;

		$timezone = isset( $input['yclients_timezone'] )
			? sanitize_text_field( wp_unslash( $input['yclients_timezone'] ) )
			: 'Europe/Moscow';
		$sanitized['yclients_timezone'] = $timezone ?: 'Europe/Moscow';

		$sanitized['yclients_debug_logging'] = isset( $input['yclients_debug_logging'] )
			? (bool) $input['yclients_debug_logging']
			: false;

		return $sanitized;
	}

	public function render_field( array $args ): void {
		$settings = self::get_settings();
		$key      = $args['key'];
		$type     = $args['type'];
		$value    = $settings[ $key ] ?? '';

		$display_value = $value;

		if ( 'checkbox' === $type ) {
			printf(
				'<input type="checkbox" name="%1$s[%2$s]" value="1" %3$s />',
				esc_attr( YCLIENTS_BOOKING_WP_OPTION_KEY ),
				esc_attr( $key ),
				checked( (bool) $value, true, false )
			);
			return;
		}

		$input_type  = $type;
		$input_value = (string) $display_value;

		if ( in_array( $key, [ 'yclients_partner_token', 'yclients_user_token' ], true ) ) {
			$input_type  = 'password';
			$input_value = '';
		}

		printf(
			'<input type="%1$s" class="regular-text" name="%2$s[%3$s]" value="%4$s" autocomplete="off" />',
			esc_attr( $input_type ),
			esc_attr( YCLIENTS_BOOKING_WP_OPTION_KEY ),
			esc_attr( $key ),
			esc_attr( $input_value )
		);

		if ( 'yclients_user_token' === $key ) {
			echo '<p class="description">' . esc_html__( 'Optional: required for some endpoints.', 'yclients-booking-wp' ) . '</p>';
		}

		if ( in_array( $key, [ 'yclients_partner_token', 'yclients_user_token' ], true ) && '' !== (string) $value ) {
			echo '<p class="description">' . esc_html__( 'Current token:', 'yclients-booking-wp' ) . ' ' . esc_html( $this->mask_token( (string) $value ) ) . '</p>';
		}
	}

	private function mask_token( string $token ): string {
		if ( '' === $token ) {
			return '';
		}

		$length = strlen( $token );
		if ( $length <= 6 ) {
			return str_repeat( '*', $length );
		}

		return substr( $token, 0, 3 ) . str_repeat( '*', $length - 6 ) . substr( $token, -3 );
	}

	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = self::get_settings();
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'YCLIENTS Booking', 'yclients-booking-wp' ); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( self::OPTION_GROUP );
				do_settings_sections( self::MENU_SLUG );
				submit_button();
				?>
			</form>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( self::NONCE_ACTION ); ?>
				<input type="hidden" name="action" value="yclients_booking_check_connection" />
				<?php submit_button( esc_html__( 'Check connection', 'yclients-booking-wp' ), 'secondary' ); ?>
			</form>
			<p class="description">
				<?php echo esc_html__( 'Tokens are stored in wp_options. Do not share them publicly.', 'yclients-booking-wp' ); ?>
			</p>
		</div>
		<?php
	}

	public function handle_check_connection(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'yclients-booking-wp' ) );
		}

		check_admin_referer( self::NONCE_ACTION );

		$client = new ApiClient();
		$result = $client->check_connection();

		update_option( YCLIENTS_BOOKING_WP_LOG_OPTION_KEY, $result, false );

		wp_safe_redirect( admin_url( 'options-general.php?page=' . self::MENU_SLUG ) );
		exit;
	}

	public function render_notice(): void {
		$notice = get_option( YCLIENTS_BOOKING_WP_LOG_OPTION_KEY );
		if ( empty( $notice ) || ! is_array( $notice ) ) {
			return;
		}

		delete_option( YCLIENTS_BOOKING_WP_LOG_OPTION_KEY );

		$status  = $notice['status'] ?? 'error';
		$message = $notice['message'] ?? '';
		$code    = $notice['code'] ?? '';
		$request = $notice['request_id'] ?? '';

		$class = 'notice notice-' . ( 'success' === $status ? 'success' : 'error' );

		echo '<div class="' . esc_attr( $class ) . '">';
		echo '<p>' . esc_html( $message ) . '</p>';
		if ( '' !== $code ) {
			echo '<p>' . esc_html__( 'HTTP code:', 'yclients-booking-wp' ) . ' ' . esc_html( (string) $code ) . '</p>';
		}
		if ( '' !== $request ) {
			echo '<p>' . esc_html__( 'Request ID:', 'yclients-booking-wp' ) . ' ' . esc_html( (string) $request ) . '</p>';
		}
		echo '</div>';
	}
}
