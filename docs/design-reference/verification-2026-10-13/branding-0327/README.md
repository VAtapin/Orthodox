# Приёмка логотипа — тема0.3.27,13октября2026

Реальные снимки локального WordPress7.1.3, не макеты.
wide-* использует публичный оригинал владельца400×120:
https://prihod.georg-kloster.ru/wp-content/uploads/2026/10/logo2t-400x120-1.png
square-* использует tests/fixtures/brand-square.png512×512.
Все пять схем: classic,hospitality,editorial,tradition,blue;
viewport1440desktop/390mobile. Логотип и название включены одновременно.
metrics.json —20случаев с прямоугольниками шапки/подвала, без пересечений/overflow.
logo-only-metrics.json —ещё10случаев с выключенным названием:
оригинал319–320px в шапке и подвале, сохранено соотношение400/120.
settings-logo-only.jpg и settings-mobile.jpg показывают фактический раздел
и предпросмотр выбранного широкого логотипа. Mobile settings390px без overflow.
logo-only-mobile.jpg — полный пример страницы с одним логотипом.
Проверка метрик: python tests/branding-layout27-check.py.
Сохранение и повторное открытие: только название, оба элемента выключены,
оба включены; выбранный attachment сохраняется. Эти сценарии покрыты branding-smoke.php.
Production не изменялся; снимки не подтверждают серверную установку0.3.27.
