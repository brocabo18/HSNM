<?php
/**
 * Print Computer Preventive Maintenance Checklist
 * Matches official template layout with multiple selection support.
 */
require_once __DIR__ . '/../../config.php';
requireLogin();

$passed_recom = trim($_REQUEST['recom'] ?? '');

$ids = [];
if (!empty($_GET['ids'])) {
    $ids = array_filter(array_map('intval', explode(',', $_GET['ids'])));
} elseif (!empty($_GET['id'])) {
    $ids = [(int)$_GET['id']];
}

if (empty($ids)) {
    die("No maintenance records selected to print. Please select at least one record.");
}

$in_clause = implode(',', array_fill(0, count($ids), '?'));
$stmt = $pdo->prepare("
    SELECT m.*, a.gpu AS asset_gpu, a.brand_model AS asset_name, a.department AS asset_dept
    FROM maintenance m
    LEFT JOIN assets a ON a.id = m.asset_id
    WHERE m.id IN ($in_clause)
    ORDER BY m.id ASC
");
$stmt->execute(array_values($ids));
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($records)) {
    die("No matching maintenance records found.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Preventive Maintenance Checklist (<?= count($records) ?> records)</title>
    <style>
        * {
            box-sizing: border-box;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        body {
            font-family: Arial, Helvetica, sans-serif;
            margin: 0;
            padding: 0;
            background: #e2e8f0;
            color: #000;
            font-size: 10px;
        }
        .no-print {
            background: #1e293b;
            color: #fff;
            padding: 12px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }
        .btn {
            background: #2563eb;
            color: #fff;
            border: none;
            padding: 8px 18px;
            font-size: 13px;
            font-weight: bold;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn:hover { background: #1d4ed8; }
        .btn-secondary { background: #64748b; }
        .btn-secondary:hover { background: #475569; }

        .sheet {
            background: #fff;
            width: 210mm;
            min-height: 280mm;
            margin: 20px auto;
            padding: 12mm 12mm;
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
            page-break-after: always;
            break-after: page;
        }
        .sheet:last-child {
            page-break-after: auto;
            break-after: auto;
        }

        .form-title {
            text-align: center;
            font-size: 14px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 0 0 10px 0;
            color: #000;
        }

        table.tbl-bordered {
            width: 100%;
            border-collapse: collapse;
            border: 1.5px solid #000;
            font-size: 10px;
            line-height: 1.3;
        }
        table.tbl-bordered td, table.tbl-bordered th {
            border: 1px solid #000;
            padding: 3.5px 6px;
            vertical-align: middle;
        }
        table.tbl-bordered th {
            background: #fff;
            font-weight: bold;
        }

        .section-header {
            font-weight: bold;
            text-transform: uppercase;
            font-size: 10.5px;
            padding: 4px 6px;
            background: #fff;
        }

        .sub-table-header {
            font-style: italic;
            font-weight: bold;
            font-size: 9.5px;
            text-align: left;
        }

        .narrative-box {
            min-height: 48px;
            padding: 4px 6px;
            line-height: 1.5;
            font-size: 9.5px;
            background: repeating-linear-gradient(
                transparent,
                transparent 17px,
                #000 17px,
                #000 18px
            );
        }
        .narrative-box-lg {
            min-height: 58px;
            padding: 4px 6px;
            line-height: 1.5;
            font-size: 9.5px;
            background: repeating-linear-gradient(
                transparent,
                transparent 17px,
                #000 17px,
                #000 18px
            );
        }
        .editable-recom {
            min-height: 58px;
            padding: 4px 6px;
            line-height: 1.5;
            font-size: 9.5px;
            background: repeating-linear-gradient(
                transparent,
                transparent 17px,
                #000 17px,
                #000 18px
            );
            cursor: text;
            outline: none;
            transition: background-color 0.2s, box-shadow 0.2s;
        }
        .editable-recom:hover {
            box-shadow: 0 0 0 1px #3b82f6;
        }
        .editable-recom:focus {
            box-shadow: 0 0 0 1.5px #2563eb;
            background-color: #fffbeb;
        }

        .sign-line {
            width: 80%;
            margin: 22px auto 3px auto;
            border-bottom: 1px solid #000;
            text-align: center;
            font-weight: bold;
            font-size: 10px;
            min-height: 14px;
        }
        .sign-title {
            text-align: center;
            font-size: 9.5px;
            font-weight: normal;
        }

        @media print {
            .no-print { display: none !important; }
            body { background: #fff !important; }
            .sheet {
                margin: 0 !important;
                padding: 10mm 10mm !important;
                width: 100% !important;
                min-height: auto !important;
                box-shadow: none !important;
                page-break-after: always !important;
                break-after: page !important;
            }
            .sheet:last-child {
                page-break-after: auto !important;
                break-after: auto !important;
            }
            @page {
                size: A4 portrait;
                margin: 6mm;
            }
        }
    </style>
</head>
<body>

<div class="no-print">
    <div style="display: flex; align-items: center; gap: 16px; flex-wrap: wrap;">
        <div>
            <strong>Computer Preventive Maintenance Checklist</strong> &mdash; 
            <span>Showing <?= count($records) ?> record<?= count($records) > 1 ? 's' : '' ?></span>
        </div>
        <div style="display: flex; align-items: center; gap: 6px;">
            <label for="globalRecomInput" style="font-size: 11px; color: #cbd5e1; font-weight: bold;">Quick Recommendation:</label>
            <input type="text" id="globalRecomInput" value="<?= htmlspecialchars($passed_recom) ?>" placeholder="Type recommendation to apply to all sheets..." style="padding: 5px 8px; font-size: 11px; border-radius: 5px; border: 1px solid #475569; background: #0f172a; color: #fff; width: 280px;">
            <button type="button" onclick="applyGlobalRecommendation()" class="btn btn-secondary" style="padding: 5px 10px; font-size: 11px;">Apply to All</button>
        </div>
    </div>
    <div style="display: flex; gap: 10px;">
        <button onclick="window.print()" class="btn">
            &#128438; Print Checklist<?= count($records) > 1 ? 's (' . count($records) . ' Pages)' : '' ?>
        </button>
        <button onclick="window.close()" class="btn btn-secondary">Close</button>
    </div>
</div>

<?php foreach ($records as $idx => $r): ?>
<?php
// Hardware items mapping
$hw_list = [
    ['Monitor',                  $r['hw_monitor'] ?? '',          $r['hw_monitor_remarks'] ?? ''],
    ['MotherBoard',              $r['hw_motherboard'] ?? '',      $r['hw_motherboard_remarks'] ?? ''],
    ['Power Supply',             $r['hw_power_supply'] ?? '',     $r['hw_power_supply_remarks'] ?? ''],
    ['Video Card',               $r['hw_video_card'] ?? '',       $r['hw_video_card_remarks'] ?? ''],
    ['Hard Disk/Back Up Drive',  $r['hw_hard_disk'] ?? '',        $r['hw_hard_disk_remarks'] ?? ''],
    ['CD-ROM drive',             $r['hw_cdrom'] ?? '',            $r['hw_cdrom_remarks'] ?? ''],
    ['LAN Card/Wifi Adapter',    $r['hw_lan_card'] ?? '',         $r['hw_lan_card_remarks'] ?? ''],
    ['Mouse/Keyboard',           $r['hw_keyboard_mouse'] ?? '',   $r['hw_keyboard_remarks'] ?? ''],
    ['Printer/Scanner',          $r['hw_printer_scanner'] ?? '',  $r['hw_printer_remarks'] ?? ''],
    ['UPS and AVR',              $r['hw_ups_avr'] ?? '',          $r['hw_ups_avr_remarks'] ?? ''],
    ['Modem',                    $r['hw_modem'] ?? '',            $r['hw_modem_remarks'] ?? ''],
    ['Others',                   '',                              ''],
];

// Software items mapping
$sw_list = [
    ['Defragmentation',          $r['sw_defrag'] ?? '',           $r['sw_defrag_remarks'] ?? ''],
    ['Registry Cleaning',        $r['sw_registry'] ?? '',         $r['sw_registry_remarks'] ?? ''],
    ['Anti Virus if Updated',    $r['sw_antivirus'] ?? '',        $r['sw_antivirus_remarks'] ?? ''],
    ['Windows if Updated',       $r['sw_os_patch'] ?? '',         $r['sw_os_patch_remarks'] ?? ''],
    ['Empty Recycle Bin',        $r['sw_recycle_bin'] ?? '',      $r['sw_recycle_remarks'] ?? ''],
    ['Delete Temporary Files',   $r['sw_temp_files'] ?? '',       $r['sw_temp_remarks'] ?? ''],
    ['Check Installed Software', $r['sw_installed'] ?? '',        $r['sw_installed_remarks'] ?? ''],
    ['Check OS',                 $r['sw_os_integrity'] ?? '',      $r['sw_integrity_remarks'] ?? ''],
    ['Check Office Application', $r['sw_office'] ?? '',           $r['sw_office_remarks'] ?? ''],
    ['',                         '',                              ''],
    ['',                         '',                              ''],
    ['Others',                   $r['sw_browser_cache'] ?? '',    $r['sw_browser_remarks'] ?? ''],
];

$gpu_val = !empty($r['asset_gpu']) ? $r['asset_gpu'] : (!empty($r['hw_video_card']) && $r['hw_video_card'] !== 'Pass' ? $r['hw_video_card'] : 'Integrated / Standard');
?>
<div class="sheet">
    <div class="form-title">COMPUTER PREVENTIVE MAINTENANCE CHECKLIST</div>

    <!-- Section 1: Header Info -->
    <table class="tbl-bordered">
        <tr>
            <td style="width: 33%;"><span style="font-weight: bold; font-style: italic;">Department</span><br><?= htmlspecialchars($r['department'] ?? '') ?></td>
            <td style="width: 38%;"><span style="font-weight: bold; font-style: italic;">Section</span><br><?= htmlspecialchars($r['department'] ?? '') ?></td>
            <td style="width: 29%;"><span style="font-weight: bold; font-style: italic;">PM Schedule:</span><br><?= htmlspecialchars($r['maintenance_date'] ?? date('Y-m-d')) ?></td>
        </tr>
        <tr>
            <td colspan="2" style="padding: 0; vertical-align: top;">
                <table style="width: 100%; border-collapse: collapse; border: none; height: 100%;">
                    <tr>
                        <td style="border: none; border-bottom: 1px solid #000; padding: 6px 8px;">
                            <strong>Employee Name :</strong> <?= htmlspecialchars($r['employee_name'] ?? '') ?>
                        </td>
                    </tr>
                    <tr>
                        <td style="border: none; padding: 6px 8px;">
                            <strong>IHOMS Control Number:</strong> <span style="font-family: monospace; font-weight: bold;"><?= htmlspecialchars($r['control_number'] ?? '') ?></span>
                        </td>
                    </tr>
                </table>
            </td>
            <td style="padding: 6px 8px; vertical-align: top;">
                <strong>Computer Description:</strong><br>
                <div style="margin-top: 3px; line-height: 1.5;">
                    <div>Processor: <u><?= htmlspecialchars($r['processor'] ?? '') ?></u></div>
                    <div>Memory: <u><?= htmlspecialchars($r['memory'] ?? '') ?></u></div>
                    <div>GPU: <u><?= htmlspecialchars($gpu_val) ?></u></div>
                </div>
            </td>
        </tr>
    </table>

    <!-- Section 2: Dual Checklist Table -->
    <table class="tbl-bordered" style="margin-top: 5px;">
        <thead>
            <!-- Category Banner Row -->
            <tr>
                <th colspan="3" class="section-header" style="text-align: left; width: 50%;">HARDWARE PREVENTIVE MAINTENANCE</th>
                <th colspan="3" class="section-header" style="text-align: left; width: 50%;">SOFTWARE PREVENTIVE MAINTENACE</th>
            </tr>
            <!-- Column Subheaders -->
            <tr>
                <th class="sub-table-header" style="width: 25%;">Hardware Component</th>
                <th class="sub-table-header" style="width: 11%; text-align: center;">Status</th>
                <th class="sub-table-header" style="width: 14%;">Remarks</th>
                <th class="sub-table-header" style="width: 25%;">Check Up Routine</th>
                <th class="sub-table-header" style="width: 11%; text-align: center;">Status</th>
                <th class="sub-table-header" style="width: 14%;">Rermarks</th>
            </tr>
        </thead>
        <tbody>
            <?php for ($i = 0; $i < 12; $i++): ?>
            <?php 
                $hw = $hw_list[$i];
                $sw = $sw_list[$i];
            ?>
            <tr>
                <td><?= htmlspecialchars($hw[0]) ?></td>
                <td style="text-align: center; font-weight: <?= ($hw[1]==='Fail')?'bold':'normal' ?>; color: <?= ($hw[1]==='Fail')?'#b91c1c':'inherit' ?>;"><?= htmlspecialchars($hw[1]) ?></td>
                <td><?= htmlspecialchars($hw[2]) ?></td>
                <td><?= htmlspecialchars($sw[0]) ?></td>
                <td style="text-align: center; font-weight: <?= ($sw[1]==='Fail')?'bold':'normal' ?>; color: <?= ($sw[1]==='Fail')?'#b91c1c':'inherit' ?>;"><?= htmlspecialchars($sw[1]) ?></td>
                <td><?= htmlspecialchars($sw[2]) ?></td>
            </tr>
            <?php endfor; ?>
        </tbody>
    </table>

    <!-- Section 3: Observation & Action -->
    <table class="tbl-bordered" style="margin-top: 5px;">
        <tr>
            <td style="width: 50%; vertical-align: top; padding: 4px 6px;">
                <span style="font-weight: bold; font-style: italic;">Observation computer Location or Environment:</span>
                <div class="narrative-box"><?= nl2br(htmlspecialchars($r['env_observations'] ?? '')) ?></div>
            </td>
            <td style="width: 50%; vertical-align: top; padding: 4px 6px;">
                <span style="font-weight: bold; font-style: italic;">Action:</span>
                <div class="narrative-box"><?= nl2br(htmlspecialchars($r['action_taken'] ?? '')) ?></div>
            </td>
        </tr>
    </table>

    <!-- Section 4: Recommendation -->
    <table class="tbl-bordered" style="margin-top: 5px;">
        <tr>
            <td style="padding: 4px 6px; vertical-align: top;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <span style="font-weight: bold; font-style: italic;">Recommendation:</span>
                    <span class="no-print" style="font-size: 8.5px; color: #64748b; font-style: italic;">(Editable: click inside box to type)</span>
                </div>
                <?php
                    $rec_val = $passed_recom !== '' ? $passed_recom : ($r['recommendation'] ?? '');
                ?>
                <div class="editable-recom" contenteditable="true" spellcheck="false"><?= nl2br(htmlspecialchars($rec_val)) ?></div>
            </td>
        </tr>
    </table>

    <!-- Section 5: Signatures -->
    <table class="tbl-bordered" style="margin-top: 5px;">
        <tr>
            <td style="width: 36%; vertical-align: top; padding: 4px 8px;">
                <span style="font-weight: bold;">Checked by:</span>
                <div class="sign-line"><?= htmlspecialchars($r['checked_by'] ?? '') ?></div>
                <div class="sign-title">IHOMS Staff</div>
            </td>
            <td style="width: 36%; vertical-align: top; padding: 4px 8px;">
                <span style="font-weight: bold;">Verified by:</span>
                <div class="sign-line">&nbsp;</div>
                <div class="sign-title">IHOMS Head</div>
            </td>
            <td style="width: 28%; vertical-align: top; padding: 4px 8px;">
                <span style="font-weight: bold;">Date:</span>
                <div class="sign-line"><?= htmlspecialchars($r['audit_date'] ?: ($r['maintenance_date'] ?? date('Y-m-d'))) ?></div>
                <div class="sign-title">&nbsp;</div>
            </td>
        </tr>
    </table>
</div>
<?php endforeach; ?>

<script>
function applyGlobalRecommendation() {
    const val = document.getElementById('globalRecomInput').value;
    document.querySelectorAll('.editable-recom').forEach(el => {
        el.innerText = val;
    });
}
</script>

</body>
</html>
