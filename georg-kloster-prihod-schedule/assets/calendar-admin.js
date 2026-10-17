document.addEventListener('DOMContentLoaded', () => {
    const date = document.querySelector('[name="gkpsch_service[date]"]');
    const status = document.querySelector('[data-gkpsch-calendar-status]');
    const accept = document.querySelector('[data-gkpsch-use-feast]');
    const showIcon = document.querySelector('[name="gkpsch_service[day_icon]"]');
    const preview = document.querySelector('[data-gkpsch-icon-preview]');
    const iconStatus = document.querySelector('[data-gkpsch-icon-status]');
    const title = document.getElementById('title');
    if (!date || !status || !accept || !showIcon || !preview || !title) return;
    const labels = window.gkpschScheduleUI;
    let controller, timer, suggested = '', loaded;
    async function icon(data, selected) {
        preview.replaceChildren(); preview.hidden = true; iconStatus.hidden = true;
        if (!showIcon.checked) return;
        if (!data?.icon) { iconStatus.hidden = false; return; }
        try {
            let url = data.icon.url;
            if (!url && data.icon.media) {
                const response = await fetch(data.icon.media);
                if (!response.ok) throw Error();
                url = (await response.json()).url;
            }
            if (!url || !/^https?:\/\//.test(url) || date.value !== selected || !showIcon.checked) return;
            const image = document.createElement('img'); image.alt = data.icon.alt;
            image.addEventListener('load', () => { if (date.value === selected && showIcon.checked && image.isConnected) preview.hidden = false; }, {once:true});
            image.addEventListener('error', () => { if (date.value === selected && showIcon.checked && image.isConnected) { preview.hidden = true; iconStatus.hidden = false; } }, {once:true});
            preview.append(image); image.src = url;
        } catch (_) { if (date.value === selected && showIcon.checked) { preview.hidden = true; iconStatus.hidden = false; } }
    }
    async function load() {
        controller?.abort(); controller = new AbortController();
        const selected = date.value;
        suggested = ''; loaded = null; accept.hidden = true; preview.replaceChildren(); preview.hidden = true; iconStatus.hidden = true;
        if (!/^\d{4}-\d{2}-\d{2}$/.test(selected)) { status.textContent = ''; return; }
        status.textContent = labels.loading;
        try {
            const url = new URL(labels.endpoint); url.searchParams.set('date', selected);
            const response = await fetch(url, {signal:controller.signal, headers:{'X-WP-Nonce':labels.nonce}});
            if (!response.ok) throw Error();
            const data = await response.json();
            if (date.value !== selected || data.date !== selected) return;
            loaded = data;
            status.textContent = data.feasts.length ? data.feasts.join('; ') : labels.empty;
            suggested = data.feasts.length ? labels.liturgy + ' · ' + data.feasts.join('; ') : '';
            accept.hidden = !suggested;
            await icon(data, selected);
        } catch (error) { if (error.name !== 'AbortError' && date.value === selected) status.textContent = labels.unavailable; }
    }
    date.addEventListener('change', () => { clearTimeout(timer); controller?.abort(); suggested = ''; accept.hidden = true; preview.hidden = true; timer = setTimeout(load, 200); });
    showIcon.addEventListener('change', () => { if (loaded?.date === date.value) icon(loaded, date.value); else load(); });
    accept.addEventListener('click', () => {
        if (!suggested) return;
        if (title.value.trim()) { status.textContent = labels.titleFilled; title.focus(); return; }
        title.value = suggested; title.dispatchEvent(new Event('input', {bubbles:true})); title.dispatchEvent(new Event('change', {bubbles:true}));
        status.textContent = loaded.feasts.join('; ');
        const kind = document.querySelector('[name="gkpsch_service[kind]"]');
        if (kind.value !== 'regular') { kind.value = 'holiday'; kind.dispatchEvent(new Event('change', {bubbles:true})); }
        title.focus();
    });
    load();
});
