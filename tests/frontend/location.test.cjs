const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('public/assets/js/location.js', 'utf8');
const flush = () => new Promise(resolve => setImmediate(resolve));

function fixture(apiKey = 'test-only-key') {
  const nodes = new Map();
  class Element {
    constructor() { this.listeners = {}; this.children = []; this.value = ''; this.disabled = false; this.hidden = false; this.dataset = {}; this.classList = { add() {}, remove() {}, toggle() {} }; }
    addEventListener(name, callback) { this.listeners[name] = callback; }
    emit(name, event = {}) { return this.listeners[name]?.({ preventDefault() {}, target: this, ...event }); }
    querySelector(selector) { return get(selector); }
    querySelectorAll() { return []; }
    appendChild(child) { this.children.push(child); return child; }
    replaceChildren() { this.children = []; }
    focus() {}
    remove() {}
  }
  const get = selector => { if (!nodes.has(selector)) nodes.set(selector, new Element()); return nodes.get(selector); };
  const sheet = get('[data-location-sheet]'); sheet.parentElement = { children: [sheet] };
  const canvas = get('[data-location-map]'); canvas.dataset = { googleApiKey: apiKey, googleMapId: 'DEMO_MAP_ID' };
  const saved = new Map();
  const instances = {};
  class GoogleMap {
    constructor(element, options) { this.listeners = {}; this.center = options.center; instances.map = this; }
    addListener(name, fn) { this.listeners[name] = fn; }
    setCenter(value) { this.center = value; this.listeners.center_changed?.(); }
    setZoom(value) { this.zoom = value; this.listeners.zoom_changed?.(); }
    getCenter() { return this.center; }
  }
  class Marker extends Element { constructor(options) { super(); Object.assign(this, options); instances.marker = this; } addListener(name, fn) { this.listeners[name] = fn; } }
  class Circle { constructor() { instances.circle = this; } setMap(map) { this.map = map; } setOptions(options) { Object.assign(this, options); } }
  class Geocoder { async geocode(request) { instances.geocodeRequest = request; return instances.geocodeReply || { results: [{ formatted_address: 'Nearby road label', geometry: { location: { lat: () => 0, lng: () => 0 } }, address_components: [{ long_name: 'Test Road', types: ['route'] }] }] }; } }
  const google = { maps: { importLibrary: async name => ({ maps: { Map: GoogleMap, Circle }, marker: { AdvancedMarkerElement: Marker, PinElement: Element }, geocoding: { Geocoder }, places: { Place: { searchByText: async () => ({ places: [{ displayName: 'Public landmark', formattedAddress: 'Test city', location: { lat: () => 22.86346, lng: () => 88.37121 } }] }) } } })[name] } };
  let gpsCallback;
  const context = { console, URL, URLSearchParams, setTimeout, clearTimeout, google,
    localStorage: { getItem: key => saved.get(key) || null, setItem: (key, value) => saved.set(key, value) },
    navigator: { geolocation: { watchPosition(callback) { gpsCallback = callback; return 7; }, clearWatch() { instances.gpsStopped = true; } } },
    requestAnimationFrame: fn => fn(),
    CustomEvent: class { constructor(type) { this.type = type; } },
    document: { addEventListener() {}, dispatchEvent() {}, querySelector: get, createElement: () => new Element(), activeElement: new Element(), body: new Element(), head: { appendChild() { queueMicrotask(() => context.window.sabjiwalahGoogleMapsReady()); } } },
  };
  context.window = { isSecureContext: true, addEventListener() {}, google };
  vm.runInNewContext(source, context);
  return { get, saved, instances, async open() { get('[data-location-open]').emit('click'); await flush(); }, close() { get('[data-location-close]').emit('click'); }, gps(position) { gpsCallback(position); } };
}

test('map movement selects its center; zoom alone keeps the point and reverse lookup cannot replace it', async () => {
  const f = fixture(); await f.open();
  f.instances.map.listeners.click({ latLng: { lat: () => 22.86346, lng: () => 88.37121 }, stop() {} });
  f.instances.map.setCenter({ lat: 23, lng: 89 });
  const selection = f.get('[data-location-selected]').title;
  f.instances.map.setZoom(19);
  assert.equal(f.get('[data-location-selected]').title, selection);
  await f.get('[data-location-confirm]').emit('click');
  const pin = JSON.parse(f.saved.get('sabjiwalah.deliveryLocation'));
  assert.equal(pin.latitude, 23); assert.equal(pin.longitude, 89);
  assert.equal(pin.label, 'Test Road'); assert.equal(pin.provider, 'google');
});

test('landmark search centers its exact point and dragging the map updates it', async () => {
  const f = fixture(); await f.open();
  f.get('[data-location-search]').value = 'Public landmark';
  await f.get('[data-location-search-form]').emit('submit');
  f.get('[data-location-results]').children[0].emit('click');
  assert.equal(f.instances.map.zoom, 18);
  f.instances.map.setCenter({ lat: 22.864, lng: 88.372 });
  await f.get('[data-location-confirm]').emit('click');
  const pin = JSON.parse(f.saved.get('sabjiwalah.deliveryLocation'));
  assert.equal(pin.latitude, 22.864); assert.equal(pin.longitude, 88.372);
});

test('GPS uses improving readings, ignores worse fixes and stops after manual placement', async () => {
  const f = fixture(); await f.open();
  f.get('[data-use-current-location]').emit('click');
  f.gps({ coords: { latitude: 22, longitude: 88, accuracy: 500 } });
  assert.match(f.get('[data-location-status]').textContent, /approximate/);
  f.gps({ coords: { latitude: 22.1, longitude: 88.1, accuracy: 50 } });
  f.gps({ coords: { latitude: 23, longitude: 89, accuracy: 700 } });
  assert.equal(f.instances.map.center.lat, 22.1);
  assert.equal(f.instances.circle.radius, 50);
  f.instances.map.listeners.click({ latLng: { lat: () => 22.2, lng: () => 88.2 } });
  assert.equal(f.instances.gpsStopped, true); assert.equal(f.instances.circle.map, null);
  f.gps({ coords: { latitude: 24, longitude: 90, accuracy: 5 } });
  assert.equal(f.instances.map.center.lat(), 22.2);
  f.close();
});

test('closing while reverse lookup is pending cannot save an unconfirmed location', async () => {
  const f = fixture(); await f.open();
  f.instances.map.listeners.click({ latLng: { lat: () => 22, lng: () => 88 } });
  let resolve;
  f.instances.geocodeReply = new Promise(done => { resolve = done; });
  const confirmation = f.get('[data-location-confirm]').emit('click');
  f.close(); resolve({ results: [] }); await confirmation;
  assert.equal(f.saved.size, 0);
});

test('missing Google key disables map-dependent actions without falling back to fake location', async () => {
  const f = fixture(''); await f.open();
  assert.equal(f.get('[data-location-confirm]').disabled, true);
  assert.equal(f.get('[data-use-current-location]').disabled, true);
  assert.match(f.get('[data-location-status]').textContent, /unavailable/);
  assert.equal(f.saved.size, 0); f.close();
});
