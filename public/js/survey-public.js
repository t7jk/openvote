/* globals evotingSurveyPublic */
( function () {
    'use strict';

    if ( typeof evotingSurveyPublic === 'undefined' ) return;

    var cfg = evotingSurveyPublic;

    document.querySelectorAll( '.evoting-survey-form' ).forEach( function ( form ) {
        var surveyId     = form.dataset.surveyId;
        var msgBox       = form.querySelector( '.evoting-survey-form__message' );
        var submitBtns   = form.querySelectorAll( '.evoting-survey-btn' );

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
                    updateStatusBanner( form.closest( '.evoting-survey-card' ), status );
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
        var existing = card.querySelector( '.evoting-survey-notice' );
        if ( existing ) {
            existing.classList.remove( 'evoting-survey-notice--success', 'evoting-survey-notice--draft' );
            if ( status === 'ready' ) {
                existing.classList.add( 'evoting-survey-notice--success' );
                existing.querySelector( 'p' ).textContent = evotingSurveyPublic.i18n.savedReady;
            } else {
                existing.classList.add( 'evoting-survey-notice--draft' );
                existing.querySelector( 'p' ).textContent = evotingSurveyPublic.i18n.savedDraft;
            }
        }
    }

} )();
