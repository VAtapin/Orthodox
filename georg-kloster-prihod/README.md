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

The same complete controls remain available under **Appearance → Parish Appearance**. Homepage settings control section visibility and order, headings, eyebrows,
descriptions, button labels, cards and linked pages. Featured images from those
pages provide card images. News controls include category, count, columns,
excerpt length, date and image visibility. A zero numeric setting uses the
selected style's default. Upcoming services use the optional Parish plugin's bundled Schedule module; a link
to an ordinary schedule page works without it.

The hero and section layouts remain theme templates. Editorial text is stored
in WordPress options; blank fields do not insert preset homepage copy. Card
titles may use the linked WordPress page title. Custom labels are registered
with WPML String Translation.

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

When Parish Core provides its optional demo importer, the Homepage settings
include the plugin's import link. Core owns the preview, consent form, content
pack and import; the theme adds no demo data or import implementation. Without
Core, this extra entry is absent and the theme continues to work independently.

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

## Style layouts

The five styles share the same saved content and homepage controls. Their
card grids use the number of visible cards (up to four in Classic, five in
the other styles). Hiding the schedule card or disabling a section does not
reserve empty columns. Upcoming services occupy a hero sidebar only when
available and placed directly after the hero; otherwise sections keep their
chosen order. Optional service headings and links do not reserve empty slots.

Traditional uses the saved quotation and motto with the theme's cross icon.
Its request/support tiles and the Editorial support tile use large symbols.
Turning card images off retains compact icon cards. Displayed homepage card
photographs are 200px high and remain inside their columns. Empty text fields
stay empty; no reference-site content is inserted.

Authored homepage gallery groups use full-width native WordPress galleries.
Their photographs have 200px frames with five, three or two columns according
to the viewport; the native image viewer remains available. Gallery blocks
are not placed in the narrow navigation column of the blue style. The theme
does not rewrite their text, image selection or page content.
