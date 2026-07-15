<?php
/**
 * Vercel Entry Point
 * Routes all requests to the appropriate file in the project root.
 */

// Get the request URI and strip query string
$uri = $_SERVER['REQUEST_URI'];
$path = parse_url($uri, PHP_URL_PATH);

// Remove leading slash
$path = ltrim($path, '/');

// Set document root for includes to work correctly
$root = dirname(__DIR__);
chdir($root);

// Map the path to a real file
if ($path === '' || $path === 'index.php') {
    require $root . '/index.php';
} elseif ($path === 'login.php') {
    require $root . '/login.php';
} elseif ($path === 'logout.php') {
    require $root . '/logout.php';
} elseif ($path === 'health.php') {
    require $root . '/health.php';
} elseif (preg_match('#^modules/([^/]+)/index\.php#', $path, $m)) {
    $module = $m[1];
    $file = $root . '/modules/' . $module . '/index.php';
    if (file_exists($file)) {
        require $file;
    } else {
        http_response_code(404);
        echo '404 Not Found';
    }
} elseif (preg_match('#^api/(.+)#', $path, $m)) {
    // Avoid infinite loop
    http_response_code(404);
    echo '404 Not Found';
} else {
    // Try to serve the file directly from root
    $file = $root . '/' . $path;
    if (file_exists($file) && is_file($file)) {
        require $file;
    } else {
        // Fallback to root index (SPA-style)
        require $root . '/index.php';
    }
}
