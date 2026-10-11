const test=require('node:test'),assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm');
const source=fs.readFileSync('public/assets/js/delivery-map.js','utf8'),flush=()=>new Promise(resolve=>setImmediate(resolve));
function setup(options={}) {
 const ids=Object.fromEntries(['delivery-map-panel','delivery-map','delivery-map-status','delivery-map-gps','delivery-map-route','delivery-map-clear','delivery-map-location','delivery-map-route-status','delivery-map-address','delivery-map-navigate','delivery-map-browser'].map(id=>[id,{textContent:'',hidden:false,disabled:false,events:{},dataset:{},children:[],addEventListener(n,f){this.events[n]=f;},replaceChildren(...nodes){this.children=nodes;}}]));
 ids['delivery-map'].dataset={googleApiKey:options.key||'',googleMapId:'DEMO_MAP_ID'};
 const events={},windowEvents={},gpsCalls=[],routeCalls=[],maps=[],markers=[],lines=[],scripts=[],timers=new Map();let now=1000000,timerId=0;
 class GoogleMap {constructor(canvas,settings){this.settings=settings;maps.push(this);}fitBounds(bounds){this.bounds=bounds;}}
 class Marker {constructor(settings){Object.assign(this,settings);markers.push(this);}}
 class Circle {setOptions(settings){this.settings=settings;}setMap(map){this.map=map;}}
 class Bounds {constructor(){this.points=[];}extend(point){this.points.push(point);}}
 const route={path:[{lat:0,lng:0},{lat:1,lng:1}],distanceMeters:1200,durationMillis:180000,createPolylines(){const line={setMap(map){this.map=map;}};lines.push(line);return [line];}};
 const google={maps:{importLibrary:async name=>name==='maps'?{Map:GoogleMap,Circle}:name==='marker'?{AdvancedMarkerElement:Marker}:{Route:{computeRoutes:request=>new Promise((resolve,reject)=>routeCalls.push({request,resolve,reject}))}},LatLngBounds:Bounds}};
 const navigator={onLine:true,geolocation:{getCurrentPosition(success,error,settings){gpsCalls.push({success,error,settings});}}};
 const document={hidden:false,getElementById:id=>ids[id],addEventListener:(n,f)=>events[n]=f,createElement:()=>({remove(){this.removed=true;}}),head:{append:script=>scripts.push(script)}};
 const window={google:options.noGoogle?undefined:google,isSecureContext:true,addEventListener:(n,f)=>windowEvents[n]=f,SabjiwalahDelivery:{isBusy:()=>false}};
 class Clock extends Date {static now(){return now;}}
 vm.runInNewContext(source,{window,document,navigator,URL,URLSearchParams,Date:Clock,Number,Math,Promise,Infinity,Error,encodeURIComponent,setTimeout:(fn,ms)=>{const id=++timerId;timers.set(id,{fn,ms});return id;},clearTimeout:id=>timers.delete(id)});
 return {ids,window,document,navigator,gpsCalls,routeCalls,maps,markers,lines,route,events,windowEvents,scripts,timers,setTime:value=>now=value,api:window.SabjiwalahDeliveryMap};
}
const order={delivery_latitude:0,delivery_longitude:0,address_line:'<img address>',city:'City',order_status:'out_for_delivery'};
async function ready(s){s.api.setOrder(order);await flush();}
function position(s,lat=1,lng=1,accuracy=12){s.ids['delivery-map-gps'].events.click();s.gpsCalls.at(-1).success({coords:{latitude:lat,longitude:lng,accuracy}});}
test('saved coordinates including zero draw destination without GPS/geocoding; safe navigation and address',async()=>{
 const s=setup();await ready(s);assert.equal(s.maps.length,1);assert.equal(s.markers[0].position.lat,0);assert.equal(s.gpsCalls.length,0);assert.equal(s.routeCalls.length,0);assert.equal(s.ids['delivery-map-address'].textContent,'<img address>, City');assert.equal(new URL(s.ids['delivery-map-navigate'].href).searchParams.get('dir_action'),'navigate');assert.equal(new URL(s.ids['delivery-map-navigate'].href).searchParams.get('destination'),'0,0');assert.equal(new URL(s.ids['delivery-map-browser'].href).searchParams.has('dir_action'),false);s.api.setOrder(order);await flush();assert.equal(s.maps.length,1);
});
test('missing or invalid coordinates retain encoded address fallback without loading Maps',async()=>{
 for(const coords of [[null,null],[91,0],[0,181],['0',0],[NaN,0]]){const s=setup({noGoogle:true,key:'FAKE_TEST_KEY'});s.api.setOrder({...order,delivery_latitude:coords[0],delivery_longitude:coords[1]});await flush();assert.equal(s.scripts.length,0);assert.equal(s.maps.length,0);assert.match(s.ids['delivery-map-status'].textContent,/missing or invalid/);assert.equal(new URL(s.ids['delivery-map-browser'].href).searchParams.get('destination'),'<img address>, City');}
});
test('missing key, script errors/timeouts and authentication failure preserve directions fallback',async()=>{
 const missing=setup({noGoogle:true});await ready(missing);assert.match(missing.ids['delivery-map-status'].textContent,/unavailable/);assert.equal(missing.ids['delivery-map-browser'].hidden,false);
 for(const reason of ['error','timeout','auth']){const s=setup({noGoogle:true,key:'FAKE_TEST_KEY'});s.api.setOrder(order);assert.equal(s.scripts.length,1);if(reason==='error')s.scripts[0].onerror();if(reason==='timeout')[...s.timers.values()][0].fn();if(reason==='auth')s.window.gm_authFailure();await flush();assert.equal(s.ids['delivery-map'].hidden,true);assert.equal(s.ids['delivery-map-gps'].disabled,true);assert.match(s.ids['delivery-map-status'].textContent,/unavailable/);}
});
test('GPS requires action/HTTPS; single reading displays marker/accuracy and never auto-routes',async()=>{
 const s=setup();await ready(s);s.window.isSecureContext=false;s.ids['delivery-map-gps'].events.click();assert.equal(s.gpsCalls.length,0);assert.match(s.ids['delivery-map-location'].textContent,/HTTPS/);s.window.isSecureContext=true;s.ids['delivery-map-gps'].events.click();s.ids['delivery-map-gps'].events.click();assert.equal(s.gpsCalls.length,1);assert.equal(s.gpsCalls[0].settings.timeout,15000);s.gpsCalls[0].success({coords:{latitude:1,longitude:1,accuracy:12}});assert.match(s.ids['delivery-map-location'].textContent,/±12 metres/);assert.equal(s.markers[1].position.lat,1);assert.equal(s.routeCalls.length,0);assert.equal(s.ids['delivery-map-route'].disabled,false);
});
test('permission denied is not repeatedly requested; unavailable, timeout and invalid readings recover',async()=>{
 const denied=setup();await ready(denied);denied.ids['delivery-map-gps'].events.click();denied.gpsCalls[0].error({code:1});denied.ids['delivery-map-gps'].events.click();assert.equal(denied.gpsCalls.length,1);assert.match(denied.ids['delivery-map-location'].textContent,/denied/);
 for(const code of [2,3]){const s=setup();await ready(s);s.ids['delivery-map-gps'].events.click();s.gpsCalls[0].error({code});assert.equal(s.ids['delivery-map-gps'].disabled,false);assert.equal(s.ids['delivery-map-route'].disabled,true);}
 const invalid=setup();await ready(invalid);position(invalid,100,0);assert.match(invalid.ids['delivery-map-location'].textContent,/invalid/);assert.equal(invalid.ids['delivery-map-route'].disabled,true);
});
test('routes use provider distance/duration, avoid overlap, cache nearby points and throttle new requests',async()=>{
 const s=setup();await ready(s);position(s);s.ids['delivery-map-route'].events.click();s.ids['delivery-map-route'].events.click();await flush();assert.equal(s.routeCalls.length,1);assert.equal(s.routeCalls[0].request.travelMode,'DRIVING');assert.deepEqual(Array.from(s.routeCalls[0].request.fields),['path','distanceMeters','durationMillis']);s.routeCalls[0].resolve({routes:[s.route]});await flush();assert.match(s.ids['delivery-map-route-status'].textContent,/1.2 km.*3 min/);s.ids['delivery-map-route'].events.click();assert.equal(s.routeCalls.length,1);assert.match(s.ids['delivery-map-route-status'].textContent,/Cached/);position(s,1.0001,1);s.ids['delivery-map-route'].events.click();assert.equal(s.routeCalls.length,1);position(s,2,2);s.ids['delivery-map-route'].events.click();await flush();assert.equal(s.routeCalls.length,1);assert.match(s.ids['delivery-map-route-status'].textContent,/one minute/);s.setTime(1060001);s.ids['delivery-map-route'].events.click();await flush();assert.equal(s.routeCalls.length,2);
});
test('no route or provider errors keep external fallback and do not invent estimates',async()=>{
 for(const rejected of [false,true]){const s=setup();await ready(s);position(s);s.ids['delivery-map-route'].events.click();await flush();if(rejected)s.routeCalls[0].reject(new Error('API unavailable'));else s.routeCalls[0].resolve({routes:[]});await flush();assert.match(s.ids['delivery-map-route-status'].textContent,/unavailable|No driving route/);assert.doesNotMatch(s.ids['delivery-map-route-status'].textContent,/km|min estimated/);assert.equal(s.ids['delivery-map-browser'].hidden,false);}
});
test('clear, hidden pages, terminal orders and departure discard location and late routes',async()=>{
 const s=setup();await ready(s);position(s);s.ids['delivery-map-route'].events.click();await flush();s.document.hidden=true;s.events.visibilitychange();s.routeCalls[0].resolve({routes:[s.route]});await flush();assert.equal(s.lines.length,0);assert.equal(s.markers[1].map,null);s.document.hidden=false;s.events.visibilitychange();position(s);s.api.setOrder({...order,order_status:'delivered'});assert.equal(s.ids['delivery-map-gps'].disabled,true);assert.equal(s.ids['delivery-map-navigate'].hidden,true);s.api.clear();assert.equal(s.ids['delivery-map-panel'].hidden,true);assert.equal(s.ids['delivery-map-address'].textContent,'');
 const leaving=setup({noGoogle:true,key:'FAKE_TEST_KEY'});leaving.api.setOrder(order);leaving.windowEvents.pagehide();leaving.window.sabjiwalahDeliveryMapsReady();await flush();assert.equal(leaving.maps.length,0);
});
