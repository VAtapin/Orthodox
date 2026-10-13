"""Validate actual loaded calendar screenshots and browser measurements."""
import itertools
import json
import struct
from pathlib import Path

root = Path(__file__).resolve().parent.parent
proof = root / 'docs/design-reference/verification-2026-10-13/calendar-rc19'
rows = json.loads((proof / 'metrics.json').read_text(encoding='utf-8'))
expected = set(itertools.product(('classic', 'hospitality', 'editorial', 'tradition', 'blue'), ('week', 'month', 'list'), (1440, 390)))
assert len(rows) == 30 and {(r['scheme'], r['view'], r['width']) for r in rows} == expected

def image_size(path):
    data = path.read_bytes()
    if data.startswith(b'\x89PNG\r\n\x1a\n'):
        return struct.unpack('>II', data[16:24])
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

for row in rows:
    assert not row['overflow'] and row['places'] == 0 and row['preview'] == row['scheme'], row
    assert row['marks'] and all(m['loaded'] and m['alt'] for m in row['marks']), row
    assert len(row['monthUrls']) == 1 and '/schedule-month?' in row['monthUrls'][0], row
    width, height = image_size(proof / f"{row['scheme']}-{row['view']}-{row['width']}.jpg")
    assert row['width'] - 20 <= width <= row['width'] and height > 500, row
    if row['view'] == 'month':
        assert all(m['compact'] == '1' and not m['text'] for m in row['memories']), row
    else:
        assert any('Покров' in m['text'] for m in row['memories']), row
        assert all('Саввы' not in m['text'] and 'Гурия' not in m['text'] for m in row['memories']), row
    if row['view'] == 'week':
        days = {d['date']: d for d in row['days']}
        assert days['2026-10-14']['color'] == 'rgb(178, 34, 34)' and days['2026-10-14']['border'] == 'rgb(52, 155, 213)', row
        for date in ('2026-10-17', '2026-10-18'):
            assert days[date]['color'] == 'rgb(128, 96, 0)' and days[date]['border'] == 'rgb(242, 197, 0)', row
        assert days['2026-10-21']['color'] == 'rgb(168, 32, 38)', row
        assert days['2026-10-25']['border'] == 'rgba(0, 0, 0, 0)', row

mixed = json.loads((proof / 'mixed-colors.json').read_text(encoding='utf-8'))
assert json.loads(mixed['colors']) == ['blue', 'red']
assert mixed['font'] == 'rgb(23, 97, 139)' and mixed['borders'] == 'rgb(52, 155, 213) rgb(203, 48, 53)'
assert not mixed['overflow'] and mixed['neutral']['border'] == 'rgba(0, 0, 0, 0)'
assert 370 <= image_size(proof / 'mixed-colors-390.jpg')[0] <= 390
print('CALENDAR19_LAYOUT_OK: 30 loaded cases, five themes, three views, mixed colors and neutral override')
