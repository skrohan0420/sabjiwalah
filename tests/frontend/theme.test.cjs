const test = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');
const source = fs.readFileSync('public/assets/js/theme.js', 'utf8');
function fixture(initial = {}, unavailable = false) {
  const saved = new Map(Object.entries(initial)), events = {}, windowEvents = {};
  const label = {}, toggle = { attrs: {}, setAttribute(key, value) { this.attrs[key] = value; } };
  const document = { documentElement: { dataset: {} }, querySelectorAll: selector => selector === '[data-account-appearance-label]' ? [label] : [toggle], addEventListener: (type, fn) => events[type] = fn };
  const window = { addEventListener: (type, fn) => windowEvents[type] = fn };
  const localStorage = {
    getItem(key) { if (unavailable) throw Error('Storage unavailable'); return saved.get(key); },
    setItem(key, value) { if (unavailable) throw Error('Storage unavailable'); saved.set(key, value); },
    removeItem(key) { saved.delete(key); },
  };
  vm.runInNewContext(source, { document, window, localStorage });
  return { saved, label, toggle, document, window, events, windowEvents };
}
test('saved global theme applies before DOMContentLoaded and initializes the switch label', () => {
  const f = fixture({ 'sabjiwalah.theme': 'dark' });
  assert.equal(f.document.documentElement.dataset.theme, 'dark');
  f.events.DOMContentLoaded();
  assert.equal(f.label.textContent, 'DARK');
  assert.equal(f.toggle.attrs['aria-label'], 'Switch to light mode');
});
test('account switch changes the global preference; next page reads it', () => {
  const f = fixture();
  f.events.click({ target: { closest: () => f.toggle } });
  assert.equal(f.saved.get('sabjiwalah.theme'), 'dark');
  assert.equal(fixture(Object.fromEntries(f.saved)).window.SabjiwalahTheme.get(), 'dark');
  f.window.SabjiwalahTheme.toggle();
  assert.equal(f.saved.get('sabjiwalah.theme'), 'light');
});
test('legacy account preference migrates and storage events update other open tabs', () => {
  const f = fixture({ 'sabjiwalah.accountTheme': 'dark' });
  assert.equal(f.saved.get('sabjiwalah.theme'), 'dark');
  f.saved.set('sabjiwalah.theme', 'light');
  f.windowEvents.storage({ key: 'sabjiwalah.theme' });
  assert.equal(f.document.documentElement.dataset.theme, 'light');
});
test('switch remains usable when browser storage is unavailable', () => {
  const f = fixture({}, true);
  f.window.SabjiwalahTheme.toggle();
  assert.equal(f.document.documentElement.dataset.theme, 'dark');
});
