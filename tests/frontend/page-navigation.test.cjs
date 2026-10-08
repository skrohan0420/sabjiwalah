const test = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');
const source = fs.readFileSync('public/assets/js/page-navigation.js', 'utf8');
function fixture({ data = new Map(), navType = 'navigate', state = {}, saveData = false, speculation = true } = {}) {
  const events = {}, windowEvents = {}, added = [], restored = [];
  const rail = { scrollLeft: 20, scrollTop: 150, scrollTo: position => restored.push(position) };
  const history = { state, replaceState(next) { this.state = next; } };
  const document = { currentScript: { dataset: { baseUrl: 'https://example.test/shop/' } }, hidden: false,
    querySelector: selector => selector === '.category-rail' ? rail : null,
    addEventListener: (name, fn) => events[name] = fn, createElement: type => ({ tag: type }), head: { appendChild: node => added.push(node) } };
  const window = { addEventListener: (name, fn) => windowEvents[name] = fn, scrollTo: position => restored.push(position), HTMLScriptElement: { supports: () => speculation } };
  const location = { href: 'https://example.test/shop/products?q=greens' };
  vm.runInNewContext(source, { document, window, history, location, URL, navigator: { connection: { saveData } }, crypto: { randomUUID: () => 'entry-1' },
    scrollX: 0, scrollY: 650, performance: { getEntriesByType: () => [{ type: navType }] }, requestAnimationFrame: fn => fn(), setTimeout, clearTimeout,
    sessionStorage: { getItem: key => data.get(key), setItem: (key, value) => data.set(key, value) } });
  const focus = (href, extra = {}) => events.focusin({ target: { closest: () => ({ href, hasAttribute: () => false, target: '', ...extra }) } });
  return { data, history, windowEvents, restored, added, focus };
}
test('prefetch allows browsing URLs in the installation and excludes actions, private pages and external origins', () => {
  const f = fixture();
  ['logout', 'account', 'login', 'checkout', 'api/v1/cart', 'products?delete=1'].forEach(path => f.focus('https://example.test/shop/' + path));
  f.focus('https://outside.test/shop/products');
  f.focus('https://example.test/other/products');
  assert.equal(f.added.length, 0);
  f.focus('https://example.test/shop/products/prd_test');
  assert.equal(f.added.length, 1);
  assert.deepEqual(JSON.parse(f.added[0].textContent).prefetch[0].urls, ['https://example.test/shop/products/prd_test']);
  f.focus('https://example.test/shop/products/prd_test');
  assert.equal(f.added.length, 1);
});
test('prefetch respects data saver, uses a fallback, and stays bounded', () => {
  const saver = fixture({ saveData: true }); saver.focus('https://example.test/shop/products/prd_a'); assert.equal(saver.added.length, 0);
  const f = fixture({ speculation: false });
  for (let i = 0; i < 8; i++) f.focus('https://example.test/shop/products/prd_' + i);
  assert.equal(f.added.length, 4); assert.equal(f.added[0].rel, 'prefetch');
});
test('back navigation restores the correct history entry and category position', () => {
  const first = fixture({ state: { existing: true } });
  assert.equal(first.history.state.existing, true);
  first.windowEvents.pagehide();
  const back = fixture({ data: first.data, state: first.history.state, navType: 'back_forward' });
  back.windowEvents.pageshow({ persisted: false });
  assert.equal(back.restored[0].top, 150);
  assert.equal(back.restored[1].top, 650);
});
test('fresh navigation starts normally and live back/forward cache is left intact', () => {
  const first = fixture(); first.windowEvents.pagehide();
  const fresh = fixture({ data: first.data, state: first.history.state }); fresh.windowEvents.pageshow({ persisted: false }); assert.equal(fresh.restored.length, 0);
  const cached = fixture({ data: first.data, state: first.history.state, navType: 'back_forward' }); cached.windowEvents.pageshow({ persisted: true }); assert.equal(cached.restored.length, 0);
});
