(() => {
  'use strict';
  const iconPaths = {
    dashboard: 'M3 3h7v7H3zM14 3h7v7h-7zM3 14h7v7H3zM14 14h7v7h-7z',
    box: 'm3 7 9-4 9 4v10l-9 4-9-4V7Zm0 0 9 4 9-4M12 11v10',
    people: 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M16 4a4 4 0 0 1 0 8M22 21v-2a4 4 0 0 0-3-4M9 3a4 4 0 1 0 0 8 4 4 0 0 0 0-8',
    truck: 'M3 6h11v11H3zM14 10h4l3 4v3h-7M7 20a2 2 0 1 0 0-4 2 2 0 0 0 0 4Zm11 0a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z',
    route: 'm12 3 9 9-9 9-9-9 9-9ZM8 14v-4h8m-3-3 3 3-3 3',
    cash: 'M3 5h18v14H3V5ZM12 8v8M9 10h6m-6 4h6',
    tag: 'M3 3h8l10 10-8 8L3 11V3ZM7 7h1',
    settings: 'M4 6h16M4 12h16M4 18h16M8 3v6m8 0v6M10 15v6',
    refresh: 'M20 7v5h-5M4 17v-5h5M6 6a8 8 0 0 1 13 3M5 15a8 8 0 0 0 13 3',
    edit: 'm15 4 5 5M4 20l4-1L21 6l-4-4L4 15v5Z',
    plus: 'M12 5v14M5 12h14',
    menu: 'M4 6h16M4 12h16M4 18h16',
    close: 'm6 6 12 12M18 6 6 18',
    theme: 'M20 14a8 8 0 0 1-10-10 8 8 0 1 0 10 10Z',
    eye: 'M2 12s4-7 10-7 10 7 10 7-4 7-10 7-10-7-10-7ZM12 9a3 3 0 1 0 0 6 3 3 0 0 0 0-6',
  };
  function icon(name) {
    const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.setAttribute('viewBox', '0 0 24 24'); svg.setAttribute('aria-hidden', 'true');
    svg.classList.add('admin-ui-icon');
    const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
    path.setAttribute('d', iconPaths[name]); svg.append(path); return svg;
  }
  const navIcons = ['dashboard', 'box', 'box', 'people', 'truck', 'route', 'cash', 'tag', 'tag', 'settings'];
  document.querySelectorAll('.admin-nav-item').forEach((link, index) => link.prepend(icon(navIcons[index] || 'box')));
  const decorated = new WeakSet();
  function decorateButtons() {
    document.querySelectorAll('.admin-button,.admin-icon-button').forEach(button => {
      if (decorated.has(button) || button.querySelector('svg')) return;
      const label = button.textContent.trim();
      let name, iconOnly = false;
      if (button.matches('[data-admin-menu]')) { name = 'menu'; iconOnly = true; }
      else if (button.matches('[data-admin-close]')) { name = 'close'; iconOnly = true; }
      else if (button.matches('[data-theme-toggle]')) { name = 'theme'; iconOnly = true; }
      else if (/^(refresh|reload)/i.test(label)) { name = 'refresh'; iconOnly = true; }
      else if (/^edit/i.test(label)) name = 'edit';
      else if (/^(add|new|create)/i.test(label)) name = 'plus';
      else if (/^view/i.test(label)) name = 'eye';
      if (!name) return;
      decorated.add(button);
      if (iconOnly) {
        const accessible = button.getAttribute('aria-label') || label;
        button.setAttribute('aria-label', accessible); button.title = accessible;
        button.textContent = ''; button.classList.add('admin-compact-icon');
      }
      button.prepend(icon(name));
    });
  }
  const tableConfigs = [
    ['products-list', 'product-row', ['Image', 'Product', 'Price', 'Stock / status', 'Actions']],
    ['customers-list', 'customer-row', ['Customer', 'Registered', 'Orders', 'Status', 'Actions']],
    ['riders-list', 'rider-row', ['Personnel', 'Account', 'Availability', 'Current orders', 'Completed', 'Actions']],
    ['cash-list', 'cash-record', ['Order', 'Rider / amount', 'Collected', 'Handover']],
    ['dispatch-list', 'dispatch-row', ['Order / progress', 'Rider', 'Waiting time', 'Action']],
    ['campaign-list', 'campaign-record', ['Campaign', 'Status', 'Offer / placement', 'Rules / image', 'Usage / destination', 'Schedule', 'Action']],
  ];
  function compactTables() {
    tableConfigs.forEach(([id, rowClass, headers]) => {
      const list = document.getElementById(id); if (!list) return;
      const records = Array.from(list.children).filter(child => child.classList.contains(rowClass));
      if (!records.length) return;
      const wrapper = document.createElement('div'); wrapper.className = 'admin-table-wrap admin-compact-wrap';
      wrapper.tabIndex = 0; wrapper.setAttribute('role', 'region'); wrapper.setAttribute('aria-label', headers[0] + ' data table, scroll horizontally for more columns');
      const table = document.createElement('table'); table.className = 'admin-table admin-compact-table';
      const head = document.createElement('thead'), headRow = document.createElement('tr');
      headers.forEach(label => { const cell = document.createElement('th'); cell.scope = 'col'; cell.textContent = label; headRow.append(cell); });
      head.append(headRow); const body = document.createElement('tbody');
      records.forEach(record => {
        const row = document.createElement('tr');
        Array.from(record.children).forEach(child => { const cell = document.createElement('td'); cell.append(child); row.append(cell); });
        body.append(row); record.remove();
      });
      table.append(head, body); wrapper.append(table); list.prepend(wrapper);
    });
  }
  document.querySelectorAll('form.admin-card').forEach(form => {
    if (!/filters$/.test(form.id)) return;
    const dialog = document.createElement('dialog'); dialog.className = 'admin-filter-sheet';
    const title = document.createElement('h2'); title.id = form.id + '-title'; title.textContent = 'Search & filters';
    dialog.setAttribute('aria-labelledby', title.id);
    const heading = document.createElement('header');
    const close = document.createElement('button'); close.type = 'button'; close.className = 'admin-icon-button';
    close.setAttribute('aria-label', 'Close filters'); close.append(icon('close'));
    heading.append(title, close);
    const trigger = document.createElement('button'); trigger.type = 'button'; trigger.className = 'admin-button admin-button-secondary admin-filter-trigger';
    trigger.setAttribute('aria-label', 'Search and filter records'); trigger.setAttribute('aria-haspopup', 'dialog');
    trigger.title = 'Search & filters'; trigger.append(icon('settings'));
    const pageHeading = document.querySelector('.admin-heading');
    if (pageHeading) {
      let actions = pageHeading.querySelector('.admin-heading-controls');
      if (!actions) {
        actions = document.createElement('div'); actions.className = 'admin-heading-controls';
        Array.from(pageHeading.children).slice(1).forEach(child => actions.append(child));
        pageHeading.append(actions);
      }
      actions.append(trigger);
    } else form.before(trigger);
    form.before(dialog); dialog.append(heading, form);
    const footer = form.querySelector('.orders-filter-actions') || document.createElement('div');
    footer.classList.add('admin-filter-sheet-actions');
    Array.from(form.children).filter(child => child.tagName === 'BUTTON').forEach(button => footer.append(button));
    form.append(footer);
    trigger.addEventListener('click', () => dialog.showModal());
    function dismiss() { dialog.close(); trigger.focus(); }
    close.addEventListener('click', dismiss);
    dialog.addEventListener('click', event => {
      if (event.target !== dialog) return;
      const rect = dialog.getBoundingClientRect();
      if (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom) dismiss();
    });
    form.addEventListener('submit', dismiss);
    form.addEventListener('reset', dismiss);
  });
  function enhance() { compactTables(); decorateButtons(); }
  enhance();
  const observer = new MutationObserver(enhance);
  observer.observe(document.getElementById('admin-main'), { childList: true, subtree: true });
  window.addEventListener('pagehide', () => observer.disconnect());
})();
