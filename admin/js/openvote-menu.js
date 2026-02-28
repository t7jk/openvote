/**
 * EP-RWL: dla użytkowników będących tylko Koordynatorem wyłącza (kursywa, brak kliku)
 * pozycje podmenu: Głosowania, Grupy, Konfiguracja — zakładka Koordynatorzy pozostaje aktywna.
 */
(function () {
    'use strict';
    if ( typeof window.openvoteMenu === 'undefined' || ! Array.isArray( window.openvoteMenu.disableSubmenuSlugs ) || window.openvoteMenu.disableSubmenuSlugs.length === 0 ) {
        return;
    }
    var slugs = window.openvoteMenu.disableSubmenuSlugs;
    document.addEventListener( 'DOMContentLoaded', function () {
        var links = document.querySelectorAll( '#adminmenu .wp-submenu a[href*="page=openvote"]' );
        links.forEach( function (a) {
            var href = a.getAttribute( 'href' ) || '';
            for ( var i = 0; i < slugs.length; i++ ) {
                var re = new RegExp( 'page=' + slugs[i].replace( /[.*+?^${}()|[\]\\]/g, '\\$&' ) + '(?:&|$)' );
                if ( re.test( href ) ) {
                    a.classList.add( 'openvote-submenu-disabled' );
                    break;
                }
            }
        } );
    } );
})();
