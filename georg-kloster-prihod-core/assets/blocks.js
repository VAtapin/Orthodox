( function ( blocks, element, serverSideRender, i18n ) {
    'use strict';
    const names = {
        profile: i18n.__( 'Parish profile', 'georg-kloster-prihod-core' ),
        contacts: i18n.__( 'Parish contacts', 'georg-kloster-prihod-core' ),
        clergy: i18n.__( 'Clergy', 'georg-kloster-prihod-core' ),
        places: i18n.__( 'Places', 'georg-kloster-prihod-core' ),
    };
    Object.keys( names ).forEach( function ( key ) {
        const name = 'gkpc/' + key;
        blocks.registerBlockType( name, {
            apiVersion: 3,
            title: names[ key ],
            category: 'widgets',
            edit: function () { return element.createElement( serverSideRender, { block: name } ); },
            save: function () { return null; },
        } );
    } );
} )( wp.blocks, wp.element, wp.serverSideRender, wp.i18n );
