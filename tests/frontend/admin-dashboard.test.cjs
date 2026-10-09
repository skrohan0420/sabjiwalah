const test = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');
const source = fs.readFileSync('public/assets/js/admin-dashboard.js', 'utf8');
function node(dataset = {}) {
  return { dataset, children: [], events: {}, hidden: false, attributes: {}, textContent: '', append(...children) { this.children.push(...children); }, replaceChildren(...children) { this.children = children; }, setAttribute(k, v) { this.attributes[k] = v; }, addEventListener(k, v) { this.events[k] = v; } };
}
function setup() {
  const ids = Object.fromEntries(['dashboard-refresh', 'dashboard-content', 'dashboard-error', 'dashboard-loading', 'dashboard-updated', 'dashboard-recent', 'dashboard-attention'].map(id => [id, node()]));
  const stats = ['total_orders', 'sales_today'].map(key => node({ dashboardStat: key }));
  const events = {}, windowEvents = {}, requests = [], timers = new Map();
  let nextTimer = 0;
  const document = { hidden: false, getElementById: id => ids[id], querySelectorAll: () => stats, createElement: () => node(), createDocumentFragment: () => node(), addEventListener: (name, fn) => events[name] = fn };
  const context = { Intl, Date, Number, AbortController, document, setTimeout: (fn, delay) => { const id = ++nextTimer; timers.set(id, { fn, delay }); return id; }, clearTimeout: id => timers.delete(id), window: { addEventListener: (name, fn) => windowEvents[name] = fn, SabjiwalahAdmin: { busy: (button, state) => button.disabled = state, api: (path, options) => new Promise((resolve, reject) => requests.push({ path, options, resolve, reject })) } } };
  vm.runInNewContext(source, context);
  return { ids, stats, document, events, windowEvents, requests, timers };
}
const flush = () => new Promise(resolve => setImmediate(resolve));
function data(orders = []) { return { totals: { total_orders: 12, sales_today: 100 }, generated_at: '2026-10-09T12:00:00+05:30', recent_orders: orders, attention_orders: [] }; }
test('polling avoids overlap, pauses hidden pages and resumes once visible', async () => {
  const s = setup();
  assert.equal(s.requests.length, 1);
  s.ids['dashboard-refresh'].events.click();
  s.events.visibilitychange();
  assert.equal(s.requests.length, 1);
  s.requests[0].resolve({ data: data() }); await flush();
  assert.equal(s.timers.size, 1);
  assert.equal([...s.timers.values()][0].delay, 30000);
  s.document.hidden = true; s.events.visibilitychange();
  assert.equal(s.timers.size, 0);
  s.document.hidden = false; s.events.visibilitychange();
  assert.equal(s.requests.length, 2);
  s.requests[1].resolve({ data: data() }); await flush();
});
test('network failure keeps last snapshot and enables retry', async () => {
  const s = setup();
  s.requests[0].resolve({ data: data() }); await flush();
  s.ids['dashboard-refresh'].events.click();
  s.requests[1].reject(new Error('Offline')); await flush();
  assert.equal(s.stats[0].textContent, '12');
  assert.match(s.ids['dashboard-error'].textContent, /last successful update/);
  assert.equal(s.ids['dashboard-refresh'].disabled, false);
  assert.equal(s.timers.size, 1);
});
test('forbidden access stops polling; pagehide aborts an active request', async () => {
  const forbidden = setup();
  forbidden.requests[0].reject(Object.assign(new Error('Forbidden'), { status: 403 })); await flush();
  assert.equal(forbidden.timers.size, 0);
  assert.equal(forbidden.ids['dashboard-refresh'].disabled, true);
  const s = setup();
  s.windowEvents.pagehide();
  assert.equal(s.requests[0].options.signal.aborted, true);
  s.requests[0].reject(new Error('Aborted')); await flush();
  assert.equal(s.timers.size, 0);
});
test('customer text is rendered as text, empty lists are explicit, invalid data preserves snapshot', async () => {
  const s = setup();
  const name = '<img src=x onerror=alert(1)>';
  s.requests[0].resolve({ data: data([{ order_number: 'SW1', customer_name: name, total_amount: 25, order_status: 'pending', created_at: '2026-10-09T10:00:00Z' }]) }); await flush();
  const item = s.ids['dashboard-recent'].children[0].children[0];
  assert.equal(item.children[1].textContent, name);
  assert.match(s.ids['dashboard-attention'].children[0].children[0].textContent, /No orders/);
  s.ids['dashboard-refresh'].events.click();
  s.requests[1].resolve({ data: {} }); await flush();
  assert.equal(s.stats[0].textContent, '12');
  assert.equal(s.ids['dashboard-error'].hidden, false);
});
