const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../georg-kloster-prihod-core/schedule/assets/schedule-admin.js'), 'utf8');
const handlers = {};
const placeHandlers = {};
let focused = 0;
let reported = 0;
let opened = 0;
const place = {
    value: '', dataset: {requiredMessage: 'Выберите место служения перед сохранением.'},
    setCustomValidity(message) { this.message = message; },
    checkValidity() { return !this.message; },
    reportValidity() { reported++; }, focus() { focused++; },
    closest() { return {classList: {remove(name) { assert.equal(name, 'closed'); opened++; }}}; },
    addEventListener(name, fn) { placeHandlers[name] = fn; },
};
const form = {addEventListener(name, fn, capture) { assert.equal(capture, true); handlers[name] = fn; }};
vm.runInNewContext(source, {document: {
    getElementById(id) { return id === 'post' ? form : null; },
    querySelector() { return place; },
    addEventListener(name, fn) { assert.equal(name, 'DOMContentLoaded'); fn(); },
}});
function event(saveButton = true) {
    return {blocked: false, stopped: false,
        target: {closest() { return saveButton; }},
        preventDefault() { this.blocked = true; },
        stopImmediatePropagation() { this.stopped = true; },
    };
}
for (const value of ['', '0']) {
    place.value = value;
    for (const action of ['click', 'submit']) {
        const attempt = event(); handlers[action](attempt);
        assert.equal(attempt.blocked, true);
        assert.equal(attempt.stopped, true);
        assert.equal(place.message, place.dataset.requiredMessage);
        assert.equal(place.value, value);
    }
}
assert.equal(focused, 4); assert.equal(reported, 4); assert.equal(opened, 4);
place.value = '42'; placeHandlers.change();
assert.equal(place.message, '');
for (const action of ['click', 'submit']) {
    const attempt = event(); handlers[action](attempt);
    assert.equal(attempt.blocked, false); assert.equal(attempt.stopped, false);
}
place.value = '';
const unrelated = event(false); handlers.click(unrelated);
assert.equal(unrelated.blocked, false);
console.log('Schedule editor validation: publish/save, keyboard submit, empty/legacy place, focus, correction and unrelated clicks passed.');
