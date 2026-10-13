<?= $this->extend('delivery/layout') ?>
<?= $this->section('content') ?>
    <section class="delivery-heading"><div><h1>My deliveries</h1><p id="delivery-count">Assigned orders: <?= esc($assignmentCount) ?></p></div><button class="delivery-button delivery-refresh-action" id="delivery-refresh" type="button">Refresh</button></section>
    <p class="delivery-intro">Record pickup first. Collect the customer's PIN after handover and confirm cash for COD orders.</p>
    <p id="delivery-message" role="alert" class="delivery-banner delivery-error" hidden></p><div id="delivery-list" aria-busy="false"></div>
    <noscript><p class="delivery-banner delivery-error">Enable JavaScript to load your deliveries and submit delivery actions.</p></noscript>
<?= $this->endSection() ?>
