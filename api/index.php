<?php
ob_start();
// api/index.php
// Vercel Single-Lambda Router
$request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
// ... (the rest of the file remains exactly the same)
$path = ltrim($request_uri, '/');

if ($path === '' || $path === 'api' || $path === 'api/index.php') {
    $path = 'index.php';
}

$base_dir = realpath(__DIR__ . '/..');

// Handle extensionless URLs (like /login -> login.php) and Directory Indexes (like /modules/routers -> /modules/routers/index.php)
if (!preg_match('/\.php$/', $path)) {
    if (file_exists($base_dir . '/' . $path . '.php')) {
        $path .= '.php';
    } elseif (is_dir($base_dir . '/' . $path) && file_exists($base_dir . '/' . $path . '/index.php')) {
        $path = rtrim($path, '/') . '/index.php';
    }
}

$target_file = realpath($base_dir . '/' . $path);

if ($target_file && strpos($target_file, $base_dir) === 0 && file_exists($target_file) && is_file($target_file)) {
    if (pathinfo($target_file, PATHINFO_EXTENSION) === 'php') {
        // Run from the script's directory so relative includes (e.g. require 'config.php') work natively
        chdir(dirname($target_file));
        
        // Mock server variables for legacy apps
        $_SERVER['SCRIPT_FILENAME'] = $target_file;
        $_SERVER['SCRIPT_NAME'] = '/' . $path;
        $_SERVER['PHP_SELF'] = '/' . $path;
        
        require $target_file;
    } else {
        // Fallback for static files if Vercel didn't catch them
        $mime = mime_content_type($target_file);
        header("Content-Type: " . ($mime ?: 'application/octet-stream'));
        readfile($target_file);
    }
} else {
    http_response_code(404);
    echo "404 Not Found";
}
