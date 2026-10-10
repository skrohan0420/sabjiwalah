<?= $this->extend('admin/layout') ?>
<?= $this->section('content') ?>
<section id="campaign-page" data-kind="<?= esc($kind, 'attr') ?>">
<header class="admin-heading"><div><p class="admin-eyebrow">CAMPAIGNS</p><h1><?= esc($pageTitle) ?></h1><p class="admin-muted">Manage campaign details and schedules.</p></div><div class="campaign-actions"><button type="button" id="campaign-refresh" class="admin-button admin-button-secondary">Refresh</button><button type="button" id="campaign-new" class="admin-button">Create <?= $kind === 'offers' ? 'offer' : 'promotion' ?></button></div></header>
<p class="admin-alert"><?= $kind === 'offers' ? 'Customers apply one coupon at checkout. Discounts are calculated by the server. Usage counts committed orders, including later cancellations.' : 'Enabled promotions in the home placement appear in Store highlights during their scheduled dates.' ?></p>
<form id="campaign-filters" class="admin-card products-filters"><div class="admin-field"><label for="campaign-search">Search <?= $kind === 'offers' ? 'name or code' : 'title' ?></label><input id="campaign-search" name="search" type="search" maxlength="120"></div><div class="admin-field"><label for="campaign-status">Schedule status</label><select name="status" id="campaign-status"><option value="">All campaigns</option><option value="enabled">Enabled now</option><option value="scheduled">Scheduled</option><option value="expired">Expired</option><option value="disabled">Disabled</option></select></div><button class="admin-button">Apply filters</button></form>
<p id="campaign-error" class="admin-alert admin-alert-error" role="alert" hidden></p><div id="campaign-list" aria-live="polite"></div>
<nav class="orders-pagination" aria-label="Campaign pages"><button type="button" id="campaign-prev" class="admin-button admin-button-secondary">Previous</button><span id="campaign-page-number"></span><button type="button" id="campaign-next" class="admin-button admin-button-secondary">Next</button></nav>
</section>
<dialog id="campaign-editor" class="admin-dialog campaign-editor" aria-labelledby="campaign-editor-title">
<form id="campaign-form"><h2 id="campaign-editor-title">Campaign</h2><p id="campaign-save-error" class="admin-alert admin-alert-error" role="alert" hidden></p>
<?php if ($kind === 'offers'): ?>
<div class="admin-field"><label for="campaign-name">Offer name</label><input id="campaign-name" name="name" maxlength="150" required></div>
<div class="admin-field"><label for="campaign-code">Coupon code</label><input id="campaign-code" name="code" maxlength="80" pattern="[A-Za-z0-9][A-Za-z0-9_-]{0,79}" required><small>Letters, numbers, underscores and hyphens. Saved in uppercase.</small></div>
<div class="campaign-grid"><div class="admin-field"><label for="campaign-type">Discount type</label><select id="campaign-type" name="type"><option value="percentage">Percentage</option><option value="fixed">Fixed amount (₹)</option></select></div><div class="admin-field"><label for="campaign-value">Discount value</label><input id="campaign-value" name="value" type="number" min="0.01" max="100" step="0.01" required></div></div>
<div class="campaign-grid"><div class="admin-field"><label for="campaign-minimum">Minimum cart subtotal (₹)</label><input id="campaign-minimum" name="minimum_order_amount" type="number" min="0" max="99999999.99" step="0.01"></div><div class="admin-field"><label for="campaign-maximum">Maximum discount (₹)</label><input id="campaign-maximum" name="maximum_discount" type="number" min="0.01" max="99999999.99" step="0.01"><small>Leave blank for no cap.</small></div></div>
<div class="admin-field"><label for="campaign-limit">Total usage limit</label><input id="campaign-limit" name="usage_limit" type="number" min="1" max="4294967295" step="1"><small>Leave blank for unlimited. Counts successfully placed orders.</small></div>
<?php else: ?>
<div class="admin-field"><label for="campaign-title">Title</label><input id="campaign-title" name="title" maxlength="150" required></div>
<div class="admin-field"><label for="campaign-image">Image URL or site path</label><input id="campaign-image" name="image" maxlength="255" placeholder="/assets/images/banner.jpg"><small>HTTPS URLs or paths beginning with /. Images are not uploaded here.</small></div>
<div class="admin-field"><label for="campaign-link">Destination URL or site path</label><input id="campaign-link" name="link" maxlength="255" placeholder="/products"></div>
<div class="admin-field"><label for="campaign-position">Placement label</label><input id="campaign-position" name="position" maxlength="80" placeholder="home"><small>Use home to show this promotion in storefront highlights.</small></div>
<?php endif ?>
<div class="campaign-grid"><div class="admin-field"><label for="campaign-starts">Starts at</label><input id="campaign-starts" name="starts_at" type="datetime-local" min="1000-01-01T00:00" max="9999-12-31T23:59" step="1"></div><div class="admin-field"><label for="campaign-ends">Ends at</label><input id="campaign-ends" name="ends_at" type="datetime-local" min="1000-01-01T00:00" max="9999-12-31T23:59" step="1"></div></div>
<p class="admin-caption">Dates use your device timezone. Leave blank for no start or end limit. The end time is exclusive.</p>
<div class="admin-field"><label for="campaign-active">Campaign state</label><select id="campaign-active" name="is_active"><option value="0">Disabled</option><option value="1">Enabled</option></select></div>
<div class="admin-dialog-actions"><button type="button" id="campaign-cancel" class="admin-button admin-button-secondary">Cancel</button><button id="campaign-save" class="admin-button">Save campaign</button></div>
</form></dialog>
<script defer src="<?= esc(app_static_url('assets/js/admin-campaigns.js'), 'attr') ?>"></script>
<?= $this->endSection() ?>
