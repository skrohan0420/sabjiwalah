(() => {
  'use strict';
  const form = document.getElementById('settings-form');
  if (!form) return;
  const admin = window.SabjiwalahAdmin;
  const fields = document.getElementById('settings-fields');
  const refresh = document.getElementById('settings-refresh');
  const errorNode = document.getElementById('settings-error');
  const version = document.getElementById('settings-version');
  const history = document.getElementById('settings-history');
  const labels = { shop_open: 'Shop open', orders_paused: 'Orders paused', delivery_charge: 'Delivery charge', free_delivery_minimum: 'Free delivery subtotal', minimum_order_amount: 'Minimum subtotal', maximum_active_orders: 'Active order capacity', opens_at: 'Opens (India time)', closes_at: 'Closes (India time)' };
  let revision = null, busy = false, dirty = false;
  function error(message = '') { errorNode.textContent = message; errorNode.hidden = !message; }
  function lock(working) { busy = working; refresh.disabled = working; fields.disabled = working || revision === null; }
  function show(settings) {
    revision = settings.revision;
    for (const key of Object.keys(labels)) form.elements.namedItem(key).value = settings[key] ?? '';
    version.textContent = 'Revision ' + revision + ' · Updated ' + new Date(settings.updated_at).toLocaleString();
    dirty = false;
  }
  function value(key, data) {
    if (data == null) return key === 'free_delivery_minimum' ? 'Disabled' : 'Unlimited / all day';
    if (key === 'shop_open' || key === 'orders_paused') return Number(data) ? 'Yes' : 'No';
    return String(data);
  }
  function showHistory(rows) {
    history.replaceChildren();
    if (!rows.length) { history.textContent = 'No changes recorded yet.'; return; }
    for (const row of rows) {
      const entry = document.createElement('article'); entry.className = 'admin-card';
      const heading = document.createElement('h3'); heading.textContent = (row.admin_name || 'Administrator') + ' · ' + new Date(row.created_at).toLocaleString(); entry.append(heading);
      const list = document.createElement('ul');
      for (const key of Object.keys(labels)) {
        if (row.old_values[key] === row.new_values[key]) continue;
        const item = document.createElement('li'); item.textContent = labels[key] + ': ' + value(key, row.old_values[key]) + ' → ' + value(key, row.new_values[key]); list.append(item);
      }
      entry.append(list); history.append(entry);
    }
  }
  async function load() {
    if (busy) return;
    lock(true);
    if (dirty && !await admin.confirm({ title: 'Refresh settings', message: 'Discard your unsaved edits and load the latest settings?', label: 'Refresh' })) { lock(false); return; }
    error();
    try { const payload = await admin.api('/api/v1/admin/settings'); show(payload.data.settings); showHistory(payload.data.history); }
    catch (e) { revision = null; error(e.message + ' Refresh to load settings before saving.'); }
    finally { lock(false); }
  }
  form.addEventListener('input', () => { dirty = true; });
  form.addEventListener('change', () => { dirty = true; });
  refresh.addEventListener('click', load);
  form.addEventListener('submit', async event => {
    event.preventDefault(); if (busy || revision === null) return;
    const body = { expected_revision: revision };
    for (const key of Object.keys(labels)) body[key] = form.elements.namedItem(key).value;
    lock(true); error();
    try {
      const payload = await admin.api('/api/v1/admin/settings', { method: 'PUT', body });
      show(payload.data.settings); admin.notify('Settings saved. New checkout requests use these rules.');
      try { const latest = await admin.api('/api/v1/admin/settings'); showHistory(latest.data.history); }
      catch { error('Settings saved, but history could not be refreshed. Refresh to check recent changes.'); }
    } catch (e) {
      if (!e.status || e.status === 409 || e.status >= 500) revision = null;
      const details = e.errors ? ' ' + Object.values(e.errors).join(' ') : '';
      error(e.message + details + (revision === null ? ' Refresh before saving again.' : ''));
    } finally { lock(false); }
  });
  load();
})();
