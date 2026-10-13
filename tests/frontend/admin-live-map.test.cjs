const test=require('node:test'),assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm');
const flush=()=>new Promise(resolve=>setImmediate(resolve));
function setup(){
 const element=()=>({children:[],events:{},dataset:{mapKey:'test'},value:'',setAttribute(){},append(...children){this.children.push(...children);},replaceChildren(...children){this.children=children;},addEventListener(name,fn){this.events[name]=fn;},close(){},showModal(){}});
 const ids=Object.fromEntries(['overview-map','overview-filter-form','overview-filter-dialog','overview-counts','overview-rider','overview-orders-tab','overview-riders-tab','overview-list','overview-map-status','overview-refresh','overview-error','overview-fit','overview-filter-open','overview-filter-close'].map(id=>[id,element()]));
 const requests=[],maps=[],markers=[],lines=[],events={};let info;
 const google={maps:{SymbolPath:{FORWARD_OPEN_ARROW:'arrow'},LatLngBounds:class{extend(){}},importLibrary:async name=>name==='maps'?{Map:class{constructor(_,options){this.options=options;this.fits=0;maps.push(this);}fitBounds(){this.fits++;}panTo(){}setZoom(){}},Polyline:class{constructor(options){Object.assign(this,options);lines.push(this);}setMap(value){this.map=value;}},InfoWindow:class{constructor(){info=this;}close(){this.opened=false;}setContent(content){this.content=content;}open(){this.opened=true;}}}:{AdvancedMarkerElement:class{constructor(options){Object.assign(this,options);this.events={};markers.push(this);}addListener(name,fn){(this.events[name]??=[]).push(fn);}}}}};
 const document={hidden:false,getElementById:id=>ids[id],createElement:element,createElementNS:element,addEventListener:(name,fn)=>events[name]=fn};
 const window={SabjiwalahAdmin:{api:(path)=>new Promise((resolve,reject)=>requests.push({path,resolve,reject}))},Sabjiwalah:{url:path=>path},SabjiwalahPickupMap:{loadGoogle:async()=>{}},addEventListener:(name,fn)=>events[name]=fn};
 vm.runInNewContext(fs.readFileSync('public/assets/js/admin-live-map.js','utf8'),{document,window,google,navigator:{onLine:true},URLSearchParams,AbortController,Map,Set,Number,Date,Array,String,Math,FormData:class{*[Symbol.iterator](){}},encodeURIComponent,setTimeout:()=>1,clearTimeout(){}});
 return{ids,document,requests,maps,markers,lines,events,info:()=>info};
}
const data={orders:[{uid:'o1',order_number:'SW1',order_status:'preparing',rider_uid:'r1',rider_name:'Rider',destination:{latitude:22.2,longitude:88.2}},{uid:'o2',order_number:'SW2',order_status:'pending',destination:{latitude:22.2,longitude:88.2}}],riders:[{uid:'r1',name:'Rider',availability:'assigned',location:{latitude:22.1,longitude:88.1,accuracy:10,recorded_at:new Date().toISOString()}}],rider_options:[{uid:'r1',name:'Rider'}],pickup:{latitude:22,longitude:88}};
test('overview loads satellite map, groups shared destinations and draws pickup/customer heading',async()=>{
 const s=setup();s.requests[0].resolve({data});await flush();assert.equal(s.maps[0].options.mapTypeId,'hybrid');assert.equal(s.markers.length,3);assert.equal(s.markers[1].content.children[1].textContent,'2 orders');assert.equal(s.lines[0].path[1].lat,22);
 s.ids['overview-list'].children[1].events.click();assert.equal(s.info().content.children[0].children[0].textContent,'SW2');
 const close=s.info().content.children[0].children[1];assert.equal(close.textContent,'×');close.events.click();assert.equal(s.info().opened,false);
 s.ids['overview-refresh'].events.click();s.requests[1].resolve({data:{...data,orders:[{...data.orders[0],order_status:'out_for_delivery'}]}});await flush();assert.equal(s.maps.length,1);assert.equal(s.maps[0].fits,1);assert.equal(s.lines.at(-1).path[1].lat,22.2);
});
test('hidden or unauthorized responses cannot expose map locations',async()=>{
 const s=setup();s.document.hidden=true;s.events.visibilitychange();s.requests[0].resolve({data});await flush();assert.equal(s.maps.length,0);
 const t=setup();t.requests[0].resolve({data});await flush();t.ids['overview-refresh'].events.click();t.requests[1].reject({status:401,message:'Session expired'});await flush();assert.ok(t.markers.every(marker=>marker.map===null));assert.equal(t.ids['overview-list'].children.length,1);assert.equal(t.ids['overview-list'].children[0].textContent,'No matches. Adjust the filters.');assert.equal(t.info().opened,false);
});
