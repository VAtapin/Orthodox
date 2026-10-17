"""Check browser measurements of WordPress featured images beside the sidebar."""
import json
import sys
from pathlib import Path

rows = json.loads(Path(sys.argv[1]).read_text(encoding="utf-8"))
assert rows and any(row["viewport"] > 900 for row in rows)
assert any(row["viewport"] <= 600 for row in rows)
for row in rows:
    image, article = row["image"], row["article"]
    label = f'{row["url"]} at {row["viewport"]}px'
    assert image["width"] > 0 and image["height"] > 0, f"Image missing: {label}"
    assert image["left"] >= article["left"] - 1, f"Image starts outside article: {label}"
    assert image["right"] <= article["right"] + 1, f"Image leaves article: {label}"
    for content_image in row["images"]:
        if content_image["width"] <= 0 or content_image["height"] <= 0:
            continue
        assert content_image["left"] >= article["left"] - 1, f"Content image starts outside article: {label}"
        assert content_image["right"] <= article["right"] + 1, f"Content image leaves article: {label}"
    if row["viewport"] > 900:
        assert image["right"] <= row["sidebar"]["left"] - 1, f"Image overlaps sidebar: {label}"

print(f"Passed {len(rows)} featured-image browser layout cases.")
