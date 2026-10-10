<?php
// Connection-local temporary SQL only. No persistent orders/accounts are changed.
define('ENVIRONMENT', 'development'); define('FCPATH', dirname(__DIR__, 2) . '/public/');
require dirname(__DIR__, 2) . '/app/Config/Paths.php'; $paths = new Config\Paths(); require $paths->systemDirectory . '/Boot.php';
CodeIgniter\Boot::bootConsole($paths); service('session'); session_start(); $db = db_connect();
date_default_timezone_set(config('App')->appTimezone);
if ($db->DBDriver !== 'MySQLi' || $db->DBPrefix !== '') throw new RuntimeException('Use local MySQL without prefix.');
foreach (['users','orders','delivery_assignments','delivery_personnel_profiles','order_status_history','order_items','delivery_completions'] as $table) {
    $ddl=$db->query('SHOW CREATE TABLE '.$table)->getRowArray()['Create Table']; $ddl=str_replace('CREATE TABLE','CREATE TEMPORARY TABLE',$ddl); $ddl=preg_replace('/^\s*CONSTRAINT .* FOREIGN KEY .*\n/m','',$ddl); $db->query(preg_replace('/,\s*\)/',')',$ddl));
}
function dispatchCheck(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
function dispatchAction(string $method, array $body=[], ?string $uid=null, array $query=[]): array {
    service('validation')->reset();$request=new CodeIgniter\HTTP\IncomingRequest(config('App'),new CodeIgniter\HTTP\URI('http://localhost/'),json_encode($body),new CodeIgniter\HTTP\UserAgent());$request->setGlobal('get',$query);
    $controller=new App\Controllers\Api\V1\Admin\DispatchController();$controller->initController($request,new CodeIgniter\HTTP\Response(config('App')),service('logger'));$response=$uid?$controller->$method($uid):$controller->$method();
    return [$response->getStatusCode(),json_decode($response->getBody(),true)];
}
function deliveryAction(string $method, int $actor, string $uid, array $body=[]): array {
    session()->set('user_id',$actor);service('validation')->reset();$request=new CodeIgniter\HTTP\IncomingRequest(config('App'),new CodeIgniter\HTTP\URI('http://localhost/'),json_encode($body),new CodeIgniter\HTTP\UserAgent());
    $controller=new App\Controllers\Api\V1\Delivery\OrderController();$controller->initController($request,new CodeIgniter\HTTP\Response(config('App')),service('logger'));$response=$method==='index'?$controller->$method():$controller->$method($uid);
    return [$response->getStatusCode(),json_decode($response->getBody(),true)];
}
$users=new App\Models\UserModel(); $ids=[];
foreach (['admin','delivery','delivery','delivery','customer'] as $i=>$role) $ids[]=$users->insert(['name'=>'Dispatch '.$i,'phone'=>'987650001'.$i,'role'=>$role,'status'=>'active']);
[$manager,$one,$two,$offline,$customer]=$ids;
foreach ([$one,$two,$offline] as $id) $db->table('delivery_personnel_profiles')->insert(['user_id'=>$id,'availability'=>$id===$offline?'offline':'available','updated_at'=>date('Y-m-d H:i:s')]);
$orders=new App\Models\OrderModel(); $orderIds=[];
foreach (['ready_for_delivery','ready_for_delivery','pending','delivered','cancelled','delivery_failed','out_for_delivery'] as $i=>$status) {
    $orderIds[]=$orders->skipValidation(true)->insert(['order_number'=>'DISPATCH_'.$i,'user_id'=>$customer,'subtotal'=>100,'total_amount'=>100,'payment_method'=>'cod','payment_status'=>'pending','order_status'=>$status,'customer_name'=>'PRIVATE_CONTACT','customer_phone'=>'9999999999','address_line'=>'PRIVATE_ADDRESS','city'=>'Fixture','postal_code'=>'700001']);
}
$uid=$orders->find($orderIds[0])['uid']; $secondUid=$orders->find($orderIds[1])['uid']; $riderUid=$users->find($one)['uid']; $twoUid=$users->find($two)['uid']; session()->set('user_id',$manager);
[$status,$data]=dispatchAction('index',[],null,['per_page'=>1]);dispatchCheck($status===200&&$data['data']['pager']['total']===3&&count($data['data']['items'])===1,'Queue/status pagination failed.');
dispatchCheck(!str_contains(json_encode($data),'PRIVATE_CONTACT')&&!str_contains(json_encode($data),'PRIVATE_ADDRESS'),'Dispatch list leaked contact/address.');
dispatchCheck(dispatchAction('candidates')[1]['data']['pager']['total']===2,'Candidate availability failed.');
foreach ([['page'=>'0'],['per_page'=>'101'],['status'=>'delivered'],['assignment'=>'bad']] as $query) dispatchCheck(dispatchAction('index',[],null,$query)[0]===422,'Invalid queue query accepted.');
foreach ([[],['rider_uid'=>[$riderUid],'expected_assignment_uid'=>'none'],['rider_uid'=>$riderUid,'expected_assignment_uid'=>'none','assigned_by'=>$manager]] as $invalid) dispatchCheck(dispatchAction('assign',$invalid,$uid)[0]===422,'Unsafe assignment payload accepted.');
$payload=['rider_uid'=>$riderUid,'expected_assignment_uid'=>'none'];
foreach ([[$users->find($offline)['uid'],409],[$users->find($customer)['uid'],404],['usr_missing',404]] as [$target,$code]) dispatchCheck(dispatchAction('assign',['rider_uid'=>$target,'expected_assignment_uid'=>'none'],$uid)[0]===$code,'Unavailable/non-rider assignment accepted.');
[$status,$data]=dispatchAction('assign',$payload,$uid);dispatchCheck($status===200,'Assignment failed: '.json_encode($data));$firstAssignment=$data['data']['assignment_uid'];
dispatchCheck($orders->find($orderIds[0])['order_status']==='ready_for_delivery','Assignment bypassed status flow.');
dispatchCheck(dispatchAction('assign',$payload,$uid)[0]===409,'Duplicate/stale assignment accepted.');
dispatchCheck(dispatchAction('assign',$payload,$secondUid)[0]===409,'Rider acquired conflicting workload.');
dispatchCheck(dispatchAction('candidates')[1]['data']['pager']['total']===1,'Busy rider was listed as candidate.');
[$status,$data]=dispatchAction('assign',['rider_uid'=>$twoUid,'expected_assignment_uid'=>$firstAssignment],$uid);dispatchCheck($status===200,'Reassignment failed.');$secondAssignment=$data['data']['assignment_uid'];
dispatchCheck($db->table('delivery_assignments')->where('order_id',$orderIds[0])->countAllResults()===2,'Reassignment destroyed history.');
dispatchCheck(dispatchAction('assign',['rider_uid'=>$twoUid,'expected_assignment_uid'=>$secondAssignment],$uid)[0]===200&&$db->table('delivery_assignments')->countAllResults()===2,'Same-rider no-op duplicated history.');
$adminOrders=(new App\Models\AdminOrderModel())->listing(['page'=>1,'per_page'=>20],null,null)['items'];
$assignedOrder=array_values(array_filter($adminOrders,fn($row)=>$row['uid']===$uid))[0];dispatchCheck($assignedOrder['assigned_delivery_name']===$users->find($two)['name'],'Admin order list did not show newest rider.');
dispatchCheck(deliveryAction('show',$one,$uid)[0]===404,'Former rider retained order details.');
dispatchCheck(deliveryAction('updateStatus',$one,$uid,['status'=>'out_for_delivery'])[0]===404,'Former rider retained write access.');
dispatchCheck(count(deliveryAction('index',$one,$uid)[1]['data']['items'])===0,'Former rider retained list access.');
dispatchCheck(deliveryAction('show',$two,$uid)[0]===200,'Current rider lost details.');
dispatchCheck(deliveryAction('updateStatus',$two,$uid,['status'=>'delivered'])[0]===400,'Pickup transition bypassed.');
dispatchCheck(deliveryAction('updateStatus',$two,$uid,['status'=>'out_for_delivery','expected_status'=>'ready_for_delivery'])[0]===200,'Pickup failed.');
session()->set('user_id',$manager);dispatchCheck(dispatchAction('assign',['rider_uid'=>$riderUid,'expected_assignment_uid'=>$secondAssignment],$uid)[0]===409,'In-transit reassignment accepted.');
dispatchCheck(deliveryAction('updateStatus',$two,$uid,['status'=>'out_for_delivery','expected_status'=>'ready_for_delivery'])[0]===409,'Duplicate pickup accepted.');
dispatchCheck(deliveryAction('updateStatus',$two,$uid,['status'=>'delivered','expected_status'=>'out_for_delivery'])[0]===400,'Unverified delivery accepted.');
dispatchCheck(deliveryAction('updateStatus',$two,$uid,['status'=>'delivery_failed','expected_status'=>'out_for_delivery','notes'=>'Customer unavailable.'])[0]===200,'Failed delivery transition failed.');
dispatchCheck($db->table('order_status_history')->where('order_id',$orderIds[0])->countAllResults()===2,'Status history missing/duplicated.');
session()->set('user_id',$manager);
foreach (array_slice($orderIds,2) as $id) dispatchCheck(dispatchAction('assign',$payload,$orders->find($id)['uid'])[0]===409,'Terminal/non-ready assignment accepted.');
$db->table('users')->where('id',$one)->update(['status'=>'inactive']);dispatchCheck(dispatchAction('assign',$payload,$secondUid)[0]===409,'Inactive rider accepted.');$db->table('users')->where('id',$one)->update(['status'=>'active']);
$db->table('orders')->where('id',$orderIds[1])->update(['updated_at'=>date('Y-m-d H:i:s',time()-16*60)]);
$queue=dispatchAction('index',[],null,['search'=>'DISPATCH_1'])[1]['data'];dispatchCheck($queue['items'][0]['delayed']&&$queue['items'][0]['waiting_minutes']>=16,'Delayed ready order missing.');
// Simulated interleaving verifies the locked recheck, not merely the controller's preflight authorization.
$interleave=true;$listener=static function($query)use($db,$orderIds,$one,$manager,&$interleave):void{if($interleave&&str_contains((string)$query,'FOR UPDATE')&&str_contains((string)$query,'`orders`')){$interleave=false;$db->connID->query("INSERT INTO delivery_assignments (uid,order_id,delivery_user_id,assigned_by,assigned_at) VALUES ('das_fixture_race',".(int)$orderIds[1].','.(int)$one.','.(int)$manager.',CURRENT_TIMESTAMP)');}};
CodeIgniter\Events\Events::on('DBQuery',$listener);
try { $competing=dispatchAction('assign',['rider_uid'=>$twoUid,'expected_assignment_uid'=>'none'],$secondUid); dispatchCheck($competing[0]===409,'Competing assignment not rechecked: '.json_encode($competing).' interleaved='.(int)!$interleave); }
finally { CodeIgniter\Events\Events::removeListener('DBQuery',$listener); }
dispatchCheck(!$interleave,'Interleaving test did not execute.');
(new App\Models\DeliveryAssignmentModel())->insert(['order_id'=>$orderIds[1],'delivery_user_id'=>$one,'assigned_by'=>$manager,'assigned_at'=>date('Y-m-d H:i:s')]);
// Inject history failure and require both nested transactions to roll back the order.
$db->query('ALTER TABLE order_status_history CHANGE notes fixture_notes TEXT NULL');
try {dispatchCheck(deliveryAction('updateStatus',$one,$secondUid,['status'=>'out_for_delivery'])[0]===503,'History failure not reported.');dispatchCheck($orders->find($orderIds[1])['order_status']==='ready_for_delivery','Delivery status partially committed.');}
finally {$db->resetTransStatus();$db->query('ALTER TABLE order_status_history CHANGE fixture_notes notes TEXT NULL');}
session()->set('user_id',$manager);
$db->table('delivery_assignments')->where('order_id',$orderIds[1])->delete();
$db->query('ALTER TABLE delivery_assignments CHANGE assigned_by fixture_assigned_by INT UNSIGNED NOT NULL');
try {dispatchCheck(dispatchAction('assign',['rider_uid'=>$twoUid,'expected_assignment_uid'=>'none'],$secondUid)[0]===503,'Assignment insert failure not reported.');dispatchCheck($db->table('delivery_assignments')->where('order_id',$orderIds[1])->countAllResults()===0,'Failed assignment partially committed.');}
finally {$db->resetTransStatus();$db->query('ALTER TABLE delivery_assignments CHANGE fixture_assigned_by assigned_by INT UNSIGNED NOT NULL');}
session_destroy();echo "PASS: dispatch queue/candidates/privacy, assignment/reassignment, stale/duplicate/capacity conflicts, latest rider authorization, pickup/status history, terminal rules, delayed flags, simulated interleaving and atomic rollback. Temporary SQL only.\n";
