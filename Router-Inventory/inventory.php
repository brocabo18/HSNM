<?php
session_start();
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header('Location: login');
    exit;
}

require_once 'db.php';
require_once 'audit_logger.php';

// Handle Add Device (POST)
$success_msg = '';
$error_msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_device') {
    $sn = $_POST['serial_number'] ?? '';
    $model = $_POST['model'] ?? '';
    $location = $_POST['location'] ?? '';
    $ip = $_POST['ip_address'] ?? '';
    $ssid = $_POST['ssid'] ?? '';
    $wifi_pass = $_POST['wifi_password'] ?? '';
    $admin_user = $_POST['admin_user'] ?? 'admin';
    $admin_pass = $_POST['admin_password'] ?? '';
    $status = $_POST['status'] ?? 'Offline';

    if ($sn && $model) {
        try {
            $stmt = $pdo->prepare("INSERT INTO inventory (serial_number, model, location, ip_address, ssid, wifi_password, admin_user, admin_password, status, last_seen) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$sn, $model, $location, $ip, $ssid, $wifi_pass, $admin_user, $admin_pass, $status]);

            // Log Activity
            logAudit($pdo, "Device Added", "New device $sn ($model) was added to the inventory.", "Success", "Create", $sn);

            $success_msg = "Device added successfully.";
        } catch (Exception $e) {
            $error_msg = "Error adding device: " . $e->getMessage();
        }
    } else {
        $error_msg = "Serial Number and Model are required.";
    }
}

// Handle Edit Device (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_device') {
    $id = $_POST['device_id'] ?? 0;
    $sn = $_POST['serial_number'] ?? '';
    $model = $_POST['model'] ?? '';
    $location = $_POST['location'] ?? '';
    $ip = $_POST['ip_address'] ?? '';
    $ssid = $_POST['ssid'] ?? '';
    $wifi_pass = $_POST['wifi_password'] ?? '';
    $admin_user = $_POST['admin_user'] ?? 'admin';
    $admin_pass = $_POST['admin_password'] ?? '';
    $status = $_POST['status'] ?? 'Offline';

    if ($id && $sn && $model) {
        try {
            $stmt = $pdo->prepare("UPDATE inventory SET serial_number = ?, model = ?, location = ?, ip_address = ?, ssid = ?, wifi_password = ?, admin_user = ?, admin_password = ?, status = ? WHERE id = ?");
            $stmt->execute([$sn, $model, $location, $ip, $ssid, $wifi_pass, $admin_user, $admin_pass, $status, $id]);

            // Log Activity
            logAudit($pdo, "Device Updated", "Device $sn ($model) information was updated.", "Info", "Update", $sn);

            $success_msg = "Device updated successfully.";
        } catch (Exception $e) {
            $error_msg = "Error updating device: " . $e->getMessage();
        }
    } else {
        $error_msg = "Invalid data for update.";
    }
}

// Handle Delete Device (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_device') {
    $id = $_POST['device_id'] ?? 0;
    if ($id) {
        try {
            // First get details for logging
            $getStmt = $pdo->prepare("SELECT serial_number FROM inventory WHERE id = ?");
            $getStmt->execute([$id]);
            $device = $getStmt->fetch();
            $sn = $device ? $device['serial_number'] : "Unknown";

            $stmt = $pdo->prepare("DELETE FROM inventory WHERE id = ?");
            $stmt->execute([$id]);

            // Log Activity
            logAudit($pdo, "Device Deleted", "Device $sn was removed from the inventory.", "Warning", "Delete", $sn);

            $success_msg = "Device deleted successfully.";
        } catch (Exception $e) {
            $error_msg = "Error deleting device: " . $e->getMessage();
        }
    }
}

// Handle Reboot (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reboot_device') {
    $id = $_POST['device_id'] ?? 0;
    if ($id) {
        try {
            $stmt = $pdo->prepare("UPDATE inventory SET uptime = '0d 00h 01m', status = 'Online', last_seen = NOW() WHERE id = ?");
            $stmt->execute([$id]);

            // Get SN for logging
            $getStmt = $pdo->prepare("SELECT serial_number FROM inventory WHERE id = ?");
            $getStmt->execute([$id]);
            $device = $getStmt->fetch();
            $sn = $device ? $device['serial_number'] : "Unknown";

            // Log Activity
            logAudit($pdo, "Remote Reboot", "Device $sn has been successfully rebooted.", "Success", "Action", $sn);

            $success_msg = "Reboot command sent to $sn.";
        } catch (Exception $e) {
            $error_msg = "Error rebooting device: " . $e->getMessage();
        }
    }
}

// Build Search & Filter Params
$search_term = $_GET['search'] ?? '';
$filter_status = $_GET['status'] ?? 'All'; // Default to All
$filter_location = $_GET['location'] ?? 'All';

$where_clauses = ["1=1"];
$params = [];

if ($search_term) {
    $where_clauses[] = "(serial_number LIKE ? OR location LIKE ? OR ip_address LIKE ?)";
    $params[] = "%$search_term%";
    $params[] = "%$search_term%";
    $params[] = "%$search_term%";
}

if ($filter_status !== 'All' && !empty($filter_status)) {
    $where_clauses[] = "status = ?";
    $params[] = $filter_status;
}

if ($filter_location !== 'All' && !empty($filter_location)) {
    $where_clauses[] = "location LIKE ?";
    $params[] = "%$filter_location%";
}

$where_sql = implode(' AND ', $where_clauses);

// Handle Export CSV
if (isset($_GET['action']) && $_GET['action'] === 'export_csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="inventory_export_' . date('Y-m-d') . '.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Serial Number', 'Model', 'Location', 'IP Address', 'Status', 'Uptime', 'Last Seen']); // Header

    $stmt = $pdo->prepare("SELECT * FROM inventory WHERE $where_sql ORDER BY id DESC");
    $stmt->execute($params);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, $row);
    }
    fclose($output);
    exit;
}

// Pagination Logic
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1)
    $page = 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Get Total Count for Pagination
$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM inventory WHERE $where_sql");
$count_stmt->execute($params);
$total_items = $count_stmt->fetchColumn();
$total_pages = ceil($total_items / $limit);

// Calculate Stats (Global, not filtered)
$stmt = $pdo->query("SELECT COUNT(*) FROM inventory");
$total_routers = $stmt->fetchColumn();

// Fetch Filtered Inventory Items
$sql = "SELECT * FROM inventory WHERE $where_sql ORDER BY id DESC LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$inventory_data = $stmt->fetchAll();

function getInventoryStatusColor($status)
{
    switch ($status) {
        case 'Online':
            return 'text-[#10b981]';
        case 'Offline':
            return 'text-[#fa6238]';
        case 'Warning':
            return 'text-orange-500';
        default:
            return 'text-slate-500';
    }
}
function getInventoryDotClass($status)
{
    switch ($status) {
        case 'Online':
            return 'bg-[#10b981] shadow-lg shadow-[#10b981]/50';
        case 'Offline':
            return 'bg-[#fa6238] shadow-lg shadow-[#fa6238]/50';
        case 'Warning':
            return 'bg-orange-500';
        default:
            return 'bg-slate-500';
    }
}
?>
<!DOCTYPE html>
<html class="dark" lang="en">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>Inventory Management - <?php echo htmlspecialchars($portalName ?? "IHOMS Router Portal"); ?></title>
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
                class="flex items-center gap-3 px-4 py-3 rounded-xl bg-primary/10 text-primary transition-all group">
                <span class="material-symbols-outlined text-[20px]">inventory_2</span>
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
                <span class="material-symbols-outlined text-primary">inventory_2</span>
                <h2 class="text-sm font-bold text-white">Inventory Management</h2>
            </div>

            <div class="flex items-center gap-4">
                <div class="relative group">
                    <span
                        class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-[18px] text-slate-500 group-focus-within:text-primary transition-colors">search</span>
                    <input type="text" id="headerSearch" placeholder="Search devices..."
                        value="<?php echo htmlspecialchars($search_term); ?>"
                        onkeydown="if(event.key === 'Enter') { window.location.href = '?search=' + encodeURIComponent(this.value); }"
                        class="bg-[#1a2130] border border-[#232b3d] text-slate-200 text-xs rounded-xl pl-9 pr-4 py-2 focus:ring-2 focus:ring-primary focus:border-transparent w-64 transition-all">
                </div>

                <div class="h-6 w-px bg-[#232b3d] mx-2"></div>

                <button onclick="toggleModal()"
                    class="flex items-center justify-center rounded-lg h-9 px-4 bg-primary text-white hover:bg-primary/90 transition-colors text-xs font-bold shadow-lg shadow-primary/20">
                    <span class="material-symbols-outlined text-[18px] mr-2">add</span> Add Device
                </button>

                <a href="inventory"
                    class="p-2 transition-colors hover:bg-white/5 rounded-lg text-slate-400 hover:text-white"
                    title="Refresh">
                    <span class="material-symbols-outlined">refresh</span>
                </a>
            </div>
        </header>

        <div class="p-8 flex-1 overflow-y-auto custom-scrollbar relative z-10">
            <!-- Notifications -->
            <?php if ($success_msg): ?>
                <div
                    class="mb-6 bg-emerald-500/10 border border-emerald-500/20 text-emerald-500 p-4 rounded-xl text-sm font-medium flex items-center gap-3">
                    <span class="material-symbols-outlined">check_circle</span>
                    <?= htmlspecialchars($success_msg) ?>
                </div>
            <?php endif; ?>
            <?php if ($error_msg): ?>
                <div
                    class="mb-6 bg-red-500/10 border border-red-500/20 text-red-500 p-4 rounded-xl text-sm font-medium flex items-center gap-3">
                    <span class="material-symbols-outlined">error</span>
                    <?= htmlspecialchars($error_msg) ?>
                </div>
            <?php endif; ?>

            <!-- Page Heading -->
            <div class="mb-8 flex items-end justify-between">
                <div>
                    <h2 class="text-2xl font-bold text-white">Router Inventory</h2>
                    <p class="text-sm text-slate-500 mt-1">Manage and monitor <?= number_format($total_routers) ?>
                        wifi-routers across all global locations.</p>
                </div>

                <div class="flex items-center gap-3">
                    <a href="?action=export_csv&<?= http_build_query($_GET) ?>"
                        class="flex items-center gap-2 bg-[#1a2130] hover:bg-[#232b3d] border border-[#232b3d] px-4 py-2 rounded-lg text-xs font-semibold transition-all text-slate-400 hover:text-white">
                        <span class="material-symbols-outlined text-[18px]">download</span>
                        Export CSV
                    </a>
                </div>
            </div>

            <!-- Filters Form -->
            <div class="mb-6 flex items-center justify-between">
                <form method="GET" class="flex items-center gap-3 flex-wrap">
                    <div class="flex bg-[#1a2130] p-1 rounded-lg border border-[#232b3d]">
                        <button type="button" onclick="window.location.href='inventory'"
                            class="px-4 py-1.5 text-[11px] font-semibold rounded-md transition-all <?= $filter_status === 'All' && $filter_location === 'All' ? 'bg-primary text-white shadow-md' : 'text-slate-500 hover:text-white' ?>">
                            All Devices
                        </button>
                    </div>

                    <div class="h-6 w-px bg-[#232b3d] mx-1"></div>

                    <div class="relative group">
                        <select name="status" onchange="this.form.submit()"
                            class="appearance-none pl-4 pr-10 py-2 bg-[#1a2130] border border-[#232b3d] rounded-lg text-xs font-medium text-slate-400 focus:ring-2 focus:ring-primary focus:border-transparent cursor-pointer hover:bg-[#232b3d] transition-colors min-w-[140px]">
                            <option value="All" <?= $filter_status === 'All' ? 'selected' : '' ?>>Status: All</option>
                            <option value="Online" <?= $filter_status === 'Online' ? 'selected' : '' ?>>Online</option>
                            <option value="Offline" <?= $filter_status === 'Offline' ? 'selected' : '' ?>>Offline</option>
                            <option value="Warning" <?= $filter_status === 'Warning' ? 'selected' : '' ?>>Warning</option>
                        </select>
                        <span
                            class="material-symbols-outlined text-[18px] absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none text-slate-500 group-hover:text-primary transition-colors">expand_more</span>
                    </div>

                    <div class="relative group">
                        <select name="location" onchange="this.form.submit()"
                            class="appearance-none pl-4 pr-10 py-2 bg-[#1a2130] border border-[#232b3d] rounded-lg text-xs font-medium text-slate-400 focus:ring-2 focus:ring-primary focus:border-transparent cursor-pointer hover:bg-[#232b3d] transition-colors min-w-[160px]">
                            <option value="All" <?= $filter_location === 'All' ? 'selected' : '' ?>>Location: All</option>
                            <option value="New York" <?= $filter_location === 'New York' ? 'selected' : '' ?>>New York
                            </option>
                            <option value="London" <?= $filter_location === 'London' ? 'selected' : '' ?>>London</option>
                            <option value="Tokyo" <?= $filter_location === 'Tokyo' ? 'selected' : '' ?>>Tokyo</option>
                        </select>
                        <span
                            class="material-symbols-outlined text-[18px] absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none text-slate-500 group-hover:text-primary transition-colors">expand_more</span>
                    </div>

                    <?php if ($search_term): ?>
                        <input type="hidden" name="search" value="<?= htmlspecialchars($search_term) ?>">
                    <?php endif; ?>

                    <?php if ($filter_status !== 'All' || $filter_location !== 'All' || $search_term): ?>
                        <a href="inventory"
                            class="text-xs font-bold text-primary hover:text-emerald-400 transition-colors flex items-center gap-1 ml-2">
                            <span class="material-symbols-outlined text-sm">close</span>
                            Clear Filters
                        </a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Inventory Table -->
            <div class="bg-[#1a2130] border border-[#232b3d] rounded-2xl overflow-hidden shadow-sm">
                <div class="overflow-x-auto custom-scrollbar">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-[#1a2130] border-b border-[#232b3d]">
                                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase tracking-wider">
                                    Device SN</th>
                                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase tracking-wider">
                                    Model</th>
                                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase tracking-wider">
                                    Location</th>
                                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase tracking-wider">IP
                                    Address</th>
                                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase tracking-wider">
                                    Status</th>
                                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase tracking-wider">
                                    Uptime</th>
                                <th
                                    class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase tracking-wider text-right">
                                    Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#232b3d]/50">
                            <?php if (empty($inventory_data)): ?>
                                <tr>
                                    <td colspan="7" class="px-6 py-12 text-center text-slate-500 text-sm font-medium">No
                                        devices found matching your criteria</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($inventory_data as $item):
                                    $status_color = getInventoryStatusColor($item['status']);
                                    $dot_class = getInventoryDotClass($item['status']);
                                    ?>
                                    <tr class="hover:bg-white/5 transition-colors group">
                                        <td class="px-6 py-4 text-sm font-medium text-white whitespace-nowrap">
                                            <?= htmlspecialchars($item['serial_number']) ?>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="flex flex-col">
                                                <span
                                                    class="text-sm font-medium text-white"><?= htmlspecialchars($item['model']) ?></span>
                                                <span
                                                    class="text-xs text-slate-500"><?= htmlspecialchars($item['ssid'] ?? 'No SSID') ?></span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-slate-400">
                                            <div class="flex items-center gap-2">
                                                <span
                                                    class="material-symbols-outlined text-[16px] text-slate-500">location_on</span>
                                                <?= htmlspecialchars($item['location']) ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 text-sm font-mono text-slate-400">
                                            <?= htmlspecialchars($item['ip_address']) ?>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="flex items-center gap-2">
                                                <div class="size-2 rounded-full <?= $dot_class ?>"></div>
                                                <span class="text-xs font-medium <?= $status_color ?>">
                                                    <?= htmlspecialchars($item['status']) ?>
                                                </span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 text-xs text-slate-500 font-medium">
                                            <?= htmlspecialchars($item['uptime']) ?>
                                        </td>
                                        <td class="px-6 py-4 text-right">
                                            <div class="flex items-center justify-end gap-2">
                                                <form method="POST"
                                                    onsubmit="return confirm('Remote reboot <?= htmlspecialchars($item['serial_number']) ?>?');">
                                                    <input type="hidden" name="action" value="reboot_device">
                                                    <input type="hidden" name="device_id" value="<?= $item['id'] ?>">
                                                    <button type="submit"
                                                        class="p-2 transition-colors hover:bg-primary/10 hover:text-primary text-slate-500 rounded-lg"
                                                        title="Reboot">
                                                        <span class="material-symbols-outlined text-[20px]">restart_alt</span>
                                                    </button>
                                                </form>
                                                <button onclick='openEditModal(<?= json_encode($item) ?>)'
                                                    class="p-2 transition-colors hover:bg-primary/10 hover:text-primary text-slate-500 rounded-lg"
                                                    title="Configure">
                                                    <span class="material-symbols-outlined text-[20px]">settings_suggest</span>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div
                        class="px-6 py-4 bg-[#1a2130] border-t border-[#232b3d] flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <p class="text-xs text-slate-500 font-medium">
                            Showing <?= $offset + 1 ?> to <?= min($offset + $limit, $total_items) ?> of
                            <?= number_format($total_items) ?> Routers
                        </p>
                        <div class="flex items-center gap-2">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search_term) ?>&status=<?= urlencode($filter_status) ?>&location=<?= urlencode($filter_location) ?>"
                                    class="flex items-center justify-center size-8 rounded-lg border border-[#232b3d] bg-[#101622] text-slate-400 hover:text-white hover:bg-[#232b3d] transition-all">
                                    <span class="material-symbols-outlined text-sm">chevron_left</span>
                                </a>
                            <?php endif; ?>

                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <a href="?page=<?= $i ?>&search=<?= urlencode($search_term) ?>&status=<?= urlencode($filter_status) ?>&location=<?= urlencode($filter_location) ?>"
                                    class="flex items-center justify-center size-8 rounded-lg border transition-all text-xs font-semibold <?= $page == $i ? 'border-primary bg-primary text-white shadow-lg shadow-primary/20' : 'border-[#232b3d] bg-[#101622] text-slate-400 hover:text-white' ?>">
                                    <?= $i ?>
                                </a>
                            <?php endfor; ?>

                            <?php if ($page < $total_pages): ?>
                                <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search_term) ?>&status=<?= urlencode($filter_status) ?>&location=<?= urlencode($filter_location) ?>"
                                    class="flex items-center justify-center size-8 rounded-lg border border-[#232b3d] bg-[#101622] text-slate-400 hover:text-white hover:bg-[#232b3d] transition-all">
                                    <span class="material-symbols-outlined text-sm">chevron_right</span>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Add Device Modal -->
        <div id="addDeviceModal" class="hidden fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title"
            role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-background-dark/80 backdrop-blur-sm transition-opacity" aria-hidden="true"
                    onclick="toggleModal()"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                <div
                    class="inline-block align-bottom bg-[#1a2130] border border-[#232b3d] rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <form method="POST">
                        <input type="hidden" name="action" value="add_device">
                        <div class="px-6 py-6">
                            <div class="flex items-start gap-4 mb-6">
                                <div
                                    class="size-12 rounded-2xl bg-primary/10 flex items-center justify-center text-primary shrink-0">
                                    <span class="material-symbols-outlined text-[24px]">add_circle</span>
                                </div>
                                <div>
                                    <h3 class="text-lg font-bold text-white">Add New Device</h3>
                                    <p class="text-sm text-slate-400 mt-1">Register a new router to the network
                                        inventory.</p>
                                </div>
                            </div>

                            <div class="space-y-4">
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-xs font-semibold text-slate-400 mb-1.5">Serial
                                            Number</label>
                                        <input type="text" name="serial_number" required
                                            class="w-full bg-[#101622] border border-[#232b3d] text-white text-sm rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-primary focus:border-transparent transition-all placeholder:text-slate-600">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-slate-400 mb-1.5">Model</label>
                                        <input type="text" name="model" required
                                            class="w-full bg-[#101622] border border-[#232b3d] text-white text-sm rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-primary focus:border-transparent transition-all placeholder:text-slate-600">
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-400 mb-1.5">Location</label>
                                    <input type="text" name="location"
                                        class="w-full bg-[#101622] border border-[#232b3d] text-white text-sm rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-primary focus:border-transparent transition-all placeholder:text-slate-600">
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-xs font-semibold text-slate-400 mb-1.5">IP
                                            Address</label>
                                        <input type="text" name="ip_address"
                                            class="w-full bg-[#101622] border border-[#232b3d] text-white text-sm rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-primary focus:border-transparent transition-all placeholder:text-slate-600">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-slate-400 mb-1.5">Status</label>
                                        <select name="status"
                                            class="w-full bg-[#101622] border border-[#232b3d] text-white text-sm rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-primary focus:border-transparent transition-all">
                                            <option value="Online">Online</option>
                                            <option value="Offline">Offline</option>
                                            <option value="Warning">Warning</option>
                                            <option value="Maintenance">Maintenance</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="relative py-2">
                                    <div class="absolute inset-0 flex items-center">
                                        <div class="w-full border-t border-[#232b3d]"></div>
                                    </div>
                                    <div class="relative flex justify-center">
                                        <span
                                            class="bg-[#1a2130] px-3 text-xs font-semibold text-slate-500">Credentials</span>
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-xs font-semibold text-slate-400 mb-1.5">SSID</label>
                                        <input type="text" name="ssid"
                                            class="w-full bg-[#101622] border border-[#232b3d] text-white text-sm rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-primary focus:border-transparent transition-all placeholder:text-slate-600">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-slate-400 mb-1.5">Wifi
                                            Password</label>
                                        <input type="text" name="wifi_password"
                                            class="w-full bg-[#101622] border border-[#232b3d] text-white text-sm rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-primary focus:border-transparent transition-all placeholder:text-slate-600">
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-4 mt-4">
                                    <div>
                                        <label class="block text-xs font-semibold text-slate-400 mb-1.5">Admin
                                            User</label>
                                        <input type="text" name="admin_user" value="admin"
                                            class="w-full bg-[#101622] border border-[#232b3d] text-white text-sm rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-primary focus:border-transparent transition-all placeholder:text-slate-600">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-slate-400 mb-1.5">Admin
                                            Password</label>
                                        <input type="text" name="admin_password"
                                            class="w-full bg-[#101622] border border-[#232b3d] text-white text-sm rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-primary focus:border-transparent transition-all placeholder:text-slate-600">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div
                            class="px-6 py-4 bg-[#101622]/50 border-t border-[#232b3d] flex items-center justify-end gap-3">
                            <button type="button" onclick="toggleModal()"
                                class="px-4 py-2 text-sm font-semibold text-slate-400 hover:text-white transition-colors">
                                Cancel
                            </button>
                            <button type="submit"
                                class="px-6 py-2 bg-primary hover:bg-primary/90 text-white text-sm font-bold rounded-xl shadow-lg shadow-primary/20 transition-all">
                                Save Device
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Edit Device Modal -->
        <div id="editDeviceModal" class="hidden fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title"
            role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-background-dark/80 backdrop-blur-sm transition-opacity" aria-hidden="true"
                    onclick="toggleEditModal()"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                <div
                    class="inline-block align-bottom bg-[#1a2130] border border-[#232b3d] rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <form method="POST">
                        <input type="hidden" name="action" value="edit_device">
                        <input type="hidden" name="device_id" id="edit_device_id">
                        <div class="px-6 py-6">
                            <div class="flex items-start gap-4 mb-6">
                                <div
                                    class="size-12 rounded-2xl bg-amber-500/10 flex items-center justify-center text-amber-500 shrink-0">
                                    <span class="material-symbols-outlined text-[24px]">edit_square</span>
                                </div>
                                <div>
                                    <h3 class="text-lg font-bold text-white">Edit Device</h3>
                                    <p class="text-sm text-slate-400 mt-1">Modify router settings and configuration.</p>
                                </div>
                            </div>

                            <div class="space-y-4">
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-xs font-semibold text-slate-400 mb-1.5">Serial
                                            Number</label>
                                        <input type="text" name="serial_number" id="edit_serial_number" required
                                            class="w-full bg-[#101622] border border-[#232b3d] text-white text-sm rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-primary focus:border-transparent transition-all">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-slate-400 mb-1.5">Model</label>
                                        <input type="text" name="model" id="edit_model" required
                                            class="w-full bg-[#101622] border border-[#232b3d] text-white text-sm rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-primary focus:border-transparent transition-all">
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-400 mb-1.5">Location</label>
                                    <input type="text" name="location" id="edit_location"
                                        class="w-full bg-[#101622] border border-[#232b3d] text-white text-sm rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-primary focus:border-transparent transition-all">
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-xs font-semibold text-slate-400 mb-1.5">IP
                                            Address</label>
                                        <input type="text" name="ip_address" id="edit_ip_address"
                                            class="w-full bg-[#101622] border border-[#232b3d] text-white text-sm rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-primary focus:border-transparent transition-all">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-slate-400 mb-1.5">Status</label>
                                        <select name="status" id="edit_status"
                                            class="w-full bg-[#101622] border border-[#232b3d] text-white text-sm rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-primary focus:border-transparent transition-all">
                                            <option value="Online">Online</option>
                                            <option value="Offline">Offline</option>
                                            <option value="Warning">Warning</option>
                                            <option value="Maintenance">Maintenance</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="relative py-2">
                                    <div class="absolute inset-0 flex items-center">
                                        <div class="w-full border-t border-[#232b3d]"></div>
                                    </div>
                                    <div class="relative flex justify-center">
                                        <span
                                            class="bg-[#1a2130] px-3 text-xs font-semibold text-slate-500">Credentials</span>
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-xs font-semibold text-slate-400 mb-1.5">SSID</label>
                                        <input type="text" name="ssid" id="edit_ssid"
                                            class="w-full bg-[#101622] border border-[#232b3d] text-white text-sm rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-primary focus:border-transparent transition-all">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-slate-400 mb-1.5">Wifi
                                            Password</label>
                                        <input type="text" name="wifi_password" id="edit_wifi_password"
                                            class="w-full bg-[#101622] border border-[#232b3d] text-white text-sm rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-primary focus:border-transparent transition-all">
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-4 mt-4">
                                    <div>
                                        <label class="block text-xs font-semibold text-slate-400 mb-1.5">Admin
                                            User</label>
                                        <input type="text" name="admin_user" id="edit_admin_user"
                                            class="w-full bg-[#101622] border border-[#232b3d] text-white text-sm rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-primary focus:border-transparent transition-all">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-slate-400 mb-1.5">Admin
                                            Password</label>
                                        <input type="text" name="admin_password" id="edit_admin_password"
                                            class="w-full bg-[#101622] border border-[#232b3d] text-white text-sm rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-primary focus:border-transparent transition-all">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div
                            class="px-6 py-4 bg-[#101622]/50 border-t border-[#232b3d] flex items-center justify-between">
                            <button type="button" onclick="confirmDeletion()"
                                class="px-4 py-2 bg-red-500/10 hover:bg-red-500/20 text-red-500 text-sm font-semibold rounded-xl transition-all border border-red-500/20">
                                Delete Device
                            </button>
                            <div class="flex items-center gap-3">
                                <button type="button" onclick="toggleEditModal()"
                                    class="px-4 py-2 text-sm font-semibold text-slate-400 hover:text-white transition-colors">
                                    Cancel
                                </button>
                                <button type="submit"
                                    class="px-6 py-2 bg-primary hover:bg-primary/90 text-white text-sm font-bold rounded-xl shadow-lg shadow-primary/20 transition-all">
                                    Update
                                </button>
                            </div>
                        </div>
                    </form>
                    <!-- Hidden Deletion Form -->
                    <form id="deleteForm" method="POST" class="hidden">
                        <input type="hidden" name="action" value="delete_device">
                        <input type="hidden" name="device_id" id="delete_device_id">
                    </form>
                </div>
            </div>
        </div>

        <script>
            function toggleModal() {
                const modal = document.getElementById('addDeviceModal');
                modal.classList.toggle('hidden');
            }

            function toggleEditModal() {
                const modal = document.getElementById('editDeviceModal');
                modal.classList.toggle('hidden');
            }

            function openEditModal(device) {
                document.getElementById('edit_device_id').value = device.id;
                document.getElementById('edit_serial_number').value = device.serial_number;
                document.getElementById('edit_model').value = device.model;
                document.getElementById('edit_location').value = device.location;
                document.getElementById('edit_ip_address').value = device.ip_address;
                document.getElementById('edit_ssid').value = device.ssid || '';
                document.getElementById('edit_wifi_password').value = device.wifi_password || '';
                document.getElementById('edit_admin_user').value = device.admin_user || 'admin';
                document.getElementById('edit_admin_password').value = device.admin_password || '';
                document.getElementById('edit_status').value = device.status;

                toggleEditModal();
            }

            function confirmDeletion() {
                const sn = document.getElementById('edit_serial_number').value;
                if (confirm(`Are you sure you want to permanently delete device ${sn}? This action cannot be undone.`)) {
                    document.getElementById('delete_device_id').value = document.getElementById('edit_device_id').value;
                    document.getElementById('deleteForm').submit();
                }
            }
        </script>
    </main>
</body>

</html>