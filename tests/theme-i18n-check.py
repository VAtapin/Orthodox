"""Guard English theme sources, native catalogs and the release preview."""
from pathlib import Path
import gettext
import re
import struct
import sys

ROOT = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(ROOT / 'scripts'))
from compile_po import read_catalog
from theme_messages import THEME, theme_messages

for path in THEME.rglob('*'):
    if not path.is_file() or path.suffix in ('.po', '.mo'):
        continue
    if path.suffix in ('.php', '.js', '.css', '.json', '.md', '.txt', '.pot', '.svg'):
        text = path.read_text(encoding='utf-8')
        assert not re.search(r'[\u0400-\u04ff]', text), (path, 'Cyrillic outside translation files')
        assert 'phpcs:ignore' not in text, (path, 'Output warning suppression remains')

expected = set(theme_messages()) | {''}
for locale in ['ru_RU', 'de_DE', 'pl_PL', 'uk']:
    path = THEME / 'languages' / f'{locale}.po'
    source = read_catalog(path)
    with path.with_suffix('.mo').open('rb') as stream:
        compiled = gettext.GNUTranslations(stream)._catalog
    assert source == compiled, (locale, 'PO and MO differ')
    assert set(compiled) == expected, (locale, 'Missing or unused messages')
    assert all(not re.search(r'[\u0400-\u04ff]', key) for key in compiled), (locale, 'Non-English source message')
    assert all(value for value in compiled.values()), (locale, 'Empty translation')

png = (THEME / 'screenshot.png').read_bytes()
assert png[:8] == b'\x89PNG\r\n\x1a\n'
assert struct.unpack('>II', png[16:24]) == (1425, 891), 'Full Classic preview dimensions changed'
pot = (THEME / 'languages/georg-kloster-prihod.pot').read_text(encoding='utf-8')
assert 'msgctxt "Custom template name"' in pot and 'msgctxt "Color name"' in pot
print(f'Theme source/catalog/preview checks passed: {len(expected) - 1} messages in four locales.')
