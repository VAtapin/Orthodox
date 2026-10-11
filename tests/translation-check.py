"""Verify bundled catalogs cover UI messages and preserve formatting placeholders."""
from pathlib import Path
import gettext
import re

ROOT = Path(__file__).resolve().parent.parent
count = 0
for folder, domain in [('prihod', 'prihod'), ('prihod-theme', 'prihod-theme')]:
    files = list((ROOT / folder).rglob('*.php')) + list((ROOT / folder).rglob('*.js'))
    messages = {match[1] for path in files for match in re.findall(r"(?:__|esc_html__|esc_attr__)\(\s*(['\"])(.*?)\1\s*,\s*'[^']+'", path.read_text(encoding='utf-8'))}
    for locale in ['de_DE', 'pl_PL', 'uk']:
        name = locale if folder == 'prihod-theme' else f'{domain}-{locale}'
        with (ROOT / folder / 'languages' / f'{name}.mo').open('rb') as stream:
            catalog = gettext.GNUTranslations(stream)
        for message in messages:
            assert message in catalog._catalog, (folder, locale, message)
            assert re.findall(r'%\d*\$?[ds]', message) == re.findall(r'%\d*\$?[ds]', catalog.gettext(message)), (locale, message)
            count += 1
print(f'Checked {count} translated UI messages and their placeholders.')
