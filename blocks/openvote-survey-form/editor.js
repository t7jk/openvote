( function ( blocks, element ) {
    'use strict';

    var el  = element.createElement;
    var RawHTML = element.RawHTML;

    blocks.registerBlockType( 'openvote/survey-form', {
        title:       'Ankiety (E-głosowania)',
        icon:        'feedback',
        category:    'widgets',
        description: 'Wyświetla publiczną stronę ankiet z aktywnymi ankietami.',

        edit: function () {
            return el(
                'div',
                { style: { border: '2px dashed #0073aa', padding: '24px', borderRadius: '4px', textAlign: 'center', background: '#f0f6fc' } },
                el( 'p', { style: { margin: 0, fontWeight: 'bold', fontSize: '1.1em', color: '#0073aa' } }, '📋 Blok Ankiet' ),
                el( 'p', { style: { margin: '8px 0 0', color: '#666' } }, 'Formularz ankiety jest wyświetlany dla odwiedzających na stronie publicznej.' )
            );
        },

        save: function () {
            return null; // Server-side rendering
        },
    } );

} ( window.wp.blocks, window.wp.element ) );
