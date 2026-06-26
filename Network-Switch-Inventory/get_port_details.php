<?php
require_once 'config.php';
header('Content-Type: application/json');

try {
    $pdo = getDBConnection();
    $stmt = $pdo->query("SELECT DISTINCT ports_detail FROM switches ORDER BY ports_detail");
    $details = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo json_encode(['success' => true, 'details' => $details]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>