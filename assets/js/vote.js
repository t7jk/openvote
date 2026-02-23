/**
 * E-Voting — AJAX vote submission & results rendering.
 * Depends on: window.evotingPublic (localized by PHP)
 */
(function () {
    'use strict';

    var cfg = window.evotingPublic || {};

    document.addEventListener('DOMContentLoaded', function () {
        initVotingForms();
        initResults();
    });

    /* ── Voting Forms ── */

    function initVotingForms() {
        document.querySelectorAll('.evoting-poll__form').forEach(function (form) {
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                handleSubmit(form);
            });
        });
    }

    function handleSubmit(form) {
        var pollId    = form.dataset.pollId;
        var msgEl     = form.querySelector('.evoting-poll__message');
        var submitBtn = form.querySelector('.evoting-poll__submit');
        var i18n      = cfg.i18n || {};

        // Collect answers: question_id → answer_id.
        var answers     = {};
        var allAnswered = true;

        form.querySelectorAll('.evoting-poll__question').forEach(function (fs) {
            var checked = fs.querySelector('input[type="radio"]:checked');
            if (!checked) { allAnswered = false; return; }
            var qId = parseInt(checked.name.replace('question_', ''), 10);
            answers[qId] = parseInt(checked.value, 10);
        });

        var visibilityRadios = form.querySelectorAll('input[name="evoting_vote_visibility"]:checked');
        var isAnonymous = visibilityRadios.length ? (parseInt(visibilityRadios[0].value, 10) === 1) : false;

        if (!allAnswered) {
            showMessage(msgEl, i18n.answerAll || 'Odpowiedz na wszystkie pytania.', 'error');
            return;
        }
        if (!visibilityRadios.length) {
            showMessage(msgEl, i18n.chooseVisibility || 'Wybierz sposób oddania głosu (jawnie lub anonimowo).', 'error');
            return;
        }

        if (submitBtn) submitBtn.disabled = true;

        var block   = form.closest('.evoting-poll-block');
        var nonce   = (block && block.dataset.nonce) ? block.dataset.nonce : (cfg.nonce || '');
        var restUrl = cfg.restUrl || '/wp-json/evoting/v1';

        fetch(restUrl + '/polls/' + pollId + '/vote', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
            body:    JSON.stringify({ answers: answers, is_anonymous: isAnonymous }),
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) {
                showMessage(msgEl, i18n.voteSuccess || 'Głos oddany!', 'success');
                form.querySelectorAll('input, button').forEach(function (el) { el.disabled = true; });
            } else {
                showMessage(msgEl, data.message || i18n.voteError || 'Błąd głosowania.', 'error');
                if (submitBtn) submitBtn.disabled = false;
            }
        })
        .catch(function () {
            showMessage(msgEl, i18n.voteError || 'Błąd połączenia.', 'error');
            if (submitBtn) submitBtn.disabled = false;
        });
    }

    function showMessage(el, text, type) {
        if (!el) return;
        el.textContent = text;
        el.className = 'evoting-poll__message evoting-poll__message--' + type;
    }

    /* ── Results (ended polls) ── */

    function initResults() {
        document.querySelectorAll('.evoting-poll__results[data-poll-id]').forEach(function (container) {
            var pollId  = container.dataset.pollId;
            var block   = container.closest('.evoting-poll-block');
            var nonce   = (block && block.dataset.nonce) ? block.dataset.nonce : (cfg.nonce || '');
            var restUrl = cfg.restUrl || '/wp-json/evoting/v1';

            fetch(restUrl + '/polls/' + pollId + '/results', {
                headers: { 'X-WP-Nonce': nonce },
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.code) {
                    container.innerHTML = '<p>' + escHtml(data.message || '') + '</p>';
                    return;
                }
                renderResults(container, data);
            })
            .catch(function () {
                var i18n = cfg.i18n || {};
                container.innerHTML = '<p>' + escHtml(i18n.voteError || 'Błąd ładowania wyników.') + '</p>';
            });
        });
    }

    function renderResults(container, data) {
        var i18n      = cfg.i18n || {};
        var eligible  = data.total_eligible || 0;
        var voted     = data.total_voters   || 0;
        var absent    = data.non_voters     || 0;
        var pctVoted  = eligible > 0 ? (voted  / eligible * 100).toFixed(1) : '0.0';
        var pctAbsent = eligible > 0 ? (absent / eligible * 100).toFixed(1) : '0.0';

        var html = '<div class="evoting-results">';

        // Participation summary.
        html += '<div class="evoting-results__participation">';
        html += '<h4>' + escHtml(i18n.participation || 'Frekwencja') + '</h4>';
        html += participationRow(i18n.totalEligible || 'Uprawnionych',         eligible, '100.0',    'eligible');
        html += participationRow(i18n.totalVoters   || 'Uczestniczyło',        voted,    pctVoted,   'voted');
        html += participationRow(i18n.totalAbsent   || 'Nie uczestniczyło',    absent,   pctAbsent,  'absent');
        html += '</div>';

        // Per-question answer bars.
        (data.questions || []).forEach(function (q, qi) {
            html += '<div class="evoting-results__question">';
            html += '<h4>' + (qi + 1) + '. ' + escHtml(q.question_text) + '</h4>';

            q.answers.forEach(function (a, ai) {
                var barClass = a.is_abstain
                    ? 'evoting-results__bar--wstrzymuje_sie'
                    : (ai === 0 ? 'evoting-results__bar--za' : 'evoting-results__bar--przeciw');

                var pct   = parseFloat(a.pct) || 0;
                var label = escHtml(a.text);
                if (a.is_abstain) {
                    label += ' <em>(' + escHtml(i18n.inclAbsent || 'inc. brak głosu') + ')</em>';
                }

                html += '<div class="evoting-results__bar-container">';
                html += '<div class="evoting-results__bar-label">'
                      + '<span>' + label + '</span>'
                      + '<span>' + a.count + ' (' + pct.toFixed(1) + '%)</span>'
                      + '</div>';
                html += '<div class="evoting-results__bar ' + barClass + '" style="width:' + pct.toFixed(1) + '%"></div>';
                html += '</div>';
            });

            html += '</div>';
        });

        // Anonymous mode notice.
        if (data.anonymous_msg) {
            html += '<p class="evoting-results__anon-msg">' + escHtml(data.anonymous_msg) + '</p>';
        }

        // Voter list — anonymized nicenames, toggled (public mode only).
        if (data.voters && data.voters.length) {
            html += '<div class="evoting-results__voters-section">';
            html += '<button type="button" class="evoting-voters-toggle">'
                  + escHtml(i18n.showVoters || 'Pokaż głosujących') + '</button>';
            html += '<div class="evoting-results__voters" hidden>';
            html += '<h4>' + escHtml(i18n.voterList || 'Głosujący (anonimowo):') + '</h4><ul>';
            data.voters.forEach(function (v) {
                html += '<li>' + escHtml(v.nicename) + '</li>';
            });
            html += '</ul></div></div>';
        }

        html += '</div>';
        container.innerHTML = html;

        // Wire voter-list toggle.
        var btn  = container.querySelector('.evoting-voters-toggle');
        var list = container.querySelector('.evoting-results__voters');
        if (btn && list) {
            btn.addEventListener('click', function () {
                var open = !list.hasAttribute('hidden');
                if (open) {
                    list.setAttribute('hidden', '');
                    btn.textContent = i18n.showVoters || 'Pokaż głosujących';
                } else {
                    list.removeAttribute('hidden');
                    btn.textContent = i18n.hideVoters || 'Ukryj głosujących';
                }
            });
        }
    }

    function participationRow(label, count, pct, cssKey) {
        return '<div class="evoting-results__bar-container">'
            + '<div class="evoting-results__bar-label">'
            + '<span>' + escHtml(label) + '</span>'
            + '<span>' + count + ' (' + pct + '%)</span>'
            + '</div>'
            + '<div class="evoting-results__bar evoting-results__bar--' + cssKey + '" style="width:' + pct + '%"></div>'
            + '</div>';
    }

    function escHtml(str) {
        var d = document.createElement('div');
        d.appendChild(document.createTextNode(String(str || '')));
        return d.innerHTML;
    }
})();
