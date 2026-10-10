// Read-only live checks and rejected mutations only; never provisions or changes real accounts.
const assert=require('node:assert/strict');
const base=new URL(process.argv[2]||'http://localhost:8080/');assert.ok(['localhost','127.0.0.1'].includes(base.hostname));
function client(){const cookies=new Map();return async(path,options={})=>{const response=await fetch(new URL(path,base),{...options,redirect:'manual',headers:{Accept:'application/json',...options.headers,Cookie:[...cookies].map(([k,v])=>`${k}=${v}`).join('; ')}});for(const cookie of response.headers.getSetCookie()){const pair=cookie.split(';')[0],i=pair.indexOf('=');cookies.set(pair.slice(0,i),pair.slice(i+1));}return response;};}
async function mutate(request,path,body,method='POST'){const csrf=(await(await request('api/v1/csrf')).json()).data;return request(path,{method,headers:{'Content-Type':'application/json',[csrf.header_name]:csrf.token_value},body:JSON.stringify(body)});}
async function login(phone){const request=client(),otp=(await(await mutate(request,'api/v1/auth/otp/start',{phone})).json()).data;assert.equal(otp.user_exists,true,'Test account must exist.');assert.equal((await mutate(request,'api/v1/auth/otp/verify',{phone,otp:otp.dev_otp})).status,200);return request;}
(async()=>{
  const endpoint='api/v1/admin/delivery-personnel',guest=client();
  assert.equal((await guest(endpoint)).status,401);assert.equal((await guest('admin/delivery-personnel')).status,302);
  assert.equal((await mutate(guest,endpoint,{name:'Never created',phone:'9876543210'})).status,401);
  for(const phone of ['9000000002','9000000003']){
    const request=await login(phone);
    assert.equal((await request(endpoint)).status,403);assert.equal((await request(endpoint+'/usr_missing')).status,403);
    assert.equal((await request('admin/delivery-personnel')).status,302);
    assert.equal((await mutate(request,endpoint,{name:'Never created',phone:'9876543210'})).status,403);
    for(const field of ['status','availability'])assert.equal((await mutate(request,endpoint+'/usr_missing/'+field,{[field]:'inactive',['expected_'+field]:'active'},'PATCH')).status,403);
    assert.equal((await request(phone==='9000000002'?'api/v1/delivery/orders':'api/v1/checkout/summary')).status,200);
    await mutate(request,'api/v1/auth/logout',{});
  }
  const admin=await login('9000000001'),page=await admin('admin/delivery-personnel');assert.equal(page.status,200);assert.match(await page.text(),/admin-delivery-personnel\.js/);
  const response=await admin(endpoint+'?per_page=1');assert.equal(response.status,200);assert.match(response.headers.get('cache-control'),/no-store/);
  const data=(await response.json()).data;assert.equal(data.pager.per_page,1);assert.ok(data.items.length<=1);
  for(const row of data.items){assert.ok(!('id'in row)&&!('phone'in row)&&!('email'in row)&&!('password_hash'in row));assert.equal(typeof row.workload,'number');assert.equal(typeof row.completed_deliveries,'number');
    for(const mode of ['current','history']){const detailResponse=await admin(endpoint+'/'+row.uid+'?mode='+mode+'&per_page=1');assert.equal(detailResponse.status,200);const detail=(await detailResponse.json()).data;assert.equal(detail.personnel.uid,row.uid);assert.ok(!('id'in detail.personnel)&&!('password_hash'in detail.personnel));assert.ok(detail.orders.items.length<=1);for(const order of detail.orders.items)assert.ok(!('address_line'in order)&&!('customer_phone'in order));}
    assert.equal((await mutate(admin,endpoint+'/'+row.uid+'/status',{status:'inactive',expected_status:row.status==='active'?'inactive':'active'},'PATCH')).status,409);
    assert.equal((await mutate(admin,endpoint+'/'+row.uid+'/availability',{availability:'busy',expected_availability:'offline'},'PATCH')).status,422);
    assert.equal((await admin(endpoint+'/'+row.uid+'/status',{method:'PATCH',headers:{'Content-Type':'application/json'},body:'{"status":"inactive","expected_status":"active"}'})).status,403);
  }
  const me=(await(await admin('api/v1/auth/me')).json()).data.user;assert.equal((await admin(endpoint+'/'+me.uid)).status,404);
  assert.equal((await mutate(admin,endpoint+'/'+me.uid+'/status',{status:'inactive',expected_status:'active'},'PATCH')).status,404);
  for(const query of ['page=0','per_page=101','search='+('x'.repeat(121)),'status=invalid'])assert.equal((await admin(endpoint+'?'+query)).status,422);
  assert.equal((await admin(endpoint+'/usr_missing')).status,404);
  for(const body of [{name:'X',phone:'bad'},{name:'X',phone:'9876543210',role:'admin'},{name:'X',phone:'9000000001'},{name:'X',phone:'9876543210',status:'active'}])assert.equal((await mutate(admin,endpoint,body)).status,422);
  assert.equal((await admin(endpoint,{method:'POST',headers:{'Content-Type':'application/json'},body:'{"name":"Never created","phone":"9876543210"}'})).status,403);
  await mutate(admin,'api/v1/auth/logout',{});
  assert.equal((await guest('api/v1/products')).status,200);
  console.log('PASS: live personnel routes, guest/customer/rider/admin access, private responses, pagination, IDOR, validation, stale writes and CSRF; existing delivery/checkout remain accessible. No records changed.');
})().catch(error=>{console.error(error);process.exitCode=1;});
