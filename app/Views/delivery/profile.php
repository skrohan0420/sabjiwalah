<?= $this->extend('delivery/layout') ?>
<?= $this->section('content') ?>
<h1>My profile</h1>
<p id="rider-profile-message" role="status" class="delivery-note">Loading your profile…</p>
<div id="rider-profile-content" aria-busy="true"></div>
<button id="rider-profile-refresh" class="delivery-button" type="button">Refresh profile</button>
<p class="delivery-note">Today means midnight to midnight in India. Cash totals use verified collection records; older deliveries without a cash record are excluded.</p>
<a class="delivery-button" href="<?= esc(site_url('delivery'), 'attr') ?>">Manage availability on Home</a>
<?= $this->endSection() ?>
