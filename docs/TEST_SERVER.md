# WordPress для проверки экосистемы

prihod.georg-kloster.ru — отдельный WordPress на общем **боевом** Plesk-сервере
владельца с другими рабочими сайтами. Разрешены только действия внутри данного
WordPress. Не менять окружение, Plesk, настройки других сайтов; не добавлять блокировки.

- Сайт: https://prihod.georg-kloster.ru/.
- Дашборд: https://prihod.georg-kloster.ru/wp-admin/admin.php?page=prihod.
- Мастер: https://prihod.georg-kloster.ru/wp-admin/admin.php?page=prihod-wizard.
- WordPress: 7.1.3, русский интерфейс, Europe/Berlin.
- Каталог: `/var/www/vhosts/prihod.georg-kloster.ru/httpdocs`.
- Существующий PHP: `/opt/plesk/php/8.5/bin/php`, фактически 8.5.11.
- Имеющийся WP-CLI Plesk: `/usr/local/bin/wp`, 2.12.0.
- MariaDB 10.6.23, отдельная база и пользователь `prihod`.
- SSH: предоставленный профиль Bitvise, пользователь `prihod`, не root.

Установлены по прямому разрешению владельца: Core 1.0.0-rc.2, тема «Приход» 0.3.0,
orthocal 1.3.78, Orthodox Donations 1.0.2 и Telegram/WhatsApp/Facebook подключения
0.1.1. Пароли и профиль SSH не входят в репозиторий. Пароль администратора изменён
владельцем; первоначальный установочный файл пароля устарел и для входа не используется.

Блокировка HTTP/email, ранее добавленная внутри WordPress, снята по требованию
владельца. Нет активного MU-плагина защиты и нет рекомендации запрещать индексацию
(blog_public=1). Серверные PHP/Plesk настройки не изменялись. Календарь получает
реальные данные публичного API Bible Desktop, что подтверждено в браузере.
Реальные оплаты, рассылки и публикации агентом не выполнялись.

Дашборд и раздел «Наши плагины» показывают настоящие версии и состояния компонентов.
Меню: дашборд, информация, обращения, наши плагины, настройки, мастер. Формы настроек
зарегистрированы с прежними capabilities и доступны кнопками из своих разделов.
Четыре виджета консоли проверяются отдельно от собственно дашборда плагина.
Материалы, приходской профиль и службы не заполнены придуманными данными.

По отдельному прямому разрешению обновлена тема 0.3.0 и создано 13 демо-страниц
**черновиками**: примерные редактируемые тексты, стандартные блоки и действующие
формы установленных плагинов. Главная и меню не назначались, публикация не включалась.
Панель: «Приход → Настройки → Оформление прихода», семь разделов, пять схем.
theme-options.png, theme-demo-pages.png и theme-options-mobile.png — снимки проверки.

Проверки: официальные контрольные суммы Core после корректной распаковки системным
tar; 24 таблицы MariaDB OK; HTTPS главной и входа 200; вход с новым паролем; реальный
календарь; мастер, дашборд и тематические настройки в браузере.
`tests/server-check.php` проходит 35 проверок на PHP 8.5.11: компоненты, настоящие
формы, блоки, схемы, шаблоны, переводы, отсутствие блокировок и дашборд.
Снимки — docs/test-server/. Проверка оплаты/доставки не проведена.

Пакеты обновления находятся **внутри WordPress** в `wp-content/prihod-packages`;
проверка — `wp-content/prihod-server-check.php`. Git checkout в webroot отсутствует.
Повторное применение уже подготовленной текущей сборки (обычное обновление WordPress):

```bash
cd /var/www/vhosts/prihod.georg-kloster.ru/httpdocs && \
export PATH="/opt/plesk/php/8.5/bin:$PATH" && \
php -d memory_limit=512M /usr/local/bin/wp plugin install wp-content/prihod-packages/prihod-1.0.0-rc.2.zip wp-content/prihod-packages/prihod-telegram-0.1.1.zip wp-content/prihod-packages/prihod-whatsapp-0.1.1.zip wp-content/prihod-packages/prihod-social-0.1.1.zip wp-content/prihod-packages/georg-kloster-orthodox-donate-1.0.2.zip --force --activate && \
php -d memory_limit=512M /usr/local/bin/wp theme install wp-content/prihod-packages/prihod-theme-0.3.0.zip --force --activate && \
php -d memory_limit=512M /usr/local/bin/wp eval-file wp-content/prihod-server-check.php
```

Команда обновляет только уже разрешённые компоненты WordPress из локальных ZIP.
Node/Composer, серверные настройки и отдельные ручные миграции для «Прихода» не нужны.
`--force` относится к обновлению файлов WordPress, не к Git. Новый ZIP нужно сначала
загрузить внутри WordPress; эта команда применяет перечисленные версии текущей сборки.

Повторная проверка без обновления:

```bash
cd /var/www/vhosts/prihod.georg-kloster.ru/httpdocs && \
export PATH="/opt/plesk/php/8.5/bin:$PATH" && \
php -d memory_limit=512M /usr/local/bin/wp core verify-checksums --locale=ru_RU && \
php /usr/local/bin/wp db check && \
php -d memory_limit=512M /usr/local/bin/wp eval-file wp-content/prihod-server-check.php
```
