<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - Sabjiwalah</title>
    <link rel="stylesheet" href="/assets/css/cart.css">
</head>
<body>
    <main class="cart-page">
        <header class="cart-header">
            <div>
                <p class="eyebrow">Sabjiwalah</p>
                <h1>Checkout</h1>
            </div>
            <nav>
                <a href="/products">Products</a>
                <a href="/cart">Cart</a>
            </nav>
        </header>

        <section class="checkout-shell" data-checkout-page>
            <form class="checkout-form" data-checkout-form>
                <div class="cart-message" data-checkout-message role="status"></div>

                <label>
                    Name
                    <input name="customer_name" type="text" value="<?= esc($user['name'] ?? '', 'attr') ?>" required>
                </label>

                <label>
                    Phone
                    <input name="customer_phone" type="tel" value="<?= esc($user['phone'] ?? '', 'attr') ?>" data-checkout-phone required>
                </label>

                <section class="checkout-otp" aria-labelledby="checkout-otp-title">
                    <div>
                        <h2 id="checkout-otp-title">Phone verification</h2>
                        <p>For now the test OTP is shown here so we can finish the checkout auth flow.</p>
                    </div>
                    <button type="button" data-send-otp>Send OTP</button>
                    <div class="dev-otp" data-dev-otp hidden></div>
                    <div class="otp-row">
                        <label>
                            OTP
                            <input name="otp" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6" data-otp-input>
                        </label>
                        <button type="button" data-verify-otp>Verify</button>
                    </div>
                    <p class="otp-status" data-otp-status>Verify your phone before placing the order.</p>
                </section>

                <label>
                    Address
                    <textarea name="address_line" rows="3" required></textarea>
                </label>

                <div class="checkout-grid">
                    <label>
                        City
                        <input name="city" type="text" required>
                    </label>

                    <label>
                        State
                        <input name="state" type="text">
                    </label>

                    <label>
                        Postal Code
                        <input name="postal_code" type="text" required>
                    </label>
                </div>

                <label>
                    Notes
                    <textarea name="notes" rows="3"></textarea>
                </label>

                <button type="submit" data-place-order disabled>Place Order</button>
            </form>

            <aside class="cart-summary">
                <h2>Order Summary</h2>
                <div data-checkout-items>
                    <p>Loading summary...</p>
                </div>
                <dl>
                    <div>
                        <dt>Subtotal</dt>
                        <dd data-checkout-subtotal>Rs 0.00</dd>
                    </div>
                    <div>
                        <dt>Delivery</dt>
                        <dd data-checkout-delivery>Rs 0.00</dd>
                    </div>
                    <div>
                        <dt>Total</dt>
                        <dd data-checkout-total>Rs 0.00</dd>
                    </div>
                </dl>
                <p>Payment method: Cash on delivery.</p>
            </aside>
        </section>
    </main>

    <script src="/assets/js/checkout.js"></script>
</body>
</html>
