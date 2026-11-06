-- Migration: Widen status and flag columns across all computer inventory and satellite tables
-- Enables full support for statuses like 'assessed but not yet deployed', 'assessed and deployed', etc.

ALTER TABLE computers ALTER COLUMN status TYPE character varying(100);
ALTER TABLE computers_jasmc_st ALTER COLUMN status TYPE character varying(100);
ALTER TABLE computers_jasmc_vt ALTER COLUMN status TYPE character varying(100);
ALTER TABLE computers_doh_jbl_cmc ALTER COLUMN status TYPE character varying(100);
ALTER TABLE assets ALTER COLUMN status TYPE character varying(100);

ALTER TABLE computers ALTER COLUMN endpoint_secure TYPE character varying(50);
ALTER TABLE computers ALTER COLUMN firewall TYPE character varying(50);
ALTER TABLE computers ALTER COLUMN license TYPE character varying(50);

ALTER TABLE computers_jasmc_st ALTER COLUMN endpoint_secure TYPE character varying(50);
ALTER TABLE computers_jasmc_st ALTER COLUMN firewall TYPE character varying(50);
ALTER TABLE computers_jasmc_st ALTER COLUMN license TYPE character varying(50);

ALTER TABLE computers_jasmc_vt ALTER COLUMN endpoint_secure TYPE character varying(50);
ALTER TABLE computers_jasmc_vt ALTER COLUMN firewall TYPE character varying(50);
ALTER TABLE computers_jasmc_vt ALTER COLUMN license TYPE character varying(50);

ALTER TABLE computers_doh_jbl_cmc ALTER COLUMN endpoint_secure TYPE character varying(50);
ALTER TABLE computers_doh_jbl_cmc ALTER COLUMN firewall TYPE character varying(50);
ALTER TABLE computers_doh_jbl_cmc ALTER COLUMN license TYPE character varying(50);
