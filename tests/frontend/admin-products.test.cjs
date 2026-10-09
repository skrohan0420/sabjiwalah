const test=require('node:test'), assert=require('node:assert/strict'), fs=require('node:fs'), vm=require('node:vm');
const source=fs.readFileSync('public/assets/js/admin-products.js','utf8');
function element(tag='div') {return {tag,children:[],events:{},value:'',hidden:false,disabled:false,textContent:'',files:[],style:{},setAttribute(){},append(...nodes){this.children.push(...nodes);},replaceChildren(...nodes){this.children=nodes;},addEventListener(name,fn){this.events[name]=fn;},showModal(){this.open=true;},close(){this.open=false;}};}
function setup() {
  const ids=Object.fromEntries(['product-form','product-editor','products-filters','products-error','products-list','products-prev','products-next','products-summary','products-page','product-title','product-images','product-reload','product-image-file','product-error','product-image-preview','product-remove-image','product-save','product-fields','product-upload','product-close','product-image-form','products-create','products-refresh'].map(id=>[id,element()]));
  ids['products-filters'].elements=Object.fromEntries(['search','active','sort','dir'].map(name=>[name,{name,value:({sort:'created_at',dir:'desc'})[name]||''}]));
  ids['product-form'].elements=Object.fromEntries(['name','slug','unit','price','sale_price','stock_quantity','description','is_active'].map(name=>[name,Object.assign(element(),{name})]));
  ids['product-form'].reset=()=>Object.values(ids['product-form'].elements).forEach(field=>{field.value='';});
  const requests=[], windowEvents={};
  const document={getElementById:id=>ids[id],createElement:tag=>element(tag)};
  class FormData {constructor(form){this.fields=form?Object.values(form.elements).map(field=>[field.name,field.value]):[];}append(k,v){this.fields.push([k,v]);}[Symbol.iterator](){return this.fields[Symbol.iterator]();}}
  const context={document,FormData,URL,URLSearchParams,Intl,Number,Object,AbortController,encodeURIComponent,window:{Sabjiwalah:{url:path=>'http://localhost:8080/'+path.replace(/^\//,'')},addEventListener:(name,fn)=>{windowEvents[name]=fn;},SabjiwalahAdmin:{api:(path,options)=>new Promise((resolve,reject)=>requests.push({path,options,resolve,reject})),confirm:async()=>true,notify(){}}}};
  vm.runInNewContext(source,context);return {ids,requests,windowEvents};
}
const flush=()=>new Promise(resolve=>setImmediate(resolve));
const product={uid:'prd_1',name:'<img src=x onerror=alert(1)>',slug:'tomato',unit:'kg',price:80,sale_price:null,stock_quantity:10,is_active:true,image:null,description:'Fixture'};
const listing=(items=[product])=>({items,pager:{total:items.length,page_count:items.length?1:0}});
function find(root,text){if(root.textContent===text)return root;for(const child of root.children||[]){const result=find(child,text);if(result)return result;}}
async function initial(s){s.requests[0].resolve({data:listing()});await flush();}
async function edit(s){find(s.ids['products-list'],'Edit').events.click();s.requests[1].resolve({data:{product}});await flush();}
test('filter races ignore old responses and product names use text nodes',async()=>{
  const s=setup();s.ids['products-filters'].elements.search.value='Fresh';s.ids['products-filters'].events.submit({preventDefault(){}});
  assert.equal(s.requests[0].options.signal.aborted,true);assert.match(s.requests[1].path,/search=Fresh/);
  s.requests[0].resolve({data:listing()});await flush();assert.equal(s.ids['products-list'].children.length,0);
  s.requests[1].resolve({data:listing()});await flush();assert.ok(find(s.ids['products-list'],product.name));
  const image=s.ids['products-list'].children[0].children[0];image.events.error();assert.match(image.src,/product-placeholder\.svg/);assert.equal(image.hidden,false);
});
test('stock saves send the loaded quantity and conflicts keep the draft until reload',async()=>{
  const s=setup();await initial(s);await edit(s);
  s.ids['product-form'].elements.stock_quantity.value='12';s.ids['product-form'].events.input();
  s.ids['product-form'].events.submit({preventDefault(){}});await flush();
  assert.equal(s.requests[2].options.method,'PATCH');assert.equal(s.requests[2].options.body.expected_stock_quantity,10);assert.equal(s.ids['product-fields'].disabled,true);
  s.requests[2].reject(Object.assign(new Error('Stock changed'),{status:409}));await flush();
  assert.equal(s.ids['product-form'].elements.stock_quantity.value,'12');assert.equal(s.ids['product-save'].disabled,true);assert.equal(s.ids['product-fields'].disabled,false);
  s.ids['product-form'].events.submit({preventDefault(){}});await flush();assert.equal(s.requests.length,3);
  s.ids['product-reload'].events.click();await flush();s.requests[3].resolve({data:{product:{...product,stock_quantity:9}}});await flush();
  assert.equal(s.ids['product-form'].elements.stock_quantity.value,9);assert.equal(s.ids['product-save'].disabled,false);
});
test('successful product save uses server values and refreshes the catalogue',async()=>{
  const s=setup();await initial(s);await edit(s);
  s.ids['product-form'].elements.sale_price.value='0';s.ids['product-form'].events.input();s.ids['product-form'].events.submit({preventDefault(){}});await flush();
  assert.equal(s.requests[2].options.body.sale_price,'0');s.requests[2].resolve({data:{product:{...product,sale_price:0}}});await flush();
  assert.equal(s.ids['product-form'].elements.sale_price.value,0);assert.match(s.requests[3].path,/\/api\/v1\/admin\/products\?/);
  s.requests[3].resolve({data:listing([{...product,sale_price:0}])});await flush();assert.equal(s.ids['product-fields'].disabled,false);
});
test('sale price validation rejects before mutation and keeps unsaved changes',async()=>{
  const s=setup();await initial(s);await edit(s);
  s.ids['product-form'].elements.sale_price.value='81';s.ids['product-form'].events.input();s.ids['product-form'].events.submit({preventDefault(){}});await flush();
  assert.equal(s.requests.length,2);assert.match(s.ids['product-error'].textContent,/cannot exceed/);
  let prevented=false;s.windowEvents.beforeunload({preventDefault(){prevented=true;}});assert.equal(prevented,true);
});
