(function (wp) {
    var el = wp.element.createElement;
    Object.keys(window.prihodBlocks || {}).forEach(function (key) {
        var name = 'prihod/' + key;
        wp.blocks.registerBlockType(name, {
            apiVersion: 3, title: prihodBlocks[key], icon: 'admin-home', category: 'widgets',
            attributes: {view: {type: 'string', default: 'week'}, location: {type: 'integer', default: 0}, limit: {type: 'integer', default: 5}},
            edit: function (props) {
                return el('div', wp.blockEditor.useBlockProps(),
                    el(wp.blockEditor.InspectorControls, {}, el(wp.components.PanelBody, {title: wp.i18n.__('Настройки', 'prihod')},
                        key === 'schedule' && el(wp.components.SelectControl, {label: wp.i18n.__('Период', 'prihod'), value: props.attributes.view, options: [{label: wp.i18n.__('Неделя', 'prihod'), value: 'week'}, {label: wp.i18n.__('Месяц', 'prihod'), value: 'month'}], onChange: function (value) {props.setAttributes({view: value});}}),
                        key.indexOf('service') !== -1 || key === 'schedule' ? el(wp.components.TextControl, {label: wp.i18n.__('ID места (0 — все)', 'prihod'), type: 'number', min: 0, value: props.attributes.location, onChange: function (value) {props.setAttributes({location: Math.max(0, parseInt(value, 10) || 0)});}}) : null,
                        key === 'next-services' && el(wp.components.RangeControl, {label: wp.i18n.__('Количество', 'prihod'), min: 1, max: 50, value: props.attributes.limit, onChange: function (value) {props.setAttributes({limit: value});}}))),
                    el(wp.serverSideRender, {block: name, attributes: props.attributes}));
            }, save: function () {return null;}
        });
    });
})(window.wp);
