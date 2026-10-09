(() => {
  'use strict';
  const refreshButton = document.getElementById('dashboard-refresh');
  if (!refreshButton) return;
  const api = window.SabjiwalahAdmin;
  const content = document.getElementById('dashboard-content');
  const errorBox = document.getElementById('dashboard-error');
  const loading = document.getElementById('dashboard-loading');
  const updated = document.getElementById('dashboard-updated');
  const money = new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR' });
  const count = new Intl.NumberFormat('en-IN');
  const time = new Intl.DateTimeFormat('en-IN', { timeZone: 'Asia/Kolkata', day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });
  const statuses = { pending: 'Pending', confirmed: 'Confirmed', preparing: 'Preparing', ready_for_delivery: 'Ready for delivery', out_for_delivery: 'Out for delivery', delivered: 'Completed', cancelled: 'Cancelled', delivery_failed: 'Delivery failed' };
  const reasons = { pending: 'Awaiting confirmation', ready_for_delivery: 'Awaiting dispatch', delivery_failed: 'Delivery failed — needs review' };
  let timer, inFlight = false, controller, stopped = false, terminal = false, hasData = false;
  function text(tag, value, className) {
    const node = document.createElement(tag);
    node.textContent = value;
    if (className) node.className = className;
    return node;
  }
  function date(value) { return value ? time.format(new Date(value)) : 'Date unavailable'; }
  function renderOrders(id, orders, attention) {
    const target = document.getElementById(id);
    const fragment = document.createDocumentFragment();
    if (!orders.length) fragment.append(text('p', attention ? 'No orders currently need attention.' : 'No orders placed yet.', 'admin-empty'));
    orders.forEach(order => {
      const item = text('article', '', 'dashboard-order');
      const heading = text('div', '', 'dashboard-order-heading');
      heading.append(text('h3', order.order_number), text('strong', money.format(order.total_amount)));
      const status = order.order_status;
      const badgeClass = status === 'delivered' ? 'admin-badge-success' : ['cancelled', 'delivery_failed'].includes(status) ? 'admin-badge-danger' : 'admin-badge-warning';
      const meta = text('div', '', 'dashboard-order-meta');
      meta.append(text('span', statuses[status] || 'Unknown status', 'admin-badge ' + badgeClass), text('time', date(order.created_at)));
      item.append(heading, text('p', order.customer_name), meta);
      if (attention) item.append(text('small', reasons[status] || 'Needs review'));
      fragment.append(item);
    });
    target.replaceChildren(fragment);
  }
  function render(data) {
    // Validate before replacing current values so failures keep the last successful snapshot.
    const stats = [...document.querySelectorAll('[data-dashboard-stat]')];
    if (!data || !data.totals || !Array.isArray(data.recent_orders) || !Array.isArray(data.attention_orders)
      || stats.some(node => !Number.isFinite(data.totals[node.dataset.dashboardStat]))
      || Number.isNaN(new Date(data.generated_at).getTime())
      || [...data.recent_orders, ...data.attention_orders].some(order => !Number.isFinite(order.total_amount) || (order.created_at && Number.isNaN(new Date(order.created_at).getTime())))) {
      throw new Error('The dashboard returned an unexpected response. Please refresh.');
    }
    stats.forEach(node => { const key = node.dataset.dashboardStat; node.textContent = key === 'sales_today' ? money.format(data.totals[key]) : count.format(data.totals[key]); });
    renderOrders('dashboard-recent', data.recent_orders, false);
    renderOrders('dashboard-attention', data.attention_orders, true);
    updated.textContent = `Updated ${date(data.generated_at)} IST · Refreshes every 30 seconds while this page is visible`;
    hasData = true;
    errorBox.hidden = true;
  }
  function schedule() {
    clearTimeout(timer);
    if (!stopped && !terminal && !document.hidden) timer = setTimeout(refresh, 30000);
  }
  async function refresh() {
    if (inFlight || stopped || terminal || document.hidden) return;
    clearTimeout(timer);
    inFlight = true;
    controller = new AbortController();
    api.busy(refreshButton, true);
    content.setAttribute('aria-busy', 'true');
    loading.hidden = hasData;
    try { const payload = await api.api('/api/v1/admin/dashboard', { signal: controller.signal }); if (!stopped) render(payload.data); }
    catch (error) {
      if (stopped) return;
      terminal = [401, 403].includes(error.status);
      errorBox.textContent = (hasData ? 'Showing the last successful update. ' : '') + (error.status === 403 ? 'You no longer have access to this dashboard.' : error.message);
      errorBox.hidden = false;
      updated.textContent = hasData ? 'Refresh failed. Displayed values may be out of date.' : 'Dashboard unavailable. No statistics loaded.';
    } finally {
      inFlight = false;
      loading.hidden = true;
      content.setAttribute('aria-busy', 'false');
      api.busy(refreshButton, terminal);
      schedule();
    }
  }
  refreshButton.addEventListener('click', refresh);
  document.addEventListener('visibilitychange', () => { clearTimeout(timer); if (!document.hidden) refresh(); });
  window.addEventListener('pagehide', () => { stopped = true; clearTimeout(timer); controller?.abort(); });
  window.addEventListener('pageshow', event => { if (event.persisted) { stopped = false; refresh(); } });
  refresh();
})();
