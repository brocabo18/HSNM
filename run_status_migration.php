<?php
require_once 'config.php';
require_once 'includes/header.php';
?>
<div class="p-8">
<h2 class="text-2xl font-bold text-slate-900 dark:text-white mb-4">Database Migration: Status Column & Satellite Tables</h2>
<?php
$sql_file = __DIR__ . '/database/add_status_and_satellite_tables.sql';
$sql = file_get_contents($sql_file);
$statements = array_filter(array_map('trim', explode(';', $sql)), fn($s) => !empty($s) && !str_starts_with(ltrim($s), '--'));
$errors = [];
$success_count = 0;
foreach ($statements as $stmt) {
    $stmt = trim($stmt);
    if (empty($stmt)) continue;
    try {
        $pdo->exec($stmt);
        $success_count++;
    } catch (PDOException $e) {
        $errors[] = htmlspecialchars($e->getMessage()) . ' | SQL: ' . htmlspecialchars(substr($stmt, 0, 100));
    }
}
echo "<p class='text-emerald-600 font-bold'>$success_count statements executed successfully.</p>";
if ($errors) {
    echo "<p class='text-red-500 font-bold'>Errors:</p><ul class='text-red-500 text-sm'>";
    foreach ($errors as $e) echo "<li>$e</li>";
    echo "</ul>";
} else {
    echo "<p class='text-emerald-600'>Migration complete! You can now delete this file.</p>";
}
?>
</div>
<?php require_once 'includes/footer.php'; ?>
