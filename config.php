<?php
// Security Headers
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");
/**
 * Hardware Network Management - Central Configuration
 */

// Database Credentials — read from environment variables
// Railway automatically provides DATABASE_URL for linked PostgreSQL services
// For local development, set individual DB_* env vars or use the fallbacks below
if (getenv('DATABASE_URL')) {
    // Parse Railway's DATABASE_URL: postgresql://user:pass@host:port/dbname
    $dbUrl = parse_url(getenv('DATABASE_URL'));
    define('DB_HOST', $dbUrl['host']);
    define('DB_PORT', $dbUrl['port'] ?? 5432);
    define('DB_NAME', ltrim($dbUrl['path'], '/'));
    define('DB_USER', $dbUrl['user']);
    define('DB_PASS', $dbUrl['pass']);
} else {
    define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
    define('DB_PORT', getenv('DB_PORT') ?: 5432);
    define('DB_NAME', getenv('DB_NAME') ?: 'unified_network_inventory');
    define('DB_USER', getenv('DB_USER') ?: 'postgres');
    define('DB_PASS', getenv('DB_PASS') ?: 'ef95d058688B@');
}

// Application Settings
define('APP_NAME', 'HSNM');

// On Railway (production) the app is served from the root, so BASE_URL = ''
// Locally under XAMPP it runs in /HSNM/, so BASE_URL = '/HSNM'
if (!defined('BASE_URL')) {
    // getenv() returns false (bool) when variable is not set at all
    $isProduction = (
        getenv('APP_ENV') === 'production' || 
        (getenv('RAILWAY_ENVIRONMENT') !== false && getenv('RAILWAY_ENVIRONMENT') !== '') ||
        getenv('RENDER') === 'true'
    );
    if ($isProduction) {
        define('BASE_URL', '');
    } else {
        $folderName = basename(__DIR__);
        define('BASE_URL', '/' . rawurlencode($folderName));
    }
}

// Error Reporting (Disabled in production for security)
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Database Connection
$pdo = null;
try {
    // Neon.tech and many cloud providers require SSL. 
    // We add sslmode=require to ensure the connection is accepted.
    $pdo = new PDO(
        // connect_timeout=5 ensures fast failure instead of 30s hang when DB unreachable
        "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";sslmode=require;connect_timeout=5",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_PERSISTENT         => false
        ]
    );

} catch (PDOException $e) {
    // Don't die() — that kills PHP before Apache can respond to health checks
    // Instead serve a proper 503 so Railway health check gets a response
    $dbError = $e->getMessage();
    if (!defined('DB_ERROR')) define('DB_ERROR', $dbError);
    // Log the real error server-side
    error_log("Database Connection Error: " . $dbError);
}

// Session Management
// Security: Prevent browser caching of authenticated pages
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

if (session_status() === PHP_SESSION_NONE) {
    // Security: HttpOnly and SameSite attributes
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

/**
 * CSRF Protection
 */
function generateCsrfToken()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken($token)
{
    // Allow CLI scripts to bypass CSRF check
    if (php_sapi_name() === 'cli' || defined('TEST_MODE')) {
        return true;
    }

    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function getCsrfInput()
{
    $token = generateCsrfToken();
    return '<input type="hidden" name="csrf_token" value="' . $token . '">';
}

/**
 * Escape CSV field to prevent formula injection
 */
function escapeCsvField($field)
{
    if (empty($field))
        return '';
    $dangerous = ['=', '+', '-', '@'];
    if (in_array(substr($field, 0, 1), $dangerous)) {
        return "'" . $field;
    }
    return $field;
}

/**
 * Helper function to check login status
 */
function requireLogin()
{
    // Allow CLI scripts to bypass login check
    if (php_sapi_name() === 'cli' || defined('TEST_MODE')) {
        return;
    }

    if (!isset($_SESSION['user_id'])) {
        header("Location: " . BASE_URL . "/login");
        exit;
    }
}

function logAudit($pdo, $action, $details, $resource_type = 'system', $resource_id = null)
{
    try {
        $user_id = $_SESSION['user_id'] ?? null;
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action_type, details, resource_type, resource_id, ip_address, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$user_id, $action, $details, $resource_type, $resource_id, $ip]);
    } catch (Exception $e) {
        // Silent fail for logs
    }
}

/**
 * Auto-log a changelog entry from any module action.
 * Increments patch version automatically.
 */
function logChangelog($pdo, $change_type, $module, $title, $description = '')
{
    try {
        // Get last version
        $stmt = $pdo->query("SELECT version FROM changelog ORDER BY id DESC LIMIT 1");
        $last_ver = $stmt->fetchColumn();

        if (!$last_ver || !preg_match('/^(\d+)\.(\d+)\.(\d+)$/', $last_ver, $m)) {
            $version = $last_ver ? $last_ver . '.1' : '1.0.0';
        } else {
            $version = $m[1] . '.' . $m[2] . '.' . ($m[3] + 1);
        }

        // Skip duplicate title on same day
        $check = $pdo->prepare("SELECT id FROM changelog WHERE title = ? AND change_date = CURRENT_DATE");
        $check->execute([$title]);
        if ($check->fetch()) {
            return; // Silently skip
        }

        $stmt = $pdo->prepare("INSERT INTO changelog (version, change_date, change_type, module, title, description) VALUES (?, CURRENT_DATE, ?, ?, ?, ?)");
        $stmt->execute([$version, $change_type, $module, $title, $description]);
    } catch (Exception $e) {
        // Silent fail — changelog should never break main operations
    }
}

/**
 * Get the latest application version from changelog
 */
function getAppVersion($pdo)
{
    try {
        $stmt = $pdo->query("SELECT version FROM changelog ORDER BY change_date DESC, id DESC LIMIT 1");
        $result = $stmt->fetch();
        return $result ? 'v' . $result['version'] : 'v1.0.0';
    } catch (Exception $e) {
        return 'v1.0.0'; // Fallback version
    }
}

/**
 * Common Status Color Helpers
 */
// Routers & Switches
function getStatusColor($status)
{
    switch (strtolower($status)) {
        case 'active':
            return 'text-[#10b981]';
        case 'maintenance':
            return 'text-[#fbbf24]';
        case 'inactive':
            return 'text-slate-500';
        default:
            return 'text-slate-400';
    }
}
function getStatusDotClass($status)
{
    switch (strtolower($status)) {
        case 'active':
            return 'bg-[#10b981] shadow-lg shadow-[#10b981]/50';
        case 'maintenance':
            return 'bg-[#fbbf24] shadow-lg shadow-[#fbbf24]/50';
        case 'inactive':
            return 'bg-slate-500';
        default:
            return 'bg-slate-400';
    }
}

// Switches (Aliases for compatibility)
function getSwitchStatusColor($status)
{
    return getStatusColor($status);
}
function getSwitchStatusDotClass($status)
{
    return getStatusDotClass($status);
}

// IP Addresses
function getIpStatusColor($status)
{
    switch (strtolower($status)) {
        case 'active':
            return 'text-[#10b981]';
        case 'reserved':
            return 'text-blue-500';
        case 'offline':
            return 'text-slate-500';
        case 'available':
            return 'text-cyan-400';
        case 'conflict':
            return 'text-[#fa6238]'; // legacy fallback
        default:
            return 'text-slate-400';
    }
}
function getIpDotClass($status)
{
    switch (strtolower($status)) {
        case 'active':
            return 'bg-[#10b981] shadow-lg shadow-[#10b981]/50';
        case 'reserved':
            return 'bg-blue-500';
        case 'offline':
            return 'bg-slate-500';
        case 'available':
            return 'bg-cyan-400';
        case 'conflict':
            return 'bg-[#fa6238] animate-pulse'; // legacy fallback
        default:
            return 'bg-slate-400';
    }
}

/**
 * Check User Module Access
 */
function canAccessModule($module)
{
    // If not logged in or no permissions set, default allow (or handle in requireLogin)
    if (!isset($_SESSION['module_access']) || $_SESSION['module_access'] === null) {
        return true;
    }

    $allowed_modules = json_decode($_SESSION['module_access'], true);
    if (!is_array($allowed_modules)) {
        return true;
    }

    // Admin always has access to settings, but maybe not other things if strictly restricted?
    // Let's assume the session list is the source of truth.

    return in_array($module, $allowed_modules);
}
?>