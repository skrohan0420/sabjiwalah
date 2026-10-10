(() => {
  'use strict';
  const admin=window.SabjiwalahAdmin,$=id=>document.getElementById(id),filters=$('riders-filters'),dialog=$('rider-details'),createDialog=$('rider-create'),createForm=$('rider-create-form');
  let page=1,pageCount=0,listRevision=0,listAbort,applied=new URLSearchParams(new FormData(filters));
  let selected=null,rider=null,historyPage=1,historyPages=0,detailRevision=0,detailAbort,working=false,loading=false,uncertain=true,createUncertain=false;
  const date=value=>value?new Intl.DateTimeFormat('en-IN',{dateStyle:'medium',timeStyle:'short',timeZone:'Asia/Kolkata'}).format(new Date(value)):'Not recorded';
  const title=value=>value.replaceAll('_',' ').replace(/^./,c=>c.toUpperCase());
  function node(tag,text,cls){const el=document.createElement(tag);if(text!=null)el.textContent=text;if(cls)el.className=cls;return el;}
  function error(target,err){target.textContent=[err.message,...Object.values(err.errors||{})].join(' ');target.hidden=false;}
  function labelValue(label,value){const el=node('div');el.append(node('span',label,'admin-caption'),node('strong',value));return el;}
  function listNavigation(){$('riders-prev').disabled=page<=1;$('riders-next').disabled=page>=pageCount;}
  async function loadList(){
    const revision=++listRevision;listAbort?.abort();listAbort=new AbortController();
    $('riders-error').hidden=true;$('riders-list').setAttribute('aria-busy','true');$('riders-prev').disabled=$('riders-next').disabled=true;
    const query=new URLSearchParams(applied);query.set('page',page);query.set('per_page',20);
    try{
      const {data}=await admin.api('/api/v1/admin/delivery-personnel?'+query,{signal:listAbort.signal});if(revision!==listRevision)return;
      pageCount=data.pager.page_count;if(page>Math.max(1,pageCount)){page=Math.max(1,pageCount);return loadList();}
      $('riders-summary').textContent=`${data.pager.total} delivery account${data.pager.total===1?'':'s'}`;$('riders-page').textContent=`Page ${page} of ${Math.max(1,pageCount)}`;
      const list=$('riders-list');list.replaceChildren();if(!data.items.length)list.append(node('p','No delivery personnel match these filters.','admin-card'));
      for(const row of data.items){
        const card=node('article',null,'admin-card rider-row'),identity=node('div');identity.append(node('h2',row.name),node('p',row.phone_masked||'No phone recorded','admin-caption'));
        const view=node('button','View delivery person','admin-button admin-button-secondary');view.type='button';view.addEventListener('click',()=>open(row.uid));
        card.append(identity,labelValue('Account',title(row.status)),labelValue('Availability',title(row.availability)),labelValue('Current orders',row.workload),labelValue('Completed',row.completed_deliveries),view);list.append(card);
      }
      listNavigation();
    }catch(err){if(revision===listRevision){error($('riders-error'),err);listNavigation();}}
    finally{if(revision===listRevision)$('riders-list').setAttribute('aria-busy','false');}
  }
  function controls(){
    const blocked=working||loading||uncertain||!rider;
    $('rider-status-action').disabled=blocked;$('rider-availability').disabled=$('rider-availability-save').disabled=blocked||rider?.status!=='active';
    $('rider-reload').disabled=working||loading;$('rider-close').disabled=working;
    $('rider-orders-prev').disabled=working||loading||historyPage<=1;$('rider-orders-next').disabled=working||loading||historyPage>=historyPages;
    $('rider-orders-mode').disabled=working||loading;
    $('rider-create-close').disabled=working;$('rider-create-save').disabled=working||createUncertain;
    for(const input of createForm.elements)if(input.tagName==='INPUT')input.disabled=working;
    $('riders-create').disabled=working;
  }
  function render(data){
    rider=data.personnel;$('rider-title').textContent=rider.name;
    const content=$('rider-content'),profile=node('div',null,'customer-profile');
    profile.append(labelValue('Phone',rider.phone||'Not recorded'),labelValue('Email',rider.email||'Not recorded'),labelValue('Registered (India)',date(rider.registered_at)),labelValue('Account',title(rider.status)),labelValue('Availability',title(rider.availability)),labelValue('Current orders',rider.workload),labelValue('Out for delivery',rider.out_for_delivery),labelValue('Completed deliveries',rider.completed_deliveries));content.replaceChildren(profile);
    $('rider-management').hidden=false;$('rider-status-action').textContent=rider.status==='active'?'Deactivate account':'Activate account';$('rider-status-action').classList.toggle('admin-button-danger',rider.status==='active');$('rider-availability').value=rider.base_availability;
    historyPages=data.orders.pager.page_count;$('rider-orders-page').textContent=`${data.orders.pager.total} assignment${data.orders.pager.total===1?'':'s'} · Page ${historyPage} of ${Math.max(1,historyPages)}`;
    const history=$('rider-orders');history.replaceChildren();if(!data.orders.items.length)history.append(node('p','No assignments in this view.','admin-muted'));
    for(const order of data.orders.items){
      const row=node('article',null,'admin-card customer-history-row'),heading=node('div'),link=node('a',order.order_number,'customer-order-link');
      link.href=window.Sabjiwalah.url('/admin/orders?search='+encodeURIComponent(order.order_number));heading.append(link,node('p',order.is_current?'Latest assignment':'Previous assignment','admin-caption'));
      row.append(heading,labelValue('Order status',title(order.order_status)),labelValue('Assigned (India)',date(order.assigned_at)),labelValue('Completion recorded',order.completed_at?date(order.completed_at):(order.is_current&&order.order_status==='delivered'?'Delivered; time not recorded':'Not recorded')));history.append(row);
    }
  }
  async function loadDetails(uid,targetPage=historyPage){
    const revision=++detailRevision;detailAbort?.abort();detailAbort=new AbortController();loading=true;uncertain=true;controls();$('rider-error').hidden=true;$('rider-content').setAttribute('aria-busy','true');
    try{
      const {data}=await admin.api('/api/v1/admin/delivery-personnel/'+encodeURIComponent(uid)+'?page='+targetPage+'&per_page=10&mode='+$('rider-orders-mode').value,{signal:detailAbort.signal});
      if(revision!==detailRevision||selected!==uid)return;
      if(targetPage>Math.max(1,data.orders.pager.page_count)){loading=false;return loadDetails(uid,Math.max(1,data.orders.pager.page_count));}
      historyPage=targetPage;render(data);uncertain=false;
    }catch(err){if(revision===detailRevision&&selected===uid)error($('rider-error'),err);}
    finally{if(revision===detailRevision){loading=false;$('rider-content').setAttribute('aria-busy','false');controls();}}
  }
  function open(uid){
    if(working||dialog.open||createDialog.open)return;selected=uid;rider=null;historyPage=1;historyPages=0;$('rider-orders-mode').value='current';
    $('rider-title').textContent='Delivery person';$('rider-content').replaceChildren(node('p','Loading delivery person…','admin-muted'));$('rider-orders').replaceChildren();$('rider-orders-page').textContent='';$('rider-management').hidden=true;
    dialog.showModal();loadDetails(uid,1);
  }
  function close(){if(working)return;++detailRevision;detailAbort?.abort();selected=null;rider=null;loading=false;dialog.close();$('rider-content').replaceChildren();$('rider-orders').replaceChildren();}
  async function change(field,value){
    if(working||loading||uncertain||!rider)return;
    const observed=rider,uid=selected,expected=field==='status'?observed.status:observed.base_availability;
    if(value===expected)return;
    const confirmed=await admin.confirm({title:field==='status'?(value==='inactive'?'Deactivate delivery account?':'Activate delivery account?'):'Change availability?',message:field==='status'&&value==='inactive'?`${observed.name} will lose access on their next request. ${observed.workload} current order(s) will still need staff attention.`:`Set ${observed.name}'s ${field} to ${value}?`,label:'Confirm',danger:field==='status'&&value==='inactive'});
    if(!confirmed||selected!==uid||rider!==observed||working||loading||uncertain)return;
    working=true;controls();$('rider-error').hidden=true;
    try{await admin.api('/api/v1/admin/delivery-personnel/'+encodeURIComponent(uid)+'/'+field,{method:'PATCH',body:{[field]:value,['expected_'+field]:expected}});admin.notify('Delivery account updated.');await loadDetails(uid,historyPage);await loadList();}
    catch(err){error($('rider-error'),err);if(!err.status||err.status===409||err.status>=500)uncertain=true;}
    finally{working=false;controls();}
  }
  $('rider-status-action').addEventListener('click',()=>change('status',rider?.status==='active'?'inactive':'active'));
  $('rider-availability-form').addEventListener('submit',event=>{event.preventDefault();change('availability',$('rider-availability').value);});
  $('rider-close').addEventListener('click',close);dialog.addEventListener('cancel',event=>{event.preventDefault();close();});
  $('rider-reload').addEventListener('click',()=>{if(!working&&!loading&&selected)loadDetails(selected,historyPage);});
  $('rider-orders-prev').addEventListener('click',()=>{if(!working&&!loading&&historyPage>1)loadDetails(selected,historyPage-1);});$('rider-orders-next').addEventListener('click',()=>{if(!working&&!loading&&historyPage<historyPages)loadDetails(selected,historyPage+1);});
  $('rider-orders-mode').addEventListener('change',()=>{if(!working&&!loading&&selected)loadDetails(selected,1);});
  filters.addEventListener('submit',event=>{event.preventDefault();applied=new URLSearchParams(new FormData(filters));page=1;loadList();});filters.addEventListener('reset',()=>{applied=new URLSearchParams({search:'',status:''});page=1;loadList();});
  $('riders-refresh').addEventListener('click',loadList);$('riders-prev').addEventListener('click',()=>{if(page>1){page--;loadList();}});$('riders-next').addEventListener('click',()=>{if(page<pageCount){page++;loadList();}});
  $('riders-create').addEventListener('click',()=>{if(working||dialog.open||createDialog.open)return;createForm.reset();createUncertain=false;$('rider-create-error').hidden=true;$('rider-create-uncertain').hidden=true;controls();createDialog.showModal();});
  async function closeCreate(){if(working)return;const dirty=[...createForm.elements].some(input=>input.tagName==='INPUT'&&input.value.trim());if(dirty&&!createUncertain&&!await admin.confirm({title:'Discard account draft?',message:'The unsaved account details will be cleared.',label:'Discard'}))return;if(working)return;createDialog.close();createForm.reset();}
  $('rider-create-close').addEventListener('click',closeCreate);createDialog.addEventListener('cancel',event=>{event.preventDefault();closeCreate();});
  createForm.addEventListener('submit',async event=>{
    event.preventDefault();if(working||createUncertain||!createForm.reportValidity())return;
    const body=Object.fromEntries(new FormData(createForm));working=true;controls();$('rider-create-error').hidden=true;
    try{const {data}=await admin.api('/api/v1/admin/delivery-personnel',{method:'POST',body});createDialog.close();createForm.reset();admin.notify('Inactive account created. Review and activate it from its details.');working=false;open(data.uid);await loadList();}
    catch(err){error($('rider-create-error'),err);if(!err.status||err.status===409||err.status>=500){createUncertain=true;$('rider-create-uncertain').hidden=false;}}
    finally{working=false;controls();}
  });
  window.addEventListener('pagehide',()=>{++listRevision;++detailRevision;listAbort?.abort();detailAbort?.abort();});
  window.addEventListener('beforeunload',event=>{if(working||(createDialog.open&&[...createForm.elements].some(input=>input.tagName==='INPUT'&&input.value.trim()))){event.preventDefault();event.returnValue='';}});
  loadList();
})();
