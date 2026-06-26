<?php
function logAudit($pdo, $title, $description, $log_level = 'Info', $action_type = 'Other', $resource_id = null)
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $user_id = $_SESSION['user_id'] ?? null;
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;

    try {
        $stmt = $pdo->prepare("INSERT INTO system_logs (user_id, title, description, log_level, action_type, resource_id, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $title, $description, $log_level, $action_type, $resource_id, $ip_address]);
        return true;
    } catch (Exception $e) {
        // Log to PHP error log if database logging fails
        error_log("Audit logging failed: " . $e->getMessage());
        return false;
    }
}
?>