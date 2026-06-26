<?php
require_once 'config.php';

try {
    $pdo = getDBConnection();

    echo "Starting migration...\n";

    // Create settings table
    $sql = "CREATE TABLE IF NOT EXISTS settings (
        id SERIAL PRIMARY KEY,
        setting_key VARCHAR(50) UNIQUE NOT NULL,
        setting_value TEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $pdo->exec($sql);
    echo "Settings table created or already exists.\n";

    // Insert default settings
    $settings = [
        ['portal_name', 'Network Switch Inventory'],
        ['portal_subtitle', 'IHOMS'],
        ['theme_color', '#135bec'],
        ['maintenance_interval', '180']
    ];
    $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON CONFLICT (setting_key) DO NOTHING");
    foreach ($settings as $s) {
        $stmt->execute($s);
    }
    echo "Default settings inserted.\n";

    // Update users table
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS is_active BOOLEAN DEFAULT TRUE");
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
    echo "Users table updated.\n";

    echo "Migration completed successfully.\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
