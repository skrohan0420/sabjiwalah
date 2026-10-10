<?php
// Query budgets and index plans on connection-local temporary schema/data only.
define('ENVIRONMENT','development'); define('FCPATH',dirname(__DIR__,2).'/public/');
require dirname(__DIR__,2).'/app/Config/Paths.php';$paths=new Config\Paths();require $paths->systemDirectory.'/Boot.php';CodeIgniter\Boot::bootConsole($paths);
$db=db_connect();if($db->DBDriver!=='MySQLi'||$db->DBPrefix!==''||!in_array($db->hostname,['localhost','127.0.0.1','::1'],true))throw new RuntimeException('Use local MySQL without prefix.');
foreach(['users','products','orders','order_items','order_status_history','delivery_assignments','delivery_personnel_profiles'] as$table){
    $ddl=$db->query('SHOW CREATE TABLE '.$table)->getRowArray()['Create Table'];
    $ddl=str_replace('CREATE TABLE','CREATE TEMPORARY TABLE',$ddl);$ddl=preg_replace('/^\s*CONSTRAINT .* FOREIGN KEY .*\n/m','',$ddl);$db->query(preg_replace('/,\s*\)/',')',$ddl));
}
$now=date('Y-m-d H:i:s');$users=[];$orders=[];$items=[];$assignments=[];
for($i=1;$i<=200;$i++){
    $users[]=['id'=>$i,'uid'=>'usr_perf_'.$i,'name'=>'Fixture '.$i,'phone'=>'900000'.str_pad((string)$i,4,'0',STR_PAD_LEFT),'role'=>$i<=100?'customer':'delivery','status'=>'active','created_at'=>$now];
    $orders[]=['id'=>$i,'uid'=>'ord_perf_'.$i,'order_number'=>'SW-PERF-'.$i,'user_id'=>($i%100)+1,'subtotal'=>100,'total_amount'=>140,'order_status'=>$i%2===0?'pending':'ready_for_delivery','customer_name'=>'Fixture','customer_phone'=>'9000000001','address_line'=>'Fixture','city'=>'Fixture','created_at'=>date('Y-m-d H:i:s',time()-$i),'updated_at'=>$now];
    $items[]=['id'=>$i,'uid'=>'oit_perf_'.$i,'order_id'=>$i,'product_id'=>1,'product_name'=>'Fixture','unit'=>'kg','quantity'=>1,'unit_price'=>100,'total_price'=>100,'created_at'=>$now];
    $assignments[]=['id'=>$i,'uid'=>'das_perf_'.$i,'order_id'=>$i,'delivery_user_id'=>101+($i%100),'assigned_by'=>101,'assigned_at'=>$now];
}
$db->table('users')->insertBatch($users);$db->table('orders')->insertBatch($orders);$db->table('order_items')->insertBatch($items);$db->table('delivery_assignments')->insertBatch($assignments);
$db->table('products')->insert(['id'=>1,'uid'=>'prd_perf','slug'=>'fixture-perf','name'=>'Fixture','price'=>100,'unit'=>'kg','stock_quantity'=>1,'is_active'=>1]);
function performanceCheck(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
$queries=[];$record=false;
CodeIgniter\Events\Events::on('DBQuery',static function($query)use(&$queries,&$record){if($record)$queries[]=(string)$query;});
function measured(callable $action,int $budget):mixed{
    global $queries,$record;$queries=[];$record=true;try{$value=$action();}finally{$record=false;}
    performanceCheck(count($queries)===$budget,'Expected '.$budget.' queries, saw '.count($queries));return $value;
}
$dashboard=measured(fn()=>(new App\Models\AdminDashboardModel())->overview('2000-01-01','2100-01-01',5),5);
performanceCheck(count($dashboard['recent_orders'])===8&&count($dashboard['attention_orders'])===8,'Dashboard lists are not bounded.');
foreach([1,100] as$size){
    $orders=measured(fn()=>(new App\Models\AdminOrderModel())->listing(['page'=>1,'per_page'=>$size],null,null),4);
    performanceCheck(count($orders['items'])===$size,'Order page size ignored.');
    $customers=measured(fn()=>(new App\Models\AdminCustomerModel())->listing(['page'=>1,'per_page'=>$size]),3);
    performanceCheck(count($customers['items'])===$size,'Customer page size ignored.');
    $riders=measured(fn()=>(new App\Models\AdminDeliveryPersonnelModel())->listing(['page'=>1,'per_page'=>$size]),3);
    performanceCheck(count($riders['items'])===$size,'Rider page size ignored.');
}
$plans=[
    'order UID'=>"SELECT id FROM orders WHERE uid='ord_perf_1'",
    'recent orders'=>'SELECT uid FROM orders ORDER BY created_at DESC,id DESC LIMIT 20',
    'status/date orders'=>"SELECT uid FROM orders WHERE order_status='pending' AND created_at >= '2000-01-01' ORDER BY created_at,id LIMIT 20",
    'latest assignment'=>'SELECT id FROM delivery_assignments WHERE order_id=1 ORDER BY id DESC LIMIT 1',
];
$indexes=[];foreach($db->query('SHOW INDEX FROM orders')->getResultArray() as$index)$indexes[$index['Key_name']][]=$index['Column_name'];
performanceCheck(($indexes['orders_admin_created']??[])===['created_at','id'],'Missing recent-order index.');
performanceCheck(($indexes['orders_admin_status_created']??[])===['order_status','created_at','id'],'Missing status/date index.');
foreach($plans as$name=>$sql){$plan=$db->query('EXPLAIN '.$sql)->getRowArray();echo $name.': '.($plan['key']??'optimizer chose scan for this fixture').' ('.$plan['type'].")\n";}
// Eligibility is checked separately from the optimizer's cost choice on this small temporary fixture.
foreach(['orders_admin_created','orders_admin_status_created'] as$index){$plan=$db->query('EXPLAIN SELECT uid FROM orders FORCE INDEX (`'.$index.'`) ORDER BY '.($index==='orders_admin_created'?'created_at,id':'order_status,created_at,id').' LIMIT 20')->getRowArray();performanceCheck($plan['key']===$index,'Order index cannot serve the sorted page.');}
echo "PASS: dashboard 5 queries / 8-row lists; orders 4 and customers/riders 3 queries at both 1- and 100-row pages, without N+1 growth; required order indexes exist and can serve sorted pages. Temporary SQL only; reported optimizer choices are not a latency/production-load claim.\n";
