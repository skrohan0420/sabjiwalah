<?= $this->extend('delivery/layout') ?>
<?= $this->section('content') ?>
<section class="delivery-heading"><h1>Home</h1><button id="rider-dashboard-refresh" class="delivery-button" type="button">Refresh dashboard</button></section>
<p id="rider-dashboard-updated" class="delivery-note" role="status">Loading your dashboard…</p>
<p id="rider-dashboard-error" class="delivery-banner delivery-error" role="alert" hidden></p>
<div id="rider-dashboard-content" aria-busy="true" hidden>
    <section class="delivery-panel" aria-labelledby="rider-availability-heading">
        <h2 id="rider-availability-heading">Your availability</h2>
        <p id="rider-availability-help" class="delivery-note">Loading availability…</p>
        <form id="rider-availability-form">
            <label for="rider-availability-choice">Set availability</label>
            <select id="rider-availability-choice" disabled><option value="available">Available</option><option value="unavailable">Unavailable</option><option value="offline">Offline</option></select>
            <button id="rider-availability-save" class="delivery-button delivery-primary" type="submit" disabled>Update availability</button>
        </form>
        <p id="rider-availability-error" class="delivery-banner delivery-error" role="alert" hidden></p>
    </section>
    <dl class="delivery-stats">
        <div><dt>Active deliveries</dt><dd id="rider-active-count">—</dd></div>
        <div><dt>Pending pickups</dt><dd id="rider-pickup-count">—</dd></div>
        <div><dt>Completed today</dt><dd id="rider-completed-count">—</dd></div>
        <div><dt>COD collected today</dt><dd id="rider-cod-total">—</dd></div>
    </dl>
    <p class="delivery-note">Today's totals follow India time. COD reflects recorded cash collections.</p>
    <section class="delivery-panel" aria-labelledby="rider-active-heading"><h2 id="rider-active-heading">Current delivery</h2><div id="rider-active-order"></div><a class="delivery-button delivery-primary" href="<?= esc(site_url('delivery/deliveries'), 'attr') ?>">Open my deliveries</a></section>
    <section aria-labelledby="rider-assignment-heading"><h2 id="rider-assignment-heading">Latest assignment activity</h2><div id="rider-recent-assignments"></div></section>
</div>
<noscript><p class="delivery-banner delivery-error">Enable JavaScript to load your dashboard.</p><a class="delivery-button" href="<?= esc(site_url('delivery/deliveries'), 'attr') ?>">Open my deliveries</a></noscript>
<?= $this->endSection() ?>
