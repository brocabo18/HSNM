<?php
require_once 'config/config.php';

// If already logged in, redirect to dashboard
if (isLoggedIn()) {
    redirect('/IP-Address-Inventory/index.php');
}

$error = '';
$success = '';

if (isset($_GET['timeout'])) {
    $error = 'Your session has expired. Please login again.';
} elseif (isset($_GET['logout'])) {
    $success = 'You have been successfully logged out.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeInput($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);

    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND is_active = true");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                // Regenerate session ID for security
                session_regenerate_id(true);

                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['last_activity'] = time();

                // Update last login
                $updateStmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                $updateStmt->execute([$user['id']]);

                // Log successful login
                logAudit('login', 'user', $user['id'], 'User logged in successfully');

                // Handle remember me
                if ($remember) {
                    setcookie('remember_user', $username, time() + (86400 * 30), '/');
                }

                redirect('/IP-Address-Inventory/index.php');
            } else {
                $error = 'Invalid username or password.';
                // Log failed login attempt
                logAudit('login_failed', 'user', null, "Failed login attempt for username: $username");
            }
        } catch (PDOException $e) {
            $error = 'An error occurred. Please try again.';
            error_log($e->getMessage());
        }
    }
}

$rememberedUser = $_COOKIE['remember_user'] ?? '';
?>
<!DOCTYPE html>
<html class="dark" lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - IP Manager</title>
    <link
        href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=Noto+Sans:wght@400;500;600;700&display=swap"
        rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap"
        rel="stylesheet">
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

        .gradient-bg {
            background: linear-gradient(135deg, #0f1419 0%, #1a2332 100%);
        }
    </style>
</head>

<body class="gradient-bg font-display min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <!-- Logo/Header -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 bg-primary/20 rounded-2xl mb-4">
                <span class="material-symbols-outlined text-primary text-4xl">hub</span>
            </div>
            <h1 class="text-white text-3xl font-bold mb-2">IP Manager</h1>
            <p class="text-gray-400 text-sm">Sign in to your account</p>
        </div>

        <!-- Login Card -->
        <div class="bg-surface-dark border border-white/10 rounded-2xl p-8 shadow-2xl">
            <?php if ($error): ?>
                <div class="mb-6 p-4 bg-red-500/10 border border-red-500/20 rounded-lg flex items-start gap-3">
                    <span class="material-symbols-outlined text-red-500 text-xl">error</span>
                    <div>
                        <p class="text-red-400 text-sm font-medium">
                            <?php echo $error; ?>
                        </p>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="mb-6 p-4 bg-emerald-500/10 border border-emerald-500/20 rounded-lg flex items-start gap-3">
                    <span class="material-symbols-outlined text-emerald-500 text-xl">check_circle</span>
                    <div>
                        <p class="text-emerald-400 text-sm font-medium">
                            <?php echo $success; ?>
                        </p>
                    </div>
                </div>
            <?php endif; ?>

            <form method="POST" action="" class="space-y-5">
                <!-- Username -->
                <div>
                    <label for="username" class="block text-gray-400 text-sm font-medium mb-2">Username</label>
                    <div class="relative">
                        <span
                            class="absolute left-3 top-1/2 -translate-y-1/2 material-symbols-outlined text-gray-500 text-xl">person</span>
                        <input type="text" id="username" name="username"
                            value="<?php echo htmlspecialchars($rememberedUser); ?>"
                            class="w-full bg-background-dark border border-gray-700 text-white text-sm rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent pl-11 pr-4 py-3 placeholder-gray-500 transition-all"
                            placeholder="Enter your username" required autofocus>
                    </div>
                </div>

                <!-- Password -->
                <div>
                    <label for="password" class="block text-gray-400 text-sm font-medium mb-2">Password</label>
                    <div class="relative">
                        <span
                            class="absolute left-3 top-1/2 -translate-y-1/2 material-symbols-outlined text-gray-500 text-xl">lock</span>
                        <input type="password" id="password" name="password"
                            class="w-full bg-background-dark border border-gray-700 text-white text-sm rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent pl-11 pr-4 py-3 placeholder-gray-500 transition-all"
                            placeholder="Enter your password" required>
                    </div>
                </div>

                <!-- Remember Me -->
                <div class="flex items-center justify-between">
                    <label class="flex items-center cursor-pointer group">
                        <input type="checkbox" name="remember"
                            class="w-4 h-4 rounded border-gray-700 bg-background-dark text-primary focus:ring-2 focus:ring-primary focus:ring-offset-0 transition-all">
                        <span class="ml-2 text-sm text-gray-400 group-hover:text-gray-300 transition-colors">Remember
                            me</span>
                    </label>
                </div>

                <!-- Submit Button -->
                <button type="submit"
                    class="w-full bg-primary hover:bg-blue-600 text-white font-medium py-3 px-4 rounded-lg transition-all duration-200 shadow-lg shadow-primary/25 flex items-center justify-center gap-2">
                    <span class="material-symbols-outlined text-xl">login</span>
                    Sign In
                </button>
            </form>

            <!-- Footer Note -->
            <div class="mt-6 pt-6 border-t border-gray-700">
                <p class="text-center text-gray-500 text-xs">
                    Default credentials: <span class="text-gray-400 font-mono">admin / admin123</span>
                </p>
            </div>
        </div>

        <!-- Version -->
        <p class="text-center text-gray-600 text-xs mt-6">
            IP Manager v
            <?php echo APP_VERSION; ?> &copy;
            <?php echo date('Y'); ?>
        </p>
    </div>
</body>

</html>