<?= $this->extend('delivery/layout') ?>
<?= $this->section('content') ?>
<a class="delivery-button" href="<?= esc(site_url('delivery/deliveries'), 'attr') ?>">Back to deliveries</a>
<section class="delivery-heading"><div><h1>Delivery details</h1><p id="delivery-count">Loading assigned order…</p></div><button class="delivery-button" id="delivery-refresh" type="button">Refresh order</button></section>
<p id="delivery-message" role="alert" class="delivery-banner delivery-error" hidden></p>
<?php $maps = config('Maps'); ?>
<section id="delivery-map-panel" class="delivery-panel" aria-labelledby="delivery-map-heading" hidden>
    <h2 id="delivery-map-heading">Customer location</h2>
    <p id="delivery-map-address" class="delivery-address"></p>
    <div class="delivery-order-links"><a id="delivery-map-navigate" class="delivery-button delivery-primary" target="_blank" rel="noopener noreferrer" referrerpolicy="no-referrer" hidden>Navigate to Customer</a><a id="delivery-map-browser" class="delivery-button" target="_blank" rel="noopener noreferrer" referrerpolicy="no-referrer" hidden>Open directions in browser</a></div>
    <p id="delivery-map-status" class="delivery-note" role="status">Loading customer location…</p>
    <div id="delivery-map" class="delivery-map" data-google-api-key="<?= esc($maps->googleApiKey, 'attr') ?>" data-google-map-id="<?= esc($maps->googleMapId, 'attr') ?>" aria-label="Customer delivery location map" hidden></div>
    <div class="delivery-order-links"><button id="delivery-map-gps" class="delivery-button" type="button" disabled>Show my location</button><button id="delivery-map-route" class="delivery-button" type="button" disabled>Show driving route</button><button id="delivery-map-clear" class="delivery-button" type="button" disabled>Clear my location</button></div>
    <p id="delivery-map-location" class="delivery-note" role="status">Your location is requested only when you choose Show my location.</p>
    <p id="delivery-map-route-status" class="delivery-note" role="status">Show driving route sends the two locations to Google Maps.</p>
</section>
<section id="delivery-tracking" class="delivery-panel" aria-labelledby="delivery-tracking-heading" hidden>
    <h2 id="delivery-tracking-heading">Share delivery location</h2>
    <p class="delivery-note">Enable tracking to send your GPS location to Sabjiwalah during this delivery. Updates are sent at most every 15 seconds, after movement or once a minute. Only the latest location is stored; it expires after 55 minutes.</p>
    <p class="delivery-note">Keep this page open and visible. Tracking stops when you leave, hide this page, sign out, or finish delivery. Reliable tracking while a phone is locked requires a native mobile app.</p>
    <div class="delivery-order-links"><button id="delivery-tracking-start" class="delivery-button" type="button" disabled>Enable tracking</button><button id="delivery-tracking-stop" class="delivery-button" type="button" disabled>Stop tracking</button></div>
    <p id="delivery-tracking-status" class="delivery-note" role="status">Tracking is off. Enable it after pickup to share your location.</p>
</section>
<div id="delivery-list" data-order-uid="<?= esc($orderUid, 'attr') ?>" aria-busy="true"></div>
<noscript><p class="delivery-banner delivery-error">Enable JavaScript to view this order and submit delivery actions.</p></noscript>
<?= $this->endSection() ?>
