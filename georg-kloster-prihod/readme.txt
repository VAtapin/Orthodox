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

The theme does not create pages. Set your own name, description, community type and contacts under Appearance > Parish Appearance > Site Details. Choose ordinary WordPress pages for links throughout the theme under Homepage. Optional Parish supplies profile, clergy, places and schedules; Connect Hub supplies forms and communications. Schedule is bundled in Parish rather than installed separately. No bundled photographs appear on the public site.

Under Optional Plugins, installed integrations have descriptions, screenshots and links to their settings. The theme contacts the WordPress.org plugin information API only when an administrator clicks Check WordPress.org. The request contains the plugin slugs and WordPress locale, with standard WordPress HTTP request metadata. Directory results are cached locally for one hour. No site content or contact-form messages are sent. Only confirmed WordPress.org entries are recommended for installation; unavailable or unpublished plugins are not advertised to new installations. Installation and activation require an explicit click and use WordPress core screens, capabilities, nonces and compatibility checks. The theme never bundles, downloads or activates plugins automatically. WordPress.org privacy policy: https://wordpress.org/about/privacy/.

== Support ==

Author: Atapin Vladimir. Website: https://georg-kloster.ru/. Email: atapin@gmail.com.

== Installation ==

Upload the theme ZIP under Appearance > Themes, activate it, then choose a visual style under Appearance > Parish Appearance. Add your own pages, navigation, logo, and photographs in WordPress. The theme does not load an external map or contact service. Add those features through page content or optional plugins.

== Frequently Asked Questions ==

= Where can I edit the right column? =

Open Appearance > Widgets and add standard WordPress blocks such as paragraphs, search, latest posts, categories, archives, calendar or custom HTML to Right sidebar. If no widgets are assigned, the theme displays its built-in calendar, service schedule, contact link and parish quotation.

= Does the theme require a plugin? =

No. It displays ordinary WordPress pages and posts on its own.

= Where do the photographs come from? =

The theme uses images selected from your WordPress media library. It does not download or import photographs.

= How do I add images to the menu? =

Create and assign a menu under Appearance > Menus. Open a menu item and choose its image from the media library; without a chosen image, the linked page or post featured image is used. The theme's Appearance > Parish Appearance > Header and Footer section enables an image menu alongside the standard desktop link row and sets the number of visual columns. The link-row-only option remains available. Menu item descriptions can be enabled under Screen Options on the Menus screen.

== Changelog ==

= 0.4.42 =
* Add the logo removal control to native Customizer branding.

= 0.4.41 =
* Ensure live-preview controls use the current normalized theme defaults.

= 0.4.40 =
* Add native Customizer controls with live preview for theme design, branding, site details and homepage sections. Keep the full Parish Appearance settings page.

= 0.4.39 =
Set the theme author, website and contact in the package metadata.

= 0.4.38 =
Register the right column as a standard WordPress widget area. Core widgets can replace its automatic sidebar content; the existing calendar, services and contact link remain as the empty-area default.

= 0.4.34 =
Add a generic homepage settings extension hook. An active Parish Core can provide its own demo import link; demo data, preview and import remain entirely in the optional plugin. No theme content or automatic setup is added.

= 0.4.33 =
Keep authored homepage galleries full width in every style, with responsive columns, 200px image frames and the native WordPress image viewer. Prevent the blue layout from squeezing a gallery into its old narrow navigation column. Page content is not changed.

= 0.4.32 =
Add an Optional Plugins section with real screenshots, descriptions, installed states and settings links. Offer standard WordPress installation only for directory-confirmed plugins, after an explicit directory check. Keep the theme independent and all content unchanged.

= 0.4.31 =
Restore the five style layouts with card grids based on visible items, optional hero panels and headings, traditional quotation and cross, and style-specific symbol cards. Keep homepage card photographs 200px high. Align classic navigation, preserve mobile contrast and support missing plugin data without empty columns.

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

Theme PHP, CSS, JavaScript, and SVG assets: © 2026 Atapin Vladimir, GPLv2 or later, included in this package.
Theme screenshot and style previews, captured from the theme demonstration: © 2026 Atapin Vladimir, GPLv2 or later, included in this package.
Plugin settings screenshots in assets/plugins, captured from the standalone GPLv2-or-later Georg-Kloster Parish plugins on a disposable WordPress installation with empty settings: © 2026 Atapin Vladimir, GPLv2 or later. WordPress admin interface: WordPress contributors, GPLv2 or later, https://wordpress.org/about/license/. Screenshots are documentation only and are never inserted into public pages.
WordPress core blocks and APIs are provided by WordPress, GPLv2 or later, https://wordpress.org/about/license/.
