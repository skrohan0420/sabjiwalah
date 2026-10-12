(() => {
  'use strict';
  const byId = id => document.getElementById(id);
  const logout = byId('delivery-logout'), message = byId('delivery-shell-message');
  const progress = byId('delivery-progress'), network = byId('delivery-network');
  const toast = byId('delivery-toast'), dialog = byId('delivery-confirm');
  let busy = false, alive = true, toastTimer, pendingConfirmation;
  const requests = new Set();
  function setBusy(value, text = 'Please wait…') {
    busy = value;
    logout.disabled = value;
    progress.textContent = value ? text : '';
    progress.hidden = !value;
    byId('delivery-main').setAttribute('aria-busy', String(value));
  }
  function notify(text) {
    if (!alive) return;
    clearTimeout(toastTimer);
    toast.textContent = text;
    toast.hidden = false;
    toastTimer = setTimeout(() => { toast.hidden = true; }, 5000);
  }
  function errorText(error) {
    if (error.status === 401) return 'Your session has expired. Sign in again to continue.';
    if (error.code === 'csrf') return 'Security verification expired. Refresh this page before trying again.';
    if (error.status === 403) return 'Access is no longer permitted. Refresh this page or sign in again.';
    return error.message || 'Check your connection and try again.';
  }
  async function request(path, options) {
    const controller = new AbortController();
    requests.add(controller);
    const timeout = setTimeout(() => controller.abort(), 20000);
    try {
      const response = await fetch(window.Sabjiwalah.url(path), {
        credentials: 'same-origin', cache: 'no-store', ...options, signal: controller.signal,
      });
      let data;
      try { data = await response.json(); }
      catch { throw Object.assign(new Error('Unexpected server response. Refresh before trying again.'), { status: response.ok ? undefined : response.status }); }
      if (!response.ok || !data.success) throw Object.assign(new Error(data.message || 'Request failed.'), {
        status: response.status, code: response.status === 403 && /csrf/i.test(data.message || '') ? 'csrf' : data.data?.code,
        retryAfter: data.data?.retry_after,
      });
      return data;
    } catch (error) {
      if (error.name === 'AbortError') throw new Error('The request did not finish. Refresh orders before trying again.');
      throw error;
    } finally { clearTimeout(timeout); requests.delete(controller); }
  }
  async function api(path, body, method = 'POST', canSend = () => true) {
    if (!alive) throw new Error('This page is no longer active.');
    if (navigator.onLine === false) throw new Error('You are offline. Reconnect before trying again.');
    const headers = { Accept: 'application/json' };
    if (body !== undefined) {
      const csrf = await request('api/v1/csrf', { headers });
      headers[csrf.data.header_name] = csrf.data.token_value;
      headers['Content-Type'] = 'application/json';
    }
    if (!alive || !canSend()) throw new Error('This operation is no longer active.');
    return request(path, { method: body === undefined ? 'GET' : method, headers, body: body === undefined ? undefined : JSON.stringify(body) });
  }
  function confirm(text, title = 'Confirm action') {
    if (busy || pendingConfirmation || !alive) return Promise.resolve(false);
    if (typeof dialog.showModal !== 'function') return Promise.resolve(window.confirm(text));
    byId('delivery-confirm-title').textContent = title;
    byId('delivery-confirm-description').textContent = text;
    const previousFocus = document.activeElement;
    return new Promise(resolve => {
      pendingConfirmation = result => {
        pendingConfirmation = null;
        dialog.close();
        if (alive && previousFocus?.isConnected) previousFocus.focus();
        resolve(result);
      };
      dialog.showModal();
      byId('delivery-confirm-cancel').focus();
    });
  }
  byId('delivery-confirm-cancel').addEventListener('click', () => pendingConfirmation?.(false));
  byId('delivery-confirm-accept').addEventListener('click', () => pendingConfirmation?.(true));
  dialog.addEventListener('cancel', event => { event.preventDefault(); pendingConfirmation?.(false); });
  const updateNetwork = () => { network.hidden = navigator.onLine !== false; };
  window.addEventListener('online', updateNetwork);
  window.addEventListener('offline', updateNetwork);
  updateNetwork();
  window.SabjiwalahDelivery = { api, confirm, notify, errorText, setBusy, isBusy: () => busy || !!pendingConfirmation };
  logout.addEventListener('click', async () => {
    if (busy || pendingConfirmation) return;
    setBusy(true, 'Signing out…');
    if (window.SabjiwalahDeliveryTracking) await window.SabjiwalahDeliveryTracking.stop('Tracking stopped for sign out.');
    message.hidden = true;
    try {
      await api('api/v1/delivery/auth/logout', {});
      if (alive) window.location.assign(window.Sabjiwalah.url('delivery/login'));
    } catch (error) {
      if (!alive) return;
      if (error.status === 401) { window.location.assign(window.Sabjiwalah.url('delivery/login')); return; }
      message.textContent = errorText(error);
      message.hidden = false;
    } finally { setBusy(false); }
  });
  window.addEventListener('pagehide', () => {
    alive = false;
    pendingConfirmation?.(false);
    clearTimeout(toastTimer);
    toast.hidden = true;
    requests.forEach(controller => controller.abort());
  });
  window.addEventListener('pageshow', event => { if (event.persisted) window.location.reload(); });
})();
