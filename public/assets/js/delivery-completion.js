(function () {
  const list=document.getElementById('delivery-list'), message=document.getElementById('delivery-message');let busy=false, uncertain=false, alive=true;
  function el(tag,text,cls) { const node=document.createElement(tag);node.textContent=text;if(cls)node.className=cls;return node; }
  async function api(path,body,method='POST') {
    const headers={Accept:'application/json'};
    if(body!==undefined){const token=await fetch(window.Sabjiwalah.url('api/v1/csrf'),{credentials:'same-origin',cache:'no-store'});const csrf=await token.json();if(!token.ok||!csrf.success)throw new Error('Reload before trying again.');headers[csrf.data.header_name]=csrf.data.token_value;headers['Content-Type']='application/json';}
    const response=await fetch(window.Sabjiwalah.url(path),{credentials:'same-origin',cache:'no-store',method:body===undefined?'GET':method,headers,body:body===undefined?undefined:JSON.stringify(body)});
    const data=await response.json();if(!response.ok||!data.success)throw Object.assign(new Error(data.message||'Request failed.'),{status:response.status});return data;
  }
  async function load() {
    if(busy)return;busy=true;message.hidden=true;
    try {
      const {data}=await api('api/v1/delivery/orders');if(!alive)return;list.replaceChildren();uncertain=false;
      const active=data.items.filter(row=>['ready_for_delivery','out_for_delivery'].includes(row.order_status));
      document.getElementById('delivery-count').textContent='Assigned orders: '+active.length;
      for(const order of active) {
        const card=el('article','','admin-card delivery-record');card.append(el('h2',order.order_number),el('p',order.order_status.replaceAll('_',' ')),el('p',order.customer_name+' · '+order.customer_phone),el('p',[order.address_line,order.city,order.postal_code].join(', ')));
        const run=async(body,path,method)=>{if(busy||uncertain)return;busy=true;message.hidden=true;card.querySelectorAll('button').forEach(button=>button.disabled=true);
          try {await api(path,body,method);busy=false;await load();}
          catch(e){uncertain=!e.status||e.status>=500||[401,403,409].includes(e.status);if(e.status===401||e.status===403)list.replaceChildren();if(alive){message.textContent=e.message+(uncertain?' Refresh orders before trying again.':'');message.hidden=false;}}
          finally{busy=false;card.querySelectorAll('button').forEach(button=>button.disabled=uncertain);}
        };
        const statusPath='api/v1/delivery/orders/'+encodeURIComponent(order.uid)+'/status';
        if(order.order_status==='ready_for_delivery') {const pickup=el('button','Record pickup','admin-button');pickup.type='button';pickup.addEventListener('click',()=>run({status:'out_for_delivery',expected_status:'ready_for_delivery'},statusPath,'PATCH'));card.append(pickup);}
        else {
          const form=el('form','','rider-availability-form'), label=el('label','Customer delivery PIN','admin-field'),pin=el('input','');pin.inputMode='numeric';pin.autocomplete='off';pin.type='password';pin.pattern='[0-9]{6}';pin.maxLength=6;pin.required=true;label.append(pin);
          const cashLabel=el('label', ' I received the full COD amount of Rs '+order.total_amount),cash=el('input','');cash.type='checkbox';cash.required=order.payment_method==='cod';cashLabel.prepend(cash);
          const submit=el('button','Verify and complete delivery','admin-button');submit.type='submit';form.append(label);if(order.payment_method==='cod')form.append(cashLabel);form.append(submit);
          form.addEventListener('submit',event=>{event.preventDefault();if(busy||uncertain)return;const entered=pin.value;pin.value='';run({pin:entered,cash_received:cash.checked},'api/v1/delivery/orders/'+encodeURIComponent(order.uid)+'/complete','POST');});card.append(form);
          const failure=el('form','','rider-availability-form'),reasonLabel=el('label','Failed delivery reason','admin-field'),reason=el('textarea','');reason.required=true;reason.maxLength=1000;reasonLabel.append(reason);const fail=el('button','Report failed delivery','admin-button admin-button-danger');fail.type='submit';failure.append(reasonLabel,fail);
          failure.addEventListener('submit',event=>{event.preventDefault();if(window.confirm('Mark '+order.order_number+' as a failed delivery?'))run({status:'delivery_failed',notes:reason.value.trim(),expected_status:'out_for_delivery'},statusPath,'PATCH');});card.append(failure);
        }list.append(card);
      }
      if(!list.children.length)list.append(el('p','No active deliveries.','admin-card'));
    }catch(e){if(alive){if(e.status===401||e.status===403)list.replaceChildren();message.textContent=e.message;message.hidden=false;}}finally{busy=false;}
  }
  document.getElementById('delivery-refresh').addEventListener('click',()=>{if(window.confirm('Refresh orders and discard any unfinished entries?'))load();});
  document.getElementById('delivery-logout').addEventListener('click',async()=>{if(busy)return;busy=true;try{await api('api/v1/auth/logout',{});window.location.assign(window.Sabjiwalah.url('login'));}catch(e){message.textContent=e.message;message.hidden=false;}finally{busy=false;}});
  window.addEventListener('pagehide',()=>{alive=false;list.querySelectorAll('input').forEach(input=>input.value='');list.replaceChildren();});
  window.addEventListener('pageshow',event=>{if(event.persisted)window.location.reload();});load();
})();
