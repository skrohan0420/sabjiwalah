(function () {
  const api=window.SabjiwalahAdmin, form=document.getElementById('cash-filters'), list=document.getElementById('cash-list'), error=document.getElementById('cash-error');
  const status=document.getElementById('cash-status'),tabs=document.getElementById('cash-tabs');
  let page=1, busy=false;
  const money=value=>new Intl.NumberFormat('en-IN',{style:'currency',currency:'INR'}).format(value);
  const time=value=>value?new Intl.DateTimeFormat('en-IN',{dateStyle:'medium',timeStyle:'short',timeZone:'Asia/Kolkata'}).format(new Date(value)):'—';
  function node(tag,text,cls) { const el=document.createElement(tag); if(text!==undefined)el.textContent=text; if(cls)el.className=cls; return el; }
  function syncTabs(){tabs?.querySelectorAll('button').forEach(button=>{button.setAttribute('aria-pressed',String(button.dataset.cashStatus===(status?.value||'')));button.disabled=busy;});}
  async function load() {
    if(busy)return; busy=true; error.hidden=true;syncTabs();
    try {
      const query=new URLSearchParams(new FormData(form));query.set('page',page);
      const {data}=await api.api('/api/v1/admin/cash?'+query);
      const totals=document.getElementById('cash-totals');totals.replaceChildren();
      for(const [label,value] of [['Collected',data.totals.collected],['Pending handover',data.totals.pending],['Reconciled',data.totals.reconciled]]){const card=node('div',undefined,'cash-total');card.append(node('span',label),node('strong',money(value)));totals.append(card);}
      list.replaceChildren();
      if(data.items.length){
        const wrapper=node('div',undefined,'admin-table-wrap admin-compact-wrap'),table=node('table',undefined,'admin-table admin-compact-table cash-ledger-table'),head=node('thead'),header=node('tr'),body=node('tbody');
        wrapper.tabIndex=0;wrapper.setAttribute('role','region');wrapper.setAttribute('aria-label','Cash ledger, scroll horizontally for all columns');
        const headings=['Order','Rider','Amount','Collected','Status','Handover','Action'];
        for(const title of headings){const th=node('th',title);th.scope='col';header.append(th);}head.append(header);
        for(const row of data.items){
          const tr=node('tr');
          const identity=node('span',row.order_number);
          const badge=node('span',row.reconciled_at?'Reconciled':'Pending','admin-badge '+(row.reconciled_at?'admin-badge-success':'admin-badge-warning'));
          const audit=node('div');audit.append(node('span',row.reconciled_at?time(row.reconciled_at):'Awaiting cash'));
          if(row.reconciled_at)audit.append(node('small',row.reconciled_by_name||'Administrator'));
          let action=node('span','—','admin-muted');
          if(!row.reconciled_at){
            const button=node('button','Confirm handover','admin-button');button.type='button';action=button;
            button.addEventListener('click',async()=>{
              if(busy)return;busy=true;syncTabs();
              try {
                if(!await api.confirm({title:'Confirm cash handover',message:'Confirm receipt of '+money(row.cash_amount)+' from '+row.rider_name+' for '+row.order_number+'.',label:'Cash received'}))return;
                button.disabled=true;
                await api.api('/api/v1/admin/cash/'+encodeURIComponent(row.uid)+'/reconcile',{method:'POST',body:{}});
              }catch(e){error.textContent=e.message+' Refresh before trying again.';error.hidden=false;return;}
              finally {busy=false;syncTabs();}
              page=1;load();
            });
          }
          const cells=[identity,node('span',row.rider_name),node('strong',money(row.cash_amount)),node('span',time(row.collected_at)),badge,audit,action];
          cells.forEach((value,index)=>{const td=node('td');td.setAttribute('data-label',headings[index]);td.append(value);tr.append(td);});body.append(tr);
        }
        table.append(head,body);wrapper.append(table);list.append(wrapper);
      }else list.append(node('p',(status?.value==='pending'?'No pending handovers. Open Reconciled to review completed handovers.':status?.value==='reconciled'?'No reconciled handovers match these filters.':'No cash collections match these filters.'),'admin-card'));
      document.getElementById('cash-page').textContent=data.pager.total+' records · Page '+page+' of '+Math.max(1,data.pager.page_count);
      document.getElementById('cash-prev').disabled=page<=1;document.getElementById('cash-next').disabled=page>=data.pager.page_count;
    }catch(e){error.textContent=e.message;error.hidden=false;}finally{busy=false;syncTabs();}
  }
  tabs?.querySelectorAll('button').forEach(button=>button.addEventListener('click',()=>{if(busy)return;status.value=button.dataset.cashStatus;page=1;load();}));
  form.addEventListener('submit',event=>{event.preventDefault();if(!busy){page=1;load();}});
  form.addEventListener('reset',()=>{setTimeout(()=>{if(!busy){page=1;load();}},0);});
  document.getElementById('cash-refresh').addEventListener('click',load);
  document.getElementById('cash-prev').addEventListener('click',()=>{if(!busy){page--;load();}});
  document.getElementById('cash-next').addEventListener('click',()=>{if(!busy){page++;load();}});
  load();
})();
