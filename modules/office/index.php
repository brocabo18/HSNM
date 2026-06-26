<?php
require_once '../../config.php';
requireLogin();

// --- CSV Export ---
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $stmt = $pdo->query("SELECT * FROM office_licenses ORDER BY id ASC");
    $items = $stmt->fetchAll();

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="office_licenses_' . date('Y-m-d_His') . '.csv"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Control Number', 'Department', 'MS Office Version', 'Product Key', 'Email', 'Password', 'Remarks', 'Created At']);

    foreach ($items as $item) {
        fputcsv($output, [
            escapeCsvField($item['control_number']),
            escapeCsvField($item['department']),
            escapeCsvField($item['ms_office_version']),
            escapeCsvField($item['product_key']),
            escapeCsvField($item['email']),
            escapeCsvField($item['password']),
            escapeCsvField($item['remarks']),
            escapeCsvField($item['created_at'])
        ]);
    }
    fclose($output);
    exit;
}

// --- Action Handling ---
$success_msg = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $error_msg = "Security validation failed.";
    } elseif ($_POST['action'] === 'sync_from_computers') {
        // Bulk UPSERT: pull ms_office_version + department from computers INTO office_licenses
        try {
            $upd = 0;
            $ins = 0;

            // Step 1 — Update existing office_licenses rows where control_number matches
            $update_sql = "
                UPDATE office_licenses ol
                SET ms_office_version = TRIM(c.microsoft_office),
                    department        = TRIM(c.department)
                FROM computers c
                WHERE TRIM(LOWER(ol.control_number)) = TRIM(LOWER(c.control_number))
                  AND c.microsoft_office IS NOT NULL
                  AND TRIM(c.microsoft_office) <> ''
            ";
            $upd = (int)$pdo->exec($update_sql);

            // Step 2 — Insert new rows for computers that have MS Office but NO office_licenses record yet
            $insert_sql = "
                INSERT INTO office_licenses (control_number, department, ms_office_version, email, remarks)
                SELECT
                    TRIM(c.control_number),
                    TRIM(c.department),
                    TRIM(c.microsoft_office),
                    COALESCE(TRIM(c.ms_office_email), ''),
                    'Auto-synced from Computer Inventory'
                FROM computers c
                LEFT JOIN office_licenses ol
                    ON TRIM(LOWER(ol.control_number)) = TRIM(LOWER(c.control_number))
                WHERE ol.id IS NULL
                  AND c.microsoft_office IS NOT NULL
                  AND TRIM(c.microsoft_office) <> ''
                  AND c.control_number IS NOT NULL
                  AND TRIM(c.control_number) <> ''
            ";
            $ins = (int)$pdo->exec($insert_sql);

            $total = $upd + $ins;
            logAudit($pdo, 'sync_office', "Bulk sync from Computer Inventory — $upd updated, $ins inserted", 'sync');
            /* logChangelog($pdo, 'enhancement', 'MS Office Licenses', 'Sync MS Office Versions from Computer Inventory',
                "..."); removed — data changes not tracked in changelog */

            $success_msg = "Sync complete! $upd record(s) updated, $ins new record(s) created — $total total affected.";
        } catch (Exception $e) {
            $error_msg = "Sync failed: " . $e->getMessage();
        }

    } elseif ($_POST['action'] === 'add_license') {
        $control_num = $_POST['control_number'] ?? '';
        $dept = $_POST['department'] ?? '';
        $ver = $_POST['ms_office_version'] ?? '';
        $key = $_POST['product_key'] ?? '';
        $email = $_POST['email'] ?? '';
        $pass = $_POST['password'] ?? '';
        $remarks = $_POST['remarks'] ?? '';

        try {
            $stmt = $pdo->prepare("INSERT INTO office_licenses (control_number, department, ms_office_version, product_key, email, password, remarks) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$control_num, $dept, $ver, $key, $email, $pass, $remarks]);
            $new_lic_id = $pdo->lastInsertId();
            $add_lic_detail = "Added Office License | Control #: $control_num | Dept: $dept | Version: $ver | Email: $email";
            if ($remarks)
                $add_lic_detail .= " | Remarks: $remarks";
            logAudit($pdo, 'add_license', $add_lic_detail, 'office', $new_lic_id);
            $success_msg = "License added successfully.";

            // Sync email AND ms_office_version back to Computer Inventory
            try {
                $sync_stmt = $pdo->prepare("UPDATE computers SET ms_office_email = ?, microsoft_office = ? WHERE control_number = ?");
                $sync_stmt->execute([$email, $ver, $control_num]);
                $synced = $sync_stmt->rowCount();
                if ($synced > 0) {
                    logAudit($pdo, 'sync_email', "Synced email + MS Office version to $synced computer(s) for $control_num", 'sync');
                }
            } catch (Exception $sync_e) {
                error_log("Email sync failed: " . $sync_e->getMessage());
            }
        } catch (Exception $e) {
            $error_msg = "Error adding license: " . $e->getMessage();
        }

    } elseif ($_POST['action'] === 'edit_license') {
        $id = $_POST['id'] ?? 0;
        $control_num = $_POST['control_number'] ?? '';
        $dept = $_POST['department'] ?? '';
        $ver = $_POST['ms_office_version'] ?? '';
        $key = $_POST['product_key'] ?? '';
        $email = $_POST['email'] ?? '';
        $pass = $_POST['password'] ?? '';
        $remarks = $_POST['remarks'] ?? '';

        if ($id) {
            try {
                // Snapshot old record for field-level diff
                $snap_ol = $pdo->prepare("SELECT * FROM office_licenses WHERE id = ?");
                $snap_ol->execute([$id]);
                $old_ol = $snap_ol->fetch(PDO::FETCH_ASSOC) ?: [];

                $stmt = $pdo->prepare("UPDATE office_licenses SET control_number=?, department=?, ms_office_version=?, product_key=?, email=?, password=?, remarks=? WHERE id=?");
                $stmt->execute([$control_num, $dept, $ver, $key, $email, $pass, $remarks, $id]);

                $ol_labels = [
                    'control_number' => 'Control #', 'department' => 'Department',
                    'ms_office_version' => 'Version', 'product_key' => 'Product Key',
                    'email' => 'Email', 'remarks' => 'Remarks',
                ];
                $new_ol = [
                    'control_number' => $control_num, 'department' => $dept,
                    'ms_office_version' => $ver, 'product_key' => $key,
                    'email' => $email, 'remarks' => $remarks,
                ];
                $ol_changes = [];
                foreach ($ol_labels as $f => $lbl) {
                    $o = trim((string)($old_ol[$f] ?? '')); $n = trim((string)($new_ol[$f] ?? ''));
                    if ($o !== $n) $ol_changes[] = "{$lbl}: \"{$o}\" → \"{$n}\"";
                }
                $upd_lic_detail = empty($ol_changes)
                    ? "Updated Office License (no field changes) | ID: $id | Control #: $control_num"
                    : "Updated Office License | Control #: $control_num | " . implode(' | ', $ol_changes);
                logAudit($pdo, 'update_license', $upd_lic_detail, 'office', $id);
                $success_msg = "License updated successfully.";


                // Sync email AND ms_office_version back to Computer Inventory
                try {
                    $sync_stmt = $pdo->prepare("UPDATE computers SET ms_office_email = ?, microsoft_office = ? WHERE control_number = ?");
                    $sync_stmt->execute([$email, $ver, $control_num]);
                    $synced = $sync_stmt->rowCount();
                    if ($synced > 0) {
                        logAudit($pdo, 'sync_email', "Synced email + MS Office version to $synced computer(s) for $control_num", 'sync');
                    }
                } catch (Exception $sync_e) {
                    error_log("Email sync failed: " . $sync_e->getMessage());
                }
            } catch (Exception $e) {
                $error_msg = "Error updating license: " . $e->getMessage();
            }
        }
    } elseif ($_POST['action'] === 'delete_license') {
        $id = $_POST['id'] ?? 0;
        if ($id) {
            try {
                $snap = $pdo->prepare("SELECT * FROM office_licenses WHERE id = ?");
                $snap->execute([$id]);
                $del_lic = $snap->fetch(PDO::FETCH_ASSOC);
                $del_lic_detail = "Deleted Office License ID $id";
                if ($del_lic) {
                    $del_lic_detail = "Deleted Office License | ID: $id | Control #: {$del_lic['control_number']} | Dept: {$del_lic['department']} | Version: {$del_lic['ms_office_version']} | Email: {$del_lic['email']}";
                    if ($del_lic['remarks'])
                        $del_lic_detail .= " | Remarks: {$del_lic['remarks']}";
                }
                $stmt = $pdo->prepare("DELETE FROM office_licenses WHERE id = ?");
                $stmt->execute([$id]);
                logAudit($pdo, 'delete_license', $del_lic_detail, 'office', $id);
                $success_msg = "License deleted successfully.";
            } catch (Exception $e) {
                $error_msg = "Error deleting license: " . $e->getMessage();
            }
        }
    }
}

// --- Data Fetching & Search ---
// --- Data Fetching & Search ---
$search_term = $_GET['search'] ?? '';
$department_filter = $_GET['department'] ?? 'All';
$sort_by = $_GET['sort'] ?? 'id_desc';

$where_clauses = ["1=1"];
$params = [];

if ($search_term) {
    $where_clauses[] = "(control_number ILIKE ? OR department ILIKE ? OR ms_office_version ILIKE ? OR product_key ILIKE ? OR email ILIKE ? OR password ILIKE ? OR remarks ILIKE ?)";
    $term = "%$search_term%";
    $params = [$term, $term, $term, $term, $term, $term, $term];
}

$where_sql = implode(' AND ', $where_clauses);

// Sorting
$order_by = "id DESC"; // Default
switch ($sort_by) {
    case 'id_asc':
        $order_by = "id ASC";
        break;
    case 'control_asc':
        $order_by = "control_number ASC";
        break;
    case 'control_desc':
        $order_by = "control_number DESC";
        break;
    case 'dept_asc':
        $order_by = "department ASC";
        break;
    case 'dept_desc':
        $order_by = "department DESC";
        break;
    case 'id_desc':
    default:
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

$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM office_licenses WHERE $where_sql");
$count_stmt->execute($params);
$total_items = $count_stmt->fetchColumn();
$total_pages = ceil($total_items / $limit);

$stmt = $pdo->prepare("SELECT t.*, last_edit.username AS last_edited_by, last_edit.created_at AS last_edited_at
    FROM office_licenses t
    LEFT JOIN LATERAL (
        SELECT u.username, al.created_at
        FROM audit_logs al
        LEFT JOIN users u ON al.user_id = u.id
        WHERE al.resource_type = 'office' AND al.resource_id::text = t.id::text
        ORDER BY al.created_at DESC
        LIMIT 1
    ) last_edit ON true
    WHERE $where_sql ORDER BY $order_by LIMIT $limit OFFSET $offset");
$stmt->execute($params);
$licenses = $stmt->fetchAll();

// AJAX Search Handler
if (isset($_GET['ajax_search'])) {
    if (empty($licenses)) {
        echo '<tr><td colspan="7" class="px-6 py-12 text-center text-slate-500">No licenses found.</td></tr>';
    } else {
        foreach ($licenses as $item) {
            $json_item = htmlspecialchars(json_encode($item), ENT_QUOTES, 'UTF-8');
            $control = htmlspecialchars($item['control_number']);
            $dept = htmlspecialchars($item['department']);
            $ver = htmlspecialchars($item['ms_office_version']);
            $key = htmlspecialchars($item['product_key']);
            $email = htmlspecialchars($item['email']);
            $pass = htmlspecialchars($item['password']);
            $csrf = getCsrfInput();

            echo "
            <tr class='hover:bg-white/5 transition-colors text-[11px]' data-item='$json_item'>
                <td class='px-2 py-1 text-center'>
                    <input type='checkbox' class='item-checkbox rounded border-slate-300 dark:border-[#232b3d] text-primary focus:ring-primary h-4 w-4 bg-slate-50 dark:bg-[#1a2130]'>
                </td>
                <td class='px-2 py-1 font-mono text-slate-900 dark:text-white cursor-pointer hover:bg-primary/10 hover:text-primary transition-colors'
                    onclick='openEditModal($json_item)'
                    title='Click to edit'>
                    $control
                </td>
                <td class='px-2 py-1 text-slate-600 dark:text-slate-400'>$dept</td>
                <td class='px-2 py-1 text-slate-600 dark:text-slate-400'>$ver</td>
                <td class='px-2 py-1 text-slate-600 dark:text-slate-400 font-mono'>$key</td>
                <td class='px-2 py-1 text-slate-600 dark:text-slate-400'>$email</td>
                <td class='px-2 py-1 text-slate-600 dark:text-slate-400 font-mono'>$pass</td>
                <td class='px-2 py-1 text-right flex items-center justify-end gap-1'>
                    <button onclick='openEditModal($json_item)' class='p-1 hover:bg-primary/10 hover:text-primary text-slate-500 rounded-lg'>
                        <span class='material-symbols-outlined text-[18px]'>edit</span>
                    </button>
                    <form method='POST' onsubmit=\"return confirm('Delete this license?');\">
                        $csrf
                        <input type='hidden' name='action' value='delete_license'>
                        <input type='hidden' name='id' value='{$item['id']}'>
                        <button class='p-1 hover:bg-red-500/10 hover:text-red-500 text-slate-500 rounded-lg'>
                            <span class='material-symbols-outlined text-[18px]'>delete</span>
                        </button>
                    </form>
                </td>
            </tr>";
        }
    }
    exit;
}

$page_title = "MS Office Licenses";
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
?>

<div class="p-4 md:p-8 flex-1 overflow-y-auto custom-scrollbar relative z-10 bg-slate-50 dark:bg-background-dark">
    <header
        class="-mt-4 -mx-4 md:-mt-8 md:-mx-8 px-4 md:px-8 pt-4 md:pt-8 pb-6 mb-8 flex flex-col md:flex-row items-start md:items-end justify-between gap-4 sticky top-0 bg-slate-50 dark:bg-background-dark z-20 border-b border-slate-200 dark:border-[#232b3d]">
        <div>
            <h2 class="text-2xl font-bold text-slate-900 dark:text-white">MS Office Licenses</h2>
            <p class="text-sm text-slate-500 mt-1">Manage Microsoft Office product keys and accounts.</p>
        </div>
        <div class="no-print flex items-center gap-2 md:gap-3 flex-wrap">
            <a href="?export=csv"
                class="flex items-center justify-center rounded-lg h-9 px-4 bg-blue-600 text-white hover:bg-blue-700 transition-colors text-xs font-bold">
                <span class="material-symbols-outlined text-[18px] mr-2">download</span> Export CSV
            </a>
            <!-- Sync from Computer Inventory -->
            <form method="POST" onsubmit="return confirm('Sync MS Office versions from Computer Inventory into all matching license records?');" style="display:inline;">
                <?= getCsrfInput() ?>
                <input type="hidden" name="action" value="sync_from_computers">
                <button type="submit"
                    class="flex items-center justify-center rounded-lg h-9 px-4 bg-violet-600 text-white hover:bg-violet-700 transition-colors text-xs font-bold no-print">
                    <span class="material-symbols-outlined text-[18px] mr-2">sync</span> Sync from Computers
                </button>
            </form>
            <button onclick="printData()"
                class="flex items-center justify-center rounded-lg h-9 px-4 bg-slate-800 dark:bg-slate-700 text-white hover:bg-slate-700 dark:hover:bg-slate-600 transition-colors text-xs font-bold no-print">
                <span class="material-symbols-outlined text-[18px] mr-2">print</span> Print
            </button>
            <button onclick="toggleModal('addModal')"
                class="flex items-center justify-center rounded-lg h-9 px-4 bg-primary text-white hover:bg-primary/90 transition-colors text-xs font-bold shadow-lg shadow-primary/20 no-print">
                <span class="material-symbols-outlined text-[18px] mr-2">add</span> Add License
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
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-8">
        <div
            class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] p-4 rounded-xl text-center">
            <div class="text-slate-500 text-xs font-bold uppercase tracking-wider mb-1">Total Licenses</div>
            <div class="text-2xl font-bold text-slate-900 dark:text-white">
                <?= number_format($total_items) ?>
            </div>
        </div>
        <div
            class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] p-4 rounded-xl text-center">
            <div class="text-slate-500 text-xs font-bold uppercase tracking-wider mb-1">Showing</div>
            <div class="text-2xl font-bold text-emerald-500">
                <?= number_format(count($licenses)) ?>
            </div>
        </div>
    </div>

    <!-- Control Bar -->
    <div class="no-print mb-6 flex gap-3">
        <form method="GET" class="flex items-center gap-3 flex-wrap w-full">
            <div class="relative group">
                <span
                    class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-[18px] text-slate-500">search</span>
                <input type="text" name="search" value="<?= htmlspecialchars($search_term) ?>" placeholder="Search..."
                    class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] text-slate-700 dark:text-slate-200 text-xs rounded-xl pl-9 pr-4 py-2 focus:ring-2 focus:ring-primary focus:border-transparent w-64">
            </div>

            <select name="sort" onchange="this.form.submit()"
                class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] text-slate-400 text-xs rounded-xl px-4 py-2">
                <option value="id_desc" <?= $sort_by === 'id_desc' ? 'selected' : '' ?>>Newest First</option>
                <option value="id_asc" <?= $sort_by === 'id_asc' ? 'selected' : '' ?>>Oldest First</option>
                <option value="control_asc" <?= $sort_by === 'control_asc' ? 'selected' : '' ?>>Control# (A-Z)</option>
                <option value="control_desc" <?= $sort_by === 'control_desc' ? 'selected' : '' ?>>Control# (Z-A)</option>
                <option value="dept_asc" <?= $sort_by === 'dept_asc' ? 'selected' : '' ?>>Dept (A-Z)</option>
                <option value="dept_desc" <?= $sort_by === 'dept_desc' ? 'selected' : '' ?>>Dept (Z-A)</option>
            </select>

            <!-- Top Pagination -->
            <div class="flex items-center gap-2 ml-auto">
                <?php if ($total_pages > 1): ?>
                    <span class="text-xs text-slate-500 mr-2">Page <?= $page ?> of <?= $total_pages ?></span>
                    <div
                        class="flex h-9 bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] rounded-xl overflow-hidden mr-2">
                        <a href="?page=<?= max(1, $page - 1) ?>&search=<?= urlencode($search_term) ?>&sort=<?= $sort_by ?>&limit=<?= $limit_param ?>"
                            class="px-3 flex items-center justify-center hover:bg-white/5 text-slate-400 hover:text-slate-900 dark:hover:text-white transition-colors border-r border-slate-200 dark:border-[#232b3d] <?= $page <= 1 ? 'pointer-events-none opacity-50' : '' ?>">
                            <span class="material-symbols-outlined text-[18px]">chevron_left</span>
                        </a>
                        <a href="?page=<?= min($total_pages, $page + 1) ?>&search=<?= urlencode($search_term) ?>&sort=<?= $sort_by ?>&limit=<?= $limit_param ?>"
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
                        <th class="px-2 py-1 text-[10px] font-bold text-slate-500 uppercase w-10 text-center">
                            <input type="checkbox" onclick="toggleAll(this)"
                                class="rounded border-slate-300 dark:border-[#232b3d] text-primary focus:ring-primary h-4 w-4 bg-slate-50 dark:bg-[#1a2130]">
                        </th>
                        <th class="px-2 py-1 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">Control
                            Number</th>
                        <th class="px-2 py-1 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">
                            Department</th>
                        <th class="px-2 py-1 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">Version
                        </th>
                        <th class="px-2 py-1 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">Product
                            Key</th>
                        <th class="px-2 py-1 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">Email
                        </th>
                        <th class="px-2 py-1 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">Password
                        </th>
                        <th class="no-print px-2 py-1 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">Last Edited By</th>
                        <th class="px-2 py-1 text-[10px] font-bold text-slate-500 uppercase text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#232b3d]/50">
                    <?php if (empty($licenses)): ?>
                        <tr>
                            <td colspan="8" class="px-6 py-12 text-center text-slate-500">No licenses found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($licenses as $item): ?>
                            <tr class="hover:bg-white/5 transition-colors text-[11px]" data-item='<?= htmlspecialchars(json_encode($item), ENT_QUOTES, "UTF-8") ?>'>
                                <td class="px-2 py-1 text-center">
                                    <input type="checkbox"
                                        class="item-checkbox rounded border-slate-300 dark:border-[#232b3d] text-primary focus:ring-primary h-4 w-4 bg-slate-50 dark:bg-[#1a2130]">
                                </td>
                                <td class="px-2 py-1 font-mono text-slate-900 dark:text-white cursor-pointer hover:bg-primary/10 hover:text-primary transition-colors"
                                    onclick='openEditModal(<?= json_encode($item) ?>)' title="Click to edit">
                                    <?= htmlspecialchars($item['control_number']) ?>
                                </td>
                                <td class="px-2 py-1 text-slate-600 dark:text-slate-400">
                                    <?= htmlspecialchars($item['department']) ?>
                                </td>
                                <td class="px-2 py-1 text-slate-600 dark:text-slate-400">
                                    <?= htmlspecialchars($item['ms_office_version']) ?>
                                </td>
                                <td class="px-2 py-1 text-slate-600 dark:text-slate-400 font-mono">
                                    <?= htmlspecialchars($item['product_key']) ?>
                                </td>
                                <td class="px-2 py-1 text-slate-600 dark:text-slate-400"><?= htmlspecialchars($item['email']) ?>
                                </td>
                                <td class="px-2 py-1 text-slate-600 dark:text-slate-400 font-mono">
                                    <?= htmlspecialchars($item['password']) ?>
                                </td>
                                <td class="no-print px-2 py-1 whitespace-nowrap">
                                    <?php if (!empty($item['last_edited_by'])): ?>
                                        <div class="text-[10px] font-semibold text-slate-700 dark:text-slate-300"><?= htmlspecialchars($item['last_edited_by']) ?></div>
                                        <div class="text-[9px] text-slate-400"><?= date('M d, Y H:i', strtotime($item['last_edited_at'])) ?></div>
                                    <?php else: ?>
                                        <span class="text-slate-400 text-[10px]">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-2 py-1 text-right flex items-center justify-end gap-1">
                                    <button onclick='openEditModal(<?= json_encode($item) ?>)'
                                        class="p-1 hover:bg-primary/10 hover:text-primary text-slate-500 rounded-lg">
                                        <span class="material-symbols-outlined text-[18px]">edit</span>
                                    </button>
                                    <form method="POST" onsubmit="return confirm('Delete this license?');">
                                        <?= getCsrfInput() ?>
                                        <input type="hidden" name="action" value="delete_license">
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
                <div class="text-xs text-slate-500">Showing <span
                        class="font-bold text-slate-900 dark:text-white"><?= $offset + 1 ?></span> to <span
                        class="font-bold text-slate-900 dark:text-white"><?= min($offset + $limit, $total_items) ?></span>
                    of <span class="font-bold text-slate-900 dark:text-white"><?= $total_items ?></span> results</div>
                <div class="flex items-center gap-2">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search_term) ?>&sort=<?= $sort_by ?>&limit=<?= $limit_param ?>"
                            class="p-2 hover:bg-white/5 text-slate-400 hover:text-slate-900 dark:hover:text-white rounded-lg transition-colors">
                            <span class="material-symbols-outlined text-[18px]">chevron_left</span>
                        </a>
                    <?php endif; ?>
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search_term) ?>&sort=<?= $sort_by ?>&limit=<?= $limit_param ?>"
                            class="p-2 hover:bg-white/5 text-slate-400 hover:text-slate-900 dark:hover:text-white rounded-lg transition-colors">
                            <span class="material-symbols-outlined text-[18px]">chevron_right</span>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add Modal -->
<div id="addModal" class="hidden fixed inset-0 z-50 overflow-y-auto" role="dialog">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-background-dark/80 backdrop-blur-sm" onclick="toggleModal('addModal')"></div>
        <div
            class="relative bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] rounded-2xl max-w-2xl w-full p-4 sm:p-6 shadow-2xl">
            <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-6">Add New License</h3>
            <form method="POST">
                <?= getCsrfInput() ?>
                <input type="hidden" name="action" value="add_license">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <input type="text" name="control_number" placeholder="Control Number" required
                        class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                    <input type="text" name="department" placeholder="Department"
                        class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                    <input type="text" name="ms_office_version" placeholder="MS Office Version"
                        class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                    <input type="text" name="product_key" placeholder="Product Key"
                        class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                    <input type="email" name="email" placeholder="Email"
                        class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                    <input type="text" name="password" placeholder="Password"
                        class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                    <textarea name="remarks" placeholder="Remarks" rows="2"
                        class="col-span-2 w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5 resize-none"></textarea>
                </div>
                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" onclick="toggleModal('addModal')"
                        class="px-4 py-2 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white">Cancel</button>
                    <button type="submit" class="px-6 py-2 bg-primary text-white font-bold rounded-xl">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div id="editModal" class="hidden fixed inset-0 z-50 overflow-y-auto" role="dialog">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-background-dark/80 backdrop-blur-sm" onclick="toggleModal('editModal')"></div>
        <div
            class="relative bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] rounded-2xl max-w-2xl w-full p-4 sm:p-6 shadow-2xl">
            <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-6">Edit License</h3>
            <form method="POST">
                <?= getCsrfInput() ?>
                <input type="hidden" name="action" value="edit_license">
                <input type="hidden" name="id" id="edit_id">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Control Number</label>
                        <input type="text" name="control_number" id="edit_control" required
                            class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Department</label>
                        <input type="text" name="department" id="edit_dept"
                            class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">MS Office Version</label>
                        <input type="text" name="ms_office_version" id="edit_version"
                            class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Product Key</label>
                        <input type="text" name="product_key" id="edit_key"
                            class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Email</label>
                        <input type="email" name="email" id="edit_email"
                            class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Password</label>
                        <input type="text" name="password" id="edit_pass"
                            class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                    </div>
                    <div class="col-span-2">
                        <label class="block text-xs font-medium text-slate-400 mb-1">Remarks</label>
                        <textarea name="remarks" id="edit_remarks" rows="2"
                            class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5 resize-none"></textarea>
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" onclick="toggleModal('editModal')"
                        class="px-4 py-2 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white">Cancel</button>
                    <button type="submit" class="px-6 py-2 bg-primary text-white font-bold rounded-xl">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Live Search Sync with AbortController
    let searchTimeout;
    let searchController = null;
    const searchInput = document.querySelector('input[name="search"]');

    if (searchInput) {
        searchInput.addEventListener('input', function () {
            clearTimeout(searchTimeout);
            const form = searchInput.form;

            searchTimeout = setTimeout(() => {
                // Abort previous request if it's still running
                if (searchController) {
                    searchController.abort();
                }
                searchController = new AbortController();

                const params = new URLSearchParams(new FormData(form));
                params.set('ajax_search', '1');

                fetch(window.location.pathname + '?' + params.toString(), {
                    signal: searchController.signal
                })
                    .then(response => {
                        if (!response.ok) throw new Error('Network response was not ok');
                        return response.text();
                    })
                    .then(html => {
                        if (html.includes('<!DOCTYPE html>')) {
                            console.warn('Received full page instead of partial.');
                            return;
                        }

                        document.querySelector('tbody').innerHTML = html;

                        const urlParams = new URLSearchParams(new FormData(form));
                        window.history.replaceState({}, '', '?' + urlParams.toString());
                    })
                    .catch(err => {
                        if (err.name === 'AbortError') {
                            // console.log('Fetch aborted');
                        } else {
                            console.error('Search error:', err);
                        }
                    });
            }, 300);
        });
    }



    function openEditModal(item) {
        document.getElementById('edit_id').value = item.id;
        document.getElementById('edit_control').value = item.control_number;
        document.getElementById('edit_dept').value = item.department;
        document.getElementById('edit_version').value = item.ms_office_version;
        document.getElementById('edit_key').value = item.product_key;
        document.getElementById('edit_email').value = item.email;
        document.getElementById('edit_pass').value = item.password;
        document.getElementById('edit_remarks').value = item.remarks || '';
        toggleModal('editModal');
    }

    function toggleAll(source) {
        const checkboxes = document.querySelectorAll('.item-checkbox');
        for (let i = 0; i < checkboxes.length; i++) {
            checkboxes[i].checked = source.checked;
        }
    }

    function printData() {
        // Filter rows based on selection
        const checkboxes = document.querySelectorAll('.item-checkbox:checked');
        const rows = document.querySelectorAll('tbody tr');

        if (checkboxes.length > 0 && checkboxes.length < rows.length) {
            // Some selected - hide unselected
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
            // None or All selected - show all
            document.body.classList.remove('print-filtered');
            rows.forEach(tr => tr.classList.remove('print-row-selected'));
        }

        // Wait for DOM updates then print
        setTimeout(() => {
            window.print();
        }, 100);
    }
</script>

<style>
    @media print {
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

        /* Hide stats, control bar, etc */
        .grid.grid-cols-1.md\:grid-cols-2.gap-4.mb-8 {
            display: none !important;
        }

        .mb-6.flex.gap-3 {
            display: none !important;
        }

        .px-6.py-4.border-t {
            display: none !important;
        }

        /* Hide Action Column and Checkbox Column */
        th:first-child,
        td:first-child {
            display: none !important;
        }

        th:last-child,
        td:last-child {
            display: none !important;
        }

        body {
            background: white !important;
            color: black !important;
        }

        .p-8 {
            padding: 0 !important;
        }

        header h2,
        header p {
            color: black !important;
        }

        table {
            width: 100% !important;
            border-collapse: collapse !important;
        }

        th,
        td {
            border: 1px solid #ddd !important;
            color: black !important;
            padding: 8px !important;
        }

        thead tr {
            background-color: #f1f5f9 !important;
            -webkit-print-color-adjust: exact;
        }

        /* Handle filtered printing */
        body.print-filtered tbody tr:not(.print-row-selected) {
            display: none !important;
        }
    }
</style>



<?php require_once '../../includes/footer.php'; ?>