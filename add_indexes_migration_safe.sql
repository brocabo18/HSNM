-- Database Index Migration Script (Safe Version)
-- Hardware Network Management System
-- Add missing indexes to improve query performance
-- Uses IF NOT EXISTS logic to prevent duplicate key errors

USE unified_network_inventory;

-- ============================================
-- IP Inventory Indexes
-- ============================================

-- Check and add indexes if they don't exist
SET @query = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = 'unified_network_inventory' AND TABLE_NAME = 'ips' AND INDEX_NAME = 'idx_control_number') = 0,
    'ALTER TABLE ips ADD INDEX idx_control_number (control_number)',
    'SELECT "Index idx_control_number already exists" AS Info'
);
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @query = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = 'unified_network_inventory' AND TABLE_NAME = 'ips' AND INDEX_NAME = 'idx_mac_address') = 0,
    'ALTER TABLE ips ADD INDEX idx_mac_address (mac_address)',
    'SELECT "Index idx_mac_address already exists" AS Info'
);
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @query = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = 'unified_network_inventory' AND TABLE_NAME = 'ips' AND INDEX_NAME = 'idx_status') = 0,
    'ALTER TABLE ips ADD INDEX idx_status (status)',
    'SELECT "Index idx_status already exists" AS Info'
);
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @query = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = 'unified_network_inventory' AND TABLE_NAME = 'ips' AND INDEX_NAME = 'idx_hostname') = 0,
    'ALTER TABLE ips ADD INDEX idx_hostname (hostname)',
    'SELECT "Index idx_hostname already exists" AS Info'
);
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @query = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = 'unified_network_inventory' AND TABLE_NAME = 'ips' AND INDEX_NAME = 'idx_department') = 0,
    'ALTER TABLE ips ADD INDEX idx_department (department)',
    'SELECT "Index idx_department already exists" AS Info'
);
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @query = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = 'unified_network_inventory' AND TABLE_NAME = 'ips' AND INDEX_NAME = 'idx_om_name') = 0,
    'ALTER TABLE ips ADD INDEX idx_om_name (om_name)',
    'SELECT "Index idx_om_name already exists" AS Info'
);
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @query = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = 'unified_network_inventory' AND TABLE_NAME = 'ips' AND INDEX_NAME = 'idx_control_ip') = 0,
    'ALTER TABLE ips ADD INDEX idx_control_ip (control_number, ip_address)',
    'SELECT "Index idx_control_ip already exists" AS Info'
);
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;


-- ============================================
-- Computer Inventory Indexes
-- ============================================

SET @query = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = 'unified_network_inventory' AND TABLE_NAME = 'computers' AND INDEX_NAME = 'idx_control_number') = 0,
    'ALTER TABLE computers ADD INDEX idx_control_number (control_number)',
    'SELECT "Index idx_control_number already exists" AS Info'
);
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @query = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = 'unified_network_inventory' AND TABLE_NAME = 'computers' AND INDEX_NAME = 'idx_ip_address') = 0,
    'ALTER TABLE computers ADD INDEX idx_ip_address (ip_address)',
    'SELECT "Index idx_ip_address already exists" AS Info'
);
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @query = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = 'unified_network_inventory' AND TABLE_NAME = 'computers' AND INDEX_NAME = 'idx_mac_address') = 0,
    'ALTER TABLE computers ADD INDEX idx_mac_address (mac_address)',
    'SELECT "Index idx_mac_address already exists" AS Info'
);
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @query = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = 'unified_network_inventory' AND TABLE_NAME = 'computers' AND INDEX_NAME = 'idx_department') = 0,
    'ALTER TABLE computers ADD INDEX idx_department (department)',
    'SELECT "Index idx_department already exists" AS Info'
);
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @query = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = 'unified_network_inventory' AND TABLE_NAME = 'computers' AND INDEX_NAME = 'idx_end_user') = 0,
    'ALTER TABLE computers ADD INDEX idx_end_user (end_user)',
    'SELECT "Index idx_end_user already exists" AS Info'
);
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @query = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = 'unified_network_inventory' AND TABLE_NAME = 'computers' AND INDEX_NAME = 'idx_system_unit') = 0,
    'ALTER TABLE computers ADD INDEX idx_system_unit (system_unit)',
    'SELECT "Index idx_system_unit already exists" AS Info'
);
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @query = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = 'unified_network_inventory' AND TABLE_NAME = 'computers' AND INDEX_NAME = 'idx_printer') = 0,
    'ALTER TABLE computers ADD INDEX idx_printer (printer)',
    'SELECT "Index idx_printer already exists" AS Info'
);
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @query = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = 'unified_network_inventory' AND TABLE_NAME = 'computers' AND INDEX_NAME = 'idx_control_ip') = 0,
    'ALTER TABLE computers ADD INDEX idx_control_ip (control_number, ip_address)',
    'SELECT "Index idx_control_ip already exists" AS Info'
);
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;


-- ============================================
-- Audit Logs Indexes
-- ============================================

SET @query = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = 'unified_network_inventory' AND TABLE_NAME = 'audit_logs' AND INDEX_NAME = 'idx_created_at') = 0,
    'ALTER TABLE audit_logs ADD INDEX idx_created_at (created_at)',
    'SELECT "Index idx_created_at already exists" AS Info'
);
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @query = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = 'unified_network_inventory' AND TABLE_NAME = 'audit_logs' AND INDEX_NAME = 'idx_action_type') = 0,
    'ALTER TABLE audit_logs ADD INDEX idx_action_type (action_type)',
    'SELECT "Index idx_action_type already exists" AS Info'
);
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @query = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = 'unified_network_inventory' AND TABLE_NAME = 'audit_logs' AND INDEX_NAME = 'idx_resource_type') = 0,
    'ALTER TABLE audit_logs ADD INDEX idx_resource_type (resource_type)',
    'SELECT "Index idx_resource_type already exists" AS Info'
);
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @query = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = 'unified_network_inventory' AND TABLE_NAME = 'audit_logs' AND INDEX_NAME = 'idx_user_created') = 0,
    'ALTER TABLE audit_logs ADD INDEX idx_user_created (user_id, created_at)',
    'SELECT "Index idx_user_created already exists" AS Info'
);
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;


-- ============================================
-- Router Inventory Indexes
-- ============================================

SET @query = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = 'unified_network_inventory' AND TABLE_NAME = 'routers' AND INDEX_NAME = 'idx_ip_address') = 0,
    'ALTER TABLE routers ADD INDEX idx_ip_address (ip_address)',
    'SELECT "Index idx_ip_address already exists" AS Info'
);
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @query = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = 'unified_network_inventory' AND TABLE_NAME = 'routers' AND INDEX_NAME = 'idx_status') = 0,
    'ALTER TABLE routers ADD INDEX idx_status (status)',
    'SELECT "Index idx_status already exists" AS Info'
);
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @query = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = 'unified_network_inventory' AND TABLE_NAME = 'routers' AND INDEX_NAME = 'idx_location') = 0,
    'ALTER TABLE routers ADD INDEX idx_location (location)',
    'SELECT "Index idx_location already exists" AS Info'
);
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @query = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = 'unified_network_inventory' AND TABLE_NAME = 'routers' AND INDEX_NAME = 'idx_brand') = 0,
    'ALTER TABLE routers ADD INDEX idx_brand (brand)',
    'SELECT "Index idx_brand already exists" AS Info'
);
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;


-- ============================================
-- Switch Inventory Indexes
-- ============================================

SET @query = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = 'unified_network_inventory' AND TABLE_NAME = 'switches' AND INDEX_NAME = 'idx_ip_address') = 0,
    'ALTER TABLE switches ADD INDEX idx_ip_address (ip_address)',
    'SELECT "Index idx_ip_address already exists" AS Info'
);
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @query = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = 'unified_network_inventory' AND TABLE_NAME = 'switches' AND INDEX_NAME = 'idx_status') = 0,
    'ALTER TABLE switches ADD INDEX idx_status (status)',
    'SELECT "Index idx_status already exists" AS Info'
);
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @query = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = 'unified_network_inventory' AND TABLE_NAME = 'switches' AND INDEX_NAME = 'idx_manufacturer') = 0,
    'ALTER TABLE switches ADD INDEX idx_manufacturer (manufacturer)',
    'SELECT "Index idx_manufacturer already exists" AS Info'
);
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @query = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = 'unified_network_inventory' AND TABLE_NAME = 'switches' AND INDEX_NAME = 'idx_building_location') = 0,
    'ALTER TABLE switches ADD INDEX idx_building_location (building_location)',
    'SELECT "Index idx_building_location already exists" AS Info'
);
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================
-- Success Message
-- ============================================
SELECT '✓ Index migration completed successfully!' AS Status;
