<?php
// Real controller, SQL and session tests on connection-local temporary tables.
define('ENVIRONMENT', 'development');
define('FCPATH', dirname(__DIR__, 2) . '/public/');
require dirname(__DIR__, 2) . '/app/Config/Paths.php';
$paths = new Config\Paths();
require $paths->systemDirectory . '/Boot.php';
CodeIgniter\Boot::bootConsole($paths);
service('session');
session_start();
$db = db_connect();
if ($db->DBDriver !== 'MySQLi' || $db->DBPrefix !== '') throw new RuntimeException('Use local MySQL without a prefix.');
foreach (['users', 'orders'] as $table) {
    $definition = $db->query("SHOW CREATE TABLE {$table}")->getRowArray()['Create Table'];
    $definition = str_replace('CREATE TABLE', 'CREATE TEMPORARY TABLE', $definition);
    $definition = preg_replace('/^\s*CONSTRAINT .* FOREIGN KEY .*\n/m', '', $definition);
    $db->query(preg_replace('/,\s*\)/', ')', $definition));
}
function customerCheck(bool $condition, string $message): void {if (! $condition) throw new RuntimeException($message);}
function customerAction(string $method, array $input = [], ?string $uid = null, array $query = []): array {
    service('validation')->reset();
    $request = new CodeIgniter\HTTP\IncomingRequest(config('App'), new CodeIgniter\HTTP\URI('http://localhost/'), json_encode($input), new CodeIgniter\HTTP\UserAgent());
    $request->setGlobal('get', $query);
    $controller = new App\Controllers\Api\V1\Admin\CustomerController();
    $controller->initController($request, new CodeIgniter\HTTP\Response(config('App')), service('logger'));
    $response = $uid ? $controller->$method($uid) : $controller->$method();
    return [$response->getStatusCode(), json_decode($response->getBody(), true)];
}
$users = new App\Models\UserModel();
$one = $users->skipValidation(true)->insert(['name' => '<img src=x>', 'email' => 'fixture@example.test', 'phone' => '9876543210', 'role' => 'customer', 'status' => 'active', 'password_hash' => 'HASH_MUST_NEVER_LEAK']);
$two = $users->skipValidation(true)->insert(['name' => 'Fixture B', 'phone' => '9876500000', 'role' => 'customer', 'status' => 'inactive']);
$manager = $users->skipValidation(true)->insert(['name' => 'Manager fixture', 'phone' => '9876500001', 'role' => 'admin', 'status' => 'active']);
$rider = $users->skipValidation(true)->insert(['name' => 'Rider fixture', 'phone' => '9876500002', 'role' => 'delivery', 'status' => 'active']);
$uid = $users->find($one)['uid'];
$db->table('users')->where('id', $one)->update(['created_at' => '2026-10-08 18:30:00']);
config('App')->appTimezone = 'UTC';
$orders = new App\Models\OrderModel();
foreach ([$one, $one, $two] as $index => $id) {
    $orders->skipValidation(true)->insert(['user_id' => $id, 'order_number' => 'FIXTURE_' . $index, 'subtotal' => 100, 'total_amount' => 100, 'payment_method' => 'cod', 'payment_status' => 'pending', 'order_status' => 'pending', 'customer_name' => 'Snapshot name', 'customer_phone' => '9999999999', 'address_line' => 'PRIVATE_ADDRESS', 'city' => 'Fixture', 'postal_code' => '700001']);
}
[$status, $data] = customerAction('index', [], null, ['per_page' => '1', 'page' => '1', 'sort' => 'name', 'dir' => 'asc']);
customerCheck($status === 200 && $data['data']['pager']['total'] === 2 && count($data['data']['items']) === 1, 'Only customers should be listed and paginated.');
$row = $data['data']['items'][0];
customerCheck($row['uid'] === $uid && $row['order_count'] === 2 && str_ends_with($row['phone_masked'], '3210'), 'Count/masked phone failed.');
customerCheck(!isset($row['email'], $row['phone'], $row['password_hash'], $row['id']) && !str_contains(json_encode($data), 'HASH_MUST_NEVER_LEAK'), 'List leaked private fields.');
foreach (['9876543210', '+91 98765 43210', '<img src=x>'] as $search) {
    [$status,$data] = customerAction('index', [], null, ['search' => $search]);
    customerCheck($status === 200 && $data['data']['pager']['total'] === 1, 'Name/phone search failed.');
}
[$status,$data] = customerAction('index', [], null, ['status' => 'inactive']);customerCheck($data['data']['pager']['total'] === 1, 'Status filter failed.');
[$status,$data] = customerAction('index', [], null, ['search' => "' OR 1=1 --"]);customerCheck($data['data']['pager']['total'] === 0, 'Search escaping failed.');
foreach (['page' => '-1', 'per_page' => '101', 'status' => 'deleted', 'sort' => 'password_hash', 'dir' => 'invalid'] as $field => $invalid) {
    [$status] = customerAction('index', [], null, [$field => $invalid]);customerCheck($status === 422, 'Invalid filter accepted.');
}
[$status,$data] = customerAction('show', [], $uid, ['per_page' => '1', 'page' => '2']);
customerCheck($status === 200 && $data['data']['customer']['phone'] === '9876543210' && $data['data']['customer']['order_count'] === 2 && count($data['data']['orders']['items']) === 1, 'Details/history pagination failed.');
customerCheck($data['data']['customer']['registered_at'] === '2026-10-08T18:30:00+00:00', 'Timestamp conversion failed.');
customerCheck(!str_contains(json_encode($data), 'HASH_MUST_NEVER_LEAK') && !str_contains(json_encode($data), 'PRIVATE_ADDRESS') && !isset($data['data']['customer']['id']), 'Details leaked unnecessary fields.');
foreach ([$users->find($manager)['uid'], $users->find($rider)['uid'], 'usr_missing'] as $other) {
    [$status] = customerAction('show', [], $other);customerCheck($status === 404, 'Privileged/missing account exposed.');
    [$status] = customerAction('updateStatus', ['status' => 'inactive', 'expected_status' => 'active'], $other);customerCheck($status === 404, 'Privileged/missing account changed.');
}
[$status] = customerAction('updateStatus', ['status' => 'inactive', 'expected_status' => 'active', 'role' => 'admin'], $uid);customerCheck($status === 422, 'Role injection accepted.');
[$status] = customerAction('updateStatus', ['status' => 'inactive'], $uid);customerCheck($status === 422, 'Missing observed status accepted.');
[$status] = customerAction('updateStatus', ['status' => 'inactive', 'expected_status' => 'active'], $uid);customerCheck($status === 200 && $users->find($one)['status'] === 'inactive', 'Deactivate failed.');
[$status] = customerAction('updateStatus', ['status' => 'active', 'expected_status' => 'active'], $uid);customerCheck($status === 409, 'Stale action accepted.');
customerCheck($db->table('orders')->where('user_id', $one)->countAllResults() === 2, 'Account action altered orders.');
session()->set(['user_id' => $one, 'user_uid' => $uid, 'user_role' => 'customer', 'is_logged_in' => true, 'auth_otp' => ['code' => '123456'], 'checkout_otp' => ['code' => '123456']]);
(new App\Services\ActiveSessionService())->validate();
customerCheck(!session('is_logged_in') && !session('auth_otp') && !session('checkout_otp'), 'Inactive session retained access or OTP state.');
$auth = new App\Services\AuthService();$otp = $auth->startPhoneOtp('9876543210');
customerCheck(!$auth->verifyPhoneOtp('9876543210', $otp['dev_otp']), 'Inactive customer logged in.');
customerAction('updateStatus', ['status' => 'active', 'expected_status' => 'inactive'], $uid);
customerCheck($auth->verifyPhoneOtp('9876543210', $otp['dev_otp']), 'Reactivated customer cannot log in.');
(new App\Services\ActiveSessionService())->validate();customerCheck((bool)session('is_logged_in'), 'Active customer session rejected.');
$db->table('users')->where('id', $one)->update(['name' => 'Updated fixture name']);
(new App\Services\ActiveSessionService())->validate();customerCheck(session('user_name') === 'Updated fixture name', 'Session profile was not refreshed.');
$db->query('ALTER TABLE users CHANGE status fixture_unavailable_status VARCHAR(20) NOT NULL');
try {
    $request = new CodeIgniter\HTTP\IncomingRequest(config('App'), new CodeIgniter\HTTP\URI('http://localhost/api/v1/auth/me'), null, new CodeIgniter\HTTP\UserAgent());
    $failure = (new App\Filters\ActiveSessionFilter())->before($request);
    customerCheck($failure && $failure->getStatusCode() === 503 && !json_decode($failure->getBody(), true)['success'], 'Unavailable session verification must block protected operations.');
} finally { $db->query('ALTER TABLE users CHANGE fixture_unavailable_status status VARCHAR(20) NOT NULL'); }
$db->table('users')->where('id', $one)->update(['role' => 'admin']);
(new App\Services\ActiveSessionService())->validate();customerCheck(!session('is_logged_in'), 'Changed role retained stale authorization.');
$new = $auth->registerCustomer(['name' => 'Registration fixture', 'phone' => '9876500003', 'role' => 'admin', 'status' => 'inactive', 'uid' => 'usr_injected']);
customerCheck($new && $users->find($new)['role'] === 'customer' && $users->find($new)['status'] === 'active' && $users->find($new)['uid'] !== 'usr_injected', 'Public registration allowed privilege injection.');
$raceUid = $users->find($new)['uid']; $interleave = true;
$listener = static function ($query) use ($db, $new, $raceUid, &$interleave): void {
    $sql = (string) $query;
    if ($interleave && str_starts_with($sql, 'SELECT') && str_contains($sql, $raceUid)) {
        $interleave = false;
        $db->connID->query('UPDATE users SET role = \'admin\' WHERE id = ' . (int) $new);
    }
};
CodeIgniter\Events\Events::on('DBQuery', $listener);
try { customerCheck((new App\Services\AdminCustomerService())->changeStatus($raceUid, 'inactive', 'active') === 'conflict', 'Concurrent role change bypassed customer restriction.'); }
finally { CodeIgniter\Events\Events::removeListener('DBQuery', $listener); }
customerCheck(!$interleave && $users->find($new)['status'] === 'active', 'Competing role update was overwritten.');
$auth->logout();
session_destroy();
echo "PASS: customer-only search/pagination/counts, contact privacy, history, status validation/conflicts, order preservation, session revocation, inactive login rejection, reactivation and registration privilege protection. Persistent data unchanged.\n";
