<?php
require_once '../../config.php';
requireLogin();

$success_msg = $error_msg = '';
$current_user     = $_SESSION['username']   ?? '';
$current_fullname = $_SESSION['full_name']  ?? $current_user;
$current_role     = $_SESSION['role']       ?? 'viewer';
$can_manage       = in_array($current_role, ['admin', 'editor']);

// ── Handle Restore / Permanent Delete ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error_msg = "Security validation failed. Please refresh and try again.";
    } else {
        $id = (int)($_POST['id'] ?? 0);

        // ── Restore ──────────────────────────────────────────────────────────
        if ($_POST['action'] === 'restore' && $can_manage) {
            try {
                $snap = $pdo->prepare("SELECT * FROM computers WHERE id = ? AND deleted_at IS NOT NULL");
                $snap->execute([$id]);
                $row = $snap->fetch(PDO::FETCH_ASSOC);
                if (!$row) {
                    $error_msg = "Record not found in trash.";
                } else {
                    $pdo->prepare("UPDATE computers SET deleted_at = NULL, deleted_by = NULL WHERE id = ?")
                        ->execute([$id]);
                    // Re-sync IP if present
                    if (!empty($row['ip_address'])) {
                        syncComputerIpToIpModule($pdo, $row['ip_address'], $row['mac_address'] ?? '',
                            $row['control_number'] ?? '', $row['department'] ?? '');
                    }
                    // Re-sync asset
                    syncComputerToAsset($pdo, $id);
                    // Log
                    $pdo->prepare("INSERT INTO trash_log (table_name, record_id, action, done_by) VALUES ('computers',?,?,?)")
                        ->execute([$id, 'restored', $current_fullname]);
                    logAudit($pdo, 'restore_computer',
                        "Restored Computer | Control #: {$row['control_number']} | User: {$row['end_user']}",
                        'computer', $id);
                    $success_msg = "✅ Computer <strong>{$row['control_number']}</strong> restored to inventory.";
                }
            } catch (Exception $e) {
                $error_msg = "Error restoring: " . $e->getMessage();
            }

        // ── Permanent Delete ─────────────────────────────────────────────────
        } elseif ($_POST['action'] === 'permanent_delete' && $current_role === 'admin') {
            try {
                $snap = $pdo->prepare("SELECT * FROM computers WHERE id = ? AND deleted_at IS NOT NULL");
                $snap->execute([$id]);
                $row = $snap->fetch(PDO::FETCH_ASSOC);
                if (!$row) {
                    $error_msg = "Record not found in trash.";
                } else {
                    $pdo->prepare("DELETE FROM computers WHERE id = ? AND deleted_at IS NOT NULL")
                        ->execute([$id]);
                    // Log
                    $pdo->prepare("INSERT INTO trash_log (table_name, record_id, action, done_by, snapshot) VALUES ('computers',?,?,?,?::jsonb)")
                        ->execute([$id, 'trashed', $current_fullname, json_encode($row)]);
                    logAudit($pdo, 'permanent_delete_computer',
                        "Permanently Deleted Computer | Control #: {$row['control_number']} | User: {$row['end_user']}",
                        'computer', $id);
                    $success_msg = "🗑️ Computer <strong>{$row['control_number']}</strong> permanently deleted.";
                }
            } catch (Exception $e) {
                $error_msg = "Error permanently deleting: " . $e->getMessage();
            }

        // ── Restore All ──────────────────────────────────────────────────────
        } elseif ($_POST['action'] === 'restore_all' && $current_role === 'admin') {
            try {
                $cnt = $pdo->query("SELECT COUNT(*) FROM computers WHERE deleted_at IS NOT NULL")->fetchColumn();
                $pdo->exec("UPDATE computers SET deleted_at = NULL, deleted_by = NULL WHERE deleted_at IS NOT NULL");
                logAudit($pdo, 'restore_all_computers', "Restored all $cnt trashed computers", 'computer');
                $success_msg = "✅ All $cnt trashed computers restored.";
            } catch (Exception $e) {
                $error_msg = "Error: " . $e->getMessage();
            }

        // ── Empty Trash ──────────────────────────────────────────────────────
        } elseif ($_POST['action'] === 'empty_trash' && $current_role === 'admin') {
            try {
                $cnt = $pdo->query("SELECT COUNT(*) FROM computers WHERE deleted_at IS NOT NULL")->fetchColumn();
                $pdo->exec("DELETE FROM computers WHERE deleted_at IS NOT NULL");
                logAudit($pdo, 'empty_trash', "Permanently deleted $cnt trashed computers (empty trash)", 'computer');
                $success_msg = "🗑️ Trash emptied — $cnt records permanently deleted.";
            } catch (Exception $e) {
                $error_msg = "Error: " . $e->getMessage();
            }
        }
    }
}

// ── Fetch trashed records ─────────────────────────────────────────────────────
$search  = trim($_GET['search'] ?? '');
$where   = "deleted_at IS NOT NULL";
$params  = [];
if ($search !== '') {
    $where  .= " AND (control_number ILIKE ? OR end_user ILIKE ? OR department ILIKE ? OR ip_address ILIKE ?)";
    $params  = array_fill(0, 4, "%$search%");
}
$stmt = $pdo->prepare("SELECT * FROM computers WHERE $where ORDER BY deleted_at DESC");
$stmt->execute($params);
$trashed = $stmt->fetchAll(PDO::FETCH_ASSOC);
$total   = count($trashed);

require_once '../../includes/header.php';
?>
<div class="p-4 md:p-8 max-w-screen-xl mx-auto">

    <!-- Page Header -->
    <header class="-mt-4 -mx-4 md:-mt-8 md:-mx-8 px-4 md:px-8 pt-4 md:pt-8 pb-4 mb-6 flex flex-col gap-3 border-b border-slate-200 dark:border-[#232b3d]">
        <div class="flex items-center justify-between flex-wrap gap-3">
            <div class="flex items-center gap-3">
                <span class="material-symbols-outlined text-3xl text-red-500">delete</span>
                <div>
                    <h1 class="text-xl font-extrabold text-slate-800 dark:text-white">Trash</h1>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Deleted computers — restore or permanently remove</p>
                </div>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <?php if ($current_role === 'admin' && $total > 0): ?>
                    <!-- Restore All -->
                    <form method="POST" onsubmit="return confirm('Restore all <?= $total ?> trashed computers?')">
                        <?= getCsrfInput() ?>
                        <input type="hidden" name="action" value="restore_all">
                        <button class="flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-bold bg-emerald-500 hover:bg-emerald-600 text-white shadow transition">
                            <span class="material-symbols-outlined text-[15px]">restore_from_trash</span> Restore All
                        </button>
                    </form>
                    <!-- Empty Trash -->
                    <form method="POST" onsubmit="return confirm('⚠️ Permanently delete ALL <?= $total ?> trashed records? This CANNOT be undone.')">
                        <?= getCsrfInput() ?>
                        <input type="hidden" name="action" value="empty_trash">
                        <button class="flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-bold bg-red-600 hover:bg-red-700 text-white shadow transition">
                            <span class="material-symbols-outlined text-[15px]">delete_forever</span> Empty Trash
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- Search bar -->
        <form method="GET" class="flex gap-2">
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                placeholder="Search by control #, user, department, IP…"
                class="flex-1 bg-white dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] rounded-xl px-4 py-2 text-sm text-slate-900 dark:text-white placeholder-slate-400 focus:ring-2 focus:ring-primary/50">
            <button class="px-4 py-2 bg-primary text-white rounded-xl text-sm font-bold hover:bg-primary/90 transition">Search</button>
            <?php if ($search): ?>
                <a href="?" class="px-4 py-2 rounded-xl text-sm font-bold bg-slate-100 dark:bg-[#232b3d] text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-[#2a3347]">Clear</a>
            <?php endif; ?>
        </form>
    </header>

    <!-- Alerts -->
    <?php if ($success_msg): ?>
        <div class="mb-4 px-4 py-3 bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-300 dark:border-emerald-700 rounded-xl text-emerald-700 dark:text-emerald-300 text-sm flex items-center gap-2">
            <span class="material-symbols-outlined text-[18px]">check_circle</span>
            <span><?= $success_msg ?></span>
        </div>
    <?php endif; ?>
    <?php if ($error_msg): ?>
        <div class="mb-4 px-4 py-3 bg-red-50 dark:bg-red-900/20 border border-red-300 dark:border-red-700 rounded-xl text-red-700 dark:text-red-300 text-sm flex items-center gap-2">
            <span class="material-symbols-outlined text-[18px]">error</span>
            <span><?= htmlspecialchars($error_msg) ?></span>
        </div>
    <?php endif; ?>

    <!-- Stats bar -->
    <div class="flex items-center gap-3 mb-4">
        <span class="text-xs text-slate-500 dark:text-slate-400">
            <?= $total ?> item<?= $total !== 1 ? 's' : '' ?> in trash
            <?= $search ? ' (filtered)' : '' ?>
        </span>
        <?php if ($total > 0): ?>
            <span class="text-[10px] px-2 py-0.5 rounded-full bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400 font-bold"><?= $total ?></span>
        <?php endif; ?>
    </div>

    <?php if (empty($trashed)): ?>
        <!-- Empty state -->
        <div class="flex flex-col items-center justify-center py-24 text-slate-400 dark:text-slate-600">
            <span class="material-symbols-outlined text-7xl mb-4">delete_sweep</span>
            <p class="text-lg font-semibold">Trash is empty</p>
            <p class="text-sm mt-1">Deleted computers will appear here and can be restored.</p>
        </div>
    <?php else: ?>
        <!-- Trash Table -->
        <div class="overflow-x-auto rounded-2xl border border-slate-200 dark:border-[#232b3d] shadow-sm">
            <table class="w-full text-xs text-left">
                <thead class="bg-slate-50 dark:bg-[#151c2c] text-slate-500 dark:text-slate-400 uppercase text-[10px] tracking-wider">
                    <tr>
                        <th class="px-4 py-3">Control #</th>
                        <th class="px-4 py-3">End User</th>
                        <th class="px-4 py-3">Department</th>
                        <th class="px-4 py-3">System Unit</th>
                        <th class="px-4 py-3">IP Address</th>
                        <th class="px-4 py-3">Deleted By</th>
                        <th class="px-4 py-3">Deleted At</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-[#232b3d]">
                    <?php foreach ($trashed as $row): ?>
                    <tr class="bg-white dark:bg-[#1a2130] hover:bg-red-50/30 dark:hover:bg-red-900/10 transition group">
                        <td class="px-4 py-3 font-mono font-semibold text-slate-700 dark:text-slate-200">
                            <?= htmlspecialchars($row['control_number'] ?? '—') ?>
                        </td>
                        <td class="px-4 py-3 text-slate-600 dark:text-slate-300"><?= htmlspecialchars($row['end_user'] ?? '—') ?></td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400"><?= htmlspecialchars($row['department'] ?? '—') ?></td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400"><?= htmlspecialchars($row['system_unit'] ?? '—') ?></td>
                        <td class="px-4 py-3 font-mono text-slate-500 dark:text-slate-400"><?= htmlspecialchars($row['ip_address'] ?? '—') ?></td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400"><?= htmlspecialchars($row['deleted_by'] ?? '—') ?></td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400 whitespace-nowrap">
                            <?php if ($row['deleted_at']): ?>
                                <span title="<?= htmlspecialchars($row['deleted_at']) ?>">
                                    <?= date('M d, Y g:i A', strtotime($row['deleted_at'])) ?>
                                </span>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-2 opacity-0 group-hover:opacity-100 transition">
                                <!-- Restore -->
                                <?php if ($can_manage): ?>
                                <form method="POST" class="inline"
                                    onsubmit="return confirm('Restore <?= htmlspecialchars(addslashes($row['control_number'] ?? 'this computer')) ?> back to inventory?')">
                                    <?= getCsrfInput() ?>
                                    <input type="hidden" name="action" value="restore">
                                    <input type="hidden" name="id"     value="<?= (int)$row['id'] ?>">
                                    <button type="submit"
                                        class="flex items-center gap-1 px-3 py-1.5 rounded-lg text-[11px] font-bold bg-emerald-500 hover:bg-emerald-600 text-white shadow transition"
                                        title="Restore to inventory">
                                        <span class="material-symbols-outlined text-[13px]">restore_from_trash</span> Restore
                                    </button>
                                </form>
                                <?php endif; ?>
                                <!-- Permanent Delete (admin only) -->
                                <?php if ($current_role === 'admin'): ?>
                                <form method="POST" class="inline"
                                    onsubmit="return confirm('⚠️ Permanently delete <?= htmlspecialchars(addslashes($row['control_number'] ?? 'this record')) ?>? This CANNOT be undone.')">
                                    <?= getCsrfInput() ?>
                                    <input type="hidden" name="action" value="permanent_delete">
                                    <input type="hidden" name="id"     value="<?= (int)$row['id'] ?>">
                                    <button type="submit"
                                        class="flex items-center gap-1 px-3 py-1.5 rounded-lg text-[11px] font-bold bg-red-600 hover:bg-red-700 text-white shadow transition"
                                        title="Permanently delete — cannot be undone">
                                        <span class="material-symbols-outlined text-[13px]">delete_forever</span> Delete
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <!-- Expandable details row -->
                    <tr class="bg-slate-50/50 dark:bg-[#151c2c]/50 border-t-0">
                        <td colspan="8" class="px-4 pb-3 pt-0">
                            <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-x-6 gap-y-1 text-[10px] text-slate-400 dark:text-slate-500 mt-1">
                                <span><span class="font-semibold">OS:</span> <?= htmlspecialchars($row['os'] ?? '—') ?></span>
                                <span><span class="font-semibold">Processor:</span> <?= htmlspecialchars($row['processor'] ?? '—') ?></span>
                                <span><span class="font-semibold">Memory:</span> <?= htmlspecialchars($row['memory'] ?? '—') ?></span>
                                <span><span class="font-semibold">Storage:</span> <?= htmlspecialchars($row['storage'] ?? '—') ?></span>
                                <span><span class="font-semibold">MAC:</span> <?= htmlspecialchars($row['mac_address'] ?? '—') ?></span>
                                <span><span class="font-semibold">Status:</span> <?= htmlspecialchars($row['status'] ?? '—') ?></span>
                                <span><span class="font-semibold">Endpoint Secure:</span> <?= $row['endpoint_secure'] === 'Y' ? 'Yes' : 'No' ?></span>
                                <span><span class="font-semibold">Firewall:</span> <?= $row['firewall'] === 'Y' ? 'Yes' : 'No' ?></span>
                                <span><span class="font-semibold">Encoded By:</span> <?= htmlspecialchars($row['encoded_by'] ?? '—') ?></span>
                                <span><span class="font-semibold">Checked By:</span> <?= htmlspecialchars($row['checked_by'] ?? '—') ?></span>
                                <?php if ($row['remarks']): ?>
                                <span class="col-span-2"><span class="font-semibold">Remarks:</span> <?= htmlspecialchars($row['remarks']) ?></span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../../includes/footer.php'; ?>
