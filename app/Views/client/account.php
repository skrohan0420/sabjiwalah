<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account - Sabjiwalah</title>
</head>
<body>
    <h1>Customer Account</h1>
    <p><a href="/">Back to home</a></p>
    <p>Name: <?= esc($user['name'] ?? '') ?></p>
    <p>Email: <?= esc($user['email'] ?? '') ?></p>
    <p>Role: <?= esc($user['role'] ?? '') ?></p>
    <p>Status: <?= esc($user['status'] ?? '') ?></p>
</body>
</html>
