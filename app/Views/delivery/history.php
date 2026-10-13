<?= $this->extend('delivery/layout') ?>
<?= $this->section('content') ?>
<details class="delivery-help"><summary>About your delivery history</summary><p class="delivery-note">Completed and failed orders assigned to you. Reassigned records show the order's final outcome and do not count as your deliveries. Date filters use recorded delivery dates in India; records without a date appear when date filters are cleared.</p></details>
<section class="delivery-heading"><h1>Delivery history</h1><div class="delivery-history-heading-actions"><button id="rider-history-refresh" class="delivery-button delivery-refresh-action" type="button">Refresh</button><button id="rider-filters-open" class="delivery-button delivery-icon-only" type="button" aria-label="Filter deliveries" title="Filter deliveries" aria-haspopup="dialog"><svg class="delivery-action-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16M7 12h10M10 18h4"/></svg><span id="rider-filters-count" hidden></span></button></div></section>
<dialog id="rider-filters-dialog" class="delivery-filter-sheet" aria-labelledby="rider-filters-title">
<header><h2 id="rider-filters-title">Filter deliveries</h2><button id="rider-filters-close" class="delivery-button" type="button" aria-label="Close filters">×</button></header>
<form id="rider-history-filters" class="delivery-history-filters">
    <label class="delivery-field">Order number<input name="search" maxlength="120" type="search" placeholder="Search an order" autocomplete="off"></label>
    <fieldset class="delivery-status-options"><legend>Delivery status</legend><div>
        <label><input type="radio" name="status" value="" checked><span>All</span></label>
        <label><input type="radio" name="status" value="delivered"><span>Delivered</span></label>
        <label><input type="radio" name="status" value="delivery_failed"><span>Failed</span></label>
    </div></fieldset>
    <fieldset class="delivery-date-options"><legend>Date range <small>India time</small></legend><div><label class="delivery-field">From<input name="from" type="date"></label><label class="delivery-field">To<input name="to" type="date"></label></div></fieldset>
    <div class="delivery-filter-actions"><button class="delivery-button" type="reset">Clear filters</button><button class="delivery-button delivery-primary" type="submit">Apply filters</button></div>
</form>
</dialog>
<p id="rider-history-message" role="status" class="delivery-note">Loading delivery history…</p>
<div id="rider-history-list" aria-busy="true"></div>
<nav class="delivery-history-pages" aria-label="History pages"><button id="rider-history-prev" class="delivery-button" type="button" disabled>Previous</button><span id="rider-history-page"></span><button id="rider-history-next" class="delivery-button" type="button" disabled>Next</button></nav>
<?= $this->endSection() ?>
