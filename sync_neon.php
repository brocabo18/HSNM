<?php
/**
 * HSNM Two-Way Database Sync (Local <-> Neon.tech / Railway)
 */

require_once __DIR__ . '/config.php';

// Security: limit to local development IPs or logged in admin
$allowed = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1', '192.163.10.11']);
// We can also allow logged in admin:
// if (!isset($_SESSION['user_id']) && !$allowed) { die("Access Denied"); }

// Handle AJAX Sync request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'sync') {
    header('Content-Type: application/json');
    $neonUrl = $_POST['neon_url'] ?? '';
    
    if (empty($neonUrl)) {
        echo json_encode(['success' => false, 'error' => 'Remote connection string is required.']);
        exit;
    }

    // Attempt connection
    try {
        $dbUrl = parse_url($neonUrl);
        $host = $dbUrl['host'] ?? '';
        $port = $dbUrl['port'] ?? 5432;
        $db   = ltrim($dbUrl['path'] ?? '', '/');
        $user = isset($dbUrl['user']) ? urldecode($dbUrl['user']) : '';
        $pass = isset($dbUrl['pass']) ? urldecode($dbUrl['pass']) : '';
        
        $options = ";sslmode=require";
        if (strpos($host, '.neon.tech') !== false) {
            $endpoint_id = explode('.', $host)[0];
            $endpoint_id = str_replace('-pooler', '', $endpoint_id);
            $options .= ";options='endpoint=$endpoint_id'";
        }

        $remotePdo = new PDO(
            "pgsql:host=$host;port=$port;dbname=$db" . $options,
            $user,
            $pass,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => true
            ]
        );
        $remotePdo->exec("SET search_path TO public;");
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Connection to Remote DB failed: ' . $e->getMessage()]);
        exit;
    }

    $logs = [];
    $logs[] = "✅ Connected to Local and Remote databases successfully.";
    
    // Tables to sync in order of dependencies (e.g. subnets before ips)
    $tables = [
        'settings'       => ['id', 'updated_at'],
        'users'          => ['id', 'updated_at'],
        'subnets'        => ['id', 'created_at'],
        'ips'            => ['id', 'last_seen'],
        'routers'        => ['id', 'last_seen'],
        'switches'       => ['id', 'updated_at'],
        'computers'      => ['id', 'updated_at'],
        'pabx_directory' => ['id', 'updated_at'],
        'office_licenses'=> ['id', 'created_at'],
        'ics_inventory'  => ['id', 'updated_at'],
        'printers'       => ['id', 'updated_at'],
        'queueing_tvs'   => ['id', 'updated_at'],
        'ihoms_links'    => ['id', 'updated_at'],
        'firewall_status'=> ['id', 'updated_at']
    ];

    foreach ($tables as $table => $config) {
        $pk = $config[0];
        $ts = $config[1]; // Timestamp column used for conflict resolution
        
        try {
            $localRows = $pdo->query("SELECT * FROM \"$table\"")->fetchAll();
            $remoteRows = $remotePdo->query("SELECT * FROM \"$table\"")->fetchAll();
            
            $localMap = [];
            foreach ($localRows as $row) $localMap[$row[$pk]] = $row;
            
            $remoteMap = [];
            foreach ($remoteRows as $row) $remoteMap[$row[$pk]] = $row;
            
            $insertedRemote = 0;
            $updatedRemote = 0;
            $insertedLocal = 0;
            $updatedLocal = 0;
            
            // 1. Sync Local -> Remote (Insert Missing, Update if Local is newer)
            foreach ($localMap as $id => $localRow) {
                if (!isset($remoteMap[$id])) {
                    // Insert into remote
                    $cols = array_keys($localRow);
                    $placeholders = implode(', ', array_fill(0, count($cols), '?'));
                    $stmt = $remotePdo->prepare("INSERT INTO \"$table\" (" . implode(', ', $cols) . ") VALUES ($placeholders)");
                    $stmt->execute(array_values($localRow));
                    $insertedRemote++;
                } else {
                    $remoteRow = $remoteMap[$id];
                    // Compare timestamps
                    $localTs = strtotime($localRow[$ts] ?? '1970-01-01');
                    $remoteTs = strtotime($remoteRow[$ts] ?? '1970-01-01');
                    
                    if ($localTs > $remoteTs) {
                        // Update remote
                        $updates = [];
                        foreach ($localRow as $k => $v) {
                            $updates[] = "\"$k\" = ?";
                        }
                        $stmt = $remotePdo->prepare("UPDATE \"$table\" SET " . implode(', ', $updates) . " WHERE \"$pk\" = ?");
                        $params = array_values($localRow);
                        $params[] = $id;
                        $stmt->execute($params);
                        $updatedRemote++;
                    }
                }
            }
            
            // 2. Sync Remote -> Local (Insert Missing, Update if Remote is newer)
            foreach ($remoteMap as $id => $remoteRow) {
                if (!isset($localMap[$id])) {
                    // Insert into local
                    $cols = array_keys($remoteRow);
                    $placeholders = implode(', ', array_fill(0, count($cols), '?'));
                    $stmt = $pdo->prepare("INSERT INTO \"$table\" (" . implode(', ', $cols) . ") VALUES ($placeholders)");
                    $stmt->execute(array_values($remoteRow));
                    $insertedLocal++;
                } else {
                    $localRow = $localMap[$id];
                    // Compare timestamps
                    $localTs = strtotime($localRow[$ts] ?? '1970-01-01');
                    $remoteTs = strtotime($remoteRow[$ts] ?? '1970-01-01');
                    
                    if ($remoteTs > $localTs) {
                        // Update local
                        $updates = [];
                        foreach ($remoteRow as $k => $v) {
                            $updates[] = "\"$k\" = ?";
                        }
                        $stmt = $pdo->prepare("UPDATE \"$table\" SET " . implode(', ', $updates) . " WHERE \"$pk\" = ?");
                        $params = array_values($remoteRow);
                        $params[] = $id;
                        $stmt->execute($params);
                        $updatedLocal++;
                    }
                }
            }
            
            // 3. Update PostgreSQL sequences so auto-increment works correctly after manual ID inserts
            try {
                $pdo->exec("SELECT setval(pg_get_serial_sequence('$table', '$pk'), coalesce(max($pk),0) + 1, false) FROM \"$table\";");
                $remotePdo->exec("SELECT setval(pg_get_serial_sequence('$table', '$pk'), coalesce(max($pk),0) + 1, false) FROM \"$table\";");
            } catch (Exception $e) {
                // Silently ignore sequence update failures (e.g., if the table has no serial sequence)
            }

            $logs[] = "➡️ <b>$table</b> synced. " . 
                      "<br>&nbsp;&nbsp;&nbsp;Local -> Remote: +$insertedRemote inserted, ~$updatedRemote updated" .
                      "<br>&nbsp;&nbsp;&nbsp;Remote -> Local: +$insertedLocal inserted, ~$updatedLocal updated";
            
        } catch (Exception $e) {
            $logs[] = "❌ <b>$table</b> Error: " . $e->getMessage();
        }
    }
    
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
    <title>HSNM Two-Way Sync</title>
    <style>
        body { font-family: 'Inter', 'Segoe UI', sans-serif; background: #0f172a; color: #e2e8f0; padding: 2rem; margin: 0; }
        .container { max-width: 800px; margin: 0 auto; background: #1e293b; padding: 2rem; border-radius: 12px; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.5); }
        h1 { color: #38bdf8; margin-top: 0; display: flex; align-items: center; gap: 10px; }
        .input-group { margin-bottom: 1.5rem; }
        label { display: block; font-size: 14px; font-weight: 600; color: #94a3b8; margin-bottom: 0.5rem; }
        input[type="text"], input[type="password"] { width: 100%; padding: 12px 16px; background: #0f172a; border: 1px solid #334155; border-radius: 8px; color: #e2e8f0; font-family: monospace; font-size: 14px; box-sizing: border-box; transition: border-color 0.2s; }
        input[type="text"]:focus, input[type="password"]:focus { outline: none; border-color: #38bdf8; }
        .btn { background: #38bdf8; color: #0f172a; font-weight: 700; padding: 12px 24px; border: none; border-radius: 8px; cursor: pointer; font-size: 16px; transition: background 0.2s; width: 100%; display: flex; justify-content: center; align-items: center; gap: 10px; }
        .btn:hover { background: #0284c7; }
        .btn:disabled { background: #334155; color: #94a3b8; cursor: not-allowed; }
        .logs-container { margin-top: 2rem; background: #000; border: 1px solid #334155; border-radius: 8px; padding: 1rem; height: 300px; overflow-y: auto; font-family: monospace; font-size: 13px; line-height: 1.6; }
        .log-entry { margin-bottom: 8px; padding-bottom: 8px; border-bottom: 1px solid #1e293b; }
        .log-entry:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
        .spinner { animation: spin 1s linear infinite; display: none; }
        @keyframes spin { 100% { transform: rotate(360deg); } }
        .local-info { margin-bottom: 2rem; padding: 1rem; background: #064e3b; border: 1px solid #047857; border-radius: 8px; color: #a7f3d0; font-size: 14px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.29 7 12 12 20.71 7"></polyline><line x1="12" y1="22" x2="12" y2="12"></line></svg>
            Data Sync
        </h1>
        
        <div class="local-info">
            <strong>🏠 Local Database:</strong> connected to <code><?= htmlspecialchars(DB_NAME) ?></code>
        </div>

        <div class="input-group">
            <label for="neon_url">Remote Database Connection URL (Neon.tech or Railway)</label>
            <input type="password" id="neon_url" value="postgresql://neondb_owner:npg_MOmuRVnI13wE@ep-solitary-dream-ao7p74qo-pooler.c-2.ap-southeast-1.aws.neon.tech/neondb?sslmode=require&channel_binding=require" placeholder="postgresql://user:password@hostname:5432/dbname" autocomplete="off">
            <div style="font-size: 12px; color: #64748b; margin-top: 8px;">
                Example: <code>postgresql://neondb_owner:password@ep-lively-tree.us-east-2.aws.neon.tech/neondb?sslmode=require</code>
            </div>
        </div>

        <button id="syncBtn" class="btn" onclick="startSync()">
            <svg class="spinner" id="spinner" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="2" x2="12" y2="6"></line><line x1="12" y1="18" x2="12" y2="22"></line><line x1="4.93" y1="4.93" x2="7.76" y2="7.76"></line><line x1="16.24" y1="16.24" x2="19.07" y2="19.07"></line><line x1="2" y1="12" x2="6" y2="12"></line><line x1="18" y1="12" x2="22" y2="12"></line><line x1="4.93" y1="19.07" x2="7.76" y2="16.24"></line><line x1="16.24" y1="7.76" x2="19.07" y2="4.93"></line></svg>
            <span id="btnText">Run Two-Way Sync</span>
        </button>

        <div class="logs-container" id="logsContainer">
            <div style="color: #64748b;">Ready to sync. Enter your connection string and click the button above.</div>
        </div>
    </div>

    <script>
        function appendLog(html) {
            const container = document.getElementById('logsContainer');
            const entry = document.createElement('div');
            entry.className = 'log-entry';
            entry.innerHTML = html;
            container.appendChild(entry);
            container.scrollTop = container.scrollHeight;
        }

        async function startSync() {
            const neonUrl = document.getElementById('neon_url').value.trim();
            if (!neonUrl) {
                alert("Please enter a valid remote connection URL.");
                return;
            }

            const btn = document.getElementById('syncBtn');
            const spinner = document.getElementById('spinner');
            const btnText = document.getElementById('btnText');
            const logsContainer = document.getElementById('logsContainer');

            // Reset UI
            btn.disabled = true;
            spinner.style.display = 'block';
            btnText.innerText = 'Syncing...';
            logsContainer.innerHTML = '';
            appendLog('⏳ Starting sync process...');

            try {
                const formData = new FormData();
                formData.append('action', 'sync');
                formData.append('neon_url', neonUrl);

                const response = await fetch('sync_neon.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    data.logs.forEach(log => appendLog(log));
                } else {
                    appendLog(`<span style="color: #f87171;">Error: ${data.error}</span>`);
                }
            } catch (err) {
                appendLog(`<span style="color: #f87171;">Network Error: ${err.message}</span>`);
            } finally {
                btn.disabled = false;
                spinner.style.display = 'none';
                btnText.innerText = 'Run Two-Way Sync';
            }
        }
    </script>
</body>
</html>
