(function () {
  const money = new Intl.NumberFormat('en-IN', {
    style: 'currency',
    currency: 'INR',
  });

  let otpVerified = false;

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
    const node = document.querySelector('[data-checkout-message]');
    if (!node) {
      return;
    }

    node.textContent = message || '';
    node.style.color = isError ? '#ffb4ab' : '';
  }

  function setOtpStatus(message, isError = false) {
    const node = document.querySelector('[data-otp-status]');
    if (!node) {
      return;
    }

    node.textContent = message || '';
    node.style.color = isError ? '#ffb4ab' : '';
  }

  function setDevOtp(code) {
    const node = document.querySelector('[data-dev-otp]');
    if (!node) {
      return;
    }

    node.hidden = !code;
    node.textContent = code ? `Testing OTP: ${code}` : '';
  }

  function setOtpVerified(verified) {
    otpVerified = verified;
    const submit = document.querySelector('[data-place-order]');
    if (submit) {
      submit.disabled = !verified;
    }
  }

  function renderSummary(checkout) {
    const items = document.querySelector('[data-checkout-items]');

    if (items) {
      items.innerHTML = checkout.items.length
        ? checkout.items.map((item) => `
          <div class="checkout-line">
            <span>${escapeHtml(item.product.name)} x ${item.quantity}</span>
            <strong>${money.format(item.total)}</strong>
          </div>
        `).join('')
        : '<p>Your cart is empty.</p>';
    }

    setText('[data-checkout-subtotal]', money.format(checkout.subtotal));
    setText('[data-checkout-delivery]', money.format(checkout.delivery_charge));
    setText('[data-checkout-total]', money.format(checkout.total_amount));
  }

  function setText(selector, value) {
    const node = document.querySelector(selector);
    if (node) {
      node.textContent = value;
    }
  }

  function escapeHtml(value) {
    const span = document.createElement('span');
    span.textContent = value;
    return span.innerHTML;
  }

  async function loadSummary() {
    const payload = await api('/api/v1/checkout/summary');
    renderSummary(payload.data.checkout);
  }

  async function sendOtp() {
    const phone = document.querySelector('[data-checkout-phone]')?.value || '';

    setOtpVerified(false);
    setDevOtp('');
    setOtpStatus('Sending OTP...');

    const payload = await api('/api/v1/checkout/otp/start', {
      method: 'POST',
      body: JSON.stringify({ customer_phone: phone }),
    });

    setDevOtp(payload.data.dev_otp);
    setOtpStatus('OTP generated. Enter the testing OTP shown above.');
  }

  async function verifyOtp() {
    const phone = document.querySelector('[data-checkout-phone]')?.value || '';
    const otp = document.querySelector('[data-otp-input]')?.value || '';

    setOtpStatus('Verifying OTP...');

    await api('/api/v1/checkout/otp/verify', {
      method: 'POST',
      body: JSON.stringify({ customer_phone: phone, otp }),
    });

    setOtpVerified(true);
    setOtpStatus('Phone verified. You can place the order now.');
  }

  document.addEventListener('click', async (event) => {
    if (event.target.closest('[data-send-otp]')) {
      try {
        await sendOtp();
      } catch (error) {
        setOtpVerified(false);
        setOtpStatus(error.message, true);
      }
    }

    if (event.target.closest('[data-verify-otp]')) {
      try {
        await verifyOtp();
      } catch (error) {
        setOtpVerified(false);
        setOtpStatus(error.message, true);
      }
    }
  });

  document.addEventListener('input', (event) => {
    if (event.target.matches('[data-checkout-phone]')) {
      setOtpVerified(false);
      setDevOtp('');
      setOtpStatus('Phone changed. Request a new OTP before placing the order.');
    }
  });

  document.addEventListener('submit', async (event) => {
    const form = event.target.closest('[data-checkout-form]');

    if (!form) {
      return;
    }

    event.preventDefault();
    setMessage('');

    const data = Object.fromEntries(new FormData(form).entries());
    delete data.otp;

    if (!otpVerified) {
      setMessage('Please verify the phone number before placing the order.', true);
      return;
    }

    try {
      const payload = await api('/api/v1/checkout/place', {
        method: 'POST',
        body: JSON.stringify(data),
      });

      setMessage(`Order placed. Order number: ${payload.data.order.order_number}`);
      form.reset();
      await loadSummary();
    } catch (error) {
      setMessage(error.message, true);
    }
  });

  if (document.querySelector('[data-checkout-page]')) {
    const form = document.querySelector('[data-checkout-form]');
    try {
      const pin = JSON.parse(localStorage.getItem('sabjiwalah.deliveryLocation'));
      if (form && pin?.version === 2 && Number.isFinite(pin.latitude) && Number.isFinite(pin.longitude)
          && Math.abs(pin.latitude) <= 90 && Math.abs(pin.longitude) <= 180) {
        const values = { address_line: [pin.addressLine, pin.detail].filter(Boolean).join(', ').slice(0, 500),
          city: pin.city, state: pin.state, postal_code: pin.postalCode,
          delivery_latitude: pin.latitude, delivery_longitude: pin.longitude };
        Object.entries(values).forEach(([name, value]) => {
          const input = form.elements.namedItem(name);
          if (input && !input.value && value !== undefined && value !== null) input.value = value;
        });
        const notice = form.querySelector('[data-checkout-pin]');
        notice.hidden = false;
        notice.textContent = 'Your confirmed delivery pin is attached. Check the address and add your house number.';
        form.addEventListener('input', event => {
          if (!['city', 'state', 'postal_code'].includes(event.target.name)) return;
          form.elements.namedItem('delivery_latitude').value = '';
          form.elements.namedItem('delivery_longitude').value = '';
          notice.textContent = 'Address changed. Return home to confirm a matching delivery pin.';
        });
      }
    } catch { /* Checkout stays available when browser storage is unavailable. */ }
    loadSummary().catch((error) => setMessage(error.message, true));
  }
})();
