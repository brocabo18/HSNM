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
$loc_f     = trim($_GET['location'] ?? '');
$status_f  = trim($_GET['fw_status'] ?? '');
$page      = max(1, (int)($_GET['page'] ?? 1));
$per_page  = 25;
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
$cnt_stmt = $pdo->prepare("SELECT COUNT(*) FROM computers c $where_sql");
$cnt_stmt->execute($params);
$total_records = (int)$cnt_stmt->fetchColumn();
$total_pages   = max(1, (int)ceil($total_records / $per_page));

// Rows
$sql = "SELECT c.id, c.control_number, c.department, c.end_user, c.ip_address, c.mac_address,
               COALESCE(fs.status,'No') AS fw_status,
               fs.last_edited_by, fs.updated_at AS fw_updated_at
        FROM computers c
        LEFT JOIN firewall_status fs ON fs.control_number = c.control_number
        $where_sql
        ORDER BY c.department, c.control_number
        LIMIT $per_page OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$items = $stmt->fetchAll();

// Distinct locations for filter
$loc_stmt = $pdo->query("SELECT DISTINCT department FROM computers WHERE firewall = 'Y' AND department IS NOT NULL AND department != '' ORDER BY department");
$locations = $loc_stmt->fetchAll(PDO::FETCH_COLUMN);

// Summary counts
$yes_count = 0; $no_count = 0;
foreach ($items as $r) { $r['fw_status'] === 'Yes' ? $yes_count++ : $no_count++; }

$csrf = generateCsrfToken();
$page_title = 'Firewall Inventory';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="p-4 md:p-8 flex-1 overflow-y-auto custom-scrollbar relative z-10 bg-slate-50 dark:bg-background-dark">

    <!-- Header -->
    <header class="-mt-4 -mx-4 md:-mt-8 md:-mx-8 px-4 md:px-8 pt-4 md:pt-8 pb-6 mb-8 flex flex-col md:flex-row items-start md:items-end justify-between gap-4 sticky top-0 bg-slate-50 dark:bg-background-dark z-20 border-b border-slate-200 dark:border-[#232b3d]">
        <div>
            <h2 class="text-2xl font-bold text-slate-900 dark:text-white">Firewall Inventory</h2>
            <p class="text-sm text-slate-500 mt-1">Computers with Firewall enabled — update status tracker.</p>
        </div>
        <div class="no-print flex items-center gap-2 md:gap-3 flex-wrap">
            <a href="?<?= http_build_query(array_merge($_GET, ['export'=>'csv'])) ?>"
               class="flex items-center justify-center rounded-lg h-9 px-4 bg-emerald-600 text-white hover:bg-emerald-700 transition-colors text-xs font-bold">
                <span class="material-symbols-outlined text-[18px] mr-2">download</span> Export CSV
            </a>
            <button onclick="window.print()"
                    class="flex items-center justify-center rounded-lg h-9 px-4 bg-slate-600 text-white hover:bg-slate-700 transition-colors text-xs font-bold no-print">
                <span class="material-symbols-outlined text-[18px] mr-2">print</span> Print
            </button>
        </div>
    </header>

    <!-- Stat Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
        <div class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] p-4 rounded-xl">
            <div class="text-slate-500 text-xs font-bold uppercase tracking-wider mb-1">Total Firewall</div>
            <div class="text-2xl font-bold text-slate-900 dark:text-white"><?= number_format($total_records) ?></div>
        </div>
        <div class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] p-4 rounded-xl">
            <div class="text-slate-500 text-xs font-bold uppercase tracking-wider mb-1">Updated (Yes)</div>
            <div class="text-2xl font-bold text-emerald-500"><?= number_format($yes_count) ?></div>
        </div>
        <div class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] p-4 rounded-xl">
            <div class="text-slate-500 text-xs font-bold uppercase tracking-wider mb-1">Not Updated (No)</div>
            <div class="text-2xl font-bold text-amber-500"><?= number_format($no_count) ?></div>
        </div>
    </div>

    <!-- Filter + Sort Bar -->
    <div class="no-print mb-6">
        <form method="GET" class="flex flex-col gap-2">
            <!-- Sort + Pagination row -->
            <div class="flex items-center gap-2 flex-wrap justify-end">
                <select name="sort" onchange="this.form.submit()"
                    class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] text-slate-400 text-xs rounded-xl px-4 py-2 focus:ring-2 focus:ring-primary">
                    <option value="dept_asc" <?= ($sort_fw??'dept_asc')==='dept_asc'?'selected':'' ?>>Location (A-Z)</option>
                    <option value="dept_desc" <?= ($sort_fw??'')==='dept_desc'?'selected':'' ?>>Location (Z-A)</option>
                    <option value="ctrl_asc"  <?= ($sort_fw??'')==='ctrl_asc' ?'selected':'' ?>>Control # (A-Z)</option>
                    <option value="ctrl_desc" <?= ($sort_fw??'')==='ctrl_desc'?'selected':'' ?>>Control # (Z-A)</option>
                </select>
                <?php if ($total_pages > 1): ?>
                <span class="text-xs text-slate-500">Page <?= $page ?> of <?= $total_pages ?></span>
                <div class="flex h-9 bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] rounded-xl overflow-hidden">
                    <a href="?page=<?= max(1,$page-1) ?>&search=<?= urlencode($search) ?>&location=<?= urlencode($loc_f) ?>&fw_status=<?= urlencode($status_f) ?>&sort=<?= urlencode($_GET['sort']??'dept_asc') ?>&limit=<?= urlencode($_GET['limit']??'50') ?>"
                       class="px-3 flex items-center justify-center hover:bg-white/5 text-slate-400 hover:text-slate-900 dark:hover:text-white transition-colors border-r border-slate-200 dark:border-[#232b3d] <?= $page<=1?'pointer-events-none opacity-50':'' ?>">
                        <span class="material-symbols-outlined text-[18px]">chevron_left</span>
                    </a>
                    <a href="?page=<?= min($total_pages,$page+1) ?>&search=<?= urlencode($search) ?>&location=<?= urlencode($loc_f) ?>&fw_status=<?= urlencode($status_f) ?>&sort=<?= urlencode($_GET['sort']??'dept_asc') ?>&limit=<?= urlencode($_GET['limit']??'50') ?>"
                       class="px-3 flex items-center justify-center hover:bg-white/5 text-slate-400 hover:text-slate-900 dark:hover:text-white transition-colors <?= $page>=$total_pages?'pointer-events-none opacity-50':'' ?>">
                        <span class="material-symbols-outlined text-[18px]">chevron_right</span>
                    </a>
                </div>
                <?php endif; ?>
                <select name="limit" onchange="this.form.submit()"
                    class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] text-slate-400 text-xs rounded-xl px-4 py-2 focus:ring-2 focus:ring-primary">
                    <option value="25"  <?= ($_GET['limit']??'50')==='25' ?'selected':'' ?>>Show: 25</option>
                    <option value="50"  <?= ($_GET['limit']??'50')==='50' ?'selected':'' ?>>Show: 50</option>
                    <option value="100" <?= ($_GET['limit']??'50')==='100'?'selected':'' ?>>Show: 100</option>
                    <option value="all" <?= ($_GET['limit']??'50')==='all'?'selected':'' ?>>Show: All</option>
                </select>
            </div>

            <!-- Search + Filter row -->
            <div class="flex items-center gap-2 flex-wrap">
                <div class="relative">
                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-[18px]">search</span>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                           placeholder="Search control #, location, user, IP, MAC…"
                           class="w-64 pl-9 pr-4 py-2 text-xs bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl focus:ring-2 focus:ring-primary outline-none">
                </div>

                <!-- Hidden inputs -->
                <input type="hidden" name="location"  id="hid_fw_location"  value="<?= htmlspecialchars($loc_f) ?>">
                <input type="hidden" name="fw_status" id="hid_fw_status"    value="<?= htmlspecialchars($status_f) ?>">

                <?php
                $fw_ac = ''; $fw_av = '';
                if ($loc_f !== '')        { $fw_ac = 'location';  $fw_av = $loc_f; }
                elseif ($status_f !== '') { $fw_ac = 'fw_status'; $fw_av = $status_f; }
                ?>

                <select id="fw_cat" onchange="fwOnCat(this.value)"
                        class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] text-slate-600 dark:text-slate-300 text-xs rounded-xl px-4 py-2 focus:ring-2 focus:ring-primary">
                    <option value="">Filter by...</option>
                    <option value="location"  <?= $fw_ac==='location' ?'selected':'' ?>>Location</option>
                    <option value="fw_status" <?= $fw_ac==='fw_status'?'selected':'' ?>>Status</option>
                </select>

                <select id="fw_val" onchange="fwApply(this.value)"
                        class="<?= $fw_ac?'':'hidden' ?> bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] text-slate-600 dark:text-slate-300 text-xs rounded-xl px-4 py-2 focus:ring-2 focus:ring-primary max-w-[200px]">
                </select>

                <?php if ($fw_ac): ?>
                <div class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-primary/10 border border-primary/30 text-primary text-xs font-semibold rounded-xl">
                    <span class="material-symbols-outlined text-[13px]">filter_alt</span>
                    <?= htmlspecialchars($fw_av) ?>
                    <a href="?search=<?= urlencode($search) ?>" class="ml-1 text-primary/60 hover:text-red-500 transition-colors" title="Clear filter">
                        <span class="material-symbols-outlined text-[14px]">close</span>
                    </a>
                </div>
                <?php endif; ?>

                <?php if ($search || $fw_ac): ?>
                <a href="?" class="px-4 py-2 bg-slate-200 dark:bg-[#232b3d] text-slate-600 dark:text-slate-300 text-xs font-bold rounded-xl hover:opacity-80 transition-opacity">Clear All</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <script>
    const _fwO = {
        location:  <?= json_encode(array_values($locations)) ?>,
        fw_status: ['Yes','No']
    };
    const _fwHid = { location:'hid_fw_location', fw_status:'hid_fw_status' };
    const _fwAC  = <?= json_encode($fw_ac) ?>;
    const _fwAV  = <?= json_encode($fw_av) ?>;
    function fwOnCat(c) {
        const v = document.getElementById('fw_val');
        if (!c) { v.classList.add('hidden'); return; }
        Object.keys(_fwHid).forEach(function(k) {
            if (k !== c) { const h = document.getElementById(_fwHid[k]); if (h) h.value = ''; }
        });
        v.innerHTML = '<option value="">All</option>';
        (_fwO[c] || []).forEach(function(o) {
            const e = document.createElement('option');
            e.value = o; e.textContent = o;
            if (c === _fwAC && o === _fwAV) e.selected = true;
            v.appendChild(e);
        });
        v.classList.remove('hidden');
    }
    function fwApply(val) {
        const c = document.getElementById('fw_cat').value;
        if (!c) return;
        const h = document.getElementById(_fwHid[c]);
        if (h) h.value = val;
        document.getElementById('fw_cat').closest('form').submit();
    }
    document.addEventListener('DOMContentLoaded', function() {
        if (_fwAC) fwOnCat(_fwAC);
    });
    </script>


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
