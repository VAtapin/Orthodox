"""Check all standalone plugin Russian PHP and editor translations."""
from pathlib import Path
import gettext
import hashlib
import json
import re
import sys

ROOT = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(ROOT / 'scripts'))
from compile_po import read_catalog
from plugin_messages import PLUGINS, source_messages

count = 0
for domain, handle in PLUGINS.items():
    folder = ROOT / domain / 'languages'
    path = folder / f'{domain}-ru_RU.po'
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
        assert re.search('[А-Яа-яЁё]', value) or key in ('Telegram', 'WhatsApp'), (domain, key, 'Non-Russian UI')
        count += 1
    javascript = source_messages(domain, javascript_only=True)
    for suffix in (handle, hashlib.md5(b'assets/blocks.js').hexdigest()):
        jed = json.loads((folder / f'{domain}-ru_RU-{suffix}.json').read_text(encoding='utf-8'))
        messages = jed['locale_data']['messages']
        assert jed['domain'] == domain and messages['']['lang'] == 'ru_RU'
        assert set(messages) == set(javascript) | {''}, (domain, 'Editor coverage mismatch')
        for key in javascript:
            assert messages[key] == [source[key]], (domain, key, 'PHP/JS translations differ')

print(f'Plugin Russian catalogs passed: {count} messages, three PHP catalogs and six editor catalogs.')
