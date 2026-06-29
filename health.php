<?php
// Diagnostic health check — no session, no heavy includes
http_response_code(200);
header('Content-Type: application/json');

$dbUrl  = getenv('DATABASE_URL');
$dbTest = 'not tested';

if ($dbUrl) {
    try {
        $parts = parse_url($dbUrl);
        $dsn   = sprintf(
            'pgsql:host=%s;port=%s;dbname=%s;connect_timeout=3',
            $parts['host'],
            $parts['port'] ?? 5432,
            ltrim($parts['path'], '/')
        );
        $pdo    = new PDO($dsn, $parts['user'], $parts['pass']);
        $dbTest = 'connected';
    } catch (Exception $e) {
        $dbTest = 'error: ' . $e->getMessage();
    }
} else {
    $dbTest = 'DATABASE_URL not set';
}

echo json_encode([
    'status'       => 'ok',
    'php'          => phpversion(),
    'time'         => date('Y-m-d H:i:s'),
    'db_url'       => $dbUrl ? 'SET' : 'NOT SET',
    'db_test'      => $dbTest,
    'pdo_pgsql'    => extension_loaded('pdo_pgsql') ? 'loaded' : 'MISSING',
    'app_env'      => getenv('APP_ENV') ?: 'not set',
    'port'         => getenv('PORT') ?: 'not set',
], JSON_PRETTY_PRINT);
