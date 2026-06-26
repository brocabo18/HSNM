<?php
require_once 'db.php';

try {
    // Create users table
    $sql = "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(100) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        role ENUM('Admin', 'Editor', 'Viewer') DEFAULT 'Viewer',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $pdo->exec($sql);
    echo "Table 'users' created successfully.<br>";

    // check if admin exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
    $stmt->execute(['admin@company.com']);

    if ($stmt->fetchColumn() == 0) {
        // Seed Admin User
        // Password: password
        $password = password_hash('password', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
        $stmt->execute(['System Admin', 'admin@company.com', $password, 'Admin']);
        echo "Admin user seeded successfully.<br>";
    } else {
        echo "Admin user already exists.<br>";
    }

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>