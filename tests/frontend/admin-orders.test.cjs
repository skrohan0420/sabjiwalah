const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('public/assets/js/admin-orders.js', 'utf8');
function element(tag = 'div') {
  return { tag, children: [], events: {}, attributes: {}, dataset: {}, value: '', hidden: false, disabled: false, textContent: '', setAttribute(k,v) { this.attributes[k] = v; }, append(...nodes) { this.children.push(...nodes); if (this.tag === 'select' && !this.value && nodes[0]) this.value = nodes[0].value; }, replaceChildren(...nodes) { this.children = []; this.append(...nodes); }, addEventListener(name,fn) { this.events[name] = fn; }, showModal() { this.open = true; }, close() { this.open = false; } };
}
function setup() {
  const ids = Object.fromEntries(['orders-filters','orders-list','orders-refresh','orders-error','order-details','order-action-form','order-action-notes','order-action','orders-page','orders-prev','orders-next','orders-new','orders-updated','orders-loading','order-detail-stale','order-action-submit','order-detail-error','order-detail-content','order-detail-refresh','order-detail-title','order-close'].map(id => [id, element(id === 'order-action' ? 'select' : 'div')]));
  ids['orders-filters'].elements = ['search','status','from','to','sort','dir','per_page'].map(name => ({ name, value: ({sort:'created_at',dir:'desc',per_page:'20'})[name] || '' }));
  const requests = [], timers = new Map(), events = {}, windowEvents = {}, urls = [];
  let next = 0;
  const document = { hidden:false, getElementById: id => ids[id], createElement: tag => element(tag), createDocumentFragment: () => element('fragment'), addEventListener: (name,fn) => events[name] = fn };
  const context = { document, URLSearchParams, Intl, Date, Math, Number, AbortController, encodeURIComponent, FormData: class { constructor(form) { this.fields = form.elements.map(field => [field.name,field.value]); } [Symbol.iterator]() { return this.fields[Symbol.iterator](); } }, location:{ search:'',pathname:'/admin/orders' }, history:{replaceState(a,b,url) {urls.push(url);} }, setTimeout(fn,delay) {const id=++next;timers.set(id,{fn,delay});return id;}, clearTimeout(id) {timers.delete(id);}, window:{ addEventListener:(name,fn)=>windowEvents[name]=fn, SabjiwalahAdmin:{busy:(button,state)=>button.disabled=state, confirm:async()=>true, notify() {}, api:(path,options)=>new Promise((resolve,reject)=>requests.push({path,options,resolve,reject}))} } };
  vm.runInNewContext(source,context);
  return {ids,requests,timers,events,windowEvents,document,urls};
}
const flush = () => new Promise(resolve => setImmediate(resolve));
const order = { uid:'ord_1',order_number:'SW1',customer_name:'<img src=x onerror=alert(1)>',placed_at:'2026-10-09T10:00:00Z',item_count:1,total_amount:100,payment_method:'cod',payment_status:'pending',order_status:'pending',assigned_delivery_name:null };
const listing = (items=[order], latest='ord_1') => ({items,pager:{page_count:items.length?1:0,current_page:1,total:items.length,per_page:20},latest_order:{uid:latest,order_number:latest}});
const details = () => ({order:{...order,subtotal:100,discount_amount:0,delivery_charge:0,customer_phone:'9000000000',address_line:'Fixture address',city:'Fixture city'},items:[],history:[],assignments:[],allowed_actions:['confirmed','cancelled']});
function find(root,predicate) { if(predicate(root))return root; for(const child of root.children||[]){const found=find(child,predicate);if(found)return found;} }
async function loadInitial(s) {s.requests[0].resolve({data:listing()});await flush();}
async function openDetail(s) {find(s.ids['orders-list'],node=>node.textContent==='View').events.click();s.requests[1].resolve({data:details()});await flush();}
test('polling keeps filters and prevents overlapping requests; customer HTML is text',async()=>{
  const s=setup();s.ids['orders-refresh'].events.click();assert.equal(s.requests.length,1);
  await loadInitial(s);assert.equal([...s.timers.values()][0].delay,8000);
  assert.ok(find(s.ids['orders-list'],node=>node.textContent===order.customer_name));
  s.ids['orders-filters'].elements[0].value='Asha';s.ids['orders-filters'].events.submit({preventDefault(){}});
  assert.match(s.requests[1].path,/search=Asha/);
  s.requests[1].resolve({data:listing([], 'ord_2')});await flush();
  assert.match(s.ids['orders-new'].textContent,/New order received/);
  s.ids['orders-refresh'].events.click();assert.match(s.requests[2].path,/search=Asha/);
  s.requests[2].resolve({data:listing([], 'ord_2')});await flush();
});
test('late filter results are ignored; changed filters queue one fresh request',async()=>{
  const s=setup();s.ids['orders-filters'].elements[0].value='Bimal';s.ids['orders-filters'].events.submit({preventDefault(){}});
  assert.equal(s.requests[0].options.signal.aborted,true);
  s.requests[0].resolve({data:listing()});await flush();
  assert.equal(s.requests.length,2);assert.equal(s.ids['orders-list'].children.length,0);
  assert.match(s.requests[1].path,/search=Bimal/);
  s.requests[1].resolve({data:listing([])});await flush();
  assert.ok(find(s.ids['orders-list'],node=>node.textContent==='No orders match these filters.'));
});
test('polling never replaces a draft and disables stale actions',async()=>{
  const s=setup();await loadInitial(s);await openDetail(s);
  s.ids['order-action-notes'].value='Keep this draft';s.ids['order-action-notes'].events.input();
  s.ids['orders-refresh'].events.click();s.requests[2].resolve({data:listing([{...order,order_status:'confirmed'}])});await flush();
  assert.equal(s.ids['order-action-notes'].value,'Keep this draft');
  assert.equal(s.ids['order-detail-stale'].hidden,false);assert.equal(s.ids['order-action-submit'].disabled,true);
});
test('status actions send expected status; conflicts keep the note and require reload',async()=>{
  const s=setup();await loadInitial(s);await openDetail(s);
  s.ids['order-action'].value='confirmed';s.ids['order-action-notes'].value='Confirm fixture';
  s.ids['order-action-notes'].events.input();s.ids['order-action-form'].events.submit({preventDefault(){}});await flush();
  assert.equal(s.requests[2].options.method,'PATCH');assert.equal(s.requests[2].options.body.expected_status,'pending');
  assert.equal(s.requests[2].options.body.notes,'Confirm fixture');
  s.requests[2].reject(Object.assign(new Error('This order changed.'),{status:409}));await flush();
  assert.equal(s.ids['order-action-notes'].value,'Confirm fixture');assert.equal(s.ids['order-action-submit'].disabled,true);
});
test('hidden pages pause polling; forbidden access stops it; pagehide aborts',async()=>{
  const s=setup();await loadInitial(s);s.document.hidden=true;s.events.visibilitychange();assert.equal(s.timers.size,0);
  s.document.hidden=false;s.events.visibilitychange();assert.equal(s.requests.length,2);
  s.requests[1].reject(Object.assign(new Error('Forbidden'),{status:403}));await flush();assert.equal(s.timers.size,0);
  const other=setup();other.windowEvents.pagehide();assert.equal(other.requests[0].options.signal.aborted,true);
  other.requests[0].reject(new Error('Aborted'));await flush();assert.equal(other.timers.size,0);
});
