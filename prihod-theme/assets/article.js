(() => {
  document.querySelectorAll('[data-share-url]').forEach(button => {
    const {shareUrl: url, shareTitle: title, copied, manual} = button.dataset;
    const box = button.closest('.prihod-share');
    const actions = box.querySelector('.prihod-share-actions');
    const status = box.querySelector('[role="status"]');
    const native = box.querySelector('[data-share-native]');
    native.hidden = !navigator.share;
    button.addEventListener('click', () => {
      actions.hidden = !actions.hidden;
      button.setAttribute('aria-expanded', String(!actions.hidden));
    });
    native.addEventListener('click', async () => {
      try { await navigator.share({title, url}); }
      catch (error) { if (error.name !== 'AbortError') box.querySelector('[data-share-copy]').focus(); }
    });
    box.querySelector('[data-share-copy]').addEventListener('click', async () => {
      try {
        if (!navigator.clipboard) throw new Error('Clipboard unavailable');
        await navigator.clipboard.writeText(url);
        status.textContent = copied;
      } catch (_) {
        const input = box.querySelector('input');
        input.hidden = false; input.focus(); input.select();
        status.textContent = manual;
      }
    });
  });
})();
