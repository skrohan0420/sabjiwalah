<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($product['name']) ?> - Sabjiwalah</title>
</head>
<body>
    <p><a href="/products">Back to products</a></p>
    <h1><?= esc($product['name']) ?></h1>
    <p><?= esc($product['description'] ?? 'No description yet.') ?></p>
    <p>Unit: <?= esc($product['unit']) ?></p>
    <p>Price: Rs <?= esc($product['sale_price'] ?? $product['price']) ?></p>
    <p>Stock: <?= esc($product['stock_quantity']) ?></p>
</body>
</html>
