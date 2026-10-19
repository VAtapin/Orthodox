(function (blocks, element, blockEditor, components, serverSideRender, i18n) {
    'use strict';
    var el = element.createElement;
    ['gkch/form', 'gkps/contact-form'].forEach(function (name) {
        blocks.registerBlockType(name, {
            apiVersion: 3,
            title: i18n.__('Form', 'georg-kloster-connect-hub'),
            icon: 'email',
            category: 'widgets',
            attributes: {formId: {type: 'integer', default: 0}},
            supports: {inserter: name === 'gkch/form'},
            edit: function (props) {
                return el('div', blockEditor.useBlockProps(),
                    el(blockEditor.InspectorControls, {},
                        el(components.PanelBody, {title: i18n.__('Form', 'georg-kloster-connect-hub')},
                            el(components.SelectControl, {
                                label: i18n.__('Select a form', 'georg-kloster-connect-hub'),
                                value: props.attributes.formId,
                                options: window.gkchForms || [],
                                onChange: function (value) { props.setAttributes({formId: parseInt(value, 10) || 0}); }
                            })
                        )
                    ),
                    el(serverSideRender, {block: name, attributes: props.attributes})
                );
            },
            save: function () { return null; }
        });
    });
}(wp.blocks, wp.element, wp.blockEditor, wp.components, wp.serverSideRender, wp.i18n));
