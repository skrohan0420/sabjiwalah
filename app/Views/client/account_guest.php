<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Account - Sabjiwalah</title>
    <link rel="stylesheet" href="/assets/css/home.css?v=20261005-orders-nav">
    <link rel="stylesheet" href="/assets/css/account.css?v=20261005-account-nav">
</head>
<body class="account-guest-body">
    <main class="account-page account-page--guest account-page--with-nav">
        <a class="account-back" href="/" aria-label="Go back" data-history-back>
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M15 5 8 12l7 7"></path>
            </svg>
        </a>

        <section class="guest-hero" aria-labelledby="guest-account-title">
            <div class="guest-avatar" aria-hidden="true">
                <svg viewBox="0 0 64 64">
                    <circle class="avatar-ring" cx="32" cy="32" r="25"></circle>
                    <circle class="avatar-head" cx="32" cy="24.5" r="7.2"></circle>
                    <path class="avatar-body" d="M19 48c2.7-8.6 7.6-12.9 13-12.9S42.3 39.4 45 48"></path>
                </svg>
            </div>
            <h1 id="guest-account-title">Your account</h1>
            <p>Log in to view your complete profile</p>
            <a class="guest-continue" href="/login?redirect=%2Faccount">Continue</a>
        </section>

        <section class="quick-actions" aria-label="Account shortcuts">
            <a href="/orders">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M5 8h14l-1.4 11H6.4L5 8Z"></path>
                    <path d="M8 8a4 4 0 0 1 8 0"></path>
                    <path d="M9 13h6"></path>
                </svg>
                <span>Your orders</span>
            </a>
            <a href="/login?redirect=%2Faccount">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M5 5h14v10H9l-4 4V5Z"></path>
                    <path d="M9 9h6"></path>
                    <path d="M9 12h4"></path>
                </svg>
                <span>Need help?</span>
            </a>
        </section>

        <section class="account-list account-list--single" aria-label="Display settings">
            <button type="button" class="account-row">
                <span class="account-row-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="4"></circle>
                        <path d="M12 2v2"></path>
                        <path d="M12 20v2"></path>
                        <path d="m4.9 4.9 1.4 1.4"></path>
                        <path d="m17.7 17.7 1.4 1.4"></path>
                        <path d="M2 12h2"></path>
                        <path d="M20 12h2"></path>
                        <path d="m4.9 19.1 1.4-1.4"></path>
                        <path d="m17.7 6.3 1.4-1.4"></path>
                    </svg>
                </span>
                <span>Appearance</span>
                <strong>LIGHT</strong>
            </button>
        </section>

        <section class="account-list" aria-labelledby="guest-info-title">
            <h2 id="guest-info-title">Your information</h2>
            <a class="account-row" href="/login?redirect=%2Faccount">
                <span class="account-row-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24">
                        <path d="M4 6.5 10 4l6 2.5 4-1.5v13.5L14 21l-6-2.5-4 1.5V6.5Z"></path>
                        <path d="M10 4v14.5"></path>
                        <path d="M16 6.5V21"></path>
                    </svg>
                </span>
                <span>Address book</span>
                <svg class="account-chevron" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="m9 5 7 7-7 7"></path>
                </svg>
            </a>
        </section>

        <section class="account-list" aria-labelledby="guest-other-title">
            <h2 id="guest-other-title">Other information</h2>
            <button type="button" class="account-row" data-share-app>
                <span class="account-row-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24">
                        <path d="M12 16V4"></path>
                        <path d="m7 9 5-5 5 5"></path>
                        <path d="M5 14v5h14v-5"></path>
                    </svg>
                </span>
                <span>Share the app</span>
                <svg class="account-chevron" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="m9 5 7 7-7 7"></path>
                </svg>
            </button>
            <a class="account-row" href="/">
                <span class="account-row-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24">
                        <path d="M4 6.5 10 4l6 2.5 4-1.5v13.5L14 21l-6-2.5-4 1.5V6.5Z"></path>
                        <path d="M10 4v14.5"></path>
                        <path d="M16 6.5V21"></path>
                    </svg>
                </span>
                <span>About us</span>
                <svg class="account-chevron" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="m9 5 7 7-7 7"></path>
                </svg>
            </a>
        </section>

        <footer class="guest-footer">
            <strong>Sabjiwalah</strong>
            <span>Fresh groceries delivered</span>
        </footer>
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

    <script src="/assets/js/navigation.js?v=20261004-history-back"></script>
    <script src="/assets/js/cart.js?v=20261005-search-redirect"></script>
    <script>
        document.addEventListener('click', async (event) => {
            const button = event.target.closest('[data-share-app]');

            if (!button) {
                return;
            }

            if (navigator.share) {
                await navigator.share({
                    title: 'Sabjiwalah',
                    text: 'Fresh groceries and vegetables delivered by Sabjiwalah.',
                    url: window.location.origin,
                });
            }
        });
    </script>
</body>
</html>
