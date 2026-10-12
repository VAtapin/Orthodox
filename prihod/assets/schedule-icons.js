document.addEventListener('DOMContentLoaded', () => {
    const requests = new Map();
    async function loadIcon(url) {
        const response = await fetch(url);
        if (!response.ok) throw Error();
        const data = await response.json();
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
            if (!requests.has(url)) requests.set(url, loadIcon(url));
            const icon = await requests.get(url);
            if (!icon) return;
            const imageUrl = icon.url;
            if (!imageUrl || !/^https?:\/\//.test(imageUrl)) return;
            const image = document.createElement('img');
            image.alt = icon.alt; image.decoding = 'async';
            image.addEventListener('load', () => { figure.hidden = false; }, {once: true});
            image.addEventListener('error', () => { figure.hidden = true; }, {once: true});
            figure.append(image);
            if (icon.attribution) { const caption = document.createElement('figcaption'); caption.textContent = icon.attribution; figure.append(caption); }
            image.src = imageUrl;
        } catch (_) { /* An unavailable icon never replaces the actual schedule. */ }
    });
});
