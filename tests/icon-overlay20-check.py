"""Check measured service-icon lightbox behavior across responsive layouts."""

import itertools
import json
import struct
from pathlib import Path


ROOT = Path(__file__).resolve().parent.parent
PROOF = ROOT / 'docs/design-reference/verification-2026-10-13/icon-overlay-rc20'
ROWS = json.loads((PROOF / 'metrics.json').read_text(encoding='utf-8'))
EXPECTED = set(itertools.product(
    ('classic', 'blue', 'hospitality', 'editorial', 'tradition'),
    ('week', 'month', 'list'),
    (1440, 390),
))
assert len(ROWS) == 30
assert {(row['scheme'], row['view'], row['width']) for row in ROWS} == EXPECTED


def jpeg_size(path):
    data = path.read_bytes()
    assert data[:2] == b'\xff\xd8', path
    offset = 2
    while offset < len(data):
        assert data[offset] == 255, path
        marker = data[offset + 1]
        size = int.from_bytes(data[offset + 2:offset + 4], 'big')
        if marker in (0xc0, 0xc1, 0xc2):
            height, width = struct.unpack('>HH', data[offset + 5:offset + 9])
            return width, height
        offset += size + 2
    raise AssertionError(path)


for row in ROWS:
    assert not row['overflow'] and row['upcoming'] >= 1, row
    assert len(row['icons']) >= 2, row
    for icon in row['icons']:
        assert icon['loaded'] and icon['alt'], row
        assert icon['button'] and icon['aligned'] and icon['focusable'], row
        assert icon['opacity'] == '0' and icon['alt'] in icon['label'], row
    width, height = jpeg_size(PROOF / f"{row['scheme']}-{row['view']}-{row['width']}.jpg")
    assert row['width'] - 20 <= width <= row['width'] and height > 500, row

for action in ('mouse', 'keyboard'):
    width, height = jpeg_size(PROOF / f'viewer-{action}.jpg')
    assert width >= 1000 and height >= 500, action

print('ICON_OVERLAY20_OK: 30 loaded cases, five themes, three views, responsive native triggers')
