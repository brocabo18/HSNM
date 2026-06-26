-- PABX Directory Module Database Schema
-- Creates table for managing PABX phone directory entries

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
