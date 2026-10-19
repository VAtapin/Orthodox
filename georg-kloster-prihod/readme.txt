=== Georg-Kloster Prihod ===
Contributors: vatapin
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html

A parish theme with five visual styles for standard WordPress pages and posts.

== Description ==

Georg-Kloster Prihod provides five visual styles: Classic, Hospitality, Editorial, Tradition, and Blue. The theme works on a standard WordPress installation without a companion plugin. Choose a style and your own images under Appearance > Parish Appearance. Standard pages and posts remain editable in WordPress.

The theme does not create pages or send data to external services. Set your own name, description, community type and contacts under Appearance > Parish Appearance > Site Details. Choose ordinary WordPress pages for links throughout the theme under Homepage. Optional plugins can supply calendar and automatic schedule data, or fill empty contact fields. No bundled photographs appear on the public site.

== Installation ==

Upload the theme ZIP under Appearance > Themes, activate it, then choose a visual style under Appearance > Parish Appearance. Add your own pages, navigation, logo, and photographs in WordPress. The theme does not load an external map or contact service. Add those features through page content or optional plugins.

== Frequently Asked Questions ==

= Does the theme require a plugin? =

No. It displays ordinary WordPress pages and posts on its own.

= Where do the photographs come from? =

The theme uses images selected from your WordPress media library. It does not download or import photographs.

= How do I add images to the menu? =

Create and assign a menu under Appearance > Menus. Open a menu item and choose its image from the media library; without a chosen image, the linked page or post featured image is used. The theme's Appearance > Parish Appearance > Header and Footer section enables an image menu alongside the standard desktop link row and sets the number of visual columns. The link-row-only option remains available. Menu item descriptions can be enabled under Screen Options on the Menus screen.

== Changelog ==

= 0.4.30 =
Use English gettext source strings with bundled Russian translations, including native theme.json contexts. Replace the theme screenshot with the full Classic preview. Remove output-warning suppression comments while retaining renderer escaping.

= 0.4.29 =
Remove built-in homepage copy, greetings, mottos and quotations. Store headings, eyebrows, descriptions and action labels in WordPress settings. Empty text fields stay empty; card titles can use the linked WordPress page title. Bundle only UI messages actually used by the theme in translation catalogs.

= 0.4.28 =
Add independent site identity and contact settings. Use theme page selections throughout the header, footer, sidebar and page templates. Companion plugins remain optional; existing plugin data is retained as a fallback for empty settings.

= 0.4.27 =
Add homepage section visibility, ordering, custom headings, page links, card order and labels, service count and news appearance controls under Appearance > Parish Appearance > Homepage. Keep the hero and section layouts in theme templates; no page content is created or changed. Custom text supports WPML String Translation.

= 0.4.25 =
Set classic homepage section-card and news-card photographs to 200px high at every viewport width.

= 0.4.24 =
Restore the next service vestment colour from Schedule and center its title when the place label is hidden.

= 0.4.23 =
Keep simple desktop navigation beside the optional image menu, simplify its trigger and align submenu indicators.

= 0.4.22 =
Apply the responsive archive cards to tags and other WordPress archives; show a clean term title instead of escaped markup.

= 0.4.21 =
Keep the schedule button in the homepage hero beside the directions button when the nearby service panel is visible.

= 0.4.20 =
Add a responsive visual navigation panel with optional images on standard WordPress menu items and theme controls for its layout.

= 0.4.19 =
Style editable core blocks for the monastery story, chronology and related people on both language pages.

= 0.4.18 =
Hide the place label and its icon in compact service cards when only one service place is published.

= 0.4.17 =
Fix category filters on catalog pages whose Query block covers all posts.

= 0.4.16 =
Restore the angled classic hero motto and avoid repeating schedule links around the nearby service panel.

= 0.4.15 =
Show configured visitor and sermons pages on the homepage and in the footer, and support accessible classic menu submenus.

= 0.4.14 =
Add a compact mobile menu for classic WordPress navigation and improve the mobile header and hero readability.

= 0.4.13 =
Style the standard WordPress comment form, threaded replies and pagination.

= 0.4.12 =
Style WordPress category archives with responsive cards and a useful empty state.

= 0.4.11 =
Keep featured images within the article column beside the sidebar.

= 0.4.4 =
Read section links from Parish Core when present, resolve their translations, and display authored front-page blocks after the news section. Allow WPML to translate the site tagline.

= 0.4.2 =
Uses the correct post type when WPML resolves a linked page or post.

= 0.4.1 =
Connects the service preview and calendar icons to the standalone Schedule plugin.

= 0.4.0 =

* Separated theme presentation from plugin features and added five style choices.

== Resources ==

Theme PHP, CSS, JavaScript, and SVG assets: © 2026 Vladimir Atapin, GPLv2 or later, included in this package.
Theme screenshot and style previews, captured from the theme demonstration: © 2026 Vladimir Atapin, GPLv2 or later, included in this package.
WordPress core blocks and APIs are provided by WordPress, GPLv2 or later, https://wordpress.org/about/license/.
