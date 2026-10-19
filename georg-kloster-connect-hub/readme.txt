=== Georg-Kloster Connect Hub ===
Contributors: vatapin
Tags: contact form, messages, social, publishing, telegram
Requires at least: 6.5
Requires PHP: 8.0
Stable tag: 0.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html

Independent forms, private messages and queued publications for any organisation.

== Description ==

Create separate forms with their own names, titles, introductions, button labels,
topics, recipients and enabled states. Use [gkch_form id="123"] or the Form block.
The previous [gkps_contact_form] shortcode and gkps/contact-form block still work.
Messages record the source form and appear in one private inbox with form and
status filters. Staff can reply by email. Telegram supports the existing opt-in
private-chat reply workflow. WhatsApp sends approved outbound templates only;
WhatsApp replies are not imported.

The menu has Forms, Messages, Publications, Telegram, WhatsApp, Social networks,
and Settings. Its title is Connect Hub in English, Kommunikation in German and
Коммуникации in Russian. Russian and German PHP/editor catalogs are bundled.
Forms can concern church matters, deliveries, appointments or any other topic.
They do not require Parish Core or a particular theme.

Select a published WordPress post and the channels to publish to. Publications
run via WordPress Cron and have a private status history, cancellation and retry.
Facebook, VK and OK publish text/link posts. Instagram publishes a JPEG featured
image with a caption via container creation, background status polling and publish.
This release does not implement carousels, videos or reels. An ambiguous OK
transport result requires checking the account before an explicitly confirmed retry.
No channel is enabled automatically and no account credentials are bundled.

== Installation ==

Upload georg-kloster-connect-hub-0.2.0.zip as a new plugin and activate it.
Activation deactivates the previous georg-kloster-prihod-social development plugin
without deleting its settings. Keep only Connect Hub active. Network-active old
installations require network-wide activation. The internal gkps data, capabilities,
cron hooks and Telegram REST paths remain compatible; no database migration is needed.

Configure forms under Forms, global reception/retention under Settings and each
channel in its section. Insert the Form block and select a saved form, or use its
listed shortcode. Exclude pages containing forms from full-page caching: tokens
are single-use and expire after one hour. Ensure WordPress Cron runs.

== External services ==

All sending is opt-in. Configure an account and enable a channel explicitly.
WordPress Cron sends selected publications in the background. Missing permissions,
expired credentials and rejected API responses are recorded as failures.
A configured channel alone is not proof of successful live delivery.

Telegram Bot API: selected public text/link and target chat ID. Optional full
private messages transmit name, email, subject, body and reply photos to the
configured private chat. https://core.telegram.org/bots/api https://telegram.org/privacy

WhatsApp Cloud API: consented recipient number, approved template, language and
text/link. https://developers.facebook.com/docs/whatsapp/cloud-api/
https://www.whatsapp.com/legal/privacy-policy/

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

Credentials are stored in non-autoload WordPress options and never echoed back
in settings. Protect database backups. Service-side app approval and account
permissions must be obtained by the site administrator.

== Privacy ==

Messages remain private in WordPress and require the designated capabilities.
Privacy exporter/eraser support and retention of closed messages are retained.
The source form ID/name is stored with each message. Form recipients are never
included in public markup or the block selector. Ordinary uninstall retains
saved messages/settings and does not delete posts in external services.

== Changelog ==

= 0.2.0 =
Rename the development package to Connect Hub. Add independent forms, a unified
message inbox, publication controls/history, Instagram, VK and OK transports,
seven-section navigation and complete bundled Russian/German catalogs.
