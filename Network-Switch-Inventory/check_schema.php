<?php
require_once 'config.php';
header('Content-Type: application/json');

try {
    $pdo = getDBConnection();
    $stmt = $pdo->query("DESCRIBE switches");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'columns' => $columns]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>