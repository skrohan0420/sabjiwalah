(function () {
  const panels = [...document.querySelectorAll('[data-delivery-pin]')];
  if (!panels.length) return;
  let busy = false, alive = true;
  const states = panels.map(panel => ({
    panel, button: panel.querySelector('[data-pin-generate]'),
    value: panel.querySelector('[data-pin-value]'), message: panel.querySelector('[data-pin-message]'),
    expires: 0, retry: 0,
  }));
  function clear(state) { state.value.textContent = ''; state.value.hidden = true; state.expires = 0; }
  function refresh() {
    for (const state of states) {
      if (state.expires && Date.now() >= state.expires) {
        clear(state);
        state.message.textContent = 'Your PIN expired. Generate a new PIN when your order arrives.';
      }
      state.button.disabled = busy || Date.now() < state.retry;
    }
  }
  for (const state of states) state.button.addEventListener('click', async () => {
    if (busy || Date.now() < state.retry || !alive) return;
    busy = true; clear(state); refresh();
    state.message.textContent = 'Generating your PIN…';
    let mutationStarted = false;
    try {
      const csrfResponse = await fetch(window.Sabjiwalah.url('api/v1/csrf'), {
        credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' },
      });
      const csrf = await csrfResponse.json();
      if (!csrfResponse.ok || !csrf.success) throw new Error('Unable to start. Refresh this page and try again.');
      mutationStarted = true;
      state.retry = Date.now() + 60000;
      const response = await fetch(window.Sabjiwalah.url('api/v1/orders/' + encodeURIComponent(state.panel.dataset.deliveryPin) + '/delivery-pin'), {
        method: 'POST', credentials: 'same-origin', cache: 'no-store',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json', [csrf.data.header_name]: csrf.data.token_value },
        body: '{}',
      });
      const result = await response.json();
      if (!alive) return;
      if (!response.ok || !result.success) {
        if (response.status === 429) state.retry = Date.now() + Math.min(1800, Math.max(1, Number(result.data?.retry_after) || 60)) * 1000;
        throw new Error(response.status === 401 ? 'Your session expired. Sign in again and reload your orders.' : result.message || 'Unable to generate your PIN.');
      }
      if (!/^\d{6}$/.test(result.data.pin) || result.data.valid_for_seconds !== 1800
        || !Number.isFinite(Date.parse(result.data.expires_at)) || result.data.retry_after !== 60) throw new Error('Unexpected response. Wait one minute before trying again.');
      state.value.textContent = result.data.pin; state.value.hidden = false;
      state.expires = Math.min(Date.parse(result.data.expires_at), Date.now() + result.data.valid_for_seconds * 1000);
      state.retry = Date.now() + result.data.retry_after * 1000;
      state.button.textContent = 'Generate a new PIN';
      state.message.textContent = 'Valid for 30 minutes. This PIN is shown once and is cleared when you leave this page. Wait one minute before generating another.';
    } catch (error) {
      if (alive) {
        if (mutationStarted && (error instanceof TypeError || error instanceof SyntaxError)) {
          state.retry = Date.now() + 60000;
          state.message.textContent = 'Connection lost. A PIN may have been generated. Wait one minute before trying again.';
        } else state.message.textContent = error instanceof Error ? error.message : 'Unable to generate your PIN. Wait one minute before trying again.';
      }
    } finally { busy = false; if (alive) refresh(); }
  });
  const timer = setInterval(refresh, 1000);
  document.addEventListener('visibilitychange', refresh);
  window.addEventListener('pagehide', () => { alive = false; states.forEach(clear); clearInterval(timer); });
  window.addEventListener('pageshow', event => { if (event.persisted) window.location.reload(); });
})();
