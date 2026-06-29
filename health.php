<?php
// Simple health check — no database required
// Railway uses /login.php but this helps diagnose issues
http_response_code(200);
header('Content-Type: application/json');
echo json_encode([
    'status' => 'ok',
    'php'    => phpversion(),
    'time'   => date('Y-m-d H:i:s'),
    'db_url' => getenv('DATABASE_URL') ? 'SET' : 'NOT SET',
    'env'    => getenv('APP_ENV') ?: 'not set',
]);
