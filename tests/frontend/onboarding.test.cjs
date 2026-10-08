const test = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');
const source = fs.readFileSync('public/assets/js/onboarding.js', 'utf8');
const profileKey = 'sabjiwalah.customerProfile';
const locationKey = 'sabjiwalah.deliveryLocation';
function fixture(profile = null, pin = null, skipped = false, required = false) {
  const nodes = new Map(), data = new Map(), transient = new Map(), events = {};
  class Node {
    constructor() { this.hidden = false; this.value = ''; this.dataset = { accountName: '' }; this.listeners = {}; this.children = []; this.classList = { add() {}, remove() {}, toggle() {} }; }
    querySelector(selector) { return get(selector); }
    querySelectorAll() { return []; }
    addEventListener(type, fn) { this.listeners[type] = fn; }
    emit(type, event = {}) { return this.listeners[type]?.({ preventDefault() {}, ...event }); }
    focus() { document.activeElement = this; }
    replaceChildren(...children) { this.children = children; }
    appendChild(node) { this.children.push(node); }
    setAttribute() {}
  }
  const get = selector => { if (!nodes.has(selector)) nodes.set(selector, new Node()); return nodes.get(selector); };
  const overlay = get('[data-onboarding]'); overlay.hidden = true;
  overlay.dataset.required = String(required);
  overlay.dataset.saveAccount = String(required);
  const background = new Node(); overlay.parentElement = { children: [overlay, background] };
  const document = { querySelector: get, activeElement: new Node(), body: new Node(), createElement: () => new Node(), createTextNode: text => ({ textContent: text }),
    addEventListener: (type, fn) => events[type] = fn, dispatchEvent: event => events[event.type]?.(event) };
  const storage = map => ({ getItem: key => map.get(key) || null, setItem: (key, value) => map.set(key, value), removeItem: key => map.delete(key) });
  if (profile) data.set(profileKey, JSON.stringify(profile));
  if (pin) data.set(locationKey, JSON.stringify(pin));
  if (skipped) transient.set('sabjiwalah.welcomeSkipped', 'true');
  let rejectSave = false, reloaded = false;
  const requests = [];
  const window = { Sabjiwalah: { url: path => path }, location: { reload() { reloaded = true; } } };
  const fetch = async (path, options) => {
    requests.push({ path, options });
    return { ok: !rejectSave, json: async () => rejectSave ? { success: false, message: 'Please try again.' }
      : { success: true, data: { header_name: 'X-CSRF', token_value: 'test-token' } } };
  };
  vm.runInNewContext(source, { document, window, fetch, localStorage: storage(data), sessionStorage: storage(transient), CustomEvent: class { constructor(type) { this.type = type; } } });
  return { get, overlay, background, data, transient, events, document, requests, failSave() { rejectSave = true; }, reloaded: () => reloaded };
}
test('new visitor enters a trimmed name, cannot complete without a pin, and returns from map cancellation', () => {
  const f = fixture();
  assert.equal(f.overlay.hidden, false); assert.equal(f.background.inert, true);
  f.get('[data-welcome-name]').value = '   ';
  f.get('[data-welcome-name-form]').emit('submit');
  assert.match(f.get('[data-welcome-error]').textContent, /enter your name/);
  f.get('[data-welcome-name]').value = '  Rohan   Roy ';
  f.get('[data-welcome-name-form]').emit('submit');
  assert.equal(f.get('[data-welcome-name]').value, 'Rohan Roy');
  assert.equal(f.get('[data-welcome-location-step]').hidden, false);
  let opened = false; f.events['sabjiwalah:location-open'] = () => opened = true;
  f.get('[data-welcome-finish]').emit('click');
  assert.equal(opened, true); assert.equal(f.overlay.hidden, true);
  assert.equal(JSON.parse(f.data.get(profileKey)).completed, false);
  f.document.dispatchEvent({ type: 'sabjiwalah:location-closed' });
  assert.equal(f.overlay.hidden, false);
  assert.equal(f.get('[data-welcome-name]').value, 'Rohan Roy');
  f.data.set(locationKey, JSON.stringify({ latitude: 22.86, longitude: 88.37, label: 'My doorstep' }));
  f.get('[data-welcome-finish]').emit('click');
  assert.equal(f.overlay.hidden, true); assert.equal(f.background.inert, false);
  assert.equal(JSON.parse(f.data.get(profileKey)).completed, true);
  assert.equal(f.get('[data-onboarding-edit]').hidden, false);
  f.get('[data-onboarding-edit]').emit('click');
  f.get('[data-welcome-name]').value = 'A new name';
  f.get('[data-welcome-name-form]').emit('submit');
  f.get('[data-onboarding-skip]').emit('click');
  assert.equal(JSON.parse(f.data.get(profileKey)).name, 'Rohan Roy');
  assert.equal(JSON.parse(f.data.get(profileKey)).completed, true);
});
test('completed visitors with a valid pin are not prompted; a missing pin resumes location setup', () => {
  const profile = { version: 1, name: 'Rohan', completed: true };
  assert.equal(fixture(profile, { latitude: 22, longitude: 88 }).overlay.hidden, true);
  const f = fixture(profile, { latitude: 200, longitude: 88 });
  assert.equal(f.overlay.hidden, false); assert.equal(f.get('[data-welcome-location-step]').hidden, false);
});
test('Later allows browsing for this session without marking setup completed', () => {
  const f = fixture(); f.get('[data-onboarding-skip]').emit('click');
  assert.equal(f.overlay.hidden, true); assert.equal(f.background.inert, false);
  assert.equal(f.data.has(profileKey), false);
  assert.equal(fixture(null, null, true).overlay.hidden, true);
});

test('required signup setup ignores Later and Escape even with completed browser state; server failure stays open', async () => {
  const f = fixture({ name: 'Test Shopper', completed: true }, { latitude: 22, longitude: 88 }, true, true);
  assert.equal(f.overlay.hidden, false);
  f.get('[data-onboarding-skip]').emit('click');
  f.overlay.emit('keydown', { key: 'Escape' });
  assert.equal(f.overlay.hidden, false);
  f.get('[data-welcome-name-form]').emit('submit');
  f.failSave();
  await f.get('[data-welcome-finish]').emit('click');
  assert.equal(f.overlay.hidden, false);
  assert.equal(f.reloaded(), false);
  assert.match(f.get('[data-welcome-error]').textContent, /try again/);
});

test('required signup saves name and pin with CSRF before completing the browser state', async () => {
  const f = fixture(null, { latitude: 22, longitude: 88 }, false, true);
  f.get('[data-welcome-name]').value = 'Test Shopper';
  f.get('[data-welcome-name-form]').emit('submit');
  await f.get('[data-welcome-finish]').emit('click');
  assert.equal(f.requests[1].path, '/api/v1/account/profile');
  const body = JSON.parse(f.requests[1].options.body);
  assert.equal(body.name, 'Test Shopper');
  assert.equal(body.delivery_location.latitude, 22);
  assert.equal(f.requests[1].options.headers['X-CSRF'], 'test-token');
  assert.equal(f.reloaded(), true);
  assert.equal(JSON.parse(f.data.get(profileKey)).completed, true);
});
