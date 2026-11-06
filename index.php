<?php
require_once 'config.php';
requireLogin();

// Fetch Token Stats
$stats = [
    'routers' => ['total' => 0, 'online' => 0, 'offline' => 0],
    'switches' => ['total' => 0, 'active' => 0, 'inactive' => 0],
    'ips' => ['total' => 0, 'active' => 0, 'available' => 0],
    'computers' => ['total' => 0, 'working' => 0, 'transferred' => 0, 'condemned' => 0],
    'jasmc_st' => ['total' => 0],
    'jasmc_vt' => ['total' => 0],
    'doh_jbl_cmc' => ['total' => 0],
    'assets' => ['total' => 0, 'active' => 0, 'maintenance' => 0, 'retired' => 0, 'total_cost' => 0],
    'maintenance' => ['total' => 0, 'completed' => 0, 'alerts' => 0, 'avg_score' => 0],
    'ics' => ['total' => 0],
    'office' => ['total' => 0],
    'reconciliation' => ['total' => 0],
    'pabx' => ['total' => 0],
    'printers' => ['total' => 0],
    'queueing_tv' => ['total' => 0]
];

$dbConnectionError = defined('DB_ERROR') ? DB_ERROR : null;

try {
    if (!$pdo) {
        throw new Exception($dbConnectionError ?: "Database connection not established");
    }

    // Check which tables actually exist in the database to prevent fatal missing relation errors
    $existingTablesStmt = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema='public'");
    $existingTables = $existingTablesStmt->fetchAll(PDO::FETCH_COLUMN);
    $hasTable = fn($t) => in_array($t, $existingTables);

    // Build subqueries dynamically based on existing tables
    $subqueries = [];

    // Routers
    if ($hasTable('routers')) {
        $subqueries[] = "(SELECT COUNT(*) FROM routers) as routers_total";
        $subqueries[] = "(SELECT COUNT(*) FROM routers WHERE status = 'Online') as routers_online";
        $subqueries[] = "(SELECT COUNT(*) FROM routers WHERE status = 'Offline') as routers_offline";
    } else {
        $subqueries[] = "0 as routers_total, 0 as routers_online, 0 as routers_offline";
    }

    // Switches
    if ($hasTable('switches')) {
        $subqueries[] = "(SELECT COUNT(*) FROM switches) as switches_total";
        $subqueries[] = "(SELECT COUNT(*) FROM switches WHERE status = 'Active') as switches_active";
        $subqueries[] = "(SELECT COUNT(*) FROM switches WHERE status = 'Inactive') as switches_inactive";
    } else {
        $subqueries[] = "0 as switches_total, 0 as switches_active, 0 as switches_inactive";
    }

    // IPs
    if ($hasTable('ips')) {
        $subqueries[] = "(SELECT COUNT(*) FROM ips) as ips_total";
        $subqueries[] = "(SELECT COUNT(*) FROM ips WHERE status = 'active') as ips_active";
    } else {
        $subqueries[] = "0 as ips_total, 0 as ips_active";
    }

    // Computers
    if ($hasTable('computers')) {
        $subqueries[] = "(SELECT COUNT(*) FROM computers) as computers_total";
        $subqueries[] = "(SELECT COUNT(*) FROM computers WHERE status = 'Working') as computers_working";
        $subqueries[] = "(SELECT COUNT(*) FROM computers WHERE status = 'Transferred') as computers_transferred";
        $subqueries[] = "(SELECT COUNT(*) FROM computers WHERE status = 'Condemned') as computers_condemned";
    } else {
        $subqueries[] = "0 as computers_total, 0 as computers_working, 0 as computers_transferred, 0 as computers_condemned";
    }

    // Satellite Branches
    $subqueries[] = $hasTable('computers_jasmc_st') ? "(SELECT COUNT(*) FROM computers_jasmc_st) as jasmc_st_total" : "0 as jasmc_st_total";
    $subqueries[] = $hasTable('computers_jasmc_vt') ? "(SELECT COUNT(*) FROM computers_jasmc_vt) as jasmc_vt_total" : "0 as jasmc_vt_total";
    $subqueries[] = $hasTable('computers_doh_jbl_cmc') ? "(SELECT COUNT(*) FROM computers_doh_jbl_cmc) as doh_jbl_cmc_total" : "0 as doh_jbl_cmc_total";

    // Assets
    if ($hasTable('assets')) {
        $subqueries[] = "(SELECT COUNT(*) FROM assets) as assets_total";
        $subqueries[] = "(SELECT COUNT(*) FROM assets WHERE status = 'Active') as assets_active";
        $subqueries[] = "(SELECT COUNT(*) FROM assets WHERE status = 'Maintenance') as assets_maintenance";
        $subqueries[] = "(SELECT COUNT(*) FROM assets WHERE status = 'Retired') as assets_retired";
        $subqueries[] = "(SELECT COALESCE(SUM(cost), 0) FROM assets) as assets_total_cost";
    } else {
        $subqueries[] = "0 as assets_total, 0 as assets_active, 0 as assets_maintenance, 0 as assets_retired, 0 as assets_total_cost";
    }

    // Maintenance
    if ($hasTable('maintenance')) {
        $subqueries[] = "(SELECT COUNT(*) FROM maintenance) as maintenance_total";
        $subqueries[] = "(SELECT COUNT(*) FROM maintenance WHERE status = 'Completed') as maintenance_completed";
        $subqueries[] = "(SELECT COUNT(*) FROM maintenance WHERE status = 'Needs Action' OR status = 'Critical Failure') as maintenance_alerts";
        $subqueries[] = "(SELECT COALESCE(ROUND(AVG(performance_score)), 0) FROM maintenance) as maintenance_avg_score";
    } else {
        $subqueries[] = "0 as maintenance_total, 0 as maintenance_completed, 0 as maintenance_alerts, 0 as maintenance_avg_score";
    }

    // ICS, Office, PABX, Printers, TVs
    $subqueries[] = $hasTable('ics_inventory') ? "(SELECT COUNT(*) FROM ics_inventory) as ics_total" : "0 as ics_total";
    $subqueries[] = $hasTable('office_licenses') ? "(SELECT COUNT(*) FROM office_licenses) as office_total" : "0 as office_total";
    $subqueries[] = $hasTable('pabx_directory') ? "(SELECT COUNT(*) FROM pabx_directory) as pabx_total" : "0 as pabx_total";
    $subqueries[] = $hasTable('printers') ? "(SELECT COUNT(*) FROM printers) as printers_total" : "0 as printers_total";
    $subqueries[] = $hasTable('queueing_tvs') ? "(SELECT COUNT(*) FROM queueing_tvs) as queueing_tv_total" : "0 as queueing_tv_total";

    $batch_query = "SELECT " . implode(",\n        ", $subqueries);
    $counts = $pdo->query($batch_query)->fetch(PDO::FETCH_ASSOC);
    
    // Routers
    $stats['routers']['total'] = (int)($counts['routers_total'] ?? 0);
    $stats['routers']['online'] = (int)($counts['routers_online'] ?? 0);
    $stats['routers']['offline'] = (int)($counts['routers_offline'] ?? 0);

    // Switches
    $stats['switches']['total'] = (int)($counts['switches_total'] ?? 0);
    $stats['switches']['active'] = (int)($counts['switches_active'] ?? 0);
    $stats['switches']['inactive'] = (int)($counts['switches_inactive'] ?? 0);

    // IPs
    $stats['ips']['total'] = (int)($counts['ips_total'] ?? 0);
    $stats['ips']['active'] = (int)($counts['ips_active'] ?? 0);
    $stats['ips']['available'] = max(0, $stats['ips']['total'] - $stats['ips']['active']);

    // Computers & Status Breakdown
    $stats['computers']['total'] = (int)($counts['computers_total'] ?? 0);
    $stats['computers']['working'] = (int)($counts['computers_working'] ?? 0);
    $stats['computers']['transferred'] = (int)($counts['computers_transferred'] ?? 0);
    $stats['computers']['condemned'] = (int)($counts['computers_condemned'] ?? 0);

    // Satellite Branches
    $stats['jasmc_st']['total'] = (int)($counts['jasmc_st_total'] ?? 0);
    $stats['jasmc_vt']['total'] = (int)($counts['jasmc_vt_total'] ?? 0);
    $stats['doh_jbl_cmc']['total'] = (int)($counts['doh_jbl_cmc_total'] ?? 0);

    // Asset Inventory
    $stats['assets']['total'] = (int)($counts['assets_total'] ?? 0);
    $stats['assets']['active'] = (int)($counts['assets_active'] ?? 0);
    $stats['assets']['maintenance'] = (int)($counts['assets_maintenance'] ?? 0);
    $stats['assets']['retired'] = (int)($counts['assets_retired'] ?? 0);
    $stats['assets']['total_cost'] = (float)($counts['assets_total_cost'] ?? 0);

    // Preventive Maintenance
    $stats['maintenance']['total'] = (int)($counts['maintenance_total'] ?? 0);
    $stats['maintenance']['completed'] = (int)($counts['maintenance_completed'] ?? 0);
    $stats['maintenance']['alerts'] = (int)($counts['maintenance_alerts'] ?? 0);
    $stats['maintenance']['avg_score'] = (int)($counts['maintenance_avg_score'] ?? 0);

    // ICS, Office, PABX, Printers, TVs
    $stats['ics']['total'] = (int)($counts['ics_total'] ?? 0);
    $stats['office']['total'] = (int)($counts['office_total'] ?? 0);
    $stats['pabx']['total'] = (int)($counts['pabx_total'] ?? 0);
    $stats['printers']['total'] = (int)($counts['printers_total'] ?? 0);
    $stats['queueing_tv']['total'] = (int)($counts['queueing_tv_total'] ?? 0);

    // Reconciliation (only if both computers and ips tables exist)
    if ($hasTable('computers') && $hasTable('ips')) {
        $mismatch_count = (int)$pdo->query("SELECT COUNT(*) FROM computers c INNER JOIN ips i ON c.control_number = i.control_number WHERE (COALESCE(i.ip_address, '') != COALESCE(c.ip_address, '')) OR (COALESCE(i.mac_address, '') != COALESCE(c.mac_address, ''))")->fetchColumn();
        $conflict_count = (int)$pdo->query("SELECT COUNT(*) FROM computers c INNER JOIN ips i ON (i.ip_address = c.ip_address OR i.mac_address = c.mac_address) WHERE (i.control_number != c.control_number) AND ((i.ip_address = c.ip_address AND i.ip_address != '' AND i.ip_address IS NOT NULL AND c.ip_address NOT LIKE '%DHCP%') OR (i.mac_address = c.mac_address AND i.mac_address != '' AND i.mac_address IS NOT NULL))")->fetchColumn();
        $stats['reconciliation']['total'] = $mismatch_count + $conflict_count;
    }

} catch (Exception $e) {
    error_log("Dashboard query error: " . $e->getMessage());
}

// Compute aggregate overview numbers
$total_hardware_endpoints = $stats['computers']['total'] + $stats['jasmc_st']['total'] + $stats['jasmc_vt']['total'] + $stats['doh_jbl_cmc']['total'] + $stats['routers']['total'] + $stats['switches']['total'] + $stats['printers']['total'] + $stats['queueing_tv']['total'];
$total_satellite_units = $stats['jasmc_st']['total'] + $stats['jasmc_vt']['total'] + $stats['doh_jbl_cmc']['total'];
$ip_utilization = $stats['ips']['total'] > 0 ? round(($stats['ips']['active'] / $stats['ips']['total']) * 100, 1) : 0;

// Fetch Recent Logs
$logs = [];
try {
    if ($pdo && !empty($hasTable) && $hasTable('audit_logs')) {
        $stmt = $pdo->query("SELECT al.*, u.username FROM audit_logs al LEFT JOIN users u ON al.user_id = u.id ORDER BY al.created_at DESC LIMIT 6");
        $logs = $stmt->fetchAll();
    }
} catch (Exception $e) {
    //
}

$page_title = "Dashboard";
require_once 'includes/header.php';
require_once 'includes/sidebar.php';
?>

<div class="p-4 md:p-8 flex-1 overflow-y-auto custom-scrollbar relative z-10 bg-slate-50 dark:bg-background-dark">
    <!-- Dashboard Header -->
    <header class="mb-8 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <span class="text-xs font-bold uppercase tracking-wider text-primary bg-primary/10 px-2 py-0.5 rounded-full">System Operations</span>
                <span class="text-xs font-mono text-slate-500"><?= htmlspecialchars(getAppVersion($pdo)) ?></span>
            </div>
            <h2 class="text-2xl md:text-3xl font-bold text-slate-900 dark:text-white">Dashboard Overview</h2>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Unified infrastructure overview, asset lifecycle, and satellite hospital facilities.</p>
        </div>
        <div class="flex items-center gap-2">
            <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] text-xs font-medium text-slate-700 dark:text-slate-300">
                <span class="size-2 rounded-full bg-emerald-500 animate-pulse"></span>
                <span>System Operational</span>
            </div>
            <a href="<?= BASE_URL ?>/modules/reconciliation/" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-primary text-white text-xs font-bold hover:bg-primary/90 transition-colors shadow-sm">
                <span class="material-symbols-outlined text-[16px]">sync_alt</span>
                <span>Sync Check</span>
            </a>
        </div>
    </header>

    <?php if (!empty($dbConnectionError)): ?>
    <div class="mb-6 p-4 rounded-xl bg-amber-500/10 border border-amber-500/30 text-amber-700 dark:text-amber-300 flex items-center gap-3">
        <span class="material-symbols-outlined text-amber-500 text-2xl">warning</span>
        <div class="text-sm">
            <strong>Database Connection Alert:</strong> <?= htmlspecialchars($dbConnectionError) ?>. 
            Please check the <code>DATABASE_URL</code> environment variable in your cloud deployment settings (Vercel / Render).
        </div>
    </div>
    <?php endif; ?>

    <!-- Top Metric Highlights Ribbon -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
        <div class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] p-4 rounded-2xl flex items-center gap-3.5">
            <div class="size-11 rounded-xl bg-blue-500/10 text-blue-500 flex items-center justify-center flex-shrink-0">
                <span class="material-symbols-outlined text-2xl">devices</span>
            </div>
            <div class="min-w-0">
                <div class="text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Tracked Devices</div>
                <div class="text-xl md:text-2xl font-bold text-slate-900 dark:text-white"><?= number_format($total_hardware_endpoints) ?></div>
                <div class="text-[11px] text-slate-400 truncate">Hospital &amp; satellite units</div>
            </div>
        </div>

        <div class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] p-4 rounded-2xl flex items-center gap-3.5">
            <div class="size-11 rounded-xl bg-emerald-500/10 text-emerald-500 flex items-center justify-center flex-shrink-0">
                <span class="material-symbols-outlined text-2xl">lan</span>
            </div>
            <div class="min-w-0">
                <div class="text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">IP Allocation</div>
                <div class="text-xl md:text-2xl font-bold text-slate-900 dark:text-white"><?= $ip_utilization ?>%</div>
                <div class="text-[11px] text-emerald-600 dark:text-emerald-400 font-medium truncate"><?= number_format($stats['ips']['active']) ?> of <?= number_format($stats['ips']['total']) ?> Active</div>
            </div>
        </div>

        <div class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] p-4 rounded-2xl flex items-center gap-3.5">
            <div class="size-11 rounded-xl bg-purple-500/10 text-purple-500 flex items-center justify-center flex-shrink-0">
                <span class="material-symbols-outlined text-2xl">domain</span>
            </div>
            <div class="min-w-0">
                <div class="text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Satellite Units</div>
                <div class="text-xl md:text-2xl font-bold text-slate-900 dark:text-white"><?= number_format($total_satellite_units) ?></div>
                <div class="text-[11px] text-slate-400 truncate">Across 3 external stations</div>
            </div>
        </div>

        <div class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] p-4 rounded-2xl flex items-center gap-3.5">
            <div class="size-11 rounded-xl <?= $stats['reconciliation']['total'] > 0 ? 'bg-rose-500/10 text-rose-500' : 'bg-emerald-500/10 text-emerald-500' ?> flex items-center justify-center flex-shrink-0">
                <span class="material-symbols-outlined text-2xl"><?= $stats['reconciliation']['total'] > 0 ? 'warning' : 'verified' ?></span>
            </div>
            <div class="min-w-0">
                <div class="text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Sync Integrity</div>
                <div class="text-xl md:text-2xl font-bold <?= $stats['reconciliation']['total'] > 0 ? 'text-rose-500' : 'text-emerald-500' ?>">
                    <?= $stats['reconciliation']['total'] > 0 ? number_format($stats['reconciliation']['total']) . ' Issues' : '100% Synced' ?>
                </div>
                <div class="text-[11px] text-slate-400 truncate"><?= $stats['reconciliation']['total'] > 0 ? 'IP/MAC conflicts found' : 'Zero IP/MAC conflicts' ?></div>
            </div>
        </div>
    </div>

    <!-- Main Module Stats Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">

        <!-- Computers Card (Main Facility) -->
        <div class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] p-6 rounded-2xl relative overflow-hidden group hover:border-amber-500/40 transition-all shadow-sm">
            <div class="absolute top-0 right-0 p-6 opacity-10 group-hover:opacity-20 transition-opacity">
                <span class="material-symbols-outlined text-6xl text-amber-500">computer</span>
            </div>
            <div class="relative z-10">
                <div class="flex items-center gap-3 mb-4">
                    <div class="p-2 bg-amber-500/10 rounded-lg text-amber-500">
                        <span class="material-symbols-outlined">computer</span>
                    </div>
                    <div>
                        <h3 class="font-bold text-slate-900 dark:text-white leading-tight">Computers</h3>
                        <p class="text-[10px] text-slate-400 uppercase font-semibold">Main Facility</p>
                    </div>
                </div>
                <div class="text-3xl font-bold text-slate-900 dark:text-white mb-2">
                    <?= number_format($stats['computers']['total']) ?>
                </div>
                <div class="flex flex-wrap items-center gap-2 text-xs font-medium mb-3">
                    <span class="text-emerald-500 font-semibold">
                        <?= number_format($stats['computers']['working']) ?> Working
                    </span>
                    <span class="text-slate-300 dark:text-slate-600">&bull;</span>
                    <span class="text-blue-500 font-semibold">
                        <?= number_format($stats['computers']['transferred']) ?> Transferred
                    </span>
                    <span class="text-slate-300 dark:text-slate-600">&bull;</span>
                    <span class="text-red-500 font-semibold">
                        <?= number_format($stats['computers']['condemned']) ?> Condemned
                    </span>
                </div>
                <div class="flex items-center justify-between text-[11px] font-semibold text-slate-500 dark:text-slate-400 border-t border-slate-100 dark:border-[#232b3d] pt-2">
                    <span>Satellites</span>
                    <div class="flex items-center gap-2">
                        <span class="hover:text-primary transition-colors">ST: <?= number_format($stats['jasmc_st']['total']) ?></span>
                        <span>&bull;</span>
                        <span class="hover:text-primary transition-colors">VT: <?= number_format($stats['jasmc_vt']['total']) ?></span>
                        <span>&bull;</span>
                        <span class="hover:text-primary transition-colors">JBL: <?= number_format($stats['doh_jbl_cmc']['total']) ?></span>
                    </div>
                </div>
                <a href="<?= BASE_URL ?>/modules/computers/" class="absolute inset-0 z-20" aria-label="Open Computers Module"></a>
            </div>
        </div>

        <!-- Asset Inventory Card -->
        <div class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] p-6 rounded-2xl relative overflow-hidden group hover:border-teal-500/40 transition-all shadow-sm">
            <div class="absolute top-0 right-0 p-6 opacity-10 group-hover:opacity-20 transition-opacity">
                <span class="material-symbols-outlined text-6xl text-teal-500">inventory_2</span>
            </div>
            <div class="relative z-10">
                <div class="flex items-center gap-3 mb-4">
                    <div class="p-2 bg-teal-500/10 rounded-lg text-teal-500">
                        <span class="material-symbols-outlined">inventory_2</span>
                    </div>
                    <div>
                        <h3 class="font-bold text-slate-900 dark:text-white leading-tight">Asset Inventory</h3>
                        <p class="text-[10px] text-slate-400 uppercase font-semibold">Registry &amp; Lifecycle</p>
                    </div>
                </div>
                <div class="text-3xl font-bold text-slate-900 dark:text-white mb-2">
                    <?= number_format($stats['assets']['total']) ?>
                </div>
                <div class="flex items-center justify-between text-xs font-medium mb-3">
                    <span class="text-teal-600 dark:text-teal-400 font-semibold">
                        <?= number_format($stats['assets']['active']) ?> Active
                    </span>
                    <span class="text-slate-500 dark:text-slate-400">
                        Val: &#8369;<?= number_format($stats['assets']['total_cost'], 0) ?>
                    </span>
                </div>
                <div class="text-[11px] text-slate-400 border-t border-slate-100 dark:border-[#232b3d] pt-2 truncate">
                    Hardware specs, MR/ICS, &amp; cost tracking
                </div>
                <a href="<?= BASE_URL ?>/modules/assets/" class="absolute inset-0 z-20" aria-label="Open Asset Inventory"></a>
            </div>
        </div>

        <!-- Preventive Maintenance Card -->
        <div class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] p-6 rounded-2xl relative overflow-hidden group hover:border-sky-500/40 transition-all shadow-sm">
            <div class="absolute top-0 right-0 p-6 opacity-10 group-hover:opacity-20 transition-opacity">
                <span class="material-symbols-outlined text-6xl text-sky-500">health_and_safety</span>
            </div>
            <div class="relative z-10">
                <div class="flex items-center gap-3 mb-4">
                    <div class="p-2 bg-sky-500/10 rounded-lg text-sky-500">
                        <span class="material-symbols-outlined">health_and_safety</span>
                    </div>
                    <div>
                        <h3 class="font-bold text-slate-900 dark:text-white leading-tight">Maintenance (PM)</h3>
                        <p class="text-[10px] text-slate-400 uppercase font-semibold">Checklists &amp; Audits</p>
                    </div>
                </div>
                <div class="text-3xl font-bold text-slate-900 dark:text-white mb-2">
                    <?= number_format($stats['maintenance']['total']) ?>
                </div>
                <div class="flex items-center justify-between text-xs font-medium mb-3">
                    <span class="text-emerald-500 font-semibold">
                        Health: <?= $stats['maintenance']['avg_score'] > 0 ? $stats['maintenance']['avg_score'] . '%' : '100%' ?>
                    </span>
                    <span class="<?= $stats['maintenance']['alerts'] > 0 ? 'text-amber-500 font-bold' : 'text-slate-400' ?>">
                        <?= number_format($stats['maintenance']['alerts']) ?> Action Req.
                    </span>
                </div>
                <div class="text-[11px] text-slate-400 border-t border-slate-100 dark:border-[#232b3d] pt-2 truncate">
                    Quarterly hardware &amp; software maintenance
                </div>
                <a href="<?= BASE_URL ?>/modules/maintenance/" class="absolute inset-0 z-20" aria-label="Open Preventive Maintenance"></a>
            </div>
        </div>

        <!-- Reconciliation Card -->
        <div class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] p-6 rounded-2xl relative overflow-hidden group hover:border-rose-500/40 transition-all shadow-sm">
            <div class="absolute top-0 right-0 p-6 opacity-10 group-hover:opacity-20 transition-opacity">
                <span class="material-symbols-outlined text-6xl text-rose-500">sync_alt</span>
            </div>
            <div class="relative z-10">
                <div class="flex items-center gap-3 mb-4">
                    <div class="p-2 bg-rose-500/10 rounded-lg text-rose-500">
                        <span class="material-symbols-outlined">sync_alt</span>
                    </div>
                    <div>
                        <h3 class="font-bold text-slate-900 dark:text-white leading-tight">Compare &amp; Sync</h3>
                        <p class="text-[10px] text-slate-400 uppercase font-semibold">IP &amp; MAC Audit</p>
                    </div>
                </div>
                <div class="text-3xl font-bold text-slate-900 dark:text-white mb-2">
                    <?= number_format($stats['reconciliation']['total']) ?>
                </div>
                <div class="flex gap-4 text-xs font-medium mb-3">
                    <span class="<?= $stats['reconciliation']['total'] > 0 ? 'text-rose-500 font-bold' : 'text-emerald-500 font-semibold' ?>">
                        <?= $stats['reconciliation']['total'] > 0 ? 'Discrepancies found' : 'All systems synced' ?>
                    </span>
                </div>
                <div class="text-[11px] text-slate-400 border-t border-slate-100 dark:border-[#232b3d] pt-2 truncate">
                    Cross-referencing computers &amp; IP subnet leases
                </div>
                <a href="<?= BASE_URL ?>/modules/reconciliation/" class="absolute inset-0 z-20" aria-label="Open Reconciliation"></a>
            </div>
        </div>

        <!-- Routers Card -->
        <div class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] p-6 rounded-2xl relative overflow-hidden group hover:border-blue-500/40 transition-all shadow-sm">
            <div class="absolute top-0 right-0 p-6 opacity-10 group-hover:opacity-20 transition-opacity">
                <span class="material-symbols-outlined text-6xl text-blue-500">router</span>
            </div>
            <div class="relative z-10">
                <div class="flex items-center gap-3 mb-4">
                    <div class="p-2 bg-blue-500/10 rounded-lg text-blue-500">
                        <span class="material-symbols-outlined">router</span>
                    </div>
                    <div>
                        <h3 class="font-bold text-slate-900 dark:text-white leading-tight">Routers</h3>
                        <p class="text-[10px] text-slate-400 uppercase font-semibold">Edge Gateways</p>
                    </div>
                </div>
                <div class="text-3xl font-bold text-slate-900 dark:text-white mb-2">
                    <?= number_format($stats['routers']['total']) ?>
                </div>
                <div class="flex gap-4 text-xs font-medium mb-3">
                    <span class="text-emerald-500 font-semibold">
                        <?= number_format($stats['routers']['online']) ?> Online
                    </span>
                    <span class="text-red-500 font-semibold">
                        <?= number_format($stats['routers']['offline']) ?> Offline
                    </span>
                </div>
                <div class="text-[11px] text-slate-400 border-t border-slate-100 dark:border-[#232b3d] pt-2 truncate">
                    Primary hospital gateway &amp; routing nodes
                </div>
                <a href="<?= BASE_URL ?>/modules/routers/" class="absolute inset-0 z-20" aria-label="Open Routers Module"></a>
            </div>
        </div>

        <!-- Switches Card -->
        <div class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] p-6 rounded-2xl relative overflow-hidden group hover:border-purple-500/40 transition-all shadow-sm">
            <div class="absolute top-0 right-0 p-6 opacity-10 group-hover:opacity-20 transition-opacity">
                <span class="material-symbols-outlined text-6xl text-purple-500">switch</span>
            </div>
            <div class="relative z-10">
                <div class="flex items-center gap-3 mb-4">
                    <div class="p-2 bg-purple-500/10 rounded-lg text-purple-500">
                        <span class="material-symbols-outlined">switch</span>
                    </div>
                    <div>
                        <h3 class="font-bold text-slate-900 dark:text-white leading-tight">Switches</h3>
                        <p class="text-[10px] text-slate-400 uppercase font-semibold">Core &amp; Access L2/L3</p>
                    </div>
                </div>
                <div class="text-3xl font-bold text-slate-900 dark:text-white mb-2">
                    <?= number_format($stats['switches']['total']) ?>
                </div>
                <div class="flex gap-4 text-xs font-medium mb-3">
                    <span class="text-emerald-500 font-semibold">
                        <?= number_format($stats['switches']['active']) ?> Active
                    </span>
                    <span class="text-red-500 font-semibold">
                        <?= number_format($stats['switches']['inactive']) ?> Inactive
                    </span>
                </div>
                <div class="text-[11px] text-slate-400 border-t border-slate-100 dark:border-[#232b3d] pt-2 truncate">
                    Distribution rack &amp; IDF switchgear
                </div>
                <a href="<?= BASE_URL ?>/modules/switches/" class="absolute inset-0 z-20" aria-label="Open Switches Module"></a>
            </div>
        </div>

        <!-- IPs Card -->
        <div class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] p-6 rounded-2xl relative overflow-hidden group hover:border-emerald-500/40 transition-all shadow-sm">
            <div class="absolute top-0 right-0 p-6 opacity-10 group-hover:opacity-20 transition-opacity">
                <span class="material-symbols-outlined text-6xl text-emerald-500">dns</span>
            </div>
            <div class="relative z-10">
                <div class="flex items-center gap-3 mb-4">
                    <div class="p-2 bg-emerald-500/10 rounded-lg text-emerald-500">
                        <span class="material-symbols-outlined">dns</span>
                    </div>
                    <div>
                        <h3 class="font-bold text-slate-900 dark:text-white leading-tight">IP Addresses</h3>
                        <p class="text-[10px] text-slate-400 uppercase font-semibold">Subnet Pool</p>
                    </div>
                </div>
                <div class="text-3xl font-bold text-slate-900 dark:text-white mb-2">
                    <?= number_format($stats['ips']['total']) ?>
                </div>
                <div class="flex gap-4 text-xs font-medium mb-3">
                    <span class="text-emerald-500 font-semibold">
                        <?= number_format($stats['ips']['active']) ?> Active
                    </span>
                    <span class="text-slate-500 dark:text-slate-400">
                        <?= number_format($stats['ips']['available']) ?> Available
                    </span>
                </div>
                <div class="text-[11px] text-slate-400 border-t border-slate-100 dark:border-[#232b3d] pt-2 truncate">
                    Static &amp; DHCP reservation pools
                </div>
                <a href="<?= BASE_URL ?>/modules/ips/" class="absolute inset-0 z-20" aria-label="Open IP Addresses Module"></a>
            </div>
        </div>

        <!-- Office Licenses Card -->
        <div class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] p-6 rounded-2xl relative overflow-hidden group hover:border-indigo-500/40 transition-all shadow-sm">
            <div class="absolute top-0 right-0 p-6 opacity-10 group-hover:opacity-20 transition-opacity">
                <span class="material-symbols-outlined text-6xl text-indigo-500">grid_view</span>
            </div>
            <div class="relative z-10">
                <div class="flex items-center gap-3 mb-4">
                    <div class="p-2 bg-indigo-500/10 rounded-lg text-indigo-500">
                        <span class="material-symbols-outlined">grid_view</span>
                    </div>
                    <div>
                        <h3 class="font-bold text-slate-900 dark:text-white leading-tight">Office Licenses</h3>
                        <p class="text-[10px] text-slate-400 uppercase font-semibold">Software Assets</p>
                    </div>
                </div>
                <div class="text-3xl font-bold text-slate-900 dark:text-white mb-2">
                    <?= number_format($stats['office']['total']) ?>
                </div>
                <div class="flex gap-4 text-xs font-medium mb-3">
                    <span class="text-indigo-600 dark:text-indigo-400 font-semibold">
                        Tracked product keys
                    </span>
                </div>
                <div class="text-[11px] text-slate-400 border-t border-slate-100 dark:border-[#232b3d] pt-2 truncate">
                    Microsoft Office volume &amp; retail activations
                </div>
                <a href="<?= BASE_URL ?>/modules/office/" class="absolute inset-0 z-20" aria-label="Open Office Licenses"></a>
            </div>
        </div>

        <!-- Printers Card -->
        <div class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] p-6 rounded-2xl relative overflow-hidden group hover:border-orange-500/40 transition-all shadow-sm">
            <div class="absolute top-0 right-0 p-6 opacity-10 group-hover:opacity-20 transition-opacity">
                <span class="material-symbols-outlined text-6xl text-orange-500">print</span>
            </div>
            <div class="relative z-10">
                <div class="flex items-center gap-3 mb-4">
                    <div class="p-2 bg-orange-500/10 rounded-lg text-orange-500">
                        <span class="material-symbols-outlined">print</span>
                    </div>
                    <div>
                        <h3 class="font-bold text-slate-900 dark:text-white leading-tight">Printers</h3>
                        <p class="text-[10px] text-slate-400 uppercase font-semibold">Peripherals</p>
                    </div>
                </div>
                <div class="text-3xl font-bold text-slate-900 dark:text-white mb-2">
                    <?= number_format($stats['printers']['total']) ?>
                </div>
                <div class="flex gap-4 text-xs font-medium mb-3">
                    <span class="text-slate-500 dark:text-slate-400">
                        Canon LBP 2900 &amp; Multi-function
                    </span>
                </div>
                <div class="text-[11px] text-slate-400 border-t border-slate-100 dark:border-[#232b3d] pt-2 truncate">
                    Wards, offices &amp; billing station printers
                </div>
                <a href="<?= BASE_URL ?>/modules/printers/" class="absolute inset-0 z-20" aria-label="Open Printers Module"></a>
            </div>
        </div>

        <!-- Queueing TVs Card -->
        <div class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] p-6 rounded-2xl relative overflow-hidden group hover:border-cyan-500/40 transition-all shadow-sm">
            <div class="absolute top-0 right-0 p-6 opacity-10 group-hover:opacity-20 transition-opacity">
                <span class="material-symbols-outlined text-6xl text-cyan-500">tv</span>
            </div>
            <div class="relative z-10">
                <div class="flex items-center gap-3 mb-4">
                    <div class="p-2 bg-cyan-500/10 rounded-lg text-cyan-500">
                        <span class="material-symbols-outlined">tv</span>
                    </div>
                    <div>
                        <h3 class="font-bold text-slate-900 dark:text-white leading-tight">Queueing TVs</h3>
                        <p class="text-[10px] text-slate-400 uppercase font-semibold">Displays</p>
                    </div>
                </div>
                <div class="text-3xl font-bold text-slate-900 dark:text-white mb-2">
                    <?= number_format($stats['queueing_tv']['total']) ?>
                </div>
                <div class="flex gap-4 text-xs font-medium mb-3">
                    <span class="text-slate-500 dark:text-slate-400">
                        Display signage arrays
                    </span>
                </div>
                <div class="text-[11px] text-slate-400 border-t border-slate-100 dark:border-[#232b3d] pt-2 truncate">
                    OPD, pharmacy &amp; admission displays
                </div>
                <a href="<?= BASE_URL ?>/modules/queueing_tv/" class="absolute inset-0 z-20" aria-label="Open Queueing TVs"></a>
            </div>
        </div>

        <!-- ICS Inventory Card -->
        <div class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] p-6 rounded-2xl relative overflow-hidden group hover:border-slate-500/40 transition-all shadow-sm">
            <div class="absolute top-0 right-0 p-6 opacity-10 group-hover:opacity-20 transition-opacity">
                <span class="material-symbols-outlined text-6xl text-slate-500">inventory</span>
            </div>
            <div class="relative z-10">
                <div class="flex items-center gap-3 mb-4">
                    <div class="p-2 bg-slate-500/10 rounded-lg text-slate-500">
                        <span class="material-symbols-outlined">inventory</span>
                    </div>
                    <div>
                        <h3 class="font-bold text-slate-900 dark:text-white leading-tight">ICS Inventory</h3>
                        <p class="text-[10px] text-slate-400 uppercase font-semibold">Custodian Slips</p>
                    </div>
                </div>
                <div class="text-3xl font-bold text-slate-900 dark:text-white mb-2">
                    <?= number_format($stats['ics']['total']) ?>
                </div>
                <div class="flex gap-4 text-xs font-medium mb-3">
                    <span class="text-slate-500 dark:text-slate-400">
                        Accountable property items
                    </span>
                </div>
                <div class="text-[11px] text-slate-400 border-t border-slate-100 dark:border-[#232b3d] pt-2 truncate">
                    Semi-expendable IT equipment records
                </div>
                <a href="<?= BASE_URL ?>/modules/ics/" class="absolute inset-0 z-20" aria-label="Open ICS Module"></a>
            </div>
        </div>

        <!-- PABX Directory Card -->
        <div class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] p-6 rounded-2xl relative overflow-hidden group hover:border-teal-500/40 transition-all shadow-sm">
            <div class="absolute top-0 right-0 p-6 opacity-10 group-hover:opacity-20 transition-opacity">
                <span class="material-symbols-outlined text-6xl text-teal-500">contact_phone</span>
            </div>
            <div class="relative z-10">
                <div class="flex items-center gap-3 mb-4">
                    <div class="p-2 bg-teal-500/10 rounded-lg text-teal-500">
                        <span class="material-symbols-outlined">contact_phone</span>
                    </div>
                    <div>
                        <h3 class="font-bold text-slate-900 dark:text-white leading-tight">PABX Directory</h3>
                        <p class="text-[10px] text-slate-400 uppercase font-semibold">Telephony</p>
                    </div>
                </div>
                <div class="text-3xl font-bold text-slate-900 dark:text-white mb-2">
                    <?= number_format($stats['pabx']['total']) ?>
                </div>
                <div class="flex gap-4 text-xs font-medium mb-3">
                    <span class="text-slate-500 dark:text-slate-400">
                        Active telephone lines
                    </span>
                </div>
                <div class="text-[11px] text-slate-400 border-t border-slate-100 dark:border-[#232b3d] pt-2 truncate">
                    Hospital-wide extension lookup
                </div>
                <a href="<?= BASE_URL ?>/modules/pabx/" class="absolute inset-0 z-20" aria-label="Open PABX Module"></a>
            </div>
        </div>

    </div>

    <!-- Satellite Hospital Inventories Hub -->
    <div class="mb-8">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 mb-4">
            <div>
                <h3 class="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary text-[22px]">domain</span>
                    Satellite Hospital Inventories
                </h3>
                <p class="text-xs text-slate-500 dark:text-slate-400">Dedicated computer inventories for decentralized hospital stations and extensions</p>
            </div>
            <div class="flex items-center gap-2">
                <span class="text-xs font-semibold px-2.5 py-1 bg-primary/10 text-primary rounded-full">
                    3 Connected Facilities
                </span>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

            <!-- JASMC-ST Card -->
            <div class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] p-5 rounded-2xl relative overflow-hidden group hover:border-blue-500/50 hover:shadow-md transition-all">
                <div class="flex items-start justify-between mb-3">
                    <div class="flex items-center gap-3">
                        <div class="size-11 rounded-xl bg-blue-500/10 text-blue-500 flex items-center justify-center font-bold text-xs tracking-wider">
                            ST
                        </div>
                        <div>
                            <h4 class="font-bold text-slate-900 dark:text-white text-sm group-hover:text-primary transition-colors">JASMC &mdash; Sto. Tomas</h4>
                            <p class="text-[11px] text-slate-500 dark:text-slate-400">Sto. Tomas Satellite Station</p>
                        </div>
                    </div>
                    <span class="text-[11px] font-mono px-2 py-0.5 rounded bg-slate-100 dark:bg-[#101622] text-slate-600 dark:text-slate-300 font-semibold border border-slate-200 dark:border-[#232b3d]">
                        JASMC-ST
                    </span>
                </div>
                <div class="flex items-baseline justify-between mt-4 pt-3 border-t border-slate-100 dark:border-[#232b3d]">
                    <div>
                        <div class="text-2xl font-bold text-slate-900 dark:text-white"><?= number_format($stats['jasmc_st']['total']) ?></div>
                        <div class="text-[11px] text-slate-400">Computer units deployed</div>
                    </div>
                    <div class="flex items-center gap-1 text-xs font-semibold text-primary group-hover:translate-x-1 transition-transform">
                        <span>Open Inventory</span>
                        <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
                    </div>
                </div>
                <a href="<?= BASE_URL ?>/modules/jasmc_st/" class="absolute inset-0 z-20" aria-label="Open JASMC - Sto. Tomas Module"></a>
            </div>

            <!-- JASMC-VT Card -->
            <div class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] p-5 rounded-2xl relative overflow-hidden group hover:border-purple-500/50 hover:shadow-md transition-all">
                <div class="flex items-start justify-between mb-3">
                    <div class="flex items-center gap-3">
                        <div class="size-11 rounded-xl bg-purple-500/10 text-purple-500 flex items-center justify-center font-bold text-xs tracking-wider">
                            VT
                        </div>
                        <div>
                            <h4 class="font-bold text-slate-900 dark:text-white text-sm group-hover:text-primary transition-colors">JASMC &mdash; Victoria Tarlac</h4>
                            <p class="text-[11px] text-slate-500 dark:text-slate-400">Victoria Tarlac Satellite Station</p>
                        </div>
                    </div>
                    <span class="text-[11px] font-mono px-2 py-0.5 rounded bg-slate-100 dark:bg-[#101622] text-slate-600 dark:text-slate-300 font-semibold border border-slate-200 dark:border-[#232b3d]">
                        JASMC-VT
                    </span>
                </div>
                <div class="flex items-baseline justify-between mt-4 pt-3 border-t border-slate-100 dark:border-[#232b3d]">
                    <div>
                        <div class="text-2xl font-bold text-slate-900 dark:text-white"><?= number_format($stats['jasmc_vt']['total']) ?></div>
                        <div class="text-[11px] text-slate-400">Computer units deployed</div>
                    </div>
                    <div class="flex items-center gap-1 text-xs font-semibold text-primary group-hover:translate-x-1 transition-transform">
                        <span>Open Inventory</span>
                        <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
                    </div>
                </div>
                <a href="<?= BASE_URL ?>/modules/jasmc_vt/" class="absolute inset-0 z-20" aria-label="Open JASMC - Victoria Tarlac Module"></a>
            </div>

            <!-- DOH-JBL-CMC Card -->
            <div class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] p-5 rounded-2xl relative overflow-hidden group hover:border-emerald-500/50 hover:shadow-md transition-all">
                <div class="flex items-start justify-between mb-3">
                    <div class="flex items-center gap-3">
                        <div class="size-11 rounded-xl bg-emerald-500/10 text-emerald-500 flex items-center justify-center font-bold text-xs tracking-wider">
                            JBL
                        </div>
                        <div>
                            <h4 class="font-bold text-slate-900 dark:text-white text-sm group-hover:text-primary transition-colors">DOH-JBL Children's Medical Center</h4>
                            <p class="text-[11px] text-slate-500 dark:text-slate-400">Extension Hospital Facility</p>
                        </div>
                    </div>
                    <span class="text-[11px] font-mono px-2 py-0.5 rounded bg-slate-100 dark:bg-[#101622] text-slate-600 dark:text-slate-300 font-semibold border border-slate-200 dark:border-[#232b3d]">
                        DOH-JBL-CMC
                    </span>
                </div>
                <div class="flex items-baseline justify-between mt-4 pt-3 border-t border-slate-100 dark:border-[#232b3d]">
                    <div>
                        <div class="text-2xl font-bold text-slate-900 dark:text-white"><?= number_format($stats['doh_jbl_cmc']['total']) ?></div>
                        <div class="text-[11px] text-slate-400">Computer units deployed</div>
                    </div>
                    <div class="flex items-center gap-1 text-xs font-semibold text-primary group-hover:translate-x-1 transition-transform">
                        <span>Open Inventory</span>
                        <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
                    </div>
                </div>
                <a href="<?= BASE_URL ?>/modules/doh_jbl_cmc/" class="absolute inset-0 z-20" aria-label="Open DOH-JBL Children's Medical Center Module"></a>
            </div>

        </div>
    </div>

    <!-- Quick Actions & Recent Activity -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        <!-- Quick Actions Panel -->
        <div class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] rounded-2xl p-6 shadow-sm">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary text-[20px]">bolt</span>
                    Quick Actions
                </h3>
                <span class="text-xs text-slate-400">Management Shortcuts</span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">

                <!-- Add Computer -->
                <button onclick="location.href='<?= BASE_URL ?>/modules/computers/'"
                    class="flex items-center justify-between p-3 bg-white hover:bg-slate-50 dark:bg-[#101622] dark:hover:bg-[#151b29] border border-slate-200 dark:border-[#232b3d] rounded-xl transition-all group hover:border-amber-500/50">
                    <div class="flex items-center gap-3">
                        <span class="material-symbols-outlined text-amber-500 text-[20px]">computer</span>
                        <span class="text-xs font-semibold text-slate-700 dark:text-slate-300 group-hover:text-slate-900 dark:group-hover:text-white">Add Computer</span>
                    </div>
                    <span class="material-symbols-outlined text-slate-400 group-hover:text-amber-500 text-[16px] transition-colors">arrow_forward</span>
                </button>

                <!-- Add Asset -->
                <button onclick="location.href='<?= BASE_URL ?>/modules/assets/'"
                    class="flex items-center justify-between p-3 bg-white hover:bg-slate-50 dark:bg-[#101622] dark:hover:bg-[#151b29] border border-slate-200 dark:border-[#232b3d] rounded-xl transition-all group hover:border-teal-500/50">
                    <div class="flex items-center gap-3">
                        <span class="material-symbols-outlined text-teal-500 text-[20px]">inventory_2</span>
                        <span class="text-xs font-semibold text-slate-700 dark:text-slate-300 group-hover:text-slate-900 dark:group-hover:text-white">Add Asset</span>
                    </div>
                    <span class="material-symbols-outlined text-slate-400 group-hover:text-teal-500 text-[16px] transition-colors">arrow_forward</span>
                </button>

                <!-- New PM Inspection -->
                <button onclick="location.href='<?= BASE_URL ?>/modules/maintenance/'"
                    class="flex items-center justify-between p-3 bg-white hover:bg-slate-50 dark:bg-[#101622] dark:hover:bg-[#151b29] border border-slate-200 dark:border-[#232b3d] rounded-xl transition-all group hover:border-sky-500/50">
                    <div class="flex items-center gap-3">
                        <span class="material-symbols-outlined text-sky-500 text-[20px]">health_and_safety</span>
                        <span class="text-xs font-semibold text-slate-700 dark:text-slate-300 group-hover:text-slate-900 dark:group-hover:text-white">Schedule PM</span>
                    </div>
                    <span class="material-symbols-outlined text-slate-400 group-hover:text-sky-500 text-[16px] transition-colors">arrow_forward</span>
                </button>

                <!-- Compare & Sync -->
                <button onclick="location.href='<?= BASE_URL ?>/modules/reconciliation/'"
                    class="flex items-center justify-between p-3 bg-white hover:bg-slate-50 dark:bg-[#101622] dark:hover:bg-[#151b29] border border-slate-200 dark:border-[#232b3d] rounded-xl transition-all group hover:border-rose-500/50">
                    <div class="flex items-center gap-3">
                        <span class="material-symbols-outlined text-rose-500 text-[20px]">sync_alt</span>
                        <span class="text-xs font-semibold text-slate-700 dark:text-slate-300 group-hover:text-slate-900 dark:group-hover:text-white">Compare &amp; Sync</span>
                    </div>
                    <span class="material-symbols-outlined text-slate-400 group-hover:text-rose-500 text-[16px] transition-colors">arrow_forward</span>
                </button>

                <!-- Add IP Address -->
                <button onclick="location.href='<?= BASE_URL ?>/modules/ips/'"
                    class="flex items-center justify-between p-3 bg-white hover:bg-slate-50 dark:bg-[#101622] dark:hover:bg-[#151b29] border border-slate-200 dark:border-[#232b3d] rounded-xl transition-all group hover:border-emerald-500/50">
                    <div class="flex items-center gap-3">
                        <span class="material-symbols-outlined text-emerald-500 text-[20px]">dns</span>
                        <span class="text-xs font-semibold text-slate-700 dark:text-slate-300 group-hover:text-slate-900 dark:group-hover:text-white">Add IP Address</span>
                    </div>
                    <span class="material-symbols-outlined text-slate-400 group-hover:text-emerald-500 text-[16px] transition-colors">arrow_forward</span>
                </button>

                <!-- Add Router -->
                <button onclick="location.href='<?= BASE_URL ?>/modules/routers/'"
                    class="flex items-center justify-between p-3 bg-white hover:bg-slate-50 dark:bg-[#101622] dark:hover:bg-[#151b29] border border-slate-200 dark:border-[#232b3d] rounded-xl transition-all group hover:border-blue-500/50">
                    <div class="flex items-center gap-3">
                        <span class="material-symbols-outlined text-blue-500 text-[20px]">router</span>
                        <span class="text-xs font-semibold text-slate-700 dark:text-slate-300 group-hover:text-slate-900 dark:group-hover:text-white">Add Router</span>
                    </div>
                    <span class="material-symbols-outlined text-slate-400 group-hover:text-blue-500 text-[16px] transition-colors">arrow_forward</span>
                </button>

                <!-- Add Switch -->
                <button onclick="location.href='<?= BASE_URL ?>/modules/switches/'"
                    class="flex items-center justify-between p-3 bg-white hover:bg-slate-50 dark:bg-[#101622] dark:hover:bg-[#151b29] border border-slate-200 dark:border-[#232b3d] rounded-xl transition-all group hover:border-purple-500/50">
                    <div class="flex items-center gap-3">
                        <span class="material-symbols-outlined text-purple-500 text-[20px]">switch</span>
                        <span class="text-xs font-semibold text-slate-700 dark:text-slate-300 group-hover:text-slate-900 dark:group-hover:text-white">Add Switch</span>
                    </div>
                    <span class="material-symbols-outlined text-slate-400 group-hover:text-purple-500 text-[16px] transition-colors">arrow_forward</span>
                </button>

                <!-- Add Office License -->
                <button onclick="location.href='<?= BASE_URL ?>/modules/office/'"
                    class="flex items-center justify-between p-3 bg-white hover:bg-slate-50 dark:bg-[#101622] dark:hover:bg-[#151b29] border border-slate-200 dark:border-[#232b3d] rounded-xl transition-all group hover:border-indigo-500/50">
                    <div class="flex items-center gap-3">
                        <span class="material-symbols-outlined text-indigo-500 text-[20px]">grid_view</span>
                        <span class="text-xs font-semibold text-slate-700 dark:text-slate-300 group-hover:text-slate-900 dark:group-hover:text-white">Add License</span>
                    </div>
                    <span class="material-symbols-outlined text-slate-400 group-hover:text-indigo-500 text-[16px] transition-colors">arrow_forward</span>
                </button>

                <!-- Add Printer -->
                <button onclick="location.href='<?= BASE_URL ?>/modules/printers/'"
                    class="flex items-center justify-between p-3 bg-white hover:bg-slate-50 dark:bg-[#101622] dark:hover:bg-[#151b29] border border-slate-200 dark:border-[#232b3d] rounded-xl transition-all group hover:border-orange-500/50">
                    <div class="flex items-center gap-3">
                        <span class="material-symbols-outlined text-orange-500 text-[20px]">print</span>
                        <span class="text-xs font-semibold text-slate-700 dark:text-slate-300 group-hover:text-slate-900 dark:group-hover:text-white">Add Printer</span>
                    </div>
                    <span class="material-symbols-outlined text-slate-400 group-hover:text-orange-500 text-[16px] transition-colors">arrow_forward</span>
                </button>

                <!-- Add Queueing TV -->
                <button onclick="location.href='<?= BASE_URL ?>/modules/queueing_tv/'"
                    class="flex items-center justify-between p-3 bg-white hover:bg-slate-50 dark:bg-[#101622] dark:hover:bg-[#151b29] border border-slate-200 dark:border-[#232b3d] rounded-xl transition-all group hover:border-cyan-500/50">
                    <div class="flex items-center gap-3">
                        <span class="material-symbols-outlined text-cyan-500 text-[20px]">tv</span>
                        <span class="text-xs font-semibold text-slate-700 dark:text-slate-300 group-hover:text-slate-900 dark:group-hover:text-white">Add Queueing TV</span>
                    </div>
                    <span class="material-symbols-outlined text-slate-400 group-hover:text-cyan-500 text-[16px] transition-colors">arrow_forward</span>
                </button>

            </div>
        </div>

        <!-- Recent Activity Log Stream -->
        <div class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] rounded-2xl p-6 shadow-sm flex flex-col">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary text-[20px]">history</span>
                    Recent Activity
                </h3>
                <a href="<?= BASE_URL ?>/modules/logs/" class="text-xs text-primary hover:underline font-semibold flex items-center gap-1">
                    <span>Audit Trail</span>
                    <span class="material-symbols-outlined text-[14px]">arrow_forward</span>
                </a>
            </div>

            <?php if (empty($logs)): ?>
                <div class="flex-1 flex flex-col items-center justify-center text-slate-400 py-12 text-sm">
                    <span class="material-symbols-outlined text-4xl mb-2 text-slate-300 dark:text-slate-600">history_toggle_off</span>
                    No recent activity logged
                </div>
            <?php else: ?>
                <div class="space-y-3 flex-1 overflow-y-auto custom-scrollbar max-h-[380px] pr-1">
                    <?php foreach ($logs as $log): ?>
                        <div class="flex items-start gap-3 p-2.5 rounded-xl hover:bg-slate-50 dark:hover:bg-[#101622] transition-colors border border-transparent hover:border-slate-100 dark:hover:border-[#232b3d]">
                            <div class="size-8 rounded-full bg-primary/10 text-primary flex items-center justify-center flex-shrink-0 mt-0.5">
                                <span class="material-symbols-outlined text-sm">history</span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-bold text-slate-900 dark:text-white truncate">
                                    <?= htmlspecialchars($log['action_type'] ?? 'Action') ?>
                                </p>
                                <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5 line-clamp-2">
                                    <span class="font-semibold text-slate-700 dark:text-slate-300"><?= htmlspecialchars($log['username'] ?? 'System') ?></span> &mdash; <?= htmlspecialchars($log['details'] ?? '') ?>
                                </p>
                            </div>
                            <div class="text-[10px] text-slate-400 dark:text-slate-500 font-mono whitespace-nowrap pl-2 flex-shrink-0">
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
