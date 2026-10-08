<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sabjiwalah</title>
    <link rel="stylesheet" href="<?= esc(base_url('assets/css/auth.css?v=20261004-auth-alerts'), 'attr') ?>">
    <script src="<?= esc(base_url('assets/js/app-url.js'), 'attr') ?>" data-base-url="<?= esc(base_url(), 'attr') ?>"></script>
</head>
<body>
    <main class="auth-page">
        <a class="auth-back" href="<?= esc(base_url('account'), 'attr') ?>" aria-label="Go back" data-history-back>
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M15 5 8 12l7 7"></path>
            </svg>
        </a>

        <section class="auth-product-wall auth-product-carousel" aria-hidden="true">
            <div class="carousel-row carousel-row--one">
                <div class="carousel-track">
                    <span class="product-tile product-tile--tomato"><i class="produce-art produce-art--tomato"></i></span>
                    <span class="product-tile product-tile--greens"><i class="produce-art produce-art--greens"></i></span>
                    <span class="product-tile product-tile--pepper"><i class="produce-art produce-art--pepper"></i></span>
                    <span class="product-tile product-tile--cabbage"><i class="produce-art produce-art--cabbage"></i></span>
                    <span class="product-tile product-tile--eggplant"><i class="produce-art produce-art--eggplant"></i></span>
                    <span class="product-tile product-tile--basket"><i class="produce-art produce-art--basket"></i></span>
                    <span class="product-tile product-tile--tomato"><i class="produce-art produce-art--tomato"></i></span>
                    <span class="product-tile product-tile--greens"><i class="produce-art produce-art--greens"></i></span>
                    <span class="product-tile product-tile--pepper"><i class="produce-art produce-art--pepper"></i></span>
                    <span class="product-tile product-tile--cabbage"><i class="produce-art produce-art--cabbage"></i></span>
                </div>
            </div>
            <div class="carousel-row carousel-row--two">
                <div class="carousel-track">
                    <span class="product-tile product-tile--basket"><i class="produce-art produce-art--basket"></i></span>
                    <span class="product-tile product-tile--cabbage"><i class="produce-art produce-art--cabbage"></i></span>
                    <span class="product-tile product-tile--tomato"><i class="produce-art produce-art--tomato"></i></span>
                    <span class="product-tile product-tile--eggplant"><i class="produce-art produce-art--eggplant"></i></span>
                    <span class="product-tile product-tile--greens"><i class="produce-art produce-art--greens"></i></span>
                    <span class="product-tile product-tile--pepper"><i class="produce-art produce-art--pepper"></i></span>
                    <span class="product-tile product-tile--basket"><i class="produce-art produce-art--basket"></i></span>
                    <span class="product-tile product-tile--cabbage"><i class="produce-art produce-art--cabbage"></i></span>
                    <span class="product-tile product-tile--tomato"><i class="produce-art produce-art--tomato"></i></span>
                    <span class="product-tile product-tile--eggplant"><i class="produce-art produce-art--eggplant"></i></span>
                </div>
            </div>
            <div class="carousel-row carousel-row--three">
                <div class="carousel-track">
                    <span class="product-tile product-tile--pepper"><i class="produce-art produce-art--pepper"></i></span>
                    <span class="product-tile product-tile--eggplant"><i class="produce-art produce-art--eggplant"></i></span>
                    <span class="product-tile product-tile--greens"><i class="produce-art produce-art--greens"></i></span>
                    <span class="product-tile product-tile--tomato"><i class="produce-art produce-art--tomato"></i></span>
                    <span class="product-tile product-tile--basket"><i class="produce-art produce-art--basket"></i></span>
                    <span class="product-tile product-tile--cabbage"><i class="produce-art produce-art--cabbage"></i></span>
                    <span class="product-tile product-tile--pepper"><i class="produce-art produce-art--pepper"></i></span>
                    <span class="product-tile product-tile--eggplant"><i class="produce-art produce-art--eggplant"></i></span>
                    <span class="product-tile product-tile--greens"><i class="produce-art produce-art--greens"></i></span>
                    <span class="product-tile product-tile--tomato"><i class="produce-art produce-art--tomato"></i></span>
                </div>
            </div>
        </section>

        <section class="auth-brand" aria-labelledby="auth-title">
            <span class="auth-logo" aria-hidden="true">
                <img src="<?= esc(base_url('assets/images/sabjiwalah-cart-logo.png'), 'attr') ?>" alt="">
            </span>
            <h1 id="auth-title">Fresh groceries at your doorstep</h1>
        </section>

        <section class="auth-card">
            <p class="saved-phone" data-auth-saved-phone hidden></p>

            <div class="auth-message" data-auth-message role="status">
                <?php if (session('error')) : ?>
                    <?= esc(session('error')) ?>
                <?php endif; ?>
            </div>

            <?php if (session('errors')) : ?>
                <ul class="auth-errors">
                    <?php foreach (session('errors') as $error) : ?>
                        <li><?= esc($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <form method="post" action="<?= esc(base_url('login'), 'attr') ?>" class="auth-form" data-auth-form data-auth-mode="login">
                <?= csrf_field() ?>
                <input type="hidden" name="redirect" value="<?= esc($redirect ?? '', 'attr') ?>" data-auth-redirect>

                <label class="phone-field" data-auth-phone-field>
                    <span>Phone number</span>
                    <span class="phone-input-shell">
                        <strong>+91</strong>
                        <input
                            type="tel"
                            name="phone"
                            value="<?= esc(old('phone'), 'attr') ?>"
                            inputmode="numeric"
                            pattern="[0-9]*"
                            autocomplete="tel-national"
                            maxlength="10"
                            placeholder="Enter mobile number"
                            data-auth-phone
                            required
                        >
                    </span>
                </label>

                <button type="button" class="auth-primary" data-send-auth-otp>Send OTP</button>

                <div class="auth-otp-panel" data-auth-otp-panel hidden>
                    <div class="otp-summary">
                        <span>Code sent to <strong data-auth-otp-phone></strong></span>
                        <button type="button" data-change-auth-phone>Change</button>
                    </div>

                    <div class="dev-otp" data-auth-dev-otp hidden></div>

                    <label class="otp-field">
                        Verification code
                        <input
                            class="otp-hidden-input"
                            type="text"
                            name="otp"
                            inputmode="numeric"
                            pattern="[0-9]*"
                            autocomplete="one-time-code"
                            maxlength="6"
                            placeholder="6 digit code"
                            data-auth-otp
                            hidden
                        >
                        <span class="otp-digit-grid" data-auth-otp-digits>
                            <input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" autocomplete="one-time-code" placeholder=" " aria-label="OTP digit 1" data-auth-otp-digit>
                            <input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" placeholder=" " aria-label="OTP digit 2" data-auth-otp-digit>
                            <input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" placeholder=" " aria-label="OTP digit 3" data-auth-otp-digit>
                            <input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" placeholder=" " aria-label="OTP digit 4" data-auth-otp-digit>
                            <input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" placeholder=" " aria-label="OTP digit 5" data-auth-otp-digit>
                            <input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" placeholder=" " aria-label="OTP digit 6" data-auth-otp-digit>
                        </span>
                    </label>

                </div>
            </form>
        </section>

        <p class="auth-terms">By continuing, you agree to our <a href="<?= esc(base_url(''), 'attr') ?>">Terms of service</a> &amp; <a href="<?= esc(base_url(''), 'attr') ?>">Privacy policy</a>.</p>
    </main>

    <script src="<?= esc(base_url('assets/js/navigation.js?v=20261004-history-back'), 'attr') ?>"></script>
    <script src="<?= esc(base_url('assets/js/auth.js'), 'attr') ?>"></script>
</body>
</html>
