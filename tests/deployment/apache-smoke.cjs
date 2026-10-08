// Run against an Apache deployment: node tests/deployment/apache-smoke.cjs URL
const assert = require('node:assert/strict');
const base = process.argv[2];
assert.ok(base && base.endsWith('/'), 'Supply the configured base URL with a trailing slash');
(async () => {
  for (const route of ['', 'products', 'search', 'orders', 'account', 'login', 'checkout', 'assets/css/home.css', 'assets/js/app-url.js', 'assets/images/product-placeholder.svg', 'robots.txt']) {
    const response = await fetch(base + route);
    assert.equal(response.status, 200, `${route || 'home'}: ${response.status}`);
    if (route === '') {
      const html = (await response.text()).replace(/&#x([0-9a-f]+);/gi, (_, hex) => String.fromCodePoint(parseInt(hex, 16))).replace(/&amp;/g, '&');
      assert.ok(html.includes(base + 'assets/js/app-url.js'), 'JS base URL bootstrap missing');
      assert.ok(html.includes(base + 'products'), 'Navigation does not use configured URL');
      assert.ok(!/(?:href|src|action)="\/(?!\/)/.test(html), 'Root-relative URL remains');
      const productLink = html.match(/href="([^"]*\/products\/prd_[^"]+)"/);
      assert.ok(productLink, 'Product URL separator missing');
      assert.equal((await fetch(productLink[1])).status, 200, 'Product detail link broken');
    }
  }
  assert.ok((await (await fetch(base + 'api/v1/csrf')).json()).success);
  assert.equal((await fetch(base + 'api/v1/admin/orders')).status, 401);
  assert.equal((await fetch(base + 'api/v1/delivery/orders')).status, 401);
  assert.equal((await fetch(base + 'api/v1/cart', { method: 'DELETE' })).status, 403);
  for (const route of ['.env', '.git/config', 'app/Config/Database.php', 'system/Boot.php', 'writable/', 'deployment/env.server.example', 'composer.json', 'env', 'assets/example.php']) {
    assert.equal((await fetch(base + route)).status, 403, `Private path accessible: ${route}`);
  }
  const legacy = await fetch(base + 'index.php/products?check=1', { redirect: 'manual' });
  assert.equal(legacy.status, 301);
  assert.equal(new URL(legacy.headers.get('location'), base).href, base + 'products?check=1');
  console.log('Apache smoke checks passed: pages, assets, API, private paths, and legacy redirect.');
})().catch(error => { console.error(error.message); process.exitCode = 1; });
