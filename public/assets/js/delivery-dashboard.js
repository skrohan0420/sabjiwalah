(() => {
  'use strict';
  const ui = window.SabjiwalahDelivery, byId = id => document.getElementById(id);
  const refreshButton = byId('rider-dashboard-refresh'), content = byId('rider-dashboard-content');
  const errorBox = byId('rider-dashboard-error'), updated = byId('rider-dashboard-updated');
  const choice = byId('rider-availability-choice'), save = byId('rider-availability-save'), availabilityError = byId('rider-availability-error');
  const baseStates = ['available', 'unavailable', 'offline'];
  let baseAvailability, canChange = false, saving = false, uncertain = false;
  function controls() {
    choice.disabled = save.disabled = !hasData || !canChange || inFlight || saving || uncertain || terminal || stopped || navigator.onLine === false;
  }
  const money = new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR' });
  const time = new Intl.DateTimeFormat('en-IN', { timeZone: 'Asia/Kolkata', day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit', second: '2-digit' });
  const states = { available: 'Available', unavailable: 'Unavailable', busy: 'Busy', assigned: 'Assigned', offline: 'Offline' };
  let timer, inFlight = false, stopped = false, terminal = false, hasData = false, lastOrders, lastAssignments, initialized = false;
  let knownAssignments = new Set();
  function text(tag, value, cls) { const node = document.createElement(tag); node.textContent = value; if (cls) node.className = cls; return node; }
  function setText(id, value) { const node = byId(id); if (node.textContent !== String(value)) node.textContent = String(value); }
  const validDate = value => value === null || (typeof value === 'string' && !Number.isNaN(new Date(value).getTime()));
  const date = value => value ? time.format(new Date(value)) + ' IST' : 'Assignment time unavailable';
  function render(data) {
    const order = data?.active_order;
    if (!data || typeof data.rider_name !== 'string' || !Object.hasOwn(states, data.availability)
      || !baseStates.includes(data.base_availability) || typeof data.can_change_availability !== 'boolean'
      || ['active_count', 'pending_pickups', 'completed_today'].some(key => !Number.isInteger(data[key]) || data[key] < 0)
      || typeof data.cod_collected_today !== 'string' || !/^\d+\.\d{2}$/.test(data.cod_collected_today) || !Number.isFinite(Number(data.cod_collected_today))
      || !data.generated_at || !validDate(data.generated_at) || !Array.isArray(data.recent_assignments)
      || data.recent_assignments.some(row => typeof row.assignment_uid !== 'string' || typeof row.order_number !== 'string' || !validDate(row.assigned_at))
      || (order !== null && (!order || typeof order.order_number !== 'string' || !['ready_for_delivery','out_for_delivery'].includes(order.order_status) || !validDate(order.assigned_at)))) {
      throw new Error('Unexpected dashboard response. Refresh before relying on these values.');
    }
    setText('delivery-rider-name', data.rider_name);
    setText('delivery-availability-value', states[data.availability]);
    setText('delivery-availability-note', ' · Updated with dashboard');
    if (baseAvailability !== data.base_availability || uncertain) choice.value = data.base_availability;
    baseAvailability = data.base_availability; canChange = data.can_change_availability; uncertain = false;
    setText('rider-availability-help', canChange ? 'Available accepts dispatch assignments. Unavailable and Offline pause new assignments.' : 'Busy and Assigned are automatic. Finish your active assignments before changing availability.');
    setText('rider-active-count', data.active_count);
    setText('rider-pickup-count', data.pending_pickups);
    setText('rider-completed-count', data.completed_today);
    setText('rider-cod-total', money.format(Number(data.cod_collected_today)));
    const orderKey = JSON.stringify(order);
    if (lastOrders !== orderKey) {
      const target = byId('rider-active-order');
      if (!order) target.replaceChildren(text('p', 'No active delivery assigned.'));
      else target.replaceChildren(text('h3', order.order_number), text('p', order.order_status === 'ready_for_delivery' ? 'Ready for pickup' : 'Out for delivery', 'delivery-status'), text('p', 'Assigned ' + date(order.assigned_at)));
      lastOrders = orderKey;
    }
    const assignmentKey = JSON.stringify(data.recent_assignments);
    if (lastAssignments !== assignmentKey) {
      const target = byId('rider-recent-assignments'), nodes = data.recent_assignments.map(row => {
        const node = text('article', '', 'delivery-assignment');
        node.append(text('p', 'Assigned ' + row.order_number), text('time', date(row.assigned_at)));
        return node;
      });
      target.replaceChildren(...(nodes.length ? nodes : [text('p', 'No recent assignments.')]));
      lastAssignments = assignmentKey;
    }
    const assignmentIds = new Set(data.recent_assignments.map(row => row.assignment_uid));
    if (initialized && data.recent_assignments.some(row => !knownAssignments.has(row.assignment_uid))) ui.notify('Your assignment activity has changed. Check your deliveries.');
    knownAssignments = assignmentIds; initialized = true;
    setText('rider-dashboard-updated', 'Updated ' + date(data.generated_at) + ' · Refreshes every 15 seconds while visible');
    errorBox.hidden = true; content.hidden = false; hasData = true;
  }
  function clearPrivate() {
    content.hidden = true;
    byId('rider-active-order').replaceChildren(); byId('rider-recent-assignments').replaceChildren();
    ['rider-active-count', 'rider-pickup-count', 'rider-completed-count', 'rider-cod-total'].forEach(id => setText(id, '—'));
    setText('delivery-rider-name', ''); setText('delivery-availability-value', 'Unavailable');
    setText('delivery-availability-note', ' · Sign in again');
    hasData = false; lastOrders = undefined; lastAssignments = undefined;
    baseAvailability = undefined; choice.value = 'offline'; controls();
  }
  function schedule() {
    clearTimeout(timer);
    if (!stopped && !terminal && !document.hidden && navigator.onLine !== false) timer = setTimeout(refresh, 15000);
  }
  async function refresh() {
    if (inFlight || stopped || terminal || document.hidden || navigator.onLine === false) return;
    if (ui.isBusy()) { schedule(); return; }
    clearTimeout(timer); inFlight = true; refreshButton.disabled = true;
    controls();
    content.setAttribute('aria-busy', 'true');
    try { const payload = await ui.api('api/v1/delivery/dashboard'); if (!stopped) render(payload.data); }
    catch (error) {
      if (stopped) return;
      terminal = [401, 403].includes(error.status);
      if (terminal) clearPrivate();
      errorBox.textContent = (hasData ? 'Showing the last successful update. ' : '') + ui.errorText(error);
      errorBox.hidden = false;
      updated.textContent = hasData ? 'Refresh failed. Displayed information may be out of date.' : 'Dashboard unavailable. No operational data loaded.';
    } finally {
      inFlight = false; content.setAttribute('aria-busy', 'false'); refreshButton.disabled = terminal;
      controls();
      schedule();
    }
  }
  refreshButton.addEventListener('click', refresh);
  byId('rider-availability-form').addEventListener('submit', async event => {
    event.preventDefault();
    if (save.disabled || ui.isBusy() || saving || inFlight || stopped || terminal) return;
    const value = choice.value, expected = baseAvailability;
    if (!baseStates.includes(value)) return;
    if (value === expected) { ui.notify('Availability is already ' + states[value] + '.'); return; }
    saving = true; controls();
    const confirmed = await ui.confirm('Set your availability to ' + states[value] + '?', 'Change availability');
    if (!confirmed || stopped) { saving = false; controls(); return; }
    clearTimeout(timer); ui.setBusy(true, 'Updating availability…'); availabilityError.hidden = true;
    try {
      await ui.api('api/v1/delivery/availability', { availability: value, expected_availability: expected }, 'PATCH');
      if (!stopped) { uncertain = true; ui.notify('Availability saved. Refreshing your dashboard…'); }
    } catch (error) {
      if (!stopped) {
        uncertain = true;
        availabilityError.textContent = ui.errorText(error) + ' Refresh the dashboard to check the current state before making another change.';
        availabilityError.hidden = false;
        if ([401,403].includes(error.status) && error.code !== 'csrf') { terminal = true; clearPrivate(); }
      }
    } finally {
      saving = false; ui.setBusy(false); controls();
      if (!stopped && !terminal) await refresh();
    }
  });
  document.addEventListener('visibilitychange', () => { clearTimeout(timer); if (!document.hidden) refresh(); });
  window.addEventListener('offline', () => { clearTimeout(timer); controls(); updated.textContent = 'Offline. Displayed information may be out of date.'; });
  window.addEventListener('online', refresh);
  window.addEventListener('pagehide', () => { stopped = true; clearTimeout(timer); clearPrivate(); });
  refresh();
})();
