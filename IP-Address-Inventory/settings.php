<?php
require_once 'config/config.php';
require_once 'includes/auth_check.php';

// Only admins can access settings
if (!isAdmin()) {
    redirect('index.php');
}

$error = '';
$success = '';

// Handle user creation/update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'create_user') {
        $username = sanitizeInput($_POST['username']);
        $email = sanitizeInput($_POST['email']);
        $fullName = sanitizeInput($_POST['full_name']);
        $password = $_POST['password'];
        $role = sanitizeInput($_POST['role']);

        // Validate
        if (strlen($password) < PASSWORD_MIN_LENGTH) {
            $error = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters';
        } else {
            // Check if username exists
            $checkStmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $checkStmt->execute([$username, $email]);

            if ($checkStmt->fetch()) {
                $error = 'Username or email already exists';
            } else {
                try {
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("
                        INSERT INTO users (username, email, password, full_name, role, is_active)
                        VALUES (?, ?, ?, ?, ?, 1)
                    ");
                    $stmt->execute([$username, $email, $hashedPassword, $fullName, $role]);

                    logAudit('create', 'user', $pdo->lastInsertId(), "Created user: $username");
                    $success = 'User created successfully';
                } catch (PDOException $e) {
                    $error = 'Error creating user: ' . $e->getMessage();
                }
            }
        }
    } elseif ($action === 'update_user') {
        $id = (int) $_POST['user_id'];
        $fullName = sanitizeInput($_POST['full_name']);
        $email = sanitizeInput($_POST['email']);
        $role = sanitizeInput($_POST['role']);
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        try {
            $stmt = $pdo->prepare("
                UPDATE users 
                SET full_name = ?, email = ?, role = ?, is_active = ?
                WHERE id = ? AND id != 1
            ");
            $stmt->execute([$fullName, $email, $role, $isActive, $id]);

            logAudit('update', 'user', $id, "Updated user settings");
            $success = 'User updated successfully';
        } catch (PDOException $e) {
            $error = 'Error updating user: ' . $e->getMessage();
        }
    } elseif ($action === 'delete_user') {
        $id = (int) $_POST['user_id'];

        // Prevent deleting admin user
        if ($id === 1) {
            $error = 'Cannot delete default admin user';
        } else {
            try {
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$id]);

                logAudit('delete', 'user', $id, "Deleted user");
                $success = 'User deleted successfully';
            } catch (PDOException $e) {
                $error = 'Error deleting user: ' . $e->getMessage();
            }
        }
    }
}

// Get all users
$usersStmt = $pdo->query("SELECT * FROM users ORDER BY id ASC");
$users = $usersStmt->fetchAll();
?>
<!DOCTYPE html>
<html class="dark" lang="en">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>Settings - IP Manager</title>
    <link
        href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=Noto+Sans:wght@400;500;600;700&display=swap"
        rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap"
        rel="stylesheet" />
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
    </style>
</head>

<body class="bg-background-dark text-white font-display min-h-screen p-6">
    <div class="w-full max-w-6xl mx-auto">
        <!-- Header -->
        <div class="mb-6">
            <a href="index.php"
                class="inline-flex items-center gap-2 text-[#9dabb9] hover:text-white transition-colors mb-4">
                <span class="material-symbols-outlined">arrow_back</span>
                Back to Dashboard
            </a>
            <h1 class="text-white text-3xl font-bold mb-2">System Settings</h1>
            <p class="text-[#9dabb9]">Manage users and system configuration</p>
        </div>

        <?php if ($error): ?>
            <div class="mb-6 p-4 bg-red-500/10 border border-red-500/20 rounded-lg flex items-start gap-3">
                <span class="material-symbols-outlined text-red-500 text-xl">error</span>
                <p class="text-red-400 text-sm">
                    <?php echo $error; ?>
                </p>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="mb-6 p-4 bg-emerald-500/10 border border-emerald-500/20 rounded-lg flex items-start gap-3">
                <span class="material-symbols-outlined text-emerald-500 text-xl">check_circle</span>
                <p class="text-emerald-400 text-sm">
                    <?php echo $success; ?>
                </p>
            </div>
        <?php endif; ?>

        <!-- User Management -->
        <div class="bg-surface-dark border border-white/10 rounded-2xl overflow-hidden mb-6">
            <div class="flex items-center justify-between p-4 border-b border-[#3e4a56]">
                <h3 class="text-white text-base font-bold">User Management</h3>
                <button onclick="openCreateUserModal()"
                    class="flex items-center gap-2 bg-primary hover:bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                    <span class="material-symbols-outlined text-[18px]">add</span>
                    Add User
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-[#1e2329] border-b border-[#3e4a56]">
                            <th class="p-4 text-xs font-semibold text-[#9dabb9] uppercase tracking-wider">Username</th>
                            <th class="p-4 text-xs font-semibold text-[#9dabb9] uppercase tracking-wider">Full Name</th>
                            <th class="p-4 text-xs font-semibold text-[#9dabb9] uppercase tracking-wider">Email</th>
                            <th class="p-4 text-xs font-semibold text-[#9dabb9] uppercase tracking-wider">Role</th>
                            <th class="p-4 text-xs font-semibold text-[#9dabb9] uppercase tracking-wider">Status</th>
                            <th class="p-4 text-xs font-semibold text-[#9dabb9] uppercase tracking-wider">Last Login
                            </th>
                            <th class="p-4 text-xs font-semibold text-[#9dabb9] uppercase tracking-wider text-right">
                                Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#3e4a56]">
                        <?php foreach ($users as $user): ?>
                            <tr class="hover:bg-[#323c47] transition-colors">
                                <td class="p-4 text-sm text-white font-medium">
                                    <?php echo htmlspecialchars($user['username']); ?>
                                </td>
                                <td class="p-4 text-sm text-[#d0d6dc]">
                                    <?php echo htmlspecialchars($user['full_name']); ?>
                                </td>
                                <td class="p-4 text-sm text-[#9dabb9]">
                                    <?php echo htmlspecialchars($user['email']); ?>
                                </td>
                                <td class="p-4">
                                    <span
                                        class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium
                                    <?php echo $user['role'] === 'admin' ? 'bg-red-500/10 text-red-500 border border-red-500/20' : ($user['role'] === 'user' ? 'bg-blue-500/10 text-blue-500 border border-blue-500/20' : 'bg-gray-500/10 text-gray-500 border border-gray-500/20'); ?>">
                                        <?php echo ucfirst($user['role']); ?>
                                    </span>
                                </td>
                                <td class="p-4">
                                    <span
                                        class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium
                                    <?php echo $user['is_active'] ? 'bg-emerald-500/10 text-emerald-500 border border-emerald-500/20' : 'bg-gray-500/10 text-gray-500 border border-gray-500/20'; ?>">
                                        <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td class="p-4 text-sm text-[#9dabb9]">
                                    <?php echo $user['last_login'] ? timeAgo($user['last_login']) : 'Never'; ?>
                                </td>
                                <td class="p-4 text-right">
                                    <?php if ($user['id'] !== 1): ?>
                                        <button onclick="editUser(<?php echo $user['id']; ?>)"
                                            class="text-primary hover:text-blue-400 text-sm font-medium mr-3">Edit</button>
                                        <button
                                            onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username'], ENT_QUOTES); ?>')"
                                            class="text-red-500 hover:text-red-400 text-sm font-medium">Delete</button>
                                    <?php else: ?>
                                        <span class="text-[#9dabb9] text-xs">Protected</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- System Info -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="bg-surface-dark border border-white/10 rounded-2xl p-6">
                <div class="flex items-center gap-3 mb-4">
                    <span class="material-symbols-outlined text-primary text-3xl">info</span>
                    <h3 class="text-white text-lg font-bold">Version</h3>
                </div>
                <p class="text-[#9dabb9] text-sm">IP Manager v
                    <?php echo APP_VERSION; ?>
                </p>
            </div>

            <div class="bg-surface-dark border border-white/10 rounded-2xl p-6">
                <div class="flex items-center gap-3 mb-4">
                    <span class="material-symbols-outlined text-emerald-500 text-3xl">storage</span>
                    <h3 class="text-white text-lg font-bold">Database</h3>
                </div>
                <p class="text-[#9dabb9] text-sm">
                    <?php echo DB_NAME; ?>
                </p>
            </div>

            <div class="bg-surface-dark border border-white/10 rounded-2xl p-6">
                <div class="flex items-center gap-3 mb-4">
                    <span class="material-symbols-outlined text-blue-500 text-3xl">people</span>
                    <h3 class="text-white text-lg font-bold">Total Users</h3>
                </div>
                <p class="text-white text-2xl font-bold">
                    <?php echo count($users); ?>
                </p>
            </div>
        </div>
    </div>

    <!-- Create User Modal (hidden by default) -->
    <div id="createUserModal" class="hidden fixed inset-0 bg-black/50 flex items-center justify-center p-4 z-50">
        <div class="bg-surface-dark border border-white/10 rounded-2xl p-8 max-w-md w-full">
            <h2 class="text-white text-2xl font-bold mb-6">Create New User</h2>
            <form method="POST" action="" class="space-y-4">
                <input type="hidden" name="action" value="create_user">

                <div>
                    <label class="block text-gray-400 text-sm font-medium mb-2">Username</label>
                    <input type="text" name="username" required
                        class="w-full bg-background-dark border border-gray-700 text-white text-sm rounded-lg focus:ring-2 focus:ring-primary px-4 py-2.5">
                </div>

                <div>
                    <label class="block text-gray-400 text-sm font-medium mb-2">Full Name</label>
                    <input type="text" name="full_name" required
                        class="w-full bg-background-dark border border-gray-700 text-white text-sm rounded-lg focus:ring-2 focus:ring-primary px-4 py-2.5">
                </div>

                <div>
                    <label class="block text-gray-400 text-sm font-medium mb-2">Email</label>
                    <input type="email" name="email" required
                        class="w-full bg-background-dark border border-gray-700 text-white text-sm rounded-lg focus:ring-2 focus:ring-primary px-4 py-2.5">
                </div>

                <div>
                    <label class="block text-gray-400 text-sm font-medium mb-2">Password</label>
                    <input type="password" name="password" required
                        class="w-full bg-background-dark border border-gray-700 text-white text-sm rounded-lg focus:ring-2 focus:ring-primary px-4 py-2.5">
                </div>

                <div>
                    <label class="block text-gray-400 text-sm font-medium mb-2">Role</label>
                    <select name="role"
                        class="w-full bg-background-dark border border-gray-700 text-white text-sm rounded-lg focus:ring-2 focus:ring-primary px-4 py-2.5">
                        <option value="user">User</option>
                        <option value="admin">Admin</option>
                        <option value="viewer">Viewer</option>
                    </select>
                </div>

                <div class="flex gap-3 pt-4">
                    <button type="submit"
                        class="flex-1 bg-primary hover:bg-blue-600 text-white py-2.5 px-4 rounded-lg transition-colors">Create
                        User</button>
                    <button type="button" onclick="closeCreateUserModal()"
                        class="px-6 py-2.5 bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition-colors">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openCreateUserModal() {
            document.getElementById('createUserModal').classList.remove('hidden');
        }

        function closeCreateUserModal() {
            document.getElementById('createUserModal').classList.add('hidden');
        }

        function editUser(id) {
            window.location.href = 'settings.php?edit=' + id;
        }

        function deleteUser(id, username) {
            if (confirm('Are you sure you want to delete user "' + username + '"?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = '<input type="hidden" name="action" value="delete_user"><input type="hidden" name="user_id" value="' + id + '">';
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>

</html>