-- Unified HSNM Database Schema (PostgreSQL Version)
-- Converted from MySQL

-- Users Table
CREATE TABLE IF NOT EXISTS users (
    id SERIAL PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100),
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100),
    role VARCHAR(20) DEFAULT 'viewer' CHECK (role IN ('admin', 'editor', 'viewer')),
    is_active BOOLEAN DEFAULT TRUE,
    last_login TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Default Admin
INSERT INTO users (username, password, full_name, role) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'admin')
ON CONFLICT (username) DO NOTHING;

-- Settings Table
CREATE TABLE IF NOT EXISTS settings (
    id SERIAL PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO settings (setting_key, setting_value, description) VALUES
('system_name', 'HSNM', 'System Name'),
('dark_mode', '1', 'Enable Dark Mode by Default')
ON CONFLICT (setting_key) DO NOTHING;

-- Audit Logs Table
CREATE TABLE IF NOT EXISTS audit_logs (
    id SERIAL PRIMARY KEY,
    user_id INT,
    action_type VARCHAR(50),
    details TEXT,
    resource_type VARCHAR(50),
    resource_id INT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX idx_audit_created_at ON audit_logs(created_at);
CREATE INDEX idx_audit_action_type ON audit_logs(action_type);
CREATE INDEX idx_audit_user_created ON audit_logs(user_id, created_at);

-- Routers Table
CREATE TABLE IF NOT EXISTS routers (
    id SERIAL PRIMARY KEY,
    serial_number VARCHAR(50) NOT NULL,
    brand VARCHAR(100) NOT NULL,
    location VARCHAR(100),
    ip_address VARCHAR(45),
    mac_address VARCHAR(17),
    lan_ip VARCHAR(45),
    ssid VARCHAR(100),
    wifi_password VARCHAR(100),
    admin_user VARCHAR(100),
    admin_password VARCHAR(100),
    status VARCHAR(20) DEFAULT 'Offline' CHECK (status IN ('Online', 'Offline', 'Warning', 'High Latency', 'Power Fail', 'Maintenance')),
    uptime VARCHAR(50) DEFAULT '--',
    firmware_status VARCHAR(50) DEFAULT 'Up to Date' CHECK (firmware_status IN ('Up to Date', 'Update Available', 'Critical Update')),
    remarks TEXT,
    last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_router_ip ON routers(ip_address);
CREATE INDEX idx_router_status ON routers(status);

-- Switches Table
CREATE TABLE IF NOT EXISTS switches (
    id SERIAL PRIMARY KEY,
    switch_id VARCHAR(50) NOT NULL UNIQUE,
    model VARCHAR(100) NOT NULL,
    manufacturer VARCHAR(100) NOT NULL,
    serial VARCHAR(100) NOT NULL UNIQUE,
    ip_address VARCHAR(45) NOT NULL,
    mac_address VARCHAR(17) NOT NULL,
    building_location VARCHAR(200) NOT NULL,
    floor VARCHAR(20),
    ports VARCHAR(50),
    port_details VARCHAR(200),
    ports_status VARCHAR(50),
    status VARCHAR(20) DEFAULT 'Active' CHECK (status IN ('Active', 'Maintenance', 'Inactive')),
    personnel VARCHAR(100),
    last_maintenance DATE,
    next_maintenance VARCHAR(50),
    remarks TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_switch_ip ON switches(ip_address);
CREATE INDEX idx_switch_status ON switches(status);

-- Subnets Table
CREATE TABLE IF NOT EXISTS subnets (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    network VARCHAR(50) NOT NULL,
    cidr VARCHAR(20) NOT NULL,
    gateway VARCHAR(50),
    vlan_id INT,
    description TEXT,
    location VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- IPs Table
CREATE TABLE IF NOT EXISTS ips (
    id SERIAL PRIMARY KEY,
    ip_address VARCHAR(50) NOT NULL UNIQUE,
    mac_address VARCHAR(17),
    hostname VARCHAR(255),
    control_number VARCHAR(100),
    department VARCHAR(100),
    om_name VARCHAR(100),
    status VARCHAR(20) DEFAULT 'offline' CHECK (status IN ('active', 'reserved', 'conflict', 'static', 'offline')),
    device_type VARCHAR(50),
    description TEXT,
    subnet_id INT,
    remarks TEXT,
    last_seen TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (subnet_id) REFERENCES subnets(id) ON DELETE SET NULL
);

CREATE INDEX idx_ip_control ON ips(control_number);
CREATE INDEX idx_ip_mac ON ips(mac_address);
CREATE INDEX idx_ip_status ON ips(status);
CREATE INDEX idx_ip_control_ip ON ips(control_number, ip_address);

-- Computers Table
CREATE TABLE IF NOT EXISTS computers (
    id SERIAL PRIMARY KEY,
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
    license CHAR(1) DEFAULT 'N' CHECK (license IN ('Y', 'N')),
    microsoft_office VARCHAR(100),
    os_product_key VARCHAR(255),
    ip_address VARCHAR(45),
    mac_address VARCHAR(17),
    checked_by VARCHAR(100),
    encoded_by VARCHAR(100),
    remarks TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_comp_control ON computers(control_number);
CREATE INDEX idx_comp_ip ON computers(ip_address);
CREATE INDEX idx_comp_mac ON computers(mac_address);
CREATE INDEX idx_comp_control_ip ON computers(control_number, ip_address);
