# Project status: Georg-Kloster Prihod

## Current state

The repository contains the Georg-Kloster Prihod theme (1.0.0), Parish Core (0.2.5), and Connect Hub (0.3.3). The theme has five visual styles, native Customizer controls, a standard WordPress widget area, English gettext source strings, and Russian, German, Polish, and Ukrainian catalogs. Author: Atapin Vladimir (`atapin`); site: https://georg-kloster.ru/; email: atapin@gmail.com.

The theme has compact optional-plugin cards for Parish, Connect Hub, Calendar Workshop, Orthodox Donations, and Slavonic Translator. Language switching is delegated to WPML or Polylang. The shared homepage map uses a full-width iframe.

The active theme on the test site `new.georg-kloster.ru` was updated from the current Git package (version 1.0.0). News and category archives use responsive card grids. The homepage service strip has a place logo, date calendar, and time clock with separate columns for date, time, and service title; the time column is widened and its stylesheet uses a file modification cache key so updates reach visitors. Narrow screens stack the strip without clipping. The classic hero motto stays in the text column. The live page retained its existing theme settings.

The newsletter widget area is present on the homepage and news/archive views. It uses a clean MailPoet form named “Новости монастыря” (form 2), linked to the “Рассылка новостей” list, with a confirmation reminder and theme-styled controls. The old stock demo form remains in MailPoet but is no longer displayed publicly. MailPoet sending is still inactive (“Other” method awaits activation); WP Mail SMTP has an SMTP transport configured, but delivery has not been tested. No campaign or test email has been sent.

## WordPress.org release status

Version 1.0.0 is submitted in WordPress.org Themes Trac ticket [#296403](https://themes.trac.wordpress.org/ticket/296403); the SVN submission remains separate from the newer Git changes deployed to the test site. The ticket is awaiting manual review. Do not treat the test-site package as a new SVN submission.

## Checks completed

`php tests/lint.php` passed; `php tests/run.php` passed 70 isolated assertions. `python tests/translation-check.py` checked 2,891 translated UI messages and placeholders. Browser checks on the test site confirmed the three-column desktop archive, one-column mobile archive without horizontal overflow, category archive cards, the `Новости` posts-page title, all five service-strip layouts, the logo/calendar/clock icon placement, and the map at full width after its lazy load. The refreshed desktop service strip measured separate, non-overlapping date, time, and service-title columns. The public `/news/` page shows the clean form with no demo background image; its field and button colors match the theme.

## Next steps

Activate and verify MailPoet sending with the configured WP Mail SMTP transport before announcing the newsletter publicly; a test email was intentionally not sent. WordPress.org manual review remains pending.

Last related code commit: service-strip width and cache-busting fix (this commit on `main`); the preceding newsletter widget update is `1f66eef`.
