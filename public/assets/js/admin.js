(() => {
  'use strict';
  const loginUrl = document.currentScript.dataset.loginUrl;
  let mutationQueue = Promise.resolve();
  class ApiError extends Error {
    constructor(message, status = 0, errors = null) { super(message); this.name = 'ApiError'; this.status = status; this.errors = errors; }
  }
  function notify(message, error = false) {
    const node = document.getElementById('admin-feedback');
    node.textContent = message;
    node.classList.toggle('admin-alert-error', error);
    node.setAttribute('role', error ? 'alert' : 'status');
    node.hidden = false;
  }
  function expired() {
    const url = new URL(loginUrl, location.href);
    url.searchParams.set('redirect', location.pathname + location.search);
    location.assign(url.href);
  }
  async function send(path, options = {}) {
    const url = new URL(window.Sabjiwalah.url(path), location.href);
    if (url.origin !== location.origin) throw new ApiError('Admin requests must use the same origin.');
    const controller = new AbortController();
    const abort = () => controller.abort();
    if (options.signal?.aborted) abort();
    else options.signal?.addEventListener('abort', abort, { once: true });
    const timer = setTimeout(abort, 20000);
    try {
      const response = await fetch(url.href, { ...options, credentials: 'same-origin', signal: controller.signal, redirect: 'error', cache: 'no-store' });
      if (response.status === 401) { expired(); throw new ApiError('Your session has expired. Please sign in again.', 401); }
      let payload;
      try { payload = await response.json(); } catch { throw new ApiError('The server returned an unexpected response. Please try again.', response.status); }
      if (!response.ok || !payload.success) {
        const message = response.status >= 500 ? 'The server could not complete the request. Please try again.' : (payload.message || 'Request failed.');
        throw new ApiError(message, response.status, payload.errors || null);
      }
      return payload;
    } catch (error) {
      if (error instanceof ApiError) throw error;
      throw new ApiError(controller.signal.aborted ? 'Request cancelled or timed out. Check the result before trying again.' : 'Unable to reach the server. Check your connection and try again.');
    } finally { clearTimeout(timer); options.signal?.removeEventListener('abort', abort); }
  }
  async function execute(path, options, method) {
    const headers = new Headers(options.headers || {});
    headers.set('Accept', 'application/json');
    let body = options.body;
    if (body != null && !(body instanceof FormData) && typeof body !== 'string') body = JSON.stringify(body);
    if (body != null && !(body instanceof FormData)) headers.set('Content-Type', 'application/json');
    const mutates = !['GET', 'HEAD'].includes(method);
    const request = () => send(path, { ...options, method, headers, body });
    if (!mutates) return request();
    const token = async () => {
      const payload = await send('/api/v1/csrf', { headers: { Accept: 'application/json' }, signal: options.signal });
      headers.set(payload.data.header_name, payload.data.token_value);
    };
    await token();
    try { return await request(); } catch (error) {
      // This exact response comes from the CSRF filter before the action runs.
      // Never retry ambiguous network failures or general authorization errors.
      if (error.status !== 403 || error.message !== 'Invalid or missing CSRF token') throw error;
      await token();
      return request();
    }
  }
  function api(path, options = {}) {
    const method = (options.method || 'GET').toUpperCase();
    if (['GET', 'HEAD'].includes(method)) return execute(path, options, method);
    const pending = mutationQueue.then(() => execute(path, options, method));
    mutationQueue = pending.catch(() => {});
    return pending;
  }
  function busy(button, working) {
    button.disabled = working;
    button.setAttribute('aria-busy', String(working));
  }
  function confirmAction({ title = 'Confirm action', message = 'Continue with this action?', label = 'Confirm', danger = false } = {}) {
    const dialog = document.getElementById('admin-confirm');
    if (dialog.open) return Promise.resolve(false);
    document.getElementById('admin-confirm-title').textContent = title;
    document.getElementById('admin-confirm-message').textContent = message;
    const action = dialog.querySelector('[data-confirm-action]');
    action.textContent = label;
    action.classList.toggle('admin-button-danger', danger);
    dialog.returnValue = '';
    return new Promise(resolve => { dialog.addEventListener('close', () => resolve(dialog.returnValue === 'confirm'), { once: true }); dialog.showModal(); });
  }
  window.SabjiwalahAdmin = { api, ApiError, notify, busy, confirm: confirmAction };

  const menu = document.querySelector('[data-admin-menu]');
  const sidebar = document.getElementById('admin-sidebar');
  const workspace = document.querySelector('.admin-workspace');
  const backdrop = document.querySelector('.admin-backdrop');
  const mobile = matchMedia('(max-width: 900px)');
  function closeMenu(restore = true) {
    document.body.classList.remove('admin-nav-open');
    menu.setAttribute('aria-expanded', 'false');
    backdrop.hidden = true;
    workspace.inert = false;
    if (restore && mobile.matches) menu.focus();
  }
  menu.addEventListener('click', () => {
    document.body.classList.add('admin-nav-open');
    menu.setAttribute('aria-expanded', 'true');
    backdrop.hidden = false;
    workspace.inert = true;
    sidebar.querySelector('[data-admin-close]').focus();
  });
  document.querySelectorAll('[data-admin-close]').forEach(node => node.addEventListener('click', () => closeMenu()));
  mobile.addEventListener('change', () => closeMenu(false));
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape') {
      if (document.body.classList.contains('admin-nav-open')) closeMenu();
      const account = document.querySelector('.admin-account');
      if (account.open) { account.open = false; account.querySelector('summary').focus(); }
    }
    if (event.key !== 'Tab' || !document.body.classList.contains('admin-nav-open')) return;
    const nodes = [...sidebar.querySelectorAll('a[href], button:not(:disabled)')].filter(node => node.getClientRects().length);
    const first = nodes[0], last = nodes[nodes.length - 1];
    if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
    else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
  });
  document.addEventListener('click', event => {
    const account = document.querySelector('.admin-account');
    if (!account.contains(event.target)) account.open = false;
  });
  document.querySelector('[data-admin-logout]').addEventListener('click', async event => {
    const button = event.currentTarget;
    busy(button, true);
    try { await api('/api/v1/auth/logout', { method: 'POST' }); location.assign(loginUrl); }
    catch (error) { notify(error.message, true); busy(button, false); }
  });
})();
