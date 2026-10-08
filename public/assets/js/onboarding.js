(() => {
  'use strict';
  const overlay = document.querySelector('[data-onboarding]');
  if (!overlay) return;
  const profileKey = 'sabjiwalah.customerProfile';
  const skippedKey = 'sabjiwalah.welcomeSkipped';
  const required = overlay.dataset.required === 'true';
  const locationKey = 'sabjiwalah.deliveryLocation';
  const input = overlay.querySelector('[data-welcome-name]');
  const form = overlay.querySelector('[data-welcome-name-form]');
  const locationStep = overlay.querySelector('[data-welcome-location-step]');
  const error = overlay.querySelector('[data-welcome-error]');
  const edit = document.querySelector('[data-onboarding-edit]');
  let saving = false;
  let previousFocus, waitingForMap = false, step = 1;
  const read = key => { try { return JSON.parse(localStorage.getItem(key)); } catch { return null; } };
  const cleanName = value => String(value || '').replace(/[\u0000-\u001f\u007f]/g, '').trim().replace(/\s+/g, ' ').slice(0, 120);
  const validPin = pin => pin && Number.isFinite(pin.latitude) && Number.isFinite(pin.longitude)
    && Math.abs(pin.latitude) <= 90 && Math.abs(pin.longitude) <= 180;
  const accountName = /^Customer(?: \d+)?$/.test(overlay.dataset.accountName) ? '' : cleanName(overlay.dataset.accountName);
  const saved = read(profileKey);
  input.value = cleanName(saved?.name) || accountName;
  function setInert(value) {
    Array.from(overlay.parentElement.children).forEach(node => {
      if (node !== overlay && !['SCRIPT', 'LINK'].includes(node.tagName)) node.inert = value;
    });
  }
  function renderGreeting() {
    const profile = read(profileKey);
    const name = cleanName(profile?.name) || accountName;
    if (!edit) return;
    edit.hidden = !name;
    edit.querySelector('[data-onboarding-greeting]').textContent = name ? `Hello, ${name.split(' ')[0]} 👋` : '';
  }
  function renderStep(next) {
    step = next;
    const name = cleanName(input.value).split(' ')[0];
    form.hidden = next !== 1;
    locationStep.hidden = next !== 2;
    overlay.querySelector('[data-welcome-title]').textContent = next === 1 ? 'Fresh starts here.' : `Where to, ${name}?`;
    overlay.querySelector('[data-welcome-subtitle]').textContent = next === 1
      ? 'A little hello before we fill your basket.' : 'Let’s find the right doorstep for your fresh picks.';
    overlay.querySelector('[data-welcome-progress]').textContent = next === 1 ? '01 / 02' : '02 / 02';
    overlay.querySelector('[data-welcome-second]').classList.toggle('is-active', next === 2);
    const pin = read(locationKey);
    const hasPin = validPin(pin);
    overlay.querySelector('[data-welcome-location-label]').textContent = hasPin ? pin.label || 'Your saved delivery point' : 'Pin your delivery address';
    overlay.querySelector('[data-welcome-location-detail]').textContent = hasPin
      ? [pin.city, pin.state, pin.postalCode].filter(Boolean).join(', ') || pin.summary || 'Tap to check or change your pin'
      : 'Search your area or use your current location';
    const finish = overlay.querySelector('[data-welcome-finish]');
    finish.replaceChildren(document.createTextNode(hasPin ? 'Start shopping ' : 'Choose location '));
    const arrow = document.createElement('span'); arrow.textContent = '→'; arrow.setAttribute('aria-hidden', 'true'); finish.appendChild(arrow);
    error.textContent = '';
  }
  function show(next = step) {
    if (overlay.hidden) previousFocus = document.activeElement;
    overlay.hidden = false;
    document.body.classList.add('is-welcome-open');
    setInert(true);
    renderStep(next);
    // Avoid opening the phone keyboard before the visitor chooses to type.
    (overlay.querySelector('[data-onboarding-skip]') || overlay.querySelector('.welcome-panel')).focus();
  }
  function hide(restoreFocus = true) {
    overlay.hidden = true;
    document.body.classList.remove('is-welcome-open');
    setInert(false);
    if (restoreFocus) previousFocus?.focus();
  }
  function skip() {
    if (required || saving) return;
    try { sessionStorage.setItem(skippedKey, 'true'); } catch { /* Browsing remains available without storage. */ }
    hide();
  }
  function openMap() {
    waitingForMap = true;
    hide(false);
    document.dispatchEvent(new CustomEvent('sabjiwalah:location-open'));
  }
  form.addEventListener('submit', event => {
    event.preventDefault();
    const name = cleanName(input.value);
    if (name.length < 2) { error.textContent = 'Please enter your name (at least 2 characters).'; input.focus(); return; }
    input.value = name;
    // Keep the entered name if the visitor reloads while choosing their pin.
    if (!read(profileKey)?.completed) {
      try { localStorage.setItem(profileKey, JSON.stringify({ version: 1, name, completed: false })); } catch { /* Report persistence failure when finishing. */ }
    }
    renderStep(2);
    overlay.querySelector('[data-welcome-location]').focus();
  });
  overlay.querySelector('[data-welcome-back]').addEventListener('click', () => { renderStep(1); input.focus(); });
  overlay.querySelector('[data-welcome-location]').addEventListener('click', openMap);
  overlay.querySelector('[data-welcome-finish]').addEventListener('click', async () => {
    if (saving) return;
    if (!validPin(read(locationKey))) { openMap(); return; }
    const name = cleanName(input.value);
    if (name.length < 2) { renderStep(1); input.focus(); return; }
    const buttons = Array.from(overlay.querySelectorAll('button'));
    try {
      saving = true;
      buttons.forEach(button => button.disabled = true);
      if (overlay.dataset.saveAccount === 'true') {
        error.textContent = 'Saving your details…';
        const csrfResponse = await fetch(window.Sabjiwalah.url('/api/v1/csrf'), { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        const csrf = await csrfResponse.json();
        if (!csrfResponse.ok || !csrf.success) throw new Error('Unable to save. Please try again.');
        const response = await fetch(window.Sabjiwalah.url('/api/v1/account/profile'), {
          method: 'PATCH', credentials: 'same-origin',
          headers: { Accept: 'application/json', 'Content-Type': 'application/json', [csrf.data.header_name]: csrf.data.token_value },
          body: JSON.stringify({ name, email: overlay.dataset.accountEmail || '', delivery_location: read(locationKey) }),
        });
        const result = await response.json();
        if (!response.ok || !result.success) throw new Error(result.message || 'Unable to save. Please try again.');
        if (result.data?.user?.phone) {
          try { localStorage.setItem('sabjiwalah.loginProfile', JSON.stringify({ phone: result.data.user.phone, name })); }
          catch { /* The account name has already been saved on the server. */ }
        }
        // Account setup is persisted before the browser welcome state is completed.
      }
      try {
        localStorage.setItem(profileKey, JSON.stringify({ version: 1, name, completed: true }));
        sessionStorage.removeItem(skippedKey);
      } catch (storageError) {
        // A successful account save must not trap someone because browser storage is full.
        if (overlay.dataset.saveAccount !== 'true') throw storageError;
      }
    } catch (failure) {
      error.textContent = overlay.dataset.saveAccount === 'true' ? failure.message : 'We couldn’t remember your details. Enable browser storage or choose Later to browse.';
      return;
    } finally { saving = false; buttons.forEach(button => button.disabled = false); }
    renderGreeting();
    hide();
    if (overlay.dataset.saveAccount === 'true') window.location.reload();
  });
  overlay.querySelector('[data-onboarding-skip]')?.addEventListener('click', skip);
  edit?.addEventListener('click', () => { input.value = cleanName(read(profileKey)?.name) || accountName; show(1); });
  document.addEventListener('sabjiwalah:location-closed', () => {
    if (waitingForMap) { waitingForMap = false; show(2); }
  });
  overlay.addEventListener('keydown', event => {
    if (event.key === 'Escape') { event.preventDefault(); skip(); return; }
    if (event.key !== 'Tab') return;
    const nodes = Array.from(overlay.querySelectorAll('button,input')).filter(node => !node.disabled && node.getClientRects().length);
    const first = nodes[0], last = nodes[nodes.length - 1];
    if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
    else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
  });
  renderGreeting();
  let skipped = false;
  try { skipped = sessionStorage.getItem(skippedKey) === 'true'; } catch { /* First visit still works. */ }
  if (required || ((!saved?.completed || !validPin(read(locationKey))) && !skipped)) show(required ? 1 : input.value.length >= 2 ? 2 : 1);
})();
