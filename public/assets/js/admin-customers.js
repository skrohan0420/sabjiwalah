(() => {
  'use strict';
  const admin = window.SabjiwalahAdmin, $ = id => document.getElementById(id), filters = $('customers-filters'), dialog = $('customer-details');
  let page = 1, pageCount = 0, listRevision = 0, listAbort, applied = new URLSearchParams(new FormData(filters));
  let selected = null, customer = null, historyPage = 1, historyPages = 0, detailRevision = 0, detailAbort, working = false, loading = false, uncertain = false;
  const date = value => value ? new Intl.DateTimeFormat('en-IN', {dateStyle:'medium',timeZone:'Asia/Kolkata'}).format(new Date(value)) : 'Not recorded';
  const money = value => new Intl.NumberFormat('en-IN', {style:'currency',currency:'INR'}).format(value);
  function node(tag, text, cls) {const el=document.createElement(tag);if(text!=null)el.textContent=text;if(cls)el.className=cls;return el;}
  function error(target, err) {target.textContent=[err.message,...Object.values(err.errors||{})].join(' ');target.hidden=false;}
  function labelValue(label, value) {const el=node('div');el.append(node('span',label,'admin-caption'),node('strong',value));return el;}
  function listNavigation() {$('customers-prev').disabled=page<=1;$('customers-next').disabled=page>=pageCount;}
  async function loadList() {
    const revision=++listRevision;listAbort?.abort();listAbort=new AbortController();
    $('customers-error').hidden=true;$('customers-list').setAttribute('aria-busy','true');$('customers-prev').disabled=$('customers-next').disabled=true;
    const query=new URLSearchParams(applied);query.set('page',page);query.set('per_page',20);
    try {
      const {data}=await admin.api('/api/v1/admin/customers?'+query,{signal:listAbort.signal});if(revision!==listRevision)return;
      pageCount=data.pager.page_count;if(page>Math.max(1,pageCount)){page=Math.max(1,pageCount);return loadList();}
      $('customers-summary').textContent=`${data.pager.total} customer${data.pager.total===1?'':'s'}`;$('customers-page').textContent=`Page ${page} of ${Math.max(1,pageCount)}`;
      const list=$('customers-list');list.replaceChildren();if(!data.items.length)list.append(node('p','No customers match these filters.','admin-card'));
      for(const row of data.items) {
        const card=node('article',null,'admin-card customer-row'),identity=node('div');identity.append(node('h2',row.name),node('p',row.phone_masked||'No phone recorded','admin-caption'));
        const view=node('button','View account','admin-button admin-button-secondary');view.type='button';view.addEventListener('click',()=>open(row.uid));
        card.append(identity,labelValue('Registered',date(row.registered_at)),labelValue('Orders',row.order_count),node('span',row.status==='active'?'Active':'Inactive','admin-badge '+(row.status==='active'?'admin-badge-success':'')),view);list.append(card);
      }
      listNavigation();
    } catch(err) {if(revision===listRevision){error($('customers-error'),err);listNavigation();}}
    finally{if(revision===listRevision)$('customers-list').setAttribute('aria-busy','false');}
  }
  function controls() {
    $('customer-status-action').disabled=working||loading||uncertain||!customer;
    $('customer-reload').disabled=working||loading;$('customer-close').disabled=working;
    $('customer-orders-prev').disabled=working||loading||historyPage<=1;
    $('customer-orders-next').disabled=working||loading||historyPage>=historyPages;
  }
  function render(data) {
    customer=data.customer;
    $('customer-title').textContent=customer.name;
    const content=$('customer-content');content.replaceChildren();
    const contact=node('div',null,'customer-profile');contact.append(labelValue('Phone',customer.phone||'Not recorded'),labelValue('Email',customer.email||'Not recorded'),labelValue('Registered (India)',date(customer.registered_at)),labelValue('Account status',customer.status==='active'?'Active':'Inactive'),labelValue('Total orders',customer.order_count));content.append(contact);
    $('customer-management').hidden=false;$('customer-status-action').textContent=customer.status==='active'?'Deactivate account':'Activate account';
    $('customer-status-action').classList.toggle('admin-button-danger',customer.status==='active');
    historyPages=data.orders.pager.page_count;$('customer-orders-page').textContent=`Page ${historyPage} of ${Math.max(1,historyPages)}`;
    const history=$('customer-orders');history.replaceChildren();if(!data.orders.items.length)history.append(node('p','No orders recorded for this customer.','admin-muted'));
    for(const order of data.orders.items){
      const row=node('article',null,'admin-card customer-history-row');
      const link=node('a',order.order_number,'customer-order-link');link.href=window.Sabjiwalah.url('/admin/orders?search='+encodeURIComponent(order.order_number));
      const heading=node('div');heading.append(link,node('p',date(order.placed_at),'admin-caption'));
      row.append(heading,labelValue('Total',money(order.total_amount)),labelValue('Order status',order.order_status.replaceAll('_',' ')),labelValue('Payment',order.payment_status.replaceAll('_',' ')));history.append(row);
    }
  }
  async function loadDetails(uid, targetPage=historyPage) {
    const revision=++detailRevision;detailAbort?.abort();detailAbort=new AbortController();loading=true;uncertain=true;controls();
    $('customer-error').hidden=true;$('customer-content').setAttribute('aria-busy','true');
    try {
      const {data}=await admin.api('/api/v1/admin/customers/'+encodeURIComponent(uid)+'?page='+targetPage+'&per_page=10',{signal:detailAbort.signal});
      if(revision!==detailRevision||selected!==uid)return;
      if(targetPage>Math.max(1,data.orders.pager.page_count)){loading=false;return loadDetails(uid,Math.max(1,data.orders.pager.page_count));}
      historyPage=targetPage;render(data);uncertain=false;
    } catch(err){if(revision===detailRevision&&selected===uid)error($('customer-error'),err);}
    finally{if(revision===detailRevision){loading=false;$('customer-content').setAttribute('aria-busy','false');controls();}}
  }
  function open(uid) {
    if(working||dialog.open)return;selected=uid;customer=null;historyPage=1;historyPages=0;
    $('customer-title').textContent='Customer details';$('customer-content').replaceChildren(node('p','Loading customer…','admin-muted'));$('customer-orders').replaceChildren();$('customer-orders-page').textContent='';$('customer-management').hidden=true;
    dialog.showModal();loadDetails(uid,1);
  }
  function close(){if(working)return;++detailRevision;detailAbort?.abort();selected=null;customer=null;loading=false;dialog.close();$('customer-content').replaceChildren();$('customer-orders').replaceChildren();}
  $('customer-status-action').addEventListener('click',async()=>{
    if(working||loading||uncertain||!customer)return;
    const observed=customer,uid=selected,status=customer.status==='active'?'inactive':'active';
    const confirmed=await admin.confirm({title:status==='inactive'?'Deactivate customer?':'Activate customer?',message:status==='inactive'?`${customer.name} will lose account access on their next request. Existing orders will remain unchanged.`:`${customer.name} will be able to log in again.`,label:status==='inactive'?'Deactivate':'Activate',danger:status==='inactive'});
    if(!confirmed||selected!==uid||working||loading||uncertain)return;
    working=true;controls();$('customer-error').hidden=true;
    try {
      await admin.api('/api/v1/admin/customers/'+encodeURIComponent(uid)+'/status',{method:'PATCH',body:{status,expected_status:observed.status}});
      admin.notify('Customer account status updated.');await loadDetails(uid,historyPage);await loadList();
    } catch(err){error($('customer-error'),err);if(!err.status||err.status===409||err.status>=500)uncertain=true;}
    finally{working=false;controls();}
  });
  $('customer-close').addEventListener('click',close);dialog.addEventListener('cancel',event=>{event.preventDefault();close();});
  $('customer-reload').addEventListener('click',()=>{if(!working&&!loading&&selected)loadDetails(selected,historyPage);});
  $('customer-orders-prev').addEventListener('click',()=>{if(!working&&!loading&&historyPage>1)loadDetails(selected,historyPage-1);});
  $('customer-orders-next').addEventListener('click',()=>{if(!working&&!loading&&historyPage<historyPages)loadDetails(selected,historyPage+1);});
  filters.addEventListener('submit',event=>{event.preventDefault();applied=new URLSearchParams(new FormData(filters));page=1;loadList();});
  filters.addEventListener('reset',()=>{applied=new URLSearchParams({search:'',status:'',sort:'created_at',dir:'desc'});page=1;loadList();});
  $('customers-refresh').addEventListener('click',loadList);
  $('customers-prev').addEventListener('click',()=>{if(page>1){page--;loadList();}});$('customers-next').addEventListener('click',()=>{if(page<pageCount){page++;loadList();}});
  window.addEventListener('pagehide',()=>{++listRevision;++detailRevision;listAbort?.abort();detailAbort?.abort();});
  window.addEventListener('beforeunload',event=>{if(working){event.preventDefault();event.returnValue='';}});
  loadList();
})();
