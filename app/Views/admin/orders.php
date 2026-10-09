<?= $this->extend('admin/layout') ?>
<?= $this->section('content') ?>
<section class="admin-heading"><div><p class="admin-eyebrow">SABJIWALAH OPERATIONS</p><h1>Orders</h1><p class="admin-muted">Follow every order from placement to preparation.</p></div><button class="admin-button admin-button-secondary" id="orders-refresh" type="button">Refresh</button></section>
<div id="orders-new" class="admin-alert" role="status" hidden></div>
<form id="orders-filters" class="admin-card orders-filters">
    <div class="admin-field"><label for="orders-search">Search orders</label><input id="orders-search" name="search" type="search" maxlength="120" placeholder="Order number or customer name"></div>
    <div class="admin-field"><label for="orders-status">Status</label><select id="orders-status" name="status"><option value="">All statuses</option><?php foreach (['pending', 'confirmed', 'preparing', 'ready_for_delivery', 'out_for_delivery', 'delivered', 'cancelled', 'delivery_failed'] as $status): ?><option value="<?= esc($status, 'attr') ?>"><?= esc(ucwords(str_replace('_', ' ', $status))) ?></option><?php endforeach ?></select></div>
    <div class="admin-field"><label for="orders-from">From date (India)</label><input id="orders-from" name="from" type="date"></div>
    <div class="admin-field"><label for="orders-to">To date (India)</label><input id="orders-to" name="to" type="date"></div>
    <div class="admin-field"><label for="orders-sort">Sort by</label><select id="orders-sort" name="sort"><option value="created_at">Order date</option><option value="order_number">Order number</option><option value="total_amount">Total amount</option></select></div>
    <div class="admin-field"><label for="orders-dir">Direction</label><select id="orders-dir" name="dir"><option value="desc">Descending</option><option value="asc">Ascending</option></select></div>
    <div class="admin-field"><label for="orders-per-page">Orders per page</label><select id="orders-per-page" name="per_page"><option>20</option><option>50</option><option>100</option></select></div>
    <div class="orders-filter-actions"><button class="admin-button" type="submit">Apply filters</button><button class="admin-button admin-button-secondary" type="reset">Clear</button></div>
</form>
<p id="orders-updated" class="admin-caption" role="status">Loading orders…</p>
<div id="orders-error" class="admin-alert admin-alert-error" role="alert" hidden></div>
<div id="orders-loading"><?= view('admin/components/state', ['state' => 'loading', 'message' => 'Loading orders…']) ?></div>
<div id="orders-list" aria-busy="true"></div>
<nav class="orders-pagination" aria-label="Order pages"><button id="orders-prev" class="admin-button admin-button-secondary" type="button" disabled>Previous</button><span id="orders-page" role="status"></span><button id="orders-next" class="admin-button admin-button-secondary" type="button" disabled>Next</button></nav>
<dialog id="order-details" class="admin-dialog order-details" aria-labelledby="order-detail-title">
    <div class="orders-detail-heading"><h2 id="order-detail-title">Order details</h2><button id="order-close" class="admin-icon-button" type="button" aria-label="Close order details">×</button></div>
    <div id="order-detail-error" class="admin-alert admin-alert-error" role="alert" hidden></div>
    <p id="order-detail-stale" class="admin-alert" role="status" hidden>This order has changed. Reload details before applying an action.</p>
    <button id="order-detail-refresh" class="admin-button admin-button-secondary" type="button">Reload details</button>
    <div id="order-detail-content" aria-busy="false"></div>
    <form id="order-action-form" class="admin-card" hidden>
        <h3>Update order</h3>
        <div class="admin-field"><label for="order-action">Action</label><select id="order-action" required></select></div>
        <div class="admin-field"><label for="order-action-notes">Status note (optional)</label><textarea id="order-action-notes" rows="3" maxlength="1000" placeholder="Add a note for the order history"></textarea></div>
        <button id="order-action-submit" class="admin-button" type="submit">Apply action</button>
    </form>
</dialog>
<noscript><p class="admin-alert admin-alert-error">Enable JavaScript to manage orders.</p></noscript>
<script defer src="<?= esc(app_static_url('assets/js/admin-orders.js'), 'attr') ?>"></script>
<?= $this->endSection() ?>
