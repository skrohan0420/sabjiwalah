const test=require('node:test'),assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm');
const flush=()=>new Promise(resolve=>setImmediate(resolve));
function setup(initial='Available'){
 const ids=Object.fromEntries(['rider-share-presence','rider-clear-presence','rider-presence-message','delivery-availability-value'].map(id=>[id,{textContent:'',events:{},setAttribute(){},addEventListener(name,fn){this.events[name]=fn;}}]));
 ids['delivery-availability-value'].textContent=initial;
 let now=100000,success,error,sync,watches=0;const requests=[],timers=new Map(),cleared=[],events={};let next=0;
 const document={hidden:false,getElementById:id=>ids[id],addEventListener:(name,fn)=>events[name]=fn};
 const window={isSecureContext:true,addEventListener:(name,fn)=>events[name]=fn,SabjiwalahDelivery:{isBusy:()=>false,errorText:e=>e.message,api:(path,body,method='POST',canSend=()=>true)=>new Promise((resolve,reject)=>requests.push({path,body,method,canSend,resolve,reject}))}};
 const navigator={onLine:true,geolocation:{watchPosition:(ok,fail)=>{watches++;success=ok;error=fail;return 7;},clearWatch:id=>cleared.push(id)}};
 vm.runInNewContext(fs.readFileSync('public/assets/js/delivery-presence.js','utf8'),{document,window,navigator,MutationObserver:class{constructor(fn){sync=fn;}observe(){}disconnect(){}},Date:{now:()=>now},Number,Math,setTimeout:fn=>{timers.set(++next,fn);return next;},clearTimeout:id=>timers.delete(id)});
 return{ids,document,events,requests,cleared,timers,start:()=>sync(),availability:value=>{ids['delivery-availability-value'].textContent=value;sync();},watches:()=>watches,sample:(accuracy=10)=>success({coords:{latitude:22.86,longitude:88.34,accuracy},timestamp:now}),tick:()=>{now+=20000;const fn=timers.values().next().value;timers.clear();fn();},advance:()=>now+=20000,error:code=>error({code})};
}
test('live GPS uploads newer samples at bounded intervals without overlap',async()=>{
 const s=setup();s.start();s.sample();assert.equal(s.requests.length,1);s.sample();assert.equal(s.requests.length,1);
 s.requests[0].resolve({});await flush();s.advance();s.sample();s.tick();assert.equal(s.requests.length,2);assert.ok(s.requests[1].body.captured_at_ms>s.requests[0].body.captured_at_ms);
});
test('stop cancels an unsent upload and clears server location after pending requests',async()=>{
 const s=setup();s.start();s.sample();s.availability('Offline');assert.equal(s.requests[0].canSend(),false);assert.deepEqual(s.cleared,[7]);
 s.requests[0].reject(new Error('cancelled'));await flush();assert.equal(s.requests[1].method,'DELETE');s.requests[1].resolve({});await flush();assert.equal(s.timers.size,0);
});
test('hidden page stops GPS and inaccurate samples never upload',async()=>{
 const s=setup();s.start();s.sample(6000);assert.equal(s.requests.length,0);
 s.document.hidden=true;s.events.visibilitychange();assert.deepEqual(s.cleared,[7]);assert.equal(s.requests.length,0);
});
test('assignment or availability conflict stops sharing',async()=>{
 const s=setup();s.start();s.sample();s.requests[0].reject({status:409,message:'Rider assigned'});await flush();assert.deepEqual(s.cleared,[7]);assert.equal(s.requests[1].method,'DELETE');
});
test('availability starts GPS automatically and stops it when unavailable',async()=>{
 const s=setup('Unavailable');assert.equal(s.watches(),0);s.availability('Available');assert.equal(s.watches(),1);s.start();assert.equal(s.watches(),1);
 s.availability('Unavailable');assert.deepEqual(s.cleared,[7]);assert.equal(s.requests[0].method,'DELETE');s.requests[0].resolve({});await flush();
 s.availability('Available');assert.equal(s.watches(),2);
});
test('GPS resumes on returning to the page but permission denial does not loop',async()=>{
 const s=setup();s.document.hidden=true;s.events.visibilitychange();await flush();
 s.document.hidden=false;s.events.visibilitychange();assert.equal(s.watches(),2);s.error(1);s.requests[0].resolve({});await flush();
 s.start();assert.equal(s.watches(),2);assert.match(s.ids['rider-presence-message'].textContent,/Allow location/);
});
test('approximate GPS is shared with its actual accuracy and clearly labelled',async()=>{
 const s=setup();s.sample(1500);assert.equal(s.requests[0].body.accuracy,1500);s.requests[0].resolve({});await flush();
 assert.equal(s.ids['rider-presence-message'].textContent,'GPS approximate');
 s.document.hidden=true;s.events.visibilitychange();assert.equal(s.requests.length,1);
});

test('GPS stays active while heading to pickup or delivering',()=>{
 const s=setup();s.availability('Assigned');assert.equal(s.watches(),1);assert.equal(s.cleared.length,0);s.sample();assert.equal(s.requests[0].canSend(),true);
 s.availability('Busy');assert.equal(s.watches(),1);assert.equal(s.requests[0].canSend(),true);
 s.availability('Offline');assert.equal(s.requests[0].canSend(),false);assert.deepEqual(s.cleared,[7]);
});
