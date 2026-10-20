# Project status: Georg-Kloster Prihod

## Current state

The repository contains the Georg-Kloster Prihod theme (first stable release 1.0.0), Parish Core (0.2.5), and Connect Hub (0.3.3). The theme has five visual styles, native Customizer controls, a standard WordPress widget area, English gettext source strings, and Russian, German, Polish, and Ukrainian catalogs. Author: Atapin Vladimir (`atapin`); site: https://georg-kloster.ru/; email: atapin@gmail.com.

The theme's Optional Plugins panel shows compact cards and screenshots for Parish, Connect Hub, Calendar Workshop, Orthodox Donations, and Slavonic Translator, including installed status and available WordPress.org links. Language switching uses WPML or Polylang; theme-managed language URLs were removed. The shared homepage map iframe is explicitly full width.

## WordPress.org release status

Prepared for first public submission as version 1.0.0. Development entries were removed from the theme's public changelog; Git history remains intact. The owner has confirmed visual review on the WordPress site. Directory metadata findings are corrected. Theme Check follow-up includes sidebar registration on `widgets_init`, native custom logo/header/background support, block styles and a block pattern. The owner will prepare the screenshot; the workspace copy is currently 1.2 MB and must be replaced before resubmission. Archive stylesheet URLs now change when their CSS file changes to prevent stale browser caches. The iframe notice is informational because the map loads only after an administrator configures it. Re-run Theme Check and submit the refreshed ZIP for review.

## Checks completed

PHP lint passed. Isolated unit tests passed with 70 assertions. Theme catalogs compiled and translation consistency passed for 2,851 UI messages. Owner confirmed the site review passed. The package generator ran, but its archive still contains the 1.2 MB screenshot and is not ready to resubmit. Production has not been updated.

## Next steps

Replace `screenshot.png` with the owner's optimized image; rebuild and audit the 1.0.0 ZIP, then rerun Theme Check and address any remaining review feedback.

Last related commit before this change: `f0c61f2` (Fix WordPress.org theme metadata), branch `main`, upstream `origin/main`.
