(() => {
  'use strict';
  const panel=document.querySelector('[data-customer-order]'); if(!panel) return;
  const $=id=>document.getElementById(id), message=$('customer-tracking-message'), refresh=$('customer-tracking-refresh');
  let alive=true, terminal=false, inFlight=false, pending=false, revision=0, abort, timer, signature='';
  const date=value=>Number.isFinite(Date.parse(value))?new Date(value).toLocaleString('en-IN',{timeZone:'Asia/Kolkata'})+' IST':'Time not recorded';
  const signal=status=>window.dispatchEvent(new CustomEvent('sabjiwalah:order-progress',{detail:{uid:panel.dataset.customerOrder,status}}));
  function schedule(){clearTimeout(timer);if(alive&&!terminal&&!document.hidden&&navigator.onLine!==false)timer=setTimeout(load,15000);}
  function render(data){
    const statuses=['pending','confirmed','preparing','ready_for_delivery','out_for_delivery','delivered','cancelled','delivery_failed'];
    if(!data?.order||data.order.uid!==panel.dataset.customerOrder||!statuses.includes(data.order.order_status)||!Array.isArray(data.progress)||data.progress.length!==5)throw new Error('Unable to verify order progress. Refresh to try again.');
    const next=JSON.stringify(data);terminal=['delivered','cancelled','delivery_failed'].includes(data.order.order_status);
    if(next!==signature){
      signature=next;const order=data.order;$('customer-status').textContent=order.status_label;
      const list=$('customer-progress');list.replaceChildren();
      for(const step of data.progress){const row=document.createElement('li'),label=document.createElement('strong'),stamp=document.createElement('span');row.className='is-'+(['complete','current','upcoming'].includes(step.state)?step.state:'upcoming');if(step.state==='current')row.setAttribute('aria-current','step');label.textContent=step.label;stamp.textContent=step.recorded_at?date(step.recorded_at):step.state==='upcoming'?'Upcoming':'Time not recorded';row.append(label,stamp);list.append(row);}
      const out=order.order_status==='out_for_delivery';$('customer-partner').hidden=$('customer-eta').hidden=!out;
      $('customer-partner').textContent=data.delivery_partner?'Delivery partner: '+data.delivery_partner.name:'Delivery partner information is currently unavailable.';
      const payment=$('customer-payment');
      const total=payment.querySelector('[data-order-total]');
      const method=payment.querySelector('[data-order-payment-method]');
      const paymentStatus=payment.querySelector('[data-order-payment-status]');
      if(total)total.textContent='₹'+Number(order.total_amount).toFixed(2);
      if(method)method.textContent=order.payment_method==='cod'?'Pay on delivery':order.payment_method.toUpperCase();
      if(paymentStatus){paymentStatus.textContent=order.payment_status.replaceAll('_',' ').replace(/\b\w/g,letter=>letter.toUpperCase());paymentStatus.setAttribute('data-order-payment-status',order.payment_status);}
    }
    signal(data.order.order_status);message.textContent=terminal?'Order progress complete':'Live updates · Checked '+new Date().toLocaleTimeString('en-IN',{timeZone:'Asia/Kolkata',hour:'numeric',minute:'2-digit'});
  }
  async function load(){
    clearTimeout(timer);if(!alive||document.hidden||navigator.onLine===false)return;if(inFlight){pending=true;return;}
    inFlight=true;if(refresh)refresh.disabled=true;abort=new AbortController();const current=revision,timeout=setTimeout(()=>abort.abort(),10000);
    try{const response=await fetch(window.Sabjiwalah.url('api/v1/orders/'+encodeURIComponent(panel.dataset.customerOrder)),{credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json'},signal:abort.signal});const result=await response.json();if(!alive||current!==revision)return;
      if(!response.ok||!result.success){if([401,403,404].includes(response.status)){alive=false;pending=false;signal(null);$('customer-order-private').replaceChildren();$('customer-order-private').hidden=true;message.textContent=response.status===404?'This order is unavailable. Return to your orders.':'Your session is unavailable. Sign in again to view this order.';return;}throw new Error('Progress refresh failed. Displayed status may be outdated.');}render(result.data);
    }catch(error){if(alive&&current===revision){signal(null);message.textContent=error.name==='AbortError'?'Progress refresh timed out. Displayed status may be outdated.':error.message||'Progress refresh failed. Displayed status may be outdated.';}}
    finally{clearTimeout(timeout);inFlight=false;if(refresh)refresh.disabled=!alive;if(pending&&alive){pending=false;load();}else schedule();}
  }
  function pause(text){++revision;pending=false;clearTimeout(timer);abort?.abort();signal(null);message.textContent=text;}
  refresh?.addEventListener('click',load);
  document.addEventListener('visibilitychange',()=>{if(document.hidden)pause('Updates paused while this page is hidden.');else load();});
  window.addEventListener('offline',()=>pause('You are offline. Displayed progress may be outdated. Reconnect to refresh.'));
  window.addEventListener('online',load);
  window.addEventListener('pagehide',()=>{alive=false;pause('');});
  window.addEventListener('pageshow',event=>{if(event.persisted)window.location.reload();});
  load();
})();
