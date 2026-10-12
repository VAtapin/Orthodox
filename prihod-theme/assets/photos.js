document.addEventListener('DOMContentLoaded', () => {
    const overlay = document.querySelector('.wp-lightbox-overlay');
    if (!overlay) return;
    const nativeNavigation = !!overlay.querySelector('.wp-lightbox-navigation-button');
    const caption = document.createElement('p');
    caption.className = 'prihod-photo-caption';
    caption.id = 'prihod-photo-caption';
    caption.setAttribute('aria-live', 'polite');
    overlay.append(caption);
    overlay.setAttribute('aria-describedby', [overlay.getAttribute('aria-describedby'),caption.id].filter(Boolean).join(' '));
    let source = null, group = [], opener = null, navigating = false, wasOpen = false, focusTimer, returning, reopenTimer, closingUntil = 0;

    function cancelReturn() {
        clearTimeout(focusTimer);
        returning = null;
        overlay.removeAttribute('data-prihod-photo-returning');
    }
    function finishReturn() {
        const pending = returning, active = document.activeElement;
        cancelReturn();
        if (pending && !overlay.classList.contains('active') && opener === pending.opener && pending.opener.isConnected && (active === document.body || overlay.contains(active) || pending.group.some(item => item.contains(active)))) pending.opener.focus({preventScroll: true});
    }

    const imageId = (figure) => figure.querySelector('img')?.className.match(/\bwp-image-(\d+)\b/)?.[1];
    function collection(figure) {
        const key = figure.dataset.prihodPhotoGroup;
        if (key) return [...document.querySelectorAll('.wp-lightbox-container')].filter(item => item.dataset.prihodPhotoGroup === key && (nativeNavigation || item.getClientRects().length));
        const parent = figure.closest('.wp-block-gallery,.prihod-photo-strip,.prihod-about-photos,.prihod-pilgrim-photo-grid');
        return parent ? [...parent.querySelectorAll('.wp-lightbox-container')] : [figure];
    }
    function update() {
        const open = overlay.classList.contains('active');
        if (open) cancelReturn();
        if (open && source) {
            const displayedId = overlay.querySelector('.lightbox-image-container img')?.className.match(/\bwp-image-(\d+)\b/)?.[1];
            const order = nativeNavigation && overlay.getAttribute('aria-label')?.match(/(?:^|\D)(\d+)\D+(\d+)(?:\D|$)/);
            if (order && Number(order[2]) === group.length && group[Number(order[1]) - 1]) source = group[Number(order[1]) - 1];
            else if (displayedId) source = group.find(item => imageId(item) === displayedId) || source;
            const description = source.querySelector('img')?.alt || '';
            const attribution = source.querySelector('figcaption')?.textContent.trim() || '';
            const text = source.hasAttribute('data-prihod-day-icon') ? [description,attribution].filter(Boolean).join(' · ') : attribution || description;
            const position = group.length > 1 ? `${group.indexOf(source) + 1} / ${group.length}` : '';
            const label = [text, position].filter(Boolean).join(' · ');
            if (caption.textContent !== label) caption.textContent = label;
        }
        if (!nativeNavigation) controls.forEach(button => { button.hidden = !open || group.length < 2; });
        if (wasOpen && !open && opener) {
            returning = {opener, group};
            overlay.setAttribute('data-prihod-photo-returning', 'true');
            // Wait for the native closing animation's own focus return.
            focusTimer = setTimeout(finishReturn, 700);
        }
        wasOpen = open;
    }
    const controls = [];
    const waiting = new WeakSet();
    function move(direction, initiator) {
        if (group.length < 2) return;
        const next = group[(group.indexOf(source) + direction + group.length) % group.length];
        const trigger = next.querySelector('.lightbox-trigger');
        if (!trigger) return;
        navigating = true;
        trigger.click();
        navigating = false;
        source = next;
        if (initiator) requestAnimationFrame(() => initiator.focus({preventScroll: true}));
    }
    // WordPress 6.5–6.9 has the native viewer but no gallery navigation yet.
    if (!nativeNavigation) [-1, 1].forEach(direction => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'prihod-photo-navigation ' + (direction < 0 ? 'is-previous' : 'is-next');
        button.textContent = direction < 0 ? '←' : '→';
        button.setAttribute('aria-label', direction < 0 ? prihodPhotoLabels.previous : prihodPhotoLabels.next);
        button.hidden = true;
        overlay.append(button);
        controls.push(button);
    });
    document.addEventListener('click', event => {
        if (returning && !overlay.classList.contains('active')) cancelReturn();
        const target = event.target instanceof Element ? event.target : null;
        if (target?.closest('.wp-lightbox-close-button') || target === overlay) closingUntil = Date.now() + 700;
        const navigation = target?.closest('.prihod-photo-navigation');
        if (navigation && overlay.contains(navigation)) {
            event.preventDefault(); event.stopImmediatePropagation();
            move(navigation.classList.contains('is-previous') ? -1 : 1, navigation);
            return;
        }
        if (target?.closest('.prihod-photo-caption')) { event.stopPropagation(); return; }
        const figure = target?.closest('.wp-lightbox-container');
        if (!figure || (!target.matches('img') && !target.closest('.lightbox-trigger'))) return;
        if (target.closest('a[href]') && !target.closest('.lightbox-trigger')) return;
        // The native close resets its selected image after its own animation.
        if (figure.hasAttribute('data-prihod-day-icon') && Date.now() < closingUntil) {
            event.preventDefault(); event.stopImmediatePropagation();
            const trigger = target.closest('.lightbox-trigger') || figure.querySelector('.lightbox-trigger');
            clearTimeout(reopenTimer);
            reopenTimer = setTimeout(() => { if (trigger?.isConnected) trigger.click(); }, closingUntil - Date.now());
            return;
        }
        source = figure;
        group = collection(figure);
        if (!navigating) opener = target.closest('.lightbox-trigger') || figure.querySelector('.lightbox-trigger');
        const image = figure.querySelector('img');
        if (image && !image.complete && !waiting.has(figure)) {
            waiting.add(figure);
            figure.setAttribute('aria-busy', 'true');
            const finished = (event) => {
                image.removeEventListener('load', finished);
                image.removeEventListener('error', finished);
                waiting.delete(figure);
                figure.removeAttribute('aria-busy');
                if (event.type === 'load' && figure.isConnected && source === figure && !overlay.classList.contains('active')) figure.querySelector('.lightbox-trigger')?.click();
            };
            image.addEventListener('load', finished);
            image.addEventListener('error', finished);
        }
    }, true);
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && overlay.classList.contains('active')) closingUntil = Date.now() + 700;
        if (returning && !overlay.classList.contains('active') && (event.key === 'Tab' || !overlay.contains(event.target))) cancelReturn();
        if (nativeNavigation || !overlay.classList.contains('active') || !overlay.contains(event.target)) return;
        if (['ArrowLeft', 'ArrowRight'].includes(event.key) && group.length > 1) {
            event.preventDefault(); event.stopImmediatePropagation(); move(event.key === 'ArrowLeft' ? -1 : 1, event.target);
        } else if (event.key === 'Tab') {
            const buttons = [...overlay.querySelectorAll('button')].filter(button => !button.hidden && !button.disabled && button.getClientRects().length);
            const index = buttons.indexOf(document.activeElement);
            if (buttons.length) { event.preventDefault(); event.stopImmediatePropagation(); buttons[(index + (event.shiftKey ? -1 : 1) + buttons.length) % buttons.length].focus(); }
        }
    }, true);
    document.addEventListener('focusin', event => {
        if (returning && !overlay.classList.contains('active') && returning.group.some(item => item.contains(event.target))) finishReturn();
    }, true);
    new MutationObserver(update).observe(overlay, {attributes: true, subtree: true, attributeFilter: ['class', 'src', 'alt', 'aria-label']});
    update();
});
