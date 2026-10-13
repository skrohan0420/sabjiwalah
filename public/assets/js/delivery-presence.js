(() => {
  'use strict';
  const status=document.getElementById('delivery-availability-value');
  if(!status)return;
  let message=document.getElementById('rider-presence-message');
  if(!message){message=document.createElement('span');message.className='sr-only';message.setAttribute('role','status');status.parentElement.append(message);}
  const ui=window.SabjiwalahDelivery;
  function report(state,label,detail=label){message.textContent=label;message.title=detail;message.setAttribute('aria-label',detail);message.setAttribute('data-state',state);}
  let alive=true,enabled=false,clearing=false,watch,timer,pending,sample,generation=0,lastAttempt=0,lastStamp=0,denied=false,conflict=false;
  const available=()=>['available','assigned','busy'].includes(status.textContent.toLowerCase());
  const active=()=>alive&&enabled&&!document.hidden&&navigator.onLine!==false&&available();
  function cancel(){
    enabled=false;++generation;clearTimeout(timer);timer=undefined;sample=undefined;
    if(watch!==undefined)navigator.geolocation.clearWatch(watch);
    watch=undefined;
  }
  async function stop(text='Location sharing stopped.',remove=true){
    cancel();report(denied?'error':'paused',denied?'Allow location':'GPS paused',text);
    if(!remove||clearing||!alive)return;
    clearing=true;
    try{if(pending){try{await pending;}catch{}}await ui.api('api/v1/delivery/presence',{},'DELETE');}
    catch{if(alive)report('error','GPS update delayed',text+' Last shared location expires within five minutes.');}
    finally{clearing=false;sync();}
  }
  function schedule(token){
    clearTimeout(timer);
    if(enabled&&alive&&token===generation)timer=setTimeout(()=>{timer=undefined;send(token);},20000);
  }
  async function send(token){
    if(token!==generation||!enabled||!alive||pending)return;
    if(!active()){await stop('GPS paused. Last location remains for up to five minutes.',!available());return;}
    const now=Date.now();
    if(ui.isBusy()||!sample||now-sample.captured_at_ms>30000||sample.captured_at_ms<=lastStamp||now-lastAttempt<20000){schedule(token);return;}
    lastAttempt=now;
    const payload={...sample};
    pending=ui.api('api/v1/delivery/presence',payload,'POST',()=>token===generation&&active());
    let failure;
    try{await pending;if(token===generation&&active()){lastStamp=payload.captured_at_ms;report(payload.accuracy>100?'waiting':'live',payload.accuracy>100?'GPS approximate':'GPS live','Live location shared with dispatch · GPS accuracy ±'+Math.round(payload.accuracy)+' metres.');}}
    catch(error){failure=error;}
    finally{pending=undefined;}
    if(token!==generation||!alive)return;
    if(failure&&[401,403,409,422].includes(failure.status)){conflict=true;await stop(ui.errorText(failure)+' Sharing stopped.');return;}
    if(failure)report('waiting','Reconnecting GPS','Location update delayed. Retrying while this page stays open.');
    schedule(token);
  }
  function start(){
    if(enabled||clearing||pending||denied||conflict||!alive)return;
    if(!window.isSecureContext||!navigator.geolocation){report('error','GPS unavailable','Live GPS requires HTTPS and browser location support.');return;}
    if(document.hidden||navigator.onLine===false){report('paused','GPS paused','Keep this page visible and reconnect to start sharing.');return;}
    enabled=true;const token=++generation;lastAttempt=0;lastStamp=0;report('waiting','Finding GPS…');
    watch=navigator.geolocation.watchPosition(position=>{
      if(token!==generation||!active())return;
      const {latitude,longitude,accuracy}=position.coords,timestamp=position.timestamp;
      if(![latitude,longitude,accuracy,timestamp].every(Number.isFinite)||Math.abs(latitude)>90||Math.abs(longitude)>180||accuracy<0||accuracy>5000||Date.now()-timestamp>30000||timestamp>Date.now()+10000){report('waiting','Finding precise GPS…','Waiting for a fresh GPS location accurate within five kilometres. Phone GPS usually gives a more precise location.');return;}
      sample={latitude,longitude,accuracy,captured_at_ms:Math.round(timestamp)};
      if(!pending&&!timer)send(token);
    },error=>{if(token!==generation)return;if(error.code===1){denied=true;stop('Allow location access in your browser to update GPS while Available.');}else report('waiting','Waiting for GPS…','Updates resume when a precise location is found.');},{enableHighAccuracy:true,maximumAge:0,timeout:15000});
  }
  function sync(){
    if(!alive)return;
    if(!available())conflict=false;
    if(!available()||document.hidden||navigator.onLine===false){
      if(enabled)stop('GPS paused. Last location remains for up to five minutes.',!available()&&navigator.onLine!==false);
      else if(!clearing&&!denied)report('paused','GPS paused','GPS updates automatically while Available.');
      return;
    }
    start();
  }
  const observer=new MutationObserver(sync);observer.observe(status,{childList:true,characterData:true,subtree:true});
  document.addEventListener('visibilitychange',sync);
  window.addEventListener('offline',()=>{if(enabled)stop('Sharing stopped while offline.',false);});
  window.addEventListener('online',sync);
  window.addEventListener('pagehide',()=>{alive=false;observer.disconnect();cancel();});
  sync();
})();
