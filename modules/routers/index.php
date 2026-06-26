<?php
require_once '../../config.php';
requireLogin();

// Handle CSV Export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $stmt = $pdo->query("SELECT * FROM routers ORDER BY id ASC");
    $routers = $stmt->fetchAll();

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="routers_export_' . date('Y-m-d_His') . '.csv"');

    $output = fopen('php://output', 'w');
    // SSID, IP Address, MAC Address, LAN IP, Status, Admin User, Admin Password, Wifi Password, Brand, Location, Remarks
    fputcsv($output, ['SSID', 'IP Address', 'MAC Address', 'LAN IP', 'Status', 'Admin User', 'Admin Password', 'Wifi Password', 'Brand', 'Location', 'Remarks']);

    foreach ($routers as $router) {
        fputcsv($output, [
            escapeCsvField($router['ssid']),
            escapeCsvField($router['ip_address']),
            escapeCsvField($router['mac_address']),
            escapeCsvField($router['lan_ip']),
            escapeCsvField($router['status']),
            escapeCsvField($router['admin_user']),
            escapeCsvField($router['admin_password']),
            escapeCsvField($router['wifi_password']),
            escapeCsvField($router['brand']),
            escapeCsvField($router['location']),
            escapeCsvField($router['remarks'])
        ]);
    }

    fclose($output);
    exit;
}

// Handle Actions
$success_msg = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // CSRF verification
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $error_msg = "Security validation failed. Please refresh the page and try again.";
    } elseif ($_POST['action'] === 'import_csv') {
        if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === 0) {
            try {
                $file = fopen($_FILES['csv_file']['tmp_name'], 'r');
                $header = fgetcsv($file); // Skip header
                $imported = 0;
                $duplicates = 0;
                $errors = [];

                while (($row = fgetcsv($file)) !== false) {
                    if (count($row) < 2)
                        continue;

                    // Assuming CSV order: SSID, IP Address, MAC Address, LAN IP, Status, Admin User, Admin Password, Wifi Password, Brand, Location, Remarks
                    $ssid = $row[0] ?? '';
                    $ip = $row[1] ?? '';
                    $mac = $row[2] ?? '';
                    $lan_ip = $row[3] ?? '';
                    $status = $row[4] ?? 'Offline';
                    $admin_user = $row[5] ?? 'admin';
                    $admin_pass = $row[6] ?? '';
                    $wifi_pass = $row[7] ?? '';
                    $brand = $row[8] ?? '';
                    $loc = $row[9] ?? '';
                    $remarks = $row[10] ?? '';
                    $sn = $row[11] ?? 'SN-' . time() . '-' . rand(100, 999);

                    // Duplicate Check
                    $check_clauses = [];
                    $check_params = [];

                    if (!empty($ip)) {
                        $check_clauses[] = "ip_address = ?";
                        $check_params[] = $ip;
                    }
                    if (!empty($mac)) {
                        $check_clauses[] = "mac_address = ?";
                        $check_params[] = $mac;
                    }

                    if (!empty($check_clauses)) {
                        $check_sql = "SELECT COUNT(*) FROM routers WHERE " . implode(" OR ", $check_clauses);
                        $chk = $pdo->prepare($check_sql);
                        $chk->execute($check_params);
                        if ($chk->fetchColumn() > 0) {
                            $duplicates++;
                            continue;
                        }
                    }

                    try {
                        $stmt = $pdo->prepare("INSERT INTO routers (ssid, ip_address, mac_address, lan_ip, status, admin_user, admin_password, wifi_password, brand, location, remarks, serial_number) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$ssid, $ip, $mac, $lan_ip, $status, $admin_user, $admin_pass, $wifi_pass, $brand, $loc, $remarks, $sn]);
                        $imported++;
                    } catch (Exception $e) {
                        $errors[] = "Row " . ($imported + $duplicates + 1) . ": " . $e->getMessage();
                    }
                }

                fclose($file);

                $msg_parts = ["Imported $imported routers"];
                if ($duplicates > 0)
                    $msg_parts[] = "$duplicates duplicates skipped";
                if (!empty($errors))
                    $msg_parts[] = count($errors) . " failed";

                $success_msg = implode(", ", $msg_parts) . ".";
                logAudit($pdo, 'import_routers', "Imported $imported routers (Skipped $duplicates)", 'router', null);

            } catch (Exception $e) {
                $error_msg = "Error importing CSV: " . $e->getMessage();
            }
        }
    } elseif ($_POST['action'] === 'add_router') {
        $ssid = $_POST['ssid'] ?? '';
        $ip = $_POST['ip_address'] ?? '';
        $mac = $_POST['mac_address'] ?? '';
        $lan_ip = $_POST['lan_ip'] ?? '';
        $status = $_POST['status'] ?? 'Offline';
        $admin_user = $_POST['admin_user'] ?? 'admin';
        $admin_pass = $_POST['admin_password'] ?? '';
        $wifi_pass = $_POST['wifi_password'] ?? '';
        $brand = $_POST['brand'] ?? '';

        // Merge Location Fields (Router Module)
        $building = $_POST['building'] ?? '';
        $floor = $_POST['floor'] ?? '';
        $dept_input = $_POST['department'] ?? '';
        $location = trim("$building $floor $dept_input");

        $remarks = $_POST['remarks'] ?? '';
        $sn = $_POST['serial_number'] ?? 'SN-' . time();

        if ($brand && $ssid) {
            // Check Duplicates
            $dups = [];
            if (!empty($ip)) {
                $c = $pdo->prepare("SELECT ssid, brand FROM routers WHERE ip_address = ? LIMIT 1");
                $c->execute([$ip]);
                $existing = $c->fetch(PDO::FETCH_ASSOC);
                if ($existing) {
                    $ctx = $existing['ssid'] . " (" . $existing['brand'] . ")";
                    $dups[] = "IP Address '$ip' (assigned to $ctx)";
                }
            }
            if (!empty($mac)) {
                $c = $pdo->prepare("SELECT ssid, brand FROM routers WHERE mac_address = ? LIMIT 1");
                $c->execute([$mac]);
                $existing = $c->fetch(PDO::FETCH_ASSOC);
                if ($existing) {
                    $ctx = $existing['ssid'] . " (" . $existing['brand'] . ")";
                    $dups[] = "MAC Address '$mac' (assigned to $ctx)";
                }
            }
            if (!empty($sn) && strpos($sn, 'SN-') !== 0) { // Don't check auto-generated SNs usually
                $c = $pdo->prepare("SELECT ssid, brand FROM routers WHERE serial_number = ? LIMIT 1");
                $c->execute([$sn]);
                $existing = $c->fetch(PDO::FETCH_ASSOC);
                if ($existing) {
                    $ctx = $existing['ssid'] . " (" . $existing['brand'] . ")";
                    $dups[] = "Serial Number '$sn' (assigned to $ctx)";
                }
            }

            if (!empty($dups)) {
                $error_msg = "Error: Duplicate found for " . implode(' and ', $dups) . ".";
            } else {
                try {
                    $stmt = $pdo->prepare("INSERT INTO routers (serial_number, brand, location, ip_address, mac_address, lan_ip, ssid, wifi_password, admin_user, admin_password, status, remarks, last_seen) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                    $stmt->execute([$sn, $brand, $location, $ip, $mac, $lan_ip, $ssid, $wifi_pass, $admin_user, $admin_pass, $status, $remarks]);
                    $new_router_id = $pdo->lastInsertId();
                    $add_detail = "Added Router | SSID: $ssid | Brand: $brand | IP: $ip | MAC: $mac | LAN IP: $lan_ip | Location: $location | Status: $status | Serial: $sn";
                    if ($remarks)
                        $add_detail .= " | Remarks: $remarks";
                    logAudit($pdo, 'add_router', $add_detail, 'router', $new_router_id);
                    /* logChangelog($pdo, 'feature', 'Router Inventory', "Added Router: $ssid ($brand)", "IP: $ip | Location: $location | Status: $status"); removed */
                    $success_msg = "Router added successfully.";
                } catch (Exception $e) {
                    $error_msg = "Error adding router: " . $e->getMessage();
                }
            }
        } else {
            $error_msg = "SSID and Brand are required.";
        }
    } elseif ($_POST['action'] === 'edit_router') {
        $id = $_POST['id'] ?? 0;
        $ssid = $_POST['ssid'] ?? '';
        $ip = $_POST['ip_address'] ?? '';
        $mac = $_POST['mac_address'] ?? '';
        $lan_ip = $_POST['lan_ip'] ?? '';
        $status = $_POST['status'] ?? 'Offline';
        $admin_user = $_POST['admin_user'] ?? 'admin';
        $admin_pass = $_POST['admin_password'] ?? '';
        $wifi_pass = $_POST['wifi_password'] ?? '';
        $brand = $_POST['brand'] ?? '';

        // Merge Location Fields (Router Module)
        $building = $_POST['building'] ?? '';
        $floor = $_POST['floor'] ?? '';
        $dept_input = $_POST['department'] ?? '';
        $location = trim("$building $floor $dept_input");

        $remarks = $_POST['remarks'] ?? '';
        $sn = $_POST['serial_number'] ?? '';

        if ($id) {
            // Check Duplicates (excluding current ID)
            $dups = [];
            if (!empty($ip)) {
                $c = $pdo->prepare("SELECT ssid, brand FROM routers WHERE ip_address = ? AND id != ? LIMIT 1");
                $c->execute([$ip, $id]);
                $existing = $c->fetch(PDO::FETCH_ASSOC);
                if ($existing) {
                    $ctx = $existing['ssid'] . " (" . $existing['brand'] . ")";
                    $dups[] = "IP Address '$ip' (assigned to $ctx)";
                }
            }
            if (!empty($mac)) {
                $c = $pdo->prepare("SELECT ssid, brand FROM routers WHERE mac_address = ? AND id != ? LIMIT 1");
                $c->execute([$mac, $id]);
                $existing = $c->fetch(PDO::FETCH_ASSOC);
                if ($existing) {
                    $ctx = $existing['ssid'] . " (" . $existing['brand'] . ")";
                    $dups[] = "MAC Address '$mac' (assigned to $ctx)";
                }
            }

            if (!empty($dups)) {
                $error_msg = "Error: Duplicate found for " . implode(' and ', $dups) . ".";
            } else {
                try {
                    // Snapshot old record for field-level diff
                    $snap = $pdo->prepare("SELECT * FROM routers WHERE id = ?");
                    $snap->execute([$id]);
                    $old_r = $snap->fetch(PDO::FETCH_ASSOC) ?: [];

                    $stmt = $pdo->prepare("UPDATE routers SET serial_number=?, brand=?, location=?, ip_address=?, mac_address=?, lan_ip=?, ssid=?, wifi_password=?, admin_user=?, admin_password=?, status=?, remarks=? WHERE id=?");
                    $stmt->execute([$sn, $brand, $location, $ip, $mac, $lan_ip, $ssid, $wifi_pass, $admin_user, $admin_pass, $status, $remarks, $id]);

                    // Build field-level diff
                    $r_labels = [
                        'serial_number' => 'Serial', 'brand' => 'Brand', 'location' => 'Location',
                        'ip_address' => 'IP', 'mac_address' => 'MAC', 'lan_ip' => 'LAN IP',
                        'ssid' => 'SSID', 'status' => 'Status', 'remarks' => 'Remarks',
                    ];
                    $new_r = [
                        'serial_number' => $sn, 'brand' => $brand, 'location' => $location,
                        'ip_address' => $ip, 'mac_address' => $mac, 'lan_ip' => $lan_ip,
                        'ssid' => $ssid, 'status' => $status, 'remarks' => $remarks,
                    ];
                    $r_changes = [];
                    foreach ($r_labels as $f => $lbl) {
                        $o = trim((string)($old_r[$f] ?? '')); $n = trim((string)($new_r[$f] ?? ''));
                        if ($o !== $n) $r_changes[] = "{$lbl}: \"{$o}\" → \"{$n}\"";
                    }
                    $upd_detail = empty($r_changes)
                        ? "Updated Router (no field changes) | ID: $id | SSID: $ssid"
                        : "Updated Router | SSID: $ssid | " . implode(' | ', $r_changes);
                    logAudit($pdo, 'update_router', $upd_detail, 'router', $id);
                    /* logChangelog($pdo, 'enhancement', 'Router Inventory', "Updated Router: $ssid ($brand)", "IP: $ip | Location: $location | Status: $status"); removed */
                    $success_msg = "Router updated successfully.";
                } catch (Exception $e) {
                    $error_msg = "Error updating router: " . $e->getMessage();
                }
            }
        }
    } elseif ($_POST['action'] === 'delete_router') {
        $id = $_POST['id'] ?? 0;
        if ($id) {
            try {
                $snap = $pdo->prepare("SELECT * FROM routers WHERE id = ?");
                $snap->execute([$id]);
                $del_r = $snap->fetch(PDO::FETCH_ASSOC);
                $del_detail = "Deleted Router ID $id";
                if ($del_r) {
                    $del_detail = "Deleted Router | ID: $id | SSID: {$del_r['ssid']} | Brand: {$del_r['brand']} | IP: {$del_r['ip_address']} | MAC: {$del_r['mac_address']} | LAN IP: {$del_r['lan_ip']} | Location: {$del_r['location']} | Status: {$del_r['status']} | Serial: {$del_r['serial_number']}";
                    if ($del_r['remarks'])
                        $del_detail .= " | Remarks: {$del_r['remarks']}";
                }
                $stmt = $pdo->prepare("DELETE FROM routers WHERE id = ?");
                $stmt->execute([$id]);
                logAudit($pdo, 'delete_router', $del_detail, 'router', $id);
                /* if ($del_r) logChangelog($pdo, 'bugfix', 'Router Inventory', "Removed Router: ...", ...); removed — data changes not tracked in changelog */
                $success_msg = "Router deleted successfully.";
            } catch (Exception $e) {
                $error_msg = "Error deleting router: " . $e->getMessage();
            }
        }
    } elseif ($_POST['action'] === 'reboot_router') {
        $id = $_POST['id'] ?? 0;
        if ($id) {
            try {
                $stmt = $pdo->prepare("UPDATE routers SET uptime = '0d 00h 01m', status = 'Online', last_seen = NOW() WHERE id = ?");
                $stmt->execute([$id]);

                $getStmt = $pdo->prepare("SELECT ssid FROM routers WHERE id = ?");
                $getStmt->execute([$id]);
                $router = $getStmt->fetch();
                $name = $router ? $router['ssid'] : "Unknown";

                logAudit($pdo, 'reboot_router', "Rebooted router $name", 'router', $id);
                $success_msg = "Reboot command sent to $name.";
            } catch (Exception $e) {
                $error_msg = "Error rebooting router: " . $e->getMessage();
            }
        }
    } elseif ($_POST['action'] === 'ping_router') {
        $ip = $_POST['ip_address'] ?? '';
        if ($ip) {
            // Determine OS and set count flag
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                $cmd = "ping -n 4 " . escapeshellarg($ip);
            } else {
                $cmd = "ping -c 4 " . escapeshellarg($ip);
            }

            // Execute command
            $output = [];
            $return_var = 0;
            exec($cmd, $output, $return_var);

            // Format output
            $output_str = implode("\n", $output);

            // Check success (look for TTL= which indicates a reply)
            if (stripos($output_str, 'TTL=') !== false) {
                try {
                    $stmt = $pdo->prepare("UPDATE routers SET status = 'Online', last_seen = NOW() WHERE ip_address = ?");
                    $stmt->execute([$ip]);
                    logAudit($pdo, 'ping_update', "Router $ip set to Online via Ping", 'router', null);
                    $output_str .= "\n\n[System] Status updated to 'Online'.";
                } catch (Exception $e) {
                    $output_str .= "\n\n[System] Failed to update status: " . $e->getMessage();
                }
            } elseif (stripos($output_str, 'unreachable') !== false || stripos($output_str, 'timed out') !== false) {
                try {
                    $stmt = $pdo->prepare("UPDATE routers SET status = 'Offline' WHERE ip_address = ?");
                    $stmt->execute([$ip]);
                    logAudit($pdo, 'ping_update', "Router $ip set to Offline via Ping (Unreachable)", 'router', null);
                    $output_str .= "\n\n[System] Status updated to 'Offline'.";
                } catch (Exception $e) {
                    $output_str .= "\n\n[System] Failed to update status: " . $e->getMessage();
                }
            }

            echo json_encode(['success' => true, 'output' => $output_str]);
            exit;
        } else {
            echo json_encode(['success' => false, 'error' => 'Invalid IP address']);
            exit;
        }
    }
}

// Stats
$total_routers = $pdo->query("SELECT COUNT(*) FROM routers")->fetchColumn();
$online_routers = $pdo->query("SELECT COUNT(*) FROM routers WHERE status = 'Online'")->fetchColumn();
$warning_routers = $pdo->query("SELECT COUNT(*) FROM routers WHERE status = 'Warning'")->fetchColumn();

// Filters
$search_term = $_GET['search'] ?? '';
$filter_status = $_GET['status'] ?? 'All';
$sort_by = $_GET['sort'] ?? 'id_desc';

$where_clauses = ["1=1"];
$params = [];

if ($search_term) {
    $where_clauses[] = "(ssid ILIKE ? OR brand ILIKE ? OR ip_address::text ILIKE ? OR lan_ip::text ILIKE ? OR mac_address ILIKE ? OR location ILIKE ? OR serial_number ILIKE ? OR admin_user ILIKE ? OR admin_password ILIKE ? OR wifi_password ILIKE ? OR remarks ILIKE ?)";
    $search_param = "%$search_term%";
    for ($i = 0; $i < 11; $i++)
        $params[] = $search_param;
}

if ($filter_status !== 'All' && !empty($filter_status)) {
    $where_clauses[] = "status = ?";
    $params[] = $filter_status;
}

$where_sql = implode(' AND ', $where_clauses);

// Sorting
$order_by = "id DESC";
switch ($sort_by) {
    case 'ssid_asc':
        $order_by = "ssid ASC";
        break;
    case 'ssid_desc':
        $order_by = "ssid DESC";
        break;
    case 'brand_asc':
        $order_by = "brand ASC";
        break;
    case 'brand_desc':
        $order_by = "brand DESC";
        break;
    case 'ip_asc':
        $order_by = "split_part(COALESCE(NULLIF(ip_address,''),'0.0.0.0'), '.', 1)::int ASC, split_part(COALESCE(NULLIF(ip_address,''),'0.0.0.0'), '.', 2)::int ASC, split_part(COALESCE(NULLIF(ip_address,''),'0.0.0.0'), '.', 3)::int ASC, split_part(COALESCE(NULLIF(ip_address,''),'0.0.0.0'), '.', 4)::int ASC";
        break;
    case 'ip_desc':
        $order_by = "split_part(COALESCE(NULLIF(ip_address,''),'0.0.0.0'), '.', 1)::int DESC, split_part(COALESCE(NULLIF(ip_address,''),'0.0.0.0'), '.', 2)::int DESC, split_part(COALESCE(NULLIF(ip_address,''),'0.0.0.0'), '.', 3)::int DESC, split_part(COALESCE(NULLIF(ip_address,''),'0.0.0.0'), '.', 4)::int DESC";
        break;
    case 'lan_ip_asc':
        $order_by = "split_part(COALESCE(NULLIF(lan_ip, ''), '0.0.0.0'), '.', 1)::int ASC, split_part(COALESCE(NULLIF(lan_ip, ''), '0.0.0.0'), '.', 2)::int ASC, split_part(COALESCE(NULLIF(lan_ip, ''), '0.0.0.0'), '.', 3)::int ASC, split_part(COALESCE(NULLIF(lan_ip, ''), '0.0.0.0'), '.', 4)::int ASC";
        break;
    case 'status_asc':
        $order_by = "status ASC";
        break;
    case 'status_desc':
        $order_by = "status DESC";
        break;
}

// Pagination
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1)
    $page = 1;
$limit_param = $_GET['limit'] ?? '50';
$limit = ($limit_param === 'all') ? 999999 : (int) $limit_param;
$offset = ($page - 1) * $limit;

$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM routers WHERE $where_sql");
$count_stmt->execute($params);
$total_items = $count_stmt->fetchColumn();
$total_pages = ceil($total_items / $limit);

$sql = "SELECT t.*, last_edit.username AS last_edited_by, last_edit.created_at AS last_edited_at
    FROM routers t
    LEFT JOIN LATERAL (
        SELECT u.username, al.created_at
        FROM audit_logs al
        LEFT JOIN users u ON al.user_id = u.id
        WHERE al.resource_type = 'router' AND al.resource_id::text = t.id::text
        ORDER BY al.created_at DESC
        LIMIT 1
    ) last_edit ON true
    WHERE $where_sql ORDER BY $order_by LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$routers = $stmt->fetchAll();

// AJAX Search Handler
if (isset($_GET['ajax_search'])) {
    if (empty($routers)) {
        echo '<tr><td colspan="12" class="px-6 py-12 text-center text-slate-500">No routers found matching your criteria.</td></tr>';
    } else {
        foreach ($routers as $item) {
            $item_json = htmlspecialchars(json_encode($item), ENT_QUOTES, 'UTF-8');
            $status_dot = getStatusDotClass($item['status']);
            $status_color = getStatusColor($item['status']);

            $ssid = htmlspecialchars($item['ssid'] ?: '-');
            $ip = htmlspecialchars($item['ip_address'] ?: '-');
            $mac = htmlspecialchars($item['mac_address'] ?: '-');
            $lan_ip = htmlspecialchars($item['lan_ip'] ?: '-');
            $status = htmlspecialchars($item['status']);
            $admin_user = htmlspecialchars($item['admin_user'] ?: '-');
            $admin_pass = htmlspecialchars($item['admin_password'] ?: '-');
            $wifi_pass = htmlspecialchars($item['wifi_password'] ?: '-');
            $brand = htmlspecialchars($item['brand'] ?: '-');
            $location = htmlspecialchars($item['location'] ?: '-');
            $remarks = htmlspecialchars($item['remarks'] ?: '-');
            $remarks_full = htmlspecialchars($item['remarks'] ?? '');

            // Check admin role for reboot button
            $admin_actions = '';
            if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
                $csrf_input = getCsrfInput();
                $admin_actions = "
                <form method='POST' onsubmit=\"return confirm('Reboot router?');\" style='display:inline;'>
                    $csrf_input
                    <input type='hidden' name='action' value='reboot_router'>
                    <input type='hidden' name='ip_address' value='{$item['ip_address']}'>
                    <button class='p-1 hover:bg-amber-500/10 hover:text-amber-500 text-slate-500 rounded-lg' title='Reboot Router'>
                        <span class='material-symbols-outlined text-[18px]'>restart_alt</span>
                    </button>
                </form>
                <form method='POST' onsubmit=\"return confirm('Delete this router?');\" style='display:inline;'>
                    $csrf_input
                    <input type='hidden' name='action' value='delete_router'>
                    <input type='hidden' name='id' value='{$item['id']}'>
                    <button class='p-1 hover:bg-red-500/10 hover:text-red-500 text-slate-500 rounded-lg'>
                        <span class='material-symbols-outlined text-[18px]'>delete</span>
                    </button>
                </form>";
            }

            echo "
            <tr class='hover:bg-white/5 transition-colors text-[11px]' data-item='$item_json'>
                <td class='no-print px-2 py-1 text-center'>
                    <input type='checkbox' class='item-checkbox rounded border-slate-200 dark:border-[#232b3d] bg-slate-50 dark:bg-[#101622] text-primary focus:ring-primary h-3 w-3' value='{$item['id']}'>
                </td>
                <td class='px-2 py-1 font-medium text-slate-900 dark:text-white whitespace-nowrap cursor-pointer hover:bg-primary/10 hover:text-primary transition-colors'
                    onclick='openEditModal($item_json)' title='Click to edit'>
                    $ssid
                </td>
                <td class='px-2 py-1 font-mono text-slate-600 dark:text-slate-400 whitespace-nowrap'>$ip</td>
                <td class='px-2 py-1 text-[10px] font-mono text-slate-500 whitespace-nowrap'>$mac</td>
                <td class='px-2 py-1 font-mono text-slate-600 dark:text-slate-400 whitespace-nowrap'>$lan_ip</td>
                <td class='px-2 py-1 whitespace-nowrap'>
                    <div class='flex items-center gap-2'>
                        <div class='size-1.5 rounded-full $status_dot'></div>
                        <span class='font-medium $status_color'>$status</span>
                    </div>
                </td>
                <td class='px-2 py-1 text-slate-600 dark:text-slate-400 whitespace-nowrap'>$admin_user</td>
                <td class='px-2 py-1 text-slate-600 dark:text-slate-400 whitespace-nowrap'>$admin_pass</td>
                <td class='px-2 py-1 text-slate-600 dark:text-slate-400 whitespace-nowrap'>$wifi_pass</td>
                <td class='px-2 py-1 text-slate-600 dark:text-slate-400 whitespace-nowrap'>$brand</td>
                <td class='px-2 py-1 text-slate-600 dark:text-slate-400 whitespace-nowrap'>$location</td>
                <td class='px-2 py-1 text-slate-500 truncate max-w-[120px] whitespace-nowrap' title='$remarks_full'>$remarks</td>
                <td class='px-2 py-1 text-right flex items-center justify-end gap-1 whitespace-nowrap'>
                    <a href='https://{$item['ip_address']}:8080/' target='_blank' title='Open Configuration' class='p-1 hover:bg-emerald-500/10 hover:text-emerald-500 text-slate-500 rounded-lg'>
                        <span class='material-symbols-outlined text-[18px]'>settings</span>
                    </a>
                    <button onclick=\"pingRouter('{$item['ip_address']}')\" class='p-1 hover:bg-emerald-500/10 hover:text-emerald-500 text-slate-500 rounded-lg' title='Ping Router'>
                        <span class='material-symbols-outlined text-[18px]'>network_ping</span>
                    </button>
                    $admin_actions
                </td>
            </tr>";
        }
    }
    exit;
}

$page_title = "Router Inventory";
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
?>

<div class="p-4 md:p-8 flex-1 overflow-y-auto custom-scrollbar relative z-10 bg-slate-50 dark:bg-background-dark">
    <header
        class="-mt-4 -mx-4 md:-mt-8 md:-mx-8 px-4 md:px-8 pt-4 md:pt-8 pb-6 mb-8 flex flex-col md:flex-row items-start md:items-end justify-between gap-4 sticky top-0 bg-slate-50 dark:bg-background-dark z-20 border-b border-slate-200 dark:border-[#232b3d]">
        <div>
            <h2 class="text-2xl font-bold text-slate-900 dark:text-white">Router Inventory</h2>
            <p class="text-sm text-slate-500 mt-1">Manage network routers and configurations.</p>
        </div>
        <div class="no-print flex items-center gap-2 md:gap-3 flex-wrap">
            <a href="?export=csv"
                class="flex items-center justify-center rounded-lg h-9 px-4 bg-blue-600 text-white hover:bg-blue-700 transition-colors text-xs font-bold">
                <span class="material-symbols-outlined text-[18px] mr-2">download</span> Export CSV
            </a>
            <button onclick="toggleModal('importModal')"
                class="flex items-center justify-center rounded-lg h-9 px-4 bg-amber-600 text-white hover:bg-amber-700 transition-colors text-xs font-bold">
                <span class="material-symbols-outlined text-[18px] mr-2">upload</span> Import CSV
            </button>
            <button onclick="printData()"
                class="flex items-center justify-center rounded-lg h-9 px-4 bg-slate-600 text-white hover:bg-slate-700 transition-colors text-xs font-bold">
                <span class="material-symbols-outlined text-[18px] mr-2">print</span> Print
            </button>
            <button onclick="toggleModal('addRouterModal')"
                class="flex items-center justify-center rounded-lg h-9 px-4 bg-primary text-white hover:bg-primary/90 transition-colors text-xs font-bold shadow-lg shadow-primary/20">
                <span class="material-symbols-outlined text-[18px] mr-2">add</span> Add Router
            </button>
        </div>
    </header>

    <?php if ($success_msg): ?>
        <div
            class="mb-6 bg-emerald-500/10 border border-emerald-500/20 text-emerald-500 p-4 rounded-xl text-sm font-medium flex items-center gap-3">
            <span class="material-symbols-outlined">check_circle</span>
            <?= htmlspecialchars($success_msg) ?>
        </div>
    <?php endif; ?>
    <?php if ($error_msg): ?>
        <div
            class="mb-6 bg-red-500/10 border border-red-500/20 text-red-500 p-4 rounded-xl text-sm font-medium flex items-center gap-3">
            <span class="material-symbols-outlined">error</span>
            <?= htmlspecialchars($error_msg) ?>
        </div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
        <div
            class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] p-4 rounded-xl text-center">
            <div class="text-slate-500 text-xs font-bold uppercase tracking-wider mb-1">Total Routers</div>
            <div class="text-2xl font-bold text-slate-900 dark:text-white">
                <?= number_format($total_routers) ?>
            </div>
        </div>
        <div
            class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] p-4 rounded-xl text-center">
            <div class="text-slate-500 text-xs font-bold uppercase tracking-wider mb-1">Online</div>
            <div class="text-2xl font-bold text-emerald-500">
                <?= number_format($online_routers) ?>
            </div>
        </div>
        <div
            class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] p-4 rounded-xl text-center">
            <div class="text-slate-500 text-xs font-bold uppercase tracking-wider mb-1">Warnings</div>
            <div class="text-2xl font-bold <?= $warning_routers > 0 ? 'text-amber-500' : 'text-slate-200' ?>">
                <?= number_format($warning_routers) ?>
            </div>
        </div>
    </div>

    <!-- Control Bar -->
    <div class="no-print mb-6 flex gap-3">
        <form method="GET" class="flex items-center gap-3 flex-wrap w-full">
            <div class="relative group">
                <span
                    class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-[18px] text-slate-500">search</span>
                <input type="text" name="search" id="searchInput" value="<?= htmlspecialchars($search_term) ?>"
                    placeholder="Search SSID, Brand, IP..."
                    class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] text-slate-700 dark:text-slate-200 text-xs rounded-xl pl-9 pr-4 py-2 focus:ring-2 focus:ring-primary focus:border-transparent w-64">
            </div>
            <input type="hidden" name="status" id="hid_status" value="<?= htmlspecialchars($filter_status) ?>">
            <?php $r_active = ($filter_status!=='All'&&$filter_status!=='') ? $filter_status : ''; ?>
            <select id="r_cat" onchange="rOnCat(this.value)"
                class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] text-slate-600 dark:text-slate-300 text-xs rounded-xl px-4 py-2">
                <option value="">Filter by...</option>
                <option value="status" <?= $r_active?'selected':'' ?>>Status</option>
            </select>
            <select id="r_val" onchange="rApply(this.value)"
                class="<?= $r_active?'':'hidden' ?> bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] text-slate-600 dark:text-slate-300 text-xs rounded-xl px-4 py-2">
            </select>
            <?php if ($r_active): ?>
            <div class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-primary/10 border border-primary/30 text-primary text-xs font-semibold rounded-xl">
                <span class="material-symbols-outlined text-[13px]">filter_alt</span>
                <?= htmlspecialchars($r_active) ?>
                <a href="?search=<?= urlencode($search_term) ?>&sort=<?= urlencode($sort_by) ?>&limit=<?= urlencode($limit_param) ?>" class="ml-1 text-primary/60 hover:text-red-500" title="Clear">
                    <span class="material-symbols-outlined text-[14px]">close</span>
                </a>
            </div>
            <?php endif; ?>
            <script>
            const _rA=<?= json_encode($r_active) ?>;
            function rOnCat(c){
                const v=document.getElementById('r_val');
                if(!c){v.classList.add('hidden');return;}
                v.innerHTML='<option value="All">All</option>';
                ['Online','Offline','Warning','Maintenance'].forEach(function(o){
                    const e=document.createElement('option');e.value=o;e.textContent=o;
                    if(o===_rA)e.selected=true;v.appendChild(e);
                });
                v.classList.remove('hidden');
            }
            function rApply(val){
                document.getElementById('hid_status').value=val;
                document.getElementById('r_cat').closest('form').submit();
            }
            document.addEventListener('DOMContentLoaded',function(){if(_rA)rOnCat('status');});
            </script>
            <select name="sort" onchange="this.form.submit()"
                class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] text-slate-400 text-xs rounded-xl px-4 py-2">
                <option value="id_desc" <?= $sort_by === 'id_desc' ? 'selected' : '' ?>>Newest First</option>
                <option value="ssid_asc" <?= $sort_by === 'ssid_asc' ? 'selected' : '' ?>>SSID (A-Z)</option>
                <option value="brand_asc" <?= $sort_by === 'brand_asc' ? 'selected' : '' ?>>Brand (A-Z)</option>
                <option value="ip_asc" <?= $sort_by === 'ip_asc' ? 'selected' : '' ?>>IP Address</option>
                <option value="status_asc" <?= $sort_by === 'status_asc' ? 'selected' : '' ?>>Status</option>
            </select>

            <!-- Top Pagination -->
            <div class="flex items-center gap-2 ml-auto">
                <?php if ($total_pages > 1): ?>
                    <span class="text-xs text-slate-500 mr-2">Page
                        <?= $page ?> of
                        <?= $total_pages ?>
                    </span>
                    <div
                        class="flex h-9 bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] rounded-xl overflow-hidden mr-2">
                        <a href="?page=<?= max(1, $page - 1) ?>&search=<?= urlencode($search_term) ?>&status=<?= urlencode($filter_status) ?>&limit=<?= $limit ?>"
                            class="px-3 flex items-center justify-center hover:bg-white/5 text-slate-400 hover:text-slate-900 dark:hover:text-white transition-colors border-r border-slate-200 dark:border-[#232b3d] <?= $page <= 1 ? 'pointer-events-none opacity-50' : '' ?>">
                            <span class="material-symbols-outlined text-[18px]">chevron_left</span>
                        </a>
                        <a href="?page=<?= min($total_pages, $page + 1) ?>&search=<?= urlencode($search_term) ?>&status=<?= urlencode($filter_status) ?>&limit=<?= $limit ?>"
                            class="px-3 flex items-center justify-center hover:bg-white/5 text-slate-400 hover:text-slate-900 dark:hover:text-white transition-colors <?= $page >= $total_pages ? 'pointer-events-none opacity-50' : '' ?>">
                            <span class="material-symbols-outlined text-[18px]">chevron_right</span>
                        </a>
                    </div>
                <?php endif; ?>
                <select name="limit" onchange="this.form.submit()"
                    class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] text-slate-400 text-xs rounded-xl px-4 py-2">
                    <option value="50" <?= $limit_param == '50' ? 'selected' : '' ?>>Show: 50</option>
                    <option value="100" <?= $limit_param == '100' ? 'selected' : '' ?>>Show: 100</option>
                    <option value="200" <?= $limit_param == '200' ? 'selected' : '' ?>>Show: 200</option>
                    <option value="500" <?= $limit_param == '500' ? 'selected' : '' ?>>Show: 500</option>
                    <option value="all" <?= $limit_param == 'all' ? 'selected' : '' ?>>Show: All</option>
                </select>
            </div>
        </form>
    </div>

    <!-- Table -->
    <div
        class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] rounded-2xl overflow-hidden shadow-sm">
        <div class="overflow-x-auto custom-scrollbar">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-white dark:bg-[#1a2130] border-b border-slate-200 dark:border-[#232b3d]">
                        <th
                            class="sticky-col-1 no-print px-2 py-1 w-8 text-center text-slate-500 bg-white dark:bg-[#1a2130] z-20">
                            <input type="checkbox" onclick="toggleAll(this)"
                                class="rounded border-slate-200 dark:border-[#232b3d] bg-slate-50 dark:bg-[#101622] text-primary focus:ring-primary h-3 w-3">
                        </th>
                        <!-- Sequence: SSID IP Address MAC Address LAN IP Status Admin User Admin Password Wifi Password Brand Location Remarks -->
                        <th class="px-2 py-1 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">SSID
                        </th>
                        <th class="px-2 py-1 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">IP
                            Address</th>
                        <th class="px-2 py-1 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">MAC
                            Address</th>
                        <th class="px-2 py-1 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">LAN
                            IP
                        </th>
                        <th class="px-2 py-1 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">
                            Status
                        </th>
                        <th class="px-2 py-1 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">Admin
                            User</th>
                        <th class="px-2 py-1 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">Admin
                            Password</th>
                        <th class="px-2 py-1 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">Wifi
                            Password</th>
                        <th class="px-2 py-1 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">Brand
                        </th>
                        <th class="px-2 py-1 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">
                            Location
                        </th>
                        <th class="px-2 py-1 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">
                            Remarks
                        </th>
                        <th class="no-print px-2 py-1 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">Last Edited By</th>
                        <th
                            class="px-2 py-1 text-[10px] font-bold text-slate-500 uppercase text-right whitespace-nowrap">
                            Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#232b3d]/50">
                    <?php if (empty($routers)): ?>
                        <tr>
                            <td colspan="13" class="px-6 py-12 text-center text-slate-500">No routers found matching your
                                criteria.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($routers as $item): ?>
                            <tr class="hover:bg-white/5 transition-colors text-[11px]" data-item='<?= htmlspecialchars(json_encode($item), ENT_QUOTES, "UTF-8") ?>'>
                                <td class="no-print px-2 py-1 text-center">
                                    <input type="checkbox"
                                        class="item-checkbox rounded border-slate-200 dark:border-[#232b3d] bg-slate-50 dark:bg-[#101622] text-primary focus:ring-primary h-3 w-3"
                                        value="<?= $item['id'] ?>">
                                </td>
                                <td class="px-2 py-1 font-medium text-slate-900 dark:text-white whitespace-nowrap cursor-pointer hover:bg-primary/10 hover:text-primary transition-colors"
                                    onclick='openEditModal(<?= htmlspecialchars(json_encode($item), ENT_QUOTES, "UTF-8") ?>)'
                                    title="Click to edit">
                                    <?= htmlspecialchars($item['ssid'] ?: '-') ?>
                                </td>
                                <td class="px-2 py-1 font-mono text-slate-600 dark:text-slate-400 whitespace-nowrap">
                                    <?= htmlspecialchars($item['ip_address'] ?: '-') ?>
                                </td>
                                <td class="px-2 py-1 text-[10px] font-mono text-slate-500 whitespace-nowrap">
                                    <?= htmlspecialchars($item['mac_address'] ?: '-') ?>
                                </td>
                                <td class="px-2 py-1 font-mono text-slate-600 dark:text-slate-400 whitespace-nowrap">
                                    <?= htmlspecialchars($item['lan_ip'] ?: '-') ?>
                                </td>
                                <td class="px-2 py-1 whitespace-nowrap">
                                    <div class="flex items-center gap-2">
                                        <div class="size-1.5 rounded-full <?= getStatusDotClass($item['status']) ?>"></div>
                                        <span
                                            class="font-medium <?= getStatusColor($item['status']) ?>"><?= htmlspecialchars($item['status']) ?></span>
                                    </div>
                                </td>
                                <td class="px-2 py-1 text-slate-600 dark:text-slate-400 whitespace-nowrap">
                                    <?= htmlspecialchars($item['admin_user'] ?: '-') ?>
                                </td>
                                <td class="px-2 py-1 text-slate-600 dark:text-slate-400 whitespace-nowrap">
                                    <?= htmlspecialchars($item['admin_password'] ?: '-') ?>
                                </td>
                                <td class="px-2 py-1 text-slate-600 dark:text-slate-400 whitespace-nowrap">
                                    <?= htmlspecialchars($item['wifi_password'] ?: '-') ?>
                                </td>
                                <td class="px-2 py-1 text-slate-600 dark:text-slate-400 whitespace-nowrap">
                                    <?= htmlspecialchars($item['brand'] ?: '-') ?>
                                </td>
                                <td class="px-2 py-1 text-slate-600 dark:text-slate-400 whitespace-nowrap">
                                    <?= htmlspecialchars($item['location'] ?: '-') ?>
                                </td>
                                <td class="px-2 py-1 text-slate-500 truncate max-w-[120px] whitespace-nowrap"
                                    title="<?= htmlspecialchars($item['remarks'] ?? '') ?>">
                                    <?= htmlspecialchars($item['remarks'] ?: '-') ?>
                                </td>
                                <td class="no-print px-2 py-1 whitespace-nowrap">
                                    <?php if (!empty($item['last_edited_by'])): ?>
                                        <div class="text-[10px] font-semibold text-slate-700 dark:text-slate-300"><?= htmlspecialchars($item['last_edited_by']) ?></div>
                                        <div class="text-[9px] text-slate-400"><?= date('M d, Y H:i', strtotime($item['last_edited_at'])) ?></div>
                                    <?php else: ?>
                                        <span class="text-slate-400 text-[10px]">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-2 py-1 text-right flex items-center justify-end gap-1 whitespace-nowrap">
                                    <a href="https://<?= htmlspecialchars($item['ip_address']) ?>:8080/" target="_blank"
                                        title="Open Configuration"
                                        class="p-1 hover:bg-emerald-500/10 hover:text-emerald-500 text-slate-500 rounded-lg">
                                        <span class="material-symbols-outlined text-[18px]">settings</span>
                                    </a>
                                    <button onclick="pingRouter('<?= htmlspecialchars($item['ip_address']) ?>')"
                                        class="p-1 hover:bg-emerald-500/10 hover:text-emerald-500 text-slate-500 rounded-lg"
                                        title="Ping Router">
                                        <span class="material-symbols-outlined text-[18px]">network_ping</span>
                                    </button>
                                    <?php if ($_SESSION['role'] === 'admin'): ?>
                                        <form method="POST" onsubmit="return confirm('Reboot router?');">
                                            <?= getCsrfInput() ?>
                                            <input type="hidden" name="action" value="reboot_router">
                                            <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                            <button title="Reboot"
                                                class="p-1 hover:bg-amber-500/10 hover:text-amber-500 text-slate-500 rounded-lg">
                                                <span class="material-symbols-outlined text-[18px]">restart_alt</span>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <button onclick='openEditModal(<?= json_encode($item) ?>)'
                                        class="p-1 hover:bg-primary/10 hover:text-primary text-slate-500 rounded-lg">
                                        <span class="material-symbols-outlined text-[18px]">edit</span>
                                    </button>
                                    <form method="POST" onsubmit="return confirm('Delete router?');">
                                        <?= getCsrfInput() ?>
                                        <input type="hidden" name="action" value="delete_router">
                                        <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                        <button class="p-1 hover:bg-red-500/10 hover:text-red-500 text-slate-500 rounded-lg">
                                            <span class="material-symbols-outlined text-[18px]">delete</span>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Bottom Pagination -->
        <?php if ($total_pages > 1): ?>
            <div
                class="px-6 py-4 border-t border-slate-200 dark:border-[#232b3d] flex items-center justify-between bg-white dark:bg-[#1a2130]">
                <div class="text-xs text-slate-500">
                    Showing <span class="font-bold text-slate-900 dark:text-white"><?= $offset + 1 ?></span> to <span
                        class="font-bold text-slate-900 dark:text-white"><?= min($offset + $limit, $total_items) ?></span>
                    of <span class="font-bold text-slate-900 dark:text-white"><?= $total_items ?></span> results
                </div>
                <div class="flex items-center gap-2">
                    <a href="?page=1&search=<?= urlencode($search_term) ?>&status=<?= urlencode($filter_status) ?>&limit=<?= $limit ?>"
                        class="p-2 hover:bg-white/5 text-slate-400 hover:text-slate-900 dark:hover:text-white rounded-lg transition-colors <?= $page <= 1 ? 'pointer-events-none opacity-50' : '' ?>"
                        title="First Page">
                        <span class="material-symbols-outlined text-[18px]">first_page</span>
                    </a>
                    <a href="?page=<?= max(1, $page - 1) ?>&search=<?= urlencode($search_term) ?>&status=<?= urlencode($filter_status) ?>&limit=<?= $limit ?>"
                        class="p-2 hover:bg-white/5 text-slate-400 hover:text-slate-900 dark:hover:text-white rounded-lg transition-colors <?= $page <= 1 ? 'pointer-events-none opacity-50' : '' ?>">
                        <span class="material-symbols-outlined text-[18px]">chevron_left</span>
                    </a>
                    <div class="flex items-center gap-1">
                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <a href="?page=<?= $i ?>&search=<?= urlencode($search_term) ?>&status=<?= urlencode($filter_status) ?>&limit=<?= $limit ?>"
                                class="w-8 h-8 flex items-center justify-center rounded-lg text-xs font-bold transition-colors <?= $i === $page ? 'bg-primary text-white' : 'text-slate-400 hover:bg-white/5 hover:text-slate-900 dark:text-white' ?>">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>
                    </div>
                    <a href="?page=<?= min($total_pages, $page + 1) ?>&search=<?= urlencode($search_term) ?>&status=<?= urlencode($filter_status) ?>&limit=<?= $limit ?>"
                        class="p-2 hover:bg-white/5 text-slate-400 hover:text-slate-900 dark:hover:text-white rounded-lg transition-colors <?= $page >= $total_pages ? 'pointer-events-none opacity-50' : '' ?>">
                        <span class="material-symbols-outlined text-[18px]">chevron_right</span>
                    </a>
                    <a href="?page=<?= $total_pages ?>&search=<?= urlencode($search_term) ?>&status=<?= urlencode($filter_status) ?>&limit=<?= $limit ?>"
                        class="p-2 hover:bg-white/5 text-slate-400 hover:text-slate-900 dark:hover:text-white rounded-lg transition-colors <?= $page >= $total_pages ? 'pointer-events-none opacity-50' : '' ?>"
                        title="Last Page">
                        <span class="material-symbols-outlined text-[18px]">last_page</span>
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal: Import -->
<div id="importModal" class="hidden fixed inset-0 z-50 overflow-y-auto" role="dialog">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-background-dark/80 backdrop-blur-sm" onclick="toggleModal('importModal')"></div>
        <div
            class="relative bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] rounded-2xl max-w-lg w-full p-4 sm:p-6 shadow-2xl">
            <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-4">Import Routers from CSV</h3>
            <p class="text-sm text-slate-400 mb-4">Upload a CSV file with the following columns: SSID, IP Address, MAC
                Address, LAN IP, Status, Admin User, Admin Password, Wifi Password, Brand, Location, Remarks</p>
            <form method="POST" enctype="multipart/form-data">
                <?= getCsrfInput() ?>
                <input type="hidden" name="action" value="import_csv">
                <div class="mb-4">
                    <input type="file" name="csv_file" accept=".csv" required
                        class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-white rounded-xl px-4 py-2.5 text-sm file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-primary file:text-slate-900 dark:text-white hover:file:bg-primary/90">
                </div>
                <div class="flex justify-end gap-3">
                    <button type="button" onclick="toggleModal('importModal')"
                        class="px-4 py-2 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white">Cancel</button>
                    <button type="submit" class="px-6 py-2 bg-primary text-white font-bold rounded-xl">Import</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Add Router -->
<div id="addRouterModal" class="hidden fixed inset-0 z-50 overflow-y-auto" role="dialog">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-background-dark/80 backdrop-blur-sm" onclick="toggleModal('addRouterModal')"></div>
        <div
            class="relative bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] rounded-2xl max-w-2xl w-full p-4 sm:p-6 shadow-2xl max-h-[90vh] overflow-y-auto custom-scrollbar">
            <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-6">Add New Router</h3>
            <form method="POST">
                <?= getCsrfInput() ?>
                <input type="hidden" name="action" value="add_router">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <input type="text" name="ssid" placeholder="SSID" required
                        class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                    <input type="text" name="ip_address" placeholder="IP Address"
                        class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                    <input type="text" name="mac_address" placeholder="MAC Address"
                        class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                    <input type="text" name="lan_ip" placeholder="LAN IP"
                        class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">

                    <select name="status"
                        class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                        <option value="Online">Online</option>
                        <option value="Offline">Offline</option>
                        <option value="Warning">Warning</option>
                        <option value="Maintenance">Maintenance</option>
                    </select>

                    <div class="flex gap-2">
                        <input type="text" name="admin_user" placeholder="Admin User" value="admin"
                            class="flex-1 bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                        <input type="text" name="admin_password" placeholder="Admin Pass"
                            class="flex-1 bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                    </div>

                    <input type="text" name="wifi_password" placeholder="Wifi Password"
                        class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                    <input type="text" name="brand" placeholder="Brand" required
                        class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">

                    <!-- Building Dropdown -->
                    <select name="building"
                        class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                        <option value="">Select Building</option>
                        <option value="ACIS">ACIS</option>
                        <option value="Bayanihan">Bayanihan</option>
                        <option value="Bio Safety">Bio Safety</option>
                        <option value="Capiz">Capiz</option>
                        <option value="Dietary">Dietary</option>
                        <option value="Frontline">Frontline</option>
                        <option value="HOPSS">HOPSS</option>
                        <option value="Isolation">Isolation</option>
                        <option value="Lingap Baga">Lingap Baga</option>
                        <option value="Medicine">Medicine</option>
                        <option value="OB-Gyne/Pedia">OB-Gyne/Pedia</option>
                        <option value="OPD">OPD</option>
                        <option value="Orthopaedics">Orthopaedics</option>
                        <option value="Surgery">Surgery</option>
                        <option value="Trauma">Trauma</option>
                        <option value="Wellness">Wellness</option>
                    </select>
                    <!-- Floor Dropdown -->
                    <select name="floor"
                        class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                        <option value="">Select Floor</option>
                        <option value="GF">GF</option>
                        <option value="2F">2F</option>
                        <option value="3F">3F</option>
                        <option value="4F">4F</option>
                        <option value="5F">5F</option>
                        <option value="6F">6F</option>
                        <option value="7F">7F</option>
                    </select>
                    <input type="text" name="department" placeholder="Department"
                        class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5 col-span-2">
                    <input type="text" name="serial_number" placeholder="Serial Number (Optional)"
                        class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5 col-span-2">

                    <textarea name="remarks" placeholder="Remarks" rows="2"
                        class="col-span-2 w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5 resize-none h-16"></textarea>
                </div>
                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" onclick="toggleModal('addRouterModal')"
                        class="px-4 py-2 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white">Cancel</button>
                    <button type="submit" class="px-6 py-2 bg-primary text-white font-bold rounded-xl">Save
                        Router</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Edit Router -->
<div id="editRouterModal" class="hidden fixed inset-0 z-50 overflow-y-auto" role="dialog">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-background-dark/80 backdrop-blur-sm" onclick="toggleModal('editRouterModal')">
        </div>
        <div
            class="relative bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] rounded-2xl max-w-2xl w-full p-4 sm:p-6 shadow-2xl max-h-[90vh] overflow-y-auto custom-scrollbar">
            <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-6">Edit Router Details</h3>
            <form method="POST">
                <?= getCsrfInput() ?>
                <input type="hidden" name="action" value="edit_router">
                <input type="hidden" name="id" id="edit_id">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">SSID</label>
                        <input type="text" name="ssid" id="edit_ssid" required
                            class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">IP Address</label>
                        <input type="text" name="ip_address" id="edit_ip"
                            class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">MAC Address</label>
                        <input type="text" name="mac_address" id="edit_mac"
                            class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">LAN IP</label>
                        <input type="text" name="lan_ip" id="edit_lan_ip"
                            class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Status</label>
                        <select name="status" id="edit_status"
                            class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                            <option value="Online">Online</option>
                            <option value="Offline">Offline</option>
                            <option value="Warning">Warning</option>
                            <option value="Maintenance">Maintenance</option>
                        </select>
                    </div>
                    <div class="flex gap-2">
                        <div class="flex-1">
                            <label class="block text-xs font-medium text-slate-400 mb-1">Admin User</label>
                            <input type="text" name="admin_user" id="edit_admin_user"
                                class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                        </div>
                        <div class="flex-1">
                            <label class="block text-xs font-medium text-slate-400 mb-1">Admin Pass</label>
                            <input type="text" name="admin_password" id="edit_admin_pass"
                                class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Wifi Password</label>
                        <input type="text" name="wifi_password" id="edit_wifi_pass"
                            class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Brand</label>
                        <input type="text" name="brand" id="edit_brand" required
                            class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                    </div>
                    <div class="col-span-2 grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1">Building</label>
                            <select name="building" id="edit_building"
                                class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                                <option value="">Select Building</option>
                                <option value="ACIS">ACIS</option>
                                <option value="Bayanihan">Bayanihan</option>
                                <option value="Bio Safety">Bio Safety</option>
                                <option value="Capiz">Capiz</option>
                                <option value="Dietary">Dietary</option>
                                <option value="Frontline">Frontline</option>
                                <option value="HOPSS">HOPSS</option>
                                <option value="Isolation">Isolation</option>
                                <option value="Lingap Baga">Lingap Baga</option>
                                <option value="Medicine">Medicine</option>
                                <option value="OB-Gyne/Pedia">OB-Gyne/Pedia</option>
                                <option value="OPD">OPD</option>
                                <option value="Orthopaedics">Orthopaedics</option>
                                <option value="Surgery">Surgery</option>
                                <option value="Trauma">Trauma</option>
                                <option value="Wellness">Wellness</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1">Floor</label>
                            <select name="floor" id="edit_floor"
                                class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                                <option value="">Select Floor</option>
                                <option value="GF">GF</option>
                                <option value="2F">2F</option>
                                <option value="3F">3F</option>
                                <option value="4F">4F</option>
                                <option value="5F">5F</option>
                                <option value="6F">6F</option>
                                <option value="7F">7F</option>
                            </select>
                        </div>
                        <div class="col-span-2">
                            <label class="block text-xs font-medium text-slate-400 mb-1">Department</label>
                            <input type="text" name="department" id="edit_department"
                                class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                        </div>
                    </div>
                    <div class="col-span-2">
                        <label class="block text-xs font-medium text-slate-400 mb-1">Serial Number</label>
                        <input type="text" name="serial_number" id="edit_sn"
                            class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                    </div>
                    <div class="col-span-2">
                        <label class="block text-xs font-medium text-slate-400 mb-1">Remarks</label>
                        <textarea name="remarks" id="edit_remarks" rows="2"
                            class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5 resize-none h-16"></textarea>
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" onclick="toggleModal('editRouterModal')"
                        class="px-4 py-2 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white">Cancel</button>
                    <button type="submit" class="px-6 py-2 bg-primary text-white font-bold rounded-xl">Update
                        Router</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Ping Modal -->
<div id="pingModal" class="hidden fixed inset-0 z-50 overflow-y-auto" role="dialog">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-background-dark/80 backdrop-blur-sm" onclick="closePingModal()"></div>
        <div
            class="relative bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] rounded-2xl max-w-2xl w-full p-4 sm:p-6 shadow-2xl">
            <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-4 flex items-center gap-2">
                <span class="material-symbols-outlined text-emerald-500">network_ping</span>
                Ping Result
            </h3>
            <div id="pingOutput"
                class="bg-black/50 rounded-xl p-4 font-mono text-xs text-emerald-500 h-64 overflow-y-auto whitespace-pre-wrap">
            </div>
            <div class="flex justify-end gap-3 mt-6">
                <button type="button" onclick="closePingModal()"
                    class="px-4 py-2 text-sm font-medium text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:text-white transition-colors">Close</button>
            </div>
        </div>
    </div>
</div>

<script>


    function openEditModal(item) {
        document.getElementById('edit_id').value = item.id;
        document.getElementById('edit_ssid').value = item.ssid || '';
        document.getElementById('edit_ip').value = item.ip_address || '';
        document.getElementById('edit_mac').value = item.mac_address || '';
        document.getElementById('edit_lan_ip').value = item.lan_ip || '';
        document.getElementById('edit_status').value = item.status;
        document.getElementById('edit_admin_user').value = item.admin_user || '';
        document.getElementById('edit_admin_pass').value = item.admin_password || '';
        document.getElementById('edit_wifi_pass').value = item.wifi_password || '';
        document.getElementById('edit_brand').value = item.brand || '';
        document.getElementById('edit_brand').value = item.brand || '';

        // Parse Location to fill Building, Floor, Department
        const locationStr = item.location || '';
        let building = '';
        let floor = '';
        let dept = locationStr;

        const buildings = ['ACIS', 'Bayanihan', 'Bio Safety', 'Capiz', 'Dietary', 'Frontline', 'HOPSS', 'Isolation', 'Lingap Baga', 'Medicine', 'OB-Gyne/Pedia', 'OPD', 'Orthopaedics', 'Surgery', 'Trauma', 'Wellness'];
        const floors = ['GF', '2F', '3F', '4F', '5F', '6F', '7F'];

        for (const b of buildings) {
            if (dept.startsWith(b)) {
                building = b;
                dept = dept.substring(b.length).trim();
                break;
            }
        }
        for (const f of floors) {
            if (dept.startsWith(f)) {
                floor = f;
                dept = dept.substring(f.length).trim();
                break;
            }
        }

        document.getElementById('edit_building').value = building;
        document.getElementById('edit_floor').value = floor;
        document.getElementById('edit_department').value = dept;

        document.getElementById('edit_sn').value = item.serial_number || '';
        document.getElementById('edit_sn').value = item.serial_number || '';
        document.getElementById('edit_remarks').value = item.remarks || '';
        toggleModal('editRouterModal');
    }

    function pingRouter(ip) {
        const modal = document.getElementById('pingModal');
        const outputDiv = document.getElementById('pingOutput');

        modal.classList.remove('hidden');
        outputDiv.innerHTML = "Pinging " + ip + "...\n";

        const formData = new FormData();
        formData.append('action', 'ping_router');
        formData.append('ip_address', ip);
        formData.append('csrf_token', '<?= generateCsrfToken() ?>');

        fetch('', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    outputDiv.textContent = data.output;
                } else {
                    outputDiv.textContent = "Error: " + (data.error || 'Unknown error');
                }
            })
            .catch(error => {
                outputDiv.textContent = "Request failed: " + error;
            });
    }

    function closePingModal() {
        document.getElementById('pingModal').classList.add('hidden');
        document.getElementById('pingOutput').textContent = '';
        location.reload(); // Refresh to show updated status
    }

    // Live Search Sync with AbortController
    let searchTimeout;
    let searchController = null;
    const searchInput = document.getElementById('searchInput');

    if (searchInput) {
        searchInput.addEventListener('input', function () {
            clearTimeout(searchTimeout);
            const form = searchInput.form;

            searchTimeout = setTimeout(() => {
                // Abort previous request if it's still running
                if (searchController) {
                    searchController.abort();
                }
                searchController = new AbortController();

                const params = new URLSearchParams(new FormData(form));
                params.set('ajax_search', '1');

                fetch(window.location.pathname + '?' + params.toString(), {
                    signal: searchController.signal
                })
                    .then(response => {
                        if (!response.ok) throw new Error('Network response was not ok');
                        return response.text();
                    })
                    .then(html => {
                        if (html.includes('<!DOCTYPE html>')) {
                            console.warn('Received full page instead of partial.');
                            return;
                        }

                        document.querySelector('tbody').innerHTML = html;

                        const urlParams = new URLSearchParams(new FormData(form));
                        window.history.replaceState({}, '', '?' + urlParams.toString());
                    })
                    .catch(err => {
                        if (err.name === 'AbortError') {
                            // console.log('Fetch aborted');
                        } else {
                            console.error('Search error:', err);
                        }
                    });
            }, 300);
        });
    }
    function toggleAll(source) {
        const checkboxes = document.querySelectorAll('.item-checkbox');
        for (let i = 0; i < checkboxes.length; i++) {
            checkboxes[i].checked = source.checked;
        }
    }

    function printData() {
        const checkboxes = document.querySelectorAll('.item-checkbox:checked');
        const rows = document.querySelectorAll('tbody tr');

        if (checkboxes.length > 0 && checkboxes.length < rows.length) {
            document.body.classList.add('print-filtered');
            rows.forEach(tr => {
                const cb = tr.querySelector('.item-checkbox');
                if (cb && cb.checked) {
                    tr.classList.add('print-row-selected');
                } else {
                    tr.classList.remove('print-row-selected');
                }
            });
        } else {
            document.body.classList.remove('print-filtered');
            rows.forEach(tr => tr.classList.remove('print-row-selected'));
        }

        setTimeout(() => {
            window.print();
        }, 500);
    }
</script>

<!-- Print Styles -->
<style>
    @media print {
        .no-print {
            display: none !important;
        }

        /* Hide sidebar */
        aside,
        .sidebar {
            display: none !important;
        }

        /* Reset overflow for containers to prevent cutoff */
        .overflow-x-auto,
        .overflow-hidden,
        .custom-scrollbar,
        .rounded-2xl {
            overflow: visible !important;
            height: auto !important;
            border-radius: 0 !important;
            box-shadow: none !important;
        }

        /* Hide header buttons */
        header .flex.items-center.gap-3 {
            display: none !important;
        }

        /* Hide stats cards */
        .grid.grid-cols-1.md\:grid-cols-3.gap-4.mb-8 {
            display: none !important;
        }

        /* Hide control bar (search, filters, pagination) */
        .mb-6.flex.gap-3 {
            display: none !important;
        }

        /* Hide action buttons in table */
        td:last-child,
        th:last-child {
            display: none !important;
        }

        /* Hide bottom pagination */
        .px-6.py-4.border-t {
            display: none !important;
        }

        /* Show only main content */
        body {
            background: white !important;
        }

        .p-8 {
            padding: 20px !important;
        }

        /* Keep title visible */
        header h2,
        header p {
            color: black !important;
        }

        /* Table styling for print */
        table {
            width: 100% !important;
            border-collapse: collapse !important;
        }

        th,
        td {
            border: 1px solid #ddd !important;
            padding: 8px !important;
            color: black !important;
            background: white !important;
        }

        th {
            background-color: #f2f2f2 !important;
            font-weight: bold !important;
        }

        /* Remove hover effects */
        tr:hover {
            background: transparent !important;
        }
    }
</style>



<?php require_once '../../includes/footer.php'; ?>