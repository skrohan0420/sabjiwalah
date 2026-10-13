(() => {
  'use strict';
  const byId = id => document.getElementById(id), panel = byId('delivery-map-panel'), canvas = byId('delivery-map');
  const status = byId('delivery-map-status'), gps = byId('delivery-map-gps'), routeButton = byId('delivery-map-route'), clearButton = byId('delivery-map-clear');
  const locationStatus = byId('delivery-map-location'), routeStatus = byId('delivery-map-route-status');
  let alive = true, generation = 0, gpsGeneration = 0, destination, active = false, ready = false, loader, mapLoading = false, authFailed = false;
  let map, destinationMarker, riderMarker, accuracyCircle, origin, polylines = [], cache;
  let gpsBusy = false, routeBusy = false, denied = false, lastRouteAttempt = -Infinity;
  function coordinate(lat, lng) {
    if (typeof lat !== 'number' || typeof lng !== 'number' || !Number.isFinite(lat) || !Number.isFinite(lng) || Math.abs(lat) > 90 || Math.abs(lng) > 180) return null;
    return { lat, lng };
  }
  function controls() {
    gps.disabled = !ready || !active || gpsBusy || denied || document.hidden || navigator.onLine === false;
    routeButton.disabled = !ready || !active || !origin || routeBusy || gpsBusy || document.hidden || navigator.onLine === false;
    clearButton.disabled = !origin && !gpsBusy;
  }
  function removeRoute() { polylines.forEach(line => line.setMap(null)); polylines = []; }
  function clearLocation() {
    gpsGeneration++; origin = undefined; cache = undefined; removeRoute();
    if (riderMarker) riderMarker.map = null;
    accuracyCircle?.setMap(null);
    locationStatus.textContent = 'Your location is requested only when you choose Show my location.';
    routeStatus.textContent = 'Show driving route sends the two locations to Google Maps.';
    controls();
  }
  function clear() {
    generation++; clearLocation(); destination = undefined; ready = false; active = false; mapLoading = false;
    if (destinationMarker) destinationMarker.map = null;
    if (map) window.google?.maps?.event?.clearInstanceListeners(map);
    destinationMarker = riderMarker = accuracyCircle = map = undefined;
    canvas.replaceChildren(); canvas.hidden = true; panel.hidden = true;
    byId('delivery-map-address').textContent = ''; controls();
  }
  function failure() {
    ready = false; canvas.hidden = true; clearLocation();
    status.textContent = 'The map is unavailable. Use the saved address or open directions in your browser.'; controls();
  }
  function loadGoogle() {
    if (window.google?.maps?.importLibrary) return Promise.resolve();
    if (loader) return loader;
    const key = canvas.dataset.googleApiKey?.trim();
    if (!key || key.includes('REPLACE')) return Promise.reject(new Error('Map not configured'));
    loader = new Promise((resolve, reject) => {
      const script = document.createElement('script'); let settled = false;
      const finish = error => {
        if (settled) return; settled = true; clearTimeout(timer);
        if (error) { loader = undefined; script.remove(); reject(error); } else resolve();
      };
      const timer = setTimeout(() => finish(new Error('Map loading timed out')), 20000);
      window.sabjiwalahDeliveryMapsReady = () => finish();
      window.gm_authFailure = () => { authFailed = true; finish(new Error('Map unavailable')); if (alive && !panel.hidden) failure(); };
      const url = new URL('https://maps.googleapis.com/maps/api/js');
      url.search = new URLSearchParams({ key, v: 'weekly', loading: 'async', language: 'en', region: 'IN', callback: 'sabjiwalahDeliveryMapsReady' });
      script.src = url.href; script.async = true; script.referrerPolicy = 'strict-origin-when-cross-origin';
      script.onerror = () => finish(new Error('Map loading failed')); document.head.append(script);
    });
    return loader;
  }
  async function init(version) {
    if (mapLoading) return;
    mapLoading = true;
    status.textContent = 'Loading customer map…';
    try {
      await loadGoogle();
      let libraryTimer;
      const libraries = Promise.all([window.google.maps.importLibrary('maps'), window.google.maps.importLibrary('marker')]);
      const [{ Map, Circle }, { AdvancedMarkerElement }] = await Promise.race([libraries, new Promise((_, reject) => { libraryTimer = setTimeout(() => reject(new Error('Map libraries timed out')), 20000); })]).finally(() => clearTimeout(libraryTimer));
      if (!alive || version !== generation) return;
      if (authFailed) throw new Error('Map unavailable');
      canvas.hidden = false;
      map = new Map(canvas, { center: destination, zoom: 17, mapId: canvas.dataset.googleMapId || 'DEMO_MAP_ID', mapTypeControl: false, streetViewControl: false, fullscreenControl: false, gestureHandling: 'cooperative' });
      destinationMarker = new AdvancedMarkerElement({ map, position: destination, title: 'Customer destination' });
      const riderPin = document.createElement('span'); riderPin.className = 'delivery-map-rider-marker'; riderPin.textContent = 'You';
      riderMarker = new AdvancedMarkerElement({ title: 'Your current location', content: riderPin });
      accuracyCircle = new Circle({ strokeColor: '#2563eb', strokeOpacity: 0.6, fillColor: '#2563eb', fillOpacity: 0.12, clickable: false });
      ready = true; status.textContent = 'Customer destination is the marked saved delivery point.'; controls();
    } catch { if (alive && version === generation) failure(); }
    finally { if (version === generation) mapLoading = false; }
  }
  function setOrder(order) {
    if (!alive) return;
    const next = coordinate(order.delivery_latitude, order.delivery_longitude);
    const changed = !destination || !next || destination.lat !== next.lat || destination.lng !== next.lng;
    if (changed) clear();
    panel.hidden = false; active = ['ready_for_delivery', 'out_for_delivery'].includes(order.order_status);
    if (!active) clearLocation();
    const address = [order.address_line, order.city, order.state, order.postal_code].filter(Boolean).join(', ');
    byId('delivery-map-address').textContent = address || 'Address not recorded. Contact the customer.';
    for (const [id, navigate] of [['delivery-map-navigate', true], ['delivery-map-browser', false]]) {
      const link = byId(id); link.hidden = !active || (!next && !address);
      const target = next ? next.lat + ',' + next.lng : address;
      link.href = 'https://www.google.com/maps/dir/?api=1&travelmode=two-wheeler&' + (navigate ? 'dir_action=navigate&' : '') + 'destination=' + encodeURIComponent(target);
      if (navigate) link.textContent = next ? 'Navigate to Customer' : 'Navigate using address';
    }
    if (!next) { status.textContent = 'Saved coordinates are missing or invalid. Confirm the address with the customer; address directions may be approximate.'; controls(); return; }
    destination = next;
    if (!ready && (changed || !loader)) init(generation);
    controls();
  }
  gps.addEventListener('click', () => {
    if (gps.disabled || window.SabjiwalahDelivery.isBusy()) return;
    if (!window.isSecureContext || !navigator.geolocation) { locationStatus.textContent = 'Location needs HTTPS and a browser with GPS support. Use external navigation.'; return; }
    gpsBusy = true; const version = ++gpsGeneration; controls();
    locationStatus.textContent = 'Requesting your location…';
    navigator.geolocation.getCurrentPosition(position => {
      gpsBusy = false;
      if (!alive || version !== gpsGeneration || !active || document.hidden) { controls(); return; }
      const point = coordinate(position.coords.latitude, position.coords.longitude), accuracy = position.coords.accuracy;
      if (!point || !Number.isFinite(accuracy) || accuracy < 0) { locationStatus.textContent = 'GPS returned an invalid location. Try again or use external navigation.'; controls(); return; }
      removeRoute(); origin = point; riderMarker.position = point; riderMarker.map = map;
      accuracyCircle.setOptions({ center: point, radius: accuracy }); accuracyCircle.setMap(map);
      const bounds = new window.google.maps.LatLngBounds(); bounds.extend(point); bounds.extend(destination); map.fitBounds(bounds);
      locationStatus.textContent = 'Your location accuracy: ±' + Math.round(accuracy) + ' metres. This is a single reading.';
      routeStatus.textContent = 'Choose Show driving route to request directions from this location.'; controls();
    }, error => {
      gpsBusy = false;
      if (!alive || version !== gpsGeneration) { controls(); return; }
      if (error.code === 1) denied = true;
      locationStatus.textContent = error.code === 1 ? 'Location permission denied. Change browser permissions and reload, or use external navigation.' : error.code === 3 ? 'Location request timed out. Try again or use external navigation.' : 'GPS is unavailable. Try again or use external navigation.';
      controls();
    }, { enableHighAccuracy: true, timeout: 15000, maximumAge: 30000 });
  });
  function nearby(a, b) {
    const radians = Math.PI / 180, dLat = (a.lat - b.lat) * radians, dLng = (a.lng - b.lng) * radians;
    const value = Math.sin(dLat / 2) ** 2 + Math.cos(a.lat * radians) * Math.cos(b.lat * radians) * Math.sin(dLng / 2) ** 2;
    return 6371000 * 2 * Math.atan2(Math.sqrt(value), Math.sqrt(Math.max(0, 1 - value))) < 100;
  }
  function showRoute(route, cached) {
    removeRoute(); polylines = route.createPolylines(); polylines.forEach(line => line.setMap(map));
    const bounds = new window.google.maps.LatLngBounds(); route.path.forEach(point => bounds.extend(point)); map.fitBounds(bounds);
    const summary = [];
    if (Number.isFinite(route.distanceMeters) && route.distanceMeters >= 0) summary.push((route.distanceMeters / 1000).toFixed(1) + ' km');
    if (Number.isFinite(route.durationMillis) && route.durationMillis >= 0) summary.push(Math.ceil(route.durationMillis / 60000) + ' min estimated');
    routeStatus.textContent = (cached ? 'Cached driving route: ' : 'Driving route: ') + (summary.join(' · ') || 'Displayed on map; distance and duration were not returned.');
  }
  routeButton.addEventListener('click', async () => {
    if (routeButton.disabled || window.SabjiwalahDelivery.isBusy()) return;
    if (cache && Date.now() - cache.time < 300000 && nearby(cache.origin, origin)) { showRoute(cache.route, true); return; }
    if (Date.now() - lastRouteAttempt < 60000) { routeStatus.textContent = 'Wait one minute between new route requests. External navigation remains available.'; return; }
    routeBusy = true; lastRouteAttempt = Date.now(); const version = generation, positionVersion = gpsGeneration, from = { ...origin }; controls();
    routeStatus.textContent = 'Loading driving route…';
    const timeout = setTimeout(() => { if (alive && version === generation && routeBusy) routeStatus.textContent = 'Routing is taking longer than expected. Use external navigation while it finishes.'; }, 20000);
    try {
      const { Route } = await window.google.maps.importLibrary('routes');
      const { routes } = await Route.computeRoutes({ origin: from, destination, travelMode: 'DRIVING', fields: ['path', 'distanceMeters', 'durationMillis'] });
      if (!alive || version !== generation || positionVersion !== gpsGeneration || !active || document.hidden) return;
      if (!routes?.[0]?.path?.length) throw new Error('No route');
      showRoute(routes[0], false); cache = { route: routes[0], origin: from, time: Date.now() };
    } catch (error) {
      if (alive && version === generation && positionVersion === gpsGeneration) {
        const deniedAccess = /denied|blocked|not.*enabled|not.*authorized|has not been used|billing|403/i.test(String(error?.message || ''));
        const noRoute = error?.message === 'No route';
        const libraryUnavailable = /computeRoutes.*not a function|undefined.*computeRoutes/i.test(String(error?.message || ''));
        console.warn('Delivery routing unavailable:', deniedAccess ? 'provider-access' : noRoute ? 'no-route' : libraryUnavailable ? 'library-unavailable' : String(error?.code || error?.name || 'unknown'));
        routeStatus.textContent = deniedAccess ? 'Routing access is unavailable. Ask the administrator to check Routes API, billing and browser-key restrictions. External navigation remains available.' : noRoute ? 'No driving route was returned for these saved locations. Use external navigation or browser directions.' : 'Driving route unavailable. Use external navigation or browser directions.';
      }
    }
    finally { clearTimeout(timeout); routeBusy = false; controls(); }
  });
  clearButton.addEventListener('click', clearLocation);
  document.addEventListener('visibilitychange', () => { if (document.hidden) clearLocation(); controls(); });
  window.addEventListener('offline', controls); window.addEventListener('online', controls);
  window.addEventListener('pagehide', () => { alive = false; clear(); });
  window.SabjiwalahDeliveryMap = { setOrder, clear };
})();
