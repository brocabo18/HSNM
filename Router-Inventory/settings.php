<?php
session_start();
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header('Location: login');
    exit;
}

// Access Control: Only Admins can manage users
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'Admin') {
    // If user_role is missing, force logout/login to refresh session
    if (!isset($_SESSION['user_role'])) {
        header('Location: logout');
        exit;
    }
    // die("Access Denied: You do not have permission to view this page.");
    // Instead of dying, we just won't show the admin section
}

require_once 'db.php';
require_once 'audit_logger.php';

$success_msg = '';
$error_msg = '';

// Handle Add User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_user') {
    $name = $_POST['name'];
    $username = $_POST['username'];
    $email = $_POST['email'] ?? null;
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = $_POST['role'];

    try {
        $stmt = $pdo->prepare("INSERT INTO users (name, username, email, password, role, is_active) VALUES (?, ?, ?, ?, ?, 1)");
        $stmt->execute([$name, $username, $email, $password, $role]);
        logAudit($pdo, "User Added", "New user $username was created with role $role.", "Success", "Create", $username);
        $success_msg = "User added successfully.";
    } catch (PDOException $e) {
        if ($e->getCode() == '42S22' || strpos($e->getMessage(), 'Unknown column') !== false) {
            header('Location: migrate_registration');
            exit;
        }
        $error_msg = "Error adding user: " . $e->getMessage();
    }
}

// Handle Update Profile (Self)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $user_id = $_SESSION['user_id'];
    $username = $_POST['username'];
    $email = $_POST['email'];

    try {
        $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ? WHERE id = ?");
        $stmt->execute([$username, $email, $user_id]);
        $_SESSION['user_name'] = $username; // Update session
        logAudit($pdo, "Profile Updated", "User updated their profile information.", "Info", "Update", $username);
        $success_msg = "Profile updated successfully.";
    } catch (PDOException $e) {
        $error_msg = "Error updating profile: " . $e->getMessage();
    }
}

// Handle Update Password (Self)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_password'])) {
    $user_id = $_SESSION['user_id'];
    $new_password = $_POST['new_password'];

    try {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$hashed_password, $user_id]);
        logAudit($pdo, "Password Changed", "User changed their password.", "Info", "Update", $_SESSION['user_name']);
        $success_msg = "Password updated successfully.";
    } catch (PDOException $e) {
        $error_msg = "Error updating password: " . $e->getMessage();
    }
}

// Handle Edit User (Admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_user') {
    $user_id = $_POST['user_id'];
    $name = $_POST['name'];
    $username = $_POST['username'];
    $email = $_POST['email'] ?? null;
    $role = $_POST['role'];
    $is_active = isset($_POST['is_active']) ? (int) $_POST['is_active'] : 1;
    $password = $_POST['password'];

    try {
        if (!empty($password)) {
            // Update with password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET name = ?, username = ?, email = ?, role = ?, is_active = ?, password = ? WHERE id = ?");
            $stmt->execute([$name, $username, $email, $role, $is_active, $hashed_password, $user_id]);
        } else {
            // Update without password
            $stmt = $pdo->prepare("UPDATE users SET name = ?, username = ?, email = ?, role = ?, is_active = ? WHERE id = ?");
            $stmt->execute([$name, $username, $email, $role, $is_active, $user_id]);
        }
        logAudit($pdo, "User Updated", "User $username configuration was updated.", "Info", "Update", $username);
        $success_msg = "User updated successfully.";
    } catch (PDOException $e) {
        if ($e->getCode() == '42S22' || strpos($e->getMessage(), 'Unknown column') !== false) {
            header('Location: migrate_registration');
            exit;
        }
        $error_msg = "Error updating user: " . $e->getMessage();
    }
}

// Handle Delete User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_user') {
    $user_id = $_POST['user_id'];

    // Prevent self-deletion
    if ($user_id == $_SESSION['user_id']) {
        $error_msg = "You cannot delete your own account.";
    } else {
        try {
            // Get username for logging
            $getStmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
            $getStmt->execute([$user_id]);
            $username = $getStmt->fetchColumn();

            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            logAudit($pdo, "User Deleted", "User $username was permanently removed.", "Warning", "Delete", $username);
            $success_msg = "User deleted successfully.";
        } catch (PDOException $e) {
            $error_msg = "Error deleting user.";
        }
    }
}

// Fetch Current User Data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: logout');
    exit;
}

// Fetch All Users (for Admin)
$users = [];
if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'Admin') {
    try {
        $stmt = $pdo->query("SELECT * FROM users ORDER BY created_at DESC");
        $users = $stmt->fetchAll();
    } catch (PDOException $e) {
        if ($e->getCode() == '42S22' || strpos($e->getMessage(), 'Unknown column') !== false) {
            // header('Location: migrate_registration');
            // exit;
        }
        // die("Database error: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - <?php echo htmlspecialchars($portalName ?? "IHOMS Router Portal"); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap"
        rel="stylesheet" />
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        primary: '#10b981',
                        'background-dark': '#101622',
                        'background-light': '#1a2130',
                        'border-dark': '#232b3d',
                    },
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }

        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
        }

        .custom-scrollbar::-webkit-scrollbar-track {
            background: transparent;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #232b3d;
            border-radius: 10px;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: #10b981;
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
                class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-white/5 text-slate-400 hover:text-white transition-all group">
                <span
                    class="material-symbols-outlined text-[20px] group-hover:text-primary transition-colors">history</span>
                <span class="text-sm font-semibold">Audit Logs</span>
            </a>

            <a href="settings"
                class="flex items-center gap-3 px-4 py-3 rounded-xl bg-primary/10 text-primary transition-all group">
                <span class="material-symbols-outlined text-[20px]">settings</span>
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
                <span class="material-symbols-outlined text-primary">settings</span>
                <h2 class="text-sm font-bold text-white">Console Settings</h2>
            </div>

            <div class="flex items-center gap-4">
                <div class="flex items-center gap-3 bg-[#1a2130] border border-[#232b3d] px-3 py-1.5 rounded-lg">
                    <div class="text-right">
                        <p class="text-xs font-bold text-white leading-none">
                            <?= htmlspecialchars($_SESSION['user_name']) ?>
                        </p>
                        <p class="text-[10px] text-primary font-medium mt-1 leading-none">
                            <?= htmlspecialchars($_SESSION['user_role']) ?>
                        </p>
                    </div>
                    <div
                        class="size-8 rounded-full bg-primary/10 flex items-center justify-center text-primary text-xs font-bold">
                        <?= strtoupper(substr($_SESSION['user_name'], 0, 1)) ?>
                    </div>
                </div>
            </div>
        </header>

        <div class="p-8 flex-1 overflow-y-auto custom-scrollbar relative z-10 space-y-8">
            <!-- Notifications -->
            <?php if ($success_msg): ?>
                <div
                    class="bg-emerald-500/10 border border-emerald-500/20 text-emerald-500 p-4 rounded-xl text-sm font-medium flex items-center gap-3">
                    <span class="material-symbols-outlined text-[20px]">check_circle</span>
                    <?= htmlspecialchars($success_msg) ?>
                </div>
            <?php endif; ?>
            <?php if ($error_msg): ?>
                <div
                    class="bg-rose-500/10 border border-rose-500/20 text-rose-500 p-4 rounded-xl text-sm font-medium flex items-center gap-3">
                    <span class="material-symbols-outlined text-[20px]">error</span>
                    <?= htmlspecialchars($error_msg) ?>
                </div>
            <?php endif; ?>

            <!-- Profile Section -->
            <section>
                <div class="mb-6">
                    <h2 class="text-xl font-bold text-white">Security & Profile</h2>
                    <p class="text-sm text-slate-500 mt-1">Manage your administrator identity and credentials</p>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <div class="bg-[#1a2130] border border-[#232b3d] rounded-2xl overflow-hidden shadow-sm">
                        <div class="p-4 border-b border-[#232b3d] bg-[#1a2130]/50">
                            <h3 class="text-xs font-bold text-slate-300 uppercase tracking-wider">Update Identity</h3>
                        </div>
                        <div class="p-6">
                            <form method="POST" class="space-y-4">
                                <div class="space-y-2">
                                    <label class="text-xs font-semibold text-slate-400">Username</label>
                                    <input type="text" name="username"
                                        value="<?= htmlspecialchars($user['username']) ?>" required
                                        class="w-full bg-[#101622] border border-[#232b3d] text-white text-sm rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-primary focus:border-transparent transition-all">
                                </div>
                                <div class="space-y-2">
                                    <label class="text-xs font-semibold text-slate-400">Email Address</label>
                                    <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>"
                                        required
                                        class="w-full bg-[#101622] border border-[#232b3d] text-white text-sm rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-primary focus:border-transparent transition-all">
                                </div>
                                <div class="pt-4">
                                    <button type="submit" name="update_profile"
                                        class="w-full py-2.5 bg-primary hover:bg-primary/90 text-white text-sm font-bold rounded-xl transition-all shadow-lg shadow-primary/20">
                                        Update Profile
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="bg-[#1a2130] border border-[#232b3d] rounded-2xl overflow-hidden shadow-sm">
                        <div class="p-4 border-b border-[#232b3d] bg-[#1a2130]/50">
                            <h3 class="text-xs font-bold text-slate-300 uppercase tracking-wider">Passkey Reset</h3>
                        </div>
                        <div class="p-6">
                            <form method="POST" class="space-y-4">
                                <div class="space-y-2">
                                    <label class="text-xs font-semibold text-slate-400">New Access Password</label>
                                    <input type="password" name="new_password" placeholder="••••••••••••" required
                                        class="w-full bg-[#101622] border border-[#232b3d] text-white text-sm rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-primary focus:border-transparent transition-all">
                                </div>
                                <p class="text-xs text-slate-500 leading-relaxed">
                                    Ensure passwords are complex to prevent unauthorized node access.
                                </p>
                                <div class="pt-4">
                                    <button type="submit" name="update_password"
                                        class="w-full py-2.5 bg-[#101622] border border-[#232b3d] text-slate-400 hover:text-white hover:border-primary/50 text-sm font-bold rounded-xl transition-all">
                                        Reset Credentials
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Admin: User Management -->
            <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'Admin'): ?>
                <section class="space-y-6">
                    <div class="flex items-end justify-between">
                        <div>
                            <h2 class="text-xl font-bold text-white">User Infrastructure</h2>
                            <p class="text-sm text-slate-500 mt-1">Console access control and role permissions</p>
                        </div>
                        <button onclick="toggleModal()"
                            class="flex items-center gap-2 px-4 py-2 bg-primary text-white text-xs font-bold rounded-xl hover:bg-primary/90 transition-all shadow-lg shadow-primary/20">
                            <span class="material-symbols-outlined text-[18px]">add</span>
                            Provision User
                        </button>
                    </div>

                    <div class="bg-[#1a2130] border border-[#232b3d] rounded-2xl overflow-hidden shadow-sm">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="bg-[#1a2130] border-b border-[#232b3d]">
                                        <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase tracking-wider">
                                            Operator</th>
                                        <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase tracking-wider">
                                            Identifier</th>
                                        <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase tracking-wider">
                                            Permission Pool</th>
                                        <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase tracking-wider">
                                            Node Status</th>
                                        <th
                                            class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase tracking-wider text-right">
                                            Control</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-[#232b3d]/50">
                                    <?php foreach ($users as $u): ?>
                                        <tr class="hover:bg-white/5 transition-colors group">
                                            <td class="px-6 py-4">
                                                <div class="flex items-center gap-3">
                                                    <div
                                                        class="size-8 rounded-lg bg-primary/10 flex items-center justify-center text-primary font-bold border border-primary/20 text-xs">
                                                        <?= strtoupper(substr($u['name'], 0, 1)) ?>
                                                    </div>
                                                    <span
                                                        class="text-sm font-semibold text-white"><?= htmlspecialchars($u['name']) ?></span>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <span
                                                    class="text-sm font-mono text-slate-400"><?= htmlspecialchars($u['username']) ?></span>
                                            </td>
                                            <td class="px-6 py-4">
                                                <span
                                                    class="px-2 py-1 rounded text-xs font-medium border border-[#232b3d] bg-[#101622] text-slate-400">
                                                    <?= $u['role'] ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4">
                                                <?php $isActive = (isset($u['is_active']) && $u['is_active'] == 0) ? false : true; ?>
                                                <div class="flex items-center gap-2">
                                                    <div
                                                        class="size-1.5 rounded-full <?= $isActive ? 'bg-emerald-500 shadow-md shadow-emerald-500/50' : 'bg-rose-500 shadow-md shadow-rose-500/50' ?>">
                                                    </div>
                                                    <span
                                                        class="text-xs font-medium <?= $isActive ? 'text-emerald-500' : 'text-rose-500' ?>">
                                                        <?= $isActive ? 'Authorized' : 'Deauthorized' ?>
                                                    </span>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 text-right">
                                                <div
                                                    class="flex items-center justify-end gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                                    <button onclick='openEditModal(<?= json_encode($u) ?>)'
                                                        class="p-2 rounded-lg bg-[#101622] border border-[#232b3d] text-slate-400 hover:text-white hover:border-primary/50 transition-all"
                                                        title="Edit">
                                                        <span class="material-symbols-outlined text-[18px]">edit</span>
                                                    </button>
                                                    <?php if ($u['id'] != $_SESSION['user_id']): ?>
                                                        <form method="POST"
                                                            onsubmit="return confirm('Immediate deprovisioning of this operator?');"
                                                            class="inline">
                                                            <input type="hidden" name="action" value="delete_user">
                                                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                            <button type="submit"
                                                                class="p-2 rounded-lg bg-rose-500/10 border border-rose-500/20 text-rose-500 hover:bg-rose-500 hover:text-white transition-all"
                                                                title="Delete">
                                                                <span
                                                                    class="material-symbols-outlined text-[18px]">person_remove</span>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>
            <?php endif; ?>
        </div>
    </main>

    <!-- Modals (Only for Admin) -->
    <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'Admin'): ?>
        <!-- Provision User Modal -->
        <div id="addUserModal"
            class="fixed inset-0 z-50 hidden flex items-center justify-center p-4 bg-[#0a0c12]/80 backdrop-blur-sm">
            <div class="bg-[#1a2130] border border-[#232b3d] w-full max-w-md rounded-2xl overflow-hidden shadow-2xl">
                <div class="p-6 border-b border-[#232b3d] flex items-center justify-between">
                    <h3 class="text-sm font-bold text-white">Provision New Operator</h3>
                    <button onclick="toggleModal()" class="text-slate-500 hover:text-white transition-colors">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>
                <form method="POST" class="p-6 space-y-4">
                    <input type="hidden" name="action" value="add_user">
                    <div class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-1.5">
                                <label class="text-xs font-semibold text-slate-400">Full Name</label>
                                <input type="text" name="name" required
                                    class="w-full bg-[#101622] border border-[#232b3d] text-white text-sm rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-primary focus:border-transparent transition-all">
                            </div>
                            <div class="space-y-1.5">
                                <label class="text-xs font-semibold text-slate-400">Username</label>
                                <input type="text" name="username" required
                                    class="w-full bg-[#101622] border border-[#232b3d] text-white text-sm rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-primary focus:border-transparent transition-all">
                            </div>
                        </div>
                        <div class="space-y-1.5">
                            <label class="text-xs font-semibold text-slate-400">Email Address</label>
                            <input type="email" name="email"
                                class="w-full bg-[#101622] border border-[#232b3d] text-white text-sm rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-primary focus:border-transparent transition-all">
                        </div>
                        <div class="space-y-1.5">
                            <label class="text-xs font-semibold text-slate-400">Temporary Password</label>
                            <input type="password" name="password" required
                                class="w-full bg-[#101622] border border-[#232b3d] text-white text-sm rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-primary focus:border-transparent transition-all">
                        </div>
                        <div class="space-y-1.5">
                            <label class="text-xs font-semibold text-slate-400">Permission Role</label>
                            <select name="role"
                                class="w-full bg-[#101622] border border-[#232b3d] text-white text-sm rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-primary focus:border-transparent transition-all">
                                <option value="Viewer">Viewer</option>
                                <option value="Editor">Editor</option>
                                <option value="Admin">Admin</option>
                            </select>
                        </div>
                    </div>
                    <div class="pt-4">
                        <button type="submit"
                            class="w-full py-2.5 bg-primary text-white text-sm font-bold rounded-xl hover:bg-primary/90 transition-all shadow-lg shadow-primary/20">
                            Create Operator Access
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Edit Operator Modal -->
        <div id="editUserModal"
            class="fixed inset-0 z-50 hidden flex items-center justify-center p-4 bg-[#0a0c12]/80 backdrop-blur-sm">
            <div class="bg-[#1a2130] border border-[#232b3d] w-full max-w-md rounded-2xl overflow-hidden shadow-2xl">
                <div class="p-6 border-b border-[#232b3d] flex items-center justify-between">
                    <h3 class="text-sm font-bold text-white">Reconfigure Operator</h3>
                    <button onclick="toggleEditModal()" class="text-slate-500 hover:text-white transition-colors">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>
                <form method="POST" class="p-6 space-y-4">
                    <input type="hidden" name="action" value="edit_user">
                    <input type="hidden" name="user_id" id="edit_user_id">
                    <div class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-1.5">
                                <label class="text-xs font-semibold text-slate-400">Full Name</label>
                                <input type="text" name="name" id="edit_name" required
                                    class="w-full bg-[#101622] border border-[#232b3d] text-white text-sm rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-primary focus:border-transparent transition-all">
                            </div>
                            <div class="space-y-1.5">
                                <label class="text-xs font-semibold text-slate-400">Username</label>
                                <input type="text" name="username" id="edit_username" required
                                    class="w-full bg-[#101622] border border-[#232b3d] text-white text-sm rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-primary focus:border-transparent transition-all">
                            </div>
                        </div>
                        <div class="space-y-1.5">
                            <label class="text-xs font-semibold text-slate-400">Email Address</label>
                            <input type="email" name="email" id="edit_email"
                                class="w-full bg-[#101622] border border-[#232b3d] text-white text-sm rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-primary focus:border-transparent transition-all">
                        </div>
                        <div class="space-y-1.5">
                            <label class="text-xs font-semibold text-slate-400">Reset Password (Blank to keep)</label>
                            <input type="password" name="password"
                                class="w-full bg-[#101622] border border-[#232b3d] text-white text-sm rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-primary focus:border-transparent transition-all">
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-1.5">
                                <label class="text-xs font-semibold text-slate-400">Role</label>
                                <select name="role" id="edit_role"
                                    class="w-full bg-[#101622] border border-[#232b3d] text-white text-sm rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-primary focus:border-transparent transition-all">
                                    <option value="Viewer">Viewer</option>
                                    <option value="Editor">Editor</option>
                                    <option value="Admin">Admin</option>
                                </select>
                            </div>
                            <div class="space-y-1.5">
                                <label class="text-xs font-semibold text-slate-400">Status</label>
                                <select name="is_active" id="edit_is_active"
                                    class="w-full bg-[#101622] border border-[#232b3d] text-white text-sm rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-primary focus:border-transparent transition-all">
                                    <option value="1">Authorized</option>
                                    <option value="0">Deauthorized</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="pt-4">
                        <button type="submit"
                            class="w-full py-2.5 bg-primary text-white text-sm font-bold rounded-xl hover:bg-primary/90 transition-all shadow-lg shadow-primary/20">
                            Apply Configuration
                        </button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <script>
        function toggleModal() {
            document.getElementById('addUserModal')?.classList.toggle('hidden');
        }

        function toggleEditModal() {
            document.getElementById('editUserModal')?.classList.toggle('hidden');
        }

        function openEditModal(user) {
            document.getElementById('edit_user_id').value = user.id;
            document.getElementById('edit_name').value = user.name;
            document.getElementById('edit_username').value = user.username;
            document.getElementById('edit_email').value = user.email || '';
            document.getElementById('edit_role').value = user.role;
            document.getElementById('edit_is_active').value = (user.is_active === "0" || user.is_active === 0) ? 0 : 1;
            toggleEditModal();
        }
    </script>
</body>

</html>