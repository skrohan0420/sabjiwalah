const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
class Element {
  constructor(tag) { this.tagName = tag; this.children = []; this.attrs = {}; this.events = {}; this._text = ''; this.className = ''; }
  get classList() { return { contains: value => this.className.split(' ').includes(value), add: value => this.className += ' ' + value }; }
  get textContent() { return this._text + this.children.map(child => child.textContent).join(''); }
  set textContent(value) { this._text = value; this.children = []; }
  append(...nodes) { nodes.forEach(node => { node.remove(); node.parent = this; this.children.push(node); }); }
  prepend(node) { node.remove(); node.parent = this; this.children.unshift(node); }
  remove() { if (this.parent) { this.parent.children = this.parent.children.filter(child => child !== this); this.parent = null; } }
  before(node) { const parent = this.parent, index = parent.children.indexOf(this); node.remove(); node.parent = parent; parent.children.splice(index, 0, node); }
  setAttribute(name, value) { this.attrs[name] = value; }
  getAttribute(name) { return this.attrs[name]; }
  addEventListener(name, callback) { this.events[name] = callback; }
  showModal() { this.open = true; }
  close() { this.open = false; }
  focus() { this.focused = true; }
  matches(selector) {
    if (selector.startsWith('[')) return Object.hasOwn(this.attrs, selector.slice(1, -1));
    if (selector.startsWith('.')) return this.classList.contains(selector.slice(1));
    if (selector === 'form.admin-card') return this.tagName === 'form' && this.classList.contains('admin-card');
    return this.tagName === selector;
  }
  querySelectorAll(selector) { const result = []; const visit = node => node.children.forEach(child => { if (selector.split(',').some(part => child.matches(part))) result.push(child); visit(child); }); visit(this); return result; }
  querySelector(selector) { return this.querySelectorAll(selector)[0] || null; }
}
test('compact tables preserve action handlers, refresh safely, and mobile filters retain their form', () => {
  const root = new Element('main'), list = new Element('div'); list.id = 'customers-list'; root.append(list);
  let clicked = 0;
  function record() {
    const row = new Element('article'); row.className = 'customer-row';
    for (const text of ['Alice', 'Today', '3', 'Active']) { const cell = new Element('div'); cell.textContent = text; row.append(cell); }
    const action = new Element('button'); action.className = 'admin-button'; action.textContent = 'View'; action.addEventListener('click', () => clicked++); row.append(action); return { row, action };
  }
  const first = record(); list.append(first.row);
  const form = new Element('form'); form.className = 'admin-card'; form.id = 'customers-filters'; root.append(form);
  let observe;
  const document = { createElement: tag => new Element(tag), createElementNS: (_, tag) => new Element(tag), createTextNode: text => { const node = new Element('#text'); node.textContent = text; return node; }, getElementById: id => id === 'admin-main' ? root : id === list.id ? list : null, querySelector: selector => root.querySelector(selector), querySelectorAll: selector => root.querySelectorAll(selector) };
  vm.runInNewContext(fs.readFileSync('public/assets/js/admin-polish.js', 'utf8'), { document, window: { matchMedia: query => ({ matches: query.includes('max-width') }), addEventListener() {} }, MutationObserver: class { constructor(callback) { observe = callback; } observe() {} }, WeakSet });
  assert.equal(list.querySelectorAll('table').length, 1);
  assert.equal(list.querySelectorAll('th').length, 5);
  assert.equal(list.querySelectorAll('td').length, 5);
  first.action.events.click(); assert.equal(clicked, 1);
  assert.equal(first.action.getAttribute('aria-label'), undefined); // Visible View label remains.
  observe(); assert.equal(list.querySelectorAll('table').length, 1);
  assert.equal(form.parent.tagName, 'dialog'); assert.ok(!form.parent.open);
  const trigger = root.querySelector('.admin-filter-trigger');
  trigger.events.click(); assert.equal(form.parent.open, true);
  form.events.submit(); assert.equal(form.parent.open, false); assert.equal(trigger.focused, true);
  list.children = []; const second = record(); list.append(second.row); observe();
  second.action.events.click(); assert.equal(clicked, 2);
  assert.equal(list.querySelectorAll('table').length, 1);
});
