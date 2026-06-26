-- IHOMS Information System Links Table
-- Run this script to create the ihoms_links table in PostgreSQL

CREATE TABLE IF NOT EXISTS ihoms_links (
    id          SERIAL PRIMARY KEY,
    system_name VARCHAR(255) NOT NULL,
    url         TEXT NOT NULL,
    category    VARCHAR(100) DEFAULT '',
    description TEXT DEFAULT '',
    status      VARCHAR(50) DEFAULT 'Active',
    remarks     TEXT DEFAULT '',
    created_at  TIMESTAMP DEFAULT NOW(),
    updated_at  TIMESTAMP DEFAULT NOW()
);

-- Index for quick category/status filtering
CREATE INDEX IF NOT EXISTS idx_ihoms_links_category ON ihoms_links(category);
CREATE INDEX IF NOT EXISTS idx_ihoms_links_status   ON ihoms_links(status);
