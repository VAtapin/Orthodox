(function () {
    'use strict';
    var choose = document.getElementById('gkch-rule-choose-image');
    if (!choose) { return; }
    var image = document.getElementById('gkch-rule-image');
    var name = document.getElementById('gkch-rule-image-name');
    choose.addEventListener('click', function () {
        var frame = wp.media({ title: gkchPublicationsMedia.title, button: { text: gkchPublicationsMedia.button }, library: { type: 'image/jpeg' }, multiple: false });
        frame.on('select', function () {
            var selected = frame.state().get('selection').first().toJSON();
            if (selected.mime !== 'image/jpeg') { return; }
            image.value = selected.id;
            name.textContent = selected.title || selected.filename;
        });
        frame.open();
    });
    document.getElementById('gkch-rule-clear-image').addEventListener('click', function () { image.value = '0'; name.textContent = ''; });
    var frequency = document.getElementById('gkch-rule-frequency');
    function update() {
        document.querySelectorAll('[data-gkch-frequency]').forEach(function (row) { row.hidden = row.getAttribute('data-gkch-frequency') !== frequency.value; });
    }
    frequency.addEventListener('change', update);
    update();
}());
