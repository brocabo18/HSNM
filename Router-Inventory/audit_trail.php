<?php
/**
 * Audit Logs View
 * View system change history
 */

session_start();
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header('Location: login');
    exit;
}

require_once 'db.php';

// Fetch system settings (Simulated for this system structure)
$portalName = "IHOMS Router Portal";
$portalSubtitle = "IHOMS";
$primaryColor = "#10b981"; // Updated to Emerald

// Pagination Logic
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1)
    $page = 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Filters
$search = $_GET['search'] ?? '';
$action_filter = $_GET['action_type'] ?? 'All';
$log_level = $_GET['log_level'] ?? 'All';

$where_clauses = ["1=1"];
$params = [];

if ($search) {
    $where_clauses[] = "(l.title ILIKE ? OR l.description ILIKE ? OR u.username ILIKE ? OR l.resource_id::text ILIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if ($action_filter !== 'All') {
    $where_clauses[] = "l.action_type = ?";
    $params[] = $action_filter;
}

if ($log_level !== 'All') {
    $where_clauses[] = "l.log_level = ?";
    $params[] = $log_level;
}

$where_sql = implode(' AND ', $where_clauses);

// Fetch Logs
try {
    $sql = "SELECT l.*, u.name as performer_name, u.username as performer_uname, u.role as performer_role 
            FROM system_logs l 
            LEFT JOIN users u ON l.user_id = u.id 
            WHERE $where_sql 
            ORDER BY l.created_at DESC 
            LIMIT $limit OFFSET $offset";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll();

    // Get Total Count for Pagination
    $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM system_logs l LEFT JOIN users u ON l.user_id = u.id WHERE $where_sql");
    $count_stmt->execute($params);
    $total_items = $count_stmt->fetchColumn();
    $total_pages = ceil($total_items / $limit);
} catch (PDOException $e) {
    if ($e->getCode() == '42S22' || strpos($e->getMessage(), 'Unknown column') !== false) {
        header('Location: migrate_audit_trail');
        exit;
    }
    die("Database error: " . $e->getMessage());
}

function getActionBadge($action)
{
    $action = strtoupper($action);
    switch ($action) {
        case 'CREATE':
        case 'ADD':
            return '<span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-emerald-500/10 text-emerald-500 border border-emerald-500/20">CREATE</span>';
        case 'UPDATE':
        case 'EDIT':
            return '<span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-amber-500/10 text-amber-500 border border-amber-500/20">UPDATE</span>';
        case 'DELETE':
        case 'REMOVE':
            return '<span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-red-500/10 text-red-500 border border-red-500/20">DELETE</span>';
        case 'LOGIN':
            return '<span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-blue-500/10 text-blue-500 border border-blue-500/20">LOGIN</span>';
        case 'ACTION':
        case 'REBOOT':
            return '<span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-purple-500/10 text-purple-400 border border-purple-500/20">' . $action . '</span>';
        default:
            return '<span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-slate-500/10 text-slate-400 border border-slate-500/20">' . $action . '</span>';
    }
}
?>
<!DOCTYPE html>
<html class="dark" lang="en">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>Audit Logs - <?php echo htmlspecialchars($portalName); ?></title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap"
        rel="stylesheet" />
    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "<?php echo $primaryColor; ?>",
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
                class="flex items-center gap-3 px-4 py-3 rounded-xl bg-primary/10 text-primary transition-all group">
                <span class="material-symbols-outlined text-[20px]">history</span>
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
    <main class="flex-1 flex flex-col h-screen overflow-hidden bg-[#0a0c12]">
        <!-- Background Pattern -->
        <div class="absolute inset-0 opacity-[0.03] pointer-events-none">
            <div class="absolute inset-0"
                style="background-image: radial-gradient(#10b981 1px, transparent 1px); background-size: 32px 32px;">
            </div>
        </div>

        <header
            class="h-16 border-b border-[#232b3d] flex items-center justify-between px-8 bg-[#101622]/90 backdrop-blur-md relative z-10">
            <div class="flex items-center gap-3">
                <span class="material-symbols-outlined text-primary">history</span>
                <h2 class="text-sm font-bold text-white">System Audit Logs</h2>
            </div>

            <div class="flex items-center gap-4">
                <form method="GET" class="flex items-center gap-3">
                    <div class="relative group">
                        <span
                            class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-[18px] text-slate-500 group-focus-within:text-primary transition-colors">search</span>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                            placeholder="Search data..."
                            class="bg-[#1a2130] border border-[#232b3d] text-slate-100 text-xs rounded-xl pl-9 pr-4 py-2 focus:ring-2 focus:ring-primary focus:border-transparent w-64 transition-all">
                    </div>

                    <select name="action_type" onchange="this.form.submit()"
                        class="bg-[#1a2130] border border-[#232b3d] text-slate-100 text-xs rounded-xl px-3 py-2 focus:ring-2 focus:ring-primary focus:border-transparent">
                        <option value="All">All Actions</option>
                        <option value="Create" <?php echo $action_filter == 'Create' ? 'selected' : ''; ?>>Create</option>
                        <option value="Update" <?php echo $action_filter == 'Update' ? 'selected' : ''; ?>>Update</option>
                        <option value="Delete" <?php echo $action_filter == 'Delete' ? 'selected' : ''; ?>>Delete</option>
                        <option value="Login" <?php echo $action_filter == 'Login' ? 'selected' : ''; ?>>Login</option>
                        <option value="Action" <?php echo $action_filter == 'Action' ? 'selected' : ''; ?>>Action</option>
                    </select>

                    <input type="hidden" name="log_level" value="<?php echo htmlspecialchars($log_level); ?>">
                    <button type="submit" class="hidden"></button>
                </form>

                <div class="h-6 w-px bg-[#232b3d] mx-2"></div>

                <a href="audit_trail"
                    class="p-2 transition-colors hover:bg-white/5 rounded-lg text-slate-400 hover:text-white"
                    title="Refresh">
                    <span class="material-symbols-outlined text-[20px]">refresh</span>
                </a>
            </div>
        </header>

        <div class="p-8 flex-1 overflow-y-auto custom-scrollbar relative z-10">
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <h2 class="text-2xl font-bold text-white">Action History</h2>
                    <p class="text-sm text-slate-500 mt-1">Detailed log of all system modifications and access events.
                    </p>
                </div>

                <div class="flex items-center gap-3">
                    <div class="flex bg-[#1a2130] p-1 rounded-lg border border-[#232b3d]">
                        <a href="?log_level=All&search=<?php echo urlencode($search); ?>&action_type=<?php echo urlencode($action_filter); ?>"
                            class="px-4 py-1.5 text-xs font-bold rounded-md transition-all <?php echo $log_level == 'All' ? 'bg-primary text-white shadow-md' : 'text-slate-500 hover:text-white'; ?>">All</a>
                        <a href="?log_level=Success&search=<?php echo urlencode($search); ?>&action_type=<?php echo urlencode($action_filter); ?>"
                            class="px-4 py-1.5 text-xs font-bold rounded-md transition-all <?php echo $log_level == 'Success' ? 'bg-emerald-500/20 text-emerald-500' : 'text-slate-500 hover:text-white'; ?>">Success</a>
                        <a href="?log_level=Warning&search=<?php echo urlencode($search); ?>&action_type=<?php echo urlencode($action_filter); ?>"
                            class="px-4 py-1.5 text-xs font-bold rounded-md transition-all <?php echo $log_level == 'Warning' ? 'bg-amber-500/20 text-amber-500' : 'text-slate-500 hover:text-white'; ?>">Warning</a>
                        <a href="?log_level=Critical&search=<?php echo urlencode($search); ?>&action_type=<?php echo urlencode($action_filter); ?>"
                            class="px-4 py-1.5 text-xs font-bold rounded-md transition-all <?php echo $log_level == 'Critical' ? 'bg-red-500/20 text-red-500' : 'text-slate-500 hover:text-white'; ?>">Critical</a>
                    </div>
                </div>
            </div>

            <div class="bg-[#1a2130] border border-[#232b3d] rounded-2xl overflow-hidden shadow-sm">
                <div class="overflow-x-auto custom-scrollbar">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-[#1a2130] border-b border-[#232b3d]">
                                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase tracking-wider">
                                    Timestamp</th>
                                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase tracking-wider">
                                    Performer</th>
                                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase tracking-wider">Type
                                </th>
                                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase tracking-wider">
                                    Resource</th>
                                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase tracking-wider">
                                    Details</th>
                                <th
                                    class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase tracking-wider text-right">
                                    IP Analysis</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#232b3d]/50">
                            <?php if (empty($logs)): ?>
                                <tr>
                                    <td colspan="6" class="px-6 py-12 text-center text-slate-500 text-sm font-medium">No
                                        audit activities found matching your criteria</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($logs as $log): ?>
                                    <tr class="hover:bg-white/5 transition-colors group">
                                        <td class="px-6 py-4 text-xs font-medium text-slate-400 whitespace-nowrap">
                                            <?php echo date('M j, Y • H:i:s', strtotime($log['created_at'])); ?>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="flex items-center gap-3">
                                                <div
                                                    class="size-8 rounded-lg bg-primary/10 flex items-center justify-center text-primary text-[11px] font-bold border border-primary/20">
                                                    <?php echo strtoupper(substr($log['performer_name'] ?? 'S', 0, 1)); ?>
                                                </div>
                                                <div class="flex flex-col">
                                                    <span
                                                        class="text-sm font-semibold text-white"><?php echo htmlspecialchars($log['performer_name'] ?? 'System Process'); ?></span>
                                                    <span
                                                        class="text-xs text-slate-500 font-medium"><?php echo htmlspecialchars($log['performer_role'] ?? 'Kernel'); ?></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <?php echo getActionBadge($log['action_type']); ?>
                                        </td>
                                        <td class="px-6 py-4 text-xs text-slate-400 font-medium tracking-tight">
                                            <?php echo htmlspecialchars($log['resource_id'] ?? '--'); ?>
                                        </td>
                                        <td class="px-6 py-4 text-xs text-white max-w-xs overflow-hidden">
                                            <div class="font-bold mb-0.5"><?php echo htmlspecialchars($log['title']); ?></div>
                                            <div class="text-slate-500 text-[11px] leading-relaxed truncate"
                                                title="<?php echo htmlspecialchars($log['description']); ?>">
                                                <?php echo htmlspecialchars($log['description']); ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 text-right">
                                            <div class="text-[10px] font-mono text-slate-500">
                                                <?php echo htmlspecialchars($log['ip_address'] ?? '0.0.0.0'); ?>
                                            </div>
                                            <div class="text-[9px] text-primary/50 font-bold tracking-widest uppercase">Verified
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="mt-8 flex items-center justify-between">
                    <p class="text-xs text-slate-500 font-medium">
                        Showing <?php echo count($logs); ?> entries on page <?php echo $page; ?>
                    </p>
                    <div class="flex gap-2">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&action_type=<?php echo urlencode($action_filter); ?>&log_level=<?php echo urlencode($log_level); ?>"
                                class="flex items-center justify-center size-8 rounded-lg border border-[#232b3d] bg-[#101622] text-slate-400 hover:text-white hover:bg-[#232b3d] transition-all">
                                <span class="material-symbols-outlined text-sm">chevron_left</span>
                            </a>
                        <?php endif; ?>

                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&action_type=<?php echo urlencode($action_filter); ?>&log_level=<?php echo urlencode($log_level); ?>"
                                class="flex items-center justify-center size-8 rounded-lg border transition-all text-xs font-bold <?php echo $page == $i ? 'border-primary bg-primary text-white shadow-lg' : 'border-[#232b3d] bg-[#101622] text-slate-400 hover:text-white'; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>

                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&action_type=<?php echo urlencode($action_filter); ?>&log_level=<?php echo urlencode($log_level); ?>"
                                class="flex items-center justify-center size-8 rounded-lg border border-[#232b3d] bg-[#101622] text-slate-400 hover:text-white hover:bg-[#232b3d] transition-all">
                                <span class="material-symbols-outlined text-sm">chevron_right</span>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>
</body>

</html>