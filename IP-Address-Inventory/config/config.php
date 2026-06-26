<?php
/**
 * IP Inventory Management System - Configuration File
 * Database connection and core functions
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'ip_inventory');

// Application configuration
define('APP_NAME', 'IP Manager');
define('APP_VERSION', '1.0.0');
define('ITEMS_PER_PAGE', 50);

// Security settings
define('SESSION_TIMEOUT', 3600); // 1 hour
define('PASSWORD_MIN_LENGTH', 8);

// Database connection
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

/**
 * Sanitize input data
 */
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['username']);
}

/**
 * Check if user has specific role
 */
function hasRole($role) {
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

/**
 * Check if user is admin
 */
function isAdmin() {
    return hasRole('admin');
}

/**
 * Get current user ID
 */
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get current username
 */
function getCurrentUsername() {
    return $_SESSION['username'] ?? 'Guest';
}

/**
 * Log audit event
 */
function logAudit($action, $entity_type = null, $entity_id = null, $description = null) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO audit_logs (user_id, username, action, entity_type, entity_id, description, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            getCurrentUserId(),
            getCurrentUsername(),
            $action,
            $entity_type,
            $entity_id,
            $description,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
        
        return true;
    } catch (PDOException $e) {
        error_log("Audit log error: " . $e->getMessage());
        return false;
    }
}

/**
 * Generate CSRF token
 */
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verifyCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Validate IP address
 */
function isValidIP($ip) {
    return filter_var($ip, FILTER_VALIDATE_IP) !== false;
}

/**
 * Validate MAC address
 */
function isValidMAC($mac) {
    return preg_match('/^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$/', $mac);
}

/**
 * Format MAC address
 */
function formatMAC($mac) {
    $mac = preg_replace('/[^0-9A-Fa-f]/', '', $mac);
    if (strlen($mac) === 12) {
        return strtoupper(implode(':', str_split($mac, 2)));
    }
    return $mac;
}

/**
 * Calculate CIDR range
 */
function cidrToRange($cidr) {
    list($ip, $prefix) = explode('/', $cidr);
    $start = ip2long($ip);
    $end = $start + pow(2, (32 - $prefix)) - 1;
    return [
        'start' => long2ip($start),
        'end' => long2ip($end),
        'total' => $end - $start + 1
    ];
}

/**
 * Format time ago
 */
function timeAgo($datetime) {
    $timestamp = strtotime($datetime);
    $diff = time() - $timestamp;
    
    if ($diff < 60) {
        return $diff <= 5 ? 'Just now' : $diff . ' secs ago';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . ' min' . ($mins > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } else {
        return date('M j, Y', $timestamp);
    }
}

/**
 * JSON response helper
 */
function jsonResponse($success, $message = '', $data = null) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

/**
 * Redirect helper
 */
function redirect($url) {
    header("Location: $url");
    exit;
}

/**
 * Check session timeout
 */
function checkSessionTimeout() {
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
        session_unset();
        session_destroy();
        return false;
    }
    $_SESSION['last_activity'] = time();
    return true;
}
