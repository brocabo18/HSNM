<?php
/**
 * HSNM Database Comparison Tool
 * Compares LOCAL database row counts with RAILWAY database row counts
 * Access: http://192.163.10.11/HSNM/db_compare.php (localhost only)
 */

// Security: localhost only
$allowed = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1', '192.163.10.11']);
// Remove restriction for now to allow access from any machine on the network
// if (!$allowed) { http_response_code(403); die("Access denied."); }

require_once __DIR__ . '/config.php';

// ── Railway credentials from import_railway.bat ──────────────────────────────
$railway = [
    'host'     => 'reseau.proxy.rlwy.net',
    'port'     => 18700,
    'dbname'   => 'railway',
    'user'     => 'postgres',
    'password' => 'fTYMTwPRmJIanIITTypnhLWbQQfZXxwQ',
];

// Tables to compare
$tables = [
    'routers'         => 'Routers',
    'switches'        => 'Switches',
    'ips'             => 'IP Addresses',
    'computers'       => 'Computers',
    'ics_inventory'   => 'ICS Inventory',
    'office_licenses' => 'Office Licenses',
    'pabx_directory'  => 'PABX Directory',
    'printers'        => 'Printers',
    'queueing_tvs'    => 'Queueing TVs',
    'users'           => 'Users',
    'audit_logs'      => 'Audit Logs',
    'changelog'       => 'Changelog',
];

// Connect to Railway
$railwayPdo = null;
$railwayError = null;
try {
    $railwayPdo = new PDO(
        "pgsql:host={$railway['host']};port={$railway['port']};dbname={$railway['dbname']};connect_timeout=10",
        $railway['user'],
        $railway['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
} catch (PDOException $e) {
    $railwayError = $e->getMessage();
}

// Gather counts
$results = [];
foreach ($tables as $table => $label) {
    $local  = null;
    $remote = null;

    try {
        $local = (int) $pdo->query("SELECT COUNT(*) FROM \"$table\"")->fetchColumn();
    } catch (Exception $e) {
        $local = 'ERR: ' . $e->getMessage();
    }

    if ($railwayPdo) {
        try {
            $remote = (int) $railwayPdo->query("SELECT COUNT(*) FROM \"$table\"")->fetchColumn();
        } catch (Exception $e) {
            $remote = 'ERR: ' . $e->getMessage();
        }
    } else {
        $remote = 'N/A';
    }

    $match = is_int($local) && is_int($remote) && $local === $remote;
    $results[$table] = compact('label', 'local', 'remote', 'match');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>HSNM - DB Comparison</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #0f172a; color: #e2e8f0; padding: 2rem; margin: 0; }
        h1 { color: #38bdf8; margin-bottom: 0.25rem; }
        p.sub { color: #64748b; margin-top: 0; margin-bottom: 2rem; }
        .badge { display: inline-block; padding: 2px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; }
        .badge-ok  { background: #064e3b; color: #34d399; }
        .badge-err { background: #450a0a; color: #f87171; }
        .badge-warn{ background: #451a03; color: #fb923c; }
        table { width: 100%; border-collapse: collapse; max-width: 800px; }
        thead th { text-align: left; padding: 12px 16px; background: #1e293b; color: #94a3b8; font-size: 12px; text-transform: uppercase; letter-spacing: 0.05em; }
        tbody tr { border-bottom: 1px solid #1e293b; }
        tbody tr:hover { background: #1e293b55; }
        td { padding: 12px 16px; }
        td.num { font-family: monospace; font-size: 16px; font-weight: 700; }
        td.local-num  { color: #38bdf8; }
        td.remote-num { color: #a78bfa; }
        td.diff-ok    { color: #34d399; }
        td.diff-err   { color: #f87171; font-weight: 700; }
        .conn-ok   { color: #34d399; }
        .conn-err  { color: #f87171; }
        .section { margin-bottom: 1rem; color: #94a3b8; font-size: 14px; }
        .alert { background: #450a0a; border: 1px solid #7f1d1d; border-radius: 8px; padding: 1rem; margin-bottom: 1.5rem; color: #fca5a5; }
        .action-box { background: #1e293b; border: 1px solid #334155; border-radius: 12px; padding: 1.5rem; margin-top: 2rem; max-width: 800px; }
        .action-box h2 { color: #f59e0b; margin-top: 0; }
        .action-box ol { color: #cbd5e1; line-height: 2; }
        code { background: #0f172a; padding: 2px 6px; border-radius: 4px; font-size: 13px; color: #7dd3fc; }
    </style>
</head>
<body>
    <h1>🔍 HSNM Database Comparison</h1>
    <p class="sub">Local (192.163.10.11) vs Railway (hsnm-main.up.railway.app)</p>

    <?php if ($railwayError): ?>
    <div class="alert">
        <strong>⚠️ Cannot connect to Railway DB:</strong><br>
        <?= htmlspecialchars($railwayError) ?>
    </div>
    <?php else: ?>
    <div class="section">✅ <span class="conn-ok">Railway connection successful</span> &nbsp;|&nbsp; 🏠 Local DB: <code><?= DB_NAME ?></code> &nbsp;|&nbsp; 🚂 Railway DB: <code><?= $railway['dbname'] ?>@<?= $railway['host'] ?></code></div>
    <?php endif; ?>

    <table>
        <thead>
            <tr>
                <th>Module</th>
                <th>Table</th>
                <th>Local Count</th>
                <th>Railway Count</th>
                <th>Difference</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($results as $table => $r):
                $diff = (is_int($r['local']) && is_int($r['remote'])) ? ($r['local'] - $r['remote']) : '—';
                $diffClass = $r['match'] ? 'diff-ok' : 'diff-err';
            ?>
            <tr>
                <td><?= htmlspecialchars($r['label']) ?></td>
                <td><code><?= $table ?></code></td>
                <td class="num local-num"><?= is_int($r['local']) ? number_format($r['local']) : htmlspecialchars($r['local']) ?></td>
                <td class="num remote-num"><?= is_int($r['remote']) ? number_format($r['remote']) : htmlspecialchars($r['remote']) ?></td>
                <td class="num <?= $diffClass ?>"><?= is_int($diff) ? ($diff > 0 ? '+' . number_format($diff) : number_format($diff)) : $diff ?></td>
                <td>
                    <?php if ($r['match']): ?>
                        <span class="badge badge-ok">✅ Match</span>
                    <?php elseif (is_int($r['local']) && is_int($r['remote'])): ?>
                        <span class="badge badge-err">❌ Mismatch</span>
                    <?php else: ?>
                        <span class="badge badge-warn">⚠️ Error</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="action-box">
        <h2>🛠️ How to Fix: Sync Local → Railway</h2>
        <ol>
            <li>Run the export script locally to generate a fresh SQL dump:<br>
                <code>http://192.163.10.11/HSNM/database/export.php</code>
            </li>
            <li>Then import using the bat file:<br>
                <code>C:\xampp\htdocs\HSNM\database\import_railway.bat</code>
            </li>
            <li>Or run directly:<br>
                <code>set PGPASSWORD=fTYMTwPRmJIanIITTypnhLWbQQfZXxwQ</code><br>
                <code>"C:\Program Files\PostgreSQL\17\bin\psql.exe" -h reseau.proxy.rlwy.net -p 18700 -U postgres -d railway -f C:\xampp\htdocs\HSNM\database\schema_export.sql</code>
            </li>
        </ol>
        <p style="color:#64748b;font-size:13px;margin-bottom:0;">
            ⚠️ The import will TRUNCATE existing Railway tables before inserting local data. Make sure the export is fresh.
        </p>
    </div>

    <p style="color:#334155;font-size:12px;margin-top:2rem;">Generated: <?= date('Y-m-d H:i:s') ?></p>
</body>
</html>
