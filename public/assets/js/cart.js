(function () {
  const money = new Intl.NumberFormat('en-IN', {
    style: 'currency',
    currency: 'INR',
  });

  let csrf = null;
  let messageTimer = null;
  const savedProductsStorageKey = 'sabjiwalah.savedProducts';
  const deliveryLocationStorageKey = 'sabjiwalah.deliveryLocation';

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

  function setCarouselSlide(card, index) {
    const track = card.querySelector('[data-carousel-track]');
    const dots = Array.from(card.querySelectorAll('[data-carousel-dot]'));

    if (!track || !dots.length) {
      return;
    }

    const nextIndex = Math.max(0, Math.min(index, dots.length - 1));
    card.dataset.carouselIndex = String(nextIndex);
    track.style.transform = `translateX(-${nextIndex * 100}%)`;

    dots.forEach((dot, dotIndex) => {
      const isActive = dotIndex === nextIndex;
      dot.classList.toggle('is-active', isActive);
      dot.setAttribute('aria-selected', isActive ? 'true' : 'false');
    });
  }

  function initProductCarousels() {
    document.querySelectorAll('.product-media').forEach((card) => {
      const photo = card.querySelector('[data-product-carousel]');
      const dots = Array.from(card.querySelectorAll('[data-carousel-dot]'));

      if (!photo || dots.length <= 1 || card.dataset.carouselReady === 'true') {
        return;
      }

      let startX = 0;
      let dragX = 0;
      let pointerId = null;

      card.dataset.carouselReady = 'true';
      card.dataset.carouselIndex = card.dataset.carouselIndex || '0';

      dots.forEach((dot) => {
        dot.addEventListener('click', (event) => {
          event.preventDefault();
          setCarouselSlide(card, Number(dot.dataset.carouselIndex) || 0);
        });
      });

      photo.addEventListener('pointerdown', (event) => {
        if (event.button !== 0) {
          return;
        }

        pointerId = event.pointerId;
        startX = event.clientX;
        dragX = 0;
        photo.classList.add('is-dragging');
        photo.setPointerCapture(pointerId);
      });

      photo.addEventListener('dragstart', (event) => {
        event.preventDefault();
      });

      photo.addEventListener('pointermove', (event) => {
        if (pointerId !== event.pointerId) {
          return;
        }

        dragX = event.clientX - startX;

        if (Math.abs(dragX) > 8) {
          event.preventDefault();
        }
      });

      photo.addEventListener('pointerup', (event) => {
        if (pointerId !== event.pointerId) {
          return;
        }

        const currentIndex = Number(card.dataset.carouselIndex) || 0;
        const threshold = Math.max(28, photo.clientWidth * 0.22);
        let nextIndex = currentIndex;

        photo.classList.remove('is-dragging');
        photo.releasePointerCapture(pointerId);
        pointerId = null;

        if (Math.abs(dragX) > threshold) {
          nextIndex = dragX < 0
            ? Math.min(currentIndex + 1, dots.length - 1)
            : Math.max(currentIndex - 1, 0);
          photo.dataset.suppressClick = 'true';
        }

        setCarouselSlide(card, nextIndex);
      });

      photo.addEventListener('pointercancel', () => {
        pointerId = null;
        dragX = 0;
        photo.classList.remove('is-dragging');
      });

      photo.addEventListener('click', (event) => {
        if (photo.dataset.suppressClick === 'true') {
          event.preventDefault();
          photo.dataset.suppressClick = 'false';
        }
      });
    });
  }

  function initLocationSheet() {
    const sheet = document.querySelector('[data-location-sheet]');
    const openButton = document.querySelector('[data-location-open]');
    const closeButton = document.querySelector('[data-location-close]');
    const searchForm = document.querySelector('[data-location-search-form]');
    const searchInput = document.querySelector('[data-location-search]');
    const currentButton = document.querySelector('[data-use-current-location]');
    const locationLabel = document.querySelector('[data-current-location-label]');
    const locationDetail = document.querySelector('[data-current-location-detail]');
    const currentAddress = document.querySelector('[data-location-current-address]');
    const searchResults = document.querySelector('[data-location-results]');
    let previousFocus = null;
    let searchTimer = null;
    let searchAbortController = null;
    let latestPlaces = [];

    if (!sheet || !openButton || !closeButton) {
      return;
    }

    function getSavedLocation() {
      try {
        return JSON.parse(window.localStorage.getItem(deliveryLocationStorageKey) || 'null');
      } catch (error) {
        return null;
      }
    }

    function saveLocation(location) {
      try {
        window.localStorage.setItem(deliveryLocationStorageKey, JSON.stringify(location));
      } catch (error) {
        // Location selection still works for the current page if storage is unavailable.
      }
    }

    function renderLocation(location) {
      if (!location || !location.label) {
        return;
      }

      const detail = location.summary || location.detail || location.label;

      if (locationLabel) {
        locationLabel.textContent = location.label;
      }

      if (locationDetail) {
        locationDetail.textContent = detail;
      }

      if (currentAddress) {
        currentAddress.textContent = detail;
      }

      if (searchInput && document.activeElement !== searchInput) {
        searchInput.value = '';
      }
    }

    function renderLocationStatus(message) {
      if (currentAddress && message) {
        currentAddress.textContent = message;
      }
    }

    function clearSearchResults() {
      latestPlaces = [];

      if (searchResults) {
        searchResults.replaceChildren();
        searchResults.hidden = true;
      }
    }

    function renderSearchMessage(message) {
      if (!searchResults) {
        return;
      }

      const item = document.createElement('div');
      item.className = 'location-result is-muted';
      item.textContent = message;

      searchResults.replaceChildren(item);
      searchResults.hidden = false;
    }

    function compactPlaceLabel(place, fallback) {
      const address = place && place.address ? place.address : {};
      const primary = address.neighbourhood
        || address.suburb
        || address.quarter
        || address.residential
        || address.road
        || address.village
        || address.town
        || address.city
        || address.county
        || fallback;
      const secondary = address.city
        || address.town
        || address.village
        || address.municipality
        || address.county
        || address.state_district
        || address.state;

      if (secondary && primary && secondary.toLowerCase() !== primary.toLowerCase()) {
        return `${primary}, ${secondary}`;
      }

      return primary || fallback;
    }

    function compactPlaceSummary(place, label) {
      const address = place && place.address ? place.address : {};
      const parts = [
        address.city || address.town || address.village || address.municipality || address.county,
        address.state_district,
        address.state,
        address.postcode,
      ].filter((part) => part && part.toLowerCase() !== String(label).toLowerCase());

      const uniqueParts = [...new Set(parts)];

      return uniqueParts.length ? uniqueParts.join(', ') : (place.display_name || label);
    }

    function locationFromMapPlace(place, fallback) {
      const label = compactPlaceLabel(place, fallback);
      const summary = compactPlaceSummary(place, label);

      return {
        detail: place.display_name || label,
        label,
        latitude: place.lat ? Number(place.lat) : null,
        longitude: place.lon ? Number(place.lon) : null,
        source: 'map',
        summary,
      };
    }

    function renderSearchResults(places) {
      if (!searchResults) {
        return;
      }

      if (!places.length) {
        renderSearchMessage('No matching address found');
        return;
      }

      const fragment = document.createDocumentFragment();

      places.forEach((place) => {
        const location = locationFromMapPlace(place, place.display_name || 'Selected location');
        const item = document.createElement('button');
        const title = document.createElement('strong');
        const detail = document.createElement('em');

        item.type = 'button';
        item.className = 'location-result';
        title.textContent = location.label;
        detail.textContent = location.summary || location.detail;

        item.append(title, detail);
        item.addEventListener('click', () => {
          setLocationFromMap(location);
        });

        fragment.append(item);
      });

      searchResults.replaceChildren(fragment);
      searchResults.hidden = false;
    }

    async function fetchMapPlaces(query, limit = 5, signal = undefined) {
      const response = await fetch(`https://nominatim.openstreetmap.org/search?${new URLSearchParams({
        addressdetails: '1',
        countrycodes: 'in',
        format: 'jsonv2',
        limit: String(limit),
        q: query,
      })}`, {
        headers: { Accept: 'application/json' },
        signal,
      });

      if (!response.ok) {
        throw new Error('Unable to fetch address from map');
      }

      const places = await response.json();

      if (!Array.isArray(places)) {
        throw new Error('No map address found');
      }

      return places;
    }

    async function geocodeAddress(query) {
      const places = await fetchMapPlaces(query, 1);

      if (places.length === 0) {
        throw new Error('No map address found');
      }

      return locationFromMapPlace(places[0], query);
    }

    async function reverseGeocode(latitude, longitude) {
      const response = await fetch(`https://nominatim.openstreetmap.org/reverse?${new URLSearchParams({
        addressdetails: '1',
        format: 'jsonv2',
        lat: String(latitude),
        lon: String(longitude),
        zoom: '18',
      })}`, {
        headers: { Accept: 'application/json' },
      });

      if (!response.ok) {
        throw new Error('Unable to fetch current address from map');
      }

      const place = await response.json();

      if (!place || place.error) {
        throw new Error('No map address found for current location');
      }

      return locationFromMapPlace(place, 'Current location');
    }

    function openSheet() {
      previousFocus = document.activeElement;
      sheet.hidden = false;
      document.body.classList.add('is-location-sheet-open');
      window.requestAnimationFrame(() => {
        sheet.classList.add('is-open');
        if (searchInput) {
          searchInput.focus();
        }
      });
    }

    function closeSheet() {
      sheet.classList.remove('is-open');
      document.body.classList.remove('is-location-sheet-open');
      window.clearTimeout(searchTimer);
      if (searchAbortController) {
        searchAbortController.abort();
        searchAbortController = null;
      }
      window.setTimeout(() => {
        sheet.hidden = true;
        if (previousFocus && typeof previousFocus.focus === 'function') {
          previousFocus.focus();
        }
      }, 220);
    }

    function setLocation(value, detail) {
      const cleanValue = value.trim();

      if (!cleanValue) {
        return;
      }

      const location = {
        detail: detail || `${cleanValue}, India`,
        label: cleanValue,
      };

      saveLocation(location);
      renderLocation(location);
      clearSearchResults();

      closeSheet();
    }

    function setLocationFromMap(location) {
      saveLocation(location);
      renderLocation(location);
      clearSearchResults();
      closeSheet();
    }

    renderLocation(getSavedLocation());

    openButton.addEventListener('click', openSheet);
    closeButton.addEventListener('click', closeSheet);

    sheet.addEventListener('click', (event) => {
      if (event.target === sheet) {
        closeSheet();
      }
    });

    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && !sheet.hidden) {
        closeSheet();
      }
    });

    if (searchForm && searchInput) {
      searchInput.addEventListener('input', () => {
        const query = searchInput.value.trim();

        window.clearTimeout(searchTimer);

        if (searchAbortController) {
          searchAbortController.abort();
          searchAbortController = null;
        }

        if (query.length < 3) {
          clearSearchResults();
          return;
        }

        renderSearchMessage('Searching map...');

        searchTimer = window.setTimeout(async () => {
          searchAbortController = new AbortController();

          try {
            latestPlaces = await fetchMapPlaces(query, 5, searchAbortController.signal);
            renderSearchResults(latestPlaces);
          } catch (error) {
            if (error.name !== 'AbortError') {
              renderSearchMessage('Could not fetch map results');
            }
          } finally {
            searchAbortController = null;
          }
        }, 520);
      });

      searchForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        const query = searchInput.value.trim();

        if (!query) {
          return;
        }

        renderLocationStatus('Fetching address from map...');
        searchInput.disabled = true;

        try {
          if (latestPlaces.length) {
            setLocationFromMap(locationFromMapPlace(latestPlaces[0], query));
          } else {
            setLocationFromMap(await geocodeAddress(query));
          }
        } catch (error) {
          setLocation(query);
        } finally {
          searchInput.disabled = false;
        }
      });
    }

    if (currentButton) {
      currentButton.addEventListener('click', () => {
        if (!navigator.geolocation) {
          setLocation('Surajpur, Greater Noida', 'Surajpur, Greater Noida, Uttar Pradesh, India');
          return;
        }

        renderLocationStatus('Detecting your location...');
        currentButton.disabled = true;
        navigator.geolocation.getCurrentPosition(
          async (position) => {
            const { latitude, longitude } = position.coords;

            try {
              setLocationFromMap(await reverseGeocode(latitude, longitude));
            } catch (error) {
              const shortLatitude = latitude.toFixed(4);
              const shortLongitude = longitude.toFixed(4);

              setLocation('Current location', `Device location: ${shortLatitude}, ${shortLongitude}`);
            } finally {
              currentButton.disabled = false;
            }
          },
          () => {
            currentButton.disabled = false;
            setLocation('Surajpur, Greater Noida', 'Surajpur, Greater Noida, Uttar Pradesh, India');
          },
          { enableHighAccuracy: false, maximumAge: 300000, timeout: 6000 },
        );
      });
    }
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
  initProductCarousels();
  initLocationSheet();

  window.SabjiwalahCart = {
    refresh: refreshCart,
    add: addToCart,
    update: updateItem,
    remove: removeItem,
    clear: clearCart,
  };
})();
