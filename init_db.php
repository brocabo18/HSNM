<?php
/**
 * init_db.php — Run once at container startup (called from start.sh)
 * Initializes the PostgreSQL schema using postgres_schema.sql.
 * Safe to run multiple times: all DDL uses IF NOT EXISTS, INSERTs use ON CONFLICT DO NOTHING.
 */

$dbUrl = getenv('DATABASE_URL');
if (!$dbUrl) {
    echo "[init_db] DATABASE_URL not set — skipping schema init.\n";
    exit(0);
}

$parts = parse_url($dbUrl);
$dsn   = sprintf(
    'pgsql:host=%s;port=%s;dbname=%s;connect_timeout=10',
    $parts['host'],
    $parts['port'] ?? 5432,
    ltrim($parts['path'], '/')
);

$retries = 5;
$pdo = null;
for ($i = 1; $i <= $retries; $i++) {
    try {
        $pdo = new PDO($dsn, $parts['user'], $parts['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        echo "[init_db] Connected to PostgreSQL.\n";
        break;
    } catch (PDOException $e) {
        echo "[init_db] Attempt $i/$retries failed: " . $e->getMessage() . "\n";
        if ($i < $retries) sleep(3);
    }
}

if (!$pdo) {
    echo "[init_db] Could not connect to database after $retries attempts. Skipping schema init.\n";
    exit(1);
}

$schemaFile = __DIR__ . '/postgres_schema.sql';
if (!file_exists($schemaFile)) {
    echo "[init_db] Schema file not found: $schemaFile\n";
    exit(1);
}

$sql = file_get_contents($schemaFile);

try {
    $pdo->exec($sql);
    echo "[init_db] Schema initialization complete.\n";
} catch (PDOException $e) {
    // Non-fatal: tables may already exist
    echo "[init_db] Schema warning: " . $e->getMessage() . "\n";
}

// Also run additional tables if they exist
$additionalFile = __DIR__ . '/database/additional_tables.sql';
if (file_exists($additionalFile)) {
    try {
        $pdo->exec(file_get_contents($additionalFile));
        echo "[init_db] Additional tables applied.\n";
    } catch (PDOException $e) {
        echo "[init_db] Additional tables warning: " . $e->getMessage() . "\n";
    }
}

echo "[init_db] Done.\n";
exit(0);
