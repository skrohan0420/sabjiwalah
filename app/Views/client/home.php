<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sabjiwalah - Grocery Delivery</title>
</head>
<body>
    <h1>Grocery Delivery</h1>
    <p>Sabjiwalah customer area is running.</p>

    <?php $databaseStatus = $databaseStatus ?? ['connected' => false, 'message' => 'Database status was not checked.']; ?>
    <section>
        <h2>Database <?= $databaseStatus['connected'] ? 'connected' : 'not connected' ?></h2>
        <p><?= esc($databaseStatus['message']) ?></p>

        <?php if ($databaseStatus['connected']) : ?>
            <ul>
                <li>Database: <?= esc($databaseStatus['database']) ?></li>
                <li>User: <?= esc($databaseStatus['user']) ?></li>
                <li>Driver: <?= esc($databaseStatus['driver']) ?></li>
            </ul>
        <?php endif; ?>
    </section>

    <?php if (session('message')) : ?>
        <p><?= esc(session('message')) ?></p>
    <?php endif; ?>

    <?php if (session('error')) : ?>
        <p><?= esc(session('error')) ?></p>
    <?php endif; ?>

    <nav>
        <a href="/products">Products</a>
        <?php if (session('is_logged_in')) : ?>
            <a href="/account">Account</a>
            <a href="/logout">Logout</a>
        <?php else : ?>
            <a href="/login">Login</a>
            <a href="/signup">Signup</a>
        <?php endif; ?>
    </nav>

    <h2>Sample Products</h2>
    <?php if ($products === []) : ?>
        <p>No products have been seeded yet.</p>
    <?php else : ?>
        <ul>
            <?php foreach ($products as $product) : ?>
                <li>
                    <a href="/products/<?= esc($product['slug'], 'url') ?>">
                        <?= esc($product['name']) ?>
                    </a>
                    - <?= esc($product['unit']) ?> - Rs <?= esc($product['sale_price'] ?? $product['price']) ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</body>
</html>
