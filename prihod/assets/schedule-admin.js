// Footer scripts run before WordPress initializes the classic editor. Keep its
// original textarea and native editor together when moving the optional description.
(function () {
    var editor = document.getElementById('postdivrich');
    var service = document.getElementById('prihod-service');
    if (!editor || !service) return;
    var details = document.createElement('details');
    details.className = 'prihod-service-description';
    var summary = document.createElement('summary');
    summary.textContent = (window.prihodScheduleUI || {}).description || '';
    details.appendChild(summary);
    details.open = Boolean(document.getElementById('content').value.trim());
    service.after(details);
    details.appendChild(editor);
})();
document.addEventListener('DOMContentLoaded', function () {
    var button = document.getElementById('prihod-add-exception');
    if (!button) return;
    var kind = document.querySelector('[name="prihod_service[kind]"]');
    var repeat = document.querySelector('[data-prihod-repeat]');
    function updateRepeat() {
        repeat.hidden = kind.value !== 'regular';
        repeat.querySelectorAll('input').forEach(function (input) { input.disabled = repeat.hidden; });
    }
    function updateRow(row) {
        var removed = row.querySelector('[name$="[remove]"]').checked;
        var replacement = row.querySelector('[data-prihod-replacement]');
        replacement.hidden = removed || row.querySelector('[name$="[action]"]').value !== 'replace';
        replacement.querySelectorAll('input,select').forEach(function (input) { input.disabled = replacement.hidden; });
        row.querySelector('[name$="[original]"]').required = !removed;
        row.classList.toggle('is-removed', removed);
    }
    kind.addEventListener('change', updateRepeat); updateRepeat();
    document.getElementById('prihod-exceptions').addEventListener('change', function (event) {
        var row = event.target.closest('.prihod-exception-row');
        if (row) updateRow(row);
    });
    document.querySelectorAll('.prihod-exception-row').forEach(updateRow);
    button.addEventListener('click', function () {
        var index = Number(button.dataset.index);
        var template = document.getElementById('prihod-exception-template');
        document.getElementById('prihod-exceptions').insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', String(index)));
        button.dataset.index = String(index + 1);
        var row = document.getElementById('prihod-exceptions').lastElementChild;
        updateRow(row); row.querySelector('input').focus();
    });
});
