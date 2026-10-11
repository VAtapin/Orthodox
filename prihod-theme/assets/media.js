jQuery(function ($) {
    $('.prihod-media-select').on('click', function () {
        var button = $(this), field = button.closest('.prihod-media-field');
        var frame = wp.media({multiple: button.attr('data-multiple') === '1', library: {type: 'image'}});
        frame.on('select', function () {
            var images = frame.state().get('selection').toJSON().slice(0, 12), preview = field.find('.prihod-media-preview').empty();
            field.find('input').val(images.map(function (image) { return image.id; }).join(','));
            images.forEach(function (image) { $('<img>').attr({src: image.sizes && image.sizes.thumbnail ? image.sizes.thumbnail.url : image.url, alt: image.alt || ''}).css({width: '100px', height: '100px', objectFit: 'cover', margin: '8px'}).appendTo(preview); });
        });
        frame.open();
    });
    $('.prihod-media-clear').on('click', function () { var field = $(this).closest('.prihod-media-field'); field.find('input').val(''); field.find('.prihod-media-preview').empty(); });
});
