<?php
require 'db.php';

try {
    // 1. Truncate Tables
    $pdo->exec("TRUNCATE TABLE inventory");
    $pdo->exec("TRUNCATE TABLE system_logs");
    echo "Tables truncated.\n";

    // 2. Insert Hospital Inventory Data
    // Note: ssid, wifi_password etc are kept null for simplicity or can be filled.
    // Assuming columns: serial_number, model, location, ip_address, ssid, wifi_password, admin_user, admin_password, status, uptime, region, firmware_status, last_seen
    $inventory_sql = "INSERT INTO inventory (serial_number, model, location, ip_address, ssid, wifi_password, admin_user, admin_password, status, uptime, region, firmware_status, last_seen) VALUES
    ('HOSP-ER-001', 'Cisco Catalyst 9300', 'Emergency Room - Zone A', '10.20.1.5', 'ER-Secure-WiFi', 'LifeSaver!2024', 'admin', 'secureErPass', 'Online', '45d 12h 00m', 'East Wing', 'Up to Date', NOW()),
    ('HOSP-ICU-102', 'Cisco Meraki MR46', 'ICU - Bed 4', '10.20.2.10', 'Patient-Guest', 'GuestAccess24', 'admin', 'icuPass99', 'Online', '12d 04h 22m', 'West Wing', 'Up to Date', NOW()),
    ('HOSP-OR-301', 'Juniper EX3400', 'Operating Room 3', '10.20.5.21', 'OR-Sterile-Net', 'SurgeryNet#1', 'admin', 'OrAdmin!', 'Online', '120d 01h', 'North Wing', 'Up to Date', NOW()),
    ('HOSP-CAF-005', 'Aruba AP-505', 'Cafeteria Main', '10.20.10.55', 'Guest-Wifi', 'CoffeeBreak', 'admin', 'cafeAdmin', 'Offline', '--', 'South Wing', 'Update Available', NOW() - INTERVAL '2 hour'),
    ('HOSP-LAB-202', 'Fortinet FortiGate 60F', 'Pathology Lab', '10.20.4.88', 'Lab-Secure', 'BioHazard@1', 'admin', 'LabSecure!', 'Warning', '02d 05h', 'East Wing', 'Critical Update', NOW()),
    ('HOSP-NS-101', 'Ubiquiti UniFi Pro', 'Nurses Station - Floor 2', '10.20.3.15', 'Staff-Only', 'NurseStation1', 'admin', 'NursePass', 'Online', '15d 10h', 'West Wing', 'Up to Date', NOW()),
    ('HOSP-PHARM-01', 'Cisco ISR 4000', 'Pharmacy Storage', '10.20.6.99', 'Meds-Track-Net', 'RxSecure2024', 'admin', 'RxAdmin', 'Online', '300d 00h', 'Central Core', 'Up to Date', NOW()),
    ('HOSP-ADM-004', 'HP Aruba 2930F', 'Administration Office', '10.20.0.12', 'Admin-Corp', 'PapersPlease', 'admin', 'BossMode', 'Online', '10d 02h', 'Admin Block', 'Up to Date', NOW())";

    $pdo->exec($inventory_sql);
    echo "Hospital inventory inserted.\n";

    // 3. Insert Hospital System Logs
    $logs_sql = "INSERT INTO system_logs (title, description, log_level, created_at) VALUES
    ('MedCart Connection Lost', 'Telemetry signal lost for Cart-4 in Room 202.', 'Warning', NOW() - INTERVAL '15 minute'),
    ('ER Network Backup Validated', 'Weekly failover test completed successfully.', 'Success', NOW() - INTERVAL '2 hour'),
    ('Pharmacy Firewall Alert', 'Blocked unauthorized access attempt from internal IP.', 'Critical', NOW() - INTERVAL '5 hour'),
    ('Guest WiFi Threshold', 'High client density detected in Cafeteria.', 'Info', NOW() - INTERVAL '1 day'))";

    $pdo->exec($logs_sql);
    echo "Hospital logs inserted.\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>