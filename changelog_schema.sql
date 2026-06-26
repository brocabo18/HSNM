-- Changelog Table Schema
-- Tracks all system changes, features, and bug fixes

CREATE TABLE IF NOT EXISTS changelog (
    id SERIAL PRIMARY KEY,
    version VARCHAR(20),
    change_type VARCHAR(20) CHECK (change_type IN ('feature', 'bugfix', 'enhancement', 'security', 'refactor')),
    module VARCHAR(50),
    title VARCHAR(255) NOT NULL,
    description TEXT,
    change_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create index for faster date-based queries
CREATE INDEX IF NOT EXISTS idx_changelog_date ON changelog(change_date DESC);
CREATE INDEX IF NOT EXISTS idx_changelog_type ON changelog(change_type);
CREATE INDEX IF NOT EXISTS idx_changelog_module ON changelog(module);
