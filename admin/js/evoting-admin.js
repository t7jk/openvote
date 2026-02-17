/**
 * E-Voting Admin JS – dynamic question management
 */
(function () {
    'use strict';

    const MAX_QUESTIONS = 12;

    document.addEventListener('DOMContentLoaded', function () {
        const container = document.getElementById('evoting-questions-container');
        const addBtn = document.getElementById('evoting-add-question');

        if (!container || !addBtn) {
            return;
        }

        addBtn.addEventListener('click', function () {
            const rows = container.querySelectorAll('.evoting-question-row');
            if (rows.length >= MAX_QUESTIONS) {
                alert('Maksymalnie ' + MAX_QUESTIONS + ' pytań.');
                return;
            }

            const row = document.createElement('div');
            row.className = 'evoting-question-row';

            const num = document.createElement('span');
            num.className = 'evoting-question-number';
            num.textContent = (rows.length + 1) + '.';

            const input = document.createElement('input');
            input.type = 'text';
            input.name = 'questions[]';
            input.className = 'regular-text';
            input.placeholder = 'Treść pytania';

            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'button evoting-remove-question';
            removeBtn.textContent = '\u00d7';

            row.appendChild(num);
            row.appendChild(input);
            row.appendChild(removeBtn);
            container.appendChild(row);

            input.focus();
        });

        container.addEventListener('click', function (e) {
            if (!e.target.classList.contains('evoting-remove-question')) {
                return;
            }

            const rows = container.querySelectorAll('.evoting-question-row');
            if (rows.length <= 1) {
                return;
            }

            e.target.closest('.evoting-question-row').remove();
            renumberQuestions();
        });

        function renumberQuestions() {
            const rows = container.querySelectorAll('.evoting-question-row');
            rows.forEach(function (row, i) {
                var num = row.querySelector('.evoting-question-number');
                if (num) {
                    num.textContent = (i + 1) + '.';
                }
            });
        }
    });
})();
