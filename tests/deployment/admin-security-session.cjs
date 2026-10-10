// Full route filters on disposable SQL/session storage; no production records are modified.
const assert=require('node:assert/strict'),fs=require('node:fs/promises'),os=require('node:os'),path=require('node:path'),net=require('node:net');
const {spawn}=require('node:child_process'),{randomUUID}=require('node:crypto');
(async()=>{
  const preview=process.argv.includes('--preview'),token=randomUUID(),root=path.resolve(__dirname,'../..').replaceAll('\\','/');
  const directory=await fs.mkdtemp(path.join(os.tmpdir(),'sabjiwalah-admin-security-'));
  const port=preview?8775:await new Promise((resolve,reject)=>{const s=net.createServer();s.on('error',reject);s.listen(0,'127.0.0.1',()=>{const p=s.address().port;s.close(()=>resolve(p));});});
  const base='http://127.0.0.1:'+port+'/';
  for(const name of ['logs','cache','session','uploads'])await fs.mkdir(path.join(directory,'writable',name),{recursive:true});
  await fs.writeFile(path.join(directory,'router.php'),`<?php
if(PHP_SAPI!=='cli-server'||!in_array($_SERVER['REMOTE_ADDR'],['127.0.0.1','::1'],true))exit;
header('X-Sabjiwalah-Fixture: ${token}');
if(str_starts_with(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH),'/assets/'))return false;
define('ENVIRONMENT','development');define('FCPATH','${root}/public/');require '${root}/app/Config/Paths.php';$paths=new Config\\Paths();$paths->writableDirectory=__DIR__.'/writable';require $paths->systemDirectory.'/Boot.php';$app=CodeIgniter\\Boot::bootWorker($paths);config('App')->baseURL='${base}';
$db=db_connect();if($db->DBDriver!=='MySQLi'||$db->DBPrefix!==''||!in_array($db->hostname,['localhost','127.0.0.1','::1'],true))throw new RuntimeException('Use local MySQL without prefix.');
// Every existing business table is shadowed, so unexpected endpoint writes remain disposable.
foreach($db->listTables() as$table){$ddl=$db->query('SHOW CREATE TABLE '.$db->protectIdentifiers($table))->getRowArray()['Create Table'];$ddl=str_replace('CREATE TABLE','CREATE TEMPORARY TABLE',$ddl);$ddl=preg_replace('/^\\s*CONSTRAINT .* FOREIGN KEY .*\\n/m','',$ddl);$db->query(preg_replace('/,\\s*\\)/',')',$ddl));}
foreach(['admin','customer','customer','delivery','delivery'] as$i=>$role)$db->table('users')->insert(['id'=>$i+1,'uid'=>'usr_security_'.($i+1),'name'=>'Sample Person '.($i+1),'phone'=>'900000000'.($i+1),'role'=>$role,'status'=>'active']);
$db->table('products')->insert(['id'=>1,'uid'=>'prd_security','name'=>'Sample product','slug'=>'sample-security','price'=>10,'unit'=>'kg','stock_quantity'=>1,'is_active'=>1]);
$db->table('orders')->insert(['id'=>1,'uid'=>'ord_security','order_number'=>'SW-SECURITY','user_id'=>2,'subtotal'=>10,'total_amount'=>50,'order_status'=>'out_for_delivery','customer_name'=>'Sample Person','customer_phone'=>'9000000002','address_line'=>'Sample','city'=>'Sample','postal_code'=>'700001','created_at'=>date('Y-m-d H:i:s'),'updated_at'=>date('Y-m-d H:i:s')]);
$db->table('delivery_assignments')->insert(['id'=>1,'uid'=>'das_security','order_id'=>1,'delivery_user_id'=>4,'assigned_by'=>1,'assigned_at'=>date('Y-m-d H:i:s')]);
$completed=$db->table('orders')->where('id',1)->get()->getRowArray();$completed['id']=2;$completed['uid']='ord_security_completed';$completed['order_number']='SW-SECURITY-COMPLETED';$completed['order_status']='delivered';$db->table('orders')->insert($completed);
$db->table('delivery_assignments')->insert(['id'=>2,'uid'=>'das_security_completed','order_id'=>2,'delivery_user_id'=>4,'assigned_by'=>1,'assigned_at'=>date('Y-m-d H:i:s')]);
$db->table('operational_settings')->insert(['id'=>1,'shop_open'=>1,'orders_paused'=>0,'delivery_charge'=>40,'free_delivery_minimum'=>499,'minimum_order_amount'=>0,'revision'=>1,'updated_at'=>date('Y-m-d H:i:s')]);
if(${preview?'true':'false'}){config('Otp')->developmentMode=true;session()->set('delivery_location',['latitude'=>22.5,'longitude'=>88.3]);}
if(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH)==='/_fixture/session'){
if(($_SERVER['HTTP_X_FIXTURE_SESSION']??'')!=='${token}'){http_response_code(404);exit;}$id=(int)file_get_contents('php://input');$user=$db->table('users')->where('id',$id)->get()->getRowArray();if(!$user){http_response_code(404);exit;}session()->set(['user_id'=>$id,'user_uid'=>$user['uid'],'user_name'=>$user['name'],'user_role'=>$user['role'],'is_logged_in'=>true,'delivery_location'=>['latitude'=>22.5,'longitude'=>88.3]]);echo '{}';exit;}
$before=hash('sha256',json_encode([$db->table('users')->get()->getResultArray(),$db->table('products')->get()->getResultArray(),$db->table('orders')->get()->getResultArray(),$db->table('delivery_assignments')->get()->getResultArray()]));
$app->run();$after=hash('sha256',json_encode([$db->table('users')->get()->getResultArray(),$db->table('products')->get()->getResultArray(),$db->table('orders')->get()->getResultArray(),$db->table('delivery_assignments')->get()->getResultArray()]));if($before!==$after)file_put_contents(__DIR__.'/unexpected-write','changed');`);
  const server=spawn(process.env.PHP_BINARY||'D:/xampp/php/php.exe',['-S','127.0.0.1:'+port,'-t',root+'/public',path.join(directory,'router.php')],{cwd:root,windowsHide:true,stdio:['ignore','ignore','pipe']});let diagnostics='';server.stderr.on('data',c=>diagnostics+=c);
  function client(){const cookies=new Map();return async(url,options={})=>{const r=await fetch(base+url,{...options,redirect:'manual',headers:{Accept:'application/json',...options.headers,Cookie:[...cookies].map(([k,v])=>k+'='+v).join('; ')}});assert.equal(r.headers.get('x-sabjiwalah-fixture'),token);for(const cookie of r.headers.getSetCookie()){const p=cookie.split(';')[0],i=p.indexOf('=');cookies.set(p.slice(0,i),p.slice(i+1));}return r;};}
  async function seed(request,id){assert.equal((await request('_fixture/session',{method:'POST',headers:{'X-Fixture-Session':token},body:String(id)})).status,200);}
  async function mutate(request,url,body={},method='POST'){const r=await request('api/v1/csrf');assert.equal(r.status,200);const csrf=(await r.json()).data;return request(url,{method,headers:{'Content-Type':'application/json',[csrf.header_name]:csrf.token_value},body:JSON.stringify(body)});}
  function privateResponse(r,requirePrivate=true){if(requirePrivate)assert.match(r.headers.get('cache-control'),/private/);assert.match(r.headers.get('cache-control'),/no-store/);assert.ok(!r.headers.get('cache-control').includes('public'));}
  try{
    let ready=false;for(let i=0;i<40;i++){try{const r=await fetch(base+'api/v1/csrf');if(r.headers.get('x-sabjiwalah-fixture')===token){ready=true;break;}}catch{}if(server.exitCode!==null)break;await new Promise(r=>setTimeout(r,100));}assert.ok(ready,diagnostics);
    if(!preview){
    const guest=client(),customer=client(),rider=client(),admin=client(),other=client();await seed(customer,2);await seed(rider,4);await seed(admin,1);await seed(other,3);
    const source=await fs.readFile(path.join(root,'app/Config/Routes.php'),'utf8');
    const group=source.split("$routes->group('admin', ['namespace' => 'App\\Controllers\\Api\\V1\\Admin'")[1].split("$routes->group('delivery'")[0];
    const routes=[...group.matchAll(/\$routes->(get|post|put|patch|delete)\('([^']+)'/g)].map(m=>[m[1].toUpperCase(),'api/v1/admin/'+m[2].replaceAll('(:segment)','fixture_unknown')]);assert.ok(routes.length>=30);
    let requests=0;
    for(const [request,status] of [[guest,401],[customer,403],[rider,403]])for(const [method,url] of routes){const r=method==='GET'?await request(url):await mutate(request,url,{},method);assert.equal(r.status,status,method+' '+url+' '+await r.clone().text());privateResponse(r);requests++;}
    for(const page of ['admin','admin/orders','admin/products','admin/customers','admin/delivery-personnel','admin/dispatch','admin/cash','admin/offers','admin/promotions','admin/settings']){
      const g=await guest(page);assert.equal(g.status,302);privateResponse(g);for(const r of [customer,rider]){const response=await r(page);assert.equal(response.status,302);assert.ok(!response.headers.get('location').includes('/admin'));privateResponse(response);}
    }
    for(const endpoint of ['api/v1/account/profile','api/v1/cart','account']){const r=await customer(endpoint);assert.equal(r.status,200,await r.clone().text());privateResponse(r);}
    const account=(await(await customer('account')).text()).replace(/&#x([0-9a-f]+);/gi,(_,hex)=>String.fromCodePoint(parseInt(hex,16)));assert.match(account,/action="[^"\s]*\/logout" method="post"/);assert.ok(!/href="[^"\s]*\/logout"/.test(account));
    assert.equal((await customer('logout')).status,404);assert.equal((await customer('api/v1/auth/me')).status,200,'GET logout changed session.');
    for(const who of [customer,admin]){let r=await who('logout',{method:'POST'});assert.equal(r.status,403);privateResponse(r,false);assert.equal((await who('api/v1/auth/me')).status,200);}
    for(const headers of [{},{'X-CSRF-TOKEN':'invalid'}]){const r=await admin('api/v1/admin/products',{method:'POST',headers:{'Content-Type':'application/json',...headers},body:'{}'});assert.equal(r.status,403);privateResponse(r);}
    const png=Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aF9sAAAAASUVORK5CYII=','base64');
    for(const who of [guest,customer,rider]){const csrf=(await(await who('api/v1/csrf')).json()).data,body=new FormData();body.append('image',new Blob([png],{type:'image/png'}),'sample.png');const r=await who('api/v1/admin/products/prd_security/image',{method:'POST',headers:{[csrf.header_name]:csrf.token_value},body});assert.equal(r.status,who===guest?401:403);privateResponse(r);}
    assert.equal((await mutate(other,'api/v1/orders/ord_security/delivery-pin')).status,404,'Another customer accessed the delivery PIN.');
    const otherRider=client();await seed(otherRider,5);assert.equal((await otherRider('api/v1/delivery/orders/ord_security')).status,404);
    assert.equal((await mutate(otherRider,'api/v1/delivery/orders/ord_security/status',{status:'delivery_failed',notes:'Injected'},'PATCH')).status,404);
    assert.equal((await mutate(otherRider,'api/v1/delivery/orders/ord_security/complete',{pin:'123456',cash_received:true})).status,404);
    assert.equal((await rider('api/v1/delivery/orders/ord_security')).status,200);
    const riderList=await rider('api/v1/delivery/orders');assert.equal(riderList.status,200);assert.deepEqual((await riderList.json()).data.items.map(row=>row.uid),['ord_security'],'Rider workload exposed historical customer contacts.');
    assert.equal((await rider('api/v1/delivery/orders/ord_security_completed')).status,200,'Latest-owner historical detail compatibility changed.');
    for(const method of ['POST','PUT','DELETE'])assert.equal((await mutate(admin,'api/v1/admin/dashboard',{},method)).status,404,'An unsupported method reached a controller.');
    const csrf=(await(await customer('api/v1/csrf')).json()).data;const logout=await customer('logout',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({[csrf.token_name]:csrf.token_value})});assert.equal(logout.status,303,await logout.clone().text());privateResponse(logout);assert.equal((await customer('api/v1/auth/me')).status,401);
    assert.ok(!(await fs.readdir(directory)).includes('unexpected-write'),'A rejected request changed fixture business data.');assert.deepEqual(await fs.readdir(path.join(directory,'writable','uploads')),[]);
    console.log('PASS: '+routes.length+' admin API routes / '+requests+' guest/customer/rider denials, all admin pages, private success/error responses, POST-only logout with CSRF, genuine multipart role protection, customer/rider IDOR, unsupported methods. Disposable SQL/storage only.');
    }else{console.log('Preview: '+base+'login (sample customer 9000000002). Send stop to close.');await new Promise(resolve=>{process.once('SIGINT',resolve);process.stdin.once('data',resolve);});process.stdin.pause();}
  }finally{if(server.exitCode===null){server.kill();await new Promise(r=>server.once('exit',r));}const resolved=path.resolve(directory);assert.ok(resolved.startsWith(path.resolve(os.tmpdir())+path.sep)&&path.basename(resolved).startsWith('sabjiwalah-admin-security-'));await fs.rm(resolved,{recursive:true,force:true});}
})().catch(e=>{console.error(e);process.exitCode=1;});
