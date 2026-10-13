(() => {
  'use strict';
  const get=id=>document.getElementById(id),root=get('settings-pickup-picker');
  if(!root)return;
  const canvas=get('settings-pickup-map'),description=get('settings-pickup-description'),search=get('settings-pickup-search');
  const use=get('settings-pickup-use'),clear=get('settings-pickup-clear'),find=get('settings-pickup-find');
  const form=get('settings-form');
  const gps=get('settings-pickup-gps');
  let map,geocoder,point,locked=true,revision=0,gpsBusy=false,alive=true,keyboardPan=false,initializing=false;
  const valid=p=>p&&Number.isFinite(p.lat)&&Number.isFinite(p.lng)&&Math.abs(p.lat)<=90&&Math.abs(p.lng)<=180;
  const controls=()=>{use.disabled=locked||gpsBusy||!map||!valid(point);find.disabled=locked||!map;clear.disabled=locked;canvas.inert=locked;if(gps){gps.disabled=locked||gpsBusy||!map;gps.textContent=gpsBusy?'Locating…':'Use GPS';}};
  function describe(prefix){
    const version=++revision;
    description.textContent=prefix;
    if(!geocoder||!valid(point))return;
    geocoder.geocode({location:point}).then(result=>{if(version===revision&&result.results?.[0])description.textContent=prefix+' · '+result.results[0].formatted_address;}).catch(()=>{});
  }
  function pin(next,pan=false){
    if(!valid(next))return;
    point={lat:next.lat,lng:next.lng};
    if(map){
      map.setCenter(point);
      if(pan)map.setZoom(17);
    }
    controls();
  }
  function commit(p){
    form.elements.namedItem('pickup_latitude').value=p?p.lat.toFixed(7):'';
    form.elements.namedItem('pickup_longitude').value=p?p.lng.toFixed(7):'';
    form.dispatchEvent(new Event('change',{bubbles:true}));
  }
  use.addEventListener('click',()=>{if(locked||!valid(point))return;commit(point);describe('Unsaved pickup point — click Save settings to keep it after refresh.');});
  clear.addEventListener('click',()=>{if(locked)return;++revision;point=null;commit(null);description.textContent='Pickup cleared. Save settings to disable distance ranking.';controls();});
  gps?.addEventListener('click',async()=>{
    if(locked||gpsBusy||!map)return;
    if(!navigator.geolocation){description.textContent='GPS unavailable. Search your shop address instead.';return;}
    const version=++revision;gpsBusy=true;controls();description.textContent='Finding your current location…';
    try{
      const position=await new Promise((resolve,reject)=>navigator.geolocation.getCurrentPosition(resolve,reject,{enableHighAccuracy:true,maximumAge:0,timeout:15000}));
      if(!alive||locked||version!==revision)return;
      pin({lat:position.coords.latitude,lng:position.coords.longitude},true);
      describe('GPS accuracy ±'+Math.round(position.coords.accuracy)+' m. Move the map to your shop, then use this point.');
    }catch(error){if(alive&&version===revision)description.textContent=error.code===1?'Location permission denied. Search or move the map instead.':'GPS could not find your location. Search or move the map instead.';}
    finally{gpsBusy=false;if(alive)controls();}
  });
  async function findAddress(){
    if(locked||!map||!search.value.trim())return;
    const version=++revision;find.disabled=true;description.textContent='Finding your shop…';
    try{
      const result=await geocoder.geocode({address:search.value.trim(),region:'IN'});
      if(version!==revision||locked)return;
      const found=result.results?.[0];
      if(!found){description.textContent='No matching address found. Try a nearby landmark or click the map.';return;}
      pin({lat:found.geometry.location.lat(),lng:found.geometry.location.lng()},true);describe('Check the pin, then use this point.');
    }catch{if(version===revision)description.textContent='Search unavailable. Click the map to choose the pickup point.';}
    finally{controls();}
  }
  find.addEventListener('click',findAddress);
  search.addEventListener('keydown',event=>{if(event.key==='Enter'){event.preventDefault();findAddress();}});
  window.SabjiwalahPickupPicker={
    lock(value){locked=value;if(value)++revision;controls();},
    set(settings){
      ++revision;
      const lat=settings.pickup_latitude,lng=settings.pickup_longitude;
      if(lat!==null&&lat!==undefined&&lat!==''&&lng!==null&&lng!==undefined&&lng!==''){pin({lat:Number(lat),lng:Number(lng)},true);describe('Saved shop pickup point');}
      else{point=null;description.textContent='Search your shop or move the map to set a pickup point.';controls();}
    },
  };
  async function init(){
    if(initializing||map)return;
    initializing=true;
    try{
      await window.SabjiwalahPickupMap.loadGoogle(root.dataset.mapKey);
      const [maps,geocoding]=await Promise.all([google.maps.importLibrary('maps'),google.maps.importLibrary('geocoding')]);
      geocoder=new geocoding.Geocoder();
      map=new maps.Map(canvas,{center:valid(point)?point:{lat:20.5937,lng:78.9629},zoom:valid(point)?17:5,mapId:root.dataset.mapId||'DEMO_MAP_ID',mapTypeId:'hybrid',mapTypeControl:false,streetViewControl:false,fullscreenControl:false,gestureHandling:'cooperative'});
      map.addListener('click',event=>{if(locked||!event.latLng)return;pin({lat:event.latLng.lat(),lng:event.latLng.lng()});describe('Pin selected. Use this point, then save settings.');});
      const moved=()=>{if(locked)return;const center=map.getCenter();if(center){pin({lat:center.lat(),lng:center.lng()});describe('Check the shop beneath the pin, then use this point.');}};
      map.addListener('dragend',moved);
      canvas.addEventListener('keydown',event=>{if(event.key?.startsWith('Arrow'))keyboardPan=true;});
      map.addListener('idle',()=>{if(keyboardPan){keyboardPan=false;moved();}});
      if(valid(point)){pin(point);describe('Saved shop pickup point');}
      else description.textContent='Search your shop address or click the map to place the pickup pin.';
    }catch{description.textContent='Pickup map unavailable. Your saved pickup point is preserved. Check the Maps configuration and refresh.';}
    initializing=false;
    controls();
  }
  root.addEventListener('toggle',()=>{
    if(!root.open)return;
    if(!map){init();return;}
    google.maps.event?.trigger(map,'resize');
    if(valid(point))map.setCenter(point);
  });
  controls();if(root.open)init();
  window.addEventListener?.('pagehide',()=>{alive=false;++revision;});
})();
