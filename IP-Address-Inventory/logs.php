<?php
require_once 'config/config.php';
require_once 'includes/auth_check.php';

// Get filter parameters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 50;
$offset = ($page - 1) * $perPage;

$userFilter = $_GET['user'] ?? '';
$actionFilter = $_GET['action'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

$whereClause = "WHERE 1=1";
$params = [];

if (!empty($userFilter)) {
    $whereClause .= " AND username LIKE ?";
    $params[] = "%$userFilter%";
}

if (!empty($actionFilter)) {
    $whereClause .= " AND action = ?";
    $params[] = $actionFilter;
}

if (!empty($dateFrom)) {
    $whereClause .= " AND DATE(created_at) >= ?";
    $params[] = $dateFrom;
}

if (!empty($dateTo)) {
    $whereClause .= " AND DATE(created_at) <= ?";
    $params[] = $dateTo;
}

// Get total count
$countStmt = $pdo->prepare("SELECT COUNT(*) as total FROM audit_logs $whereClause");
$countStmt->execute($params);
$totalItems = $countStmt->fetch()['total'];

// Get audit logs
$stmt = $pdo->prepare("
    SELECT * FROM audit_logs 
    $whereClause
    ORDER BY created_at DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$logs = $stmt->fetchAll();

$totalPages = ceil($totalItems / $perPage);

// Get unique actions for filter
$actionsStmt = $pdo->query("SELECT DISTINCT action FROM audit_logs ORDER BY action");
$actions = $actionsStmt->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html class="dark" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Audit Logs - IP Manager</title>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=Noto+Sans:wght@400;500;600;700&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
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
                        <p class="text-[#9dabb9] text-sm font-normal leading-normal"><?php echo htmlspecialchars($_SESSION['full_name']); ?></p>
                    </div>
                </div>
                <div class="flex flex-col gap-2">
                    <a href="index.php" class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-[#283039] transition-colors">
                        <span class="material-symbols-outlined text-[#9dabb9]">dashboard</span>
                        <p class="text-[#9dabb9] text-sm font-medium leading-normal">Overview</p>
                    </a>
                    <a href="subnets.php" class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-[#283039] transition-colors">
                        <span class="material-symbols-outlined text-[#9dabb9]">hub</span>
                        <p class="text-[#9dabb9] text-sm font-medium leading-normal">Subnets</p>
                    </a>
                    <a href="devices.php" class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-[#283039] transition-colors">
                        <span class="material-symbols-outlined text-[#9dabb9]">developer_board</span>
                        <p class="text-[#9dabb9] text-sm font-medium leading-normal">Devices</p>
                    </a>
                    <a href="scanner.php" class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-[#283039] transition-colors">
                        <span class="material-symbols-outlined text-[#9dabb9]">radar</span>
                        <p class="text-[#9dabb9] text-sm font-medium leading-normal">Scanner</p>
                    </a>
                    <a href="reports.php" class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-[#283039] transition-colors">
                        <span class="material-symbols-outlined text-[#9dabb9]">description</span>
                        <p class="text-[#9dabb9] text-sm font-medium leading-normal">Reports</p>
                    </a>
                    <a href="logs.php" class="flex items-center gap-3 px-3 py-2 rounded-lg bg-primary/20 border-l-4 border-primary">
                        <span class="material-symbols-outlined text-primary icon-fill">history</span>
                        <p class="text-white text-sm font-medium leading-normal">Audit Logs</p>
                    </a>
                </div>
            </div>
            <div class="flex flex-col gap-2">
                <?php if (isAdmin()): ?>
                <a href="settings.php" class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-[#283039] transition-colors">
                    <span class="material-symbols-outlined text-[#9dabb9]">settings</span>
                    <p class="text-[#9dabb9] text-sm font-medium leading-normal">Settings</p>
                </a>
                <?php endif; ?>
                <a href="logout.php" class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-[#283039] transition-colors">
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
                    <a class="text-[#9dabb9] text-sm font-medium hover:text-primary transition-colors" href="index.php">Dashboard</a>
                    <span class="text-[#9dabb9] text-sm font-medium">/</span>
                    <span class="text-white text-sm font-medium">Audit Logs</span>
                </div>
                <div class="flex flex-wrap justify-between items-end gap-4">
                    <div class="flex flex-col gap-2">
                        <h2 class="text-white tracking-tight text-[32px] font-bold leading-tight">Audit Logs</h2>
                        <p class="text-[#9dabb9] text-sm font-normal">Track all system activities and user actions.</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <a href="export/csv.php?type=logs" class="flex items-center gap-2 bg-[#283039] hover:bg-[#323c47] text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                            <span class="material-symbols-outlined text-[20px]">file_upload</span>
                            Export CSV
                        </a>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="bg-surface-dark rounded-xl p-6 border border-white/5">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div class="flex flex-col gap-2">
                        <label class="text-[#9dabb9] text-xs font-medium uppercase tracking-wider">Username</label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 material-symbols-outlined text-[#9dabb9] text-[20px]">person</span>
                            <input name="user" value="<?php echo htmlspecialchars($userFilter); ?>" class="w-full bg-[#111418] border border-[#3e4a56] text-white text-sm rounded-lg focus:ring-2 focus:ring-primary pl-10 p-2.5" placeholder="Filter by user" type="text"/>
                        </div>
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="text-[#9dabb9] text-xs font-medium uppercase tracking-wider">Action</label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 material-symbols-outlined text-[#9dabb9] text-[20px]">filter_list</span>
                            <select name="action" class="w-full bg-[#111418] border border-[#3e4a56] text-white text-sm rounded-lg focus:ring-2 focus:ring-primary pl-10 p-2.5">
                                <option value="">All Actions</option>
                                <?php foreach ($actions as $act): ?>
                                <option value="<?php echo htmlspecialchars($act); ?>" <?php echo $actionFilter === $act ? 'selected' : ''; ?>><?php echo ucfirst(str_replace('_', ' ', $act)); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <span class="absolute right-3 top-1/2 -translate-y-1/2 material-symbols-outlined text-[#9dabb9] text-[20px] pointer-events-none">expand_more</span>
                        </div>
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="text-[#9dabb9] text-xs font-medium uppercase tracking-wider">Date From</label>
                        <input name="date_from" value="<?php echo htmlspecialchars($dateFrom); ?>" class="w-full bg-[#111418] border border-[#3e4a56] text-white text-sm rounded-lg focus:ring-2 focus:ring-primary p-2.5" type="date"/>
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="text-[#9dabb9] text-xs font-medium uppercase tracking-wider">Date To</label>
                        <input name="date_to" value="<?php echo htmlspecialchars($dateTo); ?>" class="w-full bg-[#111418] border border-[#3e4a56] text-white text-sm rounded-lg focus:ring-2 focus:ring-primary p-2.5" type="date"/>
                    </div>
                    <div class="md:col-span-4 flex gap-3 justify-end">
                        <a href="logs.php" class="px-4 py-2 bg-[#111418] border border-[#3e4a56] text-white rounded-lg text-sm hover:bg-[#1a1f26] transition-colors">Clear</a>
                        <button type="submit" class="px-4 py-2 bg-primary hover:bg-blue-600 text-white rounded-lg text-sm transition-colors">Apply Filters</button>
                    </div>
                </form>
            </div>

            <!-- Logs Table -->
            <div class="flex flex-col bg-surface-dark rounded-xl border border-white/5 overflow-hidden">
                <div class="flex items-center justify-between p-4 border-b border-[#3e4a56]">
                    <div class="flex items-center gap-2">
                        <h3 class="text-white text-base font-bold mr-2">Activity Log</h3>
                        <span class="bg-[#111418] text-[#9dabb9] text-xs px-2 py-0.5 rounded-full border border-[#3e4a56]"><?php echo number_format($totalItems); ?> Entries</span>
                    </div>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-[#1e2329] border-b border-[#3e4a56]">
                                <th class="p-4 text-xs font-semibold text-[#9dabb9] uppercase tracking-wider">Timestamp</th>
                                <th class="p-4 text-xs font-semibold text-[#9dabb9] uppercase tracking-wider">User</th>
                                <th class="p-4 text-xs font-semibold text-[#9dabb9] uppercase tracking-wider">Action</th>
                                <th class="p-4 text-xs font-semibold text-[#9dabb9] uppercase tracking-wider">Description</th>
                                <th class="p-4 text-xs font-semibold text-[#9dabb9] uppercase tracking-wider">IP Address</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#3e4a56]">
                            <?php foreach ($logs as $log): 
                                $actionIcons = [
                                    'login' => ['icon' => 'login', 'color' => 'text-emerald-500'],
                                    'logout' => ['icon' => 'logout', 'color' => 'text-blue-500'],
                                    'create' => ['icon' => 'add_circle', 'color' => 'text-green-500'],
                                    'update' => ['icon' => 'edit', 'color' => 'text-yellow-500'],
                                    'delete' => ['icon' => 'delete', 'color' => 'text-red-500'],
                                    'login_failed' => ['icon' => 'error', 'color' => 'text-red-500'],
                                ];
                                $icon = $actionIcons[$log['action']] ?? ['icon' => 'info', 'color' => 'text-gray-500'];
                            ?>
                            <tr class="hover:bg-[#323c47] transition-colors">
                                <td class="p-4 text-sm text-white font-mono"><?php echo date('M j, Y H:i:s', strtotime($log['created_at'])); ?></td>
                                <td class="p-4 text-sm text-[#d0d6dc]"><?php echo htmlspecialchars($log['username']); ?></td>
                                <td class="p-4">
                                    <div class="flex items-center gap-2">
                                        <span class="material-symbols-outlined <?php echo $icon['color']; ?> text-[18px]"><?php echo $icon['icon']; ?></span>
                                        <span class="text-sm <?php echo $icon['color']; ?> font-medium"><?php echo ucfirst(str_replace('_', ' ', $log['action'])); ?></span>
                                    </div>
                                </td>
                                <td class="p-4 text-sm text-[#9dabb9]"><?php echo htmlspecialchars($log['description'] ?: 'N/A'); ?></td>
                                <td class="p-4 text-sm text-[#9dabb9] font-mono"><?php echo htmlspecialchars($log['ip_address']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                            
                            <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="5" class="p-8 text-center text-[#9dabb9]">
                                    <span class="material-symbols-outlined text-4xl mb-2 block">history</span>
                                    No audit logs found.
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="flex items-center justify-between p-4 border-t border-[#3e4a56]">
                    <p class="text-xs text-[#9dabb9]">Showing <?php echo min($offset + 1, $totalItems); ?>-<?php echo min($offset + $perPage, $totalItems); ?> of <?php echo number_format($totalItems); ?> entries</p>
                    <div class="flex gap-2">
                        <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&user=<?php echo urlencode($userFilter); ?>&action=<?php echo urlencode($actionFilter); ?>&date_from=<?php echo urlencode($dateFrom); ?>&date_to=<?php echo urlencode($dateTo); ?>" class="px-3 py-1 rounded border border-[#3e4a56] text-xs text-[#9dabb9] hover:bg-[#3e4a56] hover:text-white transition-colors">Previous</a>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <a href="?page=<?php echo $i; ?>&user=<?php echo urlencode($userFilter); ?>&action=<?php echo urlencode($actionFilter); ?>&date_from=<?php echo urlencode($dateFrom); ?>&date_to=<?php echo urlencode($dateTo); ?>" class="px-3 py-1 rounded border border-[#3e4a56] text-xs <?php echo $i === $page ? 'text-white bg-primary border-primary' : 'text-[#9dabb9] hover:bg-[#3e4a56] hover:text-white'; ?> transition-colors"><?php echo $i; ?></a>
                        <?php endfor; ?>
                        
                        <?php if ($page < $totalPages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&user=<?php echo urlencode($userFilter); ?>&action=<?php echo urlencode($actionFilter); ?>&date_from=<?php echo urlencode($dateFrom); ?>&date_to=<?php echo urlencode($dateTo); ?>" class="px-3 py-1 rounded border border-[#3e4a56] text-xs text-[#9dabb9] hover:bg-[#3e4a56] hover:text-white transition-colors">Next</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
