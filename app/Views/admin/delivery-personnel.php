<?= $this->extend('admin/layout') ?>
<?= $this->section('content') ?>
<section class="admin-heading"><div><p class="admin-eyebrow">SABJIWALAH DELIVERY TEAM</p><h1>Delivery personnel</h1><p class="admin-muted">Manage accounts, availability and delivery workload.</p></div><div class="admin-dialog-actions"><button id="riders-refresh" class="admin-button admin-button-secondary" type="button">Refresh</button><button id="riders-create" class="admin-button" type="button">Add delivery person</button></div></section>
<form id="riders-filters" class="admin-card products-filters">
<div class="admin-field"><label for="riders-search">Search personnel</label><input id="riders-search" name="search" type="search" maxlength="120" placeholder="Name or phone number"></div>
<div class="admin-field"><label for="riders-status">Account status</label><select id="riders-status" name="status"><option value="">All statuses</option><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
<button class="admin-button" type="submit">Apply filters</button><button class="admin-button admin-button-secondary" type="reset">Clear</button>
</form>
<p class="admin-caption">Availability is staff-set. Assigned and busy reflect current orders; offline does not indicate a live connection. Refresh to see the latest workload.</p>
<p id="riders-summary" class="admin-caption" role="status">Loading delivery personnel…</p><div id="riders-error" class="admin-alert admin-alert-error" role="alert" hidden></div><div id="riders-list" aria-busy="true"></div>
<nav class="orders-pagination" aria-label="Delivery personnel pages"><button id="riders-prev" class="admin-button admin-button-secondary" type="button" disabled>Previous</button><span id="riders-page" role="status"></span><button id="riders-next" class="admin-button admin-button-secondary" type="button" disabled>Next</button></nav>
<dialog id="rider-details" class="admin-dialog customer-details" aria-labelledby="rider-title">
<div class="orders-detail-heading"><h2 id="rider-title">Delivery person</h2><button id="rider-close" class="admin-icon-button" type="button" aria-label="Close delivery person details">×</button></div>
<div id="rider-error" class="admin-alert admin-alert-error" role="alert" hidden></div><button id="rider-reload" class="admin-button admin-button-secondary" type="button">Reload details</button>
<div id="rider-content" aria-busy="false"></div>
<section id="rider-management" class="admin-card customer-management" hidden><h3>Account and availability</h3><p class="admin-muted">Deactivation ends access on the next request and resets availability to offline. Current assignments remain visible for staff to handle.</p><button id="rider-status-action" class="admin-button" type="button">Activate account</button>
<form id="rider-availability-form" class="rider-availability-form"><div class="admin-field"><label for="rider-availability">Staff-set availability</label><select id="rider-availability" name="availability"><option value="offline">Offline</option><option value="available">Available</option><option value="unavailable">Unavailable</option></select></div><button id="rider-availability-save" class="admin-button admin-button-secondary" type="submit">Save availability</button></form>
<p class="admin-caption">An assigned or busy state takes precedence while orders are active. The staff-set state takes effect when the workload clears.</p></section>
<section><div class="orders-detail-heading"><h3>Deliveries</h3><div class="admin-field"><label for="rider-orders-mode">Show</label><select id="rider-orders-mode"><option value="current">Current assignments</option><option value="history">Assignment history</option></select></div></div><div id="rider-orders"></div><nav class="orders-pagination" aria-label="Delivery history pages"><button id="rider-orders-prev" class="admin-button admin-button-secondary" type="button" disabled>Previous</button><span id="rider-orders-page" role="status"></span><button id="rider-orders-next" class="admin-button admin-button-secondary" type="button" disabled>Next</button></nav></section>
</dialog>
<dialog id="rider-create" class="admin-dialog" aria-labelledby="rider-create-title"><form id="rider-create-form">
<div class="orders-detail-heading"><h2 id="rider-create-title">Add delivery person</h2><button id="rider-create-close" class="admin-icon-button" type="button" aria-label="Close account form">×</button></div>
<p class="admin-muted">The account starts inactive. Review the phone number and activate it from its details. The person then uses the existing phone OTP login.</p>
<div id="rider-create-error" class="admin-alert admin-alert-error" role="alert" hidden></div>
<div class="admin-field"><label for="rider-name">Full name</label><input id="rider-name" name="name" required maxlength="120" autocomplete="name"></div>
<div class="admin-field"><label for="rider-phone">Indian mobile number</label><input id="rider-phone" name="phone" type="tel" required maxlength="30" autocomplete="tel" placeholder="10 digits or +91"></div>
<div class="admin-field"><label for="rider-email">Email (optional)</label><input id="rider-email" name="email" type="email" maxlength="190" autocomplete="email"></div>
<p id="rider-create-uncertain" class="admin-caption" hidden>The result is uncertain. Close this form and search the phone number before trying again.</p>
<div class="admin-dialog-actions"><button id="rider-create-save" class="admin-button" type="submit">Create inactive account</button></div>
</form></dialog>
<noscript><p class="admin-alert admin-alert-error">Enable JavaScript to manage delivery personnel.</p></noscript>
<script defer src="<?= esc(app_static_url('assets/js/admin-delivery-personnel.js'), 'attr') ?>"></script>
<?= $this->endSection() ?>
