<?php
/**
 * HSNM Database Export Script
 * Run this ONCE locally to export your PostgreSQL database for Railway import.
 * Usage: php database/export.php
 * Or visit: http://localhost/HSNM/database/export.php (locally only)
 */

// Only allow CLI or localhost
$allowed = php_sapi_name() === 'cli' ||
    in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1', 'localhost']);

if (!$allowed) {
    http_response_code(403);
    die("Access denied. This script can only be run from localhost.");
}

require_once __DIR__ . '/../config.php';

$outputFile = __DIR__ . '/schema_export.sql';

// --- Build pg_dump command ---
// Try common pg_dump paths
$pgDumpPaths = [
    'pg_dump',
    'C:/Program Files/PostgreSQL/17/bin/pg_dump.exe',
    'C:/Program Files/PostgreSQL/16/bin/pg_dump.exe',
    'C:/Program Files/PostgreSQL/15/bin/pg_dump.exe',
    'C:/xampp/postgresql/bin/pg_dump.exe',
];

$pgDump = null;
foreach ($pgDumpPaths as $path) {
    $check = shell_exec('"' . $path . '" --version 2>&1');
    if ($check && strpos($check, 'pg_dump') !== false) {
        $pgDump = $path;
        break;
    }
}

if ($pgDump) {
    // Use pg_dump binary
    putenv("PGPASSWORD=" . DB_PASS);
    $cmd = sprintf(
        '"%s" -U %s -h %s -p %s -d %s --no-owner --no-acl --clean --if-exists --format=plain --file="%s" 2>&1',
        $pgDump,
        DB_USER,
        DB_HOST,
        DB_PORT,
        DB_NAME,
        $outputFile
    );
    $output = shell_exec($cmd);
    if (file_exists($outputFile) && filesize($outputFile) > 100) {
        echo "✅ Export SUCCESS via pg_dump\n";
        echo "📄 File: $outputFile\n";
        echo "📦 Size: " . number_format(filesize($outputFile)) . " bytes\n";
    } else {
        echo "❌ pg_dump failed. Output: $output\n";
        echo "Falling back to PHP PDO export...\n";
        exportViaPdo($pdo, $outputFile);
    }
} else {
    echo "ℹ️  pg_dump not found in PATH. Using PHP PDO export...\n";
    exportViaPdo($pdo, $outputFile);
}

function exportViaPdo($pdo, $outputFile) {
    $sql = "";
    $sql .= "-- HSNM Database Export\n";
    $sql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
    $sql .= "-- Database: unified_network_inventory\n\n";
    $sql .= "SET client_encoding = 'UTF8';\n";
    $sql .= "SET standard_conforming_strings = on;\n\n";

    // Get all tables in correct order (handle FK dependencies)
    $tableOrder = [
        'users', 'settings', 'subnets', 'ips', 'routers', 'switches',
        'computers', 'pabx_directory', 'printers', 'audit_logs', 'changelog',
        'office', 'ics', 'ihoms_links', 'firewall_rules'
    ];

    // Also discover any tables not in our list
    $stmt = $pdo->query("SELECT tablename FROM pg_tables WHERE schemaname = 'public' ORDER BY tablename");
    $allTables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Merge — known order first, then any extras
    $tables = array_unique(array_merge($tableOrder, $allTables));

    foreach ($tables as $table) {
        if (!in_array($table, $allTables)) continue;

        echo "  Exporting table: $table\n";

        // Get CREATE TABLE statement via pg_catalog
        try {
            $stmt = $pdo->query("SELECT column_name, data_type, character_maximum_length,
                column_default, is_nullable
                FROM information_schema.columns
                WHERE table_name = '$table' AND table_schema = 'public'
                ORDER BY ordinal_position");
            $columns = $stmt->fetchAll();

            if (empty($columns)) continue;

            // Get row data
            $stmt = $pdo->query("SELECT * FROM \"$table\"");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($rows)) {
                $sql .= "\n-- Table: $table (" . count($rows) . " rows)\n";
                $sql .= "TRUNCATE TABLE \"$table\" CASCADE;\n";
                foreach ($rows as $row) {
                    $cols = implode('", "', array_keys($row));
                    $vals = array_map(function($v) use ($pdo) {
                        if ($v === null) return 'NULL';
                        if (is_numeric($v)) return $v;
                        // Escape single quotes
                        return "'" . str_replace("'", "''", $v) . "'";
                    }, array_values($row));
                    $sql .= "INSERT INTO \"$table\" (\"$cols\") VALUES (" . implode(', ', $vals) . ");\n";
                }
            }
        } catch (Exception $e) {
            $sql .= "-- Skipped table $table: " . $e->getMessage() . "\n";
        }
    }

    file_put_contents($outputFile, $sql);
    $size = filesize($outputFile);

    if ($size > 0) {
        echo "✅ Export SUCCESS via PDO\n";
        echo "📄 File: $outputFile\n";
        echo "📦 Size: " . number_format($size) . " bytes\n";
        echo "\n⚠️  NOTE: This PDO export contains DATA only (no schema DDL).\n";
        echo "You will need to run the schema SQL on Railway first, then import this data.\n";
    } else {
        echo "❌ Export failed.\n";
    }
}
?>
