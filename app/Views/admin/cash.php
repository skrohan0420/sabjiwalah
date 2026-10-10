<?= $this->extend('admin/layout') ?>
<?= $this->section('content') ?>
<section class="admin-heading"><div><p class="admin-eyebrow">CASH ON DELIVERY</p><h1>COD reconciliation</h1><p class="admin-muted">Confirm cash handed over by delivery personnel. Amounts come from verified order totals.</p></div><button id="cash-refresh" class="admin-button admin-button-secondary">Refresh</button></section>
<p id="cash-totals" role="status"></p>
<form id="cash-filters" class="admin-card products-filters"><div class="admin-field"><label for="cash-search">Order or rider</label><input id="cash-search" name="search" maxlength="120" type="search"></div><div class="admin-field"><label for="cash-status">Reconciliation</label><select id="cash-status" name="status"><option value="pending">Pending handover</option><option value="reconciled">Reconciled</option><option value="">All collections</option></select></div><button class="admin-button">Apply filters</button></form>
<p class="admin-caption">Totals include all recorded collections. Legacy delivered orders without collection records are excluded.</p><p id="cash-error" class="admin-alert admin-alert-error" role="alert" hidden></p><div id="cash-list"></div><nav class="orders-pagination" aria-label="Cash record pages"><button id="cash-prev" class="admin-button admin-button-secondary">Previous</button><span id="cash-page"></span><button id="cash-next" class="admin-button admin-button-secondary">Next</button></nav>
<script defer src="<?= esc(app_static_url('assets/js/admin-cash.js'),'attr') ?>"></script>
<?= $this->endSection() ?>
