/**
 * E-Voting Admin JS – dynamic questions + answers management + create-poll validation
 */
(function () {
    'use strict';

    var MAX_QUESTIONS = 24;
    var MAX_ANSWERS   = 12;
    var MAX_POLL_DAYS = 31;

    document.addEventListener('DOMContentLoaded', function () {
        var container = document.getElementById('openvote-questions-container');
        var addQBtn   = document.getElementById('openvote-add-question');
        var form      = document.getElementById('openvote-poll-form');
        var startBtn  = document.getElementById('openvote-btn-start-poll');

        if (form && startBtn) {
            initStartButtonValidation(form, startBtn, container);
        }

        if (!container || !addQBtn) return;

        // Toggle target group select.
        document.querySelectorAll('input[name="target_type"]').forEach(function (radio) {
            radio.addEventListener('change', toggleTargetGroup);
        });
        toggleTargetGroup();

        // Add question.
        addQBtn.addEventListener('click', function () {
            var blocks = container.querySelectorAll('.openvote-question-block');
            if (blocks.length >= MAX_QUESTIONS) {
                alert('Maksymalnie ' + MAX_QUESTIONS + ' pytań.');
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
                    alert('Maksymalnie ' + MAX_ANSWERS + ' odpowiedzi per pytanie.');
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
                    alert('Pytanie musi mieć co najmniej dwie dowolne odpowiedzi (plus wstrzymanie).');
                    return;
                }
                e.target.closest('.openvote-answer-row').remove();
                reindexAll();
                if (form) form.dispatchEvent(new Event('input'));
            }
        });

        function toggleTargetGroup() {
            var groupRadio = document.getElementById('target_group_radio');
            var wrapper    = document.getElementById('openvote-target-group-wrapper');
            if (!groupRadio || !wrapper) return;
            wrapper.style.display = groupRadio.checked ? '' : 'none';
        }

        function buildQuestionBlock(qIndex) {
            var block = document.createElement('div');
            block.className = 'openvote-question-block';
            block.dataset.questionIndex = qIndex;

            block.innerHTML =
                '<div class="openvote-question-header">' +
                    '<span class="openvote-question-number">' + (qIndex + 1) + '.</span>' +
                    '<input type="text" name="questions[' + qIndex + '][text]" maxlength="512" class="large-text" placeholder="Treść pytania">' +
                    '<button type="button" class="button openvote-remove-question">&times;</button>' +
                '</div>' +
                '<div class="openvote-answers-container">' +
                    buildAnswerRowHTML(qIndex, 0, 'Jestem za', false) +
                    buildAnswerRowHTML(qIndex, 1, 'Jestem przeciw', false) +
                    buildAnswerRowHTML(qIndex, 2, 'Wstrzymuję się', true) +
                '</div>' +
                '<button type="button" class="button button-small openvote-add-answer">+ Dodaj odpowiedź</button>';

            return block;
        }

        function buildAnswerRow(value) {
            var row = document.createElement('div');
            row.className = 'openvote-answer-row';
            row.innerHTML =
                '<span class="openvote-answer-bullet">&#8226;</span>' +
                '<input type="text" maxlength="512" class="regular-text" placeholder="Treść odpowiedzi" value="' + escapeAttr(value) + '">' +
                '<button type="button" class="button openvote-remove-answer">&times;</button>';
            return row;
        }

        function buildAnswerRowHTML(qIndex, aIndex, value, isAbstain) {
            if (isAbstain) {
                return '<div class="openvote-answer-row openvote-answer-row--locked">' +
                    '<span class="openvote-answer-bullet">&#8226;</span>' +
                    '<input type="text" name="questions[' + qIndex + '][answers][' + aIndex + ']" maxlength="512" class="regular-text" value="' + escapeAttr(value) + '" data-abstain="1">' +
                    '<span class="openvote-abstain-label">(wstrzymanie – auto)</span>' +
                '</div>';
            }
            return '<div class="openvote-answer-row">' +
                '<span class="openvote-answer-bullet">&#8226;</span>' +
                '<input type="text" name="questions[' + qIndex + '][answers][' + aIndex + ']" maxlength="512" class="regular-text" placeholder="Treść odpowiedzi" value="' + escapeAttr(value) + '">' +
                '<button type="button" class="button openvote-remove-answer">&times;</button>' +
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
            function isFormValid() {
                var titleEl = form.querySelector('#poll_title');
                var durationEl = form.querySelector('#poll_duration');
                if (!titleEl || titleEl.value.trim() === '') return false;
                if (!durationEl || !durationEl.value) return false;

                var groupsSelect = form.querySelector('#openvote-target-groups');
                if (groupsSelect && groupsSelect.options.length > 0) {
                    var selectedCount = 0;
                    if (groupsSelect.selectedOptions) {
                        selectedCount = groupsSelect.selectedOptions.length;
                    } else {
                        for (var g = 0; g < groupsSelect.options.length; g++) {
                            if (groupsSelect.options[g].selected) selectedCount++;
                        }
                    }
                    if (selectedCount < 1) return false;
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
                            if (answerInputs.length < 3) return false;
                            for (var j = 0; j < answerInputs.length; j++) {
                                var inp = answerInputs[j];
                                if (inp.getAttribute('data-abstain') !== '1' && inp.value.trim() === '') return false;
                            }
                        }
                    }
                    if (!hasQuestion) return false;
                }
                return true;
            }

            function updateButton() {
                startBtn.disabled = !isFormValid();
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
                    btn.textContent = 'Załaduj więcej (pokazano ' + visible + ' z ' + total + ')';
                }
            });
        });
    }
})();
