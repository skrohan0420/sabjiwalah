<?php
// Execute dashboard SQL against connection-local temporary tables only.
// Persistent application tables and records are never modified.
define('ENVIRONMENT', 'development');
define('FCPATH', dirname(__DIR__, 2) . '/public/');
require dirname(__DIR__, 2) . '/app/Config/Paths.php';
$paths = new Config\Paths();
require $paths->systemDirectory . '/Boot.php';
CodeIgniter\Boot::bootConsole($paths);
$db = db_connect();
if ($db->DBDriver !== 'MySQLi' || $db->DBPrefix !== '') {
    throw new RuntimeException('This isolated fixture requires local MySQL without a table prefix.');
}
function dashboardCheck(bool $condition, string $message): void {
    if (! $condition) throw new RuntimeException($message);
}
// TEMPORARY names shadow persistent tables only on this connection and expire at disconnect.
$db->query('CREATE TEMPORARY TABLE orders (id INT AUTO_INCREMENT PRIMARY KEY, uid VARCHAR(40), order_number VARCHAR(40), customer_name VARCHAR(120), total_amount DECIMAL(10,2), order_status VARCHAR(40), payment_status VARCHAR(40), created_at DATETIME)');
$db->query('CREATE TEMPORARY TABLE products (id INT AUTO_INCREMENT PRIMARY KEY, is_active TINYINT, stock_quantity INT)');
$db->query('CREATE TEMPORARY TABLE users (id INT AUTO_INCREMENT PRIMARY KEY, role VARCHAR(20))');
config('App')->appTimezone = 'UTC';
$service = new App\Services\AdminDashboardService(new App\Models\AdminDashboardModel($db));
$now = new DateTimeImmutable('2026-10-09T12:00:00+05:30');
$empty = $service->overview($now);
dashboardCheck($empty['totals']['total_orders'] === 0 && $empty['totals']['sales_today'] === 0.0, 'Empty aggregates must be zero.');
dashboardCheck($empty['recent_orders'] === [] && $empty['attention_orders'] === [], 'Empty lists expected.');
$cases = [
    ['delivered', 'paid', '2026-10-08 18:30:00', 100], // exactly India midnight: included
    ['delivered', 'paid', '2026-10-08 18:29:59', 200], // just before: excluded
    ['delivered', 'paid', '2026-10-09 18:30:00', 300], // next midnight: excluded
    ['delivered', 'pending', '2026-10-09 10:00:00', 400], // unpaid COD: excluded
    ['cancelled', 'paid', '2026-10-09 10:01:00', 500],
    ['pending', 'paid', '2026-10-09 10:02:00', 600],
    ['confirmed', 'pending', '2026-10-09 10:03:00', 10],
    ['preparing', 'pending', '2026-10-09 10:04:00', 10],
    ['ready_for_delivery', 'pending', '2026-10-09 10:05:00', 10],
    ['out_for_delivery', 'pending', '2026-10-09 10:06:00', 10],
    ['delivery_failed', 'pending', '2026-10-09 10:07:00', 10],
];
foreach ($cases as $i => [$status, $payment, $created, $total]) {
    $db->table('orders')->insert(['uid' => 'ord_test_' . $i, 'order_number' => 'SW_TEST_' . $i, 'customer_name' => '<script>fixture</script>', 'total_amount' => $total, 'order_status' => $status, 'payment_status' => $payment, 'created_at' => $created]);
}
$db->table('products')->insertBatch([['is_active' => 1, 'stock_quantity' => 0], ['is_active' => 1, 'stock_quantity' => 5], ['is_active' => 1, 'stock_quantity' => 6], ['is_active' => 0, 'stock_quantity' => 1]]);
$db->table('users')->insertBatch([['role' => 'customer'], ['role' => 'customer'], ['role' => 'admin'], ['role' => 'delivery']]);
$data = $service->overview($now);
$t = $data['totals'];
dashboardCheck($t['total_orders'] === 11 && $t['orders_today'] === 9, 'Date window must follow India midnight with an exclusive upper bound.');
dashboardCheck($t['sales_today'] === 100.0, 'Only paid delivered orders placed today qualify as sales.');
dashboardCheck($t['delivered'] === 4 && $t['pending'] === 1 && $t['preparing'] === 1 && $t['out_for_delivery'] === 1 && $t['cancelled'] === 1, 'Status aggregates incorrect.');
dashboardCheck($t['total_customers'] === 2 && $t['active_products'] === 3 && $t['low_stock_products'] === 2, 'Customer/stock aggregates incorrect.');
dashboardCheck($t['attention_count'] === 3 && count($data['attention_orders']) === 3, 'Attention scope incorrect.');
dashboardCheck(count($data['recent_orders']) === 8 && $data['recent_orders'][0]['uid'] === 'ord_test_2', 'Recent list must be ordered and bounded.');
dashboardCheck($data['recent_orders'][0]['created_at'] === '2026-10-09T18:30:00+00:00', 'Stored timestamps need an explicit offset.');
dashboardCheck(! isset($data['recent_orders'][0]['customer_phone']), 'Overview must not expose contact details.');
config('App')->appTimezone = 'Asia/Kolkata';
$local = $service->overview($now);
dashboardCheck($local['totals']['sales_today'] === 300.0, 'Configured local storage timezone must also be respected.');
echo "PASS: dashboard aggregates, empty data, India-day boundaries, paid sales, stock thresholds, bounded lists, and timezone conversion. Persistent data unchanged.\n";
