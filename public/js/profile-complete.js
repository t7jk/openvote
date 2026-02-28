/* globals openvoteProfileComplete */
( function () {
    'use strict';

    document.querySelectorAll( '.openvote-profile-complete' ).forEach( function ( container ) {
        var restUrl = container.dataset.restUrl;
        var nonce   = container.dataset.nonce;
        var inputs  = container.querySelectorAll( '.openvote-profile-field__input' );
        var btn     = container.querySelector( '.openvote-profile-complete__btn' );
        var msgBox  = container.querySelector( '.openvote-profile-complete__message' );

        if ( ! restUrl || ! nonce || ! btn ) return;

        // Włącz przycisk tylko gdy wszystkie pola są wypełnione.
        function checkAllFilled() {
            var allFilled = true;
            inputs.forEach( function ( input ) {
                if ( ! input.value.trim() ) {
                    allFilled = false;
                }
            } );
            btn.disabled = ! allFilled;
        }

        inputs.forEach( function ( input ) {
            input.addEventListener( 'input', checkAllFilled );
            input.addEventListener( 'change', checkAllFilled );
        } );

        btn.addEventListener( 'click', function () {
            // Zbierz wartości.
            var fields = {};
            inputs.forEach( function ( input ) {
                var field = input.dataset.field;
                if ( field ) {
                    fields[ field ] = input.value.trim();
                }
            } );

            // Walidacja e-mail po stronie klienta.
            if ( fields.email && ! /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test( fields.email ) ) {
                showMessage( msgBox, 'Podaj prawidłowy adres e-mail.', 'error' );
                return;
            }

            btn.disabled = true;
            showMessage( msgBox, 'Zapisywanie…', 'info' );

            fetch( restUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce':   nonce,
                },
                body: JSON.stringify( { fields: fields } ),
            } )
            .then( function ( res ) { return res.json(); } )
            .then( function ( data ) {
                if ( data.success ) {
                    showMessage( msgBox, 'Dane zostały zapisane. Strona zostanie odświeżona…', 'success' );
                    setTimeout( function () {
                        window.location.reload();
                    }, 1200 );
                } else {
                    showMessage( msgBox, data.message || 'Wystąpił błąd. Spróbuj ponownie.', 'error' );
                    btn.disabled = false;
                }
            } )
            .catch( function () {
                showMessage( msgBox, 'Wystąpił błąd sieci. Spróbuj ponownie.', 'error' );
                btn.disabled = false;
            } );
        } );
    } );

    // ── Helpers ──────────────────────────────────────────────────────────────

    function showMessage( box, text, type ) {
        if ( ! box ) return;
        box.className = 'openvote-profile-complete__message openvote-profile-complete__message--' + type;
        box.textContent = text;
        box.style.display = 'block';
    }

} )();
