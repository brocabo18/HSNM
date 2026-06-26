<?php
require_once 'db.php';

try {
    // Check if column 'username' already exists
    $stmt = $pdo->prepare("SHOW COLUMNS FROM users LIKE 'username'");
    $stmt->execute();
    if ($stmt->fetch()) {
        echo "Column 'username' already exists.<br>";
    } else {
        // Rename email to username
        $sql = "ALTER TABLE users CHANGE email username VARCHAR(100) NOT NULL UNIQUE";
        $pdo->exec($sql);
        echo "Successfully renamed 'email' column to 'username'.<br>";

        // Update admin default
        $stmt = $pdo->prepare("UPDATE users SET username = 'admin' WHERE username = 'admin@company.com'");
        $stmt->execute();
        echo "Updated default admin username to 'admin'.<br>";
    }

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
<div style="margin-top: 20px; font-family: sans-serif;">
    <p>Migration complete. <a href="login">Go to Login</a></p>
</div>