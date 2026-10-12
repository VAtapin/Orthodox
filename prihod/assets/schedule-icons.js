document.addEventListener('DOMContentLoaded', () => {
    const requests = new Map();
    function day(url) {
        if (!requests.has(url)) requests.set(url, fetch(url).then(response => { if (!response.ok) throw Error(); return response.json(); }));
        return requests.get(url);
    }
    const normalize = value => value.normalize('NFKC').toLocaleLowerCase().replace(/[\p{P}\p{S}\s]+/gu, ' ').trim();
    document.querySelectorAll('[data-prihod-feast]').forEach(async label => {
        const host = label.closest('.prihod-event,.prihod-upcoming-card,.prihod-service-compact');
        if (host && label.dataset.color && label.dataset.color !== 'auto') host.dataset.vestmentColor = label.dataset.color;
        try {
            const url = label.dataset.prihodFeast;
            const data = await day(url);
            if (host && label.dataset.color === 'auto' && ['white','red','gold','green','blue','purple','black'].includes(data.color)) host.dataset.vestmentColor = data.color;
            const feasts = Array.isArray(data.feasts) ? data.feasts.filter(title => typeof title === 'string' && title.trim()) : [];
            if (!feasts.length) return;
            const manual = normalize(label.dataset.serviceTitle || '');
            const unique = feasts.filter(title => !manual.includes(normalize(title)));
            label.textContent = unique.join('; '); label.hidden = !unique.length;
        } catch (_) { /* Keep the actual service usable when the calendar cannot be read. */ }
    });
    async function loadIcon(url) {
        const data = await day(url);
        if (!data.icon) return null;
        const icon = data.icon;
        if (!icon.url && icon.media) {
            const media = await fetch(icon.media);
            if (!media.ok) throw Error();
            icon.url = (await media.json()).url;
        }
        return icon;
    }
    document.querySelectorAll('[data-prihod-day-icon]').forEach(async figure => {
        try {
            const url = figure.dataset.prihodDayIcon;
            const fallback = JSON.parse(figure.dataset.prihodIconFallback || 'null');
            const icon = await loadIcon(url).catch(() => fallback) || fallback;
            if (!icon) return;
            const imageUrl = icon.url;
            if (!imageUrl || !/^https?:\/\//.test(imageUrl)) return;
            const image = document.createElement('img');
            image.alt = icon.alt; image.decoding = 'async';
            figure.dataset.iconSource = icon.fallback ? 'fallback' : 'day';
            image.addEventListener('load', () => { figure.hidden = false; }, {once: true});
            image.addEventListener('error', () => {
                if (fallback?.url && image.src !== fallback.url) {
                    figure.querySelector('figcaption')?.remove();
                    figure.dataset.iconSource = 'fallback'; image.alt = fallback.alt; image.src = fallback.url;
                    image.addEventListener('error', () => { figure.hidden = true; }, {once:true});
                } else figure.hidden = true;
            }, {once: true});
            figure.append(image);
            if (icon.attribution) { const caption = document.createElement('figcaption'); caption.textContent = icon.attribution; figure.append(caption); }
            image.src = imageUrl;
        } catch (_) { /* An unavailable icon never replaces the actual schedule. */ }
    });
});
