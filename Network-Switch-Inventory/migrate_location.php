<?php
require_once 'config.php';

try {
    $pdo = getDBConnection();

    echo "Starting migration...<br>";

    // 1. Rename location to building_location
    // Check if building_location already exists to avoid errors
    $stmt = $pdo->query("SHOW COLUMNS FROM switches LIKE 'building_location'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE switches CHANGE COLUMN location building_location VARCHAR(200) NOT NULL");
        echo "Renamed 'location' to 'building_location'.<br>";
    } else {
        echo "Column 'building_location' already exists.<br>";
    }

    // 2. Add floor column
    $stmt = $pdo->query("SHOW COLUMNS FROM switches LIKE 'floor'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE switches ADD COLUMN floor VARCHAR(10) NOT NULL AFTER building_location");
        echo "Added 'floor' column.<br>";
    } else {
        echo "Column 'floor' already exists.<br>";
    }

    // 3. Update index
    try {
        $pdo->exec("DROP INDEX idx_location ON switches");
        echo "Dropped old index idx_location.<br>";
    } catch (Exception $e) {
        echo "Index idx_location might already be gone.<br>";
    }

    try {
        $pdo->exec("CREATE INDEX idx_building_location ON switches(building_location)");
        echo "Created index idx_building_location.<br>";
    } catch (Exception $e) {
        echo "Index idx_building_location might already exist.<br>";
    }

    try {
        $pdo->exec("CREATE INDEX idx_floor ON switches(floor)");
        echo "Created index idx_floor.<br>";
    } catch (Exception $e) {
        echo "Index idx_floor might already exist.<br>";
    }

    echo "Migration completed successfully!";

} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage();
}
?>