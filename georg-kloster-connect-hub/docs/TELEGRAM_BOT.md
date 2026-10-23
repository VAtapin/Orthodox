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

- A post selected for Telegram in the WordPress publication controls, or a new post matched by an enabled publication rule. The bot uses the Russian or German post translation that matches the subscriber. If that translation is missing, that recipient is marked as skipped in the delivery status.
- Published schedule changes when **Notify subscribers about changes to the published schedule** is enabled.
- An optional daily schedule digest at the selected time in the WordPress time zone. It includes the day's services and the main commemorations returned by the connected Bible Desktop calendar, when available.
- `/today` for today's services and commemorations, `/week` for the current week's services, `/lang` to choose Russian or German, `/menu` for interactive buttons, and `/help` for commands.

Open Connect Hub → **Telegram bot** to manage the built-in commands and their Russian and German descriptions and replies. `/start` and `/stop` remain available for subscription management. `/today` and `/week` use the generated schedule by default; put `{schedule}` in either reply to insert that schedule into a custom message. Without the placeholder, the saved reply is sent as written. Add slash commands with a unique lowercase English word, a menu description, and replies in both languages. Add text responders with one exact Russian phrase, one exact German phrase, and localized replies. Text triggers are case-insensitive and appear as buttons in `/menu`. Responses use the subscriber's selected language. Standard interface messages and defaults are translated through the plugin's `.po`/`.mo` catalogs.

Scheduled Telegram reminders have separate Russian and German message fields. Both are required for an enabled Telegram reminder. The reminder can be sent to Telegram alone; the general-purpose message field is only needed by other selected channels.

## Delivery status and subscriber controls

Connect Hub → **Telegram bot** shows active subscribers by language and recent campaign status. The page can remove one subscriber, retry failed recipients, or cancel an in-progress campaign. Deliveries run in WordPress Cron batches; transient API failures are retried, while blocked users are removed from active delivery.

The bot can send only to people who started a private chat with it. An active subscriber can opt out with `/stop`. Removing a subscriber in WordPress also deletes the Chat ID from the active subscriber table. Delivery history contains campaign and delivery statuses, not names or email addresses.
