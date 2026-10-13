# Оформление бренда — тема0.3.25

Раздел находится внутри Прихода, вне Фотографий. Core и сторонние плагины не меняются.
Канонический site_icon WordPress исключает отдельную копию настройки favicon.
Все поля сохраняются общей формой Settings API с nonce и manage_options.
Логотип: оригинал/пропорции, ограниченная ширина и высота, мобильное ограничение;
если выбран текстовый логотип, соседнее название можно убрать. Без изображения
название остаётся доступным. Уже существующие логотипы и site_icon не мигрируются
и не сбрасываются при обновлении. Ручное удаление выбора сохраняет сам attachment.

Социальные карточки: публичный permalink без prihod_preview; заголовок страницы,
её excerpt либо описание сайта. Главная фотография имеет приоритет над общей.
Закрытый/password-protected контент не выводится. При известных SEO-плагинах
вывод этих тегов отключается, чтобы избежать дублирования; их поля не меняются.
Социальные сети могут кэшировать старую картинку независимо от WordPress.

Проверено локально: WP7.1.3/PHP8.4.25 —310 smoke/223 design;
WP6.5.13/PHP8.0.30 —310/222; theme-alone/connectors-alone прошли.
13 новых проверок branding, реальные512-square/1200×240 PNG, native icon tags,
ошибка/сохранение старого favicon, сохранение ID при partial settings,
удаление native fallback, featured-image priority, отключение OG/Twitter.
2463 перевода UI, PHP/JS syntax,20 реальных browser geometrycases.
CUA: upload/select/save/reload/clear, square favicon, неправильный wide favicon,
пять схем×1440/390×два формата в шапке/подвале, mobile settings390 без overflow.
Снимки: design-reference/verification-2026-10-13/branding-0325.

Сервер пока остаётся на0.3.24; обновление требует прямого разрешения владельца.
Пакет: build/prihod-theme-0.3.25.zip.
SHA256:49E4762B23C021DD5302F51F67F44695DB5C477488C662EAEA1E17313CFA401D.
После согласованной загрузки пакета/проверки только в wp-content этого сайта:

```bash
cd /var/www/vhosts/prihod.georg-kloster.ru/httpdocs && \
export PATH="/opt/plesk/php/8.5/bin:$PATH" && \
php /usr/local/bin/wp theme install wp-content/prihod-packages/prihod-theme-0.3.25.zip --force && \
php /usr/local/bin/wp eval-file wp-content/prihod-server-check.php --user=prihod-owner
```

Фактический PHP сайта8.5.11. Composer/npm/migrations/restart не нужны.
