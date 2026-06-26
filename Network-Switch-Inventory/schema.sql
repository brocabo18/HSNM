-- Network Switch Inventory Database Schema
-- Create database and tables

CREATE DATABASE IF NOT EXISTS network_inventory CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE network_inventory;

-- Switches table
CREATE TABLE IF NOT EXISTS switches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    switch_id VARCHAR(50) NOT NULL UNIQUE,
    model VARCHAR(100) NOT NULL,
    manufacturer VARCHAR(100) NOT NULL,
    serial VARCHAR(100) NOT NULL UNIQUE,
    ip VARCHAR(45) NOT NULL,
    mac VARCHAR(17) NOT NULL,
    location VARCHAR(200) NOT NULL,
    ports VARCHAR(50) NOT NULL,
    ports_detail VARCHAR(200),
    status ENUM('Active', 'Maintenance', 'Inactive') DEFAULT 'Active',
    personnel VARCHAR(100),
    last_maintenance DATE,
    next_maintenance VARCHAR(50),
    remarks TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_manufacturer (manufacturer),
    INDEX idx_location (location),
    INDEX idx_status (status),
    INDEX idx_switch_id (switch_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert sample data
INSERT INTO switches (switch_id, model, manufacturer, serial, ip, mac, location, ports, ports_detail, status, personnel, last_maintenance, next_maintenance, remarks) VALUES
('SW-DC1-A01', 'Catalyst 9300', 'Cisco Systems', 'FOC2341X2LY', '192.168.10.11', '00:00:5E:00:53:AF', 'DC-01, Rack A4', '48 Ports', '48x1G PoE+, 4x10G SFP+', 'Active', 'John Doe', '2023-10-12', '2024-04-12', 'Critical core switch for department A subnet.'),
('SW-DC1-B02', 'PowerConnect 6248', 'Dell Technologies', 'DELL-998271-Z', '192.168.10.12', 'E4:11:5B:22:90:01', 'DC-01, Rack B1', '24 Ports', '24x1G Base-T', 'Maintenance', 'Sarah Connor', '2023-11-05', 'TODAY', 'Firmware upgrade in progress.'),
('SW-NY-C12', 'Aruba 2930F', 'HPE', 'HP-A99228172', '10.0.1.55', 'AA:BB:CC:11:22:33', 'NYC-Office, Rm 202', '48 Ports', '48x1G, 4xSFP+', 'Inactive', 'Mark Ruffalo', '2022-05-10', 'TBD', 'Reserved for expansion.'),
('SW-DC1-A04', 'Juniper EX4300', 'Juniper Networks', 'JNPR-EX-5541', '192.168.10.15', 'D4:21:A4:99:C1:2B', 'DC-01, Rack A4', '32 Ports', '24x10G, 8x40G QSFP+', 'Active', 'John Doe', '2024-01-20', '2024-07-20', 'Aggregation switch.'),
('SW-DC2-A01', 'Catalyst 9200', 'Cisco Systems', 'FOC9876ABCD', '192.168.20.11', '00:1A:2B:3C:4D:5E', 'DC-02, Rack A1', '48 Ports', '48x1G, 4xSFP+', 'Active', 'Jane Smith', '2023-09-15', '2024-03-15', 'Primary access switch for DC-02.'),
('SW-LA-B05', 'S5720-32X-EI', 'Huawei', 'HW-LA-983271', '10.10.5.20', 'A4:5E:60:C8:9A:1F', 'LA-Office, Server Room', '32 Ports', '24x10G, 8x40G', 'Active', 'Carlos Rodriguez', '2024-02-01', '2024-08-01', 'Core distribution switch.'),
('SW-DC1-C03', 'Nexus 3048', 'Cisco Systems', 'CIS-NEX-5599', '192.168.10.30', 'D0:D0:FD:12:34:56', 'DC-01, Rack C3', '48 Ports', '48x1G/10G SFP+', 'Maintenance', 'John Doe', '2024-01-05', '2024-01-15', 'Scheduled security patch.'),
('SW-SEA-A02', 'S4048-ON', 'Dell Technologies', 'DELL-SEA-7721', '172.16.1.10', 'E8:E8:E8:AA:BB:CC', 'Seattle DC, Rack A2', '48 Ports', '48x10G SFP+, 6x40G QSFP+', 'Active', 'Emily Chen', '2023-12-20', '2024-06-20', 'Edge aggregation switch.');
