(() => {
  'use strict';
  const key = 'sabjiwalah.theme';
  const legacyKey = 'sabjiwalah.accountTheme';
  let current = 'light';
  const valid = value => value === 'dark' || value === 'light';
  const read = () => {
    try {
      const saved = localStorage.getItem(key);
      if (valid(saved)) return saved;
      const legacy = localStorage.getItem(legacyKey);
      if (valid(legacy)) { localStorage.setItem(key, legacy); return legacy; }
    } catch { /* Theme switching still works without browser storage. */ }
    return 'light';
  };
  function apply(theme) {
    current = valid(theme) ? theme : 'light';
    document.documentElement.dataset.theme = current;
    document.querySelectorAll('[data-account-appearance-label]').forEach(node => node.textContent = current.toUpperCase());
    document.querySelectorAll('[data-account-appearance], [data-theme-toggle]').forEach(node => {
      node.setAttribute('aria-label', `Switch to ${current === 'dark' ? 'light' : 'dark'} mode`);
      node.setAttribute('aria-pressed', String(current === 'dark'));
    });
  }
  const set = theme => {
    apply(theme);
    try { localStorage.setItem(key, current); localStorage.removeItem(legacyKey); } catch { /* Use the chosen theme until navigation. */ }
  };
  window.SabjiwalahTheme = { set, get: () => current, toggle: () => set(current === 'dark' ? 'light' : 'dark') };
  // Set the root theme in the head, before the first page paint.
  apply(read());
  document.addEventListener('DOMContentLoaded', () => apply(current));
  window.addEventListener('pageshow', () => apply(read()));
  document.addEventListener('click', event => {
    if (event.target.closest('[data-account-appearance], [data-theme-toggle]')) window.SabjiwalahTheme.toggle();
  });
  window.addEventListener('storage', event => {
    if (event.key === key || event.key === legacyKey || event.key === null) apply(read());
  });
})();
