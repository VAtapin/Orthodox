=== Georg-Kloster Connect Hub ===
Contributors: atapin
Tags: contact form, messages, social, publishing, telegram
Requires at least: 6.5
Requires PHP: 8.0
Tested up to: 7.1
Stable tag: 0.4.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html

Independent forms, private messages and queued publications for any organisation.

== Description ==

Create separate forms with their own names, titles, introductions, button labels,
topics, recipients and enabled states. Use [gkch_form id="123"] or the Form block.
The previous [gkps_contact_form] shortcode and gkps/contact-form block still work.
Messages record the source form and appear in one private inbox with form and
status filters. Staff can reply by email. Telegram supports the existing opt-in
private-chat reply workflow. Separately, readers can press Start in the bot to
receive public post announcements and service-schedule messages. Russian and
German subscribers receive their matching published translations. Bot users can
request today's or this week's schedule and select their message language.

The menu has Forms, Messages, Publications, Telegram bot, Message log and Settings. The Message log
records time, recipient, message text and technical result for outgoing email and
Telegram request/schedule messages. It is separate from publication history, requires
communication-settings access, and keeps at most 200 entries for 30 days. Deleting a
request or erasing its personal data also removes its message-log entries. The five channel connections
(Telegram, Facebook, Instagram, VK and OK) are tabs in Settings.
Its title is Connect Hub in English, Kommunikation in German and
Коммуникации in Russian. Russian and German PHP/editor catalogs are bundled.
Forms can concern church matters, deliveries, appointments or any other topic.
They do not require Parish Core or a particular theme.

Set up opt-in automatic publication rules, each with its own channels. Send new
WordPress posts on first publication, optionally filtered by category; scheduled
WordPress posts are supported. Editing existing posts does not resend them.
Create standalone messages/reminders with optional links and a one-time, daily
or weekly schedule in the WordPress timezone. Instagram reminders use a JPEG
selected from the media library. Late Cron runs send once, without a missed-slot
backlog. Editing, pausing or removing a rule prevents its pending deliveries.
Rules require communication-settings and publication capabilities; the stored
owner's permissions are checked again at execution. Sending stays asynchronous,
with a private status history, cancellation and retry.
Facebook, VK and OK publish text/link posts. Instagram publishes a JPEG featured
image with a caption via container creation, background status polling and publish.
This release does not implement carousels, videos or reels. An ambiguous OK
transport result requires checking the account before an explicitly confirmed retry.
No channel is enabled automatically and no account credentials are bundled.

== Support ==

Author: Atapin Vladimir. Website: https://atapin.de/. Email: atapin@gmail.com.

== Source code ==

Development repository: https://github.com/VAtapin/Orthodox/tree/main/georg-kloster-connect-hub

== Installation ==

Upload georg-kloster-connect-hub-0.4.2.zip. Replace an existing Connect Hub version,
or install as a new plugin and activate it.
Activation deactivates the previous georg-kloster-prihod-social development plugin
without deleting its settings. Keep only Connect Hub active. Network-active old
installations require network-wide activation. Existing messages, forms, capabilities,
cron hooks and Telegram REST paths remain compatible. On the first WordPress request
after activation or upgrade, Connect Hub creates the subscriber, campaign and delivery
tables; existing plugin data is retained.

Configure forms under Forms, global reception/retention under Settings and each
channel under its tab in Settings. Each channel tab contains a step-by-step guide
with official links. Telegram contact notifications use only the private Chat ID
set on each form; a blank ID disables Telegram notification for that form. Public
posts and schedule messages go to the bot's active subscribers, not to a form's
private Chat ID. Read docs/TELEGRAM_BOT.md and docs/CHANNEL_SETUP_RU.md for setup.
Configure
automatic rules under Publications. Insert the Form block and select a saved form, or use its
listed shortcode. Exclude pages containing forms from full-page caching: tokens
are single-use and expire after one hour. Ensure WordPress Cron runs.

== External services ==

All sending is opt-in. Configure an account and enable a channel explicitly.
WordPress Cron sends selected publications in the background. Missing permissions,
expired credentials and rejected API responses are recorded as failures.
A configured channel alone is not proof of successful live delivery.

Telegram Bot API: selected post or schedule announcements are sent to private
Chat IDs of readers who explicitly started the bot; the subscriber table stores
Chat ID, language and subscription timestamps. `/stop` or admin removal deletes
the active subscriber record. Optional contact notifications transmit request
name, email, subject and body only to that form's configured private chat. The
bot does not receive email addresses or names from public subscribers.
https://core.telegram.org/bots/api https://telegram.org/privacy

Facebook Graph API: selected public text/link and Page ID. Requires a Page token
with publication rights and the API version configured in the application.
https://developers.facebook.com/docs/pages-api/posts/ https://www.facebook.com/privacy/policy/

Instagram API with Facebook Login: professional account linked to a Facebook Page,
a token with instagram_basic/instagram_content_publish permissions, account ID,
public HTTPS JPEG URL and caption. Images are fetched by Meta from that public URL.
https://developers.facebook.com/docs/instagram-platform/content-publishing/
https://privacycenter.instagram.com/policy/

VK API wall.post: selected public text/link, user access token with wall permission,
user/community owner ID and stable publication GUID. Negative owner IDs identify
communities. https://dev.vk.com/ru/method/wall.post https://vk.com/privacy

OK API mediatopic.post: selected public text/link, group ID, access token,
application public key and signature calculated with the application secret.
The application needs GROUP_CONTENT permission.
https://apiok.ru/dev/methods/rest/mediatopic/mediatopic.post https://ok.ru/privacy

Google reCAPTCHA v3: public form browser interaction data and response tokens are
sent to Google for bot-risk scoring. The plugin sends the response token and
secret key to Google's SiteVerify endpoint; it does not forward the visitor IP.
Google recommends migrating classic keys to CreateAssessment.
https://docs.cloud.google.com/recaptcha/docs/migrate-recaptcha https://policies.google.com/privacy

Credentials are stored in non-autoload WordPress options and never echoed back
in settings. Protect database backups. Service-side app approval and account
permissions must be obtained by the site administrator.

== Privacy ==

Messages remain private in WordPress and require the designated capabilities.
Privacy exporter/eraser support and retention of closed messages are retained.
The technical outgoing-message log stores recipients, message text and service
results for up to 30 days (200 entries); deleting a request or erasing its data
also removes its related log entries.
The source form ID/name is stored with each message. Form recipients are never
included in public markup or the block selector. Telegram broadcast history stores
delivery results by subscriber ID but does not store subscriber names or emails.
Ordinary uninstall retains
saved messages/settings and does not delete posts in external services.

== Shortcodes ==

Dashboard help appears under Connect Hub > Forms and Settings > General, in the Shortcodes and blocks section. Insert a Form block and select a form, or paste an example into a Shortcode block.

* [gkch_form id="123"] — display the selected form. Copy its ID from the Forms table; id="0" or no id displays the default form.
* [gkps_contact_form id="123"] — compatible alias with the same attributes, retained for existing pages.
* Optional title, intro and submit attributes override only the displayed heading, introduction and button label: [gkch_form id="123" title="Contact us" intro="Send a message" submit="Send"]. They do not change saved topics, recipients or settings.

Enable the selected form and incoming messages in Settings. Disabled or missing forms render no public form. Submitted messages retain their selected form's topics and recipient.
Configure optional reCAPTCHA v3 site and secret keys under Connect Hub > Settings > General. The server rejects responses with a failed check, unexpected action or hostname, or a score below the configured threshold.

General reception, retention and reCAPTCHA settings use one save button. A status message confirms whether both reCAPTCHA keys are saved; the secret key is never displayed.

== Changelog ==

= 0.4.2 =
Manage built-in Telegram commands and localized replies, add exact-phrase
autoresponders, and provide an interactive /menu with buttons.

= 0.4.1 =
Keep standard bot messages in plugin gettext source and RU/DE PO/MO catalogs.
Add admin-managed custom Telegram commands with localized menu descriptions and replies.

= 0.4.0 =
Add opt-in Telegram bot subscribers, Russian/German post broadcasts, schedule
commands and digest, shared webhook routing, and subscriber/campaign management.
Route each form's private notifications only to its own Chat ID.

= 0.3.13 =
Improve the requests list and add permission-checked bulk deletion.

= 0.3.12 =
Add detailed per-channel setup guides and per-form Telegram recipient/staff routing.
Preserve reply mapping to the responsible person and chat.

= 0.3.11 =
Remove WhatsApp settings and sending from Connect Hub.

= 0.3.10 =
Preserve newly submitted reCAPTCHA site keys when WordPress sanitizes the settings option.

= 0.3.9 =
Save all general and reCAPTCHA settings through one form and return to the settings page. Show whether reCAPTCHA keys are configured without exposing the secret.

= 0.3.8 =
Protect public forms with server-verified Google reCAPTCHA v3 risk scores.

= 0.3.7 =
Add a private technical journal for outgoing email and Telegram/WhatsApp request and schedule messages.
Show recipients, sent content and delivery results; erase linked entries with requests.

= 0.3.6 =
Remove admin links and service-only instructions from Telegram request notifications.
Do not send extra Telegram acknowledgements after replies.

= 0.3.5 =
Do not report a saved request as failed when a post-create notification hook throws.
Restore submitted form values after a failed submission in the same browser tab.

= 0.3.4 =
Allow the explicit Telegram reply-connection action to move the bot webhook to this site.
Warn that the previous application will stop receiving messages.

= 0.3.3 =
Add plugin author, website and support contact metadata.

= 0.3.2 =
Use English source UI strings while retaining Russian and German catalogs. Add form shortcode and block help with parameters, examples and the compatible alias.

= 0.3.1 =
Move Telegram and WhatsApp into the shared Settings tabs. Retain saved credentials,
message/reply controls, existing save actions and redirects from old settings URLs.

= 0.3.0 =
Replace the single-post publication picker with automatic rules for new posts,
category filters, scheduled messages and one-time/daily/weekly reminders.
Move social-network connections into Settings and hide the Social networks menu.
Keep existing settings and history; add rule revision/owner checks and local-time
scheduling across DST. Bundle updated Russian and German translations.

= 0.2.0 =
Rename the development package to Connect Hub. Add independent forms, a unified
message inbox, publication controls/history, Instagram, VK and OK transports,
seven-section navigation and complete bundled Russian/German catalogs.
