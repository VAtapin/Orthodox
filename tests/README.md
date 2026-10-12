# Проверки

Core rc.8: admin-overview-smoke.php добавляет13проверок реальных состояний страниц,
пустого/отфильтрованного списка, записи, неверного фильтра/страницы и отказа редактору.
Входит в wordpress-smoke.php; фикстуры только в одноразовой базе, писем не создают.
Также проверяется доступ сотрудника только к своей карточке обращений.
Проверено Playground CLI3.1.57: WP7.1.3/PHP8.4.25 —219assertions;
WP6.5.13/PHP8.0.30 —208; design191. Для воспроизводимого запуска использовать
`@wp-playground/cli@3.1.57` вместоlatest: 12октябряlatest3.1.58 не разрешил
зависимостьcommon3.1.58. Установленная3.1.57 выполнила оба сценария с exit0.

## Локальные

`php tests/lint.php` и `php tests/run.php` — синтаксис и изолированные unit-тесты.
`python tests/translation-check.py` проверяет покрытие немецких, польских и
украинских UI-строк и сохранность placeholders; переводы собираются командой
`python scripts/compile-translations.py`. Синтаксис frontend проверяется через
`node --check` для prihod/assets/blocks.js и prihod-theme/assets/{editor,media,forms}.js.
Подстановки функций WordPress находятся только в tests/bootstrap.php и не входят
в устанавливаемый плагин. `composer check` вызывает те же проверки, если Composer
доступен в окружении; зависимостей нет.

Также проверять `node --check prihod-theme/assets/settings.js` и
`node --check prihod/assets/request-form.js`. requests-smoke.php проверяет
выбранную/свою тему через существующий submit handler в одноразовой БД, без писем.
theme-options-smoke.php проверяет сохранение пяти схем, ограничения настроек,
защищённую панель, импорт/повторный импорт, ручные правки, корзину и права.
Standalone тема проверяет отдельно создание демо, назначение главной и навигацию.

## WordPress

Одноразовое окружение официального WordPress Playground, с реальной базой SQLite.
Запуск из корня репозитория (Node.js 22 для инструмента проверки, не для плагина):

```powershell
python scripts/package.py
npx.cmd --yes @wp-playground/cli@latest run-blueprint --mount-dir ./prihod /wordpress/wp-content/plugins/prihod --mount-dir ./prihod-theme /wordpress/wp-content/themes/prihod-theme --mount-dir ./build/packages/prihod-telegram /wordpress/wp-content/plugins/prihod-telegram --mount-dir ./build/packages/prihod-whatsapp /wordpress/wp-content/plugins/prihod-whatsapp --mount-dir ./build/packages/prihod-social /wordpress/wp-content/plugins/prihod-social --mount-dir ./tests /wordpress/wp-content/plugins/prihod-tests --mount-dir ./scripts /wordpress/wp-content/prihod-demo-source --blueprint tests/blueprint.json
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

admin-navigation-smoke.php проверяет общий возврат к обзору, раздел службы и сгруппированную форму.
monastery-demo-smoke.php запускает отдельный одноразовый сценарий: 13 страниц,
6 публикаций, 8 изображений, повтор без дубликатов и сохранение ручной правки.
Маленькие изображения — только локальные fixtures; на сайте импортированы настоящие
файлы владельца через медиатеку WordPress. Импортер нельзя запускать без запроса
владельца; scripts/monastery-content.json не входит в универсальные ZIP.


catalog-smoke.php — native Query Loop каталога: поиск/категория/пагинация, 15
временных публикаций только в одноразовой БД, private/draft исключены, обе схемы
ссылок, ручной текст и восстановление исходных данных. Входит в design-smoke.php.
На живом сайте этот fixture не запускать: server-check.php проверяет настоящие
публикации только отображением, без создания или редактирования материалов.

clergy-design-smoke.php проверяет настоящий зарегистрированный блок духовенства,
единственную биографию, соответствие портрета, ручной текст, иной шаблон,
несколько публичных записей и черновик. Временные изменения записей — только
в одноразовом fixture; возвращаются после проверки. На живом сайте не запускать.

pilgrim-design-smoke.php — 24 проверки штатного выбора block template, порядка
областей эталона 11, четырёх действительных якорей, настоящих фото/подписей,
всех исходных секций, контакта/карты, preview пяти схем, выбора/валидации медиа,
отсутствия ресурсов, ручной редакции и собственного Site Editor template.
Изменения страниц/настроек и временный template post — только в одноразовой БД,
после сценария возвращаются. На живом сайте этот fixture не запускать.

contacts-design-smoke.php: 14 проверок нативной иерархии шаблонов контактов, настоящих
tel/mailto/website, сохранности всех дорожных текстов/источника/фото, общей карты,
пяти preview-переходов и приоритета ручного текста/шаблона/Site Editor. Все временные
изменения выполняются исключительно в одноразовой БД и восстанавливаются.

home-detail-smoke.php: 11 проверок независимых владельческих цитат/источников и
девизов, пустых значений, sanitization/частичных старых форм, приоритета выбранных
фото над символами tradition и обработки настоящего портрета. Временные параметры
восстанавливаются. CSS geometry/лицо/мобильный overflow отдельно проверены браузером.
