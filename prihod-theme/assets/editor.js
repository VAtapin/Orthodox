(function (blocks, element, ServerSideRender) {
    Object.keys(prihodThemeBlocks).forEach(function (name) {
        blocks.registerBlockType('prihod-theme/' + name, {
            title: prihodThemeBlocks[name], category: 'theme', icon: 'admin-home',
            supports: {html: false},
            edit: function () { return element.createElement(ServerSideRender, {block: 'prihod-theme/' + name}); },
            save: function () { return null; }
        });
    });
})(wp.blocks, wp.element, wp.serverSideRender);
