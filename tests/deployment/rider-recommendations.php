<?php
define('ENVIRONMENT','development');define('FCPATH',dirname(__DIR__,2).'/public/');
require dirname(__DIR__,2).'/app/Config/Paths.php';$paths=new Config\Paths();require $paths->systemDirectory.'/Boot.php';CodeIgniter\Boot::bootConsole($paths);
$db=db_connect();$original=$db->table('operational_settings')->where('id',1)->get()->getRowArray();
foreach(['users','orders','delivery_assignments','delivery_personnel_profiles','delivery_rider_presence','operational_settings','operational_setting_history'] as $table){
    $definition=$db->query("SHOW CREATE TABLE {$table}")->getRowArray()['Create Table'];
    $definition=str_replace('CREATE TABLE','CREATE TEMPORARY TABLE',$definition);$definition=preg_replace('/^\s*CONSTRAINT .* FOREIGN KEY .*\n/m','',$definition);$db->query(preg_replace('/,\s*\)/',')',$definition));
}
function check(bool $value,string $message):void{if(!$value)throw new RuntimeException($message);}
$db->table('operational_settings')->insert($original);$db->table('operational_settings')->where('id',1)->update(['pickup_latitude'=>22.86,'pickup_longitude'=>88.34]);
foreach([1=>'Admin',2=>'Near',3=>'Far',4=>'Expired',5=>'Unknown',6=>'Inactive',7=>'Busy'] as $id=>$name){
    $db->table('users')->insert(['id'=>$id,'uid'=>'usr_location_'.$id,'name'=>$name,'role'=>$id===1?'admin':'delivery','status'=>$id===6?'inactive':'active']);
    if($id>1)$db->table('delivery_personnel_profiles')->insert(['user_id'=>$id,'availability'=>'available','updated_at'=>gmdate('Y-m-d H:i:s')]);
}
$presence=new App\Services\RiderPresenceService();$now=(int)floor(microtime(true)*1000);
$presence->record(2,['latitude'=>22.8605,'longitude'=>88.34,'accuracy'=>10,'captured_at_ms'=>$now]);
$presence->record(3,['latitude'=>22.9,'longitude'=>88.34,'accuracy'=>10,'captured_at_ms'=>$now]);
$presence->record(4,['latitude'=>22.86,'longitude'=>88.34,'accuracy'=>10,'captured_at_ms'=>$now]);
$db->table('delivery_rider_presence')->where('delivery_user_id',4)->update(['expires_at'=>gmdate('Y-m-d H:i:s',time()-1)]);
$db->table('orders')->insert(['id'=>1,'uid'=>'ord_location_busy','order_number'=>'SW_LOCATION_BUSY','user_id'=>1,'customer_name'=>'Test','customer_phone'=>'9000000000','address_line'=>'Test','city'=>'Test','postal_code'=>'700000','order_status'=>'ready_for_delivery','subtotal'=>1,'discount_amount'=>0,'delivery_charge'=>0,'total_amount'=>1,'payment_method'=>'cod','payment_status'=>'pending','created_at'=>gmdate('Y-m-d H:i:s'),'updated_at'=>gmdate('Y-m-d H:i:s')]);
$db->table('delivery_assignments')->insert(['uid'=>'asg_location_busy','order_id'=>1,'delivery_user_id'=>7,'assigned_by'=>1,'assigned_at'=>gmdate('Y-m-d H:i:s')]);
$service=new App\Services\RiderRecommendationService();$data=$service->candidates(['page'=>1,'per_page'=>10]);
check($data['items'][0]['name']==='Near' && $data['items'][1]['name']==='Far','Riders must be globally ordered by distance to pickup.');
check($data['items'][0]['distance_m']>40 && $data['items'][0]['distance_m']<70,'Distance must be calculated in metres.');
check(count($data['items'])===4,'Busy/inactive riders must be excluded.');
foreach($data['items'] as $rider){check(!isset($rider['id'])&&!isset($rider['phone']),'Candidate identity must stay minimal.');if(in_array($rider['name'],['Expired','Unknown'],true))check($rider['location']===null&&$rider['distance_m']===null,'Expired/unknown positions must not be ranked.');}
$page=$service->candidates(['page'=>2,'per_page'=>1]);check($page['items'][0]['name']==='Far','Distance ordering must happen before pagination.');
try{$presence->record(7,['latitude'=>22.86,'longitude'=>88.34,'accuracy'=>10,'captured_at_ms'=>$now]);throw new RuntimeException('Busy rider shared location.');}catch(App\Services\OrderConflictException){}
try{$presence->record(2,['latitude'=>22.86,'longitude'=>88.34,'accuracy'=>200,'captured_at_ms'=>$now+1]);throw new RuntimeException('Bad accuracy accepted.');}catch(InvalidArgumentException){}
$presence->stop(2);$data=$service->candidates(['page'=>1,'per_page'=>10]);check($data['items'][0]['name']==='Far','Clearing location must remove it from nearest suggestions.');
$db->table('operational_settings')->where('id',1)->update(['pickup_latitude'=>null,'pickup_longitude'=>null]);
$data=$service->candidates(['page'=>1,'per_page'=>10]);check($data['pickup']===null,'Unset pickup must not be replaced with guessed coordinates.');foreach($data['items'] as $rider)check($rider['distance_m']===null,'No distance may be claimed without a pickup point.');
echo "PASS: opt-in snapshots, freshness, accuracy, clearing, eligibility/privacy, global distance ordering and pagination, and unset pickup fallback. Temporary SQL only.\n";
