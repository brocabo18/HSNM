<?php
/**
 * Top Satellite Switcher Tabs for Computer Inventory
 * Displays a unified top tab bar showing real-time count badges across:
 * - Hospital (Main)
 * - JASMC - Sto. Tomas
 * - JASMC - Victoria Tarlac
 * - DOH-JBL-CMC
 * - Outsourced (JBL-OS-)
 *
 * Expected variable from caller:
 *   $active_comp_tab - 'main', 'jasmc_st', 'jasmc_vt', 'doh_jbl_cmc', 'outsourced' (default 'main')
 */

if (!isset($active_comp_tab)) {
    $active_comp_tab = 'main';
}

// Fetch real-time counts from each computer inventory table
$comp_tab_counts = [
    'main'        => 0,
    'jasmc_st'    => 0,
    'jasmc_vt'    => 0,
    'doh_jbl_cmc' => 0,
    'outsourced'  => 0,
];

try {
    $comp_tab_counts['main']        = (int)$pdo->query("SELECT COUNT(*) FROM computers")->fetchColumn();
    $comp_tab_counts['jasmc_st']    = (int)$pdo->query("SELECT COUNT(*) FROM computers_jasmc_st")->fetchColumn();
    $comp_tab_counts['jasmc_vt']    = (int)$pdo->query("SELECT COUNT(*) FROM computers_jasmc_vt")->fetchColumn();
    $comp_tab_counts['doh_jbl_cmc'] = (int)$pdo->query("SELECT COUNT(*) FROM computers_doh_jbl_cmc")->fetchColumn();
    $comp_tab_counts['outsourced']  = (int)$pdo->query("SELECT COUNT(*) FROM computers WHERE control_number LIKE 'JBL-OS-%'")->fetchColumn();
} catch (Exception $e) {
    // Graceful fallback if any query fails
}
?>

<!-- Top Satellite Switcher Tabs -->
<div class="no-print flex items-center gap-1 border-b border-slate-200 dark:border-[#232b3d] pb-2 overflow-x-auto w-full mb-3">
    <a href="<?= BASE_URL ?>/modules/computers/" 
       class="px-3 py-1.5 text-xs font-bold rounded-t-lg transition-colors flex items-center gap-1.5 border-b-2 <?= $active_comp_tab === 'main' ? 'border-primary text-primary bg-primary/10' : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-[#1a2130]' ?>">
        <span class="material-symbols-outlined text-[15px]">apartment</span>
        <span>Hospital (Main)</span>
        <span class="ml-1 px-1.5 py-0.5 rounded-full text-[10px] font-mono <?= $active_comp_tab === 'main' ? 'bg-primary text-white' : 'bg-slate-200 dark:bg-[#232b3d] text-slate-600 dark:text-slate-400' ?>">
            <?= number_format($comp_tab_counts['main']) ?>
        </span>
    </a>
    <a href="<?= BASE_URL ?>/modules/jasmc_st/" 
       class="px-3 py-1.5 text-xs font-bold rounded-t-lg transition-colors flex items-center gap-1.5 border-b-2 <?= $active_comp_tab === 'jasmc_st' ? 'border-primary text-primary bg-primary/10' : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-[#1a2130]' ?>">
        <span class="material-symbols-outlined text-[15px]">location_on</span>
        <span>JASMC &mdash; Sto. Tomas</span>
        <span class="ml-1 px-1.5 py-0.5 rounded-full text-[10px] font-mono <?= $active_comp_tab === 'jasmc_st' ? 'bg-primary text-white' : 'bg-slate-200 dark:bg-[#232b3d] text-slate-600 dark:text-slate-400' ?>">
            <?= number_format($comp_tab_counts['jasmc_st']) ?>
        </span>
    </a>
    <a href="<?= BASE_URL ?>/modules/jasmc_vt/" 
       class="px-3 py-1.5 text-xs font-bold rounded-t-lg transition-colors flex items-center gap-1.5 border-b-2 <?= $active_comp_tab === 'jasmc_vt' ? 'border-primary text-primary bg-primary/10' : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-[#1a2130]' ?>">
        <span class="material-symbols-outlined text-[15px]">location_on</span>
        <span>JASMC &mdash; Victoria Tarlac</span>
        <span class="ml-1 px-1.5 py-0.5 rounded-full text-[10px] font-mono <?= $active_comp_tab === 'jasmc_vt' ? 'bg-primary text-white' : 'bg-slate-200 dark:bg-[#232b3d] text-slate-600 dark:text-slate-400' ?>">
            <?= number_format($comp_tab_counts['jasmc_vt']) ?>
        </span>
    </a>
    <a href="<?= BASE_URL ?>/modules/doh_jbl_cmc/" 
       class="px-3 py-1.5 text-xs font-bold rounded-t-lg transition-colors flex items-center gap-1.5 border-b-2 <?= $active_comp_tab === 'doh_jbl_cmc' ? 'border-primary text-primary bg-primary/10' : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-[#1a2130]' ?>">
        <span class="material-symbols-outlined text-[15px]">location_on</span>
        <span>DOH-JBL-CMC</span>
        <span class="ml-1 px-1.5 py-0.5 rounded-full text-[10px] font-mono <?= $active_comp_tab === 'doh_jbl_cmc' ? 'bg-primary text-white' : 'bg-slate-200 dark:bg-[#232b3d] text-slate-600 dark:text-slate-400' ?>">
            <?= number_format($comp_tab_counts['doh_jbl_cmc']) ?>
        </span>
    </a>
    <a href="<?= BASE_URL ?>/modules/outsourced/" 
       class="px-3 py-1.5 text-xs font-bold rounded-t-lg transition-colors flex items-center gap-1.5 border-b-2 <?= $active_comp_tab === 'outsourced' ? 'border-violet-500 text-violet-500 bg-violet-500/10' : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-[#1a2130]' ?>"
       title="Outsourced / Donated Computers (JBL-OS-)">
        <span class="material-symbols-outlined text-[15px]">handshake</span>
        <span>Outsourced</span>
        <span class="ml-1 px-1.5 py-0.5 rounded-full text-[10px] font-mono <?= $active_comp_tab === 'outsourced' ? 'bg-violet-500 text-white' : 'bg-slate-200 dark:bg-[#232b3d] text-slate-600 dark:text-slate-400' ?>">
            <?= number_format($comp_tab_counts['outsourced']) ?>
        </span>
    </a>
</div>
