// Local development only; existing seeded users and rejected mutations only.
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
  assert.equal((await guest('api/v1/admin/products')).status,401);
  assert.equal((await guest('admin/products')).status,302);
  for(const phone of ['9000000003','9000000002']) {
    const request=await login(phone);
    assert.equal((await request('api/v1/admin/products')).status,403);
    assert.equal((await request('admin/products')).status,302);
    const csrf=await token(request);
    const body=new FormData(); body.append('image',new Blob(['fake'],{type:'image/png'}),'fake.png');
    assert.equal((await request('api/v1/admin/products/prd_missing/image',{method:'POST',headers:{[csrf.header_name]:csrf.token_value},body})).status,403);
    await post(request,'api/v1/auth/logout',{});
  }
  const admin=await login('9000000001');
  const response=await admin('api/v1/admin/products?per_page=1&sort=name&dir=asc');
  assert.equal(response.status,200);assert.match(response.headers.get('cache-control'),/no-store/);
  const data=(await response.json()).data;
  assert.ok(data.items.length<=1);assert.equal(data.pager.per_page,1);
  for(const query of ['per_page=101','active=invalid','sort=invalid','page=-1','page=1000001']) assert.equal((await admin('api/v1/admin/products?'+query)).status,422,query);
  assert.equal((await admin('api/v1/admin/products/prd_missing')).status,404);
  assert.equal((await post(admin,'api/v1/admin/products',{name:'Invalid fixture',slug:'invalid-fixture',price:-1,unit:'kg',stock_quantity:-1})).status,422);
  if(data.items.length) {
    const product=data.items[0];
    assert.equal((await admin('api/v1/admin/products/'+product.uid)).status,200);
    assert.equal((await admin('api/v1/admin/products/'+product.uid,{method:'PATCH',headers:{'Content-Type':'application/json'},body:'{"stock_quantity":-1}'})).status,403);
    const csrf=await token(admin);
    assert.equal((await admin('api/v1/admin/products/'+product.uid,{method:'PATCH',headers:{'Content-Type':'application/json',[csrf.header_name]:csrf.token_value},body:'{"stock_quantity":-1}'})).status,422);
    const imageCsrf=await token(admin), body=new FormData(); body.append('image',new Blob(['<?php echo 1;'],{type:'image/png'}),'fake.png');
    const upload=await admin('api/v1/admin/products/'+product.uid+'/image',{method:'POST',headers:{[imageCsrf.header_name]:imageCsrf.token_value},body});
    assert.equal(upload.status,422);assert.ok((await upload.json()).errors.image);
  }
  assert.equal((await admin('media/products/invalid.php')).status,404);
  const page=await admin('admin/products');assert.equal(page.status,200);assert.match(await page.text(),/admin-products\.js/);
  await post(admin,'api/v1/auth/logout',{});
  console.log('PASS: live product authorization, filters, read contracts, CSRF and rejected price/stock/file mutations. No catalogue records changed.');
})().catch(error=>{console.error(error.message);process.exitCode=1;});

