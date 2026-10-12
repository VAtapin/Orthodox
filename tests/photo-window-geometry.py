"""Validate browser measurements from the photo window regression scenarios."""
from pathlib import Path
import json

root = Path(__file__).resolve().parent.parent
proof = root / 'docs/design-reference/verification-2026-10-12'
home = json.loads((proof / 'photo320-metrics.json').read_text())
inner = json.loads((proof / 'photo320-inner-metrics.json').read_text())
assert len(home) == 10 and len(inner) == 80
for state in home:
    assert state['w'] in (1910, 390) and not state['overflow']
    for kind in ('portrait', 'landscape'):
        image = state[kind]
        assert image['nw'] >= 1000 and image['nh'] > 0
        assert image['fit'] == 'contain'
        assert abs(image['imgH'] - image['h']) < 1
        assert abs(image['imgW'] - image['w']) < 1
        assert image['h'] >= 500 and image['w'] < state['w']
    if state['url'].endswith('=blue'):
        assert all(abs(image['h'] - 85) < 1 for image in state['thumbs'])
for state in inner:
    assert state['nw'] > 0 and state['fit'] == 'contain'
    assert abs(state['boxH'] - state['imgH']) < 1
print('Verified 10 home layouts, 20 full photographs and 80 inner-page windows.')
