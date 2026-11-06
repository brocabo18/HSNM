<?php
require_once '../../config.php';
requireLogin();

// --- Export CSV ---
// --- CSV Template Download ---
if (isset($_GET['download_template']) && $_GET['download_template'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="queueing_tv_template.csv"');
    $output = fopen('php://output', 'w');
    fputs($output, "\xEF\xBB\xBF");
    fputcsv($output, ['Location', 'Department', 'Remote Status', 'Remote Link', 'Queuing Link', 'IP Address', 'Anydesk ID', 'TeamViewer ID', 'RustDesk ID', 'Password', 'Remarks']);
    fputcsv($output, ['Capiz 2F Waiting Area', 'OPD', 'Online', 'http://192.168.1.100:8080', 'http://queue.hospital.gov.ph', '192.168.1.100', '123456789', '987654321', '555666777', 'tvpass123', 'Samsung 55" Smart TV']);
    fclose($output);
    exit;
}

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $stmt = $pdo->query("SELECT * FROM queueing_tvs ORDER BY id ASC");
    $items = $stmt->fetchAll();

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="queueing_tv_export_' . date('Y-m-d_His') . '.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Location', 'Department', 'Remote Status', 'Remote Link', 'Queuing Link', 'IP Address', 'Anydesk ID', 'TeamViewer ID', 'RustDesk ID', 'Password', 'Remarks']);
    
    foreach ($items as $row) {
        fputcsv($output, [
            escapeCsvField($row['location']),
            escapeCsvField($row['department']),
            escapeCsvField($row['remote_status']),
            escapeCsvField($row['remote_link']),
            escapeCsvField($row['queuing_link']),
            escapeCsvField($row['ip_address']),
            escapeCsvField($row['anydesk_id']),
            escapeCsvField($row['teamviewer_id']),
            escapeCsvField($row['rustdesk_id']),
            escapeCsvField($row['password']),
            escapeCsvField($row['remarks'])
        ]);
    }
    fclose($output);
    exit;
}

// Handle CSRF and Actions
$success_msg = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $error_msg = "Security validation failed. Please try again.";
    } elseif ($_POST['action'] === 'import_csv') {
        if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === 0) {
            try {
                $file = fopen($_FILES['csv_file']['tmp_name'], 'r');
                $header = fgetcsv($file); // Skip header
                $imported = 0;
                $skipped = 0;
                
                while (($row = fgetcsv($file)) !== false) {
                    if (count($row) < 11) continue;
                    
                    try {
                        $stmt = $pdo->prepare("INSERT INTO queueing_tvs (location, department, remote_status, remote_link, queuing_link, ip_address, anydesk_id, teamviewer_id, rustdesk_id, password, remarks) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([
                            $row[0] ?? '',
                            $row[1] ?? '',
                            $row[2] ?? 'No',
                            $row[3] ?? '',
                            $row[4] ?? '',
                            $row[5] ?? '',
                            $row[6] ?? '',
                            $row[7] ?? '',
                            $row[8] ?? '',
                            $row[9] ?? '',
                            $row[10] ?? ''
                        ]);
                        $imported++;
                    } catch (Exception $e) {
                        $skipped++;
                    }
                }
                fclose($file);
                
                $msg_parts = ["Imported $imported Queueing TVs"];
                if ($skipped > 0) $msg_parts[] = "$skipped failed";
                $success_msg = implode(', ', $msg_parts) . '.';
                logAudit($pdo, 'import_qtv', "Imported $imported Queueing TVs", 'queueing_tv');
                /* logChangelog($pdo, 'feature', 'Queueing TVs', "Imported CSV data", "Records: $imported"); removed */
            } catch (Exception $e) {
                $error_msg = "Error importing CSV: " . $e->getMessage();
            }
        } else {
            $error_msg = "Please select a valid CSV file.";
        }
    } elseif ($_POST['action'] === 'add_qtv') {
        $location = $_POST['location'] ?? '';
        $department = $_POST['department'] ?? '';
        $remote_status = $_POST['remote_status'] ?? 'No';
        $remote_link = $_POST['remote_link'] ?? '';
        $queuing_link = $_POST['queuing_link'] ?? '';
        $ip_address = $_POST['ip_address'] ?? '';
        $anydesk_id = $_POST['anydesk_id'] ?? '';
        $teamviewer_id = $_POST['teamviewer_id'] ?? '';
        $rustdesk_id = $_POST['rustdesk_id'] ?? '';
        $password = $_POST['password'] ?? '';
        $remarks = $_POST['remarks'] ?? '';

        if ($location && $department) {
            try {
                $stmt = $pdo->prepare("INSERT INTO queueing_tvs (location, department, remote_status, remote_link, queuing_link, ip_address, anydesk_id, teamviewer_id, rustdesk_id, password, remarks) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$location, $department, $remote_status, $remote_link, $queuing_link, $ip_address, $anydesk_id, $teamviewer_id, $rustdesk_id, $password, $remarks]);
                $new_id = $pdo->lastInsertId();
                logAudit($pdo, 'add_qtv', "Added Queueing TV | Loc: $location | Dept: $department | IP: $ip_address", 'queueing_tv', $new_id);
                /* logChangelog($pdo, 'feature', 'Queueing TV', "Added new Queueing TV", "IP: $ip_address"); removed */
                $success_msg = "Queueing TV added successfully.";
            } catch (Exception $e) {
                $error_msg = "Error adding TV: " . $e->getMessage();
            }
        } else {
            $error_msg = "Location and Department are required.";
        }
    } elseif ($_POST['action'] === 'edit_qtv') {
        $id = $_POST['id'] ?? 0;
        $location = $_POST['location'] ?? '';
        $department = $_POST['department'] ?? '';
        $remote_status = $_POST['remote_status'] ?? 'No';
        $remote_link = $_POST['remote_link'] ?? '';
        $queuing_link = $_POST['queuing_link'] ?? '';
        $ip_address = $_POST['ip_address'] ?? '';
        $anydesk_id = $_POST['anydesk_id'] ?? '';
        $teamviewer_id = $_POST['teamviewer_id'] ?? '';
        $rustdesk_id = $_POST['rustdesk_id'] ?? '';
        $password = $_POST['password'] ?? '';
        $remarks = $_POST['remarks'] ?? '';

        if ($id && $location && $department) {
            try {
                $stmt = $pdo->prepare("UPDATE queueing_tvs SET location=?, department=?, remote_status=?, remote_link=?, queuing_link=?, ip_address=?, anydesk_id=?, teamviewer_id=?, rustdesk_id=?, password=?, remarks=? WHERE id=?");
                $stmt->execute([$location, $department, $remote_status, $remote_link, $queuing_link, $ip_address, $anydesk_id, $teamviewer_id, $rustdesk_id, $password, $remarks, $id]);
                logAudit($pdo, 'update_qtv', "Updated Queueing TV | ID: $id | IP: $ip_address", 'queueing_tv', $id);
                /* logChangelog($pdo, 'enhancement', 'Queueing TV', "Updated Queueing TV details", "IP: $ip_address"); removed */
                $success_msg = "Queueing TV updated successfully.";
            } catch (Exception $e) {
                $error_msg = "Error updating TV: " . $e->getMessage();
            }
        }
    } elseif ($_POST['action'] === 'delete_qtv') {
        $id = $_POST['id'] ?? 0;
        if ($id) {
            try {
                $stmt = $pdo->prepare("DELETE FROM queueing_tvs WHERE id = ?");
                $stmt->execute([$id]);
                logAudit($pdo, 'delete_qtv', "Deleted Queueing TV ID $id", 'queueing_tv', $id);
                /* logChangelog($pdo, 'bugfix', 'Queueing TV', "Removed Queueing TV", "ID: $id"); removed */
                $success_msg = "Queueing TV deleted successfully.";
            } catch (Exception $e) {
                $error_msg = "Error deleting TV: " . $e->getMessage();
            }
        }
    }
}

// Data fetching for UI table
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$where = "1=1";
$params = [];
if ($search) {
    $where .= " AND (ip_address ILIKE ? OR anydesk_id ILIKE ? OR remarks ILIKE ? OR location ILIKE ? OR department ILIKE ?)";
    $s = "%$search%";
    $params = [$s, $s, $s, $s, $s];
}
if ($status) {
    $where .= " AND LOWER(remote_status) = LOWER(?)";
    $params[] = $status;
}

$sort = $_GET['sort'] ?? 'id_desc';
$orderBy = "id DESC";
if ($sort === 'id_asc') $orderBy = "id ASC";
elseif ($sort === 'location') $orderBy = "location ASC";
elseif ($sort === 'dept') $orderBy = "department ASC";

$query = "SELECT * FROM queueing_tvs WHERE $where ORDER BY $orderBy";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$qtvs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_tvs = (int)($pdo->query("SELECT COUNT(*) FROM queueing_tvs")->fetchColumn() ?: 0);
$active_remote = (int)($pdo->query("SELECT COUNT(*) FROM queueing_tvs WHERE LOWER(remote_status) = 'yes'")->fetchColumn() ?: 0);
$no_remote = (int)($pdo->query("SELECT COUNT(*) FROM queueing_tvs WHERE LOWER(remote_status) != 'yes' OR remote_status IS NULL")->fetchColumn() ?: 0);
$locations_count = (int)($pdo->query("SELECT COUNT(DISTINCT location) FROM queueing_tvs WHERE location IS NOT NULL AND location != ''")->fetchColumn() ?: 0);
$with_ip_count = (int)($pdo->query("SELECT COUNT(*) FROM queueing_tvs WHERE ip_address IS NOT NULL AND ip_address != ''")->fetchColumn() ?: 0);
?>
<?php require_once '../../includes/header.php'; ?>

<!-- Print Styles injected globally for this module -->
<style>
/* Print Styles */
@media print {
    title { display: none; }
    body { background-color: white !important; margin: 0; padding: 0; color: black !important; }
    .no-print, header, aside, .sidebar-overlay { display: none !important; }
    .p-4 { padding: 0 !important; }
    .md\:p-8 { padding: 0 !important; }
    .flex-1 { margin-left: 0 !important; transform: none !important; width: 100% !important; }
    #printArea { box-shadow: none !important; border: none !important; width: 100% !important; overflow: visible !important; }
    table { width: 100%; border-collapse: collapse !important; font-size: 10px !important; }
    th { background-color: #f1f5f9 !important; color: black !important; border: 1px solid #cbd5e1 !important; padding: 4px !important; }
    td { border: 1px solid #cbd5e1 !important; padding: 4px !important; color: black !important; }
    
    /* Show original password text on print, hide the dots/eye */
    .pw-show { display: inline !important; }
    .pw-hide { display: none !important; }
    .material-symbols-outlined { display: none !important; }
    
    body.print-filtered tbody tr { display: none; }
    body.print-filtered tbody tr.print-row-selected { display: table-row; }
    .action-col { display: none !important; }
    .checkbox-col { display: none !important; }
}
</style>

<div class="flex h-screen w-full bg-slate-50 dark:bg-background-dark overflow-hidden transition-colors duration-300">
    <?php require_once '../../includes/sidebar.php'; ?>
    
    <div class="p-4 md:p-8 flex-1 overflow-y-auto custom-scrollbar relative z-10 bg-slate-50 dark:bg-background-dark print:p-0 print:overflow-visible">
        <!-- Page Header -->
        <div class="mb-6 flex flex-col md:flex-row items-start md:items-end justify-between gap-4 no-print">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <span class="px-2 py-0.5 rounded text-[11px] font-bold tracking-wider uppercase bg-amber-500/10 text-amber-500 border border-amber-500/20">Display Systems</span>
                </div>
                <h2 class="text-2xl font-bold text-slate-900 dark:text-white">Queueing TV Inventory</h2>
                <p class="text-xs text-slate-400 mt-0.5">Manage queuing display systems and their remote access details.</p>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <button onclick="toggleModal('addModal')" class="flex items-center gap-1.5 px-3.5 h-9 rounded-lg bg-primary hover:bg-primary-hover text-white text-xs font-bold transition-all shadow-sm">
                    <span class="material-symbols-outlined text-[16px]">add</span> Add Queueing TV
                </button>
                <button onclick="toggleModal('importModal')" class="flex items-center gap-1.5 px-3 h-9 rounded-lg bg-amber-500 hover:bg-amber-600 text-white text-xs font-bold transition-all shadow-sm">
                    <span class="material-symbols-outlined text-[16px]">upload_file</span> Import CSV
                </button>
                <a href="?export=csv" class="flex items-center gap-1.5 px-3 h-9 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold transition-all shadow-sm">
                    <span class="material-symbols-outlined text-[16px]">download</span> Export CSV
                </a>
                <button onclick="printData()" class="flex items-center gap-1.5 px-3 h-9 rounded-lg bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-[#232b3d] text-xs font-bold transition-all shadow-sm">
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
                    <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Total TVs</span>
                    <span class="material-symbols-outlined text-primary text-base">tv</span>
                </div>
                <p class="text-2xl font-bold mt-1 text-slate-900 dark:text-white"><?= number_format($total_tvs) ?></p>
                <span class="text-[10px] text-slate-400">Total queuing displays</span>
            </div>
            <div class="bg-white dark:bg-[#161d2b] p-4 rounded-xl border border-slate-200 dark:border-[#232b3d] shadow-sm">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Online Remote</span>
                    <span class="material-symbols-outlined text-emerald-500 text-base">check_circle</span>
                </div>
                <p class="text-2xl font-bold mt-1 text-emerald-500"><?= number_format($active_remote) ?></p>
                <span class="text-[10px] text-slate-400">Accessible remotely</span>
            </div>
            <div class="bg-white dark:bg-[#161d2b] p-4 rounded-xl border border-slate-200 dark:border-[#232b3d] shadow-sm">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Offline / Local</span>
                    <span class="material-symbols-outlined text-slate-400 text-base">desktop_access_disabled</span>
                </div>
                <p class="text-2xl font-bold mt-1 text-slate-700 dark:text-slate-300"><?= number_format($no_remote) ?></p>
                <span class="text-[10px] text-slate-400">Local control only</span>
            </div>
            <div class="bg-white dark:bg-[#161d2b] p-4 rounded-xl border border-slate-200 dark:border-[#232b3d] shadow-sm">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Locations</span>
                    <span class="material-symbols-outlined text-purple-500 text-base">location_on</span>
                </div>
                <p class="text-2xl font-bold mt-1 text-purple-400"><?= number_format($locations_count) ?></p>
                <span class="text-[10px] text-slate-400">Active areas</span>
            </div>
            <div class="bg-white dark:bg-[#161d2b] p-4 rounded-xl border border-slate-200 dark:border-[#232b3d] shadow-sm">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">IP Configured</span>
                    <span class="material-symbols-outlined text-blue-500 text-base">lan</span>
                </div>
                <p class="text-2xl font-bold mt-1 text-blue-400"><?= number_format($with_ip_count) ?></p>
                <span class="text-[10px] text-slate-400">Network mapped</span>
            </div>
        </div>

        <!-- Filter / Control Bar -->
        <div class="bg-white dark:bg-[#161d2b] p-3 rounded-xl border border-slate-200 dark:border-[#232b3d] shadow-sm mb-4 no-print">
            <form method="GET" class="flex flex-wrap items-center gap-2">
                <div class="relative flex-1 min-w-[200px]">
                    <span class="material-symbols-outlined absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400 text-sm">search</span>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search IP, AnyDesk, Location, Department..." class="w-full pl-9 pr-3 py-1.5 text-xs rounded-lg bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:border-primary">
                </div>
                <select name="status" class="px-2.5 py-1.5 text-xs rounded-lg bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-700 dark:text-slate-300 focus:outline-none focus:border-primary">
                    <option value="">All Status</option>
                    <option value="Yes" <?= ($status === 'Yes') ? 'selected' : '' ?>>Remote: Yes</option>
                    <option value="No" <?= ($status === 'No') ? 'selected' : '' ?>>Remote: No</option>
                </select>
                <select name="sort" class="px-2.5 py-1.5 text-xs rounded-lg bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-700 dark:text-slate-300 focus:outline-none focus:border-primary">
                    <option value="id_desc" <?= ($sort === 'id_desc') ? 'selected' : '' ?>>ID (Desc)</option>
                    <option value="id_asc" <?= ($sort === 'id_asc') ? 'selected' : '' ?>>ID (Asc)</option>
                    <option value="location" <?= ($sort === 'location') ? 'selected' : '' ?>>Location</option>
                    <option value="dept" <?= ($sort === 'dept') ? 'selected' : '' ?>>Department</option>
                </select>
                <button type="submit" class="px-3.5 py-1.5 text-xs font-bold rounded-lg bg-primary hover:bg-primary-hover text-white transition-all shadow-sm">
                    Filter
                </button>
                <?php if ($search || $status || $sort !== 'id_desc'): ?>
                    <a href="index.php" class="px-2.5 py-1.5 text-xs font-medium text-red-500 hover:text-red-600 transition-colors">
                        Clear
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Table -->
        <div id="printArea" class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] rounded-2xl overflow-hidden shadow-sm print:shadow-none print:border-none">
            
            <div class="hidden print:block mb-4">
                <h1 class="text-xl font-bold text-black border-b border-black pb-2 mb-2">Queueing TV Inventory Report</h1>
                <p class="text-xs text-gray-500">Generated on <?= date('Y-m-d H:i:s') ?> | <?= $total_tvs ?> Total TVs</p>
                <?php if($search) echo "<p class='text-xs text-gray-500'>Filtered by Search: " . htmlspecialchars($search) . "</p>"; ?>
            </div>

            <div class="overflow-x-auto custom-scrollbar print:overflow-visible">
                <table class="w-full text-left border-collapse whitespace-nowrap min-w-[1200px]" id="responsiveTable">
                    <thead>
                        <tr class="bg-slate-50 dark:bg-[#1e2636] border-b border-slate-200 dark:border-[#232b3d] text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400 font-bold">
                            <th class="p-4 w-12 text-center checkbox-col no-print">
                                <div class="flex items-center justify-center">
                                    <input type="checkbox" id="selectAll" onclick="toggleAllChecks(this)" class="w-4 h-4 text-primary bg-slate-100 border-slate-300 rounded focus:ring-primary dark:bg-[#101622] dark:border-[#232b3d] transition-shadow cursor-pointer">
                                </div>
                            </th>
                            <th class="p-4" style="width:120px">Location</th>
                            <th class="p-4" style="width:120px">Department</th>
                            <th class="p-4">Remote Status</th>
                            <th class="p-4">Remote Link</th>
                            <th class="p-4">Queuing Link</th>
                            <th class="p-4">IP Address</th>
                            <th class="p-4">AnyDesk ID</th>
                            <th class="p-4">TeamViewer ID</th>
                            <th class="p-4">RustDesk ID</th>
                            <th class="p-4">Password</th>
                            <th class="p-4">Remarks</th>
                            <th class="p-4 text-center action-col no-print">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm">
                        <?php if (empty($qtvs)): ?>
                            <tr><td colspan="12" class="p-8 text-center text-slate-500 dark:text-slate-400">No queueing TVs found matching your search.</td></tr>
                        <?php else: ?>
                            <?php foreach ($qtvs as $tv): ?>
                                <tr class="border-b border-slate-100 dark:border-[#232b3d]/50 hover:bg-slate-50 dark:hover:bg-[#1e2636]/50 transition-colors">
                                    <td class="p-4 text-center checkbox-col no-print">
                                        <div class="flex items-center justify-center">
                                            <input type="checkbox" class="item-checkbox w-4 h-4 text-primary bg-slate-100 border-slate-300 rounded focus:ring-primary dark:bg-[#101622] dark:border-[#232b3d] transition-shadow cursor-pointer">
                                        </div>
                                    </td>
                                    <td class="p-4 text-slate-900 dark:text-slate-200 font-medium"><?= htmlspecialchars($tv['location'] ?? '-') ?></td>
                                    <td class="p-4 text-slate-600 dark:text-slate-400"><?= htmlspecialchars($tv['department'] ?? '-') ?></td>
                                    <td class="p-4">
                                        <?php if(strtolower($tv['remote_status']) == 'yes'): ?>
                                            <span class="px-2 py-1 bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400 rounded text-xs font-bold print:border print:border-black print:text-black">Yes</span>
                                        <?php else: ?>
                                            <span class="px-2 py-1 bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400 rounded text-xs font-bold print:border print:border-black print:text-black">No</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="p-4 text-xs font-mono text-blue-500">
                                        <?php if($tv['remote_link']): ?>
                                            <a href="<?= htmlspecialchars($tv['remote_link']) ?>" target="_blank" class="hover:underline"><?= htmlspecialchars($tv['remote_link']) ?></a>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td class="p-4 text-xs font-mono text-blue-500">
                                        <?php if($tv['queuing_link']): ?>
                                            <a href="<?= htmlspecialchars($tv['queuing_link']) ?>" target="_blank" class="hover:underline"><?= htmlspecialchars($tv['queuing_link']) ?></a>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td class="p-4 text-slate-600 dark:text-slate-400 font-mono"><?= htmlspecialchars($tv['ip_address']) ?></td>
                                    <td class="p-4 text-slate-600 dark:text-slate-400"><?= htmlspecialchars($tv['anydesk_id']) ?></td>
                                    <td class="p-4 text-slate-600 dark:text-slate-400"><?= htmlspecialchars($tv['teamviewer_id']) ?></td>
                                    <td class="p-4 text-slate-600 dark:text-slate-400"><?= htmlspecialchars($tv['rustdesk_id']) ?></td>
                                    <td class="p-4 text-slate-600 dark:text-slate-400">
                                        <div class="relative inline-flex items-center group cursor-pointer" onclick="this.querySelector('.pw-hide').classList.toggle('hidden'); this.querySelector('.pw-show').classList.toggle('hidden');">
                                            <span class="pw-hide tracking-[0.2em] font-bold text-slate-400">••••••••</span>
                                            <span class="pw-show hidden font-mono"><?= htmlspecialchars($tv['password'] ?: 'N/A') ?></span>
                                            <span class="material-symbols-outlined text-[14px] text-slate-300 ml-2 group-hover:text-primary transition-colors">visibility</span>
                                        </div>
                                    </td>
                                    <td class="p-4"><div class="max-w-[200px] truncate text-slate-500" title="<?= htmlspecialchars($tv['remarks'] ?? '') ?>"><?= htmlspecialchars($tv['remarks'] ?? '-') ?></div></td>
                                    <td class="p-4 text-center action-col no-print">
                                        <div class="flex items-center justify-center gap-2">
                                            <button onclick='editQTV(<?= json_encode($tv, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) ?>)' class="h-8 w-8 bg-blue-500/10 text-blue-600 hover:bg-blue-500/20 rounded-lg flex items-center justify-center transition-colors"><span class="material-symbols-outlined text-[18px]">edit</span></button>
                                            <button onclick="confirmDelete(<?= $tv['id'] ?>)" class="h-8 w-8 bg-red-500/10 text-red-600 hover:bg-red-500/20 rounded-lg flex items-center justify-center transition-colors"><span class="material-symbols-outlined text-[18px]">delete</span></button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<!-- Add Modal -->
<div id="addModal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 hidden flex items-center justify-center pt-24 md:pt-0 no-print">
    <div class="bg-white dark:bg-[#1a2130] w-full p-4 sm:p-6 shadow-2xl overflow-y-auto max-h-[90vh] custom-scrollbar sm:max-w-2xl sm:rounded-2xl flex flex-col mx-4 md:mx-auto relative">
        <div class="flex items-center justify-between mb-6 pb-4 border-b border-slate-200 dark:border-[#232b3d]">
            <h3 class="text-xl font-bold text-slate-900 dark:text-white">Add Queueing TV</h3>
            <button onclick="toggleModal('addModal')" class="text-slate-400 hover:text-red-500 transition-colors"><span class="material-symbols-outlined rounded-full p-1 hover:bg-red-50 dark:hover:bg-red-500/10">close</span></button>
        </div>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
            <input type="hidden" name="action" value="add_qtv">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 mb-2">Location <span class="text-red-500">*</span></label>
                    <input type="text" name="location" required class="w-full bg-slate-50 dark:bg-background-dark border border-slate-200 dark:border-[#232b3d] rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-primary dark:text-white" placeholder="e.g. Lobby">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 mb-2">Department <span class="text-red-500">*</span></label>
                    <input type="text" name="department" required class="w-full bg-slate-50 dark:bg-background-dark border border-slate-200 dark:border-[#232b3d] rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-primary dark:text-white" placeholder="e.g. OPD">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 mb-2">Remote Status</label>
                    <select name="remote_status" class="w-full bg-slate-50 dark:bg-background-dark border border-slate-200 dark:border-[#232b3d] rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-primary dark:text-white">
                        <option value="No">No</option>
                        <option value="Yes">Yes</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 mb-2">IP Address</label>
                    <input type="text" name="ip_address" class="w-full bg-slate-50 dark:bg-background-dark border border-slate-200 dark:border-[#232b3d] rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-primary dark:text-white">
                </div>
                <div class="col-span-1 sm:col-span-2">
                    <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 mb-2">Queuing Link</label>
                    <input type="url" name="queuing_link" class="w-full bg-slate-50 dark:bg-background-dark border border-slate-200 dark:border-[#232b3d] rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-primary dark:text-white">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 mb-2">Remote Link</label>
                    <input type="url" name="remote_link" class="w-full bg-slate-50 dark:bg-background-dark border border-slate-200 dark:border-[#232b3d] rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-primary dark:text-white">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 mb-2">Anydesk ID</label>
                    <input type="text" name="anydesk_id" class="w-full bg-slate-50 dark:bg-background-dark border border-slate-200 dark:border-[#232b3d] rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-primary dark:text-white">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 mb-2">TeamViewer ID</label>
                    <input type="text" name="teamviewer_id" class="w-full bg-slate-50 dark:bg-background-dark border border-slate-200 dark:border-[#232b3d] rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-primary dark:text-white">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 mb-2">RustDesk ID</label>
                    <input type="text" name="rustdesk_id" class="w-full bg-slate-50 dark:bg-background-dark border border-slate-200 dark:border-[#232b3d] rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-primary dark:text-white">
                </div>
                <div class="col-span-1 sm:col-span-2">
                    <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 mb-2">Password</label>
                    <input type="text" name="password" class="w-full bg-slate-50 dark:bg-background-dark border border-slate-200 dark:border-[#232b3d] rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-primary dark:text-white">
                </div>
                <div class="col-span-1 sm:col-span-2">
                    <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 mb-2">Remarks</label>
                    <textarea name="remarks" rows="2" class="w-full bg-slate-50 dark:bg-background-dark border border-slate-200 dark:border-[#232b3d] rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-primary dark:text-white"></textarea>
                </div>
            </div>
            <div class="mt-6 flex justify-end gap-3 pt-6 border-t border-slate-200 dark:border-[#232b3d]">
                <button type="button" onclick="toggleModal('addModal')" class="px-6 py-2.5 text-sm font-bold text-slate-600 bg-slate-100 dark:bg-[#232b3d] dark:text-slate-300 rounded-xl hover:bg-slate-200 transition-colors">Cancel</button>
                <button type="submit" class="px-6 py-2.5 text-sm font-bold w-full sm:w-auto bg-primary text-white hover:bg-primary/90 transition-colors rounded-xl shadow-lg shadow-primary/30">Save TV</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Modal -->
<div id="editModal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 hidden flex items-center justify-center pt-24 md:pt-0 no-print">
    <div class="bg-white dark:bg-[#1a2130] w-full p-4 sm:p-6 shadow-2xl overflow-y-auto max-h-[90vh] custom-scrollbar sm:max-w-2xl sm:rounded-2xl flex flex-col mx-4 md:mx-auto">
        <div class="flex items-center justify-between mb-6 pb-4 border-b border-slate-200 dark:border-[#232b3d]">
            <h3 class="text-xl font-bold text-slate-900 dark:text-white">Edit Queueing TV</h3>
            <button onclick="toggleModal('editModal')" class="text-slate-400 hover:text-red-500 transition-colors"><span class="material-symbols-outlined rounded-full p-1 hover:bg-red-50 dark:hover:bg-red-500/10">close</span></button>
        </div>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
            <input type="hidden" name="action" value="edit_qtv">
            <input type="hidden" name="id" id="edit_id">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 mb-2">Location <span class="text-red-500">*</span></label>
                    <input type="text" name="location" id="edit_loc" required class="w-full bg-slate-50 dark:bg-background-dark border border-slate-200 dark:border-[#232b3d] rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-primary dark:text-white">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 mb-2">Department <span class="text-red-500">*</span></label>
                    <input type="text" name="department" id="edit_dep" required class="w-full bg-slate-50 dark:bg-background-dark border border-slate-200 dark:border-[#232b3d] rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-primary dark:text-white">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 mb-2">Remote Status</label>
                    <select name="remote_status" id="edit_remote_status" class="w-full bg-slate-50 dark:bg-background-dark border border-slate-200 dark:border-[#232b3d] rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-primary dark:text-white">
                        <option value="No">No</option>
                        <option value="Yes">Yes</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 mb-2">IP Address</label>
                    <input type="text" name="ip_address" id="edit_ip" class="w-full bg-slate-50 dark:bg-background-dark border border-slate-200 dark:border-[#232b3d] rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-primary dark:text-white">
                </div>
                <div class="col-span-1 sm:col-span-2">
                    <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 mb-2">Queuing Link</label>
                    <input type="url" name="queuing_link" id="edit_qlink" class="w-full bg-slate-50 dark:bg-background-dark border border-slate-200 dark:border-[#232b3d] rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-primary dark:text-white">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 mb-2">Remote Link</label>
                    <input type="url" name="remote_link" id="edit_rlink" class="w-full bg-slate-50 dark:bg-background-dark border border-slate-200 dark:border-[#232b3d] rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-primary dark:text-white">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 mb-2">Anydesk ID</label>
                    <input type="text" name="anydesk_id" id="edit_anydesk" class="w-full bg-slate-50 dark:bg-background-dark border border-slate-200 dark:border-[#232b3d] rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-primary dark:text-white">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 mb-2">TeamViewer ID</label>
                    <input type="text" name="teamviewer_id" id="edit_teamviewer" class="w-full bg-slate-50 dark:bg-background-dark border border-slate-200 dark:border-[#232b3d] rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-primary dark:text-white">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 mb-2">RustDesk ID</label>
                    <input type="text" name="rustdesk_id" id="edit_rustdesk" class="w-full bg-slate-50 dark:bg-background-dark border border-slate-200 dark:border-[#232b3d] rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-primary dark:text-white">
                </div>
                <div class="col-span-1 sm:col-span-2">
                    <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 mb-2">Password</label>
                    <input type="text" name="password" id="edit_password" class="w-full bg-slate-50 dark:bg-background-dark border border-slate-200 dark:border-[#232b3d] rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-primary dark:text-white">
                </div>
                <div class="col-span-1 sm:col-span-2">
                    <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 mb-2">Remarks</label>
                    <textarea name="remarks" id="edit_remarks" rows="2" class="w-full bg-slate-50 dark:bg-background-dark border border-slate-200 dark:border-[#232b3d] rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-primary dark:text-white"></textarea>
                </div>
            </div>
            <div class="mt-6 flex justify-end gap-3 pt-6 border-t border-slate-200 dark:border-[#232b3d]">
                <button type="button" onclick="toggleModal('editModal')" class="px-6 py-2.5 text-sm font-bold text-slate-600 bg-slate-100 dark:bg-[#232b3d] dark:text-slate-300 rounded-xl hover:bg-slate-200 transition-colors">Cancel</button>
                <button type="submit" class="px-6 py-2.5 text-sm font-bold w-full sm:w-auto bg-blue-600 text-white hover:bg-blue-700 transition-colors rounded-xl shadow-lg shadow-blue-600/30">Update TV</button>
            </div>
        </form>
    </div>
</div>

<!-- Import Modal -->
<div id="importModal" class="fixed inset-0 bg-black/60 z-50 hidden items-center justify-center p-4 no-print" onclick="if(event.target===this)toggleModal('importModal')">
    <div class="bg-white dark:bg-[#1a2130] rounded-2xl shadow-2xl w-full max-w-lg p-6 border border-slate-200 dark:border-[#232b3d]">
        <div class="flex items-center justify-between border-b border-slate-200 dark:border-[#232b3d] pb-3 mb-4">
            <h3 class="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
                <span class="material-symbols-outlined text-amber-600">upload_file</span> Import Queueing TVs from CSV
            </h3>
            <button type="button" onclick="toggleModal('importModal')" class="text-slate-400 hover:text-slate-600 dark:hover:text-white transition-colors">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>

        <form method="POST" enctype="multipart/form-data" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
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
                <p>&bull; Standard Queueing TV CSV format (11 columns matching system export).</p>
                <p>&bull; Columns: Location, Department, Remote Status, Remote Link, Queuing Link, IP, Anydesk, etc.</p>
                <p>&bull; Follow header layout strictly without modifying column names.</p>
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

<!-- Delete Form -->
<form id="deleteForm" method="POST" style="display:none;">
    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
    <input type="hidden" name="action" value="delete_qtv">
    <input type="hidden" name="id" id="delete_id">
</form>

<script>
    function toggleModal(modalID){
        const modal = document.getElementById(modalID);
        modal.classList.toggle('hidden');
    }
    
    function editQTV(tv){
        document.getElementById('edit_id').value = tv.id;
        document.getElementById('edit_loc').value = tv.location || '';
        document.getElementById('edit_dep').value = tv.department || '';
        document.getElementById('edit_remote_status').value = tv.remote_status || 'No';
        document.getElementById('edit_ip').value = tv.ip_address || '';
        document.getElementById('edit_qlink').value = tv.queuing_link || '';
        document.getElementById('edit_rlink').value = tv.remote_link || '';
        document.getElementById('edit_anydesk').value = tv.anydesk_id || '';
        document.getElementById('edit_teamviewer').value = tv.teamviewer_id || '';
        document.getElementById('edit_rustdesk').value = tv.rustdesk_id || '';
        document.getElementById('edit_password').value = tv.password || '';
        document.getElementById('edit_remarks').value = tv.remarks || '';
        toggleModal('editModal');
    }
    
    function confirmDelete(id){
        if(confirm('Are you sure you want to delete this Queueing TV?')){
            document.getElementById('delete_id').value = id;
            document.getElementById('deleteForm').submit();
        }
    }

    function toggleAllChecks(source) {
        let checkboxes = document.querySelectorAll('.item-checkbox');
        for(let i=0; i<checkboxes.length; i++) {
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
        }, 100);
    }
</script>
</body>
</html>
