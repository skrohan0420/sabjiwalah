<?php

// Each environment runs in its own process. No database connections are made.
$environment = $argv[1] ?? 'development';
if (! in_array($environment, ['development', 'production', 'testing'], true)) {
    throw new InvalidArgumentException('Unsupported test environment.');
}
define('ENVIRONMENT', $environment);
define('FCPATH', dirname(__DIR__, 2) . '/public/');
require dirname(__DIR__, 2) . '/app/Config/Paths.php';
$paths = new Config\Paths();
require $paths->systemDirectory . '/Boot.php';
CodeIgniter\Boot::bootConsole($paths);

$app = new Config\App();
$database = new Config\Database();
function check(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}
if ($environment === 'testing') {
    check($database->defaultGroup === 'tests', 'Automated tests must use the tests connection.');
} else {
    $profile = $environment === 'production' ? 'server' : 'local';
    check($app->baseURL === env("app.{$profile}BaseURL"), 'Wrong base URL selected.');
    check($app->forceGlobalSecureRequests === filter_var(env("app.{$profile}ForceHTTPS"), FILTER_VALIDATE_BOOLEAN), 'Wrong HTTPS setting selected.');
    check($database->defaultGroup === 'default', 'Wrong database group selected.');
    foreach (['hostname', 'database', 'username', 'password', 'DBDriver', 'port'] as $field) {
        check((string) $database->default[$field] === (string) env("database.{$profile}.{$field}"), "Wrong database {$field} selected.");
    }
    if ($environment === 'production') {
        check($database->default['DBDebug'] === false, 'Server DB debug must be disabled.');
    }
}
echo "{$environment}: environment selection checks passed.\n";
