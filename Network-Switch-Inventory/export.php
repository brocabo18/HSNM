<?php
/**
 * CSV Export for Network Switch Inventory
 * Exports filtered switch data to CSV format
 */

session_start();
require_once 'config.php';
require_once 'auth.php';

// Require authentication
requireAuth();

try {
    $pdo = getDBConnection();

    // Get filter parameters
    $manufacturer = $_GET['manufacturer'] ?? '';
    $building_location = $_GET['building_location'] ?? '';
    $floor = $_GET['floor'] ?? '';
    $status = $_GET['status'] ?? '';
    $search = $_GET['search'] ?? '';

    // Build query with filters
    $sql = "SELECT * FROM switches WHERE 1=1";
    $params = [];

    if (!empty($manufacturer)) {
        $sql .= " AND manufacturer = :manufacturer";
        $params[':manufacturer'] = $manufacturer;
    }

    if (!empty($building_location)) {
        $sql .= " AND building_location = :building_location";
        $params[':building_location'] = $building_location;
    }

    if (!empty($floor)) {
        $sql .= " AND floor = :floor";
        $params[':floor'] = $floor;
    }

    if (!empty($status)) {
        $sql .= " AND status = :status";
        $params[':status'] = $status;
    }

    if (!empty($search)) {
        $sql .= " AND (switch_id ILIKE :search OR model ILIKE :search OR serial ILIKE :search OR ip ILIKE :search)";
        $params[':search'] = "%{$search}%";
    }

    $sql .= " ORDER BY switch_id ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $switches = $stmt->fetchAll();

    // Set headers for CSV download
    $filename = "network_switches_" . date('Y-m-d_His') . ".csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Create output stream
    $output = fopen('php://output', 'w');

    // Add BOM for Excel UTF-8 support
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

    // Write header row
    fputcsv($output, [
        'Switch ID',
        'Model',
        'Manufacturer',
        'Serial Number',
        'IP Address',
        'MAC Address',
        'Building Location',
        'Floor',
        'Ports',
        'Port Details',
        'Status',
        'Personnel',
        'Last Maintenance',
        'Next Maintenance',
        'Remarks',
        'Created At',
        'Updated At'
    ]);

    // Write data rows
    foreach ($switches as $switch) {
        fputcsv($output, [
            $switch['switch_id'],
            $switch['model'],
            $switch['manufacturer'],
            $switch['serial'],
            $switch['ip'],
            $switch['mac'],
            $switch['building_location'],
            $switch['floor'],
            $switch['ports'],
            $switch['ports_detail'],
            $switch['status'],
            $switch['personnel'],
            $switch['last_maintenance'],
            $switch['next_maintenance'],
            $switch['remarks'],
            $switch['created_at'],
            $switch['updated_at']
        ]);
    }

    fclose($output);
    exit;

} catch (Exception $e) {
    error_log("Export Error: " . $e->getMessage());
    http_response_code(500);
    echo "Error generating CSV export. Please try again.";
    exit;
}
