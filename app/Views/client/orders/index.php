<?php
$orders = $orders ?? [];
$isLoggedIn = (bool) ($isLoggedIn ?? false);
$compactMoney = static fn (float $amount): string => 'Rs ' . rtrim(rtrim(number_format($amount, 2), '0'), '.');
$statusLabel = static fn (string $status): string => ucwords(str_replace('_', ' ', $status));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?= view('shared/favicon') ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="View your Sabjiwalah orders">
    <title>My Orders - Sabjiwalah</title>
    <link rel="stylesheet" href="<?= esc(base_url('assets/css/home.css?v=20261005-orders-nav'), 'attr') ?>">
    <script src="<?= esc(base_url('assets/js/app-url.js'), 'attr') ?>" data-base-url="<?= esc(base_url(), 'attr') ?>"></script>
</head>
<body class="orders-body">
    <div class="app-shell orders-app-shell">
        <header class="orders-header">
            <a class="orders-back-link" href="<?= esc(base_url(''), 'attr') ?>" aria-label="Go back" data-history-back></a>
            <div>
                <h1>My Orders</h1>
                <p>Track your fresh grocery orders</p>
            </div>
        </header>

        <main class="orders-page">
            <?php if (! $isLoggedIn) : ?>
                <section class="orders-empty" aria-labelledby="orders-login-title">
                    <span aria-hidden="true"></span>
                    <h2 id="orders-login-title">Login to view orders</h2>
                    <p>Your current and past orders will appear here after you sign in.</p>
                    <a href="<?= esc(base_url('login?redirect=%2Forders'), 'attr') ?>">Continue</a>
                </section>
            <?php elseif ($orders === []) : ?>
                <section class="orders-empty" aria-labelledby="orders-empty-title">
                    <span aria-hidden="true"></span>
                    <h2 id="orders-empty-title">No orders yet</h2>
                    <p>Start shopping and your placed orders will show up here.</p>
                    <a href="<?= esc(base_url('products'), 'attr') ?>">Shop products</a>
                </section>
            <?php else : ?>
                <section class="orders-list" aria-label="Your orders">
                    <?php foreach ($orders as $order) : ?>
                        <article class="order-card">
                            <div>
                                <strong>#<?= esc($order['order_number']) ?></strong>
                                <span><?= esc($statusLabel((string) $order['order_status'])) ?></span>
                            </div>
                            <p><?= esc((string) ((int) ($order['item_count'] ?? 0))) ?> items</p>
                            <dl>
                                <div>
                                    <dt>Total</dt>
                                    <dd><?= esc($compactMoney((float) $order['total_amount'])) ?></dd>
                                </div>
                                <div>
                                    <dt>Payment</dt>
                                    <dd><?= esc($statusLabel((string) $order['payment_status'])) ?></dd>
                                </div>
                            </dl>
                        </article>
                    <?php endforeach; ?>
                </section>
            <?php endif; ?>
        </main>

        <nav class="bottom-nav" aria-label="Bottom navigation">
            <a href="<?= esc(base_url(''), 'attr') ?>">
                <span class="nav-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" focusable="false">
                        <path d="M3.8 10.9 12 4.2l8.2 6.7"></path>
                        <path d="M5.7 9.9v8.5c0 1 .7 1.7 1.7 1.7h9.2c1 0 1.7-.7 1.7-1.7V9.9"></path>
                        <path d="M9.7 20.1v-5.8h4.6v5.8"></path>
                        <path d="M10.1 4.9h3.8"></path>
                    </svg>
                </span>
                Home
            </a>
            <a class="is-active" href="<?= esc(base_url('orders'), 'attr') ?>">
                <span class="nav-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" focusable="false">
                        <path d="M6.2 4.7h11.6v15l-2-1.2-1.9 1.2-1.9-1.2-1.9 1.2-1.9-1.2-2 1.2v-15Z"></path>
                        <path d="M8.9 8.4h6.2"></path>
                        <path d="M8.9 12h6.2"></path>
                        <path d="M8.9 15.6h3.7"></path>
                    </svg>
                </span>
                My Orders
            </a>
            <a href="<?= esc(base_url('products'), 'attr') ?>">
                <span class="nav-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" focusable="false">
                        <rect x="4.8" y="4.8" width="5.2" height="5.2" rx="1.3"></rect>
                        <rect x="14" y="4.8" width="5.2" height="5.2" rx="1.3"></rect>
                        <rect x="4.8" y="14" width="5.2" height="5.2" rx="1.3"></rect>
                        <rect x="14" y="14" width="5.2" height="5.2" rx="1.3"></rect>
                    </svg>
                </span>
                Categories
            </a>
            <a href="<?= esc(base_url('account'), 'attr') ?>">
                <span class="nav-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" focusable="false">
                        <path d="M12 12.4a4.2 4.2 0 1 0 0-8.4 4.2 4.2 0 0 0 0 8.4Z"></path>
                        <path d="M4.9 20.2c.7-4.2 3.1-6.3 7.1-6.3s6.4 2.1 7.1 6.3"></path>
                        <path d="M8.8 18.5h6.4"></path>
                    </svg>
                </span>
                Account
            </a>
        </nav>
    </div>
    <script src="<?= esc(base_url('assets/js/navigation.js?v=20261004-history-back'), 'attr') ?>"></script>
    <script src="<?= esc(base_url('assets/js/cart.js?v=20261005-search-redirect'), 'attr') ?>"></script>
</body>
</html>
