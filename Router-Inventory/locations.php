<?php
session_start();
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header('Location: login');
    exit;
}

require_once 'db.php';

// Fetch Location Stats
$stmt = $pdo->query("
    SELECT 
        location, 
        region, 
        COUNT(*) as total_devices,
        SUM(CASE WHEN status = 'Online' THEN 1 ELSE 0 END) as online_count,
        SUM(CASE WHEN status IN ('Offline', 'Critical') THEN 1 ELSE 0 END) as critical_count
    FROM inventory 
    GROUP BY location, region
    ORDER BY region, location
");
$locations = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html class="dark" lang="en">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>Locations - <?php echo htmlspecialchars($portalName ?? "IHOMS Router Portal"); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap"
        rel="stylesheet" />
    <script>
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "#10b981",
                        "background-light": "#f6f6f8",
                        "background-dark": "#101622",
                        "border-dark": "#282e39"
                    },
                    fontFamily: {
                        "display": ["Inter", "sans-serif"]
                    },
                    borderRadius: {
                        "DEFAULT": "0.25rem",
                        "lg": "0.5rem",
                        "xl": "0.75rem",
                        "full": "9999px"
                    },
                },
            },
        }
    </script>
    <style>
        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }

        .custom-scrollbar::-webkit-scrollbar-track {
            background: transparent;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #282e39;
            border-radius: 10px;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: #3f4756;
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
                class="flex items-center gap-3 px-4 py-3 rounded-xl bg-primary/10 text-primary transition-all group">
                <span class="material-symbols-outlined text-[20px]">map</span>
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
                <span class="material-symbols-outlined text-primary">map</span>
                <h2 class="text-sm font-bold text-white">Network Locations</h2>
            </div>

            <div class="flex items-center gap-4">
                <div class="relative group">
                    <span
                        class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-[18px] text-slate-500 group-focus-within:text-primary transition-colors">search</span>
                    <input type="text" placeholder="Search locations..."
                        class="bg-[#1a2130] border border-[#232b3d] text-slate-200 text-xs rounded-xl pl-9 pr-4 py-2 focus:ring-2 focus:ring-primary focus:border-transparent w-64 transition-all">
                </div>
            </div>
        </header>

        <div class="p-8 flex-1 overflow-y-auto custom-scrollbar relative z-10">
            <!-- Page Heading -->
            <div class="mb-8">
                <h2 class="text-2xl font-bold text-white">Location Management</h2>
                <p class="text-sm text-slate-500 mt-1">Geographic distribution and site-specific performance</p>
            </div>

            <!-- Locations Grid/Table -->
            <div class="bg-[#1a2130] border border-[#232b3d] rounded-2xl overflow-hidden shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-[#1a2130] border-b border-[#232b3d]">
                                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase tracking-wider">
                                    Location Site</th>
                                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase tracking-wider">
                                    Active Nodes</th>
                                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase tracking-wider">
                                    Primary IP Range</th>
                                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase tracking-wider">Site
                                    Status</th>
                                <th
                                    class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase tracking-wider text-right">
                                    Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#232b3d]/50">
                            <?php foreach ($locations as $loc): ?>
                                <tr class="hover:bg-white/5 transition-colors group">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <div
                                                class="size-8 rounded-lg bg-primary/10 flex items-center justify-center text-primary border border-primary/20">
                                                <span class="material-symbols-outlined text-[18px]">location_on</span>
                                            </div>
                                            <span
                                                class="text-sm font-bold text-white"><?= htmlspecialchars($loc['location']) ?></span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span
                                            class="text-sm font-medium text-slate-400"><?= number_format($loc['total_devices']) ?>
                                            Routers</span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span
                                            class="text-sm font-mono text-slate-500"><?= htmlspecialchars($loc['region'] ?? '192.168.1.x') ?></span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-2">
                                            <div
                                                class="size-1.5 rounded-full bg-emerald-500 shadow-lg shadow-emerald-500/50">
                                            </div>
                                            <span class="text-xs font-medium text-emerald-500">Connected</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <a href="inventory?location=<?= urlencode($loc['location']) ?>"
                                            class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-[#101622] border border-[#232b3d] text-slate-400 hover:text-white hover:border-primary/50 text-xs font-semibold transition-all">
                                            Site Details
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</body>

</html>