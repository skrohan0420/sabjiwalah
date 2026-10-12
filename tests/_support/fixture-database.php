<?php
// Optional override for owned HTTP fixtures when the normal local database is unavailable.
// This file is never loaded by application routes.
$fixturePort = getenv('SW_TEST_DB_PORT');
if ($fixturePort !== false && $fixturePort !== '') {
    $fixtureDatabase = (string) getenv('SW_TEST_DB_NAME');
    if (!in_array(PHP_SAPI, ['cli', 'cli-server'], true)
        || !ctype_digit($fixturePort) || (int) $fixturePort < 1024 || (int) $fixturePort > 65535
        || !preg_match('/^sw_[a-z0-9_]+$/D', $fixtureDatabase)) {
        throw new RuntimeException('Invalid disposable database configuration.');
    }
    $fixtureConfig = config('Database');
    $fixtureConfig->local = array_replace($fixtureConfig->local, [
        'hostname' => '127.0.0.1', 'port' => (int) $fixturePort,
        'username' => (string) getenv('SW_TEST_DB_USER'), 'password' => (string) getenv('SW_TEST_DB_PASSWORD'),
        'database' => $fixtureDatabase, 'DBDriver' => 'MySQLi', 'DBPrefix' => '',
    ]);
    $fixtureConfig->default = $fixtureConfig->local;
}
