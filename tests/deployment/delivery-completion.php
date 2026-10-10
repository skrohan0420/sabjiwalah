<?php
// Real services/controllers with connection-local temporary tables only.
define('ENVIRONMENT','development'); define('FCPATH',dirname(__DIR__,2).'/public/');
require dirname(__DIR__,2).'/app/Config/Paths.php'; $paths=new Config\Paths(); require $paths->systemDirectory.'/Boot.php';
CodeIgniter\Boot::bootConsole($paths); service('session');session_start();$db=db_connect();date_default_timezone_set(config('App')->appTimezone);
if ($db->DBDriver!=='MySQLi'||$db->DBPrefix!=='') throw new RuntimeException('Use local MySQL without prefix.');
foreach(['users','orders','delivery_assignments','order_status_history','order_delivery_pins','delivery_completions'] as $table){$ddl=$db->query('SHOW CREATE TABLE '.$table)->getRowArray()['Create Table'];$ddl=str_replace('CREATE TABLE','CREATE TEMPORARY TABLE',$ddl);$ddl=preg_replace('/^\s*CONSTRAINT .* FOREIGN KEY .*\n/m','',$ddl);$db->query(preg_replace('/,\s*\)/',')',$ddl));}
function completeCheck(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
function completeReject(callable $action,string $type):void{try{$action();}catch(Throwable $e){if(!$e instanceof $type)throw $e;return;}throw new RuntimeException('Rejected action succeeded.');}
$users=new App\Models\UserModel();$ids=[];foreach(['admin','customer','delivery','delivery'] as $i=>$role)$ids[]=$users->insert(['name'=>'Complete Fixture '.$i,'phone'=>'987652000'.$i,'role'=>$role,'status'=>'active']);[$admin,$customer,$rider,$former]=$ids;
$orders=new App\Models\OrderModel();$orderIds=[];foreach(range(1,5) as $i){$id=$orders->skipValidation(true)->insert(['order_number'=>'COMPLETE_'.$i,'user_id'=>$customer,'subtotal'=>123.45,'total_amount'=>123.45,'payment_method'=>'cod','payment_status'=>'pending','order_status'=>'out_for_delivery','customer_name'=>'Fixture','customer_phone'=>'9999999999','address_line'=>'Test','city'=>'Fixture']);$orderIds[]=$id;(new App\Models\DeliveryAssignmentModel())->insert(['order_id'=>$id,'delivery_user_id'=>$rider,'assigned_by'=>$admin,'assigned_at'=>date('Y-m-d H:i:s')]);}
$service=new App\Services\DeliveryCompletionService();$pins=new App\Services\DeliveryPinService();$id=$orderIds[0];$uid=$orders->find($id)['uid'];$pin=$pins->issue($uid,$customer)['pin'];
completeReject(fn()=>$service->complete($uid,$former,$pin,true),OutOfBoundsException::class);
completeReject(fn()=>$service->complete($uid,$admin,$pin,true),OutOfBoundsException::class);
$db->table('users')->where('id',$rider)->update(['status'=>'inactive']);completeReject(fn()=>$service->complete($uid,$rider,$pin,true),OutOfBoundsException::class);$db->table('users')->where('id',$rider)->update(['status'=>'active']);
completeReject(fn()=>(new App\Services\OrderService())->changeStatus($id,'delivered',$rider),InvalidArgumentException::class);
completeReject(fn()=>$service->complete($uid,$rider,$pin,false),App\Services\DeliveryVerificationException::class);
completeReject(fn()=>(new App\Services\DeliveryOrderService())->changeStatus($uid,'delivery_failed',$rider,'   '),InvalidArgumentException::class);
$wrong=$pin==='000000'?'000001':'000000';
for($i=1;$i<=5;$i++){completeReject(fn()=>$service->complete($uid,$rider,$wrong,true),App\Services\DeliveryVerificationException::class);completeCheck((int)$db->table('order_delivery_pins')->where('order_id',$id)->get()->getRowArray()['failed_attempts']===$i,'Failed attempt rolled back.');}
completeReject(fn()=>$service->complete($uid,$rider,$pin,true),App\Services\DeliveryVerificationException::class);
$db->table('order_delivery_pins')->where('order_id',$id)->update(['issued_at'=>date('Y-m-d H:i:s',time()-61)]);
completeReject(fn()=>$pins->issue($uid,$customer),App\Services\DeliveryPinCooldownException::class);
$db->table('order_delivery_pins')->where('order_id',$id)->update(['attempt_window_ends_at'=>date('Y-m-d H:i:s',time()-1)]);
$pin=$pins->issue($uid,$customer)['pin'];
// Inject status-history failure; ledger/payment/assignment must all roll back.
$db->query('ALTER TABLE order_status_history CHANGE notes fixture_notes TEXT NULL');
try{completeReject(fn()=>$service->complete($uid,$rider,$pin,true),Throwable::class);completeCheck($db->table('delivery_completions')->countAllResults()===0 && $orders->find($id)['order_status']==='out_for_delivery' && $orders->find($id)['payment_status']==='pending','Completion partially committed.');}
finally{$db->resetTransStatus();$db->query('ALTER TABLE order_status_history CHANGE fixture_notes notes TEXT NULL');}
$service->complete($uid,$rider,$pin,true);$service->complete($uid,$rider,$pin,true);
$proof=$db->table('delivery_completions')->where('order_id',$id)->get()->getRowArray();
completeCheck($orders->find($id)['order_status']==='delivered' && $orders->find($id)['payment_status']==='paid' && $proof['cash_amount']==='123.45','COD completion incorrect.');
completeCheck($db->table('order_status_history')->where('order_id',$id)->countAllResults()===1 && $db->table('delivery_completions')->countAllResults()===1,'Duplicate completion duplicated records.');
completeCheck(!empty($db->table('delivery_assignments')->where('order_id',$id)->get()->getRowArray()['completed_at']),'Assignment completion missing.');
completeReject(fn()=>$service->reconcile($uid,$rider),OutOfBoundsException::class);
$db->query('ALTER TABLE order_status_history CHANGE notes fixture_notes TEXT NULL');
try{completeReject(fn()=>$service->reconcile($uid,$admin),Throwable::class);completeCheck($db->table('delivery_completions')->where('order_id',$id)->get()->getRowArray()['reconciled_at']===null,'Reconciliation partially committed.');}
finally{$db->resetTransStatus();$db->query('ALTER TABLE order_status_history CHANGE fixture_notes notes TEXT NULL');}
$service->reconcile($uid,$admin);$service->reconcile($uid,$admin);
completeCheck($db->table('order_status_history')->where('order_id',$id)->countAllResults()===2 && (int)$db->table('delivery_completions')->where('order_id',$id)->get()->getRowArray()['reconciled_by']===$admin,'Reconciliation audit/idempotence failed.');
$list=(new App\Models\DeliveryCompletionModel())->cashListing(['page'=>1,'per_page'=>20,'status'=>'reconciled']);completeCheck(count($list['items'])===1 && $list['totals']['pending']==='0.00' && !str_contains(json_encode($list),'pin_hash'),'Cash totals/privacy failed.');
// Expired PIN and unpaid non-COD cannot complete; paid non-COD produces no cash ledger amount.
$id=$orderIds[1];$uid=$orders->find($id)['uid'];$pin=$pins->issue($uid,$customer)['pin'];$db->table('order_delivery_pins')->where('order_id',$id)->update(['expires_at'=>date('Y-m-d H:i:s',time()-1)]);completeReject(fn()=>$service->complete($uid,$rider,$pin,true),App\Services\DeliveryVerificationException::class);
$id=$orderIds[2];$uid=$orders->find($id)['uid'];$pin=$pins->issue($uid,$customer)['pin'];$db->table('orders')->where('id',$id)->update(['payment_method'=>'prepaid']);completeReject(fn()=>$service->complete($uid,$rider,$pin,false),App\Services\DeliveryVerificationException::class);$db->table('orders')->where('id',$id)->update(['payment_status'=>'paid']);$service->complete($uid,$rider,$pin,false);completeCheck($db->table('delivery_completions')->where('order_id',$id)->get()->getRowArray()['cash_amount']===null,'Prepaid created cash collection.');
session_destroy();echo "PASS: completion ownership/bypass/cash/reason rules, expiry, persisted attempts and rotation lockout, atomic rollback, idempotent completion/reconciliation, assignment/payment/history, cash totals/privacy and prepaid cases. Temporary SQL only.\n";
