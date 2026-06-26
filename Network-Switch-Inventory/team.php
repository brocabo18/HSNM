<?php
/**
 * User Registration
 * Manage users, roles, and access
 */

session_start();
require_once 'config.php';
require_once 'auth.php';

// Require authentication
requireAuth();

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
    <title>User Registration - <?php echo htmlspecialchars($portalName); ?></title>
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

        .modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.7);
            z-index: 50;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(4px);
        }

        .modal.active {
            display: flex;
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
                <h1 id="sidebarPortalName" class="text-white text-base font-bold leading-none">
                    <?php echo htmlspecialchars($portalName); ?>
                </h1>
                <p class="text-[#9da6b9] text-xs mt-1"><?php echo htmlspecialchars($portalSubtitle); ?></p>
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
            <a class="flex items-center gap-3 px-3 py-2.5 bg-primary/10 text-primary rounded-lg" href="team">
                <span class="material-symbols-outlined text-[20px]">group</span>
                <span class="text-sm font-semibold">Team Access</span>
            </a>
            <a class="flex items-center gap-3 px-3 py-2.5 text-[#9da6b9] hover:text-white transition-colors"
                href="logs">
                <span class="material-symbols-outlined text-[20px]">history</span>
                <span class="text-sm font-medium">Audit Logs</span>
            </a>
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

    <!-- Main Content -->
    <main class="flex-1 flex flex-col h-screen overflow-hidden bg-[#0a0c12]">
        <?php
        $pageTitle = "User Registration";
        $extraHeaderContent = '
            <button onclick="openUserModal()" class="flex items-center gap-2 px-4 py-2 bg-primary text-white text-sm font-semibold rounded-lg hover:bg-primary/90 transition-all shadow-lg shadow-primary/20">
                <span class="material-symbols-outlined text-lg">person_add</span>
                Register New User
            </button>';
        include "header_partial.php";
        ?>

        <div class="p-8 flex-1 overflow-y-auto custom-scrollbar">
            <!-- User Table -->
            <div class="bg-background-dark border border-border-dark rounded-xl overflow-hidden shadow-2xl">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-[#1a2130] border-b border-border-dark">
                        <tr>
                            <th class="px-6 py-4 text-[11px] font-bold text-[#9da6b9] uppercase tracking-wider">User
                                Profile</th>
                            <th class="px-6 py-4 text-[11px] font-bold text-[#9da6b9] uppercase tracking-wider">Role
                            </th>
                            <th class="px-6 py-4 text-[11px] font-bold text-[#9da6b9] uppercase tracking-wider">Last
                                Activity</th>
                            <th class="px-6 py-4 text-[11px] font-bold text-[#9da6b9] uppercase tracking-wider">Created
                            </th>
                            <th class="px-6 py-4 text-[11px] font-bold text-[#9da6b9] uppercase tracking-wider">Status
                            </th>
                            <th
                                class="px-6 py-4 text-[11px] font-bold text-[#9da6b9] uppercase tracking-wider text-right">
                                Actions</th>
                        </tr>
                    </thead>
                    <tbody id="userTableBody" class="divide-y divide-border-dark">
                        <!-- Users will be loaded here -->
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- User Modal -->
    <div id="userModal" class="modal">
        <div
            class="modal-content bg-background-dark border border-border-dark rounded-xl max-w-lg w-full mx-4 shadow-2xl overflow-hidden">
            <div class="p-6 border-b border-border-dark flex items-center justify-between bg-[#1a2130]">
                <h3 id="modalTitle" class="text-lg font-bold text-white">Register New User</h3>
                <button onclick="closeUserModal()" class="text-[#9da6b9] hover:text-white transition-colors">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <form id="userForm" class="p-6">
                <input type="hidden" id="userId" name="id">
                <div class="grid grid-cols-2 gap-4">
                    <div class="col-span-2">
                        <label class="block text-sm font-semibold text-white mb-2">Username</label>
                        <input type="text" id="username" name="username" required
                            class="w-full bg-[#1a2130] border border-border-dark text-white rounded-lg px-4 py-2.5 focus:ring-primary focus:border-primary"
                            placeholder="jdoe">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-white mb-2">Full Name</label>
                        <input type="text" id="fullName" name="full_name" required
                            class="w-full bg-[#1a2130] border border-border-dark text-white rounded-lg px-4 py-2.5 focus:ring-primary focus:border-primary"
                            placeholder="John Doe">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-white mb-2">Email Address</label>
                        <input type="email" id="email" name="email"
                            class="w-full bg-[#1a2130] border border-border-dark text-white rounded-lg px-4 py-2.5 focus:ring-primary focus:border-primary"
                            placeholder="john@example.com">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-white mb-2">System Role</label>
                        <select id="role" name="role" required
                            class="w-full bg-[#1a2130] border border-border-dark text-white rounded-lg px-4 py-2.5 focus:ring-primary focus:border-primary">
                            <option value="Viewer">Viewer</option>
                            <option value="Admin">Admin</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-white mb-2">Account Password</label>
                        <input type="password" id="password" name="password"
                            class="w-full bg-[#1a2130] border border-border-dark text-white rounded-lg px-4 py-2.5 focus:ring-primary focus:border-primary"
                            placeholder="••••••••">
                        <p id="passwordHint" class="text-[10px] text-[#4e5666] mt-1 italic hidden">Leave blank to keep
                            current password</p>
                    </div>
                    <div class="col-span-2 flex items-center gap-3 mt-4">
                        <button type="submit"
                            class="flex-1 px-6 py-2.5 bg-primary text-white font-semibold rounded-lg hover:bg-primary/90 transition-all">Save
                            User</button>
                        <button type="button" onclick="closeUserModal()"
                            class="px-6 py-2.5 bg-border-dark text-white font-semibold rounded-lg hover:bg-[#3f4756] transition-all">Cancel</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', loadUsers);

        async function loadUsers() {
            try {
                const response = await fetch('api?action=users_list');
                const data = await response.json();
                if (data.success) {
                    renderUsers(data.users);
                }
            } catch (err) { console.error('Error loading users:', err); }
        }

        function renderUsers(users) {
            const tbody = document.getElementById('userTableBody');
            tbody.innerHTML = '';
            users.forEach(user => {
                const tr = document.createElement('tr');
                tr.className = 'hover:bg-white/5 transition-colors group';
                const statusBadge = user.is_active == 1
                    ? '<span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-tighter bg-emerald-500/10 text-emerald-500 border border-emerald-500/20">Active</span>'
                    : '<span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-tighter bg-red-500/10 text-red-500 border border-red-500/20">Deactivated</span>';

                tr.innerHTML = `
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-3">
                            <div class="size-9 rounded-full bg-primary/20 flex items-center justify-center text-primary font-bold text-sm">
                                ${user.full_name.charAt(0).toUpperCase()}
                            </div>
                            <div class="flex flex-col">
                                <span class="text-sm font-bold text-white">${user.full_name}</span>
                                <span class="text-[10px] text-[#9da6b9]">${user.username} • ${user.email || 'No email'}</span>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4">
                        <span class="text-xs font-semibold px-2 py-0.5 bg-[#1a2130] rounded border border-border-dark">${user.role}</span>
                    </td>
                    <td class="px-6 py-4 text-xs text-[#9da6b9]">${user.last_login || 'Never logged in'}</td>
                    <td class="px-6 py-4 text-xs text-[#9da6b9]">${new Date(user.created_at).toLocaleDateString()}</td>
                    <td class="px-6 py-4">${statusBadge}</td>
                    <td class="px-6 py-4 text-right">
                        <div class="flex justify-end gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                            <button onclick='editUser(${JSON.stringify(user)})' class="p-1.5 hover:bg-primary/20 text-[#9da6b9] hover:text-primary rounded transition-all">
                                <span class="material-symbols-outlined text-lg">edit</span>
                            </button>
                            ${user.id != <?php echo $currentUser['id']; ?> ? `
                                <button onclick="toggleUserStatus(${user.id}, ${user.is_active})" class="p-1.5 hover:bg-amber-500/20 text-[#9da6b9] hover:text-amber-500 rounded transition-all" title="${user.is_active == 1 ? 'Deactivate' : 'Activate'} User">
                                    <span class="material-symbols-outlined text-lg">${user.is_active == 1 ? 'person_off' : 'person'}</span>
                                </button>
                                <button onclick="deleteUser(${user.id})" class="p-1.5 hover:bg-red-500/20 text-[#9da6b9] hover:text-red-500 rounded transition-all" title="Delete User">
                                    <span class="material-symbols-outlined text-lg">delete</span>
                                </button>
                            ` : ''}
                        </div>
                    </td>
                `;
                tbody.appendChild(tr);
            });
        }

        function openUserModal() {
            document.getElementById('modalTitle').textContent = 'Register New User';
            document.getElementById('userForm').reset();
            document.getElementById('userId').value = '';
            document.getElementById('password').required = true;
            document.getElementById('passwordHint').classList.add('hidden');
            document.getElementById('userModal').classList.add('active');
        }

        function editUser(user) {
            document.getElementById('modalTitle').textContent = 'Modify User Profile';
            document.getElementById('userId').value = user.id;
            document.getElementById('username').value = user.username;
            document.getElementById('fullName').value = user.full_name;
            document.getElementById('email').value = user.email || '';
            document.getElementById('role').value = user.role;
            document.getElementById('password').required = false;
            document.getElementById('password').value = '';
            document.getElementById('passwordHint').classList.remove('hidden');
            document.getElementById('userModal').classList.add('active');
        }

        function closeUserModal() {
            document.getElementById('userModal').classList.remove('active');
        }

        document.getElementById('userForm').addEventListener('submit', async function (e) {
            e.preventDefault();
            const formData = new FormData(this);
            const isEdit = !!formData.get('id');
            const action = isEdit ? 'user_update' : 'user_add';

            try {
                const response = await fetch(`api?action=${action}`, {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                if (data.success) {
                    closeUserModal();
                    loadUsers();
                } else {
                    alert(data.message);
                }
            } catch (err) { console.error('Form error:', err); }
        });

        async function toggleUserStatus(id, currentStatus) {
            if (!confirm(`Are you sure you want to ${currentStatus == 1 ? 'deactivate' : 'activate'} this user?`)) return;

            try {
                const formData = new FormData();
                formData.append('id', id);
                formData.append('is_active', currentStatus == 1 ? 0 : 1);

                const response = await fetch('api?action=user_update', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                if (data.success) loadUsers();
            } catch (err) { console.error('Toggle error:', err); }
        }

        async function deleteUser(id) {
            if (!confirm('Are you sure you want to permanently delete this user? This action cannot be undone.')) return;

            try {
                const formData = new FormData();
                formData.append('id', id);

                const response = await fetch('api?action=user_delete', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                if (data.success) {
                    loadUsers();
                } else {
                    alert(data.message);
                }
            } catch (err) { console.error('Delete error:', err); }
        }
    </script>
</body>

</html>