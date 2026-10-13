"""Check actual browser geometry for the shared question form and portrait."""
import json
from pathlib import Path

root = Path(__file__).resolve().parent.parent
rows = json.loads((root / 'docs/design-reference/verification-2026-10-13/question-0328/metrics.json').read_text(encoding='utf-8'))
assert len(rows) == 15
assert len({(r['scheme'], r['width']) for r in rows}) == 15
for row in rows:
    assert not row['overflow'] and row['complete'], row
    container, photo, copy, panel = (row[k] for k in ('container', 'photo', 'copy', 'panel'))
    assert photo['left'] >= container['left'] - 1 and photo['right'] <= container['right'] + 1, row
    overlap_x = min(photo['right'], copy['right']) - max(photo['left'], copy['left'])
    overlap_y = min(photo['bottom'], copy['bottom']) - max(photo['top'], copy['top'])
    assert overlap_x <= 0 or overlap_y <= 0, row
    inset = 16 if row['width'] == 390 else 24
    assert row['padding'] == f'{inset}px', row
    if row['width'] > 700:
        assert abs(photo['right'] - container['right']) <= 1, row
    assert len(row['fields']) == 4, row
    for field in row['fields']:
        left, right = field['left'] - panel['left'], panel['right'] - field['right']
        assert left >= inset and right >= inset and abs(left - right) <= 1, row
    assert row['button']['left'] - panel['left'] >= inset, row
    assert panel['bottom'] - row['button']['bottom'] >= inset, row
print('QUESTION28_LAYOUT_OK: 15 browser cases across five themes')
