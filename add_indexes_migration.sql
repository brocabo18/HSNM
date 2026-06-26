-- Database Index Migration Script
-- Hardware Network Management System
-- Add missing indexes to improve query performance

USE unified_network_inventory;

-- ============================================
-- IP Inventory Indexes
-- ============================================

-- Index for control_number lookups and reconciliation JOINs
ALTER TABLE ips ADD INDEX idx_control_number (control_number);

-- Index for MAC address lookups (ARP scan, reconciliation)
ALTER TABLE ips ADD INDEX idx_mac_address (mac_address);

-- Index for status filtering
ALTER TABLE ips ADD INDEX idx_status (status);

-- Index for hostname searches
ALTER TABLE ips ADD INDEX idx_hostname (hostname);

-- Index for department searches
ALTER TABLE ips ADD INDEX idx_department (department);

-- Index for OM name searches
ALTER TABLE ips ADD INDEX idx_om_name (om_name);

-- Composite index for reconciliation queries
ALTER TABLE ips ADD INDEX idx_control_ip (control_number, ip_address);


-- ============================================
-- Computer Inventory Indexes
-- ============================================

-- Index for control_number reconciliation JOINs
ALTER TABLE computers ADD INDEX idx_control_number (control_number);

-- Index for IP address matching in reconciliation
ALTER TABLE computers ADD INDEX idx_ip_address (ip_address);

-- Index for MAC address matching
ALTER TABLE computers ADD INDEX idx_mac_address (mac_address);

-- Index for department searches
ALTER TABLE computers ADD INDEX idx_department (department);

-- Index for end_user searches
ALTER TABLE computers ADD INDEX idx_end_user (end_user);

-- Index for system_unit searches
ALTER TABLE computers ADD INDEX idx_system_unit (system_unit);

-- Index for printer searches
ALTER TABLE computers ADD INDEX idx_printer (printer);

-- Composite index for conflict detection queries
ALTER TABLE computers ADD INDEX idx_control_ip (control_number, ip_address);


-- ============================================
-- Audit Logs Indexes
-- ============================================

-- Index for date-based queries and sorting
ALTER TABLE audit_logs ADD INDEX idx_created_at (created_at);

-- Index for filtering by action type
ALTER TABLE audit_logs ADD INDEX idx_action_type (action_type);

-- Index for resource type filtering
ALTER TABLE audit_logs ADD INDEX idx_resource_type (resource_type);

-- Composite index for user activity queries
ALTER TABLE audit_logs ADD INDEX idx_user_created (user_id, created_at);


-- ============================================
-- Router Inventory Indexes
-- ============================================

-- Index for IP address lookups
ALTER TABLE routers ADD INDEX idx_ip_address (ip_address);

-- Index for status filtering
ALTER TABLE routers ADD INDEX idx_status (status);

-- Index for location searches
ALTER TABLE routers ADD INDEX idx_location (location);

-- Index for brand searches
ALTER TABLE routers ADD INDEX idx_brand (brand);


-- ============================================
-- Switch Inventory Indexes
-- ============================================

-- Index for IP address lookups
ALTER TABLE switches ADD INDEX idx_ip_address (ip_address);

-- Index for status filtering
ALTER TABLE switches ADD INDEX idx_status (status);

-- Index for manufacturer searches
ALTER TABLE switches ADD INDEX idx_manufacturer (manufacturer);

-- Index for building location searches
ALTER TABLE switches ADD INDEX idx_building_location (building_location);


-- ============================================
-- Verification Query
-- ============================================

-- Run this to verify all indexes were created successfully
SELECT 
    TABLE_NAME,
    INDEX_NAME,
    COLUMN_NAME,
    INDEX_TYPE
FROM 
    INFORMATION_SCHEMA.STATISTICS
WHERE 
    TABLE_SCHEMA = 'unified_network_inventory'
ORDER BY 
    TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX;
