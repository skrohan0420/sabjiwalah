<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products - Sabjiwalah</title>
    <link rel="stylesheet" href="/assets/css/cart.css">
</head>
<body>
    <main class="cart-page">
    <header class="cart-header">
        <div>
            <p class="eyebrow">Sabjiwalah</p>
            <h1>Products</h1>
        </div>
        <nav>
            <a href="/">Home</a>
            <a href="/checkout">Checkout (<span data-cart-count-badge>0</span>)</a>
        </nav>
    </header>

    <div class="cart-message" data-cart-message role="status"></div>

    <?php if ($products === []) : ?>
        <p>No products are available yet.</p>
    <?php else : ?>
        <div class="cart-items">
            <?php foreach ($products as $product) : ?>
                <article class="cart-item">
                    <div>
                        <h3>
                            <a href="/products/<?= esc($product['uid'], 'url') ?>">
                                <?= esc($product['name']) ?>
                            </a>
                        </h3>
                        <p>
                            <?= esc($product['unit']) ?>
                            · Rs <?= esc($product['sale_price'] ?? $product['price']) ?>
                            · Stock: <?= esc($product['stock_quantity']) ?>
                        </p>
                    </div>
                    <label>
                        <span class="sr-only">Quantity</span>
                        <input id="qty-<?= esc($product['uid'], 'attr') ?>" type="number" min="1" max="<?= esc($product['stock_quantity'], 'attr') ?>" value="1">
                    </label>
                    <span></span>
                    <button
                        type="button"
                        data-add-to-cart
                        data-product-uid="<?= esc($product['uid'], 'attr') ?>"
                        data-quantity-target="#qty-<?= esc($product['uid'], 'attr') ?>"
                    >
                        Add to Cart
                    </button>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    </main>
    <script src="/assets/js/cart.js"></script>
</body>
</html>
