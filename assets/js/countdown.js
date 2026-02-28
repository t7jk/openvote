/**
 * E-Voting — Countdown Timer
 * Reads window.openvotePublic.i18n for translated time-unit labels.
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var els = document.querySelectorAll('.openvote-countdown[data-end]');
        if (!els.length) return;

        var i18n = (window.openvotePublic && window.openvotePublic.i18n) || {};

        function update() {
            els.forEach(function (el) {
                var diff = new Date(el.dataset.end).getTime() - Date.now();

                if (diff <= 0) {
                    el.textContent = i18n.ended || 'Głosowanie zakończone';
                    return;
                }

                var d = Math.floor(diff / 86400000);
                var h = Math.floor((diff % 86400000) / 3600000);
                var m = Math.floor((diff % 3600000) / 60000);
                var s = Math.floor((diff % 60000) / 1000);

                var parts = [];
                if (d > 0) parts.push(d + ' ' + (i18n.days    || 'd'));
                parts.push(h + ' ' + (i18n.hours   || 'godz.'));
                parts.push(m + ' ' + (i18n.minutes || 'min.'));
                parts.push(s + ' ' + (i18n.seconds || 'sek.'));

                el.textContent = parts.join(' ');
            });
        }

        update();
        setInterval(update, 1000);
    });
})();
