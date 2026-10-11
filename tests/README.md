# Проверки

## Локальные

`php tests/lint.php` и `php tests/run.php` — синтаксис и изолированные unit-тесты.
Подстановки функций WordPress находятся только в tests/bootstrap.php и не входят
в устанавливаемый плагин. `composer check` вызывает те же проверки, если Composer
доступен в окружении; зависимостей нет.

## WordPress

Одноразовое окружение официального WordPress Playground, с реальной базой SQLite.
Запуск из корня репозитория (Node.js 22 для инструмента проверки, не для плагина):

```powershell
npx.cmd --yes @wp-playground/cli@latest run-blueprint --mount-dir ./prihod /wordpress/wp-content/plugins/prihod --mount-dir ./prihod-theme /wordpress/wp-content/themes/prihod-theme --mount-dir ./tests /wordpress/wp-content/plugins/prihod-tests --blueprint tests/blueprint.json
```

На Linux использовать `npx` вместо `npx.cmd`. Версии задаются в `preferredVersions`
Blueprint: базовая проверка — WordPress 6.5 / PHP 8.4. Для текущего WordPress
указать `wp: latest`, для минимального PHP — `php: 8.0` в копии Blueprint.
Фактически запущенные версии проверять по отчёту: CLI flags могут уступать Blueprint.
Требуется сеть для загрузки runtime. CLI должен завершиться с кодом 0; отчёт
`tests/.wordpress-result.json` содержит `WORDPRESS_SMOKE_OK`, версии и число проверок.
CLI может не отображать успешный PHP stdout. Отчёт не включается в Git и обновляется
при успешном запуске. Сценарий изменяет только одноразовую базу Playground. Не запускать его в
существующем сайте или production.

Проверяются активация, выдача capabilities, реальная WordPress option sanitization,
создание и сохранение записей, связи, отказ редактору и допуск сотруднику с
назначенным capability, отображение формы, сохранность при деактивации/uninstall.
Проверки SQLite не заменяют будущую проверку MySQL/MariaDB в Plesk и браузерную QA.

Проверяются также безопасный мастер и тема. Второй PHP request проверяет standalone
render темы при неактивном Core; результат — `tests/.theme-result.json`.

Источник инструмента: https://developer.wordpress.org/playground/developers/local-development/wp-playground-cli/.
