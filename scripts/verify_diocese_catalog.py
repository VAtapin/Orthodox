"""Validate the research denominators, file set and final exported documents."""
import json, re
from pathlib import Path
from urllib.parse import urlsplit
from pypdf import PdfReader
from docx import Document

ROOT=Path(__file__).resolve().parents[1]
OUT=ROOT/'docs/diocese-catalog'
d=json.loads((OUT/'research/inventory.json').read_text(encoding='utf-8'))
m=json.loads((OUT/'research/mobile.json').read_text(encoding='utf-8'))
assert len(d['parishes'])==102
assert len({p['card'] for p in d['parishes']})==102
assert sum(not p['sites'] for p in d['parishes'])==26
assert len(d['sites'])==70
assert len({urlsplit(s['requested']).netloc.removeprefix('www.') for s in d['sites']})==68
assert sum('error' in s for s in d['sites'])==5
assert len(d['detail_pages'])==83
assert len(m)==65 and all(not x.get('error') and x['width']==390 for x in m)
assert sum(x['scroll']>x['width']+2 for x in m)==20
for x in m:assert (OUT/'images'/x['image']).is_file()
for s in d['sites']:
    if 'error' in s:
        assert any(x['requested']==s['requested'] and 'error' in x for x in d['detail_pages'])
raw=(OUT/'research/inventory.json').read_text(encoding='utf-8')
assert not re.search(r'(?i)[?&](?:pwd|password|token|key)=([^\s&"\]]+)',raw)
for name,count in [('Prihod_proposal',11),('Prihod_brief',2),('Diocese_sites_appendix',6)]:
    pdf=PdfReader(OUT/f'{name}.pdf');doc=Document(OUT/f'{name}.docx')
    assert len(pdf.pages)==count
    assert all(len(p.extract_text())>150 for p in pdf.pages)
    assert doc.paragraphs and all(round(float(p.mediabox.width))==792 and round(float(p.mediabox.height))==612 for p in pdf.pages)
    assert all(s.page_width>s.page_height for s in doc.sections)
overview=Document(OUT/'Diocese_sites_appendix.docx')
assert len(overview.inline_shapes)==6
assert len(overview.tables)==0
assert sum(p.text.startswith('Что делает приход:') or p.text.startswith('Как исправить:') for p in overview.paragraphs)==5
assert all((OUT/'images/visual-report'/f'compact-{name}.png').exists() for name in ['mobile','dates','structured','navigation','start'])
print('PASS: audit counts, 65 archived mobile records/images, landscape format, 5 problem/solution comparisons, 3 DOCX/PDF pairs and 19 pages')
