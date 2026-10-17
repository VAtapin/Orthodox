"""Check measured WordPress comment form layout at desktop and mobile widths."""
import json
import sys
from pathlib import Path

cases = json.loads(Path(sys.argv[1]).read_text(encoding="utf-8"))
assert len(cases) >= 2
assert any(case["viewport"] <= 600 for case in cases)
assert any(case["viewport"] > 900 for case in cases)
for case in cases:
    label = f'{case["url"]} at {case["viewport"]}px'
    assert case["form_width"] > 0 and case["textarea_width"] >= case["form_width"] - 2, label
    assert case["textarea_right"] <= case["comments_right"] + 1, label
    assert case["label_above_textarea"], label
    assert case["submit_height"] >= 44 and case["submit_background"] != "rgba(0, 0, 0, 0)", label
    assert case["action"].endswith("/wp-comments-post.php") and case["comment_required"], label
    assert case["post_id_present"] and case["cancel_reply_present"], label
    assert case["body_overflow"] <= 1, label

print(f"Passed {len(cases)} WordPress comment form layout cases.")
