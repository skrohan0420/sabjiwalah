// Independent PHP processes and MySQL connections against a newly-created, disposable local database.
const assert=require('node:assert/strict'),fs=require('node:fs/promises'),os=require('node:os'),path=require('node:path');
const {spawn}=require('node:child_process'),{randomBytes}=require('node:crypto');
(async()=>{
  const root=path.resolve(__dirname,'../..'),directory=await fs.mkdtemp(path.join(os.tmpdir(),'sabjiwalah-concurrent-'));
  const database='sw_hardening_'+randomBytes(12).toString('hex'),php=process.env.PHP_BINARY||'D:/xampp/php/php.exe',children=new Set();
  for(const name of ['logs','cache','session','uploads'])await fs.mkdir(path.join(directory,'writable',name),{recursive:true});
  function launch(action,worker){
    const child=spawn(php,[path.join(__dirname,'concurrent-admin.php'),database,directory,action,...(worker===undefined?[]:[String(worker)])],{cwd:root,windowsHide:true,stdio:['ignore','pipe','pipe']});
    children.add(child);let stdout='',stderr='';child.stdout.on('data',c=>stdout+=c);child.stderr.on('data',c=>stderr+=c);
    const done=new Promise((resolve,reject)=>{child.once('error',reject);child.once('exit',code=>{children.delete(child);if(code!==0)return reject(new Error(action+' failed: '+stderr+' '+stdout));try{resolve(JSON.parse(stdout.trim().split('\n').at(-1)));}catch{reject(new Error(action+' invalid output: '+stdout));}});});
    // Attach immediately so early worker failures never become unhandled promises while waiting at the barrier.
    done.catch(()=>{});return done;
  }
  async function race(action,count){
    const workers=Array.from({length:count},(_,i)=>launch(action,i));
    const deadline=Date.now()+15000;
    while(true){const ready=await fs.readdir(directory);if(Array.from({length:count},(_,i)=>'ready-'+action+'-'+i).every(name=>ready.includes(name)))break;assert.ok(Date.now()<deadline,'Barrier '+action+' timed out.');await new Promise(r=>setTimeout(r,20));}
    await fs.writeFile(path.join(directory,'go-'+action),'go');const results=await Promise.all(workers);
    assert.ok(results.every(r=>!r.result.startsWith('error:')),JSON.stringify(results));return results;
  }
  const counts=rows=>rows.reduce((a,r)=>(a[r.result]=(a[r.result]||0)+1,a),{}),checks=[];
  try{
    await launch('setup');
    let result=await race('status',2);assert.deepEqual(counts(result),{ok:1,conflict:1});let state=await launch('state');assert.equal(state.order_status_history.length,1);checks.push('status compare-and-set/history');
    result=await race('dispatch-capacity',2);assert.deepEqual(counts(result),{ok:1,conflict:1});state=await launch('state');assert.equal(state.delivery_assignments.length,1);checks.push('shared rider capacity');
    await launch('prepare-dispatch');result=await race('dispatch-same',2);assert.deepEqual(counts(result),{ok:1,conflict:1});state=await launch('state');assert.equal(state.delivery_assignments.filter(r=>Number(r.order_id)===4).length,1);checks.push('competing assignment revisions');
    await launch('prepare-checkout','stock');let before=(await launch('state')).orders.length;
    result=await race('checkout-stock',2);assert.deepEqual(counts(result),{ok:1,conflict:1});state=await launch('state');assert.equal(state.orders.length,before+1);assert.equal(Number(state.products[0].stock_quantity),0);checks.push('last-unit stock');
    await launch('prepare-checkout','capacity');before=(await launch('state')).orders.length;
    result=await race('checkout-capacity',2);assert.deepEqual(counts(result),{ok:1,conflict:1});state=await launch('state');assert.equal(state.orders.length,before+1);assert.equal(state.orders.filter(r=>r.order_status==='pending').length,1);checks.push('shop order capacity');
    await launch('prepare-checkout','coupon');before=(await launch('state')).orders.length;
    result=await race('checkout-coupon',2);assert.deepEqual(counts(result),{ok:1,conflict:1});state=await launch('state');assert.equal(state.orders.length,before+1);assert.equal(state.offer_redemptions.length,1);checks.push('last coupon use');
    result=await race('otp-phone',8);assert.deepEqual(counts(result),{ok:1,limited:7});checks.push('cross-session phone OTP cooldown');
    // One IP send was consumed above; nineteen additional requests may succeed.
    result=await race('otp-ip',26);assert.deepEqual(counts(result),{ok:19,limited:7});checks.push('shared IP OTP limit');
    console.log('PASS: concurrent independent-process MySQL races: '+checks.join(', ')+'. Fixture database only; not a production throughput benchmark.');
  }finally{
    for(const child of children)child.kill();
    if(children.size)await Promise.all([...children].map(c=>new Promise(r=>c.once('exit',r))));
    try{if(await fs.readFile(path.join(directory,'database-owner'),'utf8')===database)await launch('cleanup');}catch(e){if(e.code!=='ENOENT')throw e;}
    const resolved=path.resolve(directory);assert.ok(resolved.startsWith(path.resolve(os.tmpdir())+path.sep)&&path.basename(resolved).startsWith('sabjiwalah-concurrent-'));
    await fs.rm(resolved,{recursive:true,force:true});
  }
})().catch(e=>{console.error(e);process.exitCode=1;});
