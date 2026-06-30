-- HSNM Additional Tables Schema (PostgreSQL)
-- Run this AFTER postgres_schema.sql

-- Changelog Table
CREATE TABLE IF NOT EXISTS changelog (
    id SERIAL PRIMARY KEY,
    version VARCHAR(20),
    change_date DATE DEFAULT CURRENT_DATE,
    change_type VARCHAR(50),
    module VARCHAR(100),
    title VARCHAR(255),
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- PABX Directory Table
CREATE TABLE IF NOT EXISTS pabx_directory (
    id SERIAL PRIMARY KEY,
    local_number VARCHAR(50) NOT NULL UNIQUE,
    ip_address VARCHAR(45),
    department VARCHAR(100),
    building VARCHAR(100) NOT NULL,
    floor VARCHAR(10) NOT NULL,
    display_name VARCHAR(200) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_pabx_local ON pabx_directory(local_number);
CREATE INDEX IF NOT EXISTS idx_pabx_building ON pabx_directory(building);
CREATE INDEX IF NOT EXISTS idx_pabx_dept ON pabx_directory(department);

-- Printers Table
CREATE TABLE IF NOT EXISTS printers (
    id SERIAL PRIMARY KEY,
    control_number VARCHAR(100),
    brand VARCHAR(100),
    model VARCHAR(100),
    serial_number VARCHAR(100),
    ip_address VARCHAR(45),
    mac_address VARCHAR(17),
    department VARCHAR(100),
    location VARCHAR(200),
    status VARCHAR(20) DEFAULT 'Active',
    printer_type VARCHAR(50),
    connection_type VARCHAR(50),
    remarks TEXT,
    date_issued DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_printer_dept ON printers(department);
CREATE INDEX IF NOT EXISTS idx_printer_status ON printers(status);

-- Office Table
CREATE TABLE IF NOT EXISTS office (
    id SERIAL PRIMARY KEY,
    office_name VARCHAR(200) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ICS (Internet Connection Sharing) Table
CREATE TABLE IF NOT EXISTS ics (
    id SERIAL PRIMARY KEY,
    control_number VARCHAR(100),
    department VARCHAR(100),
    office VARCHAR(200),
    end_user VARCHAR(100),
    ip_address VARCHAR(45),
    mac_address VARCHAR(17),
    hostname VARCHAR(255),
    isp VARCHAR(100),
    connection_type VARCHAR(50),
    speed VARCHAR(50),
    status VARCHAR(20) DEFAULT 'Active',
    remarks TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- IHOMS Links Table
CREATE TABLE IF NOT EXISTS ihoms_links (
    id SERIAL PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    url TEXT NOT NULL,
    category VARCHAR(100),
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Firewall Rules Table
CREATE TABLE IF NOT EXISTS firewall_rules (
    id SERIAL PRIMARY KEY,
    rule_name VARCHAR(200) NOT NULL,
    source_ip VARCHAR(45),
    destination_ip VARCHAR(45),
    port VARCHAR(50),
    protocol VARCHAR(20),
    action VARCHAR(20) DEFAULT 'Allow',
    status VARCHAR(20) DEFAULT 'Active',
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Queueing TV Table
CREATE TABLE IF NOT EXISTS queueing_tv (
    id SERIAL PRIMARY KEY,
    screen_name VARCHAR(100) NOT NULL,
    location VARCHAR(200),
    ip_address VARCHAR(45),
    status VARCHAR(20) DEFAULT 'Active',
    remarks TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Add module_access column to users if not exists
ALTER TABLE users ADD COLUMN IF NOT EXISTS module_access TEXT;

SELECT 'All tables created successfully!' AS result;
