# Project status: Georg-Kloster Prihod

## Current state

The repository contains the Georg-Kloster Prihod theme (1.0.0), Parish Core (0.2.11), and Connect Hub (0.4.0). The theme has five visual styles, native Customizer controls, a standard WordPress widget area, English gettext source strings, and Russian, German, Polish, and Ukrainian catalogs. Author: Atapin Vladimir (`atapin`); site: https://georg-kloster.ru/; email: atapin@gmail.com.

The merged Parish Schedule reads vestment recommendations from Bible Desktop `day.vestment.color`; API `violet` maps to the existing `purple` palette key. Existing manual service colors and date overrides remain available. Vestment stripes use a silver frosted pattern; mobile upcoming-service cards and weekly/list event content have extra spacing after the stripe. Parish Core 0.2.11 also fits compact titles to their sidebar card width and separates the overview heading from its calendar badge. Production has not been updated.

The theme has compact optional-plugin cards for Parish, Connect Hub, Calendar Workshop, Orthodox Donations, and Slavonic Translator. Language switching is delegated to WPML or Polylang. The shared homepage map uses a full-width iframe.

The active theme on the test site `new.georg-kloster.ru` was updated from the current Git package (version 1.0.0). News and category archives use responsive card grids. The homepage service strip has a place logo, date calendar, and time clock with separate columns for date, time, and service title; the time column is widened and its stylesheet uses a file modification cache key so updates reach visitors. Narrow screens stack the strip without clipping. The classic hero motto stays in the text column. The live page retained its existing theme settings.

The native `/news/` Posts page and “News” category archive share the catalog’s category chips and search controls. Filter terms are limited to categories used by published posts, and selected search/category filters are applied to the correct main query. Translated page/category URLs are resolved through WPML or Polylang.

The newsletter widget area is present on the homepage and news/archive views. It uses MailPoet form 2 (“Новости монастыря”), linked to “Рассылка новостей”; the theme now constrains the form to a 600px maximum width and uses tighter spacing and shorter controls. The old stock demo form remains in MailPoet but is no longer displayed publicly. The confirmation email uses Russian text and the theme palette, and the confirmation page layout is compact with its sidebar hidden. Double opt-in and MailPoet’s built-in CAPTCHA are enabled. MailPoet sending is still inactive (“Other” method awaits activation); WP Mail SMTP has an SMTP transport configured, but delivery has not been tested. No campaign or test email has been sent.

Connect Hub 0.4.0 separates form notifications from public Telegram broadcasts: each form sends private inquiries only to its own configured Chat ID; publication messages go to people who opted in by pressing Start in the bot. Subscribers can choose Russian or German, use `/today` and `/week`, and receive an optional daily schedule/calendar digest. Manual and automatic post announcements, scheduled reminders, and published schedule-change announcements use a queued campaign system with per-recipient status, retry, cancel, and unsubscribe handling. The shared webhook handles subscriber commands and existing form replies. The admin page lists subscribers and recent campaign delivery. Other Connect Hub features include multi-channel publishing, setup guides, and optional Google reCAPTCHA v3. Production has not been updated.

## Connect Hub delivery

Connect Hub 0.4.0 preserves per-form private Telegram routing and adds the opt-in bot audience described above. The old global Chat ID is no longer a fallback for form inquiries. Data tables are created on the first WordPress request after activation/upgrade; deactivation keeps subscriber and campaign data. Russian and German bot copy and UI catalogs ship with the plugin. Installable package: `build/georg-kloster-connect-hub-0.4.0.zip` (to be rebuilt and validated for this task). It has not been installed on production.

## WordPress.org release status

Version 1.0.0 is submitted in WordPress.org Themes Trac ticket [#296403](https://themes.trac.wordpress.org/ticket/296403); the SVN submission remains separate from the newer Git changes deployed to the test site. The ticket is awaiting manual review. Do not treat the test-site package as a new SVN submission.

## Checks completed

`php tests/lint.php` passed; `php tests/run.php` passed 78 isolated assertions; `python tests/plugin-i18n-check.py --only georg-kloster-connect-hub` passed 780 RU/DE translations. `python scripts/package.py --only connect-hub` built the 0.4.0 ZIP; archive validation confirmed 47 entries, the version header, the bot implementation, documentation and valid JSON resources. The WordPress Playground integration could not run because `registry.npmjs.org` DNS resolution returned `ENOTFOUND`; no WordPress runtime integration test or live Telegram send was performed.

Vestment API integration: `php tests/run.php` passes 78 isolated assertions and `php tests/lint.php` passes. The Parish merge Blueprint could not run because `registry.npmjs.org` DNS was unavailable. A direct live Bible Desktop API check from this environment also failed at DNS resolution, so January 2027 production responses were not confirmed. All vestment colors share one patterned stripe style; non-white colors have brighter, more saturated fills and borders.

## Next steps

Upload `build/georg-kloster-connect-hub-0.4.0.zip` through WordPress Plugins > Add Plugin > Upload Plugin. Configure the Telegram token, enable subscriber broadcasts, connect the webhook, and share the bot link so readers can press Start and select RU/DE. Set each form's private Chat ID separately for incoming inquiries. Turn on daily digests and schedule notifications only if desired. Verify real Telegram delivery after installation. Production has not been updated.

Last related code commit: Connect Hub 0.4.0 Telegram subscriber broadcasts (this task's commit).
