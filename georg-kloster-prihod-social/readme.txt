=== Georg-Kloster Parish Social ===
Contributors: vatapin
Tags: contact form, email, telegram, whatsapp, facebook
Requires at least: 6.5
Requires PHP: 8.0
Stable tag: 0.1.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html

Configurable contact form, private correspondence and optional messaging channels.

== Description ==

The form title, introduction, button text, suggested topics and notification email are configurable. The form can be used for a parish, a hairdresser or another organisation. Messages and replies are stored privately in WordPress. Staff can answer by email. Telegram, WhatsApp and Facebook Page channels are disabled by default and configured separately.

== External services ==

Telegram Bot API receives public post text and link when an editor explicitly chooses publication. If the private request integration is enabled, Telegram receives the sender name, email, subject, message and reply photos in the configured private chat. The service and privacy policy are at https://core.telegram.org/bots/api and https://telegram.org/privacy.

WhatsApp Cloud API receives a recipient number, approved template and text/link after its administrator explicitly enables the channel and confirms recipient consent. This version sends outbound template messages only. Replies are not imported. Service terms and privacy policy: https://www.whatsapp.com/legal/business-terms and https://www.whatsapp.com/legal/privacy-policy.

Facebook Graph API receives public post text and link only after an editor selects publication for that post. Service terms and privacy policy: https://www.facebook.com/legal/terms and https://www.facebook.com/privacy/policy/.

Each service receives data only when its channel is enabled and a message is submitted or queued. WP-Cron performs background sends. The form itself works without any external service.

== Installation ==

Upload and activate the ZIP. Open Contacts to configure the form. Insert the Contact form block or [gkps_contact_form] shortcode in a page. Configure optional channels under Contacts. Ensure WordPress Cron runs for queued notifications.

== Privacy ==

Messages remain in the WordPress database and are visible only to staff with the designated capabilities. The plugin adds WordPress privacy exporter and eraser support. Ordinary uninstall retains saved messages and settings.

== Changelog ==

= 0.1.3 =
Bundle complete Russian interface and editor translations. Load translations at init.


= 0.1.2 =
Give the contact form its own public styles across themes.

= 0.1.1 =
Add the editor block, per-page form text and German form translations.

= 0.1.0 =
* Initial standalone development release.
