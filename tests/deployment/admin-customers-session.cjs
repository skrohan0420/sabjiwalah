// Full HTTP session revocation tests with temporary users/orders and fixture state.
const assert=require('node:assert/strict'),fs=require('node:fs/promises'),os=require('node:os'),path=require('node:path'),{spawn}=require('node:child_process');
(async()=>{
  const root=path.resolve(__dirname,'../..').replaceAll('\\','/'),directory=await fs.mkdtemp(path.join(os.tmpdir(),'sabjiwalah-customers-'));
  for(const name of ['logs','cache','session','uploads'])await fs.mkdir(path.join(directory,'writable',name),{recursive:true});
  const router=`<?php
if(PHP_SAPI !== 'cli-server' || !in_array($_SERVER['REMOTE_ADDR'],['127.0.0.1','::1'],true))exit;
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if($path==='/admin'){header('Location: /admin/customers');exit;}
if(str_starts_with($path,'/assets/'))return false;
if(!preg_match('#^/(?:login|admin/customers|api/v1/(?:csrf|auth/(?:otp/start|otp/verify|logout|me)|checkout/summary|admin/customers(?:/[^/]+(?:/status)?)?))$#D',$path)){http_response_code(404);exit;}
define('ENVIRONMENT','development');define('FCPATH','${root}/public/');require '${root}/app/Config/Paths.php';
$paths=new Config\\Paths();$paths->writableDirectory=__DIR__.'/writable';require $paths->systemDirectory.'/Boot.php';$app=CodeIgniter\\Boot::bootWorker($paths);
config('Otp')->developmentMode=true;config('App')->baseURL='http://127.0.0.1:8768/';
$db=db_connect();if($db->DBDriver!=='MySQLi'||$db->DBPrefix!=='')throw new RuntimeException('Use local MySQL without prefix');
foreach(['users','orders','otp_rate_limits'] as $table){$ddl=$db->query('SHOW CREATE TABLE '.$table)->getRowArray()['Create Table'];$ddl=str_replace('CREATE TABLE','CREATE TEMPORARY TABLE',$ddl);$ddl=preg_replace('/^\\s*CONSTRAINT .* FOREIGN KEY .*\\n/m','',$ddl);$db->query(preg_replace('/,\\s*\\)/',')',$ddl));}
$stateFile=__DIR__.'/status.json';$state=is_file($stateFile)?json_decode(file_get_contents($stateFile),true):['status'=>'active'];
$db->table('users')->insertBatch([
['id'=>1,'uid'=>'usr_fixture_admin','name'=>'Fixture Admin','email'=>null,'phone'=>'9000000001','role'=>'admin','status'=>'active','created_at'=>'2026-10-01 09:00:00'],
['id'=>2,'uid'=>'usr_fixture_customer','name'=>'Asha (sample customer)','email'=>'sample@example.test','phone'=>'9000000003','role'=>'customer','status'=>$state['status'],'created_at'=>'2026-10-05 09:00:00'],
['id'=>3,'uid'=>'usr_fixture_second','name'=>'Bimal (sample customer)','email'=>null,'phone'=>'9000000004','role'=>'customer','status'=>'inactive','created_at'=>'2026-10-06 09:00:00']]);
for($i=1;$i<=12;$i++)$db->table('orders')->insert(['uid'=>'ord_fixture_'.$i,'order_number'=>'SW-SAMPLE-'.str_pad((string)$i,3,'0',STR_PAD_LEFT),'user_id'=>2,'subtotal'=>120,'total_amount'=>120,'payment_method'=>'cod','payment_status'=>$i<10?'paid':'pending','order_status'=>$i<10?'delivered':'pending','customer_name'=>'Asha','customer_phone'=>'9000000003','address_line'=>'Sample address','city'=>'Sample city','created_at'=>'2026-10-08 09:00:00']);
$app->run();
$state['status']=$db->table('users')->select('status')->where('id',2)->get()->getRowArray()['status'];file_put_contents($stateFile,json_encode($state));`;
  await fs.writeFile(path.join(directory,'router.php'),router);
  const server=spawn(process.env.PHP_BINARY||'D:/xampp/php/php.exe',['-S','127.0.0.1:8768','-t',root+'/public',path.join(directory,'router.php')],{cwd:root,windowsHide:true,stdio:['ignore','ignore','pipe']});
  let diagnostics='';server.stderr.on('data',chunk=>{diagnostics+=chunk;});
  function client(){const cookies=new Map();return async(url,options={})=>{const response=await fetch('http://127.0.0.1:8768/'+url,{...options,redirect:'manual',headers:{Accept:'application/json',...options.headers,Cookie:[...cookies].map(([k,v])=>k+'='+v).join('; ')}});for(const cookie of response.headers.getSetCookie()){const [pair]=cookie.split(';');const i=pair.indexOf('=');cookies.set(pair.slice(0,i),pair.slice(i+1));}return response;};}
  async function mutate(request,url,body,method='POST'){const response=await request('api/v1/csrf');const csrf=await response.json();assert.equal(response.status,200,csrf.message);assert.ok(csrf.data,csrf.message);return request(url,{method,headers:{'Content-Type':'application/json',[csrf.data.header_name]:csrf.data.token_value},body:JSON.stringify(body)});}
  async function login(request,phone){const start=await mutate(request,'api/v1/auth/otp/start',{phone});assert.equal(start.status,200);const otp=(await start.json()).data;return mutate(request,'api/v1/auth/otp/verify',{phone,otp:otp.dev_otp});}
  try{
    let ready=false;for(let i=0;i<40;i++){try{await fetch('http://127.0.0.1:8768/api/v1/csrf');ready=true;break;}catch{await new Promise(resolve=>setTimeout(resolve,100));}}assert.ok(ready,diagnostics);
    const admin=client(),customer=client();assert.equal((await login(admin,'9000000001')).status,200);assert.equal((await login(customer,'9000000003')).status,200);
    assert.equal((await customer('api/v1/auth/me')).status,200);
    const action='api/v1/admin/customers/usr_fixture_customer/status';
    assert.equal((await mutate(admin,action,{status:'inactive',expected_status:'active'},'PATCH')).status,200);
    assert.equal((await customer('api/v1/auth/me')).status,401,'Existing session must be revoked.');
    assert.equal((await customer('api/v1/checkout/summary')).status,401,'Inactive account must not checkout.');
    assert.equal((await login(customer,'9000000003')).status,401,'Inactive account must not log in.');
    const details=(await(await admin('api/v1/admin/customers/usr_fixture_customer?per_page=10')).json()).data;
    assert.equal(details.customer.status,'inactive');assert.equal(details.orders.pager.total,12);assert.equal(details.orders.items.length,10);
    assert.equal((await mutate(admin,action,{status:'active',expected_status:'active'},'PATCH')).status,409);
    assert.equal((await mutate(admin,action,{status:'active',expected_status:'inactive'},'PATCH')).status,200);
    assert.equal((await customer('api/v1/auth/me')).status,401,'Reactivation must not resurrect a revoked session.');
    assert.equal((await login(customer,'9000000003')).status,200);assert.equal((await customer('api/v1/auth/me')).status,200);
    await mutate(admin,'api/v1/auth/logout',{});await mutate(customer,'api/v1/auth/logout',{});
    console.log('PASS: real HTTP deactivation revokes existing sessions, blocks checkout/login, retains order history, rejects stale actions and requires fresh login after reactivation. Temporary SQL/storage only.');
    if(process.argv.includes('--preview')){console.log('Isolated sample preview: http://127.0.0.1:8768/login (admin phone 9000000001). Stop with Ctrl+C.');await new Promise(resolve=>process.once('SIGINT',resolve));}
  }finally{
    if(server.exitCode===null){server.kill();await new Promise(resolve=>server.once('exit',resolve));}
    const resolved=path.resolve(directory);assert.ok(resolved.startsWith(path.resolve(os.tmpdir())+path.sep)&&path.basename(resolved).startsWith('sabjiwalah-customers-'));await fs.rm(resolved,{recursive:true,force:true});
  }
})().catch(error=>{console.error(error);process.exitCode=1;});
