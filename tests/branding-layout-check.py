"""Check recorded geometry from real browser rendering of both logo shapes."""
from pathlib import Path
import json
root=Path(__file__).resolve().parent.parent
cases=json.loads((root/'docs/design-reference/verification-2026-10-13/branding-0325/metrics.json').read_text(encoding='utf-8'))
assert len(cases)==20
for case in cases:
 assert case['viewport'] in (390,1440) and not case['overflow'],case
 assert len(case['images'])==2,case
 ratio=5 if case['shape']=='wide' else 1
 for image in case['images']:
  assert image['fit']=='contain' and image['w']>0 and image['h']>0,case
  assert abs(image['w']/image['h']-ratio)<0.02,case
print('20 logo geometry cases passed: two shapes × five schemes × desktop/mobile, header and footer.')
