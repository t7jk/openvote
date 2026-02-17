/**
 * E-Voting Public JS – voting form, countdown, results loading
 */
(function () {
    'use strict';

    var cfg = window.evotingPublic || {};

    document.addEventListener('DOMContentLoaded', function () {
        initCountdowns();
        initVotingForms();
        initResults();
    });

    /* ── Countdown Timer ── */

    function initCountdowns() {
        var countdowns = document.querySelectorAll('.evoting-countdown');
        if (!countdowns.length) return;

        function update() {
            countdowns.forEach(function (el) {
                var end = new Date(el.dataset.end).getTime();
                var now = Date.now();
                var diff = end - now;

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

    function initVotingForms() {
        var forms = document.querySelectorAll('.evoting-poll__form');

        forms.forEach(function (form) {
            form.addEventListener('submit', function (e) {
                e.preventDefault();

                var pollId = form.dataset.pollId;
                var msgEl = form.querySelector('.evoting-poll__message');
                var submitBtn = form.querySelector('.evoting-poll__submit');

                // Collect answers.
                var answers = {};
                var fieldsets = form.querySelectorAll('.evoting-poll__question');
                var allAnswered = true;

                fieldsets.forEach(function (fs) {
                    var checked = fs.querySelector('input[type="radio"]:checked');
                    if (!checked) {
                        allAnswered = false;
                        return;
                    }
                    // Extract question_id from name: "question_123" → 123
                    var qId = checked.name.replace('question_', '');
                    answers[qId] = checked.value;
                });

                if (!allAnswered) {
                    showMessage(msgEl, cfg.i18n.answerAll, 'error');
                    return;
                }

                submitBtn.disabled = true;

                fetch(cfg.restUrl + '/polls/' + pollId + '/vote', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': cfg.nonce
                    },
                    body: JSON.stringify({ answers: answers })
                })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (data.success) {
                        showMessage(msgEl, cfg.i18n.voteSuccess, 'success');
                        // Disable form.
                        form.querySelectorAll('input, button').forEach(function (el) {
                            el.disabled = true;
                        });
                    } else {
                        var errMsg = data.message || cfg.i18n.voteError;
                        showMessage(msgEl, errMsg, 'error');
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
        el.className = 'evoting-poll__message evoting-poll__message--' + type;
    }

    /* ── Results Loading (for ended polls) ── */

    function initResults() {
        var containers = document.querySelectorAll('.evoting-poll__results');

        containers.forEach(function (container) {
            var pollId = container.dataset.pollId;
            if (!pollId) return;

            // Use nonce from parent block wrapper.
            var block = container.closest('.evoting-poll-block');
            var nonce = block ? block.dataset.nonce : cfg.nonce;

            fetch(cfg.restUrl + '/polls/' + pollId + '/results', {
                headers: { 'X-WP-Nonce': nonce }
            })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.code) {
                    container.innerHTML = '<p>' + (data.message || '') + '</p>';
                    return;
                }
                renderResults(container, data);
            })
            .catch(function () {
                container.innerHTML = '<p>' + cfg.i18n.voteError + '</p>';
            });
        });
    }

    function renderResults(container, data) {
        var html = '<div class="evoting-results">';

        html += '<p class="evoting-results__summary">' +
                cfg.i18n.totalVoters + ' ' + data.total_voters +
                '</p>';

        var answerLabels = {
            za: cfg.i18n.za,
            przeciw: cfg.i18n.przeciw,
            wstrzymuje_sie: cfg.i18n.wstrzymuje
        };

        data.questions.forEach(function (q, i) {
            html += '<div class="evoting-results__question">';
            html += '<h4>' + (i + 1) + '. ' + escapeHtml(q.question_text) + '</h4>';

            var total = Math.max(q.total, 1);

            ['za', 'przeciw', 'wstrzymuje_sie'].forEach(function (key) {
                var count = q[key] || 0;
                var pct = (count / total * 100).toFixed(1);

                html += '<div class="evoting-results__bar-container">';
                html += '<div class="evoting-results__bar-label">';
                html += '<span>' + answerLabels[key] + '</span>';
                html += '<span>' + count + ' (' + pct + '%)</span>';
                html += '</div>';
                html += '<div class="evoting-results__bar evoting-results__bar--' + key + '" style="width:' + pct + '%"></div>';
                html += '</div>';
            });

            html += '</div>';
        });

        // Voter list (anonymous).
        if (data.voters && data.voters.length) {
            html += '<div class="evoting-results__voters">';
            html += '<h4>' + cfg.i18n.voterList + '</h4><ul>';
            data.voters.forEach(function (v) {
                html += '<li>' + escapeHtml(v.pseudonym) + '</li>';
            });
            html += '</ul></div>';
        }

        html += '</div>';
        container.innerHTML = html;
    }

    function escapeHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }
})();
