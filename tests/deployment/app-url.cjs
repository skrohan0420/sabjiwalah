const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const script = fs.readFileSync('public/assets/js/app-url.js', 'utf8');
for (const base of ['http://localhost:8080/', 'https://dev.example.com/sabjiwalah/', 'https://dev.example.com/nested/shop/']) {
  const context = { URL, window: {}, document: { currentScript: { dataset: { baseUrl: base } } } };
  vm.runInNewContext(script, context);
  const url = context.window.Sabjiwalah.url;
  assert.equal(url('/api/v1/cart'), base + 'api/v1/cart');
  assert.equal(url('/assets/images/product-placeholder.svg'), base + 'assets/images/product-placeholder.svg');
  assert.equal(url('/login?redirect=%2Fcheckout'), base + 'login?redirect=%2Fcheckout');
  assert.equal(url(new URL(base).pathname + 'account'), base + 'account');
  assert.equal(url('https://images.example.com/photo.jpg'), 'https://images.example.com/photo.jpg');
}
console.log('App URL checks passed for root, subfolder, nested subfolder, and external images.');
