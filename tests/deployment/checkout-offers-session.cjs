// Full HTTP checkout on temporary SQL tables and disposable sessions; persistent stock/orders are untouched.
const assert=require('node:assert/strict'), fs=require('node:fs/promises'), os=require('node:os'), path=require('node:path'), net=require('node:net');
const {randomUUID}=require('node:crypto'), {spawn}=require('node:child_process');
(async()=>{
  const preview=process.argv.includes('--preview'), token=randomUUID();
  const port=preview?8773:await new Promise((resolve,reject)=>{const s=net.createServer();s.on('error',reject);s.listen(0,'127.0.0.1',()=>{const p=s.address().port;s.close(()=>resolve(p));});});
  const base='http://127.0.0.1:'+port+'/',root=path.resolve(__dirname,'../..').replaceAll('\\','/'),directory=await fs.mkdtemp(path.join(os.tmpdir(),'sabjiwalah-checkout-offers-'));
  for(const name of ['logs','cache','session','uploads'])await fs.mkdir(path.join(directory,'writable',name),{recursive:true});
  const router=`<?php
if(PHP_SAPI!=='cli-server'||!in_array($_SERVER['REMOTE_ADDR'],['127.0.0.1','::1'],true))exit;
header('X-Sabjiwalah-Fixture: ${token}');$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if($path==='/account'){header('Location: /checkout');exit;}if(str_starts_with($path,'/assets/'))return false;
if(!preg_match('#^/(?:|login|checkout|orders|admin/(?:offers|promotions|settings)|api/v1/(?:csrf|auth/(?:otp/start|otp/verify|logout|me)|products(?:/[^/]+)?|cart(?:/items(?:/[^/]+)?)?|checkout/(?:summary|offer|place|otp/(?:start|verify))|admin/(?:offers|promotions|settings)(?:/[^/]+)?))$#D',$path)){http_response_code(404);exit;}
define('ENVIRONMENT','development');define('FCPATH','${root}/public/');require '${root}/app/Config/Paths.php';$paths=new Config\\Paths();$paths->writableDirectory=__DIR__.'/writable';require $paths->systemDirectory.'/Boot.php';$app=CodeIgniter\\Boot::bootWorker($paths);config('Otp')->developmentMode=true;config('App')->baseURL='${base}';
$db=db_connect();if($db->DBDriver!=='MySQLi'||$db->DBPrefix!=='')throw new RuntimeException('Use local MySQL without prefix.');
$tables=['users','products','offers','promotions','orders','order_items','order_status_history','offer_redemptions','operational_settings','operational_setting_history','otp_rate_limits'];
foreach($tables as $table){$ddl=$db->query('SHOW CREATE TABLE '.$table)->getRowArray()['Create Table'];$ddl=str_replace('CREATE TABLE','CREATE TEMPORARY TABLE',$ddl);$ddl=preg_replace('/^\\s*CONSTRAINT .* FOREIGN KEY .*\\n/m','',$ddl);$db->query(preg_replace('/,\\s*\\)/',')',$ddl));}
$stateFile=__DIR__.'/state.json';if(is_file($stateFile)){$state=json_decode(file_get_contents($stateFile),true);foreach($tables as $table)if($state[$table])$db->table($table)->insertBatch($state[$table]);}
else{
$db->table('operational_settings')->insert(['id'=>1,'shop_open'=>1,'orders_paused'=>0,'delivery_charge'=>40,'free_delivery_minimum'=>499,'minimum_order_amount'=>0,'revision'=>1,'updated_at'=>date('Y-m-d H:i:s')]);
$db->table('users')->insertBatch([
['id'=>1,'uid'=>'usr_sample_customer','name'=>'Sample shopper','phone'=>'9000000003','role'=>'customer','status'=>'active'],
['id'=>2,'uid'=>'usr_sample_second','name'=>'Another shopper','phone'=>'9000000004','role'=>'customer','status'=>'active'],
['id'=>3,'uid'=>'usr_sample_admin','name'=>'Sample administrator','phone'=>'9000000001','role'=>'admin','status'=>'active'],
['id'=>4,'uid'=>'usr_sample_delivery','name'=>'Sample rider','phone'=>'9000000002','role'=>'delivery','status'=>'active']]);
$db->table('products')->insert(['id'=>1,'uid'=>'prd_sample','name'=>'Sample tomatoes','slug'=>'sample-tomatoes','price'=>100,'sale_price'=>80,'unit'=>'kg','stock_quantity'=>20,'is_active'=>1]);
$db->table('offers')->insert(['id'=>1,'uid'=>'off_sample','name'=>'Fresh basket savings','code'=>'FRESH10','type'=>'percentage','value'=>10,'minimum_order_amount'=>200,'maximum_discount'=>20,'usage_limit'=>1,'is_active'=>1]);
$db->table('promotions')->insertBatch([
['id'=>1,'uid'=>'pro_sample','title'=>'Fresh basket savings — use FRESH10 at checkout','image'=>'/assets/images/sabjiwalah-wordmark-header.png','link'=>'/checkout','position'=>'home','is_active'=>1,'starts_at'=>null],
['id'=>2,'uid'=>'pro_future','title'=>'FUTURE MUST NOT SHOW','image'=>null,'link'=>'/products','position'=>'home','is_active'=>1,'starts_at'=>'2099-01-01 00:00:00'],
['id'=>3,'uid'=>'pro_unsafe','title'=>'UNSAFE MUST NOT SHOW','image'=>null,'link'=>'javascript:alert(1)','position'=>'home','is_active'=>1,'starts_at'=>null]]);
}
$app->run();$state=[];foreach($tables as $table)$state[$table]=$db->table($table)->get()->getResultArray();file_put_contents($stateFile,json_encode($state));`;
  await fs.writeFile(path.join(directory,'router.php'),router);
  const server=spawn(process.env.PHP_BINARY||'D:/xampp/php/php.exe',['-S','127.0.0.1:'+port,'-t',root+'/public',path.join(directory,'router.php')],{cwd:root,windowsHide:true,stdio:['ignore','ignore','pipe']});let diagnostics='';server.stderr.on('data',c=>diagnostics+=c);
  function client(){const cookies=new Map();return async(url,options={})=>{const r=await fetch(base+url,{...options,redirect:'manual',headers:{Accept:'application/json',...options.headers,Cookie:[...cookies].map(([k,v])=>k+'='+v).join('; ')}});assert.equal(r.headers.get('x-sabjiwalah-fixture'),token);for(const cookie of r.headers.getSetCookie()){const p=cookie.split(';')[0],i=p.indexOf('=');cookies.set(p.slice(0,i),p.slice(i+1));}return r;};}
  async function mutate(request,url,body,method='POST'){const response=await request('api/v1/csrf');assert.equal(response.status,200,diagnostics);const csrf=(await response.json()).data;return request(url,{method,headers:{'Content-Type':'application/json',[csrf.header_name]:csrf.token_value},body:JSON.stringify(body)});}
  // These are checkout regressions, so age only fixture resend cooldowns between independent logins.
  async function login(request,phone){const file=path.join(directory,'state.json');const state=JSON.parse(await fs.readFile(file,'utf8'));for(const bucket of state.otp_rate_limits)bucket.last_used_at=Number(bucket.last_used_at)-60;await fs.writeFile(file,JSON.stringify(state));const start=await mutate(request,'api/v1/auth/otp/start',{phone});assert.equal(start.status,200,await start.clone().text());const otp=(await start.json()).data;assert.equal(otp.user_exists,true);assert.equal((await mutate(request,'api/v1/auth/otp/verify',{phone,otp:otp.dev_otp})).status,200);}
  async function verify(request,phone){const otp=(await(await mutate(request,'api/v1/checkout/otp/start',{customer_phone:phone})).json()).data;assert.equal((await mutate(request,'api/v1/checkout/otp/verify',{customer_phone:phone,otp:otp.dev_otp})).status,200);}
  async function summary(request){const r=await request('api/v1/checkout/summary');assert.equal(r.status,200);assert.match(r.headers.get('cache-control'),/no-store/);return(await r.json()).data.checkout;}
  try{
    let ready=false;for(let i=0;i<40;i++){try{if((await fetch(base+'api/v1/csrf')).headers.get('x-sabjiwalah-fixture')===token){ready=true;break;}}catch{}if(server.exitCode!==null)break;await new Promise(r=>setTimeout(r,100));}assert.ok(ready,diagnostics);
    const guest=client();assert.equal((await mutate(guest,'api/v1/checkout/offer',{code:'FRESH10'})).status,401);
    for(const phone of ['9000000001','9000000002']){const denied=client();await login(denied,phone);assert.equal((await mutate(denied,'api/v1/checkout/offer',{code:'FRESH10'})).status,403);assert.equal((await mutate(denied,'api/v1/checkout/offer',{},'DELETE')).status,403);await mutate(denied,'api/v1/auth/logout',{});}
    const one=client(),two=client();await login(one,'9000000003');await login(two,'9000000004');
    for(const request of [one,two])assert.equal((await mutate(request,'api/v1/cart/items',{product_uid:'prd_sample',quantity:3})).status,201);
    assert.equal((await one('api/v1/checkout/offer',{method:'POST',headers:{'Content-Type':'application/json'},body:'{"code":"FRESH10"}'})).status,403);
    for(const body of [{code:[]},{code:'FRESH10',discount_amount:200},{code:'MISSING'}])assert.equal((await mutate(one,'api/v1/checkout/offer',body)).status,422);
    assert.equal((await mutate(one,'api/v1/checkout/offer',{code:'fresh10'})).status,200);
    let quote=await summary(one);assert.equal(quote.discount_amount,20);assert.equal(quote.total_amount,260);assert.equal(quote.applied_code,'FRESH10');assert.ok(!('offer'in quote));
    assert.equal((await mutate(one,'api/v1/cart/items/prd_sample',{quantity:1},'PATCH')).status,200);quote=await summary(one);assert.equal(quote.quote_token,null);assert.ok(quote.offer_error);
    assert.equal((await mutate(one,'api/v1/checkout/offer',{},'DELETE')).status,200);quote=await summary(one);assert.equal(quote.discount_amount,0);assert.ok(quote.quote_token);
    assert.equal((await mutate(one,'api/v1/cart/items/prd_sample',{quantity:3},'PATCH')).status,200);
    for(const request of [one,two])assert.equal((await mutate(request,'api/v1/checkout/offer',{code:'FRESH10'})).status,200);
    const first=await summary(one),second=await summary(two);await verify(one,'9000000003');await verify(two,'9000000004');
    const body={customer_name:'Sample shopper',customer_phone:'9000000003',address_line:'Sample address',city:'Sample city',postal_code:'700001',quote_token:first.quote_token};
    assert.equal((await mutate(one,'api/v1/checkout/place',{...body,total_amount:1})).status,422);
    assert.equal((await mutate(one,'api/v1/checkout/place',{...body,quote_token:'0'.repeat(64)})).status,409);
    const responses=await Promise.all([mutate(one,'api/v1/checkout/place',body),mutate(two,'api/v1/checkout/place',{...body,customer_phone:'9000000004',quote_token:second.quote_token})]);assert.deepEqual(responses.map(r=>r.status).sort(),[201,400]);
    const order=(await responses.find(r=>r.status===201).json()).data.order;assert.equal(order.discount_amount,20);assert.equal(order.total_amount,260);
    const state=JSON.parse(await fs.readFile(path.join(directory,'state.json'),'utf8'));assert.equal(state.orders.length,1);assert.equal(state.offer_redemptions.length,1);assert.equal(Number(state.products[0].stock_quantity),17);
    const winner=responses[0].status===201?one:two;assert.equal((await summary(winner)).selected_code,null);
    const html=await(await guest('')).text();assert.match(html,/Store highlights/);assert.match(html,/Fresh basket savings/);assert.ok(!html.includes('FUTURE MUST NOT SHOW')&&!html.includes('UNSAFE MUST NOT SHOW'));
    const checkoutPage=await one('checkout');assert.equal(checkoutPage.status,200);assert.match(await checkoutPage.text(),/data-coupon-form/);
    await mutate(one,'api/v1/auth/logout',{});await login(one,'9000000003');assert.equal((await summary(one)).selected_code,null);
    // Operational controls: real admin HTTP writes, public quotes and order rejection.
    assert.equal((await guest('api/v1/admin/settings')).status,401);
    assert.equal((await one('api/v1/admin/settings')).status,403);
    assert.equal((await mutate(one,'api/v1/admin/settings',{},'PUT')).status,403);
    const staff=client();await login(staff,'9000000001');
    const readSettings=async()=>{const r=await staff('api/v1/admin/settings');assert.equal(r.status,200);assert.match(r.headers.get('cache-control'),/no-store/);return(await r.json()).data;};
    let settings=(await readSettings()).settings;
    const input=changes=>{const {revision,updated_at,...values}=settings;return{...values,expected_revision:revision,...changes};};
    const save=async changes=>{const r=await mutate(staff,'api/v1/admin/settings',input(changes),'PUT');assert.equal(r.status,200,await r.clone().text());settings=(await r.json()).data.settings;};
    assert.equal((await staff('api/v1/admin/settings',{method:'PUT',headers:{'Content-Type':'application/json'},body:JSON.stringify(input({}))})).status,403);
    for(const change of [{delivery_charge:[]},{maximum_active_orders:0},{opens_at:'09:00'},{shop_open:2},{secret:1}])assert.equal((await mutate(staff,'api/v1/admin/settings',input(change),'PUT')).status,422);
    await save({});assert.equal((await readSettings()).history.length,0);
    const stale=input({});await save({delivery_charge:'30',free_delivery_minimum:'',minimum_order_amount:'250',maximum_active_orders:'1'});
    assert.equal((await mutate(staff,'api/v1/admin/settings',stale,'PUT')).status,409);
    const saved=await readSettings();assert.equal(saved.history.length,1);assert.equal(saved.history[0].old_values.delivery_charge,'40.00');assert.ok(!('changed_by'in saved.history[0]));
    const buyer=client();await login(buyer,'9000000003');await mutate(buyer,'api/v1/cart/items',{product_uid:'prd_sample',quantity:4});await verify(buyer,'9000000003');
    const blocked=await summary(buyer);assert.equal(blocked.can_place_order,false);assert.match(blocked.checkout_notice,/capacity/);assert.equal(blocked.total_amount,350);
    assert.equal((await mutate(buyer,'api/v1/checkout/place',{...body,quote_token:blocked.quote_token})).status,409);
    await save({maximum_active_orders:'',shop_open:'0'});assert.match((await summary(buyer)).checkout_notice,/closed/);
    assert.equal((await mutate(buyer,'api/v1/checkout/place',body)).status,409);
    await save({shop_open:'1',orders_paused:'1'});assert.match((await summary(buyer)).checkout_notice,/paused/);
    await save({orders_paused:'0'});assert.equal((await summary(buyer)).can_place_order,true);
    await mutate(buyer,'api/v1/cart/items/prd_sample',{quantity:3},'PATCH');assert.equal((await summary(buyer)).can_place_order,false);
    const cartPayload=(await(await guest('api/v1/cart')).json()).data.cart;assert.deepEqual(cartPayload.checkout_rules,{delivery_charge:30,free_delivery_minimum:null});
    const guestCheckout=await(await guest('checkout')).text();assert.match(guestCheckout,/data-delivery-charge="30"/);assert.match(guestCheckout,/data-free-delivery-minimum=""/);
    const settingsHtml=await(await staff('admin/settings')).text();assert.match(settingsHtml,/settings-form/);assert.match(settingsHtml,/Asia\/Kolkata/);
    const finalState=JSON.parse(await fs.readFile(path.join(directory,'state.json'),'utf8'));assert.equal(finalState.orders.length,1);assert.equal(Number(finalState.orders[0].delivery_charge),40);assert.equal(Number(finalState.products[0].stock_quantity),17);
    console.log('PASS: HTTP coupons and operational settings: role/CSRF/input checks, stale writes/quotes, audited saves, configured fees, closure/pause/minimum/capacity rejection, preserved order prices, checkout OTP and stock/redemption consistency. Temporary SQL/storage; single-worker HTTP fixture.');
    if(preview){await fs.unlink(path.join(directory,'state.json'));console.log('Preview: '+base+'login (customer phone 9000000003). Send stop to close.');await new Promise(resolve=>{process.once('SIGINT',resolve);process.stdin.once('data',resolve);});process.stdin.pause();}
  }finally{if(server.exitCode===null){server.kill();await new Promise(resolve=>server.once('exit',resolve));}const resolved=path.resolve(directory);assert.ok(resolved.startsWith(path.resolve(os.tmpdir())+path.sep)&&path.basename(resolved).startsWith('sabjiwalah-checkout-offers-'));await fs.rm(resolved,{recursive:true,force:true});}
})().catch(e=>{console.error(e);process.exitCode=1;});
