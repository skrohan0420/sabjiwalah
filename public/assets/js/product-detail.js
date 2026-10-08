(() => {
    const header = document.querySelector('[data-detail-header]');
    const hero = document.querySelector('[data-detail-hero]');
    const dialog = document.querySelector('#product-details');
    const bar = document.querySelector('.detail-purchase-bar');
    const barHome = bar.parentElement;
    const floatingCart = document.querySelector('[data-floating-cart]');
    const notice = document.querySelector('[data-detail-notice]');
    let previousFocus;
    let noticeTimer;
    const updateHeader = () => header.classList.toggle('is-scrolled', hero.getBoundingClientRect().bottom <= header.offsetHeight + 24);
    window.addEventListener('scroll', updateHeader, { passive: true });
    window.addEventListener('resize', updateHeader);
    updateHeader();
    const gallery = document.querySelector('[data-hero-gallery]');
    const galleryDots = Array.from(document.querySelectorAll('[data-hero-slide]'));
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    let galleryIndex = 0;
    let drag = null;
    const showSlide = index => {
        galleryIndex = Math.max(0, Math.min(galleryDots.length - 1, index));
        gallery.scrollTo({ left: galleryIndex * gallery.clientWidth, behavior: reducedMotion.matches ? 'instant' : 'smooth' });
    };
    galleryDots.forEach((dot, index) => dot.addEventListener('click', () => showSlide(index)));
    gallery.addEventListener('scroll', () => {
        galleryIndex = Math.round(gallery.scrollLeft / gallery.clientWidth);
        galleryDots.forEach((dot, index) => {
            dot.classList.toggle('is-active', index === galleryIndex);
            dot.setAttribute('aria-pressed', String(index === galleryIndex));
        });
    }, { passive: true });
    gallery.addEventListener('keydown', event => {
        if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
        event.preventDefault();
        showSlide(event.key === 'Home' ? 0 : event.key === 'End' ? galleryDots.length - 1 : galleryIndex + (event.key === 'ArrowRight' ? 1 : -1));
    });
    // Touch uses native scrolling; mouse users can drag the same gallery.
    gallery.addEventListener('pointerdown', event => {
        if (event.pointerType !== 'mouse' || event.button !== 0) return;
        drag = { id: event.pointerId, x: event.clientX, left: gallery.scrollLeft };
        gallery.setPointerCapture(event.pointerId);
        gallery.classList.add('is-dragging');
    });
    gallery.addEventListener('pointermove', event => {
        if (drag?.id === event.pointerId) gallery.scrollLeft = drag.left + drag.x - event.clientX;
    });
    const finishDrag = event => {
        if (drag?.id !== event.pointerId) return;
        const index = Math.round(gallery.scrollLeft / gallery.clientWidth);
        drag = null;
        gallery.classList.remove('is-dragging');
        if (gallery.hasPointerCapture(event.pointerId)) gallery.releasePointerCapture(event.pointerId);
        showSlide(index);
    };
    gallery.addEventListener('pointerup', finishDrag);
    gallery.addEventListener('pointercancel', finishDrag);
    new ResizeObserver(() => gallery.scrollTo({ left: galleryIndex * gallery.clientWidth, behavior: 'instant' })).observe(gallery);
    document.querySelectorAll('[data-open-details]').forEach(button => button.addEventListener('click', () => {
        if (dialog.open) return;
        previousFocus = document.activeElement;
        // Keep the existing cart control interactive inside the dialog's top layer.
        dialog.appendChild(bar);
        dialog.appendChild(floatingCart);
        dialog.showModal();
        document.body.classList.add('details-open');
        dialog.querySelector('[data-close-details]').focus();
    }));
    dialog.querySelector('[data-close-details]').addEventListener('click', () => dialog.close());
    dialog.addEventListener('click', event => {
        if (event.target === dialog) dialog.close();
    });
    dialog.addEventListener('close', () => {
        barHome.appendChild(bar);
        barHome.appendChild(floatingCart);
        document.body.classList.remove('details-open');
        previousFocus?.focus({ preventScroll: true });
    });
    const showNotice = message => {
        clearTimeout(noticeTimer);
        notice.textContent = message;
        notice.hidden = false;
        noticeTimer = setTimeout(() => { notice.hidden = true; }, 3000);
    };
    document.querySelector('[data-detail-share]').addEventListener('click', async () => {
        const data = { title: document.querySelector('#product-title').textContent, url: window.location.href };
        try {
            if (navigator.share) await navigator.share(data);
            else if (navigator.clipboard) {
                await navigator.clipboard.writeText(data.url);
                showNotice('Product link copied');
            } else showNotice('Copy the address from your browser to share this product.');
        } catch (error) {
            if (error.name !== 'AbortError') showNotice('Unable to share. Copy the address from your browser.');
        }
    });
})();
