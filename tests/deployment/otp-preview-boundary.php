<?php
// No database or server required; verify the preview exception and production boundary.
define('ENVIRONMENT', in_array('--production', $argv, true) ? 'production' : 'development');
define('FCPATH', dirname(__DIR__, 2) . '/public/');
require dirname(__DIR__, 2) . '/app/Config/Paths.php';
$paths = new Config\Paths();
require $paths->systemDirectory . '/Boot.php';
CodeIgniter\Boot::bootConsole($paths);
$otpConfig = config('Otp');
$otpConfig->developmentMode = true;
$otpConfig->previewDevelopmentMode = true;
config('App')->previewBaseURL = 'https://sample.ngrok-free.dev/';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_HOST'] = 'sample.ngrok-free.dev';
$_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.10';
$_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
$gate = new ReflectionMethod(App\Services\OtpService::class, 'requireLocalDevelopment');
$service = new App\Services\OtpService();
function checkGate(bool $allowed): void
{
    global $gate, $service;
    try {
        $gate->invoke($service);
        if (! $allowed) throw new RuntimeException('Unexpected OTP bypass.');
    } catch (App\Services\OtpUnavailableException $e) {
        if ($allowed) throw new RuntimeException('Configured preview was rejected.', 0, $e);
    }
}
checkGate(ENVIRONMENT === 'development');
$_SERVER['HTTP_HOST'] = 'sample.ngrok-free.dev:443';
checkGate(ENVIRONMENT === 'development');
$otpConfig->previewDevelopmentMode = false;
checkGate(false);
$otpConfig->previewDevelopmentMode = true;
$otpConfig->developmentMode = false;
checkGate(false);
$otpConfig->developmentMode = true;
$_SERVER['HTTP_HOST'] = 'other.ngrok-free.dev';
checkGate(false);
$_SERVER['HTTP_HOST'] = 'sample.ngrok-free.dev';
$_SERVER['REMOTE_ADDR'] = '203.0.113.10';
checkGate(false);
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
config('App')->previewBaseURL = 'https://user@sample.ngrok-free.dev/';
checkGate(false);
config('App')->previewBaseURL = 'https://sample.ngrok-free.dev/';
$_SERVER['HTTP_HOST'] = 'localhost:8080';
checkGate(false); // Preview opt-in does not bypass forwarded localhost requests.
unset($_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['HTTP_X_FORWARDED_PROTO']);
checkGate(ENVIRONMENT === 'development');
echo 'PASS: preview OTP boundary (' . ENVIRONMENT . "). No database changes.\n";
