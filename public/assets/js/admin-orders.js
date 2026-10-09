(() => {
  'use strict';
  const form = document.getElementById('orders-filters');
  if (!form) return;
  const ui = window.SabjiwalahAdmin;
  const get = id => document.getElementById(id);
  const list = get('orders-list'), refreshButton = get('orders-refresh'), errorBox = get('orders-error');
  const dialog = get('order-details'), actionForm = get('order-action-form'), notes = get('order-action-notes'), action = get('order-action');
  const money = new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR' });
  const dates = new Intl.DateTimeFormat('en-IN', { timeZone: 'Asia/Kolkata', day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
  const statuses = { pending: 'Pending', confirmed: 'Confirmed', preparing: 'Preparing', ready_for_delivery: 'Ready for delivery', out_for_delivery: 'Out for delivery', delivered: 'Delivered', cancelled: 'Cancelled', delivery_failed: 'Delivery failed' };
  const actions = { confirmed: 'Confirm order', preparing: 'Mark as preparing', ready_for_delivery: 'Mark ready for delivery', cancelled: 'Cancel order' };
  let filters = new URLSearchParams(), page = 1, pages = 0, revision = 0;
  let timer, controller, inFlight = false, reloadPending = false, stopped = false, terminal = false, hasData = false;
  let selected = null, detailController, detailRevision = 0, dirty = false, saving = false, closing = false;
  let latestUid, initialized = false;
  const initial = new URLSearchParams(location.search);
  for (const field of form.elements) if (field.name && initial.has(field.name)) field.value = initial.get(field.name);
  page = Math.max(1, Math.min(1000000, Number(initial.get('page')) || 1));
  function readFilters() {
    filters = new URLSearchParams();
    for (const [key, value] of new FormData(form)) if (value) filters.set(key, value);
  }
  readFilters();
  function node(tag, value = '', className = '') {
    const element = document.createElement(tag);
    element.textContent = value;
    if (className) element.className = className;
    return element;
  }
  function when(value) { return value ? dates.format(new Date(value)) + ' IST' : 'Unavailable'; }
  function elapsed(value) {
    if (!value) return 'Time unavailable';
    const minutes = Math.max(0, Math.floor((Date.now() - new Date(value).getTime()) / 60000));
    if (minutes < 60) return `${minutes} min ago`;
    if (minutes < 1440) return `${Math.floor(minutes / 60)}h ${minutes % 60}m ago`;
    return `${Math.floor(minutes / 1440)} days ago`;
  }
  function badge(status) {
    const tone = status === 'delivered' ? 'success' : ['cancelled', 'delivery_failed'].includes(status) ? 'danger' : 'warning';
    return node('span', statuses[status] || status, 'admin-badge admin-badge-' + tone);
  }
  function syncUrl() {
    const query = new URLSearchParams(filters); query.set('page', page);
    history.replaceState(null, '', location.pathname + '?' + query.toString());
  }
  function setStale(value) {
    get('order-detail-stale').hidden = !value;
    get('order-action-submit').disabled = value || saving;
  }
  function renderList(data) {
    const wrapper = node('div', '', 'admin-table-wrap');
    if (!data.items.length) wrapper.append(node('p', 'No orders match these filters.', 'admin-empty'));
    else {
      const table = node('table', '', 'admin-table orders-table');
      table.setAttribute('aria-label', 'Orders');
      const labels = ['Order', 'Customer', 'Placed', 'Items', 'Total', 'Payment', 'Status', 'Delivery person', 'Elapsed', 'Details'];
      const thead = node('thead'), header = node('tr'), tbody = node('tbody');
      labels.forEach(label => { const th = node('th', label); th.scope = 'col'; header.append(th); });
      thead.append(header);
      data.items.forEach(order => {
        const row = node('tr');
        const payment = node('div'); payment.append(node('span', order.payment_method.toUpperCase()), node('small', order.payment_status));
        const button = node('button', 'View', 'admin-button admin-button-secondary'); button.type = 'button';
        button.setAttribute('aria-label', 'View order ' + order.order_number);
        button.addEventListener('click', () => openDetails(order.uid));
        const values = [order.order_number, order.customer_name, when(order.placed_at), String(order.item_count), money.format(order.total_amount), payment, badge(order.order_status), order.assigned_delivery_name || 'Unassigned', elapsed(order.placed_at), button];
        values.forEach((value, i) => { const td = node('td'); td.dataset.label = labels[i]; td.append(typeof value === 'string' ? node('div', value) : value); row.append(td); });
        tbody.append(row);
        if (selected?.order.uid === order.uid && selected.order.order_status !== order.order_status) setStale(true);
      });
      table.append(thead, tbody); wrapper.append(table);
    }
    list.replaceChildren(wrapper);
    pages = data.pager.page_count;
    get('orders-page').textContent = `${data.pager.total} ${data.pager.total === 1 ? 'order' : 'orders'} · Page ${page} of ${Math.max(1, pages)}`;
    get('orders-prev').disabled = page <= 1;
    get('orders-next').disabled = page >= pages;
    if (initialized && data.latest_order && data.latest_order.uid !== latestUid) {
      get('orders-new').textContent = `New order received: ${data.latest_order.order_number}. Your current filters remain applied.`;
      get('orders-new').hidden = false;
    }
    latestUid = data.latest_order?.uid;
    initialized = true;
    get('orders-updated').textContent = `Updated ${dates.format(new Date())} IST · Refreshes every 8 seconds while visible`;
    errorBox.hidden = true;
    hasData = true;
  }
  function schedule() { clearTimeout(timer); if (!stopped && !terminal && !document.hidden) timer = setTimeout(refresh, 8000); }
  async function refresh() {
    if (stopped || terminal || document.hidden || inFlight) return;
    clearTimeout(timer); inFlight = true;
    const currentRevision = revision;
    controller = new AbortController();
    ui.busy(refreshButton, true); list.setAttribute('aria-busy', 'true'); get('orders-loading').hidden = hasData;
    const query = new URLSearchParams(filters); query.set('page', page);
    try {
      const payload = await ui.api('/api/v1/admin/orders?' + query.toString(), { signal: controller.signal });
      if (currentRevision !== revision || stopped) return;
      const data = payload.data;
      if (data.pager.page_count > 0 && page > data.pager.page_count) { page = data.pager.page_count; syncUrl(); reloadPending = true; return; }
      renderList(data);
    } catch (error) {
      if (currentRevision !== revision || stopped) return;
      terminal = [401, 403].includes(error.status);
      errorBox.textContent = (hasData ? 'Showing the last successful list. ' : '') + (error.errors ? Object.values(error.errors).join(' ') : error.message);
      errorBox.hidden = false;
      get('orders-updated').textContent = 'Refresh failed. Displayed orders may be out of date.';
    } finally {
      inFlight = false; get('orders-loading').hidden = true; list.setAttribute('aria-busy', 'false'); ui.busy(refreshButton, terminal);
      if (reloadPending && !stopped) { reloadPending = false; refresh(); } else schedule();
    }
  }
  function reloadList() { revision++; syncUrl(); if (inFlight) { reloadPending = true; controller.abort(); } else refresh(); }
  form.addEventListener('submit', event => { event.preventDefault(); readFilters(); page = 1; reloadList(); });
  form.addEventListener('reset', () => setTimeout(() => { readFilters(); page = 1; reloadList(); }, 0));
  refreshButton.addEventListener('click', refresh);
  get('orders-prev').addEventListener('click', () => { if (page > 1) { page--; reloadList(); } });
  get('orders-next').addEventListener('click', () => { if (page < pages) { page++; reloadList(); } });

  function facts(title, pairs) {
    const card = node('section', '', 'admin-card'); card.append(node('h3', title));
    const dl = node('dl', '', 'order-facts');
    pairs.forEach(([label, value]) => { dl.append(node('dt', label), node('dd', value == null || value === '' ? 'Not provided' : String(value))); });
    card.append(dl); return card;
  }
  function renderDetails(data) {
    selected = data; dirty = false; notes.value = ''; setStale(false);
    const order = data.order, content = get('order-detail-content'), fragment = document.createDocumentFragment();
    get('order-detail-title').textContent = order.order_number;
    const grid = node('div', '', 'order-detail-grid');
    grid.append(facts('Order information', [['Status', statuses[order.order_status]], ['Placed', when(order.placed_at)], ['Payment method', order.payment_method.toUpperCase()], ['Payment status', order.payment_status], ['Order notes', order.notes]]));
    grid.append(facts('Customer & delivery', [['Customer', order.customer_name], ['Phone', order.customer_phone], ['Address', [order.address_line, order.city, order.state, order.postal_code].filter(Boolean).join(', ')], ['Coordinates', order.delivery_latitude != null && order.delivery_longitude != null ? `${order.delivery_latitude}, ${order.delivery_longitude}` : null]]));
    fragment.append(grid, node('h3', 'Ordered products'));
    const items = node('div', '', 'admin-table-wrap'), table = node('table', '', 'admin-table order-items-table');
    const itemLabels = ['Product', 'Quantity', 'Unit price', 'Total'];
    const header = node('tr'); itemLabels.forEach(label => { const th = node('th', label); th.scope = 'col'; header.append(th); });
    const head = node('thead'); head.append(header); const body = node('tbody');
    data.items.forEach(item => { const row = node('tr'); [item.product_name + ' · ' + item.unit, String(item.quantity), money.format(item.unit_price), money.format(item.total_price)].forEach((value, index) => { const cell = node('td'); cell.dataset.label = itemLabels[index]; cell.append(node('span', value)); row.append(cell); }); body.append(row); });
    table.append(head, body); items.append(data.items.length ? table : node('p', 'No item snapshots available.', 'admin-empty')); fragment.append(items);
    const totals = node('div', '', 'order-detail-grid');
    totals.append(facts('Order total', [['Subtotal', money.format(order.subtotal)], ['Discount', money.format(order.discount_amount)], ['Delivery charge', money.format(order.delivery_charge)], ['Final total', money.format(order.total_amount)]]));
    const assignments = node('section', '', 'admin-card'); assignments.append(node('h3', 'Delivery assignments'));
    if (!data.assignments.length) assignments.append(node('p', 'No delivery assignment yet.', 'admin-muted'));
    data.assignments.forEach(entry => {
      const block = node('div', '', 'order-history');
      block.append(node('p', entry.delivery_name || 'Unavailable rider'), node('small', `Assigned ${when(entry.assigned_time)} by ${entry.assigned_by_name || 'Unavailable account'}`), node('p', entry.completed_time ? 'Completed ' + when(entry.completed_time) : 'Assignment not completed'));
      assignments.append(block);
    });
    totals.append(assignments); fragment.append(totals, node('h3', 'Status history'));
    if (!data.history.length) fragment.append(node('p', 'No history available.', 'admin-muted'));
    data.history.forEach(entry => {
      const block = node('div', '', 'order-history');
      block.append(node('p', `${entry.old_status ? statuses[entry.old_status] || entry.old_status : 'Placed'} → ${statuses[entry.new_status] || entry.new_status}`), node('small', `${when(entry.occurred_at)} · ${entry.changed_by_name || 'Unavailable account'}`));
      if (entry.notes) block.append(node('p', entry.notes)); fragment.append(block);
    });
    content.replaceChildren(fragment);
    action.replaceChildren();
    data.allowed_actions.filter(status => actions[status]).forEach(status => { const option = node('option', actions[status]); option.value = status; action.append(option); });
    actionForm.hidden = !action.children.length;
  }
  async function loadDetails(uid) {
    detailController?.abort(); detailController = new AbortController();
    const requestRevision = ++detailRevision;
    get('order-detail-error').hidden = true;
    get('order-detail-content').setAttribute('aria-busy', 'true');
    get('order-detail-refresh').disabled = true;
    actionForm.hidden = true;
    try {
      const payload = await ui.api('/api/v1/admin/orders/' + encodeURIComponent(uid), { signal: detailController.signal });
      if (requestRevision !== detailRevision || !dialog.open || stopped) return;
      renderDetails(payload.data);
    } catch (error) {
      if (requestRevision !== detailRevision || !dialog.open || stopped) return;
      get('order-detail-error').textContent = error.message; get('order-detail-error').hidden = false;
    } finally {
      if (requestRevision === detailRevision) { get('order-detail-content').setAttribute('aria-busy', 'false'); get('order-detail-refresh').disabled = false; }
    }
  }
  async function openDetails(uid) {
    if (dialog.open || saving) return;
    selected = { order: { uid } }; dirty = false;
    get('order-detail-title').textContent = 'Order details';
    get('order-detail-content').replaceChildren(node('p', 'Loading order details…', 'admin-loading'));
    setStale(false); dialog.showModal(); await loadDetails(uid);
  }
  async function closeDetails() {
    if (saving || closing) return;
    closing = true;
    try {
      if (dirty && !await ui.confirm({ title: 'Discard status note?', message: 'Your unsaved action and note will be discarded.', label: 'Discard', danger: true })) return;
      detailRevision++; detailController?.abort(); dialog.close(); selected = null; dirty = false;
    } finally { closing = false; }
  }
  dialog.addEventListener('cancel', event => { event.preventDefault(); closeDetails(); });
  get('order-close').addEventListener('click', closeDetails);
  get('order-detail-refresh').addEventListener('click', async () => {
    if (saving || !selected) return;
    if (dirty && !await ui.confirm({ title: 'Reload order details?', message: 'Reloading will discard your unsaved status note and action.', label: 'Reload' })) return;
    await loadDetails(selected.order.uid);
  });
  notes.addEventListener('input', () => dirty = true);
  action.addEventListener('change', () => dirty = true);
  actionForm.addEventListener('submit', async event => {
    event.preventDefault();
    if (saving || !selected || get('order-action-submit').disabled) return;
    const uid = selected.order.uid, status = action.value, expected = selected.order.order_status, note = notes.value;
    if (!await ui.confirm({ title: actions[status], message: `${actions[status]} for ${selected.order.order_number}?`, label: 'Apply action', danger: status === 'cancelled' })) return;
    saving = true; notes.disabled = true; action.disabled = true; get('order-close').disabled = true; get('order-detail-refresh').disabled = true; ui.busy(get('order-action-submit'), true);
    get('order-detail-error').hidden = true;
    try {
      await ui.api('/api/v1/admin/orders/' + encodeURIComponent(uid) + '/status', { method: 'PATCH', body: { status, expected_status: expected, notes: note } });
      dirty = false;
      await loadDetails(uid); reloadList();
      ui.notify('Order status updated.');
    } catch (error) {
      get('order-detail-error').textContent = error.errors ? Object.values(error.errors).join(' ') : error.message;
      get('order-detail-error').hidden = false;
      // A network failure may follow a successful server write: reload instead of resubmitting.
      if (error.status === 409 || error.status === 0 || error.status >= 500) setStale(true);
    } finally {
      saving = false; notes.disabled = false; action.disabled = false; get('order-close').disabled = false; get('order-detail-refresh').disabled = false;
      ui.busy(get('order-action-submit'), !get('order-detail-stale').hidden);
    }
  });
  document.addEventListener('visibilitychange', () => { clearTimeout(timer); if (!document.hidden) refresh(); });
  window.addEventListener('beforeunload', event => { if (dirty || saving) { event.preventDefault(); event.returnValue = ''; } });
  window.addEventListener('pagehide', () => { stopped = true; clearTimeout(timer); controller?.abort(); detailController?.abort(); });
  window.addEventListener('pageshow', event => { if (event.persisted) { stopped = false; refresh(); } });
  refresh();
})();
