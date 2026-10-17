=== Georg-Kloster Parish Schedule ===
Contributors: vatapin
Tags: church, schedule, calendar, services
Requires at least: 6.5
Requires PHP: 8.0
Stable tag: 0.1.7
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html

Independent service schedule with recurrences, changes, calendar hints and icons.

== Description ==

Create service places and services, including recurring dates, one-time events, cancellations, moved services and vestment colours. Publish a week, month or list view with the Schedule block or [gkpsch_schedule] shortcode. Upcoming services have separate blocks and shortcodes. The plugin works with any theme and without Parish Core; it uses the WordPress time zone by default.

Calendar suggestions, liturgical marks and day icons are fetched directly from the configured calendar API. The calendar service can be configured in Services > Settings. If its response is unavailable, the schedule remains editable and displays saved services. No services are created from calendar data.

== External services ==

Calendar hints contact https://bible-desktop.com by default. Dates, language and provider profile are passed through the calendar API; icon files are downloaded into local uploads. The site owner may configure another HTTPS calendar origin. This plugin does not transmit visitor contact data. The provider's terms and privacy policy apply to calendar requests.

== Installation ==

Upload and activate the ZIP. Open Services, add a service place, then add services. Insert the Schedule, Next service or Upcoming services block in a page. The public calendar API is preconfigured; it can be changed in Services > Settings.

== Changelog ==

= 0.1.7 =
Show the public place filter only when more than one service place is published.

= 0.1.6 =
Show a complete monthly calendar with commemorations, fasting rules, Typikon marks and compact services. Show schedule icons at a readable size in a bounded native WordPress lightbox and clarify the configured fallback image.

= 0.1.5 =
Restore the full schedule layout and controls in the standalone plugin.

= 0.1.4 =
Integrate calendar requests, cache, typikon marks and day icons directly into Schedule.

= 0.1.3 =
Register service and place names for WPML String Translation and read translated names from the database when available.

= 0.1.1 =
Load local translations when available.

= 0.1.0 =
* Initial standalone development release.
