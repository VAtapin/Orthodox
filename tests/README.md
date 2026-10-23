# Проверки

## Объединённый «Приход» 0.2.10

`parish-merge-blueprint.json` проверяет: один каталог мест и меню,
сохранность прежних ID/типов/служб/духовенства, совместимость демо-мест,
изображения из настоящей медиатеки без поддержки миниатюр в теме, валидация,
права/nonce, публичный вывод и фиксированный API. Проверяется классический
собственные формы без стандартного редактора, безопасное сохранение описания, контактов и нескольких связей мест, а также порядок регистрации страницы настроек. Дополнительно проверено
использование рекомендаций цветов облачений из Bible Desktop `day.vestment`, включая
маппинг `violet` в палитру расписания и отсутствие локального вывода цвета без поля API.
Нужны только mounts Core и tests:

```powershell
npx.cmd --yes @wp-playground/cli@3.1.57 run-blueprint --mount-dir ./georg-kloster-prihod-core /wordpress/wp-content/plugins/georg-kloster-prihod-core --mount-dir ./tests /wordpress/wp-content/plugins/prihod-tests --blueprint tests/parish-merge-blueprint.json
```

Для проверки ZIP распаковать `georg-kloster-prihod-core-0.2.10.zip` и монтировать
его каталог вместо исходника. Для минимальной платформы использовать копию
Blueprint с WP6.5.13/PHP8.0. Результат: `tests/.parish-merge-result.json`.

`schedule-admin-validation.php` входит в этот сценарий: настоящая запись через
`wp_update_post`, запрет частичной публикации без места, сохранность прежней
записи/расписания/редакций, исправление места, другие ошибки и русские сообщения.
`node tests/schedule-admin-validation.js` проверяет остановку нажатия
«Опубликовать»/«Сохранить» и отправки с клавиатуры до обработчиков WordPress,
раскрытие/фокус поля, выбор места и свободную работу остальных кнопок.

`parish-upgrade-blueprint.json` требует старые ZIP Core 0.1.5 и Schedule 0.1.10,
распакованные отдельно в `build/parish-upgrade-old`. Монтировать их под прежними
именами плагинов, новый Core — в `/next-parish`, tests — в `prihod-tests`.
Сценарий копирует новый пакет поверх старого **только в одноразовом WordPress**,
проверяет отключение Schedule и переход на следующий запрос без redeclare.
Перед повтором распаковать старые ZIP заново: тест заменяет их в смонтированной копии.

## Импорт демо (проверен в «Приход» 0.2.0 / теме 0.4.35)

`demo-import-blueprint.json` запускается только в одноразовом WordPress с
монтированием темы, Core и `tests` в `prihod-tests` (Schedule встроен):

```powershell
npx.cmd --yes @wp-playground/cli@3.1.57 run-blueprint --mount-dir ./georg-kloster-prihod /wordpress/wp-content/themes/georg-kloster-prihod --mount-dir ./georg-kloster-prihod-core /wordpress/wp-content/plugins/georg-kloster-prihod-core --mount-dir ./tests /wordpress/wp-content/plugins/prihod-tests --blueprint tests/demo-import-blueprint.json
```

54 проверки прошли на WP7.1.3/PHP8.4.25 и WP6.5.13/PHP8.0.30: права, nonce,
явное согласие, конкурентный запуск, настоящий сбой копирования медиа и
продолжение, отсутствие дублей, сохранность редакций/корзины/главной/меню,
заполнение только пустых настроек и три настоящие записи Schedule по выбору.
Результат — `tests/.demo-import-result.json`. Для минимальной платформы
использовать копию Blueprint с `wp: 6.5`, `php: 8.0`.

`demo-standalone-blueprint.json` проверяет английский набор на WP6.5/PHP8.0
без темы «Приход» и дополнительных плагинов, затем отключает Core: обычные блоки сохраняются.
Ему нужны только mount Core и `tests` из команды выше. HTTP/email запрещены
обоими сценариями. На настоящем сайте эти fixtures не запускать.

В браузере импорт русского набора проверен на одноразовом WP7.1/PHP8.4:
предпросмотр, три изначально снятых флажка, фактический импорт, пять оформлений
на 1425/390 px без переполнения и сохранность после отключения Core. Фотографии
карточек имеют высоту 200 px. На новом сайте проверяется только кнопка и
предпросмотр; импорт не запускался, поля темы и текст главной до/после совпали.

## Connect Hub 0.4.2

`connect-hub-blueprint.json` и `connect-hub-automation.php` запускают Connect Hub
на временном WordPress и проверяют формы, права и nonce, несколько раздельных
получателей Telegram для личных обращений, подключение общего webhook, подписку
бота через `/start`, отписку `/stop`, выбор RU/DE, настраиваемые системные
команды, пользовательские команды и автоответы по точным фразам на двух языках,
интерактивное меню `/menu`, языковые версии публикаций,
кампании с отложенной пакетной доставкой, автоматические публикационные правила,
напоминания с отдельными текстами на русском и немецком, `/today` и `/week`,
ежедневную сводку, уведомления об изменении расписания, журнал и управление
подписчиками/кампаниями. Telegram API и email перехватываются в тестовом процессе;
реальные сообщения не отправляются. Справка интерфейса проверяется в EN/RU/DE.
Отдельная документация по Telegram находится в
`georg-kloster-connect-hub/docs/TELEGRAM_BOT.md`; инструкции настройки каналов —
в соответствующих разделах интерфейса и `docs/CHANNEL_SETUP_RU.md`.

```powershell
python scripts/compile-translations.py --only connect-hub
python tests/plugin-i18n-check.py --only georg-kloster-connect-hub
python scripts/package.py --only connect-hub
npx.cmd --yes @wp-playground/cli@3.1.57 run-blueprint --mount-dir ./georg-kloster-connect-hub /wordpress/wp-content/plugins/georg-kloster-connect-hub --mount-dir ./tests /wordpress/wp-content/plugins/prihod-tests --blueprint tests/connect-hub-blueprint.json
```

Для проверки именно ZIP распаковать пакет в отдельный каталог и монтировать
его вместо исходников. `connect-hub-upgrade-blueprint.json` дополнительно
проверяет настоящую активацию поверх прежнего Social 0.1.3: нужен его старый
ZIP, распакованный в `build/connect-legacy`, и mount
`./build/connect-legacy/georg-kloster-prihod-social` в
`/wordpress/wp-content/plugins/georg-kloster-prihod-social`.
Окончательный ZIP (40 файлов) прошёл по 147 проверок на WP7.1.3/PHP8.4.25 и
WP6.5.13/PHP8.0.30 самостоятельно.
Результат — `tests/.connect-hub-result.json`.
858 строк RU/DE проверены на покрытие и соответствие PO/MO/JSON-каталогов.
Браузерная QA недоступна: локальный адрес блокируется встроенным браузером.
Реальные социальные аккаунты этим тестом не проверяются.

## Галерея главной

`theme-gallery-blueprint.json` создаёт в одноразовом WordPress пять настоящих
блоков Image внутри Gallery/Group с заголовком и ссылкой, без плагинов. Десять
проверок подтверждают сохранность галереи и содержимого страницы во всех пяти
схемах. Для браузерной проверки запустить официальный Playground CLI в режиме
`server` с монтированием темы и `tests` и этим Blueprint; открыть главную с
`?prihod_preview=blue` и остальными четырьмя схемами. Проверить на 1425 и 390 px:
галерея занимает ширину родителя, изображения имеют высоту 200 px, на телефоне
две колонки, нет горизонтального переполнения. Ссылку и штатный просмотр фото
дополнительно проверить на настоящих изображениях нового сайта. Для минимальной
платформы использовать копию Blueprint с WordPress 6.5 и PHP 8.0.

## Рекомендации плагинов в теме

`theme-plugins-blueprint.json` проверяет раздел дополнительных плагинов в
одноразовом WordPress, без скачивания плагинов или внешних HTTP-запросов.
Проверяются установленные/активные/отсутствующие модули, подтверждённая запись
каталога и ошибки API, штатные URL установки/активации и nonce, совместимость
WordPress/PHP, права редактора темы, отдельная форма проверки и сохранность
страниц, настроек и списка активных плагинов. Скриншоты сняты с настоящих
экранов трёх самостоятельных плагинов с пустыми настройками.

```powershell
npx.cmd --yes @wp-playground/cli@3.1.57 run-blueprint --mount-dir ./georg-kloster-prihod /wordpress/wp-content/themes/georg-kloster-prihod --mount-dir ./georg-kloster-prihod-core /wordpress/wp-content/plugins/georg-kloster-prihod-core --mount-dir ./tests /wordpress/wp-content/plugins/prihod-tests --blueprint tests/theme-plugins-blueprint.json
```

Результат — `tests/.theme-plugins-result.json`. Для минимальной платформы
использовать копию Blueprint с `wp: 6.5`, `php: 8.0`. Проверка успешной
установки опубликованного плагина через каталог остаётся отдельной проверкой
после публикации: API WordPress.org сейчас возвращает `Plugin not found` для
всех трёх slug. Подставной пакет за готовую установку не выдаётся.

## Английские исходники и локализация текущих плагинов

«Приход» 0.2.5 и Connect Hub 0.3.3 используют английские gettext-ключи.
`plugin-i18n-check.py` запрещает кириллицу в PHP/JS/CSS обоих пакетов, сверяет
PO/MO/JSON, покрытие и placeholders. 887 сообщений: Core 92, Schedule 195,
Connect Hub по 300 в RU и DE. Демо и многоязычные входные шаблоны календаря —
отдельные JSON-данные, не UI-строки.

```powershell
python scripts/compile-translations.py --only plugins
python tests/plugin-i18n-check.py
npx.cmd --yes @wp-playground/cli@3.1.57 run-blueprint --mount-dir ./georg-kloster-prihod-core /wordpress/wp-content/plugins/georg-kloster-prihod-core --mount-dir ./georg-kloster-connect-hub /wordpress/wp-content/plugins/georg-kloster-connect-hub --mount-dir ./tests /wordpress/wp-content/plugins/prihod-tests --blueprint tests/plugin-i18n-blueprint.json
```

129 проверок прошли на WP7.1.3/PHP8.4.25 и WP6.5.13/PHP8.0.30. Проверены
RU-меню/настройки/блоки, английские экраны без русского UI, справка по всем
девяти зарегистрированным шорткодам (включая совместимое имя формы), параметры
и размещение справки в четырёх экранах. Переключение языка сохраняет настройки
и содержимое владельца. Отчёт — `tests/.plugin-i18n-result.json`.
Для минимальной платформы использовать копию Blueprint с WP6.5/PHP8.0.
Для проверки ZIP монтировать распакованные пакеты вместо исходников.

## Самостоятельная тема и управление главной, версия 0.4.30

Сценарий `home-settings-blueprint.json` запускает standalone theme-release и
45 проверок gettext, JSON-контекстов и экранирования, затем
102 проверки настроек в одноразовой базе: без активных плагинов, затем с отдельным Schedule.
Он создаёт только искусственные материалы и не предназначен для живого сайта.

```powershell
npx.cmd --yes @wp-playground/cli@3.1.57 run-blueprint --mount-dir ./georg-kloster-prihod /wordpress/wp-content/themes/georg-kloster-prihod --mount-dir ./georg-kloster-prihod-core /wordpress/wp-content/plugins/georg-kloster-prihod-core --mount-dir ./tests /wordpress/wp-content/plugins/prihod-tests --blueprint tests/home-settings-blueprint.json
```

Отчёт — `tests/.home-settings-result.json`. Для минимальной платформы в копии
Blueprint указать `wp: 6.5`, `php: 8.0`. Прошли обе конфигурации:
WP7.1.3/PHP8.4.25 и WP6.5.13/PHP8.0.30. Тест подтверждает, что сохранение
параметров не меняет содержимое страниц/записей; проверяет собственные данные
и контакты темы, общие привязки страниц, порядок, скрытие,
блоки новостей, перевод собственных подписей и пять вариантов размещения служб.
Проверено отсутствие готовых текстов в настройках по умолчанию, сохранение
собственных приветствий/надзаголовков и очистка без возврата подставных текстов
во всех пяти оформлениях. Каталоги темы содержат только используемые UI-строки.

Для исходников и каталогов темы отдельно запускать:

```powershell
python scripts/compile-translations.py --only theme
python tests/translation-check.py
python tests/theme-i18n-check.py
```

Тема использует английские gettext msgid; RU/DE/PL/UK находятся в PO/MO.
`theme-i18n-check.py` запрещает кириллицу в исходниках вне файлов перевода,
подавления PHPCS и расхождения PO/MO; проверяет все 204 используемые строки,
контексты theme.json и размеры полного Classic preview. На 0.4.30 все эти
проверки прошли; 45 + 102 проверки и standalone release прошли на обеих
указанных выше платформах. Страницы и настройки живого сайта в тестах не меняются.

Core rc.14: настоящий приоритет календарной иконы, местный воскресный/будничный
fallback, Settings API и выбранное изображение/alt, отключение и неверные ID;
кэш, обычный золотой/попразднство/множественные сокращения, ручное исключение,
месячная сетка без повторной даты и полная мобильная карточка.
WP7.1.3/PHP8.4.25 —330/206; WP6.5.13/PHP8.0.30 —309/205;
62 unit, 2424 translated UI messages; PHP/JS syntax passed.
Локальные browser settings/media save/reset и 390px Sunday image проверены.
Core rc.14 установлен с разрешения;85серверных проверок прошли наWP7.1.3/PHP8.5.11.
Живой месяц1280:10служб/10цветов/10икон,4из источника/6запасных, без повторной
даты/переполнения;390px воскресный образ/золотой проверены. Настройки сохранены.

Core rc.13 / Telegram0.1.3: немедленный PHP wp_mail, отказ/немедленный ручной повтор,
очистка временного envelope hook, HTML Telegram/абзацы/Unicode, отдельный пункт
расписания и public target=_blank. Цвет auto по фактической дате/рангам, ручной
нейтральный override и скрытый пустой changes-card. Все HTTP/email перехвачены.
WP7.1.3/PHP8.4.25 —320/206; WP6.5.13/PHP8.0.30 —300/205;62unit,
2394 UI translations, PHP/JS syntax passed. 5desktop1280 без пересечения текста;
локально проверены настоящие иконы и открытие public страницы в новой вкладке.


Core rc.12 / Telegram0.1.2: telegram-requests-smoke.php использует настоящий
коннектор, REST, приватную историю и почтовую очередь с перехватом всех HTTP/email.
Проверяет обязательный Chat ID и сохранение прежних настроек при ошибке, соседние
старые подключения, чужой webhook, фоновую регистрацию, полный Unicode-вопрос
по частям, связь ответа с обращением, секрет/отправителя/чат/бота, повтор update,
HTML-письмо/escaping, внутренние заметки, смену token и очистку данных обращения.
WP7.1.3/PHP8.4.25 —304/206;WP6.5.13/PHP8.0.30 —284/205;62unit,
2391переводных сообщений, PHP/JS syntax прошли. Реальные Telegram/SMTP не проверены.
Локальные снимки настроек и письма на desktop/390px — verification-2026-10-12/telegram-*.
Тестовый образец письма содержит только вымышленные данные и исключён из Git.

Core rc.11: дата-цвет не изменяет время/повторения и не считается переносом;
праздник после переноса запрашивается по фактической дате, независимо от opt-in
иконы.62unit;WP7.1.3/PHP8.4.25 —280/206;WP6.5.13/PHP8.0.30 —260/205.
UI-проверки/ограничения — docs/CALENDAR_FEATURES.md.

Core rc.10: calendar-smoke.php проверяет подсказки через настоящий orthocal с
записанными публичными ответами, права REST, иконы фактических дат, выбранный
мобильный день, DST, opt-in, отказ/повтор письма и отсутствие автоматических служб.
Письма перехвачены только в одноразовой базе.276 assertions текущей платформы,
256 минимальной; детали реального браузерного API — docs/CALENDAR_FEATURES.md.

Core rc.9: wizard-interview-smoke.php проверяет настоящие шесть этапов, перенос
прежнего интервью, Назад/пропуск/восстановление, условные поля, запрет применения
без подтверждения и выбора темы без прав. admin-navigation-smoke.php проверяет
изоляцию оформления собственных страниц от WordPress и внешнего календаря.
WP7.1.3/PHP8.4.25 — 253 assertions; WP6.5.13/PHP8.0.30 — 242; design191.
PHP syntax/57 unit/2034 переводных сообщений прошли. Браузерная проверка 23 экранов
на двух размерах и шести этапов — docs/MASTER_ADMIN_RC9.md. На сервере rc.9 установлен:
67 проверок прошли с темой0.3.17; rc.10 ещё не установлен.

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

### Просмотр икон rc.15 / тема 0.3.33

336 smoke / 210 design на WP7.1.3/PHP8.4.25; 310 / 209 на WP6.5.13/PHP8.0.30.
Проверены native Image metadata обеих версий, opt-in permission full-size endpoint,
signed orthocal media без thumbnail, отдельно full/medium выбранных attachment.
Никаких реальных отправок; интеграционные fixtures одноразовые.


### Регрессия ширины «О монастыре», тема0.3.34

Через CUA при одинаковых viewport1920×1080/1122×998/390×844 сравнить about и
расписание для каждой схемы: main/header/footer, ширину/позицию, overflow,
четыре реальных года/описания, одинаковую ширину этапов и sidebar270px на
настольных экранах, вертикальную последовательность на мобильном. Отдельно
сохранить1440px в настройках одноразового локального WordPress, измерить снова
и восстановить1200px. Нельзя выполнять изменение настройки на production.
Readonly DOM-метрики сохранены в JSON; проверка фактических результатов:

```powershell
python tests/about-width-check.py docs/design-reference/verification-2026-10-13/about-width-0324/metrics.json
```

Прошли16layoutcases. Документированный полный порядок WordPress smoke →
standalone → monastery design прошёл310/210 на WP7.1.3/PHP8.4.25. Monastery
fixture нельзя запускать отдельно до активации/инициализации Core: её native
блоки требуют подготовленной среды. Снимки about для всех15сочетаний сохранены.
