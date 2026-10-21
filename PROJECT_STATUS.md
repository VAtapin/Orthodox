# Project status: Georg-Kloster Prihod

## Current state

The repository contains the Georg-Kloster Prihod theme (1.0.0), Parish Core (0.2.5), and Connect Hub (0.3.5). The theme has five visual styles, native Customizer controls, a standard WordPress widget area, English gettext source strings, and Russian, German, Polish, and Ukrainian catalogs. Author: Atapin Vladimir (`atapin`); site: https://georg-kloster.ru/; email: atapin@gmail.com.

The theme has compact optional-plugin cards for Parish, Connect Hub, Calendar Workshop, Orthodox Donations, and Slavonic Translator. Language switching is delegated to WPML or Polylang. The shared homepage map uses a full-width iframe.

The active theme on the test site `new.georg-kloster.ru` was updated from the current Git package (version 1.0.0). News and category archives use responsive card grids. The homepage service strip has a place logo, date calendar, and time clock with separate columns for date, time, and service title; the time column is widened and its stylesheet uses a file modification cache key so updates reach visitors. Narrow screens stack the strip without clipping. The classic hero motto stays in the text column. The live page retained its existing theme settings.

The newsletter widget area is present on the homepage and news/archive views. It uses MailPoet form 2 (“Новости монастыря”), linked to “Рассылка новостей”; the theme now constrains the form to a 600px maximum width and uses tighter spacing and shorter controls. The old stock demo form remains in MailPoet but is no longer displayed publicly. The confirmation email uses Russian text and the theme palette, and the confirmation page layout is compact with its sidebar hidden. Double opt-in and MailPoet’s built-in CAPTCHA are enabled. MailPoet sending is still inactive (“Other” method awaits activation); WP Mail SMTP has an SMTP transport configured, but delivery has not been tested. No campaign or test email has been sent.

Connect Hub 0.3.5 allows the explicit Telegram “Connect / check incoming replies” action to set this site's webhook even when another application currently owns it. The settings warn that the previous application will stop receiving messages; pending Telegram updates are preserved. Telegram request notifications and replies contain no admin links, reply instructions, or separate service acknowledgements. A saved contact request remains successful if a post-create notification callback fails, and failed form submissions restore values in the same browser tab. Version 0.3.5 was installed successfully on `new.georg-kloster.ru`.

## WordPress.org release status

Version 1.0.0 is submitted in WordPress.org Themes Trac ticket [#296403](https://themes.trac.wordpress.org/ticket/296403); the SVN submission remains separate from the newer Git changes deployed to the test site. The ticket is awaiting manual review. Do not treat the test-site package as a new SVN submission.

## Checks completed

`php tests/lint.php` passed; `php tests/run.php` passed 70 isolated assertions. Connect Hub translations compile and `python tests/plugin-i18n-check.py --only georg-kloster-connect-hub` passed (592 messages); `node --check georg-kloster-connect-hub/assets/request-form.js` passed; the Connect Hub 0.3.5 package built successfully. WordPress confirmed the 0.3.5 plugin ZIP was installed on `new.georg-kloster.ru`. The isolated WordPress Playground integration test could not start because npm registry DNS/network access was unavailable.

## Next steps

On the intended site, use “Connect / check incoming replies” to move the Telegram bot webhook there; the old application will stop receiving updates. Activate and verify MailPoet sending with the configured WP Mail SMTP transport before announcing the newsletter publicly; a test email was intentionally not sent. WordPress.org manual review remains pending.

Last related code commit: pending for Connect Hub 0.3.5.
