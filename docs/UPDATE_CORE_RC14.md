# Обновление только Core rc.14

На сервере сейчас rc.13. Выполнять обновление только после прямого разрешения
владельца на rc.14. Тема 0.3.22, Telegram 0.1.3 и остальные плагины не обновляются.
PHP этого WordPress фактически 8.5.11; используется его явный Plesk binary.
Серверного Git checkout нет: `git pull` здесь неприменим.

Локальный пакет: `build/prihod-1.0.0-rc.14.zip`.
SHA256: `9314FE3A22D6903371A36E4B5534B205B2F8B44648AC3B38AC0BF0F89C110EAC`.
Через существующий Bitvise профиль `prihod.tlp` загрузить только этот ZIP в
`/var/www/vhosts/prihod.georg-kloster.ru/httpdocs/wp-content/prihod-packages/`
и `tests/server-check.php` в `wp-content/prihod-server-check.php` этого WordPress.
Эти два файла ещё не загружены; команда ниже предназначена для запуска после загрузки.

```bash
cd /var/www/vhosts/prihod.georg-kloster.ru/httpdocs && \
export PATH="/opt/plesk/php/8.5/bin:$PATH" && \
/opt/plesk/php/8.5/bin/php /usr/local/bin/wp plugin install wp-content/prihod-packages/prihod-1.0.0-rc.14.zip --force --activate && \
/opt/plesk/php/8.5/bin/php /usr/local/bin/wp eval-file wp-content/prihod-server-check.php --user=prihod-owner
```

Команда обновляет только установленный Core и запускает собственную проверку.
Настройки служб/медиа/подключений не переписываются. Дополнительные установки,
Composer/Node, миграции, изменения Plesk/почты и рестарты не нужны.
После этого проверяется настоящий сайт: включённые иконы в неделе/месяце,
автоматические цвета, ручное исключение и отдельные воскресный/будничный образы.
