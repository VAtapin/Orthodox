# Project status: Georg-Kloster Prihod

## Current state

The repository contains the Georg-Kloster Prihod theme (1.0.0), Parish Core (0.2.11), and Connect Hub (current package version 0.3.11). The theme has five visual styles, native Customizer controls, a standard WordPress widget area, English gettext source strings, and Russian, German, Polish, and Ukrainian catalogs. Author: Atapin Vladimir (`atapin`); site: https://georg-kloster.ru/; email: atapin@gmail.com.

The merged Parish Schedule reads vestment recommendations from Bible Desktop `day.vestment.color`; API `violet` maps to the existing `purple` palette key. Existing manual service colors and date overrides remain available. Vestment stripes use a silver frosted pattern; mobile upcoming-service cards and weekly/list event content have extra spacing after the stripe. Parish Core 0.2.11 also fits compact titles to their sidebar card width and separates the overview heading from its calendar badge. Production has not been updated.

The theme has compact optional-plugin cards for Parish, Connect Hub, Calendar Workshop, Orthodox Donations, and Slavonic Translator. Language switching is delegated to WPML or Polylang. The shared homepage map uses a full-width iframe.

The active theme on the test site `new.georg-kloster.ru` was updated from the current Git package (version 1.0.0). News and category archives use responsive card grids. The homepage service strip has a place logo, date calendar, and time clock with separate columns for date, time, and service title; the time column is widened and its stylesheet uses a file modification cache key so updates reach visitors. Narrow screens stack the strip without clipping. The classic hero motto stays in the text column. The live page retained its existing theme settings.

The native `/news/` Posts page and “News” category archive share the catalog’s category chips and search controls. Filter terms are limited to categories used by published posts, and selected search/category filters are applied to the correct main query. Translated page/category URLs are resolved through WPML or Polylang.

The newsletter widget area is present on the homepage and news/archive views. It uses MailPoet form 2 (“Новости монастыря”), linked to “Рассылка новостей”; the theme now constrains the form to a 600px maximum width and uses tighter spacing and shorter controls. The old stock demo form remains in MailPoet but is no longer displayed publicly. The confirmation email uses Russian text and the theme palette, and the confirmation page layout is compact with its sidebar hidden. Double opt-in and MailPoet’s built-in CAPTCHA are enabled. MailPoet sending is still inactive (“Other” method awaits activation); WP Mail SMTP has an SMTP transport configured, but delivery has not been tested. No campaign or test email has been sent.

Connect Hub 0.3.6 allows the explicit Telegram “Connect / check incoming replies” action to set this site's webhook even when another application currently owns it. The settings warn that the previous application will stop receiving messages; pending Telegram updates are preserved. Telegram request notifications and replies contain no admin links, reply instructions, or separate service acknowledgements. A saved contact request remains successful if a post-create notification callback fails, and failed form submissions restore values in the same browser tab. Version 0.3.6 was installed successfully on `new.georg-kloster.ru`. Version 0.3.7 added a private Message log for outgoing request email and request/schedule messages through Telegram and WhatsApp; version 0.3.11 removes WhatsApp from Connect Hub settings, publication rules, queues and outgoing logging. Existing WhatsApp settings/history remain stored but unused. Version 0.3.8 adds optional Google reCAPTCHA v3 to public forms. Version 0.3.9 consolidates General and reCAPTCHA settings into one form and one save button, redirects back to Connect Hub settings, and reports key status. Version 0.3.10 fixes WordPress option sanitization discarding a newly submitted site key. Re-enter both keys after installing 0.3.10 if the status is incomplete.

## Connect Hub WordPress.org release candidate

Connect Hub 0.3.11 retains GPL-2.0-or-later metadata, uses one General settings save flow, reports reCAPTCHA key status, preserves newly submitted site keys through WordPress settings sanitization, and exposes five channels (Telegram, Facebook, Instagram, VK and OK); WhatsApp is removed from this plugin. The ZIP has not yet been installed on the live site. The integration uses the classic site/secret pair through SiteVerify and checks score, action and hostname server-side. Google recommends migrating to CreateAssessment; the classic keys remain usable for this implementation. The package has not yet been installed on the live site. The official Plugin Check (PCP) full category scan of 0.3.6 reported 0 errors and 96 warnings; warnings still need human review.

## WordPress.org release status

Version 1.0.0 is submitted in WordPress.org Themes Trac ticket [#296403](https://themes.trac.wordpress.org/ticket/296403); the SVN submission remains separate from the newer Git changes deployed to the test site. The ticket is awaiting manual review. Do not treat the test-site package as a new SVN submission.

## Checks completed

`php tests/lint.php` passed; `php tests/run.php` passed 78 isolated assertions. Connect Hub catalogs compile and `python tests/plugin-i18n-check.py --only georg-kloster-connect-hub` passed (682 translated messages after the channel removal). WordPress integration scripts require `/wordpress/wp-load.php`, which is unavailable in this environment. The WordPress Playground integration run could not start because npm registry DNS/network access failed, so settings-page integration assertions still need a WordPress runtime check.

Vestment API integration: `php tests/run.php` passes 78 isolated assertions and `php tests/lint.php` passes. The Parish merge Blueprint could not run because `registry.npmjs.org` DNS was unavailable. A direct live Bible Desktop API check from this environment also failed at DNS resolution, so January 2027 production responses were not confirmed. All vestment colors share one patterned stripe style; non-white colors have brighter, more saturated fills and borders.

## Next steps

Upload `build/georg-kloster-connect-hub-0.3.11.zip` through WordPress Plugins > Add Plugin > Upload Plugin on `georg-kloster.ru`; then confirm the five remaining channel tabs and test a contact form. Enter both reCAPTCHA keys under Connect Hub > Settings > General, confirm the configured status, and submit a test question. On the intended site, use “Connect / check incoming replies” to move the Telegram bot webhook there; the old application will stop receiving updates. Activate and verify MailPoet sending with the configured WP Mail SMTP transport before announcing the newsletter publicly; a test email was intentionally not sent. WordPress.org manual review remains pending.

Last related code commit before this task: `5129873` — “Update reCAPTCHA fix status” on `main`.
