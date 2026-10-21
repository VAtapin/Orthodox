(function () {
    'use strict';
    document.querySelectorAll('.gkps-requests form').forEach(function (form, index) {
        var storageKey = 'gkch-request-draft:' + window.location.pathname + ':' + index;
        var notice = new URLSearchParams(window.location.search).get('gkps_request');
        function clearDraft() {
            try { window.sessionStorage.removeItem(storageKey); } catch (ignore) {}
        }
        if (notice === 'sent') clearDraft();
        if (notice === 'error') {
            try {
                var saved = JSON.parse(window.sessionStorage.getItem(storageKey) || 'null');
                if (saved && typeof saved === 'object') {
                    ['name', 'email', 'subject', 'subject_other', 'body'].forEach(function (name) {
                        var field = form.elements.namedItem(name);
                        if (field && typeof saved[name] === 'string') field.value = saved[name];
                    });
                    var consent = form.elements.namedItem('consent');
                    if (consent && typeof saved.consent === 'boolean') consent.checked = saved.consent;
                }
            } catch (ignore) {}
            clearDraft();
        }
        form.addEventListener('submit', function () {
            var saved = {};
            ['name', 'email', 'subject', 'subject_other', 'body'].forEach(function (name) {
                var field = form.elements.namedItem(name);
                if (field) saved[name] = field.value;
            });
            var consent = form.elements.namedItem('consent');
            saved.consent = !!(consent && consent.checked);
            try { window.sessionStorage.setItem(storageKey, JSON.stringify(saved)); } catch (ignore) {}
        });
        var topic = form.querySelector('[data-request-topic]');
        var custom = form.querySelector('[data-request-custom]');
        if (!topic || !custom) return;
        var input = custom.querySelector('input');
        var help = form.querySelector('.gkps-topic-help');
        function update() {
            var other = topic.value === '__other';
            custom.hidden = !other;
            input.disabled = !other;
            input.required = other;
            if (help) help.hidden = !other;
        }
        topic.addEventListener('change', update);
        update();
    });
})();
