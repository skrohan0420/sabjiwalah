// Complete HTTP workflow on temporary SQL tables and isolated session/storage files.
const assert = require('node:assert/strict'), fs = require('node:fs/promises'), os = require('node:os'), path = require('node:path');
const net = require('node:net'), {randomUUID} = require('node:crypto'), {spawn} = require('node:child_process');
(async () => {
  const preview = process.argv.includes('--preview'), token = randomUUID();
  const port = preview ? 8772 : await new Promise((resolve, reject) => {
    const probe = net.createServer(); probe.on('error', reject); probe.listen(0, '127.0.0.1', () => { const port = probe.address().port; probe.close(() => resolve(port)); });
  });
  const base = 'http://127.0.0.1:' + port + '/', root = path.resolve(__dirname, '../..').replaceAll('\\', '/');
  const directory = await fs.mkdtemp(path.join(os.tmpdir(), 'sabjiwalah-campaigns-'));
  for (const name of ['logs', 'cache', 'session', 'uploads']) await fs.mkdir(path.join(directory, 'writable', name), {recursive: true});
  const router = `<?php
if(PHP_SAPI!=='cli-server'||!in_array($_SERVER['REMOTE_ADDR'],['127.0.0.1','::1'],true))exit;
header('X-Sabjiwalah-Fixture: ${token}');$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if($path==='/admin'||$path==='/admin/'){header('Location: /admin/offers');exit;}if(str_starts_with($path,'/assets/'))return false;
if(!preg_match('#^/(?:login|admin/(?:offers|promotions)|api/v1/(?:csrf|auth/(?:otp/start|otp/verify|logout|me)|admin/(?:offers|promotions)(?:/[^/]+)?))$#D',$path)){http_response_code(404);exit;}
define('ENVIRONMENT','development');define('FCPATH','${root}/public/');require '${root}/app/Config/Paths.php';$paths=new Config\\Paths();$paths->writableDirectory=__DIR__.'/writable';require $paths->systemDirectory.'/Boot.php';$app=CodeIgniter\\Boot::bootWorker($paths);config('Otp')->developmentMode=true;config('App')->baseURL='${base}';
$db=db_connect();if($db->DBDriver!=='MySQLi'||$db->DBPrefix!=='')throw new RuntimeException('Use local MySQL without prefix');
$tables=['users','offers','promotions','otp_rate_limits'];
foreach($tables as $table){$ddl=$db->query('SHOW CREATE TABLE '.$table)->getRowArray()['Create Table'];$ddl=str_replace('CREATE TABLE','CREATE TEMPORARY TABLE',$ddl);$db->query($ddl);}
$stateFile=__DIR__.'/state.json';
if(is_file($stateFile)){$state=json_decode(file_get_contents($stateFile),true);foreach($tables as $table)if($state[$table])$db->table($table)->insertBatch($state[$table]);}
else{$db->table('users')->insertBatch([
['id'=>1,'uid'=>'usr_sample_admin','name'=>'Sample administrator','phone'=>'9000000001','role'=>'admin','status'=>'active'],
['id'=>2,'uid'=>'usr_sample_customer','name'=>'Sample customer','phone'=>'9000000003','role'=>'customer','status'=>'active'],
['id'=>3,'uid'=>'usr_sample_rider','name'=>'Sample rider','phone'=>'9000000002','role'=>'delivery','status'=>'active']]);}
$app->run();$state=[];foreach($tables as $table)$state[$table]=$db->table($table)->get()->getResultArray();file_put_contents($stateFile,json_encode($state));`;
  await fs.writeFile(path.join(directory, 'router.php'), router);
  const server = spawn(process.env.PHP_BINARY || 'D:/xampp/php/php.exe', ['-S', '127.0.0.1:' + port, '-t', root + '/public', path.join(directory, 'router.php')], {cwd: root, windowsHide: true, stdio: ['ignore', 'ignore', 'pipe']});
  let diagnostics = ''; server.stderr.on('data', chunk => diagnostics += chunk);
  function client() {
    const cookies = new Map();
    return async (url, options = {}) => {
      const response = await fetch(base + url, {...options, redirect: 'manual', headers: {Accept: 'application/json', ...options.headers, Cookie: [...cookies].map(([key, value]) => key + '=' + value).join('; ')}});
      assert.equal(response.headers.get('x-sabjiwalah-fixture'), token, 'Use owned fixture only.');
      for (const cookie of response.headers.getSetCookie()) { const pair = cookie.split(';')[0], i = pair.indexOf('='); cookies.set(pair.slice(0, i), pair.slice(i + 1)); }
      return response;
    };
  }
  async function mutate(request, url, body, method = 'POST') {
    const csrf = (await (await request('api/v1/csrf')).json()).data;
    return request(url, {method, headers: {'Content-Type': 'application/json', [csrf.header_name]: csrf.token_value}, body: JSON.stringify(body)});
  }
  async function login(request, phone) {
    // Age only disposable resend timestamps; campaign conflict tests need two admin sessions.
    const file=path.join(directory,'state.json'),state=JSON.parse(await fs.readFile(file,'utf8'));
    for(const bucket of state.otp_rate_limits)bucket.last_used_at=Number(bucket.last_used_at)-60;
    await fs.writeFile(file,JSON.stringify(state));
    const otp = (await (await mutate(request, 'api/v1/auth/otp/start', {phone})).json()).data;
    assert.equal(otp.user_exists, true);
    assert.equal((await mutate(request, 'api/v1/auth/otp/verify', {phone, otp: otp.dev_otp})).status, 200);
  }
  try {
    let ready = false;
    for (let i = 0; i < 40; i++) {
      try { if ((await fetch(base + 'api/v1/csrf')).headers.get('x-sabjiwalah-fixture') === token) { ready = true; break; } } catch {}
      if (server.exitCode !== null) break; await new Promise(resolve => setTimeout(resolve, 100));
    }
    assert.ok(ready, diagnostics);
    const guest = client(), admin = client(), second = client();
    for (const kind of ['offers', 'promotions']) {
      assert.equal((await guest('api/v1/admin/' + kind)).status, 401);
      assert.equal((await guest('admin/' + kind)).status, 302);
    }
    for (const phone of ['9000000002', '9000000003']) {
      const request = client(); await login(request, phone);
      for (const kind of ['offers', 'promotions']) {
        assert.equal((await request('api/v1/admin/' + kind)).status, 403);
        assert.equal((await mutate(request, 'api/v1/admin/' + kind, {})).status, 403);
        assert.equal((await mutate(request, 'api/v1/admin/' + kind + '/off_missing', {}, 'PUT')).status, 403);
      }
      await mutate(request, 'api/v1/auth/logout', {});
    }
    await login(admin, '9000000001'); await login(second, '9000000001');
    const offer = {name: 'Fresh basket savings', code: 'fresh10', type: 'percentage', value: '10', minimum_order_amount: '200', maximum_discount: '80', usage_limit: '50', starts_at: null, ends_at: null, is_active: 0};
    assert.equal((await admin('api/v1/admin/offers', {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(offer)})).status, 403);
    let response = await mutate(admin, 'api/v1/admin/offers', offer); assert.equal(response.status, 201);
    const created = (await response.json()).data;
    assert.ok(!('id' in created)); assert.equal(created.code, 'FRESH10');
    assert.equal((await mutate(admin, 'api/v1/admin/offers', offer)).status, 422);
    const editing = {...offer, name: 'Updated fresh basket savings', is_active: 1, expected_revision: created.revision};
    const results = await Promise.all([mutate(admin, 'api/v1/admin/offers/' + created.uid, editing, 'PUT'), mutate(second, 'api/v1/admin/offers/' + created.uid, {...editing, name: 'Second editor'}, 'PUT')]);
    assert.deepEqual(results.map(response => response.status).sort(), [200, 409]);
    let current = (await (await admin('api/v1/admin/offers/' + created.uid)).json()).data;
    response = await mutate(admin, 'api/v1/admin/offers/' + created.uid, {...offer, expected_revision: current.revision}, 'PUT'); assert.equal(response.status, 200);
    current = (await response.json()).data; assert.equal(current.status, 'disabled');
    for (const change of [{value: '101'}, {code: 'unsafe code'}, {starts_at: 'invalid'}, {uid: 'off_injected'}, {value: []}])
      assert.equal((await mutate(admin, 'api/v1/admin/offers', {...offer, code: 'OTHER', ...change})).status, 422);
    const promotion = {title: 'Fresh picks this week', image: '/assets/images/sabjiwalah-wordmark-header.png', link: '/products', position: 'home', starts_at: '2099-01-01T00:00:00+05:30', ends_at: '2099-01-08T00:00:00+05:30', is_active: 1};
    assert.equal((await mutate(admin, 'api/v1/admin/promotions', {...promotion, link: 'javascript:alert(1)'})).status, 422);
    response = await mutate(admin, 'api/v1/admin/promotions', promotion); assert.equal(response.status, 201);
    const promo = (await response.json()).data; assert.equal(promo.status, 'scheduled');
    assert.equal((await mutate(admin, 'api/v1/admin/promotions/' + promo.uid, {...promotion, is_active: 0, expected_revision: promo.revision}, 'PUT')).status, 200);
    assert.equal((await admin('api/v1/admin/promotions/' + created.uid)).status, 404);
    for (const kind of ['offers', 'promotions']) {
      response = await admin('api/v1/admin/' + kind + '?status=disabled&per_page=1'); assert.equal(response.status, 200);
      assert.match(response.headers.get('cache-control'), /no-store/); assert.equal((await response.json()).data.pager.total, 1);
      for (const query of ['per_page=101', 'page=0', 'status=bad', 'search[]=x']) assert.equal((await admin('api/v1/admin/' + kind + '?' + query)).status, 422);
      response = await admin('admin/' + kind); assert.equal(response.status, 200); assert.match(await response.text(), /admin-campaigns\.js/);
    }
    console.log('PASS: HTTP campaign create/edit/deactivate, duplicate/stale requests, role/CSRF checks, private pages/listings, scheduling and unsafe data rejection. Temporary SQL/storage; single-worker fixture.');
    for (const request of [admin, second]) await mutate(request, 'api/v1/auth/logout', {});
    if (preview) {
      console.log('Preview: ' + base + 'login (sample admin phone 9000000001). Send stop to close.');
      await new Promise(resolve => { process.once('SIGINT', resolve); process.stdin.once('data', resolve); }); process.stdin.pause();
    }
  } finally {
    if (server.exitCode === null) { server.kill(); await new Promise(resolve => server.once('exit', resolve)); }
    const resolved = path.resolve(directory); assert.ok(resolved.startsWith(path.resolve(os.tmpdir()) + path.sep) && path.basename(resolved).startsWith('sabjiwalah-campaigns-'));
    await fs.rm(resolved, {recursive: true, force: true});
  }
})().catch(error => { console.error(error); process.exitCode = 1; });
