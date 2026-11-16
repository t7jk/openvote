/**
 * OpenVote — zakładki ekranów admina (Konfiguracja, Grupy, Koordynatorzy, Wysyłka).
 * Każdy .openvote-admin-tabs / .openvote-settings-tabs jest inicjalizowany osobno (klucz sessionStorage z data-tabs-key).
 * Wszystkie panele pozostają w DOM jednego formularza (pola zawsze wysyłane);
 * przełączanie wyłącznie przez klasę CSS. Bez JS wszystkie sekcje są widoczne.
 */
(function () {
    'use strict';

    // Inicjalizuje każdy pasek zakładek (.openvote-admin-tabs lub .openvote-settings-tabs) niezależnie.
    // Panele (.openvote-tab-panel[data-tab]) są wyszukiwane w obrębie kontenera paska (.wrap) lub dokumentu.
    var navs = Array.prototype.slice.call(document.querySelectorAll('.openvote-admin-tabs, .openvote-settings-tabs'));
    navs.forEach(initTabs);

    function initTabs(nav) {
        var tabs = Array.prototype.slice.call(nav.querySelectorAll('[role="tab"]'));
        if (!tabs.length) return;
        var wrap = nav.parentNode;
        var panels = Array.prototype.slice.call(wrap.querySelectorAll('.openvote-tab-panel'));
        var forms = Array.prototype.slice.call(wrap.querySelectorAll('form'));
        // Klucz sessionStorage na ekran; Konfiguracja zachowuje dotychczasowy klucz.
        var screenKey = nav.getAttribute('data-tabs-key') || 'settings';
        var KEY = 'openvote_' + screenKey + '_tab';

        function names() { return tabs.map(function (t) { return t.getAttribute('data-tab'); }); }

        function show(name, focus) {
            if (names().indexOf(name) === -1) name = names()[0];
            tabs.forEach(function (t) {
                var on = t.getAttribute('data-tab') === name;
                t.classList.toggle('nav-tab-active', on);
                t.setAttribute('aria-selected', on ? 'true' : 'false');
                t.setAttribute('tabindex', on ? '0' : '-1');
                if (on && focus) t.focus();
            });
            panels.forEach(function (p) {
                p.setAttribute('tabindex', '0');
                p.classList.toggle('is-active', p.getAttribute('data-tab') === name);
            });
            try { sessionStorage.setItem(KEY, name); } catch (e) {}
            return name;
        }

        function setHash(name) {
            if (window.history && history.replaceState) {
                history.replaceState(null, '', location.pathname + location.search + '#tab-' + name);
            }
        }

        function fromHash() {
            var m = /^#tab-([a-z0-9_-]+)$/.exec(location.hash || '');
            return m ? m[1] : null;
        }

        function initial() {
            var h = fromHash();
            if (h && names().indexOf(h) !== -1) return h;
            // G8: ?action=edit|preview bez fragmentu -> zakładka formularza
            if (/[?&]action=(edit|preview)(&|$)/.test(location.search) && names().indexOf('form') !== -1) return 'form';
            try {
                var s = sessionStorage.getItem(KEY);
                if (s && names().indexOf(s) !== -1) return s;
            } catch (e) {}
            return names()[0];
        }

        wrap.classList.add('openvote-tabs-js');
        show(initial(), false);
        // Przeglądarka przewija do elementu #tab-xxx z adresu — wracamy na górę, by były widoczne zakładki i komunikat o zapisie.
        if (fromHash()) {
            window.setTimeout(function () { window.scrollTo(0, 0); }, 0);
        }

        tabs.forEach(function (t, i) {
            t.addEventListener('click', function (e) {
                e.preventDefault();
                setHash(show(t.getAttribute('data-tab'), false));
            });
            t.addEventListener('keydown', function (e) {
                var j = -1;
                if (e.key === 'ArrowRight') j = (i + 1) % tabs.length;
                else if (e.key === 'ArrowLeft') j = (i - 1 + tabs.length) % tabs.length;
                else if (e.key === 'Home') j = 0;
                else if (e.key === 'End') j = tabs.length - 1;
                if (j < 0) return;
                e.preventDefault();
                setHash(show(tabs[j].getAttribute('data-tab'), true));
            });
        });

        window.addEventListener('hashchange', function () {
            var h = fromHash();
            if (h) show(h, false);
        });

        function panelOf(el) {
            return el && el.closest ? el.closest('.openvote-tab-panel') : null;
        }

        forms.forEach(function (form) {
            // Po zapisie (redirect) wracamy do tej samej zakładki: fragment URL jest zachowywany przez przekierowanie
            // (o ile serwer nie poda własnego). Formularz w panelu -> jego zakładka; formularz obejmujący panele -> aktywna.
            form.addEventListener('submit', function () {
                var p = panelOf(form);
                var name = p ? p.getAttribute('data-tab') : null;
                if (!name) {
                    if (!form.querySelector('.openvote-tab-panel')) return; // formularz poza zakładkami — bez zmian
                    var active = nav.querySelector('.nav-tab-active');
                    if (!active) return;
                    name = active.getAttribute('data-tab');
                }
                var base = form.getAttribute('action') || location.href;
                form.setAttribute('action', base.split('#')[0] + '#tab-' + name);
            });
        });

        // Pole nieprawidłowe (min/max/required) w ukrytej zakładce: pokaż jej panel, aby przeglądarka mogła zgłosić błąd.
        wrap.addEventListener('invalid', function (e) {
            var p = panelOf(e.target);
            if (p && !p.classList.contains('is-active')) {
                setHash(show(p.getAttribute('data-tab'), false));
            }
        }, true);
    }
})();
