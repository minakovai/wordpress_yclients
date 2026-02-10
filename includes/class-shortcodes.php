<?php

declare(strict_types=1);

namespace YclientsBookingWp;

final class Shortcodes {
	public function __construct() {
		add_shortcode( 'yclients_booking', [ $this, 'render_form' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'register_assets' ] );
	}

	public function register_assets(): void {
		wp_register_script(
			'yclients-booking-wp',
			YCLIENTS_BOOKING_WP_URL . 'assets/booking.js',
			[],
			YCLIENTS_BOOKING_WP_VERSION,
			true
		);

		wp_register_style(
			'yclients-booking-wp',
			YCLIENTS_BOOKING_WP_URL . 'assets/booking.css',
			[],
			YCLIENTS_BOOKING_WP_VERSION
		);
	}

	public function render_form(): string {
		wp_enqueue_script( 'yclients-booking-wp' );
		wp_enqueue_style( 'yclients-booking-wp' );

		wp_localize_script(
			'yclients-booking-wp',
			'YclientsBookingWpData',
			[
				'restUrl' => esc_url_raw( rest_url( YCLIENTS_BOOKING_WP_REST_NAMESPACE ) ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
				'strings' => [
					'loading'        => esc_html__( 'Loading...', 'yclients-booking-wp' ),
					'error'          => esc_html__( 'An error occurred. Please try again.', 'yclients-booking-wp' ),
					'select_service' => esc_html__( 'Select a service', 'yclients-booking-wp' ),
					'select_staff'   => esc_html__( 'Select a staff member', 'yclients-booking-wp' ),
					'select_date'    => esc_html__( 'Select a date', 'yclients-booking-wp' ),
					'select_time'    => esc_html__( 'Select a time', 'yclients-booking-wp' ),
					'submit'         => esc_html__( 'Book now', 'yclients-booking-wp' ),
					'success'        => esc_html__( 'Booking created.', 'yclients-booking-wp' ),
				],
			]
		);

		ob_start();
		?>
		<div class="yclients-booking-form" data-yclients-booking-form>
			<form>
				<div class="yclients-field">
					<label for="yclients-name"><?php echo esc_html__( 'Name', 'yclients-booking-wp' ); ?></label>
					<input type="text" id="yclients-name" name="name" required />
				</div>
				<div class="yclients-field">
					<label for="yclients-phone"><?php echo esc_html__( 'Phone', 'yclients-booking-wp' ); ?></label>
					<input type="tel" id="yclients-phone" name="phone" required />
				</div>
				<div class="yclients-field">
					<label for="yclients-email"><?php echo esc_html__( 'Email (optional)', 'yclients-booking-wp' ); ?></label>
					<input type="email" id="yclients-email" name="email" />
				</div>
				<div class="yclients-field">
					<label for="yclients-service"><?php echo esc_html__( 'Service', 'yclients-booking-wp' ); ?></label>
					<select id="yclients-service" name="service_id" required></select>
				</div>
				<div class="yclients-field">
					<label for="yclients-staff"><?php echo esc_html__( 'Staff', 'yclients-booking-wp' ); ?></label>
					<select id="yclients-staff" name="staff_id" required></select>
				</div>
				<div class="yclients-field">
					<label for="yclients-date"><?php echo esc_html__( 'Date', 'yclients-booking-wp' ); ?></label>
					<select id="yclients-date" name="date" required></select>
				</div>
				<div class="yclients-field">
					<label for="yclients-time"><?php echo esc_html__( 'Time', 'yclients-booking-wp' ); ?></label>
					<select id="yclients-time" name="time" required></select>
				</div>
				<div class="yclients-quick-slots" aria-live="polite">
					<h3><?php echo esc_html__( 'Available slots for ближайшие дни', 'yclients-booking-wp' ); ?></h3>
					<div class="yclients-slots-list" data-yclients-slots></div>
				</div>
				<div class="yclients-field">
					<label for="yclients-comment"><?php echo esc_html__( 'Comment (optional)', 'yclients-booking-wp' ); ?></label>
					<textarea id="yclients-comment" name="comment"></textarea>
				</div>
				<div class="yclients-field yclients-consent">
					<label>
						<input type="checkbox" name="consent" required />
						<?php echo esc_html__( 'I agree to the processing of personal data', 'yclients-booking-wp' ); ?>
					</label>
				</div>
				<button type="submit" class="yclients-submit"><?php echo esc_html__( 'Book now', 'yclients-booking-wp' ); ?></button>
				<div class="yclients-message" role="status" aria-live="polite"></div>
			</form>
		</div>
		<?php
		return (string) ob_get_clean();
	}
}
