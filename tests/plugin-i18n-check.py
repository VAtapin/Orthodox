"""Check all standalone plugin Russian PHP and editor translations."""
from pathlib import Path
import argparse
import gettext
import hashlib
import json
import re
import sys

ROOT = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(ROOT / 'scripts'))
from compile_po import read_catalog
from plugin_messages import PLUGINS, source_messages, plugin_folder

parser = argparse.ArgumentParser()
parser.add_argument('--only', choices=list(PLUGINS))
selected = parser.parse_args().only
count = 0
for domain, handle in PLUGINS.items():
    if selected and domain != selected:
        continue
    # Executable UI sources must use English gettext keys. Authored demo text
    # and multilingual calendar input patterns live in separate data files.
    for path in plugin_folder(domain).rglob('*'):
        if path.suffix in ('.php', '.js', '.css'):
            assert not re.search('[А-Яа-яЁё]', path.read_text(encoding='utf-8')), (domain, path, 'Non-English executable source')
    folder = plugin_folder(domain) / 'languages'
    for locale in (['ru_RU', 'de_DE'] if domain == 'georg-kloster-connect-hub' else ['ru_RU']):
        path = folder / f'{domain}-{locale}.po'
        source = read_catalog(path)
        with path.with_suffix('.mo').open('rb') as stream:
            compiled = gettext.GNUTranslations(stream)._catalog
        assert source == compiled, (domain, 'PO/MO mismatch')
        expected = source_messages(domain)
        assert set(source) == set(expected) | {''}, (domain, 'Missing/unused messages')
        for key in expected:
            value = source[key]
            assert value.strip(), (domain, key, 'Empty translation')
            assert re.findall(r'%(?:\d+\$)?[dsf]', key) == re.findall(r'%(?:\d+\$)?[dsf]', value), (domain, key, 'Formatting placeholders changed')
            assert locale != 'ru_RU' or re.search('[А-Яа-яЁё]', value) or key in ('Telegram', 'WhatsApp', 'Facebook', 'Instagram', 'VK', 'OK'), (domain, key, 'Non-Russian UI')
            count += 1
        javascript = source_messages(domain, javascript_only=True)
        for suffix in (handle, hashlib.md5(b'assets/blocks.js').hexdigest()):
            jed = json.loads((folder / f'{domain}-{locale}-{suffix}.json').read_text(encoding='utf-8'))
            messages = jed['locale_data']['messages']
            assert jed['domain'] == domain and messages['']['lang'] == locale
            assert set(messages) == set(javascript) | {''}, (domain, 'Editor coverage mismatch')
            for key in javascript:
                assert messages[key] == [source[key]], (domain, key, 'PHP/JS translations differ')

print(f'Plugin catalogs passed: {count} translated messages across bundled PHP and editor catalogs.')
