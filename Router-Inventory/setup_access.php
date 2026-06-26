<?php
require_once 'db.php';

try {
    // Create users table
    $sql = "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        username VARCHAR(100) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        role ENUM('Admin', 'Editor', 'Viewer') DEFAULT 'Viewer',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $pdo->exec($sql);
    echo "<div style='color: green; font-family: sans-serif; padding: 20px; border: 1px solid green; background: #e0f8e0; border-radius: 5px; margin-bottom: 20px;'>
            <strong>Success:</strong> Table 'users' created successfully.
          </div>";

    // check if admin exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
    $stmt->execute(['admin']);

    if ($stmt->fetchColumn() == 0) {
        // Seed Admin User
        // Password: password
        $password = password_hash('password', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (name, username, password, role) VALUES (?, ?, ?, ?)");
        $stmt->execute(['System Admin', 'admin', $password, 'Admin']);
        echo "<div style='color: green; font-family: sans-serif; padding: 20px; border: 1px solid green; background: #e0f8e0; border-radius: 5px;'>
                <strong>Success:</strong> Admin user (admin / password) created.
              </div>";
    } else {
        echo "<div style='color: blue; font-family: sans-serif; padding: 20px; border: 1px solid blue; background: #e0e0f8; border-radius: 5px;'>
                <strong>Info:</strong> Admin user already exists.
              </div>";
    }

} catch (PDOException $e) {
    echo "<div style='color: red; font-family: sans-serif; padding: 20px; border: 1px solid red; background: #f8e0e0; border-radius: 5px;'>
            <strong>Error:</strong> " . $e->getMessage() . "
          </div>";
}
?>
<div style="margin-top: 20px; font-family: sans-serif;">
    <p>Database setup complete. You must LOGOUT to clear your old session and login with the new database credentials.
    </p>
    <a href="logout"
        style="background: #137fec; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; font-weight: bold;">Logout
        & Login Again</a>
</div>