(() => {
  'use strict';
  const paths = {
    refresh: 'M20 7v5h-5M4 17v-5h5M6 6a8 8 0 0 1 13 3M5 15a8 8 0 0 0 13 3',
    logout: 'M9 4H4v16h5M9 12h12m-4-4 4 4-4 4',
    phone: 'M8 3H4v4c0 7 6 13 13 13h4v-4l-5-2-2 2a12 12 0 0 1-6-6l2-2-2-5Z',
    route: 'm12 3 9 9-9 9-9-9 9-9ZM8 14v-4h8m-3-3 3 3-3 3',
    pin: 'M19 10c0 5-7 11-7 11S5 15 5 10a7 7 0 0 1 14 0ZM10 10h4',
    filter: 'M4 6h16M7 12h10M10 18h4',
    check: 'm5 12 4 4L19 6',
    box: 'm3 7 9-4 9 4v10l-9 4-9-4V7Zm0 0 9 4 9-4M12 11v10',
    left: 'm14 6-6 6 6 6',
    right: 'm10 6 6 6-6 6',
    close: 'm6 6 12 12M18 6 6 18',
    warning: 'm12 3 10 18H2L12 3Zm0 6v5m0 3v1',
  };
  const rules = new Map([
    ['Refresh', ['refresh', '', true]], ['Sign out', ['logout', '', true]],
    ['Previous', ['left', '', true]], ['Next', ['right', '', true]],
    ['Call customer', ['phone', 'Call', false]],
    ['View order details', ['right', 'Details', false]],
    ['Navigate to Customer', ['route', 'Navigate', false]],
    ['Navigate using address', ['route', 'Navigate', false]],
    ['Open directions in browser', ['route', 'Directions', false]],
    ['Share my location', ['pin', 'Share location', false]],
    ['Clear shared location', ['close', 'Clear', false]],
    ['Show my location', ['pin', 'My location', false]],
    ['Show driving route', ['route', 'Route', false]],
    ['Clear my location', ['close', 'Clear location', false]],
    ['Record pickup', ['box', 'Record pickup', false]],
    ['Verify and complete delivery', ['check', 'Complete delivery', false]],
    ['Report failed delivery', ['warning', 'Report a problem', false]],
    ['Apply filters', ['check', 'Apply', false]],
    ['Clear filters', ['close', 'Clear', false]],
    ['Enable tracking', ['pin', 'Start sharing', false]],
    ['Stop tracking', ['close', 'Stop sharing', false]],
    ['Open my deliveries', ['box', 'My deliveries', false]],
    ['Manage availability on Home', ['left', 'Availability', false]],
  ]);
  const done = new WeakSet();
  function decorate() {
    document.querySelectorAll('.delivery-button').forEach(button => {
      if (done.has(button) || button.querySelector('svg')) return;
      const original = button.textContent.trim();
      const rule = rules.get(original);
      if (!rule) return;
      done.add(button);
      const [name, label, iconOnly] = rule;
      const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
      svg.setAttribute('viewBox', '0 0 24 24');
      svg.setAttribute('aria-hidden', 'true');
      svg.classList.add('delivery-action-icon');
      const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
      path.setAttribute('d', paths[name]); svg.append(path);
      button.setAttribute('aria-label', original);
      button.title = original;
      button.textContent = '';
      button.append(svg);
      if (label) button.append(document.createTextNode(label));
      if (iconOnly) button.classList.add('delivery-icon-only');
    });
  }
  decorate();
  const observer = new MutationObserver(decorate);
  observer.observe(document.body, { childList: true, subtree: true });
  window.addEventListener('pagehide', () => observer.disconnect());
})();
