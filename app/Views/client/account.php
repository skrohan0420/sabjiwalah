<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account - Sabjiwalah</title>
    <link rel="stylesheet" href="/assets/css/home.css?v=20261005-orders-nav">
    <link rel="stylesheet" href="/assets/css/account.css?v=20261005-account-nav">
</head>
<body>
    <main class="account-page account-page--with-nav" data-account-page>
        <header class="account-header">
            <div>
                <p class="eyebrow">Sabjiwalah</p>
                <h1>My Account</h1>
                <p>Manage your personal details for orders and checkout.</p>
            </div>
            <nav>
                <a href="/">Home</a>
                <a href="/products">Products</a>
                <a href="/checkout">Checkout</a>
                <a href="/logout">Logout</a>
            </nav>
        </header>

        <section class="account-shell">
            <aside class="account-card account-summary">
                <h2>Profile</h2>
                <dl>
                    <div>
                        <dt>Name</dt>
                        <dd data-profile-name><?= esc($user['name'] ?? '') ?></dd>
                    </div>
                    <div>
                        <dt>Phone</dt>
                        <dd data-profile-phone><?= esc($user['phone'] ?? '') ?></dd>
                    </div>
                    <div>
                        <dt>Email</dt>
                        <dd data-profile-email><?= esc(($user['email'] ?? '') ?: 'Not added') ?></dd>
                    </div>
                    <div>
                        <dt>Status</dt>
                        <dd><?= esc($user['status'] ?? '') ?></dd>
                    </div>
                </dl>
            </aside>

            <section class="account-card">
                <h2>Personal Details</h2>
                <p class="account-note">Your phone number is used for OTP login. Phone changes will get a separate OTP flow later.</p>

                <form class="account-form" data-account-form>
                    <div class="account-message" data-account-message role="status"></div>

                    <label>
                        Display name
                        <input name="name" type="text" value="<?= esc($user['name'] ?? '', 'attr') ?>" required>
                    </label>

                    <label>
                        Email optional
                        <input name="email" type="email" value="<?= esc($user['email'] ?? '', 'attr') ?>">
                    </label>

                    <label>
                        Verified phone
                        <input type="tel" value="<?= esc($user['phone'] ?? '', 'attr') ?>" readonly>
                    </label>

                    <button type="submit">Save Changes</button>
                </form>
            </section>
        </section>
    </main>

    <a class="floating-cart-bar" href="/checkout" data-floating-cart hidden>
        <span class="floating-cart-thumbs" data-floating-cart-thumbs aria-hidden="true"></span>
        <span>
            <strong>Checkout</strong>
            <em data-floating-cart-count>0 items</em>
        </span>
        <i aria-hidden="true"></i>
    </a>

    <nav class="bottom-nav" aria-label="Bottom navigation">
        <a href="/">
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
        <a href="/orders">
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
        <a href="/products">
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
        <a class="is-active" href="/account">
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

    <script src="/assets/js/account.js"></script>
    <script src="/assets/js/cart.js?v=20261005-search-redirect"></script>
</body>
</html>
