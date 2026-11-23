-- ============================================================
-- Supervisor Approval Module: pending computer additions table
-- Run once on the target database
-- ============================================================

CREATE TABLE IF NOT EXISTS pending_computers (
    id               SERIAL PRIMARY KEY,
    -- All computer fields (mirror of computers table)
    department       TEXT,
    end_user         TEXT,
    mr_par           TEXT,
    status           TEXT DEFAULT 'Working',
    transferred_to   TEXT,
    control_number   TEXT NOT NULL,
    system_unit      TEXT,
    system_unit_sn   TEXT,
    monitor          TEXT,
    monitor_sn       TEXT,
    mouse            TEXT,
    mouse_sn         TEXT,
    keyboard         TEXT,
    keyboard_sn      TEXT,
    printer          TEXT,
    printer_sn       TEXT,
    scanner          TEXT,
    scanner_sn       TEXT,
    avr_ups          TEXT,
    avr_ups_sn       TEXT,
    processor        TEXT,
    memory           TEXT,
    storage          TEXT,
    os               TEXT,
    os_product_key   TEXT,
    license          TEXT DEFAULT 'N',
    microsoft_office TEXT,
    ms_office_email  TEXT,
    ip_address       TEXT,
    mac_address      TEXT,
    endpoint_secure  TEXT DEFAULT 'N',
    firewall         TEXT DEFAULT 'N',
    checked_by       TEXT,
    encoded_by       TEXT,
    remarks          TEXT,
    remarks_date     DATE,
    -- Approval tracking
    submitted_by_user_id  INTEGER,
    submitted_by_name     TEXT,
    confirmed_by_supervisor TEXT,
    approval_status  TEXT DEFAULT 'Pending'  CHECK (approval_status IN ('Pending','Approved','Rejected')),
    reviewed_by      TEXT,
    review_note      TEXT,
    reviewed_at      TIMESTAMPTZ,
    created_at       TIMESTAMPTZ DEFAULT NOW(),
    updated_at       TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_pending_computers_status ON pending_computers(approval_status);
CREATE INDEX IF NOT EXISTS idx_pending_computers_ctrl ON pending_computers(control_number);
