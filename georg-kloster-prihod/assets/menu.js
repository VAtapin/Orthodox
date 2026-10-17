document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.prihod-visual-menu').forEach((menu) => {
    const summary = menu.querySelector(':scope > summary');
    menu.querySelector('.prihod-visual-close')?.addEventListener('click', () => {
      menu.open = false;
      summary?.focus();
    });
    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && menu.open) {
        menu.open = false;
        summary?.focus();
      }
    });
    document.addEventListener('click', (event) => {
      if (menu.open && !menu.contains(event.target)) menu.open = false;
    });
  });
});
