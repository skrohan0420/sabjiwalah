<?= $this->extend('admin/layout') ?>
<?= $this->section('content') ?>
<section id="settings-page">
<header class="admin-heading"><div><p class="admin-eyebrow">OPERATIONS</p><h1>Settings</h1><p class="admin-muted">Control new orders and checkout pricing.</p></div><button id="settings-refresh" type="button" class="admin-button admin-button-secondary">Refresh</button></header>
<p id="settings-error" class="admin-alert admin-alert-error" role="alert" hidden></p>
<form id="settings-form" class="admin-card">
<fieldset id="settings-fields" disabled style="border:0;padding:0;margin:0;min-width:0">
<details id="settings-pickup-picker" class="settings-pickup-picker" data-map-key="<?= esc(config('Maps')->googleApiKey, 'attr') ?>" data-map-id="<?= esc(config('Maps')->googleMapId, 'attr') ?>" aria-labelledby="settings-pickup-title">
    <summary><span id="settings-pickup-title">Shop pickup location</span><svg aria-hidden="true" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg></summary>
    <input type="hidden" name="pickup_latitude"><input type="hidden" name="pickup_longitude">
    <div class="settings-pickup-search"><label class="sr-only" for="settings-pickup-search">Search pickup address</label><input id="settings-pickup-search" type="search" placeholder="Search your shop address or landmark" maxlength="250"><button id="settings-pickup-find" class="admin-button admin-button-secondary" type="button">Search</button></div>
    <div class="settings-pickup-map-wrap"><div id="settings-pickup-map" class="settings-pickup-map" aria-label="Move the map to position your shop beneath the center pin"></div><span class="settings-pickup-center-pin" aria-hidden="true"><svg viewBox="0 0 32 42"><path d="M16 40S2 24 2 16a14 14 0 0 1 28 0c0 8-14 24-14 24Z"/><circle cx="16" cy="16" r="5"/></svg></span><button id="settings-pickup-gps" class="admin-button admin-button-secondary" type="button">Use GPS</button></div>
    <p id="settings-pickup-description" class="admin-caption" role="status">Loading pickup map…</p>
    <div class="settings-pickup-actions"><button id="settings-pickup-use" class="admin-button admin-button-secondary" type="button" disabled>Use this point</button><button id="settings-pickup-clear" class="admin-button admin-button-secondary" type="button">Clear pickup</button></div>
    <p class="admin-caption">Move the map under the pin or use GPS, then choose this point. Save settings to apply it.</p>
</details>
<div class="campaign-grid">
<div class="admin-field"><label for="shop-open">Shop status</label><select id="shop-open" name="shop_open"><option value="1">Open</option><option value="0">Closed for new orders</option></select></div>
<div class="admin-field"><label for="orders-paused">Order acceptance</label><select id="orders-paused" name="orders_paused"><option value="0">Accept new orders</option><option value="1">Temporarily pause new orders</option></select></div>
<div class="admin-field"><label for="delivery-charge">Delivery charge (₹)</label><input id="delivery-charge" name="delivery_charge" type="number" min="0" max="999999.99" step="0.01" required></div>
<div class="admin-field"><label for="free-minimum">Free delivery from subtotal (₹)</label><input id="free-minimum" name="free_delivery_minimum" type="number" min="0" max="999999.99" step="0.01"><small>Leave blank to disable free delivery. Uses subtotal before coupon discounts.</small></div>
<div class="admin-field"><label for="order-minimum">Minimum order subtotal (₹)</label><input id="order-minimum" name="minimum_order_amount" type="number" min="0" max="999999.99" step="0.01" required><small>Uses subtotal before coupon discounts.</small></div>
<div class="admin-field"><label for="order-capacity">Maximum active orders</label><input id="order-capacity" name="maximum_active_orders" type="number" min="1" max="100000" step="1"><small>Leave blank for unlimited. Counts pending through out for delivery. Completed, cancelled and failed deliveries do not count.</small></div>
<div class="admin-field"><label for="opens-at">Daily ordering opens</label><input id="opens-at" name="opens_at" type="time"></div>
<div class="admin-field"><label for="closes-at">Daily ordering closes</label><input id="closes-at" name="closes_at" type="time"></div>
</div>
<p class="admin-caption">Hours use India time (Asia/Kolkata). Leave both blank for all-day ordering. Overnight hours are supported; the closing time is exclusive. These controls govern new order acceptance; existing orders retain their prices and can still be fulfilled.</p>
<div class="campaign-actions"><button id="settings-save" class="admin-button">Save settings</button><span id="settings-version" class="admin-muted" role="status"></span></div>
</fieldset></form>
<section class="admin-card" style="margin-top:24px"><h2>Recent changes</h2><p class="admin-muted">The latest ten saved changes, with administrator and time.</p><div id="settings-history" aria-live="polite"></div></section>
</section>
<script defer src="<?= esc(app_static_url('assets/js/admin-pickup-map.js'), 'attr') ?>"></script>
<script defer src="<?= esc(app_static_url('assets/js/admin-settings-pickup.js'), 'attr') ?>"></script>
<script defer src="<?= esc(app_static_url('assets/js/admin-settings.js'), 'attr') ?>"></script>
<?= $this->endSection() ?>
