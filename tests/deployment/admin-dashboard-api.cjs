// Local development only; uses existing seeded users, never creates accounts/orders.
const assert = require('node:assert/strict');
const base = new URL(process.argv[2] || 'http://localhost:8080/');
assert.ok(['localhost', '127.0.0.1'].includes(base.hostname), 'Use a local development server only.');
function client() {
  const cookies = new Map();
  return async (path, options = {}) => {
    const response = await fetch(new URL(path, base), { ...options, redirect: 'manual', headers: { Accept: 'application/json', ...options.headers, Cookie: [...cookies].map(([k,v]) => `${k}=${v}`).join('; ') } });
    for (const cookie of response.headers.getSetCookie()) { const pair = cookie.split(';')[0]; const index = pair.indexOf('='); cookies.set(pair.slice(0,index), pair.slice(index+1)); }
    return response;
  };
}
async function token(request) {
  const response = await request('api/v1/csrf');
  assert.equal(response.status, 200);
  return (await response.json()).data;
}
async function post(request, path, body) {
  const csrf = await token(request);
  return request(path, { method: 'POST', headers: { 'Content-Type': 'application/json', [csrf.header_name]: csrf.token_value }, body: JSON.stringify(body) });
}
async function login(phone) {
  const request = client();
  const start = await post(request, 'api/v1/auth/otp/start', { phone });
  assert.equal(start.status, 200);
  const otp = (await start.json()).data;
  assert.equal(otp.user_exists, true, 'Seeded test account must already exist.');
  const verify = await post(request, 'api/v1/auth/otp/verify', { phone, otp: otp.dev_otp });
  assert.equal(verify.status, 200);
  return request;
}
(async () => {
  const guest = client();
  assert.equal((await guest('api/v1/admin/dashboard')).status, 401);
  assert.equal((await guest('admin')).status, 302);
  for (const phone of ['9000000003', '9000000002']) {
    const request = await login(phone);
    assert.equal((await request('api/v1/admin/dashboard')).status, 403);
    assert.equal((await request('admin')).status, 302);
    assert.equal((await post(request, 'api/v1/auth/logout', {})).status, 200);
  }
  const admin = await login('9000000001');
  const response = await admin('api/v1/admin/dashboard');
  assert.equal(response.status, 200);
  assert.match(response.headers.get('cache-control'), /no-store/);
  const payload = await response.json();
  assert.equal(payload.success, true);
  assert.equal(payload.data.timezone, 'Asia/Kolkata');
  for (const value of Object.values(payload.data.totals)) assert.equal(typeof value, 'number');
  assert.ok(payload.data.recent_orders.length <= 8 && payload.data.attention_orders.length <= 8);
  for (const order of [...payload.data.recent_orders, ...payload.data.attention_orders]) assert.ok(!('customer_phone' in order) && !('address_line' in order));
  const page = await admin('admin');
  assert.equal(page.status, 200);
  assert.match(await page.text(), /admin-dashboard\.js/);
  assert.equal((await admin('api/v1/admin/products', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: '{}' })).status, 403);
  assert.equal((await post(admin, 'api/v1/admin/products', {})).status, 422);
  assert.equal((await post(admin, 'api/v1/auth/logout', {})).status, 200);
  assert.equal((await admin('api/v1/admin/dashboard')).status, 401);
  console.log('PASS: live guest/customer/delivery/admin access, dashboard JSON and privacy, protected page, CSRF rejection, validation rejection, and logout.');
})().catch(error => { console.error(error.message); process.exitCode = 1; });
