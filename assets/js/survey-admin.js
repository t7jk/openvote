/* globals openvoteSurveyAdmin */
( function () {
    'use strict';

    const MAX_FIELDS = openvoteSurveyAdmin.maxFields || 20;

    const container  = document.getElementById( 'openvote-survey-fields' );
    const addBtn     = document.getElementById( 'openvote-add-survey-field' );
    const countLabel = document.getElementById( 'openvote-survey-field-count' );
    const startBtn   = document.getElementById( 'openvote-survey-start-btn' );
    const form       = document.getElementById( 'openvote-survey-form' );

    if ( ! container ) return;

    // ── Licznik pól ──────────────────────────────────────────────────────────

    function getFieldCount() {
        return container.querySelectorAll( '.openvote-survey-field-row' ).length;
    }

    function updateCounter() {
        const count = getFieldCount();
        if ( countLabel ) {
            countLabel.textContent = '(' + count + ' / ' + MAX_FIELDS + ')';
        }
        if ( addBtn ) {
            addBtn.disabled = count >= MAX_FIELDS;
        }
    }

    // ── Przeindeksowanie name atrybutów po usunięciu ─────────────────────────

    function reindexRows() {
        container.querySelectorAll( '.openvote-survey-field-row' ).forEach( function ( row, i ) {
            row.dataset.index = i;
            const numLabel = row.querySelector( 'span[style*="font-weight:600"]' );
            if ( numLabel ) numLabel.textContent = ( i + 1 ) + '.';

            row.querySelectorAll( '[name]' ).forEach( function ( el ) {
                el.name = el.name.replace( /survey_questions\[\d+\]/, 'survey_questions[' + i + ']' );
            } );
        } );
        updateCounter();
    }

    // ── Szablon nowego wiersza ───────────────────────────────────────────────

    function buildFieldRow( index ) {
        const wrap = document.createElement( 'div' );
        wrap.className    = 'openvote-survey-field-row';
        wrap.dataset.index = index;

        const types = [
            { v: 'text_short', l: openvoteSurveyAdmin.i18n.text_short },
            { v: 'text_long',  l: openvoteSurveyAdmin.i18n.text_long  },
            { v: 'url',        l: openvoteSurveyAdmin.i18n.url        },
        ];

        const opts = types.map( function ( t ) {
            return '<option value="' + t.v + '">' + t.l + '</option>';
        } ).join( '' );

        const sensitiveLabel = openvoteSurveyAdmin.i18n.sensitiveCheckboxLabel || 'Informacja wrażliwa - nie pokazuj odpowiedzi na stronie publicznie.';

        wrap.innerHTML = `
            <div style="display:flex;align-items:flex-start;gap:8px;margin-bottom:10px;padding:12px;background:#f9f9f9;border:1px solid #ddd;border-radius:4px;">
                <span style="margin-top:8px;font-weight:600;color:#666;min-width:24px;">${ index + 1 }.</span>
                <div style="flex:1;display:flex;flex-direction:column;gap:6px;">
                    <select name="survey_questions[${ index }][field_type]"
                            class="openvote-survey-field-type">
                        ${ opts }
                    </select>
                    <input type="text"
                           name="survey_questions[${ index }][body]"
                           placeholder="${ openvoteSurveyAdmin.i18n.placeholder }"
                           maxlength="512"
                           style="width:100%;"
                           class="openvote-survey-field-body">
                    <label style="display:flex;align-items:center;gap:6px;font-size:11px;color:#666;">
                        <input type="checkbox"
                               name="survey_questions[${ index }][hide_on_public]"
                               value="1">
                        ${ sensitiveLabel }
                    </label>
                </div>
                <button type="button"
                        class="button openvote-remove-survey-field"
                        style="margin-top:4px;"
                        title="${ openvoteSurveyAdmin.i18n.remove }">✕</button>
            </div>`;

        return wrap;
    }

    // ── Delegacja zdarzeń na kontenerze ─────────────────────────────────────

    container.addEventListener( 'click', function ( e ) {
        if ( e.target.classList.contains( 'openvote-remove-survey-field' ) ) {
            if ( getFieldCount() <= 1 ) {
                alert( openvoteSurveyAdmin.i18n.minOne );
                return;
            }
            const row = e.target.closest( '.openvote-survey-field-row' );
            if ( row ) {
                row.remove();
                reindexRows();
            }
        }
    } );

    // ── Przycisk "Dodaj pole" ────────────────────────────────────────────────

    if ( addBtn ) {
        addBtn.addEventListener( 'click', function () {
            if ( getFieldCount() >= MAX_FIELDS ) return;
            const idx = getFieldCount();
            const row = buildFieldRow( idx );
            container.appendChild( row );
            updateMaxCharsVisibility( row );
            row.querySelector( '.openvote-survey-field-body' ).focus();
            reindexRows();
        } );
    }

    // ── Walidacja formularza ─────────────────────────────────────────────────

    if ( form ) {
        form.addEventListener( 'submit', function ( e ) {
            const rows = container.querySelectorAll( '.openvote-survey-field-row' );
            let valid = true;

            rows.forEach( function ( row ) {
                const bodyInput = row.querySelector( '.openvote-survey-field-body' );
                if ( bodyInput && bodyInput.value.trim() === '' ) {
                    bodyInput.style.borderColor = '#b32d2e';
                    valid = false;
                } else if ( bodyInput ) {
                    bodyInput.style.borderColor = '';
                }
            } );

            if ( ! valid ) {
                e.preventDefault();
                alert( openvoteSurveyAdmin.i18n.emptyLabel );
                return;
            }

            // Zapobiegaj podwójnemu wysłaniu.
            var submitBtns = form.querySelectorAll( 'button[type="submit"], input[type="submit"]' );
            submitBtns.forEach( function ( b ) {
                b.disabled = true;
            } );

            if ( startBtn && document.activeElement === startBtn ) {
                const title    = document.getElementById( 'survey_title' );
                const duration = document.getElementById( 'survey_duration' );

                if ( title && title.value.trim() === '' ) {
                    e.preventDefault();
                    title.style.borderColor = '#b32d2e';
                    title.focus();
                    return;
                }
                if ( duration && ! duration.value ) {
                    e.preventDefault();
                    duration.focus();
                    return;
                }
            }
        } );
    }

    // ── Init ─────────────────────────────────────────────────────────────────

    updateCounter();

} )();
