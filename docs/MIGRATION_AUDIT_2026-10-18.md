# Сверка старого и нового сайтов — 18 октября 2026

## Вывод

Перенос неполный. Наличие записей в WordPress не гарантирует сохранность
содержимого: потеряны галереи, видеоблоки, часть текстов и некоторые ссылки.
Ниже перечислены подтверждённые расхождения. Во время аудита содержимое,
настройки и оформление обоих сайтов не изменялись.

## Объём и способ проверки

Источники: `georg-kloster.ru`, немецкая версия `georg-kloster.de`;
назначение: `new.georg-kloster.ru`, включая `/de/`.

| Проверка | Старый сайт | Новый сайт |
| --- | ---: | ---: |
| Опубликованные записи | 69 | 65 |
| Опубликованные страницы | 21 | 32 |
| Всего страниц и записей RU/DE | 90 | 97 |
| Рубрики | 18 | 19 |
| Метки | 21 | 21 |

88 исходных материалов сопоставлены по `_gkm_source_id`. Две главные страницы
заменены новыми главными RU/DE. Дополнительно созданы семь страниц: обращение
к священнику RU/DE, фотографии DE, паломнику RU/DE, проповеди RU/DE.
Четыре исходные записи («О монастыре» и расписание RU/DE) стали страницами;
этим объясняется уменьшение числа записей.

Проверены HTTP-ответы и публичный HTML **295 адресов**: все опубликованные
страницы и записи, все рубрики и метки, найденная пагинация, ссылки внутри
материалов, меню, четыре старых фотоальбома и пять адресов виртуальных туров
на каждом сайте. Обход найденных ссылок завершился без достижения лимита.
У 96 из 97 новых страниц/записей HTTP 200; у оставшейся — цикл редиректов.
Проверены HEAD-ответы 53 уникальных файлов галерей и локальных видео на новом
сервере: **все 53 — HTTP 200**.

Сравнение текста выполнено по содержимому WordPress после удаления обёрток
шорткодов/HTML, нормализации пробелов, регистра и URL. У 78 из 88 сопоставленных
материалов последовательность слов совпала. Эта метрика не проверяет галереи,
видео, ссылки и динамические блоки: их проверка вынесена отдельно.
Остальные десять: новости RU/DE, «О монастыре» RU/DE, расписание RU/DE,
фотографии RU, три ранее восстановленные главы Евангелия.

Публичные материалы не включают черновики, закрытые и защищённые паролем
записи. Отправка форм, платежи, email, пользовательские кабинеты и полная
работа приложений после выполнения JavaScript этим аудитом не проверялись.
Для примера страница «Монастырская кухня» также просмотрена в браузере:
текст есть, видеоплеера нет.

## 1. Потерянные галереи внутри статей

В 12 материалах удалены блоки `fusion_gallery` / `fusion_gallery_image`.
В них было **63 размещения фотографий**, включая повторения в переводах.
На новых страницах осталась только главная фотография. Некоторые из этих
главных фотографий совпадают с отдельными снимками прежней галереи;
это не означает, что сама галерея сохранилась.

Все 50 уникальных файлов этих галерей доступны на новом сервере. Причина
расхождения — удаление шорткодов с URL изображений при переносе, а не отсутствие
файлов. Для восстановления достаточно стандартных блоков галереи WordPress.

| ID старый → новый | Язык | Материал на старом сайте | Новый сайт | Фотографий в галерее |
| --- | --- | --- | --- | ---: |
| 2195 → 25 | ru | [Святой великомученик Георгий Победоносец](https://georg-kloster.ru/svyatoj-velikomuchenik-georgij-pobedonosec/) | [Открыть](https://new.georg-kloster.ru/svyatoj-velikomuchenik-georgij-pobedonosec/) | 4 |
| 2386 → 39 | ru | [Проект росписи Храма](https://georg-kloster.ru/proekt-rospisi-hrama/) | [Открыть](https://new.georg-kloster.ru/proekt-rospisi-hrama/) | 5 |
| 2803 → 47 | de | [Projekt zur Ausmalung der Kirche](https://georg-kloster.de/projekt-zur-ausmalung-der-kirche/) | [Открыть](https://new.georg-kloster.ru/de/projekt-zur-ausmalung-der-kirche/) | 5 |
| 2843 → 60 | de | [Heiliger Großmärtyrer Georg der Siegreiche](https://georg-kloster.de/heiliger-grossmaertyrer-georg-der-siegreiche/) | [Открыть](https://new.georg-kloster.ru/de/heiliger-grossmaertyrer-georg-der-siegreiche/) | 4 |
| 3138 → 75 | ru | [Отопление жилого помещения и храма](https://georg-kloster.ru/otoplenie-zhilogo-pomeshheniya-i-xrama/) | [Открыть](https://new.georg-kloster.ru/otoplenie-zhilogo-pomeshheniya-i-xrama/) | 10 |
| 3173 → 76 | ru | [Электрификация храма](https://georg-kloster.ru/svet-v-xrame/) | [Открыть](https://new.georg-kloster.ru/svet-v-xrame/) | 5 |
| 3200 → 77 | ru | [Пономарка](https://georg-kloster.ru/ponomarka-obnovlenie-i-vtoroj-uroven/) | [Открыть](https://new.georg-kloster.ru/ponomarka-obnovlenie-i-vtoroj-uroven/) | 9 |
| 3227 → 78 | ru | [Водоснабжение храма](https://georg-kloster.ru/vodosnabzhenie-xrama/) | [Открыть](https://new.georg-kloster.ru/vodosnabzhenie-xrama/) | 3 |
| 3236 → 79 | ru | [Уличное освещение](https://georg-kloster.ru/ulichnoe-osveshhenie/) | [Открыть](https://new.georg-kloster.ru/ulichnoe-osveshhenie/) | 3 |
| 3256 → 81 | ru | [Переход на экологичное отопление](https://georg-kloster.ru/perekhod-na-ekologichnoe-otoplenie/) | [Открыть](https://new.georg-kloster.ru/perekhod-na-ekologichnoe-otoplenie/) | 6 |
| 3271 → 82 | ru | [Выравнивание двора](https://georg-kloster.ru/vyravnivanie-dvora/) | [Открыть](https://new.georg-kloster.ru/vyravnivanie-dvora/) | 6 |
| 3391 → 91 | de | [Wasserversorgung der Kirche](https://georg-kloster.de/wasserversorgung-der-kirche/) | [Открыть](https://new.georg-kloster.ru/de/wasserversorgung-der-kirche/) | 3 |

## 2. Потерянные видео

Отсутствуют **10 размещений видео**:

- Шесть локальных MP4-блоков: «О монастыре», «Монастырская кухня», «Проект
  росписи Храма» и их немецкие переводы. Это три уникальных файла:
  `monastyr2.mp4`, `Монастырь-в-Гётшендорфе-480p.mp4`, `sergey.mp4`.
- Четыре YouTube-блока: «Мысли на пороге важного рубежа», «Праздник Святой
  Троицы…», «Идите за Мною, и Я сделаю вас ловцами человеков», «Что в твоей
  жизни занимает Моё место?».

Три главы Евангелия от Матфея уже имеют восстановленные YouTube-блоки.
Их не нужно восстанавливать повторно. У двух исходных статей ниже указан один
и тот же YouTube ID — это содержимое старого сайта, не ошибка сопоставления.

| ID старый → новый | Язык | Материал на старом сайте | Новый сайт | Видео |
| --- | --- | --- | --- | --- |
| 2189 → 23 | ru | [О монастыре](https://georg-kloster.ru/o-monastyre/) | [Открыть](https://new.georg-kloster.ru/o-monastyre/) | Локальный MP4 отсутствует |
| 2214 → 33 | ru | [Монастырская кухня](https://georg-kloster.ru/monastyrskaya-kuhnya/) | [Открыть](https://new.georg-kloster.ru/monastyrskaya-kuhnya/) | Локальный MP4 отсутствует |
| 2386 → 39 | ru | [Проект росписи Храма](https://georg-kloster.ru/proekt-rospisi-hrama/) | [Открыть](https://new.georg-kloster.ru/proekt-rospisi-hrama/) | Локальный MP4 отсутствует |
| 2803 → 47 | de | [Projekt zur Ausmalung der Kirche](https://georg-kloster.de/projekt-zur-ausmalung-der-kirche/) | [Открыть](https://new.georg-kloster.ru/de/projekt-zur-ausmalung-der-kirche/) | Локальный MP4 отсутствует |
| 2816 → 51 | de | [Klosterküche](https://georg-kloster.de/klosterkueche/) | [Открыть](https://new.georg-kloster.ru/de/klosterkueche/) | Локальный MP4 отсутствует |
| 2828 → 55 | de | [Über das Kloster](https://georg-kloster.de/ueber-das-kloster/) | [Открыть](https://new.georg-kloster.ru/de/ueber-das-kloster/) | Локальный MP4 отсутствует |
| 3542 → 108 | ru | [Мысли на пороге важного рубежа](https://georg-kloster.ru/na-poroge-vazhnogo-rubezha/) | [Открыть](https://new.georg-kloster.ru/na-poroge-vazhnogo-rubezha/) | YouTube `kQLkQ6N-vTk` отсутствует |
| 3548 → 109 | ru | [Праздник Святой Троицы в Свято-Георгиевском монастыре Гётчендорф](https://georg-kloster.ru/svjataja-troica-2026/) | [Открыть](https://new.georg-kloster.ru/svjataja-troica-2026/) | YouTube `kQLkQ6N-vTk` отсутствует |
| 3591 → 113 | ru | [Идите за Мною, и Я сделаю вас ловцами человеков](https://georg-kloster.ru/idi-za-mnoyu/) | [Открыть](https://new.georg-kloster.ru/idi-za-mnoyu/) | YouTube `KC704HAOmsQ` отсутствует |
| 3604 → 114 | ru | [Что в твоей жизни занимает Моё место?](https://georg-kloster.ru/chto-v-tvoey-zhizni-zanimaet-moe-mesto/) | [Открыть](https://new.georg-kloster.ru/chto-v-tvoey-zhizni-zanimaet-moe-mesto/) | YouTube `gfPZ3S8Wyd8` отсутствует |

## 3. Сокращённые и заменённые тексты

### Расписание RU/DE

На старом сайте прямо указано: **пятница, 15:00 — чтение акафистов**.
На новых страницах эта строка отсутствует. Правило нового Schedule содержит
только субботнюю и воскресную литургии. Также убрано описание служб в великие
праздники. Рекомендация приезжать за 15–20 минут и текст об общей трапезе
сохранились.

Нужно восстановить исходную информацию; перед созданием повторяющейся
пятничной службы проверить актуальность опубликованного расписания.

### «О монастыре» RU/DE

При новом оформлении исходный текст был переписан и сокращён: RU — примерно
3162 → 1622 символа, DE — 3833 → 1737 символов нормализованного текста.
Не сохранены полностью сведения о приобретении усадьбы за один евро с
обязательством реставрации, развёрнутые абзацы об игумене и жизни братии,
пристани на озере, ежедневной полевой кухне, общей трапезе, пасхальной выпечке
и духовном значении обители. Краткие сведения и ссылки на отдельные статьи
частично заменили исходные абзацы, но не сохраняют весь текст.

Есть также изменение факта: старый обзор относит известность поселения к
XV веку, новый — к XIV. Нельзя считать такую замену только оформлением;
датировку следует отдельно сверить с исторической статьёй/источником.

### Главные страницы RU/DE

Созданы заново; исходные большие тексты не перенесены целиком. Исчезли из
главной развёрнутые сведения о приёме организованных групп, расстоянии от
Берлина, духовных беседах, работе с молодёжью и семьями, восстановлении
зданий, поминовении благотворителей и онлайн-записках. Часть тем раскрыта в
других статьях, однако дословного переноса этих текстов на новые главные нет.
Восстановление допустимо распределить по соответствующим разделам,
сохранив доступность исходной информации.

### Фотографии

Все 28 фотографий трёх заполненных исходных альбомов присутствуют на новой
странице. Четвёртый альбом «Территория монастыря» пуст **также на старом сайте**
(исходная страница сообщает об отсутствии изображений). Это не потеря переноса.
Описания старых альбомов про закладку памятной капсулы, эскизы стен/свода и
работы в помещениях в новые разделы не перенесены; остались заголовки.

Новости RU/DE переработаны в каталог публикаций; убранные служебные подписи
Avada «Совет редактора», «Последние видео» не являются потерянными статьями.

## 4. Виртуальные туры отсутствуют

Все пять исходных адресов отвечают HTTP 200; соответствующие новые — 404:

| Раздел | Старый сайт | Новый сайт |
| --- | --- | --- |
| Выбор тура RU | [Открыть](https://georg-kloster.ru/360/) | [404](https://new.georg-kloster.ru/360/) |
| Выбор тура DE | [Открыть](https://georg-kloster.ru/360de/) | [404](https://new.georg-kloster.ru/360de/) |
| Территория | [Открыть](https://georg-kloster.ru/360/klostergelaende/) | [404](https://new.georg-kloster.ru/360/klostergelaende/) |
| Храм | [Открыть](https://georg-kloster.ru/360/klosterkirche/) | [404](https://new.georg-kloster.ru/360/klosterkirche/) |
| Усадебный дом | [Открыть](https://georg-kloster.ru/360/herrenhaus/) | [404](https://new.georg-kloster.ru/360/herrenhaus/) |

Это статические разделы вне списка записей WordPress. Их не охватил перенос
страниц/записей. В новых меню прямых ссылок на них тоже нет.

## 5. Немецкая «Календарная мастерская» не открывается

Исходная запись 3760 импортирована в опубликованную запись 118, но URL
[новой немецкой страницы](https://new.georg-kloster.ru/de/календарная-мастерская/)
зациклен в 301-перенаправлениях. Исходная немецкая страница отвечает 200.
Русская мастерская работает. Наличие немецкой записи в базе не означает
доступность материала. Нужно разобрать её canonical/WPML/permalink redirect.

## 6. Навигация и ссылки

- «Крещение», «Венчание», «Молебен», «Панихида», послушничество, братия,
  история Гётчендорфа и другие статьи есть на новом сайте. Однако обычное меню
  и его панель не воспроизводят прежние тематические подборки «Храм», «Требы»,
  «Проекты монастыря», «3D карта». Большинство статей приходится искать через
  новости, фильтр рубрики, поиск или связанные материалы.
- «Правовая информация» / Impressum перенесены с текстами, но на новой
  главной нет ссылки на них, в отличие от старой. Восстановить доступ из подвала.
- При удалении `fusion_button` потеряны кликабельные кнопки в «Уличном
  освещении» и «Календарной мастерской» RU/DE. В мастерской соседняя обычная
  ссылка на сервис остаётся; надпись «Создать календарь» стала простым текстом.
- Общий блок Avada 3287 с кнопкой пожертвования использовался в четырёх
  статьях проектов: укладка плитки на амвоне, экологичное отопление,
  выравнивание двора, санитарная комната. При удалении `fusion_global` он исчез.
  На главной новая отдельная карточка помощи есть; это не восстанавливает
  кнопки в этих четырёх статьях.
- Ссылок из основного текста новых материалов на старые домены не найдено.
  Это не проверка ссылок в произвольных настройках внешних плагинов.

## 7. Рубрики, метки и сохранившиеся разделы

Все 21 исходная метка сохранены. 17 из 18 исходных рубрик сохранены по slug;
немецкая служебная `Nicht kategorisiert` заменена распределением её двух
материалов. Добавлены `Nachrichten` и пустая `Uncategorized`.

Уменьшение числа записей в «Монастырь», «Новости», «Храм», Kirche и метке
«Видео» связано с превращением обзорных записей в страницы: обычные архивы
WordPress страницы не показывают. Это изменение способа доступа, не удаление
соответствующего материала. Связи остальных тематических рубрик и меток
сопоставленных статей сохранены; массовой новой потери категорий не выявлено.

Изображения записей (featured images) сохранены у всех сопоставленных
материалов, у которых они были в исходнике. Страницы Библии, церковного
календаря RU/DE и русской мастерской отвечают 200; контейнеры/содержимое
действующих приложений присутствуют. Они не были ошибочно признаны
неработающими только из-за наличия шорткода в редакторе.
На старом сайте нет одобренных комментариев (проверен счётчик WP-CLI);
потери опубликованных комментариев при переносе не выявлено.

## 8. Проблемы, существовавшие уже на старом сайте

Их нужно учитывать перед запуском нового сайта, но нельзя приписывать
исключительно этому переносу:

- `give_form` на страницах росписи RU/DE и Spenden DE выводится как текст
  шорткода и на старом, и на новом сайте.
- `simpay_payment_receipt` на подтверждении оплаты RU/DE и `give_receipt` на
  немецком подтверждении пожертвования также видны как текст на обоих сайтах.
- На старой русской странице пожертвований уже отображался необработанный
  `[kloster_form id="2"]`; новый импорт удалил его. Рабочая форма этим не была
  восстановлена. На немецкой странице настоящая форма `gkod_form` есть на
  обоих сайтах; отправка и платёж не выполнялись.
- Старые ссылки `/elektronnye-zapiski/` и немецкий путь
  `/cdn-cgi/l/email-protection` уже отвечают 404.

## 9. Объявления Popup Maker

Сверены пять опубликованных исходных всплывающих окон. На новом сайте все
пять их импортированных копий находятся в черновиках (ID 97, 98, 102, 107,
110); опубликован только отдельный демонстрационный popup. Содержимое
черновиков не экспортировалось, проверены их исходные ID и статус.

Три содержательных объявления дублируются обычными статьями: рождественское
богослужение, Богоявление, солнечные батареи. Объявление «Чтение Великого
покаянного канона св. Андрея Критского» (исходный ID 3531, новая копия 107)
не имеет опубликованного аналога: в нём было описание Великого канона и
начало службы с понедельника по четверг в 18:00. Это ещё один текст,
которого посетитель нового сайта не увидит. Его нужно сохранить как
доступный исторический материал; устаревшее сезонное объявление не следует
автоматически включать как текущее расписание.

## Рекомендуемый порядок исправлений

1. Вернуть галереи и десять видеоблоков стандартными блоками WordPress,
   сохраняя существующий текст и настройки страниц.
2. Восстановить сокращённые тексты RU/DE, описания альбомов и доступный текст
   объявления о Великом каноне; отдельно
   подтвердить актуальность пятничного акафиста и спорную историческую дату.
3. Перенести статические туры и устранить цикл редиректов немецкой мастерской.
4. Вернуть потерянные ссылки/кнопки и подготовить удобный доступ к практическим
   статьям и Impressum средствами существующего меню.
5. Отдельно привести к рабочему состоянию старые платёжные/записочные блоки.

Аудит завершён; перечисленные исправления этим отчётом не объявляются выполненными.

## Воспроизведение

`scripts/audit_site_inventory.php` запускается через WP-CLI отдельно в старом
и новом WordPress, читает только публичные записи/термины/меню и сохраняет
JSON в закрытом каталоге миграции. Никакие секреты, обращения пользователей,
таблицы платежей, cookies или дампы базы не экспортируются. PHP-процессу
старого Avada потребовался `-d memory_limit=512M`; серверная настройка памяти
не изменялась.

Полученные JSON и HTML-кэш находятся в игнорируемом `build/`, не в Git.
Повтор HTTP-проверки по сохранённому инвентарю:

```powershell
py -3 scripts/audit_site_content.py --old build/audit-old.json --new build/audit-new.json --cache build/content-audit-cache --output build/content-audit-http.json
```

Существующий кэш отражает состояние на дату аудита. Для свежей проверки
использовать новый каталог `--cache`. Проверки инструмента:

```powershell
php -l scripts/audit_site_inventory.php
py -3 tests/test_content_audit.py
py -3 -m py_compile scripts/audit_site_content.py
```

## Полная карта материалов

Таблица ниже включает все 90 опубликованных страниц/записей исходного сайта.
«Сопоставлен» означает наличие объекта и доступность URL, а не отсутствие
перечисленных выше потерь вложенных блоков. Дополнительные семь новых страниц
не имеют исходной пары и в таблицу исходных материалов не входят.

| ID старый → новый | Язык | Исходный материал | Новый сайт | Результат |
| --- | --- | --- | --- | --- |
| 1478 → 17 | ru | [Новости](https://georg-kloster.ru/news/) | [Открыть](https://new.georg-kloster.ru/news/) | каталог новостей переработан |
| 2036 → 16 | ru | [Главная](https://georg-kloster.ru/) | [Открыть](https://new.georg-kloster.ru/) | Главная создана заново; текст не перенесён целиком |
| 2181 → 19 | ru | [Крещение](https://georg-kloster.ru/kreschenie/) | [Открыть](https://new.georg-kloster.ru/kreschenie/) | Сопоставлен; текст сохранён |
| 2183 → 20 | ru | [Венчание](https://georg-kloster.ru/venchanie/) | [Открыть](https://new.georg-kloster.ru/venchanie/) | Сопоставлен; текст сохранён |
| 2185 → 21 | ru | [Молебен](https://georg-kloster.ru/moleben/) | [Открыть](https://new.georg-kloster.ru/moleben/) | Сопоставлен; текст сохранён |
| 2187 → 22 | ru | [Панихида](https://georg-kloster.ru/panihida/) | [Открыть](https://new.georg-kloster.ru/panihida/) | Сопоставлен; текст сохранён |
| 2189 → 23 | ru | [О монастыре](https://georg-kloster.ru/o-monastyre/) | [Открыть](https://new.georg-kloster.ru/o-monastyre/) | нет MP4; исходный текст сокращён; запись стала страницей |
| 2191 → 24 | ru | [Игумен Даниил (Ирбитс)](https://georg-kloster.ru/igumen-daniil-irbits/) | [Открыть](https://new.georg-kloster.ru/igumen-daniil-irbits/) | Сопоставлен; текст сохранён |
| 2195 → 25 | ru | [Святой великомученик Георгий Победоносец](https://georg-kloster.ru/svyatoj-velikomuchenik-georgij-pobedonosec/) | [Открыть](https://new.georg-kloster.ru/svyatoj-velikomuchenik-georgij-pobedonosec/) | нет галереи (4) |
| 2197 → 26 | ru | [Первый Священноархимандрит монастыря](https://georg-kloster.ru/pervyj-svyashhennoarhimandrit-monastyrya/) | [Открыть](https://new.georg-kloster.ru/pervyj-svyashhennoarhimandrit-monastyrya/) | Сопоставлен; текст сохранён |
| 2200 → 27 | ru | [Послушничество](https://georg-kloster.ru/zhizn-monastyrya/) | [Открыть](https://new.georg-kloster.ru/zhizn-monastyrya/) | Сопоставлен; текст сохранён |
| 2202 → 28 | ru | [Правовая информация](https://georg-kloster.ru/pravovaya-informaciya/) | [Открыть](https://new.georg-kloster.ru/pravovaya-informaciya/) | Сопоставлен; текст сохранён |
| 2204 → 29 | ru | [Контакты](https://georg-kloster.ru/kontakt/) | [Открыть](https://new.georg-kloster.ru/kontakt/) | Сопоставлен; текст сохранён |
| 2207 → 30 | ru | [Как доехать](https://georg-kloster.ru/kak-doehat/) | [Открыть](https://new.georg-kloster.ru/kak-doehat/) | Сопоставлен; текст сохранён |
| 2209 → 31 | ru | [Гётчендорф](https://georg-kloster.ru/goetschendorf/) | [Открыть](https://new.georg-kloster.ru/goetschendorf/) | Сопоставлен; текст сохранён |
| 2211 → 32 | ru | [Глэмпинг на природе](https://georg-kloster.ru/ecoigluglamping/) | [Открыть](https://new.georg-kloster.ru/ecoigluglamping/) | Сопоставлен; текст сохранён |
| 2214 → 33 | ru | [Монастырская кухня](https://georg-kloster.ru/monastyrskaya-kuhnya/) | [Открыть](https://new.georg-kloster.ru/monastyrskaya-kuhnya/) | нет MP4 |
| 2216 → 34 | ru | [Расписание богослужений](https://georg-kloster.ru/raspisanie-bogosluzheniy/) | [Открыть](https://new.georg-kloster.ru/raspisanie-bogosluzheniy/) | исходный текст сокращён; запись стала страницей |
| 2322 → 36 | ru | [Подтверждение оплаты](https://georg-kloster.ru/подтверждение-оплаты/) | [Открыть](https://new.georg-kloster.ru/подтверждение-оплаты/) | старый платёжный шорткод не обработан |
| 2323 → 37 | ru | [Ошибка оплаты](https://georg-kloster.ru/ошибка-оплаты/) | [Открыть](https://new.georg-kloster.ru/ошибка-оплаты/) | Сопоставлен; текст сохранён |
| 2386 → 39 | ru | [Проект росписи Храма](https://georg-kloster.ru/proekt-rospisi-hrama/) | [Открыть](https://new.georg-kloster.ru/proekt-rospisi-hrama/) | нет галереи (5); нет MP4; старый платёжный шорткод не обработан |
| 2655 → 42 | ru | [Пожертвования](https://georg-kloster.ru/spenden/) | [Открыть](https://new.georg-kloster.ru/spenden/) | текст сохранён; старая форма не восстановлена |
| 2791 → 613 | de | [Hauptseite](https://georg-kloster.de/) | [Открыть](https://new.georg-kloster.ru/de/) | Главная создана заново; текст не перенесён целиком |
| 2795 → 44 | de | [Abt Daniil (Irbits)](https://georg-kloster.de/hegumen-daniil-irbits/) | [Открыть](https://new.georg-kloster.ru/de/hegumen-daniil-irbits/) | Сопоставлен; текст сохранён |
| 2798 → 45 | de | [Altar-Kerze](https://georg-kloster.de/altar-kerze/) | [Открыть](https://new.georg-kloster.ru/de/altar-kerze/) | Сопоставлен; текст сохранён |
| 2801 → 46 | de | [Gebetsanliegen online](https://georg-kloster.de/mitteilung-online-einreichen/) | [Открыть](https://new.georg-kloster.ru/de/mitteilung-online-einreichen/) | Сопоставлен; текст сохранён |
| 2803 → 47 | de | [Projekt zur Ausmalung der Kirche](https://georg-kloster.de/projekt-zur-ausmalung-der-kirche/) | [Открыть](https://new.georg-kloster.ru/de/projekt-zur-ausmalung-der-kirche/) | нет галереи (5); нет MP4; старый платёжный шорткод не обработан |
| 2807 → 48 | de | [Anfahrt](https://georg-kloster.de/anfahrt/) | [Открыть](https://new.georg-kloster.ru/de/anfahrt/) | Сопоставлен; текст сохранён |
| 2810 → 49 | de | [Gottesdienstplan](https://georg-kloster.de/gottesdienstplan/) | [Открыть](https://new.georg-kloster.ru/de/gottesdienstplan/) | исходный текст сокращён; запись стала страницей |
| 2813 → 50 | de | [Sorokoust](https://georg-kloster.de/sorokoust/) | [Открыть](https://new.georg-kloster.ru/de/sorokoust-2/) | Сопоставлен; текст сохранён |
| 2816 → 51 | de | [Klosterküche](https://georg-kloster.de/klosterkueche/) | [Открыть](https://new.georg-kloster.ru/de/klosterkueche/) | нет MP4 |
| 2819 → 52 | de | [Glamping in der Natur](https://georg-kloster.de/glamping-in-der-natur/) | [Открыть](https://new.georg-kloster.ru/de/glamping-in-der-natur/) | Сопоставлен; текст сохранён |
| 2822 → 53 | de | [Götschendorf](https://georg-kloster.de/goetschendorf/) | [Открыть](https://new.georg-kloster.ru/de/goetschendorf-2/) | Сопоставлен; текст сохранён |
| 2825 → 54 | de | [Der erste Erzarchimandrit des Klosters](https://georg-kloster.de/der-erste-erzarchimandrit-des-klosters/) | [Открыть](https://new.georg-kloster.ru/de/der-erste-erzarchimandrit-des-klosters/) | Сопоставлен; текст сохранён |
| 2828 → 55 | de | [Über das Kloster](https://georg-kloster.de/ueber-das-kloster/) | [Открыть](https://new.georg-kloster.ru/de/ueber-das-kloster/) | нет MP4; исходный текст сокращён; запись стала страницей |
| 2831 → 56 | de | [Panichida](https://georg-kloster.de/panichida/) | [Открыть](https://new.georg-kloster.ru/de/panichida/) | Сопоставлен; текст сохранён |
| 2834 → 57 | de | [Taufe](https://georg-kloster.de/taufe/) | [Открыть](https://new.georg-kloster.ru/de/taufe/) | Сопоставлен; текст сохранён |
| 2837 → 58 | de | [Die Trauung](https://georg-kloster.de/die-trauung/) | [Открыть](https://new.georg-kloster.ru/de/die-trauung/) | Сопоставлен; текст сохранён |
| 2840 → 59 | de | [Molieben](https://georg-kloster.de/molieben/) | [Открыть](https://new.georg-kloster.ru/de/molieben/) | Сопоставлен; текст сохранён |
| 2843 → 60 | de | [Heiliger Großmärtyrer Georg der Siegreiche](https://georg-kloster.de/heiliger-grossmaertyrer-georg-der-siegreiche/) | [Открыть](https://new.georg-kloster.ru/de/heiliger-grossmaertyrer-georg-der-siegreiche/) | нет галереи (4) |
| 2847 → 61 | de | [Noviziat](https://georg-kloster.de/noviziat/) | [Открыть](https://new.georg-kloster.ru/de/noviziat/) | Сопоставлен; текст сохранён |
| 2850 → 62 | de | [Nachrichten](https://georg-kloster.de/nachrichten/) | [Открыть](https://new.georg-kloster.ru/de/nachrichten/) | каталог новостей переработан |
| 2854 → 63 | de | [Spenden](https://georg-kloster.de/spenden/) | [Открыть](https://new.georg-kloster.ru/de/spenden-2/) | старый платёжный шорткод не обработан |
| 2857 → 64 | de | [Impressum](https://georg-kloster.de/impressum/) | [Открыть](https://new.georg-kloster.ru/de/impressum/) | Сопоставлен; текст сохранён |
| 2860 → 65 | de | [Zahlungsbestätigung](https://georg-kloster.de/zahlungsbestaetigung/) | [Открыть](https://new.georg-kloster.ru/de/zahlungsbestaetigung/) | старый платёжный шорткод не обработан |
| 2862 → 66 | de | [Zahlungsfehler](https://georg-kloster.de/zahlungsfehler/) | [Открыть](https://new.georg-kloster.ru/de/zahlungsfehler/) | Сопоставлен; текст сохранён |
| 2867 → 68 | de | [Spendenbestätigung](https://georg-kloster.de/spendenbestaetigung/) | [Открыть](https://new.georg-kloster.ru/de/spendenbestaetigung/) | старый платёжный шорткод не обработан |
| 2868 → 69 | de | [Spende fehlgeschlagen](https://georg-kloster.de/spende-fehlgeschlagen/) | [Открыть](https://new.georg-kloster.ru/de/spende-fehlgeschlagen/) | Сопоставлен; текст сохранён |
| 2869 → 70 | de | [Spender-Dashboard](https://georg-kloster.de/spender-dashboard/) | [Открыть](https://new.georg-kloster.ru/de/spender-dashboard/) | Сопоставлен; текст сохранён |
| 2883 → 71 | de | [Kontakt](https://georg-kloster.de/kontakt/) | [Открыть](https://new.georg-kloster.ru/de/kontakt-2/) | Сопоставлен; текст сохранён |
| 3072 → 72 | ru | [Воздви́жение Честно́го и Животворящего Креста Господня](https://georg-kloster.ru/prazdnik-vozdvizhenie-chestnogo-i-zhivotvorjashchego-kresta-gospodnja/) | [Открыть](https://new.georg-kloster.ru/prazdnik-vozdvizhenie-chestnogo-i-zhivotvorjashchego-kresta-gospodnja/) | Сопоставлен; текст сохранён |
| 3084 → 73 | ru | [Преставление апостола и евангелиста Иоанна Богослова](https://georg-kloster.ru/prestavlenie-apostola-i-evangelista-ioanna-bogoslova/) | [Открыть](https://new.georg-kloster.ru/prestavlenie-apostola-i-evangelista-ioanna-bogoslova/) | Сопоставлен; текст сохранён |
| 3113 → 74 | ru | [Покров Пресвятой Владычицы нашей Богородицы и Приснодевы Марии](https://georg-kloster.ru/pokrov-presvyatoy-bogorodicy/) | [Открыть](https://new.georg-kloster.ru/pokrov-presvyatoy-bogorodicy/) | Сопоставлен; текст сохранён |
| 3138 → 75 | ru | [Отопление жилого помещения и храма](https://georg-kloster.ru/otoplenie-zhilogo-pomeshheniya-i-xrama/) | [Открыть](https://new.georg-kloster.ru/otoplenie-zhilogo-pomeshheniya-i-xrama/) | нет галереи (10) |
| 3173 → 76 | ru | [Электрификация храма](https://georg-kloster.ru/svet-v-xrame/) | [Открыть](https://new.georg-kloster.ru/svet-v-xrame/) | нет галереи (5) |
| 3200 → 77 | ru | [Пономарка](https://georg-kloster.ru/ponomarka-obnovlenie-i-vtoroj-uroven/) | [Открыть](https://new.georg-kloster.ru/ponomarka-obnovlenie-i-vtoroj-uroven/) | нет галереи (9) |
| 3227 → 78 | ru | [Водоснабжение храма](https://georg-kloster.ru/vodosnabzhenie-xrama/) | [Открыть](https://new.georg-kloster.ru/vodosnabzhenie-xrama/) | нет галереи (3) |
| 3236 → 79 | ru | [Уличное освещение](https://georg-kloster.ru/ulichnoe-osveshhenie/) | [Открыть](https://new.georg-kloster.ru/ulichnoe-osveshhenie/) | нет галереи (3); утрачена кнопка-ссылка |
| 3248 → 80 | ru | [Укладка плитки на амвоне](https://georg-kloster.ru/plitka-na-amvone-xrama/) | [Открыть](https://new.georg-kloster.ru/plitka-na-amvone-xrama/) | нет общей кнопки пожертвования |
| 3256 → 81 | ru | [Переход на экологичное отопление](https://georg-kloster.ru/perekhod-na-ekologichnoe-otoplenie/) | [Открыть](https://new.georg-kloster.ru/perekhod-na-ekologichnoe-otoplenie/) | нет галереи (6); нет общей кнопки пожертвования |
| 3271 → 82 | ru | [Выравнивание двора](https://georg-kloster.ru/vyravnivanie-dvora/) | [Открыть](https://new.georg-kloster.ru/vyravnivanie-dvora/) | нет галереи (6); нет общей кнопки пожертвования |
| 3275 → 83 | ru | [Обустройство санитарной комнаты](https://georg-kloster.ru/obustrojstvo-sanitarnoy-komnaty/) | [Открыть](https://new.georg-kloster.ru/obustrojstvo-sanitarnoy-komnaty/) | нет общей кнопки пожертвования |
| 3338 → 85 | ru | [Замечали ли вы, как часто мы обращаемся к Богу с просьбами?](https://georg-kloster.ru/chasto-k-bogu-s-prosbami/) | [Открыть](https://new.georg-kloster.ru/chasto-k-bogu-s-prosbami/) | Сопоставлен; текст сохранён |
| 3344 → 86 | ru | [Молитесь за ваших обидчиков, за тех, кто вам причиняет боль.](https://georg-kloster.ru/molites-za-obidchikov/) | [Открыть](https://new.georg-kloster.ru/molites-za-obidchikov/) | Сопоставлен; текст сохранён |
| 3348 → 87 | ru | [Не предрекай войны, не надо.](https://georg-kloster.ru/ne-predrekai-vojny/) | [Открыть](https://new.georg-kloster.ru/ne-predrekai-vojny/) | Сопоставлен; текст сохранён |
| 3352 → 88 | ru | [Помолись за меня у Престола](https://georg-kloster.ru/pomolis-za-menya-u-prestola/) | [Открыть](https://new.georg-kloster.ru/pomolis-za-menya-u-prestola/) | Сопоставлен; текст сохранён |
| 3354 → 89 | ru | [Как жить легко?](https://georg-kloster.ru/kak-zhit-legko/) | [Открыть](https://new.georg-kloster.ru/kak-zhit-legko/) | Сопоставлен; текст сохранён |
| 3372 → 90 | ru | [Фотографии](https://georg-kloster.ru/fotos/) | [Открыть](https://new.georg-kloster.ru/fotos/) | 28 фото сохранены; нет описаний альбомов |
| 3391 → 91 | de | [Wasserversorgung der Kirche](https://georg-kloster.de/wasserversorgung-der-kirche/) | [Открыть](https://new.georg-kloster.ru/de/wasserversorgung-der-kirche/) | нет галереи (3) |
| 3433 → 93 | ru | [Литургия в Новогоднюю ночь](https://georg-kloster.ru/liturgiya-v-novogodnyuyu-noch/) | [Открыть](https://new.georg-kloster.ru/liturgiya-v-novogodnyuyu-noch/) | Сопоставлен; текст сохранён |
| 3448 → 94 | ru | [Архиерейское слово к празднику Рождества Христова](https://georg-kloster.ru/arhierejskoe-slovo-k-prazdniku-rozhdestva/) | [Открыть](https://new.georg-kloster.ru/arhierejskoe-slovo-k-prazdniku-rozhdestva/) | Сопоставлен; текст сохранён |
| 3456 → 95 | ru | [Чтения Евангелия - встречи онлайн](https://georg-kloster.ru/online-chteniya-yevangeliya/) | [Открыть](https://new.georg-kloster.ru/online-chteniya-yevangeliya/) | Сопоставлен; текст сохранён |
| 3457 → 96 | ru | [РОЖДЕСТВЕНСКОЕ БОГОСЛУЖЕНИЕ](https://georg-kloster.ru/rozhdestvenskoye-bogosluzheniye/) | [Открыть](https://new.georg-kloster.ru/rozhdestvenskoye-bogosluzheniye/) | Сопоставлен; текст сохранён |
| 3487 → 99 | ru | [Евангелие от Матфея 1 глава](https://georg-kloster.ru/евангелие-от-матфея-1-глава/) | [Открыть](https://new.georg-kloster.ru/евангелие-от-матфея-1-глава/) | YouTube восстановлен ранее |
| 3489 → 100 | ru | [Евангелие от Матфея 2 глава](https://georg-kloster.ru/евангелие-от-матфея-2-глава/) | [Открыть](https://new.georg-kloster.ru/евангелие-от-матфея-2-глава/) | YouTube восстановлен ранее |
| 3496 → 101 | ru | [Евангелие от Матфея 3 глава](https://georg-kloster.ru/евангелие-от-матфея-3-глава/) | [Открыть](https://new.georg-kloster.ru/евангелие-от-матфея-3-глава/) | YouTube восстановлен ранее |
| 3506 → 103 | ru | [Богоявление - праздник Крещения Господня](https://georg-kloster.ru/19-01/) | [Открыть](https://new.georg-kloster.ru/19-01/) | Сопоставлен; текст сохранён |
| 3511 → 104 | ru | [Сочельник Богоявления](https://georg-kloster.ru/sochelnik-bogoyavleniya/) | [Открыть](https://new.georg-kloster.ru/sochelnik-bogoyavleniya/) | Сопоставлен; текст сохранён |
| 3527 → 105 | ru | [Блаженны миротворцы](https://georg-kloster.ru/блаженны-миротворцы/) | [Открыть](https://new.georg-kloster.ru/блаженны-миротворцы/) | Сопоставлен; текст сохранён |
| 3529 → 106 | ru | [Прощёное воскресенье](https://georg-kloster.ru/прощёное-воскресенье/) | [Открыть](https://new.georg-kloster.ru/прощёное-воскресенье/) | Сопоставлен; текст сохранён |
| 3542 → 108 | ru | [Мысли на пороге важного рубежа](https://georg-kloster.ru/na-poroge-vazhnogo-rubezha/) | [Открыть](https://new.georg-kloster.ru/na-poroge-vazhnogo-rubezha/) | нет YouTube |
| 3548 → 109 | ru | [Праздник Святой Троицы в Свято-Георгиевском монастыре Гётчендорф](https://georg-kloster.ru/svjataja-troica-2026/) | [Открыть](https://new.georg-kloster.ru/svjataja-troica-2026/) | нет YouTube |
| 3561 → 111 | ru | [Вместе позаботимся о будущем нашей обители](https://georg-kloster.ru/vmeste-pozabotimsya-o-budushchem-nashey-obiteli/) | [Открыть](https://new.georg-kloster.ru/vmeste-pozabotimsya-o-budushchem-nashey-obiteli/) | Сопоставлен; текст сохранён |
| 3589 → 112 | ru | [Библия](https://georg-kloster.ru/библия/) | [Открыть](https://new.georg-kloster.ru/библия/) | Сопоставлен; текст сохранён |
| 3591 → 113 | ru | [Идите за Мною, и Я сделаю вас ловцами человеков](https://georg-kloster.ru/idi-za-mnoyu/) | [Открыть](https://new.georg-kloster.ru/idi-za-mnoyu/) | нет YouTube |
| 3604 → 114 | ru | [Что в твоей жизни занимает Моё место?](https://georg-kloster.ru/chto-v-tvoey-zhizni-zanimaet-moe-mesto/) | [Открыть](https://new.georg-kloster.ru/chto-v-tvoey-zhizni-zanimaet-moe-mesto/) | нет YouTube |
| 3612 → 115 | ru | [Календарная мастерская](https://georg-kloster.ru/kalendarnaya-masterskaya/) | [Открыть](https://new.georg-kloster.ru/kalendarnaya-masterskaya/) | утрачена кнопка-ссылка |
| 3644 → 116 | ru | [Календарь](https://georg-kloster.ru/kalender/) | [Открыть](https://new.georg-kloster.ru/kalender/) | Сопоставлен; текст сохранён |
| 3759 → 117 | de | [Kalender](https://georg-kloster.de/kalender/) | [Открыть](https://new.georg-kloster.ru/de/kalender-2/) | Сопоставлен; текст сохранён |
| 3760 → 118 | de | [Календарная мастерская](https://georg-kloster.de/календарная-мастерская/) | [Открыть](https://new.georg-kloster.ru/de/календарная-мастерская/) | утрачена кнопка-ссылка; ЦИКЛ 301 — не открывается |

## Полная карта исходных рубрик и меток

Все найденные URL архивов на обоих сайтах отвечают HTTP 200. Число в колонке —
счётчик записей WordPress, который не включает материалы, ставшие страницами.

| Тип | Язык | Исходный архив | Записей раньше | Новый архив | Записей теперь |
| --- | --- | --- | ---: | --- | ---: |
| Рубрика | ru | [360](https://georg-kloster.ru/category/360/) | 0 | [360](https://new.georg-kloster.ru/category/360/) | 0 |
| Метка | de | [Abt](https://georg-kloster.de/tag/abt/) | 1 | [Abt](https://new.georg-kloster.ru/de/tag/abt/) | 1 |
| Рубрика | de | [Gebetsanliegen](https://georg-kloster.de/category/gebetsanliegen/) | 7 | [Gebetsanliegen](https://new.georg-kloster.ru/de/category/gebetsanliegen/) | 7 |
| Метка | de | [Kirche](https://georg-kloster.de/tag/kirche/) | 1 | [Kirche](https://new.georg-kloster.ru/de/tag/kirche/) | 1 |
| Рубрика | de | [Kirche](https://georg-kloster.de/category/kirche/) | 3 | [Kirche](https://new.georg-kloster.ru/de/category/kirche/) | 2 |
| Рубрика | de | [Kloster](https://georg-kloster.de/category/kloster/) | 7 | [Kloster](https://new.georg-kloster.ru/de/category/kloster/) | 7 |
| Рубрика | de | [Klosterprojekt](https://georg-kloster.de/category/klosterprojekt/) | 3 | [Klosterprojekt](https://new.georg-kloster.ru/de/category/klosterprojekt/) | 3 |
| Метка | de | [Küche](https://georg-kloster.de/tag/kuche/) | 1 | [Küche](https://new.georg-kloster.ru/de/tag/kuche/) | 1 |
| Рубрика | de | [Nicht kategorisiert](https://georg-kloster.de/category/nicht-kategorisiert/) | 2 | Заменена распределением материалов | — |
| Метка | de | [Projekt](https://georg-kloster.de/tag/projekt/) | 2 | [Projekt](https://new.georg-kloster.ru/de/tag/projekt/) | 2 |
| Метка | de | [Spende](https://georg-kloster.de/tag/spende/) | 2 | [Spende](https://new.georg-kloster.ru/de/tag/spende/) | 2 |
| Метка | de | [Video](https://georg-kloster.de/tag/video/) | 3 | [Video](https://new.georg-kloster.ru/de/tag/video/) | 3 |
| Рубрика | de | [Wallfahrt](https://georg-kloster.de/category/wallfahrt/) | 6 | [Wallfahrt](https://new.georg-kloster.ru/de/category/wallfahrt/) | 6 |
| Метка | ru | [Акафист](https://georg-kloster.ru/tag/акафист/) | 0 | [Акафист](https://new.georg-kloster.ru/tag/акафист/) | 0 |
| Метка | ru | [Богослужение](https://georg-kloster.ru/tag/богослужение/) | 0 | [Богослужение](https://new.georg-kloster.ru/tag/богослужение/) | 0 |
| Рубрика | ru | [Богослужения](https://georg-kloster.ru/category/bogosluzheniya/) | 8 | [Богослужения](https://new.georg-kloster.ru/category/bogosluzheniya/) | 8 |
| Метка | ru | [Видео](https://georg-kloster.ru/tag/видео/) | 7 | [Видео](https://new.georg-kloster.ru/tag/видео/) | 6 |
| Рубрика | ru | [Духовные мысли](https://georg-kloster.ru/category/duhovnye-mysli/) | 10 | [Духовные мысли](https://new.georg-kloster.ru/category/duhovnye-mysli/) | 10 |
| Метка | ru | [Игумен](https://georg-kloster.ru/tag/игумен/) | 5 | [Игумен](https://new.georg-kloster.ru/tag/игумен/) | 5 |
| Метка | ru | [Кухня](https://georg-kloster.ru/tag/кухня/) | 1 | [Кухня](https://new.georg-kloster.ru/tag/кухня/) | 1 |
| Метка | ru | [Литургия](https://georg-kloster.ru/tag/литургия/) | 1 | [Литургия](https://new.georg-kloster.ru/tag/литургия/) | 1 |
| Рубрика | ru | [Монастырь](https://georg-kloster.ru/category/monastyr/) | 9 | [Монастырь](https://new.georg-kloster.ru/category/monastyr/) | 8 |
| Метка | ru | [на главной](https://georg-kloster.ru/tag/на-главной/) | 5 | [на главной](https://new.georg-kloster.ru/tag/на-главной/) | 5 |
| Метка | de | [на главной](https://georg-kloster.de/tag/на-главной-de/) | 1 | [на главной](https://new.georg-kloster.ru/de/tag/на-главной-de/) | 1 |
| Рубрика | ru | [Новости](https://georg-kloster.ru/category/news/) | 13 | [Новости](https://new.georg-kloster.ru/category/news/) | 12 |
| Рубрика | ru | [Паломничество](https://georg-kloster.ru/category/palomnik/) | 6 | [Паломничество](https://new.georg-kloster.ru/category/palomnik/) | 6 |
| Метка | ru | [планы](https://georg-kloster.ru/tag/планы/) | 1 | [планы](https://new.georg-kloster.ru/tag/планы/) | 1 |
| Метка | ru | [Пожертвование](https://georg-kloster.ru/tag/пожертвование/) | 0 | [Пожертвование](https://new.georg-kloster.ru/tag/пожертвование/) | 0 |
| Метка | ru | [поэзия](https://georg-kloster.ru/tag/поэзия/) | 2 | [поэзия](https://new.georg-kloster.ru/tag/поэзия/) | 2 |
| Метка | ru | [проект](https://georg-kloster.ru/tag/проект/) | 10 | [проект](https://new.georg-kloster.ru/tag/проект/) | 10 |
| Рубрика | ru | [Проекты монастыря](https://georg-kloster.ru/category/proekt-monastyrya/) | 12 | [Проекты монастыря](https://new.georg-kloster.ru/category/proekt-monastyrya/) | 12 |
| Рубрика | ru | [Проповедь](https://georg-kloster.ru/category/проповедь/) | 2 | [Проповедь](https://new.georg-kloster.ru/category/проповедь/) | 2 |
| Рубрика | ru | [рассылка](https://georg-kloster.ru/category/рассылка/) | 1 | [рассылка](https://new.georg-kloster.ru/category/рассылка/) | 1 |
| Метка | ru | [Рождество](https://georg-kloster.ru/tag/рождество/) | 0 | [Рождество](https://new.georg-kloster.ru/tag/рождество/) | 0 |
| Метка | ru | [Слово пастора](https://georg-kloster.ru/tag/слово-пастора/) | 9 | [Слово пастора](https://new.georg-kloster.ru/tag/слово-пастора/) | 9 |
| Рубрика | ru | [Требы](https://georg-kloster.ru/category/treby/) | 4 | [Требы](https://new.georg-kloster.ru/category/treby/) | 4 |
| Метка | ru | [Храм](https://georg-kloster.ru/tag/храм/) | 2 | [Храм](https://new.georg-kloster.ru/tag/храм/) | 2 |
| Рубрика | ru | [Храм](https://georg-kloster.ru/category/khram/) | 2 | [Храм](https://new.georg-kloster.ru/category/khram/) | 1 |
| Рубрика | ru | [Чтение Евангелия](https://georg-kloster.ru/category/evangelie/) | 3 | [Чтение Евангелия](https://new.georg-kloster.ru/category/evangelie/) | 3 |
