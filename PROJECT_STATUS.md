# Project status: Georg-Kloster Prihod

## Current state

The repository contains the Georg-Kloster Prihod theme (first stable release 1.0.0), Parish Core (0.2.5), and Connect Hub (0.3.3). The theme has five visual styles, native Customizer controls, a standard WordPress widget area, English gettext source strings, and Russian, German, Polish, and Ukrainian catalogs. Author: Atapin Vladimir (`atapin`); site: https://georg-kloster.ru/; email: atapin@gmail.com.

The theme's Optional Plugins panel shows compact cards and screenshots for Parish, Connect Hub, Calendar Workshop, Orthodox Donations, and Slavonic Translator, including installed status and available WordPress.org links. Language switching uses WPML or Polylang; theme-managed language URLs were removed. The shared homepage map iframe is explicitly full width.

## WordPress.org release status

Version 1.0.0 is submitted in WordPress.org Themes Trac ticket [#296403](https://themes.trac.wordpress.org/ticket/296403); the released SVN directory is the current source of truth. The ticket is open (`new`) and awaits manual review. Its Theme Check results show one warning: no theme copyright notice was found for the GPL license. The uploaded SVN source has the sidebar registered on `widgets_init` and includes custom logo, header, and background support. The local `build/georg-kloster-prihod-1.0.0/georg-kloster-prihod.zip` is older and does not match SVN; do not use it as the submitted release package.

## Checks completed

PHP syntax lint passed for all 35 PHP files in the local ZIP. The local extracted release folder also passed PHP lint. The screenshot in that local ZIP is 288,952 bytes. WordPress.org's Theme Check on the submitted SVN version reports only the copyright warning; the ticket's manual review is still pending. Earlier repository checks passed: 70 isolated assertions and translation consistency for 2,851 UI messages. No production deployment was performed.

## Next steps

Wait for the reviewer on ticket #296403. If requested, add an explicit copyright notice and submit the corrected package. Keep the SVN submission as authoritative; refresh the local build ZIP before using it again.

Last related code commit: `e10e018` (Bust theme style cache after updates), branch `main`, upstream `origin/main`.
