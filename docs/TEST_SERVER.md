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

Установлены по прямому разрешению владельца: Core 1.0.0-rc.7, тема «Приход» 0.3.16,
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
php -d memory_limit=512M /usr/local/bin/wp plugin install wp-content/prihod-packages/prihod-1.0.0-rc.7.zip --force --activate && \
php -d memory_limit=512M /usr/local/bin/wp theme install wp-content/prihod-packages/prihod-theme-0.3.16.zip --force --activate && \
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
php -d memory_limit=512M /usr/local/bin/wp plugin install wp-content/prihod-packages/prihod-1.0.0-rc.7.zip --force --activate && \
php -d memory_limit=512M /usr/local/bin/wp theme install wp-content/prihod-packages/prihod-theme-0.3.16.zip --force --activate && \
php -d memory_limit=512M /usr/local/bin/wp eval-file wp-content/prihod-packages/apply-reference-design.php --user=prihod-owner && \
php -d memory_limit=512M /usr/local/bin/wp eval-file wp-content/prihod-server-check.php --user=prihod-owner
```

Все перечисленные файлы уже загружены и эта сборка применена 12.10.2026.
Новых серверных настроек, Node/Composer, migrations или блокировок команда не содержит.
Отчёт и ограничения точности: DESIGN_CONFORMITY.md.

Текущая сборка Core rc.7 / тема 0.3.15 уже применена: два верхних блока
публичного расписания, отметки мини-месяца и административные колонки.
Для повторного обновления использовать первую команду выше. Данные служб
и статусы опубликованных страниц не менялись. Проверка включает реальные
даты таблицы, две карточки расписания и сегодняшнюю/выбранную дату.

## Только обновление темы, 0.3.15

Пакет загружен и применён. Core rc.7 и внешние плагины не обновлялись.

```bash
cd /var/www/vhosts/prihod.georg-kloster.ru/httpdocs && \
export PATH="/opt/plesk/php/8.5/bin:$PATH" && \
php -d memory_limit=512M /usr/local/bin/wp theme install wp-content/prihod-packages/prihod-theme-0.3.16.zip --force --activate && \
php -d memory_limit=512M /usr/local/bin/wp eval-file wp-content/prihod-server-check.php --user=prihod-owner
```

Проверка статьи выполняется только чтением реальных новостей и проповедей;
публикации, оплаты и отправка сообщений не выполняются.

В0.3.8 исправлен итог записки/пожертвования. Он проверен локально без отправки
на двух видах поминовения и двух штатных способах оплаты; на живом WordPress
подтверждены обновлённый forms.js, его конфигурация и44 read-only проверки.

Тема 0.3.9 уже применена: отдельные шапки поддержки, три области контактов
hospitality/editorial, оформление editorial и поля цитаты. Только обновление
темы — последняя команда выше. Core rc.7 и внешние плагины не обновлялись;
44 read-only проверки и браузерные переходы CTA пройдены.

Тема 0.3.10 уже применена: исправлена мобильная читаемость hospitality hero;
390/430/1122px и44 read-only проверки пройдены. Плагины и серверные настройки
не менялись. Для повторного обновления темы использовать последнюю команду выше.


Тема 0.3.11 применена: композиция страницы обители по эталону 06, рабочие якоря,
настоящие фотообласти и нижнее приглашение. Контент/настройки владельца не сохранялись.
Повторная живая проверка: 48 checks, WP 7.1.3/PHP 8.5.11. Только тема, без Core
или внешних подключений. Команда раздела «Только обновление темы» уже актуальна.


Core rc.7 / тема 0.3.12 применены: собственная форма вопроса и её фото-полоса,
темы/обязательные поля/компактная боковая колонка. 51 проверка WP 7.1.3/PHP 8.5.11.
Старые обращения, текст страницы и настройки владельца не перезаписывались.
Пакеты находятся в wp-content/prihod-packages. Для этого блока нужны оба пакета:
использовать первую команду (Core + тема + проверка), без повторного импорта контента.
Внешние плагины/подключения, PHP/Plesk и другие сайты не менялись.


Тема 0.3.13 применена: полноширинные каталоги и нативные поиск/рубрики/пагинация.
54 проверки WP 7.1.3/PHP 8.5.11. При проверке не создавались публикации и не менялись
тексты/категории или permalink settings живого сайта. Для этого блока нужна только
команда «Только обновление темы»; Core rc.7 и внешние плагины не обновлялись.


Тема 0.3.14 применена: компактное духовенство без точного повтора биографии.
56 проверок WP 7.1.3/PHP 8.5.11. Исходный текст, каталог духовенства и настройки
не сохранялись. Для повторного применения — команда «Только обновление темы».
Core rc.7, внешние плагины и окружение общего сервера не обновлялись.


Тема 0.3.15 применена: страница «Паломнику» по эталону 11. 59 проверок
WP 7.1.3/PHP 8.5.11; пять схем при 1122/390 и настоящие переходы проверены.
Сохранены содержание и выбранный в БД шаблон, настройки фото/карты не сохранялись.
Применяется только тема: команда «Только обновление темы». Core rc.7, внешние
плагины и общий сервер не обновлялись; импорт/публикация/отправка не выполнялись.

## Установленные контакты 0.3.16

12 октября после прямого разрешения владельца загружены build/prihod-theme-0.3.16.zip
и tests/server-check.php в существующие wp-content/prihod-packages и
wp-content/prihod-server-check.php. Выполнено только обновление активной темы.
WordPress 7.1.3/PHP 8.5.11: 62 проверки прошли, включая контакты/дорогу,
настоящие tel/mailto и существующую карту. Пять схем проверены в браузере на 1122/390.
Подготовленные локальные файлы команд: build/sftp-contacts-reference.txt,
build/ssh-contacts-reference.sh. Последний содержит:

```bash
cd /var/www/vhosts/prihod.georg-kloster.ru/httpdocs && \
export PATH="/opt/plesk/php/8.5/bin:$PATH" && \
php -d memory_limit=512M /usr/local/bin/wp theme install wp-content/prihod-packages/prihod-theme-0.3.16.zip --force --activate && \
php -d memory_limit=512M /usr/local/bin/wp eval-file wp-content/prihod-server-check.php --user=prihod-owner
```

Проверка 0.3.16 завершилась CONTACTS_DESIGN_CHECK_OK: 62 total checks.
Никаких импортов, записей настроек, миграций, оплат/писем/публикаций или действий
вне этого WordPress команда не включает. Core и внешние плагины не обновляются.

## Подготовлена тема 0.3.17 — сервер пока 0.3.16

После прямого разрешения именно на новое обновление загрузить
build/prihod-theme-0.3.17.zip и build/server-check-theme-0317.php в прежний wp-content.
Этот замороженный checker ожидаетCore rc.7 и тему0.3.17; текущий tests/server-check.php
уже ожидаетCore rc.8 и не подходит для обновления только темы.
Подготовленные файлы команд — build/sftp-home-details.txt и build/ssh-home-details.sh.
Команда для этого WordPress, после загрузки архива/checker:

```bash
cd /var/www/vhosts/prihod.georg-kloster.ru/httpdocs && \
export PATH="/opt/plesk/php/8.5/bin:$PATH" && \
php -d memory_limit=512M /usr/local/bin/wp theme install wp-content/prihod-packages/prihod-theme-0.3.17.zip --force --activate && \
php -d memory_limit=512M /usr/local/bin/wp eval-file wp-content/prihod-server-check.php --user=prihod-owner
```

Core, существующие плагины, настройки и материалы не обновлять/не импортировать.
Серверная проверка новой версии ещё не запускалась; 62 проверки выше относятся к 0.3.16.

## Установлены Core rc.9 и тема 0.3.17 — 12 октября 2026

Владелец прямо разрешил обе версии. Установка выполнена только внутри указанного
WordPress; внешние плагины и окружение не менялись. Для повторного обновления
использовать уже загруженные build/prihod-1.0.0-rc.9.zip,
build/prihod-theme-0.3.17.zip в wp-content/prihod-packages, а подготовленный
tests/server-check.php — в wp-content/prihod-server-check.php этого WordPress.

```bash
cd /var/www/vhosts/prihod.georg-kloster.ru/httpdocs && \
export PATH="/opt/plesk/php/8.5/bin:$PATH" && \
php -d memory_limit=512M /usr/local/bin/wp plugin install wp-content/prihod-packages/prihod-1.0.0-rc.9.zip --force --activate && \
php -d memory_limit=512M /usr/local/bin/wp theme install wp-content/prihod-packages/prihod-theme-0.3.17.zip --force --activate && \
php -d memory_limit=512M /usr/local/bin/wp eval-file wp-content/prihod-server-check.php --user=prihod-owner
```

Подготовлены build/sftp-admin-rc9.txt и build/ssh-admin-rc9.sh; старый rc.8 пакет
и его проверка сохранены отдельно и не подменены rc.9. Все67read-only проверок прошли на WP7.1.3/PHP8.5.11.
Bitvise sexec принимает файл команды в одну строку: build/ssh-admin-rc9-one-line.sh.
Первоначальный checker требовал пять старых разовых служб; на сайте теперь одна
регулярная служба. Проверка исправлена на сравнение отображения с настоящими
датами/временем/timezone; записи не восстанавливались и не изменялись. Никаких импортов/публикаций материалов,
писем, оплат, изменений Plesk/окружения/других сайтов. Живой мастер не сбрасывать.
PHP8.5 — фактическое ранее подтверждённое окружение именно этого WordPress.
На сервере нет Git checkout; Composer и Node build для этих ZIP не требуются.

## Тема0.3.18 — подготовлено, пока не установлено

Сначала получить прямое разрешение владельца на0.3.18. Только после него загрузить
build/prihod-theme-0.3.18.zip в wp-content/prihod-packages и
build/server-check-theme-0318.php в wp-content/prihod-server-check.php того же сайта.
Проверка ожидает уже установленный Core rc.9, меняется только тема. Архив0.3.17 и
его ранее исполненный checker сохранены. Git checkout на сервере отсутствует.
После разрешённой загрузки команда для SSH/Plesk:

```bash
cd /var/www/vhosts/prihod.georg-kloster.ru/httpdocs && \
export PATH="/opt/plesk/php/8.5/bin:$PATH" && \
php -d memory_limit=512M /usr/local/bin/wp theme install wp-content/prihod-packages/prihod-theme-0.3.18.zip --force --activate && \
php -d memory_limit=512M /usr/local/bin/wp eval-file wp-content/prihod-server-check.php --user=prihod-owner
```

PHP8.5 — подтверждённый handler именно этого WordPress. Node/build/Composer и
изменения окружения не нужны. Затем фактически открыть страницу19 и проверить
фото/перелистывание/закрытие на desktop/mobile; программный checker не заменяет
браузерную проверку. Выполнение этой команды пока не разрешено и не заявляется.

## Core rc.10 — локально готов, установка не разрешена

Архив build/prihod-1.0.0-rc.10.zip и проверка build/server-check-calendar-rc10.php
подготовлены отдельно. При прямом разрешении загрузить только их в те же
wp-content/prihod-packages и wp-content/prihod-server-check.php. Тема не обновляется
этой командой: проверка совместима с уже установленной0.3.17 и отдельно разрешённой
0.3.18. На0.3.17 проверка явно оставляет просмотр фото в состоянии pending;
она не объявляет дефект фото19 устранённым.71/75 read-only checks подготовлены,
но ещё не исполнялись на сервере. Напоминание не включать и время не назначать.

После разрешённой загрузки команда для SSH/Plesk:

```bash
cd /var/www/vhosts/prihod.georg-kloster.ru/httpdocs && \
export PATH="/opt/plesk/php/8.5/bin:$PATH" && \
php -d memory_limit=512M /usr/local/bin/wp plugin install wp-content/prihod-packages/prihod-1.0.0-rc.10.zip --force --activate && \
php -d memory_limit=512M /usr/local/bin/wp eval-file wp-content/prihod-server-check.php --user=prihod-owner
```

Затем проверить редактор/икону/мобильный месяц иEurope/Berlin в этом WordPress.
Реальные письма/создание служб не запускать при приёмке. Plesk, другие сайты,
серверный cron, credentials и конфигурация PHP не меняются. Composer/Node build
не требуются. Календарные детали — docs/CALENDAR_FEATURES.md.
