"""Validate browser measurements from real wide and square logo acceptance."""
import json
from pathlib import Path

root = Path(__file__).resolve().parent.parent
rows = json.loads((root / 'docs/design-reference/verification-2026-10-13/branding-0327/metrics.json').read_text())
assert len(rows) == 20
assert len({(r['shape'], r['scheme'], r['viewport']) for r in rows}) == 20
for row in rows:
    assert not row['overflow'], row
    assert row['viewport'] in (390, 1440), row
    assert len(row['brands']) == 2, row
    for brand in row['brands']:
        image, title = brand['image'], brand['title']
        assert image['width'] > 0 and image['height'] > 0
        ratio = image['width'] / image['height']
        assert abs(ratio - (400 / 120 if row['shape'] == 'owner-wide' else 1)) < 0.02
        if row['shape'] == 'owner-wide':
            assert image['width'] >= 319, row
        assert image['left'] >= brand['rect']['left'] - 1
        assert image['right'] <= brand['rect']['right'] + 1
        overlap_x = min(image['right'], title['right']) - max(image['left'], title['left'])
        overlap_y = min(image['bottom'], title['bottom']) - max(image['top'], title['top'])
        assert overlap_x <= 0 or overlap_y <= 0, row
only = json.loads((root / 'docs/design-reference/verification-2026-10-13/branding-0327/logo-only-metrics.json').read_text())
assert len(only) == 10
for row in only:
    assert not row['overflow']
    assert len(row['brands']) == 2
    for brand in row['brands']:
        assert not brand['title']
        assert brand['image']['width'] >= 319
        assert abs(brand['image']['width'] / brand['image']['height'] - 400 / 120) < 0.02
print('BRANDING27_LAYOUT_OK: 30 browser cases, 60 header/footer checks')
