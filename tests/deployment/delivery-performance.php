<?php
// Query growth and EXPLAIN checks on connection-local temporary schema only.
define('ENVIRONMENT','development');define('FCPATH',dirname(__DIR__,2).'/public/');
require dirname(__DIR__,2).'/app/Config/Paths.php';$paths=new Config\Paths();require $paths->systemDirectory.'/Boot.php';CodeIgniter\Boot::bootConsole($paths);
$db=db_connect();if($db->DBDriver!=='MySQLi'||$db->DBPrefix!==''||!in_array($db->hostname,['localhost','127.0.0.1','::1'],true))throw new RuntimeException('Use local unprefixed MySQL.');
foreach(['users','orders','order_items','order_status_history','delivery_assignments','delivery_personnel_profiles','delivery_completions','delivery_locations'] as $table){$ddl=$db->query('SHOW CREATE TABLE '.$table)->getRowArray()['Create Table'];$ddl=str_replace('CREATE TABLE','CREATE TEMPORARY TABLE',$ddl);$ddl=preg_replace('/^\s*CONSTRAINT .* FOREIGN KEY .*\n/m','',$ddl);$db->query(preg_replace('/,\s*\)/',')',$ddl));}
$stamp=date('Y-m-d H:i:s');$users=[];for($id=1;$id<=12;$id++)$users[]=['id'=>$id,'uid'=>'usr_delivery_perf_'.$id,'name'=>'Fixture '.$id,'phone'=>'900000'.str_pad((string)$id,4,'0',STR_PAD_LEFT),'role'=>$id===1?'admin':($id===2?'customer':'delivery'),'status'=>'active'];$db->table('users')->insertBatch($users);
for($id=3;$id<=12;$id++)$db->table('delivery_personnel_profiles')->insert(['user_id'=>$id,'availability'=>'available','updated_at'=>$stamp]);
$orders=[];$assignments=[];$history=[];$completions=[];
for($id=1;$id<=1000;$id++){
    $rider=3+(($id-1)%10);$terminal=intdiv($id-1,10)%2===1;$status=$terminal?'delivered':'ready_for_delivery';
    $orders[]=['id'=>$id,'uid'=>'ord_delivery_perf_'.$id,'order_number'=>'SW-DELIVERY-PERF-'.$id,'user_id'=>2,'subtotal'=>100,'total_amount'=>100,'payment_method'=>'cod','payment_status'=>$terminal?'paid':'pending','order_status'=>$status,'customer_name'=>'Private fixture','customer_phone'=>'9000000002','address_line'=>'Private fixture','city'=>'Fixture','created_at'=>$stamp,'updated_at'=>$stamp];
    $assignments[]=['id'=>$id*2-1,'uid'=>'das_delivery_perf_old_'.$id,'order_id'=>$id,'delivery_user_id'=>$rider===12?3:$rider+1,'assigned_by'=>1,'assigned_at'=>$stamp,'completed_at'=>null];
    $assignments[]=['id'=>$id*2,'uid'=>'das_delivery_perf_'.$id,'order_id'=>$id,'delivery_user_id'=>$rider,'assigned_by'=>1,'assigned_at'=>$stamp,'completed_at'=>$terminal?$stamp:null];
    if($terminal){$history[]=['uid'=>'osh_delivery_perf_'.$id,'order_id'=>$id,'old_status'=>'out_for_delivery','new_status'=>'delivered','changed_by'=>$rider,'created_at'=>$stamp];$completions[]=['order_id'=>$id,'assignment_id'=>$id*2,'delivery_user_id'=>$rider,'verified_at'=>$stamp,'cash_amount'=>100,'collected_at'=>$stamp];}
}
$db->table('orders')->insertBatch($orders);$db->table('delivery_assignments')->insertBatch($assignments);$db->table('order_status_history')->insertBatch($history);$db->table('delivery_completions')->insertBatch($completions);
function performanceCheck(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
$record=false;$queries=[];$budgets=[];
CodeIgniter\Events\Events::on('DBQuery',static function($query)use(&$record,&$queries){if($record)$queries[]=(string)$query;});
function measure(string $label,callable $action):mixed{global $record,$queries,$budgets;$queries=[];$record=true;try{$result=$action();}finally{$record=false;}$budgets[$label]=count($queries);performanceCheck(count($queries)<=20,$label.' exceeded query budget.');echo $label.': '.count($queries)." queries\n";return $result;}
$summary=measure('dashboard',fn()=>(new App\Services\DeliveryDashboardService())->snapshot(3));performanceCheck($summary['active_count']===50&&$summary['completed_today']===50&&count($summary['recent_assignments'])===5,'Dashboard scope/count/list bounds failed.');
$profile=measure('profile',fn()=>(new App\Services\DeliveryHistoryService())->profile(3));performanceCheck($profile['completed_total']===50&&$profile['cod']['collected']==='5000.00','Profile credited another rider or lost cash.');
foreach([1,50] as $size){
    $rows=measure('history-'.$size,fn()=>(new App\Services\DeliveryHistoryService())->history(3,['page'=>1,'per_page'=>$size]));performanceCheck(count($rows['items'])===$size,'History page bound failed.');
    $queue=measure('dispatch-'.$size,fn()=>(new App\Services\DispatchService())->queue(['page'=>1,'per_page'=>$size,'search'=>'','status'=>'','assignment'=>'']));performanceCheck(count($queue['items'])===$size,'Dispatch page bound failed.');
}
performanceCheck($budgets['history-50']<=$budgets['history-1']&&$budgets['dispatch-50']<=$budgets['dispatch-1'],'Query counts grow with page size (N+1).');
$rows=measure('active-list',fn()=>(new App\Models\DeliveryOrderModel())->currentForRider(3,null,true));performanceCheck(count($rows)===50,'Active rider scope failed.');
$one=measure('tracking-ownership-read',fn()=>(new App\Models\DeliveryOrderModel())->currentForRider(3,'ord_delivery_perf_1'));performanceCheck(count($one)===1,'Tracking ownership lookup failed.');
foreach(['delivery_assignments'=>['order_id','delivery_user_id'],'order_status_history'=>['order_id'],'delivery_completions'=>['order_id','delivery_user_id'],'delivery_locations'=>['delivery_user_id','order_id','expires_at']] as $table=>$columns){$indexed=array_column($db->query('SHOW INDEX FROM '.$table)->getResultArray(),'Column_name');foreach($columns as $column)performanceCheck(in_array($column,$indexed,true),'Missing '.$table.'.'.$column.' index.');}
foreach([
    'current rider'=>"SELECT o.id FROM delivery_assignments a JOIN orders o ON o.id=a.order_id WHERE a.delivery_user_id=3 AND NOT EXISTS (SELECT 1 FROM delivery_assignments n WHERE n.order_id=a.order_id AND n.id>a.id)",
    'cash day'=>"SELECT order_id FROM delivery_completions WHERE delivery_user_id=3 AND collected_at>='2000-01-01' AND collected_at<'2100-01-01'",
    'GPS expiry'=>"SELECT delivery_user_id FROM delivery_locations WHERE expires_at<='2100-01-01' ORDER BY expires_at LIMIT 1000",
] as $label=>$sql){$plan=$db->query('EXPLAIN '.$sql)->getResultArray();echo $label.' plan: '.implode('; ',array_map(static fn($row)=>($row['table']??'?').':'.($row['key']??'none').' ('.($row['type']??'?').')',$plan))."\n";}
echo "PASS: delivery query budgets and index availability on 1000 orders/2000 assignments, bounded pages and rider-scoped aggregates; no N+1 page growth. Temporary SQL only; no production latency/load claim.\n";
