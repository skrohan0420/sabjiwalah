<?php
// Worker for concurrent-admin.cjs. All writes are confined to its newly-created disposable database.
if (PHP_SAPI !== 'cli') exit(1);
[$script, $database, $directory, $action] = $argv;
if (!preg_match('/^sw_hardening_[a-f0-9]{24}$/D', $database)) throw new RuntimeException('Invalid fixture database.');
$resolved = realpath($directory);
$tempRoot = realpath(sys_get_temp_dir());
if (!$resolved || !str_starts_with(strtolower($resolved), strtolower($tempRoot . DIRECTORY_SEPARATOR))
    || !str_starts_with(basename($resolved), 'sabjiwalah-concurrent-')) throw new RuntimeException('Invalid fixture directory.');
define('ENVIRONMENT', 'development'); define('FCPATH', dirname(__DIR__, 2) . '/public/');
require dirname(__DIR__, 2) . '/app/Config/Paths.php'; $paths = new Config\Paths();
$paths->writableDirectory = $directory . '/writable';
require $paths->systemDirectory . '/Boot.php'; CodeIgniter\Boot::bootConsole($paths);
$config = config('Database');
if ($config->default['DBDriver'] !== 'MySQLi' || $config->default['DBPrefix'] !== ''
    || !in_array($config->default['hostname'], ['localhost', '127.0.0.1', '::1'], true)) throw new RuntimeException('Use local MySQL without prefix.');
$source = db_connect(null, false);
if ($action === 'setup') {
    $source->query('CREATE DATABASE `' . $database . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci');
    // Ownership marker is created only after CREATE succeeds; cleanup never drops a pre-existing DB.
    file_put_contents($directory . '/database-owner', $database);
}
if (!is_file($directory . '/database-owner') || trim(file_get_contents($directory . '/database-owner')) !== $database) throw new RuntimeException('Fixture ownership is missing.');
if ($action === 'cleanup') { $source->query('DROP DATABASE `' . $database . '`'); echo json_encode(['ok'=>true]); exit; }
$config->default['database'] = $database;
$db = db_connect(); $db->query('SET SESSION innodb_lock_wait_timeout=8');
if ($action === 'setup') {
    $tables = ['users','products','orders','order_items','order_status_history','delivery_assignments','delivery_personnel_profiles','offers','offer_redemptions','operational_settings','operational_setting_history','otp_rate_limits'];
    $db->query('SET FOREIGN_KEY_CHECKS=0');
    foreach ($tables as $table) {
        $ddl = $source->query('SHOW CREATE TABLE `' . $table . '`')->getRowArray()['Create Table'];
        if (!str_contains($ddl, 'ENGINE=InnoDB')) throw new RuntimeException('InnoDB is required.');
        $db->query($ddl);
    }
    $db->query('SET FOREIGN_KEY_CHECKS=1');
    foreach (['admin','customer','customer','delivery','delivery','admin'] as $index=>$role) {
        $id=$index+1;
        $db->table('users')->insert(['id'=>$id,'uid'=>'usr_concurrent_'.$id,'name'=>'Fixture Person '.$id,'phone'=>'900000000'.$id,'role'=>$role,'status'=>'active']);
    }
    foreach ([4,5] as $id) $db->table('delivery_personnel_profiles')->insert(['user_id'=>$id,'availability'=>'available']);
    $db->table('products')->insert(['id'=>1,'uid'=>'prd_concurrent','name'=>'Fixture product','slug'=>'fixture-concurrent','price'=>100,'unit'=>'kg','stock_quantity'=>1,'is_active'=>1]);
    foreach (['pending','ready_for_delivery','ready_for_delivery','ready_for_delivery'] as $index=>$status) {
        $id=$index+1;
        $db->table('orders')->insert(['id'=>$id,'uid'=>'ord_concurrent_'.$id,'order_number'=>'SW-CONCURRENT-'.$id,'user_id'=>2,'subtotal'=>100,'total_amount'=>140,'order_status'=>$status,'customer_name'=>'Fixture Shopper','customer_phone'=>'9000000002','address_line'=>'Fixture','city'=>'Fixture','postal_code'=>'700001','created_at'=>date('Y-m-d H:i:s'),'updated_at'=>date('Y-m-d H:i:s')]);
    }
    $db->table('operational_settings')->insert(['id'=>1,'shop_open'=>1,'orders_paused'=>0,'delivery_charge'=>40,'free_delivery_minimum'=>499,'minimum_order_amount'=>0,'revision'=>1,'updated_at'=>date('Y-m-d H:i:s')]);
    $db->table('offers')->insert(['id'=>1,'uid'=>'off_concurrent','name'=>'Last use','code'=>'LAST','type'=>'fixed','value'=>10,'minimum_order_amount'=>0,'usage_limit'=>1,'is_active'=>1]);
    echo json_encode(['ok'=>true]); exit;
}
if ($action === 'state') {
    $state=[]; foreach (['orders','products','order_status_history','delivery_assignments','offer_redemptions','operational_setting_history','otp_rate_limits'] as $table) $state[$table]=$db->table($table)->get()->getResultArray();
    echo json_encode($state); exit;
}
if ($action === 'prepare-checkout') {
    $db->table('orders')->where('id >',0)->update(['order_status'=>'delivered']);
    $db->table('products')->where('id',1)->update(['stock_quantity'=>($argv[4]??'')==='stock'?1:20]);
    $db->table('operational_settings')->where('id',1)->update(['maximum_active_orders'=>($argv[4]??'')==='capacity'?1:null]);
    echo json_encode(['ok'=>true]); exit;
}
if ($action === 'prepare-dispatch') {
    $db->table('orders')->whereIn('id',[2,3])->update(['order_status'=>'delivered']);
    echo json_encode(['ok'=>true]); exit;
}
// Each worker uses a distinct session and connection; a file barrier starts actual overlapping calls.
$worker=(int)($argv[4]??0); $_SERVER['REMOTE_ADDR']='127.0.0.1'; config('Otp')->developmentMode=true;
service('session'); session_id('concurrent'.$action.$worker); session_start();
session()->set(['user_id'=>$worker%2===0?2:3,'user_role'=>'customer','is_logged_in'=>true]);
if (str_starts_with($action,'checkout-')) {
    (new App\Services\CartService())->add('prd_concurrent',1);
    if ($action==='checkout-coupon') session()->set('checkout_offer','LAST');
}
file_put_contents($directory.'/ready-'.$action.'-'.$worker,'ready');
$deadline=microtime(true)+15;
while(!is_file($directory.'/go-'.$action)) { if(microtime(true)>$deadline)throw new RuntimeException('Barrier timed out.'); usleep(10000); }
$started=microtime(true);
try {
    if ($action==='status') (new App\Services\OrderService())->changeStatus(1,$worker===0?'confirmed':'cancelled',$worker===0?1:6,'Concurrent fixture','pending');
    elseif ($action==='dispatch-capacity') (new App\Services\DispatchService())->assign('ord_concurrent_'.($worker+2),'usr_concurrent_4','none',$worker===0?1:6);
    elseif ($action==='dispatch-same') (new App\Services\DispatchService())->assign('ord_concurrent_4','usr_concurrent_'.($worker===0?4:5),'none',$worker===0?1:6);
    elseif ($action==='otp-phone') (new App\Services\OtpService())->start('auth','9000000100');
    elseif ($action==='otp-ip') (new App\Services\OtpService())->start('auth','9000001'.str_pad((string)$worker,3,'0',STR_PAD_LEFT));
    elseif (str_starts_with($action,'checkout-')) {
        $quote=(new App\Services\CheckoutSummaryService())->summary();
        (new App\Services\OrderService())->createFromCart((int)session('user_id'),['customer_name'=>'Fixture Shopper','customer_phone'=>'9000000002','address_line'=>'Fixture','city'=>'Fixture','postal_code'=>'700001','quote_token'=>$quote['quote_token']]);
    } else throw new RuntimeException('Unknown fixture action.');
    $result='ok';
} catch (App\Services\OtpRateLimitException) { $result='limited'; }
catch (App\Services\OrderConflictException|InvalidArgumentException) { $result='conflict'; }
catch (Throwable $e) { $result='error:'.get_class($e).':'.$e->getMessage().':'.basename($e->getFile()).':'.$e->getLine().':'.json_encode($db->error()); }
echo json_encode(['result'=>$result,'elapsed_ms'=>(int)round((microtime(true)-$started)*1000)]);
