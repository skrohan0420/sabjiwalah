// Real HTTP, one cookie jar, isolated temporary SQL and filesystem. Never touches application rows.
const assert=require('node:assert/strict'),fs=require('node:fs/promises'),os=require('node:os'),path=require('node:path'),net=require('node:net');
const {spawn}=require('node:child_process'),{randomUUID}=require('node:crypto');
(async()=>{
 const token=randomUUID(),root=path.resolve(__dirname,'../..').replaceAll('\\','/');
 const dir=await fs.mkdtemp(path.join(os.tmpdir(),'sw-role-auth-'));
 const port=await new Promise(resolve=>{const s=net.createServer();s.listen(0,'127.0.0.1',()=>{const p=s.address().port;s.close(()=>resolve(p));});});
 const base=`http://127.0.0.1:${port}/`;
 for(const d of ['logs','cache','session','uploads'])await fs.mkdir(path.join(dir,'writable',d),{recursive:true});
 await fs.writeFile(path.join(dir,'router.php'),`<?php
if(PHP_SAPI!=='cli-server'||$_SERVER['REMOTE_ADDR']!=='127.0.0.1')exit;
header('X-Fixture: ${token}');
if(str_starts_with(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH),'/assets/'))return false;
define('ENVIRONMENT','development');define('FCPATH','${root}/public/');require '${root}/app/Config/Paths.php';$paths=new Config\\Paths();$paths->writableDirectory=__DIR__.'/writable';require $paths->systemDirectory.'/Boot.php';$app=CodeIgniter\\Boot::bootWorker($paths);require '${root}/tests/_support/fixture-database.php';config('App')->baseURL='${base}';config('Otp')->developmentMode=true;
$db=db_connect();if($db->DBDriver!=='MySQLi'||$db->DBPrefix!==''||!in_array($db->hostname,['localhost','127.0.0.1','::1'],true))throw new RuntimeException('Local SQL required.');
$tables=$db->listTables();foreach($tables as$table){$ddl=$db->query('SHOW CREATE TABLE '.$db->protectIdentifiers($table))->getRowArray()['Create Table'];$ddl=str_replace('CREATE TABLE','CREATE TEMPORARY TABLE',$ddl);$ddl=preg_replace('/^\\s*CONSTRAINT .* FOREIGN KEY .*\\n/m','',$ddl);$db->query(preg_replace('/,\\s*\\)/',')',$ddl));}
require '${root}/app/Database/Migrations/2026-10-12-000001_SeparateRoleAccounts.php';(new App\\Database\\Migrations\\SeparateRoleAccounts())->up();
$file=__DIR__.'/state.json';if(is_file($file)){$state=json_decode(file_get_contents($file),true);foreach($tables as$table)if(!empty($state[$table]))$db->table($table)->insertBatch($state[$table]);}
else {
 foreach(['customer','admin','delivery'] as$i=>$role)$db->table('users')->insert(['id'=>$i+1,'uid'=>'usr_role_'.$role,'name'=>'Sample '.ucfirst($role),'phone'=>'9876543210','email'=>'shared@example.test','role'=>$role,'status'=>'active']);
 $db->table('users')->insert(['id'=>4,'uid'=>'usr_other_customer','name'=>'Other Shopper','phone'=>'9876543211','role'=>'customer','status'=>'active']);
 $db->table('products')->insert(['id'=>1,'uid'=>'prd_roles','name'=>'Test Potato','slug'=>'test-potato','price'=>10,'unit'=>'kg','stock_quantity'=>100,'is_active'=>1]);
 $db->table('operational_settings')->insert(['id'=>1,'shop_open'=>1,'orders_paused'=>0,'delivery_charge'=>0,'free_delivery_minimum'=>0,'minimum_order_amount'=>0,'revision'=>1,'updated_at'=>date('Y-m-d H:i:s')]);
 $db->table('delivery_personnel_profiles')->insert(['user_id'=>3,'availability'=>'available','updated_at'=>date('Y-m-d H:i:s')]);
}
register_shutdown_function(function()use($db,$tables,$file){$out=[];foreach($tables as$table)$out[$table]=$db->table($table)->get()->getResultArray();file_put_contents($file,json_encode($out));});
if(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH)==='/_fixture'){
 if(($_SERVER['HTTP_X_FIXTURE']??'')!=='${token}'){http_response_code(404);exit;}
 $data=json_decode(file_get_contents('php://input'),true)??[];
 if(isset($data['status']))$db->table('users')->where('id',$data['id'])->update(['status'=>$data['status']]);
 if(isset($data['role']))$db->table('users')->where('id',$data['id'])->update(['role'=>$data['role'],'phone'=>$data['role']==='delivery'?'9876543210':'9876543299','email'=>null]);
 if(isset($data['set']))session()->set($data['set']);
 if(isset($data['age_send']))$db->table('otp_rate_limits')->set('last_used_at',time()-61)->update();
 if(isset($data['reset_limits']))$db->table('otp_rate_limits')->set('expires_at',time()-1)->set('last_used_at',time()-61)->update();
 header('Content-Type: application/json');echo json_encode(session()->get());exit;
}
$app->run();`);
 const server=spawn(process.env.PHP_BINARY||'D:/xampp/php/php.exe',['-S',`127.0.0.1:${port}`,'-t',root+'/public',path.join(dir,'router.php')],{cwd:root,windowsHide:true,stdio:['ignore','ignore','pipe']});let logs='';server.stderr.on('data',c=>logs+=c);
 function client(){const cookies=new Map();const req=async(url,options={})=>{const r=await fetch(base+url,{...options,redirect:'manual',headers:{...options.headers,Cookie:[...cookies].map(([k,v])=>`${k}=${v}`).join('; ')}});assert.equal(r.headers.get('x-fixture'),token);for(const c of r.headers.getSetCookie()){const pair=c.split(';')[0],i=pair.indexOf('=');cookies.set(pair.slice(0,i),pair.slice(i+1));}return r;};req.cookies=cookies;return req;}
 const jar=client();const fixture=async(data={})=>(await(await jar('_fixture',{method:'POST',headers:{'X-Fixture':token},body:JSON.stringify(data)})).json());
 const csrf=async(req=jar)=>(await(await req('api/v1/csrf')).json()).data;
 const post=async(url,data={},req=jar,t=null,method='POST')=>{t??=await csrf(req);return req(url,{method,headers:{'Content-Type':'application/json',[t.header_name]:t.token_value},body:JSON.stringify(data)});};
 const issue=async(role,phone='9876543210')=>{const r=await post(`api/v1/${role}/auth/otp/start`,{phone});assert.equal(r.status,200,await r.clone().text());return (await r.json()).data.dev_otp;};
 const verify=(role,code,phone='9876543210',t=null)=>post(`api/v1/${role}/auth/otp/verify`,{phone,otp:code},jar,t);
 const me=async(role,expected=200)=>{const r=await jar(`api/v1/${role}/auth/me`);assert.equal(r.status,expected,role);return (await r.json()).data;};
 try {
  let ready=false;for(let i=0;i<50;i++){try{if((await fetch(base+'api/v1/csrf')).headers.get('x-fixture')===token){ready=true;break;}}catch{}await new Promise(r=>setTimeout(r,100));}assert.ok(ready,logs);
  for(const area of ['login','admin/login','delivery/login']){const r=await jar(area);assert.equal(r.status,200);const html=await r.text();assert.ok(html.includes('name="viewport"'));assert.ok(!html.includes('type="password"'));}
  const tabs=await Promise.all([csrf(),csrf(),csrf()]);
  const codes={};for(const role of ['customer','admin','delivery'])codes[role]=await issue(role);
  assert.equal((await verify('admin',codes.customer)).status,401,'Customer OTP crossed into admin.');
  assert.equal((await verify('customer',codes.customer,'9876543211')).status,401,'OTP crossed phones.');
  let previous=jar.cookies.get('ci_session');
  assert.equal((await verify('customer',codes.customer,undefined,tabs[0])).status,200);
  assert.notEqual(jar.cookies.get('ci_session'),previous);
  await fixture({set:{cart_items:{prd_roles:{product_uid:'prd_roles',quantity:2}},checkout_offer:'KEEP',checkout_otp:{version:1,purpose:'checkout',phone:'9876543210',verified:true,expires_at:Math.floor(Date.now()/1000)+600}}});
  for(const [i,role] of ['admin','delivery'].entries()){
   previous=jar.cookies.get('ci_session');assert.equal((await verify(role,codes[role],undefined,tabs[i+1])).status,200);assert.notEqual(jar.cookies.get('ci_session'),previous);
  }
  for(const role of ['customer','admin','delivery'])assert.equal((await me(role)).user.uid,'usr_role_'+role);
  let state=await fixture();assert.deepEqual(state.cart_items,{prd_roles:{product_uid:'prd_roles',quantity:2}});assert.equal(state.checkout_offer,'KEEP');assert.equal(state.checkout_otp.verified,true);
  assert.equal((await verify('delivery',codes.delivery)).status,401,'OTP reused.');
  // Exercise ownership and audit actors while all three identities are authenticated.
  await fixture({reset_limits:true});
  assert.equal((await post('api/v1/checkout/offer',{},jar,null,'DELETE')).status,200);
  let checkout=await post('api/v1/checkout/otp/start',{customer_phone:'9876543210'});assert.equal(checkout.status,200);
  const checkoutCode=(await checkout.json()).data.dev_otp;
  assert.equal((await post('api/v1/checkout/otp/verify',{customer_phone:'9876543210',otp:checkoutCode})).status,200);
  const quote=(await(await jar('api/v1/checkout/summary')).json()).data;
  const placed=await post('api/v1/checkout/place',{customer_name:'Sample Customer',customer_phone:'9876543210',address_line:'Fixture address',city:'Fixture',postal_code:'700001',quote_token:quote.quote_token});
  assert.equal(placed.status,201,await placed.clone().text());const order=(await placed.json()).data.order;
  assert.equal((await jar('api/v1/orders/'+order.uid)).status,200);
  assert.equal((await jar('api/v1/delivery/orders/'+order.uid)).status,404);
  let expected='pending';for(const status of ['confirmed','preparing','ready_for_delivery']){
   const r=await post('api/v1/admin/orders/'+order.uid+'/status',{status,expected_status:expected},jar,null,'PATCH');assert.equal(r.status,200,await r.clone().text());expected=status;
  }
  const assigned=await post('api/v1/admin/orders/'+order.uid+'/assignment',{rider_uid:'usr_role_delivery',expected_assignment_uid:'none'});assert.equal(assigned.status,200,await assigned.clone().text());
  assert.equal((await post('api/v1/delivery/orders/'+order.uid+'/status',{status:'out_for_delivery',expected_status:'ready_for_delivery'},jar,null,'PATCH')).status,200);
  let records=JSON.parse(await fs.readFile(path.join(dir,'state.json'),'utf8'));
  assert.equal(Number(records.orders[0].user_id),1);assert.equal(Number(records.delivery_assignments[0].assigned_by),2);assert.equal(Number(records.delivery_assignments[0].delivery_user_id),3);
  assert.deepEqual(records.order_status_history.map(h=>Number(h.changed_by)),[1,2,2,2,3]);
  assert.equal((await verify('customer',await issue('customer','9876543211'),'9876543211')).status,200);
  assert.equal((await jar('api/v1/orders/'+order.uid)).status,404,'Another customer saw the order.');await me('admin');await me('delivery');
  assert.equal((await verify('customer',await issue('customer'))).status,200);
  await fixture({set:{cart_items:{prd_roles:{product_uid:'prd_roles',quantity:2}},checkout_offer:'KEEP',checkout_otp:{version:1,purpose:'checkout',phone:'9876543210',verified:true,expires_at:Math.floor(Date.now()/1000)+600}}});
  await fixture({set:{__ci_last_regenerate:Math.floor(Date.now()/1000)-301}});previous=jar.cookies.get('ci_session');await me('admin');assert.notEqual(jar.cookies.get('ci_session'),previous);await me('customer');await me('delivery');
  assert.equal((await post('api/v1/admin/auth/logout',{},jar,tabs[0])).status,200);await me('admin',403);await me('customer');await me('delivery');state=await fixture();assert.equal(state.checkout_offer,'KEEP');assert.equal(state.checkout_otp.verified,true);
  assert.equal((await post('api/v1/delivery/auth/logout',{},jar,tabs[1])).status,200);await me('customer');state=await fixture();assert.equal(state.checkout_offer,'KEEP');
  assert.equal((await post('api/v1/admin/auth/otp/start',{phone:'9876543212'})).status,422);assert.equal((await post('api/v1/delivery/auth/otp/start',{phone:'9876543212'})).status,422);
  for(const role of ['admin','delivery'])assert.equal((await post(`api/v1/${role}/auth/register`,{phone:'9876543212',otp:'123456'})).status,404);
  assert.equal((await post('api/v1/auth/otp/start',{phone:'9876543210',role:'admin'})).status,422);
  assert.equal((await post('api/v1/auth/login',{email:'shared@example.test',password:'password'})).status,422);
  assert.equal((await jar('api/v1/admin/settings')).status,403);
  await fixture({reset_limits:true});
  for(const role of ['admin','delivery'])assert.equal((await verify(role,await issue(role))).status,200);
  await fixture({status:'inactive',id:2});await me('admin',403);await me('customer');await me('delivery');
  await fixture({status:'active',id:2});await me('admin',403);
  await fixture({role:'admin',id:3});await me('delivery',403);await me('customer');await fixture({role:'delivery',id:3});
  await fixture({reset_limits:true});assert.equal((await verify('admin',await issue('admin'))).status,200);
  assert.equal((await post('api/v1/customer/auth/logout',{},jar,tabs[2])).status,200);await me('customer',403);await me('admin');state=await fixture();assert.ok(!state.checkout_otp&&!state.checkout_offer);assert.deepEqual(state.cart_items,{prd_roles:{product_uid:'prd_roles',quantity:2}});
  // Legacy records do not grant access, and cannot overwrite a valid role identity.
  await fixture({set:{user_id:2,user_uid:'usr_role_admin',user_role:'admin',is_logged_in:true,auth_method:'password'}});await me('admin');state=await fixture();assert.ok(!state.user_id&&!state.is_logged_in);
  // Role switching shares the phone budget. Reset only test-owned rate rows.
  await fixture({reset_limits:true});for(let i=0;i<5;i++)await post('api/v1/'+['customer','admin','delivery'][i%3]+'/auth/otp/verify',{phone:'9876543210',otp:'000000'});
  assert.equal((await post('api/v1/customer/auth/otp/verify',{phone:'9876543210',otp:'000000'})).status,429);
  await fixture({reset_limits:true});
  for(const role of ['customer','admin','delivery','customer','admin']){await fixture({age_send:true});await issue(role);}
  assert.equal((await post('api/v1/delivery/auth/otp/start',{phone:'9876543210'})).status,429,'Changing roles bypassed aggregate send limits.');
  await fixture({reset_limits:true});
  assert.equal((await verify('customer',await issue('customer','9876543222'),'9876543222')).status,200,'Public customer signup failed.');
  const newCustomer=(await me('customer')).user;assert.equal(newCustomer.role,'customer');assert.equal(newCustomer.profile_complete,false);
  assert.equal((await jar('admin/login')).status,200,'Customer setup intercepted staff login.');assert.equal((await jar('admin/settings')).status,200,'Customer setup intercepted staff area.');
  assert.equal((await jar('delivery/login')).status,200);await me('admin');
  const stranger=client();assert.equal((await stranger('api/v1/admin/auth/me')).status,401);
  assert.equal((await jar('api/v1/admin/auth/logout',{method:'POST',headers:{'Content-Type':'application/json'},body:'{}'})).status,403);await me('admin');
  const saved=JSON.parse(await fs.readFile(path.join(dir,'state.json'),'utf8'));assert.equal(saved.users.length,5,'Public staff login created an account.');
  if(process.argv.includes('--preview')){console.log('ROLE_PREVIEW '+base);await new Promise(resolve=>{process.once('SIGINT',resolve);process.stdin.once('data',resolve);});process.stdin.pause();}
  console.log('PASS: three role OTP logins using one cookie jar, same phone, independent logout, regenerated/periodic sessions, old-tab CSRF, cross-role/phone/replay denial, no public staff signup/password login, disabled/changed-role revocation, cart/checkout isolation, legacy invalidation and aggregate phone limits.');
 } catch(e){console.error(logs.slice(-1600));for(const f of await fs.readdir(path.join(dir,'writable','logs')))console.error((await fs.readFile(path.join(dir,'writable','logs',f),'utf8')).slice(-2200));throw e;}
 finally {if(server.exitCode===null){server.kill();await new Promise(r=>server.once('exit',r));}assert.ok(path.resolve(dir).startsWith(path.resolve(os.tmpdir())+path.sep)&&path.basename(dir).startsWith('sw-role-auth-'));await fs.rm(dir,{recursive:true,force:true});}
})().catch(e=>{console.error(e);process.exitCode=1;});
