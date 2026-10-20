# Project status: Georg-Kloster Prihod

## Current state

The repository contains the Georg-Kloster Prihod theme (1.0.0), Parish Core (0.2.5), and Connect Hub (0.3.3). The theme has five visual styles, native Customizer controls, a standard WordPress widget area, English gettext source strings, and Russian, German, Polish, and Ukrainian catalogs. Author: Atapin Vladimir (`atapin`); site: https://georg-kloster.ru/; email: atapin@gmail.com.

The theme has compact optional-plugin cards for Parish, Connect Hub, Calendar Workshop, Orthodox Donations, and Slavonic Translator. Language switching is delegated to WPML or Polylang. The shared homepage map uses a full-width iframe.

The active theme on the test site `new.georg-kloster.ru` was updated from the current Git package (version 1.0.0). News and category archives now use responsive card grids; the service strip shows the place logo, date calendar, and time clock without a duplicate title icon; the classic hero motto stays in the text column. The live page retained its existing theme settings.

## WordPress.org release status

Version 1.0.0 is submitted in WordPress.org Themes Trac ticket [#296403](https://themes.trac.wordpress.org/ticket/296403); the SVN submission remains separate from the newer Git changes deployed to the test site. The ticket is awaiting manual review. Do not treat the test-site package as a new SVN submission.

## Checks completed

`php tests/lint.php` passed; `php tests/run.php` passed 70 isolated assertions. Browser checks on the test site confirmed the three-column desktop archive, one-column mobile archive without horizontal overflow, category archive cards, the `Новости` posts-page title, all five service-strip layouts, the logo/calendar/clock icon placement, and the map at full width after its lazy load.

## Next steps

Continue the MailPoet setup only after the theme work is accepted. WordPress.org manual review remains pending.

Last related code commit: this task's theme archive and homepage layout fix (current commit on `main`).
