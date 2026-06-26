-- Create Database
CREATE DATABASE IF NOT EXISTS netops_pro;
USE netops_pro;

-- Inventory Table
CREATE TABLE IF NOT EXISTS inventory (
    id INT AUTO_INCREMENT PRIMARY KEY,
    serial_number VARCHAR(50) NOT NULL,
    model VARCHAR(100) NOT NULL,
    location VARCHAR(100),
    ip_address VARCHAR(45),
    ssid VARCHAR(100),
    wifi_password VARCHAR(100),
    admin_user VARCHAR(100),
    admin_password VARCHAR(100),
    status ENUM('Online', 'Offline', 'Warning', 'High Latency', 'Power Fail', 'Maintenance') DEFAULT 'Offline',
    uptime VARCHAR(50) DEFAULT '--',
    last_seen DATETIME DEFAULT CURRENT_TIMESTAMP,
    region VARCHAR(50) DEFAULT 'North America',
    firmware_status ENUM('Up to Date', 'Update Available', 'Critical Update') DEFAULT 'Up to Date'
);

-- System Logs / Audit Trail Table
CREATE TABLE IF NOT EXISTS system_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    title VARCHAR(100) NOT NULL,
    description TEXT,
    log_level ENUM('Info', 'Warning', 'Critical', 'Success') DEFAULT 'Info',
    action_type VARCHAR(50) DEFAULT 'Other',
    resource_id VARCHAR(50) DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Insert Seed Data for Inventory
-- Insert Seed Data for Inventory
INSERT INTO inventory (serial_number, model, location, ip_address, ssid, wifi_password, admin_user, admin_password, status, uptime, region, firmware_status, last_seen) VALUES
('HOSP-ER-001', 'Cisco Catalyst 9300', 'Emergency Room - Zone A', '10.20.1.5', 'ER-Secure-WiFi', 'LifeSaver!2024', 'admin', 'secureErPass', 'Online', '45d 12h 00m', 'East Wing', 'Up to Date', NOW()),
('HOSP-ICU-102', 'Cisco Meraki MR46', 'ICU - Bed 4', '10.20.2.10', 'Patient-Guest', 'GuestAccess24', 'admin', 'icuPass99', 'Online', '12d 04h 22m', 'West Wing', 'Up to Date', NOW()),
('HOSP-OR-301', 'Juniper EX3400', 'Operating Room 3', '10.20.5.21', 'OR-Sterile-Net', 'SurgeryNet#1', 'admin', 'OrAdmin!', 'Online', '120d 01h', 'North Wing', 'Up to Date', NOW()),
('HOSP-CAF-005', 'Aruba AP-505', 'Cafeteria Main', '10.20.10.55', 'Guest-Wifi', 'CoffeeBreak', 'admin', 'cafeAdmin', 'Offline', '--', 'South Wing', 'Update Available', DATE_SUB(NOW(), INTERVAL 2 HOUR)),
('HOSP-LAB-202', 'Fortinet FortiGate 60F', 'Pathology Lab', '10.20.4.88', 'Lab-Secure', 'BioHazard@1', 'admin', 'LabSecure!', 'Warning', '02d 05h', 'East Wing', 'Critical Update', NOW()),
('HOSP-NS-101', 'Ubiquiti UniFi Pro', 'Nurses Station - Floor 2', '10.20.3.15', 'Staff-Only', 'NurseStation1', 'admin', 'NursePass', 'Online', '15d 10h', 'West Wing', 'Up to Date', NOW()),
('HOSP-PHARM-01', 'Cisco ISR 4000', 'Pharmacy Storage', '10.20.6.99', 'Meds-Track-Net', 'RxSecure2024', 'admin', 'RxAdmin', 'Online', '300d 00h', 'Central Core', 'Up to Date', NOW()),
('HOSP-ADM-004', 'HP Aruba 2930F', 'Administration Office', '10.20.0.12', 'Admin-Corp', 'PapersPlease', 'admin', 'BossMode', 'Online', '10d 02h', 'Admin Block', 'Up to Date', NOW());

-- Insert Seed Data for Logs
INSERT INTO system_logs (title, description, log_level, created_at) VALUES
('MedCart Connection Lost', 'Telemetry signal lost for Cart-4 in Room 202.', 'Warning', DATE_SUB(NOW(), INTERVAL 15 MINUTE)),
('ER Network Backup Validated', 'Weekly failover test completed successfully.', 'Success', DATE_SUB(NOW(), INTERVAL 2 HOUR)),
('Pharmacy Firewall Alert', 'Blocked unauthorized access attempt from internal IP.', 'Critical', DATE_SUB(NOW(), INTERVAL 5 HOUR)),
('Guest WiFi Threshold', 'High client density detected in Cafeteria.', 'Info', DATE_SUB(NOW(), INTERVAL 1 DAY));
