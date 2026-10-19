=== Bundled Parish Schedule module ===
Contributors: vatapin
Tags: church, schedule, calendar, services
Requires at least: 6.5
Requires PHP: 8.0
Module version: follows the parent Parish package
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html

Internal schedule module of Georg-Kloster Parish. Not a separate plugin.

== Description ==

Create services under Parish, including recurring dates, one-time events, cancellations, moved services and vestment colours. Places use the shared Parish directory. Publish a week, month or list view with the Schedule block or [gkpsch_schedule] shortcode. The module uses the WordPress time zone by default and retains existing Schedule identifiers.

Calendar suggestions, liturgical marks and day icons are fetched from the fixed public calendar API. Display options are under Parish > Schedule settings. If its response is unavailable, the schedule remains editable and displays saved services. No services are created from calendar data.

== External services ==

Calendar hints contact https://bible-desktop.com. Dates, language and provider profile are passed through the calendar API; icon files are downloaded into local uploads. The origin is fixed in code. The module does not transmit visitor contact data. The provider's terms and privacy policy apply to calendar requests.

== Installation ==

Install or update the parent Parish ZIP. This directory has no standalone activation entry. Insert the Schedule, Next service or Upcoming services block in a page. The API address is not an editable setting.

== Changelog ==

= 0.1.10 =
Bundle complete Russian interface and editor translations. Load translations at init.


= 0.1.9 =
Allow compact theme cards to resolve automatic vestment colours without displaying extra feast text or marks.

= 0.1.8 =
Use one public place-label rule across schedule views and theme cards: show place names only when multiple places are published.

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
