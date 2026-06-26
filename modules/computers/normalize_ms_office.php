<?php
/**
 * MS Office Data Normalization Script
 * Fixes inconsistent microsoft_office values in the computers table.
 * Run once, then delete or disable this file.
 */
require_once '../../config.php';
requireLogin();
if ($_SESSION['role'] !== 'admin') die('Admin only.');

$mappings = [
    // PROFESSIONAL PLUS 2019
    'PRO PLUS 2019'                            => 'PROFESSIONAL PLUS 2019',
    'MICROSOFT OFFICE PROFESSIONAL PLUS 2019'  => 'PROFESSIONAL PLUS 2019',
    'MS OFFICE 2019'                           => 'PROFESSIONAL PLUS 2019',
    'MS OFFICE PROFESSIONAL PLUS 2019'         => 'PROFESSIONAL PLUS 2019',
    // PROFESSIONAL PLUS 2021
    'PRO PLUS 2021'                            => 'PROFESSIONAL PLUS 2021',
    'OFFICE 2021'                              => 'PROFESSIONAL PLUS 2021',
    // PROFESSIONAL PLUS 2024
    'PRO PLUS 2024'                            => 'PROFESSIONAL PLUS 2024',
    'MS OFFICE 2024'                           => 'PROFESSIONAL PLUS 2024',
    // PROFESSIONAL PLUS 2013
    'PRO PLUS 2013'                            => 'PROFESSIONAL PLUS 2013',
    // HOME & STUDENT 2021
    'HOME AND STUDENT 2021'                    => 'HOME & STUDENT 2021',
    'HOME &STUDENT 2021'                       => 'HOME & STUDENT 2021',
    'MS OFFICE HOME AND STUDENT 2021'          => 'HOME & STUDENT 2021',
    'MS HOME AND STUDENT 2021'                 => 'HOME & STUDENT 2021',
    // HOME & STUDENT 2019
    'HOME AND STUDENT 2019'                    => 'HOME & STUDENT 2019',
    'HOME AND TUDENT 2019'                     => 'HOME & STUDENT 2019',
    'MICROSOFT OFFICE HOME STUDENT 2019'       => 'HOME & STUDENT 2019',
    'MS OFFICE HOME AND STUDENT 2019'          => 'HOME & STUDENT 2019',
    'HOME STUDENT 2019'                        => 'HOME & STUDENT 2019',
    'MICROSCOFT OFFICE HOME AND STUDENT 2019'  => 'HOME & STUDENT 2019',
    'MICROSOFT OFFICE HOME & STUDENT 2019'     => 'HOME & STUDENT 2019',
    // HOME & STUDENT 2016
    'HOME AND STUDENT 2016'                    => 'HOME & STUDENT 2016',
    'MS OFFFICE HOME AND STUDENT 2016'         => 'HOME & STUDENT 2016',
    // MICROSOFT 365
    'MS 365'                                   => 'MICROSOFT 365',
    'OFFICE 365'                               => 'MICROSOFT 365',
];

$total_updated = 0;
$results = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run'])) {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        die('CSRF validation failed.');
    }

    foreach ($mappings as $old => $new) {
        try {
            $stmt = $pdo->prepare(
                "UPDATE computers SET microsoft_office = ? WHERE UPPER(TRIM(microsoft_office)) = UPPER(?)"
            );
            $stmt->execute([$new, $old]);
            $count = $stmt->rowCount();
            $total_updated += $count;
            $results[] = ['from' => $old, 'to' => $new, 'count' => $count, 'ok' => true];
        } catch (Exception $e) {
            $results[] = ['from' => $old, 'to' => $new, 'count' => 0, 'ok' => false, 'error' => $e->getMessage()];
        }
    }

    // Log audit
    logAudit($pdo, 'bulk_normalize', "Normalized MS Office values: $total_updated records updated", 'computer', null);
}

// Preview: show current distinct values that don't match any canonical value
$canonical = array_unique(array_values($mappings));
$placeholders = implode(',', array_fill(0, count($canonical), '?'));
$preview = [];
try {
    $stmt = $pdo->prepare("
        SELECT microsoft_office, COUNT(*) as cnt 
        FROM computers 
        WHERE microsoft_office IS NOT NULL AND TRIM(microsoft_office) != ''
        AND UPPER(TRIM(microsoft_office)) NOT IN ($placeholders)
        GROUP BY microsoft_office 
        ORDER BY cnt DESC
    ");
    $stmt->execute(array_map('strtoupper', $canonical));
    $non_standard = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $non_standard = []; }
?>
<!DOCTYPE html>
<html>
<head>
    <title>MS Office Normalization</title>
    <style>
        body { font-family: sans-serif; padding: 2rem; background: #f8fafc; color: #1e293b; }
        h1 { font-size: 1.5rem; font-weight: bold; margin-bottom: 1rem; }
        table { border-collapse: collapse; width: 100%; margin-bottom: 2rem; }
        th, td { border: 1px solid #e2e8f0; padding: 8px 12px; text-align: left; font-size: .85rem; }
        th { background: #f1f5f9; font-weight: bold; }
        .ok { color: #16a34a; }
        .skip { color: #94a3b8; }
        .err { color: #dc2626; }
        .btn { background: #2563eb; color: white; border: none; padding: 10px 24px; border-radius: 8px; font-weight: bold; cursor: pointer; font-size: 1rem; }
        .btn:hover { background: #1d4ed8; }
        .warning { background: #fef9c3; border: 1px solid #eab308; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; }
        .success { background: #dcfce7; border: 1px solid #16a34a; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; }
    </style>
</head>
<body>
<h1>🔧 MS Office Data Normalization</h1>

<?php if (!empty($results)): ?>
<div class="success">
    ✅ Done! <strong><?= $total_updated ?></strong> total record(s) updated.
</div>
<table>
    <tr><th>Old Value</th><th>→ New Value</th><th>Updated</th><th>Status</th></tr>
    <?php foreach ($results as $r): ?>
    <tr>
        <td><?= htmlspecialchars($r['from']) ?></td>
        <td><?= htmlspecialchars($r['to']) ?></td>
        <td><?= $r['count'] ?></td>
        <td class="<?= $r['ok'] ? ($r['count'] > 0 ? 'ok' : 'skip') : 'err' ?>">
            <?= $r['ok'] ? ($r['count'] > 0 ? '✓ Updated' : '— None found') : ('✗ ' . ($r['error'] ?? 'Error')) ?>
        </td>
    </tr>
    <?php endforeach; ?>
</table>
<?php else: ?>

<div class="warning">
    ⚠️ This will update <strong><?= count($mappings) ?></strong> mapping rules across the <code>computers</code> table.
    Review the table below and click <strong>Run Normalization</strong> to proceed.
</div>

<h2 style="font-size:1.1rem; margin-bottom:.5rem;">Planned Mappings</h2>
<table>
    <tr><th>Old Value (in DB)</th><th>Will be changed to</th></tr>
    <?php foreach ($mappings as $old => $new): ?>
    <tr><td><?= htmlspecialchars($old) ?></td><td><?= htmlspecialchars($new) ?></td></tr>
    <?php endforeach; ?>
</table>

<?php if (!empty($non_standard)): ?>
<h2 style="font-size:1.1rem; margin-bottom:.5rem; color:#b45309;">⚠️ Non-standard Values Already in DB (not covered by this script)</h2>
<table>
    <tr><th>Current Value in DB</th><th>Count</th></tr>
    <?php foreach ($non_standard as $row): ?>
    <tr><td><?= htmlspecialchars($row['microsoft_office']) ?></td><td><?= $row['cnt'] ?></td></tr>
    <?php endforeach; ?>
</table>
<?php endif; ?>

<form method="POST">
    <?= getCsrfInput() ?>
    <input type="hidden" name="run" value="1">
    <button class="btn" type="submit" onclick="return confirm('Run normalization? This will update records in the database.')">
        ▶ Run Normalization
    </button>
</form>
<?php endif; ?>

</body>
</html>
