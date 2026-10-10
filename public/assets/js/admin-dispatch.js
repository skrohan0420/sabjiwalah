(() => {
  'use strict';
  const ui=window.SabjiwalahAdmin,$=id=>document.getElementById(id),form=$('dispatch-filters'),dialog=$('dispatch-assign');
  let filters=new URLSearchParams(new FormData(form)),page=1,pages=0,revision=0,listAbort,inFlight=false,pending=false,timer,stopped=false;
  let selected=null,observed=null,detailRevision=0,detailAbort,candidatePage=1,candidatePages=0,candidateSearch='',loading=false,working=false,uncertain=true;
  function node(tag,text,cls){const el=document.createElement(tag);if(text!=null)el.textContent=text;if(cls)el.className=cls;return el;}
  function error(el,err){el.textContent=[err.message,...Object.values(err.errors||{})].join(' ');el.hidden=false;}
  function nav(){$('dispatch-prev').disabled=page<=1;$('dispatch-next').disabled=page>=pages;}
  function schedule(){clearTimeout(timer);if(!stopped&&!document.hidden)timer=setTimeout(loadList,8000);}
  async function loadList(){
    clearTimeout(timer);if(stopped)return;if(inFlight){pending=true;return;}inFlight=true;const current=revision;listAbort=new AbortController();$('dispatch-error').hidden=true;$('dispatch-list').setAttribute('aria-busy','true');$('dispatch-prev').disabled=$('dispatch-next').disabled=true;
    const query=new URLSearchParams(filters);query.set('page',page);query.set('per_page',20);
    try{const {data}=await ui.api('/api/v1/admin/dispatch?'+query,{signal:listAbort.signal});if(current!==revision)return;pages=data.pager.page_count;if(page>Math.max(1,pages)){page=Math.max(1,pages);pending=true;return;}
      $('dispatch-summary').textContent=`${data.pager.total} orders in dispatch · Updated ${new Intl.DateTimeFormat('en-IN',{timeStyle:'short',timeZone:'Asia/Kolkata'}).format(new Date())} IST`;$('dispatch-page').textContent=`Page ${page} of ${Math.max(1,pages)}`;
      const list=$('dispatch-list');list.replaceChildren();if(!data.items.length)list.append(node('p','No orders match these filters.','admin-card'));
      for(const order of data.items){const row=node('article',null,'admin-card dispatch-row'),identity=node('div'),link=node('a',order.order_number);link.href=window.Sabjiwalah.url('/admin/orders?search='+encodeURIComponent(order.order_number));identity.append(link,node('p',order.order_status==='ready_for_delivery'?'Awaiting pickup':'Out for delivery','admin-caption'));
        const staff=node('div');staff.append(node('strong',order.rider_name||'Unassigned'),node('p',order.rider_status==='inactive'?'Inactive account — review assignment':'','admin-caption'));
        const age=node('div');age.append(node('strong',`${order.waiting_minutes} min in status`));if(order.delayed)age.append(node('span','Needs attention','admin-badge admin-badge-warning'));
        row.append(identity,staff,age);if(order.order_status==='ready_for_delivery'){const button=node('button',order.assignment_uid?'Reassign':'Assign','admin-button');button.type='button';button.addEventListener('click',()=>open(order));row.append(button);}else row.append(node('span','Pickup recorded','admin-badge admin-badge-success'));list.append(row);
        // Never replace a selection while staff choose a rider. A changed assignment disables its action.
        if(selected===order.uid&&observed&&(observed.assignment_uid!==order.assignment_uid||order.order_status!=='ready_for_delivery')){uncertain=true;$('dispatch-assign-error').textContent='Order or assignment changed. Reload before assigning.';$('dispatch-assign-error').hidden=false;controls();}
      }nav();
    }catch(err){if(current===revision&&!stopped){error($('dispatch-error'),err);if(err.status===401||err.status===403)stopped=true;nav();}}
    finally{inFlight=false;$('dispatch-list').setAttribute('aria-busy','false');if(pending&&!stopped){pending=false;loadList();}else schedule();}
  }
  function controls(){const blocked=working||loading||uncertain||!observed;$('dispatch-close').disabled=working;$('dispatch-reload').disabled=working||loading;$('dispatch-candidate-search').disabled=$('dispatch-candidate-search-button').disabled=working||loading;$('dispatch-candidate-prev').disabled=working||loading||candidatePage<=1;$('dispatch-candidate-next').disabled=working||loading||candidatePage>=candidatePages;for(const button of $('dispatch-candidates').querySelectorAll('button'))button.disabled=blocked;}
  async function loadDetails(reloadOrder=true){
    if(!selected)return;const uid=selected,current=++detailRevision;detailAbort?.abort();detailAbort=new AbortController();loading=true;uncertain=true;controls();$('dispatch-assign-error').hidden=true;
    try{
      if(reloadOrder){const {data}=await ui.api('/api/v1/admin/orders/'+encodeURIComponent(uid),{signal:detailAbort.signal});if(current!==detailRevision||selected!==uid)return;observed={uid,order_number:data.order.order_number,order_status:data.order.order_status,assignment_uid:data.assignments[0]?.uid||null,rider_name:data.assignments[0]?.delivery_name||null};if(observed.order_status!=='ready_for_delivery')throw new Error('This order is no longer ready for assignment. Close this dialog to review its progress.');$('dispatch-title').textContent=observed.order_number;$('dispatch-current').textContent=observed.rider_name?'Currently assigned to '+observed.rider_name:'No delivery person assigned.';}
      const query=new URLSearchParams({page:candidatePage,per_page:10,search:candidateSearch});const {data}=await ui.api('/api/v1/admin/dispatch/candidates?'+query,{signal:detailAbort.signal});if(current!==detailRevision||selected!==uid)return;
      candidatePages=data.pager.page_count;if(candidatePage>Math.max(1,candidatePages)){candidatePage=Math.max(1,candidatePages);loading=false;return loadDetails(false);}
      const list=$('dispatch-candidates');list.replaceChildren();if(!data.items.length)list.append(node('p','No available personnel found. Set an active, unassigned person to available in Delivery Management, then reload.','admin-muted'));
      for(const rider of data.items){const row=node('div',null,'dispatch-candidate'),button=node('button','Assign','admin-button');button.type='button';button.addEventListener('click',()=>assign(rider));row.append(node('strong',rider.name),button);list.append(row);}uncertain=false;$('dispatch-candidate-page').textContent=`${data.pager.total} available · Page ${candidatePage} of ${Math.max(1,candidatePages)}`;
    }catch(err){if(current===detailRevision&&selected===uid)error($('dispatch-assign-error'),err);}
    finally{if(current===detailRevision){loading=false;controls();}}
  }
  function open(order){if(working||dialog.open)return;selected=order.uid;observed=null;candidatePage=1;candidatePages=0;candidateSearch='';$('dispatch-candidate-search').value='';$('dispatch-title').textContent=order.order_number;$('dispatch-current').textContent='Loading latest assignment…';$('dispatch-candidates').replaceChildren();$('dispatch-candidate-page').textContent='';dialog.showModal();loadDetails();}
  function close(){if(working)return;++detailRevision;detailAbort?.abort();selected=null;observed=null;loading=false;dialog.close();$('dispatch-candidates').replaceChildren();}
  async function assign(rider){if(working||loading||uncertain||!observed)return;const snapshot=observed,uid=selected;const ok=await ui.confirm({title:snapshot.assignment_uid?'Reassign order?':'Assign order?',message:`Assign ${snapshot.order_number} to ${rider.name}? ${snapshot.assignment_uid?'The previous person will lose order access.':''}`,label:'Assign'});if(!ok||working||loading||uncertain||selected!==uid||observed!==snapshot)return;working=true;controls();
    try{await ui.api('/api/v1/admin/orders/'+encodeURIComponent(uid)+'/assignment',{method:'POST',body:{rider_uid:rider.uid,expected_assignment_uid:snapshot.assignment_uid||'none'}});ui.notify('Delivery assignment saved.');working=false;close();loadList();}
    catch(err){error($('dispatch-assign-error'),err);if(!err.status||err.status===409||err.status>=500)uncertain=true;}
    finally{working=false;controls();}
  }
  function changeFilters(){++revision;listAbort?.abort();page=1;loadList();}
  form.addEventListener('submit',event=>{event.preventDefault();filters=new URLSearchParams(new FormData(form));changeFilters();});form.addEventListener('reset',()=>{filters=new URLSearchParams();changeFilters();});
  $('dispatch-refresh').addEventListener('click',loadList);$('dispatch-prev').addEventListener('click',()=>{if(page>1){page--;++revision;listAbort?.abort();loadList();}});$('dispatch-next').addEventListener('click',()=>{if(page<pages){page++;++revision;listAbort?.abort();loadList();}});
  $('dispatch-close').addEventListener('click',close);dialog.addEventListener('cancel',event=>{event.preventDefault();close();});$('dispatch-reload').addEventListener('click',()=>{if(!working&&!loading){candidatePage=1;loadDetails();}});
  $('dispatch-candidate-filters').addEventListener('submit',event=>{event.preventDefault();if(working||loading)return;candidateSearch=$('dispatch-candidate-search').value.trim();candidatePage=1;loadDetails();});
  $('dispatch-candidate-prev').addEventListener('click',()=>{if(!working&&!loading&&candidatePage>1){candidatePage--;loadDetails();}});$('dispatch-candidate-next').addEventListener('click',()=>{if(!working&&!loading&&candidatePage<candidatePages){candidatePage++;loadDetails();}});
  document.addEventListener('visibilitychange',()=>{if(document.hidden)clearTimeout(timer);else if(!stopped)loadList();});window.addEventListener('pagehide',()=>{stopped=true;++revision;++detailRevision;clearTimeout(timer);listAbort?.abort();detailAbort?.abort();});window.addEventListener('beforeunload',event=>{if(working){event.preventDefault();event.returnValue='';}});loadList();
})();
