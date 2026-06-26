<?php
require_once 'config.php';

echo "<h1>Database Diagnostics</h1>";

try {
    $pdo = getDBConnection();
    echo "<p style='color:green'>Database connection successful.</p>";

    // Check if users table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
    if ($stmt->rowCount() > 0) {
        echo "<p style='color:green'>Users table exists.</p>";

        // Check for admin user
        $stmt = $pdo->query("SELECT * FROM users WHERE username = 'admin'");
        $user = $stmt->fetch();

        if ($user) {
            echo "<p style='color:green'>Admin user found.</p>";
            // Reset password to be sure
            $newHash = password_hash('password', PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = :pass WHERE username = 'admin'");
            $stmt->execute([':pass' => $newHash]);
            echo "<p style='color:blue'>Admin password reset to 'password'.</p>";
        } else {
            echo "<p style='color:red'>Admin user NOT found. Creating...</p>";
            $pass = password_hash('password', PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, email, role) VALUES ('admin', :pass, 'Administrator', 'admin@example.com', 'Admin')");
            $stmt->execute([':pass' => $pass]);
            echo "<p style='color:green'>Admin user created.</p>";
        }
    } else {
        echo "<p style='color:red'>Users table MISSING. Attempting to create...</p>";

        $sql = file_get_contents('schema_auth.sql');
        // Execute multiple statements
        $pdo->exec($sql);
        echo "<p style='color:green'>Schema imported successfully.</p>";
    }

    // Check sessions table
    $stmt = $pdo->query("SHOW TABLES LIKE 'user_sessions'");
    if ($stmt->rowCount() > 0) {
        echo "<p style='color:green'>User Sessions table exists.</p>";
    } else {
        echo "<p style='color:red'>User Sessions table MISSING. (Should have been created by schema import)</p>";
    }

} catch (Exception $e) {
    echo "<p style='color:red'>Error: " . $e->getMessage() . "</p>";
}
?>