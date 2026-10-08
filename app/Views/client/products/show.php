<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($product['name']) ?> - Sabjiwalah</title>
    <link rel="stylesheet" href="<?= esc(base_url('assets/css/cart.css'), 'attr') ?>">
    <script src="<?= esc(base_url('assets/js/app-url.js'), 'attr') ?>" data-base-url="<?= esc(base_url(), 'attr') ?>"></script>
</head>
<body>
    <main class="cart-page">
        <header class="cart-header">
            <div>
                <p class="eyebrow">Sabjiwalah</p>
                <h1><?= esc($product['name']) ?></h1>
            </div>
            <nav>
                <a href="<?= esc(base_url('products'), 'attr') ?>">Products</a>
                <a href="<?= esc(base_url('checkout'), 'attr') ?>">Checkout (<span data-cart-count-badge>0</span>)</a>
            </nav>
        </header>

        <section class="cart-shell">
            <article class="cart-items">
                <div class="cart-item">
                    <div>
                        <h3><?= esc($product['name']) ?></h3>
                        <p><?= esc($product['description'] ?? 'No description yet.') ?></p>
                        <p>Unit: <?= esc($product['unit']) ?></p>
                        <p>Stock: <?= esc($product['stock_quantity']) ?></p>
                    </div>
                    <label>
                        <span class="sr-only">Quantity</span>
                        <input id="product-quantity" type="number" min="1" max="<?= esc($product['stock_quantity'], 'attr') ?>" value="1">
                    </label>
                    <strong>Rs <?= esc($product['sale_price'] ?? $product['price']) ?></strong>
                    <button
                        type="button"
                        data-add-to-cart
                        data-product-uid="<?= esc($product['uid'], 'attr') ?>"
                        data-product-name="<?= esc($product['name'], 'attr') ?>"
                        data-product-unit="<?= esc($product['unit'], 'attr') ?>"
                        data-product-price="<?= esc((string) ($product['sale_price'] ?? $product['price']), 'attr') ?>"
                        data-quantity-target="#product-quantity"
                    >
                        Add to Cart
                    </button>
                </div>
            </article>

            <aside class="cart-summary">
                <h2>Cart</h2>
                <p>Items: <strong data-cart-count-badge>0</strong></p>
                <p><a href="<?= esc(base_url('checkout'), 'attr') ?>">Review checkout</a></p>
            </aside>
        </section>
    </main>
    <script src="<?= esc(base_url('assets/js/cart.js?v=20261005-search-redirect'), 'attr') ?>"></script>
</body>
</html>
