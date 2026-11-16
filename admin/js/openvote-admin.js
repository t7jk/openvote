/**
 * E-Voting Admin JS – dynamic questions + answers management + create-poll validation
 */
(function () {
    'use strict';

    var MAX_QUESTIONS = 24;
    var MAX_ANSWERS   = 12;
    var MAX_POLL_DAYS = 31;

    // Teksty z PHP (wp_localize_script) z polskimi wartościami zapasowymi.
    var I18N = window.openvoteAdminI18n || {};
    function t(key, fallback) {
        return (I18N && I18N[key]) ? I18N[key] : fallback;
    }

    document.addEventListener('DOMContentLoaded', function () {
        var container = document.getElementById('openvote-questions-container');
        var addQBtn   = document.getElementById('openvote-add-question');
        var form      = document.getElementById('openvote-poll-form');
        var startBtn  = document.getElementById('openvote-btn-start-poll');

        if (form && startBtn) {
            initStartButtonValidation(form, startBtn, container);
        }

        if (!container || !addQBtn) return;

        // Add question.
        addQBtn.addEventListener('click', function () {
            var blocks = container.querySelectorAll('.openvote-question-block');
            if (blocks.length >= MAX_QUESTIONS) {
                alert(t('maxQuestions', 'Maksymalnie %d pytań.').replace('%d', MAX_QUESTIONS));
                return;
            }
            var newBlock = buildQuestionBlock(blocks.length);
            container.appendChild(newBlock);
            newBlock.querySelector('input[type="text"]').focus();
            if (form) form.dispatchEvent(new Event('input'));
        });

        // Delegated events on container.
        container.addEventListener('click', function (e) {
            // Remove question.
            if (e.target.classList.contains('openvote-remove-question')) {
                var blocks = container.querySelectorAll('.openvote-question-block');
                if (blocks.length <= 1) return;
                e.target.closest('.openvote-question-block').remove();
                reindexAll();
                if (form) form.dispatchEvent(new Event('input'));
                return;
            }

            // Add answer.
            if (e.target.classList.contains('openvote-add-answer')) {
                var block    = e.target.closest('.openvote-question-block');
                var answersCtr = block.querySelector('.openvote-answers-container');
                var rows     = answersCtr.querySelectorAll('.openvote-answer-row');
                if (rows.length >= MAX_ANSWERS) {
                    alert(t('maxAnswers', 'Maksymalnie %d odpowiedzi na pytanie.').replace('%d', MAX_ANSWERS));
                    return;
                }
                // Insert before the last (abstain) row.
                var lastRow  = answersCtr.querySelector('.openvote-answer-row--locked');
                var newRow   = buildAnswerRow('');
                answersCtr.insertBefore(newRow, lastRow);
                reindexAll();
                newRow.querySelector('input').focus();
                if (form) form.dispatchEvent(new Event('input'));
                return;
            }

            // Remove answer.
            if (e.target.classList.contains('openvote-remove-answer')) {
                var answersCtr2 = e.target.closest('.openvote-answers-container');
                var nonLocked   = answersCtr2.querySelectorAll('.openvote-answer-row:not(.openvote-answer-row--locked)');
                if (nonLocked.length <= 2) {
                    alert(t('minAnswers', 'Pytanie musi mieć co najmniej dwie własne odpowiedzi plus „Wstrzymuję się”.'));
                    return;
                }
                e.target.closest('.openvote-answer-row').remove();
                reindexAll();
                if (form) form.dispatchEvent(new Event('input'));
            }
        });

        function buildQuestionBlock(qIndex) {
            var block = document.createElement('div');
            block.className = 'openvote-question-block';
            block.dataset.questionIndex = qIndex;

            block.innerHTML =
                '<div class="openvote-question-header">' +
                    '<span class="openvote-question-number">' + (qIndex + 1) + '.</span>' +
                    '<input type="text" name="questions[' + qIndex + '][text]" maxlength="512" class="large-text" placeholder="' + escapeAttr(t('questionText', 'Treść pytania')) + '">' +
                    '<button type="button" class="button openvote-remove-question" aria-label="' + escapeAttr(t('removeQuestion', 'Usuń pytanie')) + '">&times;</button>' +
                '</div>' +
                '<div class="openvote-answers-container">' +
                    buildAnswerRowHTML(qIndex, 0, 'Jestem za', false) +
                    buildAnswerRowHTML(qIndex, 1, 'Jestem przeciw', false) +
                    buildAnswerRowHTML(qIndex, 2, 'Wstrzymuję się', true) +
                '</div>' +
                '<button type="button" class="button button-small openvote-add-answer">+ ' + escapeAttr(t('addAnswer', 'Dodaj odpowiedź')) + '</button>';

            return block;
        }

        function buildAnswerRow(value) {
            var row = document.createElement('div');
            row.className = 'openvote-answer-row';
            row.innerHTML =
                '<span class="openvote-answer-bullet">&#8226;</span>' +
                '<input type="text" maxlength="512" class="regular-text" placeholder="' + escapeAttr(t('answerText', 'Treść odpowiedzi')) + '" value="' + escapeAttr(value) + '">' +
                '<button type="button" class="button openvote-remove-answer" aria-label="' + escapeAttr(t('removeAnswer', 'Usuń odpowiedź')) + '">&times;</button>';
            return row;
        }

        function buildAnswerRowHTML(qIndex, aIndex, value, isAbstain) {
            if (isAbstain) {
                return '<div class="openvote-answer-row openvote-answer-row--locked">' +
                    '<span class="openvote-answer-bullet">&#8226;</span>' +
                    '<input type="text" name="questions[' + qIndex + '][answers][' + aIndex + ']" maxlength="512" class="regular-text" value="' + escapeAttr(value) + '" data-abstain="1">' +
                    '<span class="openvote-abstain-label">' + escapeAttr(t('abstainAuto', '(wstrzymanie – auto)')) + '</span>' +
                '</div>';
            }
            return '<div class="openvote-answer-row">' +
                '<span class="openvote-answer-bullet">&#8226;</span>' +
                '<input type="text" name="questions[' + qIndex + '][answers][' + aIndex + ']" maxlength="512" class="regular-text" placeholder="' + escapeAttr(t('answerText', 'Treść odpowiedzi')) + '" value="' + escapeAttr(value) + '">' +
                '<button type="button" class="button openvote-remove-answer" aria-label="' + escapeAttr(t('removeAnswer', 'Usuń odpowiedź')) + '">&times;</button>' +
            '</div>';
        }

        function reindexAll() {
            var blocks = container.querySelectorAll('.openvote-question-block');

            blocks.forEach(function (block, qi) {
                block.dataset.questionIndex = qi;

                var numEl = block.querySelector('.openvote-question-number');
                if (numEl) numEl.textContent = (qi + 1) + '.';

                var qInput = block.querySelector('.openvote-question-header input[type="text"]');
                if (qInput) qInput.name = 'questions[' + qi + '][text]';

                var rows = block.querySelectorAll('.openvote-answers-container .openvote-answer-row');
                rows.forEach(function (row, ai) {
                    var input = row.querySelector('input[type="text"]');
                    if (input) input.name = 'questions[' + qi + '][answers][' + ai + ']';
                });
            });
        }

        function escapeAttr(str) {
            return str.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        }

        function initStartButtonValidation(form, startBtn, container) {
            var hintEl = document.getElementById('openvote-start-hint');

            // Zwraca listę braków (puste = można startować).
            function getMissing() {
                var missing = [];
                var titleEl = form.querySelector('#poll_title');
                var durationEl = form.querySelector('#poll_duration');
                if (!titleEl || titleEl.value.trim() === '') missing.push(t('missTitle', 'tytułu'));
                if (!durationEl || !durationEl.value) missing.push(t('missDuration', 'czasu trwania'));

                var groupsSelect = form.querySelector('#openvote-target-groups');
                if (groupsSelect && groupsSelect.options.length > 0) {
                    var selectedCount = 0;
                    for (var g = 0; g < groupsSelect.options.length; g++) {
                        if (groupsSelect.options[g].selected) selectedCount++;
                    }
                    if (selectedCount < 1) missing.push(t('missGroup', 'grupy docelowej'));
                }

                if (container) {
                    var blocks = container.querySelectorAll('.openvote-question-block');
                    var hasQuestion = false;
                    for (var i = 0; i < blocks.length; i++) {
                        var qInput = blocks[i].querySelector('.openvote-question-header input[type="text"]');
                        var qText = qInput ? qInput.value.trim() : '';
                        if (qText !== '') {
                            hasQuestion = true;
                            var answerInputs = blocks[i].querySelectorAll('.openvote-answers-container input[type="text"]');
                            if (answerInputs.length < 3) {
                                missing.push(t('missAnswers', 'min. 3 odpowiedzi w pytaniu %d').replace('%d', i + 1));
                                continue;
                            }
                            var seen = {};
                            for (var j = 0; j < answerInputs.length; j++) {
                                var inp = answerInputs[j];
                                var val = inp.value.trim();
                                if (inp.getAttribute('data-abstain') !== '1' && val === '') {
                                    missing.push(t('missAnswerText', 'treści %d. odpowiedzi w pytaniu %d').replace('%d', j + 1).replace('%d', i + 1));
                                } else if (val !== '') {
                                    var key = val.toLowerCase();
                                    if (seen[key]) {
                                        missing.push(t('missDuplicate', 'powtórzona odpowiedź w pytaniu %d').replace('%d', i + 1));
                                        break;
                                    }
                                    seen[key] = true;
                                }
                            }
                        }
                    }
                    if (!hasQuestion) missing.push(t('missQuestion', 'treści pytania'));
                }
                return missing;
            }

            function updateButton() {
                var missing = getMissing();
                startBtn.disabled = missing.length > 0;
                var msg = missing.length ? (t('missPrefix', 'Aby wystartować, uzupełnij: ') + missing.join(', ') + '.') : '';
                startBtn.title = msg;
                if (hintEl) hintEl.textContent = msg;
            }

            updateButton();
            form.addEventListener('input', updateButton);
            form.addEventListener('change', updateButton);
        }

        initLoadMore();
    });

    function initLoadMore() {
        document.querySelectorAll('.openvote-load-more').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var tableId  = btn.getAttribute('data-table');
                var pageSize = parseInt(btn.getAttribute('data-page-size'), 10) || 100;
                var table    = document.getElementById(tableId);
                if (!table) return;

                var hidden = Array.prototype.slice.call(
                    table.querySelectorAll('tbody tr.openvote-row-hidden')
                ).slice(0, pageSize);

                hidden.forEach(function (row) {
                    row.style.display = '';
                    row.classList.remove('openvote-row-hidden');
                });

                var remaining = table.querySelectorAll('tbody tr.openvote-row-hidden').length;
                var visible   = table.querySelectorAll('tbody tr').length - remaining;
                var total     = table.querySelectorAll('tbody tr').length;

                if (remaining === 0) {
                    btn.parentNode.removeChild(btn.parentNode.contains(btn) ? btn : btn);
                    btn.remove();
                } else {
                    btn.textContent = t('loadMore', 'Załaduj więcej (pokazano %1$d z %2$d)').replace('%1$d', visible).replace('%2$d', total);
                }
            });
        });
    }
})();
