<?php
// Usage: php log_change.php [auto|version] [type] [module] [title] [description]

require_once __DIR__ . '/../config.php';

// Argument parsing
$version_arg = $argv[1] ?? 'auto';
$type = $argv[2] ?? 'enhancement';
$module = $argv[3] ?? 'System';
$title = $argv[4] ?? 'System Update';
$desc = $argv[5] ?? 'Code changes applied.';

// Determine Version
$version = $version_arg;
if ($version === 'auto') {
    // Get last version
    // Check if table has 'created_at' for better sorting, otherwise use id
    // Assuming 'id' is auto-increment
    $stmt = $pdo->query("SELECT version FROM changelog ORDER BY id DESC LIMIT 1");
    $last_ver = $stmt->fetchColumn();

    if (!$last_ver) {
        $version = '1.0.0';
    } else {
        // Simple SemVer increment (Patch level)
        // Extract numbers
        if (preg_match('/^(\d+)\.(\d+)\.(\d+)$/', $last_ver, $matches)) {
            $major = $matches[1];
            $minor = $matches[2];
            $patch = $matches[3] + 1;
            $version = "$major.$minor.$patch";
        } else {
            // Fallback if version format is weird
            $version = $last_ver . '.1';
        }
    }
}

// Prevent Duplicate Entries for same title on same day
$check = $pdo->prepare("SELECT id FROM changelog WHERE title = ? AND change_date = CURRENT_DATE");
$check->execute([$title]);
if ($check->fetch()) {
    echo "Skipping duplicate entry: $title\n";
    exit(0);
}

try {
    $stmt = $pdo->prepare("INSERT INTO changelog (version, change_date, change_type, module, title, description) VALUES (?, CURRENT_DATE, ?, ?, ?, ?)");
    $stmt->execute([$version, $type, $module, $title, $desc]);
    echo "Success: Logged v$version - $title\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>