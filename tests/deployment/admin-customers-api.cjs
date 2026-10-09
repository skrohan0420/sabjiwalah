// Local development only; existing seeded users and rejected account mutations only.
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

(async()=>{
  const guest=client();
  for(const path of ['','products','api/v1/products','api/v1/cart'])assert.equal((await guest(path)).status,200,path);
  assert.equal((await guest('api/v1/admin/customers')).status,401);
  assert.equal((await guest('admin/customers')).status,302);
  for(const phone of ['9000000003','9000000002']){
    const request=await login(phone);
    for(const path of ['api/v1/admin/customers','api/v1/admin/customers/usr_missing'])assert.equal((await request(path)).status,403);
    assert.equal((await request('admin/customers')).status,302);
    const csrf=await token(request);
    assert.equal((await request('api/v1/admin/customers/usr_missing/status',{method:'PATCH',headers:{'Content-Type':'application/json',[csrf.header_name]:csrf.token_value},body:'{"status":"inactive","expected_status":"active"}'})).status,403);
    assert.equal((await request('api/v1/auth/me')).status,200);
    if(phone==='9000000003')assert.equal((await request('api/v1/checkout/summary')).status,200);
    if(phone==='9000000002')assert.equal((await request('api/v1/delivery/orders')).status,200);
    await post(request,'api/v1/auth/logout',{});
  }
  const admin=await login('9000000001');
  const page=await admin('admin/customers');assert.equal(page.status,200);assert.match(await page.text(),/admin-customers\.js/);
  const response=await admin('api/v1/admin/customers?per_page=1');assert.equal(response.status,200);assert.match(response.headers.get('cache-control'),/no-store/);
  const data=(await response.json()).data;assert.ok(data.items.length<=1);assert.equal(data.pager.per_page,1);
  for(const row of data.items){assert.ok(!('phone' in row)&&!('email' in row)&&!('password_hash' in row)&&!('id' in row));assert.equal(typeof row.order_count,'number');}
  for(const query of ['page=0','page=1000001','per_page=101','status=invalid','sort=password_hash','dir=invalid'])assert.equal((await admin('api/v1/admin/customers?'+query)).status,422,query);
  assert.equal((await admin('api/v1/admin/customers/usr_missing')).status,404);
  const me=(await(await admin('api/v1/auth/me')).json()).data.user;
  assert.equal((await admin('api/v1/admin/customers/'+me.uid)).status,404,'Admin account must not appear as customer.');
  async function patch(uid,body){const csrf=await token(admin);return admin('api/v1/admin/customers/'+uid+'/status',{method:'PATCH',headers:{'Content-Type':'application/json',[csrf.header_name]:csrf.token_value},body:JSON.stringify(body)});}
  assert.equal((await patch(me.uid,{status:'inactive',expected_status:'active'})).status,404);
  if(data.items.length){
    const customer=data.items[0];
    const detail=await admin('api/v1/admin/customers/'+customer.uid+'?per_page=1');assert.equal(detail.status,200);assert.match(detail.headers.get('cache-control'),/no-store/);
    const payload=(await detail.json()).data;
    assert.equal(payload.customer.uid,customer.uid);assert.ok('phone' in payload.customer&&'email' in payload.customer);
    assert.ok(!('id' in payload.customer)&&!('password_hash' in payload.customer)&&!('otp' in payload.customer));
    assert.equal(payload.orders.pager.per_page,1);assert.ok(payload.orders.items.length<=1);
    for(const order of payload.orders.items)assert.ok(!('customer_phone' in order)&&!('address_line' in order)&&!('user_id' in order));
    assert.equal((await patch(customer.uid,{status:'invalid',expected_status:customer.status})).status,422);
    assert.equal((await patch(customer.uid,{status:'inactive',expected_status:customer.status,role:'admin'})).status,422);
    assert.equal((await patch(customer.uid,{status:'inactive'})).status,422);
    assert.equal((await patch(customer.uid,{status:customer.status,expected_status:customer.status==='active'?'inactive':'active'})).status,409);
    assert.equal((await admin('api/v1/admin/customers/'+customer.uid+'/status',{method:'PATCH',headers:{'Content-Type':'application/json'},body:'{"status":"inactive","expected_status":"active"}'})).status,403);
  }
  await post(admin,'api/v1/auth/logout',{});
  console.log('PASS: live guest/customer/rider/admin authorization, contact privacy, history pagination, input/CSRF rejection, privileged account protection and storefront/checkout/delivery access. No account status changed.');
})().catch(error=>{console.error(error);process.exitCode=1;});

