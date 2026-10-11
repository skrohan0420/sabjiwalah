(() => {
  'use strict';
  const panel = document.getElementById('delivery-tracking');
  if (!panel) return;
  const start = document.getElementById('delivery-tracking-start');
  const stopButton = document.getElementById('delivery-tracking-stop');
  const status = document.getElementById('delivery-tracking-status');
  const ui = window.SabjiwalahDelivery;
  let order, enabled = false, denied = false, alive = true, generation = 0;
  let timer, watch, pending, sample, lastSent, lastAttempt = 0, clearing = false;
  const eligible = () => alive && order?.order_status === 'out_for_delivery' && !!order.assignment_uid;
  const active = () => enabled && eligible() && !document.hidden && navigator.onLine !== false;
  const buttons = () => { start.disabled = !eligible() || enabled || denied || clearing || !!pending; stopButton.disabled = !enabled; };
  const distance = (a, b) => {
    const radians = n => n * Math.PI / 180;
    const x = Math.sin(radians(b.latitude - a.latitude) / 2) ** 2 + Math.cos(radians(a.latitude)) * Math.cos(radians(b.latitude)) * Math.sin(radians(b.longitude - a.longitude) / 2) ** 2;
    return 12742000 * Math.asin(Math.min(1, Math.sqrt(x)));
  };
  function clearWatch() {
    if (watch !== undefined) navigator.geolocation.clearWatch(watch);
    watch = undefined; clearTimeout(timer); timer = undefined; sample = undefined; lastSent = undefined;
  }
  async function stop(text = 'Tracking is off.', remove = true) {
    enabled = false; generation++; clearWatch(); status.textContent = text; buttons();
    // Wait for uploads already sent, then clear. A pending CSRF fetch cannot send.
    if (remove && alive && navigator.onLine !== false && !clearing) {
      clearing = true; buttons();
      try { if (pending) { try { await pending; } catch {} } await ui.api('api/v1/delivery/location', {}, 'DELETE'); }
      catch { if (alive) status.textContent = text + ' The last server location may remain for up to one hour.'; }
      finally { clearing = false; buttons(); }
    }
  }
  function schedule(token) { if (active() && token === generation) timer = setTimeout(() => { timer = undefined; send(token); }, 15000); }
  async function send(token) {
    if (!active() || token !== generation || pending) return;
    if (ui.isBusy()) { schedule(token); return; }
    let accessError;
    const check = ui.api('api/v1/delivery/orders/' + encodeURIComponent(order.uid));
    pending = check;
    try {
      const response = await check;
      if (token === generation && active()) window.SabjiwalahDeliveryTracking.setOrder(response.data.order);
    } catch (error) { accessError = error; }
    finally { pending = undefined; buttons(); }
    if (token !== generation || !active()) return;
    if (accessError) { await stop(ui.errorText(accessError) + ' Tracking stopped.'); return; }
    const now = Date.now();
    if (!sample || now - sample.captured_at_ms > 30000 || now - lastAttempt < 15000 || (lastSent && distance(lastSent, sample) < 25 && now - lastAttempt < 60000)) { schedule(token); return; }
    const payload = { order_uid: order.uid, assignment_uid: order.assignment_uid, ...sample };
    lastAttempt = now;
    const operation = ui.api('api/v1/delivery/location', payload, 'POST', () => active() && token === generation);
    pending = operation; buttons();
    let failure;
    try {
      await operation;
      if (active() && token === generation) {
        lastSent = payload;
        status.textContent = 'Location shared at ' + new Date().toLocaleTimeString() + ' · accuracy ±' + Math.round(payload.accuracy) + ' metres.';
      }
    } catch (error) { failure = error; }
    finally { pending = undefined; buttons(); }
    if (token !== generation || !enabled) return;
    if (failure && failure.status !== 429) { await stop(ui.errorText(failure) + ' Tracking stopped.'); return; }
    if (failure) status.textContent = 'Location updates limited. Waiting before the next update.';
    schedule(token);
  }
  start.addEventListener('click', () => {
    if (!eligible() || enabled || denied || clearing || pending || ui.isBusy()) return;
    if (!window.isSecureContext || !navigator.geolocation) { status.textContent = 'Tracking requires HTTPS and browser location support.'; return; }
    if (document.hidden || navigator.onLine === false) { status.textContent = 'Keep this page visible and reconnect before enabling tracking.'; return; }
    enabled = true; const token = ++generation; lastAttempt = 0; buttons();
    status.textContent = 'Waiting for a fresh GPS reading. Location will be sent to Sabjiwalah for this delivery.';
    watch = navigator.geolocation.watchPosition(position => {
      if (!active() || token !== generation) return;
      const {latitude, longitude, accuracy} = position.coords;
      const timestamp = position.timestamp;
      if (![latitude, longitude, accuracy, timestamp].every(Number.isFinite) || Math.abs(latitude) > 90 || Math.abs(longitude) > 180 || accuracy < 0 || accuracy > 100 || Date.now() - timestamp > 30000 || timestamp > Date.now() + 10000) {
        status.textContent = 'Waiting for a fresh location accurate to within 100 metres.'; return;
      }
      sample = {latitude, longitude, accuracy, captured_at_ms: Math.round(timestamp)};
      if (!timer && !pending) send(token);
    }, error => {
      if (token !== generation) return;
      if (error.code === 1) denied = true;
      stop(error.code === 1 ? 'Location permission denied. Change browser permissions and reload to enable tracking.' : 'GPS unavailable or timed out. Tracking stopped.');
    }, {enableHighAccuracy:true, maximumAge:0, timeout:15000});
  });
  stopButton.addEventListener('click', () => { if (enabled) stop(); });
  document.addEventListener('visibilitychange', () => { if (document.hidden && enabled) stop('Tracking stopped because this page is hidden. Enable it again when you return.'); });
  window.addEventListener('offline', () => { if (enabled) stop('Tracking stopped while offline. Enable it after reconnecting.', false); });
  window.addEventListener('pagehide', () => { alive = false; stop('Tracking is off.', false); });
  window.SabjiwalahDeliveryTracking = {
    setOrder(value) {
      const changed = order && (order.uid !== value?.uid || order.assignment_uid !== value?.assignment_uid);
      order = value;
      if (enabled && (changed || !eligible())) stop('Tracking stopped because the delivery or assignment changed.');
      panel.hidden = !value; buttons();
    },
    clear() { order = undefined; stop('Tracking is off.'); panel.hidden = true; },
    stop,
  };
  buttons();
})();
