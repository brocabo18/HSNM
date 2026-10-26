-- Missing tables fix — run this in psql then re-import schema_export.sql

CREATE TABLE IF NOT EXISTS firewall_status (
    id SERIAL PRIMARY KEY,
    control_number VARCHAR(100) NOT NULL,
    status VARCHAR(10) DEFAULT 'No',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_edited_by VARCHAR(100)
);
CREATE INDEX IF NOT EXISTS idx_fw_control ON firewall_status(control_number);

CREATE TABLE IF NOT EXISTS ics_inventory (
    id SERIAL PRIMARY KEY,
    ics_no VARCHAR(100),
    user_accountable VARCHAR(200),
    model VARCHAR(200),
    serial_number VARCHAR(100),
    item_code VARCHAR(100),
    po_number VARCHAR(100),
    price NUMERIC(12,2),
    date_acquired DATE,
    supplier VARCHAR(200),
    inventory_item_no VARCHAR(100),
    iar VARCHAR(100),
    department VARCHAR(100),
    date_issued DATE,
    ris_no VARCHAR(100),
    remarks TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS office_licenses (
    id SERIAL PRIMARY KEY,
    control_number VARCHAR(100),
    department VARCHAR(100),
    ms_office_version VARCHAR(100),
    product_key VARCHAR(255),
    email VARCHAR(200),
    password VARCHAR(255),
    remarks TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_ol_control ON office_licenses(control_number);
CREATE INDEX IF NOT EXISTS idx_ol_dept ON office_licenses(department);

SELECT 'Missing tables created!' AS result;
