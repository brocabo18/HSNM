<?php
require_once '../../config.php';
requireLogin();

$success_msg = '';
$error_msg = '';

$view = $_GET['view'] ?? 'mismatch'; // 'mismatch' or 'conflict'
$tab_class = "px-4 py-2 text-sm font-bold rounded-lg transition-colors";
$active_tab = "bg-primary text-white";
$inactive_tab = "text-slate-400 hover:text-slate-900 dark:hover:text-white hover:bg-[#232b3d]";

// Search Parameter
$search = $_GET['search'] ?? '';
$is_ajax = isset($_GET['ajax']);

// --- ACTION HANDLERS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // --- ACTIONS FOR DATA MISMATCH VIEW ---
    if ($view === 'mismatch') {
        $control_number = $_POST['control_number'] ?? '';
        if ($control_number) {
            if ($_POST['action'] === 'retain_ip') {
                // "Retain IP Inventory" -> Update Computer Inventory
                try {
                    $stmt = $pdo->prepare("SELECT ip_address, mac_address FROM ips WHERE control_number = ? LIMIT 1");
                    $stmt->execute([$control_number]);
                    $ip_data = $stmt->fetch();

                    if ($ip_data) {
                        $update = $pdo->prepare("UPDATE computers SET ip_address = ?, mac_address = ? WHERE control_number = ?");
                        $update->execute([$ip_data['ip_address'], $ip_data['mac_address'], $control_number]);
                        
                        logAudit($pdo, 'reconcile_update', "Synced Computer Inventory to match IP Inventory for $control_number", 'reconciliation', null);
                        $success_msg = "Computer Inventory updated successfully for $control_number.";
                    } else {
                        $error_msg = "Source data missing in IP Inventory.";
                    }
                } catch (Exception $e) {
                    $error_msg = "Error updating Computer Inventory: " . $e->getMessage();
                }
            } elseif ($_POST['action'] === 'retain_computer') {
                // "Retain Computer Inventory" -> Update IP Inventory
                try {
                    $stmt = $pdo->prepare("SELECT ip_address, mac_address FROM computers WHERE control_number = ? LIMIT 1");
                    $stmt->execute([$control_number]);
                    $comp_data = $stmt->fetch();

                    if ($comp_data) {
                        // Get the exact ips row (id + current ip) that belongs to this control_number
                        $own_row = $pdo->prepare("SELECT id, ip_address FROM ips WHERE control_number = ? LIMIT 1");
                        $own_row->execute([$control_number]);
                        $own_record = $own_row->fetch();
                        $own_id  = $own_record ? (int)$own_record['id'] : 0;

                        if (!$own_id) {
                            $error_msg = "No matching IP Inventory row found for control number $control_number.";
                        } else {
                            // Check if the target IP already exists in a DIFFERENT ips row
                            $check_dup = $pdo->prepare("SELECT id, control_number FROM ips WHERE ip_address = ? AND id != ?");
                            $check_dup->execute([$comp_data['ip_address'], $own_id]);
                            $existing = $check_dup->fetch();

                            if ($existing) {
                                // Auto-resolve: clear the IP and MAC from the conflicting row
                                // so this address can be assigned to the correct control number.
                                $clear_conflict = $pdo->prepare("UPDATE ips SET ip_address = '', mac_address = '' WHERE id = ?");
                                $clear_conflict->execute([$existing['id']]);

                                $assigned_to = !empty($existing['control_number'])
                                    ? $existing['control_number']
                                    : 'an unassigned IP Inventory record';
                                logAudit($pdo, 'reconcile_conflict_clear', "Cleared IP/MAC from conflicting IP Inventory record (ID: {$existing['id']}, CN: $assigned_to) to free address {$comp_data['ip_address']} for $control_number", 'reconciliation', null);
                            }
                            // Proceed: either no conflict existed, or we just cleared it
                            if (true) {
                                // Update only the specific row by id to avoid touching sibling rows
                                $update = $pdo->prepare("UPDATE ips SET ip_address = ?, mac_address = ? WHERE id = ?");
                                $update->execute([$comp_data['ip_address'], $comp_data['mac_address'], $own_id]);

                                logAudit($pdo, 'reconcile_update', "Synced IP Inventory to match Computer Inventory for $control_number", 'reconciliation', null);
                                $success_msg = "IP Inventory updated successfully for $control_number.";
                            }
                        }
                    } else {
                        $error_msg = "Source data missing in Computer Inventory.";
                    }
                } catch (Exception $e) {
                    $error_msg = "Error updating IP Inventory: " . $e->getMessage();
                }
            }
        }
    } 
    
    // --- ACTIONS FOR IDENTITY CONFLICT VIEW ---
    elseif ($view === 'conflict') {
        if ($_POST['action'] === 'set_ip_owner') {
             // "Set IP Owner" -> Update IP Inventory's Control # to match Computer's
             $target_ip_id = $_POST['ip_id'] ?? '';
             $new_control_number = $_POST['new_control_number'] ?? '';
             
             if ($target_ip_id && $new_control_number) {
                 try {
                     // Update control number, status to active, and last_seen timestamp
                     $update = $pdo->prepare("UPDATE ips SET control_number = ?, status = 'active', last_seen = NOW() WHERE id = ?");
                     $update->execute([$new_control_number, $target_ip_id]);
                     
                     logAudit($pdo, 'reconcile_identity_conflict', "Updated IP Inventory Control # to $new_control_number and set status to Active", 'reconciliation', null);
                     $success_msg = "IP ownership transferred to $new_control_number and status set to Active.";
                 } catch (Exception $e) {
                     $error_msg = "Error updating IP ownership: " . $e->getMessage();
                 }
             }
        } elseif ($_POST['action'] === 'update_comp_cn') {
            // "Update Computer CN" -> Update Computer Inventory's Control # to match IP's
            $target_comp_id = $_POST['comp_id'] ?? '';
            $new_control_number = $_POST['new_control_number'] ?? '';
            $ip_id = $_POST['ip_id'] ?? ''; // Get IP ID to update its status
            
            if ($target_comp_id && $new_control_number) {
                try {
                    // Update computer control number
                    $update = $pdo->prepare("UPDATE computers SET control_number = ? WHERE id = ?");
                    $update->execute([$new_control_number, $target_comp_id]);
                    
                    // Also update IP status to active if IP ID is provided
                    if ($ip_id) {
                        $update_ip = $pdo->prepare("UPDATE ips SET status = 'active', last_seen = NOW() WHERE id = ?");
                        $update_ip->execute([$ip_id]);
                    }
                    
                    logAudit($pdo, 'reconcile_identity_conflict', "Updated Computer Inventory Control # to $new_control_number and set IP status to Active", 'reconciliation', null);
                    $success_msg = "Computer Control Number updated to $new_control_number and IP status set to Active.";
                } catch (Exception $e) {
                    $error_msg = "Error updating Computer Control Number: " . $e->getMessage();
                }
            }
        } elseif ($_POST['action'] === 'transfer_comp_to_ip') {
            // "Transfer Computer Data to IP" -> Full Update of IP record
            $comp_id = $_POST['comp_id'] ?? '';
            $ip_id = $_POST['ip_id'] ?? '';

            if ($comp_id && $ip_id) {
                try {
                    // Fetch source computer data
                    $stmt_src = $pdo->prepare("SELECT control_number, end_user, department, mac_address FROM computers WHERE id = ?");
                    $stmt_src->execute([$comp_id]);
                    $comp_data = $stmt_src->fetch(PDO::FETCH_ASSOC);

                    if ($comp_data) {
                        // Update IP Inventory
                        $update = $pdo->prepare("
                            UPDATE ips 
                            SET 
                                control_number = ?, 
                                om_name = ?, 
                                department = ?, 
                                mac_address = ?, 
                                status = 'active', 
                                last_seen = NOW() 
                            WHERE id = ?
                        ");
                        $update->execute([
                            $comp_data['control_number'],
                            $comp_data['end_user'],
                            $comp_data['department'],
                            $comp_data['mac_address'],
                            $ip_id
                        ]);

                        logAudit($pdo, 'reconcile_transfer', "Transferred Computer Data ({$comp_data['control_number']}) to IP ID $ip_id", 'reconciliation', null);
                        $success_msg = "Successfully transferred Computer Inventory data ({$comp_data['control_number']}) to IP Inventory.";
                    } else {
                        $error_msg = "Computer record not found.";
                    }
                } catch (Exception $e) {
                    $error_msg = "Error transferring data: " . $e->getMessage();
                }
            }
        }
    }
}

// --- DATA FETCHING ---
// Pagination Defaults
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 25;
if ($limit < 1) $limit = 25;
$offset = ($page - 1) * $limit;

// Search Params
$params = [];
$search_condition = "";

if ($search) {
    if ($view === 'mismatch') {
        // Search in Mismatches: Control #, User, IPs, MACs
        $search_condition = "AND (
            c.control_number ILIKE ? OR 
            c.end_user ILIKE ? OR 
            i.ip_address ILIKE ? OR 
            c.ip_address ILIKE ? OR
            i.mac_address ILIKE ? OR
            c.mac_address ILIKE ?
        )";
        $term = "%$search%";
        $params = array_fill(0, 6, $term); // 6 placeholders
    } elseif ($view === 'conflict') {
        // Search in Conflicts: IP CN, Comp CN, User, IPs, MACs
        $search_condition = "AND (
            i.control_number ILIKE ? OR 
            c.control_number ILIKE ? OR 
            c.end_user ILIKE ? OR 
            i.ip_address ILIKE ? OR 
            c.ip_address ILIKE ? OR
            i.mac_address ILIKE ? OR
            c.mac_address ILIKE ?
        )";
        $term = "%$search%";
        $params = array_fill(0, 7, $term); // 7 placeholders
    }
}

if ($view === 'mismatch') {
    // 1. Data Mismatches (Same CN, Diff Data)
    $where_condition = "(COALESCE(i.ip_address, '') != COALESCE(c.ip_address, '')) OR (COALESCE(i.mac_address, '') != COALESCE(c.mac_address, ''))";
    
    $base_sql = "FROM computers c INNER JOIN ips i ON c.control_number = i.control_number WHERE $where_condition $search_condition";
    
    $count_sql = "SELECT COUNT(*) $base_sql";
    
    $sql = "
    SELECT 
        c.control_number, c.end_user, c.department,
        i.ip_address as ip_inv_ip, i.mac_address as ip_inv_mac,
        c.ip_address as comp_inv_ip, c.mac_address as comp_inv_mac
    $base_sql
    ORDER BY c.control_number ASC
    LIMIT $limit OFFSET $offset";

} elseif ($view === 'conflict') {
    // 2. Identity Conflicts (Diff CN, Same Data)
    // Detect conflicts when either IP or MAC address is in use by different control numbers
    $where_condition = "
        (i.control_number != c.control_number OR (i.control_number IS NOT NULL AND i.control_number != '' AND (c.control_number IS NULL OR c.control_number = '')))
        AND (
            (i.ip_address = c.ip_address AND i.ip_address != '' AND i.ip_address IS NOT NULL AND c.ip_address NOT LIKE '%DHCP%')
            OR 
            (i.mac_address = c.mac_address AND i.mac_address != '' AND i.mac_address IS NOT NULL)
        )
    ";
    
    $base_sql = "FROM computers c INNER JOIN ips i ON (i.ip_address = c.ip_address OR i.mac_address = c.mac_address) WHERE $where_condition $search_condition";
    
    $count_sql = "SELECT COUNT(*) $base_sql";
    
    $sql = "
    SELECT 
        i.id as ip_id, c.id as comp_id,
        i.control_number as ip_cn,
        c.control_number as comp_cn,
        c.end_user as comp_user,
        
        i.ip_address as ip_inv_ip,
        i.mac_address as ip_inv_mac,
        c.ip_address as comp_inv_ip,
        c.mac_address as comp_inv_mac
    $base_sql
    ORDER BY i.ip_address ASC
    LIMIT $limit OFFSET $offset";
}

// Execute Count Query
$stmt_count = $pdo->prepare($count_sql);
$stmt_count->execute($params);
$total_items = $stmt_count->fetchColumn();
$total_pages = ceil($total_items / $limit);

// Execute Main Query
$stmt = $pdo->prepare($sql);
$stmt->execute($params); // Fetch needs same params
$discrepancies = $stmt->fetchAll();

// --- RENDER START ---

// If AJAX, only render the table container content
if ($is_ajax) {
    ob_start();
} else {
    $page_title = "Data Reconciliation";
    require_once '../../includes/header.php';
    require_once '../../includes/sidebar.php';
}
?>

<?php if (!$is_ajax): ?>
<div class="p-4 md:p-8 flex-1 overflow-y-auto custom-scrollbar relative z-10 bg-slate-50 dark:bg-background-dark">
    <header class="-mt-8 -mx-8 px-8 pt-8 pb-6 mb-6 flex flex-col md:flex-row items-start md:items-end justify-between gap-4 sticky top-0 bg-slate-50 dark:bg-background-dark z-20 border-b border-slate-200 dark:border-[#232b3d]">
        <div>
            <h2 class="text-2xl font-bold text-slate-900 dark:text-white">Compare & Sync</h2>
            <p class="text-sm text-slate-500 mt-1">Resolve discrepancies between IP Inventory and Computer Inventory.</p>
        </div>
        <div class="flex items-center gap-2 md:gap-3 flex-wrap">
             <div class="relative">
                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-500 text-[20px]">search</span>
                <input type="text" 
                       id="liveSearchInput"
                       placeholder="Search live..." 
                       value="<?= htmlspecialchars($search) ?>"
                       class="pl-10 pr-4 py-2 bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] text-slate-700 dark:text-slate-300 rounded-lg text-sm focus:outline-none focus:border-emerald-500/50 focus:ring-1 focus:ring-emerald-500/50 w-64 placeholder:text-slate-600 transition-all">
            </div>
            <button onclick="window.location.reload()" 
                class="flex items-center justify-center rounded-lg h-9 px-4 bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] text-slate-700 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white hover:bg-[#232b3d] transition-colors text-xs font-bold">
                <span class="material-symbols-outlined text-[18px] mr-2">refresh</span> Refresh
            </button>
        </div>
    </header>

    <!-- View Tabs -->
    <div class="flex gap-2 mb-6 border-b border-slate-200 dark:border-[#232b3d] pb-1">
        <a href="?view=mismatch" class="<?= $view === 'mismatch' ? $active_tab : $inactive_tab ?> <?= $tab_class ?>">
            Data Mismatches
        </a>
        <a href="?view=conflict" class="<?= $view === 'conflict' ? $active_tab : $inactive_tab ?> <?= $tab_class ?>">
            Identity Conflicts
        </a>
    </div>

    <?php if ($success_msg): ?>
        <div class="mb-6 bg-emerald-500/10 border border-emerald-500/20 text-emerald-500 p-4 rounded-xl text-sm font-medium flex items-center gap-3">
            <span class="material-symbols-outlined">check_circle</span><?= htmlspecialchars($success_msg) ?>
        </div>
    <?php endif; ?>
    <?php if ($error_msg): ?>
        <div class="mb-6 bg-red-500/10 border border-red-500/20 text-red-500 p-4 rounded-xl text-sm font-medium flex items-center gap-3">
            <span class="material-symbols-outlined">error</span><?= htmlspecialchars($error_msg) ?>
        </div>
    <?php endif; ?>

    <div id="reconciliation-container" class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] rounded-2xl overflow-hidden shadow-sm transition-opacity duration-200">
<?php endif; // End non-AJAX wrapper ?>

        <!-- CONTENT TO RELOAD VIA AJAX -->
        <div class="p-4 border-b border-slate-200 dark:border-[#232b3d] flex items-center justify-between">
            <h3 class="text-white font-bold flex items-center gap-2">
                <span class="material-symbols-outlined text-amber-500">warning</span>
                <?= $view === 'mismatch' ? 'Data Mismatches' : 'Identity Conflicts' ?> Found: <span class="text-white"><?= $total_items ?></span>
            </h3>
        </div>
        
        <div class="overflow-x-auto custom-scrollbar">
            <table class="w-full text-left border-collapse">
                <!-- HEADERS BASED ON VIEW -->
                <thead>
                    <tr class="bg-[#151b26] border-b border-slate-200 dark:border-[#232b3d]">
                        <?php if ($view === 'mismatch'): ?>
                            <th class="px-4 py-3 text-xs font-bold text-slate-500 uppercase tracking-wider">Device Info</th>
                            <th class="px-4 py-3 text-xs font-bold text-emerald-500 uppercase tracking-wider bg-emerald-500/5 border-l border-slate-200 dark:border-[#232b3d] border-r">IP Inventory Data</th>
                            <th class="px-4 py-3 text-xs font-bold text-blue-500 uppercase tracking-wider bg-blue-500/5 border-r border-slate-200 dark:border-[#232b3d]">Computer Inventory Data</th>
                        <?php else: ?>
                            <th class="px-4 py-3 text-xs font-bold text-slate-500 uppercase tracking-wider">IP Inventory Data</th>
                            <th class="px-4 py-3 text-xs font-bold text-emerald-500 uppercase tracking-wider bg-emerald-500/5 border-l border-slate-200 dark:border-[#232b3d] border-r">IP Owner (IP Inv)</th>
                            <th class="px-4 py-3 text-xs font-bold text-blue-500 uppercase tracking-wider bg-blue-500/5 border-r border-slate-200 dark:border-[#232b3d]">Reported Owner (Comp Inv)</th>
                        <?php endif; ?>
                        <th class="px-4 py-3 text-xs font-bold text-slate-500 uppercase tracking-wider text-right">Action</th>
                    </tr>
                </thead>

                <!-- BODY BASED ON VIEW -->
                <tbody class="divide-y divide-[#232b3d]/50">
                    <?php if (empty($discrepancies)): ?>
                        <tr>
                            <td colspan="4" class="px-6 py-12 text-center text-slate-500">
                                <span class="material-symbols-outlined text-4xl mb-2 opacity-50">search_off</span>
                                <p>No discrepancies found matching your search.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($discrepancies as $row): ?>
                            <tr class="hover:bg-white/5 transition-colors">
                            
                            <?php if ($view === 'mismatch'): ?>
                                <?php 
                                    $ip_diff = trim($row['ip_inv_ip']) !== trim($row['comp_inv_ip']);
                                    $mac_diff = trim($row['ip_inv_mac']) !== trim($row['comp_inv_mac']);
                                ?>
                                <td class="px-4 py-4 align-top">
                                    <div class="font-bold text-slate-900 dark:text-white text-sm"><?= htmlspecialchars($row['control_number']) ?></div>
                                    <div class="text-xs text-slate-400 mt-0.5"><?= htmlspecialchars($row['end_user']) ?></div>
                                </td>
                                
                                <td class="px-4 py-4 align-top bg-emerald-500/5 border-l border-slate-200 dark:border-[#232b3d] border-r">
                                    <div class="flex flex-col gap-1">
                                        <div class="flex items-center gap-2 text-xs">
                                            <span class="text-slate-500 w-8">IP:</span>
                                            <span class="font-mono <?= $ip_diff ? 'text-amber-400 font-bold' : 'text-slate-300' ?>">
                                                <?= htmlspecialchars($row['ip_inv_ip'] ?: '--') ?>
                                            </span>
                                        </div>
                                        <div class="flex items-center gap-2 text-xs">
                                            <span class="text-slate-500 w-8">MAC:</span>
                                            <span class="font-mono <?= $mac_diff ? 'text-amber-400 font-bold' : 'text-slate-300' ?>">
                                                <?= htmlspecialchars($row['ip_inv_mac'] ?: '--') ?>
                                            </span>
                                        </div>
                                    </div>
                                </td>
                                
                                <td class="px-4 py-4 align-top bg-blue-500/5 border-r border-slate-200 dark:border-[#232b3d]">
                                    <div class="flex flex-col gap-1">
                                        <div class="flex items-center gap-2 text-xs">
                                            <span class="text-slate-500 w-8">IP:</span>
                                            <span class="font-mono <?= $ip_diff ? 'text-amber-400 font-bold' : 'text-slate-300' ?>">
                                                <?= htmlspecialchars($row['comp_inv_ip'] ?: '--') ?>
                                            </span>
                                        </div>
                                        <div class="flex items-center gap-2 text-xs">
                                            <span class="text-slate-500 w-8">MAC:</span>
                                            <span class="font-mono <?= $mac_diff ? 'text-amber-400 font-bold' : 'text-slate-300' ?>">
                                                <?= htmlspecialchars($row['comp_inv_mac'] ?: '--') ?>
                                            </span>
                                        </div>
                                    </div>
                                </td>

                                <td class="px-4 py-4 text-right align-middle">
                                    <div class="flex flex-col gap-2 items-end">
                                        <form method="POST" onsubmit="return confirm('Update COMPUTER INVENTORY with data from IP Inventory?');">
                                            <input type="hidden" name="action" value="retain_ip">
                                            <input type="hidden" name="control_number" value="<?= htmlspecialchars($row['control_number']) ?>">
                                            <button type="submit" class="w-full flex items-center justify-center gap-2 px-3 py-1.5 rounded-lg bg-emerald-500/10 hover:bg-emerald-500 text-emerald-500 hover:text-slate-900 dark:hover:text-white transition-all text-xs font-bold border border-emerald-500/20">
                                                <span class="material-symbols-outlined text-[16px]">arrow_forward</span> Use IP Data
                                            </button>
                                        </form>
                                        
                                        <form method="POST" onsubmit="return confirm('Update IP INVENTORY with data from Computer Inventory?');">
                                            <input type="hidden" name="action" value="retain_computer">
                                            <input type="hidden" name="control_number" value="<?= htmlspecialchars($row['control_number']) ?>">
                                            <button type="submit" class="w-full flex items-center justify-center gap-2 px-3 py-1.5 rounded-lg bg-blue-500/10 hover:bg-blue-500 text-blue-500 hover:text-slate-900 dark:hover:text-white transition-all text-xs font-bold border border-blue-500/20">
                                                <span class="material-symbols-outlined text-[16px]">arrow_back</span> Use Comp Data
                                            </button>
                                        </form>
                                    </div>
                                </td>

                            <?php else: // CONFLICT VIEW ?>
                                <?php 
                                    // Check which identifiers match
                                    $ip_match = (trim($row['ip_inv_ip']) === trim($row['comp_inv_ip']) && !empty(trim($row['ip_inv_ip'])));
                                    $mac_match = (trim($row['ip_inv_mac']) === trim($row['comp_inv_mac']) && !empty(trim($row['ip_inv_mac'])));
                                ?>
                                <td class="px-4 py-4 align-top">
                                    <div class="flex flex-col gap-1">
                                        <div class="flex items-center gap-2 text-xs">
                                            <span class="text-slate-500 w-8">IP:</span>
                                            <span class="font-mono <?= $ip_match ? 'text-amber-400 font-bold' : 'text-slate-300' ?>">
                                                <?= htmlspecialchars($row['ip_inv_ip'] ?: '--') ?>
                                            </span>
                                        </div>
                                        <div class="flex items-center gap-2 text-xs">
                                            <span class="text-slate-500 w-8">MAC:</span>
                                            <span class="font-mono <?= $mac_match ? 'text-amber-400 font-bold' : 'text-slate-300' ?>">
                                                <?= htmlspecialchars($row['ip_inv_mac'] ?: '--') ?>
                                            </span>
                                        </div>
                                    </div>
                                </td>

                                <td class="px-4 py-4 align-top bg-emerald-500/5 border-l border-slate-200 dark:border-[#232b3d] border-r">
                                    <div class="font-bold text-slate-900 dark:text-white text-sm text-red-400"><?= htmlspecialchars($row['ip_cn']) ?></div>
                                    <div class="text-[10px] text-slate-500 italic">Current IP Owner</div>
                                </td>

                                <td class="px-4 py-4 align-top bg-blue-500/5 border-r border-slate-200 dark:border-[#232b3d]">
                                    <div class="flex flex-col gap-1">
                                        <div class="font-bold text-slate-900 dark:text-white text-sm text-emerald-400 mb-1"><?= htmlspecialchars($row['comp_cn']) ?></div>
                                        <div class="text-xs text-slate-400 mb-2"><?= htmlspecialchars($row['comp_user']) ?></div>
                                        <div class="flex items-center gap-2 text-xs">
                                            <span class="text-slate-500 w-8">IP:</span>
                                            <span class="font-mono <?= $ip_match ? 'text-amber-400 font-bold' : 'text-slate-300' ?>">
                                                <?= htmlspecialchars($row['comp_inv_ip'] ?: '--') ?>
                                            </span>
                                        </div>
                                        <div class="flex items-center gap-2 text-xs">
                                            <span class="text-slate-500 w-8">MAC:</span>
                                            <span class="font-mono <?= $mac_match ? 'text-amber-400 font-bold' : 'text-slate-300' ?>">
                                                <?= htmlspecialchars($row['comp_inv_mac'] ?: '--') ?>
                                            </span>
                                        </div>
                                    </div>
                                </td>

                                <td class="px-4 py-4 text-right align-middle">
                                    <div class="flex flex-col gap-2 items-end">
                                        <form method="POST" onsubmit="return confirm('Change IP Inventory owner to <?= htmlspecialchars($row['comp_cn']) ?>?');">
                                            <input type="hidden" name="action" value="set_ip_owner">
                                            <input type="hidden" name="ip_id" value="<?= htmlspecialchars($row['ip_id']) ?>">
                                            <input type="hidden" name="new_control_number" value="<?= htmlspecialchars($row['comp_cn']) ?>">
                                            <button type="submit" class="w-full flex items-center justify-center gap-2 px-3 py-1.5 rounded-lg bg-emerald-500/10 hover:bg-emerald-500 text-emerald-500 hover:text-slate-900 dark:hover:text-white transition-all text-xs font-bold border border-emerald-500/20" title="Transfer IP to <?= htmlspecialchars($row['comp_cn']) ?>">
                                                <span class="material-symbols-outlined text-[16px]">sync_alt</span> IP Owner to <?= htmlspecialchars($row['comp_cn']) ?>
                                            </button>
                                        </form>
                                        
                                        <form method="POST" onsubmit="return confirm('Fully overwrite IP Inventory data with Computer Inventory data (CN, User, Dept, MAC)?');">
                                            <input type="hidden" name="action" value="transfer_comp_to_ip">
                                            <input type="hidden" name="comp_id" value="<?= htmlspecialchars($row['comp_id']) ?>">
                                            <input type="hidden" name="ip_id" value="<?= htmlspecialchars($row['ip_id']) ?>">
                                            <button type="submit" class="w-full flex items-center justify-center gap-2 px-3 py-1.5 rounded-lg bg-indigo-500/10 hover:bg-indigo-500 text-indigo-500 hover:text-slate-900 dark:hover:text-white transition-all text-xs font-bold border border-indigo-500/20" title="Overwrite IP Data with Comp Data">
                                                <span class="material-symbols-outlined text-[16px]">file_copy</span> Sync Data to IP
                                            </button>
                                        </form>

                                        <form method="POST" onsubmit="return confirm('Change Computer Inventory CN to <?= htmlspecialchars($row['ip_cn']) ?>? This is rare.');">
                                            <input type="hidden" name="action" value="update_comp_cn">
                                            <input type="hidden" name="comp_id" value="<?= htmlspecialchars($row['comp_id']) ?>">
                                            <input type="hidden" name="ip_id" value="<?= htmlspecialchars($row['ip_id']) ?>">
                                            <input type="hidden" name="new_control_number" value="<?= htmlspecialchars($row['ip_cn']) ?>">
                                            <button type="submit" class="w-full flex items-center justify-center gap-2 px-3 py-1.5 rounded-lg bg-blue-500/10 hover:bg-blue-500 text-blue-500 hover:text-slate-900 dark:hover:text-white transition-all text-xs font-bold border border-blue-500/20 opacity-50 hover:opacity-100">
                                                <span class="material-symbols-outlined text-[16px]">edit_note</span> Update Comp CN
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            <?php endif; ?>
                            
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Bottom Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="px-6 py-4 border-t border-slate-200 dark:border-[#232b3d] flex items-center justify-between bg-white dark:bg-[#1a2130]">
                <div class="text-xs text-slate-500">
                    Showing <span class="font-bold text-slate-900 dark:text-white"><?= $offset + 1 ?></span> to <span
                        class="font-bold text-slate-900 dark:text-white"><?= min($offset + $limit, $total_items) ?></span> of <span
                        class="font-bold text-slate-900 dark:text-white"><?= $total_items ?></span> results
                </div>
                <div class="flex items-center gap-2">
                    <!-- Helper for pagination links to include search term -->
                    <?php 
                        $base_link = "?view=$view&limit=$limit";
                        if ($search) $base_link .= "&search=" . urlencode($search);
                    ?>
                    
                    <a href="<?= $base_link ?>&page=1"
                        class="p-2 hover:bg-white/5 text-slate-400 hover:text-slate-900 dark:hover:text-white rounded-lg transition-colors <?= $page <= 1 ? 'pointer-events-none opacity-50' : '' ?>"
                        title="First Page">
                        <span class="material-symbols-outlined text-[18px]">first_page</span>
                    </a>
                    <a href="<?= $base_link ?>&page=<?= max(1, $page - 1) ?>"
                        class="p-2 hover:bg-white/5 text-slate-400 hover:text-slate-900 dark:hover:text-white rounded-lg transition-colors <?= $page <= 1 ? 'pointer-events-none opacity-50' : '' ?>">
                        <span class="material-symbols-outlined text-[18px]">chevron_left</span>
                    </a>
                    
                    <a href="<?= $base_link ?>&page=<?= min($total_pages, $page + 1) ?>"
                        class="p-2 hover:bg-white/5 text-slate-400 hover:text-slate-900 dark:hover:text-white rounded-lg transition-colors <?= $page >= $total_pages ? 'pointer-events-none opacity-50' : '' ?>">
                        <span class="material-symbols-outlined text-[18px]">chevron_right</span>
                    </a>
                    <a href="<?= $base_link ?>&page=<?= $total_pages ?>"
                        class="p-2 hover:bg-white/5 text-slate-400 hover:text-slate-900 dark:hover:text-white rounded-lg transition-colors <?= $page >= $total_pages ? 'pointer-events-none opacity-50' : '' ?>"
                        title="Last Page">
                        <span class="material-symbols-outlined text-[18px]">last_page</span>
                    </a>
                </div>
            </div>
        <?php endif; ?>

<?php if ($is_ajax): ?>
<?php 
    $content = ob_get_clean(); 
    echo $content;
    exit; // Stop further output
?>
<?php else: ?>
    </div> <!-- End #reconciliation-container -->
</div>
<script>
    // Live Search Logic
    const searchInput = document.getElementById('liveSearchInput');
    const container = document.getElementById('reconciliation-container');
    let debounceTimer;

    if (searchInput) {
        searchInput.addEventListener('input', function(e) {
            const searchTerm = e.target.value;
            
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                fetchResults(searchTerm);
            }, 300); // 300ms debounce
        });
    }

    function fetchResults(term) {
        // Add opacity to indicate loading
        container.classList.add('opacity-50');
        
        // Construct URL
        const currentUrl = new URL(window.location.href);
        currentUrl.searchParams.set('search', term);
        currentUrl.searchParams.set('page', '1'); // Reset to page 1 on search
        currentUrl.searchParams.set('ajax', '1'); // Request partial content

        fetch(currentUrl)
            .then(response => response.text())
            .then(html => {
                container.innerHTML = html;
                container.classList.remove('opacity-50');
                
                // Update Browser URL (optional, for bookmarking/history) without reloading
                const displayUrl = new URL(window.location.href);
                if (term) {
                    displayUrl.searchParams.set('search', term);
                } else {
                    displayUrl.searchParams.delete('search');
                }
                displayUrl.searchParams.set('page', '1');
                window.history.pushState({}, '', displayUrl);
                
                // Re-bind pagination links to use AJAX if we want fully SPA-like behavior, 
                // OR simpler: just let them be normal links (pagination will trigger full reload or we can intercept them too)
                // For now, let's intercept pagination clicks inside the container to keep it "live"
                attachPaginationListeners();
            })
            .catch(err => {
                console.error('Search failed', err);
                container.classList.remove('opacity-50');
            });
    }

    function attachPaginationListeners() {
        // This function would find all <a> tags in the pagination and make them fetch via AJAX
        // For simplicity in this iteration, we permit full reload on pagination click 
        // to ensure state is clean, OR we can implement it. 
        // Given "Live Search" requirement usually focuses on the typing part.
        // Let's stick to simple typing updates first.
        
        // Note: The backend updates the hrefs in the pagination HTML, so clicking them works (full reload).
        // That is acceptable MVP.
    }
</script>

<?php require_once '../../includes/footer.php'; ?>
<?php endif; ?>