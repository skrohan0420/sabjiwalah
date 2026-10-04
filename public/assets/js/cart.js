(function () {
  const money = new Intl.NumberFormat('en-IN', {
    style: 'currency',
    currency: 'INR',
  });

  let csrf = null;
  let messageTimer = null;
  let currentCart = { count: 0, items: [], subtotal: 0 };
  let cartMutationVersion = 0;
  const cartUpdateDebounceMs = 400;
  const pendingItemUpdates = new Map();
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

  function cloneCart(cart) {
    return JSON.parse(JSON.stringify(cart || { count: 0, items: [], subtotal: 0 }));
  }

  function normalizeCart(cart) {
    const normalized = cloneCart(cart);

    normalized.items = (normalized.items || [])
      .map((item) => {
        const quantity = Math.max(0, Number(item.quantity) || 0);
        const unitPrice = Number(item.unit_price ?? item.product?.sale_price ?? item.product?.price ?? 0) || 0;

        return {
          ...item,
          quantity,
          unit_price: unitPrice,
          total: unitPrice * quantity,
          product: {
            uid: item.product?.uid || item.product_uid || '',
            name: item.product?.name || 'Product',
            unit: item.product?.unit || '',
            image: item.product?.image || '',
            ...(item.product || {}),
          },
        };
      })
      .filter((item) => item.quantity > 0);

    normalized.count = normalized.items.reduce((total, item) => total + item.quantity, 0);
    normalized.subtotal = normalized.items.reduce((total, item) => total + item.total, 0);

    return normalized;
  }

  function readProductMeta(productUid, source = null) {
    const existing = currentCart.items.find((item) => item.product.uid === productUid);
    const sourceControl = source?.closest?.('[data-cart-control]') || null;
    const sourceButton = source?.closest?.('[data-add-to-cart]') || null;
    const control = sourceControl || document.querySelector(`[data-cart-control][data-product-uid="${CSS.escape(productUid)}"]`);
    const metaNode = control || sourceButton;
    const unitPrice = Number(metaNode?.dataset.productPrice ?? existing?.unit_price ?? 0) || 0;

    return {
      product: {
        uid: productUid,
        name: metaNode?.dataset.productName || existing?.product?.name || 'Product',
        unit: metaNode?.dataset.productUnit || existing?.product?.unit || '',
        image: metaNode?.dataset.productImage || existing?.product?.image || '',
      },
      unit_price: unitPrice,
    };
  }

  function cartWithQuantity(productUid, quantity, source = null, mode = 'set') {
    const nextCart = cloneCart(currentCart);
    const itemIndex = nextCart.items.findIndex((item) => item.product.uid === productUid);
    const existing = itemIndex >= 0 ? nextCart.items[itemIndex] : null;
    const nextQuantity = mode === 'add'
      ? (Number(existing?.quantity) || 0) + Math.max(1, Number(quantity) || 1)
      : Math.max(0, Number(quantity) || 0);

    if (nextQuantity <= 0) {
      nextCart.items = nextCart.items.filter((item) => item.product.uid !== productUid);
      return normalizeCart(nextCart);
    }

    const meta = readProductMeta(productUid, source);
    const item = {
      ...(existing || {}),
      product: {
        ...(existing?.product || {}),
        ...meta.product,
      },
      quantity: nextQuantity,
      unit_price: Number(existing?.unit_price ?? meta.unit_price) || 0,
    };

    if (itemIndex >= 0) {
      nextCart.items[itemIndex] = item;
    } else {
      nextCart.items.push(item);
    }

    return normalizeCart(nextCart);
  }

  function cartWithoutItem(productUid) {
    return normalizeCart({
      ...currentCart,
      items: currentCart.items.filter((item) => item.product.uid !== productUid),
    });
  }

  function renderOptimisticCart(nextCart, message) {
    const previousCart = cloneCart(currentCart);
    renderCart(nextCart);
    setMessage(message);
    return previousCart;
  }

  async function commitCartRequest(request, previousCart, version, fallbackMessage) {
    try {
      const payload = await request;

      if (version === cartMutationVersion) {
        renderCart(payload.data.cart);
        setMessage(payload.message || fallbackMessage);
      }

      return payload.data.cart;
    } catch (error) {
      if (version === cartMutationVersion) {
        renderCart(previousCart);
      } else {
        refreshCart().catch(() => {});
      }

      setMessage(error.message, true);
      throw error;
    }
  }

  function cancelPendingItemUpdate(productUid, result = currentCart) {
    const pendingUpdate = pendingItemUpdates.get(productUid);

    if (!pendingUpdate) {
      return;
    }

    clearTimeout(pendingUpdate.timer);
    pendingItemUpdates.delete(productUid);
    pendingUpdate.resolve(cloneCart(result));
  }

  function cancelAllPendingItemUpdates(result = currentCart) {
    Array.from(pendingItemUpdates.keys()).forEach((productUid) => {
      cancelPendingItemUpdate(productUid, result);
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

  function initScrollChrome() {
    const bottomNav = document.querySelector('.bottom-nav');

    if (!bottomNav) {
      return;
    }

    let lastScrollY = window.scrollY;
    let ticking = false;

    function setBottomNavHidden(hidden) {
      document.body.classList.toggle('is-bottom-nav-hidden', hidden);
    }

    function update() {
      const currentScrollY = window.scrollY;
      const deltaY = currentScrollY - lastScrollY;
      const sheetOpen = document.body.classList.contains('is-location-sheet-open');

      if (sheetOpen || currentScrollY < 20) {
        setBottomNavHidden(false);
      } else if (deltaY > 4) {
        setBottomNavHidden(true);
      } else if (deltaY < -4) {
        setBottomNavHidden(false);
      }

      lastScrollY = Math.max(currentScrollY, 0);
      ticking = false;
    }

    function requestUpdate() {
      if (!ticking) {
        window.requestAnimationFrame(update);
        ticking = true;
      }
    }

    update();
    window.addEventListener('resize', requestUpdate);
    window.addEventListener('scroll', requestUpdate, { passive: true });
  }

  function initSearchPlaceholderRotation() {
    const input = document.querySelector('[data-search-placeholder]');
    const visual = document.querySelector('[data-search-placeholder-anim]');

    if (!input || !visual) {
      return;
    }

    const placeholders = (input.dataset.searchPlaceholders || '')
      .split('|')
      .map((placeholder) => placeholder.trim())
      .filter(Boolean);

    if (placeholders.length < 2) {
      return;
    }

    const searchBox = input.closest('.search-box');
    const animations = ['is-slide-up', 'is-type-in', 'is-fade-in', 'is-slide-down', 'is-zoom-in', 'is-wipe-in'];
    let previousIndex = -1;
    let previousAnimationIndex = -1;

    function syncVisibility() {
      const shouldHide = input.value.trim() !== '' || document.activeElement === input;
      searchBox?.classList.toggle('has-search-value', input.value.trim() !== '');
      searchBox?.classList.toggle('has-search-focus', document.activeElement === input);
      visual.hidden = shouldHide;
    }

    function showNextPlaceholder() {
      if (document.activeElement === input || input.value.trim() !== '') {
        syncVisibility();
        return;
      }

      let index = Math.floor(Math.random() * placeholders.length);
      let animationIndex = Math.floor(Math.random() * animations.length);

      if (placeholders.length > 1) {
        while (index === previousIndex) {
          index = Math.floor(Math.random() * placeholders.length);
        }
      }

      if (animations.length > 1) {
        while (animationIndex === previousAnimationIndex) {
          animationIndex = Math.floor(Math.random() * animations.length);
        }
      }

      previousIndex = index;
      previousAnimationIndex = animationIndex;

      visual.classList.remove(...animations);
      visual.textContent = placeholders[index];
      input.placeholder = placeholders[index];
      void visual.offsetWidth;
      visual.classList.add(animations[animationIndex]);
    }

    syncVisibility();
    showNextPlaceholder();

    input.addEventListener('focus', syncVisibility);
    input.addEventListener('blur', syncVisibility);
    input.addEventListener('input', syncVisibility);

    window.setInterval(showNextPlaceholder, 5200);
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
    bar.setAttribute('aria-label', count > 0 ? `Checkout, ${count} ${count === 1 ? 'item' : 'items'}` : 'Checkout');

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

  function compactMoney(amount) {
    return `Rs ${Number(amount || 0).toLocaleString('en-IN', {
      maximumFractionDigits: 2,
      minimumFractionDigits: 0,
    })}`;
  }

  function checkoutTotals(cart) {
    const page = document.querySelector('[data-checkout-review-page]');
    const deliveryCharge = Number(page?.dataset.deliveryCharge ?? 40) || 0;
    const freeDeliveryMinimum = Number(page?.dataset.freeDeliveryMinimum ?? 499) || 0;
    const subtotal = Number(cart.subtotal) || 0;
    const delivery = subtotal > 0 && subtotal < freeDeliveryMinimum ? deliveryCharge : 0;

    return {
      subtotal,
      delivery,
      total: subtotal + delivery,
    };
  }

  function checkoutItemMarkup(item) {
    const product = item.product || {};
    const price = Number(product.price ?? item.unit_price ?? 0) || 0;
    const unitPrice = Number(item.unit_price) || 0;
    const quantity = Number(item.quantity) || 0;
    const total = Number(item.total) || unitPrice * quantity;
    const image = product.image || '/assets/images/sabjiwalah-cart-icon.png';
    const hasSavings = price > unitPrice;

    return `
      <article
        class="checkout-review-item"
        data-cart-control
        data-checkout-review-item
        data-product-uid="${escapeHtml(product.uid || '')}"
        data-product-name="${escapeHtml(product.name || 'Product')}"
        data-product-unit="${escapeHtml(product.unit || '')}"
        data-product-image="${escapeHtml(image)}"
        data-product-price="${unitPrice}"
        data-current-quantity="${quantity}"
      >
        <img src="${escapeHtml(image)}" alt="">
        <div>
          <h3>${escapeHtml(product.name || 'Product')}</h3>
          <p>${escapeHtml(product.unit || '')}</p>
        </div>
        <div class="checkout-quantity-pill" aria-label="${quantity} in cart">
          <button type="button" data-cart-decrement aria-label="Decrease quantity">-</button>
          <strong data-cart-quantity-value>${quantity}</strong>
          <button type="button" data-cart-increment aria-label="Increase quantity">+</button>
        </div>
        <p class="checkout-line-price">
          ${hasSavings ? `<del>${compactMoney(price * quantity)}</del>` : ''}
          <strong>${compactMoney(total)}</strong>
        </p>
      </article>
    `;
  }

  function updateCheckoutReview(cart) {
    const page = document.querySelector('[data-checkout-review-page]');

    if (!page) {
      return;
    }

    const items = page.querySelector('[data-checkout-review-items]');
    const count = Number(cart.count) || 0;
    const totals = checkoutTotals(cart);
    const previousQuantities = new Map(
      Array.from(page.querySelectorAll('[data-checkout-review-item]')).map((item) => [
        item.dataset.productUid,
        Number(item.dataset.currentQuantity) || 0,
      ]),
    );

    setText('[data-checkout-review-count]', String(count));
    setText('[data-checkout-review-count-label]', count === 1 ? 'item' : 'items');
    setText('[data-checkout-subtotal]', compactMoney(totals.subtotal));
    setText('[data-checkout-delivery]', compactMoney(totals.delivery));
    setText('[data-checkout-total]', compactMoney(totals.total));

    if (!items) {
      return;
    }

    items.innerHTML = cart.items.length
      ? cart.items.map(checkoutItemMarkup).join('')
      : '<p class="checkout-empty">Your cart is empty. Add fresh picks before checkout.</p>';

    cart.items.forEach((item) => {
      const productUid = item.product?.uid || '';
      const previousQuantity = previousQuantities.get(productUid);
      const quantity = Number(item.quantity) || 0;
      const value = items.querySelector(`[data-checkout-review-item][data-product-uid="${CSS.escape(productUid)}"] [data-cart-quantity-value]`);

      if (previousQuantity !== undefined && quantity > 0 && quantity !== previousQuantity && value) {
        value.classList.remove('is-quantity-changing');
        value.dataset.quantityDirection = quantity > previousQuantity ? 'up' : 'down';
        void value.offsetWidth;
        value.classList.add('is-quantity-changing');
      }
    });
  }

  function renderCart(cart) {
    cart = normalizeCart(cart);
    currentCart = cloneCart(cart);

    updateCount(cart.count);
    updateCheckoutReview(cart);
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
    const version = cartMutationVersion;
    const payload = await api('/api/v1/cart');

    if (version === cartMutationVersion) {
      renderCart(payload.data.cart);
    }

    return payload.data.cart;
  }

  async function addToCart(productUid, quantity, source = null) {
    cancelPendingItemUpdate(productUid);

    const version = ++cartMutationVersion;
    const previousCart = renderOptimisticCart(
      cartWithQuantity(productUid, quantity, source, 'add'),
      'Added',
    );
    const request = api('/api/v1/cart/items', {
      method: 'POST',
      body: JSON.stringify({ product_uid: productUid, quantity }),
    });

    return commitCartRequest(request, previousCart, version, 'Added to cart');
  }

  async function updateItem(productUid, quantity, source = null) {
    const pendingUpdate = pendingItemUpdates.get(productUid);
    const previousCart = pendingUpdate?.previousCart || cloneCart(currentCart);

    if (pendingUpdate) {
      clearTimeout(pendingUpdate.timer);
      pendingUpdate.resolve(cloneCart(currentCart));
    }

    const version = ++cartMutationVersion;
    renderOptimisticCart(
      cartWithQuantity(productUid, quantity, source, 'set'),
      quantity > 0 ? 'Updated' : 'Removed',
    );

    return new Promise((resolve, reject) => {
      const timer = setTimeout(() => {
        pendingItemUpdates.delete(productUid);

        const request = api(`/api/v1/cart/items/${encodeURIComponent(productUid)}`, {
          method: 'PATCH',
          body: JSON.stringify({ quantity }),
        });

        commitCartRequest(request, previousCart, version, quantity > 0 ? 'Cart updated' : 'Product removed from cart')
          .then(resolve)
          .catch(reject);
      }, cartUpdateDebounceMs);

      pendingItemUpdates.set(productUid, {
        previousCart,
        timer,
        resolve,
      });
    });
  }

  async function removeItem(productUid) {
    cancelPendingItemUpdate(productUid);

    const version = ++cartMutationVersion;
    const previousCart = renderOptimisticCart(cartWithoutItem(productUid), 'Removed');
    const request = api(`/api/v1/cart/items/${encodeURIComponent(productUid)}`, {
      method: 'DELETE',
    });

    return commitCartRequest(request, previousCart, version, 'Removed from cart');
  }

  async function clearCart() {
    cancelAllPendingItemUpdates({ count: 0, items: [], subtotal: 0 });

    const version = ++cartMutationVersion;
    const previousCart = renderOptimisticCart({ count: 0, items: [], subtotal: 0 }, 'Cart cleared');
    const request = api('/api/v1/cart', {
      method: 'DELETE',
    });

    return commitCartRequest(request, previousCart, version, 'Cart cleared');
  }

  function setText(selector, value) {
    document.querySelectorAll(selector).forEach((node) => {
      node.textContent = value;
    });
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
        const quantityInput = addButton.dataset.quantityTarget
          ? document.querySelector(addButton.dataset.quantityTarget)
          : null;
        const quantity = quantityInput ? Number(quantityInput.value) || 1 : Number(addButton.dataset.quantity || 1);
        await addToCart(addButton.dataset.productUid, quantity, addButton);
      }

      if (incrementButton || decrementButton) {
        event.preventDefault();
        const control = event.target.closest('[data-cart-control]');
        const currentQuantity = Number(control.dataset.currentQuantity) || 0;
        const nextQuantity = incrementButton
          ? currentQuantity + 1
          : Math.max(0, currentQuantity - 1);

        await updateItem(control.dataset.productUid, nextQuantity, control);
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
      await updateItem(item.dataset.productUid, Number(quantityInput.value) || 0, item);
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
  initScrollChrome();
  initSearchPlaceholderRotation();

  window.SabjiwalahCart = {
    refresh: refreshCart,
    add: addToCart,
    update: updateItem,
    remove: removeItem,
    clear: clearCart,
  };
})();
