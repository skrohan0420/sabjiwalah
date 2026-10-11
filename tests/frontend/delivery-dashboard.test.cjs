const test = require('node:test'), assert = require('node:assert/strict'), fs = require('node:fs'), vm = require('node:vm');
const source = fs.readFileSync('public/assets/js/delivery-dashboard.js', 'utf8');
function node() { return { textContent: '', hidden: false, children: [], events: {}, attributes: {}, replacements: 0,
  append(...children) { this.children.push(...children); }, replaceChildren(...children) { this.children = children; this.replacements++; },
  setAttribute(k,v) { this.attributes[k]=v; }, addEventListener(k,v) { this.events[k]=v; } }; }
function setup() {
  const ids=Object.fromEntries(['rider-availability-form','rider-availability-choice','rider-availability-save','rider-availability-error','rider-availability-help','rider-dashboard-refresh','rider-dashboard-content','rider-dashboard-error','rider-dashboard-updated','delivery-rider-name','delivery-availability-value','delivery-availability-note','rider-active-count','rider-pickup-count','rider-completed-count','rider-cod-total','rider-active-order','rider-recent-assignments'].map(id=>[id,node()]));
  const requests=[],timers=new Map(),events={},windowEvents={},notifications=[],confirmations=[]; let next=0,busy=false;
  const document={hidden:false,getElementById:id=>ids[id],createElement:node,addEventListener:(n,f)=>events[n]=f},navigator={onLine:true};
  vm.runInNewContext(source,{document,navigator,Intl,Date,Number,JSON,Set,String,Error,setTimeout:(fn,delay)=>{const id=++next;timers.set(id,{fn,delay});return id;},clearTimeout:id=>timers.delete(id),window:{addEventListener:(n,f)=>windowEvents[n]=f,SabjiwalahDelivery:{api:(path,body,method)=>new Promise((resolve,reject)=>requests.push({path,body,method,resolve,reject})),confirm:()=>new Promise(resolve=>confirmations.push(resolve)),setBusy:value=>busy=value,isBusy:()=>busy,errorText:e=>e.status===401?'Session expired':e.message,notify:value=>notifications.push(value)}}});
  return {ids,document,navigator,requests,timers,events,windowEvents,notifications,confirmations,setBusy(value){busy=value;}};
}
const flush=()=>new Promise(r=>setImmediate(r));
function data(assignments=[]) {return {rider_name:'Rider',availability:'available',base_availability:'available',can_change_availability:true,active_count:1,pending_pickups:1,completed_today:2,cod_collected_today:'123.45',generated_at:'2026-10-11T10:00:00+05:30',active_order:{order_number:'SW1',order_status:'ready_for_delivery',assigned_at:'2026-10-11T09:00:00+05:30'},recent_assignments:assignments};}
test('15-second polling avoids overlap and pauses hidden/offline pages',async()=>{
 const s=setup();s.ids['rider-dashboard-refresh'].events.click();s.events.visibilitychange();assert.equal(s.requests.length,1);s.requests[0].resolve({data:data()});await flush();assert.equal([...s.timers.values()][0].delay,15000);
 s.document.hidden=true;s.events.visibilitychange();assert.equal(s.timers.size,0);s.document.hidden=false;s.events.visibilitychange();assert.equal(s.requests.length,2);s.requests[1].resolve({data:data()});await flush();s.navigator.onLine=false;s.windowEvents.offline();assert.equal(s.timers.size,0);s.ids['rider-dashboard-refresh'].events.click();assert.equal(s.requests.length,2);s.navigator.onLine=true;s.windowEvents.online();assert.equal(s.requests.length,3);
});
test('unchanged snapshots retain order DOM and input drafts; notification only follows new assignments',async()=>{
 const row={assignment_uid:'a1',order_number:'<img onerror=alert(1)>',assigned_at:null};const s=setup();s.requests[0].resolve({data:data([row])});await flush();assert.equal(s.notifications.length,0);assert.equal(s.ids['rider-recent-assignments'].children[0].children[0].textContent,'Assigned '+row.order_number);
 s.ids['rider-active-order'].draft='keep';const count=s.ids['rider-active-order'].replacements;s.ids['rider-dashboard-refresh'].events.click();s.requests[1].resolve({data:data([row])});await flush();assert.equal(s.ids['rider-active-order'].replacements,count);assert.equal(s.ids['rider-active-order'].draft,'keep');
 s.ids['rider-dashboard-refresh'].events.click();s.requests[2].resolve({data:data([{...row,assignment_uid:'a2'}])});await flush();assert.equal(s.notifications.length,1);
});
test('network/malformed responses retain last data and re-enable retry',async()=>{
 const s=setup();s.requests[0].resolve({data:data()});await flush();s.ids['rider-dashboard-refresh'].events.click();s.requests[1].reject(new Error('Lost connection'));await flush();assert.equal(s.ids['rider-completed-count'].textContent,'2');assert.match(s.ids['rider-dashboard-error'].textContent,/last successful/);assert.equal(s.ids['rider-dashboard-refresh'].disabled,false);
 s.ids['rider-dashboard-refresh'].events.click();s.requests[2].resolve({data:{...data(),completed_today:-1}});await flush();assert.equal(s.ids['rider-completed-count'].textContent,'2');assert.equal(s.ids['rider-dashboard-error'].hidden,false);
});
test('authorization loss clears private content and stops polling',async()=>{
 const s=setup();s.requests[0].resolve({data:data()});await flush();s.ids['rider-dashboard-refresh'].events.click();s.requests[1].reject(Object.assign(new Error('Forbidden'),{status:401}));await flush();assert.equal(s.ids['rider-dashboard-content'].hidden,true);assert.equal(s.ids['delivery-rider-name'].textContent,'');assert.equal(s.ids['rider-active-order'].children.length,0);assert.equal(s.timers.size,0);s.windowEvents.online();assert.equal(s.requests.length,2);
});
test('page departure discards late responses and sensitive DOM',async()=>{
 const s=setup();s.windowEvents.pagehide();s.requests[0].resolve({data:data()});await flush();assert.equal(s.ids['rider-active-order'].children.length,0);assert.equal(s.timers.size,0);
});
test('polling defers during shared writes/dialogs and resumes on the next interval',async()=>{
 const s=setup();s.requests[0].resolve({data:data()});await flush();s.setBusy(true);s.ids['rider-dashboard-refresh'].events.click();assert.equal(s.requests.length,1);s.setBusy(false);[...s.timers.values()][0].fn();assert.equal(s.requests.length,2);
});
const submit=s=>s.ids['rider-availability-form'].events.submit({preventDefault(){}});
test('availability drafts survive polls; busy assignments and offline state disable changes',async()=>{
 const s=setup();s.requests[0].resolve({data:data()});await flush();s.ids['rider-availability-choice'].value='offline';s.ids['rider-dashboard-refresh'].events.click();s.requests[1].resolve({data:data()});await flush();assert.equal(s.ids['rider-availability-choice'].value,'offline');
 s.ids['rider-dashboard-refresh'].events.click();s.requests[2].resolve({data:{...data(),availability:'busy',can_change_availability:false}});await flush();assert.equal(s.ids['rider-availability-save'].disabled,true);submit(s);assert.equal(s.confirmations.length,0);
 s.navigator.onLine=false;s.windowEvents.offline();assert.equal(s.ids['rider-availability-choice'].disabled,true);
});
test('cancel sends no write; confirmed availability uses expected server state and blocks overlap',async()=>{
 const s=setup();s.requests[0].resolve({data:data()});await flush();s.ids['rider-availability-choice'].value='offline';submit(s);s.confirmations[0](false);await flush();assert.equal(s.requests.length,1);
 submit(s);s.confirmations[1](true);await flush();assert.equal(s.requests[1].method,'PATCH');assert.equal(s.requests[1].body.expected_availability,'available');assert.equal(s.requests[1].body.availability,'offline');submit(s);s.ids['rider-dashboard-refresh'].events.click();assert.equal(s.requests.length,2);
 s.requests[1].resolve({data:{base_availability:'offline'}});await flush();assert.equal(s.requests[2].body,undefined);s.requests[2].resolve({data:{...data(),base_availability:'offline',availability:'offline'}});await flush();assert.equal(s.ids['rider-availability-choice'].value,'offline');assert.equal(s.ids['rider-availability-save'].disabled,false);
});
test('uncertain write and failed verification block resubmission until a successful read',async()=>{
 const s=setup();s.requests[0].resolve({data:data()});await flush();s.ids['rider-availability-choice'].value='unavailable';submit(s);s.confirmations[0](true);await flush();s.requests[1].reject(new Error('Connection lost'));await flush();s.requests[2].reject(new Error('Still offline'));await flush();assert.equal(s.ids['rider-availability-save'].disabled,true);submit(s);assert.equal(s.requests.length,3);
 s.ids['rider-dashboard-refresh'].events.click();s.requests[3].resolve({data:{...data(),availability:'unavailable',base_availability:'unavailable'}});await flush();assert.equal(s.ids['rider-availability-choice'].value,'unavailable');assert.equal(s.ids['rider-availability-save'].disabled,false);
});
test('write authorization loss clears data; cancelled departure cannot issue a late write',async()=>{
 const s=setup();s.requests[0].resolve({data:data()});await flush();s.ids['rider-availability-choice'].value='offline';submit(s);s.confirmations[0](true);await flush();s.requests[1].reject(Object.assign(new Error('Forbidden'),{status:403}));await flush();assert.equal(s.ids['rider-dashboard-content'].hidden,true);assert.equal(s.requests.length,2);assert.equal(s.ids['rider-availability-save'].disabled,true);
 const leaving=setup();leaving.requests[0].resolve({data:data()});await flush();leaving.ids['rider-availability-choice'].value='offline';submit(leaving);leaving.windowEvents.pagehide();leaving.confirmations[0](true);await flush();assert.equal(leaving.requests.length,1);
});
