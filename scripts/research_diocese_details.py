"""Follow only observed schedule/contact links and retry failed public addresses."""
import concurrent.futures, json, re
from urllib.parse import urlsplit
from research_diocese import fetch, OUT, clean

d=json.loads((OUT/'inventory.json').read_text(encoding='utf-8'))
urls=[]
for s in d['sites']:
    for pattern in (r'расписан|gottesdienst|богослуж',r'контакт|kontakt|contact'):
        matches=[a['url'] for a in s.get('links',[]) if re.search(pattern,a['text'],re.I) and a['url'].startswith(('http://','https://')) and '#' not in a['url'] and urlsplit(a['url']).netloc.removeprefix('www.')==urlsplit(s.get('url',s['requested'])).netloc.removeprefix('www.') and not a['url'].lower().endswith(('.pdf','.jpg','.png'))]
        if matches and matches[0] not in urls:urls.append(matches[0])
    if 'error' in s and s['requested'] not in urls:urls.append(s['requested'])
urls.append('https://russisch-orthodoxe-kirche-paderborn.de')
with concurrent.futures.ThreadPoolExecutor(max_workers=5) as pool:
    d['detail_pages']=list(pool.map(fetch,urls))

d=clean(d)
(OUT/'inventory.json').write_text(json.dumps(d,ensure_ascii=False,indent=2),encoding='utf-8')
print('detail pages',len(d['detail_pages']),'errors',sum('error' in s for s in d['detail_pages']))
