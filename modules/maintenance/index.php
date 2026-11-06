<?php
require_once '../../config.php';
requireLogin();

$success_msg = $error_msg = '';

// CSRF
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $error_msg = 'Security validation failed.';
        $_POST['action'] = 'invalid';
    }
}

// ── Save / Submit Maintenance Record ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_maintenance') {
    try {
        $sql = "INSERT INTO maintenance (
            asset_id, department, employee_name, control_number, processor, memory, gpu,
            maintenance_date, status, performance_score,
            hw_monitor, hw_motherboard, hw_power_supply, hw_video_card, hw_hard_disk,
            hw_cdrom, hw_lan_card, hw_keyboard_mouse, hw_printer_scanner, hw_ups_avr, hw_modem,
            hw_monitor_remarks, hw_motherboard_remarks, hw_power_supply_remarks, hw_video_card_remarks,
            hw_hard_disk_remarks, hw_cdrom_remarks, hw_lan_card_remarks, hw_keyboard_remarks,
            hw_printer_remarks, hw_ups_avr_remarks, hw_modem_remarks,
            sw_defrag, sw_registry, sw_antivirus, sw_os_patch, sw_recycle_bin, sw_temp_files,
            sw_installed, sw_os_integrity, sw_office, sw_cloud_backup, sw_browser_cache,
            sw_defrag_remarks, sw_registry_remarks, sw_antivirus_remarks, sw_os_patch_remarks,
            sw_recycle_remarks, sw_temp_remarks, sw_installed_remarks, sw_integrity_remarks,
            sw_office_remarks, sw_backup_remarks, sw_browser_remarks,
            env_observations, action_taken, recommendation, checked_by, verified_by, audit_date
        ) VALUES (
            ?,?,?,?,?,?,?,?,?,?,
            ?,?,?,?,?,?,?,?,?,?,?,
            ?,?,?,?,?,?,?,?,?,?,?,
            ?,?,?,?,?,?,?,?,?,?,?,
            ?,?,?,?,?,?,?,?,?,?,?,
            ?,?,?,?,?,?
        )";
        $p = $_POST;
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            !empty($p['asset_id']) ? (int)$p['asset_id'] : null,
            $p['department']??'', $p['employee_name']??'', $p['control_number']??'',
            $p['processor']??'', $p['memory']??'', $p['gpu']??'',
            !empty($p['maintenance_date']) ? $p['maintenance_date'] : date('Y-m-d'),
            $p['status']??'Completed', (int)($p['performance_score']??100),
            // hw checks
            $p['hw_monitor']??'Pass', $p['hw_motherboard']??'Pass', $p['hw_power_supply']??'Pass',
            $p['hw_video_card']??'Pass', $p['hw_hard_disk']??'Pass', $p['hw_cdrom']??'N/A',
            $p['hw_lan_card']??'Pass', $p['hw_keyboard_mouse']??'Pass', $p['hw_printer_scanner']??'N/A',
            $p['hw_ups_avr']??'Pass', $p['hw_modem']??'N/A',
            // hw remarks
            $p['hw_monitor_remarks']??'', $p['hw_motherboard_remarks']??'', $p['hw_power_supply_remarks']??'',
            $p['hw_video_card_remarks']??'', $p['hw_hard_disk_remarks']??'', $p['hw_cdrom_remarks']??'',
            $p['hw_lan_card_remarks']??'', $p['hw_keyboard_remarks']??'', $p['hw_printer_remarks']??'',
            $p['hw_ups_avr_remarks']??'', $p['hw_modem_remarks']??'',
            // sw checks
            $p['sw_defrag']??'Pass', $p['sw_registry']??'Pass', $p['sw_antivirus']??'Pass',
            $p['sw_os_patch']??'Pass', $p['sw_recycle_bin']??'Pass', $p['sw_temp_files']??'Pass',
            $p['sw_installed']??'Pass', $p['sw_os_integrity']??'Pass', $p['sw_office']??'Pass',
            $p['sw_cloud_backup']??'N/A', $p['sw_browser_cache']??'Pass',
            // sw remarks
            $p['sw_defrag_remarks']??'', $p['sw_registry_remarks']??'', $p['sw_antivirus_remarks']??'',
            $p['sw_os_patch_remarks']??'', $p['sw_recycle_remarks']??'', $p['sw_temp_remarks']??'',
            $p['sw_installed_remarks']??'', $p['sw_integrity_remarks']??'', $p['sw_office_remarks']??'',
            $p['sw_backup_remarks']??'', $p['sw_browser_remarks']??'',
            // narrative
            $p['env_observations']??'', $p['action_taken']??'', $p['recommendation']??'',
            $p['checked_by']??'', $p['verified_by']??'',
            !empty($p['audit_date']) ? $p['audit_date'] : null,
        ]);
        logAudit($pdo, 'add_maintenance', 'Maintenance record submitted for: ' . ($p['control_number']??''), 'maintenance');
        $success_msg = 'Maintenance record saved successfully.';
    } catch (Exception $e) { $error_msg = 'Error: ' . $e->getMessage(); }
}

// ── Delete Maintenance ────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_maintenance') {
    try {
        $id = (int)$_POST['id'];
        $pdo->prepare("DELETE FROM maintenance WHERE id=?")->execute([$id]);
        logAudit($pdo, 'delete_maintenance', "Deleted maintenance ID: $id", 'maintenance', $id);
        $success_msg = 'Record deleted.';
    } catch (Exception $e) { $error_msg = 'Error: ' . $e->getMessage(); }
}

// ── Filters / Pagination ──────────────────────────────────────────────────────
$is_ajax  = isset($_GET['ajax']);
$search   = $_GET['search']  ?? '';
$status_f = $_GET['status']  ?? 'All';
$sort_by  = $_GET['sort']    ?? 'date_desc';
$limit_p  = $_GET['limit']   ?? '15';
$asset_id = isset($_GET['asset_id']) ? (int)$_GET['asset_id'] : 0;
$page     = max(1, (int)($_GET['page'] ?? 1));
$limit    = ($limit_p === 'all') ? 999999 : (int)$limit_p;
$offset   = ($page - 1) * $limit;

$where = ['1=1']; $params = [];
if ($search) {
    $where[] = "(m.control_number ILIKE ? OR a.control_id ILIKE ? OR a.brand_model ILIKE ? OR m.employee_name ILIKE ? OR m.department ILIKE ? OR m.checked_by ILIKE ?)";
    $sp = "%$search%";
    $params[] = $sp; $params[] = $sp; $params[] = $sp; $params[] = $sp; $params[] = $sp; $params[] = $sp;
}
if ($status_f !== 'All') { $where[] = "m.status = ?"; $params[] = $status_f; }
if ($asset_id > 0) { $where[] = "m.asset_id = ?"; $params[] = $asset_id; }
$where_sql = implode(' AND ', $where);
$order = match($sort_by) { 'date_asc'=>'m.maintenance_date ASC','score_asc'=>'m.performance_score ASC','score_desc'=>'m.performance_score DESC', default=>'m.maintenance_date DESC' };

$cnt = $pdo->prepare("SELECT COUNT(*) FROM maintenance m LEFT JOIN assets a ON a.id=m.asset_id WHERE $where_sql"); $cnt->execute($params);
$total_items = $cnt->fetchColumn();
$total_pages = max(1, ceil($total_items / $limit));
$page_params = "&search=".urlencode($search)."&status=".urlencode($status_f)."&sort=".urlencode($sort_by)."&limit=".urlencode($limit_p).($asset_id?"&asset_id=$asset_id":'');

$stmt = $pdo->prepare("SELECT m.*, a.brand_model AS asset_name, a.control_id AS asset_control_id FROM maintenance m LEFT JOIN assets a ON a.id=m.asset_id WHERE $where_sql ORDER BY $order LIMIT $limit OFFSET $offset");
$stmt->execute($params); $records = $stmt->fetchAll();

if (!$is_ajax) {
    // Stats
    $total_maint  = (int)($pdo->query("SELECT COUNT(*) FROM maintenance")->fetchColumn() ?: 0);
    $completed    = (int)($pdo->query("SELECT COUNT(*) FROM maintenance WHERE status='Completed'")->fetchColumn() ?: 0);
    $needs_action = (int)($pdo->query("SELECT COUNT(*) FROM maintenance WHERE status='Needs Action'")->fetchColumn() ?: 0);
    $critical_fail= (int)($pdo->query("SELECT COUNT(*) FROM maintenance WHERE status='Critical Failure'")->fetchColumn() ?: 0);
    $avg_score    = (int)($pdo->query("SELECT ROUND(AVG(performance_score)) FROM maintenance")->fetchColumn() ?: 0);

    // Pre-fill from asset if coming from assets page
    $prefill_asset = null;
    if ($asset_id > 0) {
        $prefill_asset = $pdo->prepare("SELECT * FROM assets WHERE id=?");
        $prefill_asset->execute([$asset_id]);
        $prefill_asset = $prefill_asset->fetch();
    }

    // All assets for dropdown
    $all_assets = $pdo->query("SELECT id, control_id, brand_model FROM assets ORDER BY control_id")->fetchAll();
    $staff_users = $pdo->query("SELECT full_name FROM users WHERE full_name IS NOT NULL AND TRIM(full_name)!='' ORDER BY full_name")->fetchAll(PDO::FETCH_COLUMN);

    $hw_items = [
        'hw_monitor'=>'Monitor','hw_motherboard'=>'Motherboard','hw_power_supply'=>'Power Supply',
        'hw_video_card'=>'Video Card','hw_hard_disk'=>'Hard Disk / SSD','hw_cdrom'=>'CD-ROM Drive',
        'hw_lan_card'=>'LAN Card / WiFi','hw_keyboard_mouse'=>'Keyboard & Mouse',
        'hw_printer_scanner'=>'Printer / Scanner','hw_ups_avr'=>'UPS and AVR','hw_modem'=>'Modem',
    ];
    $sw_items = [
        'sw_defrag'=>'Defragmentation','sw_registry'=>'Registry Cleaning','sw_antivirus'=>'Antivirus Updates',
        'sw_os_patch'=>'OS Patching','sw_recycle_bin'=>'Empty Recycle Bin','sw_temp_files'=>'Temporary Files',
        'sw_installed'=>'Check Installed SW','sw_os_integrity'=>'Check OS Integrity',
        'sw_office'=>'Office Applications','sw_cloud_backup'=>'Cloud Backup Sync','sw_browser_cache'=>'Browser Cache',
    ];

    $page_title = 'Maintenance';
    require_once '../../includes/header.php';
    require_once '../../includes/sidebar.php';
}
?>

<?php if (!$is_ajax): ?>
<div class="p-4 md:p-8 flex-1 overflow-y-auto custom-scrollbar relative z-10 bg-slate-50 dark:bg-background-dark">

    <!-- Page Header -->
    <div class="mb-6 flex flex-col md:flex-row items-start md:items-end justify-between gap-4 no-print">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <span class="px-2 py-0.5 rounded text-[11px] font-bold tracking-wider uppercase bg-amber-500/10 text-amber-500 border border-amber-500/20">Preventive Maintenance</span>
            </div>
            <h2 class="text-2xl font-bold text-slate-900 dark:text-white">Maintenance History &amp; Checklist</h2>
            <p class="text-xs text-slate-400 mt-0.5">Manage and track system unit health, hardware audits, and PMS checklists across departments.</p>
        </div>
        <div class="no-print flex items-center gap-2 flex-wrap">
            <button onclick="toggleModal('addMaintenanceModal')" class="flex items-center gap-1.5 px-3.5 h-9 rounded-lg bg-primary hover:bg-primary-hover text-white text-xs font-bold transition-all shadow-sm">
                <span class="material-symbols-outlined text-[16px]">add</span> Schedule PM
            </button>
            <button onclick="exportPdfTable()" class="flex items-center gap-1.5 px-3 h-9 rounded-lg bg-slate-700 hover:bg-slate-800 text-white text-xs font-bold transition-all shadow-sm" title="Export selected records or table to PDF">
                <span class="material-symbols-outlined text-[16px]">picture_as_pdf</span> Export PDF
            </button>
            <button onclick="printSelected()" class="flex items-center gap-1.5 px-3 h-9 rounded-lg bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-[#232b3d] text-xs font-bold transition-all shadow-sm" title="Print Preventive Maintenance Checklist for selected records">
                <span class="material-symbols-outlined text-[16px]">print</span> Print Checklist
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
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Total Logs</span>
                <span class="material-symbols-outlined text-primary text-base">history</span>
            </div>
            <p class="text-2xl font-bold mt-1 text-slate-900 dark:text-white"><?= number_format($total_maint) ?></p>
            <span class="text-[10px] text-slate-400">Recorded PMS events</span>
        </div>
        <div class="bg-white dark:bg-[#161d2b] p-4 rounded-xl border border-slate-200 dark:border-[#232b3d] shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Completed</span>
                <span class="material-symbols-outlined text-emerald-500 text-base">check_circle</span>
            </div>
            <p class="text-2xl font-bold mt-1 text-emerald-500"><?= number_format($completed) ?></p>
            <span class="text-[10px] text-slate-400">Healthy / passed</span>
        </div>
        <div class="bg-white dark:bg-[#161d2b] p-4 rounded-xl border border-slate-200 dark:border-[#232b3d] shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Needs Action</span>
                <span class="material-symbols-outlined text-amber-500 text-base">warning</span>
            </div>
            <p class="text-2xl font-bold mt-1 text-amber-500"><?= number_format($needs_action) ?></p>
            <span class="text-[10px] text-slate-400">Requires attention</span>
        </div>
        <div class="bg-white dark:bg-[#161d2b] p-4 rounded-xl border border-slate-200 dark:border-[#232b3d] shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Critical Failure</span>
                <span class="material-symbols-outlined text-red-500 text-base">error</span>
            </div>
            <p class="text-2xl font-bold mt-1 text-red-500"><?= number_format($critical_fail) ?></p>
            <span class="text-[10px] text-slate-400">Defective units</span>
        </div>
        <div class="bg-white dark:bg-[#161d2b] p-4 rounded-xl border border-slate-200 dark:border-[#232b3d] shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Avg Health</span>
                <span class="material-symbols-outlined text-blue-500 text-base">speed</span>
            </div>
            <p class="text-2xl font-bold mt-1 text-blue-400"><?= $avg_score ?>%</p>
            <span class="text-[10px] text-slate-400">Fleet performance score</span>
        </div>
    </div>

    <!-- Filter / Control Bar -->
    <div class="bg-white dark:bg-[#161d2b] p-3 rounded-xl border border-slate-200 dark:border-[#232b3d] shadow-sm mb-4 no-print">
        <form method="GET" class="flex flex-wrap items-center gap-2">
            <?php if ($asset_id): ?><input type="hidden" name="asset_id" value="<?= $asset_id ?>"><?php endif; ?>
            <div class="relative flex-1 min-w-[200px]">
                <span class="material-symbols-outlined absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400 text-sm">search</span>
                <input type="text" name="search" id="searchInput" value="<?= htmlspecialchars($search) ?>" placeholder="Search Control #, Employee, Dept, Auditor..." class="w-full pl-9 pr-3 py-1.5 text-xs rounded-lg bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:border-primary" autocomplete="off">
            </div>
            <select name="status" class="px-2.5 py-1.5 text-xs rounded-lg bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-700 dark:text-slate-300 focus:outline-none focus:border-primary">
                <option value="All" <?= $status_f==='All'?'selected':'' ?>>All Status</option>
                <option value="Completed" <?= $status_f==='Completed'?'selected':'' ?>>Completed</option>
                <option value="Needs Action" <?= $status_f==='Needs Action'?'selected':'' ?>>Needs Action</option>
                <option value="Critical Failure" <?= $status_f==='Critical Failure'?'selected':'' ?>>Critical Failure</option>
            </select>
            <select name="sort" class="px-2.5 py-1.5 text-xs rounded-lg bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-700 dark:text-slate-300 focus:outline-none focus:border-primary">
                <option value="date_desc" <?= $sort_by==='date_desc'?'selected':'' ?>>Newest First</option>
                <option value="date_asc" <?= $sort_by==='date_asc'?'selected':'' ?>>Oldest First</option>
                <option value="score_desc" <?= $sort_by==='score_desc'?'selected':'' ?>>Score (High)</option>
                <option value="score_asc" <?= $sort_by==='score_asc'?'selected':'' ?>>Score (Low)</option>
            </select>
            <select name="limit" class="px-2.5 py-1.5 text-xs rounded-lg bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-700 dark:text-slate-300 focus:outline-none focus:border-primary">
                <option value="15" <?= $limit_p=='15'?'selected':'' ?>>15</option>
                <option value="50" <?= $limit_p=='50'?'selected':'' ?>>50</option>
                <option value="all" <?= $limit_p=='all'?'selected':'' ?>>All</option>
            </select>
            <button type="submit" class="px-3.5 py-1.5 text-xs font-bold rounded-lg bg-primary hover:bg-primary-hover text-white transition-all shadow-sm">
                Filter
            </button>
            <?php if ($search || $status_f !== 'All' || $sort_by !== 'date_desc' || $limit_p !== '15' || $asset_id): ?>
                <a href="index.php" class="px-2.5 py-1.5 text-xs font-medium text-red-500 hover:text-red-600 transition-colors">
                    Clear
                </a>
            <?php endif; ?>
            <?php if ($asset_id && $prefill_asset): ?>
                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 bg-blue-500/10 text-blue-500 text-[11px] font-bold rounded border border-blue-500/20">
                    <span class="material-symbols-outlined text-[13px]">filter_alt</span>
                    Asset: <?= htmlspecialchars($prefill_asset['control_id']??'Asset') ?>
                </span>
            <?php endif; ?>

            <?php if ($total_pages > 1): ?>
                <div class="ml-auto flex items-center gap-1.5 text-xs text-slate-500 dark:text-slate-400">
                    <span>Page <?= $page ?> of <?= $total_pages ?></span>
                    <a href="?page=<?= max(1, $page - 1) ?><?= $page_params ?>"
                        class="p-1 rounded bg-slate-100 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-600 dark:text-slate-300 <?= $page <= 1 ? 'pointer-events-none opacity-40' : 'hover:bg-slate-200 dark:hover:bg-[#232b3d]' ?>">
                        <span class="material-symbols-outlined text-sm block">chevron_left</span>
                    </a>
                    <a href="?page=<?= min($total_pages, $page + 1) ?><?= $page_params ?>"
                        class="p-1 rounded bg-slate-100 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-600 dark:text-slate-300 <?= $page >= $total_pages ? 'pointer-events-none opacity-40' : 'hover:bg-slate-200 dark:hover:bg-[#232b3d]' ?>">
                        <span class="material-symbols-outlined text-sm block">chevron_right</span>
                    </a>
                </div>
            <?php endif; ?>
        </form>
    </div>
<?php endif; ?>

    <!-- Maintenance History Table -->
    <div id="maintenance-table-container" class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] rounded-2xl overflow-hidden shadow-sm mb-8 transition-opacity duration-200">
        <div class="overflow-x-auto custom-scrollbar">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-slate-200 dark:border-[#232b3d] bg-white dark:bg-[#1a2130]">
                        <th class="no-print px-3 py-2 w-8 text-center"><input type="checkbox" onclick="toggleAll(this)" class="rounded h-3 w-3 text-primary"></th>
                        <th class="px-3 py-2 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">Date</th>
                        <th class="px-3 py-2 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">Asset / Control #</th>
                        <th class="px-3 py-2 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">Employee</th>
                        <th class="px-3 py-2 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">Department</th>
                        <th class="px-3 py-2 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">Status</th>
                        <th class="px-3 py-2 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">Health Score</th>
                        <th class="px-3 py-2 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">Checked By</th>
                        <th class="no-print px-3 py-2 text-[10px] font-bold text-slate-500 uppercase text-right whitespace-nowrap">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-[#232b3d]/50">
                    <?php if (empty($records)): ?>
                        <tr>
                            <td colspan="9" class="px-6 py-12 text-center text-slate-400 text-sm">
                                <div class="flex flex-col items-center justify-center gap-2">
                                    <span class="material-symbols-outlined text-4xl text-slate-300 dark:text-slate-600">build_circle</span>
                                    <p class="font-medium text-slate-700 dark:text-slate-200">No maintenance records found<?= $search ? ' matching "'.htmlspecialchars($search).'"' : '.' ?></p>
                                    <p class="text-xs text-slate-500 max-w-md"><?= $search ? 'Try adjusting your search terms or filters.' : 'Click <strong>+ Schedule PM</strong> to add one.' ?></p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($records as $r): ?>
                        <?php
                        $st = $r['status'] ?? 'Completed';
                        $sc = match($st) {
                            'Completed'       => 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-900/20 dark:text-emerald-400',
                            'Needs Action'    => 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-900/20 dark:text-amber-400',
                            'Critical Failure'=> 'bg-red-50 text-red-700 border-red-200 dark:bg-red-900/20 dark:text-red-400',
                            default           => 'bg-slate-100 text-slate-500 border-slate-200',
                        };
                        $score = (int)($r['performance_score'] ?? 100);
                        $bar_color = $score >= 80 ? 'bg-emerald-500' : ($score >= 50 ? 'bg-amber-500' : 'bg-red-500');
                        $score_text = $score >= 80 ? 'text-emerald-600' : ($score >= 50 ? 'text-amber-600' : 'text-red-500');
                        ?>
                        <tr class="hover:bg-slate-50 dark:hover:bg-white/5 transition-colors text-[11px]">
                            <td class="no-print px-3 py-2 text-center"><input type="checkbox" class="item-checkbox h-3 w-3 rounded text-primary" value="<?= $r['id'] ?>"></td>
                            <td class="px-3 py-2 font-mono text-slate-500 whitespace-nowrap"><?= htmlspecialchars($r['maintenance_date'] ?? '-') ?></td>
                            <td class="px-3 py-2 whitespace-nowrap">
                                <div class="font-bold text-primary"><?= htmlspecialchars($r['control_number'] ?: ($r['asset_control_id'] ?? '-')) ?></div>
                                <?php if ($r['asset_name']): ?><div class="text-[10px] text-slate-400"><?= htmlspecialchars($r['asset_name']) ?></div><?php endif; ?>
                            </td>
                            <td class="px-3 py-2 text-slate-700 dark:text-slate-300 whitespace-nowrap"><?= htmlspecialchars($r['employee_name'] ?? '-') ?></td>
                            <td class="px-3 py-2 text-slate-500 whitespace-nowrap"><?= htmlspecialchars($r['department'] ?? '-') ?></td>
                            <td class="px-3 py-2 whitespace-nowrap">
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold border <?= $sc ?>">
                                    <span class="w-1.5 h-1.5 rounded-full bg-current"></span><?= htmlspecialchars($st) ?>
                                </span>
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap">
                                <div class="flex items-center gap-2">
                                    <div class="w-16 h-1.5 bg-slate-200 dark:bg-slate-700 rounded-full overflow-hidden">
                                        <div class="<?= $bar_color ?> h-full rounded-full" style="width:<?= $score ?>%"></div>
                                    </div>
                                    <span class="text-[11px] font-bold <?= $score_text ?>"><?= $score ?>%</span>
                                </div>
                            </td>
                            <td class="px-3 py-2 text-slate-500 whitespace-nowrap"><?= htmlspecialchars($r['checked_by'] ?? '-') ?></td>
                            <td class="no-print px-3 py-2 text-right flex items-center justify-end gap-1">
                                <button onclick="printSingle(<?= $r['id'] ?>)" class="p-1 rounded-lg text-slate-400 hover:text-indigo-500 hover:bg-indigo-500/10 transition-colors" title="Print PM Checklist">
                                    <span class="material-symbols-outlined text-[15px]">print</span>
                                </button>
                                <button onclick='openViewModal(<?= htmlspecialchars(json_encode($r), ENT_QUOTES) ?>)' class="p-1 rounded-lg text-slate-400 hover:text-primary hover:bg-primary/10 transition-colors" title="View">
                                    <span class="material-symbols-outlined text-[15px]">visibility</span>
                                </button>
                                <button onclick='confirmDelete(<?= $r['id'] ?>)' class="p-1 rounded-lg text-slate-400 hover:text-red-500 hover:bg-red-500/10 transition-colors" title="Delete">
                                    <span class="material-symbols-outlined text-[15px]">delete</span>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3 border-t border-slate-200 dark:border-[#232b3d] flex items-center justify-between text-xs text-slate-400">
            <span>Showing <?= count($records) ?> of <?= number_format($total_items) ?> records</span>
            <?php if ($total_pages > 1): ?>
            <div class="flex gap-1">
                <a href="?page=<?= max(1,$page-1) ?><?= $page_params ?>" class="px-3 py-1 border border-slate-200 dark:border-[#232b3d] rounded hover:bg-slate-100 dark:hover:bg-[#232b3d] <?= $page<=1?'opacity-40 pointer-events-none':'' ?>">&#8249;</a>
                <span class="px-3 py-1 bg-primary text-white rounded"><?= $page ?></span>
                <a href="?page=<?= min($total_pages,$page+1) ?><?= $page_params ?>" class="px-3 py-1 border border-slate-200 dark:border-[#232b3d] rounded hover:bg-slate-100 dark:hover:bg-[#232b3d] <?= $page>=$total_pages?'opacity-40 pointer-events-none':'' ?>">&#8250;</a>
            </div>
            <?php endif; ?>
        </div>
    </div>
<?php if ($is_ajax) { exit; } ?>
</div>

<!-- ── Add / Schedule PM Modal ────────────────────────────────────────────────── -->
<div id="addMaintenanceModal" class="fixed inset-0 bg-black/60 z-50 hidden items-center justify-center p-4" onclick="if(event.target===this)toggleModal('addMaintenanceModal')">
    <div class="bg-white dark:bg-[#1a2130] rounded-2xl shadow-2xl w-full max-w-5xl max-h-[94vh] overflow-y-auto custom-scrollbar">
        <div class="p-5 border-b border-slate-200 dark:border-[#232b3d] flex items-center justify-between sticky top-0 bg-white dark:bg-[#1a2130] z-10">
            <h3 class="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
                <span class="material-symbols-outlined text-amber-500">build</span> Preventive Maintenance Checklist
            </h3>
            <button onclick="toggleModal('addMaintenanceModal')" class="text-slate-400 hover:text-white"><span class="material-symbols-outlined">close</span></button>
        </div>
        <form method="POST" class="p-5 space-y-6">
            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
            <input type="hidden" name="action" value="save_maintenance">

            <!-- Identity Card -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 border border-slate-200 dark:border-[#232b3d] rounded-xl p-4">
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Link to Asset</label>
                    <select name="asset_id" class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] rounded-xl px-3 py-2 text-sm text-slate-800 dark:text-white focus:ring-2 focus:ring-primary">
                        <option value="">— None / Manual —</option>
                        <?php foreach ($all_assets as $aa): ?>
                            <option value="<?= $aa['id'] ?>" <?= $asset_id==$aa['id']?'selected':'' ?>><?= htmlspecialchars($aa['control_id'].' — '.$aa['brand_model']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Department</label>
                    <input type="text" name="department" value="<?= htmlspecialchars($prefill_asset['department']??'') ?>"
                        class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] rounded-xl px-3 py-2 text-sm text-slate-800 dark:text-white focus:ring-2 focus:ring-primary">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">IHOMS Control #</label>
                    <input type="text" name="control_number" value="<?= htmlspecialchars($prefill_asset['control_id']??'') ?>"
                        class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] rounded-xl px-3 py-2 text-sm font-mono text-slate-800 dark:text-white focus:ring-2 focus:ring-primary">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Employee Name</label>
                    <input type="text" name="employee_name" value="<?= htmlspecialchars($prefill_asset['end_user']??'') ?>"
                        class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] rounded-xl px-3 py-2 text-sm text-slate-800 dark:text-white focus:ring-2 focus:ring-primary">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Processor</label>
                    <input type="text" name="processor" value="<?= htmlspecialchars($prefill_asset['processor']??'') ?>"
                        class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] rounded-xl px-3 py-2 text-sm text-slate-800 dark:text-white focus:ring-2 focus:ring-primary">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Memory</label>
                    <input type="text" name="memory" value="<?= htmlspecialchars($prefill_asset['memory']??'') ?>"
                        class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] rounded-xl px-3 py-2 text-sm text-slate-800 dark:text-white focus:ring-2 focus:ring-primary">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Maintenance Date</label>
                    <input type="date" name="maintenance_date" value="<?= date('Y-m-d') ?>"
                        class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] rounded-xl px-3 py-2 text-sm text-slate-800 dark:text-white focus:ring-2 focus:ring-primary">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Status</label>
                    <select name="status" class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] rounded-xl px-3 py-2 text-sm text-slate-800 dark:text-white focus:ring-2 focus:ring-primary">
                        <option value="Completed">Completed</option>
                        <option value="Needs Action">Needs Action</option>
                        <option value="Critical Failure">Critical Failure</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Performance Score (%)</label>
                    <input type="number" name="performance_score" value="100" min="0" max="100"
                        class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] rounded-xl px-3 py-2 text-sm text-slate-800 dark:text-white focus:ring-2 focus:ring-primary">
                </div>
            </div>

            <!-- Dual Checklist Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
                <!-- Hardware -->
                <div class="border border-slate-200 dark:border-[#232b3d] rounded-xl overflow-hidden">
                    <div class="bg-slate-800 dark:bg-slate-900 text-white px-4 py-2.5 flex items-center gap-2">
                        <span class="material-symbols-outlined text-[18px]">monitor</span>
                        <span class="text-sm font-bold">Hardware Maintenance</span>
                        <span class="ml-auto text-[10px] text-slate-400">Physical Inspection</span>
                    </div>
                    <table class="w-full text-[11px]">
                        <thead class="bg-slate-50 dark:bg-[#101622] border-b border-slate-200 dark:border-[#232b3d]">
                            <tr>
                                <th class="px-3 py-2 text-left text-slate-500 font-bold uppercase text-[10px] w-2/5">Component</th>
                                <th class="px-3 py-2 text-center text-slate-500 font-bold uppercase text-[10px] w-24">Status</th>
                                <th class="px-3 py-2 text-left text-slate-500 font-bold uppercase text-[10px]">Remarks</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-[#232b3d]/50">
                            <?php foreach ($hw_items as $key => $label): ?>
                            <tr class="hover:bg-slate-50 dark:hover:bg-white/5">
                                <td class="px-3 py-1.5 font-medium text-slate-700 dark:text-slate-300"><?= $label ?></td>
                                <td class="px-3 py-1.5">
                                    <select name="<?= $key ?>" class="w-full h-7 bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] rounded-lg text-[11px] focus:ring-primary">
                                        <option value="Pass">Pass</option>
                                        <option value="Fail">Fail</option>
                                        <option value="N/A">N/A</option>
                                    </select>
                                </td>
                                <td class="px-3 py-1.5">
                                    <input type="text" name="<?= $key ?>_remarks" placeholder="..."
                                        class="w-full bg-transparent border-none text-[11px] text-slate-600 dark:text-slate-400 focus:ring-0 p-0 placeholder-slate-300">
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Software -->
                <div class="border border-slate-200 dark:border-[#232b3d] rounded-xl overflow-hidden">
                    <div class="bg-slate-700 dark:bg-slate-900 text-white px-4 py-2.5 flex items-center gap-2">
                        <span class="material-symbols-outlined text-[18px]">terminal</span>
                        <span class="text-sm font-bold">Software Maintenance</span>
                        <span class="ml-auto text-[10px] text-slate-400">Optimization Logs</span>
                    </div>
                    <table class="w-full text-[11px]">
                        <thead class="bg-slate-50 dark:bg-[#101622] border-b border-slate-200 dark:border-[#232b3d]">
                            <tr>
                                <th class="px-3 py-2 text-left text-slate-500 font-bold uppercase text-[10px] w-2/5">Routine</th>
                                <th class="px-3 py-2 text-center text-slate-500 font-bold uppercase text-[10px] w-24">Status</th>
                                <th class="px-3 py-2 text-left text-slate-500 font-bold uppercase text-[10px]">Remarks</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-[#232b3d]/50">
                            <?php foreach ($sw_items as $key => $label): ?>
                            <tr class="hover:bg-slate-50 dark:hover:bg-white/5">
                                <td class="px-3 py-1.5 font-medium text-slate-700 dark:text-slate-300"><?= $label ?></td>
                                <td class="px-3 py-1.5">
                                    <select name="<?= $key ?>" class="w-full h-7 bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] rounded-lg text-[11px] focus:ring-primary">
                                        <option value="Pass">Pass</option>
                                        <option value="Fail">Fail</option>
                                        <option value="Pending">Pending</option>
                                        <option value="N/A">N/A</option>
                                    </select>
                                </td>
                                <td class="px-3 py-1.5">
                                    <input type="text" name="<?= $key ?>_remarks" placeholder="..."
                                        class="w-full bg-transparent border-none text-[11px] text-slate-600 dark:text-slate-400 focus:ring-0 p-0 placeholder-slate-300">
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Observations / Action / Recommendation -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1 flex items-center gap-1"><span class="material-symbols-outlined text-[14px] text-primary">visibility</span> Observations</label>
                    <textarea name="env_observations" rows="3" placeholder="Note room temperature, dust, ventilation issues..."
                        class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] rounded-xl px-3 py-2 text-sm text-slate-800 dark:text-white focus:ring-2 focus:ring-primary"></textarea>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1 flex items-center gap-1"><span class="material-symbols-outlined text-[14px] text-amber-500">bolt</span> Action Taken</label>
                    <textarea name="action_taken" rows="3" placeholder="Specific fixes applied or parts replaced..."
                        class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] rounded-xl px-3 py-2 text-sm text-slate-800 dark:text-white focus:ring-2 focus:ring-primary"></textarea>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1 flex items-center gap-1"><span class="material-symbols-outlined text-[14px] text-emerald-500">edit_note</span> Final Recommendation</label>
                    <textarea name="recommendation" rows="2" placeholder="Future upgrades or critical maintenance schedules..."
                        class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] rounded-xl px-3 py-2 text-sm text-slate-800 dark:text-white focus:ring-2 focus:ring-primary"></textarea>
                </div>
            </div>

            <!-- Verification Footer -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 border border-slate-200 dark:border-[#232b3d] rounded-xl p-4">
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Checked By</label>
                    <input type="text" name="checked_by" list="staff_list_maint"
                        class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] rounded-xl px-3 py-2 text-sm text-slate-800 dark:text-white focus:ring-2 focus:ring-primary">
                    <datalist id="staff_list_maint">
                        <?php foreach ($staff_users as $su): ?>
                            <option value="<?= htmlspecialchars($su) ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Verified By</label>
                    <input type="text" name="verified_by" list="staff_list_maint"
                        class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] rounded-xl px-3 py-2 text-sm text-slate-800 dark:text-white focus:ring-2 focus:ring-primary">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Audit Date</label>
                    <input type="date" name="audit_date" value="<?= date('Y-m-d') ?>"
                        class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] rounded-xl px-3 py-2 text-sm text-slate-800 dark:text-white focus:ring-2 focus:ring-primary">
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-2 border-t border-slate-200 dark:border-[#232b3d]">
                <button type="button" onclick="toggleModal('addMaintenanceModal')" class="px-5 py-2 rounded-xl text-sm font-bold text-slate-500 hover:bg-slate-100 dark:hover:bg-[#232b3d]">Cancel</button>
                <button type="submit" class="px-6 py-2 rounded-xl text-sm font-bold bg-primary text-white hover:bg-primary/90">Submit Audit</button>
            </div>
        </form>
    </div>
</div>

<!-- View Record Modal -->
<div id="viewModal" class="fixed inset-0 bg-black/60 z-50 hidden items-center justify-center p-4" onclick="if(event.target===this)toggleModal('viewModal')">
    <div class="bg-white dark:bg-[#1a2130] rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto custom-scrollbar">
        <div class="p-5 border-b border-slate-200 dark:border-[#232b3d] flex items-center justify-between">
            <h3 class="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2"><span class="material-symbols-outlined text-primary">description</span> Maintenance Record</h3>
            <button onclick="toggleModal('viewModal')" class="text-slate-400 hover:text-white"><span class="material-symbols-outlined">close</span></button>
        </div>
        <div id="viewModalContent" class="p-5 space-y-4 text-sm"></div>
    </div>
</div>

<!-- Delete Modal -->
<div id="deleteModal" class="fixed inset-0 bg-black/60 z-50 hidden items-center justify-center p-4">
    <div class="bg-white dark:bg-[#1a2130] rounded-2xl shadow-2xl w-full max-w-md p-6">
        <h3 class="text-base font-bold text-slate-900 dark:text-white mb-2">Delete Maintenance Record</h3>
        <p class="text-sm text-slate-500 mb-6">This action cannot be undone.</p>
        <form method="POST" class="flex justify-end gap-3">
            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
            <input type="hidden" name="action" value="delete_maintenance">
            <input type="hidden" name="id" id="deleteId">
            <button type="button" onclick="toggleModal('deleteModal')" class="px-5 py-2 rounded-xl text-sm font-bold text-slate-500 hover:bg-slate-100 dark:hover:bg-[#232b3d]">Cancel</button>
            <button type="submit" class="px-5 py-2 rounded-xl text-sm font-bold bg-red-500 text-white hover:bg-red-600">Delete</button>
        </form>
    </div>
</div>

<!-- Print Recommendation Modal -->
<div id="printChecklistModal" class="fixed inset-0 bg-black/60 z-50 hidden items-center justify-center p-4">
    <div class="bg-white dark:bg-[#1a2130] rounded-2xl shadow-2xl w-full max-w-lg p-6">
        <div class="flex items-center justify-between border-b border-slate-200 dark:border-[#232b3d] pb-3 mb-4">
            <h3 class="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
                <span class="material-symbols-outlined text-indigo-600">print</span> Print Maintenance Checklist
            </h3>
            <button type="button" onclick="toggleModal('printChecklistModal')" class="text-slate-400 hover:text-slate-600 dark:hover:text-white">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <p class="text-xs text-slate-500 mb-4">
            <span id="printModalCount" class="font-bold text-indigo-600 dark:text-indigo-400">1 record selected</span> &mdash; You can optionally input a custom recommendation to print on the checklist form.
        </p>

        <div class="mb-4">
            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Recommendation (Optional)</label>
            <textarea id="print_modal_recommendation" rows="3"
                placeholder="Type custom recommendation (e.g., Clean fans, apply thermal paste, keep OS and antivirus updated)..."
                class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] rounded-xl px-3 py-2 text-xs text-slate-800 dark:text-white focus:ring-2 focus:ring-primary"></textarea>
            
            <!-- Quick Chips -->
            <div class="mt-2 flex flex-wrap gap-1.5">
                <button type="button" onclick="setPrintRecomPreset('Recommended for regular scheduled maintenance and updates.')"
                    class="text-[10px] px-2 py-1 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 transition-colors">
                    + Regular Maintenance
                </button>
                <button type="button" onclick="setPrintRecomPreset('Clean internal dust, inspect fans, and re-apply thermal paste.')"
                    class="text-[10px] px-2 py-1 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 transition-colors">
                    + Fan & Thermal Paste
                </button>
                <button type="button" onclick="setPrintRecomPreset('Recommended RAM / Storage SSD upgrade for improved performance.')"
                    class="text-[10px] px-2 py-1 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 transition-colors">
                    + Hardware Upgrade
                </button>
                <button type="button" onclick="setPrintRecomPreset('')"
                    class="text-[10px] px-2 py-1 rounded-lg text-slate-400 hover:text-red-500 transition-colors">
                    Clear
                </button>
            </div>
        </div>

        <div class="flex justify-end gap-3 pt-3 border-t border-slate-200 dark:border-[#232b3d]">
            <button type="button" onclick="toggleModal('printChecklistModal')" class="px-5 py-2 rounded-xl text-xs font-bold text-slate-500 hover:bg-slate-100 dark:hover:bg-[#232b3d]">Cancel</button>
            <button type="button" onclick="executePrintChecklist()" class="px-5 py-2 rounded-xl text-xs font-bold bg-indigo-600 text-white hover:bg-indigo-700 flex items-center gap-1.5 shadow-sm">
                <span class="material-symbols-outlined text-[16px]">print</span> Proceed to Print
            </button>
        </div>
    </div>
</div>

<script>
function toggleModal(id){const e=document.getElementById(id);e.classList.toggle('hidden');e.classList.toggle('flex');}
function toggleAll(source){document.querySelectorAll('.item-checkbox').forEach(cb=>cb.checked=source.checked);}

let selectedPrintIds = [];

function printSelected(){
    const ids = [...document.querySelectorAll('.item-checkbox:checked')].map(c => c.value);
    if (ids.length === 0) {
        alert('Please select at least one maintenance record to print.');
        return;
    }
    selectedPrintIds = ids;
    document.getElementById('printModalCount').textContent = ids.length + (ids.length === 1 ? ' record selected' : ' records selected');
    toggleModal('printChecklistModal');
}

function printSingle(id){
    selectedPrintIds = [id];
    document.getElementById('printModalCount').textContent = '1 record selected';
    toggleModal('printChecklistModal');
}

function setPrintRecomPreset(text){
    document.getElementById('print_modal_recommendation').value = text;
}

function executePrintChecklist(){
    if (selectedPrintIds.length === 0) {
        alert('Please select at least one record to print.');
        return;
    }
    const recom = document.getElementById('print_modal_recommendation').value.trim();
    let url = 'print.php?ids=' + selectedPrintIds.join(',');
    if (recom) {
        url += '&recom=' + encodeURIComponent(recom);
    }
    window.open(url, '_blank');
    toggleModal('printChecklistModal');
}

function exportPdfTable(){
    const checkboxes = document.querySelectorAll('.item-checkbox:checked');
    const allRows = document.querySelectorAll('tbody tr');
    
    if (checkboxes.length === 0) {
        if (!confirm('No maintenance records selected. Do you want to export all visible records in the table to PDF?')) {
            return;
        }
        document.body.classList.remove('print-filtered');
        allRows.forEach(tr => tr.classList.remove('print-row-selected'));
    } else {
        document.body.classList.add('print-filtered');
        allRows.forEach(tr => {
            const cb = tr.querySelector('.item-checkbox');
            if (cb && cb.checked) {
                tr.classList.add('print-row-selected');
            } else {
                tr.classList.remove('print-row-selected');
            }
        });
    }

    setTimeout(() => {
        window.print();
    }, 300);
}

window.addEventListener('afterprint', () => {
    document.body.classList.remove('print-filtered');
    document.querySelectorAll('tbody tr').forEach(tr => tr.classList.remove('print-row-selected'));
});

function confirmDelete(id){document.getElementById('deleteId').value=id;toggleModal('deleteModal');}
function openViewModal(r){
    const c=document.getElementById('viewModalContent');
    const row=(label,val,cls='')=>`<div class="flex justify-between border-b border-slate-100 dark:border-[#232b3d] pb-2"><span class="text-slate-400 font-bold uppercase text-[10px]">${label}</span><span class="font-medium text-slate-700 dark:text-slate-300 ${cls}">${val||'-'}</span></div>`;
    const score=parseInt(r.performance_score||100);
    const sc=score>=80?'text-emerald-600':score>=50?'text-amber-600':'text-red-500';
    c.innerHTML=`
        <div class="grid grid-cols-2 gap-3">
            ${row('Date',r.maintenance_date)}${row('Status',r.status)}
            ${row('Control #',r.control_number)}${row('Employee',r.employee_name)}
            ${row('Department',r.department)}${row('Health Score',score+'%',sc)}
            ${row('Checked By',r.checked_by)}${row('Verified By',r.verified_by)}
        </div>
        ${r.action_taken?`<div class="mt-3"><p class="text-[10px] font-bold text-slate-400 uppercase mb-1">Action Taken</p><p class="text-sm text-slate-600 dark:text-slate-300">${r.action_taken}</p></div>`:''}
        ${r.recommendation?`<div class="mt-3"><p class="text-[10px] font-bold text-slate-400 uppercase mb-1">Recommendation</p><p class="text-sm text-slate-600 dark:text-slate-300">${r.recommendation}</p></div>`:''}
    `;
    toggleModal('viewModal');
}

// ── Live Search ─────────────────────────────────────────────────────────────
(function() {
    let searchTimeout = null;
    let searchController = null;
    const searchInput = document.getElementById('searchInput');
    if (!searchInput) return;

    const form = searchInput.form || searchInput.closest('form');

    function performSearch() {
        const container = document.getElementById('maintenance-table-container');
        if (container) container.classList.add('opacity-50');

        if (searchController) {
            searchController.abort();
        }
        searchController = new AbortController();

        const formData = form ? new FormData(form) : new FormData();
        if (!form) {
            formData.set('search', searchInput.value);
        }
        const params = new URLSearchParams(formData);
        params.set('page', '1');
        params.set('ajax', '1');

        fetch(window.location.pathname + '?' + params.toString(), {
            signal: searchController.signal
        })
        .then(response => {
            if (!response.ok) throw new Error('Network response was not ok');
            return response.text();
        })
        .then(html => {
            if (html.includes('<!DOCTYPE html>')) {
                console.warn('Full page received instead of partial.');
                return;
            }

            const currentContainer = document.getElementById('maintenance-table-container');
            if (currentContainer) {
                currentContainer.outerHTML = html;
            }

            params.delete('ajax');
            const newUrl = window.location.pathname + (params.toString() ? '?' + params.toString() : '');
            window.history.replaceState({}, '', newUrl);
        })
        .catch(err => {
            if (err.name !== 'AbortError') {
                console.error('Search error:', err);
                const currentContainer = document.getElementById('maintenance-table-container');
                if (currentContainer) currentContainer.classList.remove('opacity-50');
            }
        });
    }

    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(performSearch, 300);
    });

    searchInput.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            if (this.value !== '') {
                this.value = '';
                clearTimeout(searchTimeout);
                performSearch();
            }
        }
    });

    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            clearTimeout(searchTimeout);
            performSearch();
        });
    }
})();
</script>

<!-- Print Styles for Table Export -->
<style>
@media print {
    @page {
        size: landscape;
        margin: 8mm;
    }
    body {
        background: #fff !important;
        color: #000 !important;
        zoom: 80%;
    }
    aside, .sidebar, header .no-print, .no-print,
    form, .grid, .fixed, #printChecklistModal, #viewModal, #deleteModal,
    .border-b.sticky {
        display: none !important;
    }
    body.print-filtered tbody tr:not(.print-row-selected) {
        display: none !important;
    }
    th.no-print, td.no-print,
    th:first-child, td:first-child,
    th:last-child, td:last-child {
        display: none !important;
    }
    .overflow-x-auto, .overflow-hidden, .custom-scrollbar, .rounded-2xl {
        overflow: visible !important;
        height: auto !important;
        border-radius: 0 !important;
        box-shadow: none !important;
        border: none !important;
    }
    table {
        width: 100% !important;
        border-collapse: collapse !important;
    }
    th, td {
        border: 1px solid #cbd5e1 !important;
        color: #000 !important;
        padding: 5px 8px !important;
        font-size: 10px !important;
    }
    th {
        background: #f1f5f9 !important;
        font-weight: bold !important;
    }
}
</style>
<?php require_once '../../includes/footer.php'; ?>
