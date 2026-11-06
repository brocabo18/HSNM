<?php
require_once '../../config.php';
requireLogin();

// ── Automatic File-Based Change Detector ──────────────────────────────────────
function detectSystemChanges($pdo): array
{
    // Create tracking table if it doesn't exist yet
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS file_checksums (
            file_path  TEXT PRIMARY KEY,
            file_hash  TEXT NOT NULL,
            scanned_at TIMESTAMPTZ DEFAULT NOW()
        )
    ");

    $root = realpath(__DIR__ . '/../../');

    // Collect all files to track
    $track = [];
    foreach (glob($root . '/modules/*/index.php') ?: [] as $f) {
        $track[] = $f;
    }
    foreach (['/config.php', '/includes/header.php', '/includes/sidebar.php', '/includes/footer.php'] as $f) {
        if (file_exists($root . $f)) $track[] = $root . $f;
    }

    // Human-readable module name map
    $module_map = [
        'computers'      => 'Computer Inventory',
        'jasmc_st'       => 'JASMC-ST (Sto. Tomas)',
        'jasmc_vt'       => 'JASMC-VT (Victoria Tarlac)',
        'doh_jbl_cmc'    => 'DOH-JBL-CMC (Children\'s Medical Center)',
        'assets'         => 'Asset Inventory',
        'maintenance'    => 'Preventive Maintenance',
        'ips'            => 'IP Address Inventory',
        'office'         => 'MS Office Licenses',
        'printers'       => 'Printer Inventory',
        'routers'        => 'Router Inventory',
        'switches'       => 'Switch Inventory',
        'pabx'           => 'PABX Directory',
        'ics'            => 'ICS Inventory',
        'ihoms_links'    => 'IS Links',
        'changelog'      => 'Changelog',
        'reports'        => 'Reports',
        'queueing_tv'    => 'Queueing TV',
        'settings'       => 'Settings',
        'reconciliation' => 'Reconciliation',
        'audit_logs'     => 'Audit Logs',
    ];

    // Helper to get current system version for auto-detected items
    $latest_version = '1.15.2';
    try {
        $stmt_v = $pdo->query("SELECT version FROM changelog WHERE version IS NOT NULL AND TRIM(version) != '' AND version NOT LIKE '%-scan%' ORDER BY id DESC LIMIT 1");
        $v_row = $stmt_v ? $stmt_v->fetchColumn() : null;
        if ($v_row) $latest_version = ltrim($v_row, 'v');
    } catch (Exception $ve) {}

    // Is this the very first scan? (baseline init — no changelog spam)
    $is_baseline = ((int)$pdo->query("SELECT COUNT(*) FROM file_checksums")->fetchColumn() === 0);

    $upsert = $pdo->prepare("
        INSERT INTO file_checksums (file_path, file_hash, scanned_at)
        VALUES (?, ?, NOW())
        ON CONFLICT (file_path) DO UPDATE SET file_hash = EXCLUDED.file_hash, scanned_at = NOW()
    ");
    $get_hash  = $pdo->prepare("SELECT file_hash FROM file_checksums WHERE file_path = ?");
    $dup_check = $pdo->prepare("SELECT id FROM changelog WHERE title = ? AND change_date = CURRENT_DATE LIMIT 1");
    $ins_log   = $pdo->prepare("INSERT INTO changelog (version, change_type, module, title, description, change_date) VALUES (?, ?, ?, ?, ?, CURRENT_DATE)");

    $detected = [];

    foreach ($track as $abs) {
        if (!is_readable($abs)) continue;

        // Build a normalised relative path
        $rel  = ltrim(str_replace(['\\', $root . DIRECTORY_SEPARATOR, $root . '/'], ['/', '', ''], $abs), '/');
        $hash = md5_file($abs);

        // Determine system area + default change type
        $parts = explode('/', str_replace('\\', '/', $rel));
        $area  = 'System Core';
        $type  = 'enhancement';

        if (count($parts) >= 3 && $parts[0] === 'modules') {
            $mk   = $parts[1];
            $area = $module_map[$mk] ?? ucwords(str_replace('_', ' ', $mk));
        } elseif ($parts[0] === 'config.php') {
            $area = 'System Configuration';
            $type = 'refactor';
        } elseif (!empty($parts[0]) && $parts[0] === 'includes') {
            $area = 'System Core (' . basename($abs) . ')';
        }

        // Fetch previously stored hash
        $get_hash->execute([$rel]);
        $row = $get_hash->fetch();

        if (!$row) {
            // New file detected
            $upsert->execute([$rel, $hash]);
            if ($is_baseline) continue;   // Silent baseline — no log entry
            $type  = 'feature';
            $title = "New Module Detected: {$area}";
            $desc  = "A new module or file ({$area}) was added to the system. File: {$rel}";
        } elseif ($row['file_hash'] !== $hash) {
            // File was modified
            $upsert->execute([$rel, $hash]);
            $title = "Updated: {$area}";
            $desc  = "Code changes were detected in {$area}. File: {$rel}";
        } else {
            continue; // Unchanged — skip
        }

        // Deduplicate: skip if we already logged this title today
        $dup_check->execute([$title]);
        if ($dup_check->fetch()) continue;

        $ins_log->execute([$latest_version, $type, $area, $title, $desc]);
        $detected[] = ['type' => $type, 'area' => $area, 'title' => $title];
    }

    return $detected;
}

// Run the scan on every page load (fast — only ~15-20 files)
$scan_detected = [];
try {
    $scan_detected = detectSystemChanges($pdo);
} catch (Exception $e) {
    error_log("Changelog auto-scan error: " . $e->getMessage());
}

// Filters
$search_term = $_GET['search'] ?? '';
$filter_type = $_GET['type'] ?? 'All';
$filter_module = $_GET['module'] ?? 'All';
$filter_date = $_GET['date_range'] ?? 'All';

$where_clauses = ["1=1"];
$params = [];

// Search filter
if ($search_term) {
    $where_clauses[] = "(title ILIKE ? OR description ILIKE ? OR version ILIKE ?)";
    $params[] = "%$search_term%";
    $params[] = "%$search_term%";
    $params[] = "%$search_term%";
}

// Type filter
if ($filter_type !== 'All' && !empty($filter_type)) {
    $where_clauses[] = "change_type = ?";
    $params[] = $filter_type;
}

// Module filter
if ($filter_module !== 'All' && !empty($filter_module)) {
    $where_clauses[] = "module = ?";
    $params[] = $filter_module;
}

// Date range filter
if ($filter_date !== 'All') {
    switch ($filter_date) {
        case 'month':
            $where_clauses[] = "change_date >= CURRENT_DATE - INTERVAL '1 month'";
            break;
        case '3months':
            $where_clauses[] = "change_date >= CURRENT_DATE - INTERVAL '3 months'";
            break;
        case 'year':
            $where_clauses[] = "change_date >= CURRENT_DATE - INTERVAL '1 year'";
            break;
    }
}

$where_sql = implode(' AND ', $where_clauses);

// Pagination
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1)
    $page = 1;
$limit_param = $_GET['limit'] ?? '15';
$limit = ($limit_param === 'all') ? 999999 : (int) $limit_param;
$offset = ($page - 1) * $limit;

// Count
$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM changelog WHERE $where_sql");
$count_stmt->execute($params);
$total_items = $count_stmt->fetchColumn();
$total_pages = ceil($total_items / $limit);

// Fetch changelog entries
$sql = "SELECT * FROM changelog WHERE $where_sql ORDER BY change_date DESC, id DESC LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$entries = $stmt->fetchAll();

// Get unique modules for filter
$modules = $pdo->query("SELECT DISTINCT module FROM changelog ORDER BY module")->fetchAll(PDO::FETCH_COLUMN);

// Get statistics
$stats_query = "SELECT change_type, COUNT(*) as count FROM changelog GROUP BY change_type";
$stats_stmt = $pdo->query($stats_query);
$stats = $stats_stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$page_title = "Changelog";
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';

// Helper function for change type badge colors
function getTypeColor($type)
{
    switch ($type) {
        case 'feature':
            return 'bg-blue-500/10 text-blue-500 border-blue-500/20';
        case 'bugfix':
            return 'bg-red-500/10 text-red-500 border-red-500/20';
        case 'enhancement':
            return 'bg-purple-500/10 text-purple-500 border-purple-500/20';
        case 'security':
            return 'bg-amber-500/10 text-amber-500 border-amber-500/20';
        case 'refactor':
            return 'bg-cyan-500/10 text-cyan-500 border-cyan-500/20';
        default:
            return 'bg-slate-500/10 text-slate-500 border-slate-500/20';
    }
}

function getTypeIcon($type)
{
    switch ($type) {
        case 'feature':
            return 'new_releases';
        case 'bugfix':
            return 'bug_report';
        case 'enhancement':
            return 'upgrade';
        case 'security':
            return 'shield';
        case 'refactor':
            return 'construction';
        default:
            return 'circle';
    }
}
?>

<div class="p-4 md:p-8 flex-1 overflow-y-auto custom-scrollbar relative z-10 bg-slate-50 dark:bg-background-dark">
    <header class="-mt-4 -mx-4 md:-mt-8 md:-mx-8 px-4 md:px-8 pt-4 md:pt-8 pb-4 mb-6 flex flex-col md:flex-row items-start md:items-end justify-between gap-4 sticky top-0 bg-slate-50 dark:bg-background-dark z-20 border-b border-slate-200 dark:border-[#232b3d]">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <span class="px-2 py-0.5 rounded text-[11px] font-bold tracking-wider uppercase bg-primary/10 text-primary border border-primary/20">Version History</span>
                <span class="px-2 py-0.5 rounded-full text-[11px] font-mono font-bold bg-primary/10 text-primary border border-primary/20">
                    <?= getAppVersion($pdo) ?>
                </span>
            </div>
            <h2 class="text-2xl font-bold text-slate-900 dark:text-white">System Changelog</h2>
            <p class="text-xs text-slate-400 mt-0.5">Automated tracking for codebase changes, updates, fixes, and enhancements</p>
        </div>
        <div class="no-print flex items-center gap-2 px-3 py-1.5 rounded-lg bg-emerald-500/10 border border-emerald-500/20 text-emerald-500 text-xs font-semibold">
            <span class="material-symbols-outlined text-[16px]">radar</span>
            Auto-scan active
        </div>
    </header>

    <?php if (!empty($scan_detected)): ?>
        <div class="mb-4 p-3 rounded-xl bg-sky-500/10 border border-sky-500/20 text-sky-600 dark:text-sky-400 text-xs font-semibold no-print">
            <div class="flex items-center gap-2 font-bold mb-1">
                <span class="material-symbols-outlined text-[16px]">radar</span>
                <?= count($scan_detected) ?> new system change(s) auto-detected and logged
            </div>
            <ul class="ml-5 space-y-0.5">
                <?php foreach ($scan_detected as $r): ?>
                    <li class="text-[11px] flex items-center gap-1.5 font-normal">
                        <span class="w-1.5 h-1.5 rounded-full bg-sky-400 inline-block flex-shrink-0"></span>
                        <?= htmlspecialchars($r['title']) ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- Statistics Cards Grid -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 mb-6 no-print">
        <div class="bg-white dark:bg-[#161d2b] p-4 rounded-xl border border-slate-200 dark:border-[#232b3d] shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Features</span>
                <span class="material-symbols-outlined text-blue-500 text-base">new_releases</span>
            </div>
            <p class="text-2xl font-bold mt-1 text-blue-500"><?= number_format($stats['feature'] ?? 0) ?></p>
            <span class="text-[10px] text-slate-400">New functionality</span>
        </div>
        <div class="bg-white dark:bg-[#161d2b] p-4 rounded-xl border border-slate-200 dark:border-[#232b3d] shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Bug Fixes</span>
                <span class="material-symbols-outlined text-red-500 text-base">bug_report</span>
            </div>
            <p class="text-2xl font-bold mt-1 text-red-500"><?= number_format($stats['bugfix'] ?? 0) ?></p>
            <span class="text-[10px] text-slate-400">Resolved defects</span>
        </div>
        <div class="bg-white dark:bg-[#161d2b] p-4 rounded-xl border border-slate-200 dark:border-[#232b3d] shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Enhancements</span>
                <span class="material-symbols-outlined text-purple-500 text-base">upgrade</span>
            </div>
            <p class="text-2xl font-bold mt-1 text-purple-400"><?= number_format($stats['enhancement'] ?? 0) ?></p>
            <span class="text-[10px] text-slate-400">System improvements</span>
        </div>
        <div class="bg-white dark:bg-[#161d2b] p-4 rounded-xl border border-slate-200 dark:border-[#232b3d] shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Security</span>
                <span class="material-symbols-outlined text-amber-500 text-base">shield</span>
            </div>
            <p class="text-2xl font-bold mt-1 text-amber-500"><?= number_format($stats['security'] ?? 0) ?></p>
            <span class="text-[10px] text-slate-400">Hardening &amp; audits</span>
        </div>
        <div class="bg-white dark:bg-[#161d2b] p-4 rounded-xl border border-slate-200 dark:border-[#232b3d] shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Refactors</span>
                <span class="material-symbols-outlined text-cyan-500 text-base">construction</span>
            </div>
            <p class="text-2xl font-bold mt-1 text-cyan-400"><?= number_format($stats['refactor'] ?? 0) ?></p>
            <span class="text-[10px] text-slate-400">Code cleanups</span>
        </div>
    </div>

    <!-- Filters / Control Bar -->
    <div class="bg-white dark:bg-[#161d2b] p-3 rounded-xl border border-slate-200 dark:border-[#232b3d] shadow-sm mb-4 no-print">
        <form method="GET" class="flex flex-wrap items-center gap-2">
            <div class="relative flex-1 min-w-[200px]">
                <span class="material-symbols-outlined absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400 text-sm">search</span>
                <input type="text" name="search" id="searchInput" value="<?= htmlspecialchars($search_term) ?>" placeholder="Search changelog..." class="w-full pl-9 pr-3 py-1.5 text-xs rounded-lg bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:border-primary">
            </div>

            <input type="hidden" name="type"       id="hid_cl_type"   value="<?= htmlspecialchars($filter_type) ?>">
            <input type="hidden" name="module"     id="hid_cl_module" value="<?= htmlspecialchars($filter_module) ?>">
            <input type="hidden" name="date_range" id="hid_cl_date"   value="<?= htmlspecialchars($filter_date) ?>">
            <?php
            $cl_ac=''; $cl_av=''; $cl_al='';
            $cl_date_labels=['month'=>'Last Month','3months'=>'Last 3 Months','year'=>'Last Year'];
            if($filter_type!=='All'&&$filter_type!=='')      {$cl_ac='type';      $cl_av=$filter_type;  $cl_al=ucfirst($filter_type);}
            elseif($filter_module!=='All'&&$filter_module!==''){$cl_ac='module';    $cl_av=$filter_module;$cl_al=$filter_module;}
            elseif($filter_date!=='All'&&$filter_date!=='')  {$cl_ac='date_range';$cl_av=$filter_date;  $cl_al=$cl_date_labels[$filter_date]??$filter_date;}
            ?>
            <select id="cl_cat" onchange="clOnCat(this.value)" class="px-2.5 py-1.5 text-xs rounded-lg bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-700 dark:text-slate-300 focus:outline-none focus:border-primary">
                <option value="">Filter by...</option>
                <option value="type"       <?= $cl_ac==='type'      ?'selected':'' ?>>Type</option>
                <option value="module"     <?= $cl_ac==='module'    ?'selected':'' ?>>Module</option>
                <option value="date_range" <?= $cl_ac==='date_range'?'selected':'' ?>>Date Range</option>
            </select>
            <select id="cl_val" onchange="clApply(this.value)" class="<?= $cl_ac?'':'hidden' ?> px-2.5 py-1.5 text-xs rounded-lg bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-700 dark:text-slate-300 focus:outline-none focus:border-primary max-w-[200px]">
            </select>
            <?php if($cl_ac): ?>
            <div class="inline-flex items-center gap-1 px-2.5 py-1 bg-primary/10 border border-primary/20 text-primary text-xs font-semibold rounded-lg">
                <span class="material-symbols-outlined text-[13px]">filter_alt</span>
                <?= htmlspecialchars($cl_al) ?>
                <a href="?search=<?= urlencode($search_term) ?>&limit=<?= urlencode($limit_param) ?>" class="ml-1 text-primary/60 hover:text-red-500" title="Clear">
                    <span class="material-symbols-outlined text-[14px]">close</span>
                </a>
            </div>
            <?php endif; ?>
            <script>
            const _clO={
                type:[
                    {val:'feature',label:'Feature'},{val:'bugfix',label:'Bug Fix'},
                    {val:'enhancement',label:'Enhancement'},{val:'security',label:'Security'},
                    {val:'refactor',label:'Refactor'}
                ],
                module: <?= json_encode(array_values($modules)) ?>,
                date_range:[
                    {val:'month',label:'Last Month'},{val:'3months',label:'Last 3 Months'},
                    {val:'year',label:'Last Year'}
                ]
            };
            const _clHid={type:'hid_cl_type',module:'hid_cl_module',date_range:'hid_cl_date'};
            const _clAC=<?= json_encode($cl_ac) ?>,_clAV=<?= json_encode($cl_av) ?>;
            function clOnCat(c){
                const v=document.getElementById('cl_val');
                if(!c){v.classList.add('hidden');return;}
                Object.keys(_clHid).forEach(function(k){if(k!==c){const h=document.getElementById(_clHid[k]);if(h)h.value='All';}});
                v.innerHTML='<option value="All">All</option>';
                (_clO[c]||[]).forEach(function(o){
                    const val=typeof o==='object'?o.val:o,lbl=typeof o==='object'?o.label:o;
                    const e=document.createElement('option');e.value=val;e.textContent=lbl;
                    if(c===_clAC&&val===_clAV)e.selected=true;
                    v.appendChild(e);
                });
                v.classList.remove('hidden');
            }
            function clApply(val){
                const c=document.getElementById('cl_cat').value;if(!c)return;
                document.getElementById(_clHid[c]).value=val;
                document.getElementById('cl_cat').closest('form').submit();
            }
            document.addEventListener('DOMContentLoaded',function(){if(_clAC)ilOnCat(_clAC);});
            </script>

            <select name="limit" onchange="this.form.submit()" class="px-2.5 py-1.5 text-xs rounded-lg bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-700 dark:text-slate-300 focus:outline-none focus:border-primary">
                <option value="20" <?= $limit_param == '20' ? 'selected' : '' ?>>20</option>
                <option value="15" <?= $limit_param == '15' ? 'selected' : '' ?>>15</option>
                <option value="50" <?= $limit_param == '50' ? 'selected' : '' ?>>50</option>
                <option value="100" <?= $limit_param == '100' ? 'selected' : '' ?>>100</option>
                <option value="all" <?= $limit_param == 'all' ? 'selected' : '' ?>>All</option>
            </select>

            <button type="submit" class="px-3.5 py-1.5 text-xs font-bold rounded-lg bg-primary hover:bg-primary-hover text-white transition-all shadow-sm">
                Filter
            </button>

            <?php if ($search_term || $cl_ac): ?>
                <a href="index.php" class="px-2.5 py-1.5 text-xs font-medium text-red-500 hover:text-red-600 transition-colors">
                    Clear
                </a>
            <?php endif; ?>

            <!-- Top Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="ml-auto flex items-center gap-1.5 text-xs text-slate-500 dark:text-slate-400">
                    <span>Page <?= $page ?> of <?= $total_pages ?></span>
                    <a href="?page=<?= max(1, $page - 1) ?>&search=<?= urlencode($search_term) ?>&type=<?= urlencode($filter_type) ?>&module=<?= urlencode($filter_module) ?>&date_range=<?= urlencode($filter_date) ?>&limit=<?= $limit ?>" class="p-1 rounded bg-slate-100 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-600 dark:text-slate-300 <?= $page <= 1 ? 'pointer-events-none opacity-40' : 'hover:bg-slate-200 dark:hover:bg-[#232b3d]' ?>"><span class="material-symbols-outlined text-sm block">chevron_left</span></a>
                    <a href="?page=<?= min($total_pages, $page + 1) ?>&search=<?= urlencode($search_term) ?>&type=<?= urlencode($filter_type) ?>&module=<?= urlencode($filter_module) ?>&date_range=<?= urlencode($filter_date) ?>&limit=<?= $limit ?>" class="p-1 rounded bg-slate-100 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-600 dark:text-slate-300 <?= $page >= $total_pages ? 'pointer-events-none opacity-40' : 'hover:bg-slate-200 dark:hover:bg-[#232b3d]' ?>"><span class="material-symbols-outlined text-sm block">chevron_right</span></a>
                </div>
            <?php endif; ?>
        </form>
    </div>

    <!-- Timeline -->
    <div class="space-y-6">
        <?php if (empty($entries)): ?>
            <div
                class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] rounded-2xl p-12 text-center">
                <span class="material-symbols-outlined text-6xl text-slate-300 dark:text-slate-700 mb-4">history</span>
                <p class="text-slate-500">No changelog entries found.</p>
            </div>
        <?php else: ?>
            <?php
            $current_month = '';
            foreach ($entries as $entry):
                $entry_month = date('F Y', strtotime($entry['change_date']));
                $show_month_header = ($entry_month !== $current_month);
                $current_month = $entry_month;
                ?>

                <?php if ($show_month_header): ?>
                    <div class="flex items-center gap-4 mb-4">
                        <div class="text-lg font-bold text-primary"><?= $entry_month ?></div>
                        <div class="flex-1 h-px bg-gradient-to-r from-primary/20 to-transparent"></div>
                    </div>
                <?php endif; ?>

                <div class="relative pl-8 pb-8 border-l-2 border-slate-200 dark:border-[#232b3d] last:border-transparent">
                    <!-- Timeline dot -->
                    <div
                        class="absolute -left-[9px] top-1 size-4 rounded-full bg-primary border-4 border-white dark:border-background-dark shadow-lg shadow-primary/20">
                    </div>

                    <div
                        class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] rounded-xl p-6 hover:border-primary/30 transition-colors">
                        <div class="flex items-start justify-between gap-4 mb-3">
                            <div class="flex items-center gap-3 flex-wrap">
                                <span
                                    class="px-3 py-1 rounded-lg text-xs font-bold border <?= getTypeColor($entry['change_type']) ?>">
                                    <span class="material-symbols-outlined text-[14px] align-middle mr-1">
                                        <?= getTypeIcon($entry['change_type']) ?>
                                    </span>
                                    <?= ucfirst($entry['change_type']) ?>
                                </span>
                                <?php if ($entry['module']): ?>
                                    <span
                                        class="px-3 py-1 bg-slate-500/10 text-slate-600 dark:text-slate-400 rounded-lg text-xs font-bold border border-slate-500/20">
                                        <?= htmlspecialchars($entry['module']) ?>
                                    </span>
                                <?php endif; ?>
                                <?php if (!empty($entry['version'])): ?>
                                    <span class="text-xs text-slate-500 font-mono">v<?= htmlspecialchars(ltrim($entry['version'], 'v')) ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="flex items-center gap-2 md:gap-3 flex-wrap">
                                <span class="text-xs text-slate-500 whitespace-nowrap">
                                    <?= date('M d, Y', strtotime($entry['change_date'])) ?>
                                </span>
                            </div>
                        </div>

                        <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-2">
                            <?= htmlspecialchars($entry['title']) ?>
                        </h3>

                        <p class="text-sm text-slate-600 dark:text-slate-400 leading-relaxed">
                            <?= nl2br(htmlspecialchars($entry['description'])) ?>
                        </p>
                    </div>
                </div>

            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Bottom Pagination -->
    <?php if ($total_pages > 1): ?>
        <div class="mt-6 flex justify-center gap-2">
            <?php if ($page > 1): ?>
                <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search_term) ?>&type=<?= urlencode($filter_type) ?>&module=<?= urlencode($filter_module) ?>&date_range=<?= urlencode($filter_date) ?>"
                    class="px-3 py-2 bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] text-slate-600 dark:text-slate-400 text-xs rounded-lg hover:text-slate-900 dark:hover:text-white">Previous</a>
            <?php endif; ?>

            <span class="px-3 py-2 bg-primary/10 border border-primary/20 text-primary text-xs rounded-lg">
                Page <?= $page ?> of <?= $total_pages ?>
            </span>

            <?php if ($page < $total_pages): ?>
                <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search_term) ?>&type=<?= urlencode($filter_type) ?>&module=<?= urlencode($filter_module) ?>&date_range=<?= urlencode($filter_date) ?>"
                    class="px-3 py-2 bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] text-slate-600 dark:text-slate-400 text-xs rounded-lg hover:text-slate-900 dark:hover:text-white">Next</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<script>
    // Live Search Functionality
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
</script>

<?php require_once '../../includes/footer.php'; ?>
