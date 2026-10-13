(() => {
  const get = id => document.getElementById(id);
  const dialog = get('delivery-notifications');
  if (!dialog) return;
  const key = 'sabjiwalah.delivery.notifications.' + dialog.dataset.rider;
  let items = [];
  let audio;
  const important=type=>['assignment','ready'].includes(type);
  const destinationFor = value => ['delivery', 'delivery/deliveries', 'delivery/history', 'delivery/profile'].includes(value) ? value : 'delivery/deliveries';
  function unlockSound() {
    try {
      const AudioContext = window.AudioContext || window.webkitAudioContext;
      if (!AudioContext) return;
      audio ||= new AudioContext();
      if (audio.state === 'suspended') audio.resume().catch(() => {});
    } catch { /* Keep notifications usable when audio is unavailable. */ }
  }
  document.addEventListener('pointerdown', unlockSound, { capture: true });
  document.addEventListener('keydown', unlockSound, { capture: true });
  function playSound() {
    try {
      if (!audio || audio.state !== 'running') return;
      [659.25, 880].forEach((frequency, index) => {
        const tone = audio.createOscillator(), gain = audio.createGain();
        const time = audio.currentTime + index * 0.12;
        tone.type = 'sine'; tone.frequency.value = frequency;
        gain.gain.setValueAtTime(0, time);
        gain.gain.linearRampToValueAtTime(0.08, time + 0.015);
        gain.gain.exponentialRampToValueAtTime(0.0001, time + 0.5);
        tone.connect(gain); gain.connect(audio.destination);
        tone.start(time); tone.stop(time + 0.55);
        tone.onended = () => { tone.disconnect(); gain.disconnect(); };
      });
    } catch { /* Sound must not interrupt delivery work. */ }
  }
  try {
    const saved = JSON.parse(sessionStorage.getItem(key) || '[]');
    if (Array.isArray(saved)) items = saved.filter(item => typeof item?.text === 'string' && typeof item.time === 'number' && Number.isFinite(item.time)).map(item=>({...item,type:item.type||(item.text==='Your assignment activity has changed. Check your deliveries.'?'assignment':item.text==='Your package is ready. Head to pickup.'?'ready':null)})).filter(item=>important(item.type)).slice(0, 30);
  } catch { /* Notifications still work without session storage. */ }
  const save = () => { try { sessionStorage.setItem(key, JSON.stringify(items)); } catch {} };
  save();
  function render() {
    const unread = items.filter(item => !item.read).length;
    const badge = get('delivery-notifications-badge');
    badge.hidden = !unread; badge.textContent = unread > 9 ? '9+' : String(unread);
    get('delivery-notifications-open').setAttribute('aria-label', unread ? `Notifications, ${unread} unread` : 'Notifications');
    const list = get('delivery-notifications-list');
    list.replaceChildren();
    items.forEach(item => {
      const row = document.createElement('a'); row.className = 'delivery-notification-row';
      row.href = window.Sabjiwalah.url(destinationFor(item.destination));
      row.setAttribute('aria-label', item.text + '. Open related page');
      const text = document.createElement('p'); text.textContent = item.text;
      const time = document.createElement('time'); time.dateTime = new Date(item.time).toISOString();
      time.textContent = new Date(item.time).toLocaleString('en-IN', { timeZone: 'Asia/Kolkata', day: 'numeric', month: 'short', hour: 'numeric', minute: '2-digit' });
      const arrow = document.createElement('span'); arrow.className = 'delivery-notification-arrow';
      arrow.textContent = '→'; arrow.setAttribute('aria-hidden', 'true');
      row.append(text, time, arrow); list.append(row);
    });
    get('delivery-notifications-empty').hidden = !!items.length;
    get('delivery-notifications-clear').hidden = !items.length;
  }
  get('delivery-notifications-open').addEventListener('click', () => {
    dialog.showModal(); items.forEach(item => item.read = true); save(); render();
  });
  get('delivery-notifications-close').addEventListener('click', () => dialog.close());
  dialog.addEventListener('click', event => {
    if (event.target !== dialog) return;
    const bounds = dialog.getBoundingClientRect();
    if (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom) dialog.close();
  });
  get('delivery-notifications-clear').addEventListener('click', () => { items = []; save(); render(); });
  window.addEventListener('sabjiwalah:delivery-notification', event => {
    const text = event.detail?.text;
    const type=event.detail?.type,id=event.detail?.id;
    if (!important(type)||typeof text !== 'string' || !text.trim()) return;
    if(items.some(item=>item.type===type&&(id?item.id===id:item.text===text&&Date.now()-item.time<60000)))return;
    items.unshift({ text: text.slice(0, 500), type, id:typeof id==='string'?id:undefined, destination: destinationFor(event.detail?.destination), time: Date.now(), read: dialog.open });
    items = items.slice(0, 30); save(); render();
    playSound();
  });
  document.getElementById('delivery-logout').addEventListener('click', () => { items = []; save(); render(); });
  render();
})();
