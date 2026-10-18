const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync(require('node:path').join(__dirname, '../georg-kloster-prihod-schedule/assets/schedule-icons.js'), 'utf8');

async function check(selection, expected, available = true) {
    const host = {dataset: {}};
    const label = {
        dataset: {color: selection, colorOnly: '1', gkpschFeast: 'https://example.org/month', calendarDate: '2026-10-24'},
        hidden: true, textContent: '', closest: () => host,
    };
    let requests = 0;
    let extraMarks = 0;
    vm.runInNewContext(source, {
        document: {
            addEventListener: (event, callback) => callback(),
            querySelectorAll: selector => selector === '[data-gkpsch-feast]' ? [label] : [],
            createElement: () => { extraMarks++; throw Error('A colour-only card must not add a Typikon mark.'); },
        },
        fetch: async () => {
            requests++;
            return {ok: available, json: async () => ({days: {'2026-10-24': {color: 'green', typikon: {label: 'Mark', url: 'https://example.org/mark.png'}}}})};
        },
    });
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(host.dataset.vestmentColor, expected);
    assert.equal(requests, selection === 'auto' ? 1 : 0);
    assert.equal(extraMarks, 0);
    assert.equal(label.hidden, true);
    assert.equal(label.textContent, '');
}
(async () => {
    await check('auto', 'green');
    await check('blue', 'blue');
    await check('', undefined);
    await check('auto', undefined, false);
    console.log('Compact service colours: automatic, manual, neutral and unavailable API passed.');
})().catch(error => { console.error(error); process.exitCode = 1; });
