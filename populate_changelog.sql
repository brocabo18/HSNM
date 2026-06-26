-- Populate Changelog with Historical Data
-- Based on conversation history and project development

INSERT INTO changelog (version, change_type, module, title, description, change_date) VALUES

-- February 2026
('1.5.0', 'refactor', 'System', 'Project Folder Rename', 'Renamed the project folder from "Hardware Network Management" to "HSNM". Updated all internal code references, configuration files (APP_NAME, BASE_URL), and database schemas to reflect the new naming convention.', '2026-02-09'),

('1.4.5', 'enhancement', 'Dashboard', 'Dashboard Layout Refinement', 'Refined dashboard layout by aligning "Quick Actions" and "Recent Activity" sections into a single row with equal heights. Improved responsive layout for tablet and desktop views with proper grid and column span adjustments.', '2026-02-05'),

('1.4.4', 'feature', 'MS Office', 'Print Functionality for MS Office Module', 'Implemented comprehensive print functionality for MS Office module with checkbox row selection, matching the existing print features in Router Inventory module.', '2026-02-05'),

('1.4.3', 'feature', 'ICS Inventory', 'Print Functionality for ICS Inventory', 'Added print functionality with row selection capabilities to ICS Inventory module to standardize the user experience across all inventory modules.', '2026-02-05'),

('1.4.2', 'enhancement', 'MS Office', 'Pagination and Sorting Implementation', 'Implemented robust pagination with configurable limits (50, 100, 200, 500, All) and sorting capabilities based on ID, control number, and department fields. Added top pagination controls with Previous/Next buttons and limit dropdown.', '2026-02-03'),

-- January 2026
('1.4.1', 'bugfix', 'Computers', 'Fix Computer Inventory Search', 'Fixed live search functionality in Computer Inventory module to ensure proper synchronization when typing and correct reset behavior when the search bar is cleared.', '2026-01-21'),

('1.4.0', 'bugfix', 'SIEM', 'Authentication and Module Debugging', 'Resolved "headers already sent" errors in assets.php and incidents.php. Fixed API endpoint connectivity by correcting the URL in sentinel_agent.py. Verified all SIEM modules are fully functional.', '2026-01-21'),

('1.3.9', 'enhancement', 'IP Addresses', 'Enhanced Ping and Scan Logic', 'Improved IP scanning functionality to correctly identify active devices even when ICMP ping is blocked by firewalls. Implemented advanced MAC address scanning using ARP, NBTSTAT, and GETMAC. Devices are now marked as Active if MAC address is found, even when ping fails.', '2026-01-21'),

('1.3.8', 'feature', 'Audit Logs', 'Audit Log Statistics Dashboard', 'Added statistics display showing update counts grouped by resource type (IP, Router, Switch, User, Reconciliation). Implemented color-coded stat cards at the top of Audit Logs page with dynamic filtering support.', '2026-01-19'),

('1.3.7', 'feature', 'IP Addresses', 'Hostname Update During Ping', 'Implemented automatic hostname retrieval and database update functionality during ping operations. Hostname updates occur when the ping result modal is closed, ensuring the IP inventory maintains current device information.', '2026-01-19'),

('1.3.6', 'feature', 'IP Addresses', 'Batch Ping IP Addresses', 'Implemented batch ping feature allowing users to select multiple IP addresses via checkboxes and ping them simultaneously. Added automatic hostname retrieval for each pinged IP and status updates (active/offline) in the database. Page auto-refreshes after batch operation completion.', '2026-01-19'),

('1.3.5', 'feature', 'Reconciliation', 'Live Search in Compare & Sync', 'Added live search functionality to the Compare and Sync module with server-side filtering. Implemented AJAX-based dynamic table updates without full page reloads. Search functionality works independently for both Data Mismatches and Identity Conflicts tabs with preserved search terms.', '2026-01-16'),

('1.3.4', 'enhancement', 'Pentest', 'Pentest Dashboard UI Rebrand', 'Rebranded Pentest dashboard UI to match the Hardware Network Management system design. Updated Tailwind configuration, changed font to "Inter", updated sidebar, standardized main content container, and updated stat cards, tables, and buttons across all pages.', '2026-01-16'),

('1.3.3', 'feature', 'Pentest', 'Penetration Testing Dashboard', 'Implemented comprehensive penetration testing dashboard with secure login page, session management, and cache security. Added KPIs, vulnerability trends, recent findings, vulnerabilities management, reports generation, and settings. All modules dynamically populated from dedicated database.', '2026-01-16'),

('1.3.2', 'security', 'Authentication', 'Session Security Enhancement', 'Enhanced application security with robust logout functionality preventing re-access via browser back button. Implemented proper session termination with cleared session data and cookies. Added automatic logout after 30 minutes of user inactivity.', '2026-01-15'),

('1.3.1', 'feature', 'Reconciliation', 'Data Reconciliation Module', 'Created Compare & Sync module to reconcile discrepancies between IP Inventory and Computer Inventory. Features side-by-side comparison of IP and MAC addresses based on control_number, bidirectional data synchronization, refresh functionality, and pagination support for handling large datasets.', '2026-01-15'),

('1.3.0', 'enhancement', 'IP Addresses', 'Auto-Refresh After Ping', 'Implemented automatic page refresh functionality after closing ping result modal in IP Address module. Ensures users immediately see updated IP status (active/offline) and MAC address information without manual refresh.', '2026-01-13'),

-- Initial Release
('1.0.0', 'feature', 'System', 'Initial HSNM Release', 'Initial release of Hardware Security Network Management (HSNM) system with core modules: Router Inventory, Switch Inventory, IP Address Management, Computer Inventory, MS Office tracking, ICS Inventory, and Audit Logs. Includes user authentication, role-based access control, and unified PostgreSQL database.', '2026-01-01');
