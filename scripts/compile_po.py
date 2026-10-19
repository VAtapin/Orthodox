"""Compile simple gettext PO files to MO using the Python standard library."""

import argparse
import ast
from pathlib import Path
import struct


def read_catalog(path: Path) -> dict[str, str]:
    messages: dict[str, str] = {}
    key = value = context = ""
    field = None

    def save() -> None:
        if field is not None and value:
            messages[(context + "\x04" if context else "") + key] = value

    for line in path.read_text(encoding="utf-8").splitlines() + [""]:
        if line.startswith("msgctxt "):
            save()
            key, value, context, field = "", "", ast.literal_eval(line[8:]), "context"
        elif line.startswith("msgid "):
            save()
            key, value, field = ast.literal_eval(line[6:]), "", "id"
        elif line.startswith("msgstr "):
            value, field = ast.literal_eval(line[7:]), "str"
        elif line.startswith('"') and field:
            if field == "id":
                key += ast.literal_eval(line)
            elif field == "str":
                value += ast.literal_eval(line)
            else:
                context += ast.literal_eval(line)
        elif not line.strip():
            save()
            key = value = context = ""
            field = None
    return messages


def compile_mo(messages: dict[str, str]) -> bytes:
    keys = sorted(messages)
    ids = [key.encode("utf-8") for key in keys]
    values = [messages[key].encode("utf-8") for key in keys]
    count = len(keys)
    id_offset = 28
    value_offset = id_offset + 8 * count
    strings_offset = value_offset + 8 * count
    id_table = bytearray()
    value_table = bytearray()
    data = bytearray()
    for entry in ids:
        id_table += struct.pack("<II", len(entry), strings_offset + len(data))
        data += entry + b"\0"
    for entry in values:
        value_table += struct.pack("<II", len(entry), strings_offset + len(data))
        data += entry + b"\0"
    header = struct.pack("<7I", 0x950412DE, 0, count, id_offset, value_offset, 0, 0)
    return header + id_table + value_table + data


if __name__ == "__main__":
    parser = argparse.ArgumentParser()
    parser.add_argument("source", type=Path)
    args = parser.parse_args()
    args.source.with_suffix(".mo").write_bytes(compile_mo(read_catalog(args.source)))
