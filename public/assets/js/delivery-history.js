(() => {
  'use strict';
  const $=id=>document.getElementById(id),form=$('rider-history-filters'),profile=$('rider-profile-content');if(!form&&!profile)return;
  const ui=window.SabjiwalahDelivery,target=profile||$('rider-history-list'),message=$(profile?'rider-profile-message':'rider-history-message'),refresh=$(profile?'rider-profile-refresh':'rider-history-refresh');
  let alive=true,inFlight=false,pending=false,revision=0,page=1,pages=0,filters=new URLSearchParams();
  const money=new Intl.NumberFormat('en-IN',{style:'currency',currency:'INR'}),date=value=>value&&Number.isFinite(Date.parse(value))?new Date(value).toLocaleString('en-IN',{timeZone:'Asia/Kolkata'})+' IST':'Time not recorded';
  const node=(tag,text,cls)=>{const el=document.createElement(tag);el.textContent=text;if(cls)el.className=cls;return el;};
  function nav(){if(form){$('rider-history-prev').disabled=!alive||inFlight||page<=1;$('rider-history-next').disabled=!alive||inFlight||page>=pages;}}
  function renderHistory(data){
    if(!Array.isArray(data?.items)||!data.pager)throw new Error('Unexpected history response. Refresh to try again.');
    pages=data.pager.page_count;if(page>Math.max(1,pages)){page=Math.max(1,pages);pending=true;return;}
    target.replaceChildren();
    for(const row of data.items){const card=node('article','','delivery-panel delivery-record');card.append(node('h2',row.order_number),node('p',row.order_status==='delivered'?'Delivered':'Delivery unsuccessful','delivery-status'),node('p','Assigned '+date(row.assigned_at),'delivery-note'));
      if(!row.is_current_assignment&&!row.credited_delivery)card.append(node('p','Reassigned before completion. This order outcome is not credited to you.','delivery-note'));
      card.append(node('p',(row.order_status==='delivered'?'Delivery date: ':'Failure recorded: ')+date(row.outcome_at)),node('p',row.amount_collected===null?'Cash collection: no verified cash record for your assignment.':'Cash collected: '+money.format(Number(row.amount_collected))));target.append(card);}
    if(!data.items.length)target.append(node('p','No completed or failed deliveries match these filters.','delivery-panel'));
    $('rider-history-page').textContent=`Page ${page} of ${Math.max(1,pages)} · ${data.pager.total} ${data.pager.total === 1 ? 'order' : 'orders'}`;
    message.textContent='History updated '+date(new Date().toISOString())+'.';
  }
  function renderProfile(data){
    if(!data||typeof data.name!=='string'||!data.cod)throw new Error('Unexpected profile response. Refresh to try again.');
    const card=node('section','','delivery-panel'),facts=node('dl','','delivery-profile-facts');
    card.append(node('h2',data.name),node('p','Delivery partner'));
    for(const [label,value] of [['Phone',data.phone],['Account status',data.account_status],['Availability',data.availability]]){const row=node('div','');row.append(node('dt',label),node('dd',value));facts.append(row);}card.append(facts);
    const stats=node('dl','','delivery-stats');
    for(const [label,value] of [['Total completed deliveries',data.completed_total],['Deliveries today',data.completed_today],['COD collected today',money.format(Number(data.cod_collected_today))],['Total COD collected',money.format(Number(data.cod.collected))],['Awaiting cash handover',money.format(Number(data.cod.pending))],['Cash handover recorded',money.format(Number(data.cod.reconciled))]]){const row=node('div','');row.append(node('dt',label),node('dd',String(value)));stats.append(row);}
    target.replaceChildren(card,stats);message.textContent='Updated '+date(data.generated_at)+' · Today: '+data.day+' (India).';
    $('delivery-rider-name').textContent=data.name;
  }
  async function load(){
    if(!alive||document.hidden)return;if(navigator.onLine===false){message.textContent='You are offline. Reconnect and refresh; displayed information may be outdated.';return;}if(ui.isBusy())return;if(inFlight){pending=true;return;}
    inFlight=true;const current=revision;refresh.disabled=true;target.setAttribute('aria-busy','true');nav();message.textContent='Loading '+(profile?'your profile':'delivery history')+'…';
    const query=new URLSearchParams(filters);query.set('page',page);query.set('per_page',20);
    try{const {data}=await ui.api('api/v1/delivery/'+(profile?'profile':'history?'+query));if(!alive||current!==revision)return;profile?renderProfile(data):renderHistory(data);}
    catch(error){if(alive&&current===revision){if([401,403].includes(error.status)){target.replaceChildren();alive=false;pending=false;}message.textContent=ui.errorText(error)+' Displayed information may be outdated.';}}
    finally{inFlight=false;refresh.disabled=!alive;target.setAttribute('aria-busy','false');nav();if(pending&&alive){pending=false;load();}}
  }
  function change(){++revision;page=1;pages=0;target.replaceChildren();$('rider-history-page').textContent='';load();}
  if(form){form.addEventListener('submit',event=>{event.preventDefault();filters=new URLSearchParams(new FormData(form));change();});form.addEventListener('reset',()=>{filters=new URLSearchParams();change();});
    $('rider-history-prev').addEventListener('click',()=>{if(page>1&&!inFlight){page--;++revision;load();}});$('rider-history-next').addEventListener('click',()=>{if(page<pages&&!inFlight){page++;++revision;load();}});}
  refresh.addEventListener('click',load);
  document.addEventListener('visibilitychange',()=>{if(document.hidden){++revision;pending=false;target.replaceChildren();}else load();});
  window.addEventListener('offline',()=>{++revision;pending=false;message.textContent='You are offline. Displayed information may be outdated. Reconnect to refresh.';});
  window.addEventListener('online',load);
  window.addEventListener('pagehide',()=>{alive=false;++revision;pending=false;target.replaceChildren();});load();
})();
