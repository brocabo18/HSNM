<?php
/**
 * HSNM Two-Way Database Sync (Local <-> Neon.tech / Railway)
 */

require_once __DIR__ . '/config.php';

// Helper to create remote PDO connection with auto-retry
function createRemotePdo($neonUrl) {
    $dbUrl = parse_url(trim($neonUrl));
    if (!$dbUrl || empty($dbUrl['host'])) {
        throw new Exception("Invalid connection URL format. Expected: postgresql://user:password@hostname:5432/dbname");
    }

    $host = $dbUrl['host'];
    $port = $dbUrl['port'] ?? 5432;
    $db   = ltrim($dbUrl['path'] ?? 'neondb', '/');
    $user = isset($dbUrl['user']) ? urldecode($dbUrl['user']) : '';
    $pass = isset($dbUrl['pass']) ? urldecode($dbUrl['pass']) : '';

    $attempts = [];
    $attempts[] = "pgsql:host=$host;port=$port;dbname=$db;sslmode=require";

    if (strpos($host, '.neon.tech') !== false) {
        $endpoint_id = explode('.', $host)[0];
        $endpoint_id = str_replace('-pooler', '', $endpoint_id);
        $attempts[] = "pgsql:host=$host;port=$port;dbname=$db;sslmode=require;options='endpoint=$endpoint_id'";
    }

    $lastEx = null;
    foreach ($attempts as $dsn) {
        try {
            $remotePdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => true,
                PDO::ATTR_TIMEOUT            => 10
            ]);
            $remotePdo->exec("SET search_path TO public;");
            return $remotePdo;
        } catch (Exception $e) {
            $lastEx = $e;
        }
    }
    throw $lastEx;
}

// All tables to synchronize in dependency order
$tables_config = [
    'settings'               => ['pk' => 'id', 'ts' => 'updated_at',  'match' => 'setting_key'],
    'users'                  => ['pk' => 'id', 'ts' => 'updated_at',  'match' => 'username'],
    'subnets'                => ['pk' => 'id', 'ts' => 'created_at'],
    'ips'                    => ['pk' => 'id', 'ts' => 'last_seen',   'match' => 'ip_address'],
    'routers'                => ['pk' => 'id', 'ts' => 'last_seen'],
    'switches'               => ['pk' => 'id', 'ts' => 'updated_at',  'match' => 'switch_id'],
    'computers'              => ['pk' => 'id', 'ts' => 'updated_at'],
    'computers_jasmc_st'     => ['pk' => 'id', 'ts' => 'created_at',  'match' => 'control_number'],
    'computers_jasmc_vt'     => ['pk' => 'id', 'ts' => 'created_at',  'match' => 'control_number'],
    'computers_doh_jbl_cmc' => ['pk' => 'id', 'ts' => 'created_at',  'match' => 'control_number'],
    'ips_jasmc_st'           => ['pk' => 'id', 'ts' => 'last_seen',   'match' => 'ip_address'],
    'ips_jasmc_vt'           => ['pk' => 'id', 'ts' => 'last_seen',   'match' => 'ip_address'],
    'ips_doh_jbl_cmc'       => ['pk' => 'id', 'ts' => 'last_seen',   'match' => 'ip_address'],
    'pabx_directory'         => ['pk' => 'id', 'ts' => 'updated_at',  'match' => 'local_number'],
    'office_licenses'        => ['pk' => 'id', 'ts' => 'created_at'],
    'ics_inventory'          => ['pk' => 'id', 'ts' => 'updated_at'],
    'printers'               => ['pk' => 'id', 'ts' => 'updated_at',  'match' => 'printer_no'],
    'queueing_tvs'           => ['pk' => 'id', 'ts' => 'updated_at'],
    'ihoms_links'            => ['pk' => 'id', 'ts' => 'updated_at'],
    'firewall_status'        => ['pk' => 'id', 'ts' => 'updated_at',  'match' => 'control_number'],
    'assets'                 => ['pk' => 'id', 'ts' => 'updated_at',  'match' => 'control_id'],
    'maintenance'            => ['pk' => 'id', 'ts' => 'updated_at'],
    'audit_logs'             => ['pk' => 'id', 'ts' => 'created_at'],
    'changelog'              => ['pk' => 'id', 'ts' => 'change_date']
];

// ── Handle AJAX Test Connection ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'test') {
    header('Content-Type: application/json');
    $neonUrl = $_POST['neon_url'] ?? '';

    if (empty($neonUrl)) {
        echo json_encode(['success' => false, 'error' => 'Remote connection string is required.']);
        exit;
    }

    try {
        $remotePdo = createRemotePdo($neonUrl);
        $stmt = $remotePdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema='public' AND table_type='BASE TABLE'");
        $remoteTables = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $stmtLocal = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema='public' AND table_type='BASE TABLE'");
        $localTables = $stmtLocal->fetchAll(PDO::FETCH_COLUMN);

        $summary = [];
        foreach ($tables_config as $table => $cfg) {
            $hasLocal = in_array($table, $localTables);
            $hasRemote = in_array($table, $remoteTables);
            $lCount = $hasLocal ? (int)$pdo->query("SELECT COUNT(*) FROM \"$table\"")->fetchColumn() : null;
            $rCount = $hasRemote ? (int)$remotePdo->query("SELECT COUNT(*) FROM \"$table\"")->fetchColumn() : null;

            $summary[] = [
                'table'      => $table,
                'has_local'  => $hasLocal,
                'has_remote' => $hasRemote,
                'local_cnt'  => $lCount,
                'remote_cnt' => $rCount,
            ];
        }

        echo json_encode([
            'success' => true,
            'message' => 'Connected to Remote DB successfully! Found ' . count($remoteTables) . ' remote tables.',
            'summary' => $summary
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Connection failed: ' . $e->getMessage()]);
    }
    exit;
}

// ── Handle AJAX Sync request ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'sync') {
    header('Content-Type: application/json');
    $neonUrl = $_POST['neon_url'] ?? '';
    $allowDeletes = !empty($_POST['allow_deletes']);

    if (empty($neonUrl)) {
        echo json_encode(['success' => false, 'error' => 'Remote connection string is required.']);
        exit;
    }

    try {
        $remotePdo = createRemotePdo($neonUrl);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Connection to Remote DB failed: ' . $e->getMessage()]);
        exit;
    }

    $logs = [];
    $logs[] = "✅ Connected to Local and Remote databases successfully.";
    if (!$allowDeletes) {
        $logs[] = "🛡️ <b>Safe Mode Active:</b> Record deletions are disabled. Inserts and updates will proceed.";
    }

    // Temporarily bypass foreign key triggers during sync if supported
    try { $remotePdo->exec("SET session_replication_role = 'replica';"); } catch (Exception $e) {}
    try { $pdo->exec("SET session_replication_role = 'replica';"); } catch (Exception $e) {}

    // Get list of existing tables in remote DB
    $remoteTablesStmt = $remotePdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema='public' AND table_type='BASE TABLE'");
    $remoteExistingTables = $remoteTablesStmt->fetchAll(PDO::FETCH_COLUMN);

    // Get list of existing tables in local DB
    $localTablesStmt = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema='public' AND table_type='BASE TABLE'");
    $localExistingTables = $localTablesStmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($tables_config as $table => $config) {
        $pk = $config['pk'];
        $ts = $config['ts'];

        if (!in_array($table, $localExistingTables)) {
            continue; // Table doesn't exist locally
        }

        if (!in_array($table, $remoteExistingTables)) {
            $logs[] = "⚠️ <b>$table</b> does not exist in Remote Neon DB. Please execute the corresponding SQL schema in Neon SQL Editor. Skipped.";
            continue;
        }

        try {
            // Retrieve column metadata and data types for both local and remote
            $localColsStmt = $pdo->prepare("SELECT column_name, data_type FROM information_schema.columns WHERE table_schema='public' AND table_name=?");
            $localColsStmt->execute([$table]);
            $localColTypes = $localColsStmt->fetchAll(PDO::FETCH_KEY_PAIR);
            $localCols = array_keys($localColTypes);

            $remoteColsStmt = $remotePdo->prepare("SELECT column_name, data_type FROM information_schema.columns WHERE table_schema='public' AND table_name=?");
            $remoteColsStmt->execute([$table]);
            $remoteColTypes = $remoteColsStmt->fetchAll(PDO::FETCH_KEY_PAIR);
            $remoteCols = array_keys($remoteColTypes);

            $commonCols = array_values(array_intersect($localCols, $remoteCols));

            if (empty($commonCols) || !in_array($pk, $commonCols)) {
                $logs[] = "❌ <b>$table</b>: Primary key <code>$pk</code> not found in both databases. Skipped.";
                continue;
            }

            // Sanitizer to convert boolean, date, numeric empty strings properly for PostgreSQL
            $formatVal = function($val, $col, $targetColTypes) {
                $type = $targetColTypes[$col] ?? 'text';

                if ($type === 'boolean') {
                    if ($val === null || $val === '') {
                        return null;
                    }
                    if ($val === true || $val === 't' || $val === 'true' || $val === 1 || $val === '1' || $val === 'Y' || $val === 'y') {
                        return 'true';
                    }
                    return 'false';
                }

                if (in_array($type, ['integer', 'bigint', 'smallint', 'numeric', 'decimal', 'real', 'double precision', 'date', 'timestamp without time zone', 'timestamp with time zone'])) {
                    if ($val === '' || $val === null) {
                        return null;
                    }
                }

                return $val;
            };

            $matchCol = $config['match'] ?? $pk;
            if (!in_array($matchCol, $commonCols)) {
                $matchCol = $pk;
            }

            $colList = implode(', ', array_map(fn($c) => "\"$c\"", $commonCols));

            $localRows = $pdo->query("SELECT $colList FROM \"$table\"")->fetchAll();
            $remoteRows = $remotePdo->query("SELECT $colList FROM \"$table\"")->fetchAll();

            // Function to generate unique matching key for a row
            $getRowKey = function($row) use ($matchCol, $pk) {
                if ($matchCol !== $pk && isset($row[$matchCol]) && $row[$matchCol] !== null) {
                    $v = trim((string)$row[$matchCol]);
                    if ($v !== '') {
                        return 'm:' . mb_strtolower($v);
                    }
                }
                return 'id:' . $row[$pk];
            };

            $localMap = [];
            $localPks = [];
            foreach ($localRows as $row) {
                $localMap[$getRowKey($row)] = $row;
                $localPks[$row[$pk]] = true;
            }

            $remoteMap = [];
            $remotePks = [];
            foreach ($remoteRows as $row) {
                $remoteMap[$getRowKey($row)] = $row;
                $remotePks[$row[$pk]] = true;
            }

            $insertedRemote = 0;
            $updatedRemote  = 0;
            $deletedRemote  = 0;
            $insertedLocal  = 0;
            $updatedLocal   = 0;
            $deletedLocal   = 0;

            $maxLocalId = empty($localPks) ? 0 : max(array_keys($localPks));
            $maxRemoteId = empty($remotePks) ? 0 : max(array_keys($remotePks));

            // Columns to update/insert without PK
            $updateCols = array_values(array_filter($commonCols, fn($c) => $c !== $pk));
            $updateSetSql = implode(', ', array_map(fn($c) => "\"$c\" = ?", $updateCols));

            // Prepared statements
            $placeholdersFull = implode(', ', array_fill(0, count($commonCols), '?'));
            $remoteInsertFull = $remotePdo->prepare("INSERT INTO \"$table\" ($colList) VALUES ($placeholdersFull)");
            $localInsertFull  = $pdo->prepare("INSERT INTO \"$table\" ($colList) VALUES ($placeholdersFull)");

            $remoteInsertNoPk = null;
            $localInsertNoPk  = null;
            if (!empty($updateCols)) {
                $colListNoPk = implode(', ', array_map(fn($c) => "\"$c\"", $updateCols));
                $placeholdersNoPk = implode(', ', array_fill(0, count($updateCols), '?'));
                $remoteInsertNoPk = $remotePdo->prepare("INSERT INTO \"$table\" ($colListNoPk) VALUES ($placeholdersNoPk)");
                $localInsertNoPk  = $pdo->prepare("INSERT INTO \"$table\" ($colListNoPk) VALUES ($placeholdersNoPk)");
            }

            $remoteUpdateStmt = !empty($updateCols) ? $remotePdo->prepare("UPDATE \"$table\" SET $updateSetSql WHERE \"$pk\" = ?") : null;
            $localUpdateStmt  = !empty($updateCols) ? $pdo->prepare("UPDATE \"$table\" SET $updateSetSql WHERE \"$pk\" = ?") : null;

            // Safe insert executor with fallback
            $execInsert = function($targetPdo, $targetInsertFull, $targetInsertNoPk, $row, &$targetPks, &$insertedCount, $targetColTypes) use ($commonCols, $updateCols, $formatVal, $pk) {
                // Determine if we can include PK
                $canIncludePk = !isset($targetPks[$row[$pk]]);
                $values = [];
                if ($canIncludePk || !$targetInsertNoPk) {
                    foreach ($commonCols as $c) {
                        $values[] = $formatVal($row[$c], $c, $targetColTypes);
                    }
                    try {
                        $targetInsertFull->execute($values);
                        $insertedCount++;
                        $targetPks[$row[$pk]] = true;
                        return;
                    } catch (PDOException $e) {
                        if ($e->getCode() == '23505' && $targetInsertNoPk) {
                            // Primary key collided, try inserting without PK to auto-generate sequence ID
                            $valsNoPk = [];
                            foreach ($updateCols as $c) {
                                $valsNoPk[] = $formatVal($row[$c], $c, $targetColTypes);
                            }
                            $targetInsertNoPk->execute($valsNoPk);
                            $insertedCount++;
                            return;
                        }
                        throw $e;
                    }
                } else {
                    // PK is already occupied by a different record, omit PK
                    foreach ($updateCols as $c) {
                        $values[] = $formatVal($row[$c], $c, $targetColTypes);
                    }
                    $targetInsertNoPk->execute($values);
                    $insertedCount++;
                }
            };

            // 1. Sync Local -> Remote
            foreach ($localMap as $key => $localRow) {
                if (!isset($remoteMap[$key])) {
                    if ($allowDeletes && $matchCol === $pk && $localRow[$pk] <= $maxRemoteId) {
                        // Deleted remotely
                        $stmt = $pdo->prepare("DELETE FROM \"$table\" WHERE \"$pk\" = ?");
                        $stmt->execute([$localRow[$pk]]);
                        $deletedLocal++;
                    } else {
                        // Insert into remote
                        $execInsert($remotePdo, $remoteInsertFull, $remoteInsertNoPk, $localRow, $remotePks, $insertedRemote, $remoteColTypes);
                    }
                } else {
                    $remoteRow = $remoteMap[$key];
                    if ($remoteUpdateStmt) {
                        $shouldUpdate = false;
                        if (in_array($ts, $commonCols)) {
                            $localTs  = strtotime($localRow[$ts] ?? '1970-01-01');
                            $remoteTs = strtotime($remoteRow[$ts] ?? '1970-01-01');

                            if ($localTs > $remoteTs) {
                                $shouldUpdate = true;
                            } elseif ($localTs === $remoteTs) {
                                foreach ($updateCols as $c) {
                                    if ((string)($localRow[$c] ?? '') !== (string)($remoteRow[$c] ?? '')) {
                                        $shouldUpdate = true;
                                        break;
                                    }
                                }
                            }
                        } else {
                            foreach ($updateCols as $c) {
                                if ((string)($localRow[$c] ?? '') !== (string)($remoteRow[$c] ?? '')) {
                                    $shouldUpdate = true;
                                    break;
                                }
                            }
                        }

                        if ($shouldUpdate) {
                            $uParams = [];
                            foreach ($updateCols as $c) {
                                $uParams[] = $formatVal($localRow[$c], $c, $remoteColTypes);
                            }
                            $uParams[] = $remoteRow[$pk]; // Target's own PK!
                            $remoteUpdateStmt->execute($uParams);
                            $updatedRemote++;
                        }
                    }
                }
            }

            // 2. Sync Remote -> Local
            foreach ($remoteMap as $key => $remoteRow) {
                if (!isset($localMap[$key])) {
                    if ($allowDeletes && $matchCol === $pk && $remoteRow[$pk] <= $maxLocalId) {
                        // Deleted locally
                        $stmt = $remotePdo->prepare("DELETE FROM \"$table\" WHERE \"$pk\" = ?");
                        $stmt->execute([$remoteRow[$pk]]);
                        $deletedRemote++;
                    } else {
                        // Insert into local
                        $execInsert($pdo, $localInsertFull, $localInsertNoPk, $remoteRow, $localPks, $insertedLocal, $localColTypes);
                    }
                } else {
                    $localRow = $localMap[$key];
                    if ($localUpdateStmt) {
                        $shouldUpdate = false;
                        if (in_array($ts, $commonCols)) {
                            $localTs  = strtotime($localRow[$ts] ?? '1970-01-01');
                            $remoteTs = strtotime($remoteRow[$ts] ?? '1970-01-01');

                            if ($remoteTs > $localTs) {
                                $shouldUpdate = true;
                            }
                        }

                        if ($shouldUpdate) {
                            $uParams = [];
                            foreach ($updateCols as $c) {
                                $uParams[] = $formatVal($remoteRow[$c], $c, $localColTypes);
                            }
                            $uParams[] = $localRow[$pk]; // Target's own PK!
                            $localUpdateStmt->execute($uParams);
                            $updatedLocal++;
                        }
                    }
                }
            }

            // 3. Reset PostgreSQL sequences
            try {
                $pdo->exec("SELECT setval(pg_get_serial_sequence('$table', '$pk'), coalesce(max($pk),0) + 1, false) FROM \"$table\";");
                $remotePdo->exec("SELECT setval(pg_get_serial_sequence('$table', '$pk'), coalesce(max($pk),0) + 1, false) FROM \"$table\";");
            } catch (Exception $e) {}

            $diffLog = [];
            if ($insertedRemote > 0) $diffLog[] = "+$insertedRemote inserted into Neon";
            if ($updatedRemote > 0)  $diffLog[] = "~$updatedRemote updated in Neon";
            if ($deletedRemote > 0)  $diffLog[] = "-$deletedRemote deleted in Neon";
            if ($insertedLocal > 0)  $diffLog[] = "+$insertedLocal inserted into Local";
            if ($updatedLocal > 0)   $diffLog[] = "~$updatedLocal updated in Local";
            if ($deletedLocal > 0)   $diffLog[] = "-$deletedLocal deleted in Local";

            $summaryStr = empty($diffLog) ? "Already in sync" : implode(', ', $diffLog);
            $logs[] = "➡️ <b>$table</b>: $summaryStr";

        } catch (Exception $e) {
            $logs[] = "❌ <b>$table</b> Error: " . $e->getMessage();
        }
    }

    // Restore standard constraint triggers
    try { $remotePdo->exec("SET session_replication_role = 'origin';"); } catch (Exception $e) {}
    try { $pdo->exec("SET session_replication_role = 'origin';"); } catch (Exception $e) {}

    $logs[] = "🎉 Sync completed!";
    echo json_encode(['success' => true, 'logs' => $logs]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HSNM Two-Way Database Sync (Local <-> Neon)</title>
    <style>
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #0f172a; color: #e2e8f0; padding: 2rem; margin: 0; }
        .container { max-width: 860px; margin: 0 auto; background: #1e293b; padding: 2.2rem; border-radius: 16px; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.5); border: 1px solid #334155; }
        h1 { color: #38bdf8; margin-top: 0; display: flex; align-items: center; gap: 10px; font-size: 22px; }
        .input-group { margin-bottom: 1.5rem; }
        label { display: block; font-size: 13px; font-weight: 600; color: #94a3b8; margin-bottom: 0.5rem; }
        input[type="text"], input[type="password"] { width: 100%; padding: 12px 16px; background: #0f172a; border: 1px solid #334155; border-radius: 10px; color: #e2e8f0; font-family: monospace; font-size: 13px; box-sizing: border-box; transition: border-color 0.2s; }
        input[type="text"]:focus, input[type="password"]:focus { outline: none; border-color: #38bdf8; }
        .btn-row { display: flex; gap: 10px; margin-bottom: 1.5rem; }
        .btn { padding: 12px 20px; border: none; border-radius: 10px; cursor: pointer; font-size: 14px; font-weight: 700; transition: all 0.2s; display: flex; justify-content: center; align-items: center; gap: 8px; }
        .btn-primary { background: #38bdf8; color: #0f172a; flex: 2; }
        .btn-primary:hover { background: #0284c7; }
        .btn-secondary { background: #334155; color: #f1f5f9; flex: 1; }
        .btn-secondary:hover { background: #475569; }
        .btn:disabled { background: #1e293b; color: #64748b; cursor: not-allowed; border: 1px solid #334155; }
        .checkbox-row { margin-bottom: 1.5rem; display: flex; align-items: center; gap: 8px; font-size: 13px; color: #94a3b8; }
        .checkbox-row input { width: 16px; height: 16px; accent-color: #38bdf8; }
        .logs-container { background: #0b0f19; border: 1px solid #1e293b; border-radius: 10px; padding: 1.2rem; min-height: 250px; max-height: 480px; overflow-y: auto; font-family: monospace; font-size: 12.5px; line-height: 1.7; }
        .log-entry { margin-bottom: 6px; padding-bottom: 6px; border-bottom: 1px solid #1e293b/60; }
        .log-entry:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
        .local-info { margin-bottom: 1.5rem; padding: 1rem 1.2rem; background: #064e3b/40; border: 1px solid #047857; border-radius: 10px; color: #a7f3d0; font-size: 13px; display: flex; justify-content: space-between; align-items: center; }
        table.status-table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 12px; }
        table.status-table th { text-align: left; padding: 6px 8px; background: #1e293b; color: #94a3b8; border-bottom: 1px solid #334155; }
        table.status-table td { padding: 6px 8px; border-bottom: 1px solid #1e293b; }
    </style>
</head>
<body>
    <div class="container">
        <h1>
            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.29 7 12 12 20.71 7"></polyline><line x1="12" y1="22" x2="12" y2="12"></line></svg>
            HSNM Database Sync (Local PostgreSQL ⟷ Neon Cloud)
        </h1>
        
        <div class="local-info">
            <div><strong>🏠 Local Database:</strong> <code><?= htmlspecialchars(DB_NAME) ?></code> on <code><?= htmlspecialchars(DB_HOST) ?>:<?= DB_PORT ?></code></div>
            <a href="modules/computers/" style="color:#38bdf8; text-decoration:none; font-weight:bold; font-size:12px;">← Back to App</a>
        </div>

        <div class="input-group">
            <label for="neon_url">Remote Neon Database Connection String</label>
            <input type="password" id="neon_url" value="" placeholder="postgresql://user:password@ep-xxxx-pooler.ap-southeast-1.aws.neon.tech/neondb?sslmode=require" autocomplete="off">
            <div style="font-size: 11.5px; color: #64748b; margin-top: 6px;">
                💡 Find this in your <strong>Neon Console</strong> &gt; <strong>Dashboard</strong> &gt; <strong>Connection Details</strong> (choose <strong>Pooled connection</strong>).
            </div>
        </div>

        <div class="checkbox-row">
            <input type="checkbox" id="allow_deletes">
            <label for="allow_deletes" style="margin:0; font-size:12.5px; cursor:pointer;">
                Enable Bi-Directional Deletion Sync <span style="color:#64748b;">(Leave unchecked for Safe Additive Mode: inserts and updates only)</span>
            </label>
        </div>

        <div class="btn-row">
            <button id="testBtn" class="btn btn-secondary" onclick="testConnection()">
                🔍 Test Connection
            </button>
            <button id="syncBtn" class="btn btn-primary" onclick="startSync()">
                ⚡ Run Two-Way Sync
            </button>
        </div>

        <div class="logs-container" id="logsContainer">
            <div style="color: #64748b;">Ready to sync. Paste your Neon connection string above, click <strong>Test Connection</strong> to check parity, or click <strong>Run Two-Way Sync</strong>.</div>
        </div>
    </div>

    <script>
        const STORAGE_KEY = 'hsnm_neon_url_cache';

        // Load saved URL from browser localStorage
        window.addEventListener('DOMContentLoaded', () => {
            const cached = localStorage.getItem(STORAGE_KEY);
            if (cached) {
                document.getElementById('neon_url').value = cached;
            }
        });

        function appendLog(html) {
            const container = document.getElementById('logsContainer');
            const entry = document.createElement('div');
            entry.className = 'log-entry';
            entry.innerHTML = html;
            container.appendChild(entry);
            container.scrollTop = container.scrollHeight;
        }

        async function testConnection() {
            const neonUrl = document.getElementById('neon_url').value.trim();
            if (!neonUrl) {
                alert("Please enter a valid remote connection URL.");
                return;
            }
            localStorage.setItem(STORAGE_KEY, neonUrl);

            const testBtn = document.getElementById('testBtn');
            const syncBtn = document.getElementById('syncBtn');
            const logsContainer = document.getElementById('logsContainer');

            testBtn.disabled = true;
            syncBtn.disabled = true;
            logsContainer.innerHTML = '';
            appendLog('⏳ Testing connection to Remote Neon DB...');

            try {
                const formData = new FormData();
                formData.append('action', 'test');
                formData.append('neon_url', neonUrl);

                const res = await fetch('sync_neon.php', { method: 'POST', body: formData });
                const data = await res.json();

                if (data.success) {
                    appendLog(`<span style="color: #34d399; font-weight: bold;">${data.message}</span>`);
                    let tableHtml = '<table class="status-table"><thead><tr><th>Table</th><th>Local Rows</th><th>Neon Rows</th><th>Status</th></tr></thead><tbody>';
                    
                    data.summary.forEach(row => {
                        let statusBadge = '';
                        if (!row.has_remote) {
                            statusBadge = '<span style="color:#f87171; font-weight:bold;">MISSING IN NEON</span>';
                        } else if (row.local_cnt === row.remote_cnt) {
                            statusBadge = '<span style="color:#34d399;">IN PARITY</span>';
                        } else {
                            statusBadge = '<span style="color:#fbbf24; font-weight:bold;">DIFF (NEEDS SYNC)</span>';
                        }

                        tableHtml += `<tr>
                            <td><strong>${row.table}</strong></td>
                            <td style="color:#38bdf8;">${row.has_local ? row.local_cnt : 'N/A'}</td>
                            <td style="color:#a7f3d0;">${row.has_remote ? row.remote_cnt : 'N/A'}</td>
                            <td>${statusBadge}</td>
                        </tr>`;
                    });
                    tableHtml += '</tbody></table>';
                    appendLog(tableHtml);
                } else {
                    appendLog(`<span style="color: #f87171; font-weight:bold;">❌ ${data.error}</span>`);
                }
            } catch (err) {
                appendLog(`<span style="color: #f87171;">Network Error: ${err.message}</span>`);
            } finally {
                testBtn.disabled = false;
                syncBtn.disabled = false;
            }
        }

        async function startSync() {
            const neonUrl = document.getElementById('neon_url').value.trim();
            if (!neonUrl) {
                alert("Please enter a valid remote connection URL.");
                return;
            }
            localStorage.setItem(STORAGE_KEY, neonUrl);

            const testBtn = document.getElementById('testBtn');
            const syncBtn = document.getElementById('syncBtn');
            const allowDeletes = document.getElementById('allow_deletes').checked;
            const logsContainer = document.getElementById('logsContainer');

            testBtn.disabled = true;
            syncBtn.disabled = true;
            syncBtn.innerText = 'Syncing...';
            logsContainer.innerHTML = '';
            appendLog('⏳ Starting synchronization process...');

            try {
                const formData = new FormData();
                formData.append('action', 'sync');
                formData.append('neon_url', neonUrl);
                if (allowDeletes) {
                    formData.append('allow_deletes', '1');
                }

                const res = await fetch('sync_neon.php', { method: 'POST', body: formData });
                const data = await res.json();

                if (data.success) {
                    data.logs.forEach(log => appendLog(log));
                } else {
                    appendLog(`<span style="color: #f87171; font-weight:bold;">❌ Error: ${data.error}</span>`);
                }
            } catch (err) {
                appendLog(`<span style="color: #f87171;">Network Error: ${err.message}</span>`);
            } finally {
                testBtn.disabled = false;
                syncBtn.disabled = false;
                syncBtn.innerText = '⚡ Run Two-Way Sync';
            }
        }
    </script>
</body>
</html>
