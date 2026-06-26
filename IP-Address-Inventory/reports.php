<?php
require_once 'config/config.php';
require_once 'includes/auth_check.php';

// Get date filters
$dateFrom = $_GET['date_from'] ?? date('Y-m-01'); // First day of current month
$dateTo = $_GET['date_to'] ?? date('Y-m-d'); // Today

// IP Utilization Report
$utilizationStmt = $pdo->query("
    SELECT 
        COUNT(CASE WHEN status = 'active' THEN 1 END) as active,
        COUNT(CASE WHEN status = 'reserved' THEN 1 END) as reserved,
        COUNT(CASE WHEN status = 'static' THEN 1 END) as static,
        COUNT(CASE WHEN status = 'offline' THEN 1 END) as offline,
        COUNT(CASE WHEN status = 'conflict' THEN 1 END) as conflict,
        (SELECT SUM(total_ips) FROM subnets) as total_ips
    FROM ip_inventory
");
$utilization = $utilizationStmt->fetch();
$utilization['available'] = $utilization['total_ips'] - ($utilization['active'] + $utilization['reserved'] + $utilization['static']);

// Status Summary
$statusStmt = $pdo->query("
    SELECT status, COUNT(*) as count 
    FROM ip_inventory 
    GROUP BY status
    ORDER BY count DESC
");
$statusSummary = $statusStmt->fetchAll();

// Subnet Allocation
$subnetStmt = $pdo->query("
    SELECT 
        s.name,
        s.network,
        s.cidr,
        s.total_ips,
        COUNT(i.id) as used_ips,
        (s.total_ips - COUNT(i.id)) as available_ips,
        ROUND((COUNT(i.id) / s.total_ips * 100), 2) as utilization_percent
    FROM subnets s
    LEFT JOIN ip_inventory i ON s.id = i.subnet_id AND i.status IN ('active', 'reserved', 'static')
    GROUP BY s.id
    ORDER BY utilization_percent DESC
");
$subnets = $subnetStmt->fetchAll();

// Recent Activity (last 7 days)
$activityStmt = $pdo->prepare("
    SELECT 
        DATE(created_at) as date,
        COUNT(*) as count
    FROM audit_logs
    WHERE created_at >= NOW() - INTERVAL '7 day'
    GROUP BY DATE(created_at)
    ORDER BY date ASC
");
$activityStmt->execute();
$activity = $activityStmt->fetchAll();

// Top Active Users
$topUsersStmt = $pdo->prepare("
    SELECT 
        username,
        COUNT(*) as action_count
    FROM audit_logs
    WHERE created_at >= ? AND created_at <= ?
    GROUP BY username
    ORDER BY action_count DESC
    LIMIT 5
");
$topUsersStmt->execute([$dateFrom, $dateTo]);
$topUsers = $topUsersStmt->fetchAll();

// Device Types Count
$deviceTypesStmt = $pdo->query("
    SELECT 
        device_type,
        COUNT(*) as count
    FROM ip_inventory
    WHERE device_type IS NOT NULL AND device_type != ''
    GROUP BY device_type
    ORDER BY count DESC
    LIMIT 10
");
$deviceTypes = $deviceTypesStmt->fetchAll();
?>
<!DOCTYPE html>
<html class="dark" lang="en">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>Reports - IP Manager</title>
    <link
        href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=Noto+Sans:wght@400;500;600;700&display=swap"
        rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap"
        rel="stylesheet" />
    <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "#137fec",
                        "background-dark": "#111418",
                        "surface-dark": "#283039",
                    },
                    fontFamily: {
                        "display": ["Space Grotesk", "sans-serif"],
                        "body": ["Noto Sans", "sans-serif"]
                    },
                },
            },
        }
    </script>
    <style>
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }

        .icon-fill {
            font-variation-settings: 'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
    </style>
</head>

<body class="bg-background-dark text-white font-display overflow-hidden">
    <div class="relative flex h-screen w-full bg-background-dark overflow-hidden">
        <!-- Sidebar -->
        <div class="hidden lg:flex w-[280px] flex-col border-r border-[#283039] bg-background-dark shrink-0">
            <div class="flex h-full flex-col justify-between p-4">
                <div class="flex flex-col gap-4">
                    <div class="flex gap-3 items-center mb-6">
                        <div class="bg-primary/20 flex items-center justify-center rounded-full size-10">
                            <span class="material-symbols-outlined text-primary">hub</span>
                        </div>
                        <div class="flex flex-col">
                            <h1 class="text-white text-base font-medium leading-normal">IP Manager</h1>
                            <p class="text-[#9dabb9] text-sm font-normal leading-normal">
                                <?php echo htmlspecialchars($_SESSION['full_name']); ?>
                            </p>
                        </div>
                    </div>
                    <div class="flex flex-col gap-2">
                        <a href="index.php"
                            class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-[#283039] transition-colors">
                            <span class="material-symbols-outlined text-[#9dabb9]">dashboard</span>
                            <p class="text-[#9dabb9] text-sm font-medium leading-normal">Overview</p>
                        </a>
                        <a href="subnets.php"
                            class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-[#283039] transition-colors">
                            <span class="material-symbols-outlined text-[#9dabb9]">hub</span>
                            <p class="text-[#9dabb9] text-sm font-medium leading-normal">Subnets</p>
                        </a>
                        <a href="devices.php"
                            class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-[#283039] transition-colors">
                            <span class="material-symbols-outlined text-[#9dabb9]">developer_board</span>
                            <p class="text-[#9dabb9] text-sm font-medium leading-normal">Devices</p>
                        </a>
                        <a href="scanner.php"
                            class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-[#283039] transition-colors">
                            <span class="material-symbols-outlined text-[#9dabb9]">radar</span>
                            <p class="text-[#9dabb9] text-sm font-medium leading-normal">Scanner</p>
                        </a>
                        <a href="reports.php"
                            class="flex items-center gap-3 px-3 py-2 rounded-lg bg-primary/20 border-l-4 border-primary">
                            <span class="material-symbols-outlined text-primary icon-fill">description</span>
                            <p class="text-white text-sm font-medium leading-normal">Reports</p>
                        </a>
                        <a href="logs.php"
                            class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-[#283039] transition-colors">
                            <span class="material-symbols-outlined text-[#9dabb9]">history</span>
                            <p class="text-[#9dabb9] text-sm font-medium leading-normal">Audit Logs</p>
                        </a>
                    </div>
                </div>
                <div class="flex flex-col gap-2">
                    <?php if (isAdmin()): ?>
                        <a href="settings.php"
                            class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-[#283039] transition-colors">
                            <span class="material-symbols-outlined text-[#9dabb9]">settings</span>
                            <p class="text-[#9dabb9] text-sm font-medium leading-normal">Settings</p>
                        </a>
                    <?php endif; ?>
                    <a href="logout.php"
                        class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-[#283039] transition-colors">
                        <span class="material-symbols-outlined text-[#9dabb9]">logout</span>
                        <p class="text-[#9dabb9] text-sm font-medium leading-normal">Logout</p>
                    </a>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col h-full overflow-y-auto">
            <div class="w-full max-w-[1440px] mx-auto p-6 md:p-8 lg:p-10 flex flex-col gap-8">
                <!-- Header -->
                <div class="flex flex-col gap-4">
                    <div class="flex flex-wrap gap-2 items-center">
                        <a class="text-[#9dabb9] text-sm font-medium hover:text-primary transition-colors"
                            href="index.php">Dashboard</a>
                        <span class="text-[#9dabb9] text-sm font-medium">/</span>
                        <span class="text-white text-sm font-medium">Reports</span>
                    </div>
                    <div class="flex flex-wrap justify-between items-end gap-4">
                        <div class="flex flex-col gap-2">
                            <h2 class="text-white tracking-tight text-[32px] font-bold leading-tight">Network Reports
                            </h2>
                            <p class="text-[#9dabb9] text-sm font-normal">Comprehensive analytics and insights.</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <a href="export/csv.php?type=full_report"
                                class="flex items-center gap-2 bg-primary hover:bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-lg shadow-primary/20">
                                <span class="material-symbols-outlined text-[20px]">file_upload</span>
                                Export Full Report
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Date Filter -->
                <div class="bg-surface-dark rounded-xl p-6 border border-white/5">
                    <form method="GET" class="flex flex-col md:flex-row gap-4 items-end">
                        <div class="flex flex-col gap-2 flex-1">
                            <label class="text-[#9dabb9] text-xs font-medium uppercase tracking-wider">Date From</label>
                            <input name="date_from" value="<?php echo htmlspecialchars($dateFrom); ?>"
                                class="w-full bg-[#111418] border border-[#3e4a56] text-white text-sm rounded-lg focus:ring-2 focus:ring-primary p-2.5"
                                type="date" />
                        </div>
                        <div class="flex flex-col gap-2 flex-1">
                            <label class="text-[#9dabb9] text-xs font-medium uppercase tracking-wider">Date To</label>
                            <input name="date_to" value="<?php echo htmlspecialchars($dateTo); ?>"
                                class="w-full bg-[#111418] border border-[#3e4a56] text-white text-sm rounded-lg focus:ring-2 focus:ring-primary p-2.5"
                                type="date" />
                        </div>
                        <button type="submit"
                            class="px-6 py-2.5 bg-primary hover:bg-blue-600 text-white rounded-lg text-sm transition-colors">Update
                            Report</button>
                    </form>
                </div>

                <!-- IP Utilization Chart -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div class="bg-surface-dark rounded-xl p-6 border border-white/5">
                        <h3 class="text-white text-lg font-bold mb-4">IP Utilization</h3>
                        <canvas id="utilizationChart"></canvas>
                    </div>

                    <div class="bg-surface-dark rounded-xl p-6 border border-white/5">
                        <h3 class="text-white text-lg font-bold mb-4">Device Type Distribution</h3>
                        <div class="space-y-3">
                            <?php foreach ($deviceTypes as $type):
                                $percentage = ($type['count'] / array_sum(array_column($deviceTypes, 'count'))) * 100;
                                ?>
                                <div>
                                    <div class="flex justify-between text-sm mb-1">
                                        <span class="text-[#d0d6dc]">
                                            <?php echo htmlspecialchars($type['device_type'] ?: 'Unknown'); ?>
                                        </span>
                                        <span class="text-[#9dabb9]">
                                            <?php echo $type['count']; ?> (
                                            <?php echo round($percentage, 1); ?>%)
                                        </span>
                                    </div>
                                    <div class="w-full bg-[#111418] rounded-full h-2">
                                        <div class="bg-primary h-2 rounded-full" style="width: <?php echo $percentage; ?>%">
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <?php if (empty($deviceTypes)): ?>
                                <p class="text-[#9dabb9] text-sm text-center py-4">No device type data available</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Subnet Allocation Table -->
                <div class="bg-surface-dark rounded-xl border border-white/5 overflow-hidden">
                    <div class="flex items-center justify-between p-4 border-b border-[#3e4a56]">
                        <h3 class="text-white text-base font-bold">Subnet Allocation</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-[#1e2329] border-b border-[#3e4a56]">
                                    <th class="p-4 text-xs font-semibold text-[#9dabb9] uppercase tracking-wider">Subnet
                                        Name</th>
                                    <th class="p-4 text-xs font-semibold text-[#9dabb9] uppercase tracking-wider">
                                        Network</th>
                                    <th class="p-4 text-xs font-semibold text-[#9dabb9] uppercase tracking-wider">Total
                                        IPs</th>
                                    <th class="p-4 text-xs font-semibold text-[#9dabb9] uppercase tracking-wider">Used
                                    </th>
                                    <th class="p-4 text-xs font-semibold text-[#9dabb9] uppercase tracking-wider">
                                        Available</th>
                                    <th class="p-4 text-xs font-semibold text-[#9dabb9] uppercase tracking-wider">
                                        Utilization</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#3e4a56]">
                                <?php foreach ($subnets as $subnet): ?>
                                    <tr class="hover:bg-[#323c47] transition-colors">
                                        <td class="p-4 text-sm text-white font-medium">
                                            <?php echo htmlspecialchars($subnet['name']); ?>
                                        </td>
                                        <td class="p-4 text-sm text-[#d0d6dc] font-mono">
                                            <?php echo htmlspecialchars($subnet['cidr']); ?>
                                        </td>
                                        <td class="p-4 text-sm text-[#9dabb9]">
                                            <?php echo number_format($subnet['total_ips']); ?>
                                        </td>
                                        <td class="p-4 text-sm text-emerald-500 font-medium">
                                            <?php echo number_format($subnet['used_ips']); ?>
                                        </td>
                                        <td class="p-4 text-sm text-blue-400">
                                            <?php echo number_format($subnet['available_ips']); ?>
                                        </td>
                                        <td class="p-4">
                                            <div class="flex items-center gap-2">
                                                <div class="flex-1 bg-[#111418] rounded-full h-2 max-w-[100px]">
                                                    <div class="<?php echo $subnet['utilization_percent'] > 80 ? 'bg-red-500' : ($subnet['utilization_percent'] > 60 ? 'bg-yellow-500' : 'bg-emerald-500'); ?> h-2 rounded-full"
                                                        style="width: <?php echo min($subnet['utilization_percent'], 100); ?>%">
                                                    </div>
                                                </div>
                                                <span class="text-sm text-white font-medium">
                                                    <?php echo round($subnet['utilization_percent'], 1); ?>%
                                                </span>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Status Summary & Top Users -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div class="bg-surface-dark rounded-xl p-6 border border-white/5">
                        <h3 class="text-white text-lg font-bold mb-4">Status Summary</h3>
                        <div class="space-y-3">
                            <?php
                            $statusColors = [
                                'active' => 'emerald',
                                'reserved' => 'blue',
                                'static' => 'purple',
                                'offline' => 'gray',
                                'conflict' => 'red'
                            ];
                            foreach ($statusSummary as $status):
                                $color = $statusColors[$status['status']] ?? 'gray';
                                ?>
                                <div class="flex items-center justify-between p-3 bg-[#111418] rounded-lg">
                                    <div class="flex items-center gap-3">
                                        <div class="w-3 h-3 rounded-full bg-<?php echo $color; ?>-500"></div>
                                        <span class="text-[#d0d6dc] font-medium">
                                            <?php echo ucfirst($status['status']); ?>
                                        </span>
                                    </div>
                                    <span class="text-white font-bold">
                                        <?php echo number_format($status['count']); ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="bg-surface-dark rounded-xl p-6 border border-white/5">
                        <h3 class="text-white text-lg font-bold mb-4">Top Active Users</h3>
                        <div class="space-y-3">
                            <?php foreach ($topUsers as $user): ?>
                                <div class="flex items-center justify-between p-3 bg-[#111418] rounded-lg">
                                    <div class="flex items-center gap-3">
                                        <span class="material-symbols-outlined text-primary">person</span>
                                        <span class="text-[#d0d6dc] font-medium">
                                            <?php echo htmlspecialchars($user['username']); ?>
                                        </span>
                                    </div>
                                    <span class="text-[#9dabb9] text-sm">
                                        <?php echo number_format($user['action_count']); ?> actions
                                    </span>
                                </div>
                            <?php endforeach; ?>
                            <?php if (empty($topUsers)): ?>
                                <p class="text-[#9dabb9] text-sm text-center py-4">No user activity in selected period</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // IP Utilization Chart
        const ctx = document.getElementById('utilizationChart').getContext('2d');
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Active', 'Reserved', 'Static', 'Offline', 'Available', 'Conflict'],
                datasets: [{
                    data: [
                        <?php echo $utilization['active']; ?>,
                        <?php echo $utilization['reserved']; ?>,
                        <?php echo $utilization['static']; ?>,
                        <?php echo $utilization['offline']; ?>,
                        <?php echo $utilization['available']; ?>,
                        <?php echo $utilization['conflict']; ?>
                    ],
                    backgroundColor: [
                        '#10b981', // emerald-500
                        '#3b82f6', // blue-500
                        '#a855f7', // purple-500
                        '#6b7280', // gray-500
                        '#60a5fa', // blue-400
                        '#ef4444'  // red-500
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: '#9dabb9',
                            padding: 15,
                            font: {
                                size: 12
                            }
                        }
                    }
                }
            }
        });
    </script>
</body>

</html>