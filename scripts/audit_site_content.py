"""Read-only HTTP crawl of public WordPress inventories; no third-party packages."""
import argparse
from concurrent.futures import ThreadPoolExecutor, as_completed
from hashlib import sha256
from html.parser import HTMLParser
import json
from pathlib import Path
import re
from urllib.error import HTTPError
from urllib.parse import parse_qs, quote, urljoin, urlsplit, urlunsplit
from urllib.request import Request, urlopen


class Page(HTMLParser):
    """Extract article content separately from navigation and sidebar text."""
    VOID = {'area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'param', 'source', 'track', 'wbr'}

    def __init__(self):
        super().__init__(convert_charrefs=True)
        self.stack = []
        self.text = []
        self.article_text = []
        self.links = []
        self.article_links = []
        self.images = []
        self.media = []
        self.title = []

    def handle_starttag(self, tag, attrs):
        d = dict(attrs)
        classes = d.get('class', '').split()
        article = bool(self.stack and self.stack[-1][1]) or any(c in classes for c in ('post-content', 'wp-block-post-content'))
        hidden = bool(self.stack and self.stack[-1][2]) or tag in ('script', 'style', 'noscript', 'template') or 'hidden' in d or d.get('aria-hidden') == 'true' or 'display:none' in d.get('style', '').replace(' ', '')
        if tag == 'a' and d.get('href'):
            self.links.append(d['href'])
            if article and not hidden:
                self.article_links.append(d['href'])
        if article and not hidden:
            if tag == 'img':
                self.images.append(d.get('data-orig-file') or d.get('data-src') or d.get('src', ''))
            if tag in ('iframe', 'source', 'video', 'audio', 'embed'):
                self.media.append(d.get('data-src') or d.get('data-lazy-src') or d.get('src', ''))
        if tag not in self.VOID:
            self.stack.append((tag, article, hidden))

    def handle_startendtag(self, tag, attrs):
        self.handle_starttag(tag, attrs)
        if tag not in self.VOID:
            self.handle_endtag(tag)

    def handle_endtag(self, tag):
        for i in range(len(self.stack) - 1, -1, -1):
            if self.stack[i][0] == tag:
                del self.stack[i:]
                break

    def handle_data(self, value):
        if self.stack and not self.stack[-1][2]:
            self.text.append(value)
            if self.stack[-1][1]:
                self.article_text.append(value)
            if self.stack[-1][0] == 'title':
                self.title.append(value)

    def result(self):
        norm = lambda xs: re.sub(r'\s+', ' ', ' '.join(xs)).strip()
        return dict(text=norm(self.text), article=norm(self.article_text), title=norm(self.title), links=self.links, article_links=self.article_links, images=self.images, media=self.media)


def safe_url(url):
    p = urlsplit(url)
    return urlunsplit((p.scheme, p.netloc, quote(p.path, safe='/%:@-._~!$&\'()*+,;='), quote(p.query, safe='=&%:+,;/@'), ''))


def crawl(url, cache):
    key = sha256(url.encode()).hexdigest()
    record = cache / (key + '.json')
    if record.exists():
        result = json.loads(record.read_text(encoding='utf-8'))
        raw_file = cache / (key + '.html')
        if result.get('status') == 200 and raw_file.exists():
            page = Page()
            page.feed(raw_file.read_text(encoding='utf-8', errors='replace'))
            result.update(page.result())
        return result
    result = {'url': url}
    try:
        req = Request(safe_url(url), headers={'User-Agent': 'GeorgKloster-ContentAudit/1.0 (read-only migration verification)'})
        with urlopen(req, timeout=55) as response:
            raw = response.read()
            result.update(status=response.status, final_url=response.url, content_type=response.headers.get('Content-Type', ''))
            charset = response.headers.get_content_charset() or 'utf-8'
        (cache / (key + '.html')).write_bytes(raw)
        page = Page()
        page.feed(raw.decode(charset, errors='replace'))
        result.update(page.result())
    except HTTPError as error:
        result.update(status=error.code, final_url=error.url, error=str(error))
    except Exception as error:
        result.update(status=0, error=str(error))
    record.write_text(json.dumps(result, ensure_ascii=False, indent=2), encoding='utf-8')
    return result


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--old', type=Path, required=True)
    parser.add_argument('--new', type=Path, required=True)
    parser.add_argument('--cache', type=Path, required=True)
    parser.add_argument('--output', type=Path, required=True)
    args = parser.parse_args()
    args.cache.mkdir(parents=True, exist_ok=True)
    inventories = [json.loads(p.read_text(encoding='utf-8')) for p in (args.old, args.new)]
    urls = set()
    for inv in inventories:
        for group in ('posts', 'terms'):
            urls.update(p['url'] for p in inv[group] if p['url'])
    # The virtual tours are static files outside WordPress. They must be audited too.
    tours = ('360/', '360de/', '360/klostergelaende/', '360/klosterkirche/', '360/herrenhaus/')
    urls.update('https://' + host + '/' + path for host in ('georg-kloster.ru', 'new.georg-kloster.ru') for path in tours)
    results = {}
    with ThreadPoolExecutor(max_workers=3) as pool:
        jobs = {pool.submit(crawl, url, args.cache): url for url in sorted(urls)}
        for done in as_completed(jobs):
            url = jobs[done]
            results[url] = done.result()
            if len(results) % 20 == 0 or len(results) == len(urls):
                print(f'Checked {len(results)}/{len(urls)} URLs', flush=True)
    # Follow article links, menu destinations and archive pagination, including
    # the old Photo Gallery album URLs absent from WordPress post inventories.
    hosts = {'georg-kloster.ru', 'georg-kloster.de', 'new.georg-kloster.ru'}
    def public_html_link(base, href):
        url = urljoin(base, href)
        p = urlsplit(url)
        if p.scheme not in ('http', 'https') or p.hostname not in hosts:
            return None
        if any(part in p.path for part in ('/wp-', '/feed/', '/comments/')) or re.search(r'\.(?!html?$)[a-z0-9]{2,5}$', p.path, re.I):
            return None
        if set(parse_qs(p.query)) - {'p', 'page_id', 'paged', 'page', 'type_0', 'album_gallery_id_0'}:
            return None
        return safe_url(url)
    for depth in range(20):
        extra = set()
        for base, result in results.items():
            links = result.get('article_links', []) + [link for link in result.get('links', []) if '/page/' in link]
            for href in links:
                url = public_html_link(base, href)
                if url and url not in results:
                    extra.add(url)
        for inv in inventories:
            for menu in inv['menus']:
                for item in menu['items']:
                    url = public_html_link(inv['home'], item['url'])
                    if url and url not in results:
                        extra.add(url)
        if not extra:
            break
        with ThreadPoolExecutor(max_workers=3) as pool:
            for result in pool.map(lambda u: crawl(u, args.cache), sorted(extra)):
                results[result['url']] = result
        print(f'Followed link depth {depth + 1}: {len(extra)} additional URLs', flush=True)
    else:
        raise RuntimeError('Link discovery reached its depth limit; audit is incomplete.')
    args.output.write_text(json.dumps(results, ensure_ascii=False, indent=2), encoding='utf-8')
    bad = [(u, r.get('status'), r.get('error', '')) for u, r in results.items() if r.get('status') != 200]
    print(json.dumps({'checked': len(results), 'non_200': bad}, ensure_ascii=True), flush=True)


if __name__ == '__main__':
    main()
