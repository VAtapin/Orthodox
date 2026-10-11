# Тестовая площадка

Владелец разрешил установку WordPress 11.10.2026. Разрешение касается WordPress;
установка остальных компонентов требует отдельного прямого разрешения.

- Сайт: https://prihod.georg-kloster.ru/.
- Вход: https://prihod.georg-kloster.ru/wp-admin/.
- SSH: предоставленный владельцем профиль Bitvise, пользователь `prihod`.
- Каталог WordPress: `/var/www/vhosts/prihod.georg-kloster.ru/httpdocs`.
- PHP: `/opt/plesk/php/8.5/bin/php`, фактически 8.5.11.
- WordPress: 7.1.3, русский интерфейс, Europe/Berlin.
- Существующий WP-CLI Plesk: `/usr/local/bin/wp`, 2.12.0; запускать через PHP 8.5.
- MariaDB: 10.6.23; база `prihod`, отдельный предоставленный пользователь.
- Индексация поисковиками отключена (`blog_public=0`); это рекомендация роботам,
  а не ограничение доступа. Сайт доступен публично.

Пароли и профиль SSH не входят в репозиторий. Случайный пароль первого
администратора сохранён только на сервере вне webroot в закрытом файле
`/var/www/vhosts/prihod.georg-kloster.ru/wordpress-admin-access.txt` (600).
Логин: `prihod-owner`. Владелец может прочитать файл в своём SSH-сеансе:

```bash
cat /var/www/vhosts/prihod.georg-kloster.ru/wordpress-admin-access.txt
```

`wp-config.php` также имеет права 600. Установочное письмо не отправлялось.
Исходная страница Plesk и повреждённые артефакты первоначального распаковщика
сохранены в `wordpress-install-backup` вне webroot (каталог 700).
Из-за усечения длинных имён файлов распаковщиком WP-CLI использован уже
имеющийся системный `tar`; повторная проверка официальных контрольных сумм прошла.
Лимит памяти 512M задан только процессам установки, настройки PHP сервера не менялись.

Проверены: пустая база перед установкой, официальные контрольные суммы Core,
`core is-installed`, `db check` (12 таблиц OK), HTTPS главной и входа (200),
отображение главной в браузере. Скриншот: [установленный WordPress](test-server/wordpress-installed.jpg).

На сервере активна стандартная тема Twenty Twenty-Five. Только штатные Akismet и
Hello Dolly из официального дистрибутива, оба неактивны. «Приход», его тема,
подключения, календарь и «Записки» на сервер не устанавливались.
Проверка всей экосистемы на MariaDB/PHP 8.5 ещё не выполнена.

Репозиторий Git в webroot не создавался. Обновлять площадку командой `git pull`
сейчас нельзя. Для повторной проверки владельцем (без установки и обновления):

```bash
cd /var/www/vhosts/prihod.georg-kloster.ru/httpdocs && \
export PATH="/opt/plesk/php/8.5/bin:$PATH" && \
php -d memory_limit=512M /usr/local/bin/wp core verify-checksums --locale=ru_RU && \
php /usr/local/bin/wp db check
```
