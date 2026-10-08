<?php

// Separate processes exercise request-sensitive config without connecting to MySQL.
$scenario = $argv[1] ?? 'tunnel';
define('ENVIRONMENT', $scenario === 'production' ? 'production' : 'development');
define('FCPATH', dirname(__DIR__, 2) . '/public/');
$preview = $scenario === 'invalid' ? 'https://user:secret@preview.ngrok-free.dev/' : 'https://preview.ngrok-free.dev/';
$_ENV['app.previewBaseURL'] = $preview;
$_SERVER['HTTP_HOST'] = match ($scenario) {
    'localhost' => 'localhost:8080',
    'unknown' => 'untrusted.example',
    'forwarded' => 'localhost:8080',
    default => 'preview.ngrok-free.dev',
};
$_SERVER['HTTP_X_FORWARDED_HOST'] = 'preview.ngrok-free.dev';

require dirname(__DIR__, 2) . '/app/Config/Paths.php';
$paths = new Config\Paths();
require $paths->systemDirectory . '/Boot.php';
CodeIgniter\Boot::bootConsole($paths);

$app = new Config\App();
$database = new Config\Database();
$expected = $scenario === 'production' ? $app->serverBaseURL
    : ($scenario === 'tunnel' ? $preview : $app->localBaseURL);
if ($app->baseURL !== $expected) {
    throw new RuntimeException("Wrong URL for {$scenario}.");
}
$profile = ENVIRONMENT === 'production' ? 'server' : 'local';
if ($database->default['database'] !== $database->{$profile}['database']) {
    throw new RuntimeException('Preview changed the database profile.');
}
if ($scenario === 'tunnel' && ! isset($app->proxyIPs['127.0.0.1'], $app->proxyIPs['::1'])) {
    throw new RuntimeException('Local tunnel proxy was not configured.');
}
if ($scenario !== 'tunnel' && $app->proxyIPs !== []) {
    throw new RuntimeException('Proxy trust leaked outside preview requests.');
}
echo "{$scenario}: preview URL and database checks passed.\n";
