<?php
require_once '../../config.php';
requireLogin();

// Handle CSV Export
// --- CSV Template Download ---
if (isset($_GET['download_template']) && $_GET['download_template'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="pabx_directory_template.csv"');
    $output = fopen('php://output', 'w');
    fputs($output, "\xEF\xBB\xBF");
    fputcsv($output, ['Local Number', 'IP Address', 'Department', 'Building', 'Floor', 'Display Name']);
    fputcsv($output, ['101', '192.168.1.101', 'Accounting', 'Main Building', '2F', 'Accounting Office']);
    fclose($output);
    exit;
}

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $stmt = $pdo->query("SELECT * FROM pabx_directory ORDER BY id ASC");
    $entries = $stmt->fetchAll();

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="pabx_directory_export_' . date('Y-m-d_His') . '.csv"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Local Number', 'IP Address', 'Department', 'Building', 'Floor', 'Display Name']);

    foreach ($entries as $entry) {
        fputcsv($output, [
            escapeCsvField($entry['local_number']),
            escapeCsvField($entry['ip_address']),
            escapeCsvField($entry['department']),
            escapeCsvField($entry['building']),
            escapeCsvField($entry['floor']),
            escapeCsvField($entry['display_name'])
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

                    $local_num = $row[0] ?? '';

                    // Duplicate Check
                    if (!empty($local_num)) {
                        $check = $pdo->prepare("SELECT COUNT(*) FROM pabx_directory WHERE local_number = ?");
                        $check->execute([$local_num]);
                        if ($check->fetchColumn() > 0) {
                            $duplicates++;
                            continue;
                        }
                    }

                    try {
                        $stmt = $pdo->prepare("INSERT INTO pabx_directory (local_number, ip_address, department, building, floor, display_name) VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt->execute([
                            $local_num,
                            $row[1] ?? '', // ip_address
                            $row[2] ?? '', // department
                            $row[3] ?? '', // building
                            $row[4] ?? '', // floor
                            $row[5] ?? ''  // display_name
                        ]);
                        $imported++;
                    } catch (Exception $e) {
                        $errors[] = "Row " . ($imported + $duplicates + 1) . ": " . $e->getMessage();
                    }
                }

                fclose($file);

                $msg_parts = ["Imported $imported entries"];
                if ($duplicates > 0)
                    $msg_parts[] = "$duplicates duplicates skipped";
                if (!empty($errors))
                    $msg_parts[] = count($errors) . " failed";

                $success_msg = implode(", ", $msg_parts) . ".";
                logAudit($pdo, 'import_pabx', "Imported $imported PABX entries (Skipped $duplicates)", 'pabx', null);
            } catch (Exception $e) {
                $error_msg = "Error importing CSV: " . $e->getMessage();
            }
        }
    } elseif ($_POST['action'] === 'add_entry') {
        $local_num = $_POST['local_number'] ?? '';
        $ip = $_POST['ip_address'] ?? '';
        $dept = $_POST['department'] ?? '';
        $building = $_POST['building'] ?? '';
        $floor = $_POST['floor'] ?? '';
        $display_name = $_POST['display_name'] ?? '';

        if ($local_num && $building && $floor && $display_name) {
            // Check Duplicates
            $c = $pdo->prepare("SELECT COUNT(*) FROM pabx_directory WHERE local_number = ?");
            $c->execute([$local_num]);
            if ($c->fetchColumn() > 0) {
                $error_msg = "Duplicate data found: Local Number already exists";
            } else {
                try {
                    $stmt = $pdo->prepare("INSERT INTO pabx_directory (local_number, ip_address, department, building, floor, display_name) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$local_num, $ip, $dept, $building, $floor, $display_name]);
                    $new_pb_id = $pdo->lastInsertId();
                    $add_pb_detail = "Added PABX | Local#: $local_num | Name: $display_name | IP: $ip | Dept: $dept | Building: $building | Floor: $floor";
                    logAudit($pdo, 'add_pabx', $add_pb_detail, 'pabx', $new_pb_id);
                    /* logChangelog($pdo, 'feature', 'PABX Directory', "Added PABX Entry: $local_num ($display_name)", "Dept: $dept | Building: $building $floor"); removed */
                    $success_msg = "PABX entry added successfully.";
                } catch (Exception $e) {
                    $error_msg = "Error adding entry: " . $e->getMessage();
                }
            }
        } else {
            $error_msg = "Please fill in all required fields.";
        }
    } elseif ($_POST['action'] === 'edit_entry') {
        $id = $_POST['id'] ?? 0;
        $local_num = $_POST['local_number'] ?? '';
        $ip = $_POST['ip_address'] ?? '';
        $dept = $_POST['department'] ?? '';
        $building = $_POST['building'] ?? '';
        $floor = $_POST['floor'] ?? '';
        $display_name = $_POST['display_name'] ?? '';

        if ($id && $local_num && $building && $floor && $display_name) {
            // Check Duplicates (excluding current ID)
            $c = $pdo->prepare("SELECT COUNT(*) FROM pabx_directory WHERE local_number = ? AND id != ?");
            $c->execute([$local_num, $id]);
            if ($c->fetchColumn() > 0) {
                $error_msg = "Duplicate data found: Local Number already exists";
            } else {
                try {
                    // Snapshot old record for field-level diff
                    $snap_pb = $pdo->prepare("SELECT * FROM pabx_directory WHERE id = ?");
                    $snap_pb->execute([$id]);
                    $old_pb = $snap_pb->fetch(PDO::FETCH_ASSOC) ?: [];

                    $stmt = $pdo->prepare("UPDATE pabx_directory SET local_number=?, ip_address=?, department=?, building=?, floor=?, display_name=? WHERE id=?");
                    $stmt->execute([$local_num, $ip, $dept, $building, $floor, $display_name, $id]);

                    $pb_labels = [
                        'local_number' => 'Local #', 'ip_address' => 'IP', 'department' => 'Department',
                        'building' => 'Building', 'floor' => 'Floor', 'display_name' => 'Display Name',
                    ];
                    $new_pb = [
                        'local_number' => $local_num, 'ip_address' => $ip, 'department' => $dept,
                        'building' => $building, 'floor' => $floor, 'display_name' => $display_name,
                    ];
                    $pb_changes = [];
                    foreach ($pb_labels as $f => $lbl) {
                        $o = trim((string)($old_pb[$f] ?? '')); $n = trim((string)($new_pb[$f] ?? ''));
                        if ($o !== $n) $pb_changes[] = "{$lbl}: \"{$o}\" → \"{$n}\"";
                    }
                    $upd_pb_detail = empty($pb_changes)
                        ? "Updated PABX (no field changes) | ID: $id | Local#: $local_num"
                        : "Updated PABX | Local#: $local_num | " . implode(' | ', $pb_changes);
                    logAudit($pdo, 'update_pabx', $upd_pb_detail, 'pabx', $id);
                    /* logChangelog removed */
                    $success_msg = "PABX entry updated successfully.";

                } catch (Exception $e) {
                    $error_msg = "Error updating entry: " . $e->getMessage();
                }
            }
        }
    } elseif ($_POST['action'] === 'delete_entry') {
        $id = $_POST['id'] ?? 0;
        if ($id) {
            try {
                $snap = $pdo->prepare("SELECT * FROM pabx_directory WHERE id = ?");
                $snap->execute([$id]);
                $del_pb = $snap->fetch(PDO::FETCH_ASSOC);
                $del_pb_detail = "Deleted PABX Entry ID $id";
                if ($del_pb) {
                    $del_pb_detail = "Deleted PABX | ID: $id | Local#: {$del_pb['local_number']} | Name: {$del_pb['display_name']} | IP: {$del_pb['ip_address']} | Dept: {$del_pb['department']} | Building: {$del_pb['building']} | Floor: {$del_pb['floor']}";
                }
                $stmt = $pdo->prepare("DELETE FROM pabx_directory WHERE id = ?");
                $stmt->execute([$id]);
                logAudit($pdo, 'delete_pabx', $del_pb_detail, 'pabx', $id);
                /* if ($del_pb) logChangelog($pdo, 'bugfix', 'PABX Directory', "Removed PABX Entry: ...", ...); removed — data changes not tracked in changelog */
                $success_msg = "PABX entry deleted successfully.";
            } catch (Exception $e) {
                $error_msg = "Error deleting entry: " . $e->getMessage();
            }
        }
    }
}

// Stats
$total_entries = (int)($pdo->query("SELECT COUNT(*) FROM pabx_directory")->fetchColumn() ?: 0);
$by_building = (int)($pdo->query("SELECT COUNT(DISTINCT building) FROM pabx_directory WHERE building IS NOT NULL AND building != ''")->fetchColumn() ?: 0);
$by_dept = (int)($pdo->query("SELECT COUNT(DISTINCT department) FROM pabx_directory WHERE department IS NOT NULL AND department != ''")->fetchColumn() ?: 0);
$with_ip = (int)($pdo->query("SELECT COUNT(*) FROM pabx_directory WHERE ip_address IS NOT NULL AND ip_address != ''")->fetchColumn() ?: 0);
$without_ip = (int)($pdo->query("SELECT COUNT(*) FROM pabx_directory WHERE ip_address IS NULL OR ip_address = ''")->fetchColumn() ?: 0);

// Filters
$search_term = $_GET['search'] ?? '';
$filter_building = $_GET['building'] ?? 'All';
$filter_floor = $_GET['floor'] ?? 'All';
$sort_by = $_GET['sort'] ?? 'local_asc';

$where_clauses = ["1=1"];
$params = [];

if ($search_term) {
    $where_clauses[] = "(local_number ILIKE ? OR ip_address ILIKE ? OR department ILIKE ? OR display_name ILIKE ? OR building ILIKE ? OR floor ILIKE ?)";
    $search_param = "%$search_term%";
    for ($i = 0; $i < 6; $i++)
        $params[] = $search_param;
}

if ($filter_building !== 'All' && !empty($filter_building)) {
    $where_clauses[] = "building = ?";
    $params[] = $filter_building;
}

if ($filter_floor !== 'All' && !empty($filter_floor)) {
    $where_clauses[] = "floor = ?";
    $params[] = $filter_floor;
}

$where_sql = implode(' AND ', $where_clauses);

// Dynamic Buildings List for filter
$buildings = [
    'ACIS',
    'BAYANIHAN',
    'BIO SAFETY',
    'CAPIZ',
    'DIETARY',
    'FRONTLINE',
    'HOPSS',
    'ISOLATION',
    'LINGAP BAGA',
    'MEDICINE',
    'OB-GYNE/PEDIA',
    'OPD',
    'ORTHOPAEDICS',
    'SURGERY',
    'TRAUMA',
    'WELLNESS'
];

// Sorting
$order_by = "local_number ASC";
switch ($sort_by) {
    case 'local_asc':
        $order_by = "local_number ASC";
        break;
    case 'local_desc':
        $order_by = "local_number DESC";
        break;
    case 'building_asc':
        $order_by = "building ASC";
        break;
    case 'dept_asc':
        $order_by = "department ASC";
        break;
}

// Pagination
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1)
    $page = 1;
$limit_param = $_GET['limit'] ?? '15';
$limit = ($limit_param === 'all') ? 999999 : (int) $limit_param;
$offset = ($page - 1) * $limit;

$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM pabx_directory WHERE $where_sql");
$count_stmt->execute($params);
$total_items = $count_stmt->fetchColumn();
$total_pages = ceil($total_items / $limit);

$sql = "SELECT t.*, last_edit.username AS last_edited_by, last_edit.created_at AS last_edited_at
    FROM pabx_directory t
    LEFT JOIN LATERAL (
        SELECT u.username, al.created_at
        FROM audit_logs al
        LEFT JOIN users u ON al.user_id = u.id
        WHERE al.resource_type = 'pabx' AND al.resource_id::text = t.id::text
        ORDER BY al.created_at DESC
        LIMIT 1
    ) last_edit ON true
    WHERE $where_sql ORDER BY $order_by LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$entries = $stmt->fetchAll();

$page_title = "PABX Directory";
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
?>

<div class="p-4 md:p-8 flex-1 overflow-y-auto custom-scrollbar relative z-10 bg-slate-50 dark:bg-background-dark">
    <!-- Page Header -->
    <div class="mb-6 flex flex-col md:flex-row items-start md:items-end justify-between gap-4 no-print">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <span class="px-2 py-0.5 rounded text-[11px] font-bold tracking-wider uppercase bg-amber-500/10 text-amber-500 border border-amber-500/20">Telecommunications</span>
            </div>
            <h2 class="text-2xl font-bold text-slate-900 dark:text-white">PABX Directory</h2>
            <p class="text-xs text-slate-400 mt-0.5">Manage telephone extensions, VoIP endpoints, and department directory.</p>
        </div>
        <div class="no-print flex items-center gap-2 flex-wrap">
            <button onclick="toggleModal('addEntryModal')"
                class="flex items-center gap-1.5 px-3.5 h-9 rounded-lg bg-primary hover:bg-primary-hover text-white text-xs font-bold transition-all shadow-sm">
                <span class="material-symbols-outlined text-[16px]">add</span> Add Entry
            </button>
            <button onclick="toggleModal('importModal')"
                class="flex items-center gap-1.5 px-3 h-9 rounded-lg bg-amber-500 hover:bg-amber-600 text-white text-xs font-bold transition-all shadow-sm">
                <span class="material-symbols-outlined text-[16px]">upload_file</span> Import CSV
            </button>
            <a href="?export=csv"
                class="flex items-center gap-1.5 px-3 h-9 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold transition-all shadow-sm">
                <span class="material-symbols-outlined text-[16px]">download</span> Export CSV
            </a>
            <button onclick="printData()"
                class="flex items-center gap-1.5 px-3 h-9 rounded-lg bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-[#232b3d] text-xs font-bold transition-all shadow-sm">
                <span class="material-symbols-outlined text-[16px]">print</span> Print
            </button>
        </div>
    </div>

    <!-- Notification Alerts -->
    <?php if ($success_msg): ?>
        <div class="mb-4 p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-500 text-xs font-semibold flex items-center justify-between no-print">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-sm">check_circle</span>
                <?= htmlspecialchars($success_msg) ?>
            </div>
            <button onclick="this.parentElement.remove()" class="text-emerald-500/60 hover:text-emerald-500"><span class="material-symbols-outlined text-sm">close</span></button>
        </div>
    <?php endif; ?>
    <?php if ($error_msg): ?>
        <div class="mb-4 p-3 rounded-xl bg-red-500/10 border border-red-500/20 text-red-500 text-xs font-semibold flex items-center justify-between no-print">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-sm">error</span>
                <?= htmlspecialchars($error_msg) ?>
            </div>
            <button onclick="this.parentElement.remove()" class="text-red-500/60 hover:text-red-500"><span class="material-symbols-outlined text-sm">close</span></button>
        </div>
    <?php endif; ?>

    <!-- Stats Cards Grid -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 mb-6 no-print">
        <div class="bg-white dark:bg-[#161d2b] p-4 rounded-xl border border-slate-200 dark:border-[#232b3d] shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Total Entries</span>
                <span class="material-symbols-outlined text-primary text-base">phone_in_talk</span>
            </div>
            <p class="text-2xl font-bold mt-1 text-slate-900 dark:text-white"><?= number_format($total_entries) ?></p>
            <span class="text-[10px] text-slate-400">Registered extensions</span>
        </div>
        <div class="bg-white dark:bg-[#161d2b] p-4 rounded-xl border border-slate-200 dark:border-[#232b3d] shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">IP Lines</span>
                <span class="material-symbols-outlined text-emerald-500 text-base">lan</span>
            </div>
            <p class="text-2xl font-bold mt-1 text-emerald-500"><?= number_format($with_ip) ?></p>
            <span class="text-[10px] text-slate-400">VoIP / Network mapped</span>
        </div>
        <div class="bg-white dark:bg-[#161d2b] p-4 rounded-xl border border-slate-200 dark:border-[#232b3d] shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Direct / Analog</span>
                <span class="material-symbols-outlined text-amber-500 text-base">dialpad</span>
            </div>
            <p class="text-2xl font-bold mt-1 text-amber-500"><?= number_format($without_ip) ?></p>
            <span class="text-[10px] text-slate-400">Direct landlines</span>
        </div>
        <div class="bg-white dark:bg-[#161d2b] p-4 rounded-xl border border-slate-200 dark:border-[#232b3d] shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Buildings</span>
                <span class="material-symbols-outlined text-purple-500 text-base">domain</span>
            </div>
            <p class="text-2xl font-bold mt-1 text-purple-400"><?= number_format($by_building) ?></p>
            <span class="text-[10px] text-slate-400">Covered facilities</span>
        </div>
        <div class="bg-white dark:bg-[#161d2b] p-4 rounded-xl border border-slate-200 dark:border-[#232b3d] shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Departments</span>
                <span class="material-symbols-outlined text-blue-500 text-base">corporate_fare</span>
            </div>
            <p class="text-2xl font-bold mt-1 text-blue-400"><?= number_format($by_dept) ?></p>
            <span class="text-[10px] text-slate-400">Distinct hospital units</span>
        </div>
    </div>

    <!-- Filter / Control Bar -->
    <div class="bg-white dark:bg-[#161d2b] p-3 rounded-xl border border-slate-200 dark:border-[#232b3d] shadow-sm mb-4 no-print">
        <form method="GET" class="flex flex-wrap items-center gap-2">
            <div class="relative flex-1 min-w-[200px]">
                <span class="material-symbols-outlined absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400 text-sm">search</span>
                <input type="text" name="search" id="searchInput" value="<?= htmlspecialchars($search_term) ?>" placeholder="Search Local#, IP, Dept, Building, Floor..." class="w-full pl-9 pr-3 py-1.5 text-xs rounded-lg bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:border-primary">
            </div>
            <select name="building" class="px-2.5 py-1.5 text-xs rounded-lg bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-700 dark:text-slate-300 focus:outline-none focus:border-primary">
                <option value="All">All Buildings</option>
                <?php foreach ($buildings as $b): ?>
                    <option value="<?= htmlspecialchars($b) ?>" <?= $filter_building === $b ? 'selected' : '' ?>><?= htmlspecialchars($b) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="floor" class="px-2.5 py-1.5 text-xs rounded-lg bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-700 dark:text-slate-300 focus:outline-none focus:border-primary">
                <option value="All">All Floors</option>
                <?php foreach (['GF','2F','3F','4F','5F','6F','7F'] as $fl): ?>
                    <option value="<?= $fl ?>" <?= $filter_floor === $fl ? 'selected' : '' ?>><?= $fl ?></option>
                <?php endforeach; ?>
            </select>
            <select name="sort" class="px-2.5 py-1.5 text-xs rounded-lg bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-700 dark:text-slate-300 focus:outline-none focus:border-primary">
                <option value="local_asc" <?= $sort_by === 'local_asc' ? 'selected' : '' ?>>Local # (Asc)</option>
                <option value="local_desc" <?= $sort_by === 'local_desc' ? 'selected' : '' ?>>Local # (Desc)</option>
                <option value="building_asc" <?= $sort_by === 'building_asc' ? 'selected' : '' ?>>Building (A-Z)</option>
                <option value="dept_asc" <?= $sort_by === 'dept_asc' ? 'selected' : '' ?>>Department (A-Z)</option>
            </select>
            <select name="limit" class="px-2.5 py-1.5 text-xs rounded-lg bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-700 dark:text-slate-300 focus:outline-none focus:border-primary">
                <option value="15" <?= $limit_param == '15' ? 'selected' : '' ?>>15</option>
                <option value="50" <?= $limit_param == '50' ? 'selected' : '' ?>>50</option>
                <option value="100" <?= $limit_param == '100' ? 'selected' : '' ?>>100</option>
                <option value="200" <?= $limit_param == '200' ? 'selected' : '' ?>>200</option>
                <option value="all" <?= $limit_param == 'all' ? 'selected' : '' ?>>All</option>
            </select>
            <button type="submit" class="px-3.5 py-1.5 text-xs font-bold rounded-lg bg-primary hover:bg-primary-hover text-white transition-all shadow-sm">
                Filter
            </button>
            <?php if ($search_term || ($filter_building !== 'All' && $filter_building !== '') || ($filter_floor !== 'All' && $filter_floor !== '') || $sort_by !== 'local_asc' || $limit_param !== '15'): ?>
                <a href="index.php" class="px-2.5 py-1.5 text-xs font-medium text-red-500 hover:text-red-600 transition-colors">
                    Clear
                </a>
            <?php endif; ?>

            <?php if ($total_pages > 1): ?>
                <div class="ml-auto flex items-center gap-1.5 text-xs text-slate-500 dark:text-slate-400">
                    <span>Page <?= $page ?> of <?= $total_pages ?></span>
                    <a href="?page=<?= max(1, $page - 1) ?>&search=<?= urlencode($search_term) ?>&building=<?= urlencode($filter_building) ?>&floor=<?= urlencode($filter_floor) ?>&sort=<?= urlencode($sort_by) ?>&limit=<?= $limit_param ?>"
                        class="p-1 rounded bg-slate-100 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-600 dark:text-slate-300 <?= $page <= 1 ? 'pointer-events-none opacity-40' : 'hover:bg-slate-200 dark:hover:bg-[#232b3d]' ?>">
                        <span class="material-symbols-outlined text-sm block">chevron_left</span>
                    </a>
                    <a href="?page=<?= min($total_pages, $page + 1) ?>&search=<?= urlencode($search_term) ?>&building=<?= urlencode($filter_building) ?>&floor=<?= urlencode($filter_floor) ?>&sort=<?= urlencode($sort_by) ?>&limit=<?= $limit_param ?>"
                        class="p-1 rounded bg-slate-100 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-600 dark:text-slate-300 <?= $page >= $total_pages ? 'pointer-events-none opacity-40' : 'hover:bg-slate-200 dark:hover:bg-[#232b3d]' ?>">
                        <span class="material-symbols-outlined text-sm block">chevron_right</span>
                    </a>
                </div>
            <?php endif; ?>
        </form>
    </div>

    <!-- Print-Only Header: Direct Lines & Feature Codes -->
    <div class="print-only-header hidden">
        <div style="width: 100%; margin-bottom: 20px;">
            <!-- Direct Lines Section -->
            <table style="width: 100%; border-collapse: collapse; margin-bottom: 15px;">
                <thead>
                    <tr style="background-color: #ff9999;">
                        <th style="padding: 8px; border: 1px solid black; text-align: left; font-weight: bold;">
                            DIRECT LINES:
                        </th>
                    </tr>
                </thead>
                <tbody style="background-color: #ffb3b3;">
                    <tr>
                        <td style="padding: 2px 4px; border: 1px solid black; white-space: nowrap !important;">
                            <strong>4096688</strong> - PACD(Operator)
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 2px 4px; border: 1px solid black; white-space: nowrap !important;">
                            <strong>4096690</strong> - EMERGENCY ROOM
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 2px 4px; border: 1px solid black; white-space: nowrap !important;">
                            <strong>4096691</strong> - PACU
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 2px 4px; border: 1px solid black; white-space: nowrap !important;">
                            <strong>4096692</strong> - ACCOUNTING
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 2px 4px; border: 1px solid black; white-space: nowrap !important;">
                            <strong>4096693</strong> - CHIEF ADMINISTRATIVE OFFICER (CAO)
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 2px 4px; border: 1px solid black; white-space: nowrap !important;">
                            <strong>4096694</strong> - HUMAN RESOURCE
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 2px 4px; border: 1px solid black; white-space: nowrap !important;">
                            <strong>4096730</strong> - SUPPLY
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 2px 4px; border: 1px solid black; white-space: nowrap !important;">
                            <strong>4096731</strong> - CASHIER
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 2px 4px; border: 1px solid black; white-space: nowrap !important;">
                            <strong>4096732</strong> - BLOOD BANK
                        </td>
                    </tr>
                </tbody>
            </table>

            <!-- Feature Codes Section -->
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background-color: #f2f2f2;">
                        <th
                            style="padding: 8px; border: 1px solid black; text-align: left; width: 30%; font-weight: bold;">
                            FEATURE NAME
                        </th>
                        <th
                            style="padding: 8px; border: 1px solid black; text-align: left; width: 70%; font-weight: bold;">
                            FEATURE CODE
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td style="padding: 6px; border: 1px solid black;">Outside Call:</td>
                        <td style="padding: 6px; border: 1px solid black;">Dial 9 + Telephone Number (Outside Call
                            Pampanga Area Code Only)</td>
                    </tr>
                    <tr>
                        <td style="padding: 6px; border: 1px solid black;">Intercom Call:</td>
                        <td style="padding: 6px; border: 1px solid black;">Dial Local Number</td>
                    </tr>
                    <tr>
                        <td style="padding: 6px; border: 1px solid black;">Call Transfer:</td>
                        <td style="padding: 6px; border: 1px solid black;">Plunger/Flash Button + Local Number (Soft
                            touch Plunger)</td>
                    </tr>
                    <tr>
                        <td style="padding: 6px; border: 1px solid black;">Call Forward: Activate</td>
                        <td style="padding: 6px; border: 1px solid black;">Dial *411 + Local Number</td>
                    </tr>
                    <tr>
                        <td style="padding: 6px; border: 1px solid black;">Call Forward: Deactivate</td>
                        <td style="padding: 6px; border: 1px solid black;">Dial *410</td>
                    </tr>
                    <tr>
                        <td style="padding: 6px; border: 1px solid black;">Call Back:</td>
                        <td style="padding: 6px; border: 1px solid black;">Dial 2 (during busy tone)</td>
                    </tr>
                    <tr>
                        <td style="padding: 6px; border: 1px solid black;">Conference:</td>
                        <td style="padding: 6px; border: 1px solid black;">Dial Number + Plunger/Flash Button + Number +
                            Plunger/Flash + *3</td>
                    </tr>
                </tbody>
            </table>
        </div>
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
                        <th class="px-2 py-1 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">
                            Local Number
                        </th>
                        <th
                            class="px-2 py-1 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap col-ip no-print">
                            IP Address
                        </th>
                        <th class="px-2 py-1 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">
                            Department
                        </th>
                        <th class="px-2 py-1 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">
                            Building
                        </th>
                        <th class="px-2 py-1 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">
                            Floor
                        </th>
                        <th class="px-2 py-1 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">
                            Display Name
                        </th>
                        <th class="no-print px-2 py-1 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">Last Edited By</th>
                        <th
                            class="no-print px-2 py-1 text-[10px] font-bold text-slate-500 uppercase text-right whitespace-nowrap">
                            Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#232b3d]/50">
                    <?php if (empty($entries)): ?>
                        <tr>
                            <td colspan="9" class="px-6 py-12 text-center text-slate-500">No PABX entries found matching
                                your
                                criteria.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($entries as $item):
                            $item['building'] = strtoupper($item['building']);
                            ?>
                            <tr class="hover:bg-white/5 transition-colors text-[11px]" data-item='<?= htmlspecialchars(json_encode($item), ENT_QUOTES, "UTF-8") ?>'>
                                <td class="no-print px-2 py-1 text-center">
                                    <input type="checkbox"
                                        class="item-checkbox rounded border-slate-200 dark:border-[#232b3d] bg-slate-50 dark:bg-[#101622] text-primary focus:ring-primary h-3 w-3"
                                        value="<?= $item['id'] ?>">
                                </td>
                                <td class="px-2 py-1 font-mono font-medium text-slate-900 dark:text-white whitespace-nowrap">
                                    <button onclick='openEditModal(<?= json_encode($item) ?>)'
                                        class="text-left hover:text-primary hover:underline cursor-pointer transition-colors">
                                        <?= htmlspecialchars($item['local_number']) ?>
                                    </button>
                                </td>
                                <td
                                    class="px-2 py-1 font-mono text-slate-600 dark:text-slate-400 whitespace-nowrap col-ip no-print">
                                    <?= htmlspecialchars($item['ip_address'] ?: '-') ?>
                                </td>
                                <td class="px-2 py-1 text-slate-600 dark:text-slate-400 whitespace-nowrap">
                                    <?= htmlspecialchars($item['department'] ?: '-') ?>
                                </td>
                                <td class="px-2 py-1 text-slate-600 dark:text-slate-400 whitespace-nowrap">
                                    <?= htmlspecialchars($item['building']) ?>
                                </td>
                                <td class="px-2 py-1 text-slate-600 dark:text-slate-400 whitespace-nowrap">
                                    <?= htmlspecialchars($item['floor']) ?>
                                </td>
                                <td class="px-2 py-1 text-slate-600 dark:text-slate-400 whitespace-nowrap">
                                    <?= htmlspecialchars($item['display_name']) ?>
                                </td>
                                <td class="no-print px-2 py-1 whitespace-nowrap">
                                    <?php if (!empty($item['last_edited_by'])): ?>
                                        <div class="text-[10px] font-semibold text-slate-700 dark:text-slate-300"><?= htmlspecialchars($item['last_edited_by']) ?></div>
                                        <div class="text-[9px] text-slate-400"><?= date('M d, Y H:i', strtotime($item['last_edited_at'])) ?></div>
                                    <?php else: ?>
                                        <span class="text-slate-400 text-[10px]">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="no-print px-2 py-1 text-right flex items-center justify-end gap-1 whitespace-nowrap">
                                    <button onclick='openEditModal(<?= json_encode($item) ?>)'
                                        class="p-1 hover:bg-primary/10 hover:text-primary text-slate-500 rounded-lg">
                                        <span class="material-symbols-outlined text-[18px]">edit</span>
                                    </button>
                                    <form method="POST" onsubmit="return confirm('Delete this PABX entry?');">
                                        <?= getCsrfInput() ?>
                                        <input type="hidden" name="action" value="delete_entry">
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
                    Showing <span class="font-bold text-slate-900 dark:text-white">
                        <?= $offset + 1 ?>
                    </span> to <span class="font-bold text-slate-900 dark:text-white">
                        <?= min($offset + $limit, $total_items) ?>
                    </span>
                    of <span class="font-bold text-slate-900 dark:text-white">
                        <?= $total_items ?>
                    </span> results
                </div>
                <div class="flex items-center gap-2">
                    <a href="?page=1&search=<?= urlencode($search_term) ?>&building=<?= urlencode($filter_building) ?>&floor=<?= urlencode($filter_floor) ?>&limit=<?= $limit ?>"
                        class="p-2 hover:bg-white/5 text-slate-400 hover:text-slate-900 dark:hover:text-white rounded-lg transition-colors <?= $page <= 1 ? 'pointer-events-none opacity-50' : '' ?>"
                        title="First Page">
                        <span class="material-symbols-outlined text-[18px]">first_page</span>
                    </a>
                    <a href="?page=<?= max(1, $page - 1) ?>&search=<?= urlencode($search_term) ?>&building=<?= urlencode($filter_building) ?>&floor=<?= urlencode($filter_floor) ?>&limit=<?= $limit ?>"
                        class="p-2 hover:bg-white/5 text-slate-400 hover:text-slate-900 dark:hover:text-white rounded-lg transition-colors <?= $page <= 1 ? 'pointer-events-none opacity-50' : '' ?>">
                        <span class="material-symbols-outlined text-[18px]">chevron_left</span>
                    </a>
                    <div class="flex items-center gap-1">
                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <a href="?page=<?= $i ?>&search=<?= urlencode($search_term) ?>&building=<?= urlencode($filter_building) ?>&floor=<?= urlencode($filter_floor) ?>&limit=<?= $limit ?>"
                                class="w-8 h-8 flex items-center justify-center rounded-lg text-xs font-bold transition-colors <?= $i === $page ? 'bg-primary text-white' : 'text-slate-400 hover:bg-white/5 hover:text-slate-900 dark:text-white' ?>">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>
                    </div>
                    <a href="?page=<?= min($total_pages, $page + 1) ?>&search=<?= urlencode($search_term) ?>&building=<?= urlencode($filter_building) ?>&floor=<?= urlencode($filter_floor) ?>&limit=<?= $limit ?>"
                        class="p-2 hover:bg-white/5 text-slate-400 hover:text-slate-900 dark:hover:text-white rounded-lg transition-colors <?= $page >= $total_pages ? 'pointer-events-none opacity-50' : '' ?>">
                        <span class="material-symbols-outlined text-[18px]">chevron_right</span>
                    </a>
                    <a href="?page=<?= $total_pages ?>&search=<?= urlencode($search_term) ?>&building=<?= urlencode($filter_building) ?>&floor=<?= urlencode($filter_floor) ?>&limit=<?= $limit ?>"
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
<!-- Import Modal -->
<div id="importModal" class="hidden fixed inset-0 z-50 overflow-y-auto" role="dialog">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" onclick="toggleModal('importModal')"></div>
        <div class="relative bg-white dark:bg-[#1a2130] rounded-2xl shadow-2xl w-full max-w-lg p-6 border border-slate-200 dark:border-[#232b3d]">
            <div class="flex items-center justify-between border-b border-slate-200 dark:border-[#232b3d] pb-3 mb-4">
                <h3 class="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
                    <span class="material-symbols-outlined text-amber-600">upload_file</span> Import PABX Directory from CSV
                </h3>
                <button type="button" onclick="toggleModal('importModal')" class="text-slate-400 hover:text-slate-600 dark:hover:text-white transition-colors">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>

            <form method="POST" enctype="multipart/form-data" class="space-y-4">
                <?= getCsrfInput() ?>
                <input type="hidden" name="action" value="import_csv">

                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Select CSV File</label>
                    <input type="file" name="csv_file" accept=".csv" required
                        class="w-full text-sm text-slate-500 file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-amber-500/10 file:text-amber-600 hover:file:bg-amber-500/20 file:cursor-pointer border border-slate-200 dark:border-[#232b3d] rounded-xl p-2 bg-slate-50 dark:bg-[#101622]">
                </div>

                <div class="p-3 bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] rounded-xl text-xs space-y-1 text-slate-600 dark:text-slate-400">
                    <p class="font-bold text-slate-700 dark:text-slate-300 flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-[16px] text-amber-500">info</span> Supported Formats:
                    </p>
                    <p>&bull; Standard PABX Directory CSV format (6 columns matching system export).</p>
                    <p>&bull; Columns: Local Number, IP Address, Department, Building, Floor, Display Name.</p>
                    <p>&bull; Existing local number records are updated automatically.</p>
                    <div class="pt-2">
                        <a href="?download_template=csv" class="inline-flex items-center gap-1 font-bold text-emerald-500 hover:text-emerald-400 hover:underline">
                            <span class="material-symbols-outlined text-[14px]">download</span> Download CSV Template
                        </a>
                    </div>
                </div>

                <div class="flex justify-end gap-3 pt-3 border-t border-slate-200 dark:border-[#232b3d]">
                    <button type="button" onclick="toggleModal('importModal')" class="px-5 py-2 rounded-xl text-xs font-bold text-slate-500 hover:bg-slate-100 dark:hover:bg-[#232b3d] transition-colors">Cancel</button>
                    <button type="submit" class="px-5 py-2 rounded-xl text-xs font-bold bg-amber-600 text-white hover:bg-amber-700 flex items-center gap-1.5 shadow-sm transition-colors">
                        <span class="material-symbols-outlined text-[16px]">upload</span> Upload &amp; Import
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Add Entry -->
<div id="addEntryModal" class="hidden fixed inset-0 z-50 overflow-y-auto" role="dialog">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-background-dark/80 backdrop-blur-sm" onclick="toggleModal('addEntryModal')"></div>
        <div
            class="relative bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] rounded-2xl max-w-2xl w-full p-4 sm:p-6 shadow-2xl max-h-[90vh] overflow-y-auto custom-scrollbar">
            <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-6">Add New PABX Entry</h3>
            <form method="POST">
                <?= getCsrfInput() ?>
                <input type="hidden" name="action" value="add_entry">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <input type="text" name="local_number" placeholder="Local Number (e.g., 1001)" required
                        class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                    <input type="text" name="ip_address" placeholder="IP Address (Optional)"
                        class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                    <input type="text" name="department" placeholder="Department (Optional)"
                        class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                    <select name="building" required
                        class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                        <option value="">Select Building</option>
                        <?php foreach ($buildings as $b): ?>
                            <option value="<?= htmlspecialchars($b) ?>">
                                <?= htmlspecialchars($b) ?>
                            </option>
                        <?php endforeach; ?>
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
                    <input type="text" name="display_name" placeholder="Display Name" required
                        class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                </div>
                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" onclick="toggleModal('addEntryModal')"
                        class="px-4 py-2 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white">Cancel</button>
                    <button type="submit" class="px-6 py-2 bg-primary text-white font-bold rounded-xl">Add
                        Entry</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Edit Entry -->
<div id="editEntryModal" class="hidden fixed inset-0 z-50 overflow-y-auto" role="dialog">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-background-dark/80 backdrop-blur-sm" onclick="toggleModal('editEntryModal')">
        </div>
        <div
            class="relative bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] rounded-2xl max-w-2xl w-full p-4 sm:p-6 shadow-2xl max-h-[90vh] overflow-y-auto custom-scrollbar">
            <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-6">Edit PABX Entry</h3>
            <form method="POST">
                <?= getCsrfInput() ?>
                <input type="hidden" name="action" value="edit_entry">
                <input type="hidden" name="id" id="edit_id">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Local Number</label>
                        <input type="text" name="local_number" id="edit_local" required
                            class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">IP Address</label>
                        <input type="text" name="ip_address" id="edit_ip"
                            class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Department</label>
                        <input type="text" name="department" id="edit_dept"
                            class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Building</label>
                        <select name="building" id="edit_building" required
                            class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                            <option value="">Select Building</option>
                            <?php foreach ($buildings as $b): ?>
                                <option value="<?= htmlspecialchars($b) ?>">
                                    <?= htmlspecialchars($b) ?>
                                </option>
                            <?php endforeach; ?>
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
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Display Name</label>
                        <input type="text" name="display_name" id="edit_display" required
                            class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" onclick="toggleModal('editEntryModal')"
                        class="px-4 py-2 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white">Cancel</button>
                    <button type="submit" class="px-6 py-2 bg-primary text-white font-bold rounded-xl">Update
                        Entry</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>


    function openEditModal(item) {
        document.getElementById('edit_id').value = item.id;
        document.getElementById('edit_local').value = item.local_number;
        document.getElementById('edit_ip').value = item.ip_address || '';
        document.getElementById('edit_dept').value = item.department || '';
        document.getElementById('edit_building').value = item.building;
        document.getElementById('edit_floor').value = item.floor;
        document.getElementById('edit_display').value = item.display_name;
        toggleModal('editEntryModal');
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
        try {
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
        } catch (e) {
            console.error('Print logic error:', e);
            // Default to printing everything if logic fails
            document.body.classList.remove('print-filtered');
        }


        // Auto-detect orientation: match screen orientation
        const isLandscape = window.innerWidth > window.innerHeight;
        const style = document.createElement('style');
        style.id = 'print-orientation-style';
        style.innerHTML = `@media print { @page { size: ${isLandscape ? 'landscape' : 'portrait'}; margin: 0.25in; } }`;
        document.head.appendChild(style);

        // Trigger print dialog immediately
        window.print();

        // Cleanup after print dialog closes (though JS halts during print usually)
        // We leave it to ensure next print call re-evaluates or overwrites
        setTimeout(() => {
            const oldStyle = document.getElementById('print-orientation-style');
            if (oldStyle) oldStyle.remove();
        }, 1000);
    }


</script>

<!-- Print Styles -->
<style>
    @media print {

        /* Default page rule - overridden by JS dynamic style above */
        @page {
            size: auto;
            margin: 0.25in;
        }

        .print-only-header {
            display: block !important;
            page-break-after: auto;
            margin-bottom: 20px;
            visibility: visible !important;
        }

        .print-only-header table {
            width: 100% !important;
            border-collapse: collapse !important;
            table-layout: fixed !important;
        }

        .print-only-header th,
        .print-only-header td {
            display: table-cell !important;
            visibility: visible !important;
            border: 1px solid black !important;
            padding: 6px !important;
        }

        .no-print {
            display: none !important;
        }

        aside,
        .sidebar {
            display: none !important;
        }

        .overflow-x-auto,
        .overflow-hidden,
        .custom-scrollbar,
        .rounded-2xl {
            overflow: visible !important;
            height: auto !important;
            border-radius: 0 !important;
            box-shadow: none !important;
        }

        header .flex.items-center.gap-3 {
            display: none !important;
        }

        .grid.grid-cols-1.md\:grid-cols-3.gap-4.mb-8 {
            display: none !important;
        }

        .mb-6.flex.gap-3 {
            display: none !important;
        }

        /* Force print header to show - override everything */
        .print-only-header {
            display: block !important;
            visibility: visible !important;
            opacity: 1 !important;
            max-height: none !important;
            overflow: visible !important;
            font-size: 10px !important;
            /* Smaller font */
        }

        /* Ensure all structural elements are visible but keep their natural display type */
        .print-only-header div {
            display: block !important;
        }

        .print-only-header table {
            display: table !important;
            margin-bottom: 10px !important;
            /* Reduced margin */
        }

        .print-only-header th,
        .print-only-header td {
            padding: 2px 4px !important;
            /* Reduced padding */
            border: 1px solid #000 !important;
        }

        .print-only-header>div {
            margin-bottom: 10px !important;
            /* Reduced margin */
        }

        /* Ensure parent containers don't hide the header */
        @media print {

            body,
            main,
            .max-w-7xl {
                overflow: visible !important;
                height: auto !important;
                display: block !important;
            }
        }

        .print-only-header table {
            display: table !important;
        }

        .print-only-header thead {
            display: table-header-group !important;
        }

        .print-only-header tbody {
            display: table-row-group !important;
        }

        .print-only-header tr {
            display: table-row !important;
        }

        .print-only-header th,
        .print-only-header td {
            display: table-cell !important;
            visibility: visible !important;
        }

        .px-6.py-4.border-t {
            display: none !important;
        }

        body {
            background: white !important;
        }

        .p-8 {
            padding: 20px !important;
        }

        header h2,
        header p {
            color: black !important;
        }

        table {
            width: 100% !important;
            border-collapse: collapse !important;
            font-size: 10px !important;
            /* Scale down font for print */
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

        tr:hover {
            background: transparent !important;
        }

        /* Scoped to main table only to avoid hiding print header rows */
        body.print-filtered table:not(.print-only-header table) tr:not(.print-row-selected) {
            display: none !important;
        }
    }
</style>



<?php require_once '../../includes/footer.php'; ?>
