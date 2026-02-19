/**
 * E-Voting Admin JS – dynamic questions + answers management
 */
(function () {
    'use strict';

    var MAX_QUESTIONS = 24;
    var MAX_ANSWERS   = 12;

    document.addEventListener('DOMContentLoaded', function () {
        var container = document.getElementById('evoting-questions-container');
        var addQBtn   = document.getElementById('evoting-add-question');

        if (!container || !addQBtn) return;

        // Toggle target group select.
        document.querySelectorAll('input[name="target_type"]').forEach(function (radio) {
            radio.addEventListener('change', toggleTargetGroup);
        });
        toggleTargetGroup();

        // Add question.
        addQBtn.addEventListener('click', function () {
            var blocks = container.querySelectorAll('.evoting-question-block');
            if (blocks.length >= MAX_QUESTIONS) {
                alert('Maksymalnie ' + MAX_QUESTIONS + ' pytań.');
                return;
            }
            var newBlock = buildQuestionBlock(blocks.length);
            container.appendChild(newBlock);
            newBlock.querySelector('input[type="text"]').focus();
        });

        // Delegated events on container.
        container.addEventListener('click', function (e) {
            // Remove question.
            if (e.target.classList.contains('evoting-remove-question')) {
                var blocks = container.querySelectorAll('.evoting-question-block');
                if (blocks.length <= 1) return;
                e.target.closest('.evoting-question-block').remove();
                reindexAll();
                return;
            }

            // Add answer.
            if (e.target.classList.contains('evoting-add-answer')) {
                var block    = e.target.closest('.evoting-question-block');
                var answersCtr = block.querySelector('.evoting-answers-container');
                var rows     = answersCtr.querySelectorAll('.evoting-answer-row');
                if (rows.length >= MAX_ANSWERS) {
                    alert('Maksymalnie ' + MAX_ANSWERS + ' odpowiedzi per pytanie.');
                    return;
                }
                // Insert before the last (abstain) row.
                var lastRow  = answersCtr.querySelector('.evoting-answer-row--locked');
                var newRow   = buildAnswerRow('');
                answersCtr.insertBefore(newRow, lastRow);
                reindexAll();
                newRow.querySelector('input').focus();
                return;
            }

            // Remove answer.
            if (e.target.classList.contains('evoting-remove-answer')) {
                var answersCtr2 = e.target.closest('.evoting-answers-container');
                var nonLocked   = answersCtr2.querySelectorAll('.evoting-answer-row:not(.evoting-answer-row--locked)');
                if (nonLocked.length <= 2) {
                    alert('Pytanie musi mieć co najmniej dwie dowolne odpowiedzi (plus wstrzymanie).');
                    return;
                }
                e.target.closest('.evoting-answer-row').remove();
                reindexAll();
            }
        });

        function toggleTargetGroup() {
            var groupRadio = document.getElementById('target_group_radio');
            var wrapper    = document.getElementById('evoting-target-group-wrapper');
            if (!groupRadio || !wrapper) return;
            wrapper.style.display = groupRadio.checked ? '' : 'none';
        }

        function buildQuestionBlock(qIndex) {
            var block = document.createElement('div');
            block.className = 'evoting-question-block';
            block.dataset.questionIndex = qIndex;

            block.innerHTML =
                '<div class="evoting-question-header">' +
                    '<span class="evoting-question-number">' + (qIndex + 1) + '.</span>' +
                    '<input type="text" name="questions[' + qIndex + '][text]" maxlength="512" class="large-text" placeholder="Treść pytania">' +
                    '<button type="button" class="button evoting-remove-question">&times;</button>' +
                '</div>' +
                '<div class="evoting-answers-container">' +
                    buildAnswerRowHTML(qIndex, 0, 'Jestem za', false) +
                    buildAnswerRowHTML(qIndex, 1, 'Jestem przeciw', false) +
                    buildAnswerRowHTML(qIndex, 2, 'Wstrzymuję się', true) +
                '</div>' +
                '<button type="button" class="button button-small evoting-add-answer">+ Dodaj odpowiedź</button>';

            return block;
        }

        function buildAnswerRow(value) {
            var row = document.createElement('div');
            row.className = 'evoting-answer-row';
            row.innerHTML =
                '<span class="evoting-answer-bullet">&#8226;</span>' +
                '<input type="text" maxlength="512" class="regular-text" placeholder="Treść odpowiedzi" value="' + escapeAttr(value) + '">' +
                '<button type="button" class="button evoting-remove-answer">&times;</button>';
            return row;
        }

        function buildAnswerRowHTML(qIndex, aIndex, value, isAbstain) {
            if (isAbstain) {
                return '<div class="evoting-answer-row evoting-answer-row--locked">' +
                    '<span class="evoting-answer-bullet">&#8226;</span>' +
                    '<input type="text" name="questions[' + qIndex + '][answers][' + aIndex + ']" maxlength="512" class="regular-text" value="' + escapeAttr(value) + '" data-abstain="1">' +
                    '<span class="evoting-abstain-label">(wstrzymanie – auto)</span>' +
                '</div>';
            }
            return '<div class="evoting-answer-row">' +
                '<span class="evoting-answer-bullet">&#8226;</span>' +
                '<input type="text" name="questions[' + qIndex + '][answers][' + aIndex + ']" maxlength="512" class="regular-text" placeholder="Treść odpowiedzi" value="' + escapeAttr(value) + '">' +
                '<button type="button" class="button evoting-remove-answer">&times;</button>' +
            '</div>';
        }

        function reindexAll() {
            var blocks = container.querySelectorAll('.evoting-question-block');

            blocks.forEach(function (block, qi) {
                block.dataset.questionIndex = qi;

                var numEl = block.querySelector('.evoting-question-number');
                if (numEl) numEl.textContent = (qi + 1) + '.';

                var qInput = block.querySelector('.evoting-question-header input[type="text"]');
                if (qInput) qInput.name = 'questions[' + qi + '][text]';

                var rows = block.querySelectorAll('.evoting-answers-container .evoting-answer-row');
                rows.forEach(function (row, ai) {
                    var input = row.querySelector('input[type="text"]');
                    if (input) input.name = 'questions[' + qi + '][answers][' + ai + ']';
                });
            });
        }

        function escapeAttr(str) {
            return str.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        }
    });
})();
