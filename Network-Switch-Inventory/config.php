<?php
/**
 * Database Configuration
 * Network Switch Inventory Management System
 */

// Database credentials
define('DB_HOST', 'localhost');
define('DB_NAME', 'network_inventory');
define('DB_USER', 'root');
define('DB_PASS', '');

// Create PDO connection
function getDBConnection()
{
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        return $pdo;
    } catch (PDOException $e) {
        // Log error and return user-friendly message
        error_log("Database Connection Error: " . $e->getMessage());
        http_response_code(500);
        die(json_encode([
            'success' => false,
            'message' => 'Database connection failed. Please check your configuration.'
        ]));
    }
}

/**
 * Get all system settings or a specific setting
 */
function getSystemSettings($pdo = null)
{
    if (!$pdo)
        $pdo = getDBConnection();
    try {
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
        $settings = [];
        while ($row = $stmt->fetch()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        return $settings;
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Log an action to the audit trail
 */
function logAudit($pdo, $userId, $action, $resourceType, $resourceId = null, $details = null)
{
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'system';
        $sql = "INSERT INTO audit_logs (user_id, action, resource_type, resource_id, details, ip_address) 
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId, $action, $resourceType, $resourceId, $details, $ip]);
        return true;
    } catch (Exception $e) {
        error_log("Audit Log Error: " . $e->getMessage());
        return false;
    }
}

// Set timezone
date_default_timezone_set('Asia/Manila');

