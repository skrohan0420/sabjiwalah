(() => {
  let loader;
  const revisions=new WeakMap();
  const states=new WeakMap();
  function marker(kind,text){
    const pin=document.createElement('span');pin.className='operations-map-'+kind;
    const icon=document.createElementNS('http://www.w3.org/2000/svg','svg');
    icon.setAttribute('viewBox','0 0 24 24');icon.setAttribute('aria-hidden','true');
    icon.innerHTML=kind==='shop'
      ? '<path d="M3 10V6l2-3h14l2 3v4M3 10a3 3 0 0 0 6 0 3 3 0 0 0 6 0 3 3 0 0 0 6 0M4 13v8h16v-8M9 21v-6h6v6M3 6h18"/>'
      : '<circle cx="5" cy="18" r="2.5"/><circle cx="19" cy="18" r="2.5"/><path d="M7.5 18h6a3 3 0 0 0 3-3V8l-2-3h-3M16.5 10H20M3 15v-3h7l3.5 6M7 9h5"/><rect x="2" y="6" width="5" height="5" rx="1"/>';
    const label=document.createElement('span');label.textContent=text;
    pin.append(icon,label);return pin;
  }
  function load(key){
    if(window.google?.maps?.importLibrary)return Promise.resolve();
    if(loader)return loader;
    loader=new Promise((resolve,reject)=>{
      if(!key){reject(new Error('Maps is not configured.'));return;}
      const script=document.createElement('script');let done=false;
      const finish=error=>{if(done)return;done=true;clearTimeout(timeout);error?reject(error):resolve();};
      const timeout=setTimeout(()=>finish(new Error('Map timed out.')),20000);
      window.sabjiwalahPickupMapsReady=()=>finish();
      const url=new URL('https://maps.googleapis.com/maps/api/js');url.search=new URLSearchParams({key,v:'weekly',loading:'async',callback:'sabjiwalahPickupMapsReady',region:'IN'});
      script.src=url.href;script.async=true;script.onerror=()=>finish(new Error('Map unavailable.'));document.head.append(script);
    });return loader;
  }
  window.SabjiwalahPickupMap={loadGoogle:load,async show(canvas,data,onAssign){
    const revision=(revisions.get(canvas)||0)+1;revisions.set(canvas,revision);
    try{
      const config=document.getElementById('operations-queues').dataset;
      if(!data.pickup){states.get(canvas)?.markers.forEach(marker=>marker.map=null);states.delete(canvas);canvas.textContent='Set the shop pickup location in Settings to show nearby riders.';return;}
      await load(config.mapKey);
      const [{Map},{AdvancedMarkerElement}]=await Promise.all([google.maps.importLibrary('maps'),google.maps.importLibrary('marker')]);
      if(!canvas.isConnected||revisions.get(canvas)!==revision)return;
      const center={lat:data.pickup.latitude,lng:data.pickup.longitude};
      let state=states.get(canvas);
      if(!state){state={map:new Map(canvas,{center,zoom:17,mapTypeId:'hybrid',mapId:config.mapId||'DEMO_MAP_ID',streetViewControl:false,mapTypeControl:false,fullscreenControl:false,gestureHandling:'cooperative'}),markers:[],fittedRiders:false};states.set(canvas,state);}
      const map=state.map;
      state.markers.forEach(marker=>marker.map=null);state.markers=[];
      const bounds=new google.maps.LatLngBounds();bounds.extend(center);
      const pin=marker('shop','Pickup');
      state.markers.push(new AdvancedMarkerElement({map,position:center,title:'Shop pickup point',content:pin}));
      data.items.filter(rider=>rider.location).forEach(rider=>{
        const point={lat:rider.location.latitude,lng:rider.location.longitude};bounds.extend(point);
        const label=marker('rider',rider.name);
        const interactive=typeof onAssign==='function';
        const riderMarker=new AdvancedMarkerElement({map,position:point,title:(interactive?'Assign rider: ':'Rider: ')+rider.name,content:label,gmpClickable:interactive});
        if(interactive){
          label.classList.add('operations-map-assign');
          riderMarker.addListener('click',()=>{
            if(canvas.isConnected&&revisions.get(canvas)===revision)onAssign(rider);
          });
        }
        state.markers.push(riderMarker);
      });
      if(!state.fittedRiders&&data.items.some(rider=>rider.location)){map.fitBounds(bounds,40);state.fittedRiders=true;}
    }catch{if(canvas.isConnected&&revisions.get(canvas)===revision){states.get(canvas)?.markers.forEach(marker=>marker.map=null);states.delete(canvas);canvas.textContent='Map unavailable. Rider distance suggestions remain available below.';}}
  }};
})();
