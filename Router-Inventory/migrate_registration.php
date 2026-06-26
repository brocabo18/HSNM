<?php
require 'db.php';

try {
    // Add email column if it doesn't exist
    $stmt = $pdo->prepare("SHOW COLUMNS FROM users LIKE 'email'");
    $stmt->execute();
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE users ADD COLUMN email VARCHAR(100) DEFAULT NULL AFTER username");
        echo "Added 'email' column.<br>";
    }

    // Add is_active column if it doesn't exist
    $stmt = $pdo->prepare("SHOW COLUMNS FROM users LIKE 'is_active'");
    $stmt->execute();
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE users ADD COLUMN is_active TINYINT(1) DEFAULT 1 AFTER role");
        echo "Added 'is_active' column.<br>";
    }

    echo "Migration successful.<br>";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
<br>
<a href="./">Back to Dashboard</a>