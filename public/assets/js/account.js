(function () {
  const menuMessage = (text) => setText('[data-account-menu-message]', text);
  document.addEventListener('click', async event => {
    const open = event.target.closest('[data-account-dialog]');
    if (open) document.getElementById(open.dataset.accountDialog)?.showModal();
    if (event.target.closest('[data-account-dialog-close]')) event.target.closest('dialog')?.close();
    if (event.target.closest('[data-account-clear-browser]')) {
      try {
        ['sabjiwalah.customerProfile', 'sabjiwalah.deliveryLocation', 'sabjiwalah.loginProfile', 'sabjiwalah.loginPhone'].forEach(key => localStorage.removeItem(key));
        setText('[data-account-privacy-message]', 'Saved browser details cleared. Your account and orders are unchanged.');
        setText('[data-current-location-label]', 'Choose delivery location');
        setText('[data-current-location-detail]', 'Pin your address on the map');
      } catch { setText('[data-account-privacy-message]', 'Unable to clear browser details. Please try again.'); }
    }
    if (event.target.closest('[data-share-app]')) {
      const share = { title: 'Sabjiwalah', text: 'Fresh groceries and vegetables delivered by Sabjiwalah.', url: window.Sabjiwalah.url('/') };
      try {
        if (navigator.share) await navigator.share(share);
        else if (navigator.clipboard) { await navigator.clipboard.writeText(share.url); menuMessage('App link copied.'); }
        else menuMessage(`Share this link: ${share.url}`);
      } catch (error) { if (error.name !== 'AbortError') menuMessage('Unable to share right now. Please try again.'); }
    }
  });
  async function getCsrf() {
    const response = await fetch(window.Sabjiwalah.url('/api/v1/csrf'), {
      headers: { Accept: 'application/json' },
      credentials: 'same-origin',
    });
    const payload = await response.json();

    if (!payload.success) {
      throw new Error(payload.message || 'Unable to get CSRF token');
    }

    return payload.data;
  }

  async function api(path, options = {}) {
    const method = (options.method || 'GET').toUpperCase();
    const headers = {
      Accept: 'application/json',
      ...(options.headers || {}),
    };

    if (options.body) {
      headers['Content-Type'] = 'application/json';
    }

    if (method !== 'GET') {
      const csrf = await getCsrf();
      headers[csrf.header_name] = csrf.token_value;
    }

    const response = await fetch(window.Sabjiwalah.url(path), {
      ...options,
      method,
      headers,
      credentials: 'same-origin',
    });
    const payload = await response.json();

    if (!response.ok || !payload.success) {
      throw new Error(payload.message || 'Request failed');
    }

    return payload;
  }

  function setMessage(message, isError = false) {
    const node = document.querySelector('[data-account-message]');
    if (!node) {
      return;
    }

    node.textContent = message || '';
    node.classList.toggle('is-error', isError);
  }

  function renderProfile(user) {
    setText('[data-profile-name]', user.name || '');
    setText('[data-profile-phone]', user.phone || '');
    setText('[data-profile-email]', user.email || 'Not added');
    setText('[data-profile-initial]', Array.from(user.name || 'S')[0].toUpperCase());
    try {
      const profile = JSON.parse(localStorage.getItem('sabjiwalah.customerProfile') || '{}');
      localStorage.setItem('sabjiwalah.customerProfile', JSON.stringify({ ...profile, name: user.name }));
      localStorage.setItem('sabjiwalah.loginProfile', JSON.stringify({ phone: user.phone, name: user.name }));
    } catch { /* The saved account still works without browser storage. */ }
  }

  function setText(selector, value) {
    const node = document.querySelector(selector);
    if (node) {
      node.textContent = value;
    }
  }

  document.addEventListener('submit', async (event) => {
    const form = event.target.closest('[data-account-form]');

    if (!form) {
      return;
    }

    event.preventDefault();
    const button = form.querySelector('button[type="submit"]');
    if (button.disabled) return;
    button.disabled = true;
    setMessage('Saving...');

    const data = Object.fromEntries(new FormData(form).entries());

    try {
      const payload = await api('/api/v1/account/profile', {
        method: 'PATCH',
        body: JSON.stringify(data),
      });

      renderProfile(payload.data.user);
      setMessage('Profile updated.');
    } catch (error) {
      setMessage(error.message, true);
    } finally {
      button.disabled = false;
    }
  });
})();
