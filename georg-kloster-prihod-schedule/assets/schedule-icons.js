document.addEventListener('DOMContentLoaded', () => {
    const requests = new Map();
    function day(url) {
        if (!requests.has(url)) requests.set(url, fetch(url).then(response => { if (!response.ok) throw Error(); return response.json(); }));
        return requests.get(url);
    }
    const colors = {white:'#faf8ef',red:'#cb3035',gold:'#f2c500',green:'#159447',blue:'#349bd5',purple:'#8a45b5',black:'#353a40'};
    const textColors = {white:'#6b6045',red:'#a82026',gold:'#806000',green:'#14652f',blue:'#17618b',purple:'#713894',black:'#353a40'};
    async function marker(host, info) {
        const mark = info?.typikon;
        if (!mark?.label || (!mark.url && !mark.media)) return;
        try {
            const url = mark.url || (await day(mark.media)).url;
            if (!url || !/^https?:\/\//.test(url)) return;
            const image = document.createElement('img'); image.className = 'gkpsch-typikon';
            image.width = 20; image.height = 20; image.alt = mark.label; image.title = mark.label;
            image.addEventListener('error', () => image.remove(), {once:true});
            image.src = url; host.prepend(image); host.hidden = false;
        } catch (_) { /* No substitute mark when authoritative media is unavailable. */ }
    }
    document.querySelectorAll('[data-gkpsch-mini-month]').forEach(async month => {
        const links = Array.from(month.querySelectorAll('[data-mini-date]'));
        function frame(link, automatic) {
            const chosen = JSON.parse(link.dataset.serviceColors || '[]').map(color => color === 'auto' ? automatic : color);
            const edges = [...new Set(chosen.filter(color => Object.hasOwn(colors, color)))].slice(0, 4);
            link.classList.toggle('has-vestment-frame', edges.length > 0);
            link.style.borderColor = edges.map(color => colors[color]).join(' ');
            link.style.setProperty('--gkpsch-day-ink', edges.length ? textColors[edges[0]] : '');
        }
        links.forEach(link => frame(link, ''));
        try {
            const data = await day(month.dataset.gkpschMiniMonth);
            links.forEach(link => {
                const info = data.days?.[link.dataset.miniDate];
                if (!info) return;
                frame(link, info.color);
                marker(link, info);
                const feasts = Array.isArray(info.feasts) ? info.feasts.filter(title => typeof title === 'string' && title.trim()) : [];
                link.classList.toggle('is-great-feast', feasts.length > 0);
                if (feasts.length) {
                    link.title = feasts.join('; ');
                    link.setAttribute('aria-label', link.getAttribute('aria-label') + ' · ' + link.title);
                }
            });
        } catch (_) { /* Keep manual frames and navigation when the provider is unavailable. */ }
    });
    const normalize = value => value.normalize('NFKC').toLocaleLowerCase().replace(/[\p{P}\p{S}\s]+/gu, ' ').trim();
    document.querySelectorAll('[data-gkpsch-feast]').forEach(async label => {
        const host = label.closest('.gkpsch-event,.gkpsch-upcoming-card,.gkpsch-service-compact');
        if (host && label.dataset.color && label.dataset.color !== 'auto') host.dataset.vestmentColor = label.dataset.color;
        try {
            const url = label.dataset.gkpschFeast;
            const month = await day(url);
            const data = month.days?.[label.dataset.calendarDate];
            if (!data) return;
            if (host && label.dataset.color === 'auto' && ['white','red','gold','green','blue','purple','black'].includes(data.color)) host.dataset.vestmentColor = data.color;
            const feasts = Array.isArray(data.memories) ? data.memories.filter(title => typeof title === 'string' && title.trim()) : [];
            const manual = normalize(label.dataset.serviceTitle || '');
            const unique = feasts.filter(title => !manual.includes(normalize(title)));
            if (label.dataset.compact !== '1') label.textContent = unique.join('; ');
            label.hidden = !label.textContent;
            marker(label, data);
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
    document.querySelectorAll('[data-gkpsch-day-icon]').forEach(async figure => {
        try {
            const url = figure.dataset.gkpschDayIcon;
            const fallback = JSON.parse(figure.dataset.gkpschIconFallback || 'null');
            const icon = await loadIcon(url).catch(() => fallback) || fallback;
            if (!icon) return;
            const imageUrl = icon.url;
            if (!imageUrl || !/^https?:\/\//.test(imageUrl)) return;
            const native = figure.classList.contains('wp-lightbox-container');
            const image = figure.querySelector('img') || document.createElement('img');
            let activeIcon = icon;
            image.alt = icon.alt; image.decoding = 'async';
            figure.dataset.iconSource = icon.fallback ? 'fallback' : 'day';
            const notify = () => {
                figure.hidden = false;
                const current = activeIcon;
                if (native) {
                    const trigger = figure.querySelector('.lightbox-trigger');
                    if (trigger) {
                        const action = trigger.dataset.gkpschAction || trigger.getAttribute('aria-label') || '';
                        trigger.dataset.gkpschAction = action;
                        const label = [action, image.alt].filter(Boolean).join(': ');
                        trigger.setAttribute('aria-label', label);
                        trigger.title = label;
                    }
                } else {
                    const link = image.closest('a');
                    if (link) link.href = current.fullUrl || image.src;
                }
                figure.dispatchEvent(new CustomEvent('gkpsch:icon-loaded', {bubbles:true,detail:{figure,image,icon:current}}));
            };
            image.addEventListener('load', notify, {once: true});
            image.addEventListener('error', () => {
                if (fallback?.url && image.src !== fallback.url) {
                    activeIcon = fallback;
                    figure.querySelector('figcaption')?.remove();
                    figure.dataset.iconSource = 'fallback'; image.alt = fallback.alt; image.src = fallback.url;
                    image.addEventListener('error', () => { figure.hidden = true; }, {once:true});
                } else figure.hidden = true;
            }, {once: true});
            if (!native) {
                const link = document.createElement('a'); link.target = '_blank'; link.rel = 'noopener';
                link.href = icon.fullUrl || imageUrl; link.append(image); figure.append(link);
            }
            if (icon.attribution) { const caption = document.createElement('figcaption'); caption.textContent = icon.attribution; figure.append(caption); }
            image.src = imageUrl;
        } catch (_) { /* An unavailable icon never replaces the actual schedule. */ }
    });
});
