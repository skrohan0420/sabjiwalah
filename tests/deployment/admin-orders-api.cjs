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
  for (const path of ['', 'products', 'checkout', 'api/v1/products', 'api/v1/cart']) assert.equal((await guest(path)).status, 200, path);
  assert.equal((await guest('api/v1/admin/orders')).status,401);
  assert.equal((await guest('admin/orders')).status,302);
  for(const phone of ['9000000003','9000000002']) {
    const request=await login(phone);
    assert.equal((await request('api/v1/admin/orders')).status,403);
    assert.equal((await request('api/v1/admin/orders/ord_fixture_missing')).status,403);
    assert.equal((await request('admin/orders')).status,302);
    if (phone === '9000000003') assert.equal((await request('api/v1/checkout/summary')).status, 200);
    if (phone === '9000000002') assert.equal((await request('api/v1/delivery/orders')).status, 200);
    await post(request,'api/v1/auth/logout',{});
  }
  const admin=await login('9000000001');
  const response=await admin('api/v1/admin/orders?per_page=1&sort=order_number&dir=asc');
  assert.equal(response.status,200);assert.match(response.headers.get('cache-control'),/no-store/);
  const data=(await response.json()).data;
  assert.ok(data.items.length<=1);assert.equal(data.pager.per_page,1);
  for(const query of ['per_page=101','status=invalid','from=invalid','from=2026-10-10&to=2026-10-09','sort=invalid','page=-1','page=1000001']) assert.equal((await admin('api/v1/admin/orders?'+query)).status,422,query);
  assert.equal((await admin('api/v1/admin/orders?search=NONEXISTENT_FIXTURE_889944')).status,200);
  assert.equal((await admin('api/v1/admin/orders/ord_fixture_missing')).status,404);
  const csrf=await token(admin);
  assert.equal((await admin('api/v1/admin/orders/ord_fixture_missing/status',{method:'PATCH',headers:{'Content-Type':'application/json',[csrf.header_name]:csrf.token_value},body:JSON.stringify({status:'confirmed'})})).status,404);
  if(data.items.length) {
    const order=data.items[0];assert.equal(typeof order.item_count,'number');assert.ok('assigned_delivery_name' in order);
    const detail=await admin('api/v1/admin/orders/'+encodeURIComponent(order.uid));assert.equal(detail.status,200);
    const payload=(await detail.json()).data;
    assert.ok(Array.isArray(payload.items)&&Array.isArray(payload.history)&&Array.isArray(payload.assignments));
    assert.ok(payload.allowed_actions.every(status=>['confirmed','preparing','ready_for_delivery','cancelled'].includes(status)));
    assert.ok(!('id' in payload.order));
    const missingCsrf=await admin('api/v1/admin/orders/'+order.uid+'/status',{method:'PATCH',headers:{'Content-Type':'application/json'},body:'{"status":"confirmed"}'});assert.equal(missingCsrf.status,403);
    async function patch(body) {const csrf=await token(admin);return admin('api/v1/admin/orders/'+order.uid+'/status',{method:'PATCH',headers:{'Content-Type':'application/json',[csrf.header_name]:csrf.token_value},body:JSON.stringify(body)});}
    assert.equal((await patch({status:order.order_status,expected_status:order.order_status})).status,400); // no actual mutation
    assert.equal((await patch({status:'confirmed',expected_status:order.order_status==='pending'?'confirmed':'pending'})).status,409);
    assert.equal((await patch({status:'invalid'})).status,422);
    assert.equal((await patch({status:'confirmed',notes:'x'.repeat(1001)})).status,422);
  }
  const page=await admin('admin/orders');assert.equal(page.status,200);assert.match(await page.text(),/admin-orders\.js/);
  await post(admin,'api/v1/auth/logout',{});
  console.log('PASS: live order role authorization, list/detail contracts, filters, missing orders, invalid transitions, stale status, CSRF and notes validation. No orders changed.');
})().catch(error=>{console.error(error.message);process.exitCode=1;});
