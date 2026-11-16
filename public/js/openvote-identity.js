/**
 * OpenVote — panel „Jak chcesz oddać głos?” (głosowanie / ankieta bez logowania).
 * Wspólny moduł dla głosowań i ankiet: window.OpenvoteIdentity.
 */
( function () {
    'use strict';

    var cfg  = window.openvoteIdentity || {};
    var i18n = cfg.i18n || {};
    // wp_localize_script zamienia liczby na napisy ("0" jest prawdą w JS).
    cfg.loggedIn = ( cfg.loggedIn === 1 || cfg.loggedIn === '1' || cfg.loggedIn === true );
    var counter = 0;
    var configPromise = null;

    // ── Storage (try/catch: tryb prywatny, zablokowane dane witryny) ─────────
    function ssGet( k ) { try { return window.sessionStorage.getItem( k ); } catch ( e ) { return null; } }
    function ssSet( k, v ) { try { window.sessionStorage.setItem( k, v ); } catch ( e ) { /* ignore */ } }
    function ssDel( k ) { try { window.sessionStorage.removeItem( k ); } catch ( e ) { /* ignore */ } }

    // Jeden globalny token na całej stronie (ważny dla wszystkich głosowań i ankiet).
    var TOKEN_KEY = 'openvote_identity_token';
    var EVT = 'openvote:identity-changed';
    var memSession = null;

    function loadSession() {
        var raw = ssGet( TOKEN_KEY );
        if ( raw ) {
            try {
                var o = JSON.parse( raw );
                if ( o && o.token ) return o;
            } catch ( e ) { /* ignore */ }
        }
        return memSession && memSession.token ? memSession : null;
    }
    function saveSession( obj ) { memSession = obj; ssSet( TOKEN_KEY, JSON.stringify( obj ) ); }
    function clearSession() {
        memSession = null; ssDel( TOKEN_KEY );
        // Sprzątanie po starszej wersji (klucze per cel + client_id).
        try { window.localStorage.removeItem( 'openvote_client_id' ); } catch ( e ) { /* ignore */ }
    }
    function notifyChange() {
        try { window.dispatchEvent( new CustomEvent( EVT ) ); } catch ( e ) { /* ignore */ }
    }

    function t( key, fallback ) { return i18n[ key ] || fallback || ''; }
    function fmt( str ) {
        var args = Array.prototype.slice.call( arguments, 1 ), n = 0;
        return String( str ).replace( /%(\d+\$)?[sd]/g, function () { return args[ n++ ]; } );
    }

    function el( tag, attrs, text ) {
        var e = document.createElement( tag );
        if ( attrs ) Object.keys( attrs ).forEach( function ( k ) {
            if ( k === 'class' ) e.className = attrs[ k ]; else e.setAttribute( k, attrs[ k ] );
        } );
        if ( text !== undefined ) e.textContent = text;
        return e;
    }

    function getJson( url ) {
        return fetch( url, { credentials: 'omit', headers: { 'Accept': 'application/json' } } )
            .then( function ( r ) { return r.json().then( function ( d ) { return { ok: r.ok, status: r.status, data: d }; } ); } );
    }

    function getConfig() {
        if ( ! configPromise ) {
            configPromise = getJson( cfg.restUrl + '/identity/config' )
                .then( function ( r ) { return r.ok && r.data ? r.data : { enabled: false }; } )
                .catch( function () { return { enabled: false }; } );
        }
        return configPromise;
    }

    /** Nagłówki uwierzytelnienia dla zapytań do REST (nonce tylko dla zalogowanych). */
    function authHeaders( type, id, nonce ) {
        var h = {};
        if ( cfg.loggedIn ) {
            var n = nonce || cfg.nonce;
            if ( n ) h[ 'X-WP-Nonce' ] = n;
        } else {
            var s = loadSession();
            if ( s ) h[ 'X-Openvote-Identity' ] = s.token;
        }
        return h;
    }

    function postJson( url, body ) {
        return fetch( url, {
            method: 'POST', credentials: 'omit',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify( body )
        } ).then( function ( r ) {
            return r.json().catch( function () { return {}; } ).then( function ( d ) { return { ok: r.ok, status: r.status, data: d || {} }; } );
        } );
    }

    /**
     * mount( container, { type, id, onVerified(data), onReset(message) } ) → { invalidate(msg), clear() }
     * Token jest globalny: weryfikacja w jednym panelu odblokowuje wszystkie panele na stronie.
     */
    function mount( container, opts ) {
        if ( cfg.loggedIn || ! container ) return null;
        var type = opts.type, id = opts.id, uid = ++counter;
        var isSurvey = type === 'survey';
        var panel = el( 'div', { 'class': 'openvote-identity__panel' } );
        container.appendChild( panel );
        var conf = null, timer = null, view = null, wasVerified = false, justVerified = false;
        // Na stronie jest jeden panel główny (pierwszy); pozostałe pokazują krótką informację.
        var first = document.querySelector( '.openvote-identity' );
        var isPrimary = ( first === container );
        if ( isPrimary && ! container.id ) container.id = 'openvote-identity-primary';
        var state = { step: 'email', email: '', resendAt: 0, attemptsLeft: null, max: 5, locked: false };

        function stopTimer() { if ( timer ) { clearInterval( timer ); timer = null; } }

        function render( notice, noticeType, focusSel ) {
            stopTimer();
            panel.innerHTML = '';
            if ( ! isPrimary ) { renderSecondary(); return; }
            panel.appendChild( el( 'h4', { 'class': 'openvote-identity__title' }, isSurvey ? t( 'titleSurvey', 'Jak chcesz wypełnić ankietę?' ) : t( 'titlePoll', 'Jak chcesz oddać głos?' ) ) );

            if ( loadSession() ) { renderVerified(); return; }

            // Opcja A: logowanie.
            var a = el( 'div', { 'class': 'openvote-identity__opt openvote-identity__opt--login' } );
            a.appendChild( el( 'a', { 'class': 'openvote-identity__btn', href: cfg.loginUrl }, t( 'login', 'Zaloguj się' ) ) );
            a.appendChild( el( 'a', { 'class': 'openvote-identity__link', href: cfg.lostUrl }, t( 'lost', 'Nie pamiętam hasła' ) ) );
            panel.appendChild( a );

            var live = el( 'div', { 'class': 'openvote-identity__live', 'aria-live': 'polite', role: 'status' } );

            if ( conf && conf.enabled ) {
                if ( ! state.locked ) { panel.appendChild( el( 'div', { 'class': 'openvote-identity__sep' }, t( 'or', 'lub' ) ) ); }
                if ( state.locked ) {
                    panel.appendChild( live );
                    renderLocked( live );
                } else if ( state.step === 'code' ) {
                    renderCode( live );
                } else {
                    renderEmail( live );
                }
            } else {
                panel.appendChild( live );
            }
            if ( notice ) showNotice( live, notice, noticeType || 'error' );
            if ( focusSel ) {
                var f = panel.querySelector( focusSel );
                if ( f ) f.focus();
            }
        }

        function renderSecondary() {
            panel.classList.add( 'openvote-identity__panel--compact' );
            if ( loadSession() ) { renderVerified(); return; }
            var box = el( 'div', { 'class': 'openvote-identity__compact' } );
            box.appendChild( el( 'p', { 'class': 'openvote-identity__compact-text' }, isSurvey ? t( 'confirmAboveSurvey', 'Potwierdź e-mail powyżej, aby wypełnić ankietę.' ) : t( 'confirmAbove', 'Potwierdź e-mail powyżej, aby głosować.' ) ) );
            var go = el( 'a', { 'class': 'openvote-identity__btn', href: '#openvote-identity-primary' }, t( 'goToPanel', 'Przejdź do potwierdzenia e-maila' ) );
            go.addEventListener( 'click', function ( e ) {
                var target = document.getElementById( 'openvote-identity-primary' );
                if ( ! target ) return;
                e.preventDefault();
                try { target.scrollIntoView( { behavior: 'smooth', block: 'center' } ); } catch ( err ) { target.scrollIntoView(); }
                var f = target.querySelector( 'input' );
                if ( f ) { try { f.focus( { preventScroll: true } ); } catch ( err2 ) { f.focus(); } }
            } );
            box.appendChild( go );
            panel.appendChild( box );
        }

        function renderLocked( live ) {
            live.innerHTML = '';
            var box = el( 'div', { 'class': 'openvote-identity__notice openvote-identity__notice--error' } );
            var pre = fmt( t( 'lockedPrefix', 'Wykorzystano %1$d z %2$d prób.' ), state.max, state.max );
            box.appendChild( el( 'p', null, pre + ' ' + ( isSurvey ? t( 'lockedSurvey' ) : t( 'lockedText' ) ) ) );
            live.appendChild( box );
        }

        function showNotice( live, text, kind ) {
            var n = live.querySelector( '.openvote-identity__notice--top' );
            if ( ! n ) {
                n = el( 'div', { 'class': 'openvote-identity__notice--top' } );
                live.insertBefore( n, live.firstChild );
            }
            n.className = 'openvote-identity__notice openvote-identity__notice--top openvote-identity__notice--' + kind;
            n.textContent = text;
        }

        function renderVerified() {
            var sess = loadSession() || {};
            var box = el( 'div', { 'class': 'openvote-identity__verified', role: 'status' } );
            var line = el( 'p', { 'class': 'openvote-identity__verified-line', tabindex: '-1' } );
            line.appendChild( el( 'span', { 'class': 'openvote-identity__check', 'aria-hidden': 'true' }, '✓' ) );
            line.appendChild( document.createTextNode( ' ' + fmt( isSurvey ? t( 'answeringAs', 'Wypełniasz ankietę jako %s' ) : t( 'votingAs', 'Głosujesz jako %s' ), sess.display_name || '' ) ) );
            box.appendChild( line );
            var info = view && view.info;
            if ( info ) box.appendChild( el( 'p', { 'class': 'openvote-identity__notice openvote-identity__notice--' + ( info.kind || 'info' ) }, info.text ) );
            var chg = el( 'button', { type: 'button', 'class': 'openvote-identity__link openvote-identity__link-btn' }, t( 'changeEmail', 'Zmień adres e-mail' ) );
            chg.addEventListener( 'click', function () {
                clearSession();
                state.step = 'email'; state.resendAt = 0;
                notifyChange();
            } );
            if ( isPrimary ) box.appendChild( chg );
            panel.appendChild( box );
            if ( justVerified && isPrimary && view ) {
                justVerified = false;
                try { line.focus( { preventScroll: true } ); } catch ( e ) { /* ignore */ }
            }
        }

        function attemptsText() {
            return ( state.attemptsLeft === null || state.attemptsLeft >= state.max )
                ? '' : fmt( t( 'attempts', 'Pozostało prób: %1$d z %2$d' ), state.attemptsLeft, state.max );
        }

        function renderEmail( live ) {
            var form = el( 'form', { 'class': 'openvote-identity__form', novalidate: 'novalidate' } );
            form.appendChild( el( 'p', { 'class': 'openvote-identity__form-title' }, isSurvey ? t( 'orWithoutSurvey', t( 'orWithout' ) ) : t( 'orWithout', 'Głosuj bez logowania — potwierdź adres e-mail' ) ) );
            var fid = 'ovid-' + uid + '-email';
            var row = el( 'div', { 'class': 'openvote-identity__row' } );
            row.appendChild( el( 'label', { 'for': fid }, t( 'emailLabel', 'Adres e-mail' ) ) );
            var input = el( 'input', { id: fid, name: 'email', type: 'email', autocomplete: 'email', autocapitalize: 'off', spellcheck: 'false', placeholder: t( 'emailHint', 'np. jan@poczta.pl' ) } );
            input.value = state.email;
            row.appendChild( input );
            form.appendChild( row );
            form.appendChild( el( 'p', { 'class': 'openvote-identity__explain' }, t( 'explain', 'Kod potwierdza, że to Twój adres e-mail. Nie zakładamy nowego konta.' ) ) );
            var counterEl = el( 'p', { 'class': 'openvote-identity__attempts' }, attemptsText() );
            var submit = el( 'button', { type: 'submit', 'class': 'openvote-identity__btn openvote-identity__btn--primary' }, t( 'next', 'Wyślij kod' ) );
            var actions = el( 'div', { 'class': 'openvote-identity__actions' } );
            actions.appendChild( submit );
            actions.appendChild( counterEl );
            form.appendChild( actions );
            panel.appendChild( form );
            panel.appendChild( live );

            form.addEventListener( 'submit', function ( e ) {
                e.preventDefault();
                var v = input.value.trim();
                state.email = v;
                if ( v === '' || v.indexOf( '@' ) < 1 ) {
                    showNotice( live, t( 'emailRequired', 'Podaj poprawny adres e-mail.' ), 'error' );
                    input.setAttribute( 'aria-invalid', 'true' );
                    input.focus();
                    return;
                }
                input.removeAttribute( 'aria-invalid' );
                submit.disabled = true; submit.textContent = t( 'sending', 'Wysyłanie…' );
                requestCode( v, function ( res ) {
                    submit.disabled = false; submit.textContent = t( 'next', 'Wyślij kod' );
                    return res;
                }, live, counterEl, input );
            } );
        }

        /** Wspólne wysyłanie kodu (krok 1 i ponowne wysłanie). */
        function requestCode( email, done, live, counterEl, input ) {
            postJson( cfg.restUrl + '/identity/request-code', { email: email, target_type: type, target_id: parseInt( id, 10 ) } )
            .then( function ( res ) {
                if ( done ) done( res );
                var d = res.data || {};
                if ( typeof d.attempts_left === 'number' ) state.attemptsLeft = d.attempts_left;
                if ( res.ok && d.status === 'code_sent' ) {
                    state.step = 'code';
                    state.resendAt = Date.now() + ( parseInt( d.resend_in, 10 ) || 120 ) * 1000;
                    render( '', '', '.openvote-identity__code' );
                } else if ( res.ok && d.status === 'cooldown' ) {
                    state.step = 'code';
                    state.resendAt = Date.now() + ( parseInt( d.resend_in, 10 ) || 0 ) * 1000;
                    // Kod już wysłano wcześniej — tylko synchronizujemy licznik z serwerem (bez drugiego, statycznego komunikatu).
                    render( '', '', '.openvote-identity__code' );
                } else if ( res.ok && d.status === 'locked' ) {
                    state.locked = true; render();
                } else if ( res.ok && d.status === 'not_found' ) {
                    if ( counterEl ) counterEl.textContent = '';
                    showNotice( live, d.message || t( 'notFound', 'Brak takiego adresu e-mail w bazie.' ), 'error' );
                    if ( input ) input.focus();
                } else {
                    showNotice( live, d.message || t( 'error' ), 'error' );
                }
            } )
            .catch( function () {
                if ( done ) done( null );
                showNotice( live, t( 'networkError', t( 'error', 'Wystąpił błąd. Spróbuj ponownie.' ) ), 'error' );
            } );
        }

        function renderCode( live ) {
            var form = el( 'form', { 'class': 'openvote-identity__form', novalidate: 'novalidate' } );
            form.appendChild( el( 'p', { 'class': 'openvote-identity__form-title' }, t( 'codeSent', 'Na podany adres wysłaliśmy 4-cyfrowy kod. Wpisz go poniżej jako potwierdzenie. Jeśli nie widzisz wiadomości, sprawdź folder SPAM.' ) ) );
            form.appendChild( el( 'p', { 'class': 'openvote-identity__email-line' }, state.email ) );
            form.appendChild( el( 'p', { 'class': 'openvote-identity__explain' }, t( 'codeValid', 'Kod jest ważny 15 minut.' ) ) );
            var fid = 'ovid-' + uid + '-code';
            var row = el( 'div', { 'class': 'openvote-identity__row' } );
            row.appendChild( el( 'label', { 'for': fid }, t( 'codeLabel', 'Kod z e-maila' ) ) );
            var input = el( 'input', {
                id: fid, name: 'code', type: 'text', 'class': 'openvote-identity__code',
                inputmode: 'numeric', pattern: '[0-9]{4}', maxlength: '4',
                autocomplete: 'one-time-code', autocapitalize: 'off', spellcheck: 'false'
            } );
            row.appendChild( input );
            form.appendChild( row );
            var submit = el( 'button', { type: 'submit', 'class': 'openvote-identity__btn openvote-identity__btn--primary' }, t( 'confirm', 'Potwierdź' ) );
            var actions = el( 'div', { 'class': 'openvote-identity__actions' } );
            actions.appendChild( submit );
            form.appendChild( actions );

            // Odliczanie / ponowne wysłanie.
            var resendBox = el( 'div', { 'class': 'openvote-identity__resend' } );
            form.appendChild( resendBox );
            var chg = el( 'button', { type: 'button', 'class': 'openvote-identity__link openvote-identity__link-btn' }, t( 'changeEmail', 'Zmień adres e-mail' ) );
            chg.addEventListener( 'click', function () {
                state.step = 'email'; state.resendAt = 0;
                render( '', '', '#ovid-' + uid + '-email' );
            } );
            form.appendChild( chg );
            panel.appendChild( form );
            panel.appendChild( live );

            function tick() {
                var left = Math.max( 0, Math.ceil( ( state.resendAt - Date.now() ) / 1000 ) );
                if ( left > 0 ) {
                    var txt = el( 'p', { 'class': 'openvote-identity__countdown' }, fmt( t( 'resendIn', 'Wyślij ponownie e-mail za %d s' ), left ) );
                    resendBox.innerHTML = ''; resendBox.appendChild( txt );
                } else {
                    stopTimer();
                    resendBox.innerHTML = '';
                    var rb = el( 'button', { type: 'button', 'class': 'openvote-identity__btn openvote-identity__resend-btn' }, t( 'resend', 'Wyślij ponownie e-mail' ) );
                    rb.addEventListener( 'click', function () {
                        rb.disabled = true; rb.textContent = t( 'sending', 'Wysyłanie…' );
                        requestCode( state.email, null, live, null, null );
                    } );
                    resendBox.appendChild( rb );
                }
            }
            tick();
            if ( state.resendAt > Date.now() ) timer = setInterval( tick, 1000 );

            var busy = false;
            function submitCode() {
                if ( busy ) return;
                var v = input.value.replace( /\D/g, '' );
                if ( v.length !== 4 ) {
                    showNotice( live, t( 'codeRequired', 'Wpisz 4-cyfrowy kod.' ), 'error' );
                    input.focus();
                    return;
                }
                busy = true; submit.disabled = true; submit.textContent = t( 'checking', 'Sprawdzanie…' );
                postJson( cfg.restUrl + '/identity/verify-code', { email: state.email, code: v, target_type: type, target_id: parseInt( id, 10 ) } )
                .then( function ( res ) {
                    var d = res.data || {};
                    if ( res.ok && d.verified ) { onSuccess( d ); return; }
                    busy = false; submit.disabled = false; submit.textContent = t( 'confirm', 'Potwierdź' );
                    showNotice( live, ( d.message || t( 'error' ) ) + ' ' + t( 'wrongCodeKeep', '' ), 'error' );
                    input.setAttribute( 'aria-invalid', 'true' );
                    input.focus(); try { input.select(); } catch ( e ) { /* ignore */ }
                } )
                .catch( function () {
                    busy = false; submit.disabled = false; submit.textContent = t( 'confirm', 'Potwierdź' );
                    showNotice( live, t( 'networkError', t( 'error' ) ), 'error' );
                } );
            }
            form.addEventListener( 'submit', function ( e ) { e.preventDefault(); submitCode(); } );
            input.addEventListener( 'input', function () {
                var clean = input.value.replace( /\D/g, '' ).slice( 0, 4 );
                if ( clean !== input.value ) input.value = clean;
                input.removeAttribute( 'aria-invalid' );
                if ( clean.length === 4 ) submitCode();
            } );
        }

        function onSuccess( d ) {
            saveSession( { token: d.token, display_name: d.display_name || '', labels: d.labels || null } );
            justVerified = true;
            state.step = 'email'; state.resendAt = 0;
            notifyChange(); // wszystkie panele na stronie (także ten) przechodzą w stan zweryfikowany
        }

        /** Pobiera stan dla tego celu (has_voted / eligible / my_response) z tokenem w nagłówku. */
        function refresh() {
            var sess = loadSession();
            if ( ! sess ) return;
            var path = ( isSurvey ? '/surveys/' : '/polls/' ) + encodeURIComponent( id );
            fetch( cfg.restUrl + path, { credentials: 'omit', headers: { 'Accept': 'application/json', 'X-Openvote-Identity': sess.token } } )
            .then( function ( r ) { return r.json().catch( function () { return {}; } ).then( function ( d ) { return { ok: r.ok, data: d || {} }; } ); } )
            .catch( function () { return { ok: false, data: {} }; } )
            .then( function ( res ) {
                if ( ! loadSession() ) return;
                var d = res.data, info = null, out = { poll: null, survey: null };
                if ( res.ok && ! isSurvey ) {
                    out.poll = { has_voted: !! d.has_voted, eligible_error: d.eligible_error || null };
                    if ( d.has_voted ) info = { kind: 'info', text: t( 'hasVoted', 'Już oddałeś głos w tym głosowaniu. Dziękujemy!' ) };
                    else if ( d.eligible_error ) info = { kind: 'error', text: d.eligible_error };
                } else if ( res.ok && isSurvey ) {
                    out.survey = { my_response: d.my_response || null, profile_error: null };
                }
                view = { info: info };
                render();
                if ( opts.onVerified ) opts.onVerified( out );
            } );
        }

        function applySession() {
            if ( loadSession() ) {
                wasVerified = true;
                render();
                refresh();
            } else {
                view = null;
                if ( wasVerified ) {
                    wasVerified = false;
                    if ( opts.onReset ) opts.onReset( '' );
                }
                render();
            }
        }

        function onEvt() {
            if ( ! container.isConnected ) { window.removeEventListener( EVT, onEvt ); stopTimer(); return; }
            applySession();
        }
        window.addEventListener( EVT, onEvt );

        // Start: konfiguracja + stan prób.
        getConfig().then( function ( c ) {
            conf = c || { enabled: false };
            if ( conf.max_attempts ) state.max = conf.max_attempts;
            if ( loadSession() ) { applySession(); return; }
            if ( ! conf.enabled ) { render(); return; }
            return getJson( cfg.restUrl + '/identity/status' ).then( function ( r ) {
                if ( r.ok && r.data ) {
                    if ( typeof r.data.attempts_left === 'number' ) state.attemptsLeft = r.data.attempts_left;
                    if ( r.data.max_attempts ) state.max = r.data.max_attempts;
                    state.locked = !! r.data.locked;
                }
            } ).catch( function () { /* ignoruj */ } ).then( function () { render(); } );
        } );

        return {
            /** Wywołaj po 401 identity_invalid (token wygasł). */
            invalidate: function ( message ) {
                clearSession();
                state.step = 'email'; state.resendAt = 0;
                notifyChange();
                render( message || t( 'expired', 'Weryfikacja wygasła — potwierdź adres e-mail ponownie.' ), 'error' );
            },
            clear: function () { clearSession(); }
        };
    }

    window.OpenvoteIdentity = {
        mount: mount,
        authHeaders: authHeaders,
        clearSession: clearSession,
        loadSession: loadSession,
        loggedIn: !! cfg.loggedIn
    };
} )();
