<?php
/**
 * Forgot Password Placeholder Page
 */

session_start();
require_once 'auth.php';
require_once 'config.php';

$pdo = getDBConnection();
$appSettings = getSystemSettings($pdo);
$portalName = $appSettings['portal_name'] ?? 'Network Switch Inventory';
$primaryColor = $appSettings['theme_color'] ?? '#135bec';
?>
<!DOCTYPE html>
<html class="dark" lang="en">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>Forgot Password -
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
</head>

<body class="bg-background-dark font-display text-slate-100 min-h-screen flex items-center justify-center p-6 relative">
    <div class="w-full max-w-md text-center">
        <div class="bg-[#1a2130] border border-border-dark rounded-2xl shadow-2xl p-8">
            <div
                class="bg-amber-500/10 size-16 rounded-full flex items-center justify-center text-amber-500 mx-auto mb-6">
                <span class="material-symbols-outlined text-4xl">lock_reset</span>
            </div>

            <h2 class="text-2xl font-bold text-white mb-4">Password Reset Required</h2>

            <div class="bg-background-dark/50 rounded-xl p-6 mb-8 text-left border border-border-dark/30">
                <p class="text-sm text-slate-400 mb-4 leading-relaxed">
                    For security reasons, automated password recovery is currently disabled for this portal.
                </p>
                <div class="flex items-start gap-3">
                    <span class="material-symbols-outlined text-primary text-xl">support_agent</span>
                    <div>
                        <p class="text-sm font-bold text-white">Contact Administrator</p>
                        <p class="text-xs text-slate-500">Please reach out to your system administrator or IT department
                            to reset your credentials.</p>
                    </div>
                </div>
            </div>

            <a href="login.php"
                class="inline-flex items-center gap-2 text-sm font-semibold text-primary hover:text-primary/80 transition-colors">
                <span class="material-symbols-outlined text-lg">arrow_back</span>
                Back to Login
            </a>
        </div>
    </div>
</body>

</html>