const test=require('node:test'),assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm');
const flush=()=>new Promise(resolve=>setImmediate(resolve));
function setup(open=true){
  const ids=Object.fromEntries(['settings-pickup-picker','settings-pickup-map','settings-pickup-description','settings-pickup-search','settings-pickup-use','settings-pickup-gps','settings-pickup-clear','settings-pickup-find','settings-form'].map(id=>[id,{events:{},dataset:{mapKey:'test'},value:'',addEventListener(name,callback){this.events[name]=callback;}}]));
  const values={pickup_latitude:{value:''},pickup_longitude:{value:''}};let changes=0,map;
  ids['settings-pickup-picker'].open=open;
  ids['settings-form'].elements={namedItem:name=>values[name]};ids['settings-form'].dispatchEvent=()=>changes++;
  const google={maps:{importLibrary:async name=>name==='maps'?{Map:class{constructor(_,options){map=this;this.events={};this.center=options.center;this.zoom=options.zoom;}addListener(name,callback){this.events[name]=callback;}setCenter(value){this.center=value;}setZoom(value){this.zoom=value;}}}:name==='marker'?{AdvancedMarkerElement:class{addListener(){}}}:{Geocoder:class{geocode(){return Promise.resolve({results:[{formatted_address:'Fixture shop'}]});}}}}};
  const navigator={geolocation:{getCurrentPosition:resolve=>resolve({coords:{latitude:22.86,longitude:88.34,accuracy:12}})}};
  const window={SabjiwalahPickupMap:{loadGoogle:async()=>{}}};
  vm.runInNewContext(fs.readFileSync('public/assets/js/admin-settings-pickup.js','utf8'),{document:{getElementById:id=>ids[id]},window,google,navigator,Number,Promise,Event:class{constructor(type){this.type=type;}}});
  return{ids,values,navigator,picker:window.SabjiwalahPickupPicker,map:()=>map,changes:()=>changes};
}
test('collapsed pickup loads on opening at the saved location and preserves selection on reopening',async()=>{
  const s=setup(false);await flush();assert.equal(s.map(),undefined);
  s.picker.set({pickup_latitude:22.86,pickup_longitude:88.34});s.picker.lock(false);
  const root=s.ids['settings-pickup-picker'];root.open=true;root.events.toggle();await flush();
  assert.equal(s.map().center.lat,22.86);assert.equal(s.map().zoom,17);
  s.map().events.click({latLng:{lat:()=>23,lng:()=>89}});
  const map=s.map();root.open=false;root.events.toggle();root.open=true;root.events.toggle();
  assert.equal(s.map(),map);assert.equal(map.center.lat,23);assert.equal(s.changes(),0);
});
test('map selection is staged until Use this point and then marks settings dirty',async()=>{
  const s=setup();await flush();s.picker.lock(false);
  s.map().events.click({latLng:{lat:()=>22.86,lng:()=>88.34}});
  assert.equal(s.values.pickup_latitude.value,'');assert.equal(s.changes(),0);
  s.ids['settings-pickup-use'].events.click();
  assert.equal(s.values.pickup_latitude.value,'22.8600000');assert.equal(s.values.pickup_longitude.value,'88.3400000');assert.equal(s.changes(),1);
});
test('loading/saving locks map edits and clearing removes both coordinates',async()=>{
  const s=setup();await flush();s.map().events.click({latLng:{lat:()=>22,lng:()=>88}});
  assert.equal(s.ids['settings-pickup-use'].disabled,true);assert.equal(s.changes(),0);
  s.picker.lock(false);s.map().events.click({latLng:{lat:()=>22,lng:()=>88}});s.ids['settings-pickup-use'].events.click();s.ids['settings-pickup-clear'].events.click();
  assert.equal(s.values.pickup_latitude.value,'');assert.equal(s.values.pickup_longitude.value,'');assert.equal(s.changes(),2);
});
test('Enter in address search does not submit the settings form',async()=>{
  const s=setup();await flush();s.picker.lock(false);let prevented=false;
  s.ids['settings-pickup-search'].events.keydown({key:'Enter',preventDefault(){prevented=true;}});
  assert.equal(prevented,true);assert.equal(s.changes(),0);
});

test('GPS stages the current location without saving it automatically',async()=>{
  const s=setup();await flush();s.picker.lock(false);await s.ids['settings-pickup-gps'].events.click();
  assert.equal(s.values.pickup_latitude.value,'');assert.equal(s.changes(),0);
  s.ids['settings-pickup-use'].events.click();assert.equal(s.values.pickup_latitude.value,'22.8600000');
});
test('GPS permission denial preserves saved fields',async()=>{
  const s=setup();await flush();s.picker.lock(false);s.navigator.geolocation.getCurrentPosition=(_,reject)=>reject({code:1});await s.ids['settings-pickup-gps'].events.click();
  assert.equal(s.changes(),0);assert.match(s.ids['settings-pickup-description'].textContent,/permission denied/);
});
