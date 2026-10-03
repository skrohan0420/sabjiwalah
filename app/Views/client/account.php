<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account - Sabjiwalah</title>
    <link rel="stylesheet" href="/assets/css/account.css">
</head>
<body>
    <main class="account-page" data-account-page>
        <header class="account-header">
            <div>
                <p class="eyebrow">Sabjiwalah</p>
                <h1>My Account</h1>
                <p>Manage your personal details for orders and checkout.</p>
            </div>
            <nav>
                <a href="/">Home</a>
                <a href="/products">Products</a>
                <a href="/cart">Cart</a>
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

    <script src="/assets/js/account.js"></script>
</body>
</html>
