USE unified_network_inventory;

ALTER TABLE users
ADD COLUMN module_access JSON DEFAULT NULL AFTER role;
