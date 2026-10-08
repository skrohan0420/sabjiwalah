<?php

// Validate coordinate pairs without placing an order or changing stock.
define('ENVIRONMENT', 'development');
define('FCPATH', dirname(__DIR__, 2) . '/public/');
require dirname(__DIR__, 2) . '/app/Config/Paths.php';
$paths = new Config\Paths();
require $paths->systemDirectory . '/Boot.php';
CodeIgniter\Boot::bootConsole($paths);

$service = new App\Services\OrderService();
foreach ([
    ['delivery_latitude' => 22],
    ['delivery_longitude' => 88],
    ['delivery_latitude' => 91, 'delivery_longitude' => 88],
    ['delivery_latitude' => 22, 'delivery_longitude' => -181],
    ['delivery_latitude' => 'bad', 'delivery_longitude' => 88],
    ['delivery_latitude' => INF, 'delivery_longitude' => 88],
] as $data) {
    try {
        $service->createFromCart(0, $data);
        throw new RuntimeException('Invalid pin was accepted.');
    } catch (InvalidArgumentException $error) {
        if ($error->getMessage() !== 'Choose a valid delivery point.') {
            throw $error;
        }
    }
}

$fields = db_connect()->getFieldNames('orders');
foreach (['delivery_latitude', 'delivery_longitude'] as $field) {
    if (! in_array($field, $fields, true)) {
        throw new RuntimeException('Missing order coordinate column: ' . $field);
    }
}
echo "PASS: invalid coordinate pairs rejected; local order columns present.\n";
