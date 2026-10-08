<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Sabjiwalah</title>
</head>
<body>
    <h1>Admin Dashboard</h1>
    <p>Sabjiwalah admin area is protected.</p>
    <ul>
        <li>Products: <?= esc($productCount) ?></li>
        <li>Orders: <?= esc($orderCount) ?></li>
        <li>Customers: <?= esc($customerCount) ?></li>
    </ul>
    <p><a href="<?= esc(base_url('logout'), 'attr') ?>">Logout</a></p>
</body>
</html>
