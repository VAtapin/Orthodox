# Project status: Georg-Kloster Prihod

## Current state

The repository contains the Georg-Kloster Prihod theme (development version 0.4.47), Parish Core (0.2.5), and Connect Hub (0.3.3). The theme has five visual styles, native Customizer controls, a standard WordPress widget area, English gettext source strings, and Russian, German, Polish, and Ukrainian catalogs. Author: Atapin Vladimir (`atapin`); site: https://georg-kloster.ru/; email: atapin@gmail.com.

The theme's Optional Plugins panel shows compact cards and screenshots for Parish, Connect Hub, Calendar Workshop, Orthodox Donations, and Slavonic Translator, including installed status and available WordPress.org links. Language switching uses WPML or Polylang; theme-managed language URLs were removed. The shared homepage map iframe is explicitly full width.

## WordPress.org release status

Not yet ready for submission. Theme Check and official Theme Unit Test Data testing remain outstanding. WordPress runtime tests require `/wordpress/wp-load.php`, which is unavailable in this workspace. Keep Git commit history. For a first stable public release, use version 1.0.0 and replace development changelog entries with the initial release entry.

## Checks completed

PHP lint passed. Isolated unit tests passed with 70 assertions. Theme catalogs compiled and translation consistency passed for 2,851 UI messages. The 0.4.47 theme ZIP built successfully with the new screenshots. WordPress runtime theme tests could not run because `/wordpress/wp-load.php` is unavailable. Production has not been updated.

## Next steps

Run the WordPress runtime release checks, Theme Check, and Theme Unit Test Data on a clean WordPress installation. Verify the map width on the live site after the theme package is installed.

Last related commit before this change: `f5422a1` (Link theme to plugin installer search), branch `main`, upstream `origin/main`.
