<?php
require 'db.php';

try {
    // Check if user_id already exists
    $stmt = $pdo->prepare("SHOW COLUMNS FROM system_logs LIKE 'user_id'");
    $stmt->execute();
    if ($stmt->fetch()) {
        echo "Columns already exist.<br>";
    } else {
        $sql = "ALTER TABLE system_logs 
                ADD COLUMN user_id INT DEFAULT NULL AFTER id, 
                ADD COLUMN action_type VARCHAR(50) DEFAULT 'Other' AFTER log_level, 
                ADD COLUMN resource_id VARCHAR(50) DEFAULT NULL AFTER action_type, 
                ADD COLUMN ip_address VARCHAR(45) DEFAULT NULL AFTER resource_id, 
                ADD CONSTRAINT fk_user_id FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL";
        $pdo->exec($sql);
        echo "Successfully added Audit Trail columns to system_logs.<br>";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
<br>
<a href="./">Back to Dashboard</a>