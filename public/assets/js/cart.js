(function () {
  const money = new Intl.NumberFormat('en-IN', {
    style: 'currency',
    currency: 'INR',
  });

  let csrf = null;
  let messageTimer = null;

  async function getCsrf() {
    const response = await fetch('/api/v1/csrf', {
      headers: { Accept: 'application/json' },
      credentials: 'same-origin',
    });
    const payload = await response.json();

    if (!payload.success) {
      throw new Error(payload.message || 'Unable to get CSRF token');
    }

    csrf = payload.data;
    return csrf;
  }

  async function api(path, options = {}) {
    const method = (options.method || 'GET').toUpperCase();
    const headers = {
      Accept: 'application/json',
      ...(options.headers || {}),
    };

    if (options.body && !(options.body instanceof FormData)) {
      headers['Content-Type'] = 'application/json';
    }

    if (method !== 'GET') {
      const token = await getCsrf();
      headers[token.header_name] = token.token_value;
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
    if (messageTimer) {
      window.clearTimeout(messageTimer);
      messageTimer = null;
    }

    document.querySelectorAll('[data-cart-message]').forEach((node) => {
      node.textContent = message || '';
      node.classList.toggle('is-visible', Boolean(message));
      node.classList.toggle('is-error', Boolean(message && isError));
      node.style.color = '';
    });

    if (message) {
      messageTimer = window.setTimeout(() => setMessage(''), 2800);
    }
  }

  function updateCount(count) {
    document.querySelectorAll('[data-cart-count], [data-cart-count-badge]').forEach((node) => {
      node.textContent = String(count);
    });
  }

  function renderCart(cart) {
    updateCount(cart.count);
    document.querySelectorAll('[data-cart-subtotal]').forEach((node) => {
      node.textContent = money.format(cart.subtotal);
    });

    const container = document.querySelector('[data-cart-items]');
    if (!container) {
      return;
    }

    if (!cart.items.length) {
      container.innerHTML = '<p>Your cart is empty. Add fresh products from the product page.</p>';
      return;
    }

    container.innerHTML = cart.items.map((item) => `
      <article class="cart-item" data-product-uid="${item.product.uid}">
        <div>
          <h3>${escapeHtml(item.product.name)}</h3>
          <p>${escapeHtml(item.product.unit)} · ${money.format(item.unit_price)} each</p>
        </div>
        <label>
          <span class="sr-only">Quantity</span>
          <input data-cart-quantity type="number" min="0" max="999" value="${item.quantity}">
        </label>
        <strong>${money.format(item.total)}</strong>
        <button type="button" data-remove-cart-item>Remove</button>
      </article>
    `).join('');
  }

  function escapeHtml(value) {
    const span = document.createElement('span');
    span.textContent = value;
    return span.innerHTML;
  }

  async function refreshCart() {
    const payload = await api('/api/v1/cart');
    renderCart(payload.data.cart);
    return payload.data.cart;
  }

  async function addToCart(productUid, quantity) {
    const payload = await api('/api/v1/cart/items', {
      method: 'POST',
      body: JSON.stringify({ product_uid: productUid, quantity }),
    });
    renderCart(payload.data.cart);
    setMessage(payload.message || 'Added to cart');
  }

  async function updateItem(productUid, quantity) {
    const payload = await api(`/api/v1/cart/items/${encodeURIComponent(productUid)}`, {
      method: 'PATCH',
      body: JSON.stringify({ quantity }),
    });
    renderCart(payload.data.cart);
    setMessage(payload.message || 'Cart updated');
  }

  async function removeItem(productUid) {
    const payload = await api(`/api/v1/cart/items/${encodeURIComponent(productUid)}`, {
      method: 'DELETE',
    });
    renderCart(payload.data.cart);
    setMessage(payload.message || 'Removed from cart');
  }

  async function clearCart() {
    const payload = await api('/api/v1/cart', {
      method: 'DELETE',
    });
    renderCart(payload.data.cart);
    setMessage(payload.message || 'Cart cleared');
  }

  document.addEventListener('click', async (event) => {
    const addButton = event.target.closest('[data-add-to-cart]');
    const removeButton = event.target.closest('[data-remove-cart-item]');
    const clearButton = event.target.closest('[data-clear-cart]');

    try {
      if (addButton) {
        event.preventDefault();
        const quantityInput = addButton.dataset.quantityTarget
          ? document.querySelector(addButton.dataset.quantityTarget)
          : null;
        const quantity = quantityInput ? Number(quantityInput.value) || 1 : Number(addButton.dataset.quantity || 1);
        await addToCart(addButton.dataset.productUid, quantity);
      }

      if (removeButton) {
        event.preventDefault();
        const item = removeButton.closest('[data-product-uid]');
        await removeItem(item.dataset.productUid);
      }

      if (clearButton) {
        event.preventDefault();
        await clearCart();
      }
    } catch (error) {
      setMessage(error.message, true);
    }
  });

  document.addEventListener('change', async (event) => {
    const quantityInput = event.target.closest('[data-cart-quantity]');

    if (!quantityInput) {
      return;
    }

    try {
      const item = quantityInput.closest('[data-product-uid]');
      await updateItem(item.dataset.productUid, Number(quantityInput.value) || 0);
    } catch (error) {
      setMessage(error.message, true);
    }
  });

  if (document.querySelector('[data-cart-page]')) {
    refreshCart().catch((error) => setMessage(error.message, true));
  } else {
    refreshCart().catch(() => {});
  }

  window.SabjiwalahCart = {
    refresh: refreshCart,
    add: addToCart,
    update: updateItem,
    remove: removeItem,
    clear: clearCart,
  };
})();
