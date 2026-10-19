// Footer scripts run before WordPress initializes the classic editor. Keep its
// original textarea and native editor together when moving the optional description.
(function () {
    var editor = document.getElementById('postdivrich');
    var service = document.getElementById('gkpsch-service');
    if (!editor || !service) return;
    var details = document.createElement('details');
    details.className = 'gkpsch-service-description';
    var summary = document.createElement('summary');
    summary.textContent = (window.gkpschScheduleUI || {}).description || '';
    details.appendChild(summary);
    details.open = Boolean(document.getElementById('content').value.trim());
    service.after(details);
    details.appendChild(editor);
})();
document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('post');
    var place = document.querySelector('[name="gkpsch_service[location_id]"]');
    if (form && place) {
        function updatePlaceValidity() {
            place.setCustomValidity(!place.value || place.value === '0' ? place.dataset.requiredMessage : '');
        }
        function validatePlace(event) {
            updatePlaceValidity();
            if (place.checkValidity()) return;
            event.preventDefault();
            event.stopImmediatePropagation();
            var box = place.closest('.postbox');
            if (box) box.classList.remove('closed');
            place.focus();
            place.reportValidity();
        }
        place.addEventListener('change', updatePlaceValidity);
        updatePlaceValidity();
        // Validate before WordPress disables its publish/save buttons, including
        // classic-editor forms with novalidate and submission via the keyboard.
        form.addEventListener('click', function (event) {
            if (event.target.closest('#publish, #save-post')) validatePlace(event);
        }, true);
        form.addEventListener('submit', validatePlace, true);
    }
    var button = document.getElementById('gkpsch-add-exception');
    if (!button) return;
    var kind = document.querySelector('[name="gkpsch_service[kind]"]');
    var repeat = document.querySelector('[data-gkpsch-repeat]');
    function updateRepeat() {
        repeat.hidden = kind.value !== 'regular';
        repeat.querySelectorAll('input').forEach(function (input) { input.disabled = repeat.hidden; });
    }
    function updateRow(row) {
        var removed = row.querySelector('[name$="[remove]"]').checked;
        var replacement = row.querySelector('[data-gkpsch-replacement]');
        replacement.hidden = removed || row.querySelector('[name$="[action]"]').value !== 'replace';
        replacement.querySelectorAll('input,select').forEach(function (input) { input.disabled = replacement.hidden; });
        var color = row.querySelector('[data-gkpsch-color-control]');
        color.hidden = removed || row.querySelector('[name$="[action]"]').value === 'cancel';
        color.querySelector('select').disabled = color.hidden;
        row.querySelector('[name$="[original]"]').required = !removed;
        row.classList.toggle('is-removed', removed);
    }
    kind.addEventListener('change', updateRepeat); updateRepeat();
    document.getElementById('gkpsch-exceptions').addEventListener('change', function (event) {
        var row = event.target.closest('.gkpsch-exception-row');
        if (row) updateRow(row);
    });
    document.querySelectorAll('.gkpsch-exception-row').forEach(updateRow);
    button.addEventListener('click', function () {
        var index = Number(button.dataset.index);
        var template = document.getElementById('gkpsch-exception-template');
        document.getElementById('gkpsch-exceptions').insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', String(index)));
        button.dataset.index = String(index + 1);
        var row = document.getElementById('gkpsch-exceptions').lastElementChild;
        updateRow(row); row.querySelector('input').focus();
    });
});
