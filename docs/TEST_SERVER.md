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

Установлены по прямому разрешению владельца: Core 1.0.0-rc.6, тема «Приход» 0.3.7,
orthocal 1.3.78, Orthodox Donations 1.0.2 и Telegram/WhatsApp/Facebook подключения
0.1.1. Пароли и профиль SSH не входят в репозиторий. Пароль администратора изменён
владельцем; первоначальный установочный файл пароля устарел и для входа не используется.

Блокировка HTTP/email, ранее добавленная внутри WordPress, снята по требованию
владельца. Нет активного MU-плагина защиты и нет рекомендации запрещать индексацию
(blog_public=1). Серверные PHP/Plesk настройки не изменялись. Календарь получает
реальные данные публичного API Bible Desktop, что подтверждено в браузере.
Реальные оплаты, рассылки и публикации агентом не выполнялись.

Обзор прихода и раздел «Наши плагины» показывают настоящие версии и состояния компонентов.
Меню: обзор прихода, информация, обращения, наши плагины, настройки, мастер. Формы настроек
зарегистрированы с прежними capabilities и доступны кнопками из своих разделов.
Четыре виджета консоли проверяются отдельно от собственно дашборда плагина.
По прямому запросу владельца опубликованы и наполнены 13 демо-страниц по материалам
georg-kloster.ru; главная ID 7, тематическая навигация, шесть публикаций, восемь
изображений, профиль/духовенство и пять датированных служб. Подробнее — MONASTERY_DEMO.md.
Панель темы: «Приход → Настройки → Оформление прихода», семь разделов, пять схем.
Верхнее меню «Прихода» ведёт к «Обзору прихода» со всех его внутренних экранов;
форма расписания сгруппирована, описание/переносы раскрываются отдельно.
На полной странице календаря отсутствует дублирующий календарь боковой колонки.
Снимки всех страниц, схем и мобильной проверки сохранены в docs/test-server/.

Проверки: официальные контрольные суммы Core после корректной распаковки системным
tar; 24 таблицы MariaDB OK; HTTPS главной и входа 200; вход с новым паролем; реальный
календарь; мастер, дашборд и тематические настройки в браузере.
`tests/server-check.php` проходит 44 проверки на PHP 8.5.11: компоненты, настоящие
формы, блоки, схемы, шаблоны, переводы, отсутствие блокировок и дашборд.
Снимки — docs/test-server/. Проверка оплаты/доставки не проведена.

Пакеты обновления находятся **внутри WordPress** в `wp-content/prihod-packages`;
проверка — `wp-content/prihod-server-check.php`. Git checkout в webroot отсутствует.
Повторное применение уже подготовленной текущей сборки (обычное обновление WordPress):

```bash
cd /var/www/vhosts/prihod.georg-kloster.ru/httpdocs && \
export PATH="/opt/plesk/php/8.5/bin:$PATH" && \
php -d memory_limit=512M /usr/local/bin/wp plugin install wp-content/prihod-packages/prihod-1.0.0-rc.6.zip --force --activate && \
php -d memory_limit=512M /usr/local/bin/wp theme install wp-content/prihod-packages/prihod-theme-0.3.7.zip --force --activate && \
php -d memory_limit=512M /usr/local/bin/wp eval-file wp-content/prihod-server-check.php --user=prihod-owner
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
php -d memory_limit=512M /usr/local/bin/wp eval-file wp-content/prihod-server-check.php --user=prihod-owner
```

## Применение уточнённого оформления

scripts/apply-reference-design.php отдельно загружается в wp-content/prihod-packages.
Он добавляет OSM-карту только при отсутствии embed и назначает about только
неизменённой собственной демо-странице с прежним стандартным шаблоном.
Профиль, тексты страниц и сохранённая схема не переписываются.

```bash
cd /var/www/vhosts/prihod.georg-kloster.ru/httpdocs && \
export PATH="/opt/plesk/php/8.5/bin:$PATH" && \
php -d memory_limit=512M /usr/local/bin/wp plugin install wp-content/prihod-packages/prihod-1.0.0-rc.6.zip --force --activate && \
php -d memory_limit=512M /usr/local/bin/wp theme install wp-content/prihod-packages/prihod-theme-0.3.7.zip --force --activate && \
php -d memory_limit=512M /usr/local/bin/wp eval-file wp-content/prihod-packages/apply-reference-design.php --user=prihod-owner && \
php -d memory_limit=512M /usr/local/bin/wp eval-file wp-content/prihod-server-check.php --user=prihod-owner
```

Все перечисленные файлы уже загружены и эта сборка применена 12.10.2026.
Новых серверных настроек, Node/Composer, migrations или блокировок команда не содержит.
Отчёт и ограничения точности: DESIGN_CONFORMITY.md.

Текущая сборка Core rc.6 / тема 0.3.7 уже применена: два верхних блока
публичного расписания, отметки мини-месяца и административные колонки.
Для повторного обновления использовать первую команду выше. Данные служб
и статусы опубликованных страниц не менялись. Проверка включает реальные
даты таблицы, две карточки расписания и сегодняшнюю/выбранную дату.

## Только обновление шаблона статьи, тема 0.3.7

Пакет загружен и применён. Core rc.6 и внешние плагины не обновлялись.

```bash
cd /var/www/vhosts/prihod.georg-kloster.ru/httpdocs && \
export PATH="/opt/plesk/php/8.5/bin:$PATH" && \
php -d memory_limit=512M /usr/local/bin/wp theme install wp-content/prihod-packages/prihod-theme-0.3.7.zip --force --activate && \
php -d memory_limit=512M /usr/local/bin/wp eval-file wp-content/prihod-server-check.php --user=prihod-owner
```

Проверка статьи выполняется только чтением реальных новостей и проповедей;
публикации, оплаты и отправка сообщений не выполняются.
