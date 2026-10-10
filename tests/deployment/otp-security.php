<?php
// Authentication hardening on connection-local temporary SQL. No persistent users or budgets change.
define('ENVIRONMENT', in_array('--production', $argv, true) ? 'production' : 'development');
define('FCPATH', dirname(__DIR__,2).'/public/');
require dirname(__DIR__,2).'/app/Config/Paths.php';$paths=new Config\Paths();require $paths->systemDirectory.'/Boot.php';
CodeIgniter\Boot::bootConsole($paths);service('session');session_start();
$_SERVER['REMOTE_ADDR']='127.0.0.1';config('Otp')->developmentMode=true;
function otpCheck(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
function otpReject(callable $action,string $type):Throwable{
    $caught=null;try{$action();}catch(Throwable $e){$caught=$e;}
    if(!$caught||!($caught instanceof $type))throw new RuntimeException('Expected '.$type.', received '.($caught?get_class($caught).': '.$caught->getMessage():'success'));
    return $caught;
}
function otpAction(string $method,array $data):CodeIgniter\HTTP\ResponseInterface{
    $request=new CodeIgniter\HTTP\IncomingRequest(config('App'),new CodeIgniter\HTTP\URI('http://localhost/'),json_encode($data),new CodeIgniter\HTTP\UserAgent());
    $controller=new App\Controllers\Api\V1\AuthController();$controller->initController($request,new CodeIgniter\HTTP\Response(config('App')),service('logger'));
    return $controller->$method();
}
$otp=new App\Services\OtpService();$phone='9876570001';
if(ENVIRONMENT==='production'){
    foreach([fn()=>$otp->start('auth',$phone),fn()=>$otp->verify('auth',$phone,'123456'),fn()=>$otp->start('checkout',$phone),fn()=>$otp->verifiedForCheckout($phone)]as$action)otpReject($action,App\Services\OtpUnavailableException::class);
    $response=otpAction('startOtp',['phone'=>$phone]);otpCheck($response->getStatusCode()===503&&!str_contains($response->getBody(),'dev_otp')&&str_contains($response->getHeaderLine('Cache-Control'),'no-store'),'Production exposed a code or cached verification.');
    foreach([[],['auth_proof_version'=>App\Services\AuthService::SESSION_PROOF_VERSION,'auth_method'=>'local_development']]as$proof){
        session()->set(['user_id'=>1,'user_uid'=>'usr_sample_admin','user_role'=>'admin','is_logged_in'=>true]+$proof);
        (new App\Services\ActiveSessionService())->validate();otpCheck(!session('is_logged_in')&&!session('auth_method'),'Production retained a legacy or testing session.');
    }
    echo "PASS: production rejects auth/checkout OTP issuance, verification and receipts even with development opt-in; no code disclosure.\n";exit;
}
$db=db_connect();if($db->DBDriver!=='MySQLi'||$db->DBPrefix!=='')throw new RuntimeException('Use local MySQL without prefix.');
foreach(['users','otp_rate_limits']as$table){$ddl=$db->query('SHOW CREATE TABLE '.$table)->getRowArray()['Create Table'];$ddl=str_replace('CREATE TABLE','CREATE TEMPORARY TABLE',$ddl);$db->query($ddl);}
function resetOtpBudgets():void{db_connect()->table('otp_rate_limits')->set('expires_at',time()-1)->set('last_used_at',time()-60)->update();session()->remove(['auth_otp','checkout_otp']);}
config('Otp')->developmentMode=false;otpReject(fn()=>$otp->start('auth',$phone),App\Services\OtpUnavailableException::class);config('Otp')->developmentMode=true;
$_SERVER['REMOTE_ADDR']='203.0.113.10';$_SERVER['HTTP_X_FORWARDED_FOR']='127.0.0.1';otpReject(fn()=>$otp->start('auth',$phone),App\Services\OtpUnavailableException::class);$_SERVER['REMOTE_ADDR']='127.0.0.1';
unset($_SERVER['HTTP_X_FORWARDED_FOR']);
foreach(['HTTP_FORWARDED','HTTP_X_FORWARDED_FOR','HTTP_X_FORWARDED_HOST','HTTP_X_FORWARDED_PROTO','HTTP_X_REAL_IP'] as$header){
    $_SERVER[$header]='proxy-supplied';
    foreach([fn()=>$otp->start('auth',$phone),fn()=>$otp->verify('auth',$phone,'123456'),fn()=>$otp->verifiedForCheckout($phone)]as$action)otpReject($action,App\Services\OtpUnavailableException::class);
    unset($_SERVER[$header]);
}
$_SERVER['HTTP_HOST']='public-preview.example';otpReject(fn()=>$otp->start('auth',$phone),App\Services\OtpUnavailableException::class);unset($_SERVER['HTTP_HOST']);
foreach(['abc9876570001','12345','129876570001','++919876570001','0000000000',str_repeat('9',31)]as$value)otpReject(fn()=>App\Services\OtpService::normalizePhone($value),InvalidArgumentException::class);
foreach([$phone,'+91 '.$phone,'0091'.$phone]as$value)otpCheck(App\Services\OtpService::normalizePhone($value)===$phone,'Phone normalization failed.');
$issued=$otp->start('auth',$phone);$state=session('auth_otp');otpCheck(!isset($state['code'])&&password_verify($issued['dev_otp'],$state['code_hash']),'OTP stored as plaintext.');
$e=otpReject(fn()=>$otp->start('auth',$phone),App\Services\OtpRateLimitException::class);otpCheck($e->retryAfter>0&&$e->retryAfter<=60,'Resend interval incorrect.');
$bad=$issued['dev_otp']==='111111'?'222222':'111111';
for($i=0;$i<5;$i++)otpCheck(!$otp->verify('auth',$phone,$bad),'Wrong OTP accepted.');
otpReject(fn()=>$otp->verify('auth',$phone,$issued['dev_otp']),App\Services\OtpRateLimitException::class);
session()->remove('auth_otp');otpReject(fn()=>$otp->verify('auth',$phone,$issued['dev_otp']),App\Services\OtpRateLimitException::class);
$db->table('otp_rate_limits')->where('bucket_key',hash('sha256','auth:send:phone:'.$phone))->update(['last_used_at'=>time()-60]);
$issued=$otp->start('auth',$phone);otpReject(fn()=>$otp->verify('auth',$phone,$issued['dev_otp']),App\Services\OtpRateLimitException::class);
resetOtpBudgets();$issued=$otp->start('auth',$phone);$expired=session('auth_otp');$expired['expires_at']=time();session()->set('auth_otp',$expired);
otpCheck(!$otp->verify('auth',$phone,$issued['dev_otp'])&&!session('auth_otp'),'Expiry boundary accepted.');
resetOtpBudgets();$authCode=$otp->start('auth',$phone);otpCheck(!$otp->verify('checkout',$phone,$authCode['dev_otp']),'OTP crossed purposes.');
$checkout=$otp->start('checkout',$phone);otpCheck(!$otp->verifiedForCheckout($phone),'Unverified receipt accepted.');
otpCheck($otp->verify('checkout',$phone,$checkout['dev_otp'])&&$otp->verifiedForCheckout($phone),'Checkout verification failed.');
otpCheck(!isset(session('checkout_otp')['code_hash'])&&!$otp->verify('checkout',$phone,$checkout['dev_otp']),'Checkout code replayed.');
otpCheck(!$otp->verifiedForCheckout('9876570002'),'Checkout receipt crossed phone numbers.');
$users=new App\Models\UserModel();$id=$users->insert(['name'=>'Sample Admin','phone'=>$phone,'role'=>'admin','status'=>'active']);
$auth=new App\Services\AuthService();$previous=session_id();otpCheck($auth->verifyPhoneOtp($phone,$authCode['dev_otp']),'Admin login failed.');
otpCheck(session_id()!==$previous&&session('user_role')==='admin'&&!session('checkout_otp')&&!session('auth_otp')&&session('auth_method')==='local_development','Login did not rotate session or clear challenges.');
otpCheck(!$auth->verifyPhoneOtp($phone,$authCode['dev_otp']),'Consumed auth OTP replayed.');
$auth->logout();resetOtpBudgets();$users->update($id,['status'=>'inactive']);$issued=$auth->startPhoneOtp($phone);
otpCheck(!$auth->verifyPhoneOtp($phone,$issued['dev_otp'])&&!session('is_logged_in')&&!session('auth_otp'),'Inactive admin authenticated or retained a used challenge.');
resetOtpBudgets();for($i=0;$i<5;$i++){$otp->start('auth',$phone);$db->table('otp_rate_limits')->where('bucket_key',hash('sha256','auth:send:phone:'.$phone))->update(['last_used_at'=>time()-60]);}
otpReject(fn()=>$otp->start('auth',$phone),App\Services\OtpRateLimitException::class);
resetOtpBudgets();for($i=0;$i<20;$i++)$otp->start('auth','987658'.str_pad((string)$i,4,'0',STR_PAD_LEFT));
otpReject(fn()=>$otp->start('auth','9876590000'),App\Services\OtpRateLimitException::class);
resetOtpBudgets();for($i=0;$i<60;$i++)otpCheck(!$otp->verify('auth','987658'.str_pad((string)$i,4,'0',STR_PAD_LEFT),'111111'),'Missing challenge accepted.');
otpReject(fn()=>$otp->verify('checkout','9876590000','111111'),App\Services\OtpRateLimitException::class);
resetOtpBudgets();$otp->start('auth',$phone);$db->table('otp_rate_limits')->where('bucket_key',hash('sha256','auth:send:phone:'.$phone))->update(['expires_at'=>time()-1]);
otpReject(fn()=>$otp->start('auth',$phone),App\Services\OtpRateLimitException::class);
foreach([['phone'=>[]],['phone'=>$phone,'role'=>'admin'],['phone'=>'not-a-number'],['phone'=>$phone,'otp'=>[]]]as$data){$response=otpAction(isset($data['otp'])?'verifyOtp':'startOtp',$data);otpCheck($response->getStatusCode()===422,'Invalid OTP API payload accepted.');}
resetOtpBudgets();$otp->start('auth',$phone);$response=otpAction('startOtp',['phone'=>$phone]);
otpCheck($response->getStatusCode()===429&&(int)$response->getHeaderLine('Retry-After')>0&&str_contains($response->getHeaderLine('Cache-Control'),'no-store'),'Rate-limit API response incorrect.');
$db->query('ALTER TABLE otp_rate_limits CHANGE attempts fixture_attempts INT UNSIGNED NOT NULL');
try{$response=otpAction('startOtp',['phone'=>'9876590001']);otpCheck($response->getStatusCode()===503&&!str_contains($response->getBody(),'SELECT')&&!str_contains($response->getBody(),'dev_otp'),'Rate storage failure did not fail closed.');}
finally{$db->resetTransStatus();$db->query('ALTER TABLE otp_rate_limits CHANGE fixture_attempts attempts INT UNSIGNED NOT NULL');}
$db->table('otp_rate_limits')->insertBatch([
    ['bucket_key'=>str_repeat('a',64),'attempts'=>1,'expires_at'=>time()-1,'last_used_at'=>time()-61],
    ['bucket_key'=>str_repeat('b',64),'attempts'=>1,'expires_at'=>time()-1,'last_used_at'=>time()],
    ['bucket_key'=>str_repeat('c',64),'attempts'=>1,'expires_at'=>time()+900,'last_used_at'=>time()-61],
]);
(new App\Commands\PruneOtpLimits(service('logger'),service('commands')))->run([]);
otpCheck($db->table('otp_rate_limits')->where('bucket_key',str_repeat('a',64))->countAllResults()===0
    &&$db->table('otp_rate_limits')->whereIn('bucket_key',[str_repeat('b',64),str_repeat('c',64)])->countAllResults()===2,'Pruning reset active budgets or cooldowns.');
echo "PASS: local opt-in/IP gating, strict input, hashed single-use codes, expiry, purpose/phone binding, session rotation, inactive admin rejection, resend/phone/IP budgets across session resets and resends, Retry-After/no-store and storage failure closure. Temporary SQL only.\n";
