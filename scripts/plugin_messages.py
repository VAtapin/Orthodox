"""Extract and compile bundled Russian catalogs for the three standalone plugins."""
from pathlib import Path
import hashlib
import json
import re

from compile_po import compile_mo, read_catalog

ROOT = Path(__file__).resolve().parent.parent
PLUGINS = {
    'georg-kloster-prihod-core': 'gkpc-blocks',
    'georg-kloster-connect-hub': 'gkps-blocks',
    'georg-kloster-prihod-schedule': 'gkpsch-blocks',
}


def source_messages(domain: str, javascript_only: bool = False) -> dict[str, list[str]]:
    messages: dict[str, list[str]] = {}
    for path in sorted((ROOT / domain).rglob('*')):
        if path.suffix not in (('.js',) if javascript_only else ('.php', '.js')):
            continue
        # These plugins use singular, literal gettext calls without contexts.
        pattern = r"\b(?:__|_e|esc_html__|esc_attr__|esc_html_e|esc_attr_e)\(\s*(['\"])((?:\\.|(?!\1).)*?)\1\s*,\s*'" + re.escape(domain) + r"'\s*\)"
        for match in re.finditer(pattern, path.read_text(encoding='utf-8')):
            message = match[2].replace("\\'", "'").replace('\\"', '"').replace('\\\\', '\\')
            messages.setdefault(message, []).append(path.relative_to(ROOT / domain).as_posix())
    if not javascript_only:
        entry = ROOT / domain / f'{domain}.php'
        for message in re.findall(r'^\s*\* (?:Plugin Name|Description): (.+)$', entry.read_text(encoding='utf-8'), re.MULTILINE):
            messages.setdefault(message.strip(), []).append(entry.name)
    return messages


def compile_plugins( only: str | None = None ) -> None:
    for domain, handle in PLUGINS.items():
        if only and domain != only:
            continue
        folder = ROOT / domain / 'languages'
        folder.mkdir(exist_ok=True)
        expected = source_messages(domain)
        js = source_messages(domain, javascript_only=True)
        locales = ['ru_RU', 'de_DE'] if domain == 'georg-kloster-connect-hub' else ['ru_RU']
        for locale in locales:
            path = folder / f'{domain}-{locale}.po'
            messages = read_catalog(path)
            assert set(messages) == set(expected) | {''}, (domain, locale, 'Missing or unused translations', set(expected) - set(messages), set(messages) - set(expected) - {''})
            path.with_suffix('.mo').write_bytes(compile_mo(messages))
            plural = 'nplurals=2; plural=(n != 1);' if locale == 'de_DE' else 'nplurals=3; plural=(n%10==1 && n%100!=11 ? 0 : n%10>=2 && n%10<=4 && (n%100<12 || n%100>14) ? 1 : 2);'
            jed = {'domain': domain, 'locale_data': {'messages': {
                '': {'domain': domain, 'lang': locale, 'plural-forms': plural},
                **{key: [messages[key]] for key in sorted(js)},
            }}}
            for suffix in (handle, hashlib.md5(b'assets/blocks.js').hexdigest()):
                (folder / f'{domain}-{locale}-{suffix}.json').write_text(json.dumps(jed, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')
        header = 'Content-Type: text/plain; charset=UTF-8\nX-Domain: ' + domain + '\n'
        pot = ['msgid ""\nmsgstr ' + json.dumps(header)]
        for key, paths in sorted(expected.items()):
            pot.append('#: ' + ' '.join(sorted(set(paths))) + '\nmsgid ' + json.dumps(key, ensure_ascii=False) + '\nmsgstr ""')
        (folder / f'{domain}.pot').write_text('\n\n'.join(pot) + '\n', encoding='utf-8')
        print(f'{domain}: {len(expected)} PHP/JS messages, {len(js)} editor messages.')


if __name__ == '__main__':
    compile_plugins()
