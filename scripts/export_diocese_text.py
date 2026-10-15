"""Export the complete saved research to UTF-8 text and original screenshots."""
import io
import json
import re
import shutil
import subprocess
import xml.etree.ElementTree as ET
import zipfile
from pathlib import Path
from urllib.parse import urlsplit

ROOT = Path(__file__).resolve().parents[1]
SOURCE = ROOT / 'docs/diocese-catalog'
OUT = ROOT / 'docs/diocese-materials'
SHOTS = OUT / 'screenshots'
OUT.mkdir(exist_ok=True)
SHOTS.mkdir(exist_ok=True)
data = json.loads((SOURCE / 'research/inventory.json').read_text(encoding='utf-8'))
mobile = json.loads((SOURCE / 'research/mobile.json').read_text(encoding='utf-8'))
lines = []
image_index = []

def write(*values):
    lines.extend(str(v) for v in values)

def section(title):
    write('', '=' * 78, title, '=' * 78, '')

def links(values):
    for value in values:
        write('  ' + (value.get('text') or '[без текстовой подписи]') + ' — ' + value['url'])

def result(value,include_links=True):
    write('Запрошенный адрес: ' + value['requested'])
    if value.get('error'):
        write('Ошибка: ' + value['error'])
    else:
        write('Итоговый адрес HTTP запроса: ' + value['url'],
              'HTTP статус: ' + str(value.get('status')),
              'Заголовок страницы: ' + value.get('title',''),
              'meta viewport: ' + ('; '.join(value.get('viewport',[])) or 'не найден'))
    write('Длительность сохранённого HTTP запроса: ' + str(value.get('seconds','не измерена')) + ' с (не показатель загрузки в браузере).')
    write('Полный извлечённый текст страницы:',value.get('text','[текст не получен]'))
    if include_links:
        write('Сохранённые ссылки:');links(value.get('links',[]))

def copy_image(source,name,description):
    target=SHOTS/name
    target.parent.mkdir(parents=True,exist_ok=True)
    shutil.copyfile(source,target)
    assert target.read_bytes()==source.read_bytes()
    image_index.append((name,description))
    return name

for index,m in enumerate(mobile,1):
    host=urlsplit(m['requested']).netloc.lower().removeprefix('www.')
    host=re.sub(r'[^a-z0-9.-]','_',host)
    m['export_image']=copy_image(SOURCE/'images'/m['image'],f'{index:02}_{host}_mobile.png',
                                 f"{m['requested']} → {m['url']}; экран {m['width']} px; ширина документа {m['scroll']} px")

extras=[('brandenburg-desktop.png','66_brandenburg_desktop.png','Бранденбург, компьютер: http://www.brandenburg.orthodox.bplaced.de/ru/index.html'),
        ('brandenburg-mobile.png','67_brandenburg_mobile_additional.png','Бранденбург, дополнительный мобильный снимок с загруженной фотографией'),
        ('nuernberg-mobile.png','68_nuernberg_mobile_additional.png','Нюрнберг, дополнительный мобильный снимок: https://rus-kirche.de/'),
        ('duesseldorf-mobile.png','69_duesseldorf_mobile_additional.png','Дюссельдорф, немецкая страница: http://www.pokrow.de/de/'),
        ('berlin-mobile.png','70_berlin_mobile_additional.png','Берлинский собор, дополнительный мобильный снимок: https://soborberlin.com/')]
for original,name,description in extras:copy_image(SOURCE/'images'/original,name,description)

project=[(ROOT/f'prihod-theme/assets/schemes/{key}.jpg',f'project/{key}.jpg',f'Демонстрационный сайт Прихода: оформление {key}') for key in ['classic','hospitality','editorial','tradition','blue']]
project += [(ROOT/'docs/design-reference/verification-2026-10-12/local-wizard-plan-rc9.jpg','project/wizard-plan.jpg','Проверенный интерфейс мастера: план изменений, 12.10.2026'),
            (ROOT/'docs/design-reference/verification-2026-10-12/local-calendar-editor-rc10.jpg','project/service-editor.jpg','Полный проверочный снимок редактора богослужения, 12.10.2026'),
            (ROOT/'docs/design-reference/verification-2026-10-14/icon-overlay-rc20-live/classic-week-390.jpg','project/schedule-mobile-full.jpg','Полный снимок мобильного расписания Core rc.20, 14.10.2026'),
            (ROOT/'docs/design-reference/verification-2026-10-13/question-0328/classic-390.jpg','project/question-mobile-full.jpg','Полный снимок публичной формы вопроса, тема0.3.28'),
            (ROOT/'docs/test-server/monastery-home-mobile.png','project/home-mobile-full.png','Полный снимок мобильной главной демонстрационного сайта')]
for source,name,description in project:copy_image(source,name,description)

write('ПРИХОД И САЙТЫ ЕПАРХИИ — ВСЕ СОБРАННЫЕ МАТЕРИАЛЫ',
      'Обычный текстовый экспорт. Дата исследовательского среза: 15 октября 2026 года.',
      'Основной источник: '+data['source'],
      'Выгружены сохранённые данные исследования; новой сетевой проверки при экспорте не было.',
      'Скриншоты находятся отдельно в папке screenshots. Это исходные кадры без сравнительных монтажей и разметки.',
      'В корне screenshots — 70 кадров сайтов епархии. В screenshots/project — 10 исходных иллюстраций проекта.',
      'Соответствие файлов адресам приведено ниже. Архив screenshots.zip содержит эту же папку целиком.')

section('1. СТАТИСТИКА И ГРАНИЦЫ ПРОВЕРКИ')
write('Карточки храмов и общин: 102; города с карточками: 91.',
      'Карточки со ссылкой на сайт: 76 из 102 (74,5%). Без ссылки: 26 из 102 (25,5%).',
      'Опубликованные адреса: 70. Домены без учёта www: 68. Один сайт может обслуживать несколько общин.',
      'Получен HTML ответ: 65 из 70 (92,9%). Повторная ошибка: 5 из 70 (7,1%).',
      'Ошибки: 3 DNS, 1 несовпадение имени TLS сертификата, 1 HTTP 401 (требуется авторизация).',
      'Один полученный HTML ответ по адресу Саарбрюккена сообщает об удалении сайта.',
      'meta viewport найден в 49 из 65 полученных страниц; не найден в 16.',
      'Мобильный просмотр: 65 адресов, ширина экрана 390 px. У 20 ширина документа превысила экран более чем на 2 px.',
      'У 45 превышения ширины не выявлено. Это не подтверждение удобства всех страниц, меню, форм и вложенных рамок.',
      'Дополнительные чтения: 83 — страницы расписания/контактов, опубликованный переезд, повторные запросы ошибочных адресов.',
      'Отсутствие ссылки в карточке не доказывает отсутствия сайта. Все неуказанные сайты в интернете не разыскивались.',
      'Реальная посещаемость, уникальные пользователи, доля мобильных посетителей и время на странице не установлены.',
      'Lighthouse, Core Web Vitals, полный аудит безопасности и законодательного соответствия не проводились.',
      'Наличие ссылки на расписание/контакт — признак HTML, а не доказательство актуальности и удобства раздела.',
      'Первые экраны могут содержать cookies и динамические заставки. Снимок не показывает все страницы сайта.',
      'Количество frame/iframe включает любые вложенные области, а не только устаревшие frameset.',
      'Входы в аккаунты, отправка форм, письма, платежи и публикации не выполнялись. Приватная переписка и аналитика не читались.',
      'В сохранённых текстах исключены ссылки доступа к видеоконференциям и параметры credentials.',
      'Карточки — не число самостоятельных приходов; адреса — не число независимых сайтов.')

section('2. ПОДТВЕРЖДЁННЫЕ НАБЛЮДЕНИЯ И ВОЗМОЖНЫЕ РЕШЕНИЯ')
observations=[
('Нюрнберг — rus-kirche.de','На главной есть актуальные службы октября и ноября2026года. На390px строки расписания и верхний заголовок обрезаются. Браузер открыл HTTPS, обычный HTTP запрос сохранил другой итоговый адрес.','В Приходе — структурированные карточки служб и готовая мобильная тема. Приход вводит даты, время, места и исключения через поля.'),
('Тюбинген — orthodoxekirche.de/Tuebingen/','В разделе ближайших служб указаны16–30августа2025года. На15.10.2026 эти даты прошли; наличие другого актуального ресурса общины не установлено.','Приход добавляет службы, проверяет даты и публикует; для регулярных служб задаёт повторение. Актуальность не возникает без ответственного сотрудника.'),
('Бранденбург — brandenburg.orthodox.bplaced.de/ru/index.html','Шапка, меню и содержимое находятся в независимых рамках; на телефоне меню очень узкое и видны отдельные прокрутки.','Готовая тема Прихода даёт одну мобильную страницу, кнопку расписания и ближайшую службу. Сотрудник выбирает оформление и заполняет сведения.'),
('Шпандау — orthodox-spandau.bplaced.net/ru/','Аналогичная рамочная структура. Автоматическое извлечение текста главной неполно из-за вложенного содержимого.','Проверить сценарии посетителя и при необходимости перейти к общей мобильной структуре с сохранением материалов.'),
('Мюнхен — voskresenie.de','На первом экране расписание представлено изображением с мелким текстом; требуется увеличение. Варианты www и безwww встречаются отдельно.','Структурированное расписание позволяет редактировать отдельную службу и показывать карточки, а не заново оформлять изображение.'),
('Дюссельдорф — pokrow.de/de/','Есть немецкий раздел и содержательный архив. На390px горизонтальное меню и строки шире видимой области; измеренная ширина981px.','Сохранить материалы и обеспечить адаптивную навигацию. Само наличие архива не является недостатком.'),
('Бремен — orthodox-bremen.cerkov.ru','На первом мобильном экране длинный заголовок обрезается; измеренная ширина документа1288px.','Проверить заголовок и мобильную структуру, дать практической информации заметное место.'),
('Вормс — prihod-worms.cerkov.ru','Заголовок занимает большую часть первого экрана; до расписания нужно прокручивать или искать меню.','Использовать компактную шапку и заметную ссылку на расписание/ближайшую службу.'),
('Констанц — orthodoxe-kirche-konstanz.de','На телефоне видна часть широкой страницы; измерено768px при экране390px.','Проверить адаптивную вёрстку. Наличие meta viewport само по себе не устраняет проблему.'),
('Кобленц — koblenz.orthodoxy.ru','Измеренная ширина документа1024px при экране390px.','Использовать адаптивную структуру и проверить читаемость.'),
('Берлинский собор — soborberlin.com','Заметные ссылки на расписание, языки, адрес, часы открытия и способы связи.','Сохранить удачные решения. Замена работающего сайта не является самоцелью.'),
('Франкфурт — frankfurt.orthodoxy.ru → orthodoxekirche.org','Ближайшее богослужение с временем и местом видно на первом экране.','Полезный ориентир для структуры остальных сайтов.'),
('Гёттинген — orthodoxia-goettingen.de','Немецкоязычное приглашение, разделы Gottesdienste и Kontakt.','Учитывать немецкоязычного посетителя и сохранять понятную структуру.'),
('Падерборн и Бад Липпшпринге — rokmp-bielefeld.de','Старый сайт объявляет переезд на russisch-orthodoxe-kirche-paderborn.de; новый сайт прочитан.','Подтвердить и обновить ссылку в каталоге. Переезд не равен отсутствию сайта.'),
('Леверкузен — rok-leverkusen.de','Перенаправляет на pokrow.de.','Не считать отдельным независимым сайтом по итоговому адресу; уточнить общую структуру приходов.'),
('Саарбрюккен — saarbruecken.orthodoxy.ru','Отображается сообщение This site was deleted; та же ссылка дана Кайзерслаутерну.','Сначала уточнить актуальный адрес. При необходимости предложить техническую установку Прихода и самостоятельное наполнение через мастер.'),
('Лер — orthodox-leer.de','Обычный запрос дал пустой извлечённый текст. В браузере открылся https://orthodox-leer.de/RU/index.html.','Одного HTTP200 недостаточно для оценки содержания; учитывать браузерную навигацию.'),
('Кобург — seraphimcoburg.org','Автоматическое извлечение текста неполно. В браузере содержание открылось; ширина1024px при экране390px.','Не путать ошибку извлечения с отсутствием сайта; отдельно проверить мобильную ширину.'),
('Хузум — rokih.de','Под заголовком Покаяние размещён текст о Крещении, повторённый в следующем блоке.','Нужна редакционная сверка содержания.'),
('Бонн — rokbonn.de','Навигация первоначально превышала срок ожидания. Содержимое прочитано повторно; на сохранённом кадре видна динамическая заставка.','Не присваивать всему сайту оценку по одному состоянию загрузки; нужен отдельный сценарный просмотр.'),
('Штендаль — две карточки в исходном каталоге','Встречаются две карточки прихода святого апостола Иакова.','Возможный дубль; не объединять без подтверждения.'),
('Пфорцхайм, Вупперталь, немецкоязычная община Кирилла и Мефодия в Гамбурге','Три имени не разрешились через DNS; ошибки повторились.','Уточнить актуальный адрес и доступность у общины. Это не доказательство прекращения деятельности.'),
('Зулинген — www.sulingen.cerkov.ru','Имя в TLS сертификате не совпадает с адресом; ошибка повторилась.','Технический сопровождающий проверяет домен/переадресацию/сертификат. Ошибку сертификата не обходили.'),
('Бад Кройцнах — orthodox-bad-kreuznach.de','HTTP401 при двух запросах; браузер сообщил об ошибке credentials.','Уточнить публичный адрес и требование авторизации без попыток обхода.')]
for title,problem,solution in observations:write(title,'Наблюдение: '+problem,'Что можно исправить: '+solution,'')

section('3. УКАЗАТЕЛЬ СКРИНШОТОВ')
for name,description in image_index:write('screenshots/'+name,description,'')
write('Для пяти адресов с сетевой ошибкой снимков страниц нет. Не создавались изображения, выдаваемые за реальные сайты.')

section('4. ПОЛНЫЙ РЕЕСТР 102 КАРТОЧЕК И ИЗВЛЕЧЁННЫЕ ДАННЫЕ')
for index,p in enumerate(data['parishes'],1):
    write('',f"КАРТОЧКА {index}. {p['city']} — {p['name']}",'Карточка: '+p['card'],
          'Указанные сайты: '+('; '.join(p['sites']) or 'в карточке ссылка не указана'))
    result(p['card_result'])

section('5. ВСЕ 70 АДРЕСОВ — РЕЗУЛЬТАТЫ И ПОЛНЫЕ ИЗВЛЕЧЁННЫЕ ТЕКСТЫ')
for index,s in enumerate(data['sites'],1):
    write('',f'АДРЕС {index}')
    result(s)
    owners=[p['city']+' — '+p['name'] for p in data['parishes'] if s['requested'] in p['sites']]
    write('Связанные карточки: '+'; '.join(owners))
    m=next((x for x in mobile if x['requested']==s['requested']),None)
    if m:write('Адрес в браузере: '+m['url'],'Мобильный экран: '+str(m['width'])+'px; документ: '+str(m['scroll'])+'px; frame/iframe: '+str(m['frames']),
               'Превышение ширины более2px: '+('да' if m['scroll']>m['width']+2 else 'не выявлено'),'Снимок: screenshots/'+m['export_image'])

section('6. ВСЕ 83 ДОПОЛНИТЕЛЬНЫХ ЧТЕНИЯ И КОНТРОЛЬНЫХ ЗАПРОСА')
for index,s in enumerate(data['detail_pages'],1):write('',f'ДОПОЛНИТЕЛЬНАЯ СТРАНИЦА {index}');result(s)

section('7. ТЕКСТЫ ОПИСАНИЯ ПРОЕКТА И ПРЕДЛОЖЕНИЙ ИЗ ВСЕХ ПОДГОТОВЛЕННЫХ РЕДАКЦИЙ')
write('Ниже объединены тексты исходного расширенного документа, альбомного варианта и короткого сравнения. Точные повторы абзацев исключены.',
      'Это рабочие материалы разных редакций. Число страниц, старые названия приложений и ссылки на галерею относятся к соответствующей редакции, а не к текущему текстовому экспорту.',
      'Главный согласованный акцент: приход сам создаёт и ведёт сайт готовыми средствами; команда готова помочь с запуском и освоением. Пилот, сроки, стоимость и условия помощи — предложения для согласования.')
seen=set()
for ref,label in [('3a8db47','Первоначальный расширенный комплект'),('bedc504','Альбомный визуальный комплект'),('9f22591','Короткое сравнение проблем и решений')]:
    for name in ['Prihod_proposal','Prihod_brief','Diocese_sites_appendix']:
        raw=subprocess.run(['git','show',f'{ref}:docs/diocese-catalog/{name}.docx'],cwd=ROOT,capture_output=True,check=True).stdout
        with zipfile.ZipFile(io.BytesIO(raw)) as archive:tree=ET.fromstring(archive.read('word/document.xml'))
        paragraphs=[]
        for paragraph in tree.iter('{http://schemas.openxmlformats.org/wordprocessingml/2006/main}p'):
            text=''.join(node.text or '' for node in paragraph.iter('{http://schemas.openxmlformats.org/wordprocessingml/2006/main}t')).strip()
            key=' '.join(text.split())
            if key and key not in seen:paragraphs.append(text);seen.add(key)
        if paragraphs:write('',label+' — '+name,'',*paragraphs)

text_path=OUT/'ALL_INFORMATION.txt'
plain='\n'.join(line.rstrip() for line in '\n'.join(lines).splitlines()).rstrip()+'\n'
text_path.write_text(plain,encoding='utf-8-sig',newline='\n')
archive_path=OUT/'screenshots.zip'
with zipfile.ZipFile(archive_path,'w',compression=zipfile.ZIP_DEFLATED,compresslevel=6) as archive:
    for path in sorted(SHOTS.rglob('*')):
        if path.is_file():archive.write(path,path.relative_to(OUT).as_posix())
with zipfile.ZipFile(archive_path) as archive:
    assert archive.testzip() is None
    assert len(archive.namelist())==80
assert len(list(SHOTS.glob('*.png')))==70
assert len(list((SHOTS/'project').iterdir()))==10
assert len(data['parishes'])==102 and len(data['sites'])==70 and len(data['detail_pages'])==83
exported=text_path.read_text(encoding='utf-8-sig')
assert text_path.read_bytes().startswith(b'\xef\xbb\xbf')
assert all(s['requested'] in exported for s in data['sites'])
assert all(name in exported for name,_ in image_index)
print(f'PASS: {len(exported):,} characters; 102 cards; 70 addresses; 83 details; 70 site screenshots + 10 project images; ZIP integrity OK')
