<!DOCTYPE html>
<html lang="en">
<head>
    <?= view('shared/favicon') ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery Dashboard - Sabjiwalah</title>
    <link rel="stylesheet" href="<?= esc(app_static_url('assets/css/admin.css'),'attr') ?>">
    <script src="<?= esc(app_static_url('assets/js/app-url.js'),'attr') ?>" data-base-url="<?= esc(base_url(),'attr') ?>"></script>
    <?= view('shared/theme') ?>
</head>
<body class="admin-body">
    <main class="admin-main">
    <section class="admin-heading"><div><h1>My deliveries</h1><p id="delivery-count">Assigned orders: <?= esc($assignmentCount) ?></p></div><button class="admin-button admin-button-secondary" id="delivery-refresh">Refresh orders</button></section>
    <p>Record pickup, then ask the customer for their PIN after handing over the groceries. Confirm the full cash amount for COD orders.</p>
    <p id="delivery-message" role="status" class="admin-alert" hidden></p><div id="delivery-list"></div><button id="delivery-logout" class="admin-button admin-button-secondary">Sign out</button></main>
    <script defer src="<?= esc(app_static_url('assets/js/delivery-completion.js'),'attr') ?>"></script>
</body>
</html>
