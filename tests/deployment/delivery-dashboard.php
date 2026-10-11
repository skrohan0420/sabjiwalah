<?php
// Real MySQL services with connection-local temporary tables; no persistent writes.
define('ENVIRONMENT', 'development'); define('FCPATH', dirname(__DIR__,2).'/public/');
require dirname(__DIR__,2).'/app/Config/Paths.php'; $paths=new Config\Paths(); require $paths->systemDirectory.'/Boot.php';
CodeIgniter\Boot::bootConsole($paths); $db=db_connect(); date_default_timezone_set(config('App')->appTimezone);
if ($db->DBDriver!=='MySQLi'||$db->DBPrefix!==''||!in_array($db->hostname,['localhost','127.0.0.1','::1'],true)) throw new RuntimeException('Use unprefixed local MySQL.');
foreach(['users','orders','delivery_assignments','delivery_personnel_profiles','delivery_completions','order_status_history'] as $table) {
 $ddl=$db->query('SHOW CREATE TABLE '.$table)->getRowArray()['Create Table']; $ddl=str_replace('CREATE TABLE','CREATE TEMPORARY TABLE',$ddl);$ddl=preg_replace('/^\s*CONSTRAINT .* FOREIGN KEY .*\n/m','',$ddl);$db->query(preg_replace('/,\s*\)/',')',$ddl));
}
function dashboardCheck(bool $ok,string $message):void {if(!$ok)throw new RuntimeException($message);}
$users=new App\Models\UserModel();$ids=[];foreach(['admin','delivery','delivery','customer'] as $i=>$role)$ids[]=$users->insert(['name'=>'Fixture '.$i,'phone'=>'987654001'.$i,'role'=>$role,'status'=>'active']);[$admin,$rider,$other,$customer]=$ids;
foreach([$rider,$other] as $id)$db->table('delivery_personnel_profiles')->insert(['user_id'=>$id,'availability'=>'available','updated_at'=>'2026-10-10 00:00:00']);
$now=new DateTimeImmutable('2026-10-11T12:00:00+05:30');$zone=new DateTimeZone(config('App')->appTimezone);
$stamp=static fn(string $date):string=>(new DateTimeImmutable($date))->setTimezone($zone)->format('Y-m-d H:i:s');
$orders=new App\Models\OrderModel();$assignments=new App\Models\DeliveryAssignmentModel();$orderIds=[];$assignmentIds=[];
$statuses=['ready_for_delivery','out_for_delivery','ready_for_delivery','delivered','delivered','delivered','delivered','delivered','delivered'];
foreach($statuses as $i=>$status){
 $orderIds[$i]=$orders->skipValidation(true)->insert(['order_number'=>'DASH_'.$i,'user_id'=>$customer,'subtotal'=>100,'total_amount'=>100,'payment_method'=>'cod','payment_status'=>'pending','order_status'=>$status,'customer_name'=>'PRIVATE CUSTOMER','customer_phone'=>'9999999999','address_line'=>'PRIVATE ADDRESS','city'=>'Fixture']);
 $assignmentIds[$i]=$assignments->insert(['order_id'=>$orderIds[$i],'delivery_user_id'=>$rider,'assigned_by'=>$admin,'assigned_at'=>$stamp('2026-10-11T10:00:00+05:30')]);
}
// Former rider must lose current data and notifications when reassigned.
$assignments->insert(['order_id'=>$orderIds[2],'delivery_user_id'=>$other,'assigned_by'=>$admin,'assigned_at'=>$stamp('2026-10-11T11:00:00+05:30')]);
$times=[3=>'2026-10-11T00:00:00+05:30',4=>'2026-10-11T23:59:59+05:30',5=>'2026-10-10T23:59:59+05:30',6=>'2026-10-12T00:00:00+05:30'];
foreach($times as $i=>$time)$db->table('delivery_completions')->insert(['order_id'=>$orderIds[$i],'assignment_id'=>$assignmentIds[$i],'delivery_user_id'=>$rider,'verified_at'=>$stamp($time),'cash_amount'=>$i===4?null:'123.45','collected_at'=>$i===4?null:$stamp($time)]);
// Legacy completion uses assignment timestamp, or actor's recorded delivery history.
$db->table('delivery_assignments')->where('id',$assignmentIds[7])->update(['completed_at'=>$stamp('2026-10-11T05:00:00+05:30')]);
foreach([1,2] as $unused)(new App\Models\OrderStatusHistoryModel())->insert(['order_id'=>$orderIds[8],'old_status'=>'out_for_delivery','new_status'=>'delivered','changed_by'=>$rider,'created_at'=>$stamp('2026-10-11T06:00:00+05:30')]);
$service=new App\Services\DeliveryDashboardService();$result=$service->snapshot($rider,$now);
dashboardCheck($result['active_count']===2&&$result['pending_pickups']===1&&$result['active_order']['order_number']==='DASH_1','Active ownership/pickup priority/counts failed.');
dashboardCheck($result['completed_today']===4&&$result['cod_collected_today']==='123.45','India midnight bounds, prepaid/legacy totals or distinct history failed.');
dashboardCheck($result['availability']==='busy'&&$result['timezone']==='Asia/Kolkata'&&$result['day']==='2026-10-11','Availability/day failed.');
dashboardCheck(count($result['recent_assignments'])===5&&!str_contains(json_encode($result),'DASH_2'),'Recent assignment cap/ownership failed.');
dashboardCheck(!str_contains(json_encode($result),'PRIVATE')&&!str_contains(json_encode($result),'pin_hash'),'Dashboard exposed customer private details.');
$different=$service->snapshot($other,$now);dashboardCheck($different['active_count']===1&&$different['completed_today']===0&&$different['cod_collected_today']==='0.00','Another rider totals leaked.');
$availability=new App\Services\AdminDeliveryPersonnelService();
dashboardCheck($result['base_availability']==='available'&&!$result['can_change_availability'],'Busy availability controls enabled.');
foreach(['available','unavailable','offline'] as $value)dashboardCheck($availability->changeOwnAvailability($rider,$value,'available')==='assigned','Active workload allowed availability change.');
$db->table('orders')->whereIn('id',[$orderIds[0],$orderIds[1]])->update(['order_status'=>'delivery_failed']);
dashboardCheck($availability->changeOwnAvailability($rider,'offline','available')==='changed','Idle rider could not go offline.');
dashboardCheck($availability->changeOwnAvailability($rider,'available','available')==='conflict','Stale availability overwrote newer state.');
dashboardCheck($availability->changeOwnAvailability($rider,'available','offline')==='changed','Idle rider could not become available.');
dashboardCheck($availability->changeOwnAvailability($rider,'available','available')==='unchanged','Same-state change was not idempotent.');
dashboardCheck($availability->changeOwnAvailability($rider,'unavailable','available')==='changed','Unavailable state failed.');
dashboardCheck($availability->changeOwnAvailability($customer,'available','offline')==='missing','Customer changed availability.');
$db->table('delivery_personnel_profiles')->where('user_id',$rider)->delete();
dashboardCheck($availability->changeOwnAvailability($rider,'available','offline')==='changed','Missing profile was not safely created.');
$db->table('users')->where('id',$rider)->update(['status'=>'inactive']);
dashboardCheck($availability->changeOwnAvailability($rider,'available','available')==='inactive','Inactive rider changed availability.');
foreach([$rider,$customer,$admin,99999999] as $actor){try{$service->snapshot($actor,$now);throw new RuntimeException('Unauthorized dashboard succeeded.');}catch(OutOfBoundsException){}}
echo "PASS: dashboard latest ownership, active priority/pickups, five recent assignments, rider-scoped totals, India midnight boundaries, prepaid/legacy handling, privacy and active-role checks. Temporary SQL only.\n";
