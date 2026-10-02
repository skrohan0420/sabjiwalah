<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products - Sabjiwalah</title>
</head>
<body>
    <h1>Products</h1>
    <p><a href="/">Back to home</a></p>

    <?php if ($products === []) : ?>
        <p>No products are available yet.</p>
    <?php else : ?>
        <ul>
            <?php foreach ($products as $product) : ?>
                <li>
                    <a href="/products/<?= esc($product['uid'], 'url') ?>">
                        <?= esc($product['name']) ?>
                    </a>
                    - <?= esc($product['unit']) ?>
                    - Rs <?= esc($product['sale_price'] ?? $product['price']) ?>
                    - Stock: <?= esc($product['stock_quantity']) ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</body>
</html>
