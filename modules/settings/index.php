<?php
require_once '../../config.php';
requireLogin();

// Check admin access
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: " . BASE_URL . "/index");
    exit;
}

$success_msg = '';
$error_msg = '';

// Handle User Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_user') {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        $email = $_POST['email'] ?? '';
        $full_name = $_POST['full_name'] ?? '';
        $full_name = $_POST['full_name'] ?? '';
        $role = $_POST['role'] ?? 'viewer';
        $module_access = isset($_POST['modules']) ? json_encode($_POST['modules']) : null;

        if ($username && $password) {
            // Check Duplicates
            $check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ?");
            $check->execute([$username, $email]);
            if ($check->fetchColumn() > 0) {
                $error_msg = "Error: Username or Email already exists.";
            } else {
                try {
                    $hashed = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO users (username, password, email, full_name, role, module_access) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$username, $hashed, $email, $full_name, $role, $module_access]);
                    $new_user_id = $pdo->lastInsertId();
                    logAudit($pdo, 'create_user', "Created user | Username: $username | Full Name: $full_name | Email: $email | Role: $role", 'user', $new_user_id);
                    $success_msg = "User created successfully.";
                } catch (Exception $e) {
                    $error_msg = "Error creating user: " . $e->getMessage();
                }
            }
        }
    } elseif ($_POST['action'] === 'edit_user') {
        $id = $_POST['user_id'] ?? 0;
        $username = $_POST['username'] ?? '';
        $email = $_POST['email'] ?? '';
        $full_name = $_POST['full_name'] ?? '';
        $role = $_POST['role'] ?? 'viewer';
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $module_access = isset($_POST['modules']) ? json_encode($_POST['modules']) : null;

        if ($id) {
            // Check Duplicates (excluding current ID)
            $check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE (username = ? OR email = ?) AND id != ?");
            $check->execute([$username, $email, $id]);
            if ($check->fetchColumn() > 0) {
                $error_msg = "Error: Username or Email already exists.";
            } else {
                try {
                    $stmt = $pdo->prepare("UPDATE users SET username=?, email=?, full_name=?, role=?, is_active=?, module_access=? WHERE id=?");
                    $stmt->execute([$username, $email, $full_name, $role, $is_active, $module_access, $id]);
                    logAudit($pdo, 'update_user', "Updated user | ID: $id | Username: $username | Full Name: $full_name | Email: $email | Role: $role | Active: $is_active", 'user', $id);
                    $success_msg = "User updated successfully.";
                } catch (Exception $e) {
                    $error_msg = "Error updating user: " . $e->getMessage();
                }
            }
        }
    } elseif ($_POST['action'] === 'delete_user') {
        $id = $_POST['user_id'] ?? 0;
        if ($id && $id != $_SESSION['user_id']) { // Can't delete self
            try {
                $snap = $pdo->prepare("SELECT username, full_name, email, role FROM users WHERE id = ?");
                $snap->execute([$id]);
                $del_user = $snap->fetch(PDO::FETCH_ASSOC);
                $del_usr_detail = "Deleted User ID $id";
                if ($del_user) {
                    $del_usr_detail = "Deleted User | ID: $id | Username: {$del_user['username']} | Full Name: {$del_user['full_name']} | Email: {$del_user['email']} | Role: {$del_user['role']}";
                }
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$id]);
                logAudit($pdo, 'delete_user', $del_usr_detail, 'user', $id);
                $success_msg = "User deleted successfully.";
            } catch (Exception $e) {
                $error_msg = "Error deleting user: " . $e->getMessage();
            }
        } else {
            $error_msg = "Cannot delete your own account.";
        }
    } elseif ($_POST['action'] === 'reset_password') {
        $id = $_POST['user_id'] ?? 0;
        $new_password = $_POST['new_password'] ?? '';

        if ($id && $new_password) {
            try {
                $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password=? WHERE id=?");
                $stmt->execute([$hashed, $id]);
                logAudit($pdo, 'reset_password', "Password reset for user ID $id", 'user', $id);
                $success_msg = "Password reset successfully.";
            } catch (Exception $e) {
                $error_msg = "Error resetting password: " . $e->getMessage();
            }
        }
    }
}

// Fetch Users
$users = $pdo->query("SELECT * FROM users ORDER BY created_at DESC")->fetchAll();

$page_title = "Settings";
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
?>

<div class="p-4 md:p-8 flex-1 overflow-y-auto custom-scrollbar relative z-10 bg-slate-50 dark:bg-background-dark">
    <header
        class="-mt-4 -mx-4 md:-mt-8 md:-mx-8 px-4 md:px-8 pt-4 md:pt-8 pb-6 mb-8 flex flex-col md:flex-row items-start md:items-end justify-between gap-4 sticky top-0 bg-slate-50 dark:bg-background-dark z-20 border-b border-slate-200 dark:border-[#232b3d]">
        <div>
            <h2 class="text-2xl font-bold text-slate-900 dark:text-white">Settings</h2>
            <p class="text-sm text-slate-500 mt-1">Manage users and system configuration.</p>
        </div>
        <button onclick="toggleModal('addUserModal')"
            class="flex items-center justify-center rounded-lg h-9 px-4 bg-primary text-white hover:bg-primary/90 transition-colors text-xs font-bold shadow-lg shadow-primary/20">
            <span class="material-symbols-outlined text-[18px] mr-2">person_add</span> Add User
        </button>
    </header>

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

    <!-- User Management Table -->
    <div
        class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] rounded-2xl overflow-hidden shadow-sm">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-[#232b3d]">
            <h3 class="font-bold text-slate-900 dark:text-white text-sm">User Management</h3>
        </div>
        <div class="overflow-x-auto custom-scrollbar">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-white dark:bg-[#1a2130] border-b border-slate-200 dark:border-[#232b3d]">
                        <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase">Username</th>
                        <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase">Full Name</th>
                        <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase">Email</th>
                        <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase">Role</th>
                        <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase">Status</th>
                        <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase">Last Login</th>
                        <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#232b3d]/50">
                    <?php foreach ($users as $user): ?>
                        <tr class="hover:bg-white/5 transition-colors">
                            <td class="px-6 py-4 text-sm font-medium text-slate-900 dark:text-white">
                                <?= htmlspecialchars($user['username']) ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-400">
                                <?= htmlspecialchars($user['full_name'] ?? '-') ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-400">
                                <?= htmlspecialchars($user['email'] ?? '-') ?>
                            </td>
                            <td class="px-6 py-4">
                                <span class="px-2 py-1 bg-primary/10 text-primary rounded text-xs font-bold uppercase">
                                    <?= htmlspecialchars($user['role']) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <?php if ($user['is_active']): ?>
                                    <span
                                        class="px-2 py-1 bg-emerald-500/10 text-emerald-500 rounded text-xs font-bold">Active</span>
                                <?php else: ?>
                                    <span class="px-2 py-1 bg-red-500/10 text-red-500 rounded text-xs font-bold">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-500 font-mono">
                                <?= $user['last_login'] ? date('M d, Y H:i', strtotime($user['last_login'])) : 'Never' ?>
                            </td>
                            <td class="px-6 py-4 text-right flex items-center justify-end gap-2">
                                <button onclick='openEditModal(<?= json_encode($user) ?>)'
                                    class="p-2 hover:bg-primary/10 hover:text-primary text-slate-500 rounded-lg"><span
                                        class="material-symbols-outlined text-[20px]">edit</span></button>
                                <button onclick='openPasswordModal(<?= $user["id"] ?>)'
                                    class="p-2 hover:bg-blue-500/10 hover:text-blue-500 text-slate-500 rounded-lg"
                                    title="Reset Password"><span
                                        class="material-symbols-outlined text-[20px]">key</span></button>
                                <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                    <form method="POST" onsubmit="return confirm('Delete user?');">
                                        <input type="hidden" name="action" value="delete_user">
                                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                        <button
                                            class="p-2 hover:bg-red-500/10 hover:text-red-500 text-slate-500 rounded-lg"><span
                                                class="material-symbols-outlined text-[20px]">delete</span></button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add User Modal -->
<div id="addUserModal" class="hidden fixed inset-0 z-50 overflow-y-auto" role="dialog">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-background-dark/80 backdrop-blur-sm" onclick="toggleModal('addUserModal')"></div>
        <div
            class="relative bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] rounded-2xl max-w-lg w-full p-4 sm:p-6 shadow-2xl">
            <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-6">Add User</h3>
            <form method="POST">
                <input type="hidden" name="action" value="add_user">
                <div class="space-y-4">
                    <input type="text" name="username" placeholder="Username" required
                        class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                    <input type="email" name="email" placeholder="Email"
                        class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                    <input type="text" name="full_name" placeholder="Full Name"
                        class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                    <input type="password" name="password" placeholder="Password" required
                        class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                    <select name="role"
                        class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                        <option value="viewer">Viewer</option>
                        <option value="editor">Editor</option>
                        <option value="admin">Admin</option>
                    </select>

                    <div class="space-y-2">
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Module
                            Access</label>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            <?php
                            $available_modules = [
                                'routers'        => 'Routers',
                                'switches'       => 'Switches',
                                'ips'            => 'IP Addresses',
                                'computers'      => 'Computers',
                                'firewall'       => 'Firewall',
                                'office'         => 'MS Office',
                                'reconciliation' => 'Compare & Sync',
                                'ics'            => 'ICS Inventory',
                                'pabx'           => 'PABX Directory',
                                'ihoms_links'    => 'IS Links',
                                'printers'       => 'Printers',
                                'queueing_tv'    => 'Queueing TV',
                                'logs'           => 'Audit Logs',
                                'changelog'      => 'Changelog',
                                'reports'        => 'Reports',
                            ];
                            foreach ($available_modules as $key => $label): ?>
                                <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-400">
                                    <input type="checkbox" name="modules[]" value="<?= $key ?>" checked
                                        class="rounded bg-slate-50 dark:bg-[#101622] border-slate-200 dark:border-[#232b3d] text-primary focus:ring-primary">
                                    <?= $label ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" onclick="toggleModal('addUserModal')"
                        class="px-4 py-2 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white">Cancel</button>
                    <button type="submit" class="px-6 py-2 bg-primary text-white font-bold rounded-xl">Create</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div id="editUserModal" class="hidden fixed inset-0 z-50 overflow-y-auto" role="dialog">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-background-dark/80 backdrop-blur-sm" onclick="toggleModal('editUserModal')"></div>
        <div
            class="relative bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] rounded-2xl max-w-lg w-full p-4 sm:p-6 shadow-2xl">
            <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-6">Edit User</h3>
            <form method="POST">
                <input type="hidden" name="action" value="edit_user">
                <input type="hidden" name="user_id" id="edit_user_id">
                <div class="space-y-4">
                    <input type="text" name="username" id="edit_username" required
                        class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                    <input type="email" name="email" id="edit_email"
                        class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                    <input type="text" name="full_name" id="edit_full_name"
                        class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                    <select name="role" id="edit_role"
                        class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                        <option value="viewer">Viewer</option>
                        <option value="editor">Editor</option>
                        <option value="admin">Admin</option>
                    </select>

                    <div class="space-y-2">
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Module
                            Access</label>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            <?php foreach ($available_modules as $key => $label): ?>
                                <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-400">
                                    <input type="checkbox" name="modules[]" value="<?= $key ?>" id="edit_module_<?= $key ?>"
                                        class="rounded bg-slate-50 dark:bg-[#101622] border-slate-200 dark:border-[#232b3d] text-primary focus:ring-primary">
                                    <?= $label ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                        <input type="checkbox" name="is_active" id="edit_is_active"
                            class="rounded bg-slate-50 dark:bg-[#101622] border-slate-200 dark:border-[#232b3d] text-primary focus:ring-primary">
                        <span>Active</span>
                    </label>
                </div>
                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" onclick="toggleModal('editUserModal')"
                        class="px-4 py-2 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white">Cancel</button>
                    <button type="submit" class="px-6 py-2 bg-primary text-white font-bold rounded-xl">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reset Password Modal -->
<div id="passwordModal" class="hidden fixed inset-0 z-50 overflow-y-auto" role="dialog">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-background-dark/80 backdrop-blur-sm" onclick="toggleModal('passwordModal')"></div>
        <div
            class="relative bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] rounded-2xl max-w-md w-full p-4 sm:p-6 shadow-2xl">
            <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-6">Reset Password</h3>
            <form method="POST">
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" name="user_id" id="password_user_id">
                <div class="space-y-4">
                    <input type="password" name="new_password" placeholder="New Password" required
                        class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                </div>
                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" onclick="toggleModal('passwordModal')"
                        class="px-4 py-2 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white">Cancel</button>
                    <button type="submit" class="px-6 py-2 bg-primary text-white font-bold rounded-xl">Reset</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>


    function openEditModal(user) {
        document.getElementById('edit_user_id').value = user.id;
        document.getElementById('edit_username').value = user.username;
        document.getElementById('edit_email').value = user.email || '';
        document.getElementById('edit_full_name').value = user.full_name || '';
        document.getElementById('edit_role').value = user.role;
        document.getElementById('edit_is_active').checked = user.is_active == 1;
        document.getElementById('edit_is_active').checked = user.is_active == 1;

        // Reset all checkboxes
        document.querySelectorAll('[id^="edit_module_"]').forEach(el => el.checked = false);

        // Check allowed modules
        if (user.module_access) {
            let modules = [];
            try {
                modules = JSON.parse(user.module_access);
            } catch (e) {
                // Handle case where it might not be valid JSON (though it should be)
                console.error("Invalid JSON for module_access", e);
            }

            if (Array.isArray(modules)) {
                modules.forEach(mod => {
                    const el = document.getElementById('edit_module_' + mod);
                    if (el) el.checked = true;
                });
            }
        } else {
            // If null, assume all checked for backward compatibility or none? 
            // Plan said "Default value: NULL (implies access to all or default modules)"
            // Let's verify standard behavior. If null, maybe we should check all? 
            // For now, let's leave unchecked or checked based on preference. 
            // Actually, if it's a new feature, existing users have NULL. 
            // Better to DEFAULT TO ALL for existing users to avoid locking them out.
            document.querySelectorAll('[id^="edit_module_"]').forEach(el => el.checked = true);
        }

        toggleModal('editUserModal');
    }

    function openPasswordModal(userId) {
        document.getElementById('password_user_id').value = userId;
        toggleModal('passwordModal');
    }
</script>

<?php require_once '../../includes/footer.php'; ?>