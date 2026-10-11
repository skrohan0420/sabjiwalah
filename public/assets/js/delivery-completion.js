(() => {
  'use strict';
  const ui = window.SabjiwalahDelivery;
  const list = document.getElementById('delivery-list'), message = document.getElementById('delivery-message');
  const refresh = document.getElementById('delivery-refresh');
  const detailUid = list.getAttribute('data-order-uid');
  const money = new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR' });
  const clock = new Intl.DateTimeFormat('en-IN', { timeZone: 'Asia/Kolkata', dateStyle: 'medium', timeStyle: 'short' });
  const stamp = value => value && !Number.isNaN(new Date(value).getTime()) ? clock.format(new Date(value)) + ' IST' : 'Not recorded';
  const statusName = value => String(value || '').replaceAll('_', ' ');
  function link(label, href) { const node = el('a', label, 'delivery-button'); node.href = href; return node; }
  let busy = false, uncertain = false, alive = true;
  function el(tag, text, cls) {
    const node = document.createElement(tag);
    node.textContent = text;
    if (cls) node.className = cls;
    return node;
  }
  function setBusy(value, text) {
    busy = value;
    ui.setBusy(value, text);
    refresh.disabled = value;
    list.setAttribute('aria-busy', String(value));
    list.querySelectorAll('button').forEach(button => { button.disabled = value || uncertain; });
  }
  function showError(error) {
    if (!alive) return;
    if ([401, 403, 404].includes(error.status)) { list.replaceChildren(); window.SabjiwalahDeliveryMap?.clear(); window.SabjiwalahDeliveryTracking?.clear(); }
    message.textContent = ui.errorText(error) + (uncertain && ![401, 403].includes(error.status) ? ' Refresh orders before trying again.' : '');
    message.hidden = false;
  }
  async function run(body, path, method, successText) {
    if (busy || ui.isBusy() || uncertain || !alive) return;
    setBusy(true, 'Saving delivery action…');
    message.hidden = true;
    try {
      if (window.SabjiwalahDeliveryTracking) await window.SabjiwalahDeliveryTracking.stop('Tracking stopped while saving this delivery action.');
      await ui.api(path, body, method);
      if (!alive) return;
      ui.notify(successText);
      // Refresh under the same operation lock; a failed reload must not permit retries.
      uncertain = true;
      await loadOrders();
    } catch (error) {
      uncertain = uncertain || !error.status || error.status >= 500 || [401, 403, 409].includes(error.status);
      showError(error);
    } finally { setBusy(false); }
  }
  async function loadOrders() {
    const { data } = await ui.api('api/v1/delivery/orders' + (detailUid ? '/' + encodeURIComponent(detailUid) : ''));
    if (!alive) return;
    if (!data || (detailUid ? !data.order || !Array.isArray(data.items) || !Array.isArray(data.history) : !Array.isArray(data.items))) throw new Error('Unexpected order response. Refresh before trying again.');
    if (detailUid) window.SabjiwalahDeliveryMap?.setOrder(data.order);
    if (detailUid) window.SabjiwalahDeliveryTracking?.setOrder(data.order);
    list.replaceChildren();
    uncertain = false;
    const active = detailUid ? [data.order] : data.items.filter(row => ['ready_for_delivery', 'out_for_delivery'].includes(row.order_status));
    document.getElementById('delivery-count').textContent = detailUid ? data.order.order_number : 'Assigned orders: ' + active.length;
    for (const order of active) {
      const card = el('article', '', 'delivery-panel delivery-record');
      const status = order.order_status === 'ready_for_delivery' ? 'Ready for pickup' : statusName(order.order_status);
      card.append(el('h2', order.order_number), el('p', status, 'delivery-status'),
        el('p', order.customer_name + ' · ' + order.customer_phone),
        el('p', [order.address_line, order.city, order.state, order.postal_code].filter(Boolean).join(', '), 'delivery-address'));
      card.append(el('p', money.format(Number(order.total_amount)) + ' · ' + (order.payment_method === 'cod' ? 'Cash on delivery' : statusName(order.payment_method)) + ' · Payment ' + statusName(order.payment_status)),
        el('p', 'Assigned ' + stamp(order.assigned_at), 'delivery-note'), el('p', 'Delivery instructions: ' + (order.notes || 'None provided.')));
      if (!detailUid) card.append(link('View order details', window.Sabjiwalah.url('delivery/orders/' + encodeURIComponent(order.uid))));
      const actionable = ['ready_for_delivery','out_for_delivery'].includes(order.order_status);
      if (actionable) {
        const contact = el('div', '', 'delivery-order-links');
        const phone = String(order.customer_phone || '').replace(/[ ()-]/g, '');
        if (/^\+?[0-9]{6,15}$/.test(phone)) contact.append(link('Call customer', 'tel:' + phone));
        const lat = order.delivery_latitude, lng = order.delivery_longitude;
        const hasCoords = lat !== null && lat !== undefined && lng !== null && lng !== undefined && Number.isFinite(Number(lat)) && Number.isFinite(Number(lng)) && Math.abs(Number(lat)) <= 90 && Math.abs(Number(lng)) <= 180;
        const address = [order.address_line,order.city,order.state,order.postal_code].filter(Boolean).join(', ');
        if ((hasCoords || address) && (!detailUid || !window.SabjiwalahDeliveryMap)) {
          const nav = link(hasCoords ? 'Navigate to Customer' : 'Navigate using address', 'https://www.google.com/maps/dir/?api=1&travelmode=driving&dir_action=navigate&destination=' + encodeURIComponent(hasCoords ? Number(lat) + ',' + Number(lng) : address));
          nav.target = '_blank'; nav.rel = 'noopener noreferrer'; nav.referrerPolicy = 'no-referrer'; contact.append(nav);
        }
        card.append(contact);
      }
      if (detailUid) {
        card.append(el('h3', 'Products'));
        const products = el('ul', '', 'delivery-products');
        for (const item of data.items) products.append(el('li', item.product_name + ' · ' + item.quantity + ' × ' + item.unit + ' · ' + money.format(Number(item.total_price))));
        card.append(products.children.length ? products : el('p', 'No product details recorded.'));
        const lat = order.delivery_latitude, lng = order.delivery_longitude;
        card.append(el('h3', 'Saved delivery location'), el('p', lat !== null && lat !== undefined && lng !== null && lng !== undefined ? lat + ', ' + lng : 'Coordinates unavailable. Check the delivery address with the customer.'));
        card.append(el('h3', 'Order timeline'), el('p', 'Placed ' + stamp(order.created_at)), el('p', 'Last updated ' + stamp(order.updated_at)));
        if (order.completed_at) card.append(el('p', 'Completed ' + stamp(order.completed_at)));
        const timeline = el('ul', '', 'delivery-products');
        for (const entry of data.history) timeline.append(el('li', statusName(entry.new_status) + ' · ' + stamp(entry.occurred_at)));
        card.append(timeline);
      }
      const statusPath = 'api/v1/delivery/orders/' + encodeURIComponent(order.uid) + '/status';
      if (order.order_status === 'ready_for_delivery') {
        const pickup = el('button', 'Record pickup', 'delivery-button delivery-primary');
        pickup.type = 'button';
        pickup.addEventListener('click', () => run({ status: 'out_for_delivery', expected_status: 'ready_for_delivery' }, statusPath, 'PATCH', 'Pickup recorded.'));
        card.append(pickup);
      } else if (order.order_status === 'out_for_delivery') {
        const form = el('form', '', 'delivery-form'), label = el('label', 'Customer delivery PIN', 'delivery-field');
        const pin = el('input', '');
        pin.inputMode = 'numeric'; pin.autocomplete = 'off'; pin.type = 'password'; pin.pattern = '[0-9]{6}'; pin.maxLength = 6; pin.required = true;
        label.append(pin);
        const cashLabel = el('label', ' I received the full COD amount of Rs ' + order.total_amount, 'delivery-cash');
        const cash = el('input', ''); cash.type = 'checkbox'; cash.required = order.payment_method === 'cod'; cashLabel.prepend(cash);
        const submit = el('button', 'Verify and complete delivery', 'delivery-button delivery-primary'); submit.type = 'submit';
        form.append(label); if (order.payment_method === 'cod') form.append(cashLabel); form.append(submit);
        form.addEventListener('submit', event => {
          event.preventDefault();
          if (busy || ui.isBusy() || uncertain) return;
          const entered = pin.value; pin.value = '';
          run({ pin: entered, cash_received: cash.checked }, 'api/v1/delivery/orders/' + encodeURIComponent(order.uid) + '/complete', 'POST', 'Delivery completed.');
        });
        card.append(form);
        const failure = el('form', '', 'delivery-form delivery-failure'), reasonLabel = el('label', 'Failed delivery reason', 'delivery-field');
        const reason = el('textarea', ''); reason.required = true; reason.maxLength = 1000; reasonLabel.append(reason);
        const fail = el('button', 'Report failed delivery', 'delivery-button delivery-danger'); fail.type = 'submit'; failure.append(reasonLabel, fail);
        failure.addEventListener('submit', async event => {
          event.preventDefault();
          if (busy || ui.isBusy() || uncertain) return;
          const notes = reason.value.trim();
          if (await ui.confirm('Mark ' + order.order_number + ' as a failed delivery?', 'Report failed delivery')) {
            run({ status: 'delivery_failed', notes, expected_status: 'out_for_delivery' }, statusPath, 'PATCH', 'Failed delivery recorded.');
          }
        });
        card.append(failure);
      }
      list.append(card);
    }
    if (!list.children.length) list.append(el('p', 'No active deliveries.', 'delivery-panel'));
  }
  async function load() {
    if (busy || ui.isBusy() || !alive) return;
    setBusy(true, 'Loading your deliveries…'); message.hidden = true;
    try { await loadOrders(); }
    catch (error) { uncertain = true; showError(error); }
    finally { setBusy(false); }
  }
  refresh.addEventListener('click', async () => {
    if (busy || ui.isBusy()) return;
    if (await ui.confirm('Refresh orders and discard any unfinished entries?', 'Refresh deliveries')) load();
  });
  window.addEventListener('pagehide', () => {
    alive = false;
    window.SabjiwalahDeliveryMap?.clear();
    list.querySelectorAll('input').forEach(input => { input.value = ''; });
    list.querySelectorAll('textarea').forEach(input => { input.value = ''; });
    list.replaceChildren();
  });
  load();
})();
