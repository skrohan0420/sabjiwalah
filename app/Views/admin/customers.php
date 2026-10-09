<?= $this->extend('admin/layout') ?>
<?= $this->section('content') ?>
<section class="admin-heading"><div><p class="admin-eyebrow">SABJIWALAH CUSTOMERS</p><h1>Customers</h1><p class="admin-muted">Review accounts and order history, and manage account availability.</p></div><button class="admin-button admin-button-secondary" id="customers-refresh" type="button">Refresh</button></section>
<form id="customers-filters" class="admin-card products-filters">
<div class="admin-field"><label for="customers-search">Search customers</label><input id="customers-search" name="search" type="search" maxlength="120" placeholder="Name or phone number"></div>
<div class="admin-field"><label for="customers-status">Account status</label><select id="customers-status" name="status"><option value="">All statuses</option><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
<div class="admin-field"><label for="customers-sort">Sort by</label><select id="customers-sort" name="sort"><option value="created_at">Registration date</option><option value="name">Name</option></select></div>
<div class="admin-field"><label for="customers-dir">Direction</label><select id="customers-dir" name="dir"><option value="desc">Descending</option><option value="asc">Ascending</option></select></div>
<button class="admin-button" type="submit">Apply filters</button><button class="admin-button admin-button-secondary" type="reset">Clear</button>
</form>
<p class="admin-caption">Phone numbers are masked in the list. Open an account to view contact information.</p>
<p id="customers-summary" class="admin-caption" role="status">Loading customers…</p>
<div id="customers-error" class="admin-alert admin-alert-error" role="alert" hidden></div>
<div id="customers-list" aria-busy="true"></div>
<nav class="orders-pagination" aria-label="Customer pages"><button class="admin-button admin-button-secondary" id="customers-prev" type="button" disabled>Previous</button><span id="customers-page" role="status"></span><button class="admin-button admin-button-secondary" id="customers-next" type="button" disabled>Next</button></nav>
<dialog id="customer-details" class="admin-dialog customer-details" aria-labelledby="customer-title">
<div class="orders-detail-heading"><h2 id="customer-title">Customer details</h2><button class="admin-icon-button" id="customer-close" type="button" aria-label="Close customer details">×</button></div>
<div id="customer-error" class="admin-alert admin-alert-error" role="alert" hidden></div>
<button class="admin-button admin-button-secondary" id="customer-reload" type="button">Reload details</button>
<div id="customer-content" aria-busy="false"></div>
<section class="admin-card customer-management" id="customer-management" hidden><h3>Account status</h3><p class="admin-muted">Deactivation prevents login and ends existing access on the customer's next request. Existing orders stay available to staff for fulfilment.</p><button class="admin-button" id="customer-status-action" type="button">Deactivate account</button></section>
<section><h3>Order history</h3><div id="customer-orders"></div><nav class="orders-pagination" aria-label="Customer order history pages"><button class="admin-button admin-button-secondary" id="customer-orders-prev" type="button" disabled>Previous</button><span id="customer-orders-page" role="status"></span><button class="admin-button admin-button-secondary" id="customer-orders-next" type="button" disabled>Next</button></nav></section>
</dialog>
<noscript><p class="admin-alert admin-alert-error">Enable JavaScript to manage customers.</p></noscript>
<script defer src="<?= esc(app_static_url('assets/js/admin-customers.js'), 'attr') ?>"></script>
<?= $this->endSection() ?>
