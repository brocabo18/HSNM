<?php
/**
 * Audit Logs View
 * View system change history
 */

session_start();
require_once 'config.php';
require_once 'auth.php';

// Require Admin authentication
requireAdmin();

$pdo = getDBConnection();
$currentUser = getCurrentUser();
$isAdmin = isAdmin();

// Fetch system settings
$appSettings = getSystemSettings($pdo);
$portalName = $appSettings['portal_name'] ?? 'Network Switch Inventory';
$portalSubtitle = $appSettings['portal_subtitle'] ?? 'IHOMS';
$primaryColor = $appSettings['theme_color'] ?? '#135bec';
?>
<!DOCTYPE html>
<html class="dark" lang="en">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>Audit Logs -
        <?php echo htmlspecialchars($portalName); ?>
    </title>
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
    <aside class="w-64 border-r border-border-dark bg-background-dark flex flex-col h-screen flex-shrink-0">
        <div class="p-6 flex items-center gap-3">
            <div class="bg-primary size-10 rounded-lg flex items-center justify-center text-white">
                <span class="material-symbols-outlined">router</span>
            </div>
            <div class="flex flex-col">
                <h1 class="text-white text-base font-bold leading-none">
                    <?php echo htmlspecialchars($portalName); ?>
                </h1>
                <p class="text-[#9da6b9] text-xs mt-1">
                    <?php echo htmlspecialchars($portalSubtitle); ?>
                </p>
            </div>
        </div>
        <nav class="flex-1 px-4 space-y-1 mt-4">
            <a class="flex items-center gap-3 px-3 py-2 text-[#9da6b9] hover:text-white transition-colors" href="index">
                <span class="material-symbols-outlined text-[20px]">dashboard</span>
                <span class="text-sm font-medium">Dashboard</span>
            </a>
            <a class="flex items-center gap-3 px-3 py-2 text-[#9da6b9] hover:text-white transition-colors"
                href="dashboard">
                <span class="material-symbols-outlined text-[20px]">database</span>
                <span class="text-sm font-medium">Inventory</span>
            </a>

            <div class="pt-4 pb-2 px-3">
                <p class="text-[10px] uppercase font-bold text-[#4e5666] tracking-widest">Administration</p>
            </div>

            <?php if ($isAdmin): ?>
                <a class="flex items-center gap-3 px-3 py-2 text-[#9da6b9] hover:text-white transition-colors" href="team">
                    <span class="material-symbols-outlined text-[20px]">group</span>
                    <span class="text-sm font-medium">Team Access</span>
                </a>
                <a class="flex items-center gap-3 px-3 py-2 bg-primary/10 text-primary rounded-lg" href="logs">
                    <span class="material-symbols-outlined text-[20px]">history</span>
                    <span class="text-sm font-semibold">Audit Logs</span>
                </a>
            <?php endif; ?>

            <a class="flex items-center gap-3 px-3 py-2 text-[#9da6b9] hover:text-white transition-colors"
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
                    <p class="text-[10px] text-[#9da6b9] truncate">
                        <?php echo htmlspecialchars($currentUser['role']); ?>
                    </p>
                </div>
                <span
                    class="material-symbols-outlined text-[#9da6b9] text-lg group-hover:text-red-400 transition-colors">logout</span>
            </a>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 flex flex-col h-screen overflow-hidden bg-[#0a0c12]">
        <?php
        $pageTitle = "System Audit Logs";
        include 'header_partial.php';
        ?>

        <div class="p-8 flex-1 overflow-y-auto custom-scrollbar">
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-bold text-white">Action History</h2>
                    <p class="text-sm text-[#9da6b9] mt-1">Detailed log of all system modifications and access events.
                    </p>
                </div>
                <button onclick="loadLogs()"
                    class="p-2 bg-[#1a2130] border border-border-dark rounded-lg hover:bg-border-dark transition-colors group">
                    <span class="material-symbols-outlined text-lg text-[#9da6b9] group-hover:text-white">refresh</span>
                </button>
            </div>

            <div class="bg-background-dark border border-border-dark rounded-xl overflow-hidden shadow-2xl">
                <div class="overflow-x-auto custom-scrollbar">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-[#1a2130] border-b border-border-dark">
                                <th class="px-6 py-4 text-[10px] uppercase font-bold text-[#4e5666] tracking-widest">
                                    Timestamp</th>
                                <th class="px-6 py-4 text-[10px] uppercase font-bold text-[#4e5666] tracking-widest">
                                    User</th>
                                <th class="px-6 py-4 text-[10px] uppercase font-bold text-[#4e5666] tracking-widest">
                                    Action</th>
                                <th class="px-6 py-4 text-[10px] uppercase font-bold text-[#4e5666] tracking-widest">
                                    Resource</th>
                                <th class="px-6 py-4 text-[10px] uppercase font-bold text-[#4e5666] tracking-widest">
                                    Details</th>
                                <th
                                    class="px-6 py-4 text-[10px] uppercase font-bold text-[#4e5666] tracking-widest text-right">
                                    IP Address</th>
                            </tr>
                        </thead>
                        <tbody id="logsTableBody" class="divide-y divide-border-dark/50">
                            <!-- JS populated -->
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center text-[#4e5666]">
                                    <div class="flex flex-col items-center gap-3">
                                        <div
                                            class="size-12 border-4 border-primary/20 border-t-primary rounded-full animate-spin">
                                        </div>
                                        <p class="text-xs font-medium">Synchronizing audit data...</p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', loadLogs);

        async function loadLogs() {
            try {
                const response = await fetch('api?action=audit_logs');
                const data = await response.json();
                if (data.success) {
                    renderLogs(data.logs);
                } else {
                    console.error('Failed to load logs:', data.message);
                }
            } catch (error) {
                console.error('Error fetching logs:', error);
            }
        }

        function renderLogs(logs) {
            const tableBody = document.getElementById('logsTableBody');
            tableBody.innerHTML = '';

            if (logs.length === 0) {
                tableBody.innerHTML = '<tr><td colspan="6" class="px-6 py-12 text-center text-[#4e5666] text-xs">No audit logs found</td></tr>';
                return;
            }

            logs.forEach(log => {
                const tr = document.createElement('tr');
                tr.className = 'hover:bg-white/5 transition-colors group';

                let actionBadge = '';
                switch (log.action) {
                    case 'CREATE':
                        actionBadge = '<span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-emerald-500/10 text-emerald-500 border border-emerald-500/20">CREATE</span>';
                        break;
                    case 'UPDATE':
                    case 'IMPORT':
                        actionBadge = '<span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-amber-500/10 text-amber-500 border border-amber-500/20">' + log.action + '</span>';
                        break;
                    case 'DELETE':
                        actionBadge = '<span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-red-500/10 text-red-500 border border-red-500/20">DELETE</span>';
                        break;
                    case 'LOGIN':
                    case 'LOGOUT':
                        actionBadge = '<span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-blue-500/10 text-blue-500 border border-blue-500/20">' + log.action + '</span>';
                        break;
                    default:
                        actionBadge = '<span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-slate-500/10 text-slate-400 border border-slate-500/20">' + log.action + '</span>';
                }

                const timestamp = new Date(log.created_at).toLocaleString('en-PH', {
                    month: 'short',
                    day: '2-digit',
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit'
                });

                tr.innerHTML = `
                    <td class="px-6 py-4 text-xs font-medium text-[#9da6b9] whitespace-nowrap">${timestamp}</td>
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-2">
                             <div class="size-6 rounded-full bg-primary/20 flex items-center justify-center text-primary text-[10px] font-bold">
                                ${log.user_name.substring(0, 1)}
                            </div>
                            <span class="text-xs font-bold text-white">${log.user_name}</span>
                        </div>
                    </td>
                    <td class="px-6 py-4">${actionBadge}</td>
                    <td class="px-6 py-4 text-xs text-[#9da6b9] font-medium tracking-tight">${log.resource_type} ${log.resource_id ? `#${log.resource_id}` : ''}</td>
                    <td class="px-6 py-4 text-xs text-white max-w-xs truncate" title="${log.details || ''}">${log.details || '-'}</td>
                    <td class="px-6 py-4 text-right text-[10px] font-mono text-[#4e5666] tracking-tighter">${log.ip_address}</td>
                `;
                tableBody.appendChild(tr);
            });
        }
    </script>
</body>

</html>