const test = require('node:test'), assert = require('node:assert/strict'), vm = require('node:vm'), fs = require('node:fs');
const source = fs.readFileSync('public/assets/js/delivery-tracking.js','utf8');
const flush = async () => { for(let i=0;i<12;i++) await Promise.resolve(); };
const order = {uid:'ord_test', assignment_uid:'das_test', order_status:'out_for_delivery'};
function setup(){
  let now=1000000, busy=false, next=0, deferPost=false;
  const ids=Object.fromEntries(['delivery-tracking','delivery-tracking-start','delivery-tracking-stop','delivery-tracking-status'].map(id=>[id,{hidden:false,disabled:false,textContent:'',events:{},addEventListener(n,f){this.events[n]=f;}}]));
  const events={}, windowEvents={}, timers=new Map(), watches=[], cleared=[], calls=[];
  const navigator={onLine:true,geolocation:{watchPosition(success,error,options){watches.push({success,error,options});return watches.length;},clearWatch(id){cleared.push(id);}}};
  const document={hidden:false,getElementById:id=>ids[id],addEventListener:(n,f)=>events[n]=f};
  const window={isSecureContext:true,addEventListener:(n,f)=>windowEvents[n]=f,SabjiwalahDelivery:{isBusy:()=>busy,errorText:e=>e.message,api:(path,body,method,guard)=>{
    const request={path,body,method,guard};calls.push(request);
    if(body===undefined)return Promise.resolve({data:{order:{...order}}});
    if(method==='POST'&&deferPost)return new Promise((resolve,reject)=>Object.assign(request,{resolve,reject}));
    return Promise.resolve({data:{}});
  }}};
  class Clock extends Date {static now(){return now;}}
  vm.runInNewContext(source,{window,document,navigator,Date:Clock,Number,Math,encodeURIComponent,setTimeout:(fn,ms)=>{timers.set(++next,{fn,ms});return next;},clearTimeout:id=>timers.delete(id)});
  const api=window.SabjiwalahDeliveryTracking;api.setOrder({...order});
  return {ids,navigator,window,document,events,windowEvents,timers,watches,cleared,calls,api,
    start:()=>ids['delivery-tracking-start'].events.click(),
    stop:()=>ids['delivery-tracking-stop'].events.click(),
    position:(latitude=0,longitude=0,accuracy=15,timestamp=now)=>watches.at(-1).success({coords:{latitude,longitude,accuracy},timestamp}),
    time:value=>now=value,busy:value=>busy=value,defer:value=>deferPost=value,
    tick:async()=>{const [id,t]=timers.entries().next().value;timers.delete(id);t.fn();await flush();},
    posts:()=>calls.filter(c=>c.method==='POST'),status:()=>ids['delivery-tracking-status'].textContent};
}
test('tracking requires explicit enable, active assignment and secure GPS; no automatic location request',()=>{
  const s=setup();assert.equal(s.watches.length,0);s.api.setOrder({...order,order_status:'ready_for_delivery'});s.start();assert.equal(s.watches.length,0);
  s.api.setOrder(order);s.window.isSecureContext=false;s.start();assert.match(s.status(),/HTTPS/);assert.equal(s.watches.length,0);
  s.window.isSecureContext=true;s.start();s.start();assert.equal(s.watches.length,1);assert.equal(s.watches[0].options.maximumAge,0);
});
test('accurate fresh numeric samples only; zero valid; identity comes from assigned order',async()=>{
  const s=setup();s.start();for(const a of [[91,0,15],[0,181,15],[0,0,101],[0,0,-1],[NaN,0,15],[0,0,15,900000]])s.position(...a);
  await flush();assert.equal(s.posts().length,0);s.position();await flush();assert.equal(s.posts().length,1);assert.equal(s.posts()[0].body.latitude,0);assert.equal(s.posts()[0].body.assignment_uid,'das_test');assert.equal('delivery_user_id' in s.posts()[0].body,false);assert.match(s.status(),/Location shared/);
});
test('movement threshold, 15-second interval and one-minute stationary heartbeat',async()=>{
  const s=setup();s.start();s.position();await flush();s.time(1015000);s.position();await s.tick();assert.equal(s.posts().length,1);
  s.time(1030000);s.position(0.001);await s.tick();assert.equal(s.posts().length,2);
  s.time(1045000);s.position(0.001);await s.tick();assert.equal(s.posts().length,2);
  s.time(1090000);s.position(0.001);await s.tick();assert.equal(s.posts().length,3);
});
test('stop cancels queued upload and clears server after already-sent upload',async()=>{
  const s=setup();s.defer(true);s.start();s.position();await flush();assert.equal(s.posts().length,1);const post=s.posts()[0];assert.equal(post.guard(),true);s.stop();assert.equal(post.guard(),false);assert.equal(s.cleared.length,1);post.resolve({});await flush();assert.equal(s.calls.at(-1).method,'DELETE');assert.equal(s.timers.size,0);
});
test('hidden/offline/departure cancel GPS and require manual restart; late callbacks ignored',async()=>{
  for(const kind of ['hidden','offline','leave']){const s=setup();s.start();const old=s.watches[0];if(kind==='hidden'){s.document.hidden=true;s.events.visibilitychange();}if(kind==='offline'){s.navigator.onLine=false;s.windowEvents.offline();}if(kind==='leave')s.windowEvents.pagehide();old.success({coords:{latitude:0,longitude:0,accuracy:15},timestamp:1000000});await flush();assert.equal(s.posts().length,0);assert.equal(s.cleared.length,1);assert.equal(s.timers.size,0);}
});
test('permission denial latches; GPS timeout stops; assignment changes and terminal states stop',async()=>{
  const denied=setup();denied.start();denied.watches[0].error({code:1});await flush();denied.start();assert.equal(denied.watches.length,1);assert.match(denied.status(),/denied/);
  for(const value of [{...order,order_status:'delivered'},{...order,assignment_uid:'new'},null]){const s=setup();s.start();s.api.setOrder(value);await flush();assert.equal(s.cleared.length,1);assert.equal(s.timers.size,0);}
  const s=setup();s.start();s.watches[0].error({code:3});await flush();assert.match(s.status(),/timed out/);
});
test('authorization and network errors stop instead of queueing precise locations',async()=>{
  for(const status of [401,403,404,409,503,undefined]){const s=setup();s.defer(true);s.start();s.position();await flush();s.posts()[0].reject(Object.assign(new Error('Fixture error'),{status}));await flush();assert.equal(s.cleared.length,1);assert.equal(s.timers.size,0);assert.match(s.status(),/stopped/);}
});
test('429 backs off without overlap and shared delivery actions suppress sends',async()=>{
  const s=setup();s.defer(true);s.start();s.position();await flush();s.position(1);await flush();assert.equal(s.posts().length,1);s.posts()[0].reject(Object.assign(new Error('Limited'),{status:429}));await flush();assert.equal(s.cleared.length,0);assert.equal(s.timers.size,1);
  s.busy(true);s.time(1015000);await s.tick();assert.equal(s.posts().length,1);assert.equal(s.timers.size,1);
});
