<?php
/**
 * System and Personal Settings
 * Manage portal configuration and user profile
 */

session_start();
require_once 'config.php';
require_once 'auth.php';

$pdo = getDBConnection();
$appSettings = getSystemSettings($pdo);
$portalName = $appSettings['portal_name'] ?? 'Network Switch Inventory';
$primaryColor = $appSettings['theme_color'] ?? '#135bec';

// Require authentication
requireAuth();

$currentUser = getCurrentUser();
$isAdmin = isAdmin();

$portalSubtitle = $appSettings['portal_subtitle'] ?? 'IHOMS';
?>
<!DOCTYPE html>
<html class="dark" lang="en">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>Login - <?php echo htmlspecialchars($portalName); ?></title>
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
                        "background-dark": "#101622",
                        "background-light": "#f6f6f8",
                        "border-dark": "#282e39"
                    },
                    fontFamily: {
                        "display": ["Inter", "sans-serif"]
                    }
                },
            },
        }
    </script>
</head>

<body class="bg-background-dark font-display text-slate-100 min-h-screen flex overflow-hidden">
    <!-- Sidebar Navigation -->
    <aside class="w-64 border-r border-border-dark bg-background-dark flex flex-col h-screen flex-shrink-0">
        <div class="p-6 flex items-center gap-3">
            <div class="bg-primary size-10 rounded-lg flex items-center justify-center text-white">
                <span class="material-symbols-outlined">router</span>
            </div>
            <div class="flex flex-col">
                <h1 id="sidebarPortalName" class="text-white text-base font-bold leading-none">
                    <?php echo htmlspecialchars($portalName); ?>
                </h1>
                <p id="sidebarPortalSubtitle" class="text-[#9da6b9] text-xs mt-1">
                    <?php echo htmlspecialchars($portalSubtitle); ?>
                </p>
            </div>
        </div>
        <nav class="flex-1 px-4 space-y-2 mt-4">
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
                <a class="flex items-center gap-3 px-3 py-2 text-[#9da6b9] hover:text-white transition-colors" href="logs">
                    <span class="material-symbols-outlined text-[20px]">history</span>
                    <span class="text-sm font-medium">Audit Logs</span>
                </a>
            <?php endif; ?>
            <a class="flex items-center gap-3 px-3 py-2.5 bg-primary/10 text-primary rounded-lg" href="settings">
                <span class="material-symbols-outlined text-[20px]">settings</span>
                <span class="text-sm font-semibold">Settings</span>
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
    <main class="flex-1 flex flex-col h-screen overflow-y-auto bg-[#0a0c12] custom-scrollbar">
        <?php
        $pageTitle = "System Settings";
        include 'header_partial.php';
        ?>

        <div class="p-8 max-w-4xl">
            <div class="space-y-8">
                <!-- Personal Profile Section -->
                <section class="bg-background-dark border border-border-dark rounded-xl overflow-hidden shadow-xl">
                    <div class="p-6 border-b border-border-dark bg-[#1a2130]">
                        <h3 class="text-base font-bold text-white flex items-center gap-2">
                            <span class="material-symbols-outlined text-primary">person</span>
                            Personal Profile
                        </h3>
                        <p class="text-xs text-[#9da6b9] mt-1">Update your account information and password.</p>
                    </div>
                    <form id="profileForm" class="p-6 space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-white mb-2">Full Name</label>
                                <input type="text" name="full_name"
                                    value="<?php echo htmlspecialchars($currentUser['full_name']); ?>" required
                                    class="w-full bg-[#1a2130] border border-border-dark text-white rounded-lg px-4 py-2 focus:ring-primary focus:border-primary">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-white mb-2">Email Address</label>
                                <input type="email" name="email"
                                    value="<?php echo htmlspecialchars($currentUser['email'] ?? ''); ?>"
                                    class="w-full bg-[#1a2130] border border-border-dark text-white rounded-lg px-4 py-2 focus:ring-primary focus:border-primary">
                            </div>
                            <div class="col-span-2">
                                <label class="block text-sm font-semibold text-white mb-2">Change Password</label>
                                <input type="password" name="password"
                                    placeholder="New Password (leave blank to keep current)"
                                    class="w-full bg-[#1a2130] border border-border-dark text-white rounded-lg px-4 py-2 focus:ring-primary focus:border-primary">
                            </div>
                        </div>
                        <div class="pt-4">
                            <button type="submit"
                                class="px-6 py-2 bg-primary text-white font-semibold rounded-lg hover:bg-primary/90 transition-all shadow-lg shadow-primary/20">Update
                                Profile</button>
                        </div>
                    </form>
                </section>

                <!-- System Config Section (Admin Only) -->
                <?php if ($isAdmin): ?>
                    <section class="bg-background-dark border border-border-dark rounded-xl overflow-hidden shadow-xl">
                        <div class="p-6 border-b border-border-dark bg-[#1a2130]">
                            <h3 class="text-base font-bold text-white flex items-center gap-2">
                                <span class="material-symbols-outlined text-primary">settings_applications</span>
                                System Configuration
                            </h3>
                            <p class="text-xs text-[#9da6b9] mt-1">Manage portal-wide branding and global parameters.</p>
                        </div>
                        <form id="systemForm" class="p-6 space-y-4">
                            <div class="grid grid-cols-2 gap-6">
                                <div class="col-span-2 grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-semibold text-white mb-2">Portal Display
                                            Name</label>
                                        <input type="text" name="settings[portal_name]" id="portalNameInput" required
                                            class="w-full bg-[#1a2130] border border-border-dark text-white rounded-lg px-4 py-2 focus:ring-primary focus:border-primary">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-semibold text-white mb-2">Portal Subtitle
                                            (Tagline)</label>
                                        <input type="text" name="settings[portal_subtitle]" id="portalSubtitleInput"
                                            required
                                            class="w-full bg-[#1a2130] border border-border-dark text-white rounded-lg px-4 py-2 focus:ring-primary focus:border-primary">
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold text-white mb-2">Branding Color</label>
                                    <div class="flex gap-3">
                                        <input type="color" name="settings[theme_color]" id="themeColorInput"
                                            class="size-10 bg-transparent border-none p-0 cursor-pointer">
                                        <input type="text" id="themeColorHex" readonly
                                            class="flex-1 bg-black/20 border-border-dark text-[#9da6b9] text-xs font-mono rounded px-3 py-2 cursor-default">
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold text-white mb-2">Maintenance Cycle
                                        (Days)</label>
                                    <input type="number" name="settings[maintenance_interval]" id="maintIntervalInput"
                                        class="w-full bg-[#1a2130] border border-border-dark text-white rounded-lg px-4 py-2 focus:ring-primary focus:border-primary"
                                        min="1" max="365">
                                </div>
                            </div>
                            <div class="pt-4">
                                <button type="submit"
                                    class="px-6 py-2 bg-emerald-600 text-white font-semibold rounded-lg hover:bg-emerald-500 transition-all shadow-lg shadow-emerald-500/20">Apply
                                    Global Changes</button>
                            </div>
                        </form>
                    </section>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', loadSettings);

        async function loadSettings() {
            try {
                const response = await fetch('api?action=settings_get');
                const data = await response.json();
                if (data.success && data.settings) {
                    const s = data.settings;
                    if (document.getElementById('portalNameInput')) {
                        document.getElementById('portalNameInput').value = s.portal_name || 'Network Switch Inventory';
                        document.getElementById('portalSubtitleInput').value = s.portal_subtitle || 'IHOMS';
                        document.getElementById('themeColorInput').value = s.theme_color || '#135bec';
                        document.getElementById('themeColorHex').value = s.theme_color || '#135bec';
                        document.getElementById('maintIntervalInput').value = s.maintenance_interval || '180';
                        document.getElementById('sidebarPortalName').textContent = s.portal_name || 'Network Switch Inventory';
                        document.getElementById('sidebarPortalSubtitle').textContent = s.portal_subtitle || 'IHOMS';
                    }
                }
            } catch (err) { console.error('Settings load error:', err); }
        }

        if (document.getElementById('themeColorInput')) {
            document.getElementById('themeColorInput').oninput = e => {
                document.getElementById('themeColorHex').value = e.target.value.toUpperCase();
            };
        }

        // Profile Update
        document.getElementById('profileForm').onsubmit = async (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);
            formData.append('id', <?php echo $currentUser['id']; ?>);
            formData.append('role', '<?php echo $currentUser['role']; ?>'); // Don't allow changing role here

            try {
                const response = await fetch('api?action=user_update', { method: 'POST', body: formData });
                const res = await response.json();
                if (res.success) alert('Profile updated successfully!');
                else alert(res.message);
            } catch (err) { console.error(err); }
        };

        // System Config Update
        if (document.getElementById('systemForm')) {
            document.getElementById('systemForm').onsubmit = async (e) => {
                e.preventDefault();
                const formData = new FormData(e.target);

                try {
                    const response = await fetch('api?action=settings_save', { method: 'POST', body: formData });
                    const res = await response.json();
                    if (res.success) {
                        alert('System settings applied! Page will reload to apply branding.');
                        location.reload();
                    } else alert(res.message);
                } catch (err) { console.error(err); }
            };
        }
    </script>
</body>

</html>