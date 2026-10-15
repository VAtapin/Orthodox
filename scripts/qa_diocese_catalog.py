from pathlib import Path
from PIL import Image, ImageOps, ImageDraw
from pypdf import PdfReader
import json

ROOT=Path(__file__).resolve().parents[1]
base=ROOT/'tmp/diocese-qa'
for name in ['Prihod_proposal','Prihod_brief','Diocese_sites_appendix']:
    reader=PdfReader(ROOT/'docs/diocese-catalog'/f'{name}.pdf')
    print(name,len(reader.pages),'pages', 'chars per page:',[len(p.extract_text()) for p in reader.pages])
    pages=sorted((base/name).glob('page-*.png'),key=lambda p:int(p.stem.split('-')[1]))[:len(reader.pages)]
    for start in range(0,len(pages),6):
        sheet=Image.new('RGB',(1560,2040),'#bbbbbb');draw=ImageDraw.Draw(sheet)
        for i,path in enumerate(pages[start:start+6]):
            draw.text(((i%3)*520+8,(i//3)*1020+4),path.stem,fill='black')
            sheet.paste(ImageOps.contain(Image.open(path),(505,985)),((i%3)*520,(i//3)*1020+25))
        sheet.save(base/f'{name}-sheet-{start//6+1}.jpg')
mobile=json.loads((ROOT/'docs/diocese-catalog/research/mobile.json').read_text(encoding='utf-8'))
for start in range(0,len(mobile),12):
    sheet=Image.new('RGB',(1560,1900),'#bbb');draw=ImageDraw.Draw(sheet)
    for i,m in enumerate(mobile[start:start+12]):
        thumb=ImageOps.contain(Image.open(ROOT/'docs/diocese-catalog/images'/m['image']),(250,850))
        x=(i%6)*260;y=(i//6)*950
        sheet.paste(thumb,(x,y+55));draw.text((x+3,y+4),str(start+i+1)+' '+m['requested'].split('/')[2][:24],fill='black')
    sheet.save(base/f'mobile-sheet-{start//12+1}.jpg')
print('mobile overflow',sum(m['scroll']>m['width']+2 for m in mobile))
