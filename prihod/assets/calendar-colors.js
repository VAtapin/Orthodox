document.addEventListener('DOMContentLoaded', () => {
    const labels = window.prihodScheduleUI;
    const kind = document.querySelector('[name="prihod_service[kind]"]');
    const mainDate = document.querySelector('[name="prihod_service[date]"]');
    if (!labels || !kind || !mainDate) return;
    function bind(control) {
        if (control.dataset.bound) return;
        control.dataset.bound = '1';
        const select = control.querySelector('[data-prihod-color]');
        const swatch = control.querySelector('[data-prihod-color-swatch]');
        const status = control.querySelector('[data-prihod-color-status]');
        const use = control.querySelector('[data-prihod-use-color]');
        const row = control.closest('.prihod-exception-row');
        let manual = select.dataset.persisted === '1' || Boolean(select.value);
        let controller, sequence = 0, recommended = '';
        function paint() { swatch.dataset.color = select.value; }
        function selectedDate() {
            if (!row) return mainDate.value;
            const action = row.querySelector('[name$="[action]"]').value;
            return row.querySelector(action === 'replace' ? '[name$="[date]"]' : '[name$="[original]"]').value;
        }
        async function load() {
            controller?.abort(); controller = new AbortController();
            const current = ++sequence, date = selectedDate();
            recommended = ''; use.hidden = true; status.textContent = '';
            if (select.disabled || !/^\d{4}-\d{2}-\d{2}$/.test(date)) return;
            if (!row && kind.value === 'regular') {
                if (!manual) { select.value = 'auto'; paint(); }
                status.textContent = labels.colorRegular; return;
            }
            if (!manual) { select.value = ''; paint(); }
            status.textContent = labels.loading;
            try {
                const url = new URL(labels.endpoint); url.searchParams.set('date', date);
                const response = await fetch(url, {signal:controller.signal, headers:{'X-WP-Nonce':labels.nonce}});
                if (!response.ok) throw Error();
                const data = await response.json();
                if (current !== sequence || date !== selectedDate() || data.date !== date || select.disabled) return;
                const r = data.recommendation;
                if (!r || ![...select.options].some(x => x.value === r.color && x.value)) { status.textContent = labels.colorEmpty; return; }
                recommended = r.color;
                status.textContent = labels.colorRecommendation.replace('%1$s', r.label).replace('%2$s', r.reason);
                if (!manual) { select.value = recommended; paint(); }
                use.hidden = select.value === recommended;
            } catch (error) {
                if (error.name !== 'AbortError' && current === sequence) status.textContent = labels.unavailable;
            }
        }
        select.addEventListener('change', () => { manual = true; paint(); use.hidden = !recommended || select.value === recommended; });
        use.addEventListener('click', () => { if (recommended) { select.value = recommended; manual = true; paint(); use.hidden = true; } });
        (row || mainDate).addEventListener('change', event => { if (event.target !== select) queueMicrotask(load); });
        if (!row) kind.addEventListener('change', load);
        paint(); load();
    }
    document.querySelectorAll('[data-prihod-color-control]').forEach(bind);
    const exceptions = document.getElementById('prihod-exceptions');
    if (exceptions) new MutationObserver(() => exceptions.querySelectorAll('[data-prihod-color-control]').forEach(bind)).observe(exceptions, {childList:true});
});
