const test=require('node:test'),assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm');
const source=fs.readFileSync('public/assets/js/admin-customers.js','utf8');
function element(tag='div'){return {tag,children:[],events:{},value:'',hidden:false,disabled:false,textContent:'',classList:{toggle(){}},setAttribute(){},append(...nodes){this.children.push(...nodes);},replaceChildren(...nodes){this.children=nodes;},addEventListener(name,fn){this.events[name]=fn;},showModal(){this.open=true;},close(){this.open=false;}};}
function setup(){
  const ids=Object.fromEntries(['customers-filters','customer-details','customers-error','customers-list','customers-prev','customers-next','customers-summary','customers-page','customer-status-action','customer-reload','customer-close','customer-orders-prev','customer-orders-next','customer-title','customer-content','customer-management','customer-orders-page','customer-orders','customer-error','customers-refresh'].map(id=>[id,element()]));
  ids['customers-filters'].elements=Object.fromEntries(['search','status','sort','dir'].map(name=>[name,{name,value:({sort:'created_at',dir:'desc'})[name]||''}]));
  const requests=[],windowEvents={};
  const context={document:{getElementById:id=>ids[id],createElement:tag=>element(tag)},URLSearchParams,Intl,Date,Math,encodeURIComponent,AbortController,FormData:class{constructor(form){this.fields=Object.values(form.elements).map(field=>[field.name,field.value]);}[Symbol.iterator](){return this.fields[Symbol.iterator]();}},window:{addEventListener:(name,fn)=>{windowEvents[name]=fn;},Sabjiwalah:{url:path=>'http://localhost:8080'+path},SabjiwalahAdmin:{api:(path,options)=>new Promise((resolve,reject)=>requests.push({path,options,resolve,reject})),notify(){},confirm:async()=>true}}};
  vm.runInNewContext(source,context);return {ids,requests,windowEvents};
}
const flush=()=>new Promise(resolve=>setImmediate(resolve));
const customer={uid:'usr_1',name:'<img src=x onerror=alert(1)>',phone_masked:'••••••3210',phone:'9876543210',email:'fixture@example.test',status:'active',registered_at:'2026-10-08T18:30:00Z',order_count:12};
const listing=(items=[customer])=>({items,pager:{total:items.length,page_count:items.length?1:0}});
const details=(status='active',page=1)=>({customer:{...customer,status},orders:{items:[{uid:'ord_1',order_number:'SW <img src=x>',total_amount:100,payment_status:'pending',order_status:'pending',placed_at:'2026-10-09T10:00:00Z'}],pager:{total:12,page_count:2,current_page:page}}});
function find(root,text){if(root.textContent===text)return root;for(const child of root.children||[]){const result=find(child,text);if(result)return result;}}
async function initial(s){s.requests[0].resolve({data:listing()});await flush();}
async function open(s){find(s.ids['customers-list'],'View account').events.click();s.requests[1].resolve({data:details()});await flush();}
test('names render as text, lists show masked phones, and late filter responses are ignored',async()=>{
  const s=setup();s.ids['customers-filters'].elements.search.value='Asha';s.ids['customers-filters'].events.submit({preventDefault(){}});
  assert.equal(s.requests[0].options.signal.aborted,true);assert.match(s.requests[1].path,/search=Asha/);
  s.requests[0].resolve({data:listing()});await flush();assert.equal(s.ids['customers-list'].children.length,0);
  s.requests[1].resolve({data:listing()});await flush();assert.ok(find(s.ids['customers-list'],customer.name));assert.ok(find(s.ids['customers-list'],customer.phone_masked));assert.equal(find(s.ids['customers-list'],customer.phone),undefined);
});
test('details paginate order history and remove contact information on close',async()=>{
  const s=setup();await initial(s);await open(s);assert.ok(find(s.ids['customer-content'],customer.email));
  const link=find(s.ids['customer-orders'],'SW <img src=x>');assert.equal(link.tag,'a');assert.match(link.href,/search=SW%20%3Cimg%20src%3Dx%3E/);
  s.ids['customer-orders-next'].events.click();assert.match(s.requests[2].path,/page=2&per_page=10/);
  s.requests[2].resolve({data:details('active',2)});await flush();assert.equal(s.ids['customer-orders-prev'].disabled,false);
  s.ids['customer-close'].events.click();assert.equal(s.ids['customer-content'].children.length,0);assert.equal(s.ids['customer-orders'].children.length,0);
});
test('account actions send the observed status and conflicts require reloading',async()=>{
  const s=setup();await initial(s);await open(s);s.ids['customer-status-action'].events.click();await flush();
  assert.equal(s.requests[2].options.method,'PATCH');assert.equal(s.requests[2].options.body.status,'inactive');assert.equal(s.requests[2].options.body.expected_status,'active');assert.equal(s.ids['customer-close'].disabled,true);
  s.requests[2].reject(Object.assign(new Error('Account status changed'),{status:409}));await flush();assert.equal(s.ids['customer-status-action'].disabled,true);assert.equal(s.ids['customer-reload'].disabled,false);
  s.ids['customer-status-action'].events.click();await flush();assert.equal(s.requests.length,3);
  s.ids['customer-reload'].events.click();s.requests[3].resolve({data:details('inactive')});await flush();assert.equal(s.ids['customer-status-action'].textContent,'Activate account');assert.equal(s.ids['customer-status-action'].disabled,false);
});
test('successful account actions refresh details/list; close ignores pending detail responses',async()=>{
  const s=setup();await initial(s);await open(s);s.ids['customer-status-action'].events.click();await flush();s.requests[2].resolve({data:{status:'inactive'}});await flush();
  assert.match(s.requests[3].path,/usr_1\?page=1/);s.requests[3].resolve({data:details('inactive')});await flush();s.requests[4].resolve({data:listing([{...customer,status:'inactive'}])});await flush();assert.equal(s.ids['customer-close'].disabled,false);
  s.ids['customer-reload'].events.click();s.ids['customer-close'].events.click();assert.equal(s.requests[5].options.signal.aborted,true);
  s.requests[5].resolve({data:details()});await flush();assert.equal(s.ids['customer-content'].children.length,0);
});
