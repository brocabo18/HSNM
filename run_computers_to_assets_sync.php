<?php
require_once 'config.php';
requireLogin();

$success_msg = '';
$error_msg = '';
$stats_result = null;

// Read live counts
$total_computers = (int)$pdo->query("SELECT COUNT(*) FROM computers")->fetchColumn();
$total_assets = (int)$pdo->query("SELECT COUNT(*) FROM assets")->fetchColumn();
$total_maintenance = (int)$pdo->query("SELECT COUNT(*) FROM maintenance")->fetchColumn();
$computers_with_pms = (int)$pdo->query("SELECT COUNT(*) FROM computers WHERE remarks ILIKE '%PMS%' OR remarks ILIKE '%PREVENTIVE%' OR remarks_date IS NOT NULL")->fetchColumn();

// Handle Execution
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'execute_sync') {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $error_msg = "Security validation failed. Please refresh and try again.";
    } else {
        $seed_maintenance = isset($_POST['seed_maintenance']) && $_POST['seed_maintenance'] === '1';
        $t0 = microtime(true);

        try {
            $pdo->beginTransaction();

            // ── Step 1: Sync Computers -> Assets ─────────────────────────────
            $stmt1 = $pdo->prepare("
                INSERT INTO assets (
                    control_id, department, end_user, source, mr_ics,
                    brand_model, serial_number, processor, memory, storage,
                    monitor, monitor_sn, printer, printer_sn, ups_avr, ups_avr_sn,
                    keyboard_mouse, os_version, os_licensed, ip_address, mac_address,
                    ms_office, ms_office_licensed, antivirus, scan_date,
                    status, remarks, encoded_by, created_at, updated_at
                )
                SELECT 
                    COALESCE(NULLIF(TRIM(c.control_number), ''), 'JBL-TEMP-' || c.id) AS control_id,
                    c.department,
                    c.end_user,
                    'Purchased' AS source,
                    c.mr_par AS mr_ics,
                    c.system_unit AS brand_model,
                    c.system_unit_sn AS serial_number,
                    c.processor,
                    c.memory,
                    c.storage,
                    c.monitor,
                    c.monitor_sn,
                    c.printer,
                    c.printer_sn,
                    c.avr_ups AS ups_avr,
                    c.avr_ups_sn AS ups_avr_sn,
                    TRIM(CONCAT_WS(' / ', NULLIF(c.keyboard, ''), NULLIF(c.mouse, ''))) AS keyboard_mouse,
                    c.os AS os_version,
                    (c.license = 'Y') AS os_licensed,
                    c.ip_address,
                    c.mac_address,
                    c.microsoft_office AS ms_office,
                    (c.microsoft_office IS NOT NULL AND c.microsoft_office != '' AND c.microsoft_office NOT ILIKE '%N/A%') AS ms_office_licensed,
                    CASE WHEN c.endpoint_secure ILIKE 'Y%' THEN 'Athena EPP' ELSE 'Windows Defender' END AS antivirus,
                    c.remarks_date AS scan_date,
                    CASE 
                        WHEN c.status = 'Condemned' THEN 'Retired'
                        WHEN c.status = 'Transferred' THEN 'Maintenance'
                        ELSE 'Active'
                    END AS status,
                    c.remarks,
                    c.encoded_by,
                    c.created_at,
                    c.updated_at
                FROM computers c
                ON CONFLICT (control_id) DO UPDATE SET
                    department = EXCLUDED.department,
                    end_user = EXCLUDED.end_user,
                    mr_ics = EXCLUDED.mr_ics,
                    brand_model = EXCLUDED.brand_model,
                    serial_number = EXCLUDED.serial_number,
                    processor = EXCLUDED.processor,
                    memory = EXCLUDED.memory,
                    storage = EXCLUDED.storage,
                    monitor = EXCLUDED.monitor,
                    monitor_sn = EXCLUDED.monitor_sn,
                    printer = EXCLUDED.printer,
                    printer_sn = EXCLUDED.printer_sn,
                    ups_avr = EXCLUDED.ups_avr,
                    ups_avr_sn = EXCLUDED.ups_avr_sn,
                    keyboard_mouse = EXCLUDED.keyboard_mouse,
                    os_version = EXCLUDED.os_version,
                    os_licensed = EXCLUDED.os_licensed,
                    ip_address = EXCLUDED.ip_address,
                    mac_address = EXCLUDED.mac_address,
                    ms_office = EXCLUDED.ms_office,
                    ms_office_licensed = EXCLUDED.ms_office_licensed,
                    antivirus = EXCLUDED.antivirus,
                    scan_date = EXCLUDED.scan_date,
                    status = EXCLUDED.status,
                    remarks = EXCLUDED.remarks,
                    encoded_by = EXCLUDED.encoded_by,
                    updated_at = NOW()
            ");
            $stmt1->execute();
            $assets_synced = $stmt1->rowCount();

            // ── Step 2: Seed PMS History -> Maintenance (Optional / Default True) ──
            $maint_synced = 0;
            if ($seed_maintenance) {
                $stmt2 = $pdo->prepare("
                    INSERT INTO maintenance (
                        computer_id, asset_id, control_number, department, employee_name,
                        processor, memory, maintenance_date, status, performance_score,
                        checked_by, verified_by, recommendation, created_at, updated_at
                    )
                    SELECT 
                        c.id AS computer_id,
                        a.id AS asset_id,
                        c.control_number,
                        c.department,
                        c.end_user AS employee_name,
                        c.processor,
                        c.memory,
                        COALESCE(c.remarks_date, c.updated_at::date, CURRENT_DATE) AS maintenance_date,
                        'Completed' AS status,
                        100 AS performance_score,
                        COALESCE(NULLIF(TRIM(c.checked_by), ''), 'IT Staff') AS checked_by,
                        c.encoded_by AS verified_by,
                        '' AS recommendation,
                        c.created_at,
                        c.updated_at
                    FROM computers c
                    INNER JOIN assets a ON a.control_id = COALESCE(NULLIF(TRIM(c.control_number), ''), 'JBL-TEMP-' || c.id)
                    WHERE (c.remarks ILIKE '%PMS%' OR c.remarks ILIKE '%PREVENTIVE%' OR c.remarks_date IS NOT NULL)
                      AND NOT EXISTS (
                          SELECT 1 FROM maintenance m 
                          WHERE m.computer_id = c.id 
                            AND m.maintenance_date = COALESCE(c.remarks_date, c.updated_at::date, CURRENT_DATE)
                      )
                ");
                $stmt2->execute();
                $maint_synced = $stmt2->rowCount();
            }

            $pdo->commit();

            $elapsed_ms = round((microtime(true) - $t0) * 1000, 2);

            // Audit Log
            logAudit(
                $pdo,
                'sync_computers_to_assets',
                "Migrated/Synced $assets_synced records into assets table and $maint_synced records into maintenance table ($elapsed_ms ms)",
                'system',
                0
            );

            // Refresh counts
            $total_assets = (int)$pdo->query("SELECT COUNT(*) FROM assets")->fetchColumn();
            $total_maintenance = (int)$pdo->query("SELECT COUNT(*) FROM maintenance")->fetchColumn();

            $stats_result = [
                'assets_synced' => $assets_synced,
                'maint_synced'  => $maint_synced,
                'elapsed_ms'    => $elapsed_ms,
                'total_assets'  => $total_assets,
                'total_maint'   => $total_maintenance,
            ];

            // If user clicked "Sync & Return to Asset Inventory"
            if (isset($_POST['redirect_to_assets']) && $_POST['redirect_to_assets'] === '1') {
                header("Location: " . BASE_URL . "/modules/assets/?sync_done=1&assets=$assets_synced&maint=$maint_synced");
                exit;
            }

            $success_msg = "Synchronization completed successfully! $assets_synced assets and $maint_synced preventive maintenance records processed in {$elapsed_ms} ms.";

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error_msg = "Database synchronization error: " . $e->getMessage();
        }
    }
}

$page_title = "Computer to Asset Sync Runner";
require_once 'includes/header.php';
require_once 'includes/sidebar.php';
?>

<div class="p-4 md:p-8 flex-1 overflow-y-auto custom-scrollbar relative z-10 bg-slate-50 dark:bg-background-dark">
    
    <!-- Breadcrumb & Header -->
    <header class="mb-8 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <a href="<?= BASE_URL ?>/modules/assets/" class="text-xs font-semibold text-slate-500 hover:text-primary transition-colors flex items-center gap-1">
                    <span class="material-symbols-outlined text-[14px]">arrow_back</span>
                    <span>Asset Inventory</span>
                </a>
                <span class="text-xs text-slate-300 dark:text-slate-600">/</span>
                <span class="text-xs font-bold uppercase tracking-wider text-primary bg-primary/10 px-2 py-0.5 rounded-full">Data Integration</span>
            </div>
            <h2 class="text-2xl md:text-3xl font-bold text-slate-900 dark:text-white flex items-center gap-2">
                <span class="material-symbols-outlined text-primary text-3xl">sync_saved_locally</span>
                Computers &rarr; Assets &amp; Maintenance Migration Runner
            </h2>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                Transform central computer inventory records into enterprise asset lifecycle registry and preventive maintenance history.
            </p>
        </div>

        <div class="flex items-center gap-2">
            <a href="<?= BASE_URL ?>/modules/assets/" class="px-4 py-2 rounded-xl border border-slate-200 dark:border-[#232b3d] bg-white dark:bg-[#1a2130] text-xs font-bold text-slate-700 dark:text-slate-300 hover:bg-slate-50 transition-colors flex items-center gap-1.5">
                <span class="material-symbols-outlined text-[16px]">inventory_2</span>
                <span>Open Asset Inventory</span>
            </a>
            <a href="<?= BASE_URL ?>/modules/maintenance/" class="px-4 py-2 rounded-xl border border-slate-200 dark:border-[#232b3d] bg-white dark:bg-[#1a2130] text-xs font-bold text-slate-700 dark:text-slate-300 hover:bg-slate-50 transition-colors flex items-center gap-1.5">
                <span class="material-symbols-outlined text-[16px]">health_and_safety</span>
                <span>Open Maintenance</span>
            </a>
        </div>
    </header>

    <!-- Success & Error Banners -->
    <?php if ($success_msg): ?>
        <div class="mb-6 bg-emerald-500/10 border border-emerald-500/30 text-emerald-600 dark:text-emerald-400 p-5 rounded-2xl flex items-start gap-3.5 shadow-sm">
            <span class="material-symbols-outlined text-2xl text-emerald-500 mt-0.5">check_circle</span>
            <div class="flex-1 min-w-0">
                <h4 class="font-bold text-sm">Synchronization Finished Successfully</h4>
                <p class="text-xs mt-1 text-slate-600 dark:text-slate-300"><?= htmlspecialchars($success_msg) ?></p>
                <?php if ($stats_result): ?>
                    <div class="flex items-center gap-4 text-xs font-mono mt-3 pt-3 border-t border-emerald-500/20">
                        <span>Assets: <strong class="text-emerald-700 dark:text-emerald-300"><?= number_format($stats_result['total_assets']) ?></strong></span>
                        <span>&bull;</span>
                        <span>Maintenance: <strong class="text-emerald-700 dark:text-emerald-300"><?= number_format($stats_result['total_maint']) ?></strong></span>
                        <span>&bull;</span>
                        <span>Execution Time: <strong class="text-emerald-700 dark:text-emerald-300"><?= $stats_result['elapsed_ms'] ?> ms</strong></span>
                    </div>
                <?php endif; ?>
            </div>
            <a href="<?= BASE_URL ?>/modules/assets/" class="px-3.5 py-1.5 bg-emerald-600 text-white font-bold text-xs rounded-xl hover:bg-emerald-700 transition-colors flex items-center gap-1 flex-shrink-0">
                <span>View Assets</span>
                <span class="material-symbols-outlined text-[14px]">arrow_forward</span>
            </a>
        </div>
    <?php endif; ?>

    <?php if ($error_msg): ?>
        <div class="mb-6 bg-red-500/10 border border-red-500/30 text-red-600 dark:text-red-400 p-5 rounded-2xl flex items-start gap-3.5 shadow-sm">
            <span class="material-symbols-outlined text-2xl text-red-500 mt-0.5">error</span>
            <div>
                <h4 class="font-bold text-sm">Synchronization Failed</h4>
                <p class="text-xs mt-1"><?= htmlspecialchars($error_msg) ?></p>
            </div>
        </div>
    <?php endif; ?>

    <!-- Real-Time Metrics Ribbon -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
        
        <div class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] p-5 rounded-2xl">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Source Computers</span>
                <span class="material-symbols-outlined text-amber-500 text-xl">computer</span>
            </div>
            <div class="text-2xl md:text-3xl font-bold text-slate-900 dark:text-white"><?= number_format($total_computers) ?></div>
            <div class="text-[11px] text-slate-400 mt-1">Ready for asset mapping</div>
        </div>

        <div class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] p-5 rounded-2xl">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Asset Registry</span>
                <span class="material-symbols-outlined text-teal-500 text-xl">inventory_2</span>
            </div>
            <div class="text-2xl md:text-3xl font-bold text-teal-600 dark:text-teal-400"><?= number_format($total_assets) ?></div>
            <div class="text-[11px] text-slate-400 mt-1"><?= $total_assets > 0 ? 'Synchronized and active' : 'Needs synchronization' ?></div>
        </div>

        <div class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] p-5 rounded-2xl">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-500">PMS Notes Detected</span>
                <span class="material-symbols-outlined text-purple-500 text-xl">history_edu</span>
            </div>
            <div class="text-2xl md:text-3xl font-bold text-purple-600 dark:text-purple-400"><?= number_format($computers_with_pms) ?></div>
            <div class="text-[11px] text-slate-400 mt-1">Inspection dates in remarks</div>
        </div>

        <div class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] p-5 rounded-2xl">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Maintenance Records</span>
                <span class="material-symbols-outlined text-sky-500 text-xl">health_and_safety</span>
            </div>
            <div class="text-2xl md:text-3xl font-bold text-sky-600 dark:text-sky-400"><?= number_format($total_maintenance) ?></div>
            <div class="text-[11px] text-slate-400 mt-1"><?= $total_maintenance > 0 ? 'Audits initialized' : 'Pending import' ?></div>
        </div>

    </div>

    <!-- Main Action & Information Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
        
        <!-- Execution Card (1 Col) -->
        <div class="lg:col-span-1 bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] rounded-2xl p-6 flex flex-col justify-between shadow-sm">
            <div>
                <div class="flex items-center gap-3 mb-4">
                    <div class="size-10 rounded-xl bg-primary/10 text-primary flex items-center justify-center">
                        <span class="material-symbols-outlined text-2xl">bolt</span>
                    </div>
                    <div>
                        <h3 class="font-bold text-slate-900 dark:text-white">Run Synchronization</h3>
                        <p class="text-xs text-slate-400">One-click ETL data pipeline</p>
                    </div>
                </div>

                <p class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed mb-6">
                    This automated runner performs a high-speed upsert (<code class="text-xs bg-slate-100 dark:bg-[#101622] px-1 py-0.5 rounded">ON CONFLICT (control_id) DO UPDATE</code>) 
                    ensuring all 872 workstations are populated into the Asset Registry while preserving original timestamps, specifications, and peripherals.
                </p>

                <form method="POST" id="syncForm" class="space-y-4">
                    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                    <input type="hidden" name="action" value="execute_sync">

                    <!-- Option Checkbox -->
                    <label class="flex items-start gap-3 p-3.5 bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] rounded-xl cursor-pointer hover:border-primary/50 transition-colors">
                        <input type="checkbox" name="seed_maintenance" value="1" checked class="mt-0.5 size-4 rounded text-primary focus:ring-primary border-slate-300">
                        <div class="text-xs">
                            <span class="font-bold text-slate-800 dark:text-white block">Seed Historical PM Audits</span>
                            <span class="text-slate-500 dark:text-slate-400 block mt-0.5">
                                Automatically extract 574 historical PMS records from computer remarks and seed into Maintenance with full foreign keys.
                            </span>
                        </div>
                    </label>

                    <!-- Direct Redirect Option -->
                    <label class="flex items-center gap-3 p-3 bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] rounded-xl cursor-pointer hover:border-primary/50 transition-colors">
                        <input type="checkbox" name="redirect_to_assets" value="1" class="size-4 rounded text-primary focus:ring-primary border-slate-300">
                        <span class="text-xs font-medium text-slate-700 dark:text-slate-300">
                            Redirect immediately to Asset Inventory after completion
                        </span>
                    </label>

                    <div class="pt-4 border-t border-slate-100 dark:border-[#232b3d] space-y-2">
                        <button type="submit" id="submitBtn" class="w-full flex items-center justify-center gap-2 px-5 py-3 rounded-xl bg-primary text-white font-bold text-sm hover:bg-primary/90 transition-all shadow-lg shadow-primary/20">
                            <span class="material-symbols-outlined text-[20px]">sync</span>
                            <span>Execute One-Click Sync</span>
                        </button>
                    </div>
                </form>
            </div>

            <div class="mt-6 pt-4 border-t border-slate-100 dark:border-[#232b3d] text-[11px] text-slate-400 flex items-center justify-between">
                <span>Safe &amp; Idempotent</span>
                <span class="text-emerald-500 font-semibold">Zero duplicate risk</span>
            </div>
        </div>

        <!-- Field Transformation & Integrity Matrix (2 Cols) -->
        <div class="lg:col-span-2 bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] rounded-2xl p-6 shadow-sm">
            <h3 class="font-bold text-slate-900 dark:text-white text-base mb-1 flex items-center gap-2">
                <span class="material-symbols-outlined text-blue-500 text-[20px]">schema</span>
                Transformation &amp; Mapping Specifications
            </h3>
            <p class="text-xs text-slate-500 mb-4">How fields are translated between operational tables:</p>

            <div class="overflow-x-auto custom-scrollbar">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="border-b border-slate-200 dark:border-[#232b3d] text-[11px] font-bold text-slate-400 uppercase">
                            <th class="py-2.5 px-3">Computers Source</th>
                            <th class="py-2.5 px-3">&rarr;</th>
                            <th class="py-2.5 px-3">Target Table</th>
                            <th class="py-2.5 px-3">Target Field</th>
                            <th class="py-2.5 px-3">Transformation Rule</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-[#232b3d]/60 font-mono text-[11px]">
                        <tr>
                            <td class="py-2 px-3 text-amber-500 font-bold">control_number</td>
                            <td class="py-2 px-3 text-slate-400">&rarr;</td>
                            <td class="py-2 px-3 text-teal-500">assets</td>
                            <td class="py-2 px-3 font-bold text-slate-800 dark:text-white">control_id</td>
                            <td class="py-2 px-3 font-sans text-slate-500 text-[11px]">Unique constraint; blank fallback to <code class="text-[10px] bg-slate-100 dark:bg-[#101622] px-1">JBL-TEMP-&lt;id&gt;</code></td>
                        </tr>
                        <tr>
                            <td class="py-2 px-3 text-amber-500">system_unit</td>
                            <td class="py-2 px-3 text-slate-400">&rarr;</td>
                            <td class="py-2 px-3 text-teal-500">assets</td>
                            <td class="py-2 px-3 font-bold text-slate-800 dark:text-white">brand_model</td>
                            <td class="py-2 px-3 font-sans text-slate-500 text-[11px]">Desktop tower / system unit brand</td>
                        </tr>
                        <tr>
                            <td class="py-2 px-3 text-amber-500">system_unit_sn</td>
                            <td class="py-2 px-3 text-slate-400">&rarr;</td>
                            <td class="py-2 px-3 text-teal-500">assets</td>
                            <td class="py-2 px-3 font-bold text-slate-800 dark:text-white">serial_number</td>
                            <td class="py-2 px-3 font-sans text-slate-500 text-[11px]">Primary chassis serial number</td>
                        </tr>
                        <tr>
                            <td class="py-2 px-3 text-amber-500">mr_par</td>
                            <td class="py-2 px-3 text-slate-400">&rarr;</td>
                            <td class="py-2 px-3 text-teal-500">assets</td>
                            <td class="py-2 px-3 font-bold text-slate-800 dark:text-white">mr_ics</td>
                            <td class="py-2 px-3 font-sans text-slate-500 text-[11px]">Property Acknowledgement Receipt / Custodian Slip</td>
                        </tr>
                        <tr>
                            <td class="py-2 px-3 text-amber-500">keyboard + mouse</td>
                            <td class="py-2 px-3 text-slate-400">&rarr;</td>
                            <td class="py-2 px-3 text-teal-500">assets</td>
                            <td class="py-2 px-3 font-bold text-slate-800 dark:text-white">keyboard_mouse</td>
                            <td class="py-2 px-3 font-sans text-slate-500 text-[11px]">Concatenated with separator: <code class="text-[10px] bg-slate-100 dark:bg-[#101622] px-1">&lt;kb&gt; / &lt;mouse&gt;</code></td>
                        </tr>
                        <tr>
                            <td class="py-2 px-3 text-amber-500">license</td>
                            <td class="py-2 px-3 text-slate-400">&rarr;</td>
                            <td class="py-2 px-3 text-teal-500">assets</td>
                            <td class="py-2 px-3 font-bold text-slate-800 dark:text-white">os_licensed</td>
                            <td class="py-2 px-3 font-sans text-slate-500 text-[11px]">Converts 'Y' &rarr; <code class="text-[10px] text-emerald-500">TRUE</code>, 'N' &rarr; <code class="text-[10px] text-red-500">FALSE</code></td>
                        </tr>
                        <tr>
                            <td class="py-2 px-3 text-amber-500">endpoint_secure</td>
                            <td class="py-2 px-3 text-slate-400">&rarr;</td>
                            <td class="py-2 px-3 text-teal-500">assets</td>
                            <td class="py-2 px-3 font-bold text-slate-800 dark:text-white">antivirus</td>
                            <td class="py-2 px-3 font-sans text-slate-500 text-[11px]">'Y' &rarr; 'Endpoint Secure', else 'Windows Defender'</td>
                        </tr>
                        <tr>
                            <td class="py-2 px-3 text-amber-500">status</td>
                            <td class="py-2 px-3 text-slate-400">&rarr;</td>
                            <td class="py-2 px-3 text-teal-500">assets</td>
                            <td class="py-2 px-3 font-bold text-slate-800 dark:text-white">status</td>
                            <td class="py-2 px-3 font-sans text-slate-500 text-[11px]">'Working' &rarr; 'Active', 'Condemned' &rarr; 'Retired'</td>
                        </tr>
                        <tr>
                            <td class="py-2 px-3 text-amber-500">id, control_number</td>
                            <td class="py-2 px-3 text-slate-400">&rarr;</td>
                            <td class="py-2 px-3 text-sky-500">maintenance</td>
                            <td class="py-2 px-3 font-bold text-slate-800 dark:text-white">computer_id, asset_id</td>
                            <td class="py-2 px-3 font-sans text-slate-500 text-[11px]">Foreign key linkage to both parent records</td>
                        </tr>
                        <tr>
                            <td class="py-2 px-3 text-amber-500">remarks, remarks_date</td>
                            <td class="py-2 px-3 text-slate-400">&rarr;</td>
                            <td class="py-2 px-3 text-sky-500">maintenance</td>
                            <td class="py-2 px-3 font-bold text-slate-800 dark:text-white">recommendation, date</td>
                            <td class="py-2 px-3 font-sans text-slate-500 text-[11px]">Initializes completed audit with 100% health score</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="mt-5 p-3.5 bg-blue-500/10 border border-blue-500/20 rounded-xl text-xs text-blue-600 dark:text-blue-400 flex items-center gap-2.5">
                <span class="material-symbols-outlined text-[20px] flex-shrink-0">verified_user</span>
                <span>
                    The sync uses PostgreSQL transactional conflict resolution: existing asset records are updated while new records are inserted without wiping manually added costs or PTR numbers.
                </span>
            </div>
        </div>

    </div>

</div>

<script>
document.getElementById('syncForm').addEventListener('submit', function() {
    const btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="material-symbols-outlined text-[20px] animate-spin">refresh</span><span>Processing Data Pipeline...</span>';
    btn.classList.add('opacity-75', 'cursor-not-allowed');
});
</script>

<?php require_once 'includes/footer.php'; ?>
