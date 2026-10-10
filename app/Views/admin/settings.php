<?= $this->extend('admin/layout') ?>
<?= $this->section('content') ?>
<section id="settings-page">
<header class="admin-heading"><div><p class="admin-eyebrow">OPERATIONS</p><h1>Settings</h1><p class="admin-muted">Control new orders and checkout pricing.</p></div><button id="settings-refresh" type="button" class="admin-button admin-button-secondary">Refresh</button></header>
<p id="settings-error" class="admin-alert admin-alert-error" role="alert" hidden></p>
<form id="settings-form" class="admin-card">
<fieldset id="settings-fields" disabled style="border:0;padding:0;margin:0;min-width:0">
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
<script defer src="<?= esc(app_static_url('assets/js/admin-settings.js'), 'attr') ?>"></script>
<?= $this->endSection() ?>
