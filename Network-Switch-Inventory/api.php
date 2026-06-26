<?php
/**
 * API Endpoints for Network Switch Inventory
 * Handles CRUD operations via REST API
 */

header('Content-Type: application/json');
session_start();
require_once 'config.php';
require_once 'auth.php';

// Check authentication for all requests
if (!isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

// Get request method and action
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    $pdo = getDBConnection();

    switch ($action) {
        case 'list':
            handleList($pdo);
            break;

        case 'add':
            if ($method === 'POST') {
                requireAdmin();
                handleAdd($pdo);
            } else {
                sendError('Method not allowed', 405);
            }
            break;

        case 'update':
            if ($method === 'POST') {
                requireAdmin();
                handleUpdate($pdo);
            } else {
                sendError('Method not allowed', 405);
            }
            break;

        case 'delete':
            if ($method === 'POST') {
                requireAdmin();
                handleDelete($pdo);
            } else {
                sendError('Method not allowed', 405);
            }
            break;

        case 'filters':
            handleGetFilters($pdo);
            break;

        case 'statistics':
            handleGetStatistics($pdo);
            break;

        case 'import':
            if ($method === 'POST') {
                requireAdmin();
                handleImport($pdo);
            } else {
                sendError('Method not allowed', 405);
            }
            break;

        case 'users_list':
            requireAdmin();
            handleUsersList($pdo);
            break;
        case 'user_add':
            requireAdmin();
            handleUserAdd($pdo);
            break;
        case 'user_update':
            requireAdmin();
            handleUserUpdate($pdo);
            break;
        case 'user_delete':
            requireAdmin();
            handleUserDelete($pdo);
            break;
        case 'settings_get':
            handleSettingsGet($pdo);
            break;
        case 'settings_save':
            requireAdmin();
            handleSettingsSave($pdo);
            break;
        case 'notifications_list':
            handleNotificationsList($pdo);
            break;
        case 'notifications_read':
            if ($method === 'POST') {
                handleNotificationRead($pdo);
            } else {
                sendError('Method not allowed', 405);
            }
            break;
        case 'search':
            handleGlobalSearch($pdo);
            break;
        case 'audit_logs':
            requireAdmin();
            handleAuditLogsList($pdo);
            break;

        default:
            sendError('Invalid action', 400);
    }
} catch (Exception $e) {
    error_log("API Error: " . $e->getMessage());
    sendError('Internal server error', 500);
}

/**
 * List all switches with optional filters and pagination
 */
function handleList($pdo)
{
    $manufacturer = $_GET['manufacturer'] ?? '';
    $building_location = $_GET['building_location'] ?? '';
    $floor = $_GET['floor'] ?? '';
    $status = $_GET['status'] ?? '';
    $search = $_GET['search'] ?? '';

    // Pagination parameters
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = isset($_GET['limit']) ? max(1, min(100, intval($_GET['limit']))) : 10;
    $offset = ($page - 1) * $limit;

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
        // Use ILIKE for case-insensitive search
        $sql .= " AND (switch_id ILIKE :search OR model ILIKE :search OR serial ILIKE :search OR ip ILIKE :search)";
        $params[':search'] = "%{$search}%";
    }

    // Get total count for pagination
    $countSql = "SELECT COUNT(*) as total FROM switches WHERE 1=1";
    if (!empty($manufacturer)) {
        $countSql .= " AND manufacturer = :manufacturer";
    }
    if (!empty($building_location)) {
        $countSql .= " AND building_location = :building_location";
    }
    if (!empty($floor)) {
        $countSql .= " AND floor = :floor";
    }
    if (!empty($status)) {
        $countSql .= " AND status = :status";
    }
    if (!empty($search)) {
        $countSql .= " AND (switch_id ILIKE :search OR model ILIKE :search OR manufacturer ILIKE :search OR ip_address ILIKE :search OR mac_address ILIKE :search OR serial ILIKE :search OR building_location ILIKE :search OR floor ILIKE :search OR ports ILIKE :search OR port_details ILIKE :search OR personnel ILIKE :search OR remarks ILIKE :search)";
    }

    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $totalRecords = $countStmt->fetch()['total'];
    $totalPages = ceil($totalRecords / $limit);

    // Get paginated results
    $sql .= " ORDER BY switch_id ASC LIMIT :limit OFFSET :offset";

    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $switches = $stmt->fetchAll();

    sendSuccess([
        'switches' => $switches,
        'count' => count($switches),
        'pagination' => [
            'current_page' => $page,
            'per_page' => $limit,
            'total_pages' => $totalPages,
            'total_records' => $totalRecords
        ]
    ]);
}

/**
 * Add a new switch
 */
function handleAdd($pdo)
{
    $data = json_decode(file_get_contents('php://input'), true);

    // Validate required fields
    $required = ['building_location', 'floor', 'ports'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            sendError("Missing required field: {$field}", 400);
            return;
        }
    }

    $sql = "INSERT INTO switches (switch_id, model, manufacturer, serial, ip, mac, building_location, floor, ports, ports_detail, status, personnel, last_maintenance, next_maintenance, remarks) 
            VALUES (:switch_id, :model, :manufacturer, :serial, :ip, :mac, :building_location, :floor, :ports, :ports_detail, :status, :personnel, :last_maintenance, :next_maintenance, :remarks)";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':switch_id' => $data['switch_id'],
            ':model' => $data['model'],
            ':manufacturer' => $data['manufacturer'],
            ':serial' => $data['serial'],
            ':ip' => $data['ip'],
            ':mac' => $data['mac'],
            ':building_location' => $data['building_location'],
            ':floor' => $data['floor'],
            ':ports' => $data['ports'],
            ':ports_detail' => $data['ports_detail'] ?? '',
            ':status' => $data['status'] ?? 'Active',
            ':personnel' => $data['personnel'] ?? '',
            ':last_maintenance' => !empty($data['last_maintenance']) ? $data['last_maintenance'] : null,
            ':next_maintenance' => $data['next_maintenance'] ?? '',
            ':remarks' => $data['remarks'] ?? ''
        ]);

        $id = $pdo->lastInsertId();
        logAudit($pdo, $_SESSION['user_id'], 'CREATE', 'SWITCH', $id, "Added switch: " . ($data['switch_id'] ?: "No ID"));
        sendSuccess(['message' => 'Switch added successfully', 'id' => $id]);
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            sendError('Switch ID or Serial Number already exists', 409);
        } else {
            throw $e;
        }
    }
}

/**
 * Update an existing switch
 */
function handleUpdate($pdo)
{
    $data = json_decode(file_get_contents('php://input'), true);

    if (empty($data['id'])) {
        sendError('Missing switch ID', 400);
        return;
    }

    $sql = "UPDATE switches SET 
            switch_id = :switch_id,
            model = :model,
            manufacturer = :manufacturer,
            serial = :serial,
            ip = :ip,
            mac = :mac,
            building_location = :building_location,
            floor = :floor,
            ports = :ports,
            ports_detail = :ports_detail,
            status = :status,
            personnel = :personnel,
            last_maintenance = :last_maintenance,
            next_maintenance = :next_maintenance,
            remarks = :remarks
            WHERE id = :id";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':id' => $data['id'],
            ':switch_id' => $data['switch_id'],
            ':model' => $data['model'],
            ':manufacturer' => $data['manufacturer'],
            ':serial' => $data['serial'],
            ':ip' => $data['ip'],
            ':mac' => $data['mac'],
            ':building_location' => $data['building_location'],
            ':floor' => $data['floor'],
            ':ports' => $data['ports'],
            ':ports_detail' => $data['ports_detail'] ?? '',
            ':status' => $data['status'] ?? 'Active',
            ':personnel' => $data['personnel'] ?? '',
            ':last_maintenance' => !empty($data['last_maintenance']) ? $data['last_maintenance'] : null,
            ':next_maintenance' => $data['next_maintenance'] ?? '',
            ':remarks' => $data['remarks'] ?? ''
        ]);

        logAudit($pdo, $_SESSION['user_id'], 'UPDATE', 'SWITCH', $data['id'], "Updated switch details: " . ($data['switch_id'] ?: "ID " . $data['id']));
        sendSuccess(['message' => 'Switch updated successfully']);
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            sendError('Switch ID or Serial Number already exists', 409);
        } else {
            throw $e;
        }
    }
}

/**
 * Delete a switch
 */
function handleDelete($pdo)
{
    $data = json_decode(file_get_contents('php://input'), true);

    if (empty($data['id'])) {
        sendError('Missing switch ID', 400);
        return;
    }

    $sql = "DELETE FROM switches WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $data['id']]);

    if ($stmt->rowCount() > 0) {
        logAudit($pdo, $_SESSION['user_id'], 'DELETE', 'SWITCH', $data['id'], "Deleted switch record (ID: {$data['id']})");
        sendSuccess(['message' => 'Switch deleted successfully']);
    } else {
        sendError('Switch not found', 404);
    }
}

/**
 * Get filter options (manufacturers, locations, statuses)
 */
function handleGetFilters($pdo)
{
    $manufacturers = $pdo->query("SELECT DISTINCT manufacturer FROM switches ORDER BY manufacturer")->fetchAll(PDO::FETCH_COLUMN);
    $buildings = ['Capiz', 'Frontline', 'Ortho', 'Trauma', 'Surgery', 'Wellness', 'OPD', 'OB-Pedia', 'Medicine', 'Bayanihan ISO', 'Dietary', 'HOPSS', 'ACIS', 'Isolation', 'Bio-Safety'];
    $floors = ['GF', '2F', '3F', '4F', '5F', '6F', '7F'];
    $ports_list = ['5 ports', '8 ports', '16 ports', '24 ports', '48 ports'];
    $port_details = [
        '8x1G Base-T',
        '16x1G Base-T',
        '24x1G Base-T',
        '24xGE, 2xSFP',
        '48x1G Base-T',
        '24x1G PoE+, 4x10G SFP+',
        '48x1G PoE+, 4x10G SFP+',
        '24x1G, 4xSFP+',
        '48x1G, 4xSFP+',
        '48x1G/10G SFP+',
        '24x10G SFP+',
        '48x10G SFP+',
        '24x10G, 8x40G QSFP+',
        '48x10G SFP+, 6x40G QSFP+',
        '32x40G QSFP+',
        '32x100G QSFP28'
    ];
    $statuses = ['Active', 'Maintenance', 'Inactive'];

    sendSuccess([
        'manufacturers' => $manufacturers,
        'buildings' => $buildings,
        'floors' => $floors,
        'ports_list' => $ports_list,
        'port_details' => $port_details,
        'statuses' => $statuses
    ]);
}

/**
 * Import switches from CSV
 */
function handleUsersList($pdo)
{
    try {
        $stmt = $pdo->query("SELECT id, username, full_name, role, email, last_login, is_active, created_at FROM users ORDER BY created_at DESC");
        $users = $stmt->fetchAll();
        sendSuccess(['users' => $users]);
    } catch (Exception $e) {
        sendError('Failed to fetch users: ' . $e->getMessage());
    }
}

function handleUserAdd($pdo)
{
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $fullName = $_POST['full_name'] ?? '';
    $email = $_POST['email'] ?? '';
    $role = $_POST['role'] ?? 'Viewer';

    if (empty($username) || empty($password) || empty($fullName)) {
        sendError('Username, Password, and Full Name are required');
    }

    try {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, email, role, is_active) VALUES (?, ?, ?, ?, ?, 1)");
        $stmt->execute([$username, $hashedPassword, $fullName, $email, $role]);
        $id = $pdo->lastInsertId();
        logAudit($pdo, $_SESSION['user_id'], 'CREATE', 'USER', $id, "Added new user: " . $username);
        sendSuccess(['message' => 'User created successfully']);
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            sendError('Username already exists');
        } else {
            sendError('Failed to create user: ' . $e->getMessage());
        }
    }
}

function handleUserUpdate($pdo)
{
    $id = $_POST['id'] ?? '';
    $fullName = $_POST['full_name'] ?? '';
    $email = $_POST['email'] ?? '';
    $role = $_POST['role'] ?? '';
    $password = $_POST['password'] ?? '';
    $isActive = isset($_POST['is_active']) ? (int) $_POST['is_active'] : 1;

    if (empty($id)) {
        sendError('User ID is required');
    }

    try {
        $sql = "UPDATE users SET full_name = ?, email = ?, role = ?, is_active = ?";
        $params = [$fullName, $email, $role, $isActive];

        if (!empty($password)) {
            $sql .= ", password = ?";
            $params[] = password_hash($password, PASSWORD_DEFAULT);
        }

        $sql .= " WHERE id = ?";
        $params[] = $id;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        logAudit($pdo, $_SESSION['user_id'], 'UPDATE', 'USER', $id, "Updated user: " . $fullName);
        sendSuccess(['message' => 'User updated successfully']);
    } catch (Exception $e) {
        sendError('Failed to update user: ' . $e->getMessage());
    }
}

function handleUserDelete($pdo)
{
    $id = $_POST['id'] ?? '';

    if (empty($id)) {
        sendError('User ID is required');
    }

    if ($id == $_SESSION['user_id']) {
        sendError('You cannot delete your own account');
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);
        logAudit($pdo, $_SESSION['user_id'], 'DELETE', 'USER', $id, "Deleted user record (ID: $id)");
        sendSuccess(['message' => 'User deleted successfully']);
    } catch (Exception $e) {
        sendError('Failed to delete user: ' . $e->getMessage());
    }
}

function handleSettingsGet($pdo)
{
    try {
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
        $settings = [];
        while ($row = $stmt->fetch()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        sendSuccess(['settings' => $settings]);
    } catch (Exception $e) {
        sendError('Failed to fetch settings: ' . $e->getMessage());
    }
}

function handleSettingsSave($pdo)
{
    $settings = $_POST['settings'] ?? [];

    if (empty($settings) || !is_array($settings)) {
        sendError('No settings provided');
    }

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) 
                               ON CONFLICT (setting_key) DO UPDATE SET setting_value = EXCLUDED.setting_value");

        foreach ($settings as $key => $value) {
            $stmt->execute([$key, $value]);
        }

        $pdo->commit();
        logAudit($pdo, $_SESSION['user_id'], 'UPDATE', 'SETTINGS', null, "Updated system settings");
        sendSuccess(['message' => 'Settings saved successfully']);
    } catch (Exception $e) {
        $pdo->rollBack();
        sendError('Failed to save settings: ' . $e->getMessage());
    }
}

function handleImport($pdo)
{
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        sendError('No file uploaded or upload error', 400);
        return;
    }

    $file = $_FILES['file']['tmp_name'];
    $handle = fopen($file, 'r');

    // Skip BOM if present
    $bom = fread($handle, 3);
    if ($bom != "\xEF\xBB\xBF") {
        rewind($handle);
    }

    // Read headers
    $headers = fgetcsv($handle);
    $expectedHeaders = ['Switch ID', 'Model', 'Manufacturer', 'Serial Number', 'IP Address', 'MAC Address', 'Building Location', 'Floor', 'Ports', 'Port Details', 'Status', 'Personnel', 'Remarks'];

    // Normalize headers (case-insensitive and trim)
    $normalizedHeaders = array_map(function ($h) {
        return trim(strtolower($h));
    }, $headers);
    $normalizedExpected = array_map(function ($h) {
        return trim(strtolower($h));
    }, $expectedHeaders);

    // Basic header validation
    if (count(array_intersect($normalizedExpected, $normalizedHeaders)) < 8) {
        sendError('Invalid CSV format. Please use the template.', 400);
        return;
    }

    $successCount = 0;
    $errors = [];
    $rowCount = 0;

    $pdo->beginTransaction();

    try {
        $sql = "INSERT INTO switches (switch_id, model, manufacturer, serial, ip, mac, building_location, floor, ports, ports_detail, status, personnel, remarks) 
                VALUES (:switch_id, :model, :manufacturer, :serial, :ip, :mac, :building_location, :floor, :ports, :ports_detail, :status, :personnel, :remarks)";
        $stmt = $pdo->prepare($sql);

        while (($row = fgetcsv($handle)) !== FALSE) {
            $rowCount++;
            // Map row to data
            $data = [];
            foreach ($normalizedHeaders as $index => $header) {
                if (isset($row[$index])) {
                    $data[$header] = trim($row[$index]);
                }
            }

            // Map standard keys
            $switch_id = $data['switch id'] ?? '';
            $model = $data['model'] ?? '';
            $manufacturer = $data['manufacturer'] ?? '';
            $serial = $data['serial number'] ?? '';
            $ip = $data['ip address'] ?? '';
            $mac = $data['mac address'] ?? '';
            $building = $data['building location'] ?? '';
            $floor = $data['floor'] ?? '';
            $ports = $data['ports'] ?? '';
            $details = $data['port details'] ?? '';
            $status = $data['status'] ?? 'Active';
            $personnel = $data['personnel'] ?? '';
            $remarks = $data['remarks'] ?? '';

            // Basic validation
            if (empty($building) || empty($floor) || empty($ports)) {
                $errors[] = "Row {$rowCount}: Missing required placement/port fields";
                continue;
            }

            try {
                $stmt->execute([
                    ':switch_id' => $switch_id,
                    ':model' => $model,
                    ':manufacturer' => $manufacturer,
                    ':serial' => $serial,
                    ':ip' => $ip,
                    ':mac' => $mac,
                    ':building_location' => $building,
                    ':floor' => $floor,
                    ':ports' => $ports,
                    ':ports_detail' => $details,
                    ':status' => $status,
                    ':personnel' => $personnel,
                    ':remarks' => $remarks
                ]);
                $successCount++;
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $errors[] = "Row {$rowCount}: Duplicate Switch ID or Serial Number ({$switch_id})";
                } else {
                    $errors[] = "Row {$rowCount}: " . $e->getMessage();
                }
            }
        }

        $pdo->commit();
        sendSuccess([
            'message' => 'Import completed',
            'summary' => [
                'total' => $rowCount,
                'success' => $successCount,
                'failed' => count($errors),
                'errors' => array_slice($errors, 0, 10) // Only send first 10 errors
            ]
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        sendError('Import failed: ' . $e->getMessage(), 500);
    } finally {
        fclose($handle);
    }
}

function handleGetStatistics($pdo)
{
    // Status distribution
    $statusCounts = $pdo->query("SELECT status, COUNT(*) as count FROM switches GROUP BY status")->fetchAll();

    // Manufacturer distribution
    $manufacturerCounts = $pdo->query("SELECT manufacturer, COUNT(*) as count FROM switches GROUP BY manufacturer ORDER BY count DESC LIMIT 10")->fetchAll();

    // Building Location distribution
    $locationCounts = $pdo->query("SELECT building_location as location, COUNT(*) as count FROM switches GROUP BY building_location ORDER BY count DESC LIMIT 10")->fetchAll();

    // Maintenance timeline (upcoming maintenance in next 90 days)
    $maintenanceTimeline = $pdo->query("
        SELECT 
            CASE 
                WHEN next_maintenance = 'TBD' THEN 'Not Scheduled'
                WHEN next_maintenance = 'TODAY' THEN 'Today'
                WHEN TO_DATE(next_maintenance, 'YYYY-MM-DD') <= CURRENT_DATE + INTERVAL '30 day' THEN '0-30 Days'
                WHEN TO_DATE(next_maintenance, 'YYYY-MM-DD') <= CURRENT_DATE + INTERVAL '60 day' THEN '31-60 Days'
                WHEN TO_DATE(next_maintenance, 'YYYY-MM-DD') <= CURRENT_DATE + INTERVAL '90 day' THEN '61-90 Days'
                ELSE '90+ Days'
                ELSE '90+ Days'
            END as period,
            COUNT(*) as count
        FROM switches
        GROUP BY period
        ORDER BY period
    ")->fetchAll();

    // Total switches count
    $totalCount = $pdo->query("SELECT COUNT(*) as total FROM switches")->fetch()['total'];

    sendSuccess([
        'total_switches' => $totalCount,
        'status_distribution' => $statusCounts,
        'manufacturer_distribution' => $manufacturerCounts,
        'location_distribution' => $locationCounts,
        'maintenance_timeline' => $maintenanceTimeline
    ]);
}

function handleNotificationsList($pdo)
{
    $userId = $_SESSION['user_id'];
    $isAdmin = isAdmin();

    // Auto-generate some alerts if admin
    if ($isAdmin) {
        generateSystemAlerts($pdo);
    }

    $sql = "SELECT * FROM notifications WHERE (user_id = ? OR user_id IS NULL) AND is_read = 0 ORDER BY created_at DESC LIMIT 50";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId]);
    $notifications = $stmt->fetchAll();

    sendSuccess(['notifications' => $notifications]);
}

function handleNotificationRead($pdo)
{
    $data = json_decode(file_get_contents('php://input'), true);
    $id = $data['id'] ?? '';

    if (empty($id)) {
        sendError('Notification ID required');
        return;
    }

    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?");
    $stmt->execute([$id]);

    sendSuccess(['message' => 'Notification marked as read']);
}

function handleGlobalSearch($pdo)
{
    $q = $_GET['q'] ?? '';
    if (strlen($q) < 2) {
        sendSuccess(['results' => []]);
        return;
    }

    $searchTerm = "%$q%";
    $sql = "SELECT id, switch_id, model, manufacturer, serial, ip, mac, building_location, floor 
            FROM switches 
            WHERE switch_id ILIKE ? 
               OR model ILIKE ? 
               OR manufacturer ILIKE ? 
               OR serial ILIKE ? 
               OR ip ILIKE ? 
               OR mac ILIKE ? 
               OR building_location ILIKE ? 
               OR floor ILIKE ? 
               OR ports ILIKE ? 
               OR port_details ILIKE ? 
               OR personnel ILIKE ? 
               OR remarks ILIKE ? 
            LIMIT 20";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    $results = $stmt->fetchAll();

    sendSuccess(['results' => $results]);
}

function generateSystemAlerts($pdo)
{
    // Check for pending users
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE is_active = false");
    $pendingCount = $stmt->fetchColumn();
    if ($pendingCount > 0) {
        $title = "Pending User Registrations";
        $msg = "There are {$pendingCount} new user(s) awaiting approval.";
        // Avoid duplicates
        $check = $pdo->prepare("SELECT id FROM notifications WHERE title = ? AND is_read = 0");
        $check->execute([$title]);
        if (!$check->fetch()) {
            $stmt = $pdo->prepare("INSERT INTO notifications (title, message, type) VALUES (?, ?, 'info')");
            $stmt->execute([$title, $msg]);
        }
    }

    // Check for upcoming maintenance
    $stmt = $pdo->query("SELECT switch_id FROM switches WHERE next_maintenance = 'TODAY' AND status != 'Maintenance'");
    while ($switch = $stmt->fetch()) {
        $title = "Maintenance Due Today";
        $msg = "Switch {$switch['switch_id']} is scheduled for maintenance today.";
        $check = $pdo->prepare("SELECT id FROM notifications WHERE title = ? AND message = ? AND is_read = 0");
        $check->execute([$title, $msg]);
        if (!$check->fetch()) {
            $stmtInsert = $pdo->prepare("INSERT INTO notifications (title, message, type) VALUES (?, ?, 'warning')");
            $stmtInsert->execute([$title, $msg]);
        }
    }
}


/**
 * Send success response
 */
function sendSuccess($data, $code = 200)
{
    http_response_code($code);
    echo json_encode(['success' => true] + $data);
    exit;
}

function handleAuditLogsList($pdo)
{
    try {
        $sql = "SELECT a.*, u.full_name as user_name 
                FROM audit_logs a 
                JOIN users u ON a.user_id = u.id 
                ORDER BY a.created_at DESC 
                LIMIT 500";
        $stmt = $pdo->query($sql);
        $logs = $stmt->fetchAll();
        sendSuccess(['logs' => $logs]);
    } catch (Exception $e) {
        sendError('Failed to fetch audit logs: ' . $e->getMessage());
    }
}

/**
 * Send error response
 */
function sendError($message, $code = 400)
{
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}
