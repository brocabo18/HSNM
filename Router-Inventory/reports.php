<?php
session_start();
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header('Location: login');
    exit;
}

require_once 'db.php';

// Handle CSV Download
if (isset($_GET['action']) && $_GET['action'] === 'download_csv') {
    $report_type = $_GET['type'] ?? 'full';

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="report_' . $report_type . '_' . date('Y-m-d') . '.csv"');

    $output = fopen('php://output', 'w');

    // Headers
    fputcsv($output, ['ID', 'Serial', 'Model', 'Location', 'Region', 'IP', 'Status', 'Last Seen']);

    $query = "SELECT id, serial_number, model, location, region, ip_address, status, last_seen FROM inventory";
    if ($report_type === 'maintenance') {
        $query .= " WHERE status IN ('Offline', 'Warning', 'Critical')";
    }

    $stmt = $pdo->query($query);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, $row);
    }

    fclose($output);
    exit;
}

// Fetch some logs for the preview
$logs_stmt = $pdo->query("SELECT * FROM system_logs ORDER BY created_at DESC LIMIT 10");
$logs = $logs_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en" class="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - <?php echo htmlspecialchars($portalName ?? "IHOMS Router Portal"); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap"
        rel="stylesheet" />
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        primary: '#10b981',
                        'background-dark': '#101622',
                        'background-light': '#1a2130',
                        'border-dark': '#232b3d',
                    },
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }

        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
        }

        .custom-scrollbar::-webkit-scrollbar-track {
            background: transparent;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #232b3d;
            border-radius: 10px;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: #10b981;
        }
    </style>
</head>

<body class="bg-background-dark font-display text-slate-100 min-h-screen flex overflow-hidden">
    <!-- Sidebar Navigation -->
    <aside class="w-72 bg-[#101622] border-r border-[#232b3d] flex flex-col items-stretch shrink-0 z-20">
        <div class="p-6">
            <div class="flex items-center gap-3">
                <div class="size-10 bg-primary rounded-xl flex items-center justify-center shadow-lg shadow-primary/20">
                    <span class="material-symbols-outlined text-white text-[24px]">router</span>
                </div>
                <div>
                    <h1 class="text-white font-bold text-lg leading-tight">
                        <?= $portalName ?? 'IHOMS' ?>
                    </h1>
                    <p class="text-xs text-slate-500 font-medium">
                        <?= $portalSubtitle ?? 'Router Portal' ?>
                    </p>
                </div>
            </div>
        </div>

        <nav class="flex-1 px-4 space-y-1 overflow-y-auto custom-scrollbar">
            <div class="px-2 py-3">
                <p class="text-xs font-semibold text-slate-600 uppercase tracking-wider">Main Menu</p>
            </div>

            <a href="index"
                class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-white/5 text-slate-400 hover:text-white transition-all group">
                <span
                    class="material-symbols-outlined text-[20px] group-hover:text-primary transition-colors">dashboard</span>
                <span class="text-sm font-semibold">Overview</span>
            </a>

            <a href="inventory"
                class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-white/5 text-slate-400 hover:text-white transition-all group">
                <span
                    class="material-symbols-outlined text-[20px] group-hover:text-primary transition-colors">inventory_2</span>
                <span class="text-sm font-semibold">Inventory</span>
            </a>

            <a href="locations"
                class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-white/5 text-slate-400 hover:text-white transition-all group">
                <span
                    class="material-symbols-outlined text-[20px] group-hover:text-primary transition-colors">map</span>
                <span class="text-sm font-semibold">Locations</span>
            </a>

            <div class="px-2 py-3 mt-4">
                <p class="text-xs font-semibold text-slate-600 uppercase tracking-wider">Management</p>
            </div>

            <a href="reports"
                class="flex items-center gap-3 px-4 py-3 rounded-xl bg-primary/10 text-primary transition-all group">
                <span class="material-symbols-outlined text-[20px]">analytics</span>
                <span class="text-sm font-semibold">Reports</span>
            </a>

            <a href="audit_trail"
                class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-white/5 text-slate-400 hover:text-white transition-all group">
                <span
                    class="material-symbols-outlined text-[20px] group-hover:text-primary transition-colors">history</span>
                <span class="text-sm font-semibold">Audit Logs</span>
            </a>

            <a href="settings"
                class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-white/5 text-slate-400 hover:text-white transition-all group">
                <span
                    class="material-symbols-outlined text-[20px] group-hover:text-primary transition-colors">settings</span>
                <span class="text-sm font-semibold">Settings</span>
            </a>
        </nav>

        <div class="p-6 border-t border-[#232b3d]">
            <a href="logout"
                class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-red-500/10 hover:text-red-500 transition-all group">
                <span
                    class="material-symbols-outlined text-[20px] group-hover:text-red-500 transition-colors">logout</span>
                <span class="text-sm font-semibold">Sign Out</span>
            </a>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 flex flex-col h-screen overflow-hidden bg-[#101622] relative">
        <!-- Background Pattern -->
        <div class="absolute inset-0 opacity-[0.03] pointer-events-none">
            <div class="absolute inset-0"
                style="background-image: radial-gradient(#10b981 1px, transparent 1px); background-size: 32px 32px;">
            </div>
        </div>

        <header
            class="h-16 border-b border-[#232b3d] flex items-center justify-between px-8 bg-[#101622]/90 backdrop-blur-md relative z-10">
            <div class="flex items-center gap-3">
                <span class="material-symbols-outlined text-primary">analytics</span>
                <h2 class="text-sm font-bold text-white">System Reports</h2>
            </div>

            <div class="flex items-center gap-4">
                <div class="relative group">
                    <span
                        class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-[18px] text-slate-500 group-focus-within:text-primary transition-colors">search</span>
                    <input type="text" placeholder="Search reports..."
                        class="bg-[#1a2130] border border-[#232b3d] text-slate-200 text-xs rounded-xl pl-9 pr-4 py-2 focus:ring-2 focus:ring-primary focus:border-transparent w-64 transition-all">
                </div>
            </div>
        </header>

        <div class="p-8 flex-1 overflow-y-auto custom-scrollbar relative z-10">
            <!-- Page Heading -->
            <div class="mb-8">
                <h2 class="text-2xl font-bold text-white">Performance Analytics</h2>
                <p class="text-sm text-slate-500 mt-1">On-demand reporting and historical data insights</p>
            </div>

            <!-- Report Actions -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-12">
                <div
                    class="bg-[#1a2130] border border-[#232b3d] p-6 rounded-2xl group hover:border-primary/50 transition-all shadow-sm">
                    <div
                        class="size-12 rounded-xl bg-primary/10 flex items-center justify-center text-primary mb-6 group-hover:scale-110 transition-transform">
                        <span class="material-symbols-outlined text-[28px]">inventory_2</span>
                    </div>
                    <h3 class="text-sm font-bold text-white mb-2">Full Inventory</h3>
                    <p class="text-xs text-slate-400 leading-relaxed mb-6">Download a complete CSV export of all
                        router hardware, serials, and configurations.</p>
                    <form method="POST">
                        <button type="submit" name="export_csv"
                            class="w-full py-2.5 bg-primary/10 hover:bg-primary text-primary hover:text-white text-xs font-bold rounded-lg transition-all border border-primary/20">
                            Export CSV
                        </button>
                    </form>
                </div>

                <div
                    class="bg-[#1a2130] border border-[#232b3d] p-6 rounded-2xl group hover:border-emerald-500/50 transition-all shadow-sm">
                    <div
                        class="size-12 rounded-xl bg-emerald-500/10 flex items-center justify-center text-emerald-500 mb-6 group-hover:scale-110 transition-transform">
                        <span class="material-symbols-outlined text-[28px]">network_check</span>
                    </div>
                    <h3 class="text-sm font-bold text-white mb-2">Health Audit</h3>
                    <p class="text-xs text-slate-400 leading-relaxed mb-6">Summary of device uptimes, offline
                        events, and critical alerts over the last 30 days.</p>
                    <button
                        class="w-full py-2.5 bg-emerald-500/10 hover:bg-emerald-500 text-emerald-500 hover:text-white text-xs font-bold rounded-lg transition-all border border-emerald-500/20">
                        Run Audit
                    </button>
                </div>

                <div
                    class="bg-[#1a2130] border border-[#232b3d] p-6 rounded-2xl group hover:border-amber-500/50 transition-all shadow-sm">
                    <div
                        class="size-12 rounded-xl bg-amber-500/10 flex items-center justify-center text-amber-500 mb-6 group-hover:scale-110 transition-transform">
                        <span class="material-symbols-outlined text-[28px]">security_update_good</span>
                    </div>
                    <h3 class="text-sm font-bold text-white mb-2">Security Scan</h3>
                    <p class="text-xs text-slate-400 leading-relaxed mb-6">Review administrator access logs and
                        identify unauthorized configuration attempt.</p>
                    <button
                        class="w-full py-2.5 bg-amber-500/10 hover:bg-amber-500 text-amber-500 hover:text-white text-xs font-bold rounded-lg transition-all border border-amber-500/20">
                        Audit Access
                    </button>
                </div>
            </div>

            <!-- Recent Logs Section -->
            <div class="space-y-4">
                <h3 class="text-sm font-bold text-white px-1">System Health Exceptions</h3>
                <div class="bg-[#1a2130] border border-[#232b3d] rounded-2xl overflow-hidden shadow-sm">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-[#1a2130] border-b border-[#232b3d]">
                                    <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase tracking-wider">
                                        Timestamp</th>
                                    <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase tracking-wider">
                                        Severity</th>
                                    <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase tracking-wider">
                                        Event</th>
                                    <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase tracking-wider">
                                        Description</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#232b3d]/50">
                                <?php foreach ($logs as $log):
                                    $level_class = match ($log['log_level']) {
                                        'Critical' => 'text-rose-500 bg-rose-500/10 border-rose-500/20',
                                        'Error' => 'text-rose-500 bg-rose-500/10 border-rose-500/20',
                                        'Warning' => 'text-amber-500 bg-amber-500/10 border-amber-500/20',
                                        'Info' => 'text-primary bg-primary/10 border-primary/20',
                                        default => 'text-slate-500 bg-[#1a2130] border-[#232b3d]'
                                    };
                                    ?>
                                    <tr class="hover:bg-white/5 transition-colors group">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span
                                                class="text-xs font-mono text-slate-500"><?= htmlspecialchars($log['created_at']) ?></span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span
                                                class="px-2 py-0.5 rounded border text-[10px] font-bold uppercase tracking-wider <?= $level_class ?>">
                                                <?= htmlspecialchars($log['log_level']) ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span
                                                class="text-xs font-semibold text-white"><?= htmlspecialchars($log['title']) ?></span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <p
                                                class="text-xs text-slate-400 truncate max-w-xs group-hover:text-slate-300 transition-colors">
                                                <?= htmlspecialchars($log['description']) ?>
                                            </p>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>

</html>