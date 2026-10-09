const test = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');
const source = fs.readFileSync('public/assets/js/admin.js', 'utf8');
function setup(responses) {
  const calls = [], redirects = [];
  const node = { dataset: {}, classList: { toggle() {}, remove() {}, add() {}, contains() { return false; } }, setAttribute() {}, addEventListener() {}, querySelector() { return node; }, querySelectorAll() { return []; }, contains() { return true; } };
  const context = { URL, Headers, FormData, AbortController, setTimeout, clearTimeout, location: { href: 'https://example.test/shop/admin', origin: 'https://example.test', pathname: '/shop/admin', search: '?tab=1', assign: value => redirects.push(value) }, document: { currentScript: { dataset: { loginUrl: 'https://example.test/shop/login' } }, getElementById: () => node, querySelector: () => node, querySelectorAll: () => [], addEventListener() {}, body: node }, matchMedia: () => ({ matches: false, addEventListener() {} }), window: { Sabjiwalah: { url: path => 'https://example.test/shop/' + path.replace(/^\/+/, '') } }, fetch: async (url, options) => { calls.push({ url, options }); const response = responses.shift(); if (response instanceof Error) throw response; if (!response) throw new Error('Unexpected request'); return { ok: response.status < 400, status: response.status, json: async () => { if (response.invalid) throw new Error('Invalid JSON'); return response.payload; } }; } };
  vm.runInNewContext(source, context);
  return { api: context.window.SabjiwalahAdmin.api, calls, redirects };
}
const ok = data => ({ status: 200, payload: { success: true, data } });
const token = value => ok({ header_name: 'X-CSRF-TOKEN', token_value: value });
test('mutations serialize token acquisition and use rotated tokens', async () => {
  const { api, calls } = setup([token('first'), ok(null), token('second'), ok(null)]);
  await Promise.all([api('/api/v1/admin/products', { method: 'POST', body: { name: 'Apple' } }), api('/api/v1/admin/products/prd_1', { method: 'PATCH', body: { name: 'Pear' } })]);
  assert.deepEqual(calls.map(call => new URL(call.url).pathname), ['/shop/api/v1/csrf', '/shop/api/v1/admin/products', '/shop/api/v1/csrf', '/shop/api/v1/admin/products/prd_1']);
  assert.equal(calls[1].options.headers.get('X-CSRF-TOKEN'), 'first');
  assert.equal(calls[3].options.headers.get('X-CSRF-TOKEN'), 'second');
  assert.equal(calls[1].options.body, '{"name":"Apple"}');
  assert.equal(calls[1].options.credentials, 'same-origin');
});
test('only explicit CSRF rejection is retried once', async () => {
  const failure = { status: 403, payload: { success: false, message: 'Invalid or missing CSRF token' } };
  const { api, calls } = setup([token('old'), failure, token('fresh'), ok(null)]);
  await api('/api/v1/auth/logout', { method: 'POST' });
  assert.equal(calls.length, 4);
  assert.equal(calls[3].options.headers.get('X-CSRF-TOKEN'), 'fresh');
});
test('forbidden and network failures are not retried', async () => {
  for (const failure of [{ status: 403, payload: { success: false, message: 'Forbidden' } }, new Error('offline')]) {
    const { api, calls } = setup([token('one'), failure]);
    await assert.rejects(api('/api/v1/admin/products', { method: 'POST' }));
    assert.equal(calls.length, 2);
  }
});
test('expired session redirects to local login with return location', async () => {
  const { api, redirects } = setup([{ status: 401, payload: { success: false, message: 'Unauthenticated' } }]);
  await assert.rejects(api('/api/v1/admin/orders'), error => error.status === 401);
  const url = new URL(redirects[0]);
  assert.equal(url.pathname, '/shop/login');
  assert.equal(url.searchParams.get('redirect'), '/shop/admin?tab=1');
});
test('validation details remain available; invalid JSON fails safely', async () => {
  const { api } = setup([token('one'), { status: 422, payload: { success: false, message: 'Validation failed', errors: { price: 'Invalid price' } } }]);
  await assert.rejects(api('/api/v1/admin/products', { method: 'POST' }), error => error.errors.price === 'Invalid price');
  const malformed = setup([{ status: 200, invalid: true }]);
  await assert.rejects(malformed.api('/api/v1/admin/orders'), /unexpected response/);
});
