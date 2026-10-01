<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sabjiwalah</title>
</head>
<body>
    <h1>Login</h1>
    <p><a href="/">Back to home</a></p>

    <?php if (session('error')) : ?>
        <p><?= esc(session('error')) ?></p>
    <?php endif; ?>

    <?php if (session('errors')) : ?>
        <ul>
            <?php foreach (session('errors') as $error) : ?>
                <li><?= esc($error) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <form method="post" action="/login">
        <?= csrf_field() ?>
        <p>
            <label>Email<br>
                <input type="email" name="email" value="<?= esc(old('email')) ?>" required>
            </label>
        </p>
        <p>
            <label>Password<br>
                <input type="password" name="password" required>
            </label>
        </p>
        <button type="submit">Login</button>
    </form>
</body>
</html>
