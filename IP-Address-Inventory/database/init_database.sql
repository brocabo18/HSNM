-- IP Inventory Management System Database Schema
-- Created: 2026-01-13

-- Create database
CREATE DATABASE IF NOT EXISTS ip_inventory CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ip_inventory;

-- Users table for authentication
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('admin', 'user', 'viewer') DEFAULT 'user',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    INDEX idx_username (username),
    INDEX idx_email (email),
    INDEX idx_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Subnets table for network organization
CREATE TABLE IF NOT EXISTS subnets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    network VARCHAR(50) NOT NULL,
    cidr VARCHAR(20) NOT NULL,
    gateway VARCHAR(50),
    vlan_id INT,
    description TEXT,
    total_ips INT DEFAULT 0,
    used_ips INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_network (network),
    INDEX idx_vlan (vlan_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- IP Inventory table for device tracking
CREATE TABLE IF NOT EXISTS ip_inventory (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(50) NOT NULL,
    hostname VARCHAR(255),
    mac_address VARCHAR(17),
    status ENUM('active', 'reserved', 'conflict', 'static', 'offline') DEFAULT 'offline',
    device_type VARCHAR(50),
    location VARCHAR(100),
    vlan_id INT,
    subnet_id INT,
    notes TEXT,
    last_seen TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT,
    UNIQUE KEY unique_ip (ip_address),
    INDEX idx_ip (ip_address),
    INDEX idx_mac (mac_address),
    INDEX idx_status (status),
    INDEX idx_hostname (hostname),
    INDEX idx_subnet (subnet_id),
    FOREIGN KEY (subnet_id) REFERENCES subnets(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Audit logs table for tracking all system changes
CREATE TABLE IF NOT EXISTS audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    username VARCHAR(50),
    action VARCHAR(50) NOT NULL,
    entity_type VARCHAR(50),
    entity_id INT,
    description TEXT,
    ip_address VARCHAR(50),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_entity (entity_type, entity_id),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Settings table for system configuration
CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by INT,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default admin user (password: admin123)
INSERT INTO users (username, email, password, full_name, role, is_active) 
VALUES (
    'admin',
    'admin@ipinventory.local',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'System Administrator',
    'admin',
    1
) ON DUPLICATE KEY UPDATE username=username;

-- Insert default subnet (Main Network only)
INSERT INTO subnets (name, network, cidr, gateway, vlan_id, description, total_ips) 
VALUES 
    ('Main Network', '192.163.10.0', '192.163.10.0/21', '192.163.10.1', 1, 'Primary network subnet', 2048)
ON DUPLICATE KEY UPDATE name=name;

-- Insert sample IP inventory data
INSERT INTO ip_inventory (ip_address, hostname, mac_address, status, device_type, location, vlan_id, subnet_id, last_seen, created_by) 
VALUES 
    ('192.163.10.1', 'gateway-primary.local', '00:1A:2B:3C:4D:5E', 'active', 'Router', 'Server Room', 1, 1, NOW(), 1),
    ('192.163.10.10', 'file-server-01', 'A1:B2:C3:D4:E5:F6', 'reserved', 'Server', 'Server Room', 1, 1, DATE_SUB(NOW(), INTERVAL 2 MINUTE), 1),
    ('192.163.10.25', 'DESKTOP-8X912', '00:14:22:01:23:45', 'conflict', 'Desktop', 'Office Floor 3', 1, 1, DATE_SUB(NOW(), INTERVAL 10 SECOND), 1),
    ('192.163.10.55', 'printer-marketing', 'B4:A3:D1:C2:F5:E6', 'static', 'Printer', 'Marketing Dept', 1, 1, DATE_SUB(NOW(), INTERVAL 1 HOUR), 1),
    ('192.163.10.89', 'guest-wifi-ap-02', 'C1:C2:C3:D4:D5:D6', 'offline', 'Access Point', 'Conference Room B', 1, 1, DATE_SUB(NOW(), INTERVAL 3 DAY), 1)
ON DUPLICATE KEY UPDATE ip_address=ip_address;

-- Insert default settings
INSERT INTO settings (setting_key, setting_value, description, updated_by) 
VALUES 
    ('system_name', 'IP Manager', 'System name displayed in header', 1),
    ('scan_timeout', '5', 'Default scan timeout in seconds', 1),
    ('session_timeout', '3600', 'Session timeout in seconds', 1),
    ('items_per_page', '50', 'Default items per page', 1)
ON DUPLICATE KEY UPDATE setting_key=setting_key;

-- Update subnet used IPs count
UPDATE subnets s 
SET used_ips = (
    SELECT COUNT(*) 
    FROM ip_inventory i 
    WHERE i.subnet_id = s.id AND i.status IN ('active', 'reserved', 'static')
);
