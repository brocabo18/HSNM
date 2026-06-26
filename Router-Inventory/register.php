<?php
/**
 * User Registration Page
 * IHOMS Router Inventory Management System
 */

session_start();
require_once 'db.php';
require_once 'audit_logger.php';

// Redirect if already logged in
if (isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true) {
    header('Location: ./');
    exit;
}

$portalName = "IHOMS Router Portal";
$portalSubtitle = "IHOMS";
$primaryColor = "#10b981";

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $fullName = $_POST['full_name'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($username) || empty($fullName) || empty($password)) {
        $error = 'Please fill in all required fields';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match';
    } else {
        try {
            // Check if username already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                $error = 'Username already taken';
            } else {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                // Inserting with is_active = 0 (Requires Approval)
                $stmt = $pdo->prepare("INSERT INTO users (name, username, email, password, role, is_active) VALUES (?, ?, ?, ?, 'Viewer', 0)");
                $stmt->execute([$fullName, $username, $email, $hashedPassword]);

                logAudit($pdo, "User Registration", "New user $username registered and pending approval.", "Info", "Creation", $username);

                $success = 'Registration successful! Your account is pending admin approval.';
            }
        } catch (Exception $e) {
            // Check if migration is needed (missing columns)
            if (strpos($e->getMessage(), 'Unknown column') !== false) {
                header('Location: migrate_registration');
                exit;
            }
            $error = 'Registration failed: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html class="dark" lang="en">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>Provisioning - <?php echo htmlspecialchars($portalName); ?></title>
    <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap"
        rel="stylesheet" />
    <script>
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
                    }
                }
            }
        }
    </script>
    <style>
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-slide-up {
            animation: slideUp 0.5s ease-out;
        }

        @keyframes pulse {

            0%,
            100% {
                opacity: 1;
            }

            50% {
                opacity: 0.5;
            }
        }

        .animate-pulse-slow {
            animation: pulse 3s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
    </style>
</head>

<body class="bg-background-dark font-display text-slate-100 min-h-screen flex items-center justify-center p-4">
    <!-- Background Pattern -->
    <div class="fixed inset-0 opacity-5">
        <div class="absolute inset-0"
            style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 40px 40px;">
        </div>
    </div>

    <!-- Container -->
    <div class="relative z-10 w-full max-w-xl animate-slide-up">
        <!-- Logo & Header -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center gap-3 mb-4">
                <div
                    class="bg-primary size-14 rounded-xl flex items-center justify-center text-white shadow-lg shadow-primary/30">
                    <span class="material-symbols-outlined text-3xl">router</span>
                </div>
                <div class="flex flex-col items-start">
                    <h1 class="text-white text-2xl font-bold leading-none"><?php echo htmlspecialchars($portalName); ?>
                    </h1>
                    <p class="text-slate-400 text-sm mt-1">
                        <?php echo htmlspecialchars($portalSubtitle ?? 'IHOMS'); ?>
                    </p>
                </div>
            </div>
            <h2 class="text-xl font-semibold text-white mb-2">New Operator Provisioning</h2>
            <p class="text-sm text-slate-400">Create a new administrative identity</p>
        </div>

        <!-- Card -->
        <div class="bg-[#1a2130] border border-border-dark rounded-2xl shadow-2xl overflow-hidden">
            <!-- Card Header -->
            <div class="bg-gradient-to-r from-primary/10 to-transparent p-6 border-b border-border-dark">
                <h3 class="text-lg font-bold text-white flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary">person_add</span>
                    Register Account
                </h3>
            </div>

            <!-- Form -->
            <form method="POST" action="" class="p-6 space-y-5">
                <?php if ($error): ?>
                    <div class="bg-red-500/10 border border-red-500/20 rounded-lg p-4 flex items-start gap-3">
                        <span class="material-symbols-outlined text-red-500 flex-shrink-0">error</span>
                        <div class="flex-1">
                            <p class="text-sm text-red-500 font-medium">
                                <?php echo htmlspecialchars($error); ?>
                            </p>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="bg-emerald-500/10 border border-emerald-500/20 rounded-lg p-4 flex items-start gap-3">
                        <span class="material-symbols-outlined text-emerald-500 flex-shrink-0">check_circle</span>
                        <div class="flex-1">
                            <p class="text-sm text-emerald-500 font-medium">
                                <?php echo htmlspecialchars($success); ?>
                            </p>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-semibold text-white mb-2" for="full_name">
                            Full Name
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <span class="material-symbols-outlined text-slate-500 text-xl">badge</span>
                            </div>
                            <input type="text" id="full_name" name="full_name" required
                                class="w-full pl-11 pr-4 py-3 bg-background-dark border border-border-dark text-white text-sm rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition-all"
                                placeholder="Michael Jordan"
                                value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-white mb-2" for="username">
                            Username
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <span class="material-symbols-outlined text-slate-500 text-xl">person</span>
                            </div>
                            <input type="text" id="username" name="username" required
                                class="w-full pl-11 pr-4 py-3 bg-background-dark border border-border-dark text-white text-sm rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition-all"
                                placeholder="operator_23"
                                value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-white mb-2" for="email">
                            Email (Optional)
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <span class="material-symbols-outlined text-slate-500 text-xl">mail</span>
                            </div>
                            <input type="email" id="email" name="email"
                                class="w-full pl-11 pr-4 py-3 bg-background-dark border border-border-dark text-white text-sm rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition-all"
                                placeholder="mj@bulls.network"
                                value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-white mb-2" for="password">
                            Password
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <span class="material-symbols-outlined text-slate-500 text-xl">key</span>
                            </div>
                            <input type="password" id="password" name="password" required
                                class="w-full pl-11 pr-4 py-3 bg-background-dark border border-border-dark text-white text-sm rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition-all"
                                placeholder="••••••••">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-white mb-2" for="confirm_password">
                            Confirm Password
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <span class="material-symbols-outlined text-slate-500 text-xl">lock_reset</span>
                            </div>
                            <input type="password" id="confirm_password" name="confirm_password" required
                                class="w-full pl-11 pr-4 py-3 bg-background-dark border border-border-dark text-white text-sm rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition-all"
                                placeholder="••••••••">
                        </div>
                    </div>
                </div>

                <!-- Submit Button -->
                <button type="submit"
                    class="w-full flex items-center justify-center gap-2 px-6 py-3 bg-primary text-white text-sm font-bold rounded-lg hover:bg-primary/90 transition-all shadow-lg shadow-primary/30 hover:shadow-primary/50 mt-4">
                    <span class="material-symbols-outlined text-xl">how_to_reg</span>
                    Submit Provisioning Request
                </button>
            </form>

            <!-- Card Footer -->
            <div class="bg-background-dark border-t border-border-dark p-4">
                <div class="flex flex-col items-center gap-3">
                    <p class="text-[11px] text-slate-400">
                        Already authorized?
                        <a href="login" class="text-primary hover:underline font-bold ml-1">Sign In to Console</a>
                    </p>
                </div>
            </div>
        </div>

        <!-- Footer Info -->
        <div class="mt-6 text-center">
            <p class="text-xs text-slate-500">
                Network Switch Inventory Management System v2.0
            </p>
            <p class="text-xs text-slate-600 mt-1">
                Secure access with role-based authentication
            </p>
        </div>
    </div>

    <!-- Decorative Elements -->
    <div class="fixed top-10 left-10 w-72 h-72 bg-primary/5 rounded-full blur-3xl animate-pulse-slow"></div>
    <div class="fixed bottom-10 right-10 w-96 h-96 bg-primary/5 rounded-full blur-3xl animate-pulse-slow"
        style="animation-delay: 1.5s;"></div>
</body>

</html>