// Isolated HTTP fixture: every request uses connection-local temporary tables.
const assert=require('node:assert/strict'),fs=require('node:fs/promises'),os=require('node:os'),path=require('node:path'),net=require('node:net'),{randomUUID}=require('node:crypto'),{spawn}=require('node:child_process');
(async()=>{
  const preview=process.argv.includes('--preview'),token=randomUUID();
  const port=preview?8771:await new Promise((resolve,reject)=>{const probe=net.createServer();probe.on('error',reject);probe.listen(0,'127.0.0.1',()=>{const port=probe.address().port;probe.close(()=>resolve(port));});});
  const base='http://127.0.0.1:'+port+'/',root=path.resolve(__dirname,'../..').replaceAll('\\','/'),directory=await fs.mkdtemp(path.join(os.tmpdir(),'sabjiwalah-pin-'));
  for(const name of ['logs','cache','session','uploads'])await fs.mkdir(path.join(directory,'writable',name),{recursive:true});
  const router=`<?php
if(PHP_SAPI!=='cli-server'||!in_array($_SERVER['REMOTE_ADDR'],['127.0.0.1','::1'],true))exit;
header('X-Sabjiwalah-Fixture: ${token}');$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if(str_starts_with($path,'/assets/'))return false;
if($path==='/admin'||$path==='/admin/'){header('Location: /admin/cash');exit;}
if($path==='/account'){header('Location: /orders');exit;}
if(!preg_match('#^/(?:login|orders|delivery|admin/cash|api/v1/(?:csrf|auth/(?:otp/start|otp/verify|logout|me)|orders/[^/]+/delivery-pin|delivery/orders(?:/[^/]+(?:/complete|/status)?)?|admin/(?:cash(?:/[^/]+/reconcile)?|orders/[^/]+(?:/status)?)|cart))$#D',$path)){http_response_code(404);exit;}
define('ENVIRONMENT','development');define('FCPATH','${root}/public/');require '${root}/app/Config/Paths.php';$paths=new Config\\Paths();$paths->writableDirectory=__DIR__.'/writable';require $paths->systemDirectory.'/Boot.php';$app=CodeIgniter\\Boot::bootWorker($paths);config('Otp')->developmentMode=true;config('App')->baseURL='${base}';
$db=db_connect();if($db->DBDriver!=='MySQLi'||$db->DBPrefix!=='')throw new RuntimeException('Use local MySQL without prefix');
foreach(['users','orders','order_items','order_delivery_pins','delivery_assignments','order_status_history','delivery_completions','otp_rate_limits'] as $table){$ddl=$db->query('SHOW CREATE TABLE '.$table)->getRowArray()['Create Table'];$ddl=str_replace('CREATE TABLE','CREATE TEMPORARY TABLE',$ddl);$ddl=preg_replace('/^\\s*CONSTRAINT .* FOREIGN KEY .*\\n/m','',$ddl);$db->query(preg_replace('/,\\s*\\)/',')',$ddl));}
foreach(['customer','customer','delivery','admin'] as $i=>$role)$db->table('users')->insert(['id'=>$i+1,'uid'=>'usr_pin_fixture_'.$i,'name'=>'Sample Person '.$i,'phone'=>'900000001'.$i,'role'=>$role,'status'=>'active','created_at'=>'2026-10-01 09:00:00']);
foreach(['out_for_delivery','pending'] as $i=>$status)$db->table('orders')->insert(['id'=>$i+1,'uid'=>'ord_pin_fixture_'.$i,'order_number'=>'SW-PIN-SAMPLE-'.($i+1),'user_id'=>1,'subtotal'=>120,'total_amount'=>120,'payment_method'=>'cod','payment_status'=>'pending','order_status'=>$status,'customer_name'=>'Sample Person','customer_phone'=>'9000000010','address_line'=>'Sample address','city'=>'Sample city','created_at'=>date('Y-m-d H:i:s'),'updated_at'=>date('Y-m-d H:i:s')]);
$stateFile=__DIR__.'/pins.json';if(is_file($stateFile)){$rows=json_decode(file_get_contents($stateFile),true);if($rows)$db->table('order_delivery_pins')->insertBatch($rows);}
$deliveryState=__DIR__.'/delivery.json';$deliveryTables=['orders','delivery_assignments','order_status_history','delivery_completions'];
if(is_file($deliveryState)){$state=json_decode(file_get_contents($deliveryState),true);foreach($deliveryTables as $table){$db->table($table)->emptyTable();if($state[$table])$db->table($table)->insertBatch($state[$table]);}}
else{$db->table('delivery_assignments')->insert(['id'=>1,'uid'=>'das_pin_fixture','order_id'=>1,'delivery_user_id'=>3,'assigned_by'=>4,'assigned_at'=>date('Y-m-d H:i:s')]);}
$app->run();file_put_contents($stateFile,json_encode($db->table('order_delivery_pins')->get()->getResultArray()));$state=[];foreach($deliveryTables as $table)$state[$table]=$db->table($table)->get()->getResultArray();file_put_contents($deliveryState,json_encode($state));`;
  await fs.writeFile(path.join(directory,'router.php'),router);
  const server=spawn(process.env.PHP_BINARY||'D:/xampp/php/php.exe',['-S','127.0.0.1:'+port,'-t',root+'/public',path.join(directory,'router.php')],{cwd:root,windowsHide:true,stdio:['ignore','ignore','pipe']});let diagnostics='';server.stderr.on('data',chunk=>diagnostics+=chunk);
  function client(){const cookies=new Map();return async(url,options={})=>{const response=await fetch(base+url,{...options,redirect:'manual',headers:{Accept:'application/json',...options.headers,Cookie:[...cookies].map(([k,v])=>k+'='+v).join('; ')}});assert.equal(response.headers.get('x-sabjiwalah-fixture'),token,'Use the owned fixture only.');for(const cookie of response.headers.getSetCookie()){const pair=cookie.split(';')[0],i=pair.indexOf('=');cookies.set(pair.slice(0,i),pair.slice(i+1));}return response;};}
  async function mutate(request,url,body={},method='POST'){const csrf=(await(await request('api/v1/csrf')).json()).data;return request(url,{method,headers:{'Content-Type':'application/json',[csrf.header_name]:csrf.token_value},body:JSON.stringify(body)});}
  async function login(request,phone){const otp=(await(await mutate(request,'api/v1/auth/otp/start',{phone})).json()).data;assert.equal(otp.user_exists,true);assert.equal((await mutate(request,'api/v1/auth/otp/verify',{phone,otp:otp.dev_otp})).status,200);}
  try{
    let ready=false;for(let i=0;i<40;i++){try{const response=await fetch(base+'api/v1/csrf');if(response.headers.get('x-sabjiwalah-fixture')===token){ready=true;break;}}catch{}if(server.exitCode!==null)break;await new Promise(resolve=>setTimeout(resolve,100));}assert.ok(ready,diagnostics);
    const endpoint='api/v1/orders/ord_pin_fixture_0/delivery-pin',guest=client(),owner=client(),secondSession=client(),other=client(),rider=client(),admin=client();
    assert.equal((await mutate(guest,endpoint)).status,401);
    for(const [request,phone] of [[owner,'9000000010'],[secondSession,'9000000010'],[other,'9000000011'],[rider,'9000000012'],[admin,'9000000013']])await login(request,phone);
    assert.equal((await mutate(other,endpoint)).status,404);assert.equal((await mutate(rider,endpoint)).status,403);assert.equal((await mutate(admin,endpoint)).status,403);
    assert.equal((await owner(endpoint,{method:'POST',headers:{'Content-Type':'application/json'},body:'{}'})).status,403);
    assert.equal((await mutate(owner,endpoint,{pin:'123456'})).status,422);assert.equal((await mutate(owner,'api/v1/orders/ord_pin_fixture_1/delivery-pin')).status,409);
    const competing=await Promise.all([mutate(owner,endpoint),mutate(secondSession,endpoint)]);assert.deepEqual(competing.map(response=>response.status).sort(),[200,429]);
    const success=competing.find(response=>response.status===200);assert.match(success.headers.get('cache-control'),/no-store/);const data=(await success.json()).data;assert.match(data.pin,/^\d{6}$/);
    const stored=JSON.parse(await fs.readFile(path.join(directory,'pins.json'),'utf8'));assert.equal(stored.length,1);assert.ok(!JSON.stringify(stored).includes(data.pin));
    const page=await owner('orders'),html=await page.text();assert.equal(page.status,200);assert.match(page.headers.get('cache-control'),/no-store/);assert.match(html,/data-pin-generate/);assert.ok(!html.includes(data.pin));
    const completion='api/v1/delivery/orders/ord_pin_fixture_0/complete',cash='api/v1/admin/cash';
    assert.equal((await mutate(owner,completion,{pin:data.pin,cash_received:true})).status,403);
    assert.equal((await mutate(rider,completion,{pin:data.pin,cash_received:false})).status,422);
    assert.equal((await mutate(rider,completion,{pin:data.pin,cash_received:true,amount:1})).status,422);
    assert.equal((await mutate(admin,'api/v1/admin/orders/ord_pin_fixture_0/status',{status:'delivered'},'PATCH')).status,400);
    assert.equal((await mutate(rider,'api/v1/delivery/orders/ord_pin_fixture_0/status',{status:'delivery_failed',notes:'   '},'PATCH')).status,400);
    assert.equal((await mutate(rider,completion,{pin:data.pin==='000000'?'000001':'000000',cash_received:true})).status,422);
    assert.equal((await rider(completion,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({pin:data.pin,cash_received:true})})).status,403);
    const riderOrder=await rider('api/v1/delivery/orders/ord_pin_fixture_0');assert.equal(riderOrder.status,200);assert.ok(!JSON.stringify(await riderOrder.json()).includes(data.pin));
    const completed=await Promise.all([mutate(rider,completion,{pin:data.pin,cash_received:true}),mutate(rider,completion,{pin:data.pin,cash_received:true})]);assert.deepEqual(completed.map(response=>response.status),[200,200]);
    assert.equal((await rider(cash)).status,403);assert.equal((await owner(cash)).status,403);
    const collections=await admin(cash);assert.equal(collections.status,200);const ledger=(await collections.json()).data;assert.equal(ledger.items.length,1);assert.equal(ledger.items[0].cash_amount,'120.00');assert.ok(!('pin_hash' in ledger.items[0]));
    const reconcile=cash+'/ord_pin_fixture_0/reconcile';assert.equal((await mutate(admin,reconcile,{amount:1})).status,422);
    assert.equal((await mutate(admin,reconcile)).status,200);assert.equal((await mutate(admin,reconcile)).status,200);
    const details=(await(await admin('api/v1/admin/orders/ord_pin_fixture_0')).json()).data;assert.equal(details.history.length,2);assert.equal(details.order.payment_status,'paid');
    assert.equal((await admin('admin/cash')).status,200);assert.equal((await rider('delivery')).status,200);
    for(const request of [owner,secondSession,other,rider,admin])await mutate(request,'api/v1/auth/logout');
    console.log('PASS: HTTP PIN issuance/privacy, completion and cash role/CSRF/input restrictions, admin completion bypass rejection, failure reasons, wrong PIN, duplicate completion/reconciliation, ledger and payment/history. Temporary SQL/storage; PHP server serializes HTTP requests.');
    if(preview){await fs.unlink(path.join(directory,'pins.json'));await fs.unlink(path.join(directory,'delivery.json'));console.log('Sample preview: '+base+'login (customer 9000000010, rider 9000000012, admin 9000000013). Send stop on stdin or Ctrl+C to close.');await new Promise(resolve=>{process.once('SIGINT',resolve);process.stdin.once('data',resolve);});process.stdin.pause();}
  }finally{
    if(server.exitCode===null){server.kill();await new Promise(resolve=>server.once('exit',resolve));}
    const resolved=path.resolve(directory);assert.ok(resolved.startsWith(path.resolve(os.tmpdir())+path.sep)&&path.basename(resolved).startsWith('sabjiwalah-pin-'));await fs.rm(resolved,{recursive:true,force:true});
  }
})().catch(error=>{console.error(error);process.exitCode=1;});
