/**
 * Komunikacja: przycisk Reset (przywraca domyślną treść), ukrycie przycisków z innych wtyczek.
 */
(function() {
    function hideToolbarNodes() {
        var wrap = document.querySelector('.wp-editor-wrap');
        if (!wrap) return;
        // Ukrywamy tylko małe elementy (przyciski/linki), nigdy kontenerów — .wp-editor-wrap to DIV z całym edytorem.
        var tags = { A: 1, BUTTON: 1, SPAN: 1 };
        var walk = function(node) {
            if (node.nodeType !== 1) return;
            if (node.classList && node.classList.contains('wp-editor-wrap')) {
                for (var i = 0; i < node.childNodes.length; i++) walk(node.childNodes[i]);
                return;
            }
            var text = (node.textContent || '').trim();
            var hide = (text.indexOf('Add Registration Form') !== -1 || text.indexOf('Add Smart Tags') !== -1 || text.indexOf('Reset Content') !== -1) &&
                (tags[node.tagName] || node.getAttribute('role') === 'button');
            if (hide) {
                node.style.display = 'none';
                return;
            }
            for (var i = 0; i < node.childNodes.length; i++) {
                walk(node.childNodes[i]);
            }
        };
        walk(wrap);
    }

    function setupResetButton() {
        var btn = document.getElementById('openvote-reset-message-body');
        if (!btn || typeof openvoteCommunicationEditor === 'undefined' || !openvoteCommunicationEditor.defaultBody) return;

        var defaultBody = openvoteCommunicationEditor.defaultBody;
        var editorId = 'openvote_message_body';

        btn.addEventListener('click', function() {
            var ed = window.tinymce && window.tinymce.get(editorId);
            if (ed) {
                ed.setContent(defaultBody);
            }
            var textarea = document.getElementById(editorId);
            if (textarea) {
                textarea.value = defaultBody;
            }
        });
    }

    function run() {
        hideToolbarNodes();
        setupResetButton();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', run);
    } else {
        run();
    }
    // Ponów ukrycie — inne wtyczki często dodają przyciski do toolbara asynchronicznie.
    setTimeout(hideToolbarNodes, 300);
    setTimeout(hideToolbarNodes, 1000);
})();
