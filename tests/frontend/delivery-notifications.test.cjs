const test=require('node:test'),assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm');
test('only assignments and ready alerts remain; repeated events are deduplicated',()=>{
 const events={},store={};const node=()=>({children:[],events:{},dataset:{rider:'r1'},setAttribute(){},append(...children){this.children.push(...children);},replaceChildren(){this.children=[];},addEventListener(name,fn){this.events[name]=fn;}});
 const ids=Object.fromEntries(['delivery-notifications','delivery-notifications-badge','delivery-notifications-open','delivery-notifications-list','delivery-notifications-empty','delivery-notifications-clear','delivery-notifications-close','delivery-logout'].map(id=>[id,node()]));
 const key='sabjiwalah.delivery.notifications.r1';store[key]=JSON.stringify([{text:'Availability saved. Refreshing your dashboard…',time:1},{text:'Pickup recorded.',time:2},{text:'Your assignment activity has changed. Check your deliveries.',time:3}]);
 vm.runInNewContext(fs.readFileSync('public/assets/js/delivery-notifications.js','utf8'),{document:{getElementById:id=>ids[id],createElement:node,addEventListener(){}},window:{addEventListener:(name,fn)=>events[name]=fn,Sabjiwalah:{url:path=>path}},sessionStorage:{getItem:key=>store[key],setItem:(key,value)=>store[key]=value},Date,JSON,Number});
 assert.equal(JSON.parse(store[key]).length,1);
 const send=detail=>events['sabjiwalah:delivery-notification']({detail});
 send({text:'Delivery completed.'});assert.equal(ids['delivery-notifications-list'].children.length,1);
 send({text:'New assignment',type:'assignment',id:'a1'});send({text:'New assignment',type:'assignment',id:'a1'});assert.equal(ids['delivery-notifications-list'].children.length,2);
 send({text:'Package ready',type:'ready',id:'o1:ready'});assert.equal(ids['delivery-notifications-list'].children.length,3);
});
