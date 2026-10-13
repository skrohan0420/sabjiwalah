<?= $this->extend('delivery/layout') ?>
<?= $this->section('content') ?>
<section class="delivery-heading"><h1>Your day</h1><button id="rider-dashboard-refresh" class="delivery-button delivery-refresh-action" type="button">Refresh</button></section>
<p id="rider-dashboard-updated" class="delivery-note" role="status">Loading your dashboard…</p>
<p id="rider-dashboard-error" class="delivery-banner delivery-error" role="alert" hidden></p>
<div id="rider-dashboard-content" aria-busy="true" hidden>
    <dl class="delivery-stats delivery-home-stats">
        <div data-profile-icon="box"><dt>Active deliveries</dt><dd id="rider-active-count">—</dd></div>
        <div data-profile-icon="handover"><dt>Pickups</dt><dd id="rider-pickup-count">—</dd></div>
        <div data-profile-icon="shield"><dt>Completed today</dt><dd id="rider-completed-count">—</dd></div>
        <div data-profile-icon="cash"><dt>Cash today</dt><dd id="rider-cod-total">—</dd></div>
    </dl>

    <section class="delivery-panel delivery-availability-card" aria-labelledby="rider-availability-heading">
        <h2 id="rider-availability-heading"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v3M12 18v3M3 12h3M18 12h3"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/></svg>Availability</h2>
        <p id="rider-availability-help" class="delivery-note">Loading availability…</p>
        <form id="rider-availability-form">
            <label class="sr-only" for="rider-availability-choice">Set availability</label>
            <select id="rider-availability-choice" hidden disabled><option value="available">Available</option><option value="offline">Offline</option></select>
            <div id="rider-availability-toggle" class="delivery-availability-toggle" role="group" aria-label="Set availability">
                <button type="button" data-availability="available" aria-pressed="false" disabled><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12 4 4L19 6"/></svg><span>Available</span></button>
                <button type="button" data-availability="offline" aria-pressed="false" disabled><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v9M6 5a9 9 0 1 0 12 0"/></svg><span>Offline</span></button>
            </div>
            <button id="rider-availability-save" type="submit" hidden disabled>Update availability</button>
        </form>
        <p id="rider-availability-error" class="delivery-banner delivery-error" role="alert" hidden></p>
        <p id="rider-presence-message" class="delivery-note" data-state="paused" role="status">GPS paused</p>
    </section>
    <section class="delivery-panel delivery-current-card" aria-labelledby="rider-active-heading"><div class="delivery-current-heading"><h2 id="rider-active-heading">Current delivery</h2><a class="delivery-button" href="<?= esc(site_url('delivery/deliveries'), 'attr') ?>">Open my deliveries</a></div><div id="rider-active-order"></div></section>
    <section class="delivery-activity-card" aria-labelledby="rider-assignment-heading"><h2 id="rider-assignment-heading">Recent activity</h2><div id="rider-recent-assignments"></div></section>
</div>
<noscript><p class="delivery-banner delivery-error">Enable JavaScript to load your dashboard.</p><a class="delivery-button" href="<?= esc(site_url('delivery/deliveries'), 'attr') ?>">Open my deliveries</a></noscript>
<?= $this->endSection() ?>
