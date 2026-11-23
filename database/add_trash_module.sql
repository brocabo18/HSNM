-- ============================================================
-- Trash Module: soft-delete support for computers table
-- Run once on the target database
-- ============================================================

-- Add soft-delete columns to computers table
ALTER TABLE computers
    ADD COLUMN IF NOT EXISTS deleted_at   TIMESTAMPTZ DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS deleted_by   TEXT        DEFAULT NULL;

-- Index for fast trash queries
CREATE INDEX IF NOT EXISTS idx_computers_deleted_at ON computers(deleted_at);

-- ============================================================
-- trash_log: audit trail for all trashed/restored items
-- (optional — audit_logs already covers this, but useful for
--  per-row history visible inside the trash module itself)
-- ============================================================
CREATE TABLE IF NOT EXISTS trash_log (
    id           SERIAL PRIMARY KEY,
    table_name   TEXT NOT NULL DEFAULT 'computers',
    record_id    INTEGER NOT NULL,
    action       TEXT NOT NULL CHECK (action IN ('trashed','restored')),
    done_by      TEXT,
    done_at      TIMESTAMPTZ DEFAULT NOW(),
    snapshot     JSONB   -- full row snapshot at time of trash
);

CREATE INDEX IF NOT EXISTS idx_trash_log_record ON trash_log(table_name, record_id);
