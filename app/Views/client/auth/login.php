<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sabjiwalah</title>
    <link rel="stylesheet" href="/assets/css/auth.css">
</head>
<body>
    <main class="auth-page">
        <section class="auth-card">
            <p class="eyebrow">Sabjiwalah</p>
            <h1>Login with OTP</h1>
            <p class="auth-copy">Enter your phone number, get the testing OTP, and continue. New numbers become customer accounts automatically.</p>

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

            <form method="post" action="/login" class="auth-form" data-auth-form data-auth-mode="login">
                <?= csrf_field() ?>
                <input type="hidden" name="redirect" value="<?= esc($redirect ?? '', 'attr') ?>" data-auth-redirect>

                <label>
                    Phone number
                    <input type="tel" name="phone" value="<?= esc(old('phone'), 'attr') ?>" data-auth-phone required>
                </label>

                <button type="button" data-send-auth-otp>Send OTP</button>

                <div class="dev-otp" data-auth-dev-otp hidden></div>

                <label>
                    OTP
                    <input type="text" name="otp" inputmode="numeric" autocomplete="one-time-code" maxlength="6" data-auth-otp required>
                </label>

                <button type="submit">Verify and continue</button>
            </form>

            <p class="auth-link"><a href="/">Back to home</a></p>
        </section>
    </main>

    <script src="/assets/js/auth.js"></script>
</body>
</html>
