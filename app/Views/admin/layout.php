<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= esc($pageTitle ?? 'Dashboard') ?> · Sabjiwalah Admin</title>
<?= view('shared/favicon') ?>
<?= view('shared/theme', ['themePanel' => 'admin']) ?>
<link rel="stylesheet" href="<?= esc(app_static_url('assets/css/admin.css'), 'attr') ?>">
<link rel="stylesheet" href="<?= esc(app_static_url('assets/css/admin-polish.css'), 'attr') ?>">
<script src="<?= esc(app_static_url('assets/js/app-url.js'), 'attr') ?>" data-base-url="<?= esc(base_url(), 'attr') ?>"></script>
<script defer src="<?= esc(app_static_url('assets/js/admin.js'), 'attr') ?>" data-login-url="<?= esc(site_url('admin/login'), 'attr') ?>"></script>
</head>
<body class="admin-body">
<a class="admin-skip" href="#admin-main">Skip to content</a>
<div class="admin-shell">
<aside class="admin-sidebar" id="admin-sidebar" aria-label="Admin navigation">
<div class="admin-brand-row"><a href="<?= esc(site_url('admin'), 'attr') ?>" class="admin-brand"><img src="<?= esc(app_static_url('assets/images/sabjiwalah-wordmark-header.png'), 'attr') ?>" alt="Sabjiwalah"><span>ADMIN PANEL</span></a><button class="admin-icon-button admin-mobile-only" type="button" data-admin-close aria-label="Close navigation">×</button></div>
<p class="admin-nav-label">WORKSPACE</p>
<nav aria-label="Main navigation">
<?php foreach (['dashboard' => ['Dashboard', 'admin'], 'orders' => ['Operations', 'admin/operations'], 'products' => ['Products', 'admin/products'], 'customers' => ['Customers', 'admin/customers'], 'delivery' => ['Delivery Management', 'admin/delivery-personnel']] as $key => [$label, $path]): ?>
<a class="admin-nav-item <?= ($activeNav ?? 'dashboard') === $key ? 'is-active' : '' ?>" href="<?= esc(site_url($path), 'attr') ?>" <?= ($activeNav ?? 'dashboard') === $key ? 'aria-current="page"' : '' ?>><?= esc($label) ?></a>
<?php endforeach ?>
<a class="admin-nav-item <?= ($activeNav ?? '') === 'live-map' ? 'is-active' : '' ?>" href="<?= esc(site_url('admin/live-map'), 'attr') ?>" <?= ($activeNav ?? '') === 'live-map' ? 'aria-current="page"' : '' ?>>Live map</a>
<a class="admin-nav-item <?= ($activeNav ?? '') === 'cash' ? 'is-active' : '' ?>" href="<?= esc(site_url('admin/cash'), 'attr') ?>">COD reconciliation</a>
<?php foreach (['offers' => 'Offers', 'promotions' => 'Promotions'] as $key => $label): ?>
<a class="admin-nav-item <?= ($activeNav ?? '') === $key ? 'is-active' : '' ?>" href="<?= esc(site_url('admin/' . $key), 'attr') ?>" <?= ($activeNav ?? '') === $key ? 'aria-current="page"' : '' ?>><?= esc($label) ?></a>
<?php endforeach ?>
<a class="admin-nav-item <?= ($activeNav ?? '') === 'settings' ? 'is-active' : '' ?>" href="<?= esc(site_url('admin/settings'),'attr') ?>" <?= ($activeNav ?? '') === 'settings' ? 'aria-current="page"' : '' ?>>Settings</a>
</nav>
<div class="admin-sidebar-footer"><span class="admin-badge admin-badge-success">Sabjiwalah</span><p class="admin-caption">Store administration</p></div>
</aside>
<button class="admin-backdrop" type="button" data-admin-close aria-label="Close navigation" hidden></button>
<div class="admin-workspace">
<header class="admin-topbar"><div class="admin-topbar-title"><button class="admin-icon-button admin-mobile-only" type="button" data-admin-menu aria-controls="admin-sidebar" aria-expanded="false" aria-label="Open navigation">☰</button><span>Store administration</span></div>
<div class="admin-topbar-actions"><button class="admin-button admin-button-secondary admin-appearance-control" type="button" data-theme-toggle aria-pressed="false" aria-label="Switch appearance"><svg class="admin-theme-moon" viewBox="0 0 24 24" aria-hidden="true"><path d="M20 14a8 8 0 0 1-10-10 8 8 0 1 0 10 10Z"/></svg><svg class="admin-theme-sun" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M2 12h2M20 12h2M5 5l1.5 1.5M17.5 17.5 19 19M5 19l1.5-1.5M17.5 6.5 19 5"/></svg></button><details class="admin-account"><summary aria-label="Administrator account menu"><svg class="admin-account-icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M5 21v-2a7 7 0 0 1 14 0v2"/></svg><span class="admin-account-name"><?= esc((\App\Services\AuthService::identity('admin')['name'] ?? null) ?: 'Administrator') ?></span><svg class="admin-account-chevron" viewBox="0 0 24 24" aria-hidden="true"><path d="m7 10 5 5 5-5"/></svg></summary><div class="admin-account-menu"><p class="admin-caption">Signed in as admin</p><a href="<?= esc(site_url(), 'attr') ?>">View storefront</a><button type="button" data-admin-logout>Sign out</button></div></details></div>
</header>
<main id="admin-main" class="admin-main" tabindex="-1">
<div id="admin-feedback" class="admin-alert" role="status" aria-live="polite" hidden></div>
<?php if (session()->getFlashdata('error')): ?><p class="admin-alert admin-alert-error" role="alert"><?= esc(session()->getFlashdata('error')) ?></p><?php endif ?>
<?= $this->renderSection('content') ?>
</main>
<footer class="admin-footer">Sabjiwalah · Admin workspace</footer>
</div></div>
<dialog class="admin-dialog" id="admin-confirm" aria-labelledby="admin-confirm-title" aria-describedby="admin-confirm-message"><form method="dialog"><h2 id="admin-confirm-title">Confirm action</h2><p id="admin-confirm-message" class="admin-muted"></p><div class="admin-dialog-actions"><button class="admin-button admin-button-secondary" value="cancel">Cancel</button><button class="admin-button" value="confirm" data-confirm-action>Confirm</button></div></form></dialog>
<script defer src="<?= esc(app_static_url('assets/js/admin-polish.js'), 'attr') ?>"></script>
</body></html>
