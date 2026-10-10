<?php
// Connection-local temporary tables. No persistent customer/order/PIN records are changed.
define('ENVIRONMENT', 'development'); define('FCPATH', dirname(__DIR__, 2) . '/public/');
require dirname(__DIR__, 2) . '/app/Config/Paths.php'; $paths = new Config\Paths(); require $paths->systemDirectory . '/Boot.php';
CodeIgniter\Boot::bootConsole($paths); service('session'); session_start(); $db = db_connect();
date_default_timezone_set(config('App')->appTimezone);
if ($db->DBDriver !== 'MySQLi' || $db->DBPrefix !== '') throw new RuntimeException('Use local MySQL without prefix.');
foreach (['users', 'orders', 'order_delivery_pins', 'order_items'] as $table) {
    $ddl = $db->query('SHOW CREATE TABLE ' . $table)->getRowArray()['Create Table'];
    $ddl = str_replace('CREATE TABLE', 'CREATE TEMPORARY TABLE', $ddl);
    $ddl = preg_replace('/^\s*CONSTRAINT .* FOREIGN KEY .*\n/m', '', $ddl);
    $db->query(preg_replace('/,\s*\)/', ')', $ddl));
}
function pinCheck(bool $ok, string $message): void { if (! $ok) throw new RuntimeException($message); }
function pinAction(int $actor, string $uid, array $body = []): array {
    session()->set('user_id', $actor);
    $request = new CodeIgniter\HTTP\IncomingRequest(config('App'), new CodeIgniter\HTTP\URI('http://localhost/'), json_encode($body), new CodeIgniter\HTTP\UserAgent());
    $controller = new App\Controllers\Api\V1\DeliveryPinController();
    $controller->initController($request, new CodeIgniter\HTTP\Response(config('App')), service('logger'));
    $response = $controller->create($uid);
    return [$response->getStatusCode(), json_decode($response->getBody(), true), $response];
}
$users = new App\Models\UserModel(); $ids = [];
foreach (['customer', 'customer', 'delivery', 'admin'] as $i => $role) $ids[] = $users->insert(['name' => 'PIN Fixture ' . $i, 'phone' => '987651000' . $i, 'role' => $role, 'status' => 'active']);
[$owner, $other, $rider, $admin] = $ids;
$orders = new App\Models\OrderModel(); $orderIds = [];
foreach (['out_for_delivery', 'pending', 'ready_for_delivery', 'delivered', 'cancelled', 'delivery_failed'] as $i => $status) {
    $orderIds[] = $orders->skipValidation(true)->insert(['order_number' => 'PIN_' . $i, 'user_id' => $owner, 'subtotal' => 100, 'total_amount' => 100, 'payment_method' => 'cod', 'payment_status' => 'pending', 'order_status' => $status, 'customer_name' => 'PIN Fixture', 'customer_phone' => '9999999999', 'address_line' => 'Test', 'city' => 'Fixture', 'postal_code' => '700001']);
}
$uid = $orders->find($orderIds[0])['uid'];
foreach ([$other, $rider, $admin, 0] as $actor) pinCheck(pinAction($actor, $uid)[0] === 404, 'Unauthorized actor could obtain PIN.');
pinCheck(pinAction($owner, 'ord_missing')[0] === 404, 'Missing order not concealed.');
foreach (array_slice($orderIds, 1) as $id) pinCheck(pinAction($owner, $orders->find($id)['uid'])[0] === 409, 'Wrong status issued PIN.');
foreach ([['pin' => '123456'], ['user_id' => $other], ['expires_at' => '2099-01-01']] as $payload) pinCheck(pinAction($owner, $uid, $payload)[0] === 422, 'Client-controlled issuance accepted.');
$db->table('users')->where('id', $owner)->update(['status' => 'inactive']);
pinCheck(pinAction($owner, $uid)[0] === 404, 'Inactive owner issued PIN.');
$db->table('users')->where('id', $owner)->update(['status' => 'active']);
[$code, $payload, $response] = pinAction($owner, $uid); pinCheck($code === 200, 'Issuance failed: ' . json_encode($payload));
$first = $payload['data']; $row = $db->table('order_delivery_pins')->get()->getRowArray();
pinCheck(preg_match('/^\d{6}$/', $first['pin']) === 1 && password_verify($first['pin'], $row['pin_hash']), 'PIN not correctly hashed.');
pinCheck(! str_contains(json_encode($row), $first['pin']), 'Plaintext PIN persisted.');
pinCheck(strtotime($row['expires_at']) - strtotime($row['issued_at']) === 1800 && $first['valid_for_seconds'] === 1800, 'Expiry wrong.');
pinCheck(str_contains($response->getHeaderLine('Cache-Control'), 'no-store') && ! isset($first['pin_hash'], $first['order_id']), 'Private response contract wrong.');
[$code, $payload, $response] = pinAction($owner, $uid);
pinCheck($code === 429 && (int) $response->getHeaderLine('Retry-After') > 0 && ! str_contains(json_encode($payload), $first['pin']), 'Cooldown leaked PIN or failed.');
pinCheck($db->table('order_delivery_pins')->countAllResults() === 1, 'Duplicate issuance inserted rows.');
$db->table('order_delivery_pins')->where('order_id', $orderIds[0])->update(['issued_at' => date('Y-m-d H:i:s', time() - 61)]);
$rotated = pinAction($owner, $uid); pinCheck($rotated[0] === 200, 'Rotation failed.');
$row = $db->table('order_delivery_pins')->get()->getRowArray();
pinCheck(password_verify($rotated[1]['data']['pin'], $row['pin_hash']) && ! password_verify($first['pin'], $row['pin_hash']), 'Old PIN survived replacement.');
// Inject a competing issuance between order lock and the PIN current read.
$db->table('order_delivery_pins')->where('order_id', $orderIds[0])->delete();
$interleave = true;
$listener = static function ($query) use ($db, $orderIds, &$interleave): void {
    if ($interleave && str_contains((string) $query, 'FOR UPDATE') && str_contains((string) $query, '`orders`')) {
        $interleave = false;
        $hash = $db->connID->real_escape_string(password_hash('000001', PASSWORD_DEFAULT));
        $db->connID->query("INSERT INTO order_delivery_pins (order_id,pin_hash,issued_at,expires_at) VALUES (" . (int) $orderIds[0] . ", '" . $hash . "', '" . date('Y-m-d H:i:s') . "', '" . date('Y-m-d H:i:s', time() + 1800) . "')");
    }
};
CodeIgniter\Events\Events::on('DBQuery', $listener);
try { pinCheck(pinAction($owner, $uid)[0] === 429 && ! $interleave, 'Competing issuance not rechecked.'); }
finally { CodeIgniter\Events\Events::removeListener('DBQuery', $listener); }
// Failed insert must leave no PIN and no changed order state.
$db->query('ALTER TABLE order_delivery_pins CHANGE pin_hash fixture_hash VARCHAR(255) NOT NULL');
try {
    pinCheck(pinAction($owner, $uid)[0] === 503, 'Storage failure not handled.');
    pinCheck($db->table('order_delivery_pins')->countAllResults() === 0 && $orders->find($orderIds[0])['order_status'] === 'out_for_delivery', 'Storage failure partially committed.');
} finally { $db->resetTransStatus(); $db->query('ALTER TABLE order_delivery_pins CHANGE fixture_hash pin_hash VARCHAR(255) NOT NULL'); }
// PIN values/hashes are absent from the server-rendered page; button only for eligible orders.
$html = view('client/orders/index', ['orders' => $orders->findAll(), 'isLoggedIn' => true]);
pinCheck(substr_count($html, 'data-pin-generate') === 1 && ! str_contains($html, $first['pin']), 'Page exposed PIN or wrong order controls.');
session_destroy();
echo "PASS: delivery PIN ownership/roles/status/input checks, hash-only storage, expiry, regeneration cooldown, rotation, simulated competing issuance, atomic rollback and safe customer view. Temporary SQL only.\n";
