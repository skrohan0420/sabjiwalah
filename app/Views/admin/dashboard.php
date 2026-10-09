<?= $this->extend('admin/layout') ?>
<?= $this->section('content') ?>
<section class="admin-heading"><div><p class="admin-eyebrow">SABJIWALAH OPERATIONS</p><h1>Dashboard</h1><p class="admin-muted">Your store at a glance. Today follows India time.</p></div><button class="admin-button admin-button-secondary" id="dashboard-refresh" type="button">Refresh</button></section>
<p class="admin-caption" id="dashboard-updated" role="status">Connecting to your store…</p>
<div id="dashboard-error" class="admin-alert admin-alert-error" role="alert" hidden></div>
<div id="dashboard-loading"><?= view('admin/components/state', ['state' => 'loading', 'message' => 'Loading store activity…']) ?></div>
<div id="dashboard-content" aria-busy="true">
<section class="admin-stats" aria-label="Business overview">
<?php foreach (['orders_today' => 'Orders today', 'sales_today' => "Today's sales", 'total_orders' => 'Total orders', 'total_customers' => 'Total customers', 'active_products' => 'Active products', 'low_stock_products' => 'Low-stock products'] as $key => $label): ?>
<article class="admin-card"><p class="admin-muted"><?= esc($label) ?></p><strong class="admin-stat" data-dashboard-stat="<?= esc($key, 'attr') ?>">—</strong><?php if ($key === 'sales_today'): ?><p class="admin-caption">Paid, delivered orders placed today</p><?php elseif ($key === 'low_stock_products'): ?><p class="admin-caption">Active products with ≤5 units, including out of stock</p><?php endif ?></article>
<?php endforeach ?>
</section>
<section class="admin-card dashboard-status-section" aria-labelledby="dashboard-status-title"><h2 id="dashboard-status-title">Order status</h2><div class="dashboard-status-grid">
<?php foreach (['pending' => 'Pending', 'confirmed' => 'Confirmed', 'preparing' => 'Preparing', 'ready_for_delivery' => 'Ready for delivery', 'out_for_delivery' => 'Out for delivery', 'delivered' => 'Completed', 'cancelled' => 'Cancelled', 'delivery_failed' => 'Delivery failed'] as $key => $label): ?>
<div><span class="admin-muted"><?= esc($label) ?></span><strong data-dashboard-stat="<?= esc($key, 'attr') ?>">—</strong></div>
<?php endforeach ?>
</div><p class="admin-caption">All-time counts by current order status.</p></section>
<section class="dashboard-lists">
<article class="admin-card"><div class="dashboard-section-heading"><h2>Needs attention</h2><span class="admin-badge admin-badge-warning" data-dashboard-stat="attention_count">—</span></div><p class="admin-caption">Pending confirmation, ready for dispatch, or failed delivery. Showing up to 8 oldest orders.</p><div id="dashboard-attention"></div></article>
<article class="admin-card"><h2>Recent orders</h2><p class="admin-caption">The 8 most recently placed orders.</p><div id="dashboard-recent"></div></article>
</section>
</div>
<noscript><p class="admin-alert admin-alert-error">Enable JavaScript to load current dashboard statistics.</p></noscript>
<script defer src="<?= esc(app_static_url('assets/js/admin-dashboard.js'), 'attr') ?>"></script>
<?= $this->endSection() ?>
