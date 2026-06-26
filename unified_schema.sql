-- Unified HSNM Database Schema
-- Combines Router, Switch, and IP Address separate inventories

CREATE DATABASE IF NOT EXISTS unified_network_inventory CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE unified_network_inventory;

-- -----------------------------------------------------
-- Users Table (Unified Authentication)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100),
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100),
    role ENUM('admin', 'editor', 'viewer') DEFAULT 'viewer',
    is_active TINYINT(1) DEFAULT 1,
    last_login DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Default Admin (Password: admin123)
-- Hash generated from IP inventory default
INSERT INTO users (username, password, full_name, role) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'admin')
ON DUPLICATE KEY UPDATE username=username;

-- -----------------------------------------------------
-- Settings Table (Unified System Settings)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    description TEXT,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO settings (setting_key, setting_value, description) VALUES
('system_name', 'HSNM', 'System Name'),
('dark_mode', '1', 'Enable Dark Mode by Default');

-- -----------------------------------------------------
-- Audit Logs Table (Unified Logging)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action_type VARCHAR(50),
    details TEXT,
    resource_type VARCHAR(50),
    resource_id INT,
    ip_address VARCHAR(45),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);


-- -----------------------------------------------------
-- Module: Routers (from TABLE inventory)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS routers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    serial_number VARCHAR(50) NOT NULL,
    brand VARCHAR(100) NOT NULL, -- renamed from model
    location VARCHAR(100),
    ip_address VARCHAR(45),
    mac_address VARCHAR(17),
    lan_ip VARCHAR(45), -- new column
    ssid VARCHAR(100),
    wifi_password VARCHAR(100),
    admin_user VARCHAR(100),
    admin_password VARCHAR(100),
    status ENUM('Online', 'Offline', 'Warning', 'High Latency', 'Power Fail', 'Maintenance') DEFAULT 'Offline',
    uptime VARCHAR(50) DEFAULT '--',
    firmware_status ENUM('Up to Date', 'Update Available', 'Critical Update') DEFAULT 'Up to Date',
    remarks TEXT,
    last_seen DATETIME DEFAULT CURRENT_TIMESTAMP,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);


-- -----------------------------------------------------
-- Module: Switches (from TABLE switches)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS switches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    switch_id VARCHAR(50) NOT NULL UNIQUE,
    model VARCHAR(100) NOT NULL,
    manufacturer VARCHAR(100) NOT NULL,
    serial VARCHAR(100) NULL DEFAULT NULL UNIQUE,
    ip_address VARCHAR(45) NOT NULL, -- normalized column name from 'ip'
    mac_address VARCHAR(17) NOT NULL, -- normalized from 'mac'
    building_location VARCHAR(200) NOT NULL, -- renamed from 'location'
    floor VARCHAR(20), -- new column
    ports VARCHAR(50), -- renamed from 'port_count'
    port_details VARCHAR(200), -- normalized from 'ports_detail'
    ports_status VARCHAR(50), -- new column for port status
    status ENUM('Active', 'Maintenance', 'Inactive') DEFAULT 'Active',
    personnel VARCHAR(100),
    last_maintenance DATE,
    next_maintenance VARCHAR(50),
    remarks TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);


-- -----------------------------------------------------
-- Module: IP Address Management (from TABLE ip_inventory and subnets)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS subnets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    network VARCHAR(50) NOT NULL,
    cidr VARCHAR(20) NOT NULL,
    gateway VARCHAR(50),
    vlan_id INT,
    description TEXT,
    location VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);


CREATE TABLE IF NOT EXISTS ips (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(50) NOT NULL UNIQUE,
    mac_address VARCHAR(17),
    hostname VARCHAR(255),
    control_number VARCHAR(100),
    department VARCHAR(100),
    om_name VARCHAR(100),
    status ENUM('active', 'reserved', 'conflict', 'static', 'offline') DEFAULT 'offline',
    device_type VARCHAR(50),
    description TEXT,
    subnet_id INT,
    last_seen TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (subnet_id) REFERENCES subnets(id) ON DELETE SET NULL
);

-- -----------------------------------------------------
-- Module: Computer Inventory
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS computers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    department VARCHAR(100),
    end_user VARCHAR(100),
    mr_par VARCHAR(100),
    control_number VARCHAR(100),
    system_unit VARCHAR(100),
    system_unit_sn VARCHAR(100),
    monitor VARCHAR(100),
    monitor_sn VARCHAR(100),
    mouse VARCHAR(100),
    mouse_sn VARCHAR(100),
    keyboard VARCHAR(100),
    keyboard_sn VARCHAR(100),
    printer VARCHAR(100),
    printer_sn VARCHAR(100),
    scanner VARCHAR(100),
    scanner_sn VARCHAR(100),
    avr_ups VARCHAR(100),
    avr_ups_sn VARCHAR(100),
    processor VARCHAR(200),
    memory VARCHAR(100),
    storage VARCHAR(100),
    os VARCHAR(100),
    license ENUM('Y', 'N') DEFAULT 'N',
    microsoft_office VARCHAR(100),
    os_product_key VARCHAR(255),
    ip_address VARCHAR(45),
    mac_address VARCHAR(17),
    checked_by VARCHAR(100),
    encoded_by VARCHAR(100),
    remarks TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- -----------------------------------------------------
-- Module: PABX Directory
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS pabx_directory (
    id INT AUTO_INCREMENT PRIMARY KEY,
    local_number VARCHAR(50) NOT NULL UNIQUE,
    ip_address VARCHAR(45),
    department VARCHAR(100),
    building VARCHAR(100) NOT NULL,
    floor VARCHAR(10) NOT NULL,
    display_name VARCHAR(200) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_local_number (local_number),
    INDEX idx_building (building),
    INDEX idx_department (department),
    INDEX idx_ip_address (ip_address),
    INDEX idx_display_name (display_name),
    INDEX idx_floor (floor)
);

