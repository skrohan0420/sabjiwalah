<!DOCTYPE html>
<html lang="en">
<head>
    <?= view('shared/favicon') ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery Dashboard - Sabjiwalah</title>
</head>
<body>
    <h1>Delivery Dashboard</h1>
    <p>Sabjiwalah delivery area is protected.</p>
    <p>Assigned orders: <?= esc($assignmentCount) ?></p>
    <p><a href="<?= esc(base_url('logout'), 'attr') ?>">Logout</a></p>
</body>
</html>
