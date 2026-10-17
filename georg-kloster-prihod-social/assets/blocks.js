(function (blocks, element, blockEditor, components, serverSideRender, i18n) {
    'use strict';
    var el = element.createElement;
    blocks.registerBlockType('gkps/contact-form', {
        title: i18n.__('Contact form', 'georg-kloster-prihod-social'),
        icon: 'email',
        category: 'widgets',
        edit: function () {
            return el('div', blockEditor.useBlockProps(),
                el(components.Placeholder, {label: i18n.__('Contact form', 'georg-kloster-prihod-social')},
                    el(serverSideRender, {block: 'gkps/contact-form'})
                )
            );
        },
        save: function () { return null; }
    });
}(wp.blocks, wp.element, wp.blockEditor, wp.components, wp.serverSideRender, wp.i18n));
