# Project status: Georg-Kloster Prihod

## Current state

The repository contains the Georg-Kloster Prihod theme (development version 0.4.46), Parish Core (0.2.5), and Connect Hub (0.3.3). The theme has five styles, a standard WordPress widget area, native Customizer controls, English gettext source strings, and Russian, German, Polish, and Ukrainian catalogs. The author is Atapin Vladimir / `atapin`; site https://georg-kloster.ru/; contact atapin@gmail.com.

The theme settings no longer query the WordPress.org plugin API. The Optional Plugins panel lists only installed integrations, with descriptions and links to their settings, and includes a direct link to the WordPress plugin installer search for Georg-Kloster. The WordPress.org readme and developer README link to the GitHub theme source.

## WordPress.org release status

Not yet ready for submission. Theme Check and official Theme Unit Test Data testing remain outstanding. Runtime theme tests require a WordPress Playground installation at `/wordpress/wp-load.php`, which is unavailable in this workspace. Keep the Git commit history. When the theme is ready for its first stable public release, use version 1.0.0 and replace the development changelog with the initial release entry.

## Checks completed

Theme source/catalog/preview check passed: 257 messages in four locales. Translation consistency check passed for 2,843 UI messages. Repository PHP lint passed, as did 70 isolated unit assertions. The 0.4.46 package builds with 76 entries, includes the required classic-theme files, and has a 1200 x 900 screenshot. The modified integrations-panel Playground test and other WordPress runtime tests could not run because the workspace has no WordPress installation at `/wordpress/wp-load.php`. WordPress.org submission has not occurred.

## Next steps

Run Theme Check and theme unit data on a clean WordPress install, resolve any findings, then prepare and validate the first stable release package.

Last related commit: check `git log -1` on branch `main` (upstream `origin/main`).
