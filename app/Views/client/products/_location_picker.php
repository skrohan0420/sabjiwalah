<?php $maps = config('Maps'); ?>
<div class="location-sheet" id="location-sheet" data-location-sheet hidden>
    <section class="location-panel" role="dialog" aria-modal="true" aria-labelledby="location-title">
        <div class="location-heading">
            <div><h2 id="location-title">Delivery location</h2>
            <p class="location-instruction">Move the map to your doorstep</p></div>
            <button class="location-close" type="button" data-location-close aria-label="Close location selector"></button>
        </div>
        <form class="location-search" data-location-search-form>
            <span aria-hidden="true"></span>
            <label class="sr-only" for="location-search-input">Search delivery location</label>
            <input id="location-search-input" type="search" autocomplete="off" placeholder="Search street, building or landmark" data-location-search>
            <button type="submit">Search</button>
        </form>
        <div class="location-map-wrap">
            <div class="location-map" data-location-map data-google-api-key="<?= esc($maps->googleApiKey, 'attr') ?>" data-google-map-id="<?= esc($maps->googleMapId, 'attr') ?>" aria-label="Delivery map. Move the map to place your doorstep under the center pin."></div>
            <div class="location-results" data-location-results hidden></div>
            <span class="location-center-pin" aria-hidden="true"><svg viewBox="0 0 32 42"><path d="M16 40S2 24 2 16a14 14 0 0 1 28 0c0 8-14 24-14 24Z"/><circle cx="16" cy="16" r="5"/></svg></span>
            <span class="location-pin-shadow" aria-hidden="true"></span>
            <button class="location-gps" type="button" data-use-current-location disabled>Use GPS</button>
        </div>
        <div class="location-selection">
            <span class="location-selection-label">DELIVER HERE</span>
            <strong data-location-selected>Choose a point on the map</strong>
            <p data-location-status role="status">Move the map to choose your location.</p>
            <label class="location-address-label"><span class="sr-only">Address / landmark</span>
                <input type="text" maxlength="250" placeholder="House / landmark (optional)" data-location-address>
            </label>
            <button class="location-confirm" type="button" data-location-confirm disabled>Confirm location</button>
        </div>
    </section>
</div>
