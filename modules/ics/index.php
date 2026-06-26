<?php
require_once '../../config.php';
requireLogin();

// Handle CSV Export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $stmt = $pdo->query("SELECT * FROM ics_inventory ORDER BY id ASC");
    $items = $stmt->fetchAll();

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="ics_inventory_export_' . date('Y-m-d_His') . '.csv"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['ICS No.', 'RIS No.', 'Inventory Item No.', 'User Accountable', 'Department', 'Model', 'Serial Number', 'Item Code', 'PO#', 'IAR', 'Price', 'Date Acquired', 'Supplier', 'Date Issued', 'Remarks']);

    foreach ($items as $item) {
        fputcsv($output, [
            escapeCsvField($item['ics_no']),
            escapeCsvField($item['ris_no'] ?? ''),
            escapeCsvField($item['inventory_item_no']),
            escapeCsvField($item['user_accountable']),
            escapeCsvField($item['department']),
            escapeCsvField($item['model']),
            escapeCsvField($item['serial_number']),
            escapeCsvField($item['item_code']),
            escapeCsvField($item['po_number']),
            escapeCsvField($item['iar']),
            $item['price'] ?? '',
            $item['date_acquired'] ?? '',
            escapeCsvField($item['supplier']),
            $item['date_issued'] ?? '',
            escapeCsvField($item['remarks'] ?? '')
        ]);
    }

    fclose($output);
    exit;
}

// --- Handle Actions ---
$success_msg = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $error_msg = "Security validation failed. Please refresh and try again.";
    } elseif ($_POST['action'] === 'import_csv') {
        if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === 0) {
            try {
                $file = fopen($_FILES['csv_file']['tmp_name'], 'r');
                $header = fgetcsv($file); // Skip header row
                $imported = 0;
                $duplicates = 0;
                $errors = [];

                while (($row = fgetcsv($file)) !== false) {
                    if (count($row) < 2)
                        continue;

                    $ics_no = trim($row[0] ?? '');
                    $ris_no = trim($row[1] ?? '');
                    $inventory_item_no = trim($row[2] ?? '');
                    $user_accountable = trim($row[3] ?? '');
                    $department = trim($row[4] ?? '');
                    $model = trim($row[5] ?? '');
                    $serial_number = trim($row[6] ?? '');
                    $item_code = trim($row[7] ?? '');
                    $po_number = trim($row[8] ?? '');
                    $iar = trim($row[9] ?? '');
                    $price = !empty($row[10]) ? (float) $row[10] : 0;
                    $date_acquired = !empty($row[11]) ? trim($row[11]) : null;
                    $supplier = trim($row[12] ?? '');
                    $date_issued = !empty($row[13]) ? trim($row[13]) : null;
                    $remarks = trim($row[14] ?? '');

                    // Duplicate check on ICS No
                    if (!empty($ics_no)) {
                        $chk = $pdo->prepare("SELECT COUNT(*) FROM ics_inventory WHERE ics_no = ?");
                        $chk->execute([$ics_no]);
                        if ($chk->fetchColumn() > 0) {
                            $duplicates++;
                            continue;
                        }
                    }

                    try {
                        $stmt = $pdo->prepare("INSERT INTO ics_inventory (ics_no, ris_no, user_accountable, department, model, serial_number, item_code, inventory_item_no, po_number, iar, price, date_acquired, date_issued, supplier, remarks) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$ics_no, $ris_no, $user_accountable, $department, $model, $serial_number, $item_code, $inventory_item_no, $po_number, $iar, $price, $date_acquired, $date_issued, $supplier, $remarks]);
                        $imported++;
                    } catch (Exception $e) {
                        $errors[] = "Row " . ($imported + $duplicates + 1) . ": " . $e->getMessage();
                    }
                }

                fclose($file);

                $msg_parts = ["Imported $imported ICS items"];
                if ($duplicates > 0)
                    $msg_parts[] = "$duplicates duplicates skipped";
                if (!empty($errors))
                    $msg_parts[] = count($errors) . " failed";

                $success_msg = implode(", ", $msg_parts) . ".";
                logAudit($pdo, 'import_ics', "Imported $imported ICS items (Skipped $duplicates)", 'ics', null);
            } catch (Exception $e) {
                $error_msg = "Error importing CSV: " . $e->getMessage();
            }
        }
    } elseif ($_POST['action'] === 'add_ics') {
        // Collect fields
        $ics_no = $_POST['ics_no'] ?? '';
        $ris_no = $_POST['ris_no'] ?? '';
        $user_accountable = $_POST['user_accountable'] ?? '';
        $department = $_POST['department'] ?? '';
        $model = $_POST['model'] ?? '';
        $serial_number = $_POST['serial_number'] ?? '';
        $item_code = $_POST['item_code'] ?? '';
        $inventory_item_no = $_POST['inventory_item_no'] ?? '';
        $po_number = $_POST['po_number'] ?? '';
        $iar = $_POST['iar'] ?? '';
        $price = $_POST['price'] ?? 0;
        $date_acquired = $_POST['date_acquired'] ?? null;
        if (empty($date_acquired))
            $date_acquired = null;
        $date_issued = $_POST['date_issued'] ?? null;
        if (empty($date_issued))
            $date_issued = null;
        $supplier = $_POST['supplier'] ?? '';
        $remarks = $_POST['remarks'] ?? '';

        // Check for duplicate ICS No
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM ics_inventory WHERE ics_no = ?");
        $checkStmt->execute([$ics_no]);
        if ($checkStmt->fetchColumn() > 0) {
            $error_msg = "Error: ICS No. '$ics_no' already exists.";
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO ics_inventory (ics_no, ris_no, user_accountable, department, model, serial_number, item_code, inventory_item_no, po_number, iar, price, date_acquired, date_issued, supplier, remarks) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$ics_no, $ris_no, $user_accountable, $department, $model, $serial_number, $item_code, $inventory_item_no, $po_number, $iar, $price, $date_acquired, $date_issued, $supplier, $remarks]);
                $new_ics_id = $pdo->lastInsertId();
                $add_detail = "Added ICS | ICS No: $ics_no | RIS No: $ris_no | User: $user_accountable | Dept: $department | Model: $model | S/N: $serial_number | Item Code: $item_code | Inv Item No: $inventory_item_no | PO#: $po_number | IAR: $iar | Price: $price | Supplier: $supplier";
                if ($remarks)
                    $add_detail .= " | Remarks: $remarks";
                logAudit($pdo, 'add_ics', $add_detail, 'ics', $new_ics_id);
                /* logChangelog($pdo, 'feature', 'ICS Inventory', "Added ICS: $ics_no ($model)", "User: $user_accountable | Dept: $department | S/N: $serial_number"); removed */
                $success_msg = "ICS Item added successfully.";
            } catch (Exception $e) {
                $error_msg = "Error adding item: " . $e->getMessage();
            }
        }

    } elseif ($_POST['action'] === 'edit_ics') {
        $id = $_POST['id'] ?? 0;
        $ics_no = $_POST['ics_no'] ?? '';
        $ris_no = $_POST['ris_no'] ?? '';
        $user_accountable = $_POST['user_accountable'] ?? '';
        $department = $_POST['department'] ?? '';
        $model = $_POST['model'] ?? '';
        $serial_number = $_POST['serial_number'] ?? '';
        $item_code = $_POST['item_code'] ?? '';
        $inventory_item_no = $_POST['inventory_item_no'] ?? '';
        $po_number = $_POST['po_number'] ?? '';
        $iar = $_POST['iar'] ?? '';
        $price = $_POST['price'] ?? 0;
        $date_acquired = $_POST['date_acquired'] ?? null;
        if (empty($date_acquired))
            $date_acquired = null;
        $date_issued = $_POST['date_issued'] ?? null;
        if (empty($date_issued))
            $date_issued = null;
        $supplier = $_POST['supplier'] ?? '';
        $remarks = $_POST['remarks'] ?? '';

        if ($id) {
            // Check for duplicate ICS No (excluding current ID)
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM ics_inventory WHERE ics_no = ? AND id != ?");
            $checkStmt->execute([$ics_no, $id]);
            if ($checkStmt->fetchColumn() > 0) {
                $error_msg = "Error: ICS No. '$ics_no' already exists.";
            } else {
                try {
                    // Snapshot old record for field-level diff
                    $snap_ics = $pdo->prepare("SELECT * FROM ics_inventory WHERE id = ?");
                    $snap_ics->execute([$id]);
                    $old_ics = $snap_ics->fetch(PDO::FETCH_ASSOC) ?: [];

                    $stmt = $pdo->prepare("UPDATE ics_inventory SET ics_no=?, ris_no=?, user_accountable=?, department=?, model=?, serial_number=?, item_code=?, inventory_item_no=?, po_number=?, iar=?, price=?, date_acquired=?, date_issued=?, supplier=?, remarks=? WHERE id=?");
                    $stmt->execute([$ics_no, $ris_no, $user_accountable, $department, $model, $serial_number, $item_code, $inventory_item_no, $po_number, $iar, $price, $date_acquired, $date_issued, $supplier, $remarks, $id]);

                    $ics_labels = [
                        'ics_no' => 'ICS No', 'ris_no' => 'RIS No', 'user_accountable' => 'User',
                        'department' => 'Department', 'model' => 'Model', 'serial_number' => 'Serial',
                        'item_code' => 'Item Code', 'inventory_item_no' => 'Inv Item No',
                        'po_number' => 'PO #', 'iar' => 'IAR', 'price' => 'Price',
                        'date_acquired' => 'Date Acquired', 'date_issued' => 'Date Issued',
                        'supplier' => 'Supplier', 'remarks' => 'Remarks',
                    ];
                    $new_ics = [
                        'ics_no' => $ics_no, 'ris_no' => $ris_no, 'user_accountable' => $user_accountable,
                        'department' => $department, 'model' => $model, 'serial_number' => $serial_number,
                        'item_code' => $item_code, 'inventory_item_no' => $inventory_item_no,
                        'po_number' => $po_number, 'iar' => $iar, 'price' => $price,
                        'date_acquired' => $date_acquired, 'date_issued' => $date_issued,
                        'supplier' => $supplier, 'remarks' => $remarks,
                    ];
                    $ics_changes = [];
                    foreach ($ics_labels as $f => $lbl) {
                        $o = trim((string)($old_ics[$f] ?? '')); $n = trim((string)($new_ics[$f] ?? ''));
                        if ($o !== $n) $ics_changes[] = "{$lbl}: \"{$o}\" → \"{$n}\"";
                    }
                    $upd_detail = empty($ics_changes)
                        ? "Updated ICS (no field changes) | ID: $id | ICS No: $ics_no"
                        : "Updated ICS | ICS No: $ics_no | " . implode(' | ', $ics_changes);
                    logAudit($pdo, 'update_ics', $upd_detail, 'ics', $id);
                    /* logChangelog removed */
                    $success_msg = "ICS Item updated successfully.";

                } catch (Exception $e) {
                    $error_msg = "Error updating item: " . $e->getMessage();
                }
            }
        }
    } elseif ($_POST['action'] === 'delete_ics') {
        $id = $_POST['id'] ?? 0;
        if ($id) {
            try {
                $snap = $pdo->prepare("SELECT * FROM ics_inventory WHERE id = ?");
                $snap->execute([$id]);
                $del_ics = $snap->fetch(PDO::FETCH_ASSOC);
                $del_detail = "Deleted ICS Item ID $id";
                if ($del_ics) {
                    $del_detail = "Deleted ICS | ID: $id | ICS No: {$del_ics['ics_no']} | RIS No: {$del_ics['ris_no']} | User: {$del_ics['user_accountable']} | Dept: {$del_ics['department']} | Model: {$del_ics['model']} | S/N: {$del_ics['serial_number']} | Item Code: {$del_ics['item_code']} | PO#: {$del_ics['po_number']} | IAR: {$del_ics['iar']} | Supplier: {$del_ics['supplier']}";
                    if ($del_ics['remarks'])
                        $del_detail .= " | Remarks: {$del_ics['remarks']}";
                }
                $stmt = $pdo->prepare("DELETE FROM ics_inventory WHERE id = ?");
                $stmt->execute([$id]);
                logAudit($pdo, 'delete_ics', $del_detail, 'ics', $id);
                /* if ($del_ics) logChangelog($pdo, 'bugfix', 'ICS Inventory', "Removed ICS: ...", ...); removed — data changes not tracked in changelog */
                $success_msg = "ICS Item deleted successfully.";
            } catch (Exception $e) {
                $error_msg = "Error deleting item: " . $e->getMessage();
            }
        }
    }
}

// --- Filtering & Sorting ---
$search_term = $_GET['search'] ?? '';
$sort_by = $_GET['sort'] ?? 'id_desc';

$where_clauses = ["1=1"];
$params = [];

if ($search_term) {
    $where_clauses[] = "(ics_no ILIKE ? OR user_accountable ILIKE ? OR department ILIKE ? OR model ILIKE ? OR serial_number ILIKE ? OR item_code ILIKE ? OR inventory_item_no ILIKE ? OR po_number ILIKE ? OR iar ILIKE ? OR supplier ILIKE ?)";
    $term = "%$search_term%";
    for ($i = 0; $i < 10; $i++)
        $params[] = $term;
}

$where_sql = implode(' AND ', $where_clauses);

$order_by = "id DESC";
switch ($sort_by) {
    case 'date_asc':
        $order_by = "date_acquired ASC";
        break;
    case 'date_desc':
        $order_by = "date_acquired DESC";
        break;
    case 'user_asc':
        $order_by = "user_accountable ASC";
        break;
    case 'user_desc':
        $order_by = "user_accountable DESC";
        break;
}

// --- Pagination ---
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1)
    $page = 1;
$is_ajax = isset($_GET['ajax']);
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1)
    $page = 1;
$limit_param = $_GET['limit'] ?? '50';
$limit = ($limit_param === 'all') ? 999999 : (int) $limit_param;
$offset = ($page - 1) * $limit;

$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM ics_inventory WHERE $where_sql");
$count_stmt->execute($params);
$total_items = $count_stmt->fetchColumn();
$total_pages = ceil($total_items / $limit);

$sql = "SELECT t.*, last_edit.username AS last_edited_by, last_edit.created_at AS last_edited_at
    FROM ics_inventory t
    LEFT JOIN LATERAL (
        SELECT u.username, al.created_at
        FROM audit_logs al
        LEFT JOIN users u ON al.user_id = u.id
        WHERE al.resource_type = 'ics' AND al.resource_id::text = t.id::text
        ORDER BY al.created_at DESC
        LIMIT 1
    ) last_edit ON true
    WHERE $where_sql ORDER BY $order_by LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$ics_items = $stmt->fetchAll();

$page_title = "ICS Inventory";
if (!$is_ajax) {
    require_once '../../includes/header.php';
    require_once '../../includes/sidebar.php';
}
?>

<?php if (!$is_ajax): ?>
    <div class="p-4 md:p-8 flex-1 overflow-y-auto custom-scrollbar relative z-10 bg-slate-50 dark:bg-background-dark">
        <header
            class="-mt-4 -mx-4 md:-mt-8 md:-mx-8 px-4 md:px-8 pt-4 md:pt-8 pb-6 mb-8 flex flex-col md:flex-row items-start md:items-end justify-between gap-4 sticky top-0 bg-slate-50 dark:bg-background-dark z-20 border-b border-slate-200 dark:border-[#232b3d]">
            <div>
                <h2 class="text-2xl font-bold text-slate-900 dark:text-white">ICS Inventory</h2>
                <p class="text-sm text-slate-500 mt-1">Inventory Custodian Slip Management</p>
            </div>
            <div class="no-print flex items-center gap-2 md:gap-3 flex-wrap">
                <a href="?export=csv"
                    class="flex items-center justify-center rounded-lg h-9 px-4 bg-blue-600 text-white hover:bg-blue-700 transition-colors text-xs font-bold no-print">
                    <span class="material-symbols-outlined text-[18px] mr-2">download</span> Export CSV
                </a>
                <button onclick="toggleModal('importModal')"
                    class="flex items-center justify-center rounded-lg h-9 px-4 bg-amber-600 text-white hover:bg-amber-700 transition-colors text-xs font-bold no-print">
                    <span class="material-symbols-outlined text-[18px] mr-2">upload</span> Import CSV
                </button>
                <button onclick="printData()"
                    class="flex items-center justify-center rounded-lg h-9 px-4 bg-slate-800 dark:bg-slate-700 text-white hover:bg-slate-700 dark:hover:bg-slate-600 transition-colors text-xs font-bold no-print">
                    <span class="material-symbols-outlined text-[18px] mr-2">print</span> Print
                </button>
                <button onclick="printForm()"
                    class="flex items-center justify-center rounded-lg h-9 px-4 bg-slate-800 dark:bg-slate-700 text-white hover:bg-slate-700 dark:hover:bg-slate-600 transition-colors text-xs font-bold no-print shadow-lg shadow-slate-900/10">
                    <span class="material-symbols-outlined text-[18px] mr-2">description</span> Print Form
                </button>
                <button onclick="toggleModal('addIcsModal')"
                    class="flex items-center justify-center rounded-lg h-9 px-4 bg-primary text-white hover:bg-primary/90 transition-colors text-xs font-bold shadow-lg shadow-primary/20 no-print">
                    <span class="material-symbols-outlined text-[18px] mr-2">add</span> Add ICS
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

        <!-- Control Bar -->
        <div class="no-print mb-6 flex gap-3">
            <form method="GET" class="flex items-center gap-3 flex-wrap w-full">
                <div class="relative group">
                    <span
                        class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-[18px] text-slate-500">search</span>
                    <input type="text" name="search" id="searchInput" value="<?= htmlspecialchars($search_term) ?>"
                        placeholder="Search ICS No, User, Item No..."
                        class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] text-slate-700 dark:text-slate-200 text-xs rounded-xl pl-9 pr-4 py-2 focus:ring-2 focus:ring-primary w-64">
                </div>

                <select name="sort" onchange="this.form.submit()"
                    class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] text-slate-400 text-xs rounded-xl px-4 py-2">
                    <option value="id_desc" <?= $sort_by === 'id_desc' ? 'selected' : '' ?>>Newest First</option>
                    <option value="date_desc" <?= $sort_by === 'date_desc' ? 'selected' : '' ?>>Date (Newest)</option>
                    <option value="date_asc" <?= $sort_by === 'date_asc' ? 'selected' : '' ?>>Date (Oldest)</option>
                    <option value="user_asc" <?= $sort_by === 'user_asc' ? 'selected' : '' ?>>User (A-Z)</option>
                </select>

                <!-- Pagination Controls -->
                <div class="flex items-center gap-2 ml-auto">
                    <?php if ($total_pages > 1): ?>
                        <span class="text-xs text-slate-500 mr-2">Page
                            <?= $page ?> of
                            <?= $total_pages ?>
                        </span>
                        <div
                            class="flex h-9 bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] rounded-xl overflow-hidden mr-2">
                            <a href="?page=<?= max(1, $page - 1) ?>&search=<?= urlencode($search_term) ?>&limit=<?= $limit ?>&sort=<?= $sort_by ?>"
                                class="px-3 flex items-center justify-center hover:bg-white/5 text-slate-400 hover:text-slate-900 dark:hover:text-white transition-colors border-r border-slate-200 dark:border-[#232b3d] <?= $page <= 1 ? 'pointer-events-none opacity-50' : '' ?>">
                                <span class="material-symbols-outlined text-[18px]">chevron_left</span>
                            </a>
                            <a href="?page=<?= min($total_pages, $page + 1) ?>&search=<?= urlencode($search_term) ?>&limit=<?= $limit ?>&sort=<?= $sort_by ?>"
                                class="px-3 flex items-center justify-center hover:bg-white/5 text-slate-400 hover:text-slate-900 dark:hover:text-white transition-colors <?= $page >= $total_pages ? 'pointer-events-none opacity-50' : '' ?>">
                                <span class="material-symbols-outlined text-[18px]">chevron_right</span>
                            </a>
                        </div>
                    <?php endif; ?>
                    <select name="limit" onchange="this.form.submit()"
                        class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] text-slate-400 text-xs rounded-xl px-4 py-2">
                        <option value="50" <?= $limit_param == '50' ? 'selected' : '' ?>>Show: 50</option>
                        <option value="100" <?= $limit_param == '100' ? 'selected' : '' ?>>Show: 100</option>
                        <option value="all" <?= $limit_param == 'all' ? 'selected' : '' ?>>Show: All</option>
                    </select>
                </div>
            </form>
        </div>

        <!-- Table -->
    <?php endif; ?>

    <div id="ics-table-container"
        class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] rounded-2xl overflow-hidden shadow-sm transition-opacity duration-200">
        <div class="overflow-x-auto custom-scrollbar">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-white dark:bg-[#1a2130] border-b border-slate-200 dark:border-[#232b3d]">
                        <th class="px-4 py-3 text-[10px] font-bold text-slate-500 uppercase w-10 text-center">
                            <input type="checkbox" onclick="toggleAll(this)"
                                class="rounded border-slate-300 dark:border-[#232b3d] text-primary focus:ring-primary h-4 w-4 bg-slate-50 dark:bg-[#1a2130]">
                        </th>
                        <th class="px-4 py-3 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">ICS
                            No.</th>
                        <th class="px-4 py-3 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">RIS
                            No.</th>
                        <th class="px-4 py-3 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">
                            Inventory
                            Item No.</th>
                        <th class="px-4 py-3 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">User
                            Accountable</th>
                        <th class="px-4 py-3 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">
                            Department</th>
                        <th class="px-4 py-3 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">Model
                        </th>
                        <th class="px-4 py-3 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">
                            Serial Number</th>
                        <th class="px-4 py-3 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">Item
                            Code</th>
                        <th class="px-4 py-3 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">
                            PO#</th>
                        <th class="px-4 py-3 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">
                            IAR</th>
                        <th class="px-4 py-3 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">Price
                        </th>
                        <th class="px-4 py-3 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">Date
                            Acquired</th>
                        <th class="px-4 py-3 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">
                            Supplier</th>
                        <th class="px-4 py-3 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">Date
                            Issued</th>
                        <th class="px-4 py-3 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">
                            Remarks</th>
                        <th class="no-print px-4 py-3 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">Last Edited By</th>
                        <th
                            class="px-4 py-3 text-[10px] font-bold text-slate-500 uppercase text-right whitespace-nowrap">
                            Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#232b3d]/50">
                    <?php if (empty($ics_items)): ?>
                        <tr>
                            <td colspan="18" class="px-6 py-12 text-center text-slate-500">No ICS items found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($ics_items as $item): ?>
                            <tr class="hover:bg-white/5 transition-colors text-xs" data-item='<?= htmlspecialchars(json_encode($item), ENT_QUOTES, "UTF-8") ?>'>
                                <td class="px-4 py-3 text-center">
                                    <input type="checkbox"
                                        class="item-checkbox rounded border-slate-300 dark:border-[#232b3d] text-primary focus:ring-primary h-4 w-4 bg-slate-50 dark:bg-[#1a2130]">
                                </td>
                                <td class="px-4 py-3 font-medium text-slate-900 dark:text-white whitespace-nowrap">
                                    <a href="#" onclick='openEditModal(<?= json_encode($item) ?>)'
                                        class="text-primary hover:underline font-bold">
                                        <?= htmlspecialchars($item['ics_no'] ?: '-') ?>
                                    </a>
                                </td>
                                <td class="px-4 py-3 text-slate-600 dark:text-slate-400 whitespace-nowrap">
                                    <?= htmlspecialchars($item['ris_no'] ?: '-') ?>
                                </td>
                                <td class="px-4 py-3 text-slate-600 dark:text-slate-400">
                                    <?= htmlspecialchars($item['inventory_item_no'] ?: '-') ?>
                                </td>
                                <td class="px-4 py-3 text-slate-700 dark:text-slate-300">
                                    <?= htmlspecialchars($item['user_accountable'] ?: '-') ?>
                                </td>
                                <td class="px-4 py-3 text-slate-600 dark:text-slate-400">
                                    <?= htmlspecialchars($item['department'] ?: '-') ?>
                                </td>
                                <td class="px-4 py-3 text-slate-600 dark:text-slate-400">
                                    <?= htmlspecialchars($item['model'] ?: '-') ?>
                                </td>
                                <td class="px-4 py-3 font-mono text-slate-600 dark:text-slate-400">
                                    <?= htmlspecialchars($item['serial_number'] ?: '-') ?>
                                </td>
                                <td class="px-4 py-3 text-slate-600 dark:text-slate-400">
                                    <?= htmlspecialchars($item['item_code'] ?: '-') ?>
                                </td>
                                <td class="px-4 py-3 text-slate-600 dark:text-slate-400">
                                    <?= htmlspecialchars($item['po_number'] ?: '-') ?>
                                </td>
                                <td class="px-4 py-3 text-slate-600 dark:text-slate-400">
                                    <?= htmlspecialchars($item['iar'] ?: '-') ?>
                                </td>
                                <td class="px-4 py-3 text-slate-700 dark:text-slate-300">
                                    <?= $item['price'] ? number_format($item['price'], 2) : '-' ?>
                                </td>
                                <td class="px-4 py-3 text-slate-600 dark:text-slate-400">
                                    <?= htmlspecialchars($item['date_acquired'] ?: '-') ?>
                                </td>
                                <td class="px-4 py-3 text-slate-600 dark:text-slate-400">
                                    <?= htmlspecialchars($item['supplier'] ?: '-') ?>
                                </td>
                                <td class="px-4 py-3 text-slate-600 dark:text-slate-400">
                                    <?= htmlspecialchars($item['date_issued'] ?: '-') ?>
                                </td>
                                <td class="px-4 py-3 text-slate-600 dark:text-slate-400">
                                    <?= htmlspecialchars($item['remarks'] ?: '-') ?>
                                </td>
                                <td class="no-print px-4 py-3 whitespace-nowrap">
                                    <?php if (!empty($item['last_edited_by'])): ?>
                                        <div class="text-[10px] font-semibold text-slate-700 dark:text-slate-300"><?= htmlspecialchars($item['last_edited_by']) ?></div>
                                        <div class="text-[9px] text-slate-400"><?= date('M d, Y H:i', strtotime($item['last_edited_at'])) ?></div>
                                    <?php else: ?>
                                        <span class="text-slate-400 text-[10px]">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 text-right flex items-center justify-end gap-1">
                                    <button onclick='openEditModal(<?= json_encode($item) ?>)'
                                        class="p-1 hover:bg-primary/10 hover:text-primary text-slate-500 rounded-lg">
                                        <span class="material-symbols-outlined text-[18px]">edit</span>
                                    </button>
                                    <form method="POST" onsubmit="return confirm('Delete ICS item?');" style="display:inline;">
                                        <?= getCsrfInput() ?>
                                        <input type="hidden" name="action" value="delete_ics">
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
    </div>
    <?php if (!$is_ajax): ?>
    </div>

    <!-- Add ICS Modal -->
    <div id="addIcsModal" class="hidden fixed inset-0 z-50 overflow-y-auto" role="dialog">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 bg-background-dark/80 backdrop-blur-sm" onclick="toggleModal('addIcsModal')"></div>
            <div
                class="relative bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] rounded-2xl max-w-2xl w-full p-4 sm:p-6 shadow-2xl">
                <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-6">Add ICS Item</h3>
                <form method="POST">
                    <?= getCsrfInput() ?>
                    <input type="hidden" name="action" value="add_ics">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <input type="text" name="ics_no" placeholder="ICS No."
                            class="bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5 text-xs w-full">
                        <input type="text" name="ris_no" placeholder="RIS No."
                            class="bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5 text-xs w-full">
                        <input type="text" name="inventory_item_no" placeholder="Inventory Item No."
                            class="bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5 text-xs w-full">
                        <input type="text" name="user_accountable" placeholder="User Accountable" required
                            class="bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5 text-xs w-full">
                        <input type="text" name="department" placeholder="Department"
                            class="bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5 text-xs w-full">
                        <input type="text" name="model" placeholder="Model"
                            class="bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5 text-xs w-full">
                        <input type="text" name="serial_number" placeholder="Serial Number"
                            class="bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5 text-xs w-full">
                        <input type="text" name="item_code" placeholder="Item Code"
                            class="bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5 text-xs w-full">
                        <input type="text" name="po_number" placeholder="PO#"
                            class="bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5 text-xs w-full">
                        <input type="text" name="iar" placeholder="IAR"
                            class="bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5 text-xs w-full">
                        <input type="number" step="0.01" name="price" placeholder="Price"
                            class="bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5 text-xs w-full">

                        <div class="flex flex-col">
                            <label class="text-[10px] text-slate-500 mb-1 ml-1">Date Acquired</label>
                            <input type="date" name="date_acquired"
                                class="bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5 text-xs w-full">
                        </div>

                        <div class="flex flex-col">
                            <label class="text-[10px] text-slate-500 mb-1 ml-1">Date Issued</label>
                            <input type="date" name="date_issued"
                                class="bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5 text-xs w-full">
                        </div>

                        <input type="text" name="supplier" placeholder="Supplier"
                            class="bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5 text-xs w-full">
                        <input type="text" name="remarks" placeholder="Remarks"
                            class="bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5 text-xs w-full">
                    </div>
                    <div class="mt-6 flex justify-end gap-3">
                        <button type="button" onclick="toggleModal('addIcsModal')"
                            class="px-4 py-2 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white text-xs">Cancel</button>
                        <button type="submit" class="px-6 py-2 bg-primary text-white font-bold rounded-xl text-xs">Save
                            Item</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit ICS Modal -->
    <div id="editIcsModal" class="hidden fixed inset-0 z-50 overflow-y-auto" role="dialog">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 bg-background-dark/80 backdrop-blur-sm" onclick="toggleModal('editIcsModal')"></div>
            <div
                class="relative bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] rounded-2xl max-w-2xl w-full p-4 sm:p-6 shadow-2xl">
                <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-6">Edit ICS Item</h3>
                <form method="POST">
                    <?= getCsrfInput() ?>
                    <input type="hidden" name="action" value="edit_ics">
                    <input type="hidden" name="id" id="edit_id">

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="flex flex-col">
                            <label class="text-[10px] text-slate-500 mb-1 ml-1">ICS No.</label>
                            <input type="text" name="ics_no" id="edit_ics_no" placeholder="ICS No."
                                class="bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5 text-xs w-full">
                        </div>
                        <div class="flex flex-col">
                            <label class="text-[10px] text-slate-500 mb-1 ml-1">RIS No.</label>
                            <input type="text" name="ris_no" id="edit_ris_no" placeholder="RIS No."
                                class="bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5 text-xs w-full">
                        </div>
                        <div class="flex flex-col">
                            <label class="text-[10px] text-slate-500 mb-1 ml-1">Inventory Item No.</label>
                            <input type="text" name="inventory_item_no" id="edit_inventory_item_no"
                                placeholder="Inventory Item No."
                                class="bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5 text-xs w-full">
                        </div>
                        <div class="flex flex-col">
                            <label class="text-[10px] text-slate-500 mb-1 ml-1">User Accountable</label>
                            <input type="text" name="user_accountable" id="edit_user_accountable"
                                placeholder="User Accountable" required
                                class="bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5 text-xs w-full">
                        </div>
                        <div class="flex flex-col">
                            <label class="text-[10px] text-slate-500 mb-1 ml-1">Department</label>
                            <input type="text" name="department" id="edit_department" placeholder="Department"
                                class="bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5 text-xs w-full">
                        </div>
                        <div class="flex flex-col">
                            <label class="text-[10px] text-slate-500 mb-1 ml-1">Model</label>
                            <input type="text" name="model" id="edit_model" placeholder="Model"
                                class="bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5 text-xs w-full">
                        </div>
                        <div class="flex flex-col">
                            <label class="text-[10px] text-slate-500 mb-1 ml-1">Serial Number</label>
                            <input type="text" name="serial_number" id="edit_serial_number" placeholder="Serial Number"
                                class="bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5 text-xs w-full">
                        </div>
                        <div class="flex flex-col">
                            <label class="text-[10px] text-slate-500 mb-1 ml-1">Item Code</label>
                            <input type="text" name="item_code" id="edit_item_code" placeholder="Item Code"
                                class="bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5 text-xs w-full">
                        </div>
                        <div class="flex flex-col">
                            <label class="text-[10px] text-slate-500 mb-1 ml-1">PO#</label>
                            <input type="text" name="po_number" id="edit_po_number" placeholder="PO#"
                                class="bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5 text-xs w-full">
                        </div>
                        <div class="flex flex-col">
                            <label class="text-[10px] text-slate-500 mb-1 ml-1">IAR</label>
                            <input type="text" name="iar" id="edit_iar" placeholder="IAR"
                                class="bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5 text-xs w-full">
                        </div>
                        <div class="flex flex-col">
                            <label class="text-[10px] text-slate-500 mb-1 ml-1">Price</label>
                            <input type="number" step="0.01" name="price" id="edit_price" placeholder="Price"
                                class="bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5 text-xs w-full">
                        </div>
                        <div class="flex flex-col">
                            <label class="text-[10px] text-slate-500 mb-1 ml-1">Date Acquired</label>
                            <input type="date" name="date_acquired" id="edit_date_acquired"
                                class="bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5 text-xs w-full">
                        </div>
                        <div class="flex flex-col">
                            <label class="text-[10px] text-slate-500 mb-1 ml-1">Supplier</label>
                            <input type="text" name="supplier" id="edit_supplier" placeholder="Supplier"
                                class="bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5 text-xs w-full">
                        </div>
                        <div class="flex flex-col">
                            <label class="text-[10px] text-slate-500 mb-1 ml-1">Date Issued</label>
                            <input type="date" name="date_issued" id="edit_date_issued"
                                class="bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5 text-xs w-full">
                        </div>
                        <div class="flex flex-col">
                            <label class="text-[10px] text-slate-500 mb-1 ml-1">Remarks</label>
                            <input type="text" name="remarks" id="edit_remarks" placeholder="Remarks"
                                class="bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5 text-xs w-full">
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end gap-3">
                        <button type="button" onclick="toggleModal('editIcsModal')"
                            class="px-4 py-2 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white text-xs">Cancel</button>
                        <button type="submit" class="px-6 py-2 bg-primary text-white font-bold rounded-xl text-xs">Save
                            Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: Import -->
    <div id="importModal" class="hidden fixed inset-0 z-50 overflow-y-auto" role="dialog">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 bg-background-dark/80 backdrop-blur-sm" onclick="toggleModal('importModal')"></div>
            <div
                class="relative bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] rounded-2xl max-w-lg w-full p-4 sm:p-6 shadow-2xl">
                <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-4">Import ICS Items from CSV</h3>
                <p class="text-sm text-slate-400 mb-4">Upload a CSV file with ICS details. Order: ICS No., RIS No.,
                    Inventory Item No., User Accountable, Department, Model, Serial Number, Item Code, PO#, IAR, Price,
                    Date Acquired, Supplier, Date Issued, Remarks</p>
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

    <script>


        function openEditModal(item) {
            document.getElementById('edit_id').value = item.id;
            document.getElementById('edit_ics_no').value = item.ics_no || '';
            document.getElementById('edit_ris_no').value = item.ris_no || '';
            document.getElementById('edit_inventory_item_no').value = item.inventory_item_no || '';
            document.getElementById('edit_user_accountable').value = item.user_accountable || '';
            document.getElementById('edit_department').value = item.department || '';
            document.getElementById('edit_model').value = item.model || '';
            document.getElementById('edit_serial_number').value = item.serial_number || '';
            document.getElementById('edit_item_code').value = item.item_code || '';
            document.getElementById('edit_po_number').value = item.po_number || '';
            document.getElementById('edit_iar').value = item.iar || '';
            document.getElementById('edit_price').value = item.price || '';
            document.getElementById('edit_date_acquired').value = item.date_acquired || '';
            document.getElementById('edit_date_issued').value = item.date_issued || '';
            document.getElementById('edit_supplier').value = item.supplier || '';
            document.getElementById('edit_remarks').value = item.remarks || '';

            toggleModal('editIcsModal');
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

        function printForm() {
            const checkboxes = document.querySelectorAll('.item-checkbox:checked');
            if (checkboxes.length === 0) {
                alert('Please select at least one item to print the form.');
                return;
            }

            let ids = [];
            checkboxes.forEach(cb => {
                // Find the closest tr and get the ID from the delete form or edit button
                // But wait, the checkbox doesn't directly store the ID.
                // Let's rely on the row's data.
                // Actually, the edit button has the full item json, but that's hard to get from here.
                // Easier: Add a data-id attribute to the checkbox or the row.
                // Let's use the delete form's input which is in the same row.
                const row = cb.closest('tr');
                const idInput = row.querySelector('input[name="id"]');
                if (idInput) {
                    ids.push(idInput.value);
                }
            });

            if (ids.length > 0) {
                const url = 'print_form.php?ids=' + ids.join(',');
                window.open(url, '_blank');
            }
        }

        // Live Search Logic
        const searchInput = document.getElementById('searchInput');
        let searchTimeout;

        if (searchInput) {
            searchInput.addEventListener('input', function () {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    const query = this.value;
                    const container = document.getElementById('ics-table-container');
                    if (container) container.classList.add('opacity-50');

                    const searchParams = new URLSearchParams(window.location.search);
                    if (query) {
                        searchParams.set('search', query);
                    } else {
                        searchParams.delete('search');
                    }
                    searchParams.set('page', '1'); // Reset to page 1
                    searchParams.set('ajax', '1');

                    fetch(window.location.pathname + '?' + searchParams.toString())
                        .then(response => response.text())
                        .then(html => {
                            if (html.includes('<!DOCTYPE html>')) {
                                console.warn('Full page received. Reloading...');
                                // return;
                            }

                            const currentContainer = document.getElementById('ics-table-container');
                            if (currentContainer) {
                                currentContainer.outerHTML = html;
                            }

                            searchParams.delete('ajax');
                            window.history.replaceState({}, '', '?' + searchParams.toString());
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            if (container) container.classList.remove('opacity-50');
                        });
                }, 300);
            });
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
<?php endif; ?>