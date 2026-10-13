const test=require('node:test'),assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm');
test('satellite rider map updates markers without resetting its viewport',async()=>{
 const maps=[],markers=[];
 const el=()=>({isConnected:true,children:[],classList:{add(){}},setAttribute(){},append(...items){this.children.push(...items);}}),canvas=el();
 const google={maps:{importLibrary:async name=>name==='maps'?{Map:class{constructor(_,options){this.options=options;this.fits=0;maps.push(this);}fitBounds(){this.fits++;}}}:{AdvancedMarkerElement:class{constructor(options){Object.assign(this,options);this.events={};markers.push(this);}addListener(event,callback){this.events[event]=callback;}}},LatLngBounds:class{extend(){}}}};
 const window={google};
 vm.runInNewContext(fs.readFileSync('public/assets/js/admin-pickup-map.js','utf8'),{document:{getElementById:()=>({dataset:{mapKey:'test'}}),createElement:el,createElementNS:el},window,google,WeakMap});
 const data={pickup:{latitude:22,longitude:88},items:[]};await window.SabjiwalahPickupMap.show(canvas,data);
 assert.equal(maps[0].options.mapTypeId,'hybrid');assert.match(markers[0].content.children[0].innerHTML,/M3 10/);
 let assignments=0;
 data.items=[{uid:'rider_1',name:'Rider <script>',location:{latitude:22.1,longitude:88.1}}];await window.SabjiwalahPickupMap.show(canvas,data,()=>assignments++);
 assert.equal(maps.length,1);assert.equal(maps[0].fits,1);assert.equal(markers[0].map,null);
 const rider=markers.at(-1);assert.equal(rider.content.children[1].textContent,'Rider <script>');assert.equal(rider.title,'Assign rider: Rider <script>');assert.equal(rider.gmpClickable,true);assert.match(rider.content.children[0].innerHTML,/circle/);
 rider.events.click();assert.equal(assignments,1);
 data.items[0].location.latitude=22.2;await window.SabjiwalahPickupMap.show(canvas,data);
 assert.equal(maps.length,1);assert.equal(maps[0].fits,1);assert.equal(rider.map,null);assert.equal(markers.at(-1).position.lat,22.2);
 rider.events.click();assert.equal(assignments,1);
});
