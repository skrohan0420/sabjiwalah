(function () {
  const money = new Intl.NumberFormat('en-IN', {
    style: 'currency',
    currency: 'INR',
  });

  let csrf = null;
  let messageTimer = null;
  const savedProductsStorageKey = 'sabjiwalah.savedProducts';

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
      node.textContent = shortMessage(message || '');
      node.classList.toggle('is-visible', Boolean(message));
      node.classList.toggle('is-error', Boolean(message && isError));
      node.style.color = '';
    });

    if (message) {
      messageTimer = window.setTimeout(() => setMessage(''), 1800);
    }
  }

  function shortMessage(message) {
    const normalized = message.toLowerCase();

    if (normalized.includes('added')) {
      return 'Added';
    }

    if (normalized.includes('removed')) {
      return 'Removed';
    }

    if (normalized.includes('updated')) {
      return 'Updated';
    }

    return message;
  }

  function updateCount(count) {
    document.querySelectorAll('[data-cart-count], [data-cart-count-badge]').forEach((node) => {
      node.textContent = String(count);
    });
  }

  function getSavedProducts() {
    try {
      return new Set(JSON.parse(window.localStorage.getItem(savedProductsStorageKey) || '[]'));
    } catch (error) {
      return new Set();
    }
  }

  function setSavedProducts(savedProducts) {
    try {
      window.localStorage.setItem(savedProductsStorageKey, JSON.stringify(Array.from(savedProducts)));
    } catch (error) {
      // Favorite state is cosmetic until a server-backed wishlist exists.
    }
  }

  function syncSaveButtons(productUid, isSaved) {
    document.querySelectorAll(`[data-save-product="${CSS.escape(productUid)}"]`).forEach((button) => {
      if (!button.dataset.saveLabel) {
        button.dataset.saveLabel = button.getAttribute('aria-label') || 'Save product';
      }

      button.classList.toggle('is-saved', isSaved);
      button.setAttribute('aria-pressed', isSaved ? 'true' : 'false');
      button.setAttribute('aria-label', isSaved ? button.dataset.saveLabel.replace(/^Save /, 'Saved ') : button.dataset.saveLabel);
    });
  }

  function initSaveButtons() {
    const savedProducts = getSavedProducts();

    document.querySelectorAll('[data-save-product]').forEach((button) => {
      syncSaveButtons(button.dataset.saveProduct, savedProducts.has(button.dataset.saveProduct));
    });
  }

  function updateProductControls(cart) {
    const quantities = new Map(
      cart.items.map((item) => [item.product.uid, Number(item.quantity) || 0]),
    );

    document.querySelectorAll('[data-cart-control]').forEach((control) => {
      const previousQuantity = Number(control.dataset.currentQuantity) || 0;
      const isReady = control.dataset.cartControlReady === 'true';
      const quantity = quantities.get(control.dataset.productUid) || 0;
      const value = control.querySelector('[data-cart-quantity-value]');

      control.dataset.currentQuantity = String(quantity);
      control.dataset.cartControlReady = 'true';
      control.classList.toggle('is-in-cart', quantity > 0);

      if (value) {
        value.textContent = String(Math.max(1, quantity));

        if (isReady && quantity > 0 && quantity !== previousQuantity) {
          value.classList.remove('is-quantity-changing');
          value.dataset.quantityDirection = quantity > previousQuantity ? 'up' : 'down';
          void value.offsetWidth;
          value.classList.add('is-quantity-changing');
        }
      }
    });
  }

  function updateFloatingCart(cart) {
    const bar = document.querySelector('[data-floating-cart]');

    if (!bar) {
      return;
    }

    const count = Number(cart.count) || 0;
    const productImages = new Map(
      Array.from(document.querySelectorAll('[data-cart-control]')).map((control) => [
        control.dataset.productUid,
        control.dataset.productImage,
      ]),
    );
    const countLabel = bar.querySelector('[data-floating-cart-count]');
    const thumbs = bar.querySelector('[data-floating-cart-thumbs]');

    bar.hidden = false;
    bar.classList.toggle('is-visible', count > 0);
    bar.setAttribute('aria-label', count > 0 ? `View cart, ${count} ${count === 1 ? 'item' : 'items'}` : 'View cart');

    if (countLabel) {
      countLabel.textContent = `${count} ${count === 1 ? 'item' : 'items'}`;
    }

    if (thumbs) {
      thumbs.textContent = '';
      cart.items.slice(0, 2).forEach((item) => {
        const imageUrl = item.product.image || productImages.get(item.product.uid);

        if (!imageUrl) {
          return;
        }

        const image = document.createElement('img');
        image.src = imageUrl;
        image.alt = '';
        image.loading = 'lazy';
        thumbs.appendChild(image);
      });
    }
  }

  function renderCart(cart) {
    updateCount(cart.count);
    updateProductControls(cart);
    updateFloatingCart(cart);

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
    return payload.data.cart;
  }

  async function updateItem(productUid, quantity) {
    const payload = await api(`/api/v1/cart/items/${encodeURIComponent(productUid)}`, {
      method: 'PATCH',
      body: JSON.stringify({ quantity }),
    });
    renderCart(payload.data.cart);
    setMessage(payload.message || 'Cart updated');
    return payload.data.cart;
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
    const saveButton = event.target.closest('[data-save-product]');
    const addButton = event.target.closest('[data-add-to-cart]');
    const incrementButton = event.target.closest('[data-cart-increment]');
    const decrementButton = event.target.closest('[data-cart-decrement]');
    const removeButton = event.target.closest('[data-remove-cart-item]');
    const clearButton = event.target.closest('[data-clear-cart]');

    try {
      if (saveButton) {
        event.preventDefault();
        const savedProducts = getSavedProducts();
        const productUid = saveButton.dataset.saveProduct;
        const isSaved = !savedProducts.has(productUid);

        if (isSaved) {
          savedProducts.add(productUid);
        } else {
          savedProducts.delete(productUid);
        }

        setSavedProducts(savedProducts);
        syncSaveButtons(productUid, isSaved);
      }

      if (addButton) {
        event.preventDefault();
        addButton.disabled = true;
        const quantityInput = addButton.dataset.quantityTarget
          ? document.querySelector(addButton.dataset.quantityTarget)
          : null;
        const quantity = quantityInput ? Number(quantityInput.value) || 1 : Number(addButton.dataset.quantity || 1);
        await addToCart(addButton.dataset.productUid, quantity);
        addButton.disabled = false;
      }

      if (incrementButton || decrementButton) {
        event.preventDefault();
        const control = event.target.closest('[data-cart-control]');
        const currentQuantity = Number(control.dataset.currentQuantity) || 0;
        const nextQuantity = incrementButton
          ? currentQuantity + 1
          : Math.max(0, currentQuantity - 1);

        control.querySelectorAll('button').forEach((button) => {
          button.disabled = true;
        });

        await updateItem(control.dataset.productUid, nextQuantity);

        control.querySelectorAll('button').forEach((button) => {
          button.disabled = false;
        });
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
      document.querySelectorAll('[data-cart-control] button, [data-add-to-cart]').forEach((button) => {
        button.disabled = false;
      });
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

  initSaveButtons();

  window.SabjiwalahCart = {
    refresh: refreshCart,
    add: addToCart,
    update: updateItem,
    remove: removeItem,
    clear: clearCart,
  };
})();
