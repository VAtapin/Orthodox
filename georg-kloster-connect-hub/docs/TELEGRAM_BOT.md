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

- A post selected for Telegram in the WordPress publication controls, or a new post matched by an enabled publication rule. The bot uses the post translation for the subscriber's language when available. If that translation is missing, that recipient is marked as skipped in the delivery status.
- Published schedule changes when **Notify subscribers about changes to the published schedule** is enabled.
- An optional daily schedule digest at the selected time in the WordPress time zone. It includes the day's services and the main commemorations returned by the connected Bible Desktop calendar, when available.
- `/today` for today's services and commemorations, `/week` for the current week's services, `/lang` to choose among the active site languages, `/menu` for interactive buttons, and `/help` for commands.

Open Connect Hub → **Bot commands** to see the WordPress-style command list. Select a row to edit one item, or choose **Add command or responder**. Built-in commands such as `/start`, `/stop`, `/today` and `/week` each have their own edit screen; `/start` and `/stop` cannot be disabled because they control subscription status.

The translation fields follow the active languages in WPML. For each active language, enter the Telegram command-menu description, the exact phrase that triggers a text responder, and its reply. Existing saved language fields stay stored if that language is later removed from WPML. Standard interface labels and defaults use the plugin's `.po`/`.mo` catalogs.

For the reply type, choose plain text, formatted Telegram HTML, today's or this week's parish schedule, a published WordPress page, or a published news post. Telegram HTML is a Telegram message with supported formatting tags, not a full HTML page; unsafe or unsupported tags are removed. A page reply sends the full rendered page content in Telegram-supported formatting and its link. A news-post reply sends the translated title, a short excerpt and a link. If the page or post has no translation in the subscriber's language, the bot reports that rather than sending content in another language. For schedule replies, `{schedule}` inserts the generated schedule into your own text; if you omit it, the schedule is appended after the configured text. Text triggers are case-insensitive and appear as buttons in `/menu`. Replies use the subscriber's selected WPML language.

Open Connect Hub → **Forms** to see the list of forms. Use **Add form** to create one or select a row to edit it on its own screen. The form's Telegram Chat ID is only for private contact notifications from that form; public news and schedule broadcasts go to bot subscribers.

Scheduled Telegram reminders have one message field for each language currently active in WPML. Every active language needs a translation before a Telegram reminder can be enabled; a subscriber with no matching translation is skipped and shown in delivery status. Languages removed from WPML remain in saved rules for compatibility. The reminder can be sent to Telegram alone; the general-purpose message field is only needed by other selected channels.

## Delivery status and subscriber controls

Connect Hub → **Telegram bot** shows active subscribers by language and recent campaign status. The page can remove one subscriber, retry failed recipients, or cancel an in-progress campaign. Deliveries run in WordPress Cron batches; transient API failures are retried, while blocked users are removed from active delivery.

The bot can send only to people who started a private chat with it. An active subscriber can opt out with `/stop`. Removing a subscriber in WordPress also deletes the Chat ID from the active subscriber table. Delivery history contains campaign and delivery statuses, not names or email addresses.
