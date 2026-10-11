(() => {
  'use strict';
  const $ = id => document.getElementById(id), canvas = $('dispatch-tracking-map');
  if (!canvas) return;
  const status = $('dispatch-tracking-status'), details = $('dispatch-tracking-details');
  const select = $('dispatch-tracking-order'), load = $('dispatch-tracking-load');
  let orders = [], selected = '', requested = false, alive = true, generation = 0;
  let loader, loading = false, map, Marker, mapsLibrary, authFailed = false, unavailable = false;
  const markers = new Map();
  const point = value => value && typeof value.latitude === 'number' && typeof value.longitude === 'number' && Number.isFinite(value.latitude) && Number.isFinite(value.longitude) && Math.abs(value.latitude) <= 90 && Math.abs(value.longitude) <= 180 ? {lat:value.latitude,lng:value.longitude} : null;
  const node = (tag, text, cls) => { const el = document.createElement(tag); el.textContent = text; if (cls) el.className = cls; return el; };
  const visibleOrders = () => selected ? orders.filter(order => order.uid === selected) : orders;
  function removeMarkers() { for (const item of markers.values()) item.marker.map = null; markers.clear(); }
  function clear() {
    generation++; orders = []; selected = ''; unavailable = false; loading = false;
    removeMarkers(); if (map) window.google?.maps?.event?.clearInstanceListeners(map);
    map = undefined; canvas.replaceChildren(); canvas.hidden = true; details.replaceChildren();
    select.replaceChildren(node('option', 'All orders on this page')); select.children[0].value = ''; select.value = '';
    status.textContent = 'Waiting for current dispatch data…'; load.disabled = true;
  }
  function descriptions() {
    details.replaceChildren();
    for (const order of visibleOrders()) {
      const row = node('article', '', 'dispatch-tracking-record');
      row.append(node('strong', order.order_number), node('p', (order.order_status === 'ready_for_delivery' ? 'Awaiting pickup' : 'Out for delivery') + ' · ' + (order.rider_name || 'Unassigned'), 'admin-caption'));
      const location = order.order_status === 'out_for_delivery' && order.rider_status !== 'inactive' ? order.rider_location : null;
      let text = order.rider_status === 'inactive' ? 'Rider account inactive; location unavailable.' : 'No recent rider location available.';
      if (point(location)) {
        const stamp = new Date(location.recorded_at);
        const age = Math.max(location.age_seconds || 0, Number.isFinite(stamp.getTime()) ? Math.floor((Date.now() - stamp.getTime()) / 1000) : 0);
        const stale = unavailable || location.stale || age >= 120;
        text = (stale ? 'Stale location' : 'Latest known location') + ' · ' + (Number.isFinite(stamp.getTime()) ? stamp.toLocaleString('en-IN', {timeZone:'Asia/Kolkata'}) + ' IST' : 'timestamp unavailable') + ' · ' + Math.floor(age / 60) + ' min ago · accuracy ±' + Math.round(location.accuracy) + ' metres.';
        row.append(node('span', stale ? 'Stale — verify with rider' : 'Recent update', 'admin-badge ' + (stale ? 'admin-badge-warning' : 'admin-badge-success')));
      } else if (order.order_status === 'ready_for_delivery') text = 'Location sharing starts after pickup when the rider enables tracking.';
      row.append(node('p', text, 'admin-caption'));
      if (!point(order.destination)) row.append(node('p', 'Saved customer coordinates are missing or invalid.', 'admin-caption'));
      details.append(row);
    }
    if (!orders.length) details.append(node('p', 'No active deliveries match this page.', 'admin-caption'));
  }
  function sync(fit = false) {
    descriptions(); if (!map) return;
    const wanted = new Set(), bounds = new window.google.maps.LatLngBounds(); let points = 0;
    for (const order of visibleOrders()) {
      for (const [kind, value] of [['destination', order.destination], ['rider', order.order_status === 'out_for_delivery' && order.rider_status !== 'inactive' ? order.rider_location : null]]) {
        const position = point(value); if (!position) continue;
        const stale = kind === 'rider' && (unavailable || value.stale || Date.now() - new Date(value.recorded_at).getTime() >= 120000);
        const key = order.uid + ':' + kind; wanted.add(key); bounds.extend(position); points++;
        let item = markers.get(key);
        if (!item) {
          const content = node('span', '', 'dispatch-map-pin');
          const marker = new Marker({map, position, content});
          item = {marker, content}; markers.set(key, item);
        }
        item.marker.position = position;
        item.marker.title = order.order_number + ' · ' + (kind === 'destination' ? 'Customer destination' : (order.rider_name || 'Rider') + (stale ? ' · stale location' : ' · latest location'));
        item.content.textContent = kind === 'destination' ? 'Destination' : (stale ? 'Rider · stale' : 'Rider');
        item.content.className = 'dispatch-map-pin dispatch-map-' + (kind === 'destination' ? 'destination' : stale ? 'stale' : 'rider');
      }
    }
    for (const [key, item] of markers) if (!wanted.has(key)) { item.marker.map = null; markers.delete(key); }
    canvas.hidden = !points;
    if (fit && points) map.fitBounds(bounds, 45);
    status.textContent = unavailable ? 'Dispatch refresh failed. Locations shown are stale; refresh before relying on them.' : points ? 'Showing latest known delivery positions for ' + visibleOrders().length + (visibleOrders().length === 1 ? ' order' : ' orders') + ' on this page. Select a delivery to focus.' : 'No valid coordinates to display for this selection.';
  }
  function failure() {
    requested = false; removeMarkers(); if (map) window.google?.maps?.event?.clearInstanceListeners(map); map = undefined; canvas.replaceChildren(); canvas.hidden = true;
    status.textContent = 'Google Maps is unavailable. Delivery names, statuses and update timestamps remain below.';
    load.disabled = authFailed; load.textContent = 'Retry delivery map';
  }
  function loadGoogle() {
    if (window.google?.maps?.importLibrary) return Promise.resolve();
    if (loader) return loader;
    const key = canvas.dataset.googleApiKey?.trim();
    if (!key || key.includes('REPLACE')) return Promise.reject(new Error('Maps unavailable'));
    loader = new Promise((resolve, reject) => {
      const script = document.createElement('script'); let settled = false;
      const finish = error => { if (settled) return; settled = true; clearTimeout(timeout); if (error) { loader = undefined; script.remove(); reject(error); } else resolve(); };
      const timeout = setTimeout(() => finish(new Error('Map timed out')), 20000);
      window.sabjiwalahDispatchMapsReady = () => finish();
      window.gm_authFailure = () => { authFailed = true; finish(new Error('Maps unavailable')); if (alive) failure(); };
      const url = new URL('https://maps.googleapis.com/maps/api/js');
      url.search = new URLSearchParams({key, v:'weekly', loading:'async', language:'en', region:'IN', callback:'sabjiwalahDispatchMapsReady'});
      script.src = url.href; script.async = true; script.referrerPolicy = 'strict-origin-when-cross-origin';
      script.onerror = () => finish(new Error('Map failed')); document.head.append(script);
    });
    return loader;
  }
  async function init() {
    if (loading || !alive || document.hidden || !orders.length || authFailed) return;
    const center = visibleOrders().flatMap(order => [point(order.destination), point(order.rider_location)]).find(Boolean);
    if (!center) { status.textContent = 'No valid coordinates to display for this selection.'; return; }
    loading = true; load.disabled = true; const version = generation; status.textContent = 'Loading delivery map…';
    try {
      await loadGoogle(); let timeout;
      const libs = await Promise.race([Promise.all([window.google.maps.importLibrary('maps'), window.google.maps.importLibrary('marker')]), new Promise((_, reject) => { timeout = setTimeout(() => reject(new Error('Map libraries timed out')), 20000); })]).finally(() => clearTimeout(timeout));
      if (!alive || generation !== version || document.hidden) return;
      if (authFailed) throw new Error('Maps unavailable');
      mapsLibrary = libs[0]; Marker = libs[1].AdvancedMarkerElement;
      map = new mapsLibrary.Map(canvas, {center, zoom:12, mapId:canvas.dataset.googleMapId || 'DEMO_MAP_ID', mapTypeControl:false, streetViewControl:false, fullscreenControl:false, gestureHandling:'cooperative'});
      load.textContent = 'Fit delivery locations'; sync(true);
    } catch { if (alive && generation === version) failure(); }
    finally { if (generation === version) { loading = false; load.disabled = authFailed || !orders.length; } }
  }
  select.addEventListener('change', () => { selected = select.value; sync(true); });
  load.addEventListener('click', () => { requested = true; if (map) sync(true); else init(); });
  window.SabjiwalahDispatchMap = {
    setOrders(items, trackingAvailable = true) {
      if (!alive || document.hidden) return;
      const previous = selected; unavailable = false;
      orders = Array.isArray(items) ? items.filter(item => ['ready_for_delivery','out_for_delivery'].includes(item.order_status)) : [];
      if (!orders.some(order => order.uid === selected)) selected = '';
      select.replaceChildren(node('option', 'All orders on this page')); select.children[0].value = '';
      for (const order of orders) { const option = node('option', order.order_number + ' · ' + (order.rider_name || 'Unassigned')); option.value = order.uid; select.append(option); }
      select.value = selected; load.disabled = !orders.length || loading || authFailed;
      if (authFailed) { descriptions(); failure(); return; }
      sync(previous !== selected);
      if (!map) status.textContent = trackingAvailable ? 'Select Load delivery map to display the current page. Update timestamps are shown below.' : 'Rider tracking storage is not deployed. Destinations can still be displayed.';
      if (requested && !map) init();
    },
    clear,
    unavailable() { unavailable = true; sync(); status.textContent = 'Dispatch refresh failed. Locations shown are stale; refresh before relying on them.'; },
  };
  document.addEventListener('visibilitychange', () => { if (document.hidden) clear(); });
  window.addEventListener('offline', clear);
  window.addEventListener('pagehide', () => { alive = false; clear(); });
})();
