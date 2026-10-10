// Real HTTP authentication on disposable sessions and connection-local temporary SQL.
const assert=require('node:assert/strict'),fs=require('node:fs/promises'),os=require('node:os'),path=require('node:path'),net=require('node:net');
const {spawn}=require('node:child_process'),{randomUUID}=require('node:crypto');
(async()=>{
  const production=process.argv.includes('--production'),preview=process.argv.includes('--preview'),token=randomUUID();
  const port=preview?8774:await new Promise((resolve,reject)=>{const s=net.createServer();s.on('error',reject);s.listen(0,'127.0.0.1',()=>{const p=s.address().port;s.close(()=>resolve(p));});});
  const base='http://127.0.0.1:'+port+'/',root=path.resolve(__dirname,'../..').replaceAll('\\','/'),directory=await fs.mkdtemp(path.join(os.tmpdir(),'sabjiwalah-otp-security-'));
  for(const name of ['logs','session','cache','uploads'])await fs.mkdir(path.join(directory,'writable',name),{recursive:true});
  const router=`<?php
if(PHP_SAPI!=='cli-server'||!in_array($_SERVER['REMOTE_ADDR'],['127.0.0.1','::1'],true))exit;
header('X-Sabjiwalah-Fixture: ${token}');$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if($path==='/admin'){header('Location: /admin/settings');exit;}if(str_starts_with($path,'/assets/'))return false;
if(!preg_match('#^/(?:_fixture/session|login|admin/settings|api/v1/(?:csrf|auth/(?:otp/start|otp/verify|login|register|logout|me)|checkout/(?:otp/start|otp/verify|place)|admin/settings))$#D',$path)){http_response_code(404);exit;}
define('ENVIRONMENT','${production?'production':'development'}');define('FCPATH','${root}/public/');require '${root}/app/Config/Paths.php';$paths=new Config\\Paths();$paths->writableDirectory=__DIR__.'/writable';require $paths->systemDirectory.'/Boot.php';$app=CodeIgniter\\Boot::bootWorker($paths);config('App')->baseURL='${base}';config('Otp')->developmentMode=true;
// Production normally selects the hosted DB and HTTPS. This loopback fixture uses the local DB/HTTP;
// it verifies production OTP behavior, not TLS transport. Application deployment settings are untouched.
if(ENVIRONMENT==='production'){if(!in_array(config('Database')->local['hostname'],['localhost','127.0.0.1','::1'],true))throw new RuntimeException('A local fixture database is required.');config('Database')->default=config('Database')->local;config('App')->forceGlobalSecureRequests=false;$_SERVER['HTTPS']='on';}
$db=db_connect();if($db->DBDriver!=='MySQLi'||$db->DBPrefix!=='')throw new RuntimeException('Use local MySQL without prefix.');
$tables=['users','otp_rate_limits','operational_settings','operational_setting_history'];
foreach($tables as$table){$ddl=$db->query('SHOW CREATE TABLE '.$table)->getRowArray()['Create Table'];$ddl=str_replace('CREATE TABLE','CREATE TEMPORARY TABLE',$ddl);$ddl=preg_replace('/^\\s*CONSTRAINT .* FOREIGN KEY .*\\n/m','',$ddl);$db->query(preg_replace('/,\\s*\\)/',')',$ddl));}
$file=__DIR__.'/state.json';if(is_file($file)){$state=json_decode(file_get_contents($file),true);foreach($tables as$table)if($state[$table])$db->table($table)->insertBatch($state[$table]);}
else{$db->table('users')->insertBatch([
['id'=>1,'uid'=>'usr_sample_admin','name'=>'Sample administrator','phone'=>'9000000001','role'=>'admin','status'=>'active'],
['id'=>2,'uid'=>'usr_sample_customer','name'=>'Sample shopper','phone'=>'9000000003','role'=>'customer','status'=>'active'],
['id'=>3,'uid'=>'usr_sample_inactive','name'=>'Inactive administrator','phone'=>'9000000002','role'=>'admin','status'=>'inactive']]);
$db->table('operational_settings')->insert(['id'=>1,'shop_open'=>1,'orders_paused'=>0,'delivery_charge'=>40,'free_delivery_minimum'=>499,'minimum_order_amount'=>0,'revision'=>1,'updated_at'=>date('Y-m-d H:i:s')]);}
if($path==='/_fixture/session'){
if(($_SERVER['HTTP_X_FIXTURE_SESSION']??'')!=='${token}'){http_response_code(404);exit;}
$proof=json_decode(file_get_contents('php://input'),true);service('session')->set(['user_id'=>1,'user_uid'=>'usr_sample_admin','user_role'=>'admin','is_logged_in'=>true]+$proof);header('Content-Type: application/json');echo json_encode(['seeded'=>true]);exit;
}
$app->run();$state=[];foreach($tables as$table)$state[$table]=$db->table($table)->get()->getResultArray();file_put_contents($file,json_encode($state));`;
  await fs.writeFile(path.join(directory,'router.php'),router);
  const server=spawn(process.env.PHP_BINARY||'D:/xampp/php/php.exe',['-S','127.0.0.1:'+port,'-t',root+'/public',path.join(directory,'router.php')],{cwd:root,windowsHide:true,stdio:['ignore','ignore','pipe']});let diagnostics='';server.stderr.on('data',c=>diagnostics+=c);
  function client(){const cookies=new Map();const request=async(url,options={})=>{const r=await fetch(base+url,{...options,redirect:'manual',headers:{Accept:'application/json',...options.headers,Cookie:[...cookies].map(([k,v])=>k+'='+v).join('; ')}});assert.equal(r.headers.get('x-sabjiwalah-fixture'),token);for(const cookie of r.headers.getSetCookie()){const pair=cookie.split(';')[0],i=pair.indexOf('=');cookies.set(pair.slice(0,i),pair.slice(i+1));}return r;};request.cookies=cookies;return request;}
  async function mutate(request,url,body){const r=await request('api/v1/csrf');assert.equal(r.status,200,diagnostics);assert.match(r.headers.get('cache-control'),/no-store/);const token=(await r.json()).data;return request(url,{method:'POST',headers:{'Content-Type':'application/json',[token.header_name]:token.token_value},body:JSON.stringify(body)});}
  async function issue(request,phone){const r=await mutate(request,'api/v1/auth/otp/start',{phone});assert.equal(r.status,200,await r.clone().text());assert.match(r.headers.get('cache-control'),/no-store/);return(await r.json()).data.dev_otp;}
  async function ageSendCooldowns(){const file=path.join(directory,'state.json'),state=JSON.parse(await fs.readFile(file,'utf8'));for(const row of state.otp_rate_limits)row.last_used_at=Number(row.last_used_at)-60;await fs.writeFile(file,JSON.stringify(state));}
  try{
    let ready=false;for(let i=0;i<40;i++){try{if((await fetch(base+'api/v1/csrf',{redirect:'manual'})).headers.get('x-sabjiwalah-fixture')===token){ready=true;break;}}catch{}if(server.exitCode!==null)break;await new Promise(r=>setTimeout(r,100));}assert.ok(ready,diagnostics);
    const guest=client();const unauthorized=await guest('api/v1/admin/settings');
    if(unauthorized.status>=500){for(const name of await fs.readdir(path.join(directory,'writable','logs'))){const log=await fs.readFile(path.join(directory,'writable','logs',name),'utf8');console.error(log.split('\n').filter(line=>/^(CRITICAL|ERROR)/.test(line)).map(line=>line.slice(0,300)).join('\n'));}}
    assert.equal(unauthorized.status,401);
    assert.equal((await mutate(guest,'api/v1/checkout/otp/start',{customer_phone:'9000000003'})).status,401);
    for(const body of [{phone:[]},{phone:'9000000001',role:'admin'},{phone:'12'},{phone:'abc9000000001'}])assert.equal((await mutate(guest,'api/v1/auth/otp/start',body)).status,422);
    assert.equal((await guest('api/v1/auth/otp/start',{method:'POST',headers:{'Content-Type':'application/json'},body:'{"phone":"9000000001"}'})).status,403);
    if(production){
      const cookieResponse=await client()('api/v1/csrf');
      assert.ok(cookieResponse.headers.getSetCookie().some(cookie=>cookie.startsWith('csrf_cookie_name=')&&/;\s*Secure/i.test(cookie)&&/;\s*HttpOnly/i.test(cookie)&&/;\s*SameSite=Lax/i.test(cookie)), 'Production CSRF cookie attributes are unsafe.');
      for(const endpoint of ['auth/otp/start','auth/otp/verify','auth/login','auth/register']){
        const r=await mutate(guest,'api/v1/'+endpoint,{phone:'9000000001',...(endpoint==='auth/otp/start'?{}:{otp:'123456'})});assert.equal(r.status,503);assert.match(r.headers.get('cache-control'),/no-store/);assert.ok(!JSON.stringify(await r.json()).includes('dev_otp'));
      }
      assert.equal((await guest('api/v1/auth/me')).status,401);
      for(const proof of [{},{auth_proof_version:1,auth_method:'local_development'}]){
        const old=client(),seeded=await old('_fixture/session',{method:'POST',headers:{'Content-Type':'application/json','X-Fixture-Session':token},body:JSON.stringify(proof)});assert.equal(seeded.status,200);
        assert.ok(seeded.headers.getSetCookie().some(cookie=>cookie.startsWith('ci_session=')&&/;\s*Secure/i.test(cookie)&&/;\s*HttpOnly/i.test(cookie)&&/;\s*SameSite=Lax/i.test(cookie)), 'Production session cookies must be Secure/HttpOnly/SameSite.');
        assert.equal((await old('api/v1/admin/settings')).status,401,'A legacy/testing session retained production access.');
        assert.equal((await old('api/v1/auth/me')).status,401);
      }
      // Simulate an already authenticated server session to test scheduled rotation, independent of SMS.
      const rotating=client();assert.equal((await rotating('_fixture/session',{method:'POST',headers:{'Content-Type':'application/json','X-Fixture-Session':token},body:JSON.stringify({auth_proof_version:1,auth_method:'password',__ci_last_regenerate:Math.floor(Date.now()/1000)-301})})).status,200);
      const priorId=rotating.cookies.get('ci_session');assert.equal((await rotating('api/v1/admin/settings')).status,200);assert.notEqual(rotating.cookies.get('ci_session'),priorId,'Periodic regeneration did not rotate the cookie.');
      const replay=client();replay.cookies.set('ci_session',priorId);assert.equal((await replay('api/v1/admin/settings')).status,401,'A destroyed periodic session ID retained admin access.');
      console.log('PASS: production HTTP fails closed for all login/register aliases; no testing codes, no session privilege, CSRF/private responses. Disposable SQL/storage.');
    }else{
      for(const headers of [{'X-Forwarded-For':'203.0.113.10'},{Forwarded:'for=203.0.113.10'},{'X-Real-IP':'203.0.113.10'},{'X-Forwarded-Proto':'https'},{'X-Forwarded-Host':'public-preview.example'}]){
        const csrf=(await(await guest('api/v1/csrf')).json()).data;
        const denied=await guest('api/v1/auth/otp/start',{method:'POST',headers:{'Content-Type':'application/json',[csrf.header_name]:csrf.token_value,...headers},body:JSON.stringify({phone:'9000000001'})});
        assert.equal(denied.status,503,'A proxy/public host obtained a testing OTP: '+Object.keys(headers).join(','));assert.ok(!JSON.stringify(await denied.json()).includes('dev_otp'));
      }
      // Fetch normalizes Host; the raw HTTP client sends the actual public Host header under test.
      const hostCsrf=(await(await guest('api/v1/csrf')).json()).data;
      const hostDenied=await new Promise((resolve,reject)=>{const body=JSON.stringify({phone:'9000000001'});const req=require('node:http').request({hostname:'127.0.0.1',port,path:'/api/v1/auth/otp/start',method:'POST',headers:{Host:'public-preview.example','Content-Type':'application/json','Content-Length':Buffer.byteLength(body),[hostCsrf.header_name]:hostCsrf.token_value,Cookie:[...guest.cookies].map(([k,v])=>k+'='+v).join('; ')}},res=>{let text='';res.on('data',c=>text+=c);res.on('end',()=>resolve({status:res.statusCode,fixture:res.headers['x-sabjiwalah-fixture'],body:text}));});req.on('error',reject);req.end(body);});
      assert.equal(hostDenied.fixture,token);assert.equal(hostDenied.status,503,'Public Host obtained a testing OTP.');assert.ok(!hostDenied.body.includes('dev_otp'));
      const code=await issue(guest,'9000000001');const oldSession=guest.cookies.get('ci_session');
      let r=await mutate(guest,'api/v1/auth/otp/start',{phone:'9000000001'});assert.equal(r.status,429);assert.ok(Number(r.headers.get('retry-after'))>0);assert.match(r.headers.get('cache-control'),/no-store/);
      const stranger=client();r=await mutate(stranger,'api/v1/auth/otp/start',{phone:'9000000001'});assert.equal(r.status,429,'A new session bypassed the resend limit.');
      assert.equal((await mutate(stranger,'api/v1/auth/otp/verify',{phone:'9000000001',otp:code})).status,401,'A different session replayed a code.');
      assert.equal((await mutate(guest,'api/v1/auth/otp/verify',{phone:'9000000001',otp:code})).status,200);assert.notEqual(guest.cookies.get('ci_session'),oldSession);
      assert.equal((await guest('api/v1/admin/settings')).status,200);assert.match((await guest('api/v1/auth/me')).headers.get('cache-control'),/no-store/);
      assert.equal((await mutate(guest,'api/v1/auth/login',{phone:'9000000001',otp:code})).status,401);await mutate(guest,'api/v1/auth/logout',{});assert.equal((await guest('api/v1/admin/settings')).status,401);
      const shopper=client(),shopCode=await issue(shopper,'9000000003');assert.equal((await mutate(shopper,'api/v1/auth/otp/verify',{phone:'9000000003',otp:shopCode})).status,200);assert.equal((await shopper('api/v1/admin/settings')).status,403);
      let checkoutResponse=await mutate(shopper,'api/v1/checkout/otp/start',{customer_phone:'9000000003'});assert.equal(checkoutResponse.status,200);const checkoutCode=(await checkoutResponse.json()).data.dev_otp;
      assert.equal((await mutate(shopper,'api/v1/checkout/otp/start',{customer_phone:'9000000003'})).status,429);
      assert.equal((await mutate(shopper,'api/v1/checkout/otp/verify',{customer_phone:'9000000003',otp:checkoutCode})).status,200);
      assert.equal((await mutate(shopper,'api/v1/checkout/otp/verify',{customer_phone:'9000000003',otp:checkoutCode})).status,400);
      const inactive=client(),inactiveCode=await issue(inactive,'9000000002');assert.equal((await mutate(inactive,'api/v1/auth/otp/verify',{phone:'9000000002',otp:inactiveCode})).status,401);
      const target='9000000004',guesser=client(),targetCode=await issue(guesser,target),bad=targetCode==='111111'?'222222':'111111';
      for(let i=0;i<5;i++)assert.equal((await mutate(guesser,'api/v1/auth/otp/verify',{phone:target,otp:bad})).status,401);
      r=await mutate(client(),'api/v1/auth/otp/verify',{phone:target,otp:targetCode});assert.equal(r.status,429);assert.ok(Number(r.headers.get('retry-after'))>0);
      await ageSendCooldowns();const newCode=await issue(guesser,target);assert.equal((await mutate(guesser,'api/v1/auth/otp/verify',{phone:target,otp:newCode})).status,429,'A resend reset the verification budget.');
      for(const redirect of ['//evil.test','/%2f/evil.test','/%5cevil.test','/\\evil.test','/%0aevil']){const html=await(await guest('login?redirect='+encodeURIComponent(redirect))).text();assert.ok(!html.includes('value="'+redirect+'"'),'Unsafe redirect retained.');}
      const state=JSON.parse(await fs.readFile(path.join(directory,'state.json'),'utf8'));assert.equal(state.users.length,3);assert.ok(state.otp_rate_limits.every(row=>/^[a-f0-9]{64}$/.test(row.bucket_key)));assert.equal(state.operational_setting_history.length,0);
      console.log('PASS: HTTP OTP local login/checkout, role/CSRF/input checks, rotated sessions, replay guards, inactive admin denial, resend and guess limits across sessions/aliases/resends, private responses and redirect validation. Temporary SQL/storage; single-worker fixture.');
    }
    if(preview){await fs.unlink(path.join(directory,'state.json'));console.log('Preview: '+base+'login (sample admin 9000000001). Send stop to close.');await new Promise(resolve=>{process.once('SIGINT',resolve);process.stdin.once('data',resolve);});process.stdin.pause();}
  }finally{if(server.exitCode===null){server.kill();await new Promise(resolve=>server.once('exit',resolve));}const resolved=path.resolve(directory);assert.ok(resolved.startsWith(path.resolve(os.tmpdir())+path.sep)&&path.basename(resolved).startsWith('sabjiwalah-otp-security-'));await fs.rm(resolved,{recursive:true,force:true});}
})().catch(e=>{console.error(e);process.exitCode=1;});
