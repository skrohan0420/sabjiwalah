<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?= esc($title) ?> - Sabjiwalah Delivery</title>
    <?= view('shared/favicon') ?>
    <script src="<?= esc(app_static_url('assets/js/app-url.js'), 'attr') ?>" data-base-url="<?= esc(base_url(), 'attr') ?>"></script>
    <?= view('shared/theme', ['themePanel' => 'delivery']) ?>
    <link rel="stylesheet" href="<?= esc(app_static_url('assets/css/delivery.css'), 'attr') ?>">
</head>
<body class="delivery-body">
    <a class="delivery-skip" href="#delivery-main">Skip to content</a>
    <header class="delivery-header">
        <a class="delivery-brand" href="<?= esc(site_url('delivery'), 'attr') ?>">Sabjiwalah<span>Delivery partner</span></a>
        <div class="delivery-header-actions"><button id="delivery-notifications-open" class="delivery-button delivery-notifications-button" type="button" aria-label="Notifications" aria-haspopup="dialog"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/></svg><span id="delivery-notifications-badge" hidden></span></button>
        <button class="delivery-button delivery-appearance" type="button" data-theme-toggle aria-label="Switch appearance"><svg class="delivery-theme-moon" viewBox="0 0 24 24" aria-hidden="true"><path d="M20 14a8 8 0 0 1-10-10 8 8 0 1 0 10 10Z"/></svg><svg class="delivery-theme-sun" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M2 12h2M20 12h2M5 5l1.5 1.5M17.5 17.5 19 19M5 19l1.5-1.5M17.5 6.5 19 5"/></svg></button></div>
    </header>
    <div class="delivery-rider"><span class="delivery-rider-identity"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M5 21v-2a7 7 0 0 1 14 0v2"/></svg><strong id="delivery-rider-name"><?= esc($riderName) ?></strong></span><p class="delivery-availability"><span class="sr-only">Status</span><strong id="delivery-availability-value" data-status="<?= esc($availability, 'attr') ?>"><?= esc($availability === 'unavailable' ? 'Offline' : ucfirst($availability)) ?></strong><span id="delivery-availability-note" class="delivery-note sr-only"> · Updated when this page loads</span></p><button id="delivery-logout" class="delivery-button" type="button">Sign out</button></div>
    <p id="delivery-network" class="delivery-banner" role="status" hidden>You are offline. Reconnect before submitting delivery actions.</p>
    <p id="delivery-shell-message" class="delivery-banner delivery-error" role="alert" hidden></p>
    <p id="delivery-progress" class="delivery-banner" role="status" hidden></p>
    <main id="delivery-main" class="delivery-main" tabindex="-1">
        <?= $this->renderSection('content') ?>
    </main>
    <nav class="delivery-nav" aria-label="Delivery navigation">
        <?php foreach (['home' => ['Home', 'delivery'], 'deliveries' => ['Deliveries', 'delivery/deliveries'], 'history' => ['History', 'delivery/history'], 'profile' => ['Profile', 'delivery/profile']] as $key => [$label, $path]): ?>
            <a href="<?= esc(site_url($path), 'attr') ?>" aria-label="<?= esc($label, 'attr') ?>"<?= $section === $key ? ' aria-current="page"' : '' ?>><svg viewBox="0 0 24 24" aria-hidden="true"><?php if ($key === 'home'): ?><path d="m3 10 9-7 9 7v9a2 2 0 0 1-2 2h-4v-8H9v8H5a2 2 0 0 1-2-2v-9Z"/><?php elseif ($key === 'deliveries'): ?><path d="M3 6h11v11H3zM14 10h4l3 4v3h-7"/><circle cx="7" cy="18" r="2"/><circle cx="18" cy="18" r="2"/><?php elseif ($key === 'history'): ?><rect x="5" y="4" width="14" height="17" rx="2"/><rect x="9" y="2" width="6" height="4" rx="1"/><path d="m9 13 2 2 4-4"/><?php else: ?><circle cx="12" cy="8" r="4"/><path d="M5 21v-2a7 7 0 0 1 14 0v2"/><?php endif ?></svg><span><?= esc($label) ?></span></a>
        <?php endforeach ?>
    </nav>
    <div id="delivery-toast" class="delivery-toast" role="status" aria-live="polite" hidden></div>
    <dialog id="delivery-notifications" class="delivery-filter-sheet" aria-labelledby="delivery-notifications-title" data-rider="<?= esc((string) \App\Services\AuthService::id('delivery'), 'attr') ?>">
        <header><h2 id="delivery-notifications-title">Notifications</h2><button id="delivery-notifications-close" class="delivery-button" type="button" aria-label="Close notifications">×</button></header>
        <div id="delivery-notifications-list"></div>
        <p class="delivery-note" id="delivery-notifications-empty">You're all caught up. New assignment and action updates will appear here.</p>
        <button id="delivery-notifications-clear" class="delivery-button" type="button" hidden>Clear notifications</button>
    </dialog>
    <dialog id="delivery-confirm" class="delivery-dialog" aria-labelledby="delivery-confirm-title" aria-describedby="delivery-confirm-description">
        <h2 id="delivery-confirm-title">Confirm action</h2>
        <p id="delivery-confirm-description"></p>
        <div class="delivery-dialog-actions">
            <button id="delivery-confirm-cancel" class="delivery-button" type="button">Cancel</button>
            <button id="delivery-confirm-accept" class="delivery-button delivery-primary" type="button">Confirm</button>
        </div>
    </dialog>
    <script defer src="<?= esc(app_static_url('assets/js/delivery-notifications.js'), 'attr') ?>"></script>
    <script defer src="<?= esc(app_static_url('assets/js/delivery-shell.js'), 'attr') ?>"></script>
    <script defer src="<?= esc(app_static_url('assets/js/delivery-presence.js'), 'attr') ?>"></script>
    <?php if ($section === 'deliveries'): ?>
        <?php if (! empty($detailView)): ?><script defer src="<?= esc(app_static_url('assets/js/delivery-map.js'), 'attr') ?>"></script><script defer src="<?= esc(app_static_url('assets/js/delivery-tracking.js'), 'attr') ?>"></script><?php endif ?>
        <script defer src="<?= esc(app_static_url('assets/js/delivery-completion.js'), 'attr') ?>"></script>
    <?php endif ?>
    <?php if ($section === 'home'): ?>
        <script defer src="<?= esc(app_static_url('assets/js/delivery-dashboard.js'), 'attr') ?>"></script>
    <?php endif ?>
    <?php if (in_array($section, ['history','profile'], true)): ?><script defer src="<?= esc(app_static_url('assets/js/delivery-history.js'), 'attr') ?>"></script><?php endif ?>
    <script defer src="<?= esc(app_static_url('assets/js/delivery-icons.js'), 'attr') ?>"></script>
</body>
</html>
