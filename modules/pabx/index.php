<?php
require_once '../../config.php';
requireLogin();

// Handle CSV Export
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
$total_entries = $pdo->query("SELECT COUNT(*) FROM pabx_directory")->fetchColumn();
$by_building = $pdo->query("SELECT COUNT(DISTINCT building) FROM pabx_directory")->fetchColumn();

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
$limit_param = $_GET['limit'] ?? '50';
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
    <header
        class="-mt-4 -mx-4 md:-mt-8 md:-mx-8 px-4 md:px-8 pt-4 md:pt-8 pb-6 mb-8 flex flex-col md:flex-row items-start md:items-end justify-between gap-4 sticky top-0 bg-slate-50 dark:bg-background-dark z-20 border-b border-slate-200 dark:border-[#232b3d]">
        <div>
            <h2 class="text-2xl font-bold text-slate-900 dark:text-white">PABX Directory</h2>
            <p class="text-sm text-slate-500 mt-1">Manage phone directory and extensions.</p>
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
            <button onclick="toggleModal('addEntryModal')"
                class="flex items-center justify-center rounded-lg h-9 px-4 bg-primary text-white hover:bg-primary/90 transition-colors text-xs font-bold shadow-lg shadow-primary/20">
                <span class="material-symbols-outlined text-[18px] mr-2">add</span> Add Entry
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
            <div class="text-slate-500 text-xs font-bold uppercase tracking-wider mb-1">Total Entries</div>
            <div class="text-2xl font-bold text-slate-900 dark:text-white">
                <?= number_format($total_entries) ?>
            </div>
        </div>
        <div class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] p-4 rounded-xl">
            <div class="text-slate-500 text-xs font-bold uppercase tracking-wider mb-1">Buildings Covered</div>
            <div class="text-2xl font-bold text-emerald-500">
                <?= number_format($by_building) ?>
            </div>
        </div>
        <div class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] p-4 rounded-xl">
            <div class="text-slate-500 text-xs font-bold uppercase tracking-wider mb-1">Showing Results</div>
            <div class="text-2xl font-bold text-amber-500">
                <?= number_format($total_items) ?>
            </div>
        </div>
    </div>

    <!-- Control Bar -->
    <div class="no-print mb-6">
        <form method="GET" class="flex flex-col gap-2">
            <!-- Row 1: Pagination -->
            <div class="flex items-center gap-2 flex-wrap">
                <?php if ($total_pages > 1): ?>
                    <span class="text-xs text-slate-500">Page
                        <?= $page ?> of
                        <?= $total_pages ?>
                    </span>
                    <div
                        class="flex h-9 bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] rounded-xl overflow-hidden">
                        <a href="?page=<?= max(1, $page - 1) ?>&search=<?= urlencode($search_term) ?>&building=<?= urlencode($filter_building) ?>&floor=<?= urlencode($filter_floor) ?>&limit=<?= $limit ?>"
                            class="px-3 flex items-center justify-center hover:bg-white/5 text-slate-400 hover:text-slate-900 dark:hover:text-white transition-colors border-r border-slate-200 dark:border-[#232b3d] <?= $page <= 1 ? 'pointer-events-none opacity-50' : '' ?>">
                            <span class="material-symbols-outlined text-[18px]">chevron_left</span>
                        </a>
                        <a href="?page=<?= min($total_pages, $page + 1) ?>&search=<?= urlencode($search_term) ?>&building=<?= urlencode($filter_building) ?>&floor=<?= urlencode($filter_floor) ?>&limit=<?= $limit ?>"
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
            <!-- Row 2: Search & Filters -->
            <div class="flex items-center gap-3 flex-wrap">
                <div class="relative group">
                    <span
                        class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-[18px] text-slate-500">search</span>
                    <input type="text" name="search" id="searchInput" value="<?= htmlspecialchars($search_term) ?>"
                        placeholder="Search..."
                        class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] text-slate-700 dark:text-slate-200 text-xs rounded-xl pl-9 pr-4 py-2 focus:ring-2 focus:ring-primary focus:border-transparent w-64">
                </div>
                <input type="hidden" name="building" id="hid_building" value="<?= htmlspecialchars($filter_building) ?>">
                <input type="hidden" name="floor"    id="hid_floor"    value="<?= htmlspecialchars($filter_floor) ?>">
                <?php
                $pb_ac=''; $pb_av='';
                if($filter_building!=='All'&&$filter_building!==''){$pb_ac='building';$pb_av=$filter_building;}
                elseif($filter_floor!=='All'&&$filter_floor!=='')  {$pb_ac='floor';   $pb_av=$filter_floor;}
                ?>
                <select id="pb_cat" onchange="pbOnCat(this.value)"
                    class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] text-slate-600 dark:text-slate-300 text-xs rounded-xl px-4 py-2">
                    <option value="">Filter by...</option>
                    <option value="building" <?= $pb_ac==='building'?'selected':'' ?>>Building</option>
                    <option value="floor"    <?= $pb_ac==='floor'   ?'selected':'' ?>>Floor</option>
                </select>
                <select id="pb_val" onchange="pbApply(this.value)"
                    class="<?= $pb_ac?'':'hidden' ?> bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] text-slate-600 dark:text-slate-300 text-xs rounded-xl px-4 py-2">
                </select>
                <?php if($pb_ac): ?>
                <div class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-primary/10 border border-primary/30 text-primary text-xs font-semibold rounded-xl">
                    <span class="material-symbols-outlined text-[13px]">filter_alt</span>
                    <?= htmlspecialchars($pb_av) ?>
                    <a href="?search=<?= urlencode($search_term) ?>&sort=<?= urlencode($sort_by) ?>&limit=<?= urlencode($limit_param) ?>" class="ml-1 text-primary/60 hover:text-red-500" title="Clear">
                        <span class="material-symbols-outlined text-[14px]">close</span>
                    </a>
                </div>
                <?php endif; ?>
                <script>
                const _pbO={building:<?= json_encode(array_values($buildings)) ?>,floor:['GF','2F','3F','4F','5F','6F','7F']};
                const _pbAC=<?= json_encode($pb_ac) ?>,_pbAV=<?= json_encode($pb_av) ?>;
                function pbOnCat(c){
                    const v=document.getElementById('pb_val');
                    if(!c){v.classList.add('hidden');return;}
                    ['building','floor'].forEach(function(k){if(k!==c){const h=document.getElementById('hid_'+k);if(h)h.value='All';}});
                    v.innerHTML='<option value="All">All</option>';
                    (_pbO[c]||[]).forEach(function(o){const e=document.createElement('option');e.value=o;e.textContent=o;if(c===_pbAC&&o===_pbAV)e.selected=true;v.appendChild(e);});
                    v.classList.remove('hidden');
                }
                function pbApply(val){
                    const c=document.getElementById('pb_cat').value;if(!c)return;
                    document.getElementById('hid_'+c).value=val;
                    document.getElementById('pb_cat').closest('form').submit();
                }
                document.addEventListener('DOMContentLoaded',function(){if(_pbAC)pbOnCat(_pbAC);});
                </script>
                <select name="sort" onchange="this.form.submit()"
                    class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] text-slate-400 text-xs rounded-xl px-4 py-2">
                    <option value="id_desc" <?= $sort_by === 'id_desc' ? 'selected' : '' ?>>Newest First</option>
                    <option value="local_asc" <?= $sort_by === 'local_asc' ? 'selected' : '' ?>>Local Number (A-Z)</option>
                    <option value="building_asc" <?= $sort_by === 'building_asc' ? 'selected' : '' ?>>Building (A-Z)</option>
                    <option value="dept_asc" <?= $sort_by === 'dept_asc' ? 'selected' : '' ?>>Department (A-Z)</option>
                </select>
            </div>
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
<div id="importModal" class="hidden fixed inset-0 z-50 overflow-y-auto" role="dialog">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-background-dark/80 backdrop-blur-sm" onclick="toggleModal('importModal')"></div>
        <div
            class="relative bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] rounded-2xl max-w-lg w-full p-4 sm:p-6 shadow-2xl">
            <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-4">Import PABX Directory from CSV</h3>
            <p class="text-sm text-slate-400 mb-4">Upload a CSV file with PABX details. Order: Local Number, IP Address,
                Department, Building, Floor, Display Name</p>
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