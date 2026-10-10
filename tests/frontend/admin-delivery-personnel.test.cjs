const test=require('node:test'),assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm');
const source=fs.readFileSync('public/assets/js/admin-delivery-personnel.js','utf8');
function element(tag='div'){return {tag,tagName:tag.toUpperCase(),children:[],elements:[],events:{},value:'',hidden:false,disabled:false,textContent:'',classList:{toggle(){}},setAttribute(){},append(...nodes){this.children.push(...nodes);},replaceChildren(...nodes){this.children=nodes;},addEventListener(name,fn){this.events[name]=fn;},showModal(){this.open=true;},close(){this.open=false;},reset(){for(const field of this.elements)field.value='';},reportValidity(){return true;}};}
function setup(){
  const ids=Object.fromEntries(['riders-filters','rider-details','rider-create','rider-create-form','riders-error','riders-list','riders-prev','riders-next','riders-summary','riders-page','rider-status-action','rider-availability','rider-availability-save','rider-reload','rider-close','rider-orders-prev','rider-orders-next','rider-orders-mode','rider-create-close','rider-create-save','riders-create','rider-title','rider-content','rider-management','rider-orders-page','rider-orders','rider-error','rider-availability-form','riders-refresh','rider-create-error','rider-create-uncertain'].map(id=>[id,element()]));
  ids['riders-filters'].elements=['search','status'].map(name=>({name,value:''}));
  ids['rider-create-form'].elements=['name','phone','email'].map(name=>({...element('input'),name}));
  const requests=[],windowEvents={};
  vm.runInNewContext(source,{document:{getElementById:id=>ids[id],createElement:tag=>element(tag)},URLSearchParams,Intl,Date,Math,Object,encodeURIComponent,AbortController,FormData:class{constructor(form){this.fields=form.elements.map(field=>[field.name,field.value]);}[Symbol.iterator](){return this.fields[Symbol.iterator]();}},window:{addEventListener:(name,fn)=>windowEvents[name]=fn,Sabjiwalah:{url:path=>'http://localhost:8080'+path},SabjiwalahAdmin:{api:(path,options)=>new Promise((resolve,reject)=>requests.push({path,options,resolve,reject})),notify(){},confirm:async()=>true}}});
  return {ids,requests,windowEvents};
}
const flush=()=>new Promise(resolve=>setImmediate(resolve));
const rider={uid:'usr_1',name:'<img src=x>',phone_masked:'••••••3210',phone:'9876543210',email:'rider@example.test',status:'active',availability:'busy',base_availability:'offline',registered_at:'2026-10-09T10:00:00Z',workload:2,out_for_delivery:1,completed_deliveries:12};
const listing=(items=[rider])=>({items,pager:{total:items.length,page_count:items.length?1:0}});
const details=(status='active',page=1,base='offline')=>({personnel:{...rider,status,base_availability:base},orders:{items:[{order_number:'SW <img src=x>',order_status:'ready_for_delivery',assigned_at:'2026-10-09T10:00:00Z',completed_at:null,is_current:true}],pager:{total:12,page_count:2,current_page:page}}});
function find(root,text){if(root.textContent===text)return root;for(const child of root.children||[]){const match=find(child,text);if(match)return match;}}
async function initial(s){s.requests[0].resolve({data:listing()});await flush();}
async function open(s){find(s.ids['riders-list'],'View delivery person').events.click();s.requests[1].resolve({data:details()});await flush();}
test('personnel lists mask phones, render names as text, and ignore old filter responses',async()=>{
  const s=setup();s.ids['riders-filters'].elements[0].value='Asha';s.ids['riders-filters'].events.submit({preventDefault(){}});assert.equal(s.requests[0].options.signal.aborted,true);assert.match(s.requests[1].path,/search=Asha/);
  s.requests[0].resolve({data:listing()});await flush();assert.equal(s.ids['riders-list'].children.length,0);s.requests[1].resolve({data:listing()});await flush();assert.ok(find(s.ids['riders-list'],rider.name));assert.ok(find(s.ids['riders-list'],rider.phone_masked));assert.equal(find(s.ids['riders-list'],rider.phone),undefined);
});
test('assignment views paginate and closing removes contacts and rejects pending details',async()=>{
  const s=setup();await initial(s);await open(s);assert.ok(find(s.ids['rider-content'],rider.email));assert.match(find(s.ids['rider-orders'],'SW <img src=x>').href,/search=SW%20%3Cimg%20src%3Dx%3E/);
  s.ids['rider-orders-mode'].value='history';s.ids['rider-orders-mode'].events.change();assert.match(s.requests[2].path,/mode=history/);s.requests[2].resolve({data:details()});await flush();s.ids['rider-orders-next'].events.click();assert.match(s.requests[3].path,/page=2/);
  s.ids['rider-close'].events.click();assert.equal(s.requests[3].options.signal.aborted,true);s.requests[3].resolve({data:details('active',2)});await flush();assert.equal(s.ids['rider-content'].children.length,0);assert.equal(s.ids['rider-orders'].children.length,0);
});
test('status conflicts require reload and inactive accounts disable availability',async()=>{
  const s=setup();await initial(s);await open(s);s.ids['rider-status-action'].events.click();await flush();assert.equal(s.requests[2].options.body.expected_status,'active');assert.equal(s.ids['rider-close'].disabled,true);
  s.requests[2].reject(Object.assign(new Error('Changed'),{status:409}));await flush();assert.equal(s.ids['rider-status-action'].disabled,true);s.ids['rider-status-action'].events.click();await flush();assert.equal(s.requests.length,3);
  s.ids['rider-reload'].events.click();s.requests[3].resolve({data:details('inactive')});await flush();assert.equal(s.ids['rider-status-action'].disabled,false);assert.equal(s.ids['rider-availability-save'].disabled,true);
});
test('availability writes use the staff-set state and refresh operational snapshots',async()=>{
  const s=setup();await initial(s);await open(s);s.ids['rider-availability'].value='available';s.ids['rider-availability-form'].events.submit({preventDefault(){}});await flush();assert.equal(s.requests[2].options.body.expected_availability,'offline');assert.equal(s.requests[2].options.body.availability,'available');assert.match(s.requests[2].path,/availability$/);
  s.requests[2].resolve({data:{}});await flush();s.requests[3].resolve({data:details('active',1,'available')});await flush();s.requests[4].resolve({data:listing()});await flush();assert.equal(s.ids['rider-close'].disabled,false);
});
test('account creation retains validation drafts and blocks retries after ambiguous errors',async()=>{
  const s=setup();await initial(s);s.ids['riders-create'].events.click();const form=s.ids['rider-create-form'];form.elements[0].value='New rider';form.elements[1].value='9876543210';form.events.submit({preventDefault(){}});assert.equal(s.requests[1].options.body.name,'New rider');assert.equal(s.ids['rider-create-close'].disabled,true);
  s.requests[1].reject(Object.assign(new Error('Invalid email'),{status:422,errors:{email:'Email invalid'}}));await flush();assert.equal(form.elements[0].value,'New rider');assert.equal(s.ids['rider-create-save'].disabled,false);
  form.events.submit({preventDefault(){}});s.requests[2].reject(new Error('Network failure'));await flush();assert.equal(s.ids['rider-create-save'].disabled,true);assert.equal(s.ids['rider-create-uncertain'].hidden,false);form.events.submit({preventDefault(){}});assert.equal(s.requests.length,3);
});
test('successful creation opens the new inactive account without granting activation automatically',async()=>{
  const s=setup();await initial(s);s.ids['riders-create'].events.click();const form=s.ids['rider-create-form'];form.elements[0].value='New rider';form.elements[1].value='9876543210';form.events.submit({preventDefault(){}});s.requests[1].resolve({data:{uid:'usr_new',status:'inactive'}});await flush();assert.equal(s.ids['rider-create'].open,false);assert.equal(s.ids['rider-details'].open,true);assert.match(s.requests[2].path,/usr_new/);
  s.requests[2].resolve({data:details('inactive')});s.requests[3].resolve({data:listing()});await flush();assert.equal(s.ids['rider-status-action'].textContent,'Activate account');assert.equal(s.ids['rider-availability-save'].disabled,true);assert.equal(s.requests.filter(request=>request.options?.method==='PATCH').length,0);
});
