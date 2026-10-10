<?php
// Real SQL/controllers; connection-local temporary tables preserve all application records.
define('ENVIRONMENT', 'development');
define('FCPATH', dirname(__DIR__, 2) . '/public/');
require dirname(__DIR__, 2) . '/app/Config/Paths.php';
$paths = new Config\Paths();
require $paths->systemDirectory . '/Boot.php';
CodeIgniter\Boot::bootConsole($paths);
service('session'); session_start();
$_SERVER['REMOTE_ADDR'] = '127.0.0.1'; config('Otp')->developmentMode = true;
$db = db_connect();
if ($db->DBDriver !== 'MySQLi' || $db->DBPrefix !== '') throw new RuntimeException('Use local MySQL without prefix.');
foreach (['users', 'orders', 'delivery_assignments', 'delivery_personnel_profiles', 'otp_rate_limits'] as $table) {
    $definition = $db->query("SHOW CREATE TABLE {$table}")->getRowArray()['Create Table'];
    $definition = str_replace('CREATE TABLE', 'CREATE TEMPORARY TABLE', $definition);
    $definition = preg_replace('/^\s*CONSTRAINT .* FOREIGN KEY .*\n/m', '', $definition);
    $db->query(preg_replace('/,\s*\)/', ')', $definition));
}
function riderCheck(bool $condition, string $message): void { if (! $condition) throw new RuntimeException($message); }
function riderAction(string $method, array $input = [], ?string $uid = null, array $query = []): array {
    service('validation')->reset();
    $request = new CodeIgniter\HTTP\IncomingRequest(config('App'), new CodeIgniter\HTTP\URI('http://localhost/'), json_encode($input), new CodeIgniter\HTTP\UserAgent());
    $request->setGlobal('get', $query);
    $controller = new App\Controllers\Api\V1\Admin\DeliveryPersonnelController();
    $controller->initController($request, new CodeIgniter\HTTP\Response(config('App')), service('logger'));
    $response = $uid ? $controller->$method($uid) : $controller->$method();
    return [$response->getStatusCode(), json_decode($response->getBody(), true)];
}
$users = new App\Models\UserModel();
$customer = $users->insert(['name' => 'Customer', 'phone' => '9876500000', 'role' => 'customer', 'status' => 'active']);
$manager = $users->insert(['name' => 'Manager', 'phone' => '9876500001', 'role' => 'admin', 'status' => 'active']);
$one = $users->insert(['name' => '<img src=x>', 'phone' => '9876500002', 'email' => 'rider@example.test', 'role' => 'delivery', 'status' => 'active']);
$two = $users->insert(['name' => 'Second rider', 'phone' => '9876500003', 'role' => 'delivery', 'status' => 'inactive']);
$uid = $users->find($one)['uid']; $secondUid = $users->find($two)['uid'];
$orders = new App\Models\OrderModel(); $assignments = new App\Models\DeliveryAssignmentModel();
$orderIds = [];
foreach (['ready_for_delivery','out_for_delivery','delivered','cancelled','delivery_failed','preparing'] as $index => $status) {
    $orderIds[] = $id = $orders->skipValidation(true)->insert(['user_id' => $customer, 'order_number' => 'RIDER_FIXTURE_' . $index, 'subtotal' => 100, 'total_amount' => 100, 'payment_method' => 'cod', 'payment_status' => 'pending', 'order_status' => $status, 'customer_name' => 'PRIVATE CUSTOMER', 'customer_phone' => '9999999999', 'address_line' => 'PRIVATE ADDRESS', 'city' => 'Fixture', 'postal_code' => '700001']);
    $assignments->insert(['order_id' => $id, 'delivery_user_id' => $one, 'assigned_by' => $manager, 'assigned_at' => '2026-10-09 01:00:00']);
}
// Repeated reassignment must neither duplicate history nor inflate the original rider's workload.
foreach ([$two, $two] as $riderId) $assignments->insert(['order_id' => $orderIds[5], 'delivery_user_id' => $riderId, 'assigned_by' => $manager, 'assigned_at' => '2026-10-09 02:00:00']);
[$status,$data] = riderAction('index', [], null, ['per_page' => '1']);
riderCheck($status === 200 && $data['data']['pager']['total'] === 2 && count($data['data']['items']) === 1, 'Role filter/pagination failed.');
$row = $data['data']['items'][0];
riderCheck($row['uid'] === $uid && $row['workload'] === 2 && $row['out_for_delivery'] === 1 && $row['completed_deliveries'] === 1 && $row['availability'] === 'busy' && $row['base_availability'] === 'offline', 'Latest ownership/workload/completed/derived busy failed.');
riderCheck(! isset($row['id'], $row['phone'], $row['email'], $row['password_hash']), 'Private fields leaked in list.');
foreach (['+91 98765 00002', '<img src=x>'] as $search) { [$status,$data] = riderAction('index', [], null, ['search' => $search]); riderCheck($status === 200 && $data['data']['pager']['total'] === 1, 'Search failed.'); }
[$status,$data] = riderAction('show', [], $uid, ['mode' => 'current', 'per_page' => '1', 'page' => '2']);
riderCheck($status === 200 && $data['data']['orders']['pager']['total'] === 2 && count($data['data']['orders']['items']) === 1, 'Current assignments pagination failed.');
riderCheck(!str_contains(json_encode($data), 'PRIVATE ADDRESS') && !str_contains(json_encode($data), 'PRIVATE CUSTOMER') && !isset($data['data']['personnel']['id']), 'History disclosed customer secrets.');
[$status,$data] = riderAction('show', [], $uid, ['mode' => 'history']);
riderCheck($data['data']['orders']['pager']['total'] === 6 && count($data['data']['orders']['items']) === 6, 'Reassignments duplicated history.');
riderCheck($data['data']['orders']['items'][0]['is_current'] === false, 'Historical ownership not marked.');
foreach ([$users->find($customer)['uid'], $users->find($manager)['uid'], 'usr_missing'] as $other) {
    riderCheck(riderAction('show', [], $other)[0] === 404, 'Non-rider details exposed.');
    riderCheck(riderAction('updateStatus', ['status' => 'inactive', 'expected_status' => 'active'], $other)[0] === 404, 'Non-rider changed.');
}
foreach ([['page'=>'0'],['per_page'=>'101'],['status'=>'bad']] as $query) riderCheck(riderAction('index', [], null, $query)[0] === 422, 'Invalid query accepted.');
riderCheck(riderAction('show', [], $uid, ['mode'=>'invalid'])[0] === 422, 'Invalid history mode accepted.');
$valid = ['name' => 'New rider', 'phone' => '+91 98765 00004', 'email' => 'NEW@EXAMPLE.TEST'];
foreach ([$valid+['role'=>'admin'], $valid+['status'=>'active'], $valid+['uid'=>'usr_injected'], $valid+['password_hash'=>'x'], ['name'=>'x','phone'=>['x']], ['name'=>'x','phone'=>'9999876500004'], ['name'=>'x','phone'=>'1234567890'], ['name'=>'x','phone'=>'9876500000'], ['name'=>'x','phone'=>'9876500004','email'=>'bad']] as $invalid) riderCheck(riderAction('create', $invalid)[0] === 422, 'Unsafe/duplicate account input accepted.');
[$status,$data] = riderAction('create', $valid);
riderCheck($status === 201, 'Account creation failed: ' . json_encode($data));
$newUid = $data['data']['uid']; $new = $users->where('uid', $newUid)->first();
riderCheck($new['role'] === 'delivery' && $new['status'] === 'inactive' && $new['phone'] === '9876500004' && $new['email'] === 'new@example.test' && $new['password_hash'] === null, 'Creation authoritative fields/normalization failed.');
riderCheck($db->table('delivery_personnel_profiles')->where('user_id',$new['id'])->countAllResults() === 1, 'Account missing profile.');
riderCheck(riderAction('updateAvailability', ['availability'=>'available','expected_availability'=>'offline'], $newUid)[0] === 409, 'Inactive rider availability changed.');
riderCheck(riderAction('updateStatus', ['status'=>'active','expected_status'=>'inactive'], $newUid)[0] === 200, 'Activation failed.');
riderCheck(riderAction('updateAvailability', ['availability'=>'assigned','expected_availability'=>'offline'], $newUid)[0] === 422, 'Derived state manually accepted.');
riderCheck(riderAction('updateAvailability', ['availability'=>'available','expected_availability'=>'offline'], $newUid)[0] === 200, 'Availability failed.');
riderCheck(riderAction('updateAvailability', ['availability'=>'unavailable','expected_availability'=>'offline'], $newUid)[0] === 409, 'Stale availability accepted.');
riderCheck(riderAction('updateStatus', ['status'=>'inactive','expected_status'=>'active','role'=>'admin'], $newUid)[0] === 422, 'Extra write accepted.');
$auth = new App\Services\AuthService(); $otp = $auth->startPhoneOtp('9876500004');
riderCheck($auth->verifyPhoneOtp('9876500004', $otp['dev_otp']) && session('user_role') === 'delivery', 'Existing OTP login not compatible.');
riderCheck(riderAction('updateStatus', ['status'=>'inactive','expected_status'=>'active'], $newUid)[0] === 200, 'Deactivation failed.');
(new App\Services\ActiveSessionService())->validate();riderCheck(!session('is_logged_in'), 'Inactive rider session retained access.');
riderCheck($db->table('delivery_personnel_profiles')->where('user_id',$new['id'])->get()->getRowArray()['availability'] === 'offline', 'Deactivation did not reset availability.');
riderCheck(riderAction('updateStatus', ['status'=>'active','expected_status'=>'active'], $newUid)[0] === 409, 'Stale status accepted.');
riderCheck(riderAction('updateStatus', ['status'=>'active','expected_status'=>'inactive'], $newUid)[0] === 200, 'Reactivation failed.');
riderCheck(!session('is_logged_in'), 'Reactivation restored old session.');
$db->table('otp_rate_limits')->set('last_used_at', time()-60)->update();$otp = $auth->startPhoneOtp('9876500004');riderCheck($auth->verifyPhoneOtp('9876500004',$otp['dev_otp']), 'Fresh OTP login failed.');
// Deactivation keeps active assignments/history, and rolls back if the profile write fails.
riderCheck(riderAction('updateStatus', ['status'=>'inactive','expected_status'=>'active'], $uid)[0] === 200, 'Legacy rider deactivation failed.');
riderCheck($assignments->countAllResults() === 8 && $orders->countAllResults() === 6, 'Deactivation destroyed fulfilment data.');
riderCheck(riderAction('show', [], $uid)[1]['data']['personnel']['availability'] === 'unavailable', 'Inactive state priority failed.');
riderAction('updateStatus', ['status'=>'active','expected_status'=>'inactive'], $uid);
$db->table('orders')->where('id',$orderIds[1])->update(['order_status'=>'delivered']);
riderCheck(riderAction('show', [], $uid)[1]['data']['personnel']['availability'] === 'assigned', 'Assigned derived state failed.');
$db->table('orders')->where('id',$orderIds[0])->update(['order_status'=>'delivered']);
riderCheck(riderAction('show', [], $uid)[1]['data']['personnel']['completed_deliveries'] === 3, 'Legacy completion count failed.');
$before = $users->countAllResults();
// Force a profile failure without DDL inside the critical transaction.
$db->query('ALTER TABLE delivery_personnel_profiles CHANGE availability fixture_availability VARCHAR(20) NOT NULL');
try {
    riderCheck(riderAction('create', ['name'=>'Rollback rider','phone'=>'9876500005'])[0] === 503, 'Profile failure did not fail creation.');
    riderCheck($users->countAllResults() === $before, 'Failed creation left orphan account.');
    $db->resetTransStatus();
    riderCheck(riderAction('updateStatus', ['status'=>'inactive','expected_status'=>'active'], $uid)[0] === 503, 'Profile failure did not fail deactivation.');
    riderCheck($users->find($one)['status'] === 'active', 'Failed deactivation partially committed.');
} finally { $db->resetTransStatus(); $db->query('ALTER TABLE delivery_personnel_profiles CHANGE fixture_availability availability VARCHAR(20) NOT NULL'); }
$auth->logout();session_destroy();
echo "PASS: delivery-only queries, latest ownership/workload/history, safe account creation, validation/privacy, stale writes, availability, session revocation, preserved assignments and transaction rollback. Temporary SQL only.\n";
