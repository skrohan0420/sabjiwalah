// Full HTTP provisioning/status checks in temporary SQL and writable storage, plus optional sample UI.
const assert=require('node:assert/strict'),fs=require('node:fs/promises'),os=require('node:os'),path=require('node:path'),net=require('node:net'),{randomUUID}=require('node:crypto'),{spawn}=require('node:child_process');
(async()=>{
  const fixtureToken=randomUUID();
  const port=process.argv.includes('--preview')?8769:await new Promise((resolve,reject)=>{const probe=net.createServer();probe.on('error',reject);probe.listen(0,'127.0.0.1',()=>{const selectedPort=probe.address().port;probe.close(()=>resolve(selectedPort));});});
  const base='http://127.0.0.1:'+port+'/';
  const root=path.resolve(__dirname,'../..').replaceAll('\\','/'),directory=await fs.mkdtemp(path.join(os.tmpdir(),'sabjiwalah-riders-'));
  for(const name of ['logs','cache','session','uploads'])await fs.mkdir(path.join(directory,'writable',name),{recursive:true});
  const router=`<?php
if(PHP_SAPI!=='cli-server'||!in_array($_SERVER['REMOTE_ADDR'],['127.0.0.1','::1'],true))exit;
header('X-Sabjiwalah-Fixture: ${fixtureToken}');
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if($path==='/admin'){header('Location: /admin/delivery-personnel');exit;}
if(str_starts_with($path,'/assets/'))return false;
if(!preg_match('#^/(?:login|delivery|admin/delivery-personnel|api/v1/(?:csrf|auth/(?:otp/start|otp/verify|logout|me)|delivery/orders|admin/delivery-personnel(?:/[^/]+(?:/status|/availability)?)?))$#D',$path)){http_response_code(404);exit;}
define('ENVIRONMENT','development');define('FCPATH','${root}/public/');require '${root}/app/Config/Paths.php';
$paths=new Config\\Paths();$paths->writableDirectory=__DIR__.'/writable';require $paths->systemDirectory.'/Boot.php';$app=CodeIgniter\\Boot::bootWorker($paths);
config('Otp')->developmentMode=true;config('App')->baseURL='${base}';
$db=db_connect();if($db->DBDriver!=='MySQLi'||$db->DBPrefix!=='')throw new RuntimeException('Use local MySQL without prefix');
foreach(['users','orders','delivery_assignments','delivery_personnel_profiles','otp_rate_limits'] as $table){$ddl=$db->query('SHOW CREATE TABLE '.$table)->getRowArray()['Create Table'];$ddl=str_replace('CREATE TABLE','CREATE TEMPORARY TABLE',$ddl);$ddl=preg_replace('/^\\s*CONSTRAINT .* FOREIGN KEY .*\\n/m','',$ddl);$db->query(preg_replace('/,\\s*\\)/',')',$ddl));}
$stateFile=__DIR__.'/state.json';
$state=is_file($stateFile)?json_decode(file_get_contents($stateFile),true):['profiles'=>[], 'users'=>[
['id'=>1,'uid'=>'usr_fixture_admin','name'=>'Fixture Admin','email'=>null,'phone'=>'9000000001','role'=>'admin','status'=>'active','created_at'=>'2026-10-01 09:00:00'],
['id'=>2,'uid'=>'usr_fixture_rider','name'=>'Asha (sample rider)','email'=>'sample@example.test','phone'=>'9000000002','role'=>'delivery','status'=>'active','created_at'=>'2026-10-05 09:00:00'],
['id'=>3,'uid'=>'usr_fixture_second','name'=>'Bimal (sample rider)','email'=>null,'phone'=>'9000000004','role'=>'delivery','status'=>'inactive','created_at'=>'2026-10-06 09:00:00'],
['id'=>4,'uid'=>'usr_fixture_customer','name'=>'Sample Customer','email'=>null,'phone'=>'9000000003','role'=>'customer','status'=>'active','created_at'=>'2026-10-06 09:00:00']]];
$db->table('users')->insertBatch($state['users']);if($state['profiles'])$db->table('delivery_personnel_profiles')->insertBatch($state['profiles']);
for($i=1;$i<=12;$i++){
$db->table('orders')->insert(['id'=>$i,'uid'=>'ord_fixture_'.$i,'order_number'=>'SW-SAMPLE-'.str_pad((string)$i,3,'0',STR_PAD_LEFT),'user_id'=>4,'subtotal'=>120,'total_amount'=>120,'payment_method'=>'cod','payment_status'=>$i<=9?'paid':'pending','order_status'=>$i<=9?'delivered':($i===10?'out_for_delivery':'ready_for_delivery'),'customer_name'=>'Sample Customer','customer_phone'=>'9000000003','address_line'=>'Sample address','city'=>'Sample city','created_at'=>'2026-10-08 09:00:00']);
$db->table('delivery_assignments')->insert(['id'=>$i,'uid'=>'das_fixture_'.$i,'order_id'=>$i,'delivery_user_id'=>2,'assigned_by'=>1,'assigned_at'=>'2026-10-08 10:00:00','completed_at'=>$i<=9?'2026-10-08 11:00:00':null]);}
$app->run();$state['users']=$db->table('users')->get()->getResultArray();$state['profiles']=$db->table('delivery_personnel_profiles')->get()->getResultArray();file_put_contents($stateFile,json_encode($state));`;
  await fs.writeFile(path.join(directory,'router.php'),router);
  const server=spawn(process.env.PHP_BINARY||'D:/xampp/php/php.exe',['-S','127.0.0.1:'+port,'-t',root+'/public',path.join(directory,'router.php')],{cwd:root,windowsHide:true,stdio:['ignore','ignore','pipe']});
  let diagnostics='';server.stderr.on('data',chunk=>diagnostics+=chunk);
  function client(){const cookies=new Map();return async(url,options={})=>{const response=await fetch(base+url,{...options,redirect:'manual',headers:{Accept:'application/json',...options.headers,Cookie:[...cookies].map(([k,v])=>k+'='+v).join('; ')}});assert.equal(response.headers.get('x-sabjiwalah-fixture'),fixtureToken,'Never send test actions to a different server.');for(const cookie of response.headers.getSetCookie()){const pair=cookie.split(';')[0],i=pair.indexOf('=');cookies.set(pair.slice(0,i),pair.slice(i+1));}return response;};}
  async function mutate(request,url,body,method='POST'){const response=await request('api/v1/csrf');assert.equal(response.status,200);const csrf=(await response.json()).data;return request(url,{method,headers:{'Content-Type':'application/json',[csrf.header_name]:csrf.token_value},body:JSON.stringify(body)});}
  async function login(request,phone){const start=await mutate(request,'api/v1/auth/otp/start',{phone});assert.equal(start.status,200);const otp=(await start.json()).data;assert.equal(otp.user_exists,true,'Account must exist.');return mutate(request,'api/v1/auth/otp/verify',{phone,otp:otp.dev_otp});}
  try{
    let ready=false;for(let i=0;i<40;i++){try{const response=await fetch(base+'api/v1/csrf');if(response.headers.get('x-sabjiwalah-fixture')===fixtureToken){ready=true;break;}}catch{}if(server.exitCode!==null)break;await new Promise(resolve=>setTimeout(resolve,100));}assert.ok(ready,'Owned fixture failed to start: '+diagnostics);
    const admin=client(),rider=client();assert.equal((await login(admin,'9000000001')).status,200);assert.equal((await login(rider,'9000000002')).status,200);
    const endpoint='api/v1/admin/delivery-personnel',action=endpoint+'/usr_fixture_rider/status';
    const before=(await(await admin(endpoint+'/usr_fixture_rider?mode=history')).json()).data;assert.equal(before.personnel.workload,3);assert.equal(before.personnel.completed_deliveries,9);assert.equal(before.orders.pager.total,12);
    assert.equal((await mutate(admin,action,{status:'inactive',expected_status:'active'},'PATCH')).status,200);
    assert.equal((await rider('api/v1/auth/me')).status,401);assert.equal((await rider('api/v1/delivery/orders')).status,401);assert.equal((await login(rider,'9000000002')).status,401);
    assert.equal((await mutate(admin,action,{status:'active',expected_status:'active'},'PATCH')).status,409);
    const inactive=(await(await admin(endpoint+'/usr_fixture_rider?mode=history')).json()).data;assert.equal(inactive.orders.pager.total,12);assert.equal(inactive.personnel.workload,3);assert.equal(inactive.personnel.availability,'unavailable');assert.equal(inactive.personnel.base_availability,'offline');
    assert.equal((await mutate(admin,action,{status:'active',expected_status:'inactive'},'PATCH')).status,200);assert.equal((await rider('api/v1/auth/me')).status,401);assert.equal((await login(rider,'9000000002')).status,200);
    assert.equal((await mutate(admin,endpoint+'/usr_fixture_rider/availability',{availability:'available',expected_availability:'offline'},'PATCH')).status,200);
    const available=(await(await admin(endpoint+'/usr_fixture_rider')).json()).data.personnel;assert.equal(available.base_availability,'available');assert.equal(available.availability,'busy');
    const create=await mutate(admin,endpoint,{name:'Chitra (sample rider)',phone:'+91 90000 00005',email:'chitra@example.test'});assert.equal(create.status,201);const created=(await create.json()).data;assert.equal(created.status,'inactive');
    const newRider=client();assert.equal((await login(newRider,'9000000005')).status,401);assert.equal((await mutate(admin,endpoint+'/'+created.uid+'/status',{status:'active',expected_status:'inactive'},'PATCH')).status,200);assert.equal((await login(newRider,'9000000005')).status,200);
    const identity=(await(await newRider('api/v1/auth/me')).json()).data.user;assert.equal(identity.role,'delivery');assert.equal(identity.phone,'9000000005');assert.equal((await newRider(endpoint)).status,403);assert.equal((await newRider('api/v1/delivery/orders')).status,200);
    assert.equal((await mutate(admin,endpoint,{name:'Duplicate must not convert customer',phone:'9000000003'})).status,422);
    await mutate(admin,'api/v1/auth/logout',{});await mutate(rider,'api/v1/auth/logout',{});await mutate(newRider,'api/v1/auth/logout',{});
    console.log('PASS: full HTTP provisioning, inactive-first creation, activation/OTP delivery role, revoked rider access, retained workload/history, derived availability, stale actions and duplicate account protection. Temporary SQL/storage only.');
    if(process.argv.includes('--preview')){console.log('Sample preview: http://127.0.0.1:8769/login (admin phone 9000000001).');await new Promise(resolve=>process.once('SIGINT',resolve));}
  }finally{
    if(server.exitCode===null){server.kill();await new Promise(resolve=>server.once('exit',resolve));}
    const resolved=path.resolve(directory);assert.ok(resolved.startsWith(path.resolve(os.tmpdir())+path.sep)&&path.basename(resolved).startsWith('sabjiwalah-riders-'));await fs.rm(resolved,{recursive:true,force:true});
  }
})().catch(error=>{console.error(error);process.exitCode=1;});
