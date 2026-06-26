<?php
session_start();
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header('Location: login');
    exit;
}

// Include Database Connection
require_once 'db.php';

// Fetch KPI Stats
// Total Devices
$stmt = $pdo->query("SELECT COUNT(*) FROM inventory");
$total_devices = $stmt->fetchColumn();

// Online Now
$stmt = $pdo->query("SELECT COUNT(*) FROM inventory WHERE status = 'Online'");
$online_now = $stmt->fetchColumn();

// Offline Devices
$stmt = $pdo->query("SELECT COUNT(*) FROM inventory WHERE status = 'Offline'");
$offline_count = $stmt->fetchColumn();

// Alerts/Warning/Critical
$stmt = $pdo->query("SELECT COUNT(*) FROM inventory WHERE status IN ('Warning', 'Critical', 'Power Fail', 'High Latency')");
$alerts_count = $stmt->fetchColumn();

// Calculate Percentages for Pie Chart
$total_for_calc = $total_devices > 0 ? $total_devices : 1;
$online_pct = round(($online_now / $total_for_calc) * 100);
$offline_pct = round(($offline_count / $total_for_calc) * 100);
$other_pct = 100 - $online_pct - $offline_pct;

// Fetch System Logs
$stmt = $pdo->query("SELECT * FROM system_logs ORDER BY created_at DESC LIMIT 5");
$logs_data = $stmt->fetchAll();

function getLogIcon($level)
{
    switch ($level) {
        case 'Success':
            return ['icon' => 'check_circle', 'bg' => 'bg-[#10b981]/10', 'text' => 'text-[#10b981]'];
        case 'Warning':
            return ['icon' => 'bolt', 'bg' => 'bg-amber-500/10', 'text' => 'text-amber-500'];
        case 'Critical':
            return ['icon' => 'error', 'bg' => 'bg-rose-500/10', 'text' => 'text-rose-500'];
        case 'Info':
            return ['icon' => 'info', 'bg' => 'bg-primary/10', 'text' => 'text-primary'];
        default:
            return ['icon' => 'notifications', 'bg' => 'bg-slate-500/10', 'text' => 'text-slate-400'];
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IHOMS Router Portal - Dashboard</title>
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
            background: #101622;
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

<body class="bg-background-dark text-slate-400 custom-scrollbar overflow-hidden">
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar Navigation -->
        <aside class="w-72 bg-[#101622] border-r border-[#232b3d] flex flex-col items-stretch shrink-0 z-20">
            <div class="p-6">
                <div class="flex items-center gap-3">
                    <div
                        class="size-10 bg-primary rounded-xl flex items-center justify-center shadow-lg shadow-primary/20">
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
                    class="flex items-center gap-3 px-4 py-3 rounded-xl bg-primary/10 text-primary transition-all group">
                    <span class="material-symbols-outlined text-[20px]">dashboard</span>
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
                    class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-white/5 text-slate-400 hover:text-white transition-all group">
                    <span
                        class="material-symbols-outlined text-[20px] group-hover:text-primary transition-colors">analytics</span>
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
                    <span class="material-symbols-outlined text-primary">dashboard</span>
                    <h2 class="text-sm font-bold text-white">Network Overview</h2>
                </div>

                <div class="flex items-center gap-4">
                    <div class="relative group">
                        <span
                            class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-[18px] text-slate-500 group-focus-within:text-primary transition-colors">search</span>
                        <input type="text" placeholder="Search..."
                            class="bg-[#1a2130] border border-[#232b3d] text-slate-200 text-xs rounded-xl pl-9 pr-4 py-2 focus:ring-2 focus:ring-primary focus:border-transparent w-64 transition-all">
                    </div>

                    <div class="h-6 w-px bg-[#232b3d] mx-2"></div>

                    <div class="flex items-center gap-2 bg-[#1a2130] border border-[#232b3d] px-3 py-1.5 rounded-lg">
                        <div class="relative flex h-2 w-2">
                            <span
                                class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-500 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                        </div>
                        <span class="text-xs font-semibold text-white">System Live</span>
                    </div>
                </div>
            </header>

            <div class="p-8 flex-1 overflow-y-auto custom-scrollbar relative z-10">
                <!-- Page Heading -->
                <div class="mb-8">
                    <h2 class="text-2xl font-bold text-white">Dashboard</h2>
                    <p class="text-sm text-slate-500 mt-1">Overview of your network infrastructure</p>
                </div>

                <!-- Stats Overview -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                    <!-- Stat Card 1 -->
                    <div
                        class="bg-[#1a2130] border border-[#232b3d] p-5 rounded-2xl relative group hover:border-primary/50 transition-all duration-300">
                        <div class="flex items-start justify-between mb-4">
                            <div class="rounded-lg bg-primary/10 p-2 text-primary">
                                <span class="material-symbols-outlined text-[24px]">router</span>
                            </div>
                            <span
                                class="px-2 py-1 rounded-md bg-[#101622] border border-[#232b3d] text-xs font-semibold text-slate-400">Total</span>
                        </div>
                        <h3 class="text-2xl font-bold text-white mb-1"><?= number_format($total_devices) ?></h3>
                        <p class="text-xs text-slate-500 font-medium">Deployed Routers</p>
                    </div>

                    <!-- Stat Card 2 -->
                    <div
                        class="bg-[#1a2130] border border-[#232b3d] p-5 rounded-2xl relative group hover:border-emerald-500/50 transition-all duration-300">
                        <div class="flex items-start justify-between mb-4">
                            <div class="rounded-lg bg-emerald-500/10 p-2 text-emerald-500">
                                <span class="material-symbols-outlined text-[24px]">check_circle</span>
                            </div>
                            <span
                                class="px-2 py-1 rounded-md bg-[#101622] border border-[#232b3d] text-xs font-semibold text-emerald-500">
                                <?= $online_pct ?>%
                            </span>
                        </div>
                        <h3 class="text-2xl font-bold text-white mb-1"><?= number_format($online_now) ?></h3>
                        <p class="text-xs text-slate-500 font-medium">Active & Online</p>
                    </div>

                    <!-- Stat Card 3 -->
                    <div
                        class="bg-[#1a2130] border border-[#232b3d] p-5 rounded-2xl relative group hover:border-rose-500/50 transition-all duration-300">
                        <div class="flex items-start justify-between mb-4">
                            <div class="rounded-lg bg-rose-500/10 p-2 text-rose-500">
                                <span class="material-symbols-outlined text-[24px]">link_off</span>
                            </div>
                            <span
                                class="px-2 py-1 rounded-md bg-[#101622] border border-[#232b3d] text-xs font-semibold text-rose-500">Attention</span>
                        </div>
                        <h3 class="text-2xl font-bold text-white mb-1"><?= number_format($offline_count) ?></h3>
                        <p class="text-xs text-slate-500 font-medium">Currently Offline</p>
                    </div>

                    <!-- Stat Card 4 -->
                    <div
                        class="bg-[#1a2130] border border-[#232b3d] p-5 rounded-2xl relative group hover:border-amber-500/50 transition-all duration-300">
                        <div class="flex items-start justify-between mb-4">
                            <div class="rounded-lg bg-amber-500/10 p-2 text-amber-500">
                                <span class="material-symbols-outlined text-[24px]">notifications_active</span>
                            </div>
                            <span
                                class="px-2 py-1 rounded-md bg-[#101622] border border-[#232b3d] text-xs font-semibold text-amber-500">Alerts</span>
                        </div>
                        <h3 class="text-2xl font-bold text-white mb-1"><?= number_format($alerts_count) ?></h3>
                        <p class="text-xs text-slate-500 font-medium">System Notifications</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <!-- Activity Feed -->
                    <div class="lg:col-span-2 space-y-4">
                        <div class="flex items-center justify-between mb-2">
                            <h3 class="text-sm font-bold text-white">Recent Activity</h3>
                            <a href="audit_trail"
                                class="text-xs font-semibold text-primary hover:text-emerald-400 transition-colors">View
                                All Logs</a>
                        </div>

                        <div class="bg-[#1a2130] border border-[#232b3d] rounded-2xl overflow-hidden shadow-sm">
                            <div class="divide-y divide-[#232b3d]">
                                <?php foreach ($logs_data as $log):
                                    $icon = getLogIcon($log['log_level']);
                                    ?>
                                    <div class="p-4 hover:bg-white/5 transition-all">
                                        <div class="flex items-start gap-4">
                                            <div
                                                class="size-10 rounded-xl flex items-center justify-center <?= $icon['bg'] ?> <?= $icon['text'] ?>">
                                                <span
                                                    class="material-symbols-outlined text-[20px]"><?= $icon['icon'] ?></span>
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <div class="flex items-center justify-between gap-2 mb-1">
                                                    <p class="text-sm font-semibold text-white">
                                                        <?= htmlspecialchars($log['title']) ?>
                                                    </p>
                                                    <span
                                                        class="text-xs text-slate-500"><?= htmlspecialchars($log['created_at']) ?></span>
                                                </div>
                                                <p class="text-xs text-slate-400 leading-relaxed">
                                                    <?= htmlspecialchars($log['description']) ?>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Side Panels -->
                    <div class="space-y-6">
                        <!-- Quick Actions -->
                        <div class="space-y-4">
                            <h3 class="text-sm font-bold text-white">Quick Actions</h3>
                            <div class="grid grid-cols-1 gap-3">
                                <a href="inventory"
                                    class="flex items-center gap-3 p-4 rounded-xl bg-primary text-white hover:bg-primary/90 transition-all shadow-lg shadow-primary/20 group">
                                    <span
                                        class="material-symbols-outlined group-hover:rotate-12 transition-transform">add_circle</span>
                                    <span class="text-sm font-bold">Register Device</span>
                                </a>
                                <a href="reports"
                                    class="flex items-center gap-3 p-4 rounded-xl bg-[#1a2130] border border-[#232b3d] text-slate-400 hover:text-white hover:border-primary/50 transition-all group">
                                    <span
                                        class="material-symbols-outlined group-hover:text-primary transition-colors">analytics</span>
                                    <span class="text-sm font-semibold">Generate Report</span>
                                </a>
                                <a href="locations"
                                    class="flex items-center gap-3 p-4 rounded-xl bg-[#1a2130] border border-[#232b3d] text-slate-400 hover:text-white hover:border-primary/50 transition-all group">
                                    <span
                                        class="material-symbols-outlined group-hover:text-primary transition-colors">share_location</span>
                                    <span class="text-sm font-semibold">Deployment Map</span>
                                </a>
                            </div>
                        </div>

                        <!-- Monitor -->
                        <div class="bg-[#1a2130] border border-[#232b3d] rounded-2xl p-6">
                            <h3 class="text-sm font-bold text-white mb-6">Uptime Monitor</h3>

                            <div class="flex flex-col items-center justify-center mb-6">
                                <span class="text-4xl font-bold text-white mb-2"><?= $online_pct ?>%</span>
                                <div class="w-full bg-[#101622] rounded-full h-2 overflow-hidden">
                                    <div class="bg-primary h-2 rounded-full" style="width: <?= $online_pct ?>%"></div>
                                </div>
                                <span class="text-xs text-slate-500 mt-2">Overall Network Availability</span>
                            </div>

                            <div class="space-y-3">
                                <div class="flex items-center justify-between text-xs">
                                    <span class="text-slate-400">Latency (Avg)</span>
                                    <span class="text-white font-semibold">24ms</span>
                                </div>
                                <div class="flex items-center justify-between text-xs">
                                    <span class="text-slate-400">Packet Loss</span>
                                    <span class="text-emerald-500 font-semibold">0.01%</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>

</html>