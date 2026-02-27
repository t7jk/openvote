/**
 * Blok Gutenberg: evoting/voting-tabs
 * Placeholder w edytorze — treść renderowana dynamicznie po stronie serwera (render.php).
 */
( function ( blocks, element ) {
    var el = element.createElement;

    blocks.registerBlockType( 'evoting/voting-tabs', {
        title: 'Głosowania (zakładki)',
        icon: 'groups',
        category: 'widgets',
        description: 'Wyświetla zakładki: Trwające i Zakończone głosowania. Treść dynamiczna — zależna od zalogowanego użytkownika.',
        supports: {
            html: false,
            multiple: false,
            reusable: false,
        },

        edit: function () {
            return el(
                'div',
                {
                    style: {
                        padding: '24px 20px',
                        background: '#f0f6fc',
                        border: '2px dashed #0073aa',
                        borderRadius: '8px',
                        textAlign: 'center',
                        fontFamily: 'inherit',
                    },
                },
                el( 'span', { style: { fontSize: '2rem' } }, '🗳️' ),
                el(
                    'p',
                    { style: { fontSize: '1.1rem', fontWeight: '700', margin: '8px 0 4px' } },
                    'Blok głosowań'
                ),
                el(
                    'p',
                    { style: { color: '#555', margin: 0, fontSize: '0.9rem' } },
                    'Zakładki „Trwające głosowania" i „Zakończone głosowania" pojawią się na opublikowanej stronie.'
                )
            );
        },

        save: function () {
            return null; // server-side render
        },
    } );
} )( window.wp.blocks, window.wp.element );
