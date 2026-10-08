(() => {
  const key = 'sabjiwalah.deliveryLocation';
  const sheet = document.querySelector('[data-location-sheet]');
  if (!sheet) return;
  const canvas = sheet.querySelector('[data-location-map]');
  const search = sheet.querySelector('[data-location-search]');
  const searchButton = sheet.querySelector('[data-location-search-form] button');
  const results = sheet.querySelector('[data-location-results]');
  const gps = sheet.querySelector('[data-use-current-location]');
  const confirm = sheet.querySelector('[data-location-confirm]');
  const address = sheet.querySelector('[data-location-address]');
  const selected = sheet.querySelector('[data-location-selected]');
  const status = sheet.querySelector('[data-location-status]');
  let map, geocoder, accuracyCircle, point, previousFocus, loader, positioning = false;
  let revision = 0, session = 0, busy = false, ready = false, closeTimer;
  let gpsWatch = null, gpsTimer, bestAccuracy = Infinity, gpsGeneration = 0;
  const valid = value => value && typeof value.latitude === 'number' && typeof value.longitude === 'number'
    && Number.isFinite(value.latitude) && Number.isFinite(value.longitude)
    && Math.abs(value.latitude) <= 90 && Math.abs(value.longitude) <= 180;
  const read = () => {
    try { const value = JSON.parse(localStorage.getItem(key)); return valid(value) ? value : null; }
    catch { return null; }
  };
  const message = (text, error = false) => {
    status.textContent = text;
    status.classList.toggle('is-error', error);
  };
  const renderHeader = location => {
    document.querySelector('[data-current-location-label]').textContent = location?.label || 'Choose delivery location';
    const region = [location?.city, location?.state, location?.postalCode].filter(Boolean).join(', ');
    document.querySelector('[data-current-location-detail]').textContent = region || location?.summary || 'Pin your address on the map';
    document.querySelector('[data-location-open]').title = location ? [location.label, location.summary].filter(Boolean).join(' — ') : 'Choose delivery location';
  };
  const coordinateText = p => p.latitude.toFixed(6) + ', ' + p.longitude.toFixed(6);
  const literal = position => ({
    lat: typeof position.lat === 'function' ? position.lat() : position.lat,
    lng: typeof position.lng === 'function' ? position.lng() : position.lng,
  });
  function stopGps() {
    gpsGeneration++;
    if (gpsWatch !== null) navigator.geolocation.clearWatch(gpsWatch);
    gpsWatch = null;
    clearTimeout(gpsTimer);
    gps.disabled = !ready;
  }
  function setPoint(position, manual = true) {
    const coordinates = literal(position);
    const next = { latitude: coordinates.lat, longitude: coordinates.lng };
    if (!valid(next)) return;
    if (manual) { stopGps(); accuracyCircle?.setMap(null); }
    revision++;
    point = next;
    selected.textContent = 'Selected delivery point';
    selected.title = coordinateText(point);
    confirm.disabled = busy || !ready;
    message('Check the pin, then confirm your location.');
  }
  function positionMap(position, zoom) {
    positioning = true;
    map.setCenter(position);
    if (zoom !== undefined) map.setZoom(zoom);
    positioning = false;
  }
  function loadGoogle() {
    if (loader) return loader;
    const apiKey = canvas.dataset.googleApiKey.trim();
    if (!apiKey || apiKey === 'REPLACE_WITH_GOOGLE_MAPS_API_KEY') return Promise.reject(new Error('Missing map key'));
    loader = new Promise((resolve, reject) => {
      const script = document.createElement('script');
      const timeout = setTimeout(() => reject(new Error('Map loading timed out')), 20000);
      window.sabjiwalahGoogleMapsReady = () => { clearTimeout(timeout); resolve(); };
      window.gm_authFailure = () => {
        clearTimeout(timeout); ready = false; gps.disabled = true; searchButton.disabled = true; confirm.disabled = true;
        message('Delivery map is unavailable. Please try again later.', true);
        reject(new Error('Map authentication failed'));
      };
      const url = new URL('https://maps.googleapis.com/maps/api/js');
      url.search = new URLSearchParams({ key: apiKey, v: 'weekly', loading: 'async', language: 'en', region: 'IN', callback: 'sabjiwalahGoogleMapsReady' });
      script.src = url.href; script.async = true;
      script.referrerPolicy = 'strict-origin-when-cross-origin';
      script.onerror = () => { clearTimeout(timeout); loader = null; script.remove(); reject(new Error('Map loading failed')); };
      document.head.appendChild(script);
    });
    return loader;
  }
  async function initMap(saved, activeSession) {
    ready = false; gps.disabled = true; searchButton.disabled = true; confirm.disabled = true;
    selected.textContent = saved ? (saved.label || coordinateText(saved)) : 'Choose a point on the map';
    selected.title = saved ? coordinateText(saved) : '';
    address.value = saved?.addressLine || '';
    message('Loading delivery map…');
    try {
      await loadGoogle();
      const [{ Map: GoogleMap, Circle }, { Geocoder }] = await Promise.all([
        google.maps.importLibrary('maps'), google.maps.importLibrary('geocoding'),
      ]);
      if (session !== activeSession) return;
      const center = saved ? { lat: saved.latitude, lng: saved.longitude } : { lat: 22.9, lng: 78.96 };
      if (!map) {
        map = new GoogleMap(canvas, { center, zoom: saved ? 18 : 5, mapId: canvas.dataset.googleMapId || 'DEMO_MAP_ID',
          mapTypeId: 'roadmap', zoomControl: true, mapTypeControl: false, streetViewControl: false,
          fullscreenControl: false, scaleControl: true, gestureHandling: 'greedy', clickableIcons: true, tilt: 0,
          cameraControl: false, rotateControl: false });
        map.addListener('center_changed', () => {
          if (!ready || positioning) return;
          const center = literal(map.getCenter());
          if (point && Math.abs(point.latitude - center.lat) < 1e-9 && Math.abs(point.longitude - center.lng) < 1e-9) return;
          setPoint(center);
        });
        map.addListener('click', event => {
          if (!event.latLng || !ready) return;
          event.stop?.();
          positionMap(event.latLng);
          setPoint(event.latLng);
        });
        accuracyCircle = new Circle({ strokeColor: '#198038', strokeOpacity: 0.5, strokeWeight: 1,
          fillColor: '#198038', fillOpacity: 0.12, clickable: false });
        geocoder = new Geocoder();
      }
      point = saved ? { latitude: saved.latitude, longitude: saved.longitude } : null;
      positionMap(center, saved ? 18 : 5);
      accuracyCircle.setMap(null);
      ready = true; gps.disabled = false; searchButton.disabled = false; confirm.disabled = !point;
      message(saved ? 'Move the map to fine-tune your doorstep.' : 'Search an area or move the map under the pin.');
    } catch {
      if (session !== activeSession) return;
      canvas.textContent = 'Delivery map unavailable';
      message('Delivery map is unavailable. Please try again later.', true);
    }
  }
  function open() {
    clearTimeout(closeTimer);
    previousFocus = document.activeElement;
    sheet.hidden = false;
    document.body.classList.add('is-location-sheet-open');
    Array.from(sheet.parentElement.children).forEach(node => { if (node !== sheet && !['SCRIPT','LINK'].includes(node.tagName)) node.inert = true; });
    const activeSession = ++session;
    requestAnimationFrame(() => {
      sheet.classList.add('is-open');
      initMap(read(), activeSession);
      sheet.querySelector('[data-location-close]').focus();
    });
  }
  function close() {
    revision++; session++; stopGps();
    busy = false; ready = false; confirm.disabled = true;
    results.hidden = true;
    sheet.classList.remove('is-open');
    document.body.classList.remove('is-location-sheet-open');
    Array.from(sheet.parentElement.children).forEach(node => { node.inert = false; });
    previousFocus?.focus();
    closeTimer = setTimeout(() => {
      sheet.hidden = true;
      document.dispatchEvent(new CustomEvent('sabjiwalah:location-closed'));
    }, 220);
  }
  document.querySelector('[data-location-open]').addEventListener('click', open);
  document.addEventListener('sabjiwalah:location-open', open);
  sheet.querySelector('[data-location-close]').addEventListener('click', close);
  sheet.addEventListener('click', event => { if (event.target === sheet) close(); });
  sheet.addEventListener('keydown', event => {
    if (event.key === 'Escape') { event.preventDefault(); close(); }
    if (event.key !== 'Tab') return;
    const focusable = Array.from(sheet.querySelectorAll('button,input,a[href],[tabindex="0"]')).filter(el => !el.disabled && el.getClientRects().length);
    const first = focusable[0], last = focusable[focusable.length - 1];
    if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
    else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
  });
  sheet.querySelector('[data-location-search-form]').addEventListener('submit', async event => {
    event.preventDefault();
    if (!ready) return;
    const query = search.value.trim();
    if (query.length < 3) { message('Enter at least 3 characters to search.', true); return; }
    stopGps();
    results.hidden = false; results.textContent = 'Searching…';
    const activeSession = session, activeRevision = ++revision;
    searchButton.disabled = true;
    try {
      const { Place } = await google.maps.importLibrary('places');
      const request = { textQuery: query, fields: ['displayName', 'formattedAddress', 'location'], language: 'en', region: 'in', maxResultCount: 5 };
      if (point) request.locationBias = { center: { lat: point.latitude, lng: point.longitude }, radius: 10000 };
      const { places } = await Place.searchByText(request);
      if (session !== activeSession || revision !== activeRevision) return;
      results.replaceChildren();
      if (!places?.length) { results.textContent = 'No matching places. Include your town or a nearby landmark.'; return; }
      places.forEach(place => {
        if (!place.location) return;
        const button = document.createElement('button');
        button.type = 'button'; button.className = 'location-result';
        button.textContent = [place.displayName, place.formattedAddress].filter(Boolean).join(' · ');
        button.addEventListener('click', () => {
          setPoint(place.location);
          positionMap(place.location, 18);
          results.hidden = true;
          message('Place found. Move the map to your entrance.');
        });
        results.appendChild(button);
      });
      const credit = document.createElement('span'); credit.className = 'location-search-credit'; credit.textContent = 'Google Maps'; results.appendChild(credit);
    } catch {
      if (session === activeSession && revision === activeRevision) results.textContent = 'Place search unavailable. You can still tap the map to choose.';
    } finally { if (session === activeSession) searchButton.disabled = !ready; }
  });
  gps.addEventListener('click', () => {
    if (!ready) return;
    if (!navigator.geolocation || !window.isSecureContext) { message('GPS needs HTTPS and location permission. You can place the pin manually.', true); return; }
    stopGps(); bestAccuracy = Infinity;
    const activeSession = session, activeGps = gpsGeneration;
    gps.disabled = true; message('Finding a precise GPS position…');
    gpsWatch = navigator.geolocation.watchPosition(position => {
      if (session !== activeSession || activeGps !== gpsGeneration) return;
      const { latitude, longitude, accuracy } = position.coords;
      if (!valid({ latitude, longitude }) || !Number.isFinite(accuracy) || accuracy >= bestAccuracy) return;
      bestAccuracy = accuracy;
      const center = { lat: latitude, lng: longitude };
      setPoint(center, false);
      positionMap(center, accuracy > 1000 ? 14 : accuracy > 100 ? 16 : 18);
      accuracyCircle.setOptions({ map, center, radius: accuracy });
      message(accuracy > 100 ? `GPS is approximate (±${Math.round(accuracy)} m). Move the map to your doorstep.` : `GPS accuracy: ±${Math.round(accuracy)} m. Check your doorstep.`);
      if (accuracy <= 20) stopGps();
    }, error => {
      if (session !== activeSession || activeGps !== gpsGeneration) return;
      stopGps();
      if (bestAccuracy !== Infinity) return;
      message(error.code === 1 ? 'Location permission denied. Search or tap the map to choose.' : 'GPS unavailable. Search or tap the map to choose.', true);
    }, { enableHighAccuracy: true, maximumAge: 0, timeout: 15000 });
    gpsTimer = setTimeout(() => { stopGps(); if (bestAccuracy === Infinity && session === activeSession) message('GPS could not find your location. Search or tap the map to choose.', true); }, 20000);
  });
  confirm.addEventListener('click', async () => {
    if (!valid(point) || busy || !ready) return;
    stopGps();
    const chosen = { ...point }, activeRevision = revision, activeSession = session;
    busy = true; confirm.disabled = true; message('Looking up the address for your pin…');
    let place;
    try {
      const response = await geocoder.geocode({ location: { lat: chosen.latitude, lng: chosen.longitude } });
      place = response.results?.[0];
    } catch { /* Preserve the selected coordinates when address lookup fails. */ }
    finally { if (session === activeSession) { busy = false; confirm.disabled = !valid(point) || !ready; } }
    if (session !== activeSession || revision !== activeRevision) return;
    const part = type => place?.address_components?.find(component => component.types.includes(type))?.long_name || '';
    const label = part('route') || part('sublocality_level_1') || part('locality') || 'Pinned location';
    const addressLine = address.value.trim();
    const location = { ...chosen, version: 2, source: 'pin', provider: 'google', label,
      summary: addressLine || place?.formatted_address || coordinateText(chosen),
      detail: place?.formatted_address || coordinateText(chosen), addressLine,
      city: part('locality') || part('administrative_area_level_3') || part('administrative_area_level_2'),
      state: part('administrative_area_level_1'), postalCode: part('postal_code') };
    try { localStorage.setItem(key, JSON.stringify(location)); }
    catch { message('Could not save your location. Enable browser storage and try again.', true); return; }
    renderHeader(location); close();
  });
  window.addEventListener('storage', event => { if (event.key === key) renderHeader(read()); });
  renderHeader(read());
})();
