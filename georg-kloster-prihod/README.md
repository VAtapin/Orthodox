# Georg-Kloster Prihod

Independent theme for WordPress 6.5+ and PHP 8.0+. Five styles are available:
Classic gold, Light hospitality, Editorial, Traditional and Modern blue.
Companion plugins are optional. The theme displays standard WordPress pages
and posts without creating or rewriting them.

Source code: https://github.com/VAtapin/Orthodox/tree/main/georg-kloster-prihod

## Settings

Open **Appearance → Customize → Parish theme settings** for live-preview controls covering styles, layout, fonts, branding,
hero image, navigation and footer settings. Site details include the name,
description, community type, address, phone and email. Filled theme fields take
priority; optional Core data may supply empty fields. Without Core, WordPress
provides the site name and description.

Homepage content is edited in **Pages**, using standard WordPress blocks: headings,
paragraphs, images, buttons, columns, galleries and Query Loop. Select a static
homepage under **Settings → Reading**. The Homepage pattern provides a starting
point; insert, move or remove sections in the page editor. All five visual style
palettes remain available. The previous automatic hero/card composition is no
longer rendered; block layout is controlled by the page author.

Existing sites must migrate their option-based content before installing 1.1.0.
See `docs/THEME_BLOCK_MIGRATION.md` in the repository. The separate migration tool
is excluded from the theme ZIP and never runs automatically on activation.

Menu items, nesting and images are managed under **Appearance → Menus**.
The image menu is optional; the standard link row remains available.

The right column on pages and posts is a standard WordPress widget area. Edit it
under **Appearance → Widgets** with core blocks such as paragraphs, search,
latest posts, categories, archives, calendar, or custom HTML. When the area is
empty, the theme keeps its built-in calendar, service schedule, contact link,
and parish quotation.

A separate **Newsletter** widget area appears above the footer on the homepage
and news archives. Add the MailPoet Form block there and select a form in
**Appearance → Widgets**. The area stays hidden until it contains a widget; the
theme does not send campaigns or require MailPoet.

## Optional plugins

The settings sidebar includes **Optional plugins**. It shows compact cards for
Parish, Connect Hub, Calendar Workshop, Orthodox Donations and Slavonic
Translator, with descriptions, screenshots and installation status. Cards link
to settings when available; public plugins also link to the standard WordPress
installer search and their WordPress.org details. The theme makes no
plugin-directory API requests and does not install plugins. It is fully usable
without these integrations. WPML or Polylang supplies language switching; the
theme does not maintain a second set of language URLs.

## Translation and packaging

Source interface strings are English and use WordPress gettext. Russian,
German, Polish and Ukrainian translations are in `languages/*.po` and `*.mo`.
The POT includes native `theme.json` contexts for editor labels and style
variations. Rebuild catalogs from the repository root:

```sh
python scripts/compile-translations.py --only theme
python tests/translation-check.py
python scripts/package.py --only theme
```

The theme screenshot is the Classic style preview from
`assets/schemes/classic.jpg`, converted to PNG. Preview photographs are never
inserted into public page content. Resource credits are in `readme.txt`.

## Block layouts

The page stores the same blocks for all five styles. Change the theme palette
without rebuilding page content. Use the editor's standard Columns, Group and
Query Loop settings for layout and responsive composition. Schedule content
belongs to the optional Parish plugin's existing blocks; no theme block is
registered. Gallery blocks retain WordPress's native image viewer.
