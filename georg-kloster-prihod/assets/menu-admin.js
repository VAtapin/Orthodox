document.addEventListener('click', (event) => {
  const field = event.target.closest('.prihod-menu-image-field');
  if (!field || !window.wp?.media) return;
  const input = field.querySelector('input[type="hidden"]');
  const preview = field.querySelector('.prihod-menu-admin-image');
  const remove = field.querySelector('.prihod-menu-image-remove');
  if (event.target.closest('.prihod-menu-image-remove')) {
    input.value = '';
    preview.replaceChildren();
    remove.hidden = true;
    return;
  }
  if (!event.target.closest('.prihod-menu-image-select')) return;
  const frame = window.wp.media({ title: window.prihodMenuMedia.title, button: { text: window.prihodMenuMedia.select }, library: { type: 'image' }, multiple: false });
  frame.on('select', () => {
    const attachment = frame.state().get('selection').first()?.toJSON();
    if (!attachment) return;
    const image = document.createElement('img');
    image.className = 'prihod-menu-admin-preview';
    image.alt = '';
    image.src = attachment.sizes?.thumbnail?.url || attachment.url;
    input.value = String(attachment.id);
    preview.replaceChildren(image);
    remove.hidden = false;
  });
  frame.open();
});
