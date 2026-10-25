=== Georg-Kloster Parish ===
Contributors: vatapin
Tags: parish, church, clergy, contacts
Requires at least: 6.5
Requires PHP: 8.0
Tested up to: 7.1
Stable tag: 0.2.12
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html

Parish profile, clergy, service places with images, schedules and calendar.

== Description ==

Adds a parish profile, site-section links, clergy, service places, recurring services, cancellations, calendar views and upcoming services under one Parish menu. Works with any theme; the Parish theme also works without this plugin. Forms and messaging belong to the separate Connect Hub plugin.

Service places and clergy use dedicated directory forms rather than the standard content editor. Place forms include description, address, phone, email, website, time zone and separate logo/photo controls. Clergy forms include biography, rank, contacts and multiple service-place associations. Existing descriptions, fields and associations are retained.

Places have independent logo and photo fields with native media-library selection, outside the description. Images appear in the Places block and above the schedule for a selected place (or the only published place). Existing descriptions, featured images, places, clergy and service IDs are retained. Old Schedule places share the new Places list without moving or deleting their records. New places use the Parish directory; both old and new IDs work in services.

The previously separate Schedule implementation is bundled here. Existing gkpsch blocks, shortcodes, service metadata, REST routes, cron hooks and settings remain compatible. The old Schedule plugin is silently deactivated on activation or the first request after a ZIP update. If it was already loaded in that request, its PHP classes finish once; the next request uses the bundled module. Network-active Schedule requires network-wide Parish activation.

== Support ==

Author: Atapin Vladimir. Website: https://georg-kloster.ru/. Email: atapin@gmail.com.

== Installation ==

Upload the ZIP to Plugins, activate it, and open Parish in the dashboard. Add your profile, places, and clergy. Insert the Parish profile, Parish contacts, Clergy, or Places block in a page. The matching shortcodes are [gkpc_profile], [gkpc_contacts], [gkpc_clergy], and [gkpc_places].

For an installed Parish Core, upload georg-kloster-prihod-core-0.2.12.zip and replace the installed version. Keep the existing georg-kloster-prihod-core folder/slug. No manual database migration is needed. Create services under Parish and configure calendar display under Parish > Schedule settings. Schedule blocks and [gkpsch_schedule], [gkpsch_next_service], [gkpsch_next_services] remain available. No separate Schedule ZIP is required.

== Shortcodes ==

Dashboard help is available under Parish > Parish profile and Parish > Schedule settings, in the Shortcodes and blocks section. Insert examples into a WordPress Shortcode block. Equivalent Parish and Schedule blocks are also available.

* [gkpc_profile] — parish name and description. No parameters.
* [gkpc_contacts] — parish address, phone, email and website. No parameters.
* [gkpc_clergy] — published clergy profiles. No parameters.
* [gkpc_places] — published service places with logos and photos. No parameters.
* [gkpsch_schedule view="month" location="123"] — schedule. view accepts week (default), month or list; location is a service place ID, with 0 (default) showing all published places.
* [gkpsch_next_service location="123"] — next service, optionally filtered by place.
* [gkpsch_next_services location="123" limit="5"] — upcoming services, optionally filtered by place; limit is 1–50, default 5.

Replace 123 with the place ID from its edit URL (post=123), or omit location to include all published places. Shortcode names, attributes, post types and stored content remain unchanged across interface languages.

== External services ==

Calendar hints, liturgical marks and day icons use the fixed public HTTPS origin https://bible-desktop.com. Dates, language and the selected calendar profile are sent to its calendar API. Calendar icons are downloaded and cached locally; visitor contact data is not transmitted. The API address is not an editable setting and saved custom origins are ignored. The provider's terms/privacy policy apply. Saved services remain editable if the calendar service is unavailable. This plugin does not create services from calendar responses or send form submissions.

== Privacy ==

The profile and directory remain in the local WordPress database. Uninstalling the plugin retains authored content and settings.

== Optional demo import ==

Parish > Demo Content offers an explicit preview and import of Saint Nicholas Parish in Birch Haven and its fictional training diocese. Russian and English content packs are separate JSON data files, not PHP strings or theme defaults. Five bundled monastery photographs/illustrations are copied into the local media library with captions identifying their real origin. Ten pages, four posts, two categories and an unassigned menu are created with editable WordPress blocks. No remote download, payment setup, mail or messaging configuration is performed.

The import requires appropriate administrator capabilities, a valid nonce and an unchecked consent checkbox. Merely activating the plugin or opening the preview does not import anything. An optional, separately selected action changes the front-page selection and fills only empty profile/theme fields and page links. Existing pages, filled fields and assigned menus are retained. The previous front-page selection is recorded in the import state; restore it under Settings > Reading. Optional sample services use the bundled Schedule module and its validation. They are added only when selected, and no other plugin is installed by this importer. Previous imported places are reused, including places created by the separate Schedule plugin.

Interrupted imports can be resumed. The importer identifies its own items, does not duplicate them, preserves subsequent owner edits and does not restore trashed items. A different content language cannot replace an import already started. Imported material remains after deactivating Core and is removable through ordinary WordPress editing/media screens. No demo content is inserted into theme source files. The theme contains only a generic settings extension hook; Core owns the import entry point and all data writes.

== Demo resources ==

Author: Atapin Vladimir. Website: https://georg-kloster.ru/. Support: atapin@gmail.com.

Demo texts and media: copyright 2026 Atapin Vladimir, GPLv2 or later. The parish, diocese, address and events are fictional. The photographs and illustrations are provided by Saint George Monastery in Gotschendorf and are not images of the fictional parish. Sources and individual credits are in demo/resources.json. Files are included locally; import does not contact their original sites. Photographs of real people are not assigned to fictional persons.

== Changelog ==

= 0.2.12 =
Show every calendar commemoration in full schedule service cards and keep the overview heading clear of its calendar icon.

= 0.2.11 =
Scale upcoming service titles to sidebar width and prevent the calendar icon overlapping the overview heading.

= 0.2.10 =
Add spacing between vestment accent stripes and service content on mobile and schedule views.

= 0.2.9 =
Increase the saturation and brightness of non-white vestment accents.

= 0.2.8 =
Use consistent patterned, framed vestment accents for every liturgical color.

= 0.2.7 =
Make white vestment accents visible with a silver frost motif in schedule views.

= 0.2.6 =
Improve visibility of white vestment accents with a silver frosted pattern.

= 0.2.5 =
Add plugin author, website and support contact metadata.

= 0.2.4 =
Hide the standard content editor and restore dedicated service-place and clergy forms. Keep descriptions in post_content; restore clergy contacts/place associations and the service-place website field.

= 0.2.3 =
Register Schedule settings after the Parish menu so its admin link resolves correctly.

= 0.2.2 =
Use English source UI strings with bundled Russian translations. Add in-dashboard shortcode descriptions, parameters and examples. Keep multilingual calendar matching data separate from executable sources.

= 0.2.1 =
Require a service place before saving in the classic editor. Show a localized validation message without clearing the form. Reject invalid editor submissions before WordPress writes a partial service post; preserve existing services on validation failure. Connect fallback error notices.

= 0.2.0 =
Merge Schedule into Parish while retaining its public identifiers, existing data and optional demo workflow. Unify places without rewriting IDs. Add separate place logos/photos and public display. Fix the calendar API origin in code and remove its settings field. Retire the separate Schedule package.

= 0.1.5 =
Add a consent-based, resumable fictional parish demo importer with native WordPress content, local media, optional theme setup and optional Schedule examples. Preserve existing content and owner edits. Add a plugin-owned import entry to compatible theme settings.

= 0.1.4 =
Bundle complete Russian interface and editor translations. Load translations at init.


= 0.1.3 =
Add editable section links for the theme home page and navigation. Register parish profile fields for WPML String Translation and read translations from the database when available.

= 0.1.1 =
Expose profile texts for translation through WPML when it is installed.

= 0.1.0 =
* First standalone release.
