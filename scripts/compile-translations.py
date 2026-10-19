"""Compile reviewed UTF-8 TSV into WordPress PO/MO files (standard library only)."""
from pathlib import Path
import argparse
import struct
import json
import hashlib
import re

ROOT = Path(__file__).resolve().parent.parent
parser = argparse.ArgumentParser()
parser.add_argument('--only', choices=['core', 'theme', 'telegram'])
selected = parser.parse_args().only
rows = [line.split('\t') for line in (ROOT / 'scripts/translations.tsv').read_text(encoding='utf-8').splitlines() if line]
assert all(len(row) == 4 for row in rows)
assert len({row[0] for row in rows}) == len(rows), 'Duplicate source message'
for folder, domain in [('prihod', 'prihod'), ('georg-kloster-prihod', 'georg-kloster-prihod'), ('extensions/prihod-telegram', 'prihod-telegram')]:
    if selected and {'prihod': 'core', 'georg-kloster-prihod': 'theme', 'extensions/prihod-telegram': 'telegram'}[folder] != selected:
        continue
    if folder == 'georg-kloster-prihod':
        from theme_messages import compile_theme
        compile_theme()
        continue
    target = ROOT / folder / 'languages'
    target.mkdir(exist_ok=True)
    package_rows = rows
    for index, locale in enumerate(['de_DE', 'pl_PL', 'uk'], 1):
        header = f'Content-Type: text/plain; charset=UTF-8\nLanguage: {locale}\nMIME-Version: 1.0\nContent-Transfer-Encoding: 8bit\n'
        messages = {'': header, **{row[0]: row[index].strip() for row in package_rows}}
        items = sorted((key.encode(), value.encode()) for key, value in messages.items())
        count = len(items)
        key_offset = 28 + count * 16
        value_offset = key_offset + sum(len(key) + 1 for key, _ in items)
        key_table, value_table, keys, values = [], [], b'', b''
        for key, value in items:
            key_table.extend([len(key), key_offset + len(keys)])
            value_table.extend([len(value), value_offset + len(values)])
            keys += key + b'\0'; values += value + b'\0'
        data = struct.pack('<7I', 0x950412de, 0, count, 28, 28 + count * 8, 0, 0)
        data += struct.pack(f'<{count * 2}I', *key_table) + struct.pack(f'<{count * 2}I', *value_table) + keys + values
        name = locale if folder == 'georg-kloster-prihod' else f'{domain}-{locale}'
        (target / f'{name}.mo').write_bytes(data)
        po = '\n\n'.join('msgid ' + json.dumps(key, ensure_ascii=False) + '\nmsgstr ' + json.dumps(value, ensure_ascii=False) for key, value in messages.items()) + '\n'
        (target / f'{name}.po').write_text(po, encoding='utf-8')
        if folder == 'prihod':
            jed = {'domain': domain, 'locale_data': {'messages': {'': {'domain': domain, 'lang': locale}, **{row[0]: [row[index].strip()] for row in rows}}}}
            for suffix in ['prihod-blocks', hashlib.md5(b'assets/blocks.js').hexdigest()]:
                (target / f'{domain}-{locale}-{suffix}.json').write_text(json.dumps(jed, ensure_ascii=False), encoding='utf-8')
print(f'Compiled catalogs for {selected or "Core, theme and Telegram"}. Theme: English source; Russian, German, Polish and Ukrainian catalogs.')
