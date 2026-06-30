<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Base URL handling
if (!defined('BASE_URL')) {
    $isProduction = (getenv('APP_ENV') === 'production' || getenv('RAILWAY_ENVIRONMENT') !== false);
    if ($isProduction) {
        define('BASE_URL', '/');
    } else {
        $folderName = basename(dirname(__DIR__)); // Go up one level from includes/
        define('BASE_URL', '/' . rawurlencode($folderName)); // No trailing slash
    }
}
?>
<?php
// Enforce Module Access Control
if (isset($_SESSION['user_id']) && function_exists('canAccessModule')) {
    $current_uri = $_SERVER['REQUEST_URI'];
    // Parse URL to find module name
    // Matches /modules/{module_name}/
    if (preg_match('/\/modules\/([a-zA-Z0-9_-]+)\//', $current_uri, $matches)) {
        $module = $matches[1];

        // Special case: Settings (Admin only, not in permissions list)
        if ($module === 'settings') {
            if ($_SESSION['role'] !== 'admin') {
                header("Location: " . BASE_URL);
                exit;
            }
        }
        // Standard modules
        elseif (!canAccessModule($module)) {
            header("Location: " . BASE_URL);
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="dark">

<head>
    <!-- CRITICAL: Apply theme & block transitions BEFORE any CSS renders to prevent FOUC -->
    <script>
        (function () {
            try {
                var t = localStorage.getItem('theme') || 'dark';
                if (t === 'light') document.documentElement.classList.remove('dark');
                else document.documentElement.classList.add('dark');
            } catch (e) {}
            document.documentElement.classList.add('no-transition');
        })();
    </script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?= isset($page_title) ? $page_title . ' - ' : '' ?>HSNM
    </title>
    <link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>/assets/favicon.svg">

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap"
        rel="stylesheet" />

    <!-- Custom Config -->
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
        /* Smooth transitions for theme switching */
        *,
        *::before,
        *::after {
            transition: background-color 0.3s ease,
                color 0.3s ease,
                border-color 0.3s ease,
                box-shadow 0.3s ease;
        }

        /* Suppress ALL transitions on initial page load to prevent FOUC */
        html.no-transition *,
        html.no-transition *::before,
        html.no-transition *::after {
            transition: none !important;
            animation: none !important;
        }

        /* Immediate body background via CSS var — does NOT wait for Tailwind CDN */
        body {
            background-color: var(--bg-primary, #101622);
            font-family: 'Inter', sans-serif;
        }

        /* Light Mode Variables */
        html:not(.dark) {
            --bg-primary: #fdfbf7;
            --bg-secondary: #f5f2eb;
            --bg-tertiary: #ebe8e1;
            --text-primary: #1c1917;
            --text-secondary: #44403c;
            --text-tertiary: #78716c;
            --border-color: #e6e2da;
            --hover-bg: #ebe8e1;
            --scrollbar-track: #f5f2eb;
            --scrollbar-thumb: #d6d3cd;
            --scrollbar-hover: #10b981;
        }

        /* Dark Mode Variables */
        html.dark {
            --bg-primary: #101622;
            --bg-secondary: #1a2130;
            --bg-tertiary: #232b3d;
            --text-primary: #ffffff;
            --text-secondary: #e2e8f0;
            --text-tertiary: #94a3b8;
            --border-color: #232b3d;
            --hover-bg: #1a2130;
            --scrollbar-track: #101622;
            --scrollbar-thumb: #232b3d;
            --scrollbar-hover: #10b981;
        }

        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
        }

        .custom-scrollbar::-webkit-scrollbar-track {
            background: var(--scrollbar-track);
        }

        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: var(--scrollbar-thumb);
            border-radius: 10px;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: var(--scrollbar-hover);
        }

        * {
            transition-property: background-color, border-color, color;
            transition-duration: 0.3s;
            transition-timing-function: ease;
        }

        /* ── Mobile Sidebar ── */
        #mobile-sidebar-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.55);
            z-index: 30;
            backdrop-filter: blur(2px);
        }

        #mobile-sidebar-overlay.open {
            display: block;
        }

        /* Mobile sidebar slide-in */
        @media (max-width: 767px) {

            /* The aside starts off-screen */
            aside#main-sidebar {
                position: fixed !important;
                top: 0;
                left: 0;
                bottom: 0;
                z-index: 40;
                transform: translateX(-100%);
                transition: transform 0.28s cubic-bezier(0.4, 0, 0.2, 1);
                width: 72vw;
                max-width: 280px;
                /* push below mobile top bar */
                top: 56px;
            }

            aside#main-sidebar.sidebar-open {
                transform: translateX(0);
            }

            /* Top bar space */
            #main-layout-wrapper {
                padding-top: 56px;
            }
        }

        /* ── Mobile Top Bar ── */
        #mobile-topbar {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 56px;
            z-index: 50;
            align-items: center;
            justify-content: space-between;
            padding: 0 16px;
            background: #101622;
            border-bottom: 1px solid #232b3d;
            box-shadow: 0 1px 8px rgba(0, 0, 0, 0.4);
        }

        html:not(.dark) #mobile-topbar {
            background: #ffffff;
            border-bottom-color: #e2e8f0;
        }

        @media (max-width: 767px) {
            #mobile-topbar {
                display: flex;
            }
        }

        /* Touch-friendly inputs on mobile */
        @media (max-width: 767px) {

            input,
            select,
            textarea {
                font-size: 16px !important;
                /* prevents iOS zoom */
            }

            /* Make modals scrollable on mobile */
            .fixed.inset-0.z-50>.flex {
                align-items: flex-start !important;
                padding-top: 16px;
                padding-bottom: 16px;
            }
        }
    </style>

    <!-- Theme Initialization Script (runs before page render) -->
    <script>
            // Theme already applied by the early blocking script above.
            // Remove no-transition after two rAF cycles so future theme-toggle
            // animations remain smooth but initial page load shows no flash.
            (function () {
                requestAnimationFrame(function () {
                    requestAnimationFrame(function () {
                        document.documentElement.classList.remove('no-transition');
                    });
                });
            })();

        // Theme toggle function
        function toggleTheme() {
            const html = document.documentElement;
            const isDark = html.classList.contains('dark');

            if (isDark) {
                html.classList.remove('dark');
                localStorage.setItem('theme', 'light');
            } else {
                html.classList.add('dark');
                localStorage.setItem('theme', 'dark');
            }
        }

        // Global Modal & Dirty Form Handling
        let isFormDirty = false;

        document.addEventListener('input', (e) => {
            const form = e.target.closest('form');
            // Only track "dirty" state for forms inside modals
            if (form && form.closest('.fixed.inset-0.z-50')) {
                isFormDirty = true;
            }
        });

        // Track changes in selects and checkboxes too
        document.addEventListener('change', (e) => {
            const form = e.target.closest('form');
            if (form && form.closest('.fixed.inset-0.z-50')) {
                isFormDirty = true;
            }
        });

        function resetDirtyState() {
            isFormDirty = false;
        }

        /**
         * System-wide Safe Modal Toggle
         * id: DOM element ID of the modal
         * force: if true, skips the "dirty" confirmation check
         */
        window.toggleModal = function (id, force = false) {
            const modal = document.getElementById(id);
            if (!modal) return;

            const isHidden = modal.classList.contains('hidden');

            if (isHidden) {
                // Opening modal: Reset dirty flag so we only track NEW changes
                resetDirtyState();
                modal.classList.remove('hidden');
            } else {
                // Closing modal: Check if any form fields within the modal were modified
                if (force || !isFormDirty || confirm("You have unsaved changes. Are you sure you want to discard them?")) {
                    modal.classList.add('hidden');
                    resetDirtyState();
                }
            }
        };

        // Enhanced Escape key handler using the safe toggle logic
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                const activeModals = document.querySelectorAll('.fixed.inset-0.z-50:not(.hidden)');
                if (activeModals.length > 0) {
                    // Try to close the most recently opened (last in DOM or designated active)
                    const targetModal = activeModals[activeModals.length - 1];
                    window.toggleModal(targetModal.id);
                }
            }
        });

        // Auto-Fit Tables on Orientation/Resize
        // Automatically adjusts table font size and padding to fit the viewport width
        (function () {
            function fitTables() {
                // Target tables inside overflow containers or main data tables
                const tables = document.querySelectorAll('.overflow-x-auto table, table.w-full');

                tables.forEach(table => {
                    const container = table.closest('.overflow-x-auto') || table.parentElement;
                    if (!container) return;

                    // Reset styles to measure natural width
                    table.querySelectorAll('th, td').forEach(c => {
                        c.style.fontSize = '';
                        c.style.padding = '';
                    });

                    // Check if table overflows
                    if (table.scrollWidth > container.clientWidth) {
                        const ratio = container.clientWidth / table.scrollWidth;

                        // Calculate new size (Default approx 11px, min 8px)
                        let newSize = Math.max(9, Math.floor(11 * ratio));

                        // Apply compact styles if needed
                        if (ratio < 1) {
                            table.querySelectorAll('th, td').forEach(cell => {
                                cell.style.fontSize = newSize + 'px';
                                cell.style.lineHeight = '1.2';
                                // Reduce padding proportionally
                                cell.style.padding = ratio < 0.8 ? '2px 2px' : '3px 4px';
                            });
                        }
                    }
                });
            }

            window.addEventListener('resize', () => requestAnimationFrame(fitTables));
            window.addEventListener('orientationchange', () => setTimeout(fitTables, 100));
            document.addEventListener('DOMContentLoaded', fitTables);
            window.addEventListener('load', fitTables);
        })();

        // ── Mobile Sidebar Toggle ──
        let _sidebarOpen = false;

        function openSidebar() {
            const sidebar  = document.getElementById('main-sidebar');
            const overlay  = document.getElementById('mobile-sidebar-overlay');
            const icon     = document.getElementById('hamburger-icon');
            if (!sidebar) return;
            sidebar.classList.add('sidebar-open');
            if (overlay) overlay.classList.add('open');
            if (icon) icon.textContent = 'close';
            _sidebarOpen = true;
            document.body.style.overflow = 'hidden'; // prevent background scroll
        }

        function closeSidebar() {
            const sidebar  = document.getElementById('main-sidebar');
            const overlay  = document.getElementById('mobile-sidebar-overlay');
            const icon     = document.getElementById('hamburger-icon');
            if (!sidebar) return;
            sidebar.classList.remove('sidebar-open');
            if (overlay) overlay.classList.remove('open');
            if (icon) icon.textContent = 'menu';
            _sidebarOpen = false;
            document.body.style.overflow = '';
        }

        function toggleSidebar() {
            _sidebarOpen ? closeSidebar() : openSidebar();
        }

        // Close sidebar when a nav link inside it is tapped on mobile
        document.addEventListener('DOMContentLoaded', function () {
            const sidebar = document.getElementById('main-sidebar');
            if (!sidebar) return;
            sidebar.querySelectorAll('nav a').forEach(function (link) {
                link.addEventListener('click', function () {
                    if (window.innerWidth < 768) closeSidebar();
                });
            });

            // Swipe-left to close sidebar on mobile
            let touchStartX = 0;
            sidebar.addEventListener('touchstart', function (e) {
                touchStartX = e.touches[0].clientX;
            }, { passive: true });
            sidebar.addEventListener('touchend', function (e) {
                const dx = e.changedTouches[0].clientX - touchStartX;
                if (dx < -60) closeSidebar(); // swipe left > 60px
            }, { passive: true });
        });
    </script>
</head>

<body class="bg-background-dark text-slate-400 custom-scrollbar">

    <!-- Mobile Top Bar -->
    <div id="mobile-topbar">
        <!-- Hamburger -->
        <button onclick="toggleSidebar()" id="hamburger-btn"
            class="size-10 flex items-center justify-center rounded-lg text-slate-400 hover:text-white hover:bg-white/10 transition-colors"
            aria-label="Open navigation">
            <span class="material-symbols-outlined text-[24px]" id="hamburger-icon">menu</span>
        </button>
        <!-- App name -->
        <div class="flex items-center gap-2">
            <div class="bg-primary/10 size-8 rounded-lg flex items-center justify-center text-primary">
                <span class="material-symbols-outlined text-[18px]">hub</span>
            </div>
            <span class="text-white text-sm font-bold"><?= defined('APP_NAME') ? APP_NAME : 'HSNM' ?></span>
        </div>
        <!-- Right actions -->
        <div class="flex items-center gap-1">
            <button onclick="toggleTheme()"
                class="size-10 flex items-center justify-center rounded-lg text-slate-400 hover:text-white hover:bg-white/10 transition-colors"
                aria-label="Toggle theme">
                <span class="material-symbols-outlined text-[20px] dark:block hidden">light_mode</span>
                <span class="material-symbols-outlined text-[20px] dark:hidden block">dark_mode</span>
            </button>
        </div>
    </div>

    <!-- Sidebar Overlay (mobile) -->
    <div id="mobile-sidebar-overlay" onclick="closeSidebar()"></div>

    <div id="main-layout-wrapper" class="flex h-screen overflow-hidden">