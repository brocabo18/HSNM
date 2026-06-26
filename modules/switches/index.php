<?php
require_once '../../config.php';
requireLogin();

// Handle CSV Export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $stmt = $pdo->query("SELECT * FROM switches ORDER BY id ASC");
    $switches = $stmt->fetchAll();

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="switches_export_' . date('Y-m-d_His') . '.csv"');

    $output = fopen('php://output', 'w');
    // Align with DB: switch_id, model, manufacturer, serial, ip_address, mac_address, building_location, floor, ports, port_details, ports_status, status, personnel, last_maintenance, next_maintenance, remarks
    fputcsv($output, ['Switch ID', 'Model', 'Manufacturer', 'Serial', 'IP Address', 'MAC Address', 'Building Location', 'Floor', 'Ports', 'Port Details', 'Port Status', 'Status', 'Personnel', 'Last Maintenance', 'Next Maintenance', 'Remarks']);

    foreach ($switches as $switch) {
        fputcsv($output, [
            escapeCsvField($switch['switch_id']),
            escapeCsvField($switch['model']),
            escapeCsvField($switch['manufacturer']),
            escapeCsvField($switch['serial']),
            escapeCsvField($switch['ip_address']),
            escapeCsvField($switch['mac_address']),
            escapeCsvField($switch['building_location']),
            escapeCsvField($switch['floor']),
            escapeCsvField($switch['ports']),
            escapeCsvField($switch['port_details']),
            escapeCsvField($switch['ports_status']),
            escapeCsvField($switch['status']),
            escapeCsvField($switch['personnel']),
            escapeCsvField($switch['last_maintenance']),
            escapeCsvField($switch['next_maintenance']),
            escapeCsvField($switch['remarks'])
        ]);
    }

    fclose($output);
    exit;
}

// Handle Actions
// Handle Actions
$success_msg = '';
$error_msg = '';

function generateNextSwitchId($pdo)
{
    // Find the latest SW-XXXXX ID
    $stmt = $pdo->query("SELECT switch_id FROM switches WHERE switch_id LIKE 'SW-%' ORDER BY LENGTH(switch_id) DESC, switch_id DESC LIMIT 1");
    $last_id = $stmt->fetchColumn();

    if ($last_id) {
        // Extract number
        $num = (int) substr($last_id, 3);
        $next_num = $num + 1;
    } else {
        $next_num = 1;
    }

    return 'SW-' . str_pad($next_num, 5, '0', STR_PAD_LEFT);
}

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

                    // switch_id, model, manufacturer, serial, ip_address, mac_address
                    $sid = $row[0] ?? '';
                    if (empty($sid)) {
                        $sid = generateNextSwitchId($pdo);
                    }
                    $sn = trim($row[3] ?? '');
                    if (in_array(strtolower($sn), ['', 'n/a', 'na', 'none'])) {
                        $sn = null;
                    }
                    $ip = $row[4] ?? '';
                    $mac = $row[5] ?? '';

                    // Duplicate Check
                    $check_clauses = [];
                    $check_params = [];

                    if (!empty($sid)) {
                        $check_clauses[] = "switch_id = ?";
                        $check_params[] = $sid;
                    }
                    if (!empty($sn)) {
                        $check_clauses[] = "serial = ?";
                        $check_params[] = $sn;
                    }
                    if (!empty($ip)) {
                        $check_clauses[] = "ip_address = ?";
                        $check_params[] = $ip;
                    }
                    if (!empty($mac)) {
                        $check_clauses[] = "mac_address = ?";
                        $check_params[] = $mac;
                    }

                    if (!empty($check_clauses)) {
                        $check_sql = "SELECT COUNT(*) FROM switches WHERE " . implode(" OR ", $check_clauses);
                        $chk = $pdo->prepare($check_sql);
                        $chk->execute($check_params);
                        if ($chk->fetchColumn() > 0) {
                            $duplicates++;
                            continue;
                        }
                    }

                    try {
                        // switch_id, model, manufacturer, serial, ip_address, mac_address, building_location, floor, ports, port_details, ports_status, status, personnel, last_maintenance, next_maintenance, remarks
                        $stmt = $pdo->prepare("INSERT INTO switches (switch_id, model, manufacturer, serial, ip_address, mac_address, building_location, floor, ports, port_details, ports_status, status, personnel, last_maintenance, next_maintenance, remarks) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([
                            $sid, // switch_id
                            $row[1] ?? '', // model
                            $row[2] ?? '', // manufacturer
                            $sn, // serial
                            $ip, // ip_address
                            $mac, // mac_address
                            $row[6] ?? '', // building_location
                            $row[7] ?? '', // floor
                            $row[8] ?? '', // ports
                            $row[9] ?? '', // port_details
                            $row[10] ?? '', // ports_status
                            $row[11] ?? 'Active', // status
                            $row[12] ?? '', // personnel
                            !empty($row[13]) ? $row[13] : null, // last_maintenance
                            $row[14] ?? '', // next_maintenance
                            $row[15] ?? ''  // remarks
                        ]);
                        $imported++;
                    } catch (Exception $e) {
                        $errors[] = "Row " . ($imported + $duplicates + 1) . ": " . $e->getMessage();
                    }
                }

                fclose($file);

                $msg_parts = ["Imported $imported switches"];
                if ($duplicates > 0)
                    $msg_parts[] = "$duplicates duplicates skipped";
                if (!empty($errors))
                    $msg_parts[] = count($errors) . " failed";

                $success_msg = implode(", ", $msg_parts) . ".";
                logAudit($pdo, 'import_switches', "Imported $imported switches (Skipped $duplicates)", 'switch', null);
            } catch (Exception $e) {
                $error_msg = "Error importing CSV: " . $e->getMessage();
            }
        }
    } elseif ($_POST['action'] === 'add_switch') {
        $sid = $_POST['switch_id'] ?? '';
        if (empty($sid)) {
            $sid = generateNextSwitchId($pdo);
        }
        $model = $_POST['model'] ?? '';
        $manu = $_POST['manufacturer'] ?? '';
        $sn = trim($_POST['serial'] ?? '');
        if (in_array(strtolower($sn), ['', 'n/a', 'na', 'none'])) {
            $sn = null;
        }
        $ip = $_POST['ip_address'] ?? '';
        $mac = $_POST['mac_address'] ?? '';
        $building_location = $_POST['building_location'] ?? '';
        $floor = $_POST['floor'] ?? '';
        $ports = $_POST['ports'] ?? '';
        $p_det = $_POST['port_details'] ?? '';
        $p_stat = $_POST['ports_status'] ?? '';
        $status = $_POST['status'] ?? 'Active';
        $personnel = $_POST['personnel'] ?? '';
        $last_m = !empty($_POST['last_maintenance']) ? $_POST['last_maintenance'] : null;
        $next_m = $_POST['next_maintenance'] ?? '';
        $remarks = $_POST['remarks'] ?? '';

        if (true) {
            // Check Duplicates
            $dups = [];
            if (!empty($sid)) {
                $c = $pdo->prepare("SELECT COUNT(*) FROM switches WHERE switch_id = ?");
                $c->execute([$sid]);
                if ($c->fetchColumn() > 0)
                    $dups[] = "Switch ID";
            }
            if (!empty($sn)) {
                $c = $pdo->prepare("SELECT COUNT(*) FROM switches WHERE serial = ?");
                $c->execute([$sn]);
                if ($c->fetchColumn() > 0)
                    $dups[] = "Serial Number";
            }
            if (!empty($ip)) {
                $c = $pdo->prepare("SELECT COUNT(*) FROM switches WHERE ip_address = ?");
                $c->execute([$ip]);
                if ($c->fetchColumn() > 0)
                    $dups[] = "IP Address";
            }
            if (!empty($mac)) {
                $c = $pdo->prepare("SELECT COUNT(*) FROM switches WHERE mac_address = ?");
                $c->execute([$mac]);
                if ($c->fetchColumn() > 0)
                    $dups[] = "MAC Address";
            }

            if (!empty($dups)) {
                $error_msg = "Duplicate data found: " . implode(', ', $dups);
            } else {
                try {
                    $stmt = $pdo->prepare("INSERT INTO switches (switch_id, model, manufacturer, serial, ip_address, mac_address, building_location, floor, ports, port_details, ports_status, status, personnel, last_maintenance, next_maintenance, remarks) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$sid, $model, $manu, $sn, $ip, $mac, $building_location, $floor, $ports, $p_det, $p_stat, $status, $personnel, $last_m, $next_m, $remarks]);
                    $new_sw_id = $pdo->lastInsertId();
                    $add_detail = "Added Switch | Switch ID: $sid | Model: $model | Manu: $manu | Serial: $sn | IP: $ip | MAC: $mac | Location: $building_location $floor | Status: $status | Personnel: $personnel";
                    if ($remarks)
                        $add_detail .= " | Remarks: $remarks";
                    logAudit($pdo, 'add_switch', $add_detail, 'switch', $new_sw_id);
                    /* logChangelog($pdo, 'feature', 'Switch Inventory', "Added Switch: $sid ($model)", "Location: $building_location $floor | Status: $status"); removed */
                    $success_msg = "Switch added successfully.";
                } catch (Exception $e) {
                    $error_msg = "Error adding switch: " . $e->getMessage();
                }
            }
        }
    } elseif ($_POST['action'] === 'edit_switch') {
        $id = $_POST['id'] ?? 0;
        $sid = $_POST['switch_id'] ?? '';
        if (empty($sid)) {
            $sid = generateNextSwitchId($pdo);
        }
        $model = $_POST['model'] ?? '';
        $manu = $_POST['manufacturer'] ?? '';
        $sn = trim($_POST['serial'] ?? '');
        if (in_array(strtolower($sn), ['', 'n/a', 'na', 'none'])) {
            $sn = null;
        }
        $ip = $_POST['ip_address'] ?? '';
        $mac = $_POST['mac_address'] ?? '';
        $building_location = $_POST['building_location'] ?? '';
        $floor = $_POST['floor'] ?? '';
        $ports = $_POST['ports'] ?? '';
        $p_det = $_POST['port_details'] ?? '';
        $p_stat = $_POST['ports_status'] ?? '';
        $status = $_POST['status'] ?? 'Active';
        $personnel = $_POST['personnel'] ?? '';
        $last_m = !empty($_POST['last_maintenance']) ? $_POST['last_maintenance'] : null;
        $next_m = $_POST['next_maintenance'] ?? '';
        $remarks = $_POST['remarks'] ?? '';

        if ($id) {
            // Check Duplicates (excluding current ID)
            $dups = [];
            if (!empty($sid)) {
                $c = $pdo->prepare("SELECT COUNT(*) FROM switches WHERE switch_id = ? AND id != ?");
                $c->execute([$sid, $id]);
                if ($c->fetchColumn() > 0)
                    $dups[] = "Switch ID";
            }
            if (!empty($sn)) {
                $c = $pdo->prepare("SELECT COUNT(*) FROM switches WHERE serial = ? AND id != ?");
                $c->execute([$sn, $id]);
                if ($c->fetchColumn() > 0)
                    $dups[] = "Serial Number";
            }
            if (!empty($ip)) {
                $c = $pdo->prepare("SELECT COUNT(*) FROM switches WHERE ip_address = ? AND id != ?");
                $c->execute([$ip, $id]);
                if ($c->fetchColumn() > 0)
                    $dups[] = "IP Address";
            }
            if (!empty($mac)) {
                $c = $pdo->prepare("SELECT COUNT(*) FROM switches WHERE mac_address = ? AND id != ?");
                $c->execute([$mac, $id]);
                if ($c->fetchColumn() > 0)
                    $dups[] = "MAC Address";
            }

            if (!empty($dups)) {
                $error_msg = "Duplicate data found: " . implode(', ', $dups);
            } else {
                try {
                    // Snapshot old record for field-level diff
                    $snap_sw = $pdo->prepare("SELECT * FROM switches WHERE id = ?");
                    $snap_sw->execute([$id]);
                    $old_sw = $snap_sw->fetch(PDO::FETCH_ASSOC) ?: [];

                    $stmt = $pdo->prepare("UPDATE switches SET switch_id=?, model=?, manufacturer=?, serial=?, ip_address=?, mac_address=?, building_location=?, floor=?, ports=?, port_details=?, ports_status=?, status=?, personnel=?, last_maintenance=?, next_maintenance=?, remarks=? WHERE id=?");
                    $stmt->execute([$sid, $model, $manu, $sn, $ip, $mac, $building_location, $floor, $ports, $p_det, $p_stat, $status, $personnel, $last_m, $next_m, $remarks, $id]);

                    $sw_labels = [
                        'switch_id' => 'Switch ID', 'model' => 'Model', 'manufacturer' => 'Manufacturer',
                        'serial' => 'Serial', 'ip_address' => 'IP', 'mac_address' => 'MAC',
                        'building_location' => 'Building', 'floor' => 'Floor', 'ports' => 'Ports',
                        'ports_status' => 'Port Status', 'status' => 'Status', 'personnel' => 'Personnel',
                        'last_maintenance' => 'Last Maint.', 'next_maintenance' => 'Next Maint.', 'remarks' => 'Remarks',
                    ];
                    $new_sw = [
                        'switch_id' => $sid, 'model' => $model, 'manufacturer' => $manu,
                        'serial' => $sn, 'ip_address' => $ip, 'mac_address' => $mac,
                        'building_location' => $building_location, 'floor' => $floor, 'ports' => $ports,
                        'ports_status' => $p_stat, 'status' => $status, 'personnel' => $personnel,
                        'last_maintenance' => $last_m, 'next_maintenance' => $next_m, 'remarks' => $remarks,
                    ];
                    $sw_changes = [];
                    foreach ($sw_labels as $f => $lbl) {
                        $o = trim((string)($old_sw[$f] ?? '')); $n = trim((string)($new_sw[$f] ?? ''));
                        if ($o !== $n) $sw_changes[] = "{$lbl}: \"{$o}\" → \"{$n}\"";
                    }
                    $upd_detail = empty($sw_changes)
                        ? "Updated Switch (no field changes) | ID: $id | Switch ID: $sid"
                        : "Updated Switch | Switch ID: $sid | " . implode(' | ', $sw_changes);
                    logAudit($pdo, 'update_switch', $upd_detail, 'switch', $id);
                    /* logChangelog($pdo, 'enhancement', 'Switch Inventory', "Updated Switch: $sid ($model)", "Location: $building_location $floor | Status: $status"); removed */
                    $success_msg = "Switch updated successfully.";
                } catch (Exception $e) {
                    $error_msg = "Error updating switch: " . $e->getMessage();
                }
            }
        }
    } elseif ($_POST['action'] === 'delete_switch') {
        $id = $_POST['id'] ?? 0;
        if ($id) {
            try {
                $snap = $pdo->prepare("SELECT * FROM switches WHERE id = ?");
                $snap->execute([$id]);
                $del_sw = $snap->fetch(PDO::FETCH_ASSOC);
                $del_detail = "Deleted Switch ID $id";
                if ($del_sw) {
                    $del_detail = "Deleted Switch | ID: $id | Switch ID: {$del_sw['switch_id']} | Model: {$del_sw['model']} | Manu: {$del_sw['manufacturer']} | Serial: {$del_sw['serial']} | IP: {$del_sw['ip_address']} | MAC: {$del_sw['mac_address']} | Location: {$del_sw['building_location']} {$del_sw['floor']} | Status: {$del_sw['status']}";
                    if ($del_sw['remarks'])
                        $del_detail .= " | Remarks: {$del_sw['remarks']}";
                }
                $stmt = $pdo->prepare("DELETE FROM switches WHERE id = ?");
                $stmt->execute([$id]);
                logAudit($pdo, 'delete_switch', $del_detail, 'switch', $id);
                /* if ($del_sw) logChangelog($pdo, 'bugfix', 'Switch Inventory', "Removed Switch: {$del_sw['switch_id']} ({$del_sw['model']})", ...); removed — data changes not tracked in changelog */
                $success_msg = "Switch deleted successfully.";
            } catch (Exception $e) {
                $error_msg = "Error deleting switch: " . $e->getMessage();
            }
        }
    }
}

// Stats
$total_switches = $pdo->query("SELECT COUNT(*) FROM switches")->fetchColumn();
$active_switches = $pdo->query("SELECT COUNT(*) FROM switches WHERE status = 'Active'")->fetchColumn();
$m_switches = $pdo->query("SELECT COUNT(*) FROM switches WHERE status = 'Maintenance'")->fetchColumn();

// Filters
$search_term = $_GET['search'] ?? '';
$filter_manu = $_GET['manufacturer'] ?? 'All';
$filter_status = $_GET['status'] ?? 'All';
$filter_building = $_GET['building_location'] ?? 'All';
$sort_by = $_GET['sort'] ?? 'sid_asc';

$where_clauses = ["1=1"];
$params = [];

if ($search_term) {
    $where_clauses[] = "(switch_id ILIKE ? OR model ILIKE ? OR manufacturer ILIKE ? OR serial ILIKE ? OR ip_address::text ILIKE ? OR building_location ILIKE ? OR floor ILIKE ?)";
    $search_param = "%$search_term%";
    for ($i = 0; $i < 7; $i++) // Adjusted from 6 to 7 for building_location and floor
        $params[] = $search_param;
}

if ($filter_manu !== 'All' && !empty($filter_manu)) {
    $where_clauses[] = "manufacturer = ?";
    $params[] = $filter_manu;
}

if ($filter_status !== 'All' && !empty($filter_status)) {
    $where_clauses[] = "status = ?";
    $params[] = $filter_status;
}

if ($filter_building !== 'All' && !empty($filter_building)) {
    $where_clauses[] = "building_location = ?";
    $params[] = $filter_building;
}

$where_sql = implode(' AND ', $where_clauses);

// Dynamic Manufacturers List for filter
$manus = $pdo->query("SELECT DISTINCT manufacturer FROM switches WHERE manufacturer IS NOT NULL AND manufacturer != '' ORDER BY manufacturer ASC")->fetchAll(PDO::FETCH_COLUMN);

// Dynamic Buildings List for filter
$buildings = $pdo->query("SELECT DISTINCT building_location FROM switches WHERE building_location IS NOT NULL AND building_location != '' ORDER BY building_location ASC")->fetchAll(PDO::FETCH_COLUMN);

// Sorting
$order_by = "switch_id ASC";
switch ($sort_by) {
    case 'sid_asc':
        $order_by = "switch_id ASC";
        break;
    case 'sid_desc':
        $order_by = "switch_id DESC";
        break;
    case 'model_asc':
        $order_by = "model ASC";
        break;
    case 'ip_asc':
        $order_by = "split_part(COALESCE(NULLIF(ip_address,''),'0.0.0.0'), '.', 1)::int ASC, split_part(COALESCE(NULLIF(ip_address,''),'0.0.0.0'), '.', 2)::int ASC, split_part(COALESCE(NULLIF(ip_address,''),'0.0.0.0'), '.', 3)::int ASC, split_part(COALESCE(NULLIF(ip_address,''),'0.0.0.0'), '.', 4)::int ASC";
        break;
    case 'status_asc':
        $order_by = "status ASC";
        break;
    case 'id_desc':
        $order_by = "id DESC";
        break;
}

// Pagination
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1)
    $page = 1;
$limit_param = $_GET['limit'] ?? '50';
$limit = ($limit_param === 'all') ? 999999 : (int) $limit_param;
$offset = ($page - 1) * $limit;

$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM switches WHERE $where_sql");
$count_stmt->execute($params);
$total_items = $count_stmt->fetchColumn();
$total_pages = ceil($total_items / $limit);

$sql = "SELECT t.*, last_edit.username AS last_edited_by, last_edit.created_at AS last_edited_at
    FROM switches t
    LEFT JOIN LATERAL (
        SELECT u.username, al.created_at
        FROM audit_logs al
        LEFT JOIN users u ON al.user_id = u.id
        WHERE al.resource_type = 'switch' AND al.resource_id::text = t.id::text
        ORDER BY al.created_at DESC
        LIMIT 1
    ) last_edit ON true
    WHERE $where_sql ORDER BY $order_by LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$switches = $stmt->fetchAll();

$page_title = "Switch Inventory";
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';

// Generate ID for the Add Modal
$next_switch_id = generateNextSwitchId($pdo);
?>

<div class="p-4 md:p-8 flex-1 overflow-y-auto custom-scrollbar relative z-10 bg-slate-50 dark:bg-background-dark">
    <header
        class="-mt-4 -mx-4 md:-mt-8 md:-mx-8 px-4 md:px-8 pt-4 md:pt-8 pb-6 mb-8 flex flex-col md:flex-row items-start md:items-end justify-between gap-4 sticky top-0 bg-slate-50 dark:bg-background-dark z-20 border-b border-slate-200 dark:border-[#232b3d]">
        <div>
            <h2 class="text-2xl font-bold text-slate-900 dark:text-white">Switch Inventory</h2>
            <p class="text-sm text-slate-500 mt-1">Manage network switches and maintenance schedules.</p>
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
            <button onclick="toggleModal('addSwitchModal')"
                class="flex items-center justify-center rounded-lg h-9 px-4 bg-primary text-white hover:bg-primary/90 transition-colors text-xs font-bold shadow-lg shadow-primary/20">
                <span class="material-symbols-outlined text-[18px] mr-2">add</span> Add Switch
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
        <div class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] p-4 rounded-xl">
            <div class="text-slate-500 text-xs font-bold uppercase tracking-wider mb-1">Total Switches</div>
            <div class="text-2xl font-bold text-slate-900 dark:text-white">
                <?= number_format($total_switches) ?>
            </div>
        </div>
        <div class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] p-4 rounded-xl">
            <div class="text-slate-500 text-xs font-bold uppercase tracking-wider mb-1">Active</div>
            <div class="text-2xl font-bold text-emerald-500">
                <?= number_format($active_switches) ?>
            </div>
        </div>
        <div class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] p-4 rounded-xl">
            <div class="text-slate-500 text-xs font-bold uppercase tracking-wider mb-1">In Maintenance</div>
            <div class="text-2xl font-bold text-amber-500">
                <?= number_format($m_switches) ?>
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
                    placeholder="Search Switch ID..."
                    class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] text-slate-700 dark:text-slate-200 text-xs rounded-xl pl-9 pr-4 py-2 focus:ring-2 focus:ring-primary focus:border-transparent w-64">
            </div>
            <input type="hidden" name="manufacturer"      id="hid_manufacturer"      value="<?= htmlspecialchars($filter_manu) ?>">
            <input type="hidden" name="building_location" id="hid_building_location" value="<?= htmlspecialchars($filter_building) ?>">
            <input type="hidden" name="status"            id="hid_status"            value="<?= htmlspecialchars($filter_status) ?>">
            <?php
            $sw_ac=''; $sw_av='';
            if($filter_manu!=='All'&&$filter_manu!=='')           {$sw_ac='manufacturer';      $sw_av=$filter_manu;}
            elseif($filter_building!=='All'&&$filter_building!==''){$sw_ac='building_location'; $sw_av=$filter_building;}
            elseif($filter_status!=='All'&&$filter_status!=='')   {$sw_ac='status';            $sw_av=$filter_status;}
            ?>
            <select id="sw_cat" onchange="swOnCat(this.value)"
                class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] text-slate-600 dark:text-slate-300 text-xs rounded-xl px-4 py-2">
                <option value="">Filter by...</option>
                <option value="manufacturer"      <?= $sw_ac==='manufacturer'      ?'selected':'' ?>>Manufacturer</option>
                <option value="building_location" <?= $sw_ac==='building_location' ?'selected':'' ?>>Location</option>
                <option value="status"            <?= $sw_ac==='status'            ?'selected':'' ?>>Status</option>
            </select>
            <select id="sw_val" onchange="swApply(this.value)"
                class="<?= $sw_ac?'':'hidden' ?> bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] text-slate-600 dark:text-slate-300 text-xs rounded-xl px-4 py-2">
            </select>
            <?php if($sw_ac): ?>
            <div class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-primary/10 border border-primary/30 text-primary text-xs font-semibold rounded-xl">
                <span class="material-symbols-outlined text-[13px]">filter_alt</span>
                <?= htmlspecialchars($sw_av) ?>
                <a href="?search=<?= urlencode($search_term) ?>&sort=<?= urlencode($sort_by) ?>&limit=<?= urlencode($limit_param) ?>" class="ml-1 text-primary/60 hover:text-red-500" title="Clear">
                    <span class="material-symbols-outlined text-[14px]">close</span>
                </a>
            </div>
            <?php endif; ?>
            <script>
            const _swO={
                manufacturer:      <?= json_encode(array_values($manus)) ?>,
                building_location: <?= json_encode(array_values($buildings)) ?>,
                status:            ['Active','Maintenance','Inactive']
            };
            const _swAC=<?= json_encode($sw_ac) ?>, _swAV=<?= json_encode($sw_av) ?>;
            function swOnCat(c){
                const v=document.getElementById('sw_val');
                if(!c){v.classList.add('hidden');return;}
                ['manufacturer','building_location','status'].forEach(function(k){if(k!==c){const h=document.getElementById('hid_'+k);if(h)h.value='All';}});
                v.innerHTML='<option value="All">All</option>';
                (_swO[c]||[]).forEach(function(o){const e=document.createElement('option');e.value=o;e.textContent=o;if(c===_swAC&&o===_swAV)e.selected=true;v.appendChild(e);});
                v.classList.remove('hidden');
            }
            function swApply(val){
                const c=document.getElementById('sw_cat').value;if(!c)return;
                document.getElementById('hid_'+c).value=val;
                document.getElementById('sw_cat').closest('form').submit();
            }
            document.addEventListener('DOMContentLoaded',function(){if(_swAC)swOnCat(_swAC);});
            </script>
            <select name="sort" onchange="this.form.submit()"
                class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] text-slate-400 text-xs rounded-xl px-4 py-2">
                <option value="id_desc" <?= $sort_by === 'id_desc' ? 'selected' : '' ?>>Newest First</option>
                <option value="sid_asc" <?= $sort_by === 'sid_asc' ? 'selected' : '' ?>>Switch ID (A-Z)</option>
                <option value="model_asc" <?= $sort_by === 'model_asc' ? 'selected' : '' ?>>Model (A-Z)</option>
                <option value="ip_asc" <?= $sort_by === 'ip_asc' ? 'selected' : '' ?>>IP Address</option>
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
                        <a href="?page=<?= max(1, $page - 1) ?>&search=<?= urlencode($search_term) ?>&manufacturer=<?= urlencode($filter_manu) ?>&status=<?= urlencode($filter_status) ?>&limit=<?= $limit ?>"
                            class="px-3 flex items-center justify-center hover:bg-white/5 text-slate-400 hover:text-slate-900 dark:hover:text-white transition-colors border-r border-slate-200 dark:border-[#232b3d] <?= $page <= 1 ? 'pointer-events-none opacity-50' : '' ?>">
                            <span class="material-symbols-outlined text-[18px]">chevron_left</span>
                        </a>
                        <a href="?page=<?= min($total_pages, $page + 1) ?>&search=<?= urlencode($search_term) ?>&manufacturer=<?= urlencode($filter_manu) ?>&status=<?= urlencode($filter_status) ?>&limit=<?= $limit ?>"
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
                        <!-- switch_id, model, manufacturer, serial, ip_address, mac_address, building_location, floor, ports, port_details, ports_status, status, personnel, last_maintenance, next_maintenance, remarks -->
                        <th class="px-2 py-1 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">
                            Switch ID
                        </th>
                        <th class="px-2 py-1 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">Model
                        </th>
                        <th class="px-2 py-1 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">
                            Manufacturer</th>
                        <th class="px-2 py-1 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">
                            Serial
                        </th>
                        <th class="px-2 py-1 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">IP
                            Address</th>
                        <th class="px-2 py-1 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">MAC
                            Address</th>
                        <th class="px-2 py-1 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">
                            Building Location
                        </th>
                        <th class="px-2 py-1 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">Floor
                        </th>
                        <th class="px-2 py-1 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">Ports
                        </th>
                        <th class="px-2 py-1 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">Port
                            Details</th>
                        <th class="px-2 py-1 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">Port
                            Status</th>
                        <th class="px-2 py-1 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">
                            Status
                        </th>
                        <th class="px-2 py-1 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">
                            Personnel
                        </th>
                        <th class="px-2 py-1 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">Last
                            Maint.</th>
                        <th class="px-2 py-1 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">Next
                            Maint.</th>
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
                    <?php if (empty($switches)): ?>
                        <tr>
                            <td colspan="18" class="px-6 py-12 text-center text-slate-500">No switches found matching your
                                criteria.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($switches as $item): ?>
                            <tr class="hover:bg-white/5 transition-colors text-[11px]" data-item='<?= htmlspecialchars(json_encode($item), ENT_QUOTES, "UTF-8") ?>'>
                                <td class="no-print px-2 py-1 text-center">
                                    <input type="checkbox"
                                        class="item-checkbox rounded border-slate-200 dark:border-[#232b3d] bg-slate-50 dark:bg-[#101622] text-primary focus:ring-primary h-3 w-3"
                                        value="<?= $item['id'] ?>">
                                </td>
                                <td class="px-2 py-1 font-mono font-medium text-primary hover:text-primary/80 whitespace-nowrap cursor-pointer"
                                    onclick="openEditModal(<?= htmlspecialchars(json_encode($item), ENT_QUOTES, 'UTF-8') ?>)">
                                    <?= htmlspecialchars($item['switch_id']) ?>
                                </td>
                                <td class="px-2 py-1 text-slate-600 dark:text-slate-400 whitespace-nowrap">
                                    <?= htmlspecialchars($item['model']) ?>
                                </td>
                                <td class="px-2 py-1 text-slate-500 whitespace-nowrap">
                                    <?= htmlspecialchars($item['manufacturer']) ?>
                                </td>
                                <td class="px-2 py-1 text-[10px] font-mono text-slate-500 whitespace-nowrap">
                                    <?= htmlspecialchars($item['serial'] ?: 'N/A') ?>
                                </td>
                                <td class="px-2 py-1 font-mono text-slate-600 dark:text-slate-400 whitespace-nowrap">
                                    <?= htmlspecialchars($item['ip_address']) ?>
                                </td>
                                <td class="px-2 py-1 text-[10px] font-mono text-slate-500 whitespace-nowrap">
                                    <?= htmlspecialchars($item['mac_address']) ?>
                                </td>
                                <td class="px-2 py-1 text-slate-600 dark:text-slate-400 whitespace-nowrap">
                                    <?= htmlspecialchars($item['building_location']) ?>
                                </td>
                                <td class="px-2 py-1 text-slate-600 dark:text-slate-400 whitespace-nowrap">
                                    <?= htmlspecialchars($item['floor'] ?: '-') ?>
                                </td>
                                <td class="px-2 py-1 text-slate-600 dark:text-slate-400 whitespace-nowrap text-center">
                                    <?= htmlspecialchars($item['ports']) ?>
                                </td>
                                <td class="px-2 py-1 text-slate-400 truncate max-w-[80px] whitespace-nowrap"
                                    title="<?= htmlspecialchars($item['port_details'] ?: '-') ?>">
                                    <?= htmlspecialchars($item['port_details'] ?: '-') ?>
                                </td>
                                <td class="px-2 py-1 text-slate-400 truncate max-w-[80px] whitespace-nowrap"
                                    title="<?= htmlspecialchars($item['ports_status'] ?: '-') ?>">
                                    <?= htmlspecialchars($item['ports_status'] ?: '-') ?>
                                </td>
                                <td class="px-2 py-1 whitespace-nowrap">
                                    <div class="flex items-center gap-1.5">
                                        <div class="size-1.5 rounded-full <?= getStatusDotClass($item['status']) ?>"></div>
                                        <span
                                            class="font-medium <?= getStatusColor($item['status']) ?>"><?= htmlspecialchars($item['status']) ?></span>
                                    </div>
                                </td>
                                <td class="px-2 py-1 text-slate-600 dark:text-slate-400 whitespace-nowrap">
                                    <?= htmlspecialchars($item['personnel'] ?: '-') ?>
                                </td>
                                <td class="px-2 py-1 text-slate-500 whitespace-nowrap">
                                    <?= htmlspecialchars($item['last_maintenance'] ?: 'N/A') ?>
                                </td>
                                <td class="px-2 py-1 text-slate-500 whitespace-nowrap">
                                    <?= htmlspecialchars($item['next_maintenance'] ?: 'N/A') ?>
                                </td>
                                <td class="px-2 py-1 text-slate-500 truncate max-w-[100px] whitespace-nowrap"
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
                                    <button
                                        onclick="openEditModal(<?= htmlspecialchars(json_encode($item), ENT_QUOTES, 'UTF-8') ?>)"
                                        class="p-1 hover:bg-primary/10 hover:text-primary text-slate-500 rounded-lg">
                                        <span class="material-symbols-outlined text-[18px]">edit</span>
                                    </button>
                                    <form method="POST" onsubmit="return confirm('Delete switch?');">
                                        <?= getCsrfInput() ?>
                                        <input type="hidden" name="action" value="delete_switch">
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
                    <a href="?page=1&search=<?= urlencode($search_term) ?>&manufacturer=<?= urlencode($filter_manu) ?>&status=<?= urlencode($filter_status) ?>&limit=<?= $limit ?>"
                        class="p-2 hover:bg-white/5 text-slate-400 hover:text-slate-900 dark:hover:text-white rounded-lg transition-colors <?= $page <= 1 ? 'pointer-events-none opacity-50' : '' ?>"
                        title="First Page">
                        <span class="material-symbols-outlined text-[18px]">first_page</span>
                    </a>
                    <a href="?page=<?= max(1, $page - 1) ?>&search=<?= urlencode($search_term) ?>&manufacturer=<?= urlencode($filter_manu) ?>&status=<?= urlencode($filter_status) ?>&limit=<?= $limit ?>"
                        class="p-2 hover:bg-white/5 text-slate-400 hover:text-slate-900 dark:hover:text-white rounded-lg transition-colors <?= $page <= 1 ? 'pointer-events-none opacity-50' : '' ?>">
                        <span class="material-symbols-outlined text-[18px]">chevron_left</span>
                    </a>
                    <div class="flex items-center gap-1">
                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <a href="?page=<?= $i ?>&search=<?= urlencode($search_term) ?>&manufacturer=<?= urlencode($filter_manu) ?>&status=<?= urlencode($filter_status) ?>&limit=<?= $limit ?>"
                                class="w-8 h-8 flex items-center justify-center rounded-lg text-xs font-bold transition-colors <?= $i === $page ? 'bg-primary text-white' : 'text-slate-400 hover:bg-white/5 hover:text-slate-900 dark:text-white' ?>">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>
                    </div>
                    <a href="?page=<?= min($total_pages, $page + 1) ?>&search=<?= urlencode($search_term) ?>&manufacturer=<?= urlencode($filter_manu) ?>&status=<?= urlencode($filter_status) ?>&limit=<?= $limit ?>"
                        class="p-2 hover:bg-white/5 text-slate-400 hover:text-slate-900 dark:hover:text-white rounded-lg transition-colors <?= $page >= $total_pages ? 'pointer-events-none opacity-50' : '' ?>">
                        <span class="material-symbols-outlined text-[18px]">chevron_right</span>
                    </a>
                    <a href="?page=<?= $total_pages ?>&search=<?= urlencode($search_term) ?>&manufacturer=<?= urlencode($filter_manu) ?>&status=<?= urlencode($filter_status) ?>&limit=<?= $limit ?>"
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
            <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-4">Import Switches from CSV</h3>
            <p class="text-sm text-slate-400 mb-4">Upload a CSV file with Switch details. Order: Switch ID, Model,
                Manufacturer, Serial, IP Address, MAC Address, Location, Port Count, Port Details, Port Status, Status,
                Personnel, Last Maintenance, Next Maintenance, Remarks</p>
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

<!-- Modal: Add Switch -->
<div id="addSwitchModal" class="hidden fixed inset-0 z-50 overflow-y-auto" role="dialog">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-background-dark/80 backdrop-blur-sm" onclick="toggleModal('addSwitchModal')"></div>
        <div
            class="relative bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] rounded-2xl max-w-2xl w-full p-4 sm:p-6 shadow-2xl max-h-[90vh] overflow-y-auto custom-scrollbar">
            <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-6">Add New Switch</h3>
            <form method="POST">
                <?= getCsrfInput() ?>
                <input type="hidden" name="action" value="add_switch">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <input type="text" name="switch_id" value="<?= $next_switch_id ?>" readonly
                        class="w-full bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-[#232b3d] text-slate-500 dark:text-slate-400 rounded-xl px-4 py-2.5 cursor-not-allowed">
                    <input type="text" name="model" placeholder="Model" required
                        class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                    <input type="text" name="manufacturer" placeholder="Manufacturer"
                        class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                    <input type="text" name="serial" placeholder="Serial Number"
                        class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                    <input type="text" name="ip_address" placeholder="IP Address"
                        class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                    <input type="text" name="mac_address" placeholder="MAC Address"
                        class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                    <select name="building_location" required
                        class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                        <option value="">Select Building Location</option>
                        <option value="Capiz">Capiz</option>
                        <option value="Frontline">Frontline</option>
                        <option value="Ortho">Ortho</option>
                        <option value="Trauma">Trauma</option>
                        <option value="Surgery">Surgery</option>
                        <option value="Wellness">Wellness</option>
                        <option value="OPD">OPD</option>
                        <option value="OB-Pedia">OB-Pedia</option>
                        <option value="Medicine">Medicine</option>
                        <option value="Bayanihan ISO">Bayanihan ISO</option>
                        <option value="Dietary">Dietary</option>
                        <option value="HOPSS">HOPSS</option>
                        <option value="ACIS">ACIS</option>
                        <option value="Isolation">Isolation</option>
                        <option value="Bio-Safety">Bio-Safety</option>
                    </select>
                    <select name="floor" required
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
                    <input type="text" name="ports" placeholder="Ports (e.g. 24)"
                        class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                    <select name="port_details" required
                        class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                        <option value="">Select Port Details</option>
                        <option value="5x1G Base-T">5x1G Base-T</option>
                        <option value="8x1G Base-T">8x1G Base-T</option>
                        <option value="16x1G Base-T">16x1G Base-T</option>
                        <option value="24x1G Base-T">24x1G Base-T</option>
                        <option value="24xGE, 2xSFP">24xGE, 2xSFP</option>
                        <option value="48x1G Base-T">48x1G Base-T</option>
                        <option value="24x1G PoE+, 4x10G SFP+">24x1G PoE+, 4x10G SFP+</option>
                        <option value="48x1G PoE+, 4x10G SFP+">48x1G PoE+, 4x10G SFP+</option>
                        <option value="24x1G, 4xSFP+">24x1G, 4xSFP+</option>
                        <option value="48x1G, 4xSFP+">48x1G, 4xSFP+</option>
                        <option value="48x1G/10G SFP+">48x1G/10G SFP+</option>
                        <option value="24x10G SFP+">24x10G SFP+</option>
                        <option value="48x10G SFP+">48x10G SFP+</option>
                        <option value="24x10G, 8x40G QSFP+">24x10G, 8x40G QSFP+</option>
                        <option value="48x10G SFP+, 6x40G QSFP+">48x10G SFP+, 6x40G QSFP+</option>
                        <option value="32x40G QSFP+">32x40G QSFP+</option>
                        <option value="32x100G QSFP28">32x100G QSFP28</option>
                    </select>
                    <input type="text" name="ports_status" placeholder="Ports Status"
                        class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                    <select name="status"
                        class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                        <option value="Active">Active</option>
                        <option value="Maintenance">Maintenance</option>
                        <option value="Inactive">Inactive</option>
                    </select>
                    <input type="text" name="personnel" placeholder="Personnel Name"
                        class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                    <input type="date" name="last_maintenance" placeholder="Last Maintenance"
                        class="bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5 text-xs">
                    <input type="text" name="next_maintenance" placeholder="Next Maintenance Info"
                        class="bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                    <textarea name="remarks" placeholder="Remarks" rows="2"
                        class="col-span-2 w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5 resize-none h-16"></textarea>
                </div>
                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" onclick="toggleModal('addSwitchModal')"
                        class="px-4 py-2 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white">Cancel</button>
                    <button type="submit" class="px-6 py-2 bg-primary text-white font-bold rounded-xl">Save
                        Switch</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Edit Switch -->
<div id="editSwitchModal" class="hidden fixed inset-0 z-50 overflow-y-auto" role="dialog">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-background-dark/80 backdrop-blur-sm" onclick="toggleModal('editSwitchModal')">
        </div>
        <div
            class="relative bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] rounded-2xl max-w-2xl w-full p-4 sm:p-6 shadow-2xl max-h-[90vh] overflow-y-auto custom-scrollbar">
            <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-6">Edit Switch Details</h3>
            <form method="POST">
                <?= getCsrfInput() ?>
                <input type="hidden" name="action" value="edit_switch">
                <input type="hidden" name="id" id="edit_id">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Switch ID</label>
                        <input type="text" name="switch_id" id="edit_sid" readonly
                            class="w-full bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-[#232b3d] text-slate-500 dark:text-slate-400 rounded-xl px-4 py-2.5 cursor-not-allowed">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Model</label>
                        <input type="text" name="model" id="edit_model" required
                            class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Manufacturer</label>
                        <input type="text" name="manufacturer" id="edit_manu"
                            class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Serial Number</label>
                        <input type="text" name="serial" id="edit_sn"
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
                    <div class="col-span-2 grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1">Building Location</label>
                            <select name="building_location" id="edit_loc" required
                                class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                                <option value="">Select Building Location</option>
                                <option value="Capiz">Capiz</option>
                                <option value="Frontline">Frontline</option>
                                <option value="Ortho">Ortho</option>
                                <option value="Trauma">Trauma</option>
                                <option value="Surgery">Surgery</option>
                                <option value="Wellness">Wellness</option>
                                <option value="OPD">OPD</option>
                                <option value="OB-Pedia">OB-Pedia</option>
                                <option value="Medicine">Medicine</option>
                                <option value="Bayanihan ISO">Bayanihan ISO</option>
                                <option value="Dietary">Dietary</option>
                                <option value="HOPSS">HOPSS</option>
                                <option value="ACIS">ACIS</option>
                                <option value="Isolation">Isolation</option>
                                <option value="Bio-Safety">Bio-Safety</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1">Floor</label>
                            <select name="floor" id="edit_floor" required
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
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Ports</label>
                        <input type="text" name="ports" id="edit_ports"
                            class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Port Details</label>
                        <select name="port_details" id="edit_port_details" required
                            class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                            <option value="">Select Port Details</option>
                            <option value="5x1G Base-T">5x1G Base-T</option>
                            <option value="8x1G Base-T">8x1G Base-T</option>
                            <option value="16x1G Base-T">16x1G Base-T</option>
                            <option value="24x1G Base-T">24x1G Base-T</option>
                            <option value="24xGE, 2xSFP">24xGE, 2xSFP</option>
                            <option value="48x1G Base-T">48x1G Base-T</option>
                            <option value="24x1G PoE+, 4x10G SFP+">24x1G PoE+, 4x10G SFP+</option>
                            <option value="48x1G PoE+, 4x10G SFP+">48x1G PoE+, 4x10G SFP+</option>
                            <option value="24x1G, 4xSFP+">24x1G, 4xSFP+</option>
                            <option value="48x1G, 4xSFP+">48x1G, 4xSFP+</option>
                            <option value="48x1G/10G SFP+">48x1G/10G SFP+</option>
                            <option value="24x10G SFP+">24x10G SFP+</option>
                            <option value="48x10G SFP+">48x10G SFP+</option>
                            <option value="24x10G, 8x40G QSFP+">24x10G, 8x40G QSFP+</option>
                            <option value="48x10G SFP+, 6x40G QSFP+">48x10G SFP+, 6x40G QSFP+</option>
                            <option value="32x40G QSFP+">32x40G QSFP+</option>
                            <option value="32x100G QSFP28">32x100G QSFP28</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Port Status</label>
                        <input type="text" name="ports_status" id="edit_ports_status"
                            class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Status</label>
                        <select name="status" id="edit_status"
                            class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                            <option value="Active">Active</option>
                            <option value="Maintenance">Maintenance</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Personnel</label>
                        <input type="text" name="personnel" id="edit_personnel"
                            class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Last Maintenance</label>
                        <input type="date" name="last_maintenance" id="edit_last_m"
                            class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5 text-xs">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Next Maint. Info</label>
                        <input type="text" name="next_maintenance" id="edit_next_m"
                            class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                    </div>
                    <div class="col-span-2">
                        <label class="block text-xs font-medium text-slate-400 mb-1">Remarks</label>
                        <textarea name="remarks" id="edit_remarks" rows="2"
                            class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5 resize-none h-16"></textarea>
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" onclick="toggleModal('editSwitchModal')"
                        class="px-4 py-2 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white">Cancel</button>
                    <button type="submit" class="px-6 py-2 bg-primary text-white font-bold rounded-xl">Update
                        Switch</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>


    function openEditModal(item) {
        document.getElementById('edit_id').value = item.id;
        document.getElementById('edit_sid').value = item.switch_id;
        document.getElementById('edit_model').value = item.model;
        document.getElementById('edit_manu').value = item.manufacturer || '';
        document.getElementById('edit_sn').value = item.serial || 'N/A';
        document.getElementById('edit_ip').value = item.ip_address || '';
        document.getElementById('edit_mac').value = item.mac_address || '';
        document.getElementById('edit_loc').value = item.building_location || '';
        document.getElementById('edit_floor').value = item.floor || '';
        document.getElementById('edit_ports').value = item.ports || '';
        document.getElementById('edit_port_details').value = item.port_details || '';
        document.getElementById('edit_ports_status').value = item.ports_status || '';
        document.getElementById('edit_status').value = item.status;
        document.getElementById('edit_personnel').value = item.personnel || '';
        document.getElementById('edit_last_m').value = item.last_maintenance || '';
        document.getElementById('edit_next_m').value = item.next_maintenance || '';
        document.getElementById('edit_remarks').value = item.remarks || '';
        toggleModal('editSwitchModal');
    }

    // Live Search Sync
    let searchTimeout;
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('input', function () {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                this.form.submit();
            }, 500);
        });
        if (searchInput.value) {
            const val = searchInput.value;
            searchInput.value = '';
            searchInput.value = val;
            searchInput.focus();
        }
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