# Project status: Georg-Kloster Prihod

## Current state

The repository contains the Georg-Kloster Prihod theme (1.0.0), Parish Core (0.2.7), and Connect Hub (0.3.7). The theme has five visual styles, native Customizer controls, a standard WordPress widget area, English gettext source strings, and Russian, German, Polish, and Ukrainian catalogs. Author: Atapin Vladimir (`atapin`); site: https://georg-kloster.ru/; email: atapin@gmail.com.

The merged Parish Schedule reads vestment recommendations from Bible Desktop `day.vestment.color`; API `violet` maps to the existing `purple` palette key. Existing manual service colors and date overrides remain available. White vestments use a wider silver frosted-pattern stripe so they remain visible against white schedule cards.

The theme has compact optional-plugin cards for Parish, Connect Hub, Calendar Workshop, Orthodox Donations, and Slavonic Translator. Language switching is delegated to WPML or Polylang. The shared homepage map uses a full-width iframe.

The active theme on the test site `new.georg-kloster.ru` was updated from the current Git package (version 1.0.0). News and category archives use responsive card grids. The homepage service strip has a place logo, date calendar, and time clock with separate columns for date, time, and service title; the time column is widened and its stylesheet uses a file modification cache key so updates reach visitors. Narrow screens stack the strip without clipping. The classic hero motto stays in the text column. The live page retained its existing theme settings.

The native `/news/` Posts page and “News” category archive share the catalog’s category chips and search controls. Filter terms are limited to categories used by published posts, and selected search/category filters are applied to the correct main query. Translated page/category URLs are resolved through WPML or Polylang.

The newsletter widget area is present on the homepage and news/archive views. It uses MailPoet form 2 (“Новости монастыря”), linked to “Рассылка новостей”; the theme now constrains the form to a 600px maximum width and uses tighter spacing and shorter controls. The old stock demo form remains in MailPoet but is no longer displayed publicly. The confirmation email uses Russian text and the theme palette, and the confirmation page layout is compact with its sidebar hidden. Double opt-in and MailPoet’s built-in CAPTCHA are enabled. MailPoet sending is still inactive (“Other” method awaits activation); WP Mail SMTP has an SMTP transport configured, but delivery has not been tested. No campaign or test email has been sent.

Connect Hub 0.3.6 allows the explicit Telegram “Connect / check incoming replies” action to set this site's webhook even when another application currently owns it. The settings warn that the previous application will stop receiving messages; pending Telegram updates are preserved. Telegram request notifications and replies contain no admin links, reply instructions, or separate service acknowledgements. A saved contact request remains successful if a post-create notification callback fails, and failed form submissions restore values in the same browser tab. Version 0.3.6 was installed successfully on `new.georg-kloster.ru`. Version 0.3.7 adds a private Message log for outgoing request email and request/schedule messages through Telegram and WhatsApp: time, recipient/ID, content, attempt, API/mail response and status. It is independent of publication history, available to communication administrators, capped at 200 entries/30 days, and request-linked entries are removed with their request or privacy erasure. The 0.3.7 ZIP is built but has not yet been installed on the test site.

## Connect Hub WordPress.org release candidate

Connect Hub 0.3.7 retains GPL-2.0-or-later metadata and LICENSE.txt, the WordPress.org readme, source repository, support details and external-service/privacy disclosures. The official Plugin Check (PCP) full category scan of 0.3.6 reported 0 errors and 96 warnings; warnings still need human review. Version 0.3.7 is packaged locally and has not yet been installed on the test site, rescanned by PCP or submitted to WordPress.org.

## WordPress.org release status

Version 1.0.0 is submitted in WordPress.org Themes Trac ticket [#296403](https://themes.trac.wordpress.org/ticket/296403); the SVN submission remains separate from the newer Git changes deployed to the test site. The ticket is awaiting manual review. Do not treat the test-site package as a new SVN submission.

## Checks completed

`php tests/lint.php` passed; `php tests/run.php` passed 70 isolated assertions. Connect Hub catalogs compile and `python tests/plugin-i18n-check.py --only georg-kloster-connect-hub` passed (638 translated messages); `python scripts/package.py --only connect-hub` built the 0.3.7 ZIP. The WordPress Playground integration run could not start because npm registry DNS/network access failed, so the new live-worker/journal smoke assertions still need a WordPress runtime check.

Vestment API integration: `php tests/run.php` passes 78 isolated assertions and `php tests/lint.php` passes. The Parish merge Blueprint could not run because `registry.npmjs.org` DNS was unavailable; the Core 0.2.7 ZIP is packaged locally. A direct live Bible Desktop API check from this environment also failed at DNS resolution, so January 2027 production responses were not confirmed. White vestment accents use a silver frost motif confined to the stripe, not the whole day card.

## Next steps

Install `build/georg-kloster-connect-hub-0.3.7.zip` on `new.georg-kloster.ru` and verify the Message log with test Telegram/WhatsApp request notifications and email replies. On the intended site, use “Connect / check incoming replies” to move the Telegram bot webhook there; the old application will stop receiving updates. Activate and verify MailPoet sending with the configured WP Mail SMTP transport before announcing the newsletter publicly; a test email was intentionally not sent. WordPress.org manual review remains pending.

Last related code commit: `7097c2a` — “Bump Parish Core to 0.2.6” on `main`.
