(() => {
  'use strict';
  const base = new URL(document.currentScript.dataset.baseUrl);
  const connection = navigator.connection;
  const canPrefetch = !connection?.saveData && !/^(slow-)?2g$/.test(connection?.effectiveType || '');
  const seen = new Set();
  const scrollSelectors = ['.category-rail', '.category-tabs', '.category-filter-bar', '.search-filter-strip'];
  const stateKey = 'sabjiwalahNavigationId';
  const storageKey = 'sabjiwalah.navigationPositions';
  let entryId = history.state?.[stateKey];
  try {
    if (!entryId) {
      entryId = crypto.randomUUID?.() || `${Date.now()}-${Math.random()}`;
      history.replaceState({ ...(history.state || {}), [stateKey]: entryId }, '');
    }
  } catch { /* Native history restoration remains the fallback. */ }
  const readPositions = () => {
    try { return JSON.parse(sessionStorage.getItem(storageKey) || '{}') || {}; } catch { return {}; }
  };
  function savePosition() {
    if (!entryId) return;
    const positions = readPositions();
    positions[entryId] = { url: location.href, x: scrollX, y: scrollY, time: Date.now(), containers: {} };
    scrollSelectors.forEach(selector => {
      const node = document.querySelector(selector);
      if (node) positions[entryId].containers[selector] = { x: node.scrollLeft, y: node.scrollTop };
    });
    const latest = Object.entries(positions).sort((a, b) => b[1].time - a[1].time).slice(0, 30);
    try { sessionStorage.setItem(storageKey, JSON.stringify(Object.fromEntries(latest))); } catch { /* Native browser restoration still works. */ }
  }
  window.addEventListener('pagehide', savePosition);
  document.addEventListener('click', event => { if (event.target.closest('a[href], [data-search-redirect]')) savePosition(); }, true);
  window.addEventListener('pageshow', event => {
    // The back/forward cache already retains the whole live page, including forms.
    if (event.persisted || performance.getEntriesByType('navigation')[0]?.type !== 'back_forward') return;
    const saved = readPositions()[entryId];
    if (!saved || saved.url !== location.href) return;
    requestAnimationFrame(() => {
      Object.entries(saved.containers || {}).forEach(([selector, position]) => {
        document.querySelector(selector)?.scrollTo({ left: position.x, top: position.y, behavior: 'instant' });
      });
      window.scrollTo({ left: saved.x, top: saved.y, behavior: 'instant' });
    });
  });
  function safeDestination(anchor) {
    if (!anchor || anchor.hasAttribute('download') || anchor.target && anchor.target !== '_self') return null;
    let url;
    try { url = new URL(anchor.href, base); } catch { return null; }
    if (url.origin !== base.origin || !url.pathname.startsWith(base.pathname) || url.hash) return null;
    const path = url.pathname.slice(base.pathname.length).replace(/^index\.php\//, '');
    if (!(path === '' || path === 'products' || /^products\/prd_[\w-]+$/.test(path) || path === 'search')) return null;
    if (Array.from(url.searchParams.keys()).some(key => key !== 'q')) return null;
    return url.href === location.href ? null : url.href;
  }
  function prefetch(anchor) {
    if (!canPrefetch || document.hidden || seen.size >= 4) return;
    const url = safeDestination(anchor);
    if (!url || seen.has(url)) return;
    seen.add(url);
    if (window.HTMLScriptElement?.supports?.('speculationrules')) {
      const rules = document.createElement('script');
      rules.type = 'speculationrules';
      rules.textContent = JSON.stringify({ prefetch: [{ source: 'list', urls: [url], eagerness: 'moderate' }] });
      document.head.appendChild(rules);
    } else {
      const link = document.createElement('link');
      link.rel = 'prefetch'; link.href = url; link.as = 'document';
      document.head.appendChild(link);
    }
  }
  let intentTimer;
  document.addEventListener('pointerover', event => {
    clearTimeout(intentTimer);
    const anchor = event.target.closest('a[href]');
    if (anchor) intentTimer = setTimeout(() => prefetch(anchor), 100);
  }, { passive: true });
  document.addEventListener('pointerout', () => clearTimeout(intentTimer), { passive: true });
  document.addEventListener('focusin', event => prefetch(event.target.closest('a[href]')));
  document.addEventListener('touchstart', event => prefetch(event.target.closest('a[href]')), { passive: true });
})();
