<?php
/**
 * Print Computer Unit Inventory Form
 * Matches official template layout with multiple selection support.
 */
require_once __DIR__ . '/../../config.php';
requireLogin();

$ids = [];
if (!empty($_GET['ids'])) {
    $ids = array_filter(array_map('intval', explode(',', $_GET['ids'])));
} elseif (!empty($_GET['id'])) {
    $ids = [(int)$_GET['id']];
}

if (empty($ids)) {
    die("No asset records selected to print. Please select at least one asset.");
}

$in_clause = implode(',', array_fill(0, count($ids), '?'));
$stmt = $pdo->prepare("SELECT * FROM assets WHERE id IN ($in_clause) ORDER BY id ASC");
$stmt->execute(array_values($ids));
$assets = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($assets)) {
    die("No matching asset records found.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Computer Unit Inventory Form (<?= count($assets) ?> records)</title>
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
            font-size: 11px;
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
            min-height: 275mm;
            margin: 20px auto;
            padding: 18mm 15mm;
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
            font-size: 16px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 0 0 16px 0;
            color: #000;
        }

        table.form-grid {
            width: 100%;
            border-collapse: collapse;
            border: 1.5px solid #000;
            font-size: 11px;
            line-height: 1.35;
        }
        table.form-grid td, table.form-grid th {
            border: 1px solid #000;
            padding: 5px 8px;
            vertical-align: middle;
        }
        table.form-grid th {
            background: #fff;
            font-weight: bold;
            text-align: center;
        }

        .label {
            font-weight: bold;
            color: #000;
        }
        .val {
            font-weight: normal;
            color: #000;
        }

        .chk-box {
            display: inline-block;
            width: 13px;
            height: 13px;
            border: 1.5px solid #000;
            text-align: center;
            line-height: 11px;
            font-size: 11px;
            font-weight: bold;
            vertical-align: middle;
            margin-right: 4px;
            margin-left: 2px;
        }

        .mt-grid {
            margin-top: 10px;
        }

        @media print {
            .no-print { display: none !important; }
            body { background: #fff !important; }
            .sheet {
                margin: 0 !important;
                padding: 14mm 12mm !important;
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
                margin: 8mm;
            }
        }
    </style>
</head>
<body>

<div class="no-print">
    <div>
        <strong>Computer Unit Inventory Form</strong> &mdash; 
        <span>Showing <?= count($assets) ?> asset<?= count($assets) > 1 ? 's' : '' ?></span>
    </div>
    <div style="display: flex; gap: 10px;">
        <button onclick="window.print()" class="btn">
            &#128438; Print Form<?= count($assets) > 1 ? 's (' . count($assets) . ' Pages)' : '' ?>
        </button>
        <button onclick="window.close()" class="btn btn-secondary">Close</button>
    </div>
</div>

<?php foreach ($assets as $idx => $a): ?>
<?php
$source = strtolower(trim((string)($a['source'] ?? '')));
$is_purchased = ($source === 'purchased' || $source === '');
$is_donation  = ($source === 'donation');
$is_personal  = ($source === 'personal');

// Parse keyboard / mouse
$km = trim((string)($a['keyboard_mouse'] ?? ''));
$keyboard = $mouse = '';
if (!empty($km)) {
    if (str_contains($km, ' / ')) {
        $parts = explode(' / ', $km, 2);
        $keyboard = trim($parts[0] ?? '');
        $mouse    = trim($parts[1] ?? '');
    } else {
        $keyboard = $km;
        $mouse    = $km;
    }
}

$os_lic = !empty($a['os_licensed']);
$ms_lic = !empty($a['ms_office_licensed']);
$cost_val = (!empty($a['cost']) && (float)$a['cost'] > 0) ? '₱ ' . number_format((float)$a['cost'], 2) : '';
?>
<div class="sheet">
    <div class="form-title">COMPUTER UNIT INVENTORY FORM</div>

    <!-- Section 1: General Info -->
    <table class="form-grid">
        <tr>
            <td style="width: 50%;"><span class="label">Department / Section:</span> <span class="val"><?= htmlspecialchars($a['department'] ?? '') ?></span></td>
            <td style="width: 50%;"><span class="label">End User:</span> <span class="val"><?= htmlspecialchars($a['end_user'] ?? '') ?></span></td>
        </tr>
        <tr>
            <td>
                <span class="label">Source:</span> &nbsp;
                <span class="chk-box"><?= $is_purchased ? '&#10003;' : '' ?></span> Purchased &nbsp;&nbsp;&nbsp;
                <span class="chk-box"><?= $is_donation  ? '&#10003;' : '' ?></span> Donation &nbsp;&nbsp;&nbsp;
                <span class="chk-box"><?= $is_personal  ? '&#10003;' : '' ?></span> Personal
            </td>
            <td><span class="label">MR/ICS:</span> <span class="val"><?= htmlspecialchars($a['mr_ics'] ?? '') ?></span></td>
        </tr>
        <tr>
            <td style="padding: 0; border: none;" colspan="2">
                <table style="width: 100%; border-collapse: collapse; border: none;">
                    <tr>
                        <td style="width: 36%; border: none; border-right: 1px solid #000; padding: 5px 8px;">
                            <span class="label">Unit Control ID:</span> <span class="val" style="font-family: monospace; font-weight: bold;"><?= htmlspecialchars($a['control_id'] ?? '') ?></span>
                        </td>
                        <td style="width: 34%; border: none; border-right: 1px solid #000; padding: 5px 8px;">
                            <span class="label">Item Code:</span> <span class="val"><?= htmlspecialchars($a['item_code'] ?? '') ?></span>
                        </td>
                        <td style="width: 30%; border: none; padding: 5px 8px;">
                            <span class="label">Cost:</span> <span class="val"><?= htmlspecialchars($cost_val) ?></span>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td style="padding: 0; border: none;" colspan="2">
                <table style="width: 100%; border-collapse: collapse; border: none;">
                    <tr>
                        <td style="width: 25%; border: none; border-right: 1px solid #000; padding: 5px 8px;">
                            <span class="label">Processor:</span> <span class="val"><?= htmlspecialchars($a['processor'] ?? '') ?></span>
                        </td>
                        <td style="width: 28%; border: none; border-right: 1px solid #000; padding: 5px 8px;">
                            <span class="label">Storage (SSD/HDD):</span> <span class="val"><?= htmlspecialchars($a['storage'] ?? '') ?></span>
                        </td>
                        <td style="width: 25%; border: none; border-right: 1px solid #000; padding: 5px 8px;">
                            <span class="label">Memory/RAM:</span> <span class="val"><?= htmlspecialchars($a['memory'] ?? '') ?></span>
                        </td>
                        <td style="width: 22%; border: none; padding: 5px 8px;">
                            <span class="label">GPU:</span> <span class="val"><?= htmlspecialchars($a['gpu'] ?? '') ?></span>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- Section 2: Components Table -->
    <table class="form-grid mt-grid">
        <thead>
            <tr>
                <th style="width: 20%; border: 1px solid #000; height: 26px;"></th>
                <th style="width: 32%; border: 1px solid #000;">Brand/Model</th>
                <th style="width: 28%; border: 1px solid #000;">Serial Number</th>
                <th style="width: 20%; border: 1px solid #000;">PTR Number</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="label">System Unit</td>
                <td><?= htmlspecialchars($a['brand_model'] ?? '') ?></td>
                <td style="font-family: monospace;"><?= htmlspecialchars($a['serial_number'] ?? '') ?></td>
                <td><?= htmlspecialchars($a['ptr_number'] ?? '') ?></td>
            </tr>
            <tr>
                <td class="label">Monitor</td>
                <td><?= htmlspecialchars($a['monitor'] ?? '') ?></td>
                <td style="font-family: monospace;"><?= htmlspecialchars($a['monitor_sn'] ?? '') ?></td>
                <td></td>
            </tr>
            <tr>
                <td class="label">Printer</td>
                <td><?= htmlspecialchars($a['printer'] ?? '') ?></td>
                <td style="font-family: monospace;"><?= htmlspecialchars($a['printer_sn'] ?? '') ?></td>
                <td></td>
            </tr>
            <tr>
                <td class="label">UPS/AVR</td>
                <td><?= htmlspecialchars($a['ups_avr'] ?? '') ?></td>
                <td style="font-family: monospace;"><?= htmlspecialchars($a['ups_avr_sn'] ?? '') ?></td>
                <td></td>
            </tr>
            <tr>
                <td class="label">Scanner</td>
                <td></td>
                <td></td>
                <td></td>
            </tr>
            <tr>
                <td class="label">Keyboard</td>
                <td><?= htmlspecialchars($keyboard) ?></td>
                <td></td>
                <td></td>
            </tr>
            <tr>
                <td class="label">Mouse</td>
                <td><?= htmlspecialchars($mouse) ?></td>
                <td></td>
                <td></td>
            </tr>
        </tbody>
    </table>

    <!-- Section 3: System, Network, Verification -->
    <table class="form-grid mt-grid">
        <tr>
            <td style="width: 40%;"><span class="label">Windows OS:</span> <span class="val"><?= htmlspecialchars($a['os_version'] ?? '') ?></span></td>
            <td style="width: 28%;">
                <span class="label">Licensed:</span> &nbsp;
                <span class="chk-box"><?= $os_lic ? '&#10003;' : '' ?></span> Yes &nbsp;&nbsp;&nbsp;
                <span class="chk-box"><?= !$os_lic ? '&#10003;' : '' ?></span> No
            </td>
            <td style="width: 32%;"><span class="label">IP Address:</span> <span class="val" style="font-family: monospace;"><?= htmlspecialchars($a['ip_address'] ?? '') ?></span></td>
        </tr>
        <tr>
            <td><span class="label">MS Office:</span> <span class="val"><?= htmlspecialchars($a['ms_office'] ?? '') ?></span></td>
            <td>
                <span class="label">Licensed:</span> &nbsp;
                <span class="chk-box"><?= $ms_lic ? '&#10003;' : '' ?></span> Yes &nbsp;&nbsp;&nbsp;
                <span class="chk-box"><?= !$ms_lic ? '&#10003;' : '' ?></span> No
            </td>
            <td><span class="label">MAC Address:</span> <span class="val" style="font-family: monospace;"><?= htmlspecialchars($a['mac_address'] ?? '') ?></span></td>
        </tr>
        <tr>
            <td><span class="label">Checked By:</span> <span class="val"><?= htmlspecialchars($a['encoded_by'] ?? '') ?></span></td>
            <td><span class="label">Date:</span> <span class="val"><?= htmlspecialchars($a['scan_date'] ?? date('Y-m-d')) ?></span></td>
            <td><span class="label">Anti-Virus:</span> <span class="val"><?= htmlspecialchars($a['antivirus'] ?? '') ?></span></td>
        </tr>
    </table>
</div>
<?php endforeach; ?>

</body>
</html>
