<?php
// Connection-local temporary SQL only; never apply migrations to the real database.
define('ENVIRONMENT', 'development'); define('FCPATH', dirname(__DIR__, 2) . '/public/');
require dirname(__DIR__, 2) . '/app/Config/Paths.php'; $paths = new Config\Paths(); require $paths->systemDirectory . '/Boot.php';
CodeIgniter\Boot::bootConsole($paths); $db = db_connect(); date_default_timezone_set(config('App')->appTimezone);
if ($db->DBDriver !== 'MySQLi' || $db->DBPrefix !== '' || !in_array($db->hostname, ['localhost', '127.0.0.1', '::1'], true)) throw new RuntimeException('Use local unprefixed MySQL.');
foreach (['users', 'orders', 'delivery_assignments', 'order_status_history'] as $table) {
    $ddl = $db->query('SHOW CREATE TABLE ' . $table)->getRowArray()['Create Table'];
    $ddl = str_replace('CREATE TABLE', 'CREATE TEMPORARY TABLE', $ddl);
    $ddl = preg_replace('/^\s*CONSTRAINT .* FOREIGN KEY .*\n/m', '', $ddl);
    $db->query(preg_replace('/,\s*\)/', ')', $ddl));
}
$ddl = file_get_contents(dirname(__DIR__, 2) . '/deployment/add-delivery-locations.sql');
$ddl = str_replace('CREATE TABLE', 'CREATE TEMPORARY TABLE', $ddl);
$ddl = preg_replace('/^  FOREIGN KEY .*\n/m', '', $ddl); $db->query(preg_replace('/,\s*\)/', ')', $ddl));
$db->listTables(); $db->dataCache['table_names'][] = 'delivery_locations';
function locationCheck(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
function locationReject(callable $fn, string $class): void { try { $fn(); } catch (Throwable $e) { locationCheck($e instanceof $class, 'Wrong rejection: ' . $e::class); return; } throw new RuntimeException('Expected rejection: ' . $class); }
$users = new App\Models\UserModel(); $ids = [];
foreach (['admin', 'delivery', 'delivery', 'customer'] as $i => $role) $ids[] = $users->insert(['name' => 'Fixture ' . $i, 'phone' => '987654002' . $i, 'role' => $role, 'status' => 'active']);
[$admin, $rider, $other, $customer] = $ids;
$orders = new App\Models\OrderModel();
$id = $orders->skipValidation(true)->insert(['order_number' => 'LOCATION_FIXTURE', 'user_id' => $customer, 'subtotal' => 100, 'total_amount' => 100, 'payment_method' => 'cod', 'payment_status' => 'pending', 'order_status' => 'out_for_delivery', 'customer_name' => 'Fixture', 'customer_phone' => '9999999999', 'address_line' => 'Fixture', 'city' => 'Fixture']);
$assignments = new App\Models\DeliveryAssignmentModel();
$aid = $assignments->insert(['order_id' => $id, 'delivery_user_id' => $rider, 'assigned_by' => $admin, 'assigned_at' => date('Y-m-d H:i:s')]);
$now = (int) floor(microtime(true) * 1000);
$sample = ['order_uid' => $orders->find($id)['uid'], 'assignment_uid' => $assignments->find($aid)['uid'], 'latitude' => 0, 'longitude' => 0, 'accuracy' => 15, 'captured_at_ms' => $now];
$service = new App\Services\DeliveryLocationService();
foreach ([['latitude' => '0'], ['latitude' => 91], ['longitude' => -181], ['accuracy' => 101], ['accuracy' => -1], ['captured_at_ms' => $now - 60001], ['captured_at_ms' => $now + 10001], ['delivery_user_id' => $other], ['latitude' => NAN], ['accuracy' => INF]] as $override) locationReject(fn () => $service->record($rider, array_replace($sample, $override), $now), InvalidArgumentException::class);
foreach ([$admin, $customer, $other, 99999999] as $actor) locationReject(fn () => $service->record($actor, $sample, $now), OutOfBoundsException::class);
$db->table('users')->where('id', $rider)->update(['status' => 'inactive']);
locationReject(fn () => $service->record($rider, $sample, $now), OutOfBoundsException::class);
$db->table('users')->where('id', $rider)->update(['status' => 'active']);
$result = $service->record($rider, $sample, $now); locationCheck(!$result['duplicate'], 'First sample not stored.');
locationCheck($service->record($rider, $sample, $now)['duplicate'], 'Duplicate not idempotent.');
locationReject(fn () => $service->record($rider, array_replace($sample, ['latitude' => 1]), $now), App\Services\OrderConflictException::class);
locationReject(fn () => $service->record($rider, array_replace($sample, ['captured_at_ms' => $now - 1]), $now), App\Services\OrderConflictException::class);
locationReject(fn () => $service->record($rider, array_replace($sample, ['captured_at_ms' => $now + 1]), $now + 1), App\Services\DeliveryLocationRateLimitException::class);
$service->stop($rider); locationCheck($db->table('delivery_locations')->get()->getRowArray()['latitude'] === null, 'Stop retained precise GPS.');
locationReject(fn () => $service->record($rider, array_replace($sample, ['captured_at_ms' => $now + 2]), $now + 2), App\Services\DeliveryLocationRateLimitException::class);
$later = array_replace($sample, ['captured_at_ms' => $now + 16000, 'latitude' => 1]); $service->record($rider, $later, $now + 16000);
locationCheck($db->table('delivery_locations')->countAllResults() === 1, 'Unlimited history was stored.');
(new App\Services\OrderService())->changeStatus($id, 'delivery_failed', $rider, 'Fixture test', 'out_for_delivery');
locationCheck($db->table('delivery_locations')->get()->getRowArray()['latitude'] === null, 'Terminal status did not clear GPS.');
locationReject(fn () => $service->record($rider, $later, $now + 16000), App\Services\OrderConflictException::class);
$db->table('orders')->where('id', $id)->update(['order_status' => 'out_for_delivery']);
$assignments->insert(['order_id' => $id, 'delivery_user_id' => $other, 'assigned_by' => $admin, 'assigned_at' => date('Y-m-d H:i:s')]);
locationReject(fn () => $service->record($rider, $later, $now + 16000), OutOfBoundsException::class);
locationReject(fn () => $service->record($other, $later, $now + 16000), App\Services\OrderConflictException::class);
$db->table('delivery_locations')->update(['expires_at' => gmdate('Y-m-d H:i:s', time() - 1)]);
$expired=[];for($i=0;$i<1001;$i++)$expired[]=['delivery_user_id'=>10000+$i,'order_id'=>$id,'assignment_id'=>$aid,'latitude'=>1,'longitude'=>1,'accuracy'=>15,'captured_at_ms'=>$now,'recorded_at'=>gmdate('Y-m-d H:i:s',time()-3600),'expires_at'=>gmdate('Y-m-d H:i:s',time()-1)];
$db->table('delivery_locations')->insertBatch($expired);
(new App\Commands\PruneDeliveryLocations(service('logger'), service('commands')))->run([]);
locationCheck($db->table('delivery_locations')->countAllResults() === 0, 'Expiration pruning left a backlog larger than one deletion batch.');
echo "PASS: location validation/freshness, actor/current-assignment authorization, duplicate conflicts, rate limiting including stop/restart, latest-only storage, terminal cleanup, reassignment and retention pruning. Temporary SQL only.\n";
