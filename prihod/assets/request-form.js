(function () {
    'use strict';
    document.querySelectorAll('.prihod-requests form').forEach(function (form) {
        var topic = form.querySelector('[data-request-topic]');
        var custom = form.querySelector('[data-request-custom]');
        if (!topic || !custom) return;
        var input = custom.querySelector('input');
        var help = form.querySelector('.prihod-topic-help');
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
