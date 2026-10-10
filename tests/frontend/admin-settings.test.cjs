const test=require('node:test'),assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm');
const flush=()=>new Promise(resolve=>setImmediate(resolve));
function element(){return{value:'',disabled:false,hidden:false,textContent:'',children:[],events:{},addEventListener(n,f){this.events[n]=f;},append(n){this.children.push(n);},replaceChildren(){this.children=[];}};}
const settings={shop_open:1,orders_paused:0,delivery_charge:'40.00',free_delivery_minimum:'499.00',minimum_order_amount:'0.00',opens_at:null,closes_at:null,maximum_active_orders:null,revision:1,updated_at:'2026-10-10T00:00:00Z'};
function setup(){
  const ids=['settings-form','settings-fields','settings-refresh','settings-error','settings-version','settings-history'];
  const nodes=Object.fromEntries(ids.map(id=>[id,element()])),inputs=Object.fromEntries(Object.keys(settings).map(key=>[key,element()])),calls=[],confirmations=[];
  nodes['settings-form'].elements={namedItem:key=>inputs[key]};
  vm.runInNewContext(fs.readFileSync('public/assets/js/admin-settings.js','utf8'),{document:{getElementById:id=>nodes[id],createElement:element},window:{SabjiwalahAdmin:{api:(path,options)=>new Promise((resolve,reject)=>calls.push({path,options,resolve,reject})),notify(){},confirm:options=>new Promise(resolve=>confirmations.push({options,resolve}))}},Date,Object,Number,String});
  return{nodes,inputs,calls,confirmations};
}
async function initialize(s){s.calls[0].resolve({data:{settings,history:[]}});await flush();}
const submit=()=>({preventDefault(){}});
test('settings submit observed revision, preserve blanks, block overlap and render audit as text',async()=>{
  const s=setup();assert.equal(s.nodes['settings-fields'].disabled,true);await initialize(s);assert.equal(s.nodes['settings-fields'].disabled,false);
  s.inputs.free_delivery_minimum.value='';s.nodes['settings-form'].events.submit(submit());assert.equal(s.nodes['settings-fields'].disabled,true);
  assert.equal(s.calls[1].options.method,'PUT');assert.equal(s.calls[1].options.body.expected_revision,1);assert.equal(s.calls[1].options.body.free_delivery_minimum,'');
  s.nodes['settings-form'].events.submit(submit());assert.equal(s.calls.length,2);
  s.calls[1].resolve({data:{settings:{...settings,revision:2,free_delivery_minimum:null}}});await flush();
  s.calls[2].resolve({data:{history:[{admin_name:'<script>alert(1)</script>',created_at:settings.updated_at,old_values:settings,new_values:{...settings,free_delivery_minimum:null}}]}});await flush();
  const article=s.nodes['settings-history'].children[0];assert.match(article.children[0].textContent,/<script>/);assert.match(article.children[1].children[0].textContent,/Disabled/);assert.equal(s.nodes['settings-fields'].disabled,false);
});
test('stale and uncertain saves require refresh; validation leaves draft editable',async()=>{
  for(const status of [409,0,503,422]){
    const s=setup();await initialize(s);s.nodes['settings-form'].events.submit(submit());s.calls[1].reject(Object.assign(new Error('Failed'),{status,errors:status===422?{delivery_charge:'Use a positive amount.'}:null}));await flush();
    assert.equal(s.nodes['settings-fields'].disabled,status!==422);assert.equal(s.nodes['settings-refresh'].disabled,false);
    if(status===422)assert.match(s.nodes['settings-error'].textContent,/positive amount/);
    s.nodes['settings-form'].events.submit(submit());assert.equal(s.calls.length,status===422?3:2);
  }
});
test('refresh confirms before discarding edits; failed initial read prevents saves',async()=>{
  const s=setup();s.calls[0].reject(new Error('Offline'));await flush();assert.equal(s.nodes['settings-fields'].disabled,true);
  s.nodes['settings-form'].events.submit(submit());assert.equal(s.calls.length,1);
  s.nodes['settings-refresh'].events.click();s.calls[1].resolve({data:{settings,history:[]}});await flush();
  s.nodes['settings-form'].events.input();s.nodes['settings-refresh'].events.click();assert.equal(s.calls.length,2);s.confirmations[0].resolve(false);await flush();assert.equal(s.calls.length,2);
  s.nodes['settings-refresh'].events.click();s.confirmations[1].resolve(true);await flush();assert.equal(s.calls.length,3);
});
