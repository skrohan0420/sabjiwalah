const test = require('node:test'), assert = require('node:assert/strict'), fs = require('node:fs'), vm = require('node:vm');
class El {
  constructor(tag = 'div') { this.tag = tag; this.children = []; this.events = {}; this.attrs = {}; this.dataset = {}; this.value = ''; this.textContent = ''; }
  append(...nodes) { nodes.forEach(child => { child.parent = this; this.children.push(child); }); }
  after(node) { node.parent = this.parent; this.parent.children.splice(this.parent.children.indexOf(this) + 1, 0, node); }
  remove() { if (this.parent) this.parent.children = this.parent.children.filter(child => child !== this); }
  replaceChildren(...nodes) { this.children = []; this.append(...nodes); }
  setAttribute(name, value) { this.attrs[name] = value; }
  getAttribute(name) { return this.attrs[name]; }
  addEventListener(name, callback) { this.events[name] = callback; }
  focus() {}
  showModal() { this.open=true; }
  close() { this.open=false; }
  matches(selector) { return selector.startsWith('.') ? (this.className || '').split(' ').includes(selector.slice(1)) : selector.startsWith('[') ? Object.hasOwn(this.attrs, selector.slice(1, -1)) : this.tag === selector; }
  querySelectorAll(selector) { const found = []; const walk = node => node.children.forEach(child => { if (child.matches(selector)) found.push(child); walk(child); }); walk(this); return found; }
  querySelector(selector) { return this.querySelectorAll(selector)[0]; }
  closest(selector) { return this.matches(selector) ? this : null; }
}
const flush = () => new Promise(resolve => setImmediate(resolve));
function setup() {
  const ids = Object.fromEntries(['operations-queues','operations-queue','orders-error','orders-list','orders-refresh'].map(id => [id,new El()]));
  ids['operations-queue'].value = 'new';
  for (const queue of ['new','preparing','ready','on_way','history']) { const button = new El('button'); button.dataset.queue = queue; button.setAttribute('data-queue',''); const count = new El(); count.setAttribute('data-queue-count',''); button.append(count); ids['operations-queues'].append(button); }
  const body = ids['orders-list'];
  const requests = [], notices = [], events = {}; let refreshes = 0, confirmed = true;
  const window = { SabjiwalahAdmin: { api: (path, options) => new Promise((resolve,reject) => requests.push({path,options,resolve,reject})), busy: (button,value) => button.disabled=value, notify: text=>notices.push(text), confirm: async()=>confirmed }, SabjiwalahOrderWorkspace: { refresh:()=>refreshes++, changeQueue:queue=>{ids['operations-queue'].value=queue;refreshes++;} }, addEventListener:(event,cb)=>events[event]=cb };
  vm.runInNewContext(fs.readFileSync('public/assets/js/admin-operations.js','utf8'), { document: { body, getElementById:id=>ids[id], createElement:tag=>new El(tag) }, window, setTimeout:()=>1,clearTimeout:()=>{},URLSearchParams, Set, Array, String, Error, encodeURIComponent });
  const row = new El('tr'); for(let i=0;i<7;i++)row.append(new El('td')); ids['orders-list'].append(row);
  const button = new El('button');row.children[6].append(button);
  return { ids, requests, notices, events, row, button, ui:window.SabjiwalahOperations, refreshes:()=>refreshes, confirm:value=>confirmed=value };
}
test('accept & prepare uses one guarded request, blocks overlap, and refreshes the queue',async()=>{
  const s=setup(),order={uid:'ord_one',order_number:'SW1',order_status:'pending'};
  s.ui.act(order,s.row,s.button);s.ui.act(order,s.row,s.button);
  assert.equal(s.requests.length,1);assert.equal(s.requests[0].options.body.action,'accept_prepare');assert.equal(s.requests[0].options.body.expected_status,'pending');
  s.requests[0].resolve({data:{}});await flush();assert.equal(s.ui.isBusy(),false);assert.equal(s.refreshes(),1);
});
test('an uncertain write cannot be repeated automatically or by another click',async()=>{
  const s=setup(),order={uid:'ord_one',order_status:'pending'};s.ui.act(order,s.row,s.button);s.requests[0].reject(new Error('Connection lost'));await flush();
  assert.equal(s.ui.isBlocked(order.uid),true);s.ui.act(order,s.row,s.button);assert.equal(s.requests.length,1);assert.match(s.ids['orders-error'].textContent,/uncertain/);
});
test('inline assignment uses fresh latest ownership and cannot overlap',async()=>{
  const s=setup(),order={uid:'ord_one',order_number:'SW1',order_status:'ready_for_delivery'};s.ui.act(order,s.row,s.button);
  assert.equal(s.ui.isBusy(),true);assert.equal(s.requests.length,2);
  s.requests[0].resolve({data:{order,assignments:[]}});s.requests[1].resolve({data:{items:[{uid:'usr_one',name:'Rider'}],pager:{total:1,page_count:1}}});await flush();
  const assign=s.ids['orders-list'].querySelector('.operations-candidate').querySelector('button');assign.events.click();assign.events.click();
  assert.equal(s.requests.length,3);assert.equal(s.requests[2].options.body.expected_assignment_uid,'none');assert.equal(s.requests[2].options.body.rider_uid,'usr_one');
  s.requests[2].resolve({data:{}});await flush();assert.equal(s.ui.isBusy(),false);assert.equal(s.refreshes(),1);
});
test('a changed order cannot offer assignment actions',async()=>{
  const s=setup(),order={uid:'ord_one',order_status:'ready_for_delivery'};s.ui.act(order,s.row,s.button);
  s.requests[0].resolve({data:{order:{...order,order_status:'out_for_delivery'},assignments:[]}});s.requests[1].resolve({data:{items:[{uid:'usr_one',name:'Rider'}],pager:{total:1,page_count:1}}});await flush();
  assert.equal(s.ids['orders-list'].querySelectorAll('.operations-candidate').length,0);assert.match(s.ids['orders-list'].querySelector('.operations-assignment-message').textContent,/no longer ready/);
});

test('preparing orders can open the picker and assign while preparation continues',async()=>{
 const s=setup(),order={uid:'ord_prep',order_number:'SW_PREP',order_status:'preparing'};
 s.ui.chooseRider(order,s.row);s.requests[0].resolve({data:{order,assignments:[]}});s.requests[1].resolve({data:{items:[{uid:'usr_rider',name:'Rider'}],pager:{total:1,page_count:1}}});await flush();
 const assign=s.ids['orders-list'].querySelector('.operations-candidate').querySelector('button');assign.events.click();assert.equal(s.requests[2].options.body.rider_uid,'usr_rider');
 s.requests[2].resolve({data:{}});await flush();assert.match(s.notices[0],/while you prepare/);
});
