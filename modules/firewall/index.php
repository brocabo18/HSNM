<?php
require_once __DIR__ . '/../../config.php';
requireLogin();

// ── Handle Status Toggle (AJAX or regular POST) ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'toggle_status') {
        if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
            http_response_code(403); echo json_encode(['error' => 'Invalid token']); exit;
        }
        $ctrl      = trim($_POST['control_number'] ?? '');
        $new_status = ($_POST['status'] ?? 'No') === 'Yes' ? 'Yes' : 'No';
        $editor    = $_SESSION['username'] ?? '';

        try {
            $stmt = $pdo->prepare("
                INSERT INTO firewall_status (control_number, status, last_edited_by, updated_at)
                VALUES (?, ?, ?, NOW())
                ON CONFLICT (control_number) DO UPDATE
                    SET status = EXCLUDED.status,
                        last_edited_by = EXCLUDED.last_edited_by,
                        updated_at = NOW()
            ");
            $stmt->execute([$ctrl, $new_status, $editor]);
            logAudit($pdo, 'update_firewall_status', "Firewall status set to $new_status for Control #: $ctrl", 'firewall', null);
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                echo json_encode(['success' => true, 'status' => $new_status]);
                exit;
            }
        } catch (Exception $e) {
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                http_response_code(500); echo json_encode(['error' => $e->getMessage()]); exit;
            }
        }
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }
}

// ── CSV Export ────────────────────────────────────────────────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $search  = trim($_GET['search']  ?? '');
    if (strpos($search, ':') !== false) {
        $search = str_replace(':', '-', $search);
    }
    $loc_f   = trim($_GET['location'] ?? '');

    $where = ["c.firewall = 'Y'"];
    $params = [];
    if ($search !== '') {
        $where[]  = "(c.control_number ILIKE ? OR c.department ILIKE ? OR c.end_user ILIKE ? OR c.ip_address ILIKE ? OR c.mac_address ILIKE ?)";
        $params   = array_merge($params, ["%$search%", "%$search%", "%$search%", "%$search%", "%$search%"]);
    }
    if ($loc_f !== '') {
        $where[]  = "c.department ILIKE ?";
        $params[] = "%$loc_f%";
    }
    $sql = "SELECT c.control_number, c.department, c.ip_address, c.mac_address,
                   COALESCE(fs.status,'No') AS fw_status,
                   fs.last_edited_by, fs.updated_at
            FROM computers c
            LEFT JOIN firewall_status fs ON fs.control_number = c.control_number
            WHERE " . implode(' AND ', $where) . "
            ORDER BY c.department, c.control_number";
    $stmt = $pdo->prepare($sql); $stmt->execute($params);
    $rows = $stmt->fetchAll();

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="firewall_' . date('Y-m-d_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Control Number', 'Location', 'IP Address', 'MAC Address', 'Firewall Updated', 'Last Edited By', 'Last Updated At']);
    foreach ($rows as $row) {
        fputcsv($out, [
            $row['control_number'],
            $row['department'],
            $row['ip_address'],
            $row['mac_address'],
            $row['fw_status'],
            $row['last_edited_by'] ?? '',
            $row['updated_at'] ? date('Y-m-d H:i', strtotime($row['updated_at'])) : '',
        ]);
    }
    fclose($out);
    exit;
}

// ── Params ────────────────────────────────────────────────────────────────────
$search    = trim($_GET['search']   ?? '');
if (strpos($search, ':') !== false) {
    $search = str_replace(':', '-', $search);
}
$loc_f     = trim($_GET['location'] ?? '');
$status_f  = trim($_GET['fw_status'] ?? '');
$sort_fw   = trim($_GET['sort'] ?? 'dept_asc');
$page      = max(1, (int)($_GET['page'] ?? 1));
$limit_param = $_GET['limit'] ?? '50';
$per_page  = ($limit_param === 'all') ? 999999 : (int)$limit_param;
$offset    = ($page - 1) * $per_page;

// ── Query ─────────────────────────────────────────────────────────────────────
$where  = ["c.firewall = 'Y'"];
$params = [];
if ($search !== '') {
    $where[]  = "(c.control_number ILIKE ? OR c.department ILIKE ? OR c.end_user ILIKE ? OR c.ip_address ILIKE ? OR c.mac_address ILIKE ?)";
    $params   = array_merge($params, ["%$search%", "%$search%", "%$search%", "%$search%", "%$search%"]);
}
if ($loc_f !== '') {
    $where[]  = "c.department ILIKE ?";
    $params[] = "%$loc_f%";
}
if ($status_f !== '') {
    $where[]  = "COALESCE(fs.status,'No') = ?";
    $params[] = $status_f;
}
$where_sql = 'WHERE ' . implode(' AND ', $where);

// Count
$cnt_stmt = $pdo->prepare("SELECT COUNT(*) FROM computers c LEFT JOIN firewall_status fs ON fs.control_number = c.control_number $where_sql");
$cnt_stmt->execute($params);
$total_records = (int)$cnt_stmt->fetchColumn();
$total_pages   = max(1, (int)ceil($total_records / $per_page));

// Order
$orderBy = "c.department ASC, c.control_number ASC";
if ($sort_fw === 'dept_desc') $orderBy = "c.department DESC, c.control_number ASC";
elseif ($sort_fw === 'ctrl_asc') $orderBy = "c.control_number ASC";
elseif ($sort_fw === 'ctrl_desc') $orderBy = "c.control_number DESC";

// Rows
$sql = "SELECT c.id, c.control_number, c.department, c.end_user, c.ip_address, c.mac_address,
               COALESCE(fs.status,'No') AS fw_status,
               fs.last_edited_by, fs.updated_at AS fw_updated_at
        FROM computers c
        LEFT JOIN firewall_status fs ON fs.control_number = c.control_number
        $where_sql
        ORDER BY $orderBy
        LIMIT $per_page OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$items = $stmt->fetchAll();

// Distinct locations for filter
$loc_stmt = $pdo->query("SELECT DISTINCT department FROM computers WHERE firewall = 'Y' AND department IS NOT NULL AND department != '' ORDER BY department");
$locations = $loc_stmt->fetchAll(PDO::FETCH_COLUMN);

// Summary counts
$total_fw = (int)$pdo->query("SELECT COUNT(*) FROM computers WHERE firewall = 'Y'")->fetchColumn();
$yes_count = (int)$pdo->query("SELECT COUNT(*) FROM computers c JOIN firewall_status fs ON fs.control_number = c.control_number WHERE c.firewall = 'Y' AND fs.status = 'Yes'")->fetchColumn();
$no_count = (int)$pdo->query("SELECT COUNT(*) FROM computers c LEFT JOIN firewall_status fs ON fs.control_number = c.control_number WHERE c.firewall = 'Y' AND (fs.status = 'No' OR fs.status IS NULL)")->fetchColumn();
$total_locs = (int)$pdo->query("SELECT COUNT(DISTINCT department) FROM computers WHERE firewall = 'Y' AND department IS NOT NULL AND department != ''")->fetchColumn();
$total_with_ip = (int)$pdo->query("SELECT COUNT(*) FROM computers WHERE firewall = 'Y' AND ip_address IS NOT NULL AND ip_address != ''")->fetchColumn();

$csrf = generateCsrfToken();
$page_title = 'Firewall Inventory';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="p-4 md:p-8 flex-1 overflow-y-auto custom-scrollbar relative z-10 bg-slate-50 dark:bg-background-dark">

    <!-- Page Header -->
    <div class="mb-6 flex flex-col md:flex-row items-start md:items-end justify-between gap-4 no-print">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <span class="px-2 py-0.5 rounded text-[11px] font-bold tracking-wider uppercase bg-amber-500/10 text-amber-500 border border-amber-500/20">Security Inventory</span>
            </div>
            <h2 class="text-2xl font-bold text-slate-900 dark:text-white">Firewall Inventory</h2>
            <p class="text-xs text-slate-400 mt-0.5">Track computers with firewall enabled and monitor endpoint update compliance.</p>
        </div>
        <div class="no-print flex items-center gap-2 flex-wrap">
            <a href="?<?= http_build_query(array_merge($_GET, ['export'=>'csv'])) ?>"
               class="flex items-center gap-1.5 px-3 h-9 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold transition-all shadow-sm">
                <span class="material-symbols-outlined text-[16px]">download</span> Export CSV
            </a>
            <button onclick="window.print()"
                    class="flex items-center gap-1.5 px-3 h-9 rounded-lg bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-[#232b3d] text-xs font-bold transition-all shadow-sm">
                <span class="material-symbols-outlined text-[16px]">print</span> Print
            </button>
        </div>
    </div>

    <!-- Stats Cards Grid -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 mb-6 no-print">
        <div class="bg-white dark:bg-[#161d2b] p-4 rounded-xl border border-slate-200 dark:border-[#232b3d] shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Total Firewall</span>
                <span class="material-symbols-outlined text-primary text-base">security</span>
            </div>
            <p class="text-2xl font-bold mt-1 text-slate-900 dark:text-white"><?= number_format($total_fw) ?></p>
            <span class="text-[10px] text-slate-400">Firewall enabled units</span>
        </div>
        <div class="bg-white dark:bg-[#161d2b] p-4 rounded-xl border border-slate-200 dark:border-[#232b3d] shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Updated (Yes)</span>
                <span class="material-symbols-outlined text-emerald-500 text-base">verified_user</span>
            </div>
            <p class="text-2xl font-bold mt-1 text-emerald-500"><?= number_format($yes_count) ?></p>
            <span class="text-[10px] text-slate-400">Compliant & verified</span>
        </div>
        <div class="bg-white dark:bg-[#161d2b] p-4 rounded-xl border border-slate-200 dark:border-[#232b3d] shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Pending (No)</span>
                <span class="material-symbols-outlined text-amber-500 text-base">gpp_maybe</span>
            </div>
            <p class="text-2xl font-bold mt-1 text-amber-500"><?= number_format($no_count) ?></p>
            <span class="text-[10px] text-slate-400">Requires update</span>
        </div>
        <div class="bg-white dark:bg-[#161d2b] p-4 rounded-xl border border-slate-200 dark:border-[#232b3d] shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Locations</span>
                <span class="material-symbols-outlined text-purple-500 text-base">location_on</span>
            </div>
            <p class="text-2xl font-bold mt-1 text-purple-400"><?= number_format($total_locs) ?></p>
            <span class="text-[10px] text-slate-400">Departments covered</span>
        </div>
        <div class="bg-white dark:bg-[#161d2b] p-4 rounded-xl border border-slate-200 dark:border-[#232b3d] shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">IP Assigned</span>
                <span class="material-symbols-outlined text-blue-500 text-base">lan</span>
            </div>
            <p class="text-2xl font-bold mt-1 text-blue-400"><?= number_format($total_with_ip) ?></p>
            <span class="text-[10px] text-slate-400">Network mapped</span>
        </div>
    </div>

    <!-- Filter / Control Bar -->
    <div class="bg-white dark:bg-[#161d2b] p-3 rounded-xl border border-slate-200 dark:border-[#232b3d] shadow-sm mb-4 no-print">
        <form method="GET" class="flex flex-wrap items-center gap-2">
            <div class="relative flex-1 min-w-[200px]">
                <span class="material-symbols-outlined absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400 text-sm">search</span>
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search control #, location, user, IP, MAC…" class="w-full pl-9 pr-3 py-1.5 text-xs rounded-lg bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:border-primary">
            </div>
            <select name="location" class="px-2.5 py-1.5 text-xs rounded-lg bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-700 dark:text-slate-300 focus:outline-none focus:border-primary">
                <option value="">All Locations</option>
                <?php foreach ($locations as $loc): ?>
                    <option value="<?= htmlspecialchars($loc) ?>" <?= $loc_f === $loc ? 'selected' : '' ?>><?= htmlspecialchars($loc) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="fw_status" class="px-2.5 py-1.5 text-xs rounded-lg bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-700 dark:text-slate-300 focus:outline-none focus:border-primary">
                <option value="">All Status</option>
                <option value="Yes" <?= $status_f === 'Yes' ? 'selected' : '' ?>>Updated: Yes</option>
                <option value="No" <?= $status_f === 'No' ? 'selected' : '' ?>>Updated: No</option>
            </select>
            <select name="sort" class="px-2.5 py-1.5 text-xs rounded-lg bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-700 dark:text-slate-300 focus:outline-none focus:border-primary">
                <option value="dept_asc" <?= $sort_fw === 'dept_asc' ? 'selected' : '' ?>>Location (A-Z)</option>
                <option value="dept_desc" <?= $sort_fw === 'dept_desc' ? 'selected' : '' ?>>Location (Z-A)</option>
                <option value="ctrl_asc" <?= $sort_fw === 'ctrl_asc' ? 'selected' : '' ?>>Control # (A-Z)</option>
                <option value="ctrl_desc" <?= $sort_fw === 'ctrl_desc' ? 'selected' : '' ?>>Control # (Z-A)</option>
            </select>
            <select name="limit" class="px-2.5 py-1.5 text-xs rounded-lg bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-700 dark:text-slate-300 focus:outline-none focus:border-primary">
                <option value="25" <?= $limit_param === '25' ? 'selected' : '' ?>>25</option>
                <option value="50" <?= $limit_param === '50' ? 'selected' : '' ?>>50</option>
                <option value="100" <?= $limit_param === '100' ? 'selected' : '' ?>>100</option>
                <option value="all" <?= $limit_param === 'all' ? 'selected' : '' ?>>All</option>
            </select>
            <button type="submit" class="px-3.5 py-1.5 text-xs font-bold rounded-lg bg-primary hover:bg-primary-hover text-white transition-all shadow-sm">
                Filter
            </button>
            <?php if ($search !== '' || $loc_f !== '' || $status_f !== '' || $sort_fw !== 'dept_asc' || $limit_param !== '50'): ?>
                <a href="index.php" class="px-2.5 py-1.5 text-xs font-medium text-red-500 hover:text-red-600 transition-colors">
                    Clear
                </a>
            <?php endif; ?>

            <?php if ($total_pages > 1): ?>
                <div class="ml-auto flex items-center gap-1.5 text-xs text-slate-500 dark:text-slate-400">
                    <span>Page <?= $page ?> of <?= $total_pages ?></span>
                    <a href="?page=<?= max(1, $page - 1) ?>&search=<?= urlencode($search) ?>&location=<?= urlencode($loc_f) ?>&fw_status=<?= urlencode($status_f) ?>&sort=<?= urlencode($sort_fw) ?>&limit=<?= urlencode($limit_param) ?>"
                        class="p-1 rounded bg-slate-100 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-600 dark:text-slate-300 <?= $page <= 1 ? 'pointer-events-none opacity-40' : 'hover:bg-slate-200 dark:hover:bg-[#232b3d]' ?>">
                        <span class="material-symbols-outlined text-sm block">chevron_left</span>
                    </a>
                    <a href="?page=<?= min($total_pages, $page + 1) ?>&search=<?= urlencode($search) ?>&location=<?= urlencode($loc_f) ?>&fw_status=<?= urlencode($status_f) ?>&sort=<?= urlencode($sort_fw) ?>&limit=<?= urlencode($limit_param) ?>"
                        class="p-1 rounded bg-slate-100 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-600 dark:text-slate-300 <?= $page >= $total_pages ? 'pointer-events-none opacity-40' : 'hover:bg-slate-200 dark:hover:bg-[#232b3d]' ?>">
                        <span class="material-symbols-outlined text-sm block">chevron_right</span>
                    </a>
                </div>
            <?php endif; ?>
        </form>
    </div>


    <!-- Table -->
    <div class="flex-1 overflow-auto px-6 pb-6">
        <div class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] rounded-2xl overflow-hidden shadow-sm">
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr class="bg-slate-50 dark:bg-[#141b28] border-b border-slate-200 dark:border-[#232b3d]">
                        <th class="px-4 py-3 text-[10px] font-bold text-slate-500 uppercase tracking-wider w-10">#</th>
                        <th class="px-4 py-3 text-[10px] font-bold text-slate-500 uppercase tracking-wider">Control #</th>
                        <th class="px-4 py-3 text-[10px] font-bold text-slate-500 uppercase tracking-wider">Location</th>
                        <th class="px-4 py-3 text-[10px] font-bold text-slate-500 uppercase tracking-wider">End User</th>
                        <th class="px-4 py-3 text-[10px] font-bold text-slate-500 uppercase tracking-wider">IP Address</th>
                        <th class="px-4 py-3 text-[10px] font-bold text-slate-500 uppercase tracking-wider">MAC Address</th>
                        <th class="px-4 py-3 text-[10px] font-bold text-slate-500 uppercase tracking-wider text-center">Updated?</th>
                        <th class="px-4 py-3 text-[10px] font-bold text-slate-500 uppercase tracking-wider no-print">Last Edited By</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-[#232b3d]/60">
                    <?php if (empty($items)): ?>
                    <tr>
                        <td colspan="8" class="px-4 py-16 text-center text-slate-500">
                            <span class="material-symbols-outlined text-4xl mb-3 block opacity-30">security</span>
                            <p class="text-sm font-medium">No firewall records found.</p>
                            <p class="text-xs mt-1">Set Firewall = Yes on computers in the Computer Inventory module.</p>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($items as $idx => $item): ?>
                    <?php
                        $is_yes      = $item['fw_status'] === 'Yes';
                        $toggle_next = $is_yes ? 'No' : 'Yes';
                        $ctrl        = htmlspecialchars($item['control_number']);
                        $loc         = htmlspecialchars($item['department'] ?? '—');
                        $user        = htmlspecialchars($item['end_user'] ?? '—');
                        $ip          = htmlspecialchars($item['ip_address'] ?? '—');
                        $mac         = htmlspecialchars($item['mac_address'] ?? '—');
                        $editor      = htmlspecialchars($item['last_edited_by'] ?? '');
                        $fw_at       = $item['fw_updated_at'] ? date('M j, Y g:i A', strtotime($item['fw_updated_at'])) : '';
                    ?>
                    <tr class="hover:bg-slate-50 dark:hover:bg-[#1e2738] transition-colors group" id="row-<?= htmlspecialchars($item['control_number']) ?>">
                        <td class="px-4 py-3 text-xs text-slate-400 font-mono"><?= $offset + $idx + 1 ?></td>
                        <td class="px-4 py-3">
                            <span class="bg-blue-500/10 text-blue-600 dark:text-blue-400 text-xs font-bold px-2 py-0.5 rounded font-mono">
                                <?= $ctrl ?>
                            </span>
                        </td>
                        <td class="px-4 py-3 text-xs text-slate-600 dark:text-slate-300">
                            <span class="flex items-center gap-1">
                                <span class="material-symbols-outlined text-[13px] text-slate-400">location_on</span>
                                <?= $loc ?>
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm text-slate-700 dark:text-slate-200"><?= $user ?></td>
                        <td class="px-4 py-3 font-mono text-xs text-slate-600 dark:text-slate-400"><?= $ip ?></td>
                        <td class="px-4 py-3 font-mono text-[11px] text-slate-500"><?= $mac ?></td>
                        <td class="px-4 py-3 text-center">
                            <!-- Toggle button -->
                            <form method="POST" class="inline no-print"
                                  onsubmit="return toggleStatus(event, this)">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                                <input type="hidden" name="action" value="toggle_status">
                                <input type="hidden" name="control_number" value="<?= htmlspecialchars($item['control_number']) ?>">
                                <input type="hidden" name="status" value="<?= htmlspecialchars($toggle_next) ?>">
                                <button type="submit"
                                        class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-bold transition-all
                                               <?= $is_yes
                                                   ? 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300 hover:bg-emerald-200'
                                                   : 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300 hover:bg-amber-200' ?>"
                                        id="btn-<?= htmlspecialchars($item['control_number']) ?>"
                                        title="Click to toggle">
                                    <span class="material-symbols-outlined text-[14px]">
                                        <?= $is_yes ? 'check_circle' : 'pending' ?>
                                    </span>
                                    <?= $is_yes ? 'Yes' : 'No' ?>
                                </button>
                            </form>
                            <!-- Print view (no button) -->
                            <span class="hidden print:inline font-bold <?= $is_yes ? 'text-emerald-600' : 'text-amber-600' ?>">
                                <?= $is_yes ? 'Yes' : 'No' ?>
                            </span>
                        </td>
                        <td class="px-4 py-3 no-print">
                            <?php if ($editor): ?>
                            <div class="flex flex-col">
                                <span class="text-xs font-medium text-slate-700 dark:text-slate-200"><?= $editor ?></span>
                                <?php if ($fw_at): ?>
                                <span class="text-[10px] text-slate-400 font-mono"><?= $fw_at ?></span>
                                <?php endif; ?>
                            </div>
                            <?php else: ?>
                            <span class="text-xs text-slate-400 italic">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="flex items-center justify-between mt-4 no-print">
            <span class="text-xs text-slate-500">
                Showing <?= number_format($offset + 1) ?>–<?= number_format(min($offset + $per_page, $total_records)) ?>
                of <?= number_format($total_records) ?> records
            </span>
            <div class="flex gap-1">
                <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $p])) ?>"
                   class="px-3 py-1 text-xs rounded-lg font-bold transition-colors
                          <?= $p === $page
                              ? 'bg-blue-600 text-white'
                              : 'bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] text-slate-600 dark:text-slate-300 hover:bg-slate-50' ?>">
                    <?= $p ?>
                </a>
                <?php endfor; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Toast notification -->
<div id="fw-toast"
     class="fixed bottom-6 right-6 z-50 hidden items-center gap-3 px-5 py-3 rounded-2xl shadow-2xl text-white text-sm font-bold
            bg-slate-800 dark:bg-[#1a2130] border border-slate-700 transition-all duration-300">
    <span class="material-symbols-outlined text-[20px]" id="fw-toast-icon">check_circle</span>
    <span id="fw-toast-msg">Status updated.</span>
</div>

<script>
const csrf = <?= json_encode($csrf) ?>;

async function toggleStatus(e, form) {
    e.preventDefault();
    const ctrl   = form.querySelector('[name="control_number"]').value;
    const next   = form.querySelector('[name="status"]').value;
    const btn    = document.getElementById('btn-' + ctrl);

    try {
        const fd = new FormData(form);
        const res = await fetch(window.location.pathname, {
            method: 'POST',
            body: fd,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await res.json();
        if (data.success) {
            const isYes = data.status === 'Yes';
            // Update button
            btn.className = 'inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-bold transition-all ' +
                (isYes
                    ? 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300 hover:bg-emerald-200'
                    : 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300 hover:bg-amber-200');
            btn.innerHTML = '<span class="material-symbols-outlined text-[14px]">' +
                (isYes ? 'check_circle' : 'pending') + '</span> ' + data.status;
            // Flip hidden next-value input
            form.querySelector('[name="status"]').value = isYes ? 'No' : 'Yes';
            showToast(isYes ? 'check_circle' : 'pending',
                      'Firewall status set to ' + data.status + ' for ' + ctrl,
                      isYes ? '#10b981' : '#f59e0b');
        }
    } catch (err) {
        showToast('error', 'Update failed. Please try again.', '#ef4444');
    }
    return false;
}

function showToast(icon, msg, color) {
    const t   = document.getElementById('fw-toast');
    const ico = document.getElementById('fw-toast-icon');
    const txt = document.getElementById('fw-toast-msg');
    ico.textContent  = icon;
    txt.textContent  = msg;
    t.style.color    = color || '';
    t.classList.remove('hidden');
    t.classList.add('flex');
    clearTimeout(window._fwToast);
    window._fwToast = setTimeout(() => {
        t.classList.add('hidden');
        t.classList.remove('flex');
    }, 3000);
}
</script>

<style>
@media print {
    .no-print { display: none !important; }
    .print\:inline { display: inline !important; }
}
</style>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
