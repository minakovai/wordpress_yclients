=== YCLIENTS Booking WP ===
Contributors: yclients-booking-wp
Tags: booking, yclients, appointment
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Integrates a booking form with YCLIENTS REST API.

== Description ==

YCLIENTS Booking WP provides a frontend booking form and REST API endpoints to connect your WordPress site to YCLIENTS.

== Installation ==

1. Upload the `yclients-booking-wp` folder to `/wp-content/plugins/`.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Go to Settings → YCLIENTS Booking and enter your tokens, Company ID, and other settings.
4. Add the shortcode `[yclients_booking]` to any page.

== Usage ==

* Configure settings: Settings → YCLIENTS Booking.
* Use shortcode: `[yclients_booking]`.

== Security ==

Tokens are stored in `wp_options`. Only administrators should have access to settings. Do not publish tokens in public code.

== API Errors ==

Typical YCLIENTS errors and how they appear:

* Invalid token: "API error" message is shown to the user and logged in debug mode.
* Validation error: YCLIENTS returns a message which is displayed to the user.
* Rate limit: WordPress returns "Too many requests." with HTTP 429.

== Privacy ==

The booking form requires consent for personal data processing. Consent timestamp is logged when booking is created.

== Changelog ==

= 1.0.0 =
* Initial release.
