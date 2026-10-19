# Georg-Kloster Prihod

Independent theme for WordPress 6.5+ and PHP 8.0+. Five styles are available:
Classic gold, Light hospitality, Editorial, Traditional and Modern blue.
Companion plugins are optional. The theme displays standard WordPress pages
and posts without creating or rewriting them.

## Settings

Open **Appearance → Parish Appearance** for styles, layout, fonts, branding,
hero image, navigation and footer settings. Site details include the name,
description, community type, address, phone and email. Filled theme fields take
priority; optional Core data may supply empty fields. Without Core, WordPress
provides the site name and description.

Homepage settings control section visibility and order, headings, eyebrows,
descriptions, button labels, cards and linked pages. Featured images from those
pages provide card images. News controls include category, count, columns,
excerpt length, date and image visibility. A zero numeric setting uses the
selected style's default. Upcoming services require a schedule plugin; a link
to an ordinary schedule page works without it.

The hero and section layouts remain theme templates. Editorial text is stored
in WordPress options; blank fields do not insert preset homepage copy. Card
titles may use the linked WordPress page title. Custom labels are registered
with WPML String Translation.

Menu items, nesting and images are managed under **Appearance → Menus**.
The image menu is optional; the standard link row remains available.

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
