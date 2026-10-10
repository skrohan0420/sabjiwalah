const test = require('node:test'), assert = require('node:assert/strict'), fs = require('node:fs'), vm = require('node:vm');
function el(tag = 'div') {
  return {tag, children: [], events: {}, value: '', disabled: false, hidden: false, textContent: '', dataset: {},
    append(...nodes) {this.children.push(...nodes);}, replaceChildren(...nodes) {this.children = nodes;},
    addEventListener(name, fn) {this.events[name] = fn;}, showModal() {this.open = true;}, close() {this.open = false;}};
}
const flush = () => new Promise(resolve => setImmediate(resolve));
const row = {uid: 'off_one', revision: 'a'.repeat(64), name: '<img src=x>', code: 'FRESH10', type: 'percentage', value: '10.00',
  minimum_order_amount: '200.00', maximum_discount: '80.00', usage_limit: '50', is_active: false, starts_at: null, ends_at: null, status: 'disabled'};
function setup(kind = 'offers') {
  const ids = Object.fromEntries(['campaign-page', 'campaign-list', 'campaign-filters', 'campaign-editor', 'campaign-form', 'campaign-error',
    'campaign-save-error', 'campaign-save', 'campaign-editor-title', 'campaign-page-number', 'campaign-prev', 'campaign-next', 'campaign-cancel', 'campaign-new', 'campaign-refresh'].map(id => [id, el()]));
  ids['campaign-page'].dataset.kind = kind;
  const fields = Object.fromEntries((kind === 'offers' ? ['name', 'code', 'type', 'value', 'minimum_order_amount', 'maximum_discount', 'usage_limit'] : ['title', 'image', 'link', 'position'])
    .concat(['starts_at', 'ends_at', 'is_active']).map(key => [key, el('input')]));
  fields.namedItem = key => fields[key]; ids['campaign-form'].elements = fields;
  ids['campaign-form'].reset = () => {for (const value of Object.values(fields)) if (typeof value === 'object') value.value = ''; fields.is_active.value = '0'; if (fields.type) fields.type.value = 'percentage';};
  const calls = [];
  vm.runInNewContext(fs.readFileSync('public/assets/js/admin-campaigns.js', 'utf8'), {
    document: {getElementById: id => ids[id], createElement: el},
    window: {SabjiwalahAdmin: {api: (path, options) => new Promise((resolve, reject) => calls.push({path, options, resolve, reject}))}},
    FormData: class {constructor(form) {this.form = form;} *[Symbol.iterator]() {
      if (this.form === ids['campaign-filters']) {yield ['status', '']; return;}
      for (const [key, field] of Object.entries(fields)) if (typeof field === 'object') yield [key, field.value];
    }}, URLSearchParams, Intl, Date, Math, Number, String, Object, encodeURIComponent
  });
  return {ids, fields, calls};
}
async function initialize(s, data = row) {s.calls[0].resolve({data: {items: [data], pager: {page_count: 1}}}); await flush();}
async function edit(s, data = row) {s.ids['campaign-list'].children[0].children.at(-1).events.click(); s.calls.at(-1).resolve({data}); await flush();}
const event = {preventDefault() {}};
test('campaign renders untrusted values as text; edit reloads latest record and sends revision without IDs', async () => {
  const s = setup(); await initialize(s);
  assert.equal(s.ids['campaign-list'].children[0].children[0].textContent, '<img src=x>');
  await edit(s); assert.equal(s.ids['campaign-editor'].open, true); assert.equal(s.fields.name.value, row.name);
  s.fields.name.value = 'Changed'; s.ids['campaign-form'].events.submit(event);
  const save = s.calls.at(-1); assert.equal(save.path, '/api/v1/admin/offers/off_one'); assert.equal(save.options.method, 'PUT');
  assert.equal(save.options.body.expected_revision, row.revision); assert.equal(save.options.body.is_active, 0);
  assert.ok(!('uid' in save.options.body));
  s.ids['campaign-form'].events.submit(event); assert.equal(s.calls.length, 3);
  save.resolve({data: row}); await flush(); assert.equal(s.ids['campaign-editor'].open, false); assert.equal(s.calls.length, 4);
});
test('new campaigns use POST and disabled default; percentage and fixed value bounds follow type', async () => {
  const s = setup(); await initialize(s); s.ids['campaign-new'].events.click(); assert.equal(s.fields.is_active.value, '0');
  assert.equal(s.fields.value.max, '100'); s.fields.type.value = 'fixed'; s.fields.type.events.change(); assert.equal(s.fields.value.max, '99999999.99');
  s.ids['campaign-form'].events.submit(event); assert.equal(s.calls[1].options.method, 'POST'); assert.ok(!('expected_revision' in s.calls[1].options.body));
});
test('validation details keep editor reusable; stale and uncertain saves block resubmission until refreshed', async () => {
  for (const status of [422, 409, 503, 0]) {
    const s = setup(); await initialize(s); await edit(s); s.ids['campaign-form'].events.submit(event);
    s.calls[2].reject(Object.assign(new Error('Save failed'), {status, errors: {value: 'Percentage cannot exceed 100.'}})); await flush();
    assert.equal(s.ids['campaign-editor'].open, true); assert.match(s.ids['campaign-save-error'].textContent, /Percentage cannot exceed 100/);
    assert.equal(s.ids['campaign-save'].disabled, status !== 422);
    s.ids['campaign-form'].events.submit(event); assert.equal(s.calls.length, status === 422 ? 4 : 3);
  }
});
test('busy save prevents dialog cancellation; promotion URLs render as inert text', async () => {
  const s = setup('promotions'), promo = {uid: 'pro_one', revision: row.revision, title: '<b>Title</b>', image: 'https://example.test/img', link: '/products',
    position: 'home', is_active: true, starts_at: null, ends_at: null, status: 'enabled'};
  await initialize(s, promo); assert.equal(s.ids['campaign-list'].children[0].children[0].textContent, promo.title);
  await edit(s, promo); s.ids['campaign-form'].events.submit(event); let cancelled = false;
  s.ids['campaign-editor'].events.cancel({preventDefault() {cancelled = true;}}); assert.equal(cancelled, true);
  s.ids['campaign-cancel'].events.click(); assert.equal(s.ids['campaign-editor'].open, true);
  assert.equal(s.calls[2].path, '/api/v1/admin/promotions/pro_one'); assert.equal(s.calls[2].options.body.link, '/products');
});
