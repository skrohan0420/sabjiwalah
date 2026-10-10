<?php
$isLoggedIn = (bool) ($isLoggedIn ?? false);
$checkout = $checkout ?? [
    'items'           => [],
    'count'           => 0,
    'subtotal'        => 0,
    'discount_amount' => 0,
    'delivery_charge' => 0,
    'total_amount'    => 0,
];
$items = $checkout['items'] ?? [];
$itemCount = (int) ($checkout['count'] ?? 0);
$loginUrl = '/login?redirect=' . rawurlencode('/checkout');
$pageTitle = $pageTitle ?? 'Checkout';
$backUrl = $backUrl ?? '/';
$money = static fn (float $amount): string => 'Rs ' . number_format($amount, 2);
$compactMoney = static fn (float $amount): string => 'Rs ' . rtrim(rtrim(number_format($amount, 2), '0'), '.');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?= view('shared/favicon') ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($pageTitle) ?> - Sabjiwalah</title>
    <link rel="stylesheet" href="<?= esc(app_static_url('assets/css/cart.css'), 'attr') ?>">
    <script src="<?= esc(app_static_url('assets/js/app-url.js'), 'attr') ?>" data-base-url="<?= esc(base_url(), 'attr') ?>"></script>
    <?= view('shared/theme') ?>
</head>
<body class="checkout-body">
    <main
        class="checkout-page<?= $isLoggedIn ? ' is-authenticated' : ' is-guest' ?>"
        data-checkout-review-page
        data-delivery-charge="<?= esc((string) ($checkout['standard_delivery_charge'] ?? 0), 'attr') ?>"
        data-free-delivery-minimum="<?= esc((string) ($checkout['free_delivery_minimum'] ?? ''), 'attr') ?>"
        <?= $isLoggedIn ? 'data-checkout-page' : '' ?>
    >
        <header class="checkout-topbar">
            <a class="checkout-icon-link" href="<?= esc(app_asset_url($backUrl), 'attr') ?>" aria-label="Go back" data-history-back></a>
            <h1><?= esc($pageTitle) ?></h1>
            <a class="checkout-text-link" href="<?= esc(base_url('products'), 'attr') ?>">Shop</a>
        </header>

        <p class="checkout-card" data-checkout-policy role="status"><?= esc($checkout['checkout_notice'] ?? 'Orders are currently accepted.') ?></p>
        <?php if ($isLoggedIn): ?>
        <form class="checkout-card checkout-coupon" data-coupon-form>
            <label for="checkout-coupon">Coupon code</label>
            <div><input id="checkout-coupon" name="code" maxlength="80" pattern="[A-Za-z0-9][A-Za-z0-9_-]{0,79}" autocomplete="off" required data-coupon-code>
            <button type="submit" data-coupon-apply>Apply</button><button type="button" data-coupon-remove hidden>Remove</button></div>
            <p data-coupon-status role="status" aria-live="polite"></p>
        </form>
        <?php endif ?>
        <?php if (! $isLoggedIn) : ?>
            <section class="checkout-stack" aria-label="Checkout review">
                <section class="checkout-card checkout-items-card" aria-labelledby="checkout-items-title">
                    <div class="checkout-card-heading">
                        <span class="checkout-card-icon" aria-hidden="true"></span>
                        <div>
                            <h2 id="checkout-items-title">Review your cart</h2>
                            <p>Shipment of <span data-checkout-review-count><?= esc((string) $itemCount) ?></span> <span data-checkout-review-count-label><?= $itemCount === 1 ? 'item' : 'items' ?></span></p>
                        </div>
                    </div>

                    <div class="checkout-item-list" data-checkout-review-items>
                        <?php if ($items === []) : ?>
                            <p class="checkout-empty">Your cart is empty. Add fresh picks before checkout.</p>
                        <?php endif; ?>

                        <?php foreach ($items as $item) : ?>
                            <?php
                            $product = $item['product'];
                            $price = (float) ($product['price'] ?? $item['unit_price']);
                            $unitPrice = (float) $item['unit_price'];
                            $hasSavings = $price > $unitPrice;
                            ?>
                            <article
                                class="checkout-review-item"
                                data-cart-control
                                data-checkout-review-item
                                data-product-uid="<?= esc($product['uid'], 'attr') ?>"
                                data-product-name="<?= esc($product['name'], 'attr') ?>"
                                data-product-unit="<?= esc($product['unit'], 'attr') ?>"
                                data-product-image="<?= esc(app_asset_url($product['image']), 'attr') ?>"
                                data-product-price="<?= esc((string) $unitPrice, 'attr') ?>"
                                data-current-quantity="<?= esc((string) $item['quantity'], 'attr') ?>"
                            >
                                <img src="<?= esc(app_asset_url($product['image'] ?: '/assets/images/product-placeholder.svg'), 'attr') ?>" alt="" data-fallback-image="<?= esc(app_static_url('assets/images/product-placeholder.svg'), 'attr') ?>">
                                <div>
                                    <h3><?= esc($product['name']) ?></h3>
                                    <p><?= esc($product['unit']) ?></p>
                                </div>
                                <div class="checkout-quantity-pill" aria-label="<?= esc((string) $item['quantity']) ?> in cart">
                                    <button type="button" data-cart-decrement aria-label="Decrease quantity">-</button>
                                    <strong data-cart-quantity-value><?= esc((string) $item['quantity']) ?></strong>
                                    <button type="button" data-cart-increment aria-label="Increase quantity">+</button>
                                </div>
                                <p class="checkout-line-price">
                                    <?php if ($hasSavings) : ?>
                                        <del><?= esc($compactMoney($price * (int) $item['quantity'])) ?></del>
                                    <?php endif; ?>
                                    <strong><?= esc($compactMoney((float) $item['total'])) ?></strong>
                                </p>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>

                <p class="checkout-card">Sign in to apply a coupon at checkout.</p>

                <section class="checkout-card checkout-bill-card" aria-labelledby="bill-title">
                    <h2 id="bill-title">Bill details</h2>
                    <dl>
                        <div>
                            <dt>Items total</dt>
                            <dd>
                                <strong data-checkout-subtotal><?= esc($compactMoney((float) $checkout['subtotal'])) ?></strong>
                            </dd>
                        </div>
                        <div>
                            <dt>Delivery charge</dt>
                            <dd data-checkout-delivery><?= esc($compactMoney((float) $checkout['delivery_charge'])) ?></dd>
                        </div>
                        <div><dt>Coupon discount</dt><dd data-checkout-discount><?= esc($compactMoney((float) $checkout['discount_amount'])) ?></dd></div>
                        <div class="grand-total">
                            <dt>Grand total</dt>
                            <dd data-checkout-total><?= esc($compactMoney((float) $checkout['total_amount'])) ?></dd>
                        </div>
                    </dl>
                </section>

                <section class="checkout-card checkout-policy">
                    <h2>Cancellation Policy</h2>
                    <p>Orders can be cancelled until they are packed. If there is an unexpected delay, refund support will be provided where applicable.</p>
                </section>
            </section>

            <div class="checkout-login-bar">
                <a href="<?= esc($loginUrl, 'attr') ?>">Login to Proceed</a>
            </div>
        <?php else : ?>
            <section class="checkout-shell" data-checkout-authenticated="true">
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
                    <input type="hidden" name="delivery_latitude" data-delivery-latitude>
                    <input type="hidden" name="delivery_longitude" data-delivery-longitude>
                    <p data-checkout-pin hidden></p>

                    <button type="submit" data-place-order disabled>Place Order</button>
                </form>

                <aside class="cart-summary">
                    <h2>Order Summary</h2>
                    <div data-checkout-items>
                        <?php if ($items === []) : ?>
                            <p>Your cart is empty.</p>
                        <?php else : ?>
                            <?php foreach ($items as $item) : ?>
                                <div class="checkout-line">
                                    <span><?= esc($item['product']['name']) ?> x <?= esc((string) $item['quantity']) ?></span>
                                    <strong><?= esc($money((float) $item['total'])) ?></strong>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <dl>
                        <div>
                            <dt>Subtotal</dt>
                            <dd data-checkout-subtotal><?= esc($money((float) $checkout['subtotal'])) ?></dd>
                        </div>
                        <div>
                            <dt>Delivery</dt>
                            <dd data-checkout-delivery><?= esc($money((float) $checkout['delivery_charge'])) ?></dd>
                        </div>
                        <div><dt>Coupon discount</dt><dd data-checkout-discount><?= esc($money((float) $checkout['discount_amount'])) ?></dd></div>
                        <div>
                            <dt>Total</dt>
                            <dd data-checkout-total><?= esc($money((float) $checkout['total_amount'])) ?></dd>
                        </div>
                    </dl>
                    <p>Payment method: Cash on delivery.</p>
                </aside>
            </section>
        <?php endif; ?>
    </main>

    <script src="<?= esc(app_static_url('assets/js/navigation.js'), 'attr') ?>"></script>
    <script src="<?= esc(app_static_url('assets/js/cart.js'), 'attr') ?>"></script>
    <?php if ($isLoggedIn) : ?>
        <script src="<?= esc(app_static_url('assets/js/checkout.js'), 'attr') ?>"></script>
    <?php endif; ?>
</body>
</html>
