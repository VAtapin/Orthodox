# Переписка через Telegram — Core rc.17

Только Приход; тема0.3.26, Telegram0.1.3 и сторонние плагины не меняются.
Requests уже допускал новые ответы после answered; новая проверка подтверждает
отдельные записи/письма и сохранность истории. Текстовый ответ/фото/статический
стикер отправляются кнопкой Reply к исходному сообщению бота. Закрытое обращение
не принимает новые ответы. Повтор того же update_id подавляется до загрузки файла.

Фото: официальный getFile, фиксированный api.telegram.org, путь photos/stickers,
нет redirects,4MiB лимит HTTP и данных,≤4млн пикселей. JPEG/PNG сохраняются,
статический WebP превращается в PNG в памяти через GD. На действующем PHP
поддержка GD/WebP подтверждена без обработки личных файлов. Никаких public uploads,
временных открытых файлов или ссылок с bot token. MIME Content-ID и AltBody,
временные PHPMailer Sender/AltBody/attachments восстанавливаются после отправки.
PHP8.5: unset(GdImage), без deprecated imagedestroy.

Private meta _prihod_reply_media; защищённый admin-post endpoint только для
пользователя с view_prihod_requests и nonce конкретного сообщения. Редактор и гость
не имеют доступа. Standard privacy export включает данные изображения; удаление
сообщения/обращения удаляет их. Медиа не публикуется в REST или медиатеке.
Анимации/видео/документы не поддерживаются; сохраняется внутреннее объяснение,
без фиктивного email. Временная ошибка HTTP оставляет update без receipt для повтора.

## Диагностика действующего обращения

13 октября внутри разрешённого WordPress исследовано последнее обращение96.
Сохранён один ответ97 от13октября13:46:07UTC (15:46:07 Europe/Berlin), Telegram Reply.
Одна попытка; delivery=sent; transport=mail; From и envelope совпадают с профилем.
Это подтверждает передачу PHP-почте, не доставку адресату. WP_DEBUG_LOG выключен;
доступного журнала дальнейшей доставки внутри WordPress нет. С прямого разрешения проверен MTA-доступ: /var/log/maillog существует,640syslog:adm,
SSH prihod:psacln не может читать его. Права/настройки сервера не менялись; Gmail
не подключался, реальных писем/сообщений не отправляли. Подробнее MAIL_DIAGNOSTICS.md.
Причина недоставки после PHP остаётся неподтверждённой. Требуется запись MTA
именно об этой отправке от администратора, прежде чем утверждать причину отказа.
Фото/стикер не сохранены старым обработчиком: он допускал только message.text.
Прошлые пропущенные медиа не восстанавливаются автоматически и не переотправляются.

Новые попытки содержат accepted/attempted_at_gmt/error_code в закрытой meta;
UI явно отделяет передачу почтовой системе от получения в ящик. Ошибки хранятся
без текстов письма, секретов и сырого ответа провайдера.

## Входящий email

Настоящего приёма/сопоставления входящих писем сейчас нет. Подготовленный вариант:
выделенный ящик домена монастыря → IMAP внутри Прихода → проверка отправителя и
Message-ID/In-Reply-To → отдельная запись истории → уведомление сотруднику.
Нужны адрес/IMAP host и отдельное разрешение на подключение; пароль только в форме
WordPress. Gmail запрещён. До выбора канала и реализации это не готовая функция;
нынешний Reply-To ведёт на адрес прихода, не на автоматический импорт.

## Проверки и установка

WP7.1.3/PHP8.4.25:356 smoke/223 design;
WP6.5.13/PHP8.0.30:356/222;62 unit,2556 UI translations, PHP syntax.
Транспорт Telegram и wp_mail перехвачен:2разных ответа, duplicate update, PNG+caption,
настоящий WebP→PNG, multipart/related/alternative и CID, безопасное восстановление
PHPMailer, запрет guest/editor/forged nonce, export/delete, path traversal,
HTTP failure/recovery, недопустимый animated sticker, отсутствие public attachment.
HTML-образец письма: desktop1440/mobile390, изображение загружено, overflow нет.
Это браузерный просмотр MIME-HTML, не подтверждение доставки в реальный ящик.

Пакет build/prihod-1.0.0-rc.17.zip,
SHA256:301373A938B80D87431AC05F5F212C5717BF79E6D4EACEE75269DD1CA6DBF1A7.
На сервере пока rc.15; rc.17 требует прямого согласия владельца.
После согласованной загрузки пакета и проверки только в wp-content:

```bash
cd /var/www/vhosts/prihod.georg-kloster.ru/httpdocs && \
export PATH="/opt/plesk/php/8.5/bin:$PATH" && \
php /usr/local/bin/wp plugin install wp-content/prihod-packages/prihod-1.0.0-rc.17.zip --force && \
php /usr/local/bin/wp eval-file wp-content/prihod-server-check.php --user=prihod-owner
```

Нет Composer/npm/migrations/restart. Сохранённая конфигурация/переписка не сбрасываются.
Источники API: [Telegram Bot API](https://core.telegram.org/bots/api#getfile),
[PHP GD](https://www.php.net/manual/en/function.imagedestroy.php).
