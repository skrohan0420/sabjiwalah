(() => {
 'use strict';
 const $=id=>document.getElementById(id),canvas=$('overview-map');if(!canvas)return;
 const ui=window.SabjiwalahAdmin,form=$('overview-filter-form'),dialog=$('overview-filter-dialog');
 let data={orders:[],riders:[]},filters=new URLSearchParams(),tab='orders',alive=true,pending=false,generation=0,timer,controller,map,libs,mapLoading,info,first=true;
 let pins=[],lines=[];
 const labels={pending:'New',confirmed:'Accepted',preparing:'Preparing',ready_for_delivery:'Ready for pickup',out_for_delivery:'On the way',delivered:'Delivered',cancelled:'Cancelled',delivery_failed:'Failed',available:'Available',assigned:'Heading to pickup',busy:'Delivering',offline:'Offline'};
 const el=(tag,text,cls)=>{const node=document.createElement(tag);if(text!==undefined)node.textContent=text;if(cls)node.className=cls;return node;};
 const point=p=>p&&Number.isFinite(p.latitude)&&Number.isFinite(p.longitude)?{lat:p.latitude,lng:p.longitude}:null;
 const icons={shop:'<path d="M3 10V6l2-3h14l2 3v4M3 10a3 3 0 0 0 6 0 3 3 0 0 0 6 0 3 3 0 0 0 6 0M4 13v8h16v-8M9 21v-6h6v6"/>',order:'<path d="m3 7 9-4 9 4v10l-9 4-9-4ZM3 7l9 4 9-4M12 11v10"/>',rider:'<circle cx="5" cy="18" r="2.5"/><circle cx="19" cy="18" r="2.5"/><path d="M7.5 18h6a3 3 0 0 0 3-3V8l-2-3h-3M16.5 10H20M3 15v-3h7l3.5 6M7 9h5"/><rect x="2" y="6" width="5" height="5" rx="1"/>'};
 function content(kind,name){const node=el('span',undefined,'overview-pin overview-pin-'+kind),svg=document.createElementNS('http://www.w3.org/2000/svg','svg');svg.setAttribute('viewBox','0 0 24 24');svg.setAttribute('aria-hidden','true');svg.innerHTML=icons[kind];node.append(svg,el('span',name));return node;}
 function heading(rider){const order=data.orders.find(o=>o.rider_uid===rider.uid&&['preparing','ready_for_delivery','out_for_delivery'].includes(o.order_status));return order?order.order_status==='out_for_delivery'?'To customer · '+order.order_number:'To pickup · '+order.order_number:labels[rider.availability];}
 function popupBody(title){
  const body=el('div',undefined,'overview-popup'),header=el('div',undefined,'overview-popup-header'),close=el('button','×','overview-popup-close');
  close.type='button';close.setAttribute('aria-label','Close map popup');close.addEventListener('click',()=>info.close());
  header.append(el('strong',title),close);body.append(header);return body;
 }
 function popup(marker,name,description,order){const body=popupBody(name);body.append(el('p',description));if(order){const link=el('a','Open order');link.href=window.Sabjiwalah.url('/admin/orders?search='+encodeURIComponent(order.order_number));body.append(link);}info.setContent(body);info.open({map,anchor:marker});}
 function clearPins(){pins.forEach(pin=>pin.map=null);lines.forEach(line=>line.setMap(null));pins=[];lines=[];}
 function marker(kind,name,value,description,order){const position=point(value);if(!position)return;const pin=new libs.Marker({map,position,content:content(kind,name),title:name+' · '+description,gmpClickable:true});pin.addListener('click',()=>popup(pin,name,description,order));pins.push(pin);return pin;}
 function paint(fit=false){
  if(!map||!alive||document.hidden)return;clearPins();info.close();
  marker('shop','Pickup',data.pickup,'Shop pickup location');
  const groups=new Map();
  data.orders.forEach(order=>{if(!point(order.destination))return;const key=order.destination.latitude.toFixed(6)+','+order.destination.longitude.toFixed(6);if(!groups.has(key))groups.set(key,[]);groups.get(key).push(order);});
  groups.forEach(orders=>{
   const order=orders[0],pin=marker('order',orders.length>1?orders.length+' orders':order.order_number,order.destination,orders.length>1?'Orders at this location':labels[order.order_status]+' · '+(order.rider_name||'Unassigned'),orders.length===1?order:null);
   pin.orderUids=orders.map(o=>o.uid);
   if(orders.length>1)pin.addListener('click',()=>{const body=popupBody(orders.length+' orders at this location');orders.forEach(o=>{const link=el('a',o.order_number+' · '+labels[o.order_status]);link.href=window.Sabjiwalah.url('/admin/orders?search='+encodeURIComponent(o.order_number));body.append(link);});info.setContent(body);info.open({map,anchor:pin});});
  });
  data.riders.forEach(rider=>{
   const location=rider.location;if(!location)return;
   const age=Math.max(0,Math.floor((Date.now()-Date.parse(location.recorded_at))/60000));
   const order=data.orders.find(o=>o.rider_uid===rider.uid&&['preparing','ready_for_delivery','out_for_delivery'].includes(o.order_status));
   const riderPin=marker('rider',rider.name,location,heading(rider)+' · '+age+' min ago · GPS ±'+Math.round(location.accuracy)+' m',order);riderPin.riderUid=rider.uid;
   const target=order?.order_status==='out_for_delivery'?order.destination:order?data.pickup:null;
   if(point(target))lines.push(new libs.Polyline({map,path:[point(location),point(target)],strokeOpacity:0,icons:[{icon:{path:google.maps.SymbolPath.FORWARD_OPEN_ARROW,strokeColor:'#60a5fa',strokeOpacity:1,scale:2},offset:'100%'},{icon:{path:'M 0,-1 0,1',strokeColor:'#60a5fa',strokeOpacity:1,scale:2},offset:'0',repeat:'12px'}]}));
  });
  if(fit&&pins.length){const bounds=new google.maps.LatLngBounds();pins.forEach(pin=>bounds.extend(pin.position));map.fitBounds(bounds,45);if(pins.length===1)map.setZoom(16);}
  const missing=data.orders.filter(o=>!point(o.destination)).length,unknown=data.riders.filter(r=>!point(r.location)).length;
  $('overview-map-status').textContent=(missing?missing+' orders without coordinates · ':'')+(unknown?unknown+' riders without fresh GPS · ':'')+'Latest shared locations; expire after 5 minutes.';
 }
 function renderList(){
  $('overview-orders-tab').setAttribute('aria-pressed',String(tab==='orders'));$('overview-riders-tab').setAttribute('aria-pressed',String(tab==='riders'));
  const list=$('overview-list');list.replaceChildren();const rows=tab==='orders'?data.orders:data.riders;
  rows.forEach(row=>{const item=el('button',undefined,'overview-list-item');item.type='button';const location=tab==='orders'?row.destination:row.location;item.append(el('strong',tab==='orders'?row.order_number:row.name),el('small',tab==='orders'?labels[row.order_status]+' · '+(row.rider_name||'Unassigned'):heading(row)),el('small',point(location)?tab==='riders'?'GPS ±'+Math.round(location.accuracy)+' m':'View on map':'Location unavailable'));
   item.addEventListener('click',()=>{if(!map)return;const pin=pins.find(p=>tab==='orders'?p.orderUids?.includes(row.uid):p.riderUid===row.uid);if(pin){map.panTo(pin.position);map.setZoom(17);popup(pin,tab==='orders'?row.order_number:row.name,tab==='orders'?labels[row.order_status]:heading(row),tab==='orders'?row:null);}});list.append(item);});
  if(!rows.length)list.append(el('p','No matches. Adjust the filters.','admin-caption'));
 }
 async function initMap(){
  if(mapLoading)return mapLoading;
  mapLoading=(async()=>{try{
   await window.SabjiwalahPickupMap.loadGoogle(canvas.dataset.mapKey);
   const [maps,markers]=await Promise.all([google.maps.importLibrary('maps'),google.maps.importLibrary('marker')]);if(!alive||document.hidden)return;
   libs={Marker:markers.AdvancedMarkerElement,Polyline:maps.Polyline};
   map=new maps.Map(canvas,{center:point(data.pickup)||{lat:20.5937,lng:78.9629},zoom:point(data.pickup)?15:5,mapId:canvas.dataset.mapId||'DEMO_MAP_ID',mapTypeId:'hybrid',mapTypeControl:false,streetViewControl:false,fullscreenControl:true,gestureHandling:'cooperative'});info=new maps.InfoWindow({headerDisabled:true});paint(true);first=false;
  }catch{$('overview-map-status').textContent='Map unavailable. Order and rider details remain in the list.';}finally{mapLoading=undefined;}})();return mapLoading;
 }
 async function refresh(){
  if(!alive||document.hidden||navigator.onLine===false||pending)return;
  pending=true;const token=generation;controller=new AbortController();$('overview-refresh').disabled=true;
  try{const response=await ui.api('/api/v1/admin/live-map?'+filters,{signal:controller.signal});if(!alive||document.hidden||token!==generation)return;data=response.data;$('overview-error').hidden=true;
   $('overview-counts').textContent=data.orders.length+(data.orders.length===1?' order':' orders')+' · '+data.riders.length+(data.riders.length===1?' rider':' riders')+' · '+data.riders.filter(r=>point(r.location)).length+' on map'+(Array.from(filters.values()).some(Boolean)?' · Filtered':'');
   const rider=$('overview-rider'),selection=rider.value;rider.replaceChildren(el('option','All riders'));rider.children[0].value='';data.rider_options.forEach(r=>{const option=el('option',r.name);option.value=r.uid;rider.append(option);});rider.value=selection;
   renderList();if(map){paint(first);first=false;}else await initMap();
  }catch(error){if(alive&&token===generation){clearPins();info?.close();if([401,403].includes(error.status)){data={orders:[],riders:[]};renderList();$('overview-counts').textContent='Sign in again to view the live map.';alive=false;}$('overview-error').hidden=false;$('overview-error').textContent=error.message||'Refresh failed. Map positions are paused until reconnecting.';}}
  finally{pending=false;$('overview-refresh').disabled=false;clearTimeout(timer);if(alive&&!document.hidden)timer=setTimeout(refresh,15000);}
 }
 function reload(){generation++;controller?.abort();clearTimeout(timer);first=true;if(pending){setTimeout(reload,100);return;}refresh();}
 $('overview-refresh').addEventListener('click',refresh);$('overview-fit').addEventListener('click',()=>paint(true));
 $('overview-filter-open').addEventListener('click',()=>dialog.showModal());$('overview-filter-close').addEventListener('click',()=>dialog.close());dialog.addEventListener('click',event=>{if(event.target===dialog){const b=dialog.getBoundingClientRect();if(event.clientX<b.left||event.clientX>b.right||event.clientY<b.top||event.clientY>b.bottom)dialog.close();}});
 form.addEventListener('submit',event=>{event.preventDefault();filters=new URLSearchParams(new FormData(form));dialog.close();reload();});form.addEventListener('reset',()=>{setTimeout(()=>{filters=new URLSearchParams();dialog.close();reload();},0);});
 $('overview-orders-tab').addEventListener('click',()=>{tab='orders';renderList();});$('overview-riders-tab').addEventListener('click',()=>{tab='riders';renderList();});
 document.addEventListener('visibilitychange',()=>{clearTimeout(timer);if(document.hidden){generation++;controller?.abort();clearPins();info?.close();}else reload();});window.addEventListener('offline',()=>{clearTimeout(timer);generation++;controller?.abort();clearPins();});window.addEventListener('online',reload);window.addEventListener('pagehide',()=>{alive=false;generation++;clearTimeout(timer);controller?.abort();clearPins();});refresh();
})();
