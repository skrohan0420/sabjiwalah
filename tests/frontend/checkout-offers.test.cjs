const test=require('node:test'),assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm');
const flush=()=>new Promise(resolve=>setImmediate(resolve));
function el(){return{value:'',disabled:false,hidden:false,textContent:'',innerHTML:'',style:{},events:{},classList:{add(){},remove(){}},setAttribute(){},addEventListener(n,f){this.events[n]=f;}};}
function setup(){
  const selectors=['data-checkout-page','data-checkout-form','data-checkout-message','data-checkout-policy','data-otp-status','data-dev-otp','data-place-order','data-checkout-items','data-checkout-subtotal','data-checkout-delivery','data-checkout-discount','data-checkout-total','data-coupon-status','data-coupon-remove','data-coupon-code','data-checkout-phone','data-otp-input'];
  const nodes=Object.fromEntries(selectors.map(s=>['['+s+']',el()])),events={},windowEvents={},calls=[];
  const form=nodes['[data-checkout-form]'];form.querySelector=s=>nodes[s]||null;form.elements={namedItem:()=>null};form.reset=()=>{form.resetCalled=true;};
  nodes['[data-checkout-phone]'].value='9000000003';nodes['[data-coupon-code]'].value='FRESH10';
  const twinTotal=el(), redirects=[], timers=[];
  vm.runInNewContext(fs.readFileSync('public/assets/js/checkout.js','utf8'),{
    document:{querySelector:s=>nodes[s]||null,querySelectorAll:s=>s==='[data-checkout-total]'?[nodes[s],twinTotal]:nodes[s]?[nodes[s]]:[],createElement:el,addEventListener:(n,f)=>events[n]=f},
    setTimeout:fn=>timers.push(fn),
    window:{location:{replace:url=>redirects.push(url)},Sabjiwalah:{url:p=>'/shop'+p},addEventListener:(n,f)=>windowEvents[n]=f},
    fetch:(path,options)=>new Promise((resolve,reject)=>calls.push({path,options,resolve,reject})),
    FormData:class{entries(){return Object.entries({customer_name:'Sample',customer_phone:'9000000003',address_line:'Test',city:'City',postal_code:'700001',delivery_latitude:'22.8',delivery_longitude:'88.3',otp:'123456'});}},
    localStorage:{getItem:()=>null},Intl,JSON,Object,Error,Number,Math
  });return{nodes,events,windowEvents,calls,form,twinTotal,redirects,timers};
}
function response(call,data,status=200){call.resolve({ok:status>=200&&status<300,status,json:async()=>({success:status>=200&&status<300,data,message:status===409?'Checkout changed':'Request failed'})});}
const quote={items:[],subtotal:240,delivery_charge:40,discount_amount:20,total_amount:260,selected_code:'FRESH10',applied_code:'FRESH10',offer_error:null,quote_token:'a'.repeat(64)};
test('operational closure disables placement without a second OTP and explains the policy',async()=>{
  const s=setup();response(s.calls[0],{checkout:{...quote,can_place_order:false,checkout_notice:'New orders are temporarily paused.'}});await flush();await verify(s);
  assert.equal(s.nodes['[data-place-order]'].disabled,true);assert.match(s.nodes['[data-checkout-policy]'].textContent,/temporarily paused/);
  s.events.submit(submit(s));await flush();assert.equal(s.calls.length,1);
});
async function initialize(s){response(s.calls[0],{checkout:quote});await flush();}
function click(selector){return{target:{closest:s=>s===selector?{}:null}};}
function submit(s,coupon=false){return{preventDefault(){},target:{matches:()=>coupon,closest:()=>coupon?null:s.form}};}
async function verify(s){ assert.equal(s.calls.some(call=>call.path.includes("/otp/")),false); }
test('coupon summary uses server totals, updates every bill and excludes prices from apply request',async()=>{
  const s=setup();await initialize(s);assert.match(s.nodes['[data-checkout-total]'].textContent,/260/);assert.equal(s.twinTotal.textContent,s.nodes['[data-checkout-total]'].textContent);
  assert.match(s.nodes['[data-coupon-status]'].textContent,/FRESH10 applied/);s.events.submit(submit(s,true));response(s.calls[1],{header_name:'X-CSRF',token_value:'fresh'});await flush();
  assert.equal(s.calls[2].path,'/shop/api/v1/checkout/offer');assert.deepEqual(JSON.parse(s.calls[2].options.body),{code:'FRESH10'});
  s.events.submit(submit(s,true));assert.equal(s.calls.length,3);response(s.calls[2],{checkout:quote});await flush();assert.equal(s.calls[3].options.method,'GET');
});
test('a late checkout read cannot overwrite a newer quote after a cart change',async()=>{
  const s=setup();s.windowEvents['sabjiwalah:cart-changed']();response(s.calls[1],{checkout:{...quote,total_amount:120,discount_amount:0,applied_code:null}});await flush();
  response(s.calls[0],{checkout:quote});await flush();assert.match(s.nodes['[data-checkout-total]'].textContent,/120/);
});
test('unavailable coupon prevents placement and offers a remove action',async()=>{
  const s=setup();response(s.calls[0],{checkout:{...quote,quote_token:null,discount_amount:0,offer_error:'Usage limit reached'}});await flush();await verify(s);
  assert.equal(s.nodes['[data-place-order]'].disabled,true);assert.equal(s.nodes['[data-coupon-remove]'].hidden,false);
  s.events.submit(submit(s));await flush();assert.equal(s.calls.length,1);
  s.events.click(click('[data-coupon-remove]'));response(s.calls[1],{header_name:'X-CSRF',token_value:'fresh'});await flush();assert.equal(s.calls[2].options.method,'DELETE');assert.equal(s.calls[2].options.body,'{}');
});
test('placement submits current quote, blocks overlapping requests and does not repeat an ambiguous mutation',async()=>{
  const s=setup();await initialize(s);await verify(s);assert.equal(s.nodes['[data-place-order]'].disabled,false);
  s.events.submit(submit(s));response(s.calls[1],{header_name:'X-CSRF',token_value:'fresh'});await flush();assert.equal(s.calls[2].path,'/shop/api/v1/checkout/place');
  assert.equal(s.nodes['[data-place-order]'].textContent,'Placing order…');assert.equal(s.nodes['[data-place-order]'].disabled,true);
  const body=JSON.parse(s.calls[2].options.body);assert.equal(body.quote_token,quote.quote_token);assert.ok(!('otp'in body)&&!('discount_amount'in body));
  s.events.submit(submit(s));assert.equal(s.calls.length,3);s.calls[2].reject(new TypeError('Connection lost'));await flush();
  assert.equal(s.nodes['[data-place-order]'].disabled,true);assert.match(s.nodes['[data-checkout-message]'].textContent,/Check your orders/);s.events.submit(submit(s));assert.equal(s.calls.length,3);
  assert.equal(s.nodes['[data-place-order]'].textContent,'Place Order');
});
test('stale placement refreshes quote; confirmation stays open without automatic navigation',async()=>{
  const s=setup();await initialize(s);await verify(s);s.events.submit(submit(s));response(s.calls[1],{header_name:'X-CSRF',token_value:'fresh'});await flush();response(s.calls[2],null,409);await flush();
  assert.equal(s.calls[3].path,'/shop/api/v1/checkout/summary');response(s.calls[3],{checkout:{...quote,quote_token:'b'.repeat(64)}});await flush();
  s.events.submit(submit(s));response(s.calls[4],{header_name:'X-CSRF',token_value:'fresh'});await flush();assert.equal(JSON.parse(s.calls[5].options.body).quote_token,'b'.repeat(64));
  response(s.calls[5],{order:{uid:'order-sample',order_number:'SW-SAMPLE'}},201);await flush();
  assert.equal(s.nodes['[data-place-order]'].disabled,true);
  assert.equal(s.calls.length,6);
  s.events.submit(submit(s));assert.equal(s.calls.length,6);
  assert.equal(s.timers.length,0);assert.deepEqual(s.redirects,[]);
});
