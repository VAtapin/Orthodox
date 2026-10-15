"""Public, read-only inventory. No logins, submissions or analytics access."""
import concurrent.futures, json, re, time, urllib.request, urllib.error
from pathlib import Path
from urllib.parse import urljoin, urlsplit
from lxml import html

ROOT = Path(__file__).resolve().parents[1]
OUT = ROOT / 'docs/diocese-catalog/research'
OUT.mkdir(parents=True, exist_ok=True)

def clean(value):
    """Do not retain meeting access links or credential query parameters."""
    if isinstance(value,str):
        value=re.sub(r'https?://[^\s<>]*zoom\.us/[^\s<>]*','[ссылка видеоконференции исключена]',value,flags=re.I)
        return re.sub(r'([?&](?:pwd|token|key|password|sessionid)=)[^&\s]+',r'\1[исключено]',value,flags=re.I)
    if isinstance(value,list):return [clean(v) for v in value]
    if isinstance(value,dict):return {k:clean(v) for k,v in value.items()}
    return value

def fetch(url):
    start = time.monotonic()
    try:
        req = urllib.request.Request(url, headers={'User-Agent':'Mozilla/5.0 (compatible; ParishWebsiteReview/1.0)'})
        with urllib.request.urlopen(req, timeout=22) as response:
            raw = response.read(3_000_000)
            try:
                decoded = raw.decode('utf-8')
            except UnicodeDecodeError:
                decoded = raw.decode(response.headers.get_content_charset() or 'windows-1251',errors='replace')
            doc = html.fromstring(decoded, base_url=response.url)
            for node in doc.xpath('//script|//style|//noscript'):
                node.drop_tree()
            text = re.sub(r'\s+', ' ', doc.text_content()).strip()
            scope = doc.xpath('//table[contains(@class,"church-info")]') if '/churches/' in url else []
            links = [{'text':re.sub(r'\s+',' ',a.text_content()).strip(), 'url':urljoin(response.url,a.get('href','').strip())} for a in (scope[0] if scope else doc).xpath('.//a[@href]')]
            return {'requested':url,'url':response.url,'status':response.status,'seconds':round(time.monotonic()-start,2),'title':' '.join(doc.xpath('//title/text()')),'viewport':doc.xpath('//meta[translate(@name,"VIEWPORT","viewport")="viewport"]/@content'),'text':text,'links':links}
    except Exception as e:
        return {'requested':url,'error':str(e),'seconds':round(time.monotonic()-start,2)}

if __name__ == '__main__':
    index=ROOT/'diocese-index.tmp.html'
    if not index.exists():
        req=urllib.request.Request('https://rokmp.de/churches-by-cities/',headers={'User-Agent':'Mozilla/5.0 (compatible; ParishWebsiteReview/1.0)'})
        with urllib.request.urlopen(req,timeout=30) as r:index.write_bytes(r.read())
    source = html.fromstring(index.read_bytes())
    city = ''
    parishes=[]
    for a in source.xpath('//a[@href]'):
        url=a.get('href','')
        if '/cities/' in url:
            city=a.text_content().strip()
        if '/churches/' in url and url not in [p['card'] for p in parishes]:
            parishes.append({'city':city,'name':a.text_content().strip(),'card':url})
    with concurrent.futures.ThreadPoolExecutor(max_workers=5) as pool:
        cards=list(pool.map(fetch,[p['card'] for p in parishes]))
    excluded=('rokmp.de','youtube.com','facebook.com','instagram.com','twitter.com','vk.com','t.me','google.','maps.','wa.me','wordpress.org','cookiedatabase.org')
    for p,c in zip(parishes,cards):
        p['card_result']=c
        candidates=[]
        for a in c.get('links',[]):
            u=a['url'];host=urlsplit(u).netloc.lower()
            if u.startswith(('http://','https://')) and host and not any(x in host for x in excluded):
                if u not in candidates:candidates.append(u)
        p['sites']=candidates
    urls=sorted(set(u for p in parishes for u in p['sites']))
    with concurrent.futures.ThreadPoolExecutor(max_workers=5) as pool:
        sites=list(pool.map(fetch,urls))
    payload=clean({'date':'2026-10-15','source':'https://rokmp.de/churches-by-cities/','parishes':parishes,'sites':sites})
    (OUT/'inventory.json').write_text(json.dumps(payload,ensure_ascii=False,indent=2),encoding='utf-8')
    print(json.dumps({'cards':len(parishes),'candidate_urls':len(urls),'cards_failed':sum('error' in c for c in cards),'sites_failed':sum('error' in c for c in sites)},ensure_ascii=False))
