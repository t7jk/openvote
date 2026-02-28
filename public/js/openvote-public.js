/**
 * E-Voting Public JS – voting form, countdown, results loading
 */
(function () {
    'use strict';

    var cfg = window.openvotePublic || {};

    document.addEventListener('DOMContentLoaded', function () {
        initCountdowns();
        initVotingForms();
        initResults();
    });

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
                    return;
                }

                var d = Math.floor(diff / 86400000);
                var h = Math.floor((diff % 86400000) / 3600000);
                var m = Math.floor((diff % 3600000) / 60000);
                var s = Math.floor((diff % 60000) / 1000);

                var parts = [];
                if (d > 0) parts.push(d + ' ' + cfg.i18n.days);
                parts.push(h + ' ' + cfg.i18n.hours);
                parts.push(m + ' ' + cfg.i18n.minutes);
                parts.push(s + ' ' + cfg.i18n.seconds);

                el.textContent = parts.join(' ');
            });
        }

        update();
        setInterval(update, 1000);
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

    function updateVoteSubmitButton(form) {
        var submitBtn = form.querySelector('.openvote-poll__submit');
        if (submitBtn) {
            submitBtn.disabled = !isFormVoteComplete(form);
        }
    }

    function initVotingForms() {
        document.querySelectorAll('.openvote-poll__form').forEach(function (form) {
            var submitBtn = form.querySelector('.openvote-poll__submit');
            if (submitBtn) {
                submitBtn.disabled = true;
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

                var block = form.closest('.openvote-poll-block');
                var nonce = block ? block.dataset.nonce : cfg.nonce;

                fetch(cfg.restUrl + '/polls/' + pollId + '/vote', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
                    body: JSON.stringify({ answers: answers, is_anonymous: isAnonymous })
                })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.success) {
                        showMessage(msgEl, cfg.i18n.voteSuccess, 'success');
                        form.querySelectorAll('input, button').forEach(function (el) { el.disabled = true; });
                    } else {
                        showMessage(msgEl, data.message || cfg.i18n.voteError, 'error');
                        submitBtn.disabled = false;
                    }
                })
                .catch(function () {
                    showMessage(msgEl, cfg.i18n.voteError, 'error');
                    submitBtn.disabled = false;
                });
            });
        });
    }

    function showMessage(el, text, type) {
        if (!el) return;
        el.textContent = text;
        el.className = 'openvote-poll__message openvote-poll__message--' + type;
    }

    /* ── Results (ended polls) ── */

    function initResults() {
        document.querySelectorAll('.openvote-poll__results').forEach(function (container) {
            var pollId = container.dataset.pollId;
            if (!pollId) return;

            var block = container.closest('.openvote-poll-block');
            var nonce = block ? block.dataset.nonce : cfg.nonce;

            fetch(cfg.restUrl + '/polls/' + pollId + '/results', {
                headers: { 'X-WP-Nonce': nonce }
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.code) {
                    container.innerHTML = '<p>' + escapeHtml(data.message || '') + '</p>';
                    return;
                }
                renderResults(container, data);
            })
            .catch(function () {
                container.innerHTML = '<p>' + escapeHtml(cfg.i18n.voteError) + '</p>';
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

            q.answers.forEach(function (answer, ai) {
                var barClass = answer.is_abstain
                    ? 'openvote-results__bar--wstrzymuje_sie'
                    : (ai === 0 ? 'openvote-results__bar--za' : 'openvote-results__bar--przeciw');

                var pct   = parseFloat(answer.pct) || 0;
                var label = escapeHtml(answer.text);
                if (answer.is_abstain) {
                    label += ' <em>(' + escapeHtml(cfg.i18n.inclAbsent) + ')</em>';
                }

                html += '<div class="openvote-results__bar-container">';
                html += '<div class="openvote-results__bar-label">';
                html += '<span>' + label + '</span>';
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
            html += '<button type="button" class="openvote-voters-toggle" data-list="voters">' + escapeHtml(cfg.i18n.showVoters) + '</button>';
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
            html += '<button type="button" class="openvote-voters-toggle" data-list="non-voters">' + escapeHtml(cfg.i18n.showNonVoters) + '</button>';
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
