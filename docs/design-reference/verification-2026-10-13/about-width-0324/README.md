# Общая ширина и история — тема0.3.24

Локальный WordPress7.1.3/PHP8.4.25; Core rc.15. Пять схем×1920/1122/390.
Main/header/footer измерены рядом с соответствующими областями расписания при
той же ширине окна. Все≤выбранных1200px, различия размеров/позиций≤1px,
горизонтального overflow нет. На1920main1200px; на1122classic/hospitality/
editorial/tradition1043px, blue987px с её общими полями. На390main343px.

Desktop — четыре равных этапа в одну строку, sidebar270px. Mobile≤600px —
вертикальная линия и последовательные2006/2007/2011/2013. Размер описаний13px
не уменьшен, описания не обрезаются. Настройки/контент production не менялись.
Дополнительно через реальные локальные настройки выбрана1440px: main1440px
и совпадает с соседней страницей; затем восстановлены1200px.

Все16измерений проверяет tests/about-width-check.py, данные metrics.json.
Снимки всех15aboutcases и два крупных вида timeline-desktop/mobile сохранены.
Документированные smoke/design проверки310/210 и standalone прошли.
Тема0.3.24 установлена 13 октября с прямого разрешения владельца; Core rc.15.

ZIP/проверка загружены только в wp-content. Команда повторного обновления:

```bash
cd /var/www/vhosts/prihod.georg-kloster.ru/httpdocs && \
export PATH="/opt/plesk/php/8.5/bin:$PATH" && \
php /usr/local/bin/wp theme install wp-content/prihod-packages/prihod-theme-0.3.24.zip --force && \
php /usr/local/bin/wp eval-file wp-content/prihod-server-check.php --user=prihod-owner
```

Core/подключения/сторонние плагины не обновляются. Dependencies, build/migrations
и серверные настройки не требуются.

ZIP SHA256:C7CF80EBB2FA490CCD2A3BA77638807101049688A18BF38F7E2345FBE4D20C19.


Live-проверка после установки:87серверных проверок WP7.1.3/PHP8.5.11.
15реальных layoutcases (все5схем×1920/1122/390) прошли тем же
about-width-check.py; main/header/footer совпали с соответствующими областями
расписания, без overflow. На1920 во всех5схемах main1200px. Общие поля
tradition дают995px при1122 и319px при390, совпадая с соседней страницей.
В остальных main1043/343px, blue987px при1122; выбранная ширина не менялась.

Наdesktop сохранились270px sidebar и четыре равных этапа истории; наmobile
вертикальная линия, последовательность/шрифт/описания сохранены. ОшибокJS нет.
Снимки live-{scheme}-{viewport}, live-timeline-desktop/mobile и live-metrics.json.
Отпечаток правил служб/профиля/темы/Telegram/модулей одинаков до/после:
479529cc8bb83698e3df529ec5fce17112a45158eb7a3f7239e001470b3fbe15.
Ни Core, ни подключения, ни сторонние плагины, ни настройки сервера не обновлялись.
Сообщения/письма не отправлялись.
