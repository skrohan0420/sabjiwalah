(() => {
  'use strict';
  document.querySelectorAll('[data-banner-carousel]').forEach((carousel) => {
    const track = carousel.querySelector('[data-banner-track]');
    const slides = Array.from(carousel.querySelectorAll('[data-banner-slide]'));
    const dots = Array.from(carousel.querySelectorAll('[data-banner-dot]'));
    const pause = carousel.querySelector('[data-banner-pause]');
    const motion = window.matchMedia('(prefers-reduced-motion: reduce)');
    let index = 0;
    let paused = motion.matches;
    let timer;
    let frame;
    const updatePause = () => {
      pause.textContent = paused ? '▶' : 'Ⅱ';
      pause.setAttribute('aria-label', paused ? 'Play banner rotation' : 'Pause banner rotation');
    };
    const stop = () => window.clearInterval(timer);
    const go = (next) => {
      const target = (next + slides.length) % slides.length;
      track.scrollTo({ left: target * track.clientWidth, behavior: motion.matches ? 'instant' : 'smooth' });
    };
    const start = () => {
      stop();
      if (!paused && !document.hidden && !carousel.matches(':hover') && !carousel.contains(document.activeElement)) {
        timer = window.setInterval(() => go(index + 1), 5500);
      }
    };
    track.addEventListener('scroll', () => {
      window.cancelAnimationFrame(frame);
      frame = window.requestAnimationFrame(() => {
        index = Math.round(track.scrollLeft / Math.max(track.clientWidth, 1));
        dots.forEach((dot, i) => dot.setAttribute('aria-current', String(i === index)));
      });
    }, { passive: true });
    dots.forEach((dot, i) => dot.addEventListener('click', () => { go(i); start(); }));
    pause.addEventListener('click', () => { paused = !paused; updatePause(); start(); });
    carousel.addEventListener('mouseenter', stop);
    carousel.addEventListener('mouseleave', start);
    carousel.addEventListener('focusin', stop);
    carousel.addEventListener('focusout', () => window.setTimeout(start, 0));
    track.addEventListener('pointerdown', stop, { passive: true });
    window.addEventListener('pointerup', start, { passive: true });
    document.addEventListener('visibilitychange', start);
    motion.addEventListener('change', () => { paused = motion.matches; updatePause(); start(); });
    new ResizeObserver(() => track.scrollTo({ left: index * track.clientWidth, behavior: 'instant' })).observe(track);
    carousel.querySelector('[data-banner-controls]').hidden = false;
    updatePause();
    start();
  });
})();
