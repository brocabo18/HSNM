<?php
/**
 * Forgot Password Placeholder Page
 * IHOMS Router Inventory Management System
 */

session_start();
require_once 'db.php';

$portalName = "IHOMS Router Portal";
$portalSubtitle = "IHOMS";
$primaryColor = "#10b981";
?>
<!DOCTYPE html>
<html class="dark" lang="en">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>Recovery - <?php echo htmlspecialchars($portalName); ?></title>
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
    <div class="relative z-10 w-full max-w-md animate-slide-up">
        <!-- Header -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center gap-3 mb-4">
                <div
                    class="bg-amber-500 size-14 rounded-xl flex items-center justify-center text-white shadow-lg shadow-amber-500/30">
                    <span class="material-symbols-outlined text-3xl">shield_person</span>
                </div>
                <div class="flex flex-col items-start">
                    <h1 class="text-white text-2xl font-bold leading-none">Access Recovery
                    </h1>
                    <p class="text-amber-500 text-sm mt-1 font-bold">
                        Security Protocol 403
                    </p>
                </div>
            </div>
            <h2 class="text-xl font-semibold text-white mb-2">Restricted Action</h2>
            <p class="text-sm text-slate-400">Manual verification required</p>
        </div>

        <!-- Card -->
        <div class="bg-[#1a2130] border border-border-dark rounded-2xl shadow-2xl overflow-hidden">
            <!-- Card Header -->
            <div class="bg-gradient-to-r from-amber-500/10 to-transparent p-6 border-b border-border-dark">
                <h3 class="text-lg font-bold text-white flex items-center gap-2">
                    <span class="material-symbols-outlined text-amber-500">lock_reset</span>
                    Recovery Disabled
                </h3>
            </div>

            <!-- Content -->
            <div class="p-6 space-y-6">
                <div class="bg-background-dark border border-border-dark rounded-xl p-4 flex gap-4">
                    <div class="bg-amber-500/10 p-2.5 rounded-lg h-fit">
                        <span class="material-symbols-outlined text-amber-500 text-xl">verified_user</span>
                    </div>
                    <div>
                        <p class="text-sm text-slate-300 leading-relaxed font-medium">
                            Automated passkey recovery is currently deactivated for this terminal to prevent
                            unauthorized
                            lateral movement.
                        </p>
                    </div>
                </div>

                <div class="space-y-3">
                    <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Protocol Instruction</h4>
                    <div class="flex items-start gap-3 text-sm text-slate-300">
                        <span class="material-symbols-outlined text-primary text-lg mt-0.5">arrow_right</span>
                        <p>Contact your direct Network Supervisor for manual override.</p>
                    </div>
                    <div class="flex items-start gap-3 text-sm text-slate-300">
                        <span class="material-symbols-outlined text-primary text-lg mt-0.5">arrow_right</span>
                        <p>Submit a formal access request via internal channels.</p>
                    </div>
                </div>

                <!-- Return Button -->
                <a href="login"
                    class="w-full flex items-center justify-center gap-2 px-6 py-3 bg-white/5 border border-white/10 text-white text-sm font-bold rounded-lg hover:bg-white/10 transition-all mt-4">
                    <span class="material-symbols-outlined text-xl">arrow_back</span>
                    Return to Login
                </a>
            </div>

            <!-- Card Footer -->
            <div class="bg-background-dark border-t border-border-dark p-4 text-center">
                <p class="text-[11px] text-slate-400">
                    Incident ID: <span class="font-mono text-slate-300"><?= uniqid('INC-') ?></span>
                </p>
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
    <div class="fixed top-10 left-10 w-72 h-72 bg-amber-500/5 rounded-full blur-3xl animate-pulse-slow"></div>
    <div class="fixed bottom-10 right-10 w-96 h-96 bg-primary/5 rounded-full blur-3xl animate-pulse-slow"
        style="animation-delay: 1.5s;"></div>
</body>

</html>