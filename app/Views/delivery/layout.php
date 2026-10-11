<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?= esc($title) ?> - Sabjiwalah Delivery</title>
    <?= view('shared/favicon') ?>
    <script src="<?= esc(app_static_url('assets/js/app-url.js'), 'attr') ?>" data-base-url="<?= esc(base_url(), 'attr') ?>"></script>
    <?= view('shared/theme') ?>
    <link rel="stylesheet" href="<?= esc(app_static_url('assets/css/delivery.css'), 'attr') ?>">
</head>
<body class="delivery-body">
    <a class="delivery-skip" href="#delivery-main">Skip to content</a>
    <header class="delivery-header">
        <a class="delivery-brand" href="<?= esc(site_url('delivery'), 'attr') ?>">Sabjiwalah<span>Delivery partner</span></a>
        <button class="delivery-button delivery-appearance" type="button" data-theme-toggle>Appearance</button>
    </header>
    <div class="delivery-rider"><span>Signed in as <strong id="delivery-rider-name"><?= esc($riderName) ?></strong></span><button id="delivery-logout" class="delivery-button" type="button">Sign out</button></div>
    <p class="delivery-availability">Availability: <strong id="delivery-availability-value"><?= esc(ucfirst($availability)) ?></strong><span id="delivery-availability-note" class="delivery-note"> · Updated when this page loads</span></p>
    <p id="delivery-network" class="delivery-banner" role="status" hidden>You are offline. Reconnect before submitting delivery actions.</p>
    <p id="delivery-shell-message" class="delivery-banner delivery-error" role="alert" hidden></p>
    <p id="delivery-progress" class="delivery-banner" role="status" hidden></p>
    <main id="delivery-main" class="delivery-main" tabindex="-1">
        <?= $this->renderSection('content') ?>
    </main>
    <nav class="delivery-nav" aria-label="Delivery navigation">
        <?php foreach (['home' => ['Home', 'delivery'], 'deliveries' => ['Deliveries', 'delivery/deliveries'], 'history' => ['History', 'delivery/history'], 'profile' => ['Profile', 'delivery/profile']] as $key => [$label, $path]): ?>
            <a href="<?= esc(site_url($path), 'attr') ?>"<?= $section === $key ? ' aria-current="page"' : '' ?>><?= esc($label) ?></a>
        <?php endforeach ?>
    </nav>
    <div id="delivery-toast" class="delivery-toast" role="status" aria-live="polite" hidden></div>
    <dialog id="delivery-confirm" class="delivery-dialog" aria-labelledby="delivery-confirm-title" aria-describedby="delivery-confirm-description">
        <h2 id="delivery-confirm-title">Confirm action</h2>
        <p id="delivery-confirm-description"></p>
        <div class="delivery-dialog-actions">
            <button id="delivery-confirm-cancel" class="delivery-button" type="button">Cancel</button>
            <button id="delivery-confirm-accept" class="delivery-button delivery-primary" type="button">Confirm</button>
        </div>
    </dialog>
    <script defer src="<?= esc(app_static_url('assets/js/delivery-shell.js'), 'attr') ?>"></script>
    <?php if ($section === 'deliveries'): ?>
        <?php if (! empty($detailView)): ?><script defer src="<?= esc(app_static_url('assets/js/delivery-map.js'), 'attr') ?>"></script><script defer src="<?= esc(app_static_url('assets/js/delivery-tracking.js'), 'attr') ?>"></script><?php endif ?>
        <script defer src="<?= esc(app_static_url('assets/js/delivery-completion.js'), 'attr') ?>"></script>
    <?php endif ?>
    <?php if ($section === 'home'): ?>
        <script defer src="<?= esc(app_static_url('assets/js/delivery-dashboard.js'), 'attr') ?>"></script>
    <?php endif ?>
    <?php if (in_array($section, ['history','profile'], true)): ?><script defer src="<?= esc(app_static_url('assets/js/delivery-history.js'), 'attr') ?>"></script><?php endif ?>
</body>
</html>
