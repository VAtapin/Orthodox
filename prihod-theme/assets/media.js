jQuery(function ($) {
    function refreshBrand(field) {
        var image = field.find('.prihod-media-preview img').first(), src = image.attr('src');
        var targets = field.find('.prihod-logo-example-image,.prihod-icon-example>span,.prihod-social-example>div').empty();
        if (src) { targets.append($('<img>').attr({src: src, alt: image.attr('alt') || ''})); }
        var showLogo = field.find('[name="prihod_theme_options[logo_show]"]:checkbox').prop('checked');
        var showTitle = field.find('[name="prihod_theme_options[title_visible]"]:checkbox').prop('checked');
        var example = field.find('.prihod-logo-example'), target = field.find('.prihod-logo-example-image');
        example.css('--prihod-logo-width', Math.max(64, Math.min(320, Number(field.find('[name="prihod_theme_options[logo_width]"]').val()) || 240)) + 'px');
        target.prop('hidden', !showLogo);
        example.find('strong').prop('hidden', !showTitle);
        field.find('.prihod-brand-empty').prop('hidden', showLogo || showTitle);
        var preview = target.find('img');
        function shape() { example.toggleClass('prihod-logo-example-square', this.naturalWidth <= this.naturalHeight * 1.5); }
        preview.on('load', shape).each(function () { if (this.complete) { shape.call(this); } });
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
