<?php
require_once 'config/config.php';
require_once 'includes/auth_check.php';

// Get dashboard statistics
$stats = [];

// Total IPs
$stmt = $pdo->query("SELECT SUM(total_ips) as total FROM subnets");
$stats['total_ips'] = $stmt->fetch()['total'] ?? 0;

// Active IPs
$stmt = $pdo->query("SELECT COUNT(*) as count FROM ip_inventory WHERE status IN ('active', 'reserved', 'static')");
$stats['active_ips'] = $stmt->fetch()['count'] ?? 0;

// Available IPs
$stats['available_ips'] = $stats['total_ips'] - $stats['active_ips'];

// Calculate active percentage
$stats['active_percentage'] = $stats['total_ips'] > 0 ? round(($stats['active_ips'] / $stats['total_ips']) * 100) : 0;

// Conflict count
$stmt = $pdo->query("SELECT COUNT(*) as count FROM ip_inventory WHERE status = 'conflict'");
$stats['conflicts'] = $stmt->fetch()['count'] ?? 0;

// Recent activity (last 24 hours)
$stmt = $pdo->query("
    SELECT COUNT(*) as count FROM ip_inventory 
    WHERE last_seen >= NOW() - INTERVAL '24 hour' AND status = 'active'
");
$recentActive = $stmt->fetch()['count'] ?? 0;

// Get inventory data with pagination
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$perPage = 50;
$offset = ($page - 1) * $perPage;

$search = $_GET['search'] ?? '';
$statusFilter = $_GET['status'] ?? '';

$whereClause = "WHERE 1=1";
$params = [];

if (!empty($search)) {
    $whereClause .= " AND (ip_address ILIKE ? OR hostname ILIKE ? OR mac_address ILIKE ? OR control_number ILIKE ? OR department ILIKE ? OR om_name ILIKE ? OR device_type ILIKE ? OR remarks ILIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if (!empty($statusFilter)) {
    $whereClause .= " AND status = ?";
    $params[] = $statusFilter;
}

// Get total count
$countStmt = $pdo->prepare("SELECT COUNT(*) as total FROM ip_inventory $whereClause");
$countStmt->execute($params);
$totalItems = $countStmt->fetch()['total'];

// Get inventory data
$stmt = $pdo->prepare("
    SELECT * FROM ip_inventory 
    $whereClause
    ORDER BY INET_ATON(ip_address) ASC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$inventory = $stmt->fetchAll();

$totalPages = ceil($totalItems / $perPage);
?>
<!DOCTYPE html>
<html class="dark" lang="en">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>Dashboard - IP Manager</title>
    <link
        href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=Noto+Sans:wght@400;500;600;700&display=swap"
        rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap"
        rel="stylesheet" />
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <script>
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "#137fec",
                        "background-light": "#f6f7f8",
                        "background-dark": "#111418",
                        "surface-dark": "#283039",
                    },
                    fontFamily: {
                        "display": ["Space Grotesk", "sans-serif"],
                        "body": ["Noto Sans", "sans-serif"]
                    },
                    borderRadius: { "DEFAULT": "0.25rem", "lg": "0.5rem", "xl": "0.75rem", "full": "9999px" },
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

        @keyframes pulse {

            0%,
            100% {
                opacity: 1;
            }

            50% {
                opacity: 0.5;
            }
        }

        .animate-pulse {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
    </style>
</head>

<body class="bg-background-light dark:bg-background-dark text-[#111418] dark:text-white font-display overflow-hidden">
    <div class="relative flex h-screen w-full bg-background-light dark:bg-background-dark overflow-hidden">
        <!-- Sidebar -->
        <div
            class="hidden lg:flex w-[280px] flex-col border-r border-[#283039] bg-background-light dark:bg-background-dark shrink-0">
            <div class="flex h-full flex-col justify-between p-4">
                <div class="flex flex-col gap-4">
                    <!-- Profile / Brand -->
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
                    <!-- Navigation -->
                    <div class="flex flex-col gap-2">
                        <a href="index.php"
                            class="flex items-center gap-3 px-3 py-2 rounded-lg bg-primary/20 border-l-4 border-primary">
                            <span class="material-symbols-outlined text-primary icon-fill">dashboard</span>
                            <p class="text-white text-sm font-medium leading-normal">Overview</p>
                        </a>
                        <a href="subnets.php"
                            class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-[#283039] transition-colors cursor-pointer group">
                            <span class="material-symbols-outlined text-[#9dabb9] group-hover:text-white">hub</span>
                            <p class="text-[#9dabb9] group-hover:text-white text-sm font-medium leading-normal">Subnets
                            </p>
                        </a>
                        <a href="devices.php"
                            class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-[#283039] transition-colors cursor-pointer group">
                            <span
                                class="material-symbols-outlined text-[#9dabb9] group-hover:text-white">developer_board</span>
                            <p class="text-[#9dabb9] group-hover:text-white text-sm font-medium leading-normal">Devices
                            </p>
                        </a>
                        <a href="scanner.php"
                            class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-[#283039] transition-colors cursor-pointer group">
                            <span class="material-symbols-outlined text-[#9dabb9] group-hover:text-white">radar</span>
                            <p class="text-[#9dabb9] group-hover:text-white text-sm font-medium leading-normal">Scanner
                            </p>
                        </a>
                        <a href="reports.php"
                            class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-[#283039] transition-colors cursor-pointer group">
                            <span
                                class="material-symbols-outlined text-[#9dabb9] group-hover:text-white">description</span>
                            <p class="text-[#9dabb9] group-hover:text-white text-sm font-medium leading-normal">Reports
                            </p>
                        </a>
                        <a href="logs.php"
                            class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-[#283039] transition-colors cursor-pointer group">
                            <span class="material-symbols-outlined text-[#9dabb9] group-hover:text-white">history</span>
                            <p class="text-[#9dabb9] group-hover:text-white text-sm font-medium leading-normal">Audit
                                Logs</p>
                        </a>
                    </div>
                </div>
                <div class="flex flex-col gap-2">
                    <?php if (isAdmin()): ?>
                        <a href="settings.php"
                            class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-[#283039] transition-colors cursor-pointer group">
                            <span class="material-symbols-outlined text-[#9dabb9] group-hover:text-white">settings</span>
                            <p class="text-[#9dabb9] group-hover:text-white text-sm font-medium leading-normal">Settings</p>
                        </a>
                    <?php endif; ?>
                    <a href="logout.php"
                        class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-[#283039] transition-colors cursor-pointer group">
                        <span class="material-symbols-outlined text-[#9dabb9] group-hover:text-white">logout</span>
                        <p class="text-[#9dabb9] group-hover:text-white text-sm font-medium leading-normal">Logout</p>
                    </a>
                </div>
            </div>
        </div>
        <!-- Main Content -->
        <div class="flex-1 flex flex-col h-full overflow-y-auto">
            <div class="w-full max-w-[1440px] mx-auto p-6 md:p-8 lg:p-10 flex flex-col gap-8">
                <!-- Breadcrumbs & Heading -->
                <div class="flex flex-col gap-4">
                    <div class="flex flex-wrap gap-2 items-center">
                        <a class="text-[#9dabb9] text-sm font-medium leading-normal hover:text-primary transition-colors"
                            href="#">Network Overview</a>
                        <span class="text-[#9dabb9] text-sm font-medium leading-normal">/</span>
                        <span class="text-white text-sm font-medium leading-normal">Dashboard</span>
                    </div>
                    <div class="flex flex-wrap justify-between items-end gap-4">
                        <div class="flex flex-col gap-2">
                            <h2 class="text-white tracking-tight text-[32px] font-bold leading-tight">Dashboard Overview
                            </h2>
                            <p class="text-[#9dabb9] text-sm font-normal leading-normal">Real-time network status and IP
                                allocation metrics.</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <a href="export/csv.php?type=inventory"
                                class="flex items-center gap-2 bg-[#283039] hover:bg-[#323c47] text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                                <span class="material-symbols-outlined text-[20px]">file_upload</span>
                                Export
                            </a>
                            <button onclick="openAddDeviceModal()"
                                class="flex items-center gap-2 bg-primary hover:bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-lg shadow-primary/20">
                                <span class="material-symbols-outlined text-[20px]">add</span>
                                Add Device
                            </button>
                        </div>
                    </div>
                </div>
                <!-- Stats Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="flex flex-col gap-2 rounded-xl p-6 bg-surface-dark border border-white/5 shadow-sm">
                        <div class="flex justify-between items-start">
                            <p class="text-[#9dabb9] text-sm font-medium leading-normal">Total IPs</p>
                            <span
                                class="material-symbols-outlined text-primary bg-primary/10 p-2 rounded-lg text-[20px]">dns</span>
                        </div>
                        <p class="text-white tracking-tight text-3xl font-bold leading-tight mt-2">
                            <?php echo number_format($stats['total_ips']); ?>
                        </p>
                        <p class="text-[#9dabb9] text-xs mt-1">Total addressable space</p>
                    </div>
                    <div class="flex flex-col gap-2 rounded-xl p-6 bg-surface-dark border border-white/5 shadow-sm">
                        <div class="flex justify-between items-start">
                            <p class="text-[#9dabb9] text-sm font-medium leading-normal">Active IPs</p>
                            <span
                                class="material-symbols-outlined text-emerald-500 bg-emerald-500/10 p-2 rounded-lg text-[20px]">check_circle</span>
                        </div>
                        <p class="text-white tracking-tight text-3xl font-bold leading-tight mt-2">
                            <?php echo number_format($stats['active_ips']); ?>
                            <span
                                class="text-lg font-normal text-[#9dabb9]">(<?php echo $stats['active_percentage']; ?>%)</span>
                        </p>
                        <p class="text-emerald-500 text-xs mt-1 font-medium">+<?php echo $recentActive; ?> since last
                            day</p>
                    </div>
                    <div class="flex flex-col gap-2 rounded-xl p-6 bg-surface-dark border border-white/5 shadow-sm">
                        <div class="flex justify-between items-start">
                            <p class="text-[#9dabb9] text-sm font-medium leading-normal">Available IPs</p>
                            <span
                                class="material-symbols-outlined text-blue-400 bg-blue-400/10 p-2 rounded-lg text-[20px]">timelapse</span>
                        </div>
                        <p class="text-white tracking-tight text-3xl font-bold leading-tight mt-2">
                            <?php echo number_format($stats['available_ips']); ?>
                        </p>
                        <p class="text-[#9dabb9] text-xs mt-1">Ready for allocation</p>
                    </div>
                    <div
                        class="flex flex-col gap-2 rounded-xl p-6 bg-surface-dark border border-<?php echo $stats['conflicts'] > 0 ? 'red-500/30' : 'white/5'; ?> shadow-<?php echo $stats['conflicts'] > 0 ? '[0_0_15px_-3px_rgba(239,68,68,0.2)]' : 'sm'; ?> relative overflow-hidden">
                        <?php if ($stats['conflicts'] > 0): ?>
                            <div class="absolute top-0 right-0 w-16 h-16 bg-red-500/10 rounded-bl-full -mr-2 -mt-2"></div>
                        <?php endif; ?>
                        <div class="flex justify-between items-start z-10">
                            <p
                                class="<?php echo $stats['conflicts'] > 0 ? 'text-red-400' : 'text-[#9dabb9]'; ?> text-sm font-medium leading-normal">
                                Duplicate Alerts</p>
                            <span
                                class="material-symbols-outlined <?php echo $stats['conflicts'] > 0 ? 'text-red-500 bg-red-500/10' : 'text-emerald-500 bg-emerald-500/10'; ?> p-2 rounded-lg text-[20px]"><?php echo $stats['conflicts'] > 0 ? 'warning' : 'check_circle'; ?></span>
                        </div>
                        <p class="text-white tracking-tight text-3xl font-bold leading-tight mt-2 z-10">
                            <?php echo $stats['conflicts']; ?>
                        </p>
                        <p
                            class="<?php echo $stats['conflicts'] > 0 ? 'text-red-400' : 'text-emerald-500'; ?> text-xs mt-1 font-medium z-10">
                            <?php echo $stats['conflicts'] > 0 ? 'Conflicts Detected' : 'No Conflicts'; ?>
                        </p>
                    </div>
                </div>
                <!-- Advanced Scanner Section -->
                <div class="flex flex-col gap-4 rounded-xl bg-surface-dark p-6 border border-white/5">
                    <h3 class="text-white text-lg font-bold leading-tight tracking-[-0.015em]">Advanced IP Scanner</h3>
                    <form action="scanner.php" method="GET" class="flex flex-col lg:flex-row gap-4 items-end">
                        <div class="flex-1 w-full grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="flex flex-col gap-2">
                                <label class="text-[#9dabb9] text-xs font-medium uppercase tracking-wider">Start IP /
                                    CIDR</label>
                                <div class="relative">
                                    <span
                                        class="absolute left-3 top-1/2 -translate-y-1/2 material-symbols-outlined text-[#9dabb9] text-[20px]">router</span>
                                    <input name="start_ip"
                                        class="w-full bg-[#111418] border border-[#3e4a56] text-white text-sm rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent block pl-10 p-2.5 placeholder-[#5b6b7a]"
                                        placeholder="192.168.1.0/24" type="text" />
                                </div>
                            </div>
                            <div class="flex flex-col gap-2">
                                <label class="text-[#9dabb9] text-xs font-medium uppercase tracking-wider">End IP
                                    (Optional)</label>
                                <div class="relative">
                                    <span
                                        class="absolute left-3 top-1/2 -translate-y-1/2 material-symbols-outlined text-[#9dabb9] text-[20px]">router</span>
                                    <input name="end_ip"
                                        class="w-full bg-[#111418] border border-[#3e4a56] text-white text-sm rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent block pl-10 p-2.5 placeholder-[#5b6b7a]"
                                        placeholder="192.168.1.255" type="text" />
                                </div>
                            </div>
                            <div class="flex flex-col gap-2">
                                <label class="text-[#9dabb9] text-xs font-medium uppercase tracking-wider">Scan
                                    Options</label>
                                <div class="relative">
                                    <span
                                        class="absolute left-3 top-1/2 -translate-y-1/2 material-symbols-outlined text-[#9dabb9] text-[20px]">tune</span>
                                    <select name="scan_type"
                                        class="w-full bg-[#111418] border border-[#3e4a56] text-white text-sm rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent block pl-10 p-2.5 appearance-none">
                                        <option value="fast">Fast Scan (Ping)</option>
                                        <option value="deep">Deep Scan (All Ports)</option>
                                        <option value="custom">Custom Ports</option>
                                    </select>
                                    <span
                                        class="absolute right-3 top-1/2 -translate-y-1/2 material-symbols-outlined text-[#9dabb9] text-[20px] pointer-events-none">expand_more</span>
                                </div>
                            </div>
                        </div>
                        <button type="submit"
                            class="w-full lg:w-auto flex items-center justify-center gap-2 bg-primary hover:bg-blue-600 text-white px-6 py-2.5 rounded-lg text-sm font-medium transition-colors h-[42px] whitespace-nowrap shadow-lg shadow-primary/25">
                            <span class="material-symbols-outlined text-[20px]">play_arrow</span>
                            Run Scan
                        </button>
                    </form>
                </div>
                <!-- Inventory Data Table -->
                <div class="flex flex-col bg-surface-dark rounded-xl border border-white/5 overflow-hidden">
                    <!-- Table Toolbar -->
                    <div class="flex flex-wrap items-center justify-between p-4 gap-4 border-b border-[#3e4a56]">
                        <div class="flex items-center gap-2">
                            <h3 class="text-white text-base font-bold mr-2">Inventory Results</h3>
                            <span
                                class="bg-[#111418] text-[#9dabb9] text-xs px-2 py-0.5 rounded-full border border-[#3e4a56]"><?php echo number_format($totalItems); ?>
                                Items</span>
                        </div>
                        <div class="flex items-center gap-3 w-full md:w-auto">
                            <form method="GET" class="relative w-full md:w-64">
                                <span
                                    class="absolute left-3 top-1/2 -translate-y-1/2 material-symbols-outlined text-[#9dabb9] text-[18px]">search</span>
                                <input name="search" value="<?php echo htmlspecialchars($search); ?>"
                                    class="w-full bg-[#111418] border border-[#3e4a56] text-white text-sm rounded-lg focus:ring-1 focus:ring-primary focus:border-primary block pl-9 p-2 placeholder-[#5b6b7a]"
                                    placeholder="Search IP, Hostname, MAC..." type="text" />
                            </form>
                        </div>
                    </div>
                    <!-- Table -->
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-[#1e2329] border-b border-[#3e4a56]">
                                    <th class="p-4 text-xs font-semibold text-[#9dabb9] uppercase tracking-wider">IP
                                        Address</th>
                                    <th class="p-4 text-xs font-semibold text-[#9dabb9] uppercase tracking-wider">Status
                                    </th>
                                    <th class="p-4 text-xs font-semibold text-[#9dabb9] uppercase tracking-wider">
                                        Hostname</th>
                                    <th class="p-4 text-xs font-semibold text-[#9dabb9] uppercase tracking-wider">MAC
                                        Address</th>
                                    <th class="p-4 text-xs font-semibold text-[#9dabb9] uppercase tracking-wider">Last
                                        Seen</th>
                                    <th
                                        class="p-4 text-xs font-semibold text-[#9dabb9] uppercase tracking-wider text-right">
                                        Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#3e4a56]">
                                <?php foreach ($inventory as $device):
                                    $statusColors = [
                                        'active' => ['bg' => 'bg-emerald-500/10', 'border' => 'border-emerald-500/20', 'text' => 'text-emerald-500', 'dot' => 'bg-emerald-500'],
                                        'reserved' => ['bg' => 'bg-blue-500/10', 'border' => 'border-blue-500/20', 'text' => 'text-blue-500', 'dot' => 'bg-blue-500'],
                                        'conflict' => ['bg' => 'bg-red-500/10', 'border' => 'border-red-500/20', 'text' => 'text-red-500', 'dot' => 'bg-red-500 animate-pulse'],
                                        'static' => ['bg' => 'bg-purple-500/10', 'border' => 'border-purple-500/20', 'text' => 'text-purple-500', 'dot' => 'bg-purple-500'],
                                        'offline' => ['bg' => 'bg-[#3e4a56]/30', 'border' => 'border-[#3e4a56]', 'text' => 'text-[#9dabb9]', 'dot' => 'bg-[#9dabb9]'],
                                    ];
                                    $color = $statusColors[$device['status']] ?? $statusColors['offline'];
                                    ?>
                                    <tr
                                        class="group hover:bg-[#323c47] transition-colors <?php echo $device['status'] === 'conflict' ? 'bg-red-500/5' : ''; ?>">
                                        <td class="p-4 text-sm font-medium text-white font-mono">
                                            <?php if ($device['status'] === 'conflict'): ?>
                                                <span
                                                    class="material-symbols-outlined text-red-500 text-[18px] inline-block mr-1 align-middle"
                                                    title="IP Conflict Detected">warning</span>
                                            <?php endif; ?>
                                            <?php echo htmlspecialchars($device['ip_address']); ?>
                                        </td>
                                        <td class="p-4">
                                            <div
                                                class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full <?php echo $color['bg']; ?> border <?php echo $color['border']; ?>">
                                                <div class="w-1.5 h-1.5 rounded-full <?php echo $color['dot']; ?>"></div>
                                                <span
                                                    class="text-xs font-medium <?php echo $color['text']; ?>"><?php echo ucfirst($device['status']); ?></span>
                                            </div>
                                        </td>
                                        <td class="p-4 text-sm text-[#d0d6dc]">
                                            <?php echo htmlspecialchars($device['hostname'] ?: 'N/A'); ?>
                                        </td>
                                        <td class="p-4 text-sm text-[#9dabb9] font-mono">
                                            <?php echo htmlspecialchars($device['mac_address'] ?: 'N/A'); ?>
                                        </td>
                                        <td class="p-4 text-sm text-[#9dabb9]">
                                            <?php echo $device['last_seen'] ? timeAgo($device['last_seen']) : 'Never'; ?>
                                        </td>
                                        <td class="p-4 text-right">
                                            <button onclick="editDevice(<?php echo $device['id']; ?>)"
                                                class="text-[#9dabb9] hover:text-white transition-colors">
                                                <span class="material-symbols-outlined text-[20px]">more_vert</span>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>

                                <?php if (empty($inventory)): ?>
                                    <tr>
                                        <td colspan="6" class="p-8 text-center text-[#9dabb9]">
                                            <span class="material-symbols-outlined text-4xl mb-2 block">device_hub</span>
                                            No devices found. Add your first device to get started.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <!-- Pagination -->
                    <div class="flex items-center justify-between p-4 border-t border-[#3e4a56]">
                        <p class="text-xs text-[#9dabb9]">Showing
                            <?php echo min($offset + 1, $totalItems); ?>-<?php echo min($offset + $perPage, $totalItems); ?>
                            of <?php echo number_format($totalItems); ?> items
                        </p>
                        <div class="flex gap-2">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>"
                                    class="px-3 py-1 rounded border border-[#3e4a56] text-xs text-[#9dabb9] hover:bg-[#3e4a56] hover:text-white transition-colors">Previous</a>
                            <?php endif; ?>

                            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>"
                                    class="px-3 py-1 rounded border border-[#3e4a56] text-xs <?php echo $i === $page ? 'text-white bg-primary border-primary' : 'text-[#9dabb9] hover:bg-[#3e4a56] hover:text-white'; ?> transition-colors"><?php echo $i; ?></a>
                            <?php endfor; ?>

                            <?php if ($page < $totalPages): ?>
                                <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>"
                                    class="px-3 py-1 rounded border border-[#3e4a56] text-xs text-[#9dabb9] hover:bg-[#3e4a56] hover:text-white transition-colors">Next</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function openAddDeviceModal() {
            window.location.href = 'devices.php?action=add';
        }

        function editDevice(id) {
            window.location.href = 'devices.php?action=edit&id=' + id;
        }

        // Auto-refresh stats every 30 seconds
        setTimeout(function () {
            location.reload();
        }, 30000);
    </script>
</body>

</html>