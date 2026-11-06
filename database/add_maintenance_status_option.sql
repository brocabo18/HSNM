-- Migration: Ensure status column accommodates 'Maintenance' status
-- 'Maintenance' status has been added to Computer Inventory, Satellite modules, and synced with Asset Registry.

-- Status column was already widened to VARCHAR(100), but if running from scratch:
ALTER TABLE computers ALTER COLUMN status TYPE character varying(100);
ALTER TABLE computers_jasmc_st ALTER COLUMN status TYPE character varying(100);
ALTER TABLE computers_jasmc_vt ALTER COLUMN status TYPE character varying(100);
ALTER TABLE computers_doh_jbl_cmc ALTER COLUMN status TYPE character varying(100);
ALTER TABLE assets ALTER COLUMN status TYPE character varying(100);

-- Valid values for computers.status now include:
-- 'Working', 'Maintenance', 'Transferred', 'Condemned'
