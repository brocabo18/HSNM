<?php
require_once '../../config.php';
requireLogin();

$success_msg = $error_msg = '';
$current_user = $_SESSION['username'] ?? '';
$current_role = $_SESSION['role'] ?? 'viewer';
$current_fullname = $_SESSION['full_name'] ?? $current_user;

// Only admins and supervisors may approve/reject
$can_approve = in_array($current_role, ['admin', 'supervisor']);

// ── Ensure table exists ───────────────────────────────────────────────────────
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS pending_computers (
            id               SERIAL PRIMARY KEY,
            department       TEXT, end_user TEXT, mr_par TEXT,
            status           TEXT DEFAULT 'Working', transferred_to TEXT,
            control_number   TEXT NOT NULL,
            system_unit TEXT, system_unit_sn TEXT, monitor TEXT, monitor_sn TEXT,
            mouse TEXT, mouse_sn TEXT, keyboard TEXT, keyboard_sn TEXT,
            printer TEXT, printer_sn TEXT, scanner TEXT, scanner_sn TEXT,
            avr_ups TEXT, avr_ups_sn TEXT,
            processor TEXT, memory TEXT, storage TEXT,
            os TEXT, os_product_key TEXT, license TEXT DEFAULT 'N',
            microsoft_office TEXT, ms_office_email TEXT,
            ip_address TEXT, mac_address TEXT,
            endpoint_secure TEXT DEFAULT 'N', firewall TEXT DEFAULT 'N',
            checked_by TEXT, encoded_by TEXT, remarks TEXT, remarks_date DATE,
            submitted_by_user_id INTEGER, submitted_by_name TEXT,
            confirmed_by_supervisor TEXT,
            approval_status TEXT DEFAULT 'Pending',
            reviewed_by TEXT, review_note TEXT, reviewed_at TIMESTAMPTZ,
            created_at TIMESTAMPTZ DEFAULT NOW(), updated_at TIMESTAMPTZ DEFAULT NOW()
        )
    ");
} catch (Exception $e) {
    // Table may already exist
}

// ── Handle Approve / Reject ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error_msg = "Security validation failed. Please refresh and try again.";
    } elseif ($_POST['action'] === 'approve' || $_POST['action'] === 'reject') {
        $pending_id  = (int)($_POST['pending_id'] ?? 0);
        $review_note = trim($_POST['review_note'] ?? '');
        $new_status  = $_POST['action'] === 'approve' ? 'Approved' : 'Rejected';

        if (!$can_approve) {
            $error_msg = "You do not have permission to approve or reject submissions.";
        } elseif (!$pending_id) {
            $error_msg = "Invalid submission ID.";
        } else {
            try {
                // Fetch the pending record
                $fetch = $pdo->prepare("SELECT * FROM pending_computers WHERE id = ? AND approval_status = 'Pending'");
                $fetch->execute([$pending_id]);
                $pc = $fetch->fetch(PDO::FETCH_ASSOC);

                if (!$pc) {
                    $error_msg = "Submission not found or already processed.";
                } else {
                    // Update pending record
                    $upd = $pdo->prepare("
                        UPDATE pending_computers SET
                            approval_status = ?,
                            reviewed_by     = ?,
                            review_note     = ?,
                            reviewed_at     = NOW(),
                            updated_at      = NOW()
                        WHERE id = ?
                    ");
                    $upd->execute([$new_status, $current_fullname, $review_note, $pending_id]);

                    if ($new_status === 'Approved') {
                        // Insert into the computers table
                        $ins = $pdo->prepare("
                            INSERT INTO computers (
                                department, end_user, mr_par, status, transferred_to,
                                control_number, system_unit, system_unit_sn,
                                monitor, monitor_sn, mouse, mouse_sn, keyboard, keyboard_sn,
                                printer, printer_sn, scanner, scanner_sn, avr_ups, avr_ups_sn,
                                processor, memory, storage, os, os_product_key, license,
                                microsoft_office, ms_office_email, ip_address, mac_address,
                                endpoint_secure, firewall, checked_by, encoded_by, remarks, remarks_date
                            ) VALUES (
                                ?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?
                            )
                        ");
                        $ins->execute([
                            $pc['department'], $pc['end_user'], $pc['mr_par'],
                            $pc['status'], $pc['transferred_to'],
                            $pc['control_number'], $pc['system_unit'], $pc['system_unit_sn'],
                            $pc['monitor'], $pc['monitor_sn'], $pc['mouse'], $pc['mouse_sn'],
                            $pc['keyboard'], $pc['keyboard_sn'],
                            $pc['printer'], $pc['printer_sn'], $pc['scanner'], $pc['scanner_sn'],
                            $pc['avr_ups'], $pc['avr_ups_sn'],
                            $pc['processor'], $pc['memory'], $pc['storage'],
                            $pc['os'], $pc['os_product_key'], $pc['license'],
                            $pc['microsoft_office'], $pc['ms_office_email'],
                            $pc['ip_address'], $pc['mac_address'],
                            $pc['endpoint_secure'], $pc['firewall'],
                            $pc['checked_by'], $pc['encoded_by'],
                            $pc['remarks'], $pc['remarks_date'],
                        ]);
                        $computer_id = $pdo->lastInsertId();

                        // Sync firewall status
                        if (!empty($pc['control_number'])) {
                            try {
                                $fw_val = ($pc['firewall'] === 'Y') ? 'Yes' : 'No';
                                $fw_sync = $pdo->prepare("
                                    INSERT INTO firewall_status (control_number, status, last_edited_by, updated_at)
                                    VALUES (?, ?, ?, NOW())
                                    ON CONFLICT (control_number) DO UPDATE
                                        SET status = EXCLUDED.status,
                                            last_edited_by = EXCLUDED.last_edited_by,
                                            updated_at = NOW()
                                ");
                                $fw_sync->execute([$pc['control_number'], $fw_val, $current_user]);
                            } catch (Exception $fe) { /* silent */ }
                        }

                        // Sync to asset inventory
                        if (function_exists('syncComputerToAsset')) syncComputerToAsset($pdo, $computer_id);

                        logAudit($pdo, 'approve_computer',
                            "Approved pending Computer | Control #: {$pc['control_number']} | Submitted by: {$pc['submitted_by_name']} | Supervisor: {$pc['confirmed_by_supervisor']}",
                            'computer', $computer_id);

                        $success_msg = "Computer <strong>{$pc['control_number']}</strong> approved and added to the inventory.";
                    } else {
                        logAudit($pdo, 'reject_computer',
                            "Rejected pending Computer | Control #: {$pc['control_number']} | Note: $review_note",
                            'computer');
                        $success_msg = "Submission for <strong>{$pc['control_number']}</strong> has been rejected.";
                    }
                }
            } catch (Exception $e) {
                $error_msg = "Error: " . $e->getMessage();
            }
        }
    }
}

// ── Fetch pending records ─────────────────────────────────────────────────────
$filter_status = $_GET['filter'] ?? 'Pending';
$valid_statuses = ['Pending', 'Approved', 'Rejected', 'All'];
if (!in_array($filter_status, $valid_statuses)) $filter_status = 'Pending';

$where = $filter_status === 'All' ? '1=1' : "approval_status = ?";
$params = $filter_status === 'All' ? [] : [$filter_status];

try {
    $stmt = $pdo->prepare("SELECT * FROM pending_computers WHERE $where ORDER BY created_at DESC LIMIT 200");
    $stmt->execute($params);
    $pending_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $pending_list = [];
    $error_msg = "Could not fetch submissions: " . $e->getMessage();
}

$pending_count = 0;
try {
    $pending_count = (int)$pdo->query("SELECT COUNT(*) FROM pending_computers WHERE approval_status = 'Pending'")->fetchColumn();
} catch (Exception $e) {}

$page_title = "Supervisor Approval";
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
?>

<div class="p-4 md:p-8 flex-1 overflow-y-auto custom-scrollbar relative z-10 bg-slate-50 dark:bg-background-dark">

    <!-- Page Header -->
    <header class="-mt-4 -mx-4 md:-mt-8 md:-mx-8 px-4 md:px-8 pt-4 md:pt-8 pb-4 mb-6 flex flex-col gap-3 sticky top-0 bg-slate-50 dark:bg-background-dark z-20 border-b border-slate-200 dark:border-[#232b3d]">
        <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-3 w-full">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <span class="px-2 py-0.5 rounded text-[11px] font-bold tracking-wider uppercase bg-amber-500/10 text-amber-500 border border-amber-500/20">Supervisor Approval</span>
                    <?php if ($pending_count > 0): ?>
                        <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-red-500/10 text-red-500 border border-red-500/20"><?= $pending_count ?> Pending</span>
                    <?php endif; ?>
                </div>
                <h2 class="text-2xl font-bold text-slate-900 dark:text-white">Computer Approval Queue</h2>
                <p class="text-xs text-slate-400 mt-0.5">Review and approve new computer additions submitted by staff.</p>
            </div>
            <!-- Filter tabs -->
            <div class="flex items-center gap-2 flex-wrap">
                <?php foreach (['Pending' => 'amber', 'Approved' => 'emerald', 'Rejected' => 'red', 'All' => 'slate'] as $s => $color): ?>
                    <a href="?filter=<?= $s ?>"
                       class="px-4 py-1.5 rounded-xl text-xs font-bold border transition-colors
                              <?= $filter_status === $s
                                  ? "bg-$color-500/20 text-$color-600 dark:text-$color-400 border-$color-400/40"
                                  : 'bg-white dark:bg-[#1a2130] text-slate-500 border-slate-200 dark:border-[#232b3d] hover:border-slate-300' ?>">
                        <?= $s ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </header>

    <!-- Alerts -->
    <?php if ($success_msg): ?>
        <div class="mb-4 p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-600 dark:text-emerald-400 text-xs font-semibold flex items-center justify-between">
            <div class="flex items-center gap-2"><span class="material-symbols-outlined text-sm">check_circle</span><span><?= $success_msg ?></span></div>
            <button onclick="this.parentElement.remove()"><span class="material-symbols-outlined text-sm">close</span></button>
        </div>
    <?php endif; ?>
    <?php if ($error_msg): ?>
        <div class="mb-4 p-3 rounded-xl bg-red-500/10 border border-red-500/20 text-red-500 text-xs font-semibold flex items-center justify-between">
            <div class="flex items-center gap-2"><span class="material-symbols-outlined text-sm">error</span><?= htmlspecialchars($error_msg) ?></div>
            <button onclick="this.parentElement.remove()"><span class="material-symbols-outlined text-sm">close</span></button>
        </div>
    <?php endif; ?>

    <?php if (empty($pending_list)): ?>
        <div class="bg-white dark:bg-[#1a2130] rounded-2xl border border-slate-200 dark:border-[#232b3d] p-12 text-center">
            <span class="material-symbols-outlined text-5xl text-slate-300 dark:text-slate-600">inbox</span>
            <p class="text-slate-500 mt-3 font-semibold">No <?= strtolower($filter_status) ?> submissions.</p>
        </div>
    <?php else: ?>
        <div class="space-y-4">
        <?php foreach ($pending_list as $pc): ?>
            <?php
            $status_color = match($pc['approval_status']) {
                'Approved' => 'emerald',
                'Rejected' => 'red',
                default    => 'amber',
            };
            ?>
            <div class="bg-white dark:bg-[#1a2130] rounded-2xl border border-slate-200 dark:border-[#232b3d] shadow-sm overflow-hidden">
                <!-- Card Header -->
                <div class="flex flex-col md:flex-row items-start md:items-center justify-between px-5 py-4 border-b border-slate-100 dark:border-[#232b3d] gap-3">
                    <div class="flex items-center gap-3 flex-wrap">
                        <span class="font-mono font-bold text-primary text-sm"><?= htmlspecialchars($pc['control_number']) ?></span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-<?= $status_color ?>-100 text-<?= $status_color ?>-700 dark:bg-<?= $status_color ?>-900/30 dark:text-<?= $status_color ?>-400">
                            <?= htmlspecialchars($pc['approval_status']) ?>
                        </span>
                        <span class="text-xs text-slate-400">
                            Submitted <?= date('M d, Y H:i', strtotime($pc['created_at'])) ?>
                            <?php if ($pc['submitted_by_name']): ?>
                                by <span class="font-semibold text-slate-600 dark:text-slate-300"><?= htmlspecialchars($pc['submitted_by_name']) ?></span>
                            <?php endif; ?>
                        </span>
                        <?php if ($pc['confirmed_by_supervisor']): ?>
                            <span class="inline-flex items-center gap-1 text-[11px] text-amber-600 dark:text-amber-400 font-semibold bg-amber-50 dark:bg-amber-900/20 px-2 py-0.5 rounded-lg border border-amber-200 dark:border-amber-800">
                                <span class="material-symbols-outlined text-[13px]">verified_user</span>
                                Supervisor: <?= htmlspecialchars($pc['confirmed_by_supervisor']) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="flex items-center gap-2">
                        <button onclick="toggleDetails(<?= $pc['id'] ?>)"
                            class="text-xs font-bold text-primary hover:underline flex items-center gap-1">
                            <span class="material-symbols-outlined text-[15px]">expand_more</span> Details
                        </button>
                    </div>
                </div>

                <!-- Summary row -->
                <div class="px-5 py-3 grid grid-cols-2 md:grid-cols-4 gap-3 text-xs">
                    <div>
                        <span class="text-slate-400 block text-[10px] uppercase font-bold">End User</span>
                        <span class="font-semibold text-slate-800 dark:text-white"><?= htmlspecialchars($pc['end_user'] ?: '—') ?></span>
                    </div>
                    <div>
                        <span class="text-slate-400 block text-[10px] uppercase font-bold">Location</span>
                        <span class="text-slate-600 dark:text-slate-300"><?= htmlspecialchars($pc['department'] ?: '—') ?></span>
                    </div>
                    <div>
                        <span class="text-slate-400 block text-[10px] uppercase font-bold">System Unit</span>
                        <span class="text-slate-600 dark:text-slate-300"><?= htmlspecialchars($pc['system_unit'] ?: '—') ?></span>
                    </div>
                    <div>
                        <span class="text-slate-400 block text-[10px] uppercase font-bold">IP Address</span>
                        <span class="font-mono text-slate-600 dark:text-slate-300"><?= htmlspecialchars($pc['ip_address'] ?: '—') ?></span>
                    </div>
                </div>

                <!-- Expandable Details -->
                <div id="details-<?= $pc['id'] ?>" class="hidden border-t border-slate-100 dark:border-[#232b3d] px-5 py-4">
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-xs mb-4">
                        <?php
                        $detail_fields = [
                            'MR/PAR' => $pc['mr_par'], 'Status' => $pc['status'],
                            'Processor' => $pc['processor'], 'Memory' => $pc['memory'],
                            'Storage' => $pc['storage'], 'OS' => $pc['os'],
                            'OS Product Key' => $pc['os_product_key'], 'License' => $pc['license'],
                            'MS Office' => $pc['microsoft_office'], 'MS Office Email' => $pc['ms_office_email'],
                            'Monitor' => $pc['monitor'], 'Monitor S/N' => $pc['monitor_sn'],
                            'Mouse' => $pc['mouse'], 'Keyboard' => $pc['keyboard'],
                            'Printer' => $pc['printer'], 'Scanner' => $pc['scanner'],
                            'AVR/UPS' => $pc['avr_ups'], 'MAC Address' => $pc['mac_address'],
                            'Endpoint Secure' => ($pc['endpoint_secure'] === 'Y' ? 'Yes' : 'No'),
                            'Firewall' => ($pc['firewall'] === 'Y' ? 'Yes' : 'No'),
                            'Checked By' => $pc['checked_by'], 'Encoded By' => $pc['encoded_by'],
                            'Remarks' => $pc['remarks'],
                        ];
                        foreach ($detail_fields as $label => $val):
                        ?>
                            <div>
                                <span class="text-slate-400 block text-[10px] uppercase font-bold"><?= $label ?></span>
                                <span class="text-slate-700 dark:text-slate-200"><?= htmlspecialchars($val ?: '—') ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($pc['approval_status'] !== 'Pending'): ?>
                        <!-- Already reviewed -->
                        <div class="bg-slate-50 dark:bg-[#101622] rounded-xl p-3 text-xs text-slate-500">
                            <span class="font-bold text-slate-700 dark:text-slate-300">Reviewed by <?= htmlspecialchars($pc['reviewed_by'] ?? '—') ?></span>
                            on <?= $pc['reviewed_at'] ? date('M d, Y H:i', strtotime($pc['reviewed_at'])) : '—' ?>
                            <?php if ($pc['review_note']): ?>
                                <br><span class="text-slate-500">Note: <?= htmlspecialchars($pc['review_note']) ?></span>
                            <?php endif; ?>
                        </div>
                    <?php elseif ($can_approve): ?>
                        <!-- Approve / Reject form -->
                        <form method="POST" class="space-y-3 mt-2">
                            <?= getCsrfInput() ?>
                            <input type="hidden" name="pending_id" value="<?= $pc['id'] ?>">
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Review Note (optional)</label>
                                <textarea name="review_note" rows="2" placeholder="Add a note for the submitter..."
                                    class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] rounded-xl px-3 py-2 text-sm text-slate-800 dark:text-white focus:ring-2 focus:ring-primary resize-none"></textarea>
                            </div>
                            <div class="flex items-center gap-3">
                                <button type="submit" name="action" value="approve"
                                    onclick="return confirm('Approve and add this computer to the inventory?')"
                                    class="inline-flex items-center gap-1.5 px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl transition-colors shadow-sm">
                                    <span class="material-symbols-outlined text-[16px]">check_circle</span> Approve & Add to Inventory
                                </button>
                                <button type="submit" name="action" value="reject"
                                    onclick="return confirm('Reject this submission?')"
                                    class="inline-flex items-center gap-1.5 px-5 py-2 bg-red-600 hover:bg-red-700 text-white text-xs font-bold rounded-xl transition-colors shadow-sm">
                                    <span class="material-symbols-outlined text-[16px]">cancel</span> Reject
                                </button>
                            </div>
                        </form>
                    <?php else: ?>
                        <p class="text-xs text-amber-600 dark:text-amber-400 font-semibold flex items-center gap-1.5 mt-2">
                            <span class="material-symbols-outlined text-[15px]">lock</span>
                            Awaiting supervisor/admin review.
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
function toggleDetails(id) {
    const el = document.getElementById('details-' + id);
    if (!el) return;
    el.classList.toggle('hidden');
}
</script>

<?php require_once '../../includes/footer.php'; ?>
