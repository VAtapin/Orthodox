# Project status: Georg-Kloster Prihod

## Current state

The repository contains the Georg-Kloster Prihod theme (first stable release 1.0.0), Parish Core (0.2.5), and Connect Hub (0.3.3). The theme has five visual styles, native Customizer controls, a standard WordPress widget area, English gettext source strings, and Russian, German, Polish, and Ukrainian catalogs. Author: Atapin Vladimir (`atapin`); site: https://georg-kloster.ru/; email: atapin@gmail.com.

The theme's Optional Plugins panel shows compact cards and screenshots for Parish, Connect Hub, Calendar Workshop, Orthodox Donations, and Slavonic Translator, including installed status and available WordPress.org links. Language switching uses WPML or Polylang; theme-managed language URLs were removed. The shared homepage map iframe is explicitly full width.

## WordPress.org release status

Prepared for first public submission as version 1.0.0. Development entries were removed from the theme's public changelog; Git history remains intact. The owner has confirmed visual review on the WordPress site. The first directory review's metadata findings are corrected. Automatic Theme Check found a large screenshot (owner is preparing its replacement), required sidebar registration timing, and recommendations; the sidebar and custom logo support are corrected. Theme Check and directory submission remain external steps.

## Checks completed

PHP lint passed. Isolated unit tests passed with 70 assertions. Theme catalogs compiled and translation consistency passed for 2,851 UI messages. Theme package ZIP built and audited; owner confirmed the site review passed. Production has not been updated. The 1.0.0 ZIP must be rebuilt after the owner replaces `screenshot.png`.

## Next steps

Owner replaces/compresses `screenshot.png`; rebuild and resubmit the 1.0.0 ZIP, then address any remaining Theme Check or manual review feedback.

Last related commit before this change: `f0c61f2` (Fix WordPress.org theme metadata), branch `main`, upstream `origin/main`.
