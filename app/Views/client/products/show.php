<?php
$price = (float) ($product['sale_price'] ?? $product['price']);
$discount = $price < (float) $product['price'];
$image = app_asset_url($product['image'] ?: 'assets/images/product-placeholder.svg');
$money = static fn ($amount) => number_format((float) $amount, 2, '.', '');
$icons = [
    'back' => '<path d="m5 9 7 7 7-7"/>',
    'heart' => '<path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8L12 21l8.8-8.6a5.5 5.5 0 0 0 0-7.8Z"/>',
    'search' => '<circle cx="10.5" cy="10.5" r="7.5"/><path d="m16 16 5 5"/>',
    'share' => '<path d="M12 16V3m-4 4 4-4 4 4M5 12v7a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-7"/>',
    'close' => '<path d="m6 6 12 12M18 6 6 18"/>',
    'box' => '<path d="m3 7 9-4 9 4v10l-9 4-9-4V7Zm0 0 9 4 9-4M12 11v10M7 5l9 4"/>',
    'next' => '<path d="m9 5 7 7-7 7"/>',
];
$icon = static fn ($name) => '<svg viewBox="0 0 24 24" aria-hidden="true">' . $icons[$name] . '</svg>';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($product['name']) ?> - Sabjiwalah</title>
    <link rel="stylesheet" href="<?= esc(base_url('assets/css/home.css?v=20261008-shared'), 'attr') ?>">
    <link rel="stylesheet" href="<?= esc(base_url('assets/css/product-detail.css?v=20261008-compact'), 'attr') ?>">
    <script src="<?= esc(base_url('assets/js/app-url.js'), 'attr') ?>" data-base-url="<?= esc(base_url(), 'attr') ?>"></script>
</head>
<body class="product-detail-page">
<main class="product-detail-shell">
    <header class="detail-header" data-detail-header>
        <a class="detail-icon" href="<?= esc(base_url('products'), 'attr') ?>" data-history-back aria-label="Back to products"><?= $icon('back') ?></a>
        <span class="detail-header-name"><?= esc($product['name']) ?></span>
        <div class="detail-header-actions">
            <button class="detail-icon" type="button" data-save-product="<?= esc($product['uid'], 'attr') ?>" aria-label="Save product" aria-pressed="false"><?= $icon('heart') ?></button>
            <a class="detail-icon" href="<?= esc(base_url('search'), 'attr') ?>" aria-label="Search products"><?= $icon('search') ?></a>
            <button class="detail-icon" type="button" data-detail-share aria-label="Share product"><?= $icon('share') ?></button>
        </div>
    </header>
    <section class="detail-hero" data-detail-hero aria-label="Product images" aria-roledescription="carousel">
        <div class="hero-gallery" data-hero-gallery tabindex="0" aria-label="Product image gallery. Use left and right arrows to switch views.">
            <?php foreach (['Full image', 'Close-up', 'Detail'] as $slide => $label): ?>
                <div class="hero-slide hero-slide-<?= $slide ?>" role="group" aria-roledescription="slide" aria-label="<?= esc($label . ', ' . ($slide + 1) . ' of 3', 'attr') ?>">
                    <img src="<?= esc($image, 'attr') ?>" alt="<?= esc($product['name'] . ' — ' . $label, 'attr') ?>" draggable="false" data-fallback-image="<?= esc(app_asset_url('assets/images/product-placeholder.svg'), 'attr') ?>">
                </div>
            <?php endforeach ?>
        </div>
        <div class="hero-gallery-dots" aria-label="Choose product image">
            <?php foreach (['Full image', 'Close-up', 'Detail'] as $slide => $label): ?>
                <button type="button" data-hero-slide="<?= $slide ?>" aria-label="<?= esc($label, 'attr') ?>" aria-pressed="<?= $slide === 0 ? 'true' : 'false' ?>" class="<?= $slide === 0 ? 'is-active' : '' ?>"></button>
            <?php endforeach ?>
        </div>
    </section>
    <div class="detail-content">
        <section class="detail-overview" aria-label="Product overview">
        <div class="detail-highlights">
            <div class="highlight"><span>Pack size</span><strong><?= esc($product['unit']) ?></strong></div>
            <div class="highlight"><span>Availability</span><strong><?= (int) $product['stock_quantity'] > 0 ? 'In stock' : 'Sold out' ?></strong></div>
        </div>
        <section class="detail-panel detail-summary">
            <p class="detail-kicker"><span></span> Fresh groceries</p>
            <h1 id="product-title"><?= esc($product['name']) ?></h1>
            <div class="detail-summary-bottom">
                <span class="detail-stock"><?= (int) $product['stock_quantity'] ?> packs available</span>
                <div class="detail-price"><strong>₹<?= $money($price) ?></strong><?php if ($discount): ?><span>MRP <s>₹<?= $money($product['price']) ?></s></span><?php endif ?></div>
            </div>
        </section>
        <button type="button" class="detail-panel detail-info-link" data-open-details>
            <span class="info-icon"><?= $icon('box') ?></span><span>Product details<small>Description &amp; pack information</small></span><?= $icon('next') ?>
        </button>
        </section>
        <?php if ($suggestions): ?>
        <section class="detail-panel detail-recommendations">
            <h2>More fresh picks</h2>
            <div class="commerce-grid">
                <?php foreach ($suggestions as $index => $pick): ?>
                    <?= view('client/products/_catalog_card', ['product' => $pick, 'index' => $index, 'sectionIndex' => 1]) ?>
                <?php endforeach ?>
            </div>
        </section>
        <?php endif ?>

    </div>
</main>
<dialog id="product-details" class="details-dialog" aria-labelledby="details-title">
    <button type="button" class="details-close" data-close-details aria-label="Close product details"><?= $icon('close') ?></button>
    <div class="details-sheet">
        <header class="details-sheet-heading"><img src="<?= esc($image, 'attr') ?>" alt=""><h2 id="details-title"><?= esc($product['name']) ?></h2></header>
        <div class="details-sheet-content">
            <h3>Highlights</h3>
            <div class="sheet-highlights">
                <div class="highlight"><span>Pack size</span><strong><?= esc($product['unit']) ?></strong></div>
                <div class="highlight"><span>Availability</span><strong><?= (int) $product['stock_quantity'] > 0 ? 'In stock' : 'Sold out' ?></strong></div>
            </div>
            <h3>All details</h3>
            <details class="detail-accordion" open>
                <summary>Key Information</summary>
                <dl><dt>Pack size</dt><dd><?= esc($product['unit']) ?></dd><dt>Availability</dt><dd><?= (int) $product['stock_quantity'] ?> packs available</dd><dt>Price per pack</dt><dd>₹<?= $money($price) ?></dd></dl>
            </details>
            <details class="detail-accordion">
                <summary>Info</summary>
                <div class="detail-description"><?= esc(trim((string) ($product['description'] ?? '')) ?: 'More product information will be available soon.') ?></div>
            </details>
        </div>
    </div>
</dialog>
<footer class="detail-purchase-bar">
    <div><strong class="purchase-unit"><?= esc($product['unit']) ?></strong><div class="purchase-price"><strong>₹<?= $money($price) ?></strong><?php if ($discount): ?><span>MRP <s>₹<?= $money($product['price']) ?></s></span><?php endif ?></div><small>Price per pack</small></div>
    <?= view('client/products/_detail_control', ['item' => $product, 'primary' => true]) ?>
</footer>
<?= view('client/products/_floating_cart') ?>
<p class="detail-notice" data-detail-notice role="status" hidden></p>
<script src="<?= esc(base_url('assets/js/navigation.js'), 'attr') ?>"></script>
<script src="<?= esc(base_url('assets/js/cart.js?v=20261008-detail'), 'attr') ?>"></script>
<script src="<?= esc(base_url('assets/js/product-detail.js?v=20261008-gallery'), 'attr') ?>"></script>
</body>
</html>
