"""Check real, read-only browser measurements against neighbouring page layout."""
import json
import sys
from pathlib import Path

cases = json.loads(Path(sys.argv[1]).read_text(encoding="utf-8"))
expected = {(scheme, viewport) for scheme in ("classic", "hospitality", "editorial", "tradition", "blue") for viewport in (1920, 1122, 390)}
assert expected <= {(case["about"]["scheme"], case["about"]["viewport"]) for case in cases}, "Missing scheme/viewport coverage"
for case in cases:
    about, reference = case["about"], case["reference"]
    label = f'{about["scheme"]}/{about["viewport"]}'
    assert not about["overflow"], f"Page overflow: {label}"
    for key in ("main", "header", "footer"):
        actual = about["parts"][key]
        baseline = reference["parts"][key]
        if key == "main":
            actual, baseline = [actual], [baseline]
        assert actual and len(actual) == len(baseline), f"Missing containers: {label}/{key}"
        for measured, neighbour in zip(actual, baseline):
            assert measured["width"] <= about["limit"] + 1, f"Theme width ignored: {label}/{key}"
            assert abs(measured["width"] - neighbour["width"]) <= 1 and abs(measured["x"] - neighbour["x"]) <= 1, f"Neighbour alignment differs: {label}/{key}"
    items = about["timeline"]["items"]
    assert [item["year"] for item in items] == ["2006", "2007", "2011", "2013"], f"History changed: {label}"
    assert all(not item["overflow"] and item["font"] >= 13 for item in items), f"Clipped or reduced timeline text: {label}"
    if about["viewport"] > 900:
        assert max(item["rect"]["y"] for item in items) - min(item["rect"]["y"] for item in items) <= 1, f"Desktop stages not in one row: {label}"
        assert max(item["rect"]["width"] for item in items) - min(item["rect"]["width"] for item in items) <= 1, f"Unequal desktop stages: {label}"
        sidebar = about["sidebar"]
        assert abs(sidebar["width"] - 270) <= 1, f"Sidebar composition changed: {label}"
    else:
        assert about["timeline"]["borderLeft"] != "0px" and about["timeline"]["borderTop"] == "0px", f"Missing vertical history line: {label}"
        assert all(items[i + 1]["rect"]["y"] >= items[i]["rect"]["y"] + items[i]["rect"]["height"] for i in range(3)), f"Mobile stages overlap: {label}"
print(f"Passed {len(cases)} real browser layout cases.")
