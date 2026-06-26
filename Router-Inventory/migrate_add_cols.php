<?php
require 'db.php';

try {
    // Add columns if they don't exist
    $sql = "ALTER TABLE inventory 
            ADD COLUMN ssid VARCHAR(255) DEFAULT NULL AFTER ip_address,
            ADD COLUMN wifi_password VARCHAR(255) DEFAULT NULL AFTER ssid,
            ADD COLUMN admin_user VARCHAR(255) DEFAULT 'admin' AFTER wifi_password,
            ADD COLUMN admin_password VARCHAR(255) DEFAULT NULL AFTER admin_user";

    $pdo->exec($sql);
    echo "Successfully added new columns (ssid, wifi_password, admin_user, admin_password) to inventory table.";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "Columns already exist in the table.";
    } else {
        echo "Error: " . $e->getMessage();
    }
}
?>