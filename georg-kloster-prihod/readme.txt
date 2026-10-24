=== Georg-Kloster Prihod ===
Contributors: atapin
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html

A parish theme with five visual styles for standard WordPress pages and posts.

== Description ==

Georg-Kloster Prihod provides five visual styles: Classic, Hospitality, Editorial, Tradition, and Blue. The theme works on a standard WordPress installation without a companion plugin. Choose a style and your own images under Appearance > Parish Appearance. Standard pages and posts remain editable in WordPress.

The theme does not create pages. Set your own name, description, community type and contacts under Appearance > Parish Appearance > Site Details. Choose ordinary WordPress pages for homepage cards and related theme sections under Homepage. Optional Parish supplies profile, clergy, places and schedules; Connect Hub supplies forms and communications. Schedule is bundled in Parish rather than installed separately. No bundled photographs appear on the public site.

The Optional Plugins section shows compact screenshot cards for the five Georg-Kloster plugins: Parish, Connect Hub, Calendar Workshop, Orthodox Donations and Slavonic Translator. Cards show whether a plugin is installed and link to settings when available. Public plugins link to the standard WordPress installer search and their WordPress.org details. The theme makes no plugin-directory API requests and does not install plugins.

== Support ==

Author: Atapin Vladimir. Website: https://georg-kloster.ru/. Email: atapin@gmail.com. Source code: https://github.com/VAtapin/Orthodox/tree/main/georg-kloster-prihod.

== Installation ==

Upload the theme ZIP under Appearance > Themes, activate it, then choose a visual style under Appearance > Parish Appearance. Add your own pages, navigation, logo, photographs and contact details in WordPress. Create a standard WordPress menu and assign it to the Footer menu location to control footer links. Under Homepage > Contacts, choose whether to show the address, phone, email and map. The homepage contact area can show an OpenStreetMap embed when coordinates are configured; Google Maps is also available with an API key. Map embeds are loaded from the selected provider only after coordinates are entered.

== External services ==

The homepage map is optional and does not load until an administrator enters map coordinates. OpenStreetMap is the default provider (https://www.openstreetmap.org/); administrators can instead choose Google Maps Platform Embed API (https://developers.google.com/maps/documentation/embed/get-started) and enter an API key. The selected provider receives the visitor's IP address, browser request data and the configured map coordinates when the map loads. Restrict Google API keys to the website in Google Cloud. OpenStreetMap privacy policy: https://osmfoundation.org/wiki/Privacy_Policy. Google privacy policy: https://policies.google.com/privacy.

== Frequently Asked Questions ==

= Where can I edit the right column? =

Open Appearance > Widgets and add standard WordPress blocks such as paragraphs, search, latest posts, categories, archives, calendar or custom HTML to Right sidebar. If no widgets are assigned, the theme displays its built-in calendar, service schedule, contact link and parish quotation.

= Does the theme require a plugin? =

No. It displays ordinary WordPress pages and posts on its own.

= Where do the photographs come from? =

The theme uses images selected from your WordPress media library. It does not download or import photographs.

= How do I add images to the menu? =

Create and assign a menu under Appearance > Menus. Open a menu item and choose its image from the media library; without a chosen image, the linked page or post featured image is used. The theme's Appearance > Parish Appearance > Header and Footer section enables an image menu alongside the standard desktop link row and sets the number of visual columns. The link-row-only option remains available. Menu item descriptions can be enabled under Screen Options on the Menus screen.

= How do I edit the footer links? =

Create or edit a standard WordPress menu under Appearance > Menus and assign it to the Footer menu location. With WPML, create a translated menu for each language and assign it to the same location.

== Changelog ==

= 1.0.0 =
* First public release. Includes five visual styles, native WordPress settings and widgets, multilingual plugin compatibility, optional plugin guidance, and responsive homepage layouts.

== Resources ==

Theme PHP, CSS, JavaScript, and SVG assets: © 2026 Atapin Vladimir, GPLv2 or later, included in this package.
Theme screenshot and style previews, captured from the theme demonstration: © 2026 Atapin Vladimir, GPLv2 or later, included in this package.
Plugin settings screenshots in assets/plugins, captured from the standalone GPLv2-or-later Georg-Kloster Parish plugins on a disposable WordPress installation with empty settings: © 2026 Atapin Vladimir, GPLv2 or later. WordPress admin interface: WordPress contributors, GPLv2 or later, https://wordpress.org/about/license/. Screenshots are documentation only and are never inserted into public pages.
WordPress core blocks and APIs are provided by WordPress, GPLv2 or later, https://wordpress.org/about/license/.
