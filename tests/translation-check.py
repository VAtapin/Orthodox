"""Verify bundled catalogs cover UI messages and preserve formatting placeholders."""
from pathlib import Path
import gettext
import re

ROOT = Path(__file__).resolve().parent.parent
count = 0
for folder, domain in [('prihod', 'prihod'), ('georg-kloster-prihod', 'georg-kloster-prihod'), ('extensions/prihod-telegram', 'prihod-telegram')]:
    files = list((ROOT / folder).rglob('*.php')) + list((ROOT / folder).rglob('*.js'))
    if folder == 'extensions/prihod-telegram': files.append(ROOT / 'extensions/common/class-connector.php')
    messages = {match[1] for path in files for match in re.findall(r"(?:__|esc_html__|esc_attr__|esc_html_e|esc_attr_e|_e)\(\s*(['\"])(.*?)\1\s*,\s*'[^']+'", path.read_text(encoding='utf-8'))}
    for locale in ['de_DE', 'pl_PL', 'uk']:
        name = locale if folder == 'georg-kloster-prihod' else f'{domain}-{locale}'
        with (ROOT / folder / 'languages' / f'{name}.mo').open('rb') as stream:
            catalog = gettext.GNUTranslations(stream)
        if folder == 'georg-kloster-prihod':
            assert set(catalog._catalog) == messages | {''}, (folder, locale, 'Unused content remains in theme catalog')
        for message in messages:
            assert message in catalog._catalog, (folder, locale, message)
            assert re.findall(r'%\d*\$?[ds]', message) == re.findall(r'%\d*\$?[ds]', catalog.gettext(message)), (locale, message)
            count += 1
print(f'Checked {count} translated UI messages and their placeholders.')
