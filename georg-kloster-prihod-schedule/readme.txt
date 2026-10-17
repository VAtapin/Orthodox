=== Georg-Kloster Parish Schedule ===
Contributors: vatapin
Tags: church, schedule, calendar, services
Requires at least: 6.5
Requires PHP: 8.0
Stable tag: 0.1.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html

Independent service schedule with recurrences, changes, calendar hints and icons.

== Description ==

Create service places and services, including recurring dates, one-time events, cancellations, moved services and vestment colours. Publish a week, month or list view with the Schedule block or [gkpsch_schedule] shortcode. Upcoming services have separate blocks and shortcodes. The plugin works with any theme and without Parish Core; it uses the WordPress time zone by default.

Calendar suggestions and day icons use a separately installed Orthocal plugin. If that plugin is absent or its response is incomplete, the schedule remains editable and displays only saved services. No services are created from calendar data.

== External services ==

When the Orthocal plugin is installed and calendar hints are requested, it may contact its configured calendar provider. Dates, language and provider profile are passed through its API. See the Orthocal plugin documentation for its service terms and privacy policy. Calendar integration is optional; this plugin does not transmit visitor contact data.

== Installation ==

Upload and activate the ZIP. Open Services, add a service place, then add services. Insert the Schedule, Next service or Upcoming services block in a page. A calendar provider is optional.

== Changelog ==

= 0.1.3 =
Register service and place names for WPML String Translation and read translated names from the database when available.

= 0.1.1 =
Load local translations when available.

= 0.1.0 =
* Initial standalone development release.
