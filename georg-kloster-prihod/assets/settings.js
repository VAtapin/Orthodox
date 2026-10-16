document.addEventListener('DOMContentLoaded', function () {
    var shell = document.querySelector('.prihod-options-shell');
    if (!shell) return;
    var links = Array.from(shell.querySelectorAll('[data-section]'));
    var panels = Array.from(shell.querySelectorAll('.prihod-option-section'));
    function show() {
        var id = location.hash.replace('#prihod-option-', '');
        if (!links.some(function (link) { return link.dataset.section === id; })) id = 'schemes';
        panels.forEach(function (panel) { panel.hidden = panel.id !== 'prihod-option-' + id; });
        links.forEach(function (link) {
            if (link.dataset.section === id) link.setAttribute('aria-current', 'page');
            else link.removeAttribute('aria-current');
        });
        shell.querySelector('.prihod-options-toolbar').hidden = false;
    }
    window.addEventListener('hashchange', show);
    show();
});
