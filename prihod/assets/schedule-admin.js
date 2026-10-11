document.addEventListener('DOMContentLoaded', function () {
    var button = document.getElementById('prihod-add-exception');
    if (!button) return;
    button.addEventListener('click', function () {
        var index = Number(button.dataset.index);
        var template = document.getElementById('prihod-exception-template');
        document.getElementById('prihod-exceptions').insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', String(index)));
        button.dataset.index = String(index + 1);
    });
});
