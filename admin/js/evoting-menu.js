/**
 * EP-RWL: dla użytkowników będących tylko Koordynatorem wyłącza (kursywa, brak kliku)
 * pozycje podmenu: Głosowania, Grupy, Konfiguracja — zakładka Koordynatorzy pozostaje aktywna.
 */
(function () {
    'use strict';
    if ( typeof window.evotingMenu === 'undefined' || ! Array.isArray( window.evotingMenu.disableSubmenuSlugs ) || window.evotingMenu.disableSubmenuSlugs.length === 0 ) {
        return;
    }
    var slugs = window.evotingMenu.disableSubmenuSlugs;
    document.addEventListener( 'DOMContentLoaded', function () {
        var links = document.querySelectorAll( '#adminmenu .wp-submenu a[href*="page=evoting"]' );
        links.forEach( function (a) {
            var href = a.getAttribute( 'href' ) || '';
            for ( var i = 0; i < slugs.length; i++ ) {
                var re = new RegExp( 'page=' + slugs[i].replace( /[.*+?^${}()|[\]\\]/g, '\\$&' ) + '(?:&|$)' );
                if ( re.test( href ) ) {
                    a.classList.add( 'evoting-submenu-disabled' );
                    break;
                }
            }
        } );
    } );
})();
