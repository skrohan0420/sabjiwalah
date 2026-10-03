(function () {
  async function getCsrf() {
    const response = await fetch('/api/v1/csrf', {
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

    const response = await fetch(path, {
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
    node.style.color = isError ? '#ffb4ab' : '';
  }

  function renderProfile(user) {
    setText('[data-profile-name]', user.name || '');
    setText('[data-profile-phone]', user.phone || '');
    setText('[data-profile-email]', user.email || 'Not added');
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
    }
  });
})();
