( function ( blocks, element ) {
    'use strict';

    var el = element.createElement;

    blocks.registerBlockType( 'openvote/survey-responses', {
        title:       'Zgłoszenia ankiet (Nie spam)',
        icon:        'list-view',
        category:    'widgets',
        description: 'Wyświetla wypełnione odpowiedzi na ankiety ze statusem „Nie spam”.',

        edit: function () {
            return el(
                'div',
                { style: { border: '2px dashed #0073aa', padding: '24px', borderRadius: '4px', textAlign: 'center', background: '#f0f6fc' } },
                el( 'p', { style: { margin: 0, fontWeight: 'bold', fontSize: '1.1em', color: '#0073aa' } }, '📋 Zgłoszenia ankiet (Nie spam)' ),
                el( 'p', { style: { margin: '8px 0 0', color: '#666' } }, 'Na stronie publicznej zostaną wyświetlone zatwierdzone zgłoszenia (oznaczone jako „To nie spam”).' )
            );
        },

        save: function () {
            return null; // Server-side rendering
        },
    } );

} ( window.wp.blocks, window.wp.element ) );
