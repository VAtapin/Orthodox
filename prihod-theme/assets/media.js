jQuery(function ($) {
    function refreshBrand(field) {
        var image = field.find('.prihod-media-preview img').first(), src = image.attr('src');
        var targets = field.find('.prihod-logo-example-image,.prihod-icon-example>span,.prihod-social-example>div').empty();
        if (src) { targets.append($('<img>').attr({src: src, alt: image.attr('alt') || ''})); }
        field.find('.prihod-logo-example-image').css('max-width', field.find('[name="prihod_theme_options[logo_width]"]').val() + 'px');
        field.find('.prihod-logo-example strong').prop('hidden', !!src && field.find('[name="prihod_theme_options[logo_hide_title]"]:checkbox').prop('checked'));
    }
    $('.prihod-branding-field').each(function () { refreshBrand($(this)); }).on('input change', 'input', function () { refreshBrand($(this).closest('.prihod-media-field')); });
    $('.prihod-media-select').on('click', function () {
        var button = $(this), field = button.closest('.prihod-media-field');
        var frame = wp.media({multiple: button.attr('data-multiple') === '1', library: {type: 'image'}});
        frame.on('select', function () {
            var images = frame.state().get('selection').toJSON().slice(0, 12), preview = field.find('.prihod-media-preview').empty();
            field.children('input[type="hidden"]').first().val(images.map(function (image) { return image.id; }).join(','));
            field.find('[name="prihod_theme_options[logo_removed]"]').val('0');
            images.forEach(function (image) { $('<img>').attr({src: field.is('[data-branding]') ? image.url : (image.sizes && image.sizes.thumbnail ? image.sizes.thumbnail.url : image.url), alt: image.alt || ''}).appendTo(preview); });
            refreshBrand(field);
        });
        frame.on('open', function () {
            var ids = field.children('input[type="hidden"]').first().val().split(',').filter(Boolean);
            ids.forEach(function (id) { var attachment = wp.media.attachment(id); attachment.fetch(); frame.state().get('selection').add(attachment); });
        });
        frame.open();
    });
    $('.prihod-media-clear').on('click', function () { var field = $(this).closest('.prihod-media-field'); field.children('input[type="hidden"]').first().val('0'); field.find('[name="prihod_theme_options[logo_removed]"]').val('1'); field.find('.prihod-media-preview').empty(); refreshBrand(field); });
});
