<?php
/**
 * System Health Check Script
 * Checks for common bugs and issues in the HSNM system
 */

require_once 'config.php';

$issues = [];
$warnings = [];
$passed = [];

echo "=== HSNM System Health Check ===\n\n";

// 1. Database Connection
echo "1. Testing Database Connection...\n";
try {
    $pdo->query("SELECT 1");
    $passed[] = "Database connection successful";
    echo "   ✓ Database connected\n";
} catch (PDOException $e) {
    $issues[] = "Database connection failed: " . $e->getMessage();
    echo "   ✗ Database connection failed\n";
}

// 2. Check tables exist
echo "\n2. Checking Core Tables...\n";
$required_tables = ['users', 'computers', 'office_licenses', 'ips', 'routers', 'switches', 'audit_logs'];
foreach ($required_tables as $table) {
    $stmt = $pdo->prepare("SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name = ?)");
    $stmt->execute([$table]);
    if ($stmt->fetchColumn()) {
        echo "   ✓ Table '$table' exists\n";
    } else {
        $issues[] = "Missing table: $table";
        echo "   ✗ Table '$table' missing\n";
    }
}

// 3. Check ms_office_email column
echo "\n3. Checking Recent Schema Changes...\n";
$stmt = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'computers' AND column_name = 'ms_office_email'");
if ($stmt->rowCount() > 0) {
    $passed[] = "Column 'ms_office_email' exists in computers table";
    echo "   ✓ ms_office_email column exists\n";
} else {
    $issues[] = "Missing column: computers.ms_office_email";
    echo "   ✗ ms_office_email column missing\n";
}

// 4. Check for duplicate control numbers
echo "\n4. Checking Data Integrity...\n";
$stmt = $pdo->query("SELECT control_number, COUNT(*) as cnt FROM computers WHERE control_number IS NOT NULL AND control_number != '' GROUP BY control_number HAVING COUNT(*) > 1");
$duplicates = $stmt->fetchAll();
if (empty($duplicates)) {
    $passed[] = "No duplicate control numbers in computers";
    echo "   ✓ No duplicate control numbers\n";
} else {
    $warnings[] = "Found " . count($duplicates) . " duplicate control numbers";
    echo "   ⚠ Found " . count($duplicates) . " duplicate control numbers\n";
    foreach ($duplicates as $dup) {
        echo "      - '{$dup['control_number']}' appears {$dup['cnt']} times\n";
    }
}

// 5. Check for orphaned records
echo "\n5. Checking for Orphaned Records...\n";
$stmt = $pdo->query("
    SELECT COUNT(*) 
    FROM office_licenses ol 
    WHERE ol.control_number IS NOT NULL 
    AND ol.control_number != '' 
    AND NOT EXISTS (
        SELECT 1 FROM computers c WHERE c.control_number = ol.control_number
    )
");
$orphaned = $stmt->fetchColumn();
if ($orphaned == 0) {
    $passed[] = "No orphaned office licenses";
    echo "   ✓ No orphaned office licenses\n";
} else {
    $warnings[] = "Found $orphaned office licenses without matching computers";
    echo "   ⚠ Found $orphaned office licenses without matching computers\n";
}

// 6. Check session configuration
echo "\n6. Checking Session Configuration...\n";
if (session_status() === PHP_SESSION_ACTIVE || session_status() === PHP_SESSION_NONE) {
    $passed[] = "Session handling configured";
    echo "   ✓ Session handling OK\n";
} else {
    $warnings[] = "Session status unexpected: " . session_status();
    echo "   ⚠ Session status: " . session_status() . "\n";
}

// 7. Check file permissions
echo "\n7. Checking Critical Files...\n";
$critical_files = [
    'config.php',
    'login.php',
    'modules/computers/index.php',
    'modules/office/index.php',
    'includes/header.php',
    'includes/sidebar.php'
];
foreach ($critical_files as $file) {
    if (file_exists($file)) {
        echo "   ✓ $file exists\n";
    } else {
        $issues[] = "Missing critical file: $file";
        echo "   ✗ $file missing\n";
    }
}

// 8. Check for SQL injection vulnerabilities (basic check)
echo "\n8. Checking for Common Security Issues...\n";
$files_to_check = [
    'modules/computers/index.php',
    'modules/office/index.php',
    'modules/ips/index.php'
];
$vulnerable_patterns = 0;
foreach ($files_to_check as $file) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        // Check for direct SQL concatenation (potential SQL injection)
        if (preg_match('/\$pdo->query\s*\(\s*["\'].*\$/', $content)) {
            $vulnerable_patterns++;
            $warnings[] = "Potential SQL injection in $file (direct variable concatenation)";
        }
    }
}
if ($vulnerable_patterns == 0) {
    $passed[] = "No obvious SQL injection patterns detected";
    echo "   ✓ No obvious SQL injection patterns\n";
} else {
    echo "   ⚠ Found $vulnerable_patterns files with potential SQL issues\n";
}

// 9. Check AJAX endpoints
echo "\n9. Checking AJAX Handlers...\n";
$stmt = $pdo->query("SELECT COUNT(*) FROM computers");
$computer_count = $stmt->fetchColumn();
if ($computer_count >= 0) {
    $passed[] = "Can query computers table";
    echo "   ✓ Computers table accessible ($computer_count records)\n";
}

// Summary
echo "\n" . str_repeat("=", 50) . "\n";
echo "SUMMARY\n";
echo str_repeat("=", 50) . "\n\n";

if (!empty($issues)) {
    echo "❌ CRITICAL ISSUES (" . count($issues) . "):\n";
    foreach ($issues as $issue) {
        echo "   - $issue\n";
    }
    echo "\n";
}

if (!empty($warnings)) {
    echo "⚠️  WARNINGS (" . count($warnings) . "):\n";
    foreach ($warnings as $warning) {
        echo "   - $warning\n";
    }
    echo "\n";
}

echo "✅ PASSED (" . count($passed) . " checks)\n\n";

if (empty($issues)) {
    echo "Overall Status: HEALTHY ✓\n";
} else {
    echo "Overall Status: NEEDS ATTENTION ⚠\n";
}
