-- Migration: Create Satellite IP Address Tables and Populate Initial Records
-- Tables: ips_jasmc_st, ips_jasmc_vt, ips_doh_jbl_cmc

-- 1. Create JASMC-ST IP addresses table
CREATE TABLE IF NOT EXISTS ips_jasmc_st (
    id SERIAL PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL UNIQUE,
    mac_address VARCHAR(50),
    hostname VARCHAR(255),
    control_number VARCHAR(100),
    location VARCHAR(255),
    om_name VARCHAR(255),
    status VARCHAR(50) DEFAULT 'available',
    device_type VARCHAR(50) DEFAULT 'Workstation',
    description TEXT,
    subnet_id INTEGER,
    remarks TEXT,
    last_seen TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    department VARCHAR(255)
);

CREATE INDEX IF NOT EXISTS idx_ips_jasmc_st_ip ON ips_jasmc_st(ip_address);
CREATE INDEX IF NOT EXISTS idx_ips_jasmc_st_mac ON ips_jasmc_st(mac_address);
CREATE INDEX IF NOT EXISTS idx_ips_jasmc_st_ctrl ON ips_jasmc_st(control_number);
CREATE INDEX IF NOT EXISTS idx_ips_jasmc_st_status ON ips_jasmc_st(status);

-- 2. Create JASMC-VT IP addresses table
CREATE TABLE IF NOT EXISTS ips_jasmc_vt (
    id SERIAL PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL UNIQUE,
    mac_address VARCHAR(50),
    hostname VARCHAR(255),
    control_number VARCHAR(100),
    location VARCHAR(255),
    om_name VARCHAR(255),
    status VARCHAR(50) DEFAULT 'available',
    device_type VARCHAR(50) DEFAULT 'Workstation',
    description TEXT,
    subnet_id INTEGER,
    remarks TEXT,
    last_seen TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    department VARCHAR(255)
);

CREATE INDEX IF NOT EXISTS idx_ips_jasmc_vt_ip ON ips_jasmc_vt(ip_address);
CREATE INDEX IF NOT EXISTS idx_ips_jasmc_vt_mac ON ips_jasmc_vt(mac_address);
CREATE INDEX IF NOT EXISTS idx_ips_jasmc_vt_ctrl ON ips_jasmc_vt(control_number);
CREATE INDEX IF NOT EXISTS idx_ips_jasmc_vt_status ON ips_jasmc_vt(status);

-- 3. Create DOH-JBL-CMC IP addresses table
CREATE TABLE IF NOT EXISTS ips_doh_jbl_cmc (
    id SERIAL PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL UNIQUE,
    mac_address VARCHAR(50),
    hostname VARCHAR(255),
    control_number VARCHAR(100),
    location VARCHAR(255),
    om_name VARCHAR(255),
    status VARCHAR(50) DEFAULT 'available',
    device_type VARCHAR(50) DEFAULT 'Workstation',
    description TEXT,
    subnet_id INTEGER,
    remarks TEXT,
    last_seen TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    department VARCHAR(255)
);

CREATE INDEX IF NOT EXISTS idx_ips_doh_jbl_cmc_ip ON ips_doh_jbl_cmc(ip_address);
CREATE INDEX IF NOT EXISTS idx_ips_doh_jbl_cmc_mac ON ips_doh_jbl_cmc(mac_address);
CREATE INDEX IF NOT EXISTS idx_ips_doh_jbl_cmc_ctrl ON ips_doh_jbl_cmc(control_number);
CREATE INDEX IF NOT EXISTS idx_ips_doh_jbl_cmc_status ON ips_doh_jbl_cmc(status);

-- 4. Populate JASMC-ST IP addresses from existing computers_jasmc_st inventory
INSERT INTO ips_jasmc_st (ip_address, mac_address, control_number, om_name, department, location, status, device_type, last_seen)
SELECT 
    TRIM(ip_address),
    TRIM(mac_address),
    TRIM(control_number),
    TRIM(end_user),
    TRIM(department),
    TRIM(department),
    'active',
    'Workstation',
    NOW()
FROM computers_jasmc_st
WHERE ip_address IS NOT NULL AND TRIM(ip_address) != ''
ON CONFLICT (ip_address) DO UPDATE SET
    mac_address = EXCLUDED.mac_address,
    control_number = EXCLUDED.control_number,
    om_name = EXCLUDED.om_name,
    department = EXCLUDED.department,
    location = EXCLUDED.location,
    status = 'active',
    device_type = 'Workstation',
    last_seen = NOW();
