# Telegram bot: subscriptions and publications

Connect Hub uses one Telegram bot token and one incoming webhook for two separate purposes:

- Each form sends a new contact message only to the private Chat ID saved on that form. An empty form Chat ID means that the form does not send a Telegram notification. The old global Chat ID is retained for compatibility but is not used for form routing.
- Public posts, schedule notices and digests go to bot subscribers. A reader subscribes by opening the bot and pressing **Start**. `/stop` deletes the active subscription; `/lang` changes its language.

## Connect the bot

1. Create or select the bot with [@BotFather](https://t.me/BotFather) and enter its token in Connect Hub → Settings → Telegram. Keep the token private.
2. Enable sending and select **Enable bot subscriptions and broadcasts**.
3. Save, then click **Connect / check Telegram bot webhook**. The same webhook continues to receive bot commands and, when enabled, replies to full contact messages.
4. Send the bot `/start` from a test account. The account appears in Connect Hub → **Telegram bot**.

For contact messages, set a separate private Chat ID on each form. The person responsible opens the bot, presses Start, and gets their numeric ID from [@userinfobot](https://t.me/userinfobot). This ID routes only contact messages from that form; it does not receive public broadcasts.

## What subscribers receive

- A post selected for Telegram in the WordPress publication controls, or a new post matched by an enabled publication rule. News uses the subscriber's translated post title and a short HTML excerpt, the featured image, and a localized **Read the news** button that opens the matching translated article. The long URL is not printed in the message. If that translation is missing, that recipient is marked as skipped in the delivery status.
- Published schedule changes when **Notify subscribers about changes to the published schedule** is enabled.
- An optional daily schedule digest at the selected time in the WordPress time zone. It includes the day icon and caption, every service, the services remaining that week, and all commemorations returned by the connected Bible Desktop calendar, when available.
- `/today` sends the same complete daily schedule and commemorations; `/week` groups every service by localized date. Both show each service place once after the schedule, with its address linked to Google Maps directions. Service lines include a vestment-color marker when the calendar provides one.
- `/lang` chooses among the active site languages, `/menu` opens interactive buttons, and `/help` lists commands.

Incoming commands and replies, form notifications, email replies, publication sends and subscriber broadcasts run directly in the request that triggers them. Each `/start` subscriber receives a separate private Telegram API call; failed deliveries are recorded for an administrator to retry manually. No Telegram or reply-email delivery is deferred to WP-Cron. Only calendar timing uses WP-Cron: the daily digest and scheduled publication rules fire at their configured time, then send immediately.

Service place addresses are edited in **Parish → Places of service**, in that place's own **Address** field. Schedule messages use the address of each place where a service is scheduled. The general address in **Parish → Parish details** is used only when a service place has no address; it is not used to replace a place's own address.

Open Connect Hub → **Bot commands** to see the WordPress-style command list. Select a row to edit one item, or choose **Add command or responder**. Built-in commands such as `/start`, `/stop`, `/today` and `/week` each have their own edit screen; `/start` and `/stop` cannot be disabled because they control subscription status.

The translation fields follow the active languages in WPML. For each active language, enter the Telegram command-menu description, the exact phrase that triggers a text responder, and its reply. Existing saved language fields stay stored if that language is later removed from WPML. Standard interface labels and defaults use the plugin's `.po`/`.mo` catalogs.

For the reply type, choose plain text, formatted Telegram HTML, today's or this week's parish schedule, a published WordPress page, or a published news post. Text replies use the WordPress visual editor. Its formatting is limited to Telegram-supported bold, italic, underline, strikethrough, links, quotations, and code; unsupported HTML is removed. Lists are converted to bullet lines because regular Telegram HTML messages do not support HTML list tags. A page reply sends the full rendered page content in Telegram-supported formatting and its link. A news-post reply sends the featured image, a short localized HTML teaser and an inline button to the translated article; its URL is hidden from the message text. If the page or post has no translation in the subscriber's language, the bot reports that rather than sending content in another language. For schedule replies, `{schedule}` inserts the generated schedule into your own text; if you omit it, the schedule is appended after the configured text. Text triggers are case-insensitive and appear as buttons in `/menu`. Replies use the subscriber's selected WPML language.

Each language has its own media selection. Choose up to ten JPEG/PNG photos or MP4 videos from the WordPress Media Library; the bot sends one file directly or groups multiple files as an album. Telegram fetches the files from their public WordPress URLs, so the URLs must be reachable without logging in. URL-based delivery supports photos up to 5 MB and videos up to 20 MB. A short reply is included as the media caption; longer text is sent separately.

Open Connect Hub → **Forms** to see the list of forms. Use **Add form** to create one or select a row to edit it on its own screen. The form's Telegram Chat ID is only for private contact notifications from that form; public news and schedule broadcasts go to bot subscribers.

Scheduled Telegram reminders have one message field for each language currently active in WPML. Every active language needs a translation before a Telegram reminder can be enabled; a subscriber with no matching translation is skipped and shown in delivery status. Languages removed from WPML remain in saved rules for compatibility. The reminder can be sent to Telegram alone; the general-purpose message field is only needed by other selected channels.

## Delivery status and subscriber controls

Connect Hub → **Telegram bot** shows active subscribers by language and recent campaign status. The page can remove one subscriber, retry failed recipients immediately, or cancel an in-progress campaign. A Telegram error is recorded without an automatic retry; blocked users are removed from active delivery. Since Telegram sends a separate message to each private chat, a large subscriber list can take time in the request and may meet Telegram's rate limit.

The bot can send only to people who started a private chat with it. An active subscriber can opt out with `/stop`. Removing a subscriber in WordPress also deletes the Chat ID from the active subscriber table. Delivery history contains campaign and delivery statuses, not names or email addresses.
