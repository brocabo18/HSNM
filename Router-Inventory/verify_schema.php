<?php
require_once 'db.php';

try {
    echo "<h1>Database Schema Verification</h1>";

    // Check users table columns
    $stmt = $pdo->prepare("SHOW COLUMNS FROM users");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo "<h3>Columns in 'users' table:</h3>";
    echo "<ul>";
    foreach ($columns as $col) {
        echo "<li>" . htmlspecialchars($col) . "</li>";
    }
    echo "</ul>";

    if (in_array('username', $columns)) {
        echo "<h2 style='color: green;'>✅ Migration Successful: 'username' column exists.</h2>";
    } else {
        echo "<h2 style='color: red;'>❌ Migration Incomplete: 'username' column MISSING.</h2>";
        echo "<p>Please run the <a href='migrate_username.php'>Migration Script</a> again.</p>";
    }

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>