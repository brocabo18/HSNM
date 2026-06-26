<?php
// Determine active page based on current path
$current_path = $_SERVER['REQUEST_URI'] ?? '';
$active_page = 'dashboard';
if (strpos($current_path, '/modules/routers') !== false)
    $active_page = 'routers';
elseif (strpos($current_path, '/modules/switches') !== false)
    $active_page = 'switches';
elseif (strpos($current_path, '/modules/ips') !== false)
    $active_page = 'ips';
elseif (strpos($current_path, '/modules/computers') !== false)
    $active_page = 'computers';
elseif (strpos($current_path, '/modules/firewall') !== false)
    $active_page = 'firewall';
elseif (strpos($current_path, '/modules/office') !== false)
    $active_page = 'office';
elseif (strpos($current_path, '/modules/logs') !== false)
    $active_page = 'logs';
elseif (strpos($current_path, '/modules/reconciliation') !== false)
    $active_page = 'reconciliation';
elseif (strpos($current_path, '/modules/ics') !== false)
    $active_page = 'ics';
elseif (strpos($current_path, '/modules/pabx') !== false)
    $active_page = 'pabx';
elseif (strpos($current_path, '/modules/ihoms_links') !== false)
    $active_page = 'ihoms_links';
elseif (strpos($current_path, '/modules/printers') !== false)
    $active_page = 'printers';
elseif (strpos($current_path, '/modules/changelog') !== false)
    $active_page = 'changelog';
elseif (strpos($current_path, '/modules/reports') !== false)
    $active_page = 'reports';
elseif (strpos($current_path, '/modules/settings') !== false)
    $active_page = 'settings';
?>
<?php
// Sidebar logic
?>
<!-- Sidebar -->
<aside id="main-sidebar"
    class="w-64 border-r border-[#232b3d] dark:border-[#232b3d] border-slate-200 bg-white dark:bg-[#101622] flex flex-col h-screen flex-shrink-0">
    <div class="p-4 md:p-6 flex items-center gap-3 border-b border-slate-200 dark:border-[#232b3d]">
        <div class="bg-primary/10 size-10 rounded-lg flex items-center justify-center text-primary flex-shrink-0">
            <span class="material-symbols-outlined">hub</span>
        </div>
        <div class="flex-1 min-w-0">
            <h1 class="text-slate-800 dark:text-white text-xs font-bold leading-none truncate"><?= APP_NAME ?></h1>
            <p class="text-slate-500 dark:text-[#9da6b9] text-xs mt-1">Network Management</p>
        </div>
        <!-- Mobile close button -->
        <button onclick="closeSidebar()"
            class="md:hidden size-8 flex items-center justify-center rounded-lg text-slate-400 hover:text-white hover:bg-white/10 transition-colors flex-shrink-0"
            aria-label="Close menu">
            <span class="material-symbols-outlined text-[17px]">close</span>
        </button>
    </div>

    <nav class="flex-1 p-4 space-y-1 overflow-y-auto custom-scrollbar">
        <a class="flex items-center gap-2.5 px-3 py-1.5 text-slate-600 dark:text-[#9da6b9] hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-[#1a2130] transition-colors rounded-lg <?= $active_page === 'dashboard' ? 'bg-primary/10 text-primary font-bold' : '' ?>"
            href="<?= BASE_URL ?>/">
            <span class="material-symbols-outlined text-[17px]">dashboard</span>
            <span class="text-xs font-medium">Overview</span>
        </a>

        <?php if (canAccessModule('routers')): ?>
            <a class="flex items-center gap-2.5 px-3 py-1.5 text-slate-600 dark:text-[#9da6b9] hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-[#1a2130] transition-colors rounded-lg <?= $active_page === 'routers' ? 'bg-primary/10 text-primary font-bold' : '' ?>"
                href="<?= BASE_URL ?>/modules/routers/">
                <span class="material-symbols-outlined text-[17px]">router</span>
                <span class="text-xs font-medium">Routers</span>
            </a>
        <?php endif; ?>

        <?php if (canAccessModule('switches')): ?>
            <a class="flex items-center gap-2.5 px-3 py-1.5 text-slate-600 dark:text-[#9da6b9] hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-[#1a2130] transition-colors rounded-lg <?= $active_page === 'switches' ? 'bg-primary/10 text-primary font-bold' : '' ?>"
                href="<?= BASE_URL ?>/modules/switches/">
                <span class="material-symbols-outlined text-[17px]">switch</span>
                <span class="text-xs font-medium">Switches</span>
            </a>
        <?php endif; ?>

        <?php if (canAccessModule('ips')): ?>
            <a class="flex items-center gap-2.5 px-3 py-1.5 text-slate-600 dark:text-[#9da6b9] hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-[#1a2130] transition-colors rounded-lg <?= $active_page === 'ips' ? 'bg-primary/10 text-primary font-bold' : '' ?>"
                href="<?= BASE_URL ?>/modules/ips/">
                <span class="material-symbols-outlined text-[17px]">dns</span>
                <span class="text-xs font-medium">IP Addresses</span>
            </a>
        <?php endif; ?>

        <?php if (canAccessModule('computers')): ?>
            <a class="flex items-center gap-2.5 px-3 py-1.5 text-slate-600 dark:text-[#9da6b9] hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-[#1a2130] transition-colors rounded-lg <?= $active_page === 'computers' ? 'bg-primary/10 text-primary font-bold' : '' ?>"
                href="<?= BASE_URL ?>/modules/computers/">
                <span class="material-symbols-outlined text-[17px]">computer</span>
                <span class="text-xs font-medium">Computers</span>
            </a>
        <?php endif; ?>

        <?php if (canAccessModule('firewall')): ?>
            <a class="flex items-center gap-2.5 px-3 py-1.5 text-slate-600 dark:text-[#9da6b9] hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-[#1a2130] transition-colors rounded-lg <?= $active_page === 'firewall' ? 'bg-primary/10 text-primary font-bold' : '' ?>"
                href="<?= BASE_URL ?>/modules/firewall/">
                <span class="material-symbols-outlined text-[17px]">security</span>
                <span class="text-xs font-medium">Firewall</span>
            </a>
        <?php endif; ?>

        <?php if (canAccessModule('office')): ?>
            <a class="flex items-center gap-2.5 px-3 py-1.5 text-slate-600 dark:text-[#9da6b9] hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-[#1a2130] transition-colors rounded-lg <?= $active_page === 'office' ? 'bg-primary/10 text-primary font-bold' : '' ?>"
                href="<?= BASE_URL ?>/modules/office/">
                <span class="material-symbols-outlined text-[17px]">apps</span>
                <span class="text-xs font-medium">MS Office</span>
            </a>
        <?php endif; ?>

        <?php if (canAccessModule('reconciliation')): ?>
            <a class="flex items-center gap-2.5 px-3 py-1.5 text-slate-600 dark:text-[#9da6b9] hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-[#1a2130] transition-colors rounded-lg <?= $active_page === 'reconciliation' ? 'bg-primary/10 text-primary font-bold' : '' ?>"
                href="<?= BASE_URL ?>/modules/reconciliation/">
                <span class="material-symbols-outlined text-[17px]">sync_alt</span>
                <span class="text-xs font-medium">Compare & Sync</span>
            </a>
        <?php endif; ?>

        <?php if (canAccessModule('ics')): ?>
            <a class="flex items-center gap-2.5 px-3 py-1.5 text-slate-600 dark:text-[#9da6b9] hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-[#1a2130] transition-colors rounded-lg <?= $active_page === 'ics' ? 'bg-primary/10 text-primary font-bold' : '' ?>"
                href="<?= BASE_URL ?>/modules/ics/">
                <span class="material-symbols-outlined text-[17px]">inventory</span>
                <span class="text-xs font-medium">ICS Inventory</span>
            </a>
        <?php endif; ?>

        <?php if (canAccessModule('pabx')): ?>
            <a class="flex items-center gap-2.5 px-3 py-1.5 text-slate-600 dark:text-[#9da6b9] hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-[#1a2130] transition-colors rounded-lg <?= $active_page === 'pabx' ? 'bg-primary/10 text-primary font-bold' : '' ?>"
                href="<?= BASE_URL ?>/modules/pabx/">
                <span class="material-symbols-outlined text-[17px]">phone_in_talk</span>
                <span class="text-xs font-medium">PABX Directory</span>
            </a>
        <?php endif; ?>

        <?php if (canAccessModule('ihoms_links')): ?>
            <a class="flex items-center gap-2.5 px-3 py-1.5 text-slate-600 dark:text-[#9da6b9] hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-[#1a2130] transition-colors rounded-lg <?= $active_page === 'ihoms_links' ? 'bg-primary/10 text-primary font-bold' : '' ?>"
                href="<?= BASE_URL ?>/modules/ihoms_links/">
                <span class="material-symbols-outlined text-[17px]">link</span>
                <span class="text-xs font-medium">IS Links</span>
            </a>
        <?php endif; ?>

        <?php if (canAccessModule('printers')): ?>
            <a class="flex items-center gap-2.5 px-3 py-1.5 text-slate-600 dark:text-[#9da6b9] hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-[#1a2130] transition-colors rounded-lg <?= $active_page === 'printers' ? 'bg-primary/10 text-primary font-bold' : '' ?>"
                href="<?= BASE_URL ?>/modules/printers/">
                <span class="material-symbols-outlined text-[17px]">print</span>
                <span class="text-xs font-medium">Printers</span>
            </a>
        <?php endif; ?>

        <?php if (canAccessModule('queueing_tv')): ?>
            <a class="flex items-center gap-2.5 px-3 py-1.5 text-slate-600 dark:text-[#9da6b9] hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-[#1a2130] transition-colors rounded-lg <?= $active_page === 'queueing_tv' ? 'bg-primary/10 text-primary font-bold' : '' ?>"
                href="<?= BASE_URL ?>/modules/queueing_tv/">
                <span class="material-symbols-outlined text-[17px]">tv</span>
                <span class="text-xs font-medium">Queueing TV</span>
            </a>
        <?php endif; ?>

        <?php if (canAccessModule('reports')): ?>
        <a class="flex items-center gap-2.5 px-3 py-1.5 text-slate-600 dark:text-[#9da6b9] hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-[#1a2130] transition-colors rounded-lg <?= $active_page === 'reports' ? 'bg-primary/10 text-primary font-bold' : '' ?>"
            href="<?= BASE_URL ?>/modules/reports/">
            <span class="material-symbols-outlined text-[17px]">bar_chart</span>
            <span class="text-xs font-medium">Reports</span>
        </a>
        <?php endif; ?>

        <div class="h-px bg-slate-200 dark:bg-[#232b3d] my-2"></div>

        <?php if ((!isset($_SESSION['role']) || $_SESSION['role'] !== 'viewer') && canAccessModule('logs')): ?>
            <a class="flex items-center gap-2.5 px-3 py-1.5 text-slate-600 dark:text-[#9da6b9] hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-[#1a2130] transition-colors rounded-lg <?= $active_page === 'logs' ? 'bg-primary/10 text-primary font-bold' : '' ?>"
                href="<?= BASE_URL ?>/modules/logs/">
                <span class="material-symbols-outlined text-[17px]">history</span>
                <span class="text-xs font-medium">Audit Logs</span>
            </a>
        <?php endif; ?>

        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
            <a class="flex items-center gap-2.5 px-3 py-1.5 text-slate-600 dark:text-[#9da6b9] hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-[#1a2130] transition-colors rounded-lg <?= $active_page === 'settings' ? 'bg-primary/10 text-primary font-bold' : '' ?>"
                href="<?= BASE_URL ?>/modules/settings/">
                <span class="material-symbols-outlined text-[17px]">settings</span>
                <span class="text-xs font-medium">Settings</span>
            </a>
        <?php endif; ?>

        <?php if (canAccessModule('changelog')): ?>
            <a class=" flex items-center gap-2.5 px-3 py-1.5 text-slate-600 dark:text-[#9da6b9] hover:text-slate-900
                dark:hover:text-white hover:bg-slate-100 dark:hover:bg-[#1a2130] transition-colors rounded-lg
                <?= $active_page === 'changelog' ? 'bg-primary/10 text-primary font-bold' : '' ?>" href="
                <?= BASE_URL ?>/modules/changelog/">
                <span class="material-symbols-outlined text-[17px]">update</span>
                <span class="text-xs font-medium">Changelog</span> </a>
        </nav>
    <?php endif; ?>

    <div class="mt-auto p-4 border-t border-slate-200 dark:border-[#232b3d] space-y-3">
        <!-- Theme Toggle Button -->
        <button onclick="toggleTheme()"
            class="w-full flex items-center gap-3 p-3 bg-[#1a2130] dark:bg-[#1a2130] bg-white rounded-xl hover:bg-[#f1f5f9] dark:hover:bg-[#242b3d] transition-all group border border-transparent dark:border-transparent hover:border-primary/20">
            <div class="size-8 rounded-full bg-primary/10 flex items-center justify-center text-primary">
                <!-- Sun icon for light mode (shown in dark mode) -->
                <span class="material-symbols-outlined text-[20px] dark:block hidden">light_mode</span>
                <!-- Moon icon for dark mode (shown in light mode) -->
                <span class="material-symbols-outlined text-[20px] dark:hidden block">dark_mode</span>
            </div>
            <div class="flex-1 text-left">
                <p class="text-xs font-bold text-slate-700 dark:text-white">
                    <span class="dark:hidden">Dark Mode</span>
                    <span class="hidden dark:inline">Light Mode</span>
                </p>
                <p class="text-[10px] text-slate-500">Switch theme</p>
            </div>
            <span
                class="material-symbols-outlined text-slate-400 group-hover:text-primary transition-colors text-[18px]">toggle_on</span>
        </button>

        <!-- User Profile / Logout -->
        <a href="<?= BASE_URL ?>/logout"
            class="flex items-center gap-3 p-3 bg-[#1a2130] dark:bg-[#1a2130] bg-white rounded-xl hover:bg-[#f1f5f9] dark:hover:bg-[#242b3d] transition-colors group border border-transparent dark:border-transparent hover:border-red-200 dark:hover:border-transparent">
            <div
                class="size-8 rounded-full bg-primary/10 flex items-center justify-center text-primary font-bold text-sm">
                <?= strtoupper(substr($_SESSION['username'] ?? 'U', 0, 1)) ?>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-xs font-bold text-slate-700 dark:text-white truncate">
                    <?= htmlspecialchars($_SESSION['username'] ?? 'User') ?>
                </p>
                <p class="text-[10px] text-slate-500">
                    <?= htmlspecialchars($_SESSION['role'] ?? 'user') ?>
                </p>
            </div>
            <span
                class="material-symbols-outlined text-slate-500 group-hover:text-red-400 transition-colors">logout</span>
        </a>
    </div>
</aside>