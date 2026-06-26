<?php
require_once '../../config.php';
requireLogin();

// ── Auto-create table if not yet migrated ────────────────────────────────────
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ihoms_links (
            id          SERIAL PRIMARY KEY,
            system_name VARCHAR(255) NOT NULL,
            url         TEXT NOT NULL,
            category    VARCHAR(100) DEFAULT '',
            description TEXT DEFAULT '',
            status      VARCHAR(50) DEFAULT 'Active',
            remarks     TEXT DEFAULT '',
            created_at  TIMESTAMP DEFAULT NOW(),
            updated_at  TIMESTAMP DEFAULT NOW()
        );
        CREATE INDEX IF NOT EXISTS idx_ihoms_links_category ON ihoms_links(category);
        CREATE INDEX IF NOT EXISTS idx_ihoms_links_status   ON ihoms_links(status);
    ");
} catch (Exception $e) {
    // Silently ignore if table already exists or index already exists
}

$success_msg = $error_msg = '';

// ── CRUD ─────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $error_msg = "Security validation failed. Please refresh and try again.";
    } elseif ($_POST['action'] === 'add_link') {
        $system_name = trim($_POST['system_name'] ?? '');
        $url = trim($_POST['url'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $status = $_POST['status'] ?? 'Active';
        $remarks = trim($_POST['remarks'] ?? '');

        if (empty($system_name) || empty($url)) {
            $error_msg = "System Name and URL are required.";
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO ihoms_links (system_name, url, category, description, status, remarks, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())");
                $stmt->execute([$system_name, $url, $category, $description, $status, $remarks]);
                $new_link_id = $pdo->lastInsertId();
                $add_lnk_detail = "Added IS Link | Name: $system_name | URL: $url | Category: $category | Status: $status";
                if ($description)
                    $add_lnk_detail .= " | Desc: $description";
                if ($remarks)
                    $add_lnk_detail .= " | Remarks: $remarks";
                logAudit($pdo, 'add_is_link', $add_lnk_detail, 'ihoms_links', $new_link_id);
                /* logChangelog($pdo, 'feature', 'IS Links', "Added IS Link: $system_name", "URL: $url | Category: $category | Status: $status"); removed */
                $success_msg = "IS Link '$system_name' added successfully.";
            } catch (Exception $e) {
                $error_msg = "Error adding link: " . $e->getMessage();
            }
        }
    } elseif ($_POST['action'] === 'edit_link') {
        $id = (int) ($_POST['id'] ?? 0);
        $system_name = trim($_POST['system_name'] ?? '');
        $url = trim($_POST['url'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $status = $_POST['status'] ?? 'Active';
        $remarks = trim($_POST['remarks'] ?? '');

        if (empty($system_name) || empty($url)) {
            $error_msg = "System Name and URL are required.";
        } elseif ($id > 0) {
            try {
                $stmt = $pdo->prepare("UPDATE ihoms_links SET system_name=?, url=?, category=?, description=?, status=?, remarks=?, updated_at=NOW() WHERE id=?");
                $stmt->execute([$system_name, $url, $category, $description, $status, $remarks, $id]);
                $upd_lnk_detail = "Updated IS Link | ID: $id | Name: $system_name | URL: $url | Category: $category | Status: $status";
                if ($description)
                    $upd_lnk_detail .= " | Desc: $description";
                if ($remarks)
                    $upd_lnk_detail .= " | Remarks: $remarks";
                logAudit($pdo, 'update_is_link', $upd_lnk_detail, 'ihoms_links', $id);
                /* logChangelog($pdo, 'enhancement', 'IS Links', "Updated IS Link: $system_name", "URL: $url | Category: $category | Status: $status"); removed */
                $success_msg = "IS Link updated successfully.";
            } catch (Exception $e) {
                $error_msg = "Error updating link: " . $e->getMessage();
            }
        }
    } elseif ($_POST['action'] === 'delete_link') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $snap = $pdo->prepare("SELECT * FROM ihoms_links WHERE id = ?");
                $snap->execute([$id]);
                $del_lnk = $snap->fetch(PDO::FETCH_ASSOC);
                $del_lnk_detail = "Deleted IS Link ID $id";
                if ($del_lnk) {
                    $del_lnk_detail = "Deleted IS Link | ID: $id | Name: {$del_lnk['system_name']} | URL: {$del_lnk['url']} | Category: {$del_lnk['category']} | Status: {$del_lnk['status']}";
                    if ($del_lnk['remarks'])
                        $del_lnk_detail .= " | Remarks: {$del_lnk['remarks']}";
                }
                $stmt = $pdo->prepare("DELETE FROM ihoms_links WHERE id = ?");
                $stmt->execute([$id]);
                logAudit($pdo, 'delete_is_link', $del_lnk_detail, 'ihoms_links', $id);
                /* if ($del_lnk) logChangelog($pdo, 'bugfix', 'IS Links', "Removed IS Link: ...", ...); removed — data changes not tracked in changelog */
                $success_msg = "IS Link deleted successfully.";
            } catch (Exception $e) {
                $error_msg = "Error deleting link: " . $e->getMessage();
            }
        }
    }
}

// ── Filters ───────────────────────────────────────────────────────────────────
$search_term = $_GET['search'] ?? '';
$filter_category = $_GET['category'] ?? 'All';
$filter_status = $_GET['status'] ?? 'All';
$view_mode = $_GET['view'] ?? 'card'; // 'card' or 'table'

$where_clauses = ["1=1"];
$params = [];

if ($search_term) {
    $where_clauses[] = "(system_name ILIKE ? OR url ILIKE ? OR category ILIKE ? OR description ILIKE ? OR remarks ILIKE ?)";
    $term = "%$search_term%";
    for ($i = 0; $i < 5; $i++)
        $params[] = $term;
}

if ($filter_category !== 'All' && !empty($filter_category)) {
    $where_clauses[] = "category = ?";
    $params[] = $filter_category;
}

if ($filter_status !== 'All' && !empty($filter_status)) {
    $where_clauses[] = "status = ?";
    $params[] = $filter_status;
}

$where_sql = implode(' AND ', $where_clauses);

// Fetch all distinct categories for filter dropdown
try {
    $categories = $pdo->query("SELECT DISTINCT category FROM ihoms_links WHERE category IS NOT NULL AND category != '' ORDER BY category ASC")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $categories = [];
}

// Fetch links
try {
    $stmt = $pdo->prepare("SELECT * FROM ihoms_links WHERE $where_sql ORDER BY category ASC, system_name ASC");
    $stmt->execute($params);
    $links = $stmt->fetchAll();
} catch (Exception $e) {
    $links = [];
    $error_msg = "Error loading data: " . $e->getMessage();
}

// Stats
try {
    $total_links = $pdo->query("SELECT COUNT(*) FROM ihoms_links")->fetchColumn();
    $active_links = $pdo->query("SELECT COUNT(*) FROM ihoms_links WHERE status = 'Active'")->fetchColumn();
} catch (Exception $e) {
    $total_links = $active_links = 0;
}

$page_title = "IS Links";
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
?>

<div class="p-4 md:p-8 flex-1 overflow-y-auto custom-scrollbar relative z-10 bg-slate-50 dark:bg-background-dark">
    <header
        class="-mt-4 -mx-4 md:-mt-8 md:-mx-8 px-4 md:px-8 pt-4 md:pt-8 pb-6 mb-8 flex flex-col md:flex-row items-start md:items-end justify-between gap-4 sticky top-0 bg-slate-50 dark:bg-background-dark z-20 border-b border-slate-200 dark:border-[#232b3d]">
        <div>
            <h2 class="text-2xl font-bold text-slate-900 dark:text-white">IS Links</h2>
            <p class="text-sm text-slate-500 mt-1">IHOMS Information System URLs & Links</p>
        </div>
        <div class="flex items-center gap-2 md:gap-3 flex-wrap">
            <!-- View Toggle -->
            <div
                class="flex h-9 bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] rounded-xl overflow-hidden">
                <a href="?view=card&search=<?= urlencode($search_term) ?>&category=<?= urlencode($filter_category) ?>&status=<?= urlencode($filter_status) ?>"
                    class="px-3 flex items-center gap-1.5 text-xs font-bold transition-colors <?= $view_mode === 'card' ? 'bg-primary text-white' : 'text-slate-400 hover:text-slate-900 dark:hover:text-white' ?>">
                    <span class="material-symbols-outlined text-[16px]">grid_view</span> Cards
                </a>
                <a href="?view=table&search=<?= urlencode($search_term) ?>&category=<?= urlencode($filter_category) ?>&status=<?= urlencode($filter_status) ?>"
                    class="px-3 flex items-center gap-1.5 text-xs font-bold transition-colors <?= $view_mode === 'table' ? 'bg-primary text-white' : 'text-slate-400 hover:text-slate-900 dark:hover:text-white' ?> border-l border-slate-200 dark:border-[#232b3d]">
                    <span class="material-symbols-outlined text-[16px]">table_rows</span> Table
                </a>
            </div>
            <button onclick="toggleModal('addLinkModal')"
                class="flex items-center justify-center rounded-lg h-9 px-4 bg-primary text-white hover:bg-primary/90 transition-colors text-xs font-bold shadow-lg shadow-primary/20">
                <span class="material-symbols-outlined text-[18px] mr-2">add</span> Add IS Link
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
            <div class="text-slate-500 text-xs font-bold uppercase tracking-wider mb-1">Total Links</div>
            <div class="text-2xl font-bold text-slate-900 dark:text-white">
                <?= number_format($total_links) ?>
            </div>
        </div>
        <div class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] p-4 rounded-xl">
            <div class="text-slate-500 text-xs font-bold uppercase tracking-wider mb-1">Active</div>
            <div class="text-2xl font-bold text-emerald-500">
                <?= number_format($active_links) ?>
            </div>
        </div>
        <div class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] p-4 rounded-xl">
            <div class="text-slate-500 text-xs font-bold uppercase tracking-wider mb-1">Categories</div>
            <div class="text-2xl font-bold text-slate-700 dark:text-slate-200">
                <?= number_format(count($categories)) ?>
            </div>
        </div>
    </div>

    <!-- Control Bar -->
    <div class="mb-6 flex gap-3">
        <form method="GET" class="flex items-center gap-3 flex-wrap w-full">
            <input type="hidden" name="view" value="<?= htmlspecialchars($view_mode) ?>">
            <div class="relative group">
                <span
                    class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-[18px] text-slate-500">search</span>
                <input type="text" name="search" id="searchInput" value="<?= htmlspecialchars($search_term) ?>"
                    placeholder="Search system name, URL, category..."
                    class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] text-slate-700 dark:text-slate-200 text-xs rounded-xl pl-9 pr-4 py-2 focus:ring-2 focus:ring-primary focus:border-transparent w-72">
            </div>

            <input type="hidden" name="category" id="hid_category" value="<?= htmlspecialchars($filter_category) ?>">
            <input type="hidden" name="status"   id="hid_il_status" value="<?= htmlspecialchars($filter_status) ?>">
            <?php
            $il_ac=''; $il_av='';
            if($filter_category!=='All'&&$filter_category!==''){$il_ac='category'; $il_av=$filter_category;}
            elseif($filter_status!=='All'&&$filter_status!=='') {$il_ac='il_status';$il_av=$filter_status;}
            ?>
            <select id="il_cat" onchange="ilOnCat(this.value)"
                class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] text-slate-600 dark:text-slate-300 text-xs rounded-xl px-4 py-2 focus:ring-2 focus:ring-primary">
                <option value="">Filter by...</option>
                <option value="category"  <?= $il_ac==='category' ?'selected':'' ?>>Category</option>
                <option value="il_status" <?= $il_ac==='il_status'?'selected':'' ?>>Status</option>
            </select>
            <select id="il_val" onchange="ilApply(this.value)"
                class="<?= $il_ac?'':'hidden' ?> bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] text-slate-600 dark:text-slate-300 text-xs rounded-xl px-4 py-2 focus:ring-2 focus:ring-primary">
            </select>
            <?php if($il_ac): ?>
            <div class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-primary/10 border border-primary/30 text-primary text-xs font-semibold rounded-xl">
                <span class="material-symbols-outlined text-[13px]">filter_alt</span>
                <?= htmlspecialchars($il_av) ?>
                <a href="?search=<?= urlencode($search_term) ?>&view=<?= urlencode($view_mode) ?>" class="ml-1 text-primary/60 hover:text-red-500" title="Clear">
                    <span class="material-symbols-outlined text-[14px]">close</span>
                </a>
            </div>
            <?php endif; ?>
            <script>
            const _ilO={category:<?= json_encode(array_values($categories)) ?>,il_status:['Active','Inactive']};
            const _ilAC=<?= json_encode($il_ac) ?>,_ilAV=<?= json_encode($il_av) ?>;
            const _ilHidMap={category:'hid_category',il_status:'hid_il_status'};
            function ilOnCat(c){
                const v=document.getElementById('il_val');
                if(!c){v.classList.add('hidden');return;}
                Object.keys(_ilHidMap).forEach(function(k){if(k!==c){const h=document.getElementById(_ilHidMap[k]);if(h)h.value='All';}});
                v.innerHTML='<option value="All">All</option>';
                (_ilO[c]||[]).forEach(function(o){const e=document.createElement('option');e.value=o;e.textContent=o;if(c===_ilAC&&o===_ilAV)e.selected=true;v.appendChild(e);});
                v.classList.remove('hidden');
            }
            function ilApply(val){
                const c=document.getElementById('il_cat').value;if(!c)return;
                const h=document.getElementById(_ilHidMap[c]);if(h)h.value=val;
                document.getElementById('il_cat').closest('form').submit();
            }
            document.addEventListener('DOMContentLoaded',function(){if(_ilAC)ilOnCat(_ilAC);});
            </script>
        </form>
    </div>

    <?php if ($view_mode === 'card'): ?>
        <!-- ── CARD VIEW ─────────────────────────────────────────────────────────── -->
        <?php if (empty($links)): ?>
            <div class="text-center py-24 text-slate-400">
                <span class="material-symbols-outlined text-[64px] mb-4 block opacity-30">link_off</span>
                <p class="text-lg font-medium">No IS links found.</p>
                <p class="text-sm mt-1">Add your first information system link using the button above.</p>
            </div>
        <?php else: ?>
            <?php
            // Group by category
            $grouped = [];
            foreach ($links as $link) {
                $cat = $link['category'] ?: 'Uncategorized';
                $grouped[$cat][] = $link;
            }
            ksort($grouped);
            ?>
            <?php foreach ($grouped as $cat => $cat_links): ?>
                <div class="mb-8">
                    <h3 class="text-xs font-bold text-slate-500 uppercase tracking-widest mb-4 flex items-center gap-2">
                        <span class="material-symbols-outlined text-[14px]">folder</span>
                        <?= htmlspecialchars($cat) ?>
                        <span class="bg-slate-100 dark:bg-[#232b3d] text-slate-500 rounded-full px-2 py-0.5 text-[10px]">
                            <?= count($cat_links) ?>
                        </span>
                    </h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                        <?php foreach ($cat_links as $link): ?>
                            <div
                                class="group bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] rounded-2xl p-5 hover:border-primary/40 hover:shadow-lg hover:shadow-primary/5 transition-all duration-200 flex flex-col">
                                <!-- Top Row -->
                                <div class="flex items-start justify-between mb-3">
                                    <div
                                        class="bg-primary/10 size-10 rounded-xl flex items-center justify-center text-primary flex-shrink-0">
                                        <span class="material-symbols-outlined text-[20px]">language</span>
                                    </div>
                                    <div class="flex items-center gap-1">
                                        <?php if (strtolower($link['status']) === 'active'): ?>
                                            <span
                                                class="inline-flex items-center gap-1 text-[10px] font-bold text-emerald-500 bg-emerald-500/10 px-2 py-0.5 rounded-full">
                                                <span class="size-1.5 rounded-full bg-emerald-500"></span>Active
                                            </span>
                                        <?php else: ?>
                                            <span
                                                class="inline-flex items-center gap-1 text-[10px] font-bold text-slate-400 bg-slate-100 dark:bg-[#232b3d] px-2 py-0.5 rounded-full">
                                                <span class="size-1.5 rounded-full bg-slate-400"></span>Inactive
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Name & Description -->
                                <h4 class="text-sm font-bold text-slate-900 dark:text-white mb-1 truncate"
                                    title="<?= htmlspecialchars($link['system_name']) ?>">
                                    <?= htmlspecialchars($link['system_name']) ?>
                                </h4>
                                <?php if (!empty($link['description'])): ?>
                                    <p class="text-xs text-slate-500 mb-3 line-clamp-2 flex-1">
                                        <?= htmlspecialchars($link['description']) ?>
                                    </p>
                                <?php else: ?>
                                    <p class="flex-1"></p>
                                <?php endif; ?>

                                <!-- URL preview -->
                                <div class="text-[10px] text-slate-400 font-mono truncate mb-4"
                                    title="<?= htmlspecialchars($link['url']) ?>">
                                    <?= htmlspecialchars($link['url']) ?>
                                </div>

                                <!-- Actions -->
                                <div class="flex items-center gap-2 mt-auto">
                                    <a href="<?= htmlspecialchars($link['url']) ?>" target="_blank" rel="noopener noreferrer"
                                        class="flex-1 flex items-center justify-center gap-1.5 h-8 rounded-lg bg-primary text-white text-xs font-bold hover:bg-primary/90 transition-colors">
                                        <span class="material-symbols-outlined text-[15px]">open_in_new</span> Open
                                    </a>
                                    <button onclick='openEditModal(<?= json_encode($link) ?>)'
                                        class="h-8 w-8 flex items-center justify-center rounded-lg hover:bg-primary/10 hover:text-primary text-slate-400 transition-colors">
                                        <span class="material-symbols-outlined text-[18px]">edit</span>
                                    </button>
                                    <form method="POST" onsubmit="return confirm('Delete this link?');" style="display:inline;">
                                        <?= getCsrfInput() ?>
                                        <input type="hidden" name="action" value="delete_link">
                                        <input type="hidden" name="id" value="<?= $link['id'] ?>">
                                        <button type="submit"
                                            class="h-8 w-8 flex items-center justify-center rounded-lg hover:bg-red-500/10 hover:text-red-500 text-slate-400 transition-colors">
                                            <span class="material-symbols-outlined text-[18px]">delete</span>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

    <?php else: ?>
        <!-- ── TABLE VIEW ────────────────────────────────────────────────────────── -->
        <div
            class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] rounded-2xl overflow-hidden shadow-sm">
            <div class="overflow-x-auto custom-scrollbar">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-white dark:bg-[#1a2130] border-b border-slate-200 dark:border-[#232b3d]">
                            <th class="px-4 py-3 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">System
                                Name</th>
                            <th class="px-4 py-3 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">URL</th>
                            <th class="px-4 py-3 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">Category
                            </th>
                            <th class="px-4 py-3 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">
                                Description</th>
                            <th class="px-4 py-3 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">Status
                            </th>
                            <th class="px-4 py-3 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">Remarks
                            </th>
                            <th
                                class="px-4 py-3 text-[10px] font-bold text-slate-500 uppercase text-right whitespace-nowrap">
                                Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#232b3d]/50">
                        <?php if (empty($links)): ?>
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center text-slate-500">No IS links found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($links as $link): ?>
                                <tr class="hover:bg-white/5 transition-colors text-xs">
                                    <td class="px-4 py-3 font-medium text-slate-900 dark:text-white whitespace-nowrap">
                                        <?= htmlspecialchars($link['system_name']) ?>
                                    </td>
                                    <td class="px-4 py-3 font-mono text-primary max-w-[280px] truncate"
                                        title="<?= htmlspecialchars($link['url']) ?>">
                                        <a href="<?= htmlspecialchars($link['url']) ?>" target="_blank" rel="noopener noreferrer"
                                            class="hover:underline flex items-center gap-1">
                                            <?= htmlspecialchars($link['url']) ?>
                                            <span class="material-symbols-outlined text-[12px]">open_in_new</span>
                                        </a>
                                    </td>
                                    <td class="px-4 py-3 text-slate-500 whitespace-nowrap">
                                        <?= htmlspecialchars($link['category'] ?: '-') ?>
                                    </td>
                                    <td class="px-4 py-3 text-slate-500 max-w-[200px] truncate"
                                        title="<?= htmlspecialchars($link['description'] ?? '') ?>">
                                        <?= htmlspecialchars($link['description'] ?: '-') ?>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <?php if (strtolower($link['status']) === 'active'): ?>
                                            <span
                                                class="inline-flex items-center gap-1 text-[10px] font-bold text-emerald-500 bg-emerald-500/10 px-2 py-0.5 rounded-full">
                                                <span class="size-1.5 rounded-full bg-emerald-500"></span>Active
                                            </span>
                                        <?php else: ?>
                                            <span
                                                class="inline-flex items-center gap-1 text-[10px] font-bold text-slate-400 bg-slate-100 dark:bg-[#232b3d] px-2 py-0.5 rounded-full">
                                                <span class="size-1.5 rounded-full bg-slate-400"></span>Inactive
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3 text-slate-500 max-w-[150px] truncate">
                                        <?= htmlspecialchars($link['remarks'] ?: '-') ?>
                                    </td>
                                    <td class="px-4 py-3 text-right flex items-center justify-end gap-1">
                                        <a href="<?= htmlspecialchars($link['url']) ?>" target="_blank" rel="noopener noreferrer"
                                            class="p-1 hover:bg-primary/10 hover:text-primary text-slate-500 rounded-lg"
                                            title="Open">
                                            <span class="material-symbols-outlined text-[18px]">open_in_new</span>
                                        </a>
                                        <button onclick='openEditModal(<?= json_encode($link) ?>)'
                                            class="p-1 hover:bg-primary/10 hover:text-primary text-slate-500 rounded-lg">
                                            <span class="material-symbols-outlined text-[18px]">edit</span>
                                        </button>
                                        <form method="POST" onsubmit="return confirm('Delete this link?');" style="display:inline;">
                                            <?= getCsrfInput() ?>
                                            <input type="hidden" name="action" value="delete_link">
                                            <input type="hidden" name="id" value="<?= $link['id'] ?>">
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
        </div>
    <?php endif; ?>
</div>

<!-- ── Modal: Add Link ──────────────────────────────────────────────────────── -->
<div id="addLinkModal" class="hidden fixed inset-0 z-50 overflow-y-auto" role="dialog">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-background-dark/80 backdrop-blur-sm" onclick="toggleModal('addLinkModal')"></div>
        <div
            class="relative bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] rounded-2xl max-w-lg w-full p-4 sm:p-6 shadow-2xl">
            <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-6">Add IS Link</h3>
            <form method="POST">
                <?= getCsrfInput() ?>
                <input type="hidden" name="action" value="add_link">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="col-span-2 flex flex-col">
                        <label class="text-[10px] text-slate-500 mb-1 ml-1">System Name <span
                                class="text-red-500">*</span></label>
                        <input type="text" name="system_name" placeholder="e.g. IHOMS Patient Portal" required
                            class="bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5 text-xs w-full">
                    </div>
                    <div class="col-span-2 flex flex-col">
                        <label class="text-[10px] text-slate-500 mb-1 ml-1">URL <span
                                class="text-red-500">*</span></label>
                        <input type="url" name="url" placeholder="https://..." required
                            class="bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5 text-xs w-full font-mono">
                    </div>
                    <div class="flex flex-col">
                        <label class="text-[10px] text-slate-500 mb-1 ml-1">Category</label>
                        <input type="text" name="category" placeholder="e.g. Clinical, Finance, Admin"
                            list="category-list"
                            class="bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5 text-xs w-full">
                        <datalist id="category-list">
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= htmlspecialchars($cat) ?>">
                                <?php endforeach; ?>
                        </datalist>
                    </div>
                    <div class="flex flex-col">
                        <label class="text-[10px] text-slate-500 mb-1 ml-1">Status</label>
                        <select name="status"
                            class="bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5 text-xs w-full">
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="col-span-2 flex flex-col">
                        <label class="text-[10px] text-slate-500 mb-1 ml-1">Description</label>
                        <textarea name="description" rows="2" placeholder="Brief description of the system..."
                            class="bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5 text-xs w-full resize-none"></textarea>
                    </div>
                    <div class="col-span-2 flex flex-col">
                        <label class="text-[10px] text-slate-500 mb-1 ml-1">Remarks</label>
                        <input type="text" name="remarks" placeholder="Optional remarks"
                            class="bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5 text-xs w-full">
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" onclick="toggleModal('addLinkModal')"
                        class="px-4 py-2 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white text-xs">Cancel</button>
                    <button type="submit" class="px-6 py-2 bg-primary text-white font-bold rounded-xl text-xs">Save
                        Link</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ── Modal: Edit Link ─────────────────────────────────────────────────────── -->
<div id="editLinkModal" class="hidden fixed inset-0 z-50 overflow-y-auto" role="dialog">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-background-dark/80 backdrop-blur-sm" onclick="toggleModal('editLinkModal')"></div>
        <div
            class="relative bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] rounded-2xl max-w-lg w-full p-4 sm:p-6 shadow-2xl">
            <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-6">Edit IS Link</h3>
            <form method="POST">
                <?= getCsrfInput() ?>
                <input type="hidden" name="action" value="edit_link">
                <input type="hidden" name="id" id="edit_id">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="col-span-2 flex flex-col">
                        <label class="text-[10px] text-slate-500 mb-1 ml-1">System Name <span
                                class="text-red-500">*</span></label>
                        <input type="text" name="system_name" id="edit_system_name" placeholder="System Name" required
                            class="bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5 text-xs w-full">
                    </div>
                    <div class="col-span-2 flex flex-col">
                        <label class="text-[10px] text-slate-500 mb-1 ml-1">URL <span
                                class="text-red-500">*</span></label>
                        <input type="url" name="url" id="edit_url" placeholder="https://..." required
                            class="bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5 text-xs w-full font-mono">
                    </div>
                    <div class="flex flex-col">
                        <label class="text-[10px] text-slate-500 mb-1 ml-1">Category</label>
                        <input type="text" name="category" id="edit_category" placeholder="Category"
                            list="category-list"
                            class="bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5 text-xs w-full">
                    </div>
                    <div class="flex flex-col">
                        <label class="text-[10px] text-slate-500 mb-1 ml-1">Status</label>
                        <select name="status" id="edit_status"
                            class="bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5 text-xs w-full">
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="col-span-2 flex flex-col">
                        <label class="text-[10px] text-slate-500 mb-1 ml-1">Description</label>
                        <textarea name="description" id="edit_description" rows="2"
                            class="bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5 text-xs w-full resize-none"></textarea>
                    </div>
                    <div class="col-span-2 flex flex-col">
                        <label class="text-[10px] text-slate-500 mb-1 ml-1">Remarks</label>
                        <input type="text" name="remarks" id="edit_remarks" placeholder="Optional remarks"
                            class="bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5 text-xs w-full">
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" onclick="toggleModal('editLinkModal')"
                        class="px-4 py-2 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white text-xs">Cancel</button>
                    <button type="submit" class="px-6 py-2 bg-primary text-white font-bold rounded-xl text-xs">Save
                        Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function openEditModal(item) {
        document.getElementById('edit_id').value = item.id || '';
        document.getElementById('edit_system_name').value = item.system_name || '';
        document.getElementById('edit_url').value = item.url || '';
        document.getElementById('edit_category').value = item.category || '';
        document.getElementById('edit_description').value = item.description || '';
        document.getElementById('edit_status').value = item.status || 'Active';
        document.getElementById('edit_remarks').value = item.remarks || '';
        toggleModal('editLinkModal');
    }
</script>

<?php require_once '../../includes/footer.php'; ?>