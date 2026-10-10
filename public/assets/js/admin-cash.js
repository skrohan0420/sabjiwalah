(function () {
  const api=window.SabjiwalahAdmin, form=document.getElementById('cash-filters'), list=document.getElementById('cash-list'), error=document.getElementById('cash-error');
  let page=1, busy=false;
  const money=value=>new Intl.NumberFormat('en-IN',{style:'currency',currency:'INR'}).format(value);
  const time=value=>value?new Intl.DateTimeFormat('en-IN',{dateStyle:'medium',timeStyle:'short',timeZone:'Asia/Kolkata'}).format(new Date(value)):'Pending';
  function node(tag,text,cls) { const el=document.createElement(tag); el.textContent=text; if(cls)el.className=cls; return el; }
  async function load() {
    if(busy)return; busy=true; error.hidden=true;
    try {
      const query=new URLSearchParams(new FormData(form));query.set('page',page);
      const {data}=await api.api('/api/v1/admin/cash?'+query);
      document.getElementById('cash-totals').textContent='Collected: '+money(data.totals.collected)+' · Pending handover: '+money(data.totals.pending)+' · Reconciled: '+money(data.totals.reconciled);
      list.replaceChildren();
      for(const row of data.items) {
        const card=node('article','','admin-card cash-record');card.append(node('h2',row.order_number),node('p',row.rider_name+' · '+money(row.cash_amount)),node('p','Collected '+time(row.collected_at)));
        if(row.reconciled_at)card.append(node('p','Reconciled '+time(row.reconciled_at)+' by '+row.reconciled_by_name));
        else {const button=node('button','Confirm cash handover','admin-button'); button.type='button';button.addEventListener('click',async()=>{
          if(busy)return;busy=true;
          try {
            if(!await api.confirm({title:'Confirm cash handover',message:'Confirm receipt of '+money(row.cash_amount)+' from '+row.rider_name+' for '+row.order_number+'.',label:'Cash received'}))return;
            button.disabled=true;
            await api.api('/api/v1/admin/cash/'+encodeURIComponent(row.uid)+'/reconcile',{method:'POST',body:{}});
          }catch(e){error.textContent=e.message+' Refresh before trying again.';error.hidden=false;return;}
          finally {busy=false;}
          load();
        });card.append(button);}list.append(card);
      }
      if(!data.items.length)list.append(node('p','No cash collections match these filters.','admin-card'));
      document.getElementById('cash-page').textContent='Page '+page+' of '+Math.max(1,data.pager.page_count);
      document.getElementById('cash-prev').disabled=page<=1;document.getElementById('cash-next').disabled=page>=data.pager.page_count;
    }catch(e){error.textContent=e.message;error.hidden=false;}finally{busy=false;}
  }
  form.addEventListener('submit',event=>{event.preventDefault();if(!busy){page=1;load();}});
  document.getElementById('cash-refresh').addEventListener('click',load);
  document.getElementById('cash-prev').addEventListener('click',()=>{if(!busy){page--;load();}});
  document.getElementById('cash-next').addEventListener('click',()=>{if(!busy){page++;load();}});
  load();
})();
