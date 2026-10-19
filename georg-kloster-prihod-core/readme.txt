=== Georg-Kloster Parish Core ===
Contributors: vatapin
Tags: parish, church, clergy, contacts
Requires at least: 6.5
Requires PHP: 8.0
Stable tag: 0.1.5
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html

Parish profile, places and clergy. Works with any WordPress theme.

== Description ==

Adds a parish profile, site-section links, and private editor screens for places and clergy. Four dynamic blocks and shortcodes display this information on public pages. This plugin does not send form submissions or load external services. It works without the Georg-Kloster Parish theme, and the theme works without it.

== Installation ==

Upload the ZIP to Plugins, activate it, and open Parish in the dashboard. Add your profile, places, and clergy. Insert the Parish profile, Parish contacts, Clergy, or Places block in a page. The matching shortcodes are [gkpc_profile], [gkpc_contacts], [gkpc_clergy], and [gkpc_places].

== Privacy ==

The profile and directory remain in the local WordPress database. Uninstalling the plugin retains authored content and settings.

== Optional demo import ==

Parish > Demo Content offers an explicit preview and import of Saint Nicholas Parish in Birch Haven and its fictional training diocese. Russian and English content packs are separate JSON data files, not PHP strings or theme defaults. Five bundled monastery photographs/illustrations are copied into the local media library with captions identifying their real origin. Ten pages, four posts, two categories and an unassigned menu are created with editable WordPress blocks. No remote download, payment setup, mail or messaging configuration is performed.

The import requires appropriate administrator capabilities, a valid nonce and an unchecked consent checkbox. Merely activating the plugin or opening the preview does not import anything. An optional, separately selected action changes the front-page selection and fills only empty profile/theme fields and page links. Existing pages, filled fields and assigned menus are retained. The previous front-page selection is recorded in the import state; restore it under Settings > Reading. Optional sample services use the active Schedule plugin and its validation; no service post types or plugins are installed by this importer.

Interrupted imports can be resumed. The importer identifies its own items, does not duplicate them, preserves subsequent owner edits and does not restore trashed items. A different content language cannot replace an import already started. Imported material remains after deactivating Core and is removable through ordinary WordPress editing/media screens. No demo content is inserted into theme source files. The theme contains only a generic settings extension hook; Core owns the import entry point and all data writes.

== Demo resources ==

Demo texts and media: copyright 2026 Vladimir Atapin, GPLv2 or later. The parish, diocese, address and events are fictional. The photographs and illustrations are provided by Saint George Monastery in Gotschendorf and are not images of the fictional parish. Sources and individual credits are in demo/resources.json. Files are included locally; import does not contact their original sites. Photographs of real people are not assigned to fictional persons.

== Changelog ==

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
