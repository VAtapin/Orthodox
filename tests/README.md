# Проверки

## Локальные

`php tests/lint.php` и `php tests/run.php` — синтаксис и изолированные unit-тесты.
`python tests/translation-check.py` проверяет покрытие немецких, польских и
украинских UI-строк и сохранность placeholders; переводы собираются командой
`python scripts/compile-translations.py`. Синтаксис frontend проверяется через
`node --check` для prihod/assets/blocks.js и prihod-theme/assets/{editor,media,forms}.js.
Подстановки функций WordPress находятся только в tests/bootstrap.php и не входят
в устанавливаемый плагин. `composer check` вызывает те же проверки, если Composer
доступен в окружении; зависимостей нет.

Также проверять `node --check prihod-theme/assets/settings.js`.
theme-options-smoke.php проверяет сохранение пяти схем, ограничения настроек,
защищённую панель, импорт/повторный импорт, ручные правки, корзину и права.
Standalone тема проверяет отдельно создание демо, назначение главной и навигацию.

## WordPress

Одноразовое окружение официального WordPress Playground, с реальной базой SQLite.
Запуск из корня репозитория (Node.js 22 для инструмента проверки, не для плагина):

```powershell
python scripts/package.py
npx.cmd --yes @wp-playground/cli@latest run-blueprint --mount-dir ./prihod /wordpress/wp-content/plugins/prihod --mount-dir ./prihod-theme /wordpress/wp-content/themes/prihod-theme --mount-dir ./build/packages/prihod-telegram /wordpress/wp-content/plugins/prihod-telegram --mount-dir ./build/packages/prihod-whatsapp /wordpress/wp-content/plugins/prihod-whatsapp --mount-dir ./build/packages/prihod-social /wordpress/wp-content/plugins/prihod-social --mount-dir ./tests /wordpress/wp-content/plugins/prihod-tests --blueprint tests/blueprint.json
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

Команда включает три независимо собранных подключения.
Проверяется также их standalone работа; отчёт .connectors-result.json.
HTTP перехватывается, реальные сообщения/публикации не отправляются.

Условия веток тестирования существующих плагинов проверяют фактическое присутствие
их файлов и поддерживаемую версию платформы. Без дополнительных mounts эти ветки
не выполняются. Это не означает успешной проверки совместимости с живыми плагинами.
Владелец разрешил подключение только каталогов D:/Projekte/Kalendar/wordpress/orthocal
и D:/Projekte/Kloster-PAY/kloster-prayer; совместная проверка прошла на WordPress 7.1.3
и PHP 8.4.25. Для воспроизведения использовать копию Blueprint с preferredVersions
wp: latest / php: 8.4 и добавить к команде:
`--mount-dir D:/Projekte/Kalendar/wordpress/orthocal /wordpress/wp-content/plugins/orthocal`
и `--mount-dir D:/Projekte/Kloster-PAY/kloster-prayer /wordpress/wp-content/plugins/kloster-prayer`.
Сценарий блокирует все HTTP/email на участке существующих плагинов, выводит формы,
но не отправляет их. На WordPress 6.5 «Записки» 1.2.15 не активируются:
их минимум WordPress 7.1 и PHP 8.4. Соседние проекты не входят в CI и ZIP «Прихода».
После переименования соседнего проекта владелец разрешил актуальный каталог
D:/Projekte/Kloster-PAY/georg-kloster-orthodox-donate (версия 1.0.0).
Для текущей совместной проверки заменить mount старых «Записок» на этот каталог
и /wordpress/wp-content/plugins/georg-kloster-orthodox-donate. Проверка поддерживает
обе точки входа. Публичный API календаря не требует ключа.

Для браузерной QA существует tests/ui-blueprint.json и одноразовый ui-fixture.php.
В команде Playground заменить run-blueprint на server, Blueprint на ui-blueprint.json,
добавить --port=9400 и указанные mounts исходных плагинов. Это тестовая база с
искусственными материалами, не переносить fixture в существующий сайт.
ui-preview.php блокирует HTTP/email и позволяет сравнивать ?ui_scheme=classic,
hospitality,editorial,tradition,blue и ?ui_locale=ru_RU,de_DE,pl_PL,uk.
Проверять переходы формы без её отправки; это не тест оплаты. Завершить сервер
после проверки. Снимки находятся в docs/design-reference/verification.

tests/server-check.php — отдельная неразрушающая проверка установленного WordPress
на prihod.georg-kloster.ru. Запускается имеющимся WP-CLI через PHP 8.5 внутри
WordPress. Материалы не создаёт/не удаляет; проверяет отсутствие блокировки HTTP/email,
реальные шорткоды, тему и доступ к мастеру. Команды — docs/TEST_SERVER.md.

Актуальная совместная проверка использует выпущенный ZIP Orthodox Donations 1.0.2
из output соседнего проекта. Его содержимое распаковано в build/fixtures и подключено
как /wordpress/wp-content/plugins/georg-kloster-orthodox-donate, чтобы параллельные
изменения исходников не изменяли проверяемую версию. В продуктовые ZIP и Git
исходники соседнего плагина не входят. Дашборд проверяет права виджетов и настоящую
маршрутизацию скрытых форм внутри тематических разделов.
