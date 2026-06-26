<?php
/**
 * Network Switch Inventory Dashboard
 * Main application interface with database integration
 */

session_start();
require_once 'config.php';
require_once 'auth.php';

// Require authentication
requireAuth();

// Get current user
$pdo = getDBConnection();
$currentUser = getCurrentUser();
$isAdmin = isAdmin();

// Fetch system settings
$appSettings = getSystemSettings($pdo);
$portalName = $appSettings['portal_name'] ?? 'Network Switch Inventory';
$portalSubtitle = $appSettings['portal_subtitle'] ?? 'IHOMS';
$primaryColor = $appSettings['theme_color'] ?? '#135bec';

// Fetch initial data from database
try {
    $stmt = $pdo->query("SELECT * FROM switches ORDER BY switch_id ASC");
    $switches = $stmt->fetchAll();
    $totalDevices = count($switches);
} catch (Exception $e) {
    error_log("Dashboard Error: " . $e->getMessage());
    $switches = [];
    $totalDevices = 0;
}

// Helper function to get status badge styling
function getStatusBadge($status)
{
    $badges = [
        'Active' => 'bg-emerald-500/10 text-emerald-500 border-emerald-500/20',
        'Maintenance' => 'bg-amber-500/10 text-amber-500 border-amber-500/20',
        'Inactive' => 'bg-red-500/10 text-red-500 border-red-500/20'
    ];

    $class = $badges[$status] ?? 'bg-gray-500/10 text-gray-500 border-gray-500/20';
    return "<span class=\"inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-tighter {$class} border\">{$status}</span>";
}

// Helper function to format maintenance date
function formatMaintenanceDate($date)
{
    if ($date === 'TODAY') {
        return '<span class="text-amber-500 font-bold">Next: TODAY</span>';
    } elseif ($date === 'TBD') {
        return '<span class="text-white font-medium">Next: TBD</span>';
    } else {
        return '<span class="text-white font-medium">Next: ' . htmlspecialchars($date) . '</span>';
    }
}
?>
<!DOCTYPE html>
<html class="dark" lang="en">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>Network Switch Inventory Dashboard - Detailed View</title>
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
                        "table-stripe": "#1a2130",
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
            height: 8px;
            width: 8px;
        }

        .custom-scrollbar::-webkit-scrollbar-track {
            background: #101622;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #282e39;
            border-radius: 4px;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: #3f4756;
        }

        .mono-font {
            font-family: 'ui-monospace', 'SFMono-Regular', 'Menlo', 'Monaco', 'Consolas', "Liberation Mono", "Courier New", monospace;
        }

        .modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.75);
            z-index: 50;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal.active .modal-content {
            animation: slideIn 0.3s ease-out;
        }
    </style>
</head>

<body
    class="bg-background-light dark:bg-background-dark font-display text-slate-900 dark:text-slate-100 min-h-screen flex overflow-hidden">
    <!-- Sidebar Navigation -->
    <aside class="w-64 border-r border-border-dark bg-background-dark flex flex-col h-screen flex-shrink-0">
        <div class="p-6 flex items-center gap-3">
            <div class="bg-primary size-10 rounded-lg flex items-center justify-center text-white">
                <span class="material-symbols-outlined">router</span>
            </div>
            <div class="flex flex-col">
                <h1 class="text-white text-base font-bold leading-none"><?php echo htmlspecialchars($portalName); ?>
                </h1>
                <p class="text-[#9da6b9] text-xs mt-1"><?php echo htmlspecialchars($portalSubtitle); ?></p>
            </div>
        </div>
        <nav class="flex-1 px-4 space-y-2 mt-4">
            <a class="flex items-center gap-3 px-3 py-2 text-[#9da6b9] hover:text-white transition-colors" href="index">
                <span class="material-symbols-outlined text-[20px]">dashboard</span>
                <span class="text-sm font-medium">Dashboard</span>
            </a>
            <a class="flex items-center gap-3 px-3 py-2 bg-primary/10 text-primary rounded-lg" href="dashboard">
                <span class="material-symbols-outlined text-[20px] fill-1">database</span>
                <span class="text-sm font-semibold">Inventory</span>
            </a>
            <div class="pt-4 pb-2 px-3">
                <p class="text-[10px] font-bold text-[#4e5666] uppercase tracking-wider">Administration</p>
            </div>
            <?php if ($isAdmin): ?>
                <a class="flex items-center gap-3 px-3 py-2.5 text-[#9da6b9] hover:text-white transition-colors"
                    href="team">
                    <span class="material-symbols-outlined text-[20px]">group</span>
                    <span class="text-sm font-medium">Team Access</span>
                </a>
                <a class="flex items-center gap-3 px-3 py-2.5 text-[#9da6b9] hover:text-white transition-colors"
                    href="logs">
                    <span class="material-symbols-outlined text-[20px]">history</span>
                    <span class="text-sm font-medium">Audit Logs</span>
                </a>
            <?php endif; ?>
            <a class="flex items-center gap-3 px-3 py-2.5 text-[#9da6b9] hover:text-white transition-colors"
                href="settings">
                <span class="material-symbols-outlined text-[20px]">settings</span>
                <span class="text-sm font-medium">Settings</span>
            </a>
        </nav>
        <div class="p-4 mt-auto border-t border-border-dark">
            <a href="logout.php"
                class="flex items-center gap-3 p-2 bg-[#1a2130] rounded-xl hover:bg-[#242b3d] transition-colors group">
                <div
                    class="size-8 rounded-full bg-primary flex items-center justify-center text-white font-bold text-sm">
                    <?php echo strtoupper(substr($currentUser['full_name'], 0, 1)); ?>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-bold text-white truncate">
                        <?php echo htmlspecialchars($currentUser['full_name']); ?>
                    </p>
                    <p class="text-[10px] text-[#9da6b9] truncate"><?php echo htmlspecialchars($currentUser['role']); ?>
                    </p>
                </div>
                <span
                    class="material-symbols-outlined text-[#9da6b9] text-lg group-hover:text-red-400 transition-colors">logout</span>
            </a>
        </div>
    </aside>

    <!-- Main Content Area -->
    <main class="flex-1 flex flex-col h-screen overflow-hidden bg-[#0a0c12]">
        <?php
        $pageTitle = "Switch Inventory";
        include 'header_partial.php';
        ?>

        <!-- Page Content -->
        <div class="flex-1 flex flex-col overflow-hidden p-8">
            <!-- Headline & Primary Actions -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
                <div>
                    <h2 class="text-2xl font-bold text-white tracking-tight">Network Switch Inventory</h2>
                    <p class="text-sm text-[#9da6b9] mt-1">Manage <span
                            id="deviceCount"><?php echo $totalDevices; ?></span> active devices across 4 global data
                        centers</p>
                </div>
                <div class="flex items-center gap-3">
                    <button onclick="exportCSV()"
                        class="flex items-center gap-2 px-4 py-2 bg-border-dark text-white text-sm font-semibold rounded-lg hover:bg-[#3f4756] transition-all">
                        <span class="material-symbols-outlined text-lg">download</span>
                        Export CSV
                    </button>
                    <?php if (isAdmin()): ?>
                        <button onclick="openImportModal()"
                            class="flex items-center gap-2 px-4 py-2 bg-border-dark text-white text-sm font-semibold rounded-lg hover:bg-[#3f4756] transition-all">
                            <span class="material-symbols-outlined text-lg">upload_file</span>
                            Import CSV
                        </button>
                        <button onclick="openAddModal()"
                            class="flex items-center gap-2 px-4 py-2 bg-primary text-white text-sm font-semibold rounded-lg hover:bg-primary/90 transition-all shadow-lg shadow-primary/20">
                            <span class="material-symbols-outlined text-lg">add</span>
                            Add New Switch
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Filter Toolbar -->
            <div
                class="bg-background-dark border border-border-dark p-3 rounded-xl mb-6 flex flex-wrap gap-3 items-center">
                <div class="flex items-center gap-2 px-3 py-1.5 bg-[#1a2130] rounded-lg border border-border-dark">
                    <span class="text-xs font-bold text-[#4e5666] uppercase">Filter:</span>
                    <select id="filterManufacturer" class="bg-transparent border-none focus:ring-0 text-sm text-white">
                        <option value="">All Manufacturers</option>
                    </select>
                </div>
                <div class="flex items-center gap-2 px-3 py-1.5 bg-[#1a2130] rounded-lg border border-border-dark">
                    <select id="filterBuilding" class="bg-transparent border-none focus:ring-0 text-sm text-white">
                        <option value="">All Buildings</option>
                    </select>
                </div>
                <div class="flex items-center gap-2 px-3 py-1.5 bg-[#1a2130] rounded-lg border border-border-dark">
                    <select id="filterFloor" class="bg-transparent border-none focus:ring-0 text-sm text-white">
                        <option value="">All Floors</option>
                    </select>
                </div>
                <div class="flex items-center gap-2 px-3 py-1.5 bg-[#1a2130] rounded-lg border border-border-dark">
                    <select id="filterStatus" class="bg-transparent border-none focus:ring-0 text-sm text-white">
                        <option value="">Status: Any</option>
                        <option value="Active">Active</option>
                        <option value="Maintenance">Maintenance</option>
                        <option value="Inactive">Inactive</option>
                    </select>
                </div>
                <div class="h-6 w-px bg-border-dark mx-1"></div>
                <button onclick="clearFilters()" class="text-xs font-semibold text-primary hover:underline">Clear all
                    filters</button>
            </div>

            <!-- Table Container -->
            <div
                class="flex-1 bg-background-dark border border-border-dark rounded-xl overflow-hidden flex flex-col shadow-2xl">
                <div class="overflow-x-auto flex-1 custom-scrollbar">
                    <table class="w-full text-left border-collapse table-auto">
                        <thead class="sticky top-0 bg-[#1a2130] z-10 border-b border-border-dark">
                            <tr>
                                <th
                                    class="px-2.5 py-3 text-[11px] font-bold text-[#9da6b9] uppercase tracking-wider whitespace-nowrap">
                                    Switch ID</th>
                                <th
                                    class="px-2.5 py-3 text-[11px] font-bold text-[#9da6b9] uppercase tracking-wider whitespace-nowrap">
                                    Model &amp; Manufacturer</th>
                                <th
                                    class="px-2.5 py-3 text-[11px] font-bold text-[#9da6b9] uppercase tracking-wider whitespace-nowrap">
                                    Serial Number</th>
                                <th
                                    class="px-2.5 py-3 text-[11px] font-bold text-[#9da6b9] uppercase tracking-wider whitespace-nowrap">
                                    IP &amp; MAC Address</th>
                                <th
                                    class="px-2.5 py-3 text-[11px] font-bold text-[#9da6b9] uppercase tracking-wider whitespace-nowrap">
                                    Placement</th>
                                <th
                                    class="px-2.5 py-3 text-[11px] font-bold text-[#9da6b9] uppercase tracking-wider whitespace-nowrap">
                                    Ports</th>
                                <th
                                    class="px-2.5 py-3 text-[11px] font-bold text-[#9da6b9] uppercase tracking-wider whitespace-nowrap">
                                    Status</th>
                                <th
                                    class="px-2.5 py-3 text-[11px] font-bold text-[#9da6b9] uppercase tracking-wider whitespace-nowrap">
                                    Personnel</th>
                                <th
                                    class="px-2.5 py-3 text-[11px] font-bold text-[#9da6b9] uppercase tracking-wider whitespace-nowrap">
                                    Maintenance</th>
                                <th
                                    class="px-2.5 py-3 text-[11px] font-bold text-[#9da6b9] uppercase tracking-wider whitespace-nowrap">
                                    Remarks</th>
                                <th
                                    class="px-2.5 py-3 text-[11px] font-bold text-[#9da6b9] uppercase tracking-wider whitespace-nowrap">
                                    History</th>
                                <th
                                    class="px-2.5 py-3 text-[11px] font-bold text-[#9da6b9] uppercase tracking-wider whitespace-nowrap text-right">
                                    Actions</th>
                            </tr>
                        </thead>
                        <tbody id="switchTableBody" class="divide-y divide-border-dark">
                            <?php foreach ($switches as $switch): ?>
                                <tr class="hover:bg-white/5 transition-colors group"
                                    data-switch-id="<?php echo $switch['id']; ?>">
                                    <td class="px-2.5 py-3 text-sm font-semibold text-primary">
                                        <?php echo htmlspecialchars($switch['switch_id']); ?>
                                    </td>
                                    <td class="px-2.5 py-3">
                                        <div class="flex flex-col">
                                            <span
                                                class="text-sm font-medium text-white"><?php echo htmlspecialchars($switch['model']); ?></span>
                                            <span
                                                class="text-[10px] text-[#9da6b9]"><?php echo htmlspecialchars($switch['manufacturer']); ?></span>
                                        </div>
                                    </td>
                                    <td class="px-2.5 py-3 text-xs mono-font text-[#9da6b9]">
                                        <?php echo htmlspecialchars($switch['serial']); ?>
                                    </td>
                                    <td class="px-2.5 py-3">
                                        <div class="flex flex-col gap-0.5">
                                            <span
                                                class="text-xs font-medium text-white mono-font"><?php echo htmlspecialchars($switch['ip']); ?></span>
                                            <span
                                                class="text-[10px] text-[#4e5666] mono-font uppercase"><?php echo htmlspecialchars($switch['mac']); ?></span>
                                        </div>
                                    </td>
                                    <td class="px-2.5 py-3">
                                        <div class="flex flex-col">
                                            <span
                                                class="text-xs text-white font-medium"><?php echo htmlspecialchars($switch['building_location']); ?></span>
                                            <span
                                                class="text-[10px] text-[#9da6b9] uppercase font-bold tracking-tight"><?php echo htmlspecialchars($switch['floor']); ?></span>
                                        </div>
                                    </td>
                                    <td class="px-2.5 py-3">
                                        <div class="flex flex-col">
                                            <span
                                                class="text-xs font-bold text-white"><?php echo htmlspecialchars($switch['ports']); ?></span>
                                            <span
                                                class="text-[10px] text-[#9da6b9]"><?php echo htmlspecialchars($switch['ports_detail']); ?></span>
                                        </div>
                                    </td>
                                    <td class="px-2.5 py-3">
                                        <?php echo getStatusBadge($switch['status']); ?>
                                    </td>
                                    <td class="px-2.5 py-3 text-xs text-[#9da6b9]">
                                        <?php echo htmlspecialchars($switch['personnel']); ?>
                                    </td>
                                    <td class="px-2.5 py-3">
                                        <div class="flex flex-col text-[10px]">
                                            <span class="text-[#4e5666]">Last:
                                                <?php echo htmlspecialchars($switch['last_maintenance']); ?></span>
                                            <?php echo formatMaintenanceDate($switch['next_maintenance']); ?>
                                        </div>
                                    </td>
                                    <td class="px-2.5 py-3 max-w-[120px]">
                                        <p class="text-xs text-[#9da6b9] truncate"
                                            title="<?php echo htmlspecialchars($switch['remarks']); ?>">
                                            <?php echo htmlspecialchars($switch['remarks']); ?>
                                        </p>
                                    </td>
                                    <td class="px-2.5 py-3">
                                        <div class="flex flex-col text-[9px] gap-0.5 whitespace-nowrap">
                                            <div class="flex items-center gap-1">
                                                <span class="text-[#4e5666] uppercase font-bold tracking-tighter">In:</span>
                                                <span
                                                    class="text-[#9da6b9]"><?php echo date('M d, Y', strtotime($switch['created_at'])); ?></span>
                                            </div>
                                            <div class="flex items-center gap-1">
                                                <span class="text-[#4e5666] uppercase font-bold tracking-tighter">Up:</span>
                                                <span
                                                    class="text-[#9da6b9]"><?php echo date('M d, Y H:i', strtotime($switch['updated_at'])); ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-2.5 py-3 text-right">
                                        <?php if (isAdmin()): ?>
                                            <div
                                                class="flex justify-end gap-1.5 opacity-0 group-hover:opacity-100 transition-opacity">
                                                <button onclick='editSwitch(<?php echo json_encode($switch); ?>)'
                                                    class="p-1 hover:bg-primary/20 text-[#9da6b9] hover:text-primary rounded transition-colors"><span
                                                        class="material-symbols-outlined text-lg">edit</span></button>
                                                <button
                                                    onclick="deleteSwitch(<?php echo $switch['id']; ?>, '<?php echo htmlspecialchars($switch['switch_id']); ?>')"
                                                    class="p-1 hover:bg-red-500/20 text-[#9da6b9] hover:text-red-500 rounded transition-colors"><span
                                                        class="material-symbols-outlined text-lg">delete</span></button>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination & Table Footer -->
                <div class="bg-[#1a2130] border-t border-border-dark px-6 py-4 flex items-center justify-between">
                    <p class="text-xs text-[#9da6b9]">Showing <span
                            id="showingCount"><?php echo $totalDevices; ?></span> of <span
                            id="totalCount"><?php echo $totalDevices; ?></span> devices</p>
                    <div class="flex items-center gap-2">
                        <button onclick="goToPage(1)" id="firstPageBtn"
                            class="p-1 text-[#4e5666] hover:text-white disabled:opacity-30">
                            <span class="material-symbols-outlined">first_page</span>
                        </button>
                        <button onclick="goToPage(currentPage - 1)" id="prevPageBtn"
                            class="p-1 text-[#4e5666] hover:text-white disabled:opacity-30">
                            <span class="material-symbols-outlined">chevron_left</span>
                        </button>
                        <div class="flex items-center gap-1 px-2" id="pageNumbers">
                            <button
                                class="size-7 flex items-center justify-center rounded bg-primary text-xs font-bold text-white">1</button>
                        </div>
                        <button onclick="goToPage(currentPage + 1)" id="nextPageBtn"
                            class="p-1 text-[#9da6b9] hover:text-white">
                            <span class="material-symbols-outlined">chevron_right</span>
                        </button>
                        <button onclick="goToPage(totalPages)" id="lastPageBtn"
                            class="p-1 text-[#9da6b9] hover:text-white">
                            <span class="material-symbols-outlined">last_page</span>
                        </button>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-xs text-[#9da6b9]">Rows per page:</span>
                        <select id="rowsPerPage" onchange="changePageSize(this.value)"
                            class="bg-background-dark border-border-dark text-white text-[10px] font-bold rounded p-1 focus:ring-primary focus:border-primary">
                            <option value="10" selected>10</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Add/Edit Switch Modal -->
    <div id="switchModal" class="modal">
        <div
            class="modal-content bg-background-dark border border-border-dark rounded-xl max-w-4xl w-full mx-4 max-h-[90vh] overflow-y-auto custom-scrollbar">
            <div class="p-6 border-b border-border-dark flex items-center justify-between">
                <div>
                    <h3 id="modalTitle" class="text-xl font-bold text-white">Add New Switch</h3>
                    <p class="text-sm text-[#9da6b9] mt-1">Enter the switch details below</p>
                </div>
                <button onclick="closeModal()" class="p-2 hover:bg-border-dark rounded-lg transition-colors">
                    <span class="material-symbols-outlined text-white">close</span>
                </button>
            </div>
            <form id="switchForm" class="p-6">
                <input type="hidden" id="switchDbId" name="id">

                <div class="grid grid-cols-2 gap-6">
                    <!-- Switch ID -->
                    <div>
                        <label class="block text-sm font-semibold text-white mb-2">Switch ID</label>
                        <input type="text" id="switchId" name="switch_id"
                            class="w-full bg-[#1a2130] border border-border-dark text-white text-sm rounded-lg px-4 py-2.5 focus:ring-primary focus:border-primary"
                            placeholder="SW-DC1-A01">
                    </div>

                    <!-- Model -->
                    <div>
                        <label class="block text-sm font-semibold text-white mb-2">Model</label>
                        <input type="text" id="model" name="model"
                            class="w-full bg-[#1a2130] border border-border-dark text-white text-sm rounded-lg px-4 py-2.5 focus:ring-primary focus:border-primary"
                            placeholder="Catalyst 9300">
                    </div>

                    <!-- Manufacturer -->
                    <div>
                        <label class="block text-sm font-semibold text-white mb-2">Manufacturer</label>
                        <input type="text" id="manufacturer" name="manufacturer" list="manufacturerList"
                            class="w-full bg-[#1a2130] border border-border-dark text-white text-sm rounded-lg px-4 py-2.5 focus:ring-primary focus:border-primary"
                            placeholder="Cisco Systems">
                        <datalist id="manufacturerList"></datalist>
                    </div>

                    <!-- Serial Number -->
                    <div>
                        <label class="block text-sm font-semibold text-white mb-2">Serial Number</label>
                        <input type="text" id="serial" name="serial"
                            class="w-full bg-[#1a2130] border border-border-dark text-white text-sm rounded-lg px-4 py-2.5 focus:ring-primary focus:border-primary mono-font"
                            placeholder="FOC2341X2LY">
                    </div>

                    <!-- IP Address -->
                    <div>
                        <label class="block text-sm font-semibold text-white mb-2">IP Address</label>
                        <input type="text" id="ip" name="ip"
                            class="w-full bg-[#1a2130] border border-border-dark text-white text-sm rounded-lg px-4 py-2.5 focus:ring-primary focus:border-primary mono-font"
                            placeholder="192.168.10.11">
                    </div>

                    <!-- MAC Address -->
                    <div>
                        <label class="block text-sm font-semibold text-white mb-2">MAC Address</label>
                        <input type="text" id="mac" name="mac"
                            class="w-full bg-[#1a2130] border border-border-dark text-white text-sm rounded-lg px-4 py-2.5 focus:ring-primary focus:border-primary mono-font uppercase"
                            placeholder="00:00:5E:00:53:AF">
                    </div>

                    <!-- Building Location -->
                    <div>
                        <label class="block text-sm font-semibold text-white mb-2">Building Location <span
                                class="text-red-500">*</span></label>
                        <select id="buildingLocation" name="building_location" required
                            class="w-full bg-[#1a2130] border border-border-dark text-white text-sm rounded-lg px-4 py-2.5 focus:ring-primary focus:border-primary">
                            <option value="">Select Building</option>
                        </select>
                    </div>

                    <!-- Floor -->
                    <div>
                        <label class="block text-sm font-semibold text-white mb-2">Floor <span
                                class="text-red-500">*</span></label>
                        <select id="floor" name="floor" required
                            class="w-full bg-[#1a2130] border border-border-dark text-white text-sm rounded-lg px-4 py-2.5 focus:ring-primary focus:border-primary">
                            <option value="">Select Floor</option>
                        </select>
                    </div>

                    <!-- Ports -->
                    <div>
                        <label class="block text-sm font-semibold text-white mb-2">Ports <span
                                class="text-red-500">*</span></label>
                        <select id="ports" name="ports" required
                            class="w-full bg-[#1a2130] border border-border-dark text-white text-sm rounded-lg px-4 py-2.5 focus:ring-primary focus:border-primary">
                            <option value="">Select Ports</option>
                        </select>
                    </div>

                    <!-- Port Details -->
                    <div>
                        <label class="block text-sm font-semibold text-white mb-2">Port Details</label>
                        <select id="portsDetail" name="ports_detail"
                            class="w-full bg-[#1a2130] border border-border-dark text-white text-sm rounded-lg px-4 py-2.5 focus:ring-primary focus:border-primary">
                            <option value="">Select Port Specs</option>
                        </select>
                    </div>

                    <!-- Status -->
                    <div>
                        <label class="block text-sm font-semibold text-white mb-2">Status <span
                                class="text-red-500">*</span></label>
                        <select id="status" name="status" required
                            class="w-full bg-[#1a2130] border border-border-dark text-white text-sm rounded-lg px-4 py-2.5 focus:ring-primary focus:border-primary">
                            <option value="Active">Active</option>
                            <option value="Maintenance">Maintenance</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>

                    <!-- Personnel -->
                    <div>
                        <label class="block text-sm font-semibold text-white mb-2">Personnel</label>
                        <input type="text" id="personnel" name="personnel"
                            class="w-full bg-[#1a2130] border border-border-dark text-white text-sm rounded-lg px-4 py-2.5 focus:ring-primary focus:border-primary"
                            placeholder="John Doe">
                    </div>

                    <!-- Last Maintenance -->
                    <div>
                        <label class="block text-sm font-semibold text-white mb-2">Last Maintenance</label>
                        <input type="date" id="lastMaintenance" name="last_maintenance"
                            class="w-full bg-[#1a2130] border border-border-dark text-white text-sm rounded-lg px-4 py-2.5 focus:ring-primary focus:border-primary">
                    </div>

                    <!-- Next Maintenance -->
                    <div>
                        <label class="block text-sm font-semibold text-white mb-2">Next Maintenance</label>
                        <input type="text" id="nextMaintenance" name="next_maintenance"
                            class="w-full bg-[#1a2130] border border-border-dark text-white text-sm rounded-lg px-4 py-2.5 focus:ring-primary focus:border-primary"
                            placeholder="2024-04-12 or TBD">
                    </div>

                    <!-- Remarks -->
                    <div class="col-span-2">
                        <label class="block text-sm font-semibold text-white mb-2">Remarks</label>
                        <textarea id="remarks" name="remarks" rows="2"
                            class="w-full bg-[#1a2130] border border-border-dark text-white text-sm rounded-lg px-4 py-2.5 focus:ring-primary focus:border-primary resize-none"
                            placeholder="Additional notes..."></textarea>
                    </div>

                    <!-- Record Info (Visible in Edit Mode) -->
                    <div id="recordInfo"
                        class="col-span-2 hidden bg-[#131926] p-4 rounded-lg flex items-center justify-between border border-border-dark/50">
                        <div class="flex items-center gap-4">
                            <div class="flex flex-col">
                                <span class="text-[10px] uppercase font-bold text-[#4e5666] tracking-wider">Date
                                    Added</span>
                                <span id="createdAtLabel" class="text-xs text-[#9da6b9]">N/A</span>
                            </div>
                            <div class="h-8 w-px bg-border-dark"></div>
                            <div class="flex flex-col">
                                <span class="text-[10px] uppercase font-bold text-[#4e5666] tracking-wider">Last
                                    Updated</span>
                                <span id="updatedAtLabel" class="text-xs text-[#9da6b9]">N/A</span>
                            </div>
                        </div>
                        <div
                            class="flex items-center gap-1.5 px-3 py-1 bg-primary/5 border border-primary/20 rounded-full">
                            <span class="material-symbols-outlined text-xs text-primary">verified</span>
                            <span class="text-[10px] font-bold text-primary uppercase">Sync Verified</span>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-3 mt-8 pt-6 border-t border-border-dark">
                    <button type="submit"
                        class="flex items-center gap-2 px-6 py-2.5 bg-primary text-white text-sm font-semibold rounded-lg hover:bg-primary/90 transition-all shadow-lg shadow-primary/20">
                        <span class="material-symbols-outlined text-lg">save</span>
                        <span id="submitButtonText">Add Switch</span>
                    </button>
                    <button type="button" onclick="closeModal()"
                        class="px-6 py-2.5 bg-border-dark text-white text-sm font-semibold rounded-lg hover:bg-[#3f4756] transition-all">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <!-- CSV Import Modal -->
    <div id="importModal" class="modal">
        <div class="modal-content bg-background-dark border border-border-dark rounded-xl max-w-lg w-full mx-4">
            <div class="p-6 border-b border-border-dark flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="size-10 rounded-lg bg-primary/10 flex items-center justify-center text-primary">
                        <span class="material-symbols-outlined">upload_file</span>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-white">Import Switch Data</h3>
                        <p class="text-xs text-[#9da6b9]">Bulk upload devices from a CSV file</p>
                    </div>
                </div>
                <button onclick="closeImportModal()" class="text-[#4e5666] hover:text-white transition-colors">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <div class="p-6">
                <div class="bg-primary/5 border border-primary/20 rounded-lg p-4 mb-6">
                    <h4 class="text-sm font-bold text-primary mb-2 flex items-center gap-2">
                        <span class="material-symbols-outlined text-sm">info</span>
                        Instructions
                    </h4>
                    <ul class="text-xs text-[#9da6b9] space-y-2 list-disc pl-4">
                        <li>Download the <a href="import_template.php"
                                class="text-primary hover:underline font-semibold">CSV Template</a> to get started.</li>
                        <li>Required fields: Switch ID, Model, Serial, IP, MAC.</li>
                        <li>Status must be one of: <b>Active, Maintenance, Inactive</b>.</li>
                        <li>Duplicates will be skipped and reported in the summary.</li>
                    </ul>
                </div>
                <form id="importForm">
                    <div class="mb-6">
                        <label class="block text-sm font-semibold text-white mb-2">Select CSV File</label>
                        <div class="relative group">
                            <input type="file" id="csvFile" name="file" accept=".csv" required
                                class="w-full bg-[#1a2130] border border-border-dark text-[#9da6b9] text-sm rounded-lg px-4 py-10 file:hidden cursor-pointer hover:border-primary transition-all text-center">
                            <div
                                class="absolute inset-0 pointer-events-none flex flex-col items-center justify-center gap-2">
                                <span
                                    class="material-symbols-outlined text-3xl text-[#4e5666] group-hover:text-primary transition-colors">cloud_upload</span>
                                <span class="text-xs" id="fileNameDisp">Click to browse or drag and drop</span>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 pt-6 border-t border-border-dark">
                        <button type="submit" id="importBtn"
                            class="flex-1 flex items-center justify-center gap-2 px-6 py-2.5 bg-primary text-white text-sm font-semibold rounded-lg hover:bg-primary/90 transition-all shadow-lg shadow-primary/20">
                            <span class="material-symbols-outlined text-lg">publish</span>
                            Start Import
                        </button>
                        <button type="button" onclick="closeImportModal()"
                            class="px-6 py-2.5 bg-border-dark text-white text-sm font-semibold rounded-lg hover:bg-[#3f4756] transition-all">
                            Cancel
                        </button>
                    </div>
                </form>

                <!-- Import Result Summary (Hidden by default) -->
                <div id="importResult" class="hidden mt-6 p-4 bg-background-dark border border-border-dark rounded-lg">
                    <h4 class="text-sm font-bold text-white mb-3 flex items-center gap-2">
                        <span class="material-symbols-outlined text-sm">analytics</span>
                        Import Summary
                    </h4>
                    <div class="grid grid-cols-3 gap-4 mb-4">
                        <div class="p-3 bg-[#131926] rounded-lg text-center">
                            <p class="text-[10px] text-[#9da6b9] uppercase font-bold">Total</p>
                            <p class="text-lg font-bold text-white" id="resTotal">0</p>
                        </div>
                        <div class="p-3 bg-green-500/10 rounded-lg text-center">
                            <p class="text-[10px] text-green-500 uppercase font-bold text-opacity-80">Success</p>
                            <p class="text-lg font-bold text-green-500" id="resSuccess">0</p>
                        </div>
                        <div class="p-3 bg-red-500/10 rounded-lg text-center">
                            <p class="text-[10px] text-red-500 uppercase font-bold text-opacity-80">Failed</p>
                            <p class="text-lg font-bold text-red-500" id="resFailed">0</p>
                        </div>
                    </div>
                    <div id="errorDetails" class="hidden">
                        <p class="text-[10px] font-bold text-red-500 uppercase mb-2">Error Details (First 10):</p>
                        <div class="text-[11px] text-[#9da6b9] bg-black/20 p-2 rounded max-h-32 overflow-y-auto space-y-1"
                            id="errorList">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content bg-background-dark border border-border-dark rounded-xl max-w-md w-full mx-4">
            <div class="p-6">
                <div class="flex items-start gap-4">
                    <div class="size-12 rounded-full bg-red-500/10 flex items-center justify-center flex-shrink-0">
                        <span class="material-symbols-outlined text-red-500 text-2xl">warning</span>
                    </div>
                    <div class="flex-1">
                        <h3 class="text-lg font-bold text-white mb-2">Delete Switch</h3>
                        <p class="text-sm text-[#9da6b9]">Are you sure you want to delete switch <span
                                id="deleteSwitchName" class="text-primary font-semibold"></span>? This action cannot be
                            undone.</p>
                    </div>
                </div>
                <div class="flex items-center gap-3 mt-6">
                    <button onclick="confirmDelete()"
                        class="flex-1 px-4 py-2.5 bg-red-500 text-white text-sm font-semibold rounded-lg hover:bg-red-600 transition-all">
                        Delete
                    </button>
                    <button onclick="closeDeleteModal()"
                        class="flex-1 px-4 py-2.5 bg-border-dark text-white text-sm font-semibold rounded-lg hover:bg-[#3f4756] transition-all">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        let currentFilters = {
            manufacturer: '',
            building_location: '',
            floor: '',
            status: '',
            search: ''
        };

        let currentPage = 1;
        let itemsPerPage = 10;
        let totalPages = 1;
        let totalRecords = 0;

        let deleteTargetId = null;

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function () {
            // Check for URL parameters
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('search')) {
                currentFilters.search = urlParams.get('search');
                document.getElementById('inventorySearch').value = currentFilters.search;
            } else if (urlParams.has('id')) {
                // If ID is provided, we can either search by ID or just load it
                currentFilters.search = urlParams.get('id'); // api handleList includes ID in search check usually
            }

            loadFilterOptions();
            setupFilterListeners();
            setupSearchListener();
            applyFilters(); // Initial load
        });

        // Load filter dropdown options
        async function loadFilterOptions() {
            try {
                const response = await fetch('api?action=filters');
                const data = await response.json();

                if (data.success) {
                    // Populate building dropdowns (Filter and Modal)
                    const filterBldgSelect = document.getElementById('filterBuilding');
                    const modalBldgSelect = document.getElementById('buildingLocation');
                    data.buildings.forEach(bldg => {
                        // Filter option
                        const optFilter = document.createElement('option');
                        optFilter.value = bldg;
                        optFilter.textContent = bldg;
                        filterBldgSelect.appendChild(optFilter);

                        // Modal option
                        const optModal = document.createElement('option');
                        optModal.value = bldg;
                        optModal.textContent = bldg;
                        modalBldgSelect.appendChild(optModal);
                    });

                    // Populate floor dropdowns (Filter and Modal)
                    const filterFloorSelect = document.getElementById('filterFloor');
                    const modalFloorSelect = document.getElementById('floor');
                    data.floors.forEach(fl => {
                        // Filter option
                        const optFilter = document.createElement('option');
                        optFilter.value = fl;
                        optFilter.textContent = fl;
                        filterFloorSelect.appendChild(optFilter);

                        // Modal option
                        const optModal = document.createElement('option');
                        optModal.value = fl;
                        optModal.textContent = fl;
                        modalFloorSelect.appendChild(optModal);
                    });

                    // Populate manufacturers (Filter and Datalist)
                    const manuSelect = document.getElementById('filterManufacturer');
                    const manuDatalist = document.getElementById('manufacturerList');
                    data.manufacturers.forEach(manu => {
                        // Filter option
                        const optFilter = document.createElement('option');
                        optFilter.value = manu;
                        optFilter.textContent = manu;
                        manuSelect.appendChild(optFilter);

                        // Datalist option
                        const optData = document.createElement('option');
                        optData.value = manu;
                        manuDatalist.appendChild(optData);
                    });

                    // Populate modal-only specifies
                    const portDetailModalSelect = document.getElementById('portsDetail');
                    data.port_details.forEach(detail => {
                        const option = document.createElement('option');
                        option.value = detail;
                        option.textContent = detail;
                        portDetailModalSelect.appendChild(option);
                    });

                    const portsModalSelect = document.getElementById('ports');
                    data.ports_list.forEach(ports => {
                        const option = document.createElement('option');
                        option.value = ports;
                        option.textContent = ports;
                        portsModalSelect.appendChild(option);
                    });
                }
            } catch (error) {
                console.error('Error loading filter options:', error);
            }
        }

        // Setup filter change listeners
        function setupFilterListeners() {
            document.getElementById('filterManufacturer').addEventListener('change', function () {
                currentFilters.manufacturer = this.value;
                applyFilters();
            });

            document.getElementById('filterBuilding').addEventListener('change', function () {
                currentFilters.building_location = this.value;
                applyFilters();
            });

            document.getElementById('filterFloor').addEventListener('change', function () {
                currentFilters.floor = this.value;
                applyFilters();
            });

            document.getElementById('filterStatus').addEventListener('change', function () {
                currentFilters.status = this.value;
                applyFilters();
            });
        }

        // Setup global search
        function setupSearchListener() {
            const searchInput = document.getElementById('globalSearch');
            let searchTimeout;

            searchInput.addEventListener('input', function () {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    currentFilters.search = this.value;
                    applyFilters();
                }, 300);
            });
        }

        // Apply filters and reload data
        async function applyFilters(resetPage = true) {
            if (resetPage) {
                currentPage = 1;
            }

            try {
                const params = new URLSearchParams({
                    action: 'list',
                    page: currentPage,
                    limit: itemsPerPage,
                    ...currentFilters
                });

                const response = await fetch(`api?${params}`);
                const data = await response.json();

                if (data.success) {
                    updateTable(data.switches);

                    // Update pagination info
                    if (data.pagination) {
                        totalPages = data.pagination.total_pages;
                        totalRecords = data.pagination.total_records;
                        updatePaginationUI();
                    }

                    document.getElementById('showingCount').textContent = data.count;
                    document.getElementById('totalCount').textContent = totalRecords;
                    document.getElementById('deviceCount').textContent = totalRecords;
                }
            } catch (error) {
                console.error('Error applying filters:', error);
            }
        }

        // Clear all filters
        function clearFilters() {
            document.getElementById('filterManufacturer').value = '';
            document.getElementById('filterBuilding').value = '';
            document.getElementById('filterFloor').value = '';
            document.getElementById('filterStatus').value = '';
            document.getElementById('globalSearch').value = '';

            currentFilters = {
                manufacturer: '',
                building_location: '',
                floor: '',
                status: '',
                search: ''
            };

            applyFilters();
        }

        // Update table with new data
        function updateTable(switches) {
            const tbody = document.getElementById('switchTableBody');
            tbody.innerHTML = '';

            switches.forEach(sw => {
                const row = createTableRow(sw);
                tbody.appendChild(row);
            });
        }

        // Create table row HTML
        function createTableRow(sw) {
            const tr = document.createElement('tr');
            tr.className = 'hover:bg-white/5 transition-colors group';
            tr.setAttribute('data-switch-id', sw.id);

            const statusBadges = {
                'Active': 'bg-emerald-500/10 text-emerald-500 border-emerald-500/20',
                'Maintenance': 'bg-amber-500/10 text-amber-500 border-amber-500/20',
                'Inactive': 'bg-red-500/10 text-red-500 border-red-500/20'
            };

            const nextMaintText = sw.next_maintenance === 'TODAY'
                ? '<span class="text-amber-500 font-bold">Next: TODAY</span>'
                : sw.next_maintenance === 'TBD'
                    ? '<span class="text-white font-medium">Next: TBD</span>'
                    : `<span class="text-white font-medium">Next: ${sw.next_maintenance}</span>`;

            tr.innerHTML = `
                <td class="px-2.5 py-3 text-sm font-semibold text-primary">${sw.switch_id}</td>
                <td class="px-2.5 py-3">
                    <div class="flex flex-col">
                        <span class="text-sm font-medium text-white">${sw.model}</span>
                        <span class="text-[10px] text-[#9da6b9]">${sw.manufacturer}</span>
                    </div>
                </td>
                <td class="px-2.5 py-3 text-xs mono-font text-[#9da6b9]">${sw.serial}</td>
                <td class="px-2.5 py-3">
                    <div class="flex flex-col gap-0.5">
                        <span class="text-xs font-medium text-white mono-font">${sw.ip}</span>
                        <span class="text-[10px] text-[#4e5666] mono-font uppercase">${sw.mac}</span>
                    </div>
                </td>
                <td class="px-2.5 py-3">
                    <div class="flex flex-col">
                        <span class="text-xs text-white font-medium">${sw.building_location}</span>
                        <span class="text-[10px] text-[#9da6b9] uppercase font-bold tracking-tight">${sw.floor}</span>
                    </div>
                </td>
                <td class="px-2.5 py-3">
                    <div class="flex flex-col">
                        <span class="text-xs font-bold text-white">${sw.ports}</span>
                        <span class="text-[10px] text-[#9da6b9]">${sw.ports_detail || ''}</span>
                    </div>
                </td>
                <td class="px-2.5 py-3">
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-tighter ${statusBadges[sw.status]} border">${sw.status}</span>
                </td>
                <td class="px-2.5 py-3 text-xs text-[#9da6b9]">${sw.personnel || ''}</td>
                <td class="px-2.5 py-3">
                    <div class="flex flex-col text-[10px]">
                        <span class="text-[#4e5666]">Last: ${sw.last_maintenance || 'N/A'}</span>
                        ${nextMaintText}
                    </div>
                </td>
                <td class="px-2.5 py-3 max-w-[120px]">
                    <p class="text-xs text-[#9da6b9] truncate" title="${sw.remarks || ''}">${sw.remarks || ''}</p>
                </td>
                <td class="px-2.5 py-3">
                    <div class="flex flex-col text-[9px] gap-0.5 whitespace-nowrap">
                        <div class="flex items-center gap-1">
                            <span class="text-[#4e5666] uppercase font-bold tracking-tighter">In:</span>
                            <span class="text-[#9da6b9]">${new Date(sw.created_at).toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' })}</span>
                        </div>
                        <div class="flex items-center gap-1">
                            <span class="text-[#4e5666] uppercase font-bold tracking-tighter">Up:</span>
                            <span class="text-[#9da6b9]">${new Date(sw.updated_at).toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })}</span>
                        </div>
                    </div>
                </td>
                <td class="px-2.5 py-3 text-right">
                    <div class="flex justify-end gap-1.5 opacity-0 group-hover:opacity-100 transition-opacity">
                        <button onclick='editSwitch(${JSON.stringify(sw)})' class="p-1 hover:bg-primary/20 text-[#9da6b9] hover:text-primary rounded transition-colors"><span class="material-symbols-outlined text-lg">edit</span></button>
                        <button onclick="deleteSwitch(${sw.id}, '${sw.switch_id}')" class="p-1 hover:bg-red-500/20 text-[#9da6b9] hover:text-red-500 rounded transition-colors"><span class="material-symbols-outlined text-lg">delete</span></button>
                    </div>
                </td>
            `;

            return tr;
        }

        // Open add modal
        function openAddModal() {
            document.getElementById('modalTitle').textContent = 'Add New Switch';
            document.getElementById('submitButtonText').textContent = 'Add Switch';
            document.getElementById('switchForm').reset();
            document.getElementById('switchDbId').value = '';

            // Hide record history
            document.getElementById('recordInfo').classList.add('hidden');

            document.getElementById('switchModal').classList.add('active');
        }

        // Edit switch
        function editSwitch(switchData) {
            document.getElementById('modalTitle').textContent = 'Edit Switch';
            document.getElementById('submitButtonText').textContent = 'Update Switch';

            document.getElementById('switchDbId').value = switchData.id;
            document.getElementById('switchId').value = switchData.switch_id;
            document.getElementById('model').value = switchData.model;
            document.getElementById('manufacturer').value = switchData.manufacturer;
            document.getElementById('serial').value = switchData.serial;
            document.getElementById('ip').value = switchData.ip;
            document.getElementById('mac').value = switchData.mac;
            document.getElementById('buildingLocation').value = switchData.building_location;
            document.getElementById('floor').value = switchData.floor;
            document.getElementById('ports').value = switchData.ports;
            document.getElementById('portsDetail').value = switchData.ports_detail || '';
            document.getElementById('status').value = switchData.status;
            document.getElementById('personnel').value = switchData.personnel || '';
            document.getElementById('lastMaintenance').value = switchData.last_maintenance || '';
            document.getElementById('nextMaintenance').value = switchData.next_maintenance || '';
            document.getElementById('remarks').value = switchData.remarks || '';

            // Show record history
            document.getElementById('recordInfo').classList.remove('hidden');
            document.getElementById('createdAtLabel').textContent = new Date(switchData.created_at).toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' });
            document.getElementById('updatedAtLabel').textContent = new Date(switchData.updated_at).toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });

            document.getElementById('switchModal').classList.add('active');
        }

        // Close modal
        function closeModal() {
            document.getElementById('switchModal').classList.remove('active');
        }

        // Handle form submission
        document.getElementById('switchForm').addEventListener('submit', async function (e) {
            e.preventDefault();

            const formData = new FormData(this);
            const data = Object.fromEntries(formData.entries());

            const isEdit = !!data.id;
            const action = isEdit ? 'update' : 'add';

            try {
                const response = await fetch(`api?action=${action}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(data)
                });

                const result = await response.json();

                if (result.success) {
                    closeModal();
                    applyFilters(); // Reload data
                    alert(isEdit ? 'Switch updated successfully!' : 'Switch added successfully!');
                } else {
                    alert('Error: ' + result.message);
                }
            } catch (error) {
                console.error('Error saving switch:', error);
                alert('An error occurred. Please try again.');
            }
        });

        // Delete switch
        function deleteSwitch(id, switchId) {
            deleteTargetId = id;
            document.getElementById('deleteSwitchName').textContent = switchId;
            document.getElementById('deleteModal').classList.add('active');
        }

        // Confirm delete
        async function confirmDelete() {
            if (!deleteTargetId) return;

            try {
                const response = await fetch('api?action=delete', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ id: deleteTargetId })
                });

                const result = await response.json();

                if (result.success) {
                    closeDeleteModal();
                    applyFilters(); // Reload data
                    alert('Switch deleted successfully!');
                } else {
                    alert('Error: ' + result.message);
                }
            } catch (error) {
                console.error('Error deleting switch:', error);
                alert('An error occurred. Please try again.');
            }
        }

        // Close delete modal
        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.remove('active');
            deleteTargetId = null;
        }

        // Export CSV
        function exportCSV() {
            const params = new URLSearchParams({
                ...currentFilters
            });
            window.location.href = `export.php?${params}`;
        }

        // Open import modal
        function openImportModal() {
            document.getElementById('importModal').classList.add('active');
            document.getElementById('importResult').classList.add('hidden');
            document.getElementById('importForm').reset();
            document.getElementById('fileNameDisp').textContent = 'Click to browse or drag and drop';
        }

        // Close import modal
        function closeImportModal() {
            document.getElementById('importModal').classList.remove('active');
        }

        // Handle file selection display
        document.getElementById('csvFile').addEventListener('change', function (e) {
            const fileName = e.target.files[0] ? e.target.files[0].name : 'Click to browse or drag and drop';
            document.getElementById('fileNameDisp').textContent = fileName;
        });

        // Handle import form submission
        document.getElementById('importForm').addEventListener('submit', async function (e) {
            e.preventDefault();

            const fileInput = document.getElementById('csvFile');
            if (!fileInput.files[0]) return;

            const formData = new FormData();
            formData.append('file', fileInput.files[0]);

            const importBtn = document.getElementById('importBtn');
            const originalBtnContent = importBtn.innerHTML;
            importBtn.disabled = true;
            importBtn.innerHTML = '<span class="material-symbols-outlined animate-spin">sync</span> Processing...';

            try {
                const response = await fetch('api?action=import', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    // Update UI with summary
                    document.getElementById('importResult').classList.remove('hidden');
                    document.getElementById('resTotal').textContent = result.summary.total;
                    document.getElementById('resSuccess').textContent = result.summary.success;
                    document.getElementById('resFailed').textContent = result.summary.failed;

                    if (result.summary.failed > 0) {
                        document.getElementById('errorDetails').classList.remove('hidden');
                        const errorList = document.getElementById('errorList');
                        errorList.innerHTML = result.summary.errors.map(err => `<p>• ${err}</p>`).join('');
                    } else {
                        document.getElementById('errorDetails').classList.add('hidden');
                    }

                    // Refresh table after a short delay if any were successful
                    if (result.summary.success > 0) {
                        setTimeout(() => applyFilters(), 1500);
                    }
                } else {
                    alert('Import Error: ' + result.message);
                }
            } catch (error) {
                console.error('Import Error:', error);
                alert('An error occurred during import. Please try again.');
            } finally {
                importBtn.disabled = false;
                importBtn.innerHTML = originalBtnContent;
            }
        });

        // Close modals on background click
        document.getElementById('importModal').addEventListener('click', function (e) {
            if (e.target === this) {
                closeImportModal();
            }
        });

        document.getElementById('switchModal').addEventListener('click', function (e) {
            if (e.target === this) {
                closeModal();
            }
        });

        document.getElementById('deleteModal').addEventListener('click', function (e) {
            if (e.target === this) {
                closeDeleteModal();
            }
        });

        // Pagination functions
        function goToPage(page) {
            if (page < 1 || page > totalPages) return;
            currentPage = page;
            applyFilters(false);
        }

        function changePageSize(newSize) {
            itemsPerPage = parseInt(newSize);
            currentPage = 1;
            applyFilters(false);
        }

        function updatePaginationUI() {
            // Update page buttons state
            const firstBtn = document.getElementById('firstPageBtn');
            const prevBtn = document.getElementById('prevPageBtn');
            const nextBtn = document.getElementById('nextPageBtn');
            const lastBtn = document.getElementById('lastPageBtn');

            firstBtn.disabled = currentPage === 1;
            prevBtn.disabled = currentPage === 1;
            nextBtn.disabled = currentPage === totalPages;
            lastBtn.disabled = currentPage === totalPages;

            // Update page numbers
            const pageNumbersDiv = document.getElementById('pageNumbers');
            pageNumbersDiv.innerHTML = '';

            // Show max 5 page numbers
            let startPage = Math.max(1, currentPage - 2);
            let endPage = Math.min(totalPages, currentPage + 2);

            if (endPage - startPage < 4) {
                if (startPage === 1) {
                    endPage = Math.min(5, totalPages);
                } else if (endPage === totalPages) {
                    startPage = Math.max(1, totalPages - 4);
                }
            }

            for (let i = startPage; i <= endPage; i++) {
                const btn = document.createElement('button');
                btn.textContent = i;
                btn.onclick = () => goToPage(i);
                btn.className = 'size-7 flex items-center justify-center rounded text-xs font-bold ' +
                    (i === currentPage ? 'bg-primary text-white' : 'text-[#9da6b9] hover:bg-border-dark hover:text-white');
                pageNumbersDiv.appendChild(btn);
            }
        }
    </script>
</body>

</html>