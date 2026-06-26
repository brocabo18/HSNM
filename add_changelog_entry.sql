-- Add the current changelog feature to the changelog itself
INSERT INTO changelog (version, change_type, module, title, description, change_date) 
VALUES ('1.6.0', 'feature', 'System', 'Changelog Module with Auto-Versioning', 'Implemented comprehensive changelog module with timeline-based UI, filtering, search, and pagination. Added automatic version display in footer that syncs with the latest changelog entry. Version number is now dynamically pulled from the changelog table and displayed on the login page.', '2026-02-10');
