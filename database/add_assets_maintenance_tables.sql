-- ============================================================
-- HSNM: Assets & Maintenance Module Migration
-- Created: 2026-10-27
-- ============================================================

-- 1. Assets table
CREATE TABLE IF NOT EXISTS assets (
    id                  SERIAL PRIMARY KEY,
    control_id          VARCHAR(50) UNIQUE,
    department          VARCHAR(100),
    end_user            VARCHAR(100),
    source              VARCHAR(20) DEFAULT 'Purchased',
    mr_ics              VARCHAR(50),
    item_code           VARCHAR(50),
    cost                NUMERIC(12,2),
    brand_model         VARCHAR(150),
    serial_number       VARCHAR(100),
    ptr_number          VARCHAR(50),
    processor           VARCHAR(100),
    memory              VARCHAR(50),
    storage             VARCHAR(50),
    gpu                 VARCHAR(100),
    monitor             VARCHAR(100),
    monitor_sn          VARCHAR(100),
    printer             VARCHAR(100),
    printer_sn          VARCHAR(100),
    ups_avr             VARCHAR(100),
    ups_avr_sn          VARCHAR(100),
    keyboard_mouse      VARCHAR(100),
    os_version          VARCHAR(100),
    os_licensed         BOOLEAN DEFAULT FALSE,
    ip_address          VARCHAR(45),
    mac_address         VARCHAR(17),
    ms_office           VARCHAR(100),
    ms_office_licensed  BOOLEAN DEFAULT FALSE,
    antivirus           VARCHAR(100),
    scan_date           DATE,
    status              VARCHAR(20) DEFAULT 'Active',
    remarks             TEXT,
    encoded_by          VARCHAR(100),
    created_at          TIMESTAMP DEFAULT NOW(),
    updated_at          TIMESTAMP DEFAULT NOW()
);

-- 2. Maintenance records table
CREATE TABLE IF NOT EXISTS maintenance (
    id                  SERIAL PRIMARY KEY,
    asset_id            INTEGER REFERENCES assets(id) ON DELETE SET NULL,
    computer_id         INTEGER REFERENCES computers(id) ON DELETE SET NULL,
    department          VARCHAR(100),
    employee_name       VARCHAR(100),
    control_number      VARCHAR(50),
    processor           VARCHAR(100),
    memory              VARCHAR(50),
    gpu                 VARCHAR(100),
    maintenance_date    DATE NOT NULL DEFAULT CURRENT_DATE,
    status              VARCHAR(30) DEFAULT 'Completed',
    performance_score   INTEGER DEFAULT 100,
    -- Hardware checks (Pass/Fail/N/A)
    hw_monitor          VARCHAR(10) DEFAULT 'Pass',
    hw_motherboard      VARCHAR(10) DEFAULT 'Pass',
    hw_power_supply     VARCHAR(10) DEFAULT 'Pass',
    hw_video_card       VARCHAR(10) DEFAULT 'Pass',
    hw_hard_disk        VARCHAR(10) DEFAULT 'Pass',
    hw_cdrom            VARCHAR(10) DEFAULT 'N/A',
    hw_lan_card         VARCHAR(10) DEFAULT 'Pass',
    hw_keyboard_mouse   VARCHAR(10) DEFAULT 'Pass',
    hw_printer_scanner  VARCHAR(10) DEFAULT 'N/A',
    hw_ups_avr          VARCHAR(10) DEFAULT 'Pass',
    hw_modem            VARCHAR(10) DEFAULT 'N/A',
    -- Hardware remarks
    hw_monitor_remarks      TEXT,
    hw_motherboard_remarks  TEXT,
    hw_power_supply_remarks TEXT,
    hw_video_card_remarks   TEXT,
    hw_hard_disk_remarks    TEXT,
    hw_cdrom_remarks        TEXT,
    hw_lan_card_remarks     TEXT,
    hw_keyboard_remarks     TEXT,
    hw_printer_remarks      TEXT,
    hw_ups_avr_remarks      TEXT,
    hw_modem_remarks        TEXT,
    -- Software checks (Pass/Fail/Pending)
    sw_defrag           VARCHAR(10) DEFAULT 'Pass',
    sw_registry         VARCHAR(10) DEFAULT 'Pass',
    sw_antivirus        VARCHAR(10) DEFAULT 'Pass',
    sw_os_patch         VARCHAR(10) DEFAULT 'Pass',
    sw_recycle_bin      VARCHAR(10) DEFAULT 'Pass',
    sw_temp_files       VARCHAR(10) DEFAULT 'Pass',
    sw_installed        VARCHAR(10) DEFAULT 'Pass',
    sw_os_integrity     VARCHAR(10) DEFAULT 'Pass',
    sw_office           VARCHAR(10) DEFAULT 'Pass',
    sw_cloud_backup     VARCHAR(10) DEFAULT 'N/A',
    sw_browser_cache    VARCHAR(10) DEFAULT 'Pass',
    -- Software remarks
    sw_defrag_remarks       TEXT,
    sw_registry_remarks     TEXT,
    sw_antivirus_remarks    TEXT,
    sw_os_patch_remarks     TEXT,
    sw_recycle_remarks      TEXT,
    sw_temp_remarks         TEXT,
    sw_installed_remarks    TEXT,
    sw_integrity_remarks    TEXT,
    sw_office_remarks       TEXT,
    sw_backup_remarks       TEXT,
    sw_browser_remarks      TEXT,
    -- Narrative fields
    env_observations    TEXT,
    action_taken        TEXT,
    recommendation      TEXT,
    checked_by          VARCHAR(100),
    verified_by         VARCHAR(100),
    audit_date          DATE,
    created_at          TIMESTAMP DEFAULT NOW(),
    updated_at          TIMESTAMP DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_assets_status       ON assets(status);
CREATE INDEX IF NOT EXISTS idx_assets_department   ON assets(department);
CREATE INDEX IF NOT EXISTS idx_maintenance_date    ON maintenance(maintenance_date);
CREATE INDEX IF NOT EXISTS idx_maintenance_status  ON maintenance(status);
CREATE INDEX IF NOT EXISTS idx_maintenance_dept    ON maintenance(department);
