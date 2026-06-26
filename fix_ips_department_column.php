<?php
/**
 * Migration Script: Add 'department' column to 'ips' table if missing
 * Usage: Run this file once via browser or CLI: php fix_ips_department_column.php
 */

require_once 'config.php';

try {
    echo "Checking if 'department' column exists in 'ips' table...\n";

    // Check if column exists
    $check = $pdo->query("
        SELECT column_name 
        FROM information_schema.columns 
        WHERE table_name='ips' AND column_name='department'
    ")->fetch();

    if ($check) {
        echo "✓ Column 'department' already exists in 'ips' table. No action needed.\n";
    } else {
        echo "✗ Column 'department' NOT found. Adding it now...\n";

        // Add the column
        $pdo->exec("ALTER TABLE ips ADD COLUMN department VARCHAR(100)");

        echo "✓ Successfully added 'department' column to 'ips' table.\n";
    }

    // Also check for 'om_name' column while we're at it
    echo "\nChecking if 'om_name' column exists in 'ips' table...\n";

    $check_om = $pdo->query("
        SELECT column_name 
        FROM information_schema.columns 
        WHERE table_name='ips' AND column_name='om_name'
    ")->fetch();

    if ($check_om) {
        echo "✓ Column 'om_name' already exists in 'ips' table.\n";
    } else {
        echo "✗ Column 'om_name' NOT found. Adding it now...\n";

        // Add the column
        $pdo->exec("ALTER TABLE ips ADD COLUMN om_name VARCHAR(100)");

        echo "✓ Successfully added 'om_name' column to 'ips' table.\n";
    }

    echo "\n✅ Migration completed successfully!\n";

} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>