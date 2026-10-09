<?= $this->extend('admin/layout') ?>
<?= $this->section('content') ?>
<section class="admin-heading"><div><p class="admin-eyebrow">SABJIWALAH CATALOGUE</p><h1>Products</h1><p class="admin-muted">Keep prices, availability and stock up to date.</p></div><button class="admin-button" id="products-create" type="button">Add product</button></section>
<form id="products-filters" class="admin-card products-filters">
<div class="admin-field"><label for="products-search">Search products</label><input id="products-search" name="search" type="search" maxlength="120" placeholder="Product name"></div>
<div class="admin-field"><label for="products-active">Availability</label><select id="products-active" name="active"><option value="">All products</option><option value="1">Active</option><option value="0">Inactive / archived</option></select></div>
<div class="admin-field"><label for="products-sort">Sort by</label><select id="products-sort" name="sort"><option value="created_at">Newest</option><option value="name">Name</option><option value="price">Price</option><option value="stock_quantity">Stock</option></select></div>
<div class="admin-field"><label for="products-dir">Direction</label><select id="products-dir" name="dir"><option value="desc">Descending</option><option value="asc">Ascending</option></select></div>
<button class="admin-button" type="submit">Apply filters</button><button class="admin-button admin-button-secondary" id="products-refresh" type="button">Refresh</button>
</form>
<p id="products-summary" class="admin-caption" role="status">Loading products…</p>
<div id="products-error" class="admin-alert admin-alert-error" role="alert" hidden></div>
<div id="products-list" aria-busy="true"></div>
<nav class="orders-pagination" aria-label="Product pages"><button class="admin-button admin-button-secondary" id="products-prev" type="button" disabled>Previous</button><span id="products-page"></span><button class="admin-button admin-button-secondary" id="products-next" type="button" disabled>Next</button></nav>
<dialog id="product-editor" class="admin-dialog product-editor" aria-labelledby="product-title">
<div class="orders-detail-heading"><h2 id="product-title">Add product</h2><button class="admin-icon-button" id="product-close" type="button" aria-label="Close product editor">×</button></div>
<div id="product-error" class="admin-alert admin-alert-error" role="alert" hidden></div>
<form id="product-form"><fieldset id="product-fields" class="product-fields">
<div class="product-grid">
<?php foreach (['name' => ['Name',150], 'slug' => ['URL slug',190], 'unit' => ['Unit (e.g. kg, pack)',40]] as $name => [$label,$length]): ?>
<div class="admin-field"><label for="product-<?= esc($name) ?>"><?= esc($label) ?></label><input id="product-<?= esc($name) ?>" name="<?= esc($name) ?>" maxlength="<?= $length ?>" required></div>
<?php endforeach ?>
<div class="admin-field"><label for="product-price">Regular price (₹)</label><input id="product-price" name="price" type="number" min="0" max="99999999.99" step="0.01" required></div>
<div class="admin-field"><label for="product-sale_price">Sale price (₹, optional)</label><input id="product-sale_price" name="sale_price" type="number" min="0" max="99999999.99" step="0.01"><small class="admin-caption">Leave blank to use the regular price.</small></div>
<div class="admin-field"><label for="product-stock_quantity">Stock quantity</label><input id="product-stock_quantity" name="stock_quantity" type="number" min="0" max="4294967295" step="1" required></div>
<div class="admin-field"><label for="product-is_active">Availability</label><select id="product-is_active" name="is_active"><option value="1">Active</option><option value="0">Inactive</option></select></div>
</div>
<div class="admin-field"><label for="product-description">Description</label><textarea id="product-description" name="description" maxlength="10000" rows="3"></textarea></div>
<div class="admin-dialog-actions"><button class="admin-button" type="submit" id="product-save">Save product</button><button class="admin-button admin-button-secondary" type="button" id="product-reload" hidden>Reload product</button></div>
</fieldset></form>
<section id="product-images" hidden><h3>Product image</h3><img id="product-image-preview" class="product-preview" alt="Current product image" hidden><p class="admin-caption">JPEG, PNG or WebP. Up to 2 MB and 4096 × 4096 pixels. Save product changes before updating its image.</p>
<form id="product-image-form"><label for="product-image-file">Choose replacement image</label><input id="product-image-file" type="file" accept="image/jpeg,image/png,image/webp" required><div class="admin-dialog-actions"><button class="admin-button" id="product-upload" type="submit">Upload image</button><button class="admin-button admin-button-secondary" id="product-remove-image" type="button">Remove image</button></div></form>
</section>
</dialog>
<noscript><p class="admin-alert admin-alert-error">Enable JavaScript to manage products.</p></noscript>
<script defer src="<?= esc(app_static_url('assets/js/admin-products.js'), 'attr') ?>"></script>
<?= $this->endSection() ?>
