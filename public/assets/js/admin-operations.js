(() => {
  'use strict';
  const queues = document.getElementById('operations-queues');
  if (!queues) return;
  const ui = window.SabjiwalahAdmin, workspace = window.SabjiwalahOrderWorkspace;
  const errorBox = document.getElementById('operations-feedback') || document.getElementById('orders-error');
  const list = document.getElementById('orders-list');
  let alive = true, writing = false, panel, panelRevision = 0, candidatePage = 1, selectedOrder, expectedAssignment, candidateSearch = '', loading = false, offerAssignment;
  const blocked = new Set();
  let candidateTimer;
  function scheduleCandidates(){
    clearTimeout(candidateTimer);
    if(alive&&panel)candidateTimer=setTimeout(()=>{
      if(document.hidden||writing||loading){scheduleCandidates();return;}
      loadCandidates();
    },20000);
  }
  function node(tag, text = '', cls = '') { const el = document.createElement(tag); el.textContent = text; if (cls) el.className = cls; return el; }
  function error(error) { errorBox.hidden = false; errorBox.textContent = error.message || 'This operation could not be completed. Refresh to review the current state.'; }
  function controls() {
    queues.querySelectorAll('button').forEach(button => button.disabled = writing);
    list.querySelectorAll('button').forEach(button => button.disabled = writing);
  }
  function closePanel() { if (writing) return; clearTimeout(candidateTimer); panelRevision++; panel?.close?.(); panel?.remove(); panel = null; selectedOrder = null; loading = false; }
  function renderQueues(counts) {
    const current = document.getElementById('operations-queue').value;
    queues.querySelectorAll('[data-queue]').forEach(button => {
      button.setAttribute('aria-pressed', String(button.dataset.queue === current));
      button.querySelector('[data-queue-count]').textContent = String(counts[button.dataset.queue] ?? 0);
    });
  }
  async function transition(order, button) {
    if (writing || blocked.has(order.uid) || !alive) return;
    closePanel(); writing = true; controls(); ui.busy(button, true); errorBox.hidden = true;
    const status = order.order_status === 'preparing' ? 'ready_for_delivery' : 'preparing';
    const body = { status, expected_status: order.order_status };
    if (order.order_status === 'pending') body.action = 'accept_prepare';
    try {
      await ui.api('/api/v1/admin/orders/' + encodeURIComponent(order.uid) + '/status', { method: 'PATCH', body });
      if (!alive) return;
      ui.notify(status === 'ready_for_delivery' ? 'Package ready for pickup.' : 'Preparation started. Choose a rider while the package is prepared.');
      if (status === 'preparing' || (status === 'ready_for_delivery' && !order.assigned_delivery_name)) offerAssignment = order.uid;
    } catch (failure) {
      if (!alive) return;
      if (!failure.status || failure.status >= 500) { blocked.add(order.uid); failure.message += ' Review this order before retrying; the result may be uncertain.'; }
      error(failure);
    } finally {
      writing = false;
      if (alive) { ui.busy(button, false); controls(); if (offerAssignment) workspace.changeQueue(status === 'preparing' ? 'preparing' : 'ready'); else workspace.refresh(); }
    }
  }
  async function assign(rider, button) {
    if (writing || loading || !selectedOrder || !alive || expectedAssignment === undefined) return;
    const order = selectedOrder, expected = expectedAssignment;
    writing = true; controls();
    if (expected !== 'none') {
      const confirmed = await ui.confirm({ title: 'Reassign delivery?', message: `Move ${order.order_number} to ${rider.name}? The previous rider will lose access.`, label: 'Reassign' });
      if (!confirmed || !alive) { writing = false; controls(); return; }
    }
    ui.busy(button, true); errorBox.hidden = true;
    try {
      await ui.api('/api/v1/admin/orders/' + encodeURIComponent(order.uid) + '/assignment', { method: 'POST', body: { rider_uid: rider.uid, expected_assignment_uid: expected } });
      if (!alive) return;
      ui.notify('Assigned to ' + rider.name + (order.order_status === 'preparing' ? '. Rider can head to the shop while you prepare.' : '. Waiting for pickup.'));
      writing = false; closePanel(); workspace.refresh();
    } catch (failure) {
      if (!alive) return;
      // Never offer a second assignment using an old or uncertain snapshot.
      expectedAssignment = undefined;
      const message = panel?.querySelector('.operations-assignment-message');
      if (message) message.textContent = failure.message + ' Close this panel and refresh to review the current assignment.';
      error(failure);
    } finally { writing = false; if (alive) { ui.busy(button, false); controls(); } }
  }
  async function loadCandidates() {
    clearTimeout(candidateTimer);
    if (!panel || writing || loading || !alive) return;
    const revision = panelRevision, order = selectedOrder;
    loading = true; expectedAssignment = undefined;
    const target = panel.querySelector('.operations-candidates'), message = panel.querySelector('.operations-assignment-message');
    target.replaceChildren(); message.textContent = 'Checking available riders…';
    try {
      const query = new URLSearchParams({ page: String(candidatePage), per_page: '10', search: candidateSearch });
      const [detail, candidates] = await Promise.all([
        ui.api('/api/v1/admin/orders/' + encodeURIComponent(order.uid)),
        ui.api('/api/v1/admin/dispatch/recommendations?' + query),
      ]);
      if (!alive || !panel || revision !== panelRevision) return;
      if (detail.data.order.uid !== order.uid || !['preparing','ready_for_delivery'].includes(detail.data.order.order_status)) throw new Error('This order is no longer ready for assignment. Close and refresh the queue.');
      expectedAssignment = detail.data.assignments[0]?.uid || 'none';
      if (!Array.isArray(candidates.data.items)) throw new Error('Rider availability could not be verified.');
      const riderButtons=new Map();
      candidates.data.items.forEach((rider,index) => {
        const row = node('div', '', 'operations-candidate');
        const avatar = node('span', rider.name.trim().charAt(0).toUpperCase() || 'R', 'operations-rider-avatar'); avatar.setAttribute('aria-hidden', 'true');
        const distance=typeof rider.distance_m==='number' ? (rider.distance_m<1000?Math.round(rider.distance_m)+' m':(rider.distance_m/1000).toFixed(1)+' km')+' from pickup' : rider.location ? 'Location shared · Set pickup in Settings' : 'Location unknown';
        const accuracy=rider.location?.accuracy;
        const uncertainty=accuracy>100?' · Approximate GPS ±'+(accuracy>=1000?(accuracy/1000).toFixed(1)+' km':Math.round(accuracy)+' m'):'';
        const identity = node('div'); identity.append(node('strong', rider.name), node('small', 'Available · '+distance+uncertainty));
        if (index===0 && candidatePage===1 && typeof rider.distance_m==='number' && !(accuracy>100)) identity.append(node('span','Nearest shared location','admin-badge admin-badge-success'));
        const select = node('button', expectedAssignment === 'none' ? 'Assign' : 'Reassign', 'admin-button'); select.type = 'button';
        riderButtons.set(rider.uid,select);
        select.addEventListener('click', () => assign(rider, select)); row.append(avatar, identity, select); target.append(row);
      });
      const pager = candidates.data.pager;
      message.textContent = pager.total ? `${pager.total} eligible riders · Page ${candidatePage} of ${pager.page_count}` : 'No available riders. Ask a rider to set Available, then reopen this panel.';
      const previous = panel.querySelector('[data-riders-prev]'), next = panel.querySelector('[data-riders-next]');
      previous.disabled = candidatePage <= 1; next.disabled = candidatePage >= pager.page_count;
      panel.querySelector('.operations-rider-pages').hidden = pager.page_count <= 1;
      const map=panel.querySelector('.operations-pickup-map');
      window.SabjiwalahPickupMap?.show(map,candidates.data,rider=>{
        const button=riderButtons.get(rider.uid);
        if(alive&&panel&&revision===panelRevision&&button)assign(rider,button);
      });
    } catch (failure) {
      if (alive && panel && revision === panelRevision) { expectedAssignment = undefined; message.textContent = failure.message; }
    } finally {
      if (revision === panelRevision) {
        loading = false;
        scheduleCandidates();
      }
    }
  }
  function openAssignment(order, row) {
    if (writing || blocked.has(order.uid) || !alive) return;
    closePanel(); selectedOrder = order; expectedAssignment = undefined; candidatePage = 1; candidateSearch = '';
    panel = node('dialog', '', 'admin-filter-sheet operations-assignment-sheet');
    const cell = node('div');
    const content = node('div', '', 'operations-assignment');
    const heading = node('div', '', 'operations-assignment-heading');
    const close = node('button', '×', 'admin-button admin-button-secondary operations-close'); close.type = 'button'; close.setAttribute('aria-label', 'Close rider picker'); close.addEventListener('click', closePanel);
    const headingCopy = node('div'); headingCopy.append(node('strong', 'Choose a rider'), node('small', order.order_number));
    heading.append(headingCopy, close);
    const search = node('form', '', 'operations-rider-search'); const input = node('input'); input.type = 'search'; input.maxLength = 120; input.placeholder = 'Search available riders'; input.setAttribute('aria-label', 'Search available riders');
    const searchButton = node('button', 'Search', 'admin-button admin-button-secondary'); searchButton.type = 'submit'; search.append(input, searchButton);
    search.addEventListener('submit', event => { event.preventDefault(); if (writing || loading) return; candidateSearch = input.value.trim(); candidatePage = 1; ++panelRevision; loadCandidates(); });
    const message = node('p', '', 'operations-assignment-message'); message.setAttribute('role', 'status');
    const map=node('div','Loading rider map…','operations-pickup-map');
    const distanceNote=node('p','Approximate straight-line distances. Locations expire after five minutes; this is not a travel-time estimate.','operations-assignment-message');
    const candidates = node('div', '', 'operations-candidates');
    const pagination = node('div', '', 'operations-rider-pages');
    ['prev', 'next'].forEach(direction => { const button = node('button', direction === 'prev' ? 'Previous' : 'Next', 'admin-button admin-button-secondary'); button.type = 'button'; button.disabled = true; button.setAttribute('data-riders-' + direction, ''); button.addEventListener('click', () => { if (loading || writing || button.disabled) return; candidatePage += direction === 'prev' ? -1 : 1; ++panelRevision; loadCandidates(); }); pagination.append(button); });
    content.append(heading, search, message, map, distanceNote, candidates, pagination); cell.append(content); panel.append(cell);
    {
      document.body.append(panel); panel.setAttribute('aria-label', 'Assign a delivery rider');
      panel.addEventListener('cancel', event => { event.preventDefault(); closePanel(); });
      panel.addEventListener('click', event => {
        if (event.target !== panel || writing) return;
        const bounds = panel.getBoundingClientRect();
        if (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom) closePanel();
      });
      panel.showModal();
    }
    input.focus(); loadCandidates();
  }
  queues.addEventListener('click', event => {
    const button = event.target.closest('[data-queue]'); if (!button || writing || !alive) return;
    closePanel(); workspace.changeQueue(button.dataset.queue);
    queues.querySelectorAll('[data-queue]').forEach(tab => tab.setAttribute('aria-pressed', String(tab === button)));
  });
  window.SabjiwalahOperations = {
    chooseRider: openAssignment,
    act: (order, row, button) => order.order_status === 'ready_for_delivery' ? openAssignment(order, row) : transition(order, button),
    renderQueues, isBusy: () => writing || !!panel, isBlocked: uid => blocked.has(uid),
    review: () => { if (writing) return false; closePanel(); blocked.clear(); return true; },
    afterRender: orders => {
      if (!offerAssignment) return;
      const order = orders.find(item => item.uid === offerAssignment);
      offerAssignment = undefined;
      const row = order && Array.from(list.querySelectorAll('[data-order-uid]')).find(item => item.getAttribute('data-order-uid') === order.uid);
      if (order && row && ['preparing','ready_for_delivery'].includes(order.order_status)) openAssignment(order, row);
    },
  };
  window.addEventListener('pagehide', () => { alive = false; clearTimeout(candidateTimer); ++panelRevision; });
  window.addEventListener('beforeunload', event => { if (writing) { event.preventDefault(); event.returnValue = ''; } });
})();
