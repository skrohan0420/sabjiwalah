(() => {
  'use strict';
  const admin = window.SabjiwalahAdmin, $ = id => document.getElementById(id);
  const form = $('product-form'), dialog = $('product-editor'), filters = $('products-filters');
  let page = 1, pageCount = 0, revision = 0, current = null, dirty = false, working = false, uncertain = false;
  let applied = new URLSearchParams(new FormData(filters)), listAbort;
  const money = value => new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR' }).format(value);
  function node(tag, text, cls) { const el = document.createElement(tag); if (text != null) el.textContent = text; if (cls) el.className = cls; return el; }
  function error(target, err) { target.textContent = [err.message, ...Object.values(err.errors || {})].join(' '); target.hidden = false; }
  function imageUrl(path) {
    if (!path) return '';
    const url = new URL(path.replace(/^\/(?!\/)/, ''), window.Sabjiwalah.url('/'));
    return ['http:', 'https:'].includes(url.protocol) ? url.href : '';
  }
  function button(label, action, secondary = true) { const el = node('button', label, 'admin-button' + (secondary ? ' admin-button-secondary' : '')); el.type = 'button'; el.addEventListener('click', action); return el; }
  function navigation() { $('products-prev').disabled = page <= 1; $('products-next').disabled = page >= pageCount; }
  async function load() {
    const version = ++revision; listAbort?.abort(); listAbort = new AbortController();
    $('products-error').hidden = true; $('products-list').setAttribute('aria-busy', 'true');
    $('products-prev').disabled = $('products-next').disabled = true;
    const query = new URLSearchParams(applied); query.set('page', page); query.set('per_page', 20);
    try {
      const { data } = await admin.api('/api/v1/admin/products?' + query, { signal: listAbort.signal });
      if (version !== revision) return;
      pageCount = data.pager.page_count;
      if (page > Math.max(1, pageCount)) { page = Math.max(1, pageCount); return load(); }
      $('products-summary').textContent = `${data.pager.total} product${data.pager.total === 1 ? '' : 's'} · Stock is shown in each product’s unit.`;
      $('products-page').textContent = `Page ${page} of ${Math.max(1, pageCount)}`;
      const list = $('products-list'); list.replaceChildren();
      if (!data.items.length) list.append(node('p', 'No products match these filters.', 'admin-card'));
      for (const product of data.items) {
        const card = node('article', null, 'admin-card product-row');
        const preview = node('img'); preview.src = imageUrl(product.image) || window.Sabjiwalah.url('/assets/images/product-placeholder.svg'); preview.alt = ''; preview.loading = 'lazy'; preview.className = 'product-thumbnail';
        preview.addEventListener('error', () => { preview.src = window.Sabjiwalah.url('/assets/images/product-placeholder.svg'); }, { once: true });
        const info = node('div'); info.append(node('h2', product.name), node('p', `${product.unit} · ${product.slug}`, 'admin-caption'));
        const prices = node('div'); prices.append(node('strong', money(product.sale_price ?? product.price)));
        if (product.sale_price !== null) prices.append(node('del', money(product.price), 'admin-caption'));
        const stock = node('div'); stock.append(node('strong', `${product.stock_quantity} in stock`), node('span', product.is_active ? 'Active' : 'Inactive / archived', 'admin-badge ' + (product.is_active ? 'admin-badge-success' : '')));
        const actions = node('div', null, 'product-row-actions');
        actions.append(button('Edit', () => open(product.uid)), button(product.is_active ? 'Deactivate' : 'Activate', () => availability(product)), button('Archive', () => archive(product)));
        card.append(preview, info, prices, stock, actions); list.append(card);
      }
      navigation();
    } catch (err) { if (version === revision) { error($('products-error'), err); navigation(); } }
    finally { if (version === revision) $('products-list').setAttribute('aria-busy', 'false'); }
  }
  function fill(product) {
    form.reset(); current = product; dirty = uncertain = false;
    for (const field of ['name','slug','unit','price','sale_price','stock_quantity','description']) form.elements[field].value = product?.[field] ?? '';
    form.elements.is_active.value = product ? (product.is_active ? '1' : '0') : '1';
    $('product-title').textContent = product ? 'Edit product' : 'Add product';
    $('product-images').hidden = !product; $('product-reload').hidden = !product;
    $('product-image-file').value = ''; $('product-error').hidden = true;
    const src = imageUrl(product?.image); $('product-image-preview').hidden = !src; if (src) $('product-image-preview').src = src;
    $('product-remove-image').disabled = !product?.image;
    $('product-save').disabled = false;
  }
  async function open(uid) {
    if (working) return;
    try { const product = uid ? (await admin.api('/api/v1/admin/products/' + encodeURIComponent(uid))).data.product : null; fill(product); dialog.showModal(); }
    catch (err) { admin.notify(err.message, true); }
  }
  async function discard() { return !dirty || await admin.confirm({ title: 'Discard changes?', message: 'Your unsaved product changes will be lost.', label: 'Discard changes' }); }
  async function close() { if (!working && await discard()) { dirty = false; dialog.close(); } }
  function lock(value) { working = value; $('product-fields').disabled = value; $('product-image-file').disabled = value; $('product-upload').disabled = value; $('product-remove-image').disabled = value || !current?.image; $('product-close').disabled = value; }
  async function mutate(task) {
    if (working) return; lock(true); $('product-error').hidden = true;
    try { await task(); await load(); }
    catch (err) { error($('product-error'), err); if (!err.status || err.status === 409 || err.status >= 500) { uncertain = true; $('product-save').disabled = true; } }
    finally { lock(false); }
  }
  form.addEventListener('input', () => { dirty = true; });
  form.addEventListener('change', () => { dirty = true; });
  form.elements.name.addEventListener('input', () => { if (!current) form.elements.slug.value = form.elements.name.value.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, ''); });
  form.addEventListener('submit', async event => {
    event.preventDefault(); if (uncertain || working) return;
    const body = Object.fromEntries(new FormData(form));
    if (body.sale_price !== '' && Number(body.sale_price) > Number(body.price)) return error($('product-error'), { message: 'Sale price cannot exceed regular price.' });
    if (!await admin.confirm({ title: 'Save product?', message: 'Price and stock changes apply to the storefront immediately.', label: 'Save product' })) return;
    if (current) body.expected_stock_quantity = current.stock_quantity;
    await mutate(async () => { const {data} = await admin.api('/api/v1/admin/products' + (current ? '/' + encodeURIComponent(current.uid) : ''), { method: current ? 'PATCH' : 'POST', body }); fill(data.product); admin.notify('Product saved.'); });
  });
  $('product-image-form').addEventListener('submit', async event => {
    event.preventDefault(); if (dirty || uncertain) return error($('product-error'), { message: 'Save or reload product changes before updating its image.' });
    const file = $('product-image-file').files[0]; if (!file) return;
    if (file.size > 2097152 || !['image/jpeg','image/png','image/webp'].includes(file.type)) return error($('product-error'), { message: 'Choose a JPEG, PNG or WebP image up to 2 MB.' });
    const body = new FormData(); body.append('image', file);
    await mutate(async () => { const {data} = await admin.api('/api/v1/admin/products/' + encodeURIComponent(current.uid) + '/image', { method: 'POST', body }); fill(data.product); admin.notify('Product image uploaded.'); });
  });
  $('product-remove-image').addEventListener('click', async () => {
    if (dirty || uncertain || working) return error($('product-error'), {message: 'Save or reload product changes before removing its image.'});
    if (!await admin.confirm({title: 'Remove image?', message: 'The storefront will use its fallback image.', label: 'Remove image'})) return;
    await mutate(async () => { const {data} = await admin.api('/api/v1/admin/products/' + encodeURIComponent(current.uid) + '/image', {method: 'DELETE'}); fill(data.product); });
  });
  async function availability(product) {
    if (!await admin.confirm({ title: product.is_active ? 'Deactivate product?' : 'Activate product?', message: product.name, label: product.is_active ? 'Deactivate' : 'Activate' })) return;
    try { await admin.api('/api/v1/admin/products/' + encodeURIComponent(product.uid), {method: 'PATCH', body: {is_active: product.is_active ? 0 : 1}}); await load(); } catch (err) { admin.notify(err.message, true); }
  }
  async function archive(product) {
    if (!await admin.confirm({title: 'Archive product?', message: `${product.name} will be hidden from the storefront. Historical orders stay intact. You can activate it again.`, label: 'Archive', danger: true})) return;
    try { await admin.api('/api/v1/admin/products/' + encodeURIComponent(product.uid), {method: 'DELETE'}); admin.notify('Product archived.'); await load(); } catch (err) { admin.notify(err.message, true); }
  }
  $('product-reload').addEventListener('click', async () => { if (!working && await discard()) { try { fill((await admin.api('/api/v1/admin/products/' + encodeURIComponent(current.uid))).data.product); } catch (err) { error($('product-error'), err); } } });
  $('product-close').addEventListener('click', close); dialog.addEventListener('cancel', event => { event.preventDefault(); close(); });
  window.addEventListener('beforeunload', event => { if (dirty || working) { event.preventDefault(); event.returnValue = ''; } });
  $('products-create').addEventListener('click', () => open(null));
  filters.addEventListener('submit', event => { event.preventDefault(); applied = new URLSearchParams(new FormData(filters)); page = 1; load(); });
  $('products-refresh').addEventListener('click', load);
  $('products-prev').addEventListener('click', () => { if (page > 1) { page--; load(); } });
  $('products-next').addEventListener('click', () => { if (page < pageCount) { page++; load(); } });
  load();
})();
