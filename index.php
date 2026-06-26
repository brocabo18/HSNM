<?php
require_once 'config.php';
requireLogin();

// Fetch Token Stats
$stats = [
    'routers' => ['total' => 0, 'online' => 0, 'offline' => 0],
    'switches' => ['total' => 0, 'active' => 0, 'inactive' => 0],
    'ips' => ['total' => 0, 'active' => 0, 'available' => 0],
    'computers' => ['total' => 0],
    'ics' => ['total' => 0],
    'office' => ['total' => 0],
    'reconciliation' => ['total' => 0],
    'pabx' => ['total' => 0],
    'printers' => ['total' => 0],
    'queueing_tv' => ['total' => 0]
];

try {
    // Routers
    $stats['routers']['total'] = $pdo->query("SELECT COUNT(*) FROM routers")->fetchColumn();
    $stats['routers']['online'] = $pdo->query("SELECT COUNT(*) FROM routers WHERE status = 'Online'")->fetchColumn();
    $stats['routers']['offline'] = $pdo->query("SELECT COUNT(*) FROM routers WHERE status = 'Offline'")->fetchColumn();

    // Switches
    $stats['switches']['total'] = $pdo->query("SELECT COUNT(*) FROM switches")->fetchColumn();
    $stats['switches']['active'] = $pdo->query("SELECT COUNT(*) FROM switches WHERE status = 'Active'")->fetchColumn();
    $stats['switches']['inactive'] = $pdo->query("SELECT COUNT(*) FROM switches WHERE status = 'Inactive'")->fetchColumn();

    // IPs
    $stats['ips']['total'] = $pdo->query("SELECT COUNT(*) FROM ips")->fetchColumn();
    $stats['ips']['active'] = $pdo->query("SELECT COUNT(*) FROM ips WHERE status = 'active'")->fetchColumn();
    // Simplified available calculation
    $stats['ips']['available'] = $stats['ips']['total'] - $stats['ips']['active'];

    // Computers
    $stats['computers']['total'] = $pdo->query("SELECT COUNT(*) FROM computers")->fetchColumn();

    // ICS
    $stats['ics']['total'] = $pdo->query("SELECT COUNT(*) FROM ics_inventory")->fetchColumn();

    // Office
    $stats['office']['total'] = $pdo->query("SELECT COUNT(*) FROM office_licenses")->fetchColumn();

    // Reconciliation (Mismatches + Conflicts)
    $mismatch_count = $pdo->query("SELECT COUNT(*) FROM computers c INNER JOIN ips i ON c.control_number = i.control_number WHERE (COALESCE(i.ip_address, '') != COALESCE(c.ip_address, '')) OR (COALESCE(i.mac_address, '') != COALESCE(c.mac_address, ''))")->fetchColumn();

    $conflict_count = $pdo->query("SELECT COUNT(*) FROM computers c INNER JOIN ips i ON (i.ip_address = c.ip_address OR i.mac_address = c.mac_address) WHERE (i.control_number != c.control_number) AND ((i.ip_address = c.ip_address AND i.ip_address != '' AND i.ip_address IS NOT NULL AND c.ip_address NOT LIKE '%DHCP%') OR (i.mac_address = c.mac_address AND i.mac_address != '' AND i.mac_address IS NOT NULL))")->fetchColumn();

    $stats['reconciliation']['total'] = $mismatch_count + $conflict_count;

    // PABX Directory
    $stats['pabx']['total'] = $pdo->query("SELECT COUNT(*) FROM pabx_directory")->fetchColumn();

    // Printers
    $stats['printers']['total'] = $pdo->query("SELECT COUNT(*) FROM printers")->fetchColumn();

    // Queueing TV
    $stats['queueing_tv']['total'] = $pdo->query("SELECT COUNT(*) FROM queueing_tvs")->fetchColumn();

} catch (PDOException $e) {
    // Handle error silently or log it
}

// Fetch Recent Logs
$logs = [];
try {
    $stmt = $pdo->query("SELECT al.*, u.username FROM audit_logs al LEFT JOIN users u ON al.user_id = u.id ORDER BY al.created_at DESC LIMIT 5");
    $logs = $stmt->fetchAll();
} catch (PDOException $e) {
    //
}

$page_title = "Dashboard";
require_once 'includes/header.php';
require_once 'includes/sidebar.php';
?>

<div class="p-4 md:p-8 flex-1 overflow-y-auto custom-scrollbar relative z-10 bg-slate-50 dark:bg-background-dark">
    <header class="mb-8">
        <h2 class="text-2xl font-bold text-slate-900 dark:text-white">Dashboard</h2>
        <p class="text-sm text-slate-500 dark:text-slate-500 mt-1">Overview of your network hardware.</p>
    </header>

    <!-- Stats Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">

        <!-- Routers Card -->
        <div
            class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] p-6 rounded-2xl relative overflow-hidden group">
            <div class="absolute top-0 right-0 p-6 opacity-10 group-hover:opacity-20 transition-opacity">
                <span class="material-symbols-outlined text-6xl text-blue-500">router</span>
            </div>
            <div class="relative z-10">
                <div class="flex items-center gap-3 mb-4">
                    <div class="p-2 bg-blue-500/10 rounded-lg text-blue-500"><span
                            class="material-symbols-outlined">router</span></div>
                    <h3 class="font-bold text-slate-900 dark:text-white">Routers</h3>
                </div>
                <div class="text-3xl font-bold text-slate-900 dark:text-white mb-2">
                    <?= number_format($stats['routers']['total']) ?>
                </div>
                <div class="flex gap-4 text-xs font-medium">
                    <span class="text-emerald-500">
                        <?= number_format($stats['routers']['online']) ?> Online
                    </span>
                    <span class="text-red-500">
                        <?= number_format($stats['routers']['offline']) ?> Offline
                    </span>
                </div>
                <a href="<?= BASE_URL ?>/modules/routers/" class="absolute inset-0 z-20"></a>
            </div>
        </div>

        <!-- Switches Card -->
        <div
            class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] p-6 rounded-2xl relative overflow-hidden group">
            <div class="absolute top-0 right-0 p-6 opacity-10 group-hover:opacity-20 transition-opacity">
                <span class="material-symbols-outlined text-6xl text-purple-500">switch</span>
            </div>
            <div class="relative z-10">
                <div class="flex items-center gap-3 mb-4">
                    <div class="p-2 bg-purple-500/10 rounded-lg text-purple-500"><span
                            class="material-symbols-outlined">switch</span></div>
                    <h3 class="font-bold text-slate-900 dark:text-white">Switches</h3>
                </div>
                <div class="text-3xl font-bold text-slate-900 dark:text-white mb-2">
                    <?= number_format($stats['switches']['total']) ?>
                </div>
                <div class="flex gap-4 text-xs font-medium">
                    <span class="text-emerald-500">
                        <?= number_format($stats['switches']['active']) ?> Active
                    </span>
                    <span class="text-red-500">
                        <?= number_format($stats['switches']['inactive']) ?> Inactive
                    </span>
                </div>
                <a href="<?= BASE_URL ?>/modules/switches/" class="absolute inset-0 z-20"></a>
            </div>
        </div>

        <!-- IPs Card -->
        <div
            class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] p-6 rounded-2xl relative overflow-hidden group">
            <div class="absolute top-0 right-0 p-6 opacity-10 group-hover:opacity-20 transition-opacity">
                <span class="material-symbols-outlined text-6xl text-emerald-500">dns</span>
            </div>
            <div class="relative z-10">
                <div class="flex items-center gap-3 mb-4">
                    <div class="p-2 bg-emerald-500/10 rounded-lg text-emerald-500"><span
                            class="material-symbols-outlined">dns</span></div>
                    <h3 class="font-bold text-slate-900 dark:text-white">IP Addresses</h3>
                </div>
                <div class="text-3xl font-bold text-slate-900 dark:text-white mb-2">
                    <?= number_format($stats['ips']['total']) ?>
                </div>
                <div class="flex gap-4 text-xs font-medium">
                    <span class="text-emerald-500">
                        <?= number_format($stats['ips']['active']) ?> Active
                    </span>
                    <span class="text-slate-500 dark:text-slate-500">
                        <?= number_format($stats['ips']['available']) ?> Available
                    </span>
                </div>
                <a href="<?= BASE_URL ?>/modules/ips/" class="absolute inset-0 z-20"></a>
            </div>
        </div>

        <!-- Computers Card -->
        <div
            class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] p-6 rounded-2xl relative overflow-hidden group">
            <div class="absolute top-0 right-0 p-6 opacity-10 group-hover:opacity-20 transition-opacity">
                <span class="material-symbols-outlined text-6xl text-amber-500">computer</span>
            </div>
            <div class="relative z-10">
                <div class="flex items-center gap-3 mb-4">
                    <div class="p-2 bg-amber-500/10 rounded-lg text-amber-500"><span
                            class="material-symbols-outlined">computer</span></div>
                    <h3 class="font-bold text-slate-900 dark:text-white">Computers</h3>
                </div>
                <div class="text-3xl font-bold text-slate-900 dark:text-white mb-2">
                    <?= number_format($stats['computers']['total']) ?>
                </div>
                <div class="flex gap-4 text-xs font-medium">
                    <span class="text-slate-400">
                        Complete systems tracked
                    </span>
                </div>
                <a href="<?= BASE_URL ?>/modules/computers/" class="absolute inset-0 z-20"></a>
            </div>
        </div>

        <!-- ICS Card -->
        <div
            class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] p-6 rounded-2xl relative overflow-hidden group">
            <div class="absolute top-0 right-0 p-6 opacity-10 group-hover:opacity-20 transition-opacity">
                <span class="material-symbols-outlined text-6xl text-slate-500">inventory_2</span>
            </div>
            <div class="relative z-10">
                <div class="flex items-center gap-3 mb-4">
                    <div class="p-2 bg-slate-500/10 rounded-lg text-slate-500"><span
                            class="material-symbols-outlined">inventory_2</span></div>
                    <h3 class="font-bold text-slate-900 dark:text-white">ICS Inventory</h3>
                </div>
                <div class="text-3xl font-bold text-slate-900 dark:text-white mb-2">
                    <?= number_format($stats['ics']['total']) ?>
                </div>
                <div class="flex gap-4 text-xs font-medium">
                    <span class="text-slate-400">
                        Total items
                    </span>
                </div>
                <a href="<?= BASE_URL ?>/modules/ics/" class="absolute inset-0 z-20"></a>
            </div>
        </div>

        <!-- Office Licenses Card -->
        <div
            class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] p-6 rounded-2xl relative overflow-hidden group">
            <div class="absolute top-0 right-0 p-6 opacity-10 group-hover:opacity-20 transition-opacity">
                <span class="material-symbols-outlined text-6xl text-indigo-500">grid_view</span>
            </div>
            <div class="relative z-10">
                <div class="flex items-center gap-3 mb-4">
                    <div class="p-2 bg-indigo-500/10 rounded-lg text-indigo-500"><span
                            class="material-symbols-outlined">grid_view</span></div>
                    <h3 class="font-bold text-slate-900 dark:text-white">Office Licenses</h3>
                </div>
                <div class="text-3xl font-bold text-slate-900 dark:text-white mb-2">
                    <?= number_format($stats['office']['total']) ?>
                </div>
                <div class="flex gap-4 text-xs font-medium">
                    <span class="text-slate-400">
                        Total licenses
                    </span>
                </div>
                <a href="<?= BASE_URL ?>/modules/office/" class="absolute inset-0 z-20"></a>
            </div>
        </div>

        <!-- Reconciliation Card -->
        <div
            class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] p-6 rounded-2xl relative overflow-hidden group">
            <div class="absolute top-0 right-0 p-6 opacity-10 group-hover:opacity-20 transition-opacity">
                <span class="material-symbols-outlined text-6xl text-rose-500">sync_problem</span>
            </div>
            <div class="relative z-10">
                <div class="flex items-center gap-3 mb-4">
                    <div class="p-2 bg-rose-500/10 rounded-lg text-rose-500"><span
                            class="material-symbols-outlined">sync_problem</span></div>
                    <h3 class="font-bold text-slate-900 dark:text-white">Reconciliation</h3>
                </div>
                <div class="text-3xl font-bold text-slate-900 dark:text-white mb-2">
                    <?= number_format($stats['reconciliation']['total']) ?>
                </div>
                <div class="flex gap-4 text-xs font-medium">
                    <span class="<?= $stats['reconciliation']['total'] > 0 ? 'text-rose-500' : 'text-emerald-500' ?>">
                        <?= $stats['reconciliation']['total'] > 0 ? 'Discrepancies found' : 'All synced' ?>
                    </span>
                </div>
                <a href="<?= BASE_URL ?>/modules/reconciliation/" class="absolute inset-0 z-20"></a>
            </div>
        </div>

        <!-- PABX Directory Card -->
        <div
            class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] p-6 rounded-2xl relative overflow-hidden group">
            <div class="absolute top-0 right-0 p-6 opacity-10 group-hover:opacity-20 transition-opacity">
                <span class="material-symbols-outlined text-6xl text-teal-500">contact_phone</span>
            </div>
            <div class="relative z-10">
                <div class="flex items-center gap-3 mb-4">
                    <div class="p-2 bg-teal-500/10 rounded-lg text-teal-500"><span
                            class="material-symbols-outlined">contact_phone</span></div>
                    <h3 class="font-bold text-slate-900 dark:text-white">PABX Directory</h3>
                </div>
                <div class="text-3xl font-bold text-slate-900 dark:text-white mb-2">
                    <?= number_format($stats['pabx']['total']) ?>
                </div>
                <div class="flex gap-4 text-xs font-medium">
                    <span class="text-slate-400">
                        Phone directory entries
                    </span>
                </div>
                <a href="<?= BASE_URL ?>/modules/pabx/" class="absolute inset-0 z-20"></a>
            </div>
        </div>

        <!-- Printers Card -->
        <div class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] p-6 rounded-2xl relative overflow-hidden group">
            <div class="absolute top-0 right-0 p-6 opacity-10 group-hover:opacity-20 transition-opacity">
                <span class="material-symbols-outlined text-6xl text-orange-500">print</span>
            </div>
            <div class="relative z-10">
                <div class="flex items-center gap-3 mb-4">
                    <div class="p-2 bg-orange-500/10 rounded-lg text-orange-500"><span class="material-symbols-outlined">print</span></div>
                    <h3 class="font-bold text-slate-900 dark:text-white">Printers</h3>
                </div>
                <div class="text-3xl font-bold text-slate-900 dark:text-white mb-2">
                    <?= number_format($stats['printers']['total']) ?>
                </div>
                <div class="flex gap-4 text-xs font-medium">
                    <span class="text-slate-400">Canon LBP 2900 units</span>
                </div>
                <a href="<?= BASE_URL ?>/modules/printers/" class="absolute inset-0 z-20"></a>
            </div>
        </div>

        <!-- Queueing TVs Card -->
        <div class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] p-6 rounded-2xl relative overflow-hidden group">
            <div class="absolute top-0 right-0 p-6 opacity-10 group-hover:opacity-20 transition-opacity">
                <span class="material-symbols-outlined text-6xl text-cyan-500">tv</span>
            </div>
            <div class="relative z-10">
                <div class="flex items-center gap-3 mb-4">
                    <div class="p-2 bg-cyan-500/10 rounded-lg text-cyan-500"><span class="material-symbols-outlined">tv</span></div>
                    <h3 class="font-bold text-slate-900 dark:text-white">Queueing TVs</h3>
                </div>
                <div class="text-3xl font-bold text-slate-900 dark:text-white mb-2">
                    <?= number_format($stats['queueing_tv']['total']) ?>
                </div>
                <div class="flex gap-4 text-xs font-medium">
                    <span class="text-slate-400">Queueing Display Arrays</span>
                </div>
                <a href="<?= BASE_URL ?>/modules/queueing_tv/" class="absolute inset-0 z-20"></a>
            </div>
        </div>
    </div>

    <!-- Quick Actions & Logs -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Quick Actions -->
        <div class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] rounded-2xl p-6">
            <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-4">Quick Actions</h3>
            <div class="space-y-3">
                <button onclick="location.href='modules/routers/'"
                    class="w-full flex items-center justify-between p-3 bg-white hover:bg-slate-50 dark:bg-[#101622] dark:hover:bg-[#151b29] border border-slate-200 dark:border-[#232b3d] rounded-xl transition-colors group">
                    <div class="flex items-center gap-3">
                        <span class="material-symbols-outlined text-blue-500">add_circle</span>
                        <span
                            class="text-sm font-medium text-slate-700 dark:text-slate-700 dark:text-slate-300 group-hover:text-slate-900 dark:group-hover:text-slate-900 dark:group-hover:text-white">Add
                            Router</span>
                    </div>
                    <span
                        class="material-symbols-outlined text-slate-400 dark:text-slate-600 text-[18px]">arrow_forward</span>
                </button>
                <button onclick="location.href='modules/switches/'"
                    class="w-full flex items-center justify-between p-3 bg-white hover:bg-slate-50 dark:bg-[#101622] dark:hover:bg-[#151b29] border border-slate-200 dark:border-[#232b3d] rounded-xl transition-colors group">
                    <div class="flex items-center gap-3">
                        <span class="material-symbols-outlined text-purple-500">add_circle</span>
                        <span
                            class="text-sm font-medium text-slate-700 dark:text-slate-700 dark:text-slate-300 group-hover:text-slate-900 dark:group-hover:text-slate-900 dark:group-hover:text-white">Add
                            Switch</span>
                    </div>
                    <span
                        class="material-symbols-outlined text-slate-400 dark:text-slate-600 text-[18px]">arrow_forward</span>
                </button>
                <button onclick="location.href='modules/ips/'"
                    class="w-full flex items-center justify-between p-3 bg-white hover:bg-slate-50 dark:bg-[#101622] dark:hover:bg-[#151b29] border border-slate-200 dark:border-[#232b3d] rounded-xl transition-colors group">
                    <div class="flex items-center gap-3">
                        <span class="material-symbols-outlined text-emerald-500">add_circle</span>
                        <span
                            class="text-sm font-medium text-slate-700 dark:text-slate-700 dark:text-slate-300 group-hover:text-slate-900 dark:group-hover:text-slate-900 dark:group-hover:text-white">Add
                            IP Address</span>
                    </div>
                    <span
                        class="material-symbols-outlined text-slate-400 dark:text-slate-600 text-[18px]">arrow_forward</span>
                </button>
                <button onclick="location.href='modules/computers/'"
                    class="w-full flex items-center justify-between p-3 bg-white hover:bg-slate-50 dark:bg-[#101622] dark:hover:bg-[#151b29] border border-slate-200 dark:border-[#232b3d] rounded-xl transition-colors group">
                    <div class="flex items-center gap-3">
                        <span class="material-symbols-outlined text-amber-500">add_circle</span>
                        <span
                            class="text-sm font-medium text-slate-700 dark:text-slate-700 dark:text-slate-300 group-hover:text-slate-900 dark:group-hover:text-slate-900 dark:group-hover:text-white">Add
                            Computer</span>
                    </div>
                    <span
                        class="material-symbols-outlined text-slate-400 dark:text-slate-600 text-[18px]">arrow_forward</span>
                </button>
            </div>
            <button onclick="location.href='modules/office/'"
                class="w-full flex items-center justify-between p-3 bg-white hover:bg-slate-50 dark:bg-[#101622] dark:hover:bg-[#151b29] border border-slate-200 dark:border-[#232b3d] rounded-xl transition-colors group">
                <div class="flex items-center gap-3">
                    <span class="material-symbols-outlined text-indigo-500">grid_view</span>
                    <span
                        class="text-sm font-medium text-slate-700 dark:text-slate-700 dark:text-slate-300 group-hover:text-slate-900 dark:group-hover:text-slate-900 dark:group-hover:text-white">Add
                        Office License</span>
                </div>
                <span
                    class="material-symbols-outlined text-slate-400 dark:text-slate-600 text-[18px]">arrow_forward</span>
            </button>
            <button onclick="location.href='modules/ics/'"
                class="w-full flex items-center justify-between p-3 bg-white hover:bg-slate-50 dark:bg-[#101622] dark:hover:bg-[#151b29] border border-slate-200 dark:border-[#232b3d] rounded-xl transition-colors group">
                <div class="flex items-center gap-3">
                    <span class="material-symbols-outlined text-slate-500">inventory_2</span>
                    <span
                        class="text-sm font-medium text-slate-700 dark:text-slate-700 dark:text-slate-300 group-hover:text-slate-900 dark:group-hover:text-slate-900 dark:group-hover:text-white">Add
                        ICS Item</span>
                </div>
                <span
                    class="material-symbols-outlined text-slate-400 dark:text-slate-600 text-[18px]">arrow_forward</span>
            </button>
            <button onclick="location.href='modules/printers/'"
                class="w-full flex items-center justify-between p-3 bg-white hover:bg-slate-50 dark:bg-[#101622] dark:hover:bg-[#151b29] border border-slate-200 dark:border-[#232b3d] rounded-xl transition-colors group">
                <div class="flex items-center gap-3">
                    <span class="material-symbols-outlined text-orange-500">print</span>
                    <span
                        class="text-sm font-medium text-slate-700 dark:text-slate-700 dark:text-slate-300 group-hover:text-slate-900 dark:group-hover:text-slate-900 dark:group-hover:text-white">Add
                        Printer</span>
                </div>
                <span
                    class="material-symbols-outlined text-slate-400 dark:text-slate-600 text-[18px]">arrow_forward</span>
            </button>
            <button onclick="location.href='modules/queueing_tv/'"
                class="w-full flex items-center justify-between p-3 bg-white hover:bg-slate-50 dark:bg-[#101622] dark:hover:bg-[#151b29] border border-slate-200 dark:border-[#232b3d] rounded-xl transition-colors group">
                <div class="flex items-center gap-3">
                    <span class="material-symbols-outlined text-cyan-500">tv</span>
                    <span
                        class="text-sm font-medium text-slate-700 dark:text-slate-700 dark:text-slate-300 group-hover:text-slate-900 dark:group-hover:text-slate-900 dark:group-hover:text-white">Add
                        Queueing TV</span>
                </div>
                <span
                    class="material-symbols-outlined text-slate-400 dark:text-slate-600 text-[18px]">arrow_forward</span>
            </button>
        </div>

        <!-- Recent Activity -->
        <div class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] rounded-2xl p-6">
            <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-4">Recent Activity</h3>
            <?php if (empty($logs)): ?>
                <div class="text-center text-slate-500 dark:text-slate-500 py-8 text-sm">No recent activity</div>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($logs as $log): ?>
                        <div
                            class="flex items-start gap-4 pb-4 border-b border-slate-200 dark:border-[#232b3d] last:border-0 last:pb-0">
                            <div
                                class="size-8 rounded-full bg-slate-50 dark:bg-[#101622] flex items-center justify-center border border-slate-200 dark:border-[#232b3d] flex-shrink-0">
                                <span
                                    class="material-symbols-outlined text-xs text-slate-400 dark:text-slate-400">history</span>
                            </div>
                            <div>
                                <p class="text-sm text-slate-900 dark:text-white font-medium">
                                    <?= htmlspecialchars($log['action_type'] ?? 'Action') ?>
                                </p>
                                <p class="text-xs text-slate-500 dark:text-slate-500 mt-0.5">
                                    <span class="text-slate-600 dark:text-slate-400">
                                        <?= htmlspecialchars($log['username'] ?? 'System') ?>
                                    </span>
                                    -
                                    <?= htmlspecialchars($log['details'] ?? '') ?>
                                </p>
                            </div>
                            <div class="ml-auto text-[10px] text-slate-500 dark:text-slate-600 font-mono whitespace-nowrap">
                                <?= date('M d H:i', strtotime($log['created_at'])) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>