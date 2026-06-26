-- Migration: Add endpoint_secure column to computers table
-- Date: 2026-01-28
-- Description: Adds endpoint_secure field with Yes/No values

-- Add the column
ALTER TABLE computers 
ADD COLUMN endpoint_secure VARCHAR(3) DEFAULT 'N' CHECK (endpoint_secure IN ('Y', 'N'));

-- Add comment for documentation
COMMENT ON COLUMN computers.endpoint_secure IS 'Indicates if endpoint security is enabled: Y (Yes) or N (No)';
