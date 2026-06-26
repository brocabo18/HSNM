<?php
// Adjust path to config based on location
if (file_exists('../../config.php')) {
    require_once '../../config.php';
} else {
    require_once 'config.php'; // Fallback if moved
}

// Ensure only admin or CLI can run this
if (php_sapi_name() !== 'cli' && (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin')) {
    die("Access denied. Admin privileges required.");
}

echo "=== HSNM Computer-Office Sync Backfill Tool ===\n";
echo "Scanning for computers with MS Office but no license entry...\n\n";

try {
    // 1. Get all computers with MS Office
    $stmt = $pdo->query("SELECT id, control_number, department, microsoft_office 
                         FROM computers 
                         WHERE microsoft_office IS NOT NULL 
                         AND microsoft_office != ''");
    $computers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Found " . count($computers) . " computers with MS Office listed.\n";

    $added = 0;
    $skipped = 0;

    // Prepare statements outside loop for efficiency
    $check_stmt = $pdo->prepare("SELECT id FROM office_licenses WHERE control_number = ? LIMIT 1");
    $insert_stmt = $pdo->prepare("INSERT INTO office_licenses 
        (control_number, department, ms_office_version, product_key, remarks) 
        VALUES (?, ?, ?, ?, ?)");

    foreach ($computers as $comp) {
        $control_no = $comp['control_number'];

        // 2. Check if license already exists
        $check_stmt->execute([$control_no]);

        if ($check_stmt->rowCount() > 0) {
            $skipped++;
            continue;
        }

        // 3. Insert missing license
        try {
            $insert_stmt->execute([
                $control_no,
                $comp['department'],
                $comp['microsoft_office'],
                '', // Leave blank as requested
                "Auto-synced (Backfill) from Computer ID: " . $comp['id']
            ]);

            echo " [+] Added license for Control #: $control_no ({$comp['microsoft_office']})\n";
            $added++;

        } catch (Exception $e) {
            echo " [!] Failed to add $control_no: " . $e->getMessage() . "\n";
        }
    }

    echo "\n=== Sync Complete ===\n";
    echo "Added: $added\n";
    echo "Skipped (Already Existed): $skipped\n";

} catch (PDOException $e) {
    echo "Database Error: " . $e->getMessage() . "\n";
}
?>