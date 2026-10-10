(function () {
  'use strict';
  const admin = window.SabjiwalahAdmin, root = document.getElementById('campaign-page');
  const kind = root.dataset.kind, endpoint = '/api/v1/admin/' + kind;
  const list = document.getElementById('campaign-list'), filters = document.getElementById('campaign-filters');
  const dialog = document.getElementById('campaign-editor'), form = document.getElementById('campaign-form');
  const error = document.getElementById('campaign-error'), saveError = document.getElementById('campaign-save-error');
  const saveButton = document.getElementById('campaign-save');
  let page = 1, busy = false, editing = null, uncertain = false;
  const money = value => new Intl.NumberFormat('en-IN', {style: 'currency', currency: 'INR'}).format(value);
  const time = value => value ? new Intl.DateTimeFormat('en-IN', {dateStyle: 'medium', timeStyle: 'short', timeZone: 'Asia/Kolkata'}).format(new Date(value)) : 'No limit';
  function node(tag, text, cls) { const el = document.createElement(tag); el.textContent = text; if (cls) el.className = cls; return el; }
  function showError(target, e) {
    const details = e.errors;
    target.textContent = e.message + (details ? ' ' + Object.entries(details).map(([key, value]) => key + ': ' + value).join(' ') : '');
    target.hidden = false;
  }
  function localDate(value) {
    if (!value) return '';
    const date = new Date(value), pad = n => String(n).padStart(2, '0');
    return date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate()) + 'T' + pad(date.getHours()) + ':' + pad(date.getMinutes()) + ':' + pad(date.getSeconds());
  }
  function valueLimit() {
    if (kind === 'offers') form.elements.value.max = form.elements.type.value === 'percentage' ? '100' : '99999999.99';
  }
  function openEditor(row = null) {
    if (busy) return;
    editing = row; uncertain = false; form.reset(); saveError.hidden = true; saveButton.disabled = false;
    for (const [key, value] of Object.entries(row || {})) {
      const field = form.elements.namedItem(key); if (!field) continue;
      field.value = ['starts_at', 'ends_at'].includes(key) ? localDate(value) : key === 'is_active' ? String(Number(value)) : value ?? '';
    }
    valueLimit(); document.getElementById('campaign-editor-title').textContent = (row ? 'Edit ' : 'Create ') + (kind === 'offers' ? 'offer' : 'promotion');
    dialog.showModal();
  }
  async function load() {
    if (busy) return; busy = true; error.hidden = true;
    try {
      const query = new URLSearchParams(new FormData(filters)); query.set('page', page);
      const {data} = await admin.api(endpoint + '?' + query);
      list.replaceChildren();
      for (const row of data.items) {
        const card = node('article', '', 'admin-card campaign-record');
        card.append(node('h2', row.name || row.title), node('p', 'Status: ' + row.status));
        if (kind === 'offers') card.append(node('p', row.code + ' · ' + (row.type === 'percentage' ? row.value + '%' : money(row.value))),
          node('p', 'Minimum subtotal: ' + money(row.minimum_order_amount || 0) + ' · Discount cap: ' + (row.maximum_discount ? money(row.maximum_discount) : 'None')),
          node('p', 'Usage limit: ' + (row.usage_limit || 'Unlimited') + ' committed orders'));
        else card.append(node('p', 'Placement: ' + (row.position || 'Unspecified')), node('p', 'Image: ' + (row.image || 'None')), node('p', 'Destination: ' + (row.link || 'None')));
        card.append(node('p', 'Starts: ' + time(row.starts_at) + ' · Ends: ' + time(row.ends_at) + ' (IST)'));
        const edit = node('button', 'Edit campaign', 'admin-button admin-button-secondary'); edit.type = 'button';
        edit.addEventListener('click', async () => {
          if (busy) return; busy = true; edit.disabled = true;
          try { const {data} = await admin.api(endpoint + '/' + encodeURIComponent(row.uid)); busy = false; openEditor(data); }
          catch (e) { showError(error, e); } finally { busy = false; edit.disabled = false; }
        });
        card.append(edit); list.append(card);
      }
      if (!data.items.length) list.append(node('p', 'No campaigns match these filters.', 'admin-card'));
      document.getElementById('campaign-page-number').textContent = 'Page ' + page + ' of ' + Math.max(1, data.pager.page_count);
      document.getElementById('campaign-prev').disabled = page <= 1;
      document.getElementById('campaign-next').disabled = page >= data.pager.page_count;
    } catch (e) { showError(error, e); } finally { busy = false; }
  }
  form.addEventListener('submit', async event => {
    event.preventDefault(); if (busy || uncertain) return;
    const body = Object.fromEntries(new FormData(form));
    for (const key of ['starts_at', 'ends_at']) body[key] = body[key] ? new Date(body[key]).toISOString().replace(/\.\d{3}Z$/, 'Z') : null;
    body.is_active = Number(body.is_active);
    if (editing) body.expected_revision = editing.revision;
    busy = true; saveButton.disabled = true; saveError.hidden = true;
    try {
      await admin.api(endpoint + (editing ? '/' + encodeURIComponent(editing.uid) : ''), {method: editing ? 'PUT' : 'POST', body});
      dialog.close(); page = 1;
    } catch (e) {
      uncertain = !e.status || e.status >= 500 || [401, 403, 404, 409].includes(e.status);
      showError(saveError, e);
      if (uncertain) saveError.textContent += ' Close this editor and refresh before saving again.';
      return;
    } finally { busy = false; saveButton.disabled = uncertain; }
    load();
  });
  dialog.addEventListener('cancel', event => { if (busy) event.preventDefault(); });
  document.getElementById('campaign-cancel').addEventListener('click', () => { if (!busy) dialog.close(); });
  document.getElementById('campaign-new').addEventListener('click', () => openEditor());
  document.getElementById('campaign-refresh').addEventListener('click', load);
  filters.addEventListener('submit', event => { event.preventDefault(); if (!busy) { page = 1; load(); } });
  document.getElementById('campaign-prev').addEventListener('click', () => { if (!busy) { page--; load(); } });
  document.getElementById('campaign-next').addEventListener('click', () => { if (!busy) { page++; load(); } });
  if (kind === 'offers') form.elements.type.addEventListener('change', valueLimit);
  load();
})();
