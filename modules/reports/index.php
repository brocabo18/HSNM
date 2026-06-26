<?php
require_once '../../config.php';
requireLogin();

// NOTE: we deliberately use $rpt_module (not $module) here to avoid
// collision with header.php which sets its own $module variable.

// --- Report Configuration ---
$report_configs = [
    'computers' => [
        'label'  => 'Computer Inventory',
        'icon'   => 'computer',
        'color'  => 'blue',
        'table'  => 'computers',
        'dept_col' => 'department',
        'reports' => [
            'date_encoded'  => ['label' => 'By Date Encoded',      'column' => 'created_at',     'multi' => false, 'type' => 'detail_list'],
            'encoded_by'    => ['label' => 'By Encoded By',        'column' => 'encoded_by',       'multi' => false],
            'location'      => ['label' => 'By Location',         'column' => 'department',       'multi' => false],
            'building'      => ['label' => 'By Building',         'column' => 'department',       'multi' => false,
                'sql_expr' => "CASE
                    WHEN department ILIKE 'ACIS%'          THEN 'ACIS'
                    WHEN department ILIKE 'Bayanihan%'     THEN 'Bayanihan'
                    WHEN department ILIKE 'Bio Safety%'    THEN 'Bio Safety'
                    WHEN department ILIKE 'Capiz%'         THEN 'Capiz'
                    WHEN department ILIKE 'Dietary%'       THEN 'Dietary'
                    WHEN department ILIKE 'Frontline%'     THEN 'Frontline'
                    WHEN department ILIKE 'HOPSS%'         THEN 'HOPSS'
                    WHEN department ILIKE 'Isolation%'     THEN 'Isolation'
                    WHEN department ILIKE 'Lingap Baga%'   THEN 'Lingap Baga'
                    WHEN department ILIKE 'Medicine%'      THEN 'Medicine'
                    WHEN department ILIKE 'OB-Gyne/Pedia%' THEN 'OB-Gyne/Pedia'
                    WHEN department ILIKE 'OPD%'           THEN 'OPD'
                    WHEN department ILIKE 'Orthopaedics%'  THEN 'Orthopaedics'
                    WHEN department ILIKE 'Surgery%'       THEN 'Surgery'
                    WHEN department ILIKE 'Trauma%'        THEN 'Trauma'
                    WHEN department ILIKE 'Wellness%'      THEN 'Wellness'
                    ELSE '(Not specified)' END"],
            'building_floor' => ['label' => 'By Building per Floor', 'column' => 'department', 'multi' => false,
                'sql_expr' => "
                    (CASE
                        WHEN department ILIKE 'ACIS%'          THEN 'ACIS'
                        WHEN department ILIKE 'Bayanihan%'     THEN 'Bayanihan'
                        WHEN department ILIKE 'Bio Safety%'    THEN 'Bio Safety'
                        WHEN department ILIKE 'Capiz%'         THEN 'Capiz'
                        WHEN department ILIKE 'Dietary%'       THEN 'Dietary'
                        WHEN department ILIKE 'Frontline%'     THEN 'Frontline'
                        WHEN department ILIKE 'HOPSS%'         THEN 'HOPSS'
                        WHEN department ILIKE 'Isolation%'     THEN 'Isolation'
                        WHEN department ILIKE 'Lingap Baga%'   THEN 'Lingap Baga'
                        WHEN department ILIKE 'Medicine%'      THEN 'Medicine'
                        WHEN department ILIKE 'OB-Gyne/Pedia%' THEN 'OB-Gyne/Pedia'
                        WHEN department ILIKE 'OPD%'           THEN 'OPD'
                        WHEN department ILIKE 'Orthopaedics%'  THEN 'Orthopaedics'
                        WHEN department ILIKE 'Surgery%'       THEN 'Surgery'
                        WHEN department ILIKE 'Trauma%'        THEN 'Trauma'
                        WHEN department ILIKE 'Wellness%'      THEN 'Wellness'
                        ELSE '(No Building)'
                    END)
                    || ' - ' ||
                    (CASE
                        WHEN department ILIKE '% GF %' OR department ILIKE '% GF' THEN 'GF'
                        WHEN department ILIKE '% 2F %' OR department ILIKE '% 2F' THEN '2F'
                        WHEN department ILIKE '% 3F %' OR department ILIKE '% 3F' THEN '3F'
                        WHEN department ILIKE '% 4F %' OR department ILIKE '% 4F' THEN '4F'
                        WHEN department ILIKE '% 5F %' OR department ILIKE '% 5F' THEN '5F'
                        WHEN department ILIKE '% 6F %' OR department ILIKE '% 6F' THEN '6F'
                        WHEN department ILIKE '% 7F %' OR department ILIKE '% 7F' THEN '7F'
                        ELSE '(No Floor)'
                    END)
                "],

            'building_floor_dept' => ['label' => 'By Building per Floor per Department', 'column' => 'department', 'multi' => false,
                'sql_expr' => "
                    (CASE
                        WHEN department ILIKE 'ACIS%'          THEN 'ACIS'
                        WHEN department ILIKE 'Bayanihan%'     THEN 'Bayanihan'
                        WHEN department ILIKE 'Bio Safety%'    THEN 'Bio Safety'
                        WHEN department ILIKE 'Capiz%'         THEN 'Capiz'
                        WHEN department ILIKE 'Dietary%'       THEN 'Dietary'
                        WHEN department ILIKE 'Frontline%'     THEN 'Frontline'
                        WHEN department ILIKE 'HOPSS%'         THEN 'HOPSS'
                        WHEN department ILIKE 'Isolation%'     THEN 'Isolation'
                        WHEN department ILIKE 'Lingap Baga%'   THEN 'Lingap Baga'
                        WHEN department ILIKE 'Medicine%'      THEN 'Medicine'
                        WHEN department ILIKE 'OB-Gyne/Pedia%' THEN 'OB-Gyne/Pedia'
                        WHEN department ILIKE 'OPD%'           THEN 'OPD'
                        WHEN department ILIKE 'Orthopaedics%'  THEN 'Orthopaedics'
                        WHEN department ILIKE 'Surgery%'       THEN 'Surgery'
                        WHEN department ILIKE 'Trauma%'        THEN 'Trauma'
                        WHEN department ILIKE 'Wellness%'      THEN 'Wellness'
                        ELSE '(No Building)'
                    END)
                    || ' - ' ||
                    (CASE
                        WHEN department ILIKE '% GF %' OR department ILIKE '% GF' THEN 'GF'
                        WHEN department ILIKE '% 2F %' OR department ILIKE '% 2F' THEN '2F'
                        WHEN department ILIKE '% 3F %' OR department ILIKE '% 3F' THEN '3F'
                        WHEN department ILIKE '% 4F %' OR department ILIKE '% 4F' THEN '4F'
                        WHEN department ILIKE '% 5F %' OR department ILIKE '% 5F' THEN '5F'
                        WHEN department ILIKE '% 6F %' OR department ILIKE '% 6F' THEN '6F'
                        WHEN department ILIKE '% 7F %' OR department ILIKE '% 7F' THEN '7F'
                        ELSE '(No Floor)'
                    END)
                    || ' - ' ||
                    COALESCE(
                        NULLIF(TRIM(
                            REGEXP_REPLACE(
                                REGEXP_REPLACE(
                                    department,
                                    '^(ACIS|Bayanihan|Bio Safety|Capiz|Dietary|Frontline|HOPSS|Isolation|Lingap Baga|Medicine|OB-Gyne/Pedia|OPD|Orthopaedics|Surgery|Trauma|Wellness)\\s*',
                                    '', 'i'
                                ),
                                '^(GF|2F|3F|4F|5F|6F|7F)\\s*',
                                '', 'i'
                            )
                        ), ''),
                        '(No Department)'
                    )
                "],

            'os'            => ['label' => 'Operating System',     'column' => 'os',               'multi' => false],
            'ms_office'     => ['label' => 'Microsoft Office',     'column' => 'microsoft_office', 'multi' => false],
            'processor'     => ['label' => 'Processor',            'column' => 'processor',        'multi' => false],
            'memory'        => ['label' => 'Memory / RAM',         'column' => 'memory',           'multi' => false],
            'storage'       => ['label' => 'Storage',              'column' => 'storage',          'multi' => false],
            'printer'       => ['label' => 'Printers',             'column' => 'printer',          'multi' => true],
            'scanner'       => ['label' => 'Scanners',             'column' => 'scanner',          'multi' => true],
            'avr_ups'       => ['label' => 'AVR / UPS',            'column' => 'avr_ups',          'multi' => true],
            'system_unit'   => ['label' => 'System Unit Brand',    'column' => 'system_unit',      'multi' => false],
            'monitor'       => ['label' => 'Monitor Brand',        'column' => 'monitor',          'multi' => false],
            'checked_by'    => ['label' => 'Checked By',           'column' => 'checked_by',       'multi' => false],
        ],
    ],
    'ips' => [
        'label'  => 'IP Addresses',
        'icon'   => 'dns',
        'color'  => 'emerald',
        'table'  => 'ips',
        'dept_col' => 'location',
        'reports' => [
            'status'      => ['label' => 'By Status',       'column' => 'status',      'multi' => false],
            'device_type' => ['label' => 'By Device Type',  'column' => 'device_type', 'multi' => false],
            'location'    => ['label' => 'By Location',     'column' => 'location',    'multi' => false],
        ],
    ],
    'routers' => [
        'label'  => 'Routers',
        'icon'   => 'router',
        'color'  => 'violet',
        'table'  => 'routers',
        'dept_col' => 'location',
        'reports' => [
            'status'   => ['label' => 'By Status',   'column' => 'status',   'multi' => false],
            'brand'    => ['label' => 'By Brand',    'column' => 'brand',    'multi' => false],
            'location' => ['label' => 'By Location', 'column' => 'location', 'multi' => false],
        ],
    ],
    'switches' => [
        'label'    => 'Switch Inventory',
        'icon'     => 'hub',
        'color'    => 'cyan',
        'table'    => 'switches',
        'dept_col' => 'building_location',
        'reports'  => [
            'status'            => ['label' => 'By Status',            'column' => 'status',            'multi' => false],
            'manufacturer'      => ['label' => 'By Manufacturer',      'column' => 'manufacturer',      'multi' => false],
            'building_location' => ['label' => 'By Building Location', 'column' => 'building_location', 'multi' => false],
            'floor'             => ['label' => 'By Floor',             'column' => 'floor',             'multi' => false],
            'building_floor'    => ['label' => 'By Building per Floor', 'column' => 'building_location', 'multi' => false,
                'sql_expr' => "
                    (CASE WHEN building_location IS NULL OR TRIM(building_location) = '' THEN '(No Building)' ELSE TRIM(building_location) END)
                    || ' - ' ||
                    (CASE WHEN floor IS NULL OR TRIM(floor) = '' THEN '(No Floor)' ELSE TRIM(floor) END)
                "
            ],
            'model'             => ['label' => 'By Model',             'column' => 'model',             'multi' => false],
        ],
    ],
    'printers' => [
        'label'    => 'Printer Inventory',
        'icon'     => 'print',
        'color'    => 'amber',
        'table'    => 'printers',
        'dept_col' => 'location',
        'reports'  => [
            'location'           => ['label' => 'By Location',          'column' => 'location',           'multi' => false],
            'person_responsible' => ['label' => 'By Person Responsible', 'column' => 'person_responsible', 'multi' => false],
            'remarks'            => ['label' => 'By Remarks / Status',   'column' => 'remarks',            'multi' => false],
        ],
    ],
    'pabx' => [
        'label'    => 'PABX Directory',
        'icon'     => 'phone_in_talk',
        'color'    => 'rose',
        'table'    => 'pabx_directory',   // actual table name
        'dept_col' => 'department',
        'reports'  => [
            'department'   => ['label' => 'By Department',  'column' => 'department',   'multi' => false],
            'building'     => ['label' => 'By Building',    'column' => 'building',     'multi' => false],
            'floor'        => ['label' => 'By Floor',       'column' => 'floor',        'multi' => false],
            'display_name' => ['label' => 'By Display Name','column' => 'display_name', 'multi' => false],
        ],
    ],
];

// --- Params (use $rpt_module not $module) ---
$rpt_module      = $_GET['module']       ?? 'computers';
$report_type     = $_GET['report_type']  ?? '';
$dept_filter     = $_GET['department']   ?? 'All';
$export_csv      = isset($_GET['export']) && $_GET['export'] === 'csv';

// Date range params for detail_list reports
$date_from      = $_GET['date_from']     ?? '';
$date_to        = $_GET['date_to']       ?? '';
$encoder_filter = $_GET['encoder']       ?? 'All';

// Validate module & report type
if (!isset($report_configs[$rpt_module])) $rpt_module = 'computers';
$cfg = $report_configs[$rpt_module];
if (!$report_type || !isset($cfg['reports'][$report_type])) {
    $report_type = array_key_first($cfg['reports']);
}
$rpt = $cfg['reports'][$report_type];

// Check if this is a detail-list report (e.g., By Date)
$is_detail_list = ($rpt['type'] ?? '') === 'detail_list';

// --- Fetch Report Data ---
$table    = $cfg['table'];
$column   = $rpt['column'];
$is_multi = $rpt['multi'];
$dept_col = $cfg['dept_col'];

// Build dept filter clause
$dept_where  = '';
$dept_params = [];
if ($dept_filter !== 'All' && !empty($dept_filter) && $dept_col) {
    $dept_where  = "WHERE $dept_col = ?";
    $dept_params = [$dept_filter];
}

// Get departments for filter dropdown
$dept_values = [];
try {
    if ($dept_col) {
        $d_stmt = $pdo->prepare("SELECT DISTINCT $dept_col FROM $table WHERE $dept_col IS NOT NULL AND $dept_col != '' ORDER BY $dept_col");
        $d_stmt->execute();
        $dept_values = $d_stmt->fetchAll(PDO::FETCH_COLUMN);
    }
} catch (Exception $e) {}

// Get total records
$total_records = 0;
try {
    $t_stmt = $pdo->prepare("SELECT COUNT(*) FROM $table $dept_where");
    $t_stmt->execute($dept_params);
    $total_records = (int)$t_stmt->fetchColumn();
} catch (Exception $e) {}

// -------------------------------------------------------
// DETAIL LIST report (By Date Encoded) — fetch individual records
// -------------------------------------------------------
$detail_rows    = [];   // raw records for detail_list
$detail_grouped = [];   // grouped by date  [ 'YYYY-MM-DD' => [rows...] ]
$report_rows    = [];   // summary rows for grouped reports
$grand_total    = 0;
$error_msg      = null;

if ($is_detail_list) {
    // Build WHERE with optional dept, date range, and encoder
    $where_parts  = [];
    $query_params = [];

    if ($dept_filter !== 'All' && !empty($dept_filter) && $dept_col) {
        $where_parts[]  = "c.$dept_col = ?";
        $query_params[] = $dept_filter;
    }
    if (!empty($date_from)) {
        $where_parts[]  = "DATE(c.$column) >= ?";
        $query_params[] = $date_from;
    }
    if (!empty($date_to)) {
        $where_parts[]  = "DATE(c.$column) <= ?";
        $query_params[] = $date_to;
    }
    if ($encoder_filter !== 'All' && !empty($encoder_filter)) {
        $where_parts[]  = "c.encoded_by = ?";
        $query_params[] = $encoder_filter;
    }
    $where_sql = $where_parts ? 'WHERE ' . implode(' AND ', $where_parts) : '';

    // Fetch distinct encoded_by values for dropdown
    $encoder_values = [];
    try {
        $ev_stmt = $pdo->prepare("SELECT DISTINCT encoded_by FROM $table WHERE encoded_by IS NOT NULL AND encoded_by != '' ORDER BY encoded_by");
        $ev_stmt->execute();
        $encoder_values = $ev_stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {}

    try {
        $sql  = "SELECT c.id, c.control_number, c.department, c.end_user,
                        c.encoded_by, c.updated_at,
                        c.$column AS encoded_at,
                        (
                            SELECT u.full_name
                            FROM audit_logs al
                            JOIN users u ON u.id = al.user_id
                            WHERE al.resource_type = 'computer'
                              AND al.resource_id = c.id
                              AND al.action_type IN ('update_computer','edit_computer')
                            ORDER BY al.created_at DESC
                            LIMIT 1
                        ) AS last_edited_by
                 FROM $table c
                 $where_sql
                 ORDER BY c.$column DESC, c.id DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($query_params);
        $detail_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $grand_total = count($detail_rows);

        // Group by date
        foreach ($detail_rows as $row) {
            $day = $row['encoded_at'] ? date('Y-m-d', strtotime($row['encoded_at'])) : '(No Date)';
            $detail_grouped[$day][] = $row;
        }
    } catch (Exception $e) {
        $error_msg = $e->getMessage();
    }

    // CSV export for detail list
    if ($export_csv) {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="report_computers_by_date_' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Date Encoded', 'Time', 'Control Number', 'End User', 'Location', 'Encoded By', 'Last Edited By', 'Last Edited At']);
        foreach ($detail_rows as $row) {
            $day      = $row['encoded_at'] ? date('Y-m-d', strtotime($row['encoded_at'])) : '';
            $time_str = $row['encoded_at'] ? date('h:i A', strtotime($row['encoded_at'])) : '';
            $last_at  = $row['updated_at']  ? date('Y-m-d h:i A', strtotime($row['updated_at'])) : '';
            fputcsv($out, [
                $day,
                $time_str,
                $row['control_number'],
                $row['end_user'],
                $row['department'],
                $row['encoded_by'] ?? '',
                $row['last_edited_by'] ?? '',
                $last_at,
            ]);
        }
        fputcsv($out, ['TOTAL', $grand_total, '', '', '', '', '', '']);
        fclose($out);
        exit;
    }

} else {
    // Build grouped report query
    try {
        if ($is_multi) {
            // Multi-value field: unnest comma-separated values
            $base_where = $dept_where
                ? $dept_where . " AND $column IS NOT NULL AND $column != ''"
                : "WHERE $column IS NOT NULL AND $column != ''";
            $sql = "
                SELECT TRIM(val) AS value, COUNT(*) AS count
                FROM $table, unnest(string_to_array($column, ',')) AS val
                $base_where
                GROUP BY TRIM(val)
                ORDER BY count DESC
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($dept_params);
            $report_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($report_rows as $r) $grand_total += (int)$r['count'];

            // Count records with no value
            $null_and = $dept_where ? " AND" : " WHERE";
            $null_stmt = $pdo->prepare("SELECT COUNT(*) FROM $table $dept_where$null_and ($column IS NULL OR TRIM($column) = '')");
            $null_stmt->execute($dept_params);
            $no_value_count = (int)$null_stmt->fetchColumn();
            if ($no_value_count > 0) {
                $report_rows[] = ['value' => '(None assigned)', 'count' => $no_value_count];
            }
        } else {
            $sql_expr = $rpt['sql_expr'] ?? null;
            if ($sql_expr) {
                // Custom expression report (e.g., extract building/floor from a combined column)
                $sql = "
                    SELECT sub.value, COUNT(*) AS count
                    FROM (
                        SELECT ($sql_expr) AS value
                        FROM $table
                        $dept_where
                    ) AS sub
                    GROUP BY sub.value
                    ORDER BY count DESC
                ";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($dept_params);
                $report_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $grand_total = $total_records;
            } else {
                $sql = "
                    SELECT
                        CASE WHEN $column IS NULL OR TRIM(CAST($column AS TEXT)) = ''
                             THEN '(Not specified)'
                             ELSE TRIM(CAST($column AS TEXT))
                        END AS value,
                        COUNT(*) AS count
                    FROM $table
                    $dept_where
                    GROUP BY value
                    ORDER BY count DESC
                ";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($dept_params);
                $report_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $grand_total = $total_records;
            }
        }
    } catch (Exception $e) {
        $error_msg = $e->getMessage();
    }

    // Max count for bar widths
    $max_count = 0;
    foreach ($report_rows as $r) $max_count = max($max_count, (int)$r['count']);

    // --- CSV Export (grouped) ---
    if ($export_csv) {
        header('Content-Type: text/csv');
        $label = str_replace(' ', '_', strtolower($rpt_module . '_' . $rpt['label']));
        header('Content-Disposition: attachment; filename="report_' . $label . '_' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Value', 'Count', 'Percentage']);
        foreach ($report_rows as $row) {
            $pct = $grand_total > 0 ? round(($row['count'] / $grand_total) * 100, 1) : 0;
            fputcsv($out, [$row['value'], $row['count'], $pct . '%']);
        }
        fputcsv($out, ['TOTAL', $grand_total, '100%']);
        fclose($out);
        exit;
    }
}

// --- Color Helpers ---
$color_map = [
    'blue'   => ['bg' => 'bg-blue-600',   'light' => 'bg-blue-100 dark:bg-blue-900/30',   'text' => 'text-blue-600 dark:text-blue-400',   'bar' => 'bg-blue-500',   'badge' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300'],
    'emerald'=> ['bg' => 'bg-emerald-600', 'light' => 'bg-emerald-100 dark:bg-emerald-900/30', 'text' => 'text-emerald-600 dark:text-emerald-400', 'bar' => 'bg-emerald-500', 'badge' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300'],
    'violet' => ['bg' => 'bg-violet-600',  'light' => 'bg-violet-100 dark:bg-violet-900/30',  'text' => 'text-violet-600 dark:text-violet-400',  'bar' => 'bg-violet-500',  'badge' => 'bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-300'],
    'amber'  => ['bg' => 'bg-amber-600',   'light' => 'bg-amber-100 dark:bg-amber-900/30',   'text' => 'text-amber-600 dark:text-amber-400',   'bar' => 'bg-amber-500',   'badge' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300'],
    'rose'   => ['bg' => 'bg-rose-600',    'light' => 'bg-rose-100 dark:bg-rose-900/30',    'text' => 'text-rose-600 dark:text-rose-400',    'bar' => 'bg-rose-500',    'badge' => 'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300'],
    'cyan'   => ['bg' => 'bg-cyan-600',    'light' => 'bg-cyan-100 dark:bg-cyan-900/30',    'text' => 'text-cyan-600 dark:text-cyan-400',    'bar' => 'bg-cyan-500',    'badge' => 'bg-cyan-100 text-cyan-700 dark:bg-cyan-900/40 dark:text-cyan-300'],
];
$col = $color_map[$cfg['color']] ?? $color_map['blue'];

$page_title = "Reports";
require_once '../../includes/header.php';
// header.php sets its own $module (from URL path matching), which is 'reports'.
// Our data is safely stored in $rpt_module, $cfg, $rpt, etc.
require_once '../../includes/sidebar.php';
?>

<div class="p-4 md:p-8 flex-1 overflow-y-auto custom-scrollbar relative z-10 bg-slate-50 dark:bg-background-dark">
    <!-- Page Header -->
    <header class="-mt-4 -mx-4 md:-mt-8 md:-mx-8 px-4 md:px-8 pt-4 md:pt-8 pb-6 mb-8 flex flex-col md:flex-row items-start md:items-end justify-between gap-4 sticky top-0 bg-slate-50 dark:bg-background-dark z-20 border-b border-slate-200 dark:border-[#232b3d] no-print">
        <div>
            <h2 class="text-2xl font-bold text-slate-900 dark:text-white">Reports</h2>
            <p class="text-sm text-slate-500 mt-1">Generate grouped summaries and extract data from any module.</p>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            <?php if ($is_detail_list): ?>
            <a href="?module=<?= urlencode($rpt_module) ?>&report_type=<?= urlencode($report_type) ?>&department=<?= urlencode($dept_filter) ?>&date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>&encoder=<?= urlencode($encoder_filter) ?>&export=csv"
               class="flex items-center justify-center rounded-lg h-9 px-4 bg-blue-600 text-white hover:bg-blue-700 transition-colors text-xs font-bold">
                <span class="material-symbols-outlined text-[18px] mr-2">download</span> Export CSV
            </a>
            <?php else: ?>
            <a href="?module=<?= urlencode($rpt_module) ?>&report_type=<?= urlencode($report_type) ?>&department=<?= urlencode($dept_filter) ?>&export=csv"
               class="flex items-center justify-center rounded-lg h-9 px-4 bg-blue-600 text-white hover:bg-blue-700 transition-colors text-xs font-bold">
                <span class="material-symbols-outlined text-[18px] mr-2">download</span> Export CSV
            </a>
            <?php endif; ?>
            <button onclick="window.print()"
                    class="flex items-center justify-center rounded-lg h-9 px-4 bg-slate-600 text-white hover:bg-slate-700 transition-colors text-xs font-bold">
                <span class="material-symbols-outlined text-[18px] mr-2">print</span> Print
            </button>
        </div>
    </header>

    <!-- Print-only Title -->
    <div class="hidden print:block mb-4 text-center">
        <h2 class="text-lg font-bold"><?= htmlspecialchars($cfg['label']) ?> — <?= htmlspecialchars($rpt['label']) ?></h2>
        <p class="text-xs text-slate-500">Generated: <?= date('F j, Y g:i A') ?><?= $dept_filter !== 'All' ? ' | Filter: ' . htmlspecialchars($dept_filter) : '' ?><?= ($date_from || $date_to) ? ' | Date: ' . ($date_from ?: '—') . ' to ' . ($date_to ?: '—') : '' ?></p>
    </div>

    <!-- Module Selector Tabs -->
    <div class="no-print mb-6 flex gap-2 flex-wrap">
        <?php foreach ($report_configs as $mod_key => $mod_cfg):
            $mc = $color_map[$mod_cfg['color']] ?? $color_map['blue'];
            $is_active = $mod_key === $rpt_module;
        ?>
        <a href="?module=<?= $mod_key ?>&report_type=<?= array_key_first($mod_cfg['reports']) ?>&department=All"
           class="flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold transition-all
                  <?= $is_active
                        ? $mc['bg'] . ' text-white shadow-lg'
                        : 'bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] text-slate-600 dark:text-slate-400 hover:border-primary/30' ?>">
            <span class="material-symbols-outlined text-[16px]"><?= $mod_cfg['icon'] ?></span>
            <?= htmlspecialchars($mod_cfg['label']) ?>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Main Content: Report Type + Filters + Table -->
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
        <!-- Left: Report Type Picker -->
        <div class="lg:col-span-1 no-print">
            <div class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] rounded-2xl p-4">
                <div class="text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-3">Report Type</div>
                <div class="space-y-1">
                    <?php foreach ($cfg['reports'] as $rkey => $rval): ?>
                    <a href="?module=<?= $rpt_module ?>&report_type=<?= $rkey ?>&department=<?= urlencode($dept_filter) ?>"
                       class="flex items-center gap-2 px-3 py-2 rounded-xl text-xs font-medium transition-colors
                              <?= $rkey === $report_type
                                    ? $col['light'] . ' ' . $col['text'] . ' font-bold'
                                    : 'text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-[#232b3d]' ?>">
                        <span class="material-symbols-outlined text-[15px]">
                            <?php
                            $icons = ['location'=>'location_on','department'=>'business','os'=>'terminal','ms_office'=>'apps','processor'=>'memory',
                                      'memory'=>'developer_board','storage'=>'hard_drive','printer'=>'print','scanner'=>'scanner',
                                      'avr_ups'=>'power','system_unit'=>'computer','monitor'=>'monitor','checked_by'=>'badge',
                                      'status'=>'circle','device_type'=>'devices','brand'=>'label',
                                      'model'=>'category','building'=>'apartment','floor'=>'layers','building_floor'=>'domain',
                                      'building_floor_dept'=>'account_tree',
                                      'display_name'=>'contacts','person_responsible'=>'person','remarks'=>'comment',
                                      'date_encoded'=>'calendar_month','encoded_by'=>'edit_note'];

                            echo $icons[$rkey] ?? 'bar_chart';
                            ?>
                        </span>
                        <?= htmlspecialchars($rval['label']) ?>
                    </a>
                    <?php endforeach; ?>
                </div>

                <?php if ($is_detail_list): ?>
                <!-- Date Range Filter -->
                <div class="mt-4 pt-4 border-t border-slate-200 dark:border-[#232b3d]">
                    <div class="text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-2">Filter by Date</div>
                    <form method="GET" class="space-y-2">
                        <input type="hidden" name="module" value="<?= htmlspecialchars($rpt_module) ?>">
                        <input type="hidden" name="report_type" value="<?= htmlspecialchars($report_type) ?>">
                        <input type="hidden" name="department" value="<?= htmlspecialchars($dept_filter) ?>">
                        <input type="hidden" name="encoder" value="<?= htmlspecialchars($encoder_filter) ?>">
                        <div>
                            <label class="block text-[10px] text-slate-400 mb-1">From</label>
                            <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>"
                                   class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-700 dark:text-slate-300 text-xs rounded-xl px-3 py-2 focus:ring-1 focus:ring-primary">
                        </div>
                        <div>
                            <label class="block text-[10px] text-slate-400 mb-1">To</label>
                            <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>"
                                   class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-700 dark:text-slate-300 text-xs rounded-xl px-3 py-2 focus:ring-1 focus:ring-primary">
                        </div>
                        <div class="flex gap-2">
                            <button type="submit" class="flex-1 text-xs font-bold py-2 rounded-xl bg-blue-600 text-white hover:bg-blue-700 transition-colors">
                                Apply
                            </button>
                            <a href="?module=<?= $rpt_module ?>&report_type=<?= $report_type ?>&department=All&encoder=All"
                               class="flex-1 text-xs font-bold py-2 rounded-xl bg-slate-100 dark:bg-[#232b3d] text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-[#2a3347] transition-colors text-center">
                                Clear All
                            </a>
                        </div>
                    </form>
                </div>
                <!-- Encoded By Filter for detail list -->
                <?php if (!empty($encoder_values)): ?>
                <div class="mt-4 pt-4 border-t border-slate-200 dark:border-[#232b3d]">
                    <div class="text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-2">Filter by Encoded By</div>
                    <form method="GET">
                        <input type="hidden" name="module" value="<?= htmlspecialchars($rpt_module) ?>">
                        <input type="hidden" name="report_type" value="<?= htmlspecialchars($report_type) ?>">
                        <input type="hidden" name="date_from" value="<?= htmlspecialchars($date_from) ?>">
                        <input type="hidden" name="date_to" value="<?= htmlspecialchars($date_to) ?>">
                        <input type="hidden" name="department" value="<?= htmlspecialchars($dept_filter) ?>">
                        <select name="encoder" onchange="this.form.submit()"
                                class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-700 dark:text-slate-300 text-xs rounded-xl px-3 py-2 focus:ring-1 focus:ring-primary">
                            <option value="All">All Encoders</option>
                            <?php foreach ($encoder_values as $ev): ?>
                            <option value="<?= htmlspecialchars($ev) ?>" <?= $encoder_filter === $ev ? 'selected' : '' ?>>
                                <?= htmlspecialchars($ev) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>
                <?php endif; ?>
                <!-- Location Filter for detail list -->
                <?php if (!empty($dept_values)): ?>
                <div class="mt-4 pt-4 border-t border-slate-200 dark:border-[#232b3d]">
                    <div class="text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-2">Filter by Location</div>
                    <form method="GET">
                        <input type="hidden" name="module" value="<?= htmlspecialchars($rpt_module) ?>">
                        <input type="hidden" name="report_type" value="<?= htmlspecialchars($report_type) ?>">
                        <input type="hidden" name="date_from" value="<?= htmlspecialchars($date_from) ?>">
                        <input type="hidden" name="date_to" value="<?= htmlspecialchars($date_to) ?>">
                        <input type="hidden" name="encoder" value="<?= htmlspecialchars($encoder_filter) ?>">
                        <select name="department" onchange="this.form.submit()"
                                class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-700 dark:text-slate-300 text-xs rounded-xl px-3 py-2 focus:ring-1 focus:ring-primary">
                            <option value="All">All Locations</option>
                            <?php foreach ($dept_values as $dv): ?>
                            <option value="<?= htmlspecialchars($dv) ?>" <?= $dept_filter === $dv ? 'selected' : '' ?>>
                                <?= htmlspecialchars($dv) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>
                <?php endif; ?>

                <?php elseif (!empty($dept_values) && $column !== $dept_col): ?>
                <div class="mt-4 pt-4 border-t border-slate-200 dark:border-[#232b3d]">
                    <?php
                        // Determine the filter label based on what the dept_col represents
                        $filter_label = 'Filter';
                        if ($rpt_module === 'computers') {
                            $filter_label = 'Filter by Location';
                        } elseif ($dept_col === 'location') {
                            $filter_label = 'Filter by Location';
                        } elseif ($dept_col === 'department') {
                            $filter_label = 'Filter by Department';
                        }
                    ?>
                    <div class="text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-2"><?= $filter_label ?></div>
                    <form method="GET">
                        <input type="hidden" name="module" value="<?= htmlspecialchars($rpt_module) ?>">
                        <input type="hidden" name="report_type" value="<?= htmlspecialchars($report_type) ?>">
                        <select name="department" onchange="this.form.submit()"
                                class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-700 dark:text-slate-300 text-xs rounded-xl px-3 py-2 focus:ring-1 focus:ring-primary">
                            <option value="All">All</option>
                            <?php foreach ($dept_values as $dv): ?>
                            <option value="<?= htmlspecialchars($dv) ?>" <?= $dept_filter === $dv ? 'selected' : '' ?>>
                                <?= htmlspecialchars($dv) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>
                <?php endif; ?>

            </div>

            <!-- Summary Stats -->
            <div class="mt-4 grid grid-cols-2 gap-3">
                <div class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] rounded-xl p-3 text-center">
                    <div class="text-[10px] text-slate-500 uppercase font-bold mb-1"><?= $is_detail_list ? 'Total Records' : 'Total Records' ?></div>
                    <div class="text-xl font-bold <?= $col['text'] ?>"><?= number_format($is_detail_list ? $grand_total : $total_records) ?></div>
                </div>
                <div class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] rounded-xl p-3 text-center">
                    <div class="text-[10px] text-slate-500 uppercase font-bold mb-1"><?= $is_detail_list ? 'Date Groups' : 'Unique Values' ?></div>
                    <div class="text-xl font-bold text-slate-700 dark:text-slate-200"><?= number_format($is_detail_list ? count($detail_grouped) : count($report_rows)) ?></div>
                </div>
            </div>
        </div>

        <!-- Right: Report Table -->
        <div class="lg:col-span-3 print:col-span-4">
            <div class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] rounded-2xl overflow-hidden shadow-sm">
                <!-- Report Header -->
                <div class="px-5 py-4 border-b border-slate-200 dark:border-[#232b3d] flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="size-9 rounded-xl <?= $col['light'] ?> flex items-center justify-center <?= $col['text'] ?>">
                            <span class="material-symbols-outlined text-[20px]"><?= $cfg['icon'] ?></span>
                        </div>
                        <div>
                            <div class="text-sm font-bold text-slate-900 dark:text-white"><?= htmlspecialchars($cfg['label']) ?></div>
                            <div class="text-xs text-slate-500"><?= htmlspecialchars($rpt['label']) ?><?= $dept_filter !== 'All' ? ' — ' . htmlspecialchars($dept_filter) : '' ?><?= ($date_from || $date_to) ? ' · ' . ($date_from ?: '…') . ' → ' . ($date_to ?: 'now') : '' ?></div>
                        </div>
                    </div>
                    <span class="<?= $col['badge'] ?> text-xs font-bold px-3 py-1 rounded-full">
                        <?= number_format($grand_total) ?> <?= $is_detail_list ? 'records' : ($is_multi ? 'tags' : 'records') ?>
                    </span>
                </div>

                <?php if ($error_msg): ?>
                <div class="p-8 text-center text-red-500 text-sm">
                    <span class="material-symbols-outlined text-3xl mb-2 block">error</span>
                    Query error: <?= htmlspecialchars($error_msg) ?>
                </div>

                <?php elseif ($is_detail_list): ?>
                    <?php if (empty($detail_rows)): ?>
                    <div class="p-12 text-center text-slate-500">
                        <span class="material-symbols-outlined text-4xl mb-3 block opacity-40">calendar_month</span>
                        <p class="text-sm font-medium">No records found for this date range.</p>
                        <p class="text-xs mt-1">Adjust the date filter or clear it to see all records.</p>
                    </div>
                    <?php else: ?>
                    <!-- Active date filter badge -->
                    <?php if ($date_from || $date_to): ?>
                    <div class="px-5 py-2 bg-blue-50 dark:bg-blue-900/20 border-b border-blue-100 dark:border-blue-900/40 flex items-center gap-2 no-print">
                        <span class="material-symbols-outlined text-[14px] text-blue-500">filter_alt</span>
                        <span class="text-xs text-blue-700 dark:text-blue-300 font-medium">
                            Showing records from
                            <?= $date_from ? '<strong>' . htmlspecialchars(date('M j, Y', strtotime($date_from))) . '</strong>' : '(all)' ?>
                            to
                            <?= $date_to ? '<strong>' . htmlspecialchars(date('M j, Y', strtotime($date_to))) . '</strong>' : '<strong>today</strong>' ?>
                        </span>
                    </div>
                    <?php endif; ?>
                    <div class="overflow-x-auto">
                        <?php
                        $row_num = 0;
                        foreach ($detail_grouped as $day => $day_rows):
                            $display_date = ($day !== '(No Date)') ? date('F j, Y', strtotime($day)) : '(No Date)';
                        ?>
                        <!-- Date group header -->
                        <div class="px-5 py-2 bg-slate-50 dark:bg-[#141b28] border-b border-t border-slate-200 dark:border-[#232b3d] flex items-center justify-between sticky top-0 z-10">
                            <div class="flex items-center gap-2">
                                <span class="material-symbols-outlined text-[15px] <?= $col['text'] ?>">calendar_today</span>
                                <span class="text-xs font-bold text-slate-700 dark:text-slate-200"><?= htmlspecialchars($display_date) ?></span>
                                <span class="text-[10px] text-slate-400 font-mono"><?= $day !== '(No Date)' ? htmlspecialchars($day) : '' ?></span>
                            </div>
                            <span class="<?= $col['badge'] ?> text-[10px] font-bold px-2 py-0.5 rounded-full">
                                <?= count($day_rows) ?> record<?= count($day_rows) !== 1 ? 's' : '' ?>
                            </span>
                        </div>
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b border-slate-100 dark:border-[#232b3d]/60">
                                    <th class="px-5 py-2 text-[10px] font-bold text-slate-400 uppercase tracking-wider w-10">#</th>
                                    <th class="px-5 py-2 text-[10px] font-bold text-slate-400 uppercase tracking-wider">Control Number</th>
                                    <th class="px-5 py-2 text-[10px] font-bold text-slate-400 uppercase tracking-wider">End User</th>
                                    <th class="px-5 py-2 text-[10px] font-bold text-slate-400 uppercase tracking-wider">Location</th>
                                    <th class="px-5 py-2 text-[10px] font-bold text-slate-400 uppercase tracking-wider">Encoded By</th>
                                    <th class="px-5 py-2 text-[10px] font-bold text-slate-400 uppercase tracking-wider">Last Edited By</th>
                                    <th class="px-5 py-2 text-[10px] font-bold text-slate-400 uppercase tracking-wider no-print">Time</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-[#232b3d]/60">
                                <?php foreach ($day_rows as $row):
                                    $row_num++;
                                    $time_str       = $row['encoded_at']  ? date('h:i A', strtotime($row['encoded_at']))  : '—';
                                    $ctrl           = trim($row['control_number']  ?? '');
                                    $user           = trim($row['end_user']        ?? '');
                                    $loc            = trim($row['department']      ?? '');
                                    $enc_by         = trim($row['encoded_by']      ?? '');
                                    $last_edit_by   = trim($row['last_edited_by']  ?? '');
                                    $last_edit_at   = $row['updated_at'] ? date('M j, Y g:i A', strtotime($row['updated_at'])) : '';
                                    // Only show last_edit if actually different from created_at (i.e. record was edited)
                                    $was_edited = $row['updated_at'] && $row['encoded_at']
                                        && abs(strtotime($row['updated_at']) - strtotime($row['encoded_at'])) > 5;
                                ?>
                                <tr class="hover:bg-slate-50 dark:hover:bg-[#1e2738] transition-colors">
                                    <td class="px-5 py-3 text-xs text-slate-400 font-mono"><?= $row_num ?></td>
                                    <td class="px-5 py-3">
                                        <?php if ($ctrl): ?>
                                        <span class="<?= $col['badge'] ?> text-xs font-bold px-2 py-0.5 rounded font-mono"><?= htmlspecialchars($ctrl) ?></span>
                                        <?php else: ?>
                                        <span class="text-xs text-slate-400 italic">(No control #)</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-5 py-3 text-sm text-slate-700 dark:text-slate-200">
                                        <?= $user ? htmlspecialchars($user) : '<span class="text-slate-400 italic text-xs">(No user)</span>' ?>
                                    </td>
                                    <td class="px-5 py-3">
                                        <?php if ($loc): ?>
                                        <span class="flex items-center gap-1 text-xs text-slate-600 dark:text-slate-300">
                                            <span class="material-symbols-outlined text-[13px] text-slate-400">location_on</span>
                                            <?= htmlspecialchars($loc) ?>
                                        </span>
                                        <?php else: ?>
                                        <span class="text-xs text-slate-400 italic">(No location)</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-5 py-3">
                                        <?php if ($enc_by): ?>
                                        <span class="flex items-center gap-1 text-xs text-slate-700 dark:text-slate-200">
                                            <span class="material-symbols-outlined text-[13px] text-slate-400">edit_note</span>
                                            <?= htmlspecialchars($enc_by) ?>
                                        </span>
                                        <?php else: ?>
                                        <span class="text-xs text-slate-400 italic">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-5 py-3">
                                        <?php if ($was_edited && $last_edit_by): ?>
                                        <span class="flex flex-col gap-0.5">
                                            <span class="flex items-center gap-1 text-xs text-amber-600 dark:text-amber-400 font-medium">
                                                <span class="material-symbols-outlined text-[13px]">manage_accounts</span>
                                                <?= htmlspecialchars($last_edit_by) ?>
                                            </span>
                                            <span class="text-[10px] text-slate-400 font-mono pl-4"><?= htmlspecialchars($last_edit_at) ?></span>
                                        </span>
                                        <?php elseif ($was_edited): ?>
                                        <span class="text-xs text-slate-400 italic">Edited <?= htmlspecialchars($last_edit_at) ?></span>
                                        <?php else: ?>
                                        <span class="text-xs text-slate-300 dark:text-slate-600">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-5 py-3 text-xs text-slate-400 font-mono no-print"><?= htmlspecialchars($time_str) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php endforeach; ?>
                    </div>
                    <!-- Grand total footer -->
                    <div class="px-5 py-3 border-t-2 border-slate-200 dark:border-[#232b3d] bg-slate-50 dark:bg-[#141b28] flex items-center justify-between">
                        <span class="text-xs font-bold text-slate-600 dark:text-slate-300 uppercase">Grand Total</span>
                        <span class="text-sm font-bold <?= $col['text'] ?>"><?= number_format($grand_total) ?> records across <?= count($detail_grouped) ?> date<?= count($detail_grouped) !== 1 ? 's' : '' ?></span>
                    </div>
                    <?php endif; ?>

                <?php elseif (empty($report_rows)): ?>
                <div class="p-12 text-center text-slate-500">
                    <span class="material-symbols-outlined text-4xl mb-3 block opacity-40">bar_chart</span>
                    <p class="text-sm font-medium">No data found for this report.</p>
                    <p class="text-xs mt-1">Try selecting a different module or report type.</p>
                </div>
                <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-slate-200 dark:border-[#232b3d] bg-slate-50 dark:bg-[#141b28]">
                                <th class="px-5 py-3 text-[10px] font-bold text-slate-500 uppercase tracking-wider">#</th>
                                <th class="px-5 py-3 text-[10px] font-bold text-slate-500 uppercase tracking-wider"><?= htmlspecialchars($rpt['label']) ?></th>
                                <th class="px-5 py-3 text-[10px] font-bold text-slate-500 uppercase tracking-wider text-right w-24">Count</th>
                                <th class="px-5 py-3 text-[10px] font-bold text-slate-500 uppercase tracking-wider text-right w-20">%</th>
                                <th class="px-5 py-3 text-[10px] font-bold text-slate-500 uppercase tracking-wider w-48 no-print">Distribution</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-[#232b3d]/60">
                            <?php foreach ($report_rows as $idx => $row):
                                $count  = (int)$row['count'];
                                $pct    = $grand_total > 0 ? round(($count / $grand_total) * 100, 1) : 0;
                                $bar_w  = $max_count > 0 ? round(($count / $max_count) * 100) : 0;
                                $is_none = in_array($row['value'], ['(None assigned)', '(Not specified)', '']);
                            ?>
                            <tr class="hover:bg-slate-50 dark:hover:bg-[#1e2738] transition-colors">
                                <td class="px-5 py-3 text-xs text-slate-400 font-mono"><?= $idx + 1 ?></td>
                                <td class="px-5 py-3">
                                    <span class="text-sm font-medium <?= $is_none ? 'text-slate-400 italic' : 'text-slate-900 dark:text-white' ?>">
                                        <?= htmlspecialchars($row['value'] ?: '(Not specified)') ?>
                                    </span>
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <span class="text-sm font-bold <?= $col['text'] ?>"><?= number_format($count) ?></span>
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <span class="text-xs font-semibold text-slate-500"><?= $pct ?>%</span>
                                </td>
                                <td class="px-5 py-3 no-print">
                                    <div class="flex items-center gap-2">
                                        <div class="flex-1 h-2 bg-slate-100 dark:bg-[#232b3d] rounded-full overflow-hidden">
                                            <div class="h-full <?= $is_none ? 'bg-slate-300 dark:bg-slate-600' : $col['bar'] ?> rounded-full transition-all duration-500"
                                                 style="width: <?= $bar_w ?>%"></div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="border-t-2 border-slate-200 dark:border-[#232b3d] bg-slate-50 dark:bg-[#141b28]">
                                <td colspan="2" class="px-5 py-3 text-xs font-bold text-slate-700 dark:text-slate-300 uppercase">Total</td>
                                <td class="px-5 py-3 text-right text-sm font-bold <?= $col['text'] ?>"><?= number_format($grand_total) ?></td>
                                <td class="px-5 py-3 text-right text-xs font-bold text-slate-600 dark:text-slate-300">100%</td>
                                <td class="px-5 py-3 no-print"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
@media print {
    .no-print { display: none !important; }
    aside, #mobile-topbar, #mobile-sidebar-overlay { display: none !important; }

    #main-layout-wrapper { display: block !important; }

    .print\:col-span-4 {
        grid-column: span 4 / span 4 !important;
        width: 100% !important;
    }

    .overflow-x-auto, .overflow-hidden { overflow: visible !important; }
    .rounded-2xl, .rounded-xl { border-radius: 0 !important; }
    .shadow-sm { box-shadow: none !important; }

    body { background: white !important; }
    .bg-white, .dark\:bg-\[\#1a2130\] { background: white !important; }
    .bg-slate-50, .dark\:bg-\[\#141b28\] { background: #f8fafc !important; }

    table { width: 100% !important; border-collapse: collapse !important; }
    th, td {
        border: 1px solid #cbd5e1 !important;
        color: #1e293b !important;
        padding: 6px 10px !important;
        font-size: 11px !important;
    }
    thead tr { background-color: #f1f5f9 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    tfoot tr { background-color: #f1f5f9 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
}
</style>

<?php require_once '../../includes/footer.php'; ?>
