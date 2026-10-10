<?php
// Real SQL and service tests on connection-local temporary copies only.
define('ENVIRONMENT', 'development');
define('FCPATH', dirname(__DIR__, 2) . '/public/');
require dirname(__DIR__, 2) . '/app/Config/Paths.php';
$paths = new Config\Paths();
require $paths->systemDirectory . '/Boot.php';
CodeIgniter\Boot::bootConsole($paths);
$db = db_connect();
if ($db->DBDriver !== 'MySQLi' || $db->DBPrefix !== '') throw new RuntimeException('Use local MySQL without a prefix.');
foreach (['orders', 'order_items', 'order_status_history', 'delivery_assignments', 'users', 'delivery_completions'] as $table) {
    $definition = $db->query("SHOW CREATE TABLE {$table}")->getRowArray()['Create Table'];
    $definition = str_replace('CREATE TABLE', 'CREATE TEMPORARY TABLE', $definition);
    // MySQL temporary tables do not support foreign keys; preserve columns and indexes.
    $definition = preg_replace('/^\s*CONSTRAINT .* FOREIGN KEY .*\n/m', '', $definition);
    $definition = preg_replace('/,\s*\)/', ')', $definition);
    $db->query($definition);
}
function orderCheck(bool $condition, string $message): void { if (! $condition) throw new RuntimeException($message); }
config('App')->appTimezone = 'UTC';
$orders = new App\Models\OrderModel($db);
function fixtureOrder(App\Models\OrderModel $orders, string $number, string $status, string $created, string $name = 'Fixture Customer'): int {
    $id = (int) $orders->skipValidation(true)->insert(['order_number' => $number, 'user_id' => 1, 'subtotal' => 100, 'discount_amount' => 5, 'delivery_charge' => 40, 'total_amount' => 135, 'payment_method' => 'cod', 'payment_status' => 'pending', 'order_status' => $status, 'customer_name' => $name, 'customer_phone' => '9000000000', 'address_line' => 'Fixture address', 'city' => 'Fixture city', 'state' => 'Fixture state', 'postal_code' => '700001', 'notes' => '<b>fixture note</b>', 'created_at' => $created, 'updated_at' => $created]);
    db_connect()->table('orders')->where('id', $id)->update(['created_at' => $created, 'updated_at' => $created]);
    return $id;
}
$one = fixtureOrder($orders, 'SW_FIXTURE_A', 'pending', '2026-10-08 18:30:00', 'Asha');
$two = fixtureOrder($orders, 'SW_FIXTURE_B', 'preparing', '2026-10-09 18:29:59', 'Bimal');
$three = fixtureOrder($orders, 'SW_FIXTURE_C', 'pending', '2026-10-09 18:30:00', 'Asha');
$db->table('users')->insertBatch([['id' => 1, 'uid' => 'usr_fixture_1', 'name' => 'Fixture Admin', 'role' => 'admin', 'status' => 'active'], ['id' => 2, 'uid' => 'usr_fixture_2', 'name' => 'Fixture Rider', 'role' => 'delivery', 'status' => 'active']]);
(new App\Models\OrderItemModel($db))->skipValidation(true)->insert(['order_id' => $one, 'product_name' => '<img src=x>', 'unit' => 'kg', 'quantity' => 2, 'unit_price' => 50, 'total_price' => 100, 'created_at' => '2026-10-09 00:00:00']);
(new App\Models\DeliveryAssignmentModel($db))->skipValidation(true)->insert(['order_id' => $one, 'delivery_user_id' => 2, 'assigned_by' => 1, 'assigned_at' => '2026-10-09 00:00:00']);
$service = new App\Services\AdminOrderService(new App\Models\AdminOrderModel($db));
$list = $service->listing(['page' => 1, 'per_page' => 1, 'search' => 'Asha', 'status' => 'pending', 'from' => '2026-10-09', 'to' => '2026-10-09']);
orderCheck($list['pager']['total'] === 1 && (int) $list['items'][0]['id'] === $one, 'Search/status/India date boundaries must combine correctly.');
orderCheck($list['items'][0]['item_count'] === 1 && $list['items'][0]['assigned_delivery_name'] === 'Fixture Rider', 'Batched counts and assignment missing.');
$sorted = $service->listing(['page' => 2, 'per_page' => 1, 'sort' => 'order_number', 'dir' => 'asc']);
orderCheck($sorted['pager']['page_count'] === 3 && (int) $sorted['items'][0]['id'] === $two, 'Pagination/sorting incorrect.');
orderCheck($service->listing(['search' => "' OR 1=1 --"])['pager']['total'] === 0, 'Search must be escaped.');
$uid = $orders->find($one)['uid'];
$details = $service->details($uid);
orderCheck($details['items'][0]['product_name'] === '<img src=x>' && $details['items'][0]['unit_price'] === 50.0, 'Historical item snapshots missing.');
orderCheck($details['allowed_actions'] === ['confirmed', 'cancelled'] && $details['assignments'][0]['delivery_name'] === 'Fixture Rider', 'Actions or assignment details incorrect.');
orderCheck($service->details('ord_missing') === null, 'Missing order should not be exposed.');
$transitions = new App\Services\OrderService();
orderCheck($transitions->changeStatus($one, 'confirmed', 1, 'Confirmed fixture', 'pending'), 'Valid transition failed.');
orderCheck($orders->find($one)['order_status'] === 'confirmed' && $db->table('order_status_history')->where('order_id', $one)->countAllResults() === 1, 'Status and history must both persist.');
try { $transitions->changeStatus($one, 'confirmed', 1, null, 'pending'); throw new RuntimeException('Stale duplicate accepted.'); } catch (App\Services\OrderConflictException) {}
try { $transitions->changeStatus($one, 'delivered', 1); throw new RuntimeException('Invalid transition accepted.'); } catch (InvalidArgumentException) {}
orderCheck($db->table('order_status_history')->where('order_id', $one)->countAllResults() === 1, 'Rejected updates must not create history.');
$transitions->changeStatus($one, 'preparing', 1);
$transitions->changeStatus($one, 'ready_for_delivery', 1);
orderCheck($service->details($uid)['allowed_actions'] === [], 'Ready orders must not expose future dispatch/completion UI actions.');
try { $transitions->changeStatus($one, 'cancelled', 1); throw new RuntimeException('Late cancellation accepted.'); } catch (InvalidArgumentException) {}
$transitions->changeStatus($one, 'out_for_delivery', 2);
try { $transitions->changeStatus($one, 'delivered', 2); throw new RuntimeException('Unverified delivery accepted.'); } catch (InvalidArgumentException) {}
$transitions->changeStatus($one, 'delivery_failed', 2, 'Customer unavailable.');
orderCheck($orders->find($one)['order_status'] === 'delivery_failed', 'Failed delivery transition must remain supported.');
$cancel = fixtureOrder($orders, 'SW_FIXTURE_CANCEL', 'pending', '2026-10-09 10:00:00');
$transitions->changeStatus($cancel, 'cancelled', 1, 'Cancelled fixture', 'pending');
orderCheck($orders->find($cancel)['order_status'] === 'cancelled', 'Permitted cancellation must succeed.');
$rollback = fixtureOrder($orders, 'SW_FIXTURE_ROLLBACK', 'pending', '2026-10-09 10:00:00');
$db->query('ALTER TABLE order_status_history CHANGE notes fixture_unavailable_notes TEXT NULL');
try { $transitions->changeStatus($rollback, 'confirmed', 1); throw new RuntimeException('History failure accepted.'); } catch (RuntimeException $error) { orderCheck($error->getMessage() !== 'History failure accepted.', 'History failure must be reported.'); }
orderCheck($orders->find($rollback)['order_status'] === 'pending', 'History failure must roll back the status update.');
$db->query('ALTER TABLE order_status_history CHANGE fixture_unavailable_notes notes TEXT NULL');
$db->resetTransStatus();
$race = fixtureOrder($orders, 'SW_FIXTURE_RACE', 'pending', '2026-10-09 10:00:00');
$interleave = true;
$listener = static function ($query) use ($db, $race, &$interleave): void {
    $sql = (string) $query;
    if ($interleave && str_starts_with($sql, 'SELECT') && str_contains($sql, '`orders`') && str_contains($sql, (string) $race)) {
        $interleave = false;
        // Simulate a competing write after the SELECT snapshot, before the CAS UPDATE.
        $db->connID->query("UPDATE orders SET order_status = 'confirmed' WHERE id = " . (int) $race);
    }
};
CodeIgniter\Events\Events::on('DBQuery', $listener);
try { $transitions->changeStatus($race, 'cancelled', 1, null, 'pending'); throw new RuntimeException('Competing write overwritten.'); } catch (App\Services\OrderConflictException) {} finally { CodeIgniter\Events\Events::removeListener('DBQuery', $listener); }
orderCheck(! $interleave && $orders->find($race)['order_status'] === 'confirmed', 'Compare-and-set must preserve the competing write.');
orderCheck($db->table('order_status_history')->where('order_id', $race)->countAllResults() === 0, 'Conflict must not create misleading history.');
echo "PASS: order search, filtering, pagination, snapshots, assignments, valid/invalid transitions, duplicate/stale requests, atomic rollback, and simulated competing write. Persistent data unchanged.\n";


