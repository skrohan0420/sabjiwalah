const test=require('node:test'),assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm');
const flush=()=>new Promise(resolve=>setImmediate(resolve));
function setup(dataset={deliveryCharge:'40',freeDeliveryMinimum:'499'}){
  const nodes=Object.fromEntries(['data-checkout-subtotal','data-checkout-delivery','data-checkout-total'].map(key=>['['+key+']',{textContent:''}]));
  const page={dataset,querySelector:()=>null,querySelectorAll:()=>[],hasAttribute:()=>false},calls=[],errors=[];
  vm.runInNewContext(fs.readFileSync('public/assets/js/cart.js','utf8'),{
    document:{querySelector:selector=>selector==='[data-checkout-review-page]'?page:null,querySelectorAll:selector=>nodes[selector]?[nodes[selector]]:[],addEventListener(){},body:{classList:{add(){},remove(){}}}},
    window:{Sabjiwalah:{url:path=>path},localStorage:{getItem:()=>null,setItem(){}}},
    fetch:()=>new Promise(resolve=>calls.push(resolve)),console:{error:e=>errors.push(e)},Intl,JSON,Math,Number,Object,Map,Set,Array,FormData:class{}
  });return{nodes,calls,errors};
}
function cart(subtotal,rules){return{items:subtotal?[{product:{uid:'sample',name:'Sample'},quantity:1,unit_price:subtotal}]:[],...(rules?{checkout_rules:rules}:{})};}
test('guest checkout uses updated server fee rules including disabled/zero/inclusive free thresholds',async()=>{
  for(const [subtotal,rules,expected] of [[80,{delivery_charge:35,free_delivery_minimum:null},115],[500,{delivery_charge:35,free_delivery_minimum:null},535],[80,{delivery_charge:35,free_delivery_minimum:0},80],[250,{delivery_charge:35,free_delivery_minimum:250},250],[0,{delivery_charge:35,free_delivery_minimum:null},0]]){
    const s=setup();s.calls[0]({ok:true,json:async()=>({success:true,data:{cart:cart(subtotal,rules)}})});await flush();assert.equal(s.errors.length,0);assert.match(s.nodes['[data-checkout-total]'].textContent,new RegExp('Rs '+expected+'(?:\\.00)?$'));
  }
});
test('guest checkout falls back to server-rendered settings when cart rules are absent',async()=>{
  const s=setup({deliveryCharge:'25',freeDeliveryMinimum:''});s.calls[0]({ok:true,json:async()=>({success:true,data:{cart:cart(600)}})});await flush();assert.equal(s.errors.length,0);assert.match(s.nodes['[data-checkout-total]'].textContent,/625/);
});
