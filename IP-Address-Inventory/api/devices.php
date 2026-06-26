<?php
require_once '../config/config.php';
require_once '../includes/auth_check.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// GET - List devices
if ($method === 'GET' && empty($action)) {
    $search = $_GET['search'] ?? '';
    $status = $_GET['status'] ?? '';

    $whereClause = "WHERE 1=1";
    $params = [];

    if (!empty($search)) {
        $whereClause .= " AND (ip_address LIKE ? OR hostname LIKE ? OR mac_address LIKE ?)";
        $searchTerm = "%$search%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }

    if (!empty($status)) {
        $whereClause .= " AND status = ?";
        $params[] = $status;
    }

    $stmt = $pdo->prepare("SELECT * FROM ip_inventory $whereClause ORDER BY INET_ATON(ip_address)");
    $stmt->execute($params);
    $devices = $stmt->fetchAll();

    jsonResponse(true, 'Devices retrieved', $devices);
}

// GET - Get single device
if ($method === 'GET' && $action === 'get') {
    $id = $_GET['id'] ?? 0;

    $stmt = $pdo->prepare("SELECT * FROM ip_inventory WHERE id = ?");
    $stmt->execute([$id]);
    $device = $stmt->fetch();

    if ($device) {
        jsonResponse(true, 'Device found', $device);
    } else {
        jsonResponse(false, 'Device not found');
    }
}

// POST - Create device
if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    $ip = sanitizeInput($data['ip_address'] ?? '');
    $hostname = sanitizeInput($data['hostname'] ?? '');
    $mac = formatMAC(sanitizeInput($data['mac_address'] ?? ''));
    $status = sanitizeInput($data['status'] ?? 'offline');
    $deviceType = sanitizeInput($data['device_type'] ?? '');
    $location = sanitizeInput($data['location'] ?? '');
    $vlan = !empty($data['vlan_id']) ? (int) $data['vlan_id'] : null;
    $subnetId = !empty($data['subnet_id']) ? (int) $data['subnet_id'] : null;
    $notes = sanitizeInput($data['notes'] ?? '');

    // Validate IP
    if (!isValidIP($ip)) {
        jsonResponse(false, 'Invalid IP address');
    }

    // Check for duplicate IP
    $checkStmt = $pdo->prepare("SELECT id FROM ip_inventory WHERE ip_address = ?");
    $checkStmt->execute([$ip]);
    if ($checkStmt->fetch()) {
        jsonResponse(false, 'IP address already exists');
    }

    // Validate MAC if provided
    if (!empty($mac) && !isValidMAC($mac)) {
        jsonResponse(false, 'Invalid MAC address format');
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO ip_inventory 
            (ip_address, hostname, mac_address, status, device_type, location, vlan_id, subnet_id, notes, last_seen, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)
        ");

        $stmt->execute([
            $ip,
            $hostname,
            $mac,
            $status,
            $deviceType,
            $location,
            $vlan,
            $subnetId,
            $notes,
            getCurrentUserId()
        ]);

        $deviceId = $pdo->lastInsertId();

        // Log audit
        logAudit('create', 'device', $deviceId, "Created device: $ip ($hostname)");

        jsonResponse(true, 'Device created successfully', ['id' => $deviceId]);
    } catch (PDOException $e) {
        jsonResponse(false, 'Error creating device: ' . $e->getMessage());
    }
}

// PUT - Update device
if ($method === 'PUT') {
    $data = json_decode(file_get_contents('php://input'), true);

    $id = (int) ($data['id'] ?? 0);
    $ip = sanitizeInput($data['ip_address'] ?? '');
    $hostname = sanitizeInput($data['hostname'] ?? '');
    $mac = formatMAC(sanitizeInput($data['mac_address'] ?? ''));
    $status = sanitizeInput($data['status'] ?? 'offline');
    $deviceType = sanitizeInput($data['device_type'] ?? '');
    $location = sanitizeInput($data['location'] ?? '');
    $vlan = !empty($data['vlan_id']) ? (int) $data['vlan_id'] : null;
    $subnetId = !empty($data['subnet_id']) ? (int) $data['subnet_id'] : null;
    $notes = sanitizeInput($data['notes'] ?? '');

    // Validate IP
    if (!isValidIP($ip)) {
        jsonResponse(false, 'Invalid IP address');
    }

    // Check for duplicate IP (excluding current device)
    $checkStmt = $pdo->prepare("SELECT id FROM ip_inventory WHERE ip_address = ? AND id != ?");
    $checkStmt->execute([$ip, $id]);
    if ($checkStmt->fetch()) {
        jsonResponse(false, 'IP address already exists');
    }

    // Validate MAC if provided
    if (!empty($mac) && !isValidMAC($mac)) {
        jsonResponse(false, 'Invalid MAC address format');
    }

    try {
        $stmt = $pdo->prepare("
            UPDATE ip_inventory 
            SET ip_address = ?, hostname = ?, mac_address = ?, status = ?, 
                device_type = ?, location = ?, vlan_id = ?, subnet_id = ?, 
                notes = ?, last_seen = NOW()
            WHERE id = ?
        ");

        $stmt->execute([
            $ip,
            $hostname,
            $mac,
            $status,
            $deviceType,
            $location,
            $vlan,
            $subnetId,
            $notes,
            $id
        ]);

        // Log audit
        logAudit('update', 'device', $id, "Updated device: $ip ($hostname)");

        jsonResponse(true, 'Device updated successfully');
    } catch (PDOException $e) {
        jsonResponse(false, 'Error updating device: ' . $e->getMessage());
    }
}

// DELETE - Delete device
if ($method === 'DELETE') {
    $id = $_GET['id'] ?? 0;

    try {
        // Get device info for audit log
        $stmt = $pdo->prepare("SELECT ip_address, hostname FROM ip_inventory WHERE id = ?");
        $stmt->execute([$id]);
        $device = $stmt->fetch();

        if (!$device) {
            jsonResponse(false, 'Device not found');
        }

        // Delete device
        $deleteStmt = $pdo->prepare("DELETE FROM ip_inventory WHERE id = ?");
        $deleteStmt->execute([$id]);

        // Log audit
        logAudit('delete', 'device', $id, "Deleted device: {$device['ip_address']} ({$device['hostname']})");

        jsonResponse(true, 'Device deleted successfully');
    } catch (PDOException $e) {
        jsonResponse(false, 'Error deleting device: ' . $e->getMessage());
    }
}

jsonResponse(false, 'Invalid request');
