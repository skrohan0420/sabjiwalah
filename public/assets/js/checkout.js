(function () {
  const money = new Intl.NumberFormat('en-IN', {
    style: 'currency',
    currency: 'INR',
  });

  let mutationBusy = false, quotePending = true, quoteToken = null, summaryGeneration = 0, placementUncertain = false, orderPlaced = false;
  let confirmationAudio;
  function prepareConfirmationSound() {
    try {
      const AudioContext = window.AudioContext || window.webkitAudioContext;
      if (!AudioContext) return;
      confirmationAudio ||= new AudioContext();
      // Unlock audio during the user's submit gesture, before the network request.
      if (confirmationAudio.state === 'suspended') confirmationAudio.resume().catch(() => {});
    } catch { /* Confirmation remains available without audio support. */ }
  }
  function playConfirmationSound() {
    try {
      if (!confirmationAudio || confirmationAudio.state !== 'running') return;
      const start = confirmationAudio.currentTime;
      // A warm opening followed by two sparkling notes and a gentle chord tail.
      const notes = [[392, 0, 0.09, 0.45], [783.99, 0.08, 0.095, 0.7],
        [1046.5, 0.19, 0.08, 0.9], [523.25, 0.21, 0.04, 0.85], [659.25, 0.23, 0.035, 0.8]];
      notes.forEach(([frequency, delay, volume, decay]) => {
        const tone = confirmationAudio.createOscillator();
        const envelope = confirmationAudio.createGain();
        const time = start + delay;
        tone.type = 'sine';
        tone.frequency.value = frequency;
        envelope.gain.setValueAtTime(0, time);
        envelope.gain.linearRampToValueAtTime(volume, time + 0.015);
        envelope.gain.exponentialRampToValueAtTime(0.0001, time + decay);
        tone.connect(envelope);
        envelope.connect(confirmationAudio.destination);
        tone.start(time);
        tone.stop(time + decay + 0.05);
        tone.onended = () => { tone.disconnect(); envelope.disconnect(); };
        // A quiet upper harmonic adds a glassy shimmer without a sharp attack.
        const shimmer = confirmationAudio.createOscillator();
        const shimmerEnvelope = confirmationAudio.createGain();
        shimmer.type = 'sine';
        shimmer.frequency.value = frequency * 2;
        shimmerEnvelope.gain.setValueAtTime(0, time);
        shimmerEnvelope.gain.linearRampToValueAtTime(volume * 0.16, time + 0.025);
        shimmerEnvelope.gain.exponentialRampToValueAtTime(0.0001, time + decay * 0.65);
        shimmer.connect(shimmerEnvelope);
        shimmerEnvelope.connect(confirmationAudio.destination);
        shimmer.start(time);
        shimmer.stop(time + decay);
        shimmer.onended = () => { shimmer.disconnect(); shimmerEnvelope.disconnect(); };
      });
    } catch { /* A sound failure must not interrupt a confirmed order. */ }
  }

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
      throw Object.assign(new Error(payload.message || 'Request failed'), {status: response.status});
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

  function updatePlacement() {
    const submit = document.querySelector('[data-place-order]');
    if (submit) submit.disabled = orderPlaced || mutationBusy || quotePending || !quoteToken || placementUncertain;
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
    setText('[data-checkout-discount]', money.format(checkout.discount_amount));
    setText('[data-checkout-total]', money.format(checkout.total_amount));
    quoteToken = checkout.quote_token;
    if (checkout.can_place_order === false) quoteToken = null;
    const minimum = Number(checkout.minimum_order_amount || 0);
    const belowMinimum = Number(checkout.subtotal) < minimum;
    setText('[data-checkout-policy]', checkout.checkout_notice || (belowMinimum ? 'Minimum order subtotal: ' + money.format(minimum) + '.' : ''));
    document.querySelectorAll('[data-checkout-policy]').forEach(node => { node.hidden = !belowMinimum; });
    const status = document.querySelector('[data-coupon-status]'), remove = document.querySelector('[data-coupon-remove]');
    if (status) status.textContent = checkout.offer_error || (checkout.applied_code ? checkout.applied_code + ' applied. You save ' + money.format(checkout.discount_amount) + '.' : 'One coupon per order. Delivery charges use the subtotal before discounts.');
    if (remove) remove.hidden = !checkout.selected_code;
  }

  function setText(selector, value) {
    document.querySelectorAll(selector).forEach(node => { node.textContent = value; });
  }

  function escapeHtml(value) {
    const span = document.createElement('span');
    span.textContent = value;
    return span.innerHTML;
  }

  async function loadSummary() {
    const generation = ++summaryGeneration; quotePending = true; quoteToken = null; updatePlacement();
    try {
      const payload = await api('/api/v1/checkout/summary');
      if (generation !== summaryGeneration) return;
      renderSummary(payload.data.checkout);
    } finally {
      if (generation === summaryGeneration) { quotePending = false; updatePlacement(); }
    }
  }

  function busy(working) {
    mutationBusy = working;
    document.querySelectorAll('[data-coupon-apply], [data-coupon-remove]').forEach(button => { button.disabled = working; });
    updatePlacement();
  }

  async function coupon(method) {
    if (mutationBusy) return;
    busy(true); ++summaryGeneration; quotePending = true; quoteToken = null;
    const status = document.querySelector('[data-coupon-status]');
    try {
      setMessage('');
      const code = document.querySelector('[data-coupon-code]').value.trim();
      const payload = await api('/api/v1/checkout/offer', {method, body: JSON.stringify(method === 'POST' ? {code} : {})});
      renderSummary(payload.data.checkout);
    } catch (error) { if (status) status.textContent = error.message; setMessage(error.message, true); }
    finally { quotePending = false; busy(false); }
    // Re-read the selected server coupon after rejected or ambiguous requests; never repeat the mutation.
    await loadSummary().catch(error => setMessage(error.message, true));
  }

  window.addEventListener('sabjiwalah:cart-changed', () => {
    if (!mutationBusy) loadSummary().catch(error => setMessage(error.message, true));
    else { ++summaryGeneration; quoteToken = null; quotePending = true; }
  });

  document.addEventListener('click', async (event) => {
    if (event.target.closest('[data-coupon-remove]')) await coupon('DELETE');
  });

  document.addEventListener('submit', async (event) => {
    if (event.target.matches('[data-coupon-form]')) { event.preventDefault(); await coupon('POST'); return; }
    const form = event.target.closest('[data-checkout-form]');

    if (!form) {
      return;
    }

    event.preventDefault();
    if (orderPlaced || mutationBusy || placementUncertain) return;
    setMessage('');

    const data = Object.fromEntries(new FormData(form).entries());
    delete data.otp;
    data.quote_token = quoteToken;
    if (!data.delivery_latitude || !data.delivery_longitude || !data.address_line || !data.city || !data.postal_code) {
      setMessage('Choose a delivery pin with a complete address and postal code before placing your order.', true);
      return;
    }

    if (quotePending || !quoteToken) { setMessage('Review a current checkout total and remove any unavailable coupon before placing the order.', true); return; }
    prepareConfirmationSound();
    const placeButton = document.querySelector('[data-place-order]');
    if (placeButton) {
      placeButton.classList.add('is-loading');
      placeButton.setAttribute('aria-busy', 'true');
      placeButton.textContent = 'Placing order…';
    }
    busy(true);
    try {
      const payload = await api('/api/v1/checkout/place', {
        method: 'POST',
        body: JSON.stringify(data),
      });

      orderPlaced = true;
      playConfirmationSound();
      const order = payload.data.order;
      const destination = window.Sabjiwalah.url(order.uid ? '/orders/' + encodeURIComponent(order.uid) : '/orders');
      const confirmation = document.querySelector('[data-order-confirmation]');
      if (confirmation) {
        confirmation.hidden = false;
        confirmation.querySelector('[data-confirmation-number]').textContent = order.order_number;
        const link = confirmation.querySelector('a');
        link.href = destination;
        link.focus();
        document.body.classList.add('is-order-confirmed');
      }
      setMessage(`Order confirmed. Order number: ${order.order_number}`);
    } catch (error) {
      placementUncertain = !error.status || error.status >= 500;
      setMessage(error.message, true);
      if (placementUncertain) setMessage(error.message + ' Check your orders before reloading this page to try again.', true);
      else await loadSummary().catch(summaryError => setMessage(summaryError.message, true));
    } finally {
      if (placeButton) {
        placeButton.classList.remove('is-loading');
        placeButton.setAttribute('aria-busy', 'false');
        placeButton.textContent = orderPlaced ? 'Order confirmed' : 'Place Order';
      }
      busy(false);
    }
  });

  if (document.querySelector('[data-checkout-page]')) {
    const form = document.querySelector('[data-checkout-form]');
    const map = form.querySelector('[data-checkout-map]');
    const mapStatus = form.querySelector('[data-checkout-map-status]');
    const updateMap = () => {
      if (!map) return;
      const latitude = form.elements.namedItem('delivery_latitude').value;
      const longitude = form.elements.namedItem('delivery_longitude').value;
      const hasPin = latitude !== '' && longitude !== '';
      const address = ['address_line', 'city', 'state', 'postal_code']
        .map(name => form.elements.namedItem(name).value.trim()).filter(Boolean).join(', ');
      const query = hasPin ? `${latitude},${longitude}` : address;
      map.hidden = !query;
      if (query) {
        const url = `https://maps.google.com/maps?q=${encodeURIComponent(query)}&z=18&t=h&output=embed`;
        if (map.getAttribute('src') !== url) map.src = url;
      } else map.removeAttribute('src');
      const addressText = form.querySelector('[data-checkout-map-address]');
      if (addressText) addressText.textContent = address;
      mapStatus.textContent = hasPin
        ? 'Address fetched from your confirmed delivery pin.'
        : query ? 'Approximate address preview. Choose a delivery pin to confirm the exact spot.'
          : 'Choose your delivery pin to fetch the address.';
    };
    const applyPin = pin => {
      if (form && pin?.version === 2 && Number.isFinite(pin.latitude) && Number.isFinite(pin.longitude)
          && Math.abs(pin.latitude) <= 90 && Math.abs(pin.longitude) <= 180) {
        const values = { address_line: [pin.addressLine, pin.detail].filter(Boolean).join(', ').slice(0, 500),
          city: pin.city, state: pin.state, postal_code: pin.postalCode,
          delivery_latitude: pin.latitude, delivery_longitude: pin.longitude };
        Object.entries(values).forEach(([name, value]) => {
          const input = form.elements.namedItem(name);
          if (input) input.value = value === undefined || value === null ? '' : value;
        });

      }
      updateMap();
    };
    try { applyPin(JSON.parse(localStorage.getItem('sabjiwalah.deliveryLocation'))); }
    catch { /* Checkout stays available without storage. */ }
    document.addEventListener('sabjiwalah:location-changed', event => applyPin(event.detail));
    updateMap();
    form.addEventListener('change', event => {
      if (['address_line', 'city', 'state', 'postal_code'].includes(event.target.name)) updateMap();
    });
    try {
      const profile = JSON.parse(localStorage.getItem('sabjiwalah.customerProfile') || 'null');
      const name = form.elements.namedItem('customer_name');
      if (name && (!name.value || /^Customer(?: \d+)?$/.test(name.value)) && typeof profile?.name === 'string') {
        name.value = profile.name.trim().slice(0, 120);
      }
    } catch { /* Name entry stays available without browser storage. */ }
    const contactName = form.querySelector('[data-checkout-customer-name]');
    if (contactName) contactName.textContent = form.elements.namedItem('customer_name').value;
    loadSummary().catch((error) => setMessage(error.message, true));
  }
})();
