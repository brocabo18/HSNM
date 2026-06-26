<?php
/**
 * Main Dashboard
 * High-level overview with data visualizations
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

// Fetch summary data for the dashboard
try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM switches");
    $totalDevices = $stmt->fetch()['total'];
} catch (Exception $e) {
    error_log("Dashboard Error: " . $e->getMessage());
    $totalDevices = 0;
}
?>
<!DOCTYPE html>
<html class="dark" lang="en">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>Network Switch Dashboard - Overview</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap"
        rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
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
            <a class="flex items-center gap-3 px-3 py-2.5 bg-primary/10 text-primary rounded-lg" href="index.php">
                <span class="material-symbols-outlined text-[20px]">dashboard</span>
                <span class="text-sm font-semibold">Dashboard</span>
            </a>
            <a class="flex items-center gap-3 px-3 py-2.5 text-[#9da6b9] hover:text-white transition-colors"
                href="dashboard.php">
                <span class="material-symbols-outlined text-[20px]">database</span>
                <span class="text-sm font-medium">Inventory</span>
            </a>
            <div class="pt-4 pb-2 px-3">
                <p class="text-[10px] uppercase font-bold text-[#4e5666] tracking-widest">Administration</p>
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
                    <p class="text-[10px] text-[#9da6b9] truncate">
                        <?php echo htmlspecialchars($currentUser['role']); ?>
                    </p>
                </div>
                <span
                    class="material-symbols-outlined text-[#9da6b9] text-lg group-hover:text-red-400 transition-colors">logout</span>
            </a>
        </div>
    </aside>

    <!-- Main Content Area -->
    <main class="flex-1 flex flex-col h-screen overflow-y-auto bg-[#0a0c12] custom-scrollbar">
        <?php
        $pageTitle = "Network Dashboard Overview";
        include 'header_partial.php';
        ?>


        <div class="p-8">
            <div class="flex items-center justify-between mb-8">
                <div>
                    <h2 class="text-2xl font-bold text-white tracking-tight">System Insights</h2>
                    <p class="text-sm text-[#9da6b9] mt-1">Real-time visualization of <span
                            class="text-primary font-bold">
                            <?php echo $totalDevices; ?>
                        </span> managed switches</p>
                </div>
                <div class="flex gap-4">
                    <div
                        class="bg-background-dark border border-border-dark px-6 py-3 rounded-xl flex items-center gap-4 shadow-xl">
                        <div class="bg-green-500/10 p-2 rounded-lg"><span
                                class="material-symbols-outlined text-green-500">check_circle</span></div>
                        <div>
                            <p class="text-[10px] text-[#4e5666] font-bold uppercase">Uptime</p>
                            <p class="text-lg font-bold text-white leading-tight">99.9%</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Statistics Charts Dashboard -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pb-8">
                <!-- Status Distribution Card -->
                <div class="bg-background-dark border border-border-dark rounded-xl p-6 shadow-2xl">
                    <h3 class="text-lg font-bold text-white mb-6 flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary">pie_chart</span>
                        Status Distribution
                    </h3>
                    <div class="h-64">
                        <canvas id="statusChart"></canvas>
                    </div>
                </div>

                <!-- Manufacturer Distribution Card -->
                <div class="bg-background-dark border border-border-dark rounded-xl p-6 shadow-2xl">
                    <h3 class="text-lg font-bold text-white mb-6 flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary">bar_chart</span>
                        Top Manufacturers
                    </h3>
                    <div class="h-64">
                        <canvas id="manufacturerChart"></canvas>
                    </div>
                </div>

                <!-- Location Distribution Card -->
                <div class="bg-background-dark border border-border-dark rounded-xl p-6 shadow-2xl">
                    <h3 class="text-lg font-bold text-white mb-6 flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary">location_on</span>
                        Locations Breakdown
                    </h3>
                    <div class="h-64">
                        <canvas id="locationChart"></canvas>
                    </div>
                </div>

                <!-- Maintenance Timeline Card -->
                <div class="bg-background-dark border border-border-dark rounded-xl p-6 shadow-2xl">
                    <h3 class="text-lg font-bold text-white mb-6 flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary">schedule</span>
                        Upcoming Maintenance
                    </h3>
                    <div class="h-64">
                        <canvas id="maintenanceChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        let charts = {};

        document.addEventListener('DOMContentLoaded', function () {
            initializeCharts();
        });

        async function initializeCharts() {
            try {
                const response = await fetch('api?action=statistics');
                const data = await response.json();

                if (data.success) {
                    createStatusChart(data.status_distribution);
                    createManufacturerChart(data.manufacturer_distribution);
                    createLocationChart(data.location_distribution);
                    createMaintenanceChart(data.maintenance_timeline);
                }
            } catch (error) {
                console.error('Error loading statistics:', error);
            }
        }

        function createStatusChart(statusData) {
            const ctx = document.getElementById('statusChart').getContext('2d');
            const labels = statusData.map(item => item.status);
            const counts = statusData.map(item => item.count);
            const colors = { 'Active': '#10b981', 'Maintenance': '#f59e0b', 'Inactive': '#ef4444' };

            charts.status = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: counts,
                        backgroundColor: labels.map(label => colors[label] || '#6b7280'),
                        borderColor: '#1a2130',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom', labels: { color: '#9da6b9', font: { size: 12 }, padding: 20 } }
                    }
                }
            });
        }

        function createManufacturerChart(manufacturerData) {
            const ctx = document.getElementById('manufacturerChart').getContext('2d');
            charts.manufacturer = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: manufacturerData.map(item => item.manufacturer),
                    datasets: [{ label: 'Switches', data: manufacturerData.map(item => item.count), backgroundColor: '#135bec', borderRadius: 6 }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: { beginAtZero: true, ticks: { color: '#9da6b9' }, grid: { color: '#282e39' } },
                        x: { ticks: { color: '#9da6b9' }, grid: { display: false } }
                    },
                    plugins: { legend: { display: false } }
                }
            });
        }

        function createLocationChart(locationData) {
            const ctx = document.getElementById('locationChart').getContext('2d');
            charts.location = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: locationData.map(item => item.location),
                    datasets: [{ label: 'Switches', data: locationData.map(item => item.count), backgroundColor: '#8b5cf6', borderRadius: 6 }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: { beginAtZero: true, ticks: { color: '#9da6b9' }, grid: { color: '#282e39' } },
                        y: { ticks: { color: '#9da6b9' }, grid: { display: false } }
                    },
                    plugins: { legend: { display: false } }
                }
            });
        }

        function createMaintenanceChart(maintenanceData) {
            const ctx = document.getElementById('maintenanceChart').getContext('2d');
            charts.maintenance = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: maintenanceData.map(item => item.period),
                    datasets: [{ label: 'Switches', data: maintenanceData.map(item => item.count), borderColor: '#f59e0b', backgroundColor: 'rgba(245, 158, 11, 0.1)', tension: 0.4, fill: true }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: { beginAtZero: true, ticks: { color: '#9da6b9' }, grid: { color: '#282e39' } },
                        x: { ticks: { color: '#9da6b9' }, grid: { display: false } }
                    },
                    plugins: { legend: { display: false } }
                }
            });
        }
        // Global Search & Notifications Logic
        const globalSearch = document.getElementById('globalSearch');
        const searchResults = document.getElementById('searchResults');
        const searchResultsList = document.getElementById('searchResultsList');
        let searchTimeout;

        globalSearch.addEventListener('input', (e) => {
            clearTimeout(searchTimeout);
            const q = e.target.value.trim();
            if (q.length < 2) {
                searchResults.classList.add('hidden');
                return;
            }

            searchTimeout = setTimeout(async () => {
                try {
                    const response = await fetch(`api?action=search&q=${encodeURIComponent(q)}`);
                    const data = await response.json();
                    if (data.success) {
                        renderSearchResults(data.results);
                    }
                } catch (err) { console.error('Search error:', err); }
            }, 300);
        });

        function renderSearchResults(results) {
            searchResultsList.innerHTML = '';
            if (results.length === 0) {
                searchResultsList.innerHTML = '<div class="p-4 text-center text-xs text-[#4e5666]">No switches found</div>';
            } else {
                results.forEach(item => {
                    const div = document.createElement('div');
                    div.className = 'p-3 hover:bg-primary/10 rounded-lg transition-colors cursor-pointer group mb-1';
                    div.innerHTML = `
                        <div class="flex items-center gap-3">
                            <div class="size-8 rounded bg-primary/20 flex items-center justify-center text-primary">
                                <span class="material-symbols-outlined text-lg">router</span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-bold text-white truncate">${item.switch_id}</p>
                                <p class="text-[10px] text-[#9da6b9] truncate">${item.model} • ${item.ip}</p>
                            </div>
                            <span class="material-symbols-outlined text-[#4e5666] group-hover:text-primary transition-colors">arrow_forward</span>
                        </div>
                    `;
                    div.onclick = () => window.location.href = `dashboard?id=${item.id}`;
                    searchResultsList.appendChild(div);
                });
            }
            searchResults.classList.remove('hidden');
        }

        // Close search on click outside
        document.addEventListener('click', (e) => {
            if (!globalSearch.contains(e.target) && !searchResults.contains(e.target)) {
                searchResults.classList.add('hidden');
            }
        });

        // Notifications Logic
        const notificationBtn = document.getElementById('notificationBtn');
        const notificationDropdown = document.getElementById('notificationDropdown');
        const notificationList = document.getElementById('notificationList');
        const notifBadge = document.getElementById('notifBadge');

        notificationBtn.addEventListener('click', () => {
            notificationDropdown.classList.toggle('hidden');
            if (!notificationDropdown.classList.contains('hidden')) {
                loadNotifications();
            }
        });

        async function loadNotifications() {
            try {
                const response = await fetch('api?action=notifications_list');
                const data = await response.json();
                if (data.success) {
                    renderNotifications(data.notifications);
                }
            } catch (err) { console.error('Notif load error:', err); }
        }

        function renderNotifications(notifs) {
            if (notifs.length === 0) {
                notificationList.innerHTML = `
                    <div class="p-8 text-center text-[#4e5666]">
                        <span class="material-symbols-outlined text-4xl mb-2 opacity-20">notifications_paused</span>
                        <p class="text-xs">No unread notifications</p>
                    </div>`;
                notifBadge.classList.add('hidden');
                return;
            }

            notifBadge.textContent = notifs.length;
            notifBadge.classList.remove('hidden');

            notificationList.innerHTML = '';
            notifs.forEach(n => {
                const div = document.createElement('div');
                div.className = 'p-4 hover:bg-white/5 border-b border-border-dark/50 last:border-0 transition-colors relative group';
                const icon = n.type === 'warning' ? 'warning' : 'info';
                const color = n.type === 'warning' ? 'text-amber-500' : 'text-primary';

                div.innerHTML = `
                    <div class="flex gap-3">
                        <span class="material-symbols-outlined ${color} text-xl mt-0.5">${icon}</span>
                        <div class="flex-1">
                            <p class="text-xs font-bold text-white mb-1">${n.title}</p>
                            <p class="text-[10px] text-[#9da6b9] leading-normal mb-2">${n.message}</p>
                            <p class="text-[9px] text-[#4e5666] font-medium">${new Date(n.created_at).toLocaleString()}</p>
                        </div>
                        <button class="size-6 rounded-lg opacity-0 group-hover:opacity-100 hover:bg-white/10 flex items-center justify-center text-[#4e5666] hover:text-white transition-all">
                            <span class="material-symbols-outlined text-sm">check</span>
                        </button>
                    </div>
                `;

                div.querySelector('button').onclick = async (e) => {
                    e.stopPropagation();
                    await markAsRead(n.id);
                };

                notificationList.appendChild(div);
            });
        }

        async function markAsRead(id) {
            try {
                await fetch('api?action=notifications_read', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id })
                });
                loadNotifications();
            } catch (err) { console.error('Mark read error:', err); }
        }

        // Initial check
        loadNotifications();
        setInterval(loadNotifications, 30000); // Check every 30s
    </script>
</body>

</html>