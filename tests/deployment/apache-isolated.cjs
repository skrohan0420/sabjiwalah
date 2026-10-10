// Local XAMPP Apache smoke test on a private ephemeral port, using guest/read-only business requests.
const assert=require('node:assert/strict'),fs=require('node:fs/promises'),os=require('node:os'),path=require('node:path'),net=require('node:net');
const {spawn}=require('node:child_process'),{randomUUID}=require('node:crypto');
(async()=>{
  const root=path.resolve(__dirname,'../..').replaceAll('\\','/'),apache=process.env.APACHE_BINARY||'D:/xampp/apache/bin/httpd.exe';
  const apacheRoot=path.resolve(path.dirname(apache),'..').replaceAll('\\','/'),phpRoot=(process.env.XAMPP_PHP_DIR||'D:/xampp/php').replaceAll('\\','/');
  const directory=await fs.mkdtemp(path.join(os.tmpdir(),'sabjiwalah-apache-')),temp=directory.replaceAll('\\','/'),token=randomUUID();
  await fs.mkdir(path.join(directory,'session'));
  const port=await new Promise((resolve,reject)=>{const s=net.createServer();s.on('error',reject);s.listen(0,'127.0.0.1',()=>{const p=s.address().port;s.close(()=>resolve(p));});});
  const base='http://127.0.0.1:'+port+'/';
  // Apache's SAPI does not populate PHP's ENV array from child process variables.
  // Set only fixture URL/session overrides before the application loads its real local DB profile.
  await fs.writeFile(path.join(directory,'prepend.php'),`<?php $_ENV['app.localBaseURL']='${base}';$_ENV['session.savePath']='${temp}/session';$_SERVER['CI_ENVIRONMENT']='development';`);
  const modules=['authz_core','authz_host','dir','mime','rewrite','headers','env'].map(name=>'LoadModule '+name+'_module "'+apacheRoot+'/modules/mod_'+name+'.so"').join('\n');
  await fs.writeFile(path.join(directory,'httpd.conf'),`ServerRoot "${apacheRoot}"
Listen 127.0.0.1:${port}
ServerName 127.0.0.1:${port}
PidFile "${temp}/httpd.pid"
ErrorLog "${temp}/error.log"
LogLevel warn
${modules}
LoadFile "${phpRoot}/php8ts.dll"
LoadFile "${phpRoot}/libpq.dll"
LoadFile "${phpRoot}/libsqlite3.dll"
LoadModule php_module "${phpRoot}/php8apache2_4.dll"
PHPINIDir "${phpRoot}"
php_admin_value auto_prepend_file "${temp}/prepend.php"
TypesConfig "${apacheRoot}/conf/mime.types"
DocumentRoot "${root}"
<Directory "${root}">
Options FollowSymLinks
AllowOverride All
Require ip 127.0.0.1
</Directory>
<FilesMatch "\\.php$">
SetHandler application/x-httpd-php
</FilesMatch>
Header always set X-Sabjiwalah-Fixture "${token}"
SetEnv CI_ENVIRONMENT development
SetEnv app.localBaseURL "${base}"
SetEnv session.savePath "${temp}/session"
`);
  const server=spawn(apache,['-X','-f',path.join(directory,'httpd.conf')],{cwd:root,windowsHide:true,stdio:['ignore','ignore','pipe'],env:{...process.env,CI_ENVIRONMENT:'development','app.localBaseURL':base,'session.savePath':temp+'/session'}});
  let diagnostics='';server.stderr.on('data',c=>diagnostics+=c);let spawnError;server.on('error',e=>spawnError=e);
  try{
    let ready=false;for(let i=0;i<80;i++){try{const r=await fetch(base+'api/v1/csrf');if(r.headers.get('x-sabjiwalah-fixture')===token&&r.status===200){ready=true;break;}}catch{}if(server.exitCode!==null||spawnError)break;await new Promise(r=>setTimeout(r,100));}
    if(!ready){try{diagnostics+=' '+await fs.readFile(path.join(directory,'error.log'),'utf8');}catch{}throw new Error(spawnError?.message||diagnostics||'Apache fixture did not start.');}
    const smoke=spawn(process.execPath,[path.join(__dirname,'apache-smoke.cjs'),base],{cwd:root,windowsHide:true,stdio:['ignore','pipe','pipe']});let output='';smoke.stdout.on('data',c=>output+=c);smoke.stderr.on('data',c=>output+=c);const code=await new Promise((resolve,reject)=>{smoke.on('error',reject);smoke.on('exit',resolve);});assert.equal(code,0,output);console.log(output.trim());
    for(const url of ['api/v1/admin/orders','api/v1/delivery/orders','account']){const r=await fetch(base+url);assert.match(r.headers.get('cache-control'),/no-store/);}
    console.log('PASS: owned loopback Apache/private-path smoke, including composer.lock and build reports; guest reads only. Hosted TLS/permissions are not tested.');
  }finally{
    if(server.exitCode===null&&!spawnError){server.kill();await new Promise(r=>server.once('exit',r));}
    const resolved=path.resolve(directory);assert.ok(resolved.startsWith(path.resolve(os.tmpdir())+path.sep)&&path.basename(resolved).startsWith('sabjiwalah-apache-'));await fs.rm(resolved,{recursive:true,force:true});
  }
})().catch(e=>{console.error(e.message);process.exitCode=1;});
