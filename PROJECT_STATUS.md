# Project status: Georg-Kloster Prihod

## Current state

The repository contains the Georg-Kloster Prihod theme (1.0.0), Parish Core (0.2.11), and Connect Hub (0.4.5). The theme has five visual styles, native Customizer controls, standard WordPress widget and primary/footer menu locations, English gettext source strings, and Russian, German, Polish, and Ukrainian catalogs. Footer links are managed by assigning a standard menu to the Footer menu location. Author: Atapin Vladimir (`atapin`); site: https://georg-kloster.ru/; email: atapin@gmail.com.

The footer no longer generates a fixed list of site links. It renders the standard WordPress menu assigned to the Footer menu location; per-language menus can be assigned through the normal WordPress/WPML menu workflow. The theme package has not been installed on production.

The merged Parish Schedule reads vestment recommendations from Bible Desktop `day.vestment.color`; API `violet` maps to the existing `purple` palette key. Existing manual service colors and date overrides remain available. Vestment stripes use a silver frosted pattern; mobile upcoming-service cards and weekly/list event content have extra spacing after the stripe. Parish Core 0.2.11 also fits compact titles to their sidebar card width and separates the overview heading from its calendar badge. Production has not been updated.

The theme has compact optional-plugin cards for Parish, Connect Hub, Calendar Workshop, Orthodox Donations, and Slavonic Translator. Language switching is delegated to WPML or Polylang. The shared homepage map uses a full-width iframe.

The active theme on the test site `new.georg-kloster.ru` was updated from the current Git package (version 1.0.0). News and category archives use responsive card grids. The homepage service strip has a place logo, date calendar, and time clock with separate columns for date, time, and service title; the time column is widened and its stylesheet uses a file modification cache key so updates reach visitors. Narrow screens stack the strip without clipping. The classic hero motto stays in the text column. The live page retained its existing theme settings.

The native `/news/` Posts page and “News” category archive share the catalog’s category chips and search controls. Filter terms are limited to categories used by published posts, and selected search/category filters are applied to the correct main query. Translated page/category URLs are resolved through WPML or Polylang.

The newsletter widget area is present on the homepage and news/archive views. It uses MailPoet form 2 (“Новости монастыря”), linked to “Рассылка новостей”; the theme now constrains the form to a 600px maximum width and uses tighter spacing and shorter controls. The old stock demo form remains in MailPoet but is no longer displayed publicly. The confirmation email uses Russian text and the theme palette, and the confirmation page layout is compact with its sidebar hidden. Double opt-in and MailPoet’s built-in CAPTCHA are enabled. MailPoet sending is still inactive (“Other” method awaits activation); WP Mail SMTP has an SMTP transport configured, but delivery has not been tested. No campaign or test email has been sent.

Connect Hub 0.4.5 keeps form notifications separate from public Telegram broadcasts. Forms send private inquiries only to their configured Chat IDs; publications go to bot subscribers. Bot language choices follow active WPML languages. Calendar messages use Telegram HTML: the daily digest and /today include the day icon with its caption, today’s services, remaining services this week, commemorations and a full-schedule link; /week groups services by localized date; schedule-change announcements are formatted too. Forms and bot commands use separate WordPress-style list and edit screens. Bot replies use the WordPress visual editor with Telegram-supported formatting and up to ten photos or MP4 videos per language. Built-in and custom commands can return text, schedules, pages or news. Queued campaigns retain per-recipient status, retry, cancellation and unsubscribe handling. Standard interface text has RU/DE PO/MO catalogs. Production has not been updated.

## Connect Hub delivery

Connect Hub 0.4.5 formats calendar messages as Telegram HTML, groups weekly services by day, includes the calendar icon in the daily digest and /today, and formats schedule-change notifications. Existing WPML language selection, configured replies and media remain supported. Installable package: `build/georg-kloster-connect-hub-0.4.5.zip`. It has not been installed on production.

## WordPress.org release status

Version 1.0.0 is submitted in WordPress.org Themes Trac ticket [#296403](https://themes.trac.wordpress.org/ticket/296403); the SVN submission remains separate from the newer Git changes deployed to the test site. The ticket is awaiting manual review. Do not treat the test-site package as a new SVN submission.

## Checks completed

`php tests/lint.php` passed; `php tests/run.php` passed 78 isolated assertions; translation compilation found 489 PHP/JS messages and 2 editor messages; Connect Hub catalogs passed 978 translated-message checks; `node --check` passed. The 0.4.5 ZIP built and archive validation confirmed 47 entries, version 0.4.5, the HTML/photo path and translations. The WordPress Playground integration could not start because DNS lookup for `registry.npmjs.org` returned `ENOTFOUND`; no WordPress runtime test or live Telegram send was performed.

Vestment API integration: `php tests/run.php` passes 78 isolated assertions and `php tests/lint.php` passes. The Parish merge Blueprint could not run because `registry.npmjs.org` DNS was unavailable. A direct live Bible Desktop API check from this environment also failed at DNS resolution, so January 2027 production responses were not confirmed. All vestment colors share one patterned stripe style; non-white colors have brighter, more saturated fills and borders.

Footer menu change: `php tests/run.php`, `php tests/lint.php`, and `python scripts/compile-translations.py --only theme` passed. The WordPress Playground integration test is unavailable in this environment. Composer's wrapper could not start because the local Composer runtime lacks OpenSSL; the PHP checks were run directly. Production has not been updated.

## Next steps

Upload `build/georg-kloster-connect-hub-0.4.5.zip` through WordPress Plugins > Add Plugin > Upload Plugin. Configure the Telegram token, enable subscriber broadcasts, connect the webhook, and share the bot link so readers can press Start and select an active WPML language. Manage commands and replies under Connect Hub → Bot commands; set each form's private Chat ID separately for incoming inquiries. Verify real Telegram delivery after installation. Production has not been updated.

Last related code commit: Format Telegram calendar messages in HTML.
