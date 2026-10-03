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

  async function api(path, body) {
    const csrf = await getCsrf();
    const response = await fetch(path, {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        [csrf.header_name]: csrf.token_value,
      },
      credentials: 'same-origin',
      body: JSON.stringify(body),
    });
    const payload = await response.json();

    if (!response.ok || !payload.success) {
      throw new Error(payload.message || 'Request failed');
    }

    return payload;
  }

  function setMessage(form, message, isError = false) {
    const node = form.closest('.auth-card')?.querySelector('[data-auth-message]');
    if (!node) {
      return;
    }

    node.textContent = message || '';
    node.style.color = isError ? '#ffb4ab' : '#86c83c';
  }

  function showOtp(form, code) {
    const node = form.querySelector('[data-auth-dev-otp]');
    if (!node) {
      return;
    }

    node.hidden = !code;
    node.textContent = code ? `Testing OTP: ${code}` : '';
  }

  function targetFor(user, redirect) {
    if (user.role === 'admin') {
      return '/admin';
    }

    if (user.role === 'delivery') {
      return '/delivery';
    }

    return redirect || '/account';
  }

  document.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-send-auth-otp]');
    if (!button) {
      return;
    }

    const form = button.closest('[data-auth-form]');
    const phone = form.querySelector('[data-auth-phone]')?.value || '';

    showOtp(form, '');
    setMessage(form, 'Sending OTP...');

    try {
      const payload = await api('/api/v1/auth/otp/start', { phone });
      showOtp(form, payload.data.dev_otp);

      if (!payload.data.user_exists) {
        setMessage(form, 'New number. Verify the OTP and we will create your customer account.');
        return;
      }

      setMessage(form, 'OTP generated. Enter the testing OTP shown above.');
    } catch (error) {
      setMessage(form, error.message, true);
    }
  });

  document.addEventListener('submit', async (event) => {
    const form = event.target.closest('[data-auth-form]');
    if (!form) {
      return;
    }

    event.preventDefault();
    setMessage(form, 'Verifying OTP...');

    const phone = form.querySelector('[data-auth-phone]')?.value || '';
    const otp = form.querySelector('[data-auth-otp]')?.value || '';
    const redirect = form.querySelector('[data-auth-redirect]')?.value || '';

    try {
      const payload = await api('/api/v1/auth/otp/verify', { phone, otp });
      window.location.href = targetFor(payload.data.user, redirect);
    } catch (error) {
      setMessage(form, error.message, true);
    }
  });
})();
