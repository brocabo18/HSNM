<?php
session_start();
require_once 'config.php';

$error = '';
$success = '';
$mode = $_GET['mode'] ?? 'login'; // login, register, forgot

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF verification for all POST requests
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $error = "Security validation failed. Please refresh the page and try again.";
    } elseif ($_POST['action'] === 'login') {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';

        if ($username && $password) {
            try {
                $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND is_active = true LIMIT 1");
                $stmt->execute([$username]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password'])) {
                    session_regenerate_id(true); // Prevent Session Fixation
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['module_access'] = $user['module_access']; // JSON string from DB

                    $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);
                    logAudit($pdo, 'user_login', "User $username logged in", 'user', $user['id']);

                    header("Location: " . BASE_URL . "/index");
                    exit;
                } else {
                    $error = "Invalid credentials or account is inactive.";
                }
            } catch (Exception $e) {
                $error = "System error: " . $e->getMessage();
            }
        } else {
            $error = "Please enter username and password";
        }
    } elseif ($_POST['action'] === 'register') {
        $username = $_POST['username'] ?? '';
        $email = $_POST['email'] ?? '';
        $full_name = $_POST['full_name'] ?? '';
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if ($username && $password && $email) {
            if ($password !== $confirm) {
                $error = "Passwords do not match.";
            } else {
                try {
                    $hashed = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO users (username, password, email, full_name, role) VALUES (?, ?, ?, ?, 'viewer')");
                    $stmt->execute([$username, $hashed, $email, $full_name]);
                    $success = "Registration successful! You can now login.";
                    $mode = 'login';
                } catch (Exception $e) {
                    $error = "Username or email already exists.";
                }
            }
        } else {
            $error = "All fields are required.";
        }
    } elseif ($_POST['action'] === 'forgot') {
        // Simplified: Admin will need to reset password in Settings
        $username = $_POST['username'] ?? '';
        if ($username) {
            $success = "Password reset request received. Please contact your administrator.";
        } else {
            $error = "Please enter your username.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(ucfirst($mode)) ?> - <?= APP_NAME ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>/assets/favicon.svg">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap"
        rel="stylesheet">
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        background: '#0a0c12',
                        surface: '#1a2130',
                        primary: '#10b981',
                    },
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    }
                }
            }
        }
    </script>
</head>

<body class="bg-background text-slate-200 flex items-center justify-center min-h-screen p-4">

    <div class="w-full max-w-md">
        <div class="text-center mb-10">
            <div class="inline-flex items-center justify-center size-12 rounded-xl bg-primary/10 text-primary mb-4">
                <span class="material-symbols-outlined text-2xl">hub</span>
            </div>
            <h1 class="text-2xl font-bold text-white"><?= APP_NAME ?></h1>
            <p class="text-sm text-slate-500 mt-2">
                <?php if ($mode === 'register'): ?>
                    Create a new account
                <?php elseif ($mode === 'forgot'): ?>
                    Reset your password
                <?php else: ?>
                    Sign in to access the unified dashboard
                <?php endif; ?>
            </p>
        </div>

        <div class="bg-surface border border-[#232b3d] rounded-2xl p-8 shadow-2xl">
            <?php if ($error): ?>
                <div
                    class="mb-6 p-3 rounded-lg bg-red-500/10 border border-red-500/20 text-red-500 text-xs font-bold text-center">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div
                    class="mb-6 p-3 rounded-lg bg-emerald-500/10 border border-emerald-500/20 text-emerald-500 text-xs font-bold text-center">
                    <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <?php if ($mode === 'login'): ?>
                <!-- Login Form -->
                <form method="POST" class="space-y-5">
                    <input type="hidden" name="action" value="login">
                    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Username</label>
                        <input type="text" name="username" required
                            class="w-full bg-[#0a0c12] border border-[#232b3d] rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-primary focus:border-transparent transition-all outline-none"
                            placeholder="Enter your username">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Password</label>
                        <input type="password" name="password" required
                            class="w-full bg-[#0a0c12] border border-[#232b3d] rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-primary focus:border-transparent transition-all outline-none"
                            placeholder="••••••••">
                    </div>
                    <button type="submit"
                        class="w-full bg-primary hover:bg-primary/90 text-white font-bold py-3 rounded-xl transition-all shadow-lg shadow-primary/20">
                        Sign In
                    </button>
                </form>

                <div class="mt-6 flex items-center justify-between text-xs">
                    <a href="?mode=forgot" class="text-slate-500 hover:text-primary transition-colors">Forgot Password?</a>
                    <a href="?mode=register" class="text-primary hover:text-primary/80 transition-colors font-bold">Create
                        Account</a>
                </div>

            <?php elseif ($mode === 'register'): ?>
                <!-- Registration Form -->
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="register">
                    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Username</label>
                        <input type="text" name="username" required
                            class="w-full bg-[#0a0c12] border border-[#232b3d] rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-primary focus:border-transparent transition-all outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Email</label>
                        <input type="email" name="email" required
                            class="w-full bg-[#0a0c12] border border-[#232b3d] rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-primary focus:border-transparent transition-all outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Full
                            Name</label>
                        <input type="text" name="full_name"
                            class="w-full bg-[#0a0c12] border border-[#232b3d] rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-primary focus:border-transparent transition-all outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Password</label>
                        <input type="password" name="password" required
                            class="w-full bg-[#0a0c12] border border-[#232b3d] rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-primary focus:border-transparent transition-all outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Confirm
                            Password</label>
                        <input type="password" name="confirm_password" required
                            class="w-full bg-[#0a0c12] border border-[#232b3d] rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-primary focus:border-transparent transition-all outline-none">
                    </div>
                    <button type="submit"
                        class="w-full bg-primary hover:bg-primary/90 text-white font-bold py-3 rounded-xl transition-all shadow-lg shadow-primary/20">
                        Create Account
                    </button>
                </form>

                <div class="mt-6 text-center text-xs">
                    <a href="?mode=login" class="text-slate-500 hover:text-primary transition-colors">Already have an
                        account? <span class="font-bold">Sign In</span></a>
                </div>

            <?php else: // forgot password ?>
                <!-- Forgot Password Form -->
                <form method="POST" class="space-y-5">
                    <input type="hidden" name="action" value="forgot">
                    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Username</label>
                        <input type="text" name="username" required
                            class="w-full bg-[#0a0c12] border border-[#232b3d] rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-primary focus:border-transparent transition-all outline-none"
                            placeholder="Enter your username">
                    </div>
                    <p class="text-xs text-slate-500">Please contact your system administrator to reset your password.</p>
                    <button type="submit"
                        class="w-full bg-primary hover:bg-primary/90 text-white font-bold py-3 rounded-xl transition-all shadow-lg shadow-primary/20">
                        Request Reset
                    </button>
                </form>

                <div class="mt-6 text-center text-xs">
                    <a href="?mode=login" class="text-slate-500 hover:text-primary transition-colors">Back to <span
                            class="font-bold">Sign In</span></a>
                </div>
            <?php endif; ?>
        </div>

        <p class="text-center text-xs text-slate-600 mt-8">
            &copy; <?= date('Y') ?> IHOMS-MIKE. All rights reserved. <?= getAppVersion($pdo) ?>
        </p>
    </div>

</body>

</html>