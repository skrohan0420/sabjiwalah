<?php
// Real services on connection-local temporary tables; persistent configuration/orders are untouched.
define('ENVIRONMENT','development'); define('FCPATH',dirname(__DIR__,2).'/public/');
require dirname(__DIR__,2).'/app/Config/Paths.php'; $paths=new Config\Paths(); require $paths->systemDirectory.'/Boot.php';
CodeIgniter\Boot::bootConsole($paths); service('session'); session_start(); date_default_timezone_set(config('App')->appTimezone);
$db=db_connect(); if($db->DBDriver!=='MySQLi'||$db->DBPrefix!=='')throw new RuntimeException('Use local MySQL without prefix.');
foreach(['users','products','offers','orders','order_items','order_status_history','offer_redemptions','operational_settings','operational_setting_history'] as $table){
    $ddl=$db->query('SHOW CREATE TABLE '.$table)->getRowArray()['Create Table'];
    $ddl=str_replace('CREATE TABLE','CREATE TEMPORARY TABLE',$ddl);
    $ddl=preg_replace('/^\s*CONSTRAINT .* FOREIGN KEY .*\n/m','',$ddl);$db->query(preg_replace('/,\s*\)/',')',$ddl));
}
function settingsCheck(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
function settingsReject(callable $action,string $type):void{
    $caught=null;try{$action();}catch(Throwable $e){$caught=$e;}
    if(!$caught||!($caught instanceof $type))throw new RuntimeException('Expected '.$type.', received '.($caught?get_class($caught).': '.$caught->getMessage():'success'));
}
$db->table('operational_settings')->insert(['id'=>1,'shop_open'=>1,'orders_paused'=>0,'delivery_charge'=>40,'free_delivery_minimum'=>499,'minimum_order_amount'=>0,'revision'=>1,'updated_at'=>date('Y-m-d H:i:s')]);
$users=new App\Models\UserModel();$admin=$users->insert(['name'=>'Admin <script>','phone'=>'9876560001','role'=>'admin','status'=>'active']);
$customer=$users->insert(['name'=>'Customer','phone'=>'9876560002','role'=>'customer','status'=>'active']);
session()->set(['user_id'=>$customer,'user_role'=>'customer','is_logged_in'=>true]);
$service=new App\Services\OperationalSettingsService();
function settingsInput(array $changes=[]):array{global $service;return array_replace(array_diff_key($service->get(),['revision'=>true,'updated_at'=>true]),['expected_revision'=>$service->get()['revision']],$changes);}
$default=$service->get();settingsCheck($default['delivery_charge']==='40.00'&&$default['free_delivery_minimum']==='499.00','Default pricing changed.');
foreach([['delivery_charge'=>-1],['delivery_charge'=>[]],['minimum_order_amount'=>'0.001'],['free_delivery_minimum'=>'1000000'],['maximum_active_orders'=>0],['maximum_active_orders'=>'1.5'],['shop_open'=>2],['orders_paused'=>null],['opens_at'=>'09:00'],['opens_at'=>'25:00','closes_at'=>'10:00'],['opens_at'=>'09:00','closes_at'=>'09:00'],['expected_revision'=>[]],['unknown'=>'x']]as$change)
    settingsReject(fn()=>$service->save(settingsInput($change),$admin),App\Services\SettingsValidationException::class);
settingsReject(fn()=>$service->save(settingsInput(),$customer),OutOfBoundsException::class);
$users->update($admin,['status'=>'inactive']);settingsReject(fn()=>$service->save(settingsInput(),$admin),OutOfBoundsException::class);$users->update($admin,['status'=>'active']);
$service->save(settingsInput(),$admin);settingsCheck(count($service->history())===0&&$service->get()['revision']===1,'No-op produced audit/revision.');
$stale=settingsInput();$changed=$service->save(settingsInput(['delivery_charge'=>25.50,'free_delivery_minimum'=>'','minimum_order_amount'=>'100']),$admin);
settingsCheck($changed['revision']===2&&$changed['free_delivery_minimum']===null,'Save normalization failed.');
$history=$service->history();settingsCheck(count($history)===1&&$history[0]['old_values']['delivery_charge']==='40.00'&&$history[0]['new_values']['delivery_charge']==='25.50'&&!isset($history[0]['changed_by']),'Audit snapshot/privacy incorrect.');
settingsReject(fn()=>$service->save($stale,$admin),App\Services\OrderConflictException::class);
// A late audit failure must roll back the settings row too.
$db->query('ALTER TABLE operational_setting_history CHANGE new_values fixture_values TEXT NOT NULL');
try{settingsReject(fn()=>$service->save(settingsInput(['delivery_charge'=>'30']),$admin),Throwable::class);
    settingsCheck($service->get()['delivery_charge']==='25.50'&&$service->get()['revision']===2,'Partial settings update committed.');
}finally{$db->resetTransStatus();$db->query('ALTER TABLE operational_setting_history CHANGE fixture_values new_values TEXT NOT NULL');}
$hours=$service->save(settingsInput(['opens_at'=>'09:00','closes_at'=>'18:00','minimum_order_amount'=>'0']),$admin);
foreach(['08:59'=>false,'09:00'=>true,'17:59'=>true,'18:00'=>false]as$time=>$accepted)
    settingsCheck(($service->notice($hours,80,false,new DateTimeImmutable('2026-10-10 '.$time.':00+05:30'))===null)===$accepted,'Daily boundary failed: '.$time);
$overnight=$service->save(settingsInput(['opens_at'=>'22:00','closes_at'=>'06:00']),$admin);
foreach(['21:59'=>false,'22:00'=>true,'05:59'=>true,'06:00'=>false,'12:00'=>false]as$time=>$accepted)
    settingsCheck(($service->notice($overnight,80,false,new DateTimeImmutable('2026-10-10 '.$time.':00+05:30'))===null)===$accepted,'Overnight boundary failed: '.$time);
$service->save(settingsInput(['opens_at'=>'','closes_at'=>'','minimum_order_amount'=>'100']),$admin);
$products=new App\Models\ProductModel();$product=$products->insert(['name'=>'Sample product','slug'=>'settings-product','price'=>80,'unit'=>'kg','stock_quantity'=>10,'is_active'=>1]);$uid=$products->find($product)['uid'];
$cart=new App\Services\CartService();$cart->add($uid,1);$summary=new App\Services\CheckoutSummaryService();$orders=new App\Services\OrderService();
$data=['customer_name'=>'Customer','customer_phone'=>'9876560002','address_line'=>'Sample','city'=>'City','postal_code'=>'700001'];
settingsCheck(!$summary->summary()['can_place_order'],'Minimum not enforced in summary.');
settingsReject(fn()=>$orders->createFromCart($customer,$data),App\Services\OrderConflictException::class);
$cart->update($uid,2);
foreach([['shop_open'=>0],['orders_paused'=>1]]as$change){$service->save(settingsInput($change),$admin);settingsCheck(!$summary->summary()['can_place_order'],'Closed/paused summary accepted.');
    settingsReject(fn()=>$orders->createFromCart($customer,$data),App\Services\OrderConflictException::class);$service->save(settingsInput(['shop_open'=>1,'orders_paused'=>0]),$admin);}
$q=$summary->summary();settingsCheck($q['delivery_charge']===25.5&&$q['total_amount']===185.5,'Disabled free delivery ignored.');
$service->save(settingsInput(['maximum_active_orders'=>'1']),$admin);
settingsReject(fn()=>$orders->createFromCart($customer,$data+['quote_token'=>$q['quote_token']]),App\Services\OrderConflictException::class);
settingsCheck($db->table('orders')->countAllResults()===0&&(int)$products->find($product)['stock_quantity']===10,'Rejected orders changed stock.');
$q=$summary->summary();$order=$orders->createFromCart($customer,$data+['quote_token'=>$q['quote_token']]);settingsCheck((float)$order['delivery_charge']===25.5&&(float)$order['total_amount']===185.5,'Persisted fees incorrect.');
$cart->add($uid,2);settingsCheck(!$summary->summary()['can_place_order'],'Capacity summary accepted.');
settingsReject(fn()=>$orders->createFromCart($customer,$data),App\Services\OrderConflictException::class);
settingsCheck($db->table('orders')->countAllResults()===1&&(int)$products->find($product)['stock_quantity']===8,'Capacity rejection changed records.');
foreach(['pending','confirmed','preparing','ready_for_delivery','out_for_delivery','delivered','delivery_failed','cancelled']as$status){
    $db->table('orders')->where('id',$order['id'])->update(['order_status'=>$status]);
    settingsCheck($summary->summary()['can_place_order']===in_array($status,['delivered','delivery_failed','cancelled'],true),'Capacity status incorrect: '.$status);
}
$db->table('orders')->where('id',$order['id'])->update(['order_status'=>'pending']);
$orders->changeStatus((int)$order['id'],'cancelled',$customer);settingsCheck($summary->summary()['can_place_order'],'Cancellation did not release capacity.');
$service->save(settingsInput(['free_delivery_minimum'=>'160']),$admin);settingsCheck($summary->summary()['delivery_charge']===0.0,'Inclusive free threshold failed.');
$service->save(settingsInput(['free_delivery_minimum'=>'0']),$admin);settingsCheck($summary->summary()['delivery_charge']===0.0,'Zero free threshold failed.');
settingsCheck((float)$db->table('orders')->where('id',$order['id'])->get()->getRowArray()['delivery_charge']===25.5,'Settings rewrote historical fees.');
echo "PASS: settings validation, admin/revision/no-op/audit/rollback, IST daily/overnight boundaries, closure/pause/minimum/capacity enforcement, stale quotes, persisted fees and capacity release. Temporary SQL only.\n";
