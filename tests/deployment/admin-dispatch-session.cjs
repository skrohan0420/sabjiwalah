// HTTP dispatch workflow on connection-local temporary tables and disposable writable storage.
const assert=require('node:assert/strict'),fs=require('node:fs/promises'),os=require('node:os'),path=require('node:path'),net=require('node:net'),{randomUUID}=require('node:crypto'),{spawn}=require('node:child_process');
(async()=>{
  const preview=process.argv.includes('--preview'),fixtureToken=randomUUID();
  const port=preview?8770:await new Promise((resolve,reject)=>{const probe=net.createServer();probe.on('error',reject);probe.listen(0,'127.0.0.1',()=>{const port=probe.address().port;probe.close(()=>resolve(port));});});
  const base='http://127.0.0.1:'+port+'/',root=path.resolve(__dirname,'../..').replaceAll('\\','/'),directory=await fs.mkdtemp(path.join(os.tmpdir(),'sabjiwalah-dispatch-'));
  for(const name of ['logs','cache','session','uploads'])await fs.mkdir(path.join(directory,'writable',name),{recursive:true});
  const router=`<?php
if(PHP_SAPI!=='cli-server'||!in_array($_SERVER['REMOTE_ADDR'],['127.0.0.1','::1'],true))exit;
header('X-Sabjiwalah-Fixture: ${fixtureToken}');$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if($path==='/admin'){header('Location: /admin/dispatch');exit;}if(str_starts_with($path,'/assets/'))return false;
if(!preg_match('#^/(?:login|delivery|admin/(?:dispatch|orders|delivery-personnel)|api/v1/(?:csrf|auth/(?:otp/start|otp/verify|logout|me)|delivery/orders(?:/[^/]+(?:/status)?)?|admin/(?:dispatch(?:/candidates)?|orders(?:/[^/]+(?:/assignment|/status)?)?|delivery-personnel(?:/[^/]+(?:/status|/availability)?)?)))$#D',$path)){http_response_code(404);exit;}
define('ENVIRONMENT','development');define('FCPATH','${root}/public/');require '${root}/app/Config/Paths.php';$paths=new Config\\Paths();$paths->writableDirectory=__DIR__.'/writable';require $paths->systemDirectory.'/Boot.php';$app=CodeIgniter\\Boot::bootWorker($paths);config('Otp')->developmentMode=true;config('App')->baseURL='${base}';
$db=db_connect();if($db->DBDriver!=='MySQLi'||$db->DBPrefix!=='')throw new RuntimeException('Use local MySQL without prefix');
$tables=['users','orders','delivery_assignments','delivery_personnel_profiles','order_status_history','order_items','otp_rate_limits'];
foreach($tables as $table){$ddl=$db->query('SHOW CREATE TABLE '.$table)->getRowArray()['Create Table'];$ddl=str_replace('CREATE TABLE','CREATE TEMPORARY TABLE',$ddl);$ddl=preg_replace('/^\\s*CONSTRAINT .* FOREIGN KEY .*\\n/m','',$ddl);$db->query(preg_replace('/,\\s*\\)/',')',$ddl));}
$stateFile=__DIR__.'/state.json';
if(is_file($stateFile)){$state=json_decode(file_get_contents($stateFile),true);foreach($tables as $table)if($state[$table])$db->table($table)->insertBatch($state[$table]);}
else{
$db->table('users')->insertBatch([
['id'=>1,'uid'=>'usr_fixture_admin','name'=>'Fixture Admin','phone'=>'9000000001','role'=>'admin','status'=>'active','created_at'=>'2026-10-01 09:00:00'],
['id'=>2,'uid'=>'usr_fixture_rider_one','name'=>'Asha (sample rider)','phone'=>'9000000002','role'=>'delivery','status'=>'active','created_at'=>'2026-10-05 09:00:00'],
['id'=>3,'uid'=>'usr_fixture_rider_two','name'=>'Bimal (sample rider)','phone'=>'9000000004','role'=>'delivery','status'=>'active','created_at'=>'2026-10-05 09:00:00'],
['id'=>4,'uid'=>'usr_fixture_customer','name'=>'Sample Customer','phone'=>'9000000003','role'=>'customer','status'=>'active','created_at'=>'2026-10-05 09:00:00']]);
foreach([2,3] as $id)$db->table('delivery_personnel_profiles')->insert(['user_id'=>$id,'availability'=>'available','updated_at'=>'2026-10-05 09:00:00']);
for($i=1;$i<=3;$i++)$db->table('orders')->insert(['id'=>$i,'uid'=>'ord_fixture_'.$i,'order_number'=>'SW-SAMPLE-'.str_pad((string)$i,3,'0',STR_PAD_LEFT),'user_id'=>4,'subtotal'=>120,'total_amount'=>120,'payment_method'=>'cod','payment_status'=>'pending','order_status'=>'ready_for_delivery','customer_name'=>'Sample Customer','customer_phone'=>'9000000003','address_line'=>'Sample address','city'=>'Sample city','created_at'=>date('Y-m-d H:i:s',time()-1800),'updated_at'=>date('Y-m-d H:i:s',time()-1800)]);
}
$app->run();$state=[];foreach($tables as $table)$state[$table]=$db->table($table)->get()->getResultArray();file_put_contents($stateFile,json_encode($state));`;
  await fs.writeFile(path.join(directory,'router.php'),router);
  const server=spawn(process.env.PHP_BINARY||'D:/xampp/php/php.exe',['-S','127.0.0.1:'+port,'-t',root+'/public',path.join(directory,'router.php')],{cwd:root,windowsHide:true,stdio:['ignore','ignore','pipe']});let diagnostics='';server.stderr.on('data',chunk=>diagnostics+=chunk);
  function client(){const cookies=new Map();return async(url,options={})=>{const response=await fetch(base+url,{...options,redirect:'manual',headers:{Accept:'application/json',...options.headers,Cookie:[...cookies].map(([k,v])=>k+'='+v).join('; ')}});assert.equal(response.headers.get('x-sabjiwalah-fixture'),fixtureToken,'Use the owned fixture only.');for(const cookie of response.headers.getSetCookie()){const pair=cookie.split(';')[0],i=pair.indexOf('=');cookies.set(pair.slice(0,i),pair.slice(i+1));}return response;};}
  async function mutate(request,url,body,method='POST'){const response=await request('api/v1/csrf');assert.equal(response.status,200);const csrf=(await response.json()).data;return request(url,{method,headers:{'Content-Type':'application/json',[csrf.header_name]:csrf.token_value},body:JSON.stringify(body)});}
  // Age only disposable resend timestamps; dispatch conflict tests need two admin sessions.
  async function login(request,phone){const file=path.join(directory,'state.json'),state=JSON.parse(await fs.readFile(file,'utf8'));for(const bucket of state.otp_rate_limits)bucket.last_used_at=Number(bucket.last_used_at)-60;await fs.writeFile(file,JSON.stringify(state));const otp=(await(await mutate(request,'api/v1/auth/otp/start',{phone})).json()).data;assert.equal(otp.user_exists,true);assert.equal((await mutate(request,'api/v1/auth/otp/verify',{phone,otp:otp.dev_otp})).status,200);}
  try{
    let ready=false;for(let i=0;i<40;i++){try{const response=await fetch(base+'api/v1/csrf');if(response.headers.get('x-sabjiwalah-fixture')===fixtureToken){ready=true;break;}}catch{}if(server.exitCode!==null)break;await new Promise(resolve=>setTimeout(resolve,100));}assert.ok(ready,diagnostics);
    const admin=client(),otherAdmin=client(),one=client(),two=client();await login(admin,'9000000001');await login(otherAdmin,'9000000001');await login(one,'9000000002');await login(two,'9000000004');
    const action='api/v1/admin/orders/ord_fixture_1/assignment';
    const competing=await Promise.all([mutate(admin,action,{rider_uid:'usr_fixture_rider_one',expected_assignment_uid:'none'}),mutate(otherAdmin,action,{rider_uid:'usr_fixture_rider_two',expected_assignment_uid:'none'})]);assert.deepEqual(competing.map(response=>response.status).sort(),[200,409]);
    const first=(await competing.find(response=>response.status===200).json()).data;const firstOne=first.rider_uid==='usr_fixture_rider_one',former=firstOne?one:two,current=firstOne?two:one,currentUid=firstOne?'usr_fixture_rider_two':'usr_fixture_rider_one';
    const reassign=await mutate(admin,action,{rider_uid:currentUid,expected_assignment_uid:first.assignment_uid});assert.equal(reassign.status,200);const latest=(await reassign.json()).data;
    assert.equal((await former('api/v1/delivery/orders/ord_fixture_1')).status,404);assert.equal((await mutate(former,'api/v1/delivery/orders/ord_fixture_1/status',{status:'out_for_delivery'},'PATCH')).status,404);
    assert.ok(!(await(await former('api/v1/delivery/orders')).json()).data.items.some(order=>order.uid==='ord_fixture_1'));
    const history=(await(await admin('api/v1/admin/orders/ord_fixture_1')).json()).data;assert.equal(history.assignments.length,2);assert.equal(history.assignments[0].uid,latest.assignment_uid);
    assert.equal((await mutate(admin,'api/v1/admin/orders/ord_fixture_2/assignment',{rider_uid:currentUid,expected_assignment_uid:'none'})).status,409);
    assert.equal((await mutate(current,'api/v1/delivery/orders/ord_fixture_1/status',{status:'out_for_delivery',expected_status:'ready_for_delivery'},'PATCH')).status,200);
    assert.equal((await mutate(admin,action,{rider_uid:first.rider_uid,expected_assignment_uid:latest.assignment_uid})).status,409);
    assert.equal((await mutate(current,'api/v1/delivery/orders/ord_fixture_1/status',{status:'delivery_failed',expected_status:'out_for_delivery',notes:'Sample failed delivery'},'PATCH')).status,200);
    const progressed=(await(await admin('api/v1/admin/orders/ord_fixture_1')).json()).data;assert.equal(progressed.order.order_status,'delivery_failed');assert.equal(progressed.history.length,2);
    assert.equal((await mutate(admin,action,{rider_uid:first.rider_uid,expected_assignment_uid:latest.assignment_uid})).status,409);
    const remaining=(await(await admin('api/v1/admin/dispatch')).json()).data;assert.equal(remaining.pager.total,2);assert.ok(remaining.items.every(order=>order.delayed));
    assert.equal((await mutate(admin,'api/v1/admin/orders/ord_fixture_2/assignment',{rider_uid:first.rider_uid,expected_assignment_uid:'none'})).status,200);
    assert.equal((await mutate(admin,'api/v1/admin/delivery-personnel/'+first.rider_uid+'/status',{status:'inactive',expected_status:'active'},'PATCH')).status,200);
    assert.equal((await mutate(former,'api/v1/delivery/orders/ord_fixture_2/status',{status:'out_for_delivery'},'PATCH')).status,401);
    for(const request of [admin,otherAdmin,current])await mutate(request,'api/v1/auth/logout',{});
    console.log('PASS: HTTP competing/stale assignment requests, capacity conflicts, reassignment history and former-rider revocation, pickup/failed-delivery progress, in-transit/terminal rejection and inactive session denial. Temporary SQL/storage; PHP fixture serializes HTTP requests.');
    if(preview){await fs.unlink(path.join(directory,'state.json'));console.log('Sample preview: '+base+'login (admin phone 9000000001). Send stop on stdin or Ctrl+C to close.');await new Promise(resolve=>{process.once('SIGINT',resolve);process.stdin.once('data',resolve);});}
  }finally{
    if(server.exitCode===null){server.kill();await new Promise(resolve=>server.once('exit',resolve));}
    const resolved=path.resolve(directory);assert.ok(resolved.startsWith(path.resolve(os.tmpdir())+path.sep)&&path.basename(resolved).startsWith('sabjiwalah-dispatch-'));await fs.rm(resolved,{recursive:true,force:true});
  }
})().catch(error=>{console.error(error);process.exitCode=1;});
