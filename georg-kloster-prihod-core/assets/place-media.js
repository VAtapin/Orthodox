(function () {
    'use strict';
    document.querySelectorAll('[data-gkpc-media]').forEach(function (group) {
        var field = group.getAttribute('data-gkpc-media');
        var input = group.querySelector('input');
        var preview = group.querySelector('.gkpc-media-preview');
        group.querySelector('[data-gkpc-media-choose]').addEventListener('click', function () {
            var frame = wp.media({ title: gkpcPlaceMedia[field], button: { text: gkpcPlaceMedia.use }, library: { type: 'image' }, multiple: field === 'photos' });
            frame.on('open', function () { input.value.split(',').filter(Boolean).forEach(function (id) { var attachment = wp.media.attachment(id); attachment.fetch(); frame.state().get('selection').add(attachment); }); });
            frame.on('select', function () {
                var images = frame.state().get('selection').toJSON().slice(0, field === 'logo' ? 1 : 24);
                input.value = images.map(function (item) { return item.id; }).join(',');
                preview.replaceChildren();
                images.forEach(function (item) {
                    var image = document.createElement('img');
                    image.src = item.sizes && item.sizes.thumbnail ? item.sizes.thumbnail.url : item.url;
                    image.alt = item.alt || '';
                    image.style.cssText = 'width:100px;height:100px;object-fit:contain;margin:4px;';
                    preview.appendChild(image);
                });
            });
            frame.open();
        });
        group.querySelector('[data-gkpc-media-clear]').addEventListener('click', function () { input.value = ''; preview.replaceChildren(); });
    });
}());
