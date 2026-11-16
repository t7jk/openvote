/**
 * E-Voting Public JS – voting form, countdown, results loading
 */
(function () {
    'use strict';

    var cfg = window.openvotePublic || {};
    var cdTimer = null;

    function runOnReady() {
        initCountdowns();
        initVotingForms();
        initResults();
        initClosedPollExpand();
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', runOnReady);
    } else {
        runOnReady();
    }

    /* ── Zakończone głosowania: Rozwiń / Zwiń ── */

    function initClosedPollExpand() {
        document.querySelectorAll('.openvote-closed-poll-block__expand-btn').forEach(function (btn) {
            if (btn._openvoteExpandBound) return;
            btn._openvoteExpandBound = true;
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                var block = btn.closest('.openvote-closed-poll-block');
                if (!block) return;
                var details = block.querySelector('.openvote-closed-poll-block__details');
                if (!details) return;
                var isCurrentlyHidden = details.hasAttribute('hidden');
                if (isCurrentlyHidden) {
                    details.removeAttribute('hidden');
                    btn.setAttribute('aria-expanded', 'true');
                    btn.textContent = (cfg.i18n && cfg.i18n.collapse) ? cfg.i18n.collapse : 'Zwiń';
                } else {
                    details.setAttribute('hidden', '');
                    btn.setAttribute('aria-expanded', 'false');
                    btn.textContent = (cfg.i18n && cfg.i18n.expand) ? cfg.i18n.expand : 'Rozwiń';
                }
            });
        });
    }

    /* ── Countdown Timer ── */

    function initCountdowns() {
        var countdowns = document.querySelectorAll('.openvote-countdown');
        if (!countdowns.length) return;

        function update() {
            countdowns.forEach(function (el) {
                var end  = new Date(el.dataset.end).getTime();
                var diff = end - Date.now();

                if (diff <= 0) {
                    el.textContent = cfg.i18n.ended;
                    var blk = el.closest('.openvote-poll-block');
                    var f = blk ? blk.querySelector('.openvote-poll__form') : null;
                    if (f && !f.dataset.ended) {
                        f.dataset.ended = '1';
                        f.querySelectorAll('input, button').forEach(function (x) { x.disabled = true; });
                        var m = f.querySelector('.openvote-poll__message');
                        showMessage(m, cfg.i18n.endedReload || 'Głosowanie zakończyło się. Odśwież stronę, aby zobaczyć wyniki.', 'error');
                        var h = f.querySelector('.openvote-poll__hint');
                        if (h) h.textContent = '';
                    }
                    return;
                }

                var d = Math.floor(diff / 86400000);
                var h = Math.floor((diff % 86400000) / 3600000);
                var m = Math.floor((diff % 3600000) / 60000);
                var s = Math.floor((diff % 60000) / 1000);

                var parts = [];
                if (d > 0) parts.push(d + ' ' + cfg.i18n.days);
                if (d > 0 || h > 0) parts.push(h + ' ' + cfg.i18n.hours);
                parts.push(m + ' ' + cfg.i18n.minutes);
                if (d === 0 && h === 0) parts.push(s + ' ' + cfg.i18n.seconds);

                el.textContent = parts.join(' ');
            });
        }

        update();
        if (cdTimer) clearInterval(cdTimer);
        cdTimer = setInterval(update, 1000);
    }

    /* ── Voting Forms ── */

    function isFormVoteComplete(form) {
        var questionFieldsets = form.querySelectorAll('.openvote-poll__question');
        var allAnswered = true;
        questionFieldsets.forEach(function (fs) {
            if (!fs.querySelector('input[type="radio"]:checked')) {
                allAnswered = false;
            }
        });
        var visibilityChecked = form.querySelector('input[name="openvote_vote_visibility"]:checked');
        return allAnswered && !!visibilityChecked;
    }

    function updateHint(form) {
        var hint = form.querySelector('.openvote-poll__hint');
        if (!hint || form.dataset.ended || form.dataset.done) return;
        var text = '';
        var ok = false;
        if (form.classList.contains('openvote-poll__form--locked')) {
            text = form.dataset.identityRequired === '1'
                ? (window.OpenvoteIdentity && window.OpenvoteIdentity.loadSession && window.OpenvoteIdentity.loadSession()
                    ? ''
                    : (cfg.i18n.hintGuest || 'Aby oddać głos, najpierw zaloguj się lub potwierdź adres e-mail (ramka wyżej).'))
                : '';
        } else {
            var missing = [];
            var n = 0;
            form.querySelectorAll('.openvote-poll__question').forEach(function (fs) {
                n++;
                if (!fs.querySelector('input[type="radio"]:checked')) missing.push(n);
            });
            var vis = form.querySelector('input[name="openvote_vote_visibility"]:checked');
            if (missing.length) {
                text = (missing.length === 1 ? (cfg.i18n.hintQ1 || 'Zaznacz odpowiedź w pytaniu %s.') : (cfg.i18n.hintQn || 'Zaznacz odpowiedzi w pytaniach: %s.')).replace('%s', missing.join(', '));
            } else if (!vis) {
                text = cfg.i18n.chooseVisibility || 'Wybierz sposób oddania głosu (jawnie lub anonimowo).';
            } else {
                text = cfg.i18n.hintReady || 'Wszystko gotowe. Naciśnij „Oddaj głos”.';
                ok = true;
            }
        }
        hint.textContent = text;
        hint.classList.toggle('openvote-poll__hint--ok', ok);
    }

    function updateVoteSubmitButton(form) {
        var submitBtn = form.querySelector('.openvote-poll__submit');
        if (submitBtn) {
            submitBtn.disabled = !isFormVoteComplete(form) || form.classList.contains('openvote-poll__form--locked');
        }
        updateHint(form);
    }

    function applyLabels(form, labels) {
        form.querySelectorAll('[data-ov-label]').forEach(function (sp) {
            var k = sp.getAttribute('data-ov-label');
            sp.textContent = (labels && labels[k]) ? labels[k] : (sp.getAttribute('data-ov-default') || sp.textContent);
        });
    }

    function setFormLocked(form, locked) {
        form.classList.toggle('openvote-poll__form--locked', locked);
        form.querySelectorAll('input, button').forEach(function (el) { el.disabled = locked; });
        if (!locked) updateVoteSubmitButton(form); else updateHint(form);
    }

    function authHeaders(form, pollId) {
        var block = form.closest('.openvote-poll-block');
        var nonce = (block && block.dataset.nonce) || cfg.nonce || '';
        var h = {};
        if (window.OpenvoteIdentity && !nonce) {
            h = window.OpenvoteIdentity.authHeaders('poll', pollId);
        } else if (nonce) {
            h['X-WP-Nonce'] = nonce;
        }
        return h;
    }

    function initIdentity(form, pollId) {
        var Id = window.OpenvoteIdentity;
        var block = form.closest('.openvote-poll-block');
        var slot = block ? block.querySelector('.openvote-identity') : null;
        if (!Id || !slot) return null;
        var ctrl = Id.mount(slot, {
            type: 'poll',
            id: pollId,
            onVerified: function (sess) {
                var p = sess.poll || {};
                var ss = Id.loadSession && Id.loadSession();
                applyLabels(form, ss && ss.labels);
                if (p.has_voted) {
                    form.hidden = true;
                    return;
                }
                form.hidden = false;
                if (p.eligible_error) {
                    setFormLocked(form, true);
                    return;
                }
                setFormLocked(form, false);
            },
            onReset: function () {
                applyLabels(form, null);
                form.hidden = false;
                setFormLocked(form, true);
            }
        });
        return ctrl;
    }

    function initVotingForms() {
        document.querySelectorAll('.openvote-poll__form').forEach(function (form) {
            var submitBtn = form.querySelector('.openvote-poll__submit');
            if (submitBtn) {
                submitBtn.disabled = true;
            }
            var identityCtrl = null;
            if (form.dataset.identityRequired === '1') {
                updateHint(form);
                identityCtrl = initIdentity(form, form.dataset.pollId);
            }
            form.addEventListener('change', function () {
                updateVoteSubmitButton(form);
            });
            updateVoteSubmitButton(form);

            form.addEventListener('submit', function (e) {
                e.preventDefault();

                var pollId    = form.dataset.pollId;
                var msgEl     = form.querySelector('.openvote-poll__message');
                var submitBtn = form.querySelector('.openvote-poll__submit');

                // Collect answers: question_id (int) → answer_id (int).
                var answers     = {};
                var allAnswered = true;

                form.querySelectorAll('.openvote-poll__question').forEach(function (fs) {
                    var checked = fs.querySelector('input[type="radio"]:checked');
                    if (!checked) { allAnswered = false; return; }
                    var qId = parseInt(checked.name.replace('question_', ''), 10);
                    answers[qId] = parseInt(checked.value, 10);
                });

                var visibilityRadios = form.querySelectorAll('input[name="openvote_vote_visibility"]:checked');
                var isAnonymous = visibilityRadios.length ? (parseInt(visibilityRadios[0].value, 10) === 1) : false;

                if (!allAnswered) {
                    showMessage(msgEl, cfg.i18n.answerAll, 'error');
                    return;
                }
                if (!visibilityRadios.length) {
                    showMessage(msgEl, cfg.i18n.chooseVisibility || 'Wybierz sposób oddania głosu (jawnie lub anonimowo).', 'error');
                    return;
                }

                submitBtn.disabled = true;
                var btnLabel = submitBtn.textContent;
                submitBtn.textContent = cfg.i18n.saving || 'Zapisuję głos…';
                var restoreBtn = function () { submitBtn.textContent = btnLabel; };

                var headers = authHeaders(form, pollId);
                headers['Content-Type'] = 'application/json';

                fetch(cfg.restUrl + '/polls/' + pollId + '/vote', {
                    method: 'POST',
                    credentials: headers['X-WP-Nonce'] ? 'same-origin' : 'omit',
                    headers: headers,
                    body: JSON.stringify({ answers: answers, is_anonymous: isAnonymous })
                })
                .then(function (r) {
                    return r.json().catch(function () { return { code: '__bad_response' }; });
                })
                .then(function (data) {
                    restoreBtn();
                    if (data.success) {
                        showVoteSuccess(form);
                    } else if (data.code === 'identity_invalid' && identityCtrl) {
                        identityCtrl.invalidate(data.message);
                    } else if (data.code === 'already_voted' || data.code === 'openvote_already_voted') {
                        showVoteSuccess(form, true);
                    } else {
                        showMessage(msgEl, mapVoteError(data), 'error');
                        submitBtn.disabled = false;
                    }
                })
                .catch(function () {
                    restoreBtn();
                    showMessage(msgEl, cfg.i18n.networkError || 'Brak połączenia. Sprawdź internet i spróbuj ponownie. Jeśli Twój głos został już zapisany, po odświeżeniu strony zobaczysz informację o tym.', 'error');
                    submitBtn.disabled = false;
                });
            });
        });
    }

    function showMessage(el, text, type) {
        if (!el) return;
        el.textContent = text;
        el.className = 'openvote-poll__message openvote-poll__message--' + type;
        if (type === 'error') {
            el.setAttribute('role', 'alert');
            try { el.scrollIntoView({ block: 'center', behavior: 'smooth' }); } catch (e) { /* ignore */ }
        }
    }

    function mapVoteError(data) {
        var c = data && data.code;
        var msg = (data && data.message) || '';
        if (c === 'rest_cookie_invalid_nonce' || c === 'rest_forbidden' || c === '__bad_response') {
            return c === '__bad_response'
                ? (cfg.i18n.badResponse || 'Serwer nie odpowiedział poprawnie. Odśwież stronę (klawisz F5). Jeśli Twój głos został zapisany, zobaczysz tam odpowiednią informację.')
                : (cfg.i18n.sessionExpired || 'Sesja wygasła. Odśwież stronę (klawisz F5) i spróbuj ponownie.');
        }
        return msg || cfg.i18n.voteError;
    }

    /* Po oddaniu głosu: zastępujemy formularz wyraźnym potwierdzeniem. */
    function showVoteSuccess(form, already) {
        form.dataset.done = '1';
        var box = document.createElement('div');
        box.className = 'openvote-poll__success';
        box.setAttribute('role', 'status');
        box.setAttribute('tabindex', '-1');
        var h = document.createElement('p');
        h.className = 'openvote-poll__success-title';
        h.textContent = '✓ ' + (already ? (cfg.i18n.alreadyVoted || 'Już oddałeś głos w tym głosowaniu. Dziękujemy!') : (cfg.i18n.voteSuccessTitle || 'Dziękujemy! Twój głos został zapisany.'));
        box.appendChild(h);
        var p = document.createElement('p');
        p.textContent = cfg.i18n.voteSuccessNote || 'Możesz zamknąć tę stronę.';
        box.appendChild(p);
        var cd = form.querySelector('.openvote-poll__countdown');
        if (cd) { box.appendChild(cd.cloneNode(true)); }
        form.parentNode.insertBefore(box, form);
        form.hidden = true;
        form.querySelectorAll('input, button').forEach(function (el) { el.disabled = true; });
        initCountdowns();
        try { box.scrollIntoView({ block: 'center', behavior: 'smooth' }); } catch (e) { /* ignore */ }
        box.focus({ preventScroll: true });
    }

    /* ── Results (ended polls) ── */

    function resultsError(container, msg) {
        container.innerHTML = '';
        var p = document.createElement('p');
        p.textContent = msg || (cfg.i18n.resultsFailed || 'Nie udało się pobrać wyników.');
        container.appendChild(p);
        var b = document.createElement('button');
        b.type = 'button';
        b.className = 'openvote-poll__retry';
        b.textContent = cfg.i18n.retry || 'Spróbuj ponownie';
        b.addEventListener('click', function () { initResults(container); });
        container.appendChild(b);
    }

    function initResults(only) {
        (only ? [only] : document.querySelectorAll('.openvote-poll__results')).forEach(function (container) {
            var pollId = container.dataset.pollId;
            if (!pollId) return;

            var block = container.closest('.openvote-poll-block');
            var nonce = (block && block.dataset.nonce) || cfg.nonce || '';
            var rHeaders = nonce ? { 'X-WP-Nonce': nonce } : {};

            fetch(cfg.restUrl + '/polls/' + pollId + '/results', {
                headers: rHeaders
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.code) {
                    resultsError(container, data.message || '');
                    return;
                }
                renderResults(container, data);
            })
            .catch(function () {
                resultsError(container, '');
            });
        });
    }

    function renderResults(container, data) {
        var eligible   = data.total_eligible || 0;
        var voted      = data.total_voters   || 0;
        var absent     = data.non_voters     || 0;
        var pctVoted   = eligible > 0 ? (voted  / eligible * 100).toFixed(1) : '0.0';
        var pctAbsent  = eligible > 0 ? (absent / eligible * 100).toFixed(1) : '0.0';

        var html = '<div class="openvote-results">';

        /* Participation summary */
        html += '<div class="openvote-results__participation">';
        html += '<h4>' + escapeHtml(cfg.i18n.participation) + '</h4>';
        html += participationRow(cfg.i18n.totalEligible, eligible, '100.0', 'eligible');
        html += participationRow(cfg.i18n.totalVoters,   voted,    pctVoted,  'voted');
        html += participationRow(cfg.i18n.totalAbsent,   absent,   pctAbsent, 'absent');
        html += '</div>';

        /* Per-question answer bars */
        data.questions.forEach(function (q, qi) {
            html += '<div class="openvote-results__question">';
            html += '<h4>' + (qi + 1) + '. ' + escapeHtml(q.question_text) + '</h4>';

            var maxCount = 0;
            q.answers.forEach(function (a0) { if (!a0.is_abstain && a0.count > maxCount) maxCount = a0.count; });
            q.answers.forEach(function (answer, ai) {
                var txt = String(answer.text || '').trim().toLowerCase();
                var barClass = answer.is_abstain
                    ? 'openvote-results__bar--wstrzymuje_sie'
                    : (txt === 'tak' ? 'openvote-results__bar--za' : (txt === 'nie' ? 'openvote-results__bar--przeciw' : 'openvote-results__bar--neutral'));
                var isTop = !answer.is_abstain && answer.count > 0 && answer.count === maxCount;

                var pct   = parseFloat(answer.pct) || 0;
                var label = answer.is_abstain
                    ? escapeHtml(cfg.i18n.abstainedLabel || 'Wstrzymało się')
                    : escapeHtml(answer.text);

                html += '<div class="openvote-results__bar-container">';
                html += '<div class="openvote-results__bar-label">';
                html += '<span>' + label + (isTop ? ' <strong class="openvote-results__top">✓ ' + escapeHtml(cfg.i18n.mostVotes || 'najwięcej głosów') + '</strong>' : '') + '</span>';
                html += '<span>' + answer.count + ' (' + pct.toFixed(1) + '%)</span>';
                html += '</div>';
                html += '<div class="openvote-results__bar ' + barClass + '" style="width:' + pct.toFixed(1) + '%"></div>';
                html += '</div>';
            });

            html += '</div>';
        });

        /* Voter list – anonymized nicenames, toggled */
        if (data.voters && data.voters.length) {
            html += '<div class="openvote-results__voters-section">';
            html += '<button type="button" class="openvote-voters-toggle" data-list="voters" aria-expanded="false">' + escapeHtml(cfg.i18n.showVoters) + '</button>';
            html += '<div class="openvote-results__voters" style="display:none">';
            html += '<h4>' + escapeHtml(cfg.i18n.voterList) + '</h4><ul>';
            data.voters.forEach(function (v) {
                html += '<li>' + escapeHtml(v.nicename) + '</li>';
            });
            html += '</ul></div></div>';
        }

        /* Non-voter list – eligible users who did not vote */
        if (data.non_voters_list && data.non_voters_list.length) {
            html += '<div class="openvote-results__voters-section">';
            html += '<button type="button" class="openvote-voters-toggle" data-list="non-voters" aria-expanded="false">' + escapeHtml(cfg.i18n.showNonVoters) + '</button>';
            html += '<div class="openvote-results__non-voters" style="display:none">';
            html += '<h4>' + escapeHtml(cfg.i18n.nonVoterList) + '</h4><ul>';
            data.non_voters_list.forEach(function (v) {
                html += '<li>' + escapeHtml(v.nicename) + '</li>';
            });
            html += '</ul></div></div>';
        }

        html += '</div>';
        container.innerHTML = html;

        /* Wire up voter-list toggle */
        container.querySelectorAll('.openvote-voters-toggle').forEach(function (toggleBtn) {
            toggleBtn.addEventListener('click', function () {
                var listKey = toggleBtn.getAttribute('data-list');
                var listEl = listKey === 'non-voters'
                    ? container.querySelector('.openvote-results__non-voters')
                    : container.querySelector('.openvote-results__voters');
                if (!listEl) return;
                var open = listEl.style.display !== 'none';
                listEl.style.display = open ? 'none' : '';
                toggleBtn.setAttribute('aria-expanded', open ? 'false' : 'true');
                if (listKey === 'non-voters') {
                    toggleBtn.textContent = open ? cfg.i18n.showNonVoters : cfg.i18n.hideNonVoters;
                } else {
                    toggleBtn.textContent = open ? cfg.i18n.showVoters : cfg.i18n.hideVoters;
                }
            });
        });
    }

    function participationRow(label, count, pct, cssKey) {
        return '<div class="openvote-results__bar-container">'
            + '<div class="openvote-results__bar-label">'
            + '<span>' + escapeHtml(label) + '</span>'
            + '<span>' + count + ' (' + pct + '%)</span>'
            + '</div>'
            + '<div class="openvote-results__bar openvote-results__bar--' + cssKey + '" style="width:' + pct + '%"></div>'
            + '</div>';
    }

    function escapeHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(String(str)));
        return div.innerHTML;
    }
})();
