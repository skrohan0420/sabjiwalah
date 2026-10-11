<?= $this->extend('delivery/layout') ?>
<?= $this->section('content') ?>
<h1>Delivery history</h1>
<section class="delivery-panel"><h2>History is coming soon</h2><p>Your completed and failed deliveries and collection summaries will appear here.</p><a class="delivery-button" href="<?= esc(site_url('delivery/deliveries'), 'attr') ?>">View active deliveries</a></section>
<?= $this->endSection() ?>
