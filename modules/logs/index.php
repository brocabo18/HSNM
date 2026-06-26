<?php
require_once '../../config.php';
requireLogin();

// Restrict access to admin/user only (viewers cannot see logs)
if (isset($_SESSION['role']) && $_SESSION['role'] === 'viewer') {
    header("Location: " . BASE_URL . "/index.php");
    exit;
}

// Filters
$search_term = $_GET['search'] ?? '';
$filter_action = $_GET['action'] ?? 'All';
$filter_resource = $_GET['resource'] ?? 'All';
$filter_user = $_GET['user'] ?? 'All';
$filter_date_from = $_GET['date_from'] ?? '';
$filter_date_to = $_GET['date_to'] ?? '';

$where_clauses = ["1=1"];
$params = [];

if ($search_term) {
    // using ILIKE for case-insensitive search
    $where_clauses[] = "(al.details ILIKE ? OR u.username ILIKE ? OR al.action_type ILIKE ? OR al.resource_type ILIKE ? OR al.ip_address ILIKE ? OR al.resource_id::text ILIKE ?)";
    $params[] = "%$search_term%";
    $params[] = "%$search_term%";
    $params[] = "%$search_term%";
    $params[] = "%$search_term%";
    $params[] = "%$search_term%";
    $params[] = "%$search_term%";
}

if ($filter_action !== 'All' && !empty($filter_action)) {
    $where_clauses[] = "al.action_type = ?";
    $params[] = $filter_action;
}

if ($filter_resource !== 'All' && !empty($filter_resource)) {
    $where_clauses[] = "al.resource_type = ?";
    $params[] = $filter_resource;
}

// User filter
if ($filter_user !== 'All' && !empty($filter_user)) {
    $where_clauses[] = "u.username = ?";
    $params[] = $filter_user;
}

// Date range filter
if (!empty($filter_date_from)) {
    $where_clauses[] = "al.created_at::date >= ?";
    $params[] = $filter_date_from;
}
if (!empty($filter_date_to)) {
    $where_clauses[] = "al.created_at::date <= ?";
    $params[] = $filter_date_to;
}

$where_sql = implode(' AND ', $where_clauses);

// Pagination
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1)
    $page = 1;
$limit_param = $_GET['limit'] ?? '50';
$limit = ($limit_param === 'all') ? 999999 : (int) $limit_param;
$offset = ($page - 1) * $limit;

// Count
$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM audit_logs al LEFT JOIN users u ON al.user_id = u.id WHERE $where_sql");
$count_stmt->execute($params);
$total_items = $count_stmt->fetchColumn();
$total_pages = ceil($total_items / $limit);

// Fetch
$sql = "SELECT al.*, u.username FROM audit_logs al LEFT JOIN users u ON al.user_id = u.id WHERE $where_sql ORDER BY al.created_at DESC LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Get unique action types for filter
$actions = $pdo->query("SELECT DISTINCT action_type FROM audit_logs ORDER BY action_type")->fetchAll(PDO::FETCH_COLUMN);

// Get all users who appear in audit logs
$users_list = $pdo->query("SELECT DISTINCT u.username FROM audit_logs al LEFT JOIN users u ON al.user_id = u.id WHERE u.username IS NOT NULL ORDER BY u.username")->fetchAll(PDO::FETCH_COLUMN);

// Get counts by resource for statistics - synced with action filter
$stats_where = [];
$stats_params = [];

if ($filter_action !== 'All' && !empty($filter_action)) {
    // If specific action selected, show counts for that action only
    $stats_where[] = "action_type = ?";
    $stats_params[] = $filter_action;
} else {
    // If no filter, show all update-related actions by default
    $stats_where[] = "action_type LIKE '%update%'";
}

$stats_where_sql = implode(' AND ', $stats_where);
$stats_query = "SELECT resource_type, COUNT(*) as count FROM audit_logs WHERE $stats_where_sql GROUP BY resource_type";
$stats_stmt = $pdo->prepare($stats_query);
$stats_stmt->execute($stats_params);
$stats = $stats_stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Get total count for selected action/filter
$total_filtered = array_sum($stats);

$page_title = "Audit Logs";
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
?>

<div class="p-4 md:p-8 flex-1 overflow-y-auto custom-scrollbar relative z-10 bg-slate-50 dark:bg-background-dark">
    <header
        class="-mt-4 -mx-4 md:-mt-8 md:-mx-8 px-4 md:px-8 pt-4 md:pt-8 pb-6 mb-8 flex flex-col md:flex-row items-start md:items-end justify-between gap-4 sticky top-0 bg-slate-50 dark:bg-background-dark z-20 border-b border-slate-200 dark:border-[#232b3d]">
        <div>
            <h2 class="text-2xl font-bold text-slate-900 dark:text-white">Audit Logs</h2>
            <?php if ($filter_action !== 'All' && !empty($filter_action)): ?>
                <p class="text-sm text-slate-400 mt-1">
                    Showing <span
                        class="text-primary font-bold"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $filter_action))) ?></span>
                    statistics
                </p>
            <?php else: ?>
                <p class="text-sm text-slate-500 mt-1">View system activity and changes.</p>
            <?php endif; ?>
        </div>
    </header>

    <!-- Update Statistics -->
    <?php
    // Dynamic label based on filter
    $action_label = ($filter_action !== 'All' && !empty($filter_action))
        ? ucfirst(str_replace('_', ' ', $filter_action))
        : 'Updates';
    ?>
    <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
        <div class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] p-4 rounded-xl">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-slate-500 text-xs font-bold uppercase tracking-wider mb-1">IP <?= $action_label ?>
                    </div>
                    <div class="text-2xl font-bold text-emerald-500"><?= number_format($stats['ip'] ?? 0) ?></div>
                </div>
                <span class="material-symbols-outlined text-4xl text-emerald-500/20">dns</span>
            </div>
        </div>
        <div class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] p-4 rounded-xl">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-slate-500 text-xs font-bold uppercase tracking-wider mb-1">Router
                        <?= $action_label ?>
                    </div>
                    <div class="text-2xl font-bold text-blue-500"><?= number_format($stats['router'] ?? 0) ?></div>
                </div>
                <span class="material-symbols-outlined text-4xl text-blue-500/20">router</span>
            </div>
        </div>
        <div class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] p-4 rounded-xl">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-slate-500 text-xs font-bold uppercase tracking-wider mb-1">Switch
                        <?= $action_label ?>
                    </div>
                    <div class="text-2xl font-bold text-purple-500"><?= number_format($stats['switch'] ?? 0) ?></div>
                </div>
                <span class="material-symbols-outlined text-4xl text-purple-500/20">hub</span>
            </div>
        </div>
        <div class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] p-4 rounded-xl">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-slate-500 text-xs font-bold uppercase tracking-wider mb-1">User
                        <?= $action_label ?>
                    </div>
                    <div class="text-2xl font-bold text-amber-500"><?= number_format($stats['user'] ?? 0) ?></div>
                </div>
                <span class="material-symbols-outlined text-4xl text-amber-500/20">person</span>
            </div>
        </div>
        <div class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] p-4 rounded-xl">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-slate-500 text-xs font-bold uppercase tracking-wider mb-1">Reconcile
                        <?= $action_label ?>
                    </div>
                    <div class="text-2xl font-bold text-cyan-500"><?= number_format($stats['reconciliation'] ?? 0) ?>
                    </div>
                </div>
                <span class="material-symbols-outlined text-4xl text-cyan-500/20">sync_alt</span>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="mb-6 flex gap-3">
        <form method="GET" class="flex items-center gap-3 flex-wrap w-full">
            <div class="relative group">
                <span
                    class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-[18px] text-slate-500">search</span>
                <input type="text" name="search" id="searchInput" value="<?= htmlspecialchars($search_term) ?>"
                    placeholder="Search logs..."
                    class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] text-slate-700 dark:text-slate-200 text-xs rounded-xl pl-9 pr-4 py-2 focus:ring-2 focus:ring-primary focus:border-transparent w-64">
            </div>
            <input type="hidden" name="action"   id="hid_lg_action"   value="<?= htmlspecialchars($filter_action) ?>">
            <input type="hidden" name="resource" id="hid_lg_resource" value="<?= htmlspecialchars($filter_resource) ?>">
            <input type="hidden" name="user"     id="hid_lg_user"     value="<?= htmlspecialchars($filter_user) ?>">
            <?php
            $lg_ac=''; $lg_av=''; $lg_al='';
            if($filter_action!=='All'&&$filter_action!=='')  {$lg_ac='action';  $lg_av=$filter_action;  $lg_al=ucfirst(str_replace('_',' ',$filter_action));}
            elseif($filter_resource!=='All'&&$filter_resource!==''){$lg_ac='resource';$lg_av=$filter_resource;$lg_al=ucfirst($filter_resource);}
            elseif($filter_user!=='All'&&$filter_user!=='')  {$lg_ac='user';    $lg_av=$filter_user;    $lg_al=$filter_user;}
            ?>
            <select id="lg_cat" onchange="lgOnCat(this.value)"
                class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] text-slate-600 dark:text-slate-300 text-xs rounded-xl px-4 py-2 focus:ring-2 focus:ring-primary">
                <option value="">Filter by...</option>
                <option value="action"   <?= $lg_ac==='action'  ?'selected':'' ?>>Action</option>
                <option value="resource" <?= $lg_ac==='resource'?'selected':'' ?>>Resource</option>
                <option value="user"     <?= $lg_ac==='user'    ?'selected':'' ?>>User</option>
            </select>
            <select id="lg_val" onchange="lgApply(this.value)"
                class="<?= $lg_ac?'':'hidden' ?> bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] text-slate-600 dark:text-slate-300 text-xs rounded-xl px-4 py-2 focus:ring-2 focus:ring-primary max-w-[200px]">
            </select>
            <?php if($lg_ac): ?>
            <div class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-primary/10 border border-primary/30 text-primary text-xs font-semibold rounded-xl">
                <span class="material-symbols-outlined text-[13px]">filter_alt</span>
                <?= htmlspecialchars($lg_al) ?>
                <a href="?search=<?= urlencode($search_term) ?>&limit=<?= urlencode($limit_param) ?>" class="ml-1 text-primary/60 hover:text-red-500" title="Clear">
                    <span class="material-symbols-outlined text-[14px]">close</span>
                </a>
            </div>
            <?php endif; ?>
            <script>
            const _lgO = {
                action:   <?= json_encode(array_map(function($a){return['val'=>$a,'label'=>ucfirst(str_replace('_',' ',$a))];}, $actions)) ?>,
                resource: [
                    {val:'computer',label:'Computer'},{val:'router',label:'Router'},{val:'switch',label:'Switch'},
                    {val:'ip',label:'IP Address'},{val:'office',label:'MS Office'},{val:'printers',label:'Printers'},
                    {val:'pabx',label:'PABX'},{val:'ics',label:'ICS'},{val:'user',label:'User'},
                    {val:'sync',label:'Sync'},{val:'system',label:'System'}
                ],
                user: <?= json_encode(array_values($users_list)) ?>
            };
            const _lgHid={action:'hid_lg_action',resource:'hid_lg_resource',user:'hid_lg_user'};
            const _lgAC=<?= json_encode($lg_ac) ?>,_lgAV=<?= json_encode($lg_av) ?>;
            function lgOnCat(c){
                const v=document.getElementById('lg_val');
                if(!c){v.classList.add('hidden');return;}
                Object.keys(_lgHid).forEach(function(k){if(k!==c){const h=document.getElementById(_lgHid[k]);if(h)h.value='All';}});
                v.innerHTML='<option value="All">All</option>';
                (_lgO[c]||[]).forEach(function(o){
                    const val=typeof o==='object'?o.val:o,lbl=typeof o==='object'?o.label:o;
                    const e=document.createElement('option');e.value=val;e.textContent=lbl;
                    if(c===_lgAC&&val===_lgAV)e.selected=true;
                    v.appendChild(e);
                });
                v.classList.remove('hidden');
            }
            function lgApply(val){
                const c=document.getElementById('lg_cat').value;if(!c)return;
                document.getElementById(_lgHid[c]).value=val;
                document.getElementById('lg_cat').closest('form').submit();
            }
            document.addEventListener('DOMContentLoaded',function(){if(_lgAC)lgOnCat(_lgAC);});
            </script>

            <!-- Date Range Filter -->
            <div class="flex items-center gap-1.5">
                <span class="material-symbols-outlined text-[16px] text-slate-400">calendar_month</span>
                <input type="date" name="date_from" id="dateFrom"
                    value="<?= htmlspecialchars($filter_date_from) ?>"
                    title="From date"
                    class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] text-slate-700 dark:text-slate-300 text-xs rounded-xl px-3 py-2 focus:ring-2 focus:ring-primary focus:border-transparent">
                <span class="text-xs text-slate-400">–</span>
                <input type="date" name="date_to" id="dateTo"
                    value="<?= htmlspecialchars($filter_date_to) ?>"
                    title="To date"
                    class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] text-slate-700 dark:text-slate-300 text-xs rounded-xl px-3 py-2 focus:ring-2 focus:ring-primary focus:border-transparent">
            </div>

            <!-- Clear Filters (shown when any filter is active) -->
            <?php if ($filter_user !== 'All' || !empty($filter_date_from) || !empty($filter_date_to)): ?>
                <a href="?action=<?= urlencode($filter_action) ?>&resource=<?= urlencode($filter_resource) ?>&search=<?= urlencode($search_term) ?>&limit=<?= $limit_param ?>"
                    class="flex items-center gap-1 text-xs text-slate-400 hover:text-red-400 transition-colors whitespace-nowrap">
                    <span class="material-symbols-outlined text-[15px]">close</span>
                    Clear date/user
                </a>
            <?php endif; ?>

            <!-- Top Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="flex items-center gap-2 ml-auto">
                    <span class="text-xs text-slate-500 mr-2">Page
                        <?= $page ?> of
                        <?= $total_pages ?>
                    </span>
                    <div
                        class="flex h-9 bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] rounded-xl overflow-hidden mr-2">
                        <a href="?page=<?= max(1, $page - 1) ?>&search=<?= urlencode($search_term) ?>&action=<?= urlencode($filter_action) ?>&resource=<?= urlencode($filter_resource) ?>&user=<?= urlencode($filter_user) ?>&date_from=<?= urlencode($filter_date_from) ?>&date_to=<?= urlencode($filter_date_to) ?>&limit=<?= $limit ?>"
                            class="px-3 flex items-center justify-center hover:bg-white/5 text-slate-400 hover:text-slate-900 dark:hover:text-white transition-colors border-r border-slate-200 dark:border-[#232b3d] <?= $page <= 1 ? 'pointer-events-none opacity-50' : '' ?>">
                            <span class="material-symbols-outlined text-[18px]">chevron_left</span>
                        </a>
                        <a href="?page=<?= min($total_pages, $page + 1) ?>&search=<?= urlencode($search_term) ?>&action=<?= urlencode($filter_action) ?>&resource=<?= urlencode($filter_resource) ?>&user=<?= urlencode($filter_user) ?>&date_from=<?= urlencode($filter_date_from) ?>&date_to=<?= urlencode($filter_date_to) ?>&limit=<?= $limit ?>"
                            class="px-3 flex items-center justify-center hover:bg-white/5 text-slate-400 hover:text-slate-900 dark:hover:text-white transition-colors <?= $page >= $total_pages ? 'pointer-events-none opacity-50' : '' ?>">
                            <span class="material-symbols-outlined text-[18px]">chevron_right</span>
                        </a>
                    </div>
                    <select name="limit" onchange="this.form.submit()"
                        class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] text-slate-400 text-xs rounded-xl px-4 py-2 focus:ring-2 focus:ring-primary">
                        <option value="50" <?= $limit_param == '50' ? 'selected' : '' ?>>Show: 50</option>
                        <option value="100" <?= $limit_param == '100' ? 'selected' : '' ?>>Show: 100</option>
                        <option value="200" <?= $limit_param == '200' ? 'selected' : '' ?>>Show: 200</option>
                        <option value="500" <?= $limit_param == '500' ? 'selected' : '' ?>>Show: 500</option>
                        <option value="all" <?= $limit_param == 'all' ? 'selected' : '' ?>>Show: All</option>
                    </select>
                </div>
            <?php else: ?>
                <select name="limit" onchange="this.form.submit()"
                    class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] text-slate-400 text-xs rounded-xl px-4 py-2 focus:ring-2 focus:ring-primary ml-auto">
                    <option value="50" <?= $limit_param == '50' ? 'selected' : '' ?>>Show: 50</option>
                    <option value="100" <?= $limit_param == '100' ? 'selected' : '' ?>>Show: 100</option>
                    <option value="200" <?= $limit_param == '200' ? 'selected' : '' ?>>Show: 200</option>
                    <option value="500" <?= $limit_param == '500' ? 'selected' : '' ?>>Show: 500</option>
                    <option value="all" <?= $limit_param == 'all' ? 'selected' : '' ?>>Show: All</option>
                </select>
            <?php endif; ?>
        </form>
    </div>

    <!-- Table -->
    <div
        class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] rounded-2xl overflow-hidden shadow-sm">
        <div class="overflow-x-auto custom-scrollbar">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-white dark:bg-[#1a2130] border-b border-slate-200 dark:border-[#232b3d]">
                        <th class="px-6 py-1 text-xs font-bold text-slate-500 uppercase whitespace-nowrap">Timestamp
                        </th>
                        <th class="px-6 py-1 text-xs font-bold text-slate-500 uppercase whitespace-nowrap">User</th>
                        <th class="px-6 py-1 text-xs font-bold text-slate-500 uppercase whitespace-nowrap">Action
                        </th>
                        <th class="px-6 py-1 text-xs font-bold text-slate-500 uppercase whitespace-nowrap">Resource
                        </th>
                        <th class="px-6 py-1 text-xs font-bold text-slate-500 uppercase max-w-md">Details
                        </th>
                        <th class="px-6 py-1 text-xs font-bold text-slate-500 uppercase whitespace-nowrap">IP
                            Address</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#232b3d]/50">
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-slate-500">No logs found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <tr class="hover:bg-white/5 transition-colors border-b border-slate-200 dark:border-[#232b3d]/30">
                                <td class="px-6 py-1 text-sm text-slate-600 dark:text-slate-400 font-mono whitespace-nowrap">
                                    <?= date('M d, Y H:i:s', strtotime($log['created_at'])) ?>
                                </td>
                                <td class="px-6 py-1 text-sm text-slate-900 dark:text-white whitespace-nowrap">
                                    <?= htmlspecialchars($log['username'] ?? 'System') ?>
                                </td>
                                <td class="px-6 py-1 text-sm text-slate-700 dark:text-slate-300 whitespace-nowrap">
                                    <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $log['action_type']))) ?>
                                </td>
                                <td class="px-6 py-1 text-sm text-slate-600 dark:text-slate-400 whitespace-nowrap">
                                    <span class="px-2 py-1 bg-primary/10 text-primary rounded text-xs font-bold uppercase">
                                        <?= htmlspecialchars($log['resource_type'] ?? 'System') ?>
                                    </span>
                                </td>
                                <td class="px-6 py-2 text-xs text-slate-600 dark:text-slate-400 max-w-sm">
                                    <span class="block whitespace-pre-wrap break-words leading-relaxed"><?= nl2br(htmlspecialchars($log['details'] ?? '')) ?></span>
                                </td>
                                <td class="px-6 py-1 text-sm font-mono text-slate-500 whitespace-nowrap">
                                    <?= htmlspecialchars($log['ip_address'] ?? '-') ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <div class="mt-6 flex justify-center gap-2">
            <?php if ($page > 1): ?>
                <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search_term) ?>&action=<?= urlencode($filter_action) ?>&resource=<?= urlencode($filter_resource) ?>&user=<?= urlencode($filter_user) ?>&date_from=<?= urlencode($filter_date_from) ?>&date_to=<?= urlencode($filter_date_to) ?>&limit=<?= $limit_param ?>"
                    class="px-3 py-2 bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] text-slate-600 dark:text-slate-400 text-xs rounded-lg hover:text-slate-900 dark:hover:text-white">Previous</a>
            <?php endif; ?>

            <span class="px-3 py-2 bg-primary/10 border border-primary/20 text-primary text-xs rounded-lg">
                Page
                <?= $page ?> of
                <?= $total_pages ?>
            </span>

            <?php if ($page < $total_pages): ?>
                <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search_term) ?>&action=<?= urlencode($filter_action) ?>&resource=<?= urlencode($filter_resource) ?>&user=<?= urlencode($filter_user) ?>&date_from=<?= urlencode($filter_date_from) ?>&date_to=<?= urlencode($filter_date_to) ?>&limit=<?= $limit_param ?>"
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
            }, 500); // 500ms debounce
        });

        // Place cursor at the end of text on load
        if (searchInput.value) {
            const val = searchInput.value;
            searchInput.value = '';
            searchInput.value = val;
            searchInput.focus();
        }
    }

    // Auto-submit when date pickers change
    const dateFrom = document.getElementById('dateFrom');
    const dateTo   = document.getElementById('dateTo');
    if (dateFrom) dateFrom.addEventListener('change', () => dateFrom.form.submit());
    if (dateTo)   dateTo.addEventListener('change',   () => dateTo.form.submit());
</script>


<?php require_once '../../includes/footer.php'; ?>