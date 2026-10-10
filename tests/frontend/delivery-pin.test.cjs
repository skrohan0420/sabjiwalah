const test = require('node:test'), assert = require('node:assert/strict'), fs = require('node:fs'), vm = require('node:vm');
const source = fs.readFileSync('public/assets/js/delivery-pin.js', 'utf8');
function setup(count = 1) {
  let now = Date.UTC(2026, 9, 10), tick;
  const docEvents = {}, events = {}, calls = [];
  const panels = Array.from({length: count}, (_, i) => {
    const nodes = Object.fromEntries(['generate', 'value', 'message'].map(key => [key, {textContent: '', hidden: true, disabled: false, addEventListener(event, fn) { this[event] = fn; }}]));
    return {dataset: {deliveryPin: 'ord_' + i}, nodes, querySelector(selector) { return nodes[selector.slice(10, -1)]; }};
  });
  class Clock extends Date { static now() { return now; } }
  vm.runInNewContext(source, {document: {querySelectorAll: () => panels, addEventListener: (name, fn) => docEvents[name] = fn},
    window: {Sabjiwalah: {url: path => '/shop/' + path}, location: {reload() { events.reloaded = true; }}, addEventListener: (name, fn) => events[name] = fn},
    fetch: (path, options) => new Promise((resolve, reject) => calls.push({path, options, resolve, reject})),
    setInterval: fn => { tick = fn; return 1; }, clearInterval: () => { events.cleared = true; }, Date: Clock, Error, TypeError, SyntaxError});
  return {panels, calls, events, docEvents, advance(ms) { now += ms; tick(); }, now: () => now};
}
const flush = () => new Promise(resolve => setImmediate(resolve));
const reply = (call, status, data) => call.resolve({ok: status === 200, status, json: async () => data});
async function start(s, index = 0) {
  const promise = s.panels[index].nodes.generate.click();
  reply(s.calls.at(-1), 200, {success: true, data: {header_name: 'X-CSRF-TOKEN', token_value: 'fresh'}});
  await flush(); return {promise};
}
function issued(s, pin = '012345') { return {success: true, data: {pin, expires_at: new Date(s.now() + 1800000).toISOString(), valid_for_seconds: 1800, retry_after: 60}}; }
test('customer issuance uses fresh CSRF, base path, one mutation and displays leading zeros safely', async () => {
  const s = setup(2); const pending = await start(s);
  s.panels[1].nodes.generate.click(); assert.equal(s.calls.length, 2);
  assert.equal(s.calls[1].path, '/shop/api/v1/orders/ord_0/delivery-pin');
  assert.equal(s.calls[1].options.headers['X-CSRF-TOKEN'], 'fresh'); assert.equal(s.calls[1].options.cache, 'no-store'); assert.equal(s.calls[1].options.body, '{}');
  reply(s.calls[1], 200, issued(s)); await pending.promise;
  assert.equal(s.panels[0].nodes.value.textContent, '012345'); assert.equal(s.panels[0].nodes.value.hidden, false);
  assert.equal(s.panels[0].nodes.generate.disabled, true); assert.equal(s.panels[1].nodes.generate.disabled, false);
  s.advance(60000); assert.equal(s.panels[0].nodes.generate.disabled, false);
});
test('expiry and page departure erase plaintext; late responses cannot restore it', async () => {
  const s = setup(); const pending = await start(s); reply(s.calls[1], 200, issued(s)); await pending.promise;
  s.advance(1800000); assert.equal(s.panels[0].nodes.value.textContent, ''); assert.equal(s.panels[0].nodes.value.hidden, true);
  const next = await start(s); s.events.pagehide(); reply(s.calls[3], 200, issued(s)); await next.promise;
  assert.equal(s.panels[0].nodes.value.textContent, ''); assert.equal(s.events.cleared, true);
  s.events.pageshow({persisted: true}); assert.equal(s.events.reloaded, true);
});
test('cooldown failures reveal no PIN and enforce server retry time without retries', async () => {
  const s = setup(); const pending = await start(s);
  reply(s.calls[1], 429, {success: false, message: 'Wait before regenerating.', data: {retry_after: 35}}); await pending.promise;
  assert.equal(s.panels[0].nodes.value.textContent, ''); assert.match(s.panels[0].nodes.message.textContent, /Wait/);
  s.panels[0].nodes.generate.click(); assert.equal(s.calls.length, 2); s.advance(35000); assert.equal(s.panels[0].nodes.generate.disabled, false);
});
test('ambiguous network failures do not retry issuance and delay another attempt', async () => {
  const s = setup(); const pending = await start(s); s.calls[1].reject(new TypeError('network')); await pending.promise;
  assert.match(s.panels[0].nodes.message.textContent, /may have been generated/); assert.equal(s.panels[0].nodes.generate.disabled, true);
  assert.equal(s.calls.length, 2); s.advance(60000); assert.equal(s.panels[0].nodes.generate.disabled, false);
});
test('malformed or injected PIN responses are not rendered; sessions report sign-in requirement', async () => {
  const s = setup(); const pending = await start(s); reply(s.calls[1], 200, issued(s, '<img>')); await pending.promise;
  assert.equal(s.panels[0].nodes.value.textContent, ''); s.advance(60000);
  const next = await start(s); reply(s.calls[3], 401, {success: false}); await next.promise;
  assert.match(s.panels[0].nodes.message.textContent, /Sign in again/);
});
