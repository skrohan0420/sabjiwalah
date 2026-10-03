<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Cart - Sabjiwalah</title>
    <link rel="stylesheet" href="/assets/css/cart.css">
</head>
<body>
    <main class="cart-page">
        <header class="cart-header">
            <div>
                <p class="eyebrow">Sabjiwalah</p>
                <h1>Your Cart</h1>
            </div>
            <nav>
                <a href="/">Home</a>
                <a href="/products">Products</a>
            </nav>
        </header>

        <section class="cart-shell" data-cart-page>
            <div class="cart-message" data-cart-message role="status"></div>
            <div class="cart-items" data-cart-items>
                <p>Loading cart...</p>
            </div>

            <aside class="cart-summary">
                <h2>Summary</h2>
                <dl>
                    <div>
                        <dt>Items</dt>
                        <dd data-cart-count>0</dd>
                    </div>
                    <div>
                        <dt>Subtotal</dt>
                        <dd data-cart-subtotal>Rs 0.00</dd>
                    </div>
                </dl>
                <button type="button" data-clear-cart>Clear Cart</button>
                <p><a href="/checkout">Go to checkout</a></p>
            </aside>
        </section>
    </main>

    <script src="/assets/js/cart.js?v=20261003-optimistic-cart"></script>
</body>
</html>
