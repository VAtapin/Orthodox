"""Extract this theme's gettext strings and native WordPress JSON contexts."""
from pathlib import Path
import json
import re

ROOT = Path(__file__).resolve().parent.parent
THEME = ROOT / 'georg-kloster-prihod'


def theme_messages() -> dict[str, list[str]]:
    messages: dict[str, list[str]] = {}

    def add(message: str, path: Path, context: str = '') -> None:
        key = context + '\x04' + message if context else message
        messages.setdefault(key, []).append(path.relative_to(THEME).as_posix())

    for path in sorted(THEME.rglob('*')):
        if path.suffix in ('.php', '.js'):
            text = path.read_text(encoding='utf-8')
            for match in re.finditer(r"(?:__|esc_html__|esc_attr__|esc_html_e|esc_attr_e|_e)\(\s*(['\"])(.*?)\1\s*,\s*'georg-kloster-prihod'", text):
                add(match[2], path)
            for title in re.findall(r'Template Name: ([^\r\n*]+)', text):
                add(title.strip(), path)
        elif path.name == 'theme.json' or path.parent == THEME / 'styles' and path.suffix == '.json':
            data = json.loads(path.read_text(encoding='utf-8'))
            if 'title' in data:
                add(data['title'], path, 'Style variation name')
            for field, context in [('templateParts', 'Template part name'), ('customTemplates', 'Custom template name')]:
                for item in data.get(field, []):
                    add(item['title'], path, context)
            for group, field, context in [('color', 'palette', 'Color name'), ('typography', 'fontFamilies', 'Font family name'), ('typography', 'fontSizes', 'Font size name')]:
                for item in data.get('settings', {}).get(group, {}).get(field, []):
                    add(item['name'], path, context)
    return messages


def write_pot() -> None:
    header = 'Project-Id-Version: Georg-Kloster Prihod\nContent-Type: text/plain; charset=UTF-8\nMIME-Version: 1.0\nContent-Transfer-Encoding: 8bit\nX-Domain: georg-kloster-prihod\n'
    blocks = ['msgid ""\nmsgstr ' + json.dumps(header)]
    for key, paths in sorted(theme_messages().items()):
        block = '#: ' + ' '.join(sorted(set(paths))) + '\n'
        if '\x04' in key:
            context, key = key.split('\x04')
            block += 'msgctxt ' + json.dumps(context) + '\n'
        blocks.append(block + 'msgid ' + json.dumps(key, ensure_ascii=False) + '\nmsgstr ""')
    (THEME / 'languages/georg-kloster-prihod.pot').write_text('\n\n'.join(blocks) + '\n', encoding='utf-8')


def compile_theme() -> None:
    from compile_po import compile_mo, read_catalog
    write_pot()
    expected = set(theme_messages()) | {''}
    for locale in ['ru_RU', 'de_DE', 'pl_PL', 'uk']:
        path = THEME / 'languages' / f'{locale}.po'
        messages = read_catalog(path)
        assert set(messages) == expected, (locale, 'Catalog differs from theme sources', expected - set(messages), set(messages) - expected)
        path.with_suffix('.mo').write_bytes(compile_mo(messages))
