<?= $this->extend('delivery/layout') ?>
<?= $this->section('content') ?>
<section class="delivery-heading"><h1>My profile</h1><button id="rider-profile-refresh" class="delivery-button delivery-refresh-action" type="button">Refresh</button></section>
<p id="rider-profile-message" role="status" class="delivery-note">Loading your profile…</p>
<div id="rider-profile-content" aria-busy="true"></div>
<details class="delivery-help"><summary>How daily totals work</summary><p class="delivery-note">Today means midnight to midnight in India. Cash totals use verified collection records; older deliveries without a cash record are excluded.</p></details>
<a class="delivery-button" href="<?= esc(site_url('delivery'), 'attr') ?>">Manage availability on Home</a>
<?= $this->endSection() ?>
