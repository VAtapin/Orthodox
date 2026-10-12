document.querySelectorAll('.prihod-home-gallery').forEach((gallery) => {
    const strip = gallery.querySelector('.prihod-photo-strip');
    const buttons = gallery.querySelectorAll('[data-gallery-direction]');
    if (!strip || !buttons.length) return;
    const update = () => buttons.forEach((button) => {
        const direction = Number(button.dataset.galleryDirection);
        button.disabled = direction < 0 ? strip.scrollLeft <= 1 : strip.scrollLeft + strip.clientWidth >= strip.scrollWidth - 1;
    });
    buttons.forEach((button) => button.addEventListener('click', () => strip.scrollBy({left: Number(button.dataset.galleryDirection) * strip.clientWidth * 0.75, behavior: 'smooth'})));
    strip.addEventListener('scroll', update, {passive: true});
    window.addEventListener('resize', update);
    update();
});
