-- Enable Trigram Extension for partial string matching
CREATE EXTENSION IF NOT EXISTS pg_trgm;

-- IPS Table Indexes
CREATE INDEX IF NOT EXISTS idx_ips_hostname_trgm ON ips USING GIN (UPPER(hostname) gin_trgm_ops);
CREATE INDEX IF NOT EXISTS idx_ips_desc_trgm ON ips USING GIN (UPPER(description) gin_trgm_ops);
CREATE INDEX IF NOT EXISTS idx_ips_dept_trgm ON ips USING GIN (UPPER(department) gin_trgm_ops);
CREATE INDEX IF NOT EXISTS idx_ips_subnet_id ON ips(subnet_id);

-- Routers Table Indexes
CREATE INDEX IF NOT EXISTS idx_routers_ssid_trgm ON routers USING GIN (UPPER(ssid) gin_trgm_ops);
CREATE INDEX IF NOT EXISTS idx_routers_brand_trgm ON routers USING GIN (UPPER(brand) gin_trgm_ops);
CREATE INDEX IF NOT EXISTS idx_routers_location_trgm ON routers USING GIN (UPPER(location) gin_trgm_ops);
CREATE INDEX IF NOT EXISTS idx_routers_location_btree ON routers(location);

-- Switches Table Indexes
CREATE INDEX IF NOT EXISTS idx_switches_switch_id_trgm ON switches USING GIN (UPPER(switch_id) gin_trgm_ops);
CREATE INDEX IF NOT EXISTS idx_switches_model_trgm ON switches USING GIN (UPPER(model) gin_trgm_ops);
CREATE INDEX IF NOT EXISTS idx_switches_location_trgm ON switches USING GIN (UPPER(building_location) gin_trgm_ops);

-- Computers Table Indexes
CREATE INDEX IF NOT EXISTS idx_computers_end_user_trgm ON computers USING GIN (UPPER(end_user) gin_trgm_ops);
CREATE INDEX IF NOT EXISTS idx_computers_dept_trgm ON computers USING GIN (UPPER(department) gin_trgm_ops);

-- Users Table (for fast username lookup if valid connection)
CREATE INDEX IF NOT EXISTS idx_users_username_active ON users(username) WHERE is_active = true;
