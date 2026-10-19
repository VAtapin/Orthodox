document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-gkpsch-icon-choice]').forEach(field => {
        const input = field.querySelector('input[type="hidden"]');
        const image = field.querySelector('img');
        field.querySelector('[data-gkpsch-icon-reset]').addEventListener('click', () => { input.value = '0'; image.src = image.dataset.default; });
        const choose = field.querySelector('[data-gkpsch-icon-select]');
        choose.addEventListener('click', () => {
            const frame = wp.media({title:field.querySelector('legend').textContent,button:{text:choose.textContent},library:{type:'image'},multiple:false});
            frame.on('select', () => { const selected = frame.state().get('selection').first().toJSON(); input.value = selected.id; image.src = selected.sizes?.medium?.url || selected.url; });
            frame.open();
        });
    });
});
