/* globals openvoteSurveyPublic */
( function () {
    'use strict';

    if ( typeof openvoteSurveyPublic === 'undefined' ) return;

    var cfg = openvoteSurveyPublic;

    document.querySelectorAll( '.openvote-survey-form' ).forEach( function ( form ) {
        var surveyId     = form.dataset.surveyId;
        var msgBox       = form.querySelector( '.openvote-survey-form__message' );
        var submitBtns   = form.querySelectorAll( '.openvote-survey-btn' );

        // Przywróć zapisane odpowiedzi z API przy załadowaniu strony (jeśli zalogowany).
        if ( cfg.loggedIn ) {
            fetchMyResponse( surveyId, form );
        }

        form.addEventListener( 'submit', function ( e ) {
            e.preventDefault();

            var clickedBtn = e.submitter || document.activeElement;
            var status     = clickedBtn && clickedBtn.dataset.status ? clickedBtn.dataset.status : 'draft';

            // Zbierz odpowiedzi.
            var answers = {};
            form.querySelectorAll( '[name^="answers["]' ).forEach( function ( input ) {
                var match = input.name.match( /answers\[(\d+)\]/ );
                if ( match ) {
                    answers[ match[1] ] = input.value;
                }
            } );

            showMessage( msgBox, cfg.i18n.saving, 'info' );
            setButtonsDisabled( submitBtns, true );

            fetch( cfg.restUrl + '/surveys/' + surveyId + '/submit', {
                method:  'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce':   cfg.nonce,
                },
                body: JSON.stringify( { status: status, answers: answers } ),
            } )
            .then( function ( res ) { return res.json(); } )
            .then( function ( data ) {
                if ( data.success ) {
                    var msg = ( status === 'ready' ) ? cfg.i18n.savedReady : cfg.i18n.savedDraft;
                    showMessage( msgBox, msg, status === 'ready' ? 'success' : 'draft' );
                    // Zaktualizuj banner statusu.
                    updateStatusBanner( form.closest( '.openvote-survey-card' ), status );
                } else {
                    showMessage( msgBox, data.message || cfg.i18n.error, 'error' );
                }
            } )
            .catch( function () {
                showMessage( msgBox, cfg.i18n.error, 'error' );
            } )
            .finally( function () {
                setButtonsDisabled( submitBtns, false );
            } );
        } );
    } );

    // ── Helpers ──────────────────────────────────────────────────────────────

    function fetchMyResponse( surveyId, form ) {
        fetch( cfg.restUrl + '/surveys/' + surveyId + '/my-response', {
            headers: { 'X-WP-Nonce': cfg.nonce },
        } )
        .then( function ( res ) { return res.ok ? res.json() : null; } )
        .then( function ( data ) {
            if ( ! data || ! data.answers ) return;
            // Wypełnij pola zapisanymi odpowiedziami.
            Object.keys( data.answers ).forEach( function ( qid ) {
                var input = form.querySelector( '[name="answers[' + qid + ']"]' );
                if ( input ) {
                    input.value = data.answers[ qid ];
                }
            } );
        } )
        .catch( function () { /* silent */ } );
    }

    function showMessage( box, text, type ) {
        if ( ! box ) return;
        var colors = {
            info:    '#0073aa',
            success: '#46b450',
            draft:   '#826eb4',
            error:   '#b32d2e',
        };
        box.style.display     = 'block';
        box.style.color       = colors[ type ] || '#333';
        box.style.fontWeight  = '600';
        box.style.marginTop   = '10px';
        box.textContent       = text;
    }

    function setButtonsDisabled( buttons, disabled ) {
        buttons.forEach( function ( btn ) {
            btn.disabled = disabled;
        } );
    }

    function updateStatusBanner( card, status ) {
        if ( ! card ) return;
        var existing = card.querySelector( '.openvote-survey-notice' );
        if ( existing ) {
            existing.classList.remove( 'openvote-survey-notice--success', 'openvote-survey-notice--draft' );
            if ( status === 'ready' ) {
                existing.classList.add( 'openvote-survey-notice--success' );
                existing.querySelector( 'p' ).textContent = openvoteSurveyPublic.i18n.savedReady;
            } else {
                existing.classList.add( 'openvote-survey-notice--draft' );
                existing.querySelector( 'p' ).textContent = openvoteSurveyPublic.i18n.savedDraft;
            }
        }
    }

    // ── Edycja profilu (Dane z Twojego profilu) — delegowanie zdarzeń ───────
    function getProfileBlockParts( block ) {
        var view = block.querySelector( '.openvote-survey-profile__view' ) || block.querySelector( '.openvote-survey-profile_view' );
        var edit = block.querySelector( '.openvote-survey-profile__edit' ) || block.querySelector( '.openvote-survey-profile_edit' );
        var form = block.querySelector( '.openvote-survey-profile-edit-form' );
        var msgBox = block.querySelector( '.openvote-survey-profile-edit__message' );
        var restUrl = ( block.dataset && block.dataset.restUrl ) || cfg.restUrl || '';
        var nonce   = ( block.dataset && block.dataset.nonce ) || cfg.nonce || '';
        return { view: view, edit: edit, form: form, msgBox: msgBox, restUrl: restUrl, nonce: nonce };
    }

    document.addEventListener( 'click', function ( e ) {
        var editBtn = e.target && e.target.closest && e.target.closest( '[data-action="openvote-edit-profile"]' );
        if ( ! editBtn ) return;
        var block = editBtn.closest( '.openvote-survey-profile' );
        if ( ! block ) return;
        e.preventDefault();
        e.stopPropagation();
        var parts = getProfileBlockParts( block );
        if ( ! parts.view || ! parts.edit ) return;
        parts.view.style.display = 'none';
        parts.edit.removeAttribute( 'hidden' );
        if ( parts.msgBox ) parts.msgBox.textContent = '';
    } );

    document.addEventListener( 'click', function ( e ) {
        var cancelBtn = e.target && e.target.closest && e.target.closest( '.openvote-survey-profile-edit__cancel' );
        if ( ! cancelBtn ) return;
        var block = cancelBtn.closest( '.openvote-survey-profile' );
        if ( ! block ) return;
        e.preventDefault();
        var parts = getProfileBlockParts( block );
        if ( ! parts.view || ! parts.edit ) return;
        parts.view.style.display = '';
        parts.edit.setAttribute( 'hidden', '' );
    } );

    document.querySelectorAll( '.openvote-survey-profile' ).forEach( function ( block ) {
        var parts = getProfileBlockParts( block );
        var view = parts.view, edit = parts.edit, form = parts.form, msgBox = parts.msgBox;
        var restUrl = parts.restUrl, nonce = parts.nonce;

        if ( ! view || ! edit || ! form ) return;

        function showView() {
            view.style.display = '';
            edit.setAttribute( 'hidden', '' );
        }

        form.addEventListener( 'submit', function ( e ) {
            e.preventDefault();
            var inputs = form.querySelectorAll( '.openvote-survey-profile-edit__input' );
            var fields = {};
            inputs.forEach( function ( input ) {
                var key = input.dataset.field;
                if ( key ) fields[ key ] = input.value.trim();
            } );

            if ( Object.keys( fields ).length === 0 ) return;

            if ( msgBox ) msgBox.textContent = cfg.i18n.saving || 'Zapisywanie…';
            if ( msgBox ) msgBox.style.color = '#0073aa';

            fetch( restUrl + '/profile/complete', {
                method:  'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': nonce,
                },
                body: JSON.stringify( { fields: fields } ),
            } )
            .then( function ( res ) { return res.json(); } )
            .then( function ( data ) {
                if ( data.success ) {
                    if ( msgBox ) {
                        msgBox.textContent = cfg.i18n.profileUpdated || 'Profil zaktualizowany.';
                        msgBox.style.color = '#46b450';
                    }
                    inputs.forEach( function ( input ) {
                        var key = input.dataset.field;
                        if ( ! key ) return;
                        var valueCell = view.querySelector( '.openvote-survey-profile__row[data-field="' + key + '"] .openvote-survey-profile__value' )
                            || view.querySelector( '.openvote-survey-profile_row[data-field="' + key + '"] .openvote-survey-profile_value' );
                        if ( valueCell ) valueCell.textContent = input.value.trim() || '—';
                    } );
                    setTimeout( showView, 1200 );
                } else {
                    if ( msgBox ) {
                        msgBox.textContent = data.message || cfg.i18n.error || 'Wystąpił błąd.';
                        msgBox.style.color = '#b32d2e';
                    }
                }
            } )
            .catch( function () {
                if ( msgBox ) {
                    msgBox.textContent = cfg.i18n.error || 'Wystąpił błąd. Spróbuj ponownie.';
                    msgBox.style.color = '#b32d2e';
                }
            } );
        } );
    } );

} )();
