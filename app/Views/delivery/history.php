<?= $this->extend('delivery/layout') ?>
<?= $this->section('content') ?>
<h1>Delivery history</h1>
<p class="delivery-note">Completed and failed orders assigned to you. Reassigned records show the order's final outcome and do not count as your deliveries. Date filters use recorded delivery dates in India; records without a date appear when date filters are cleared.</p>
<form id="rider-history-filters" class="delivery-panel delivery-history-filters">
    <label class="delivery-field">Order number<input name="search" maxlength="120" type="search"></label>
    <label class="delivery-field">Status<select name="status"><option value="">Completed and failed</option><option value="delivered">Delivered</option><option value="delivery_failed">Failed delivery</option></select></label>
    <label class="delivery-field">From date (India)<input name="from" type="date"></label><label class="delivery-field">To date (India)<input name="to" type="date"></label>
    <button class="delivery-button delivery-primary" type="submit">Apply filters</button><button class="delivery-button" type="reset">Clear filters</button>
</form>
<p id="rider-history-message" role="status" class="delivery-note">Loading delivery history…</p>
<div id="rider-history-list" aria-busy="true"></div>
<nav class="delivery-history-pages" aria-label="History pages"><button id="rider-history-prev" class="delivery-button" type="button" disabled>Previous</button><span id="rider-history-page"></span><button id="rider-history-next" class="delivery-button" type="button" disabled>Next</button></nav>
<button id="rider-history-refresh" class="delivery-button" type="button">Refresh history</button>
<?= $this->endSection() ?>
