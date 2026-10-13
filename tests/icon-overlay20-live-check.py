"""Validate the seven post-install browser measurements and screenshots."""

import json
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
PROOF = ROOT / 'docs/design-reference/verification-2026-10-14/icon-overlay-rc20-live'
ROWS = json.loads((PROOF / 'metrics.json').read_text(encoding='utf-8'))
EXPECTED = {(scheme, 'week') for scheme in ('classic', 'blue', 'hospitality', 'editorial', 'tradition')}
EXPECTED.update({('classic', 'month'), ('classic', 'list')})
assert len(ROWS) == 7
assert {(row['scheme'], row['view']) for row in ROWS} == EXPECTED


def jpeg_size(path):
    data = path.read_bytes()
    assert data[:2] == b'\xff\xd8', path
    offset = 2
    while offset < len(data):
        assert data[offset] == 255, path
        marker = data[offset + 1]
        size = int.from_bytes(data[offset + 2:offset + 4], 'big')
        if marker in (0xc0, 0xc1, 0xc2):
            return int.from_bytes(data[offset + 7:offset + 9], 'big'), int.from_bytes(data[offset + 5:offset + 7], 'big')
        offset += size + 2
    raise AssertionError(path)


for row in ROWS:
    assert row['width'] == 390 and not row['overflow'], row
    assert len(row['icons']) >= 2, row
    for icon in row['icons']:
        assert icon['loaded'] and icon['aligned'] and icon['width'] >= 44, row
        assert icon['opacity'] == '0' and icon['alt'] in icon['label'], row
    width, height = jpeg_size(PROOF / f"{row['scheme']}-{row['view']}-390.jpg")
    assert 370 <= width <= 390 and height > 500, row

for action in ('mouse', 'keyboard'):
    width, height = jpeg_size(PROOF / f'viewer-{action}-390.jpg')
    assert 370 <= width <= 390 and height >= 500, action

print('ICON_OVERLAY20_LIVE_OK: seven mobile cases, native viewer mouse and keyboard')
