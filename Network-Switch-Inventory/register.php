<?php
/**
 * User Registration Page
 */

session_start();
require_once 'auth.php';
require_once 'config.php';

// Redirect if already logged in
if (isAuthenticated()) {
    header('Location: index.php');
    exit;
}

$pdo = getDBConnection();
$appSettings = getSystemSettings($pdo);
$portalName = $appSettings['portal_name'] ?? 'Network Switch Inventory';
$primaryColor = $appSettings['theme_color'] ?? '#135bec';

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
                $stmt = $pdo->prepare("INSERT INTO users (username, full_name, email, password, role, is_active) VALUES (?, ?, ?, ?, 'Viewer', 0)");
                $stmt->execute([$username, $fullName, $email, $hashedPassword]);
                $success = 'Registration successful! Your account is pending admin approval.';
            }
        } catch (Exception $e) {
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
    <title>Register -
        <?php echo htmlspecialchars($portalName); ?>
    </title>
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
                        "primary": "<?php echo $primaryColor; ?>",
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
        .animate-slide-up {
            animation: slideUp 0.5s ease-out;
        }

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

        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
        }

        .custom-scrollbar::-webkit-scrollbar-track {
            background: transparent;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #282e39;
            border-radius: 10px;
        }
    </style>
</head>

<body
    class="bg-background-dark font-display text-slate-100 min-h-screen flex items-center justify-center p-6 relative overflow-x-hidden">
    <div class="relative z-10 w-full max-w-md animate-slide-up">
        <div class="text-center mb-8">
            <div class="inline-flex items-center gap-3 mb-4">
                <div
                    class="bg-primary size-14 rounded-xl flex items-center justify-center text-white shadow-lg shadow-primary/30">
                    <span class="material-symbols-outlined text-3xl">router</span>
                </div>
                <div class="flex flex-col items-start">
                    <h1 class="text-white text-2xl font-bold leading-none">
                        <?php echo htmlspecialchars($portalName); ?>
                    </h1>
                    <p class="text-slate-400 text-sm mt-1">User Registration</p>
                </div>
            </div>
            <h2 class="text-xl font-semibold text-white mb-2">Create Account</h2>
            <p class="text-sm text-slate-400">Join the network inventory management system</p>
        </div>

        <div class="bg-[#1a2130] border border-border-dark rounded-2xl shadow-2xl overflow-hidden">
            <div class="bg-gradient-to-r from-primary/10 to-transparent p-6 border-b border-border-dark">
                <h3 class="text-lg font-bold text-white flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary">person_add</span>
                    Register
                </h3>
            </div>

            <form method="POST" action="" class="p-6 space-y-4">
                <?php if ($error): ?>
                    <div class="bg-red-500/10 border border-red-500/20 rounded-lg p-4 flex items-start gap-3">
                        <span class="material-symbols-outlined text-red-500 flex-shrink-0">error</span>
                        <p class="text-sm text-red-500 font-medium">
                            <?php echo htmlspecialchars($error); ?>
                        </p>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="bg-emerald-500/10 border border-emerald-500/20 rounded-lg p-4 flex items-start gap-3">
                        <span class="material-symbols-outlined text-emerald-500 flex-shrink-0">check_circle</span>
                        <p class="text-sm text-emerald-500 font-medium">
                            <?php echo htmlspecialchars($success); ?>
                        </p>
                    </div>
                <?php endif; ?>

                <div class="grid grid-cols-2 gap-4">
                    <div class="col-span-2">
                        <label class="block text-sm font-semibold text-white mb-2">Full Name</label>
                        <input type="text" name="full_name" required
                            class="w-full px-4 py-2.5 bg-background-dark border border-border-dark text-white text-sm rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition-all"
                            placeholder="John Doe">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-white mb-2">Username</label>
                        <input type="text" name="username" required
                            class="w-full px-4 py-2.5 bg-background-dark border border-border-dark text-white text-sm rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition-all"
                            placeholder="jdoe">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-white mb-2">Email (Optional)</label>
                        <input type="email" name="email"
                            class="w-full px-4 py-2.5 bg-background-dark border border-border-dark text-white text-sm rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition-all"
                            placeholder="john@example.com">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-white mb-2">Password</label>
                    <input type="password" name="password" required
                        class="w-full px-4 py-2.5 bg-background-dark border border-border-dark text-white text-sm rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition-all"
                        placeholder="••••••••">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-white mb-2">Confirm Password</label>
                    <input type="password" name="confirm_password" required
                        class="w-full px-4 py-2.5 bg-background-dark border border-border-dark text-white text-sm rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition-all"
                        placeholder="••••••••">
                </div>

                <button type="submit"
                    class="w-full flex items-center justify-center gap-2 px-6 py-3 bg-primary text-white text-sm font-bold rounded-lg hover:bg-primary/90 transition-all shadow-lg shadow-primary/30">
                    Create Account
                </button>
            </form>

            <div class="bg-background-dark border-t border-border-dark p-4 text-center">
                <p class="text-xs text-slate-500">
                    Already have an account?
                    <a href="login.php" class="text-primary hover:underline font-bold ml-1">Sign In</a>
                </p>
            </div>
        </div>
    </div>
</body>

</html>