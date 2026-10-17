"""Check empty and populated category archives measured in a browser."""
import json
import sys
from pathlib import Path

cases = json.loads(Path(sys.argv[1]).read_text(encoding="utf-8"))
assert len(cases) >= 3
assert any(case["viewport"] <= 600 for case in cases)
assert any(case["empty"] for case in cases)
assert any(not case["empty"] for case in cases)
for case in cases:
    label = f'{case["url"]} at {case["viewport"]}px'
    assert case["heading"] and "<span>" not in case["heading"], label
    assert case["body_overflow"] <= 1, f"Horizontal overflow: {label}"
    if case["empty"]:
        assert case["empty_message"] and case["card_count"] == 0, label
    else:
        assert case["card_count"] > 0 and case["grid_columns"] >= 1, label
        assert case["card_min_width"] > 0 and case["card_max_right"] <= case["viewport"] + 1, label

print(f"Passed {len(cases)} category archive layout cases.")
