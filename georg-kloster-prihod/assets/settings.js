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
        shell.querySelector('.prihod-options-toolbar').hidden = id === 'plugins';
    }
    window.addEventListener('hashchange', show);
    show();
    shell.querySelector('form').addEventListener('submit', function () {
        var referer = shell.querySelector('[name="_wp_http_referer"]');
        if (referer) referer.value = location.pathname + location.search + location.hash;
    });
    function updateMoves(list) {
        var rows = Array.from(list.children).filter(function (row) { return row.hasAttribute('data-home-item'); });
        rows.forEach(function (row, index) {
            row.querySelector('[data-home-move="up"]').disabled = index === 0;
            row.querySelector('[data-home-move="down"]').disabled = index === rows.length - 1;
        });
    }
    shell.querySelectorAll('[data-home-order]').forEach(updateMoves);
    shell.addEventListener('click', function (event) {
        var button = event.target.closest('[data-home-move]');
        if (!button) return;
        var row = button.closest('[data-home-item]'), list = row.parentElement;
        var sibling = button.dataset.homeMove === 'up' ? row.previousElementSibling : row.nextElementSibling;
        if (!sibling) return;
        if (button.dataset.homeMove === 'up') list.insertBefore(row, sibling);
        else list.insertBefore(sibling, row);
        updateMoves(list);
        // Keep keyboard focus on the moved section even at a list boundary.
        if (button.disabled) row.querySelector('[data-home-move]:not(:disabled)').focus();
        else button.focus();
    });
});
