<?php
// Security Headers
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");
/**
 * Hardware Network Management - Central Configuration
 */

// Database Credentials — read from environment variables
// Railway automatically provides DATABASE_URL for linked PostgreSQL services
// For local development, set individual DB_* env vars or use the fallbacks below
// Support Railway (DATABASE_URL) and Vercel/Neon (POSTGRES_URL / POSTGRES_URL_NON_POOLING)
$env_db_url = getenv('DATABASE_URL') ?: (getenv('POSTGRES_URL') ?: getenv('POSTGRES_URL_NON_POOLING'));

if ($env_db_url) {
    // Parse the connection string: postgresql://user:pass@host:port/dbname
    $dbUrl = parse_url($env_db_url);
    define('DB_HOST', $dbUrl['host']);
    define('DB_PORT', $dbUrl['port'] ?? 5432);
    define('DB_NAME', ltrim($dbUrl['path'], '/'));
    // Passwords on Vercel/Neon often contain special chars and are URL-encoded
    define('DB_USER', isset($dbUrl['user']) ? urldecode($dbUrl['user']) : '');
    define('DB_PASS', isset($dbUrl['pass']) ? urldecode($dbUrl['pass']) : '');
} else {
    define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
    define('DB_PORT', getenv('DB_PORT') ?: 5432);
    define('DB_NAME', getenv('DB_NAME') ?: 'unified_network_inventory');
    define('DB_USER', getenv('DB_USER') ?: 'postgres');
    define('DB_PASS', getenv('DB_PASS') ?: 'ef95d058688B@');
}

// Application Settings
define('APP_NAME', 'HSNM');

// On Railway (production) the app is served from the root, so BASE_URL = ''
// Locally under XAMPP it runs in /HSNM/, so BASE_URL = '/HSNM'
if (!defined('BASE_URL')) {
    // getenv() returns false (bool) when variable is not set at all
    $isProduction = (
        getenv('APP_ENV') === 'production' || 
        (getenv('RAILWAY_ENVIRONMENT') !== false && getenv('RAILWAY_ENVIRONMENT') !== '') ||
        getenv('RENDER') === 'true' ||
        getenv('VERCEL') === '1' ||
        isset($_SERVER['VERCEL']) || 
        isset($_ENV['VERCEL']) ||
        isset($_SERVER['HTTP_X_VERCEL_ID'])
    );
    if ($isProduction) {
        define('BASE_URL', '');
    } else {
        $folderName = basename(__DIR__);
        define('BASE_URL', '/' . rawurlencode($folderName));
    }
}

// Error Reporting (Disabled in production for security)
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Database Connection
$pdo = null;
try {
    // Neon.tech and cloud providers require SSL, but local XAMPP usually doesn't.
    $ssl_param = (DB_HOST === 'localhost' || DB_HOST === '127.0.0.1') ? '' : ';sslmode=require';
    
    // Neon SNI workaround for older libpq versions (like XAMPP on Windows)
    $options_param = '';
    if (strpos(DB_HOST, '.neon.tech') !== false) {
        $endpoint_id = explode('.', DB_HOST)[0];
        // For PDO DSN, we use options='endpoint=ID' (not URL-encoded)
        $options_param = ";options='endpoint=$endpoint_id'";
    }

    $pdo = new PDO(
        // connect_timeout=5 ensures fast failure instead of 30s hang when DB unreachable
        "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . $ssl_param . $options_param . ";connect_timeout=5",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => true, // Required for Neon's PgBouncer transaction pooler
            PDO::ATTR_PERSISTENT         => false
        ]
    );

    // Ensure we are operating in the public schema (required for some connection pools like Neon)
    $pdo->exec("SET search_path TO public;");

} catch (PDOException $e) {
    // Don't die() — that kills PHP before Apache can respond to health checks
    // Instead serve a proper 503 so Railway health check gets a response
    $dbError = $e->getMessage();
    if (!defined('DB_ERROR')) define('DB_ERROR', $dbError);
    // Log the real error server-side
    error_log("Database Connection Error: " . $dbError);
}

// Session Management
// Security: Prevent browser caching of authenticated pages
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

if (session_status() === PHP_SESSION_NONE) {
    // Security: HttpOnly and SameSite attributes
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

/**
 * CSRF Protection
 */
function generateCsrfToken()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken($token)
{
    // Allow CLI scripts to bypass CSRF check
    if (php_sapi_name() === 'cli' || defined('TEST_MODE')) {
        return true;
    }

    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function getCsrfInput()
{
    $token = generateCsrfToken();
    return '<input type="hidden" name="csrf_token" value="' . $token . '">';
}

/**
 * Escape CSV field to prevent formula injection
 */
function escapeCsvField($field)
{
    if (empty($field))
        return '';
    $dangerous = ['=', '+', '-', '@'];
    if (in_array(substr($field, 0, 1), $dangerous)) {
        return "'" . $field;
    }
    return $field;
}

/**
 * Helper function to check login status
 */
function requireLogin()
{
    // Allow CLI scripts to bypass login check
    if (php_sapi_name() === 'cli' || defined('TEST_MODE')) {
        return;
    }

    if (!isset($_SESSION['user_id'])) {
        header("Location: " . BASE_URL . "/login");
        exit;
    }
}

function logAudit($pdo, $action, $details, $resource_type = 'system', $resource_id = null)
{
    try {
        $user_id = $_SESSION['user_id'] ?? null;
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action_type, details, resource_type, resource_id, ip_address, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$user_id, $action, $details, $resource_type, $resource_id, $ip]);
    } catch (Exception $e) {
        // Silent fail for logs
    }
}

/**
 * Auto-log a changelog entry from any module action.
 * Increments patch version automatically.
 */
function logChangelog($pdo, $change_type, $module, $title, $description = '')
{
    try {
        // Get last formal version
        $stmt = $pdo->query("SELECT version FROM changelog WHERE version IS NOT NULL AND TRIM(version) != '' AND version NOT LIKE '%-scan%' ORDER BY id DESC LIMIT 1");
        $last_ver = $stmt ? $stmt->fetchColumn() : null;

        if (!$last_ver || !preg_match('/^(\d+)\.(\d+)\.(\d+)$/', $last_ver, $m)) {
            $version = $last_ver ? $last_ver . '.1' : '1.15.3';
        } else {
            $version = $m[1] . '.' . $m[2] . '.' . ($m[3] + 1);
        }

        // Skip duplicate title on same day
        $check = $pdo->prepare("SELECT id FROM changelog WHERE title = ? AND change_date = CURRENT_DATE");
        $check->execute([$title]);
        if ($check->fetch()) {
            return; // Silently skip
        }

        $stmt = $pdo->prepare("INSERT INTO changelog (version, change_date, change_type, module, title, description) VALUES (?, CURRENT_DATE, ?, ?, ?, ?)");
        $stmt->execute([$version, $change_type, $module, $title, $description]);
    } catch (Exception $e) {
        // Silent fail — changelog should never break main operations
    }
}

/**
 * Get the latest application version from changelog
 */
function getAppVersion($pdo)
{
    try {
        $stmt = $pdo->query("SELECT version FROM changelog WHERE version IS NOT NULL AND TRIM(version) != '' AND version NOT LIKE '%-scan%' ORDER BY id DESC LIMIT 1");
        $ver = $stmt ? trim((string)$stmt->fetchColumn()) : '';
        if (!empty($ver)) {
            return str_starts_with($ver, 'v') ? $ver : 'v' . $ver;
        }
        return 'v1.15.6'; // Fallback current version
    } catch (Exception $e) {
        return 'v1.15.6'; // Fallback version
    }
}

/**
 * Automatically synchronize a computer workstation record to the asset registry
 */
function syncComputerToAsset($pdo, $computer_id)
{
    if (!$computer_id) return;
    try {
        $stmt = $pdo->prepare("
            INSERT INTO assets (
                control_id, department, end_user, source, mr_ics,
                brand_model, serial_number, processor, memory, storage,
                monitor, monitor_sn, printer, printer_sn, ups_avr, ups_avr_sn,
                keyboard_mouse, os_version, os_licensed, ip_address, mac_address,
                ms_office, ms_office_licensed, antivirus, scan_date,
                status, remarks, encoded_by, created_at, updated_at
            )
            SELECT 
                COALESCE(NULLIF(TRIM(c.control_number), ''), 'JBL-TEMP-' || c.id) AS control_id,
                c.department,
                c.end_user,
                'Purchased' AS source,
                c.mr_par AS mr_ics,
                c.system_unit AS brand_model,
                c.system_unit_sn AS serial_number,
                c.processor,
                c.memory,
                c.storage,
                c.monitor,
                c.monitor_sn,
                c.printer,
                c.printer_sn,
                c.avr_ups AS ups_avr,
                c.avr_ups_sn AS ups_avr_sn,
                TRIM(CONCAT_WS(' / ', NULLIF(c.keyboard, ''), NULLIF(c.mouse, ''))) AS keyboard_mouse,
                c.os AS os_version,
                (c.license = 'Y') AS os_licensed,
                c.ip_address,
                c.mac_address,
                c.microsoft_office AS ms_office,
                (c.microsoft_office IS NOT NULL AND c.microsoft_office != '' AND c.microsoft_office NOT ILIKE '%N/A%') AS ms_office_licensed,
                CASE WHEN c.endpoint_secure ILIKE 'Y%' THEN 'Athena EPP' ELSE 'Windows Defender' END AS antivirus,
                c.remarks_date AS scan_date,
                CASE 
                    WHEN c.status = 'Condemned' THEN 'Retired'
                    WHEN c.status = 'Transferred' THEN 'Maintenance'
                    WHEN c.status = 'Maintenance' THEN 'Maintenance'
                    ELSE 'Active'
                END AS status,
                c.remarks,
                c.encoded_by,
                c.created_at,
                c.updated_at
            FROM computers c
            WHERE c.id = ?
            ON CONFLICT (control_id) DO UPDATE SET
                department = EXCLUDED.department,
                end_user = EXCLUDED.end_user,
                mr_ics = EXCLUDED.mr_ics,
                brand_model = EXCLUDED.brand_model,
                serial_number = EXCLUDED.serial_number,
                processor = EXCLUDED.processor,
                memory = EXCLUDED.memory,
                storage = EXCLUDED.storage,
                monitor = EXCLUDED.monitor,
                monitor_sn = EXCLUDED.monitor_sn,
                printer = EXCLUDED.printer,
                printer_sn = EXCLUDED.printer_sn,
                ups_avr = EXCLUDED.ups_avr,
                ups_avr_sn = EXCLUDED.ups_avr_sn,
                keyboard_mouse = EXCLUDED.keyboard_mouse,
                os_version = EXCLUDED.os_version,
                os_licensed = EXCLUDED.os_licensed,
                ip_address = EXCLUDED.ip_address,
                mac_address = EXCLUDED.mac_address,
                ms_office = EXCLUDED.ms_office,
                ms_office_licensed = EXCLUDED.ms_office_licensed,
                antivirus = EXCLUDED.antivirus,
                scan_date = EXCLUDED.scan_date,
                status = EXCLUDED.status,
                remarks = EXCLUDED.remarks,
                encoded_by = EXCLUDED.encoded_by,
                updated_at = NOW()
        ");
        $stmt->execute([$computer_id]);
    } catch (Exception $e) {
        error_log("syncComputerToAsset Error: " . $e->getMessage());
    }
}

/**
 * Automatically synchronize computer IP & MAC address to the IP Address module (ips table).
 * Ensures status is set to 'active', updates workstation details and location,
 * and releases any previous IP if changed.
 */
/**
 * Release an IP address in the IP module:
 * Status becomes 'available', type becomes 'Other', and all other attributes are set to blank/NULL.
 */
function releaseComputerIpInIpModule($pdo, $ip, $control_number = null)
{
    $clean_ip = trim((string)$ip);
    if (empty($clean_ip) || !filter_var($clean_ip, FILTER_VALIDATE_IP)) {
        return;
    }
    try {
        $stmt = $pdo->prepare("
            UPDATE ips
            SET status = 'available',
                device_type = 'Other',
                mac_address = NULL,
                hostname = NULL,
                control_number = NULL,
                location = NULL,
                om_name = NULL,
                department = NULL,
                description = NULL,
                remarks = NULL,
                last_seen = NOW()
            WHERE ip_address = ?
        ");
        $stmt->execute([$clean_ip]);
        $ctrl_txt = $control_number ? " for Control #: " . trim((string)$control_number) : "";
        logAudit($pdo, 'release_ip', "Released IP $clean_ip (status: available, type: Other, fields cleared)$ctrl_txt", 'ip');
    } catch (Exception $e) {
        error_log("releaseComputerIpInIpModule error for IP $clean_ip: " . $e->getMessage());
    }
}

/**
 * Automatically update or transfer IP Address data in the IP Address module (ips table):
 * - If IP address is changed:
 *   Transfers original data (MAC Address, HOSTNAME, CONTROL NUMBER, LOCATION, OM) to the new IP address.
 *   Sets new IP status to 'active' (device_type: Workstation).
 *   Resets the previous IP address so only its IP address remains, status = 'available', type = 'Other', and the rest blank/NULL.
 * - If MAC address is changed on the same IP address:
 *   Updates MAC address and ensures status is 'active'.
 */
function syncComputerIpToIpModule($pdo, $ip, $mac, $control_number, $location = '', $department = '', $old_ip = null)
{
    $clean_ip   = trim((string)$ip);
    $clean_mac  = strtoupper(str_replace(':', '-', trim((string)$mac)));
    $clean_ctrl = trim((string)$control_number);
    $clean_loc  = trim((string)$location);
    $clean_dept = trim((string)$department);
    $clean_old_ip = !empty($old_ip) ? trim((string)$old_ip) : '';

    // If old_ip wasn't explicitly passed, check if this control_number is currently assigned to an IP in `ips`
    if (empty($clean_old_ip) && !empty($clean_ctrl)) {
        try {
            $find_old_stmt = $pdo->prepare("SELECT ip_address FROM ips WHERE control_number = ? AND ip_address != ? LIMIT 1");
            $find_old_stmt->execute([$clean_ctrl, $clean_ip]);
            $found_old = $find_old_stmt->fetchColumn();
            if ($found_old && filter_var($found_old, FILTER_VALIDATE_IP)) {
                $clean_old_ip = $found_old;
            }
        } catch (Exception $e_find) {}
    }

    $orig_data = null;

    // ── 1. If previous IP existed and is different from new IP, transfer data and release old IP ──
    if (!empty($clean_old_ip) && $clean_old_ip !== $clean_ip && filter_var($clean_old_ip, FILTER_VALIDATE_IP)) {
        try {
            // Fetch original data of old IP from ips table before clearing
            $orig_stmt = $pdo->prepare("SELECT * FROM ips WHERE ip_address = ? LIMIT 1");
            $orig_stmt->execute([$clean_old_ip]);
            $orig_data = $orig_stmt->fetch(PDO::FETCH_ASSOC);

            // Release previous IP: only keep IP, status = 'available', type = 'Other', rest blank
            $rel_stmt = $pdo->prepare("
                UPDATE ips
                SET status = 'available',
                    device_type = 'Other',
                    mac_address = NULL,
                    hostname = NULL,
                    control_number = NULL,
                    location = NULL,
                    om_name = NULL,
                    department = NULL,
                    description = NULL,
                    remarks = NULL,
                    last_seen = NOW()
                WHERE ip_address = ?
            ");
            $rel_stmt->execute([$clean_old_ip]);
            logAudit($pdo, 'release_ip', "Released previous IP $clean_old_ip (status: available, type: Other, fields cleared) transferred to $clean_ip for Control #: $clean_ctrl", 'ip');
        } catch (Exception $e_rel) {
            error_log("Failed to release old IP $clean_old_ip: " . $e_rel->getMessage());
        }
    }

    // Also check if any other IP in `ips` was holding this control_number
    if (!empty($clean_ctrl)) {
        try {
            $other_stmt = $pdo->prepare("SELECT * FROM ips WHERE control_number = ? AND ip_address != ?");
            $other_stmt->execute([$clean_ctrl, $clean_ip]);
            $other_ips = $other_stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($other_ips as $oip) {
                if (empty($orig_data)) {
                    $orig_data = $oip;
                }
                $pdo->prepare("
                    UPDATE ips
                    SET status = 'available',
                        device_type = 'Other',
                        mac_address = NULL,
                        hostname = NULL,
                        control_number = NULL,
                        location = NULL,
                        om_name = NULL,
                        department = NULL,
                        description = NULL,
                        remarks = NULL,
                        last_seen = NOW()
                    WHERE ip_address = ?
                ")->execute([$oip['ip_address']]);
                logAudit($pdo, 'release_ip', "Released conflicting previous IP {$oip['ip_address']} (status: available, type: Other, fields cleared) for Control #: $clean_ctrl", 'ip');
            }
        } catch (Exception $e_other) {}
    }

    // Only process valid IPv4/IPv6 addresses for the new IP
    if (empty($clean_ip) || !filter_var($clean_ip, FILTER_VALIDATE_IP)) {
        return;
    }

    // ── 2. Transfer original data to the new IP ──
    // MAC Address, HOSTNAME, CONTROL NUMBER, LOCATION, OM
    $trans_mac      = !empty($clean_mac) ? $clean_mac : ($orig_data['mac_address'] ?? '');
    $trans_hostname = !empty($orig_data['hostname']) ? $orig_data['hostname'] : '';
    $trans_ctrl     = !empty($clean_ctrl) ? $clean_ctrl : ($orig_data['control_number'] ?? '');
    $trans_loc      = !empty($clean_loc) ? $clean_loc : ($orig_data['location'] ?? '');
    $trans_om       = !empty($orig_data['om_name']) ? $orig_data['om_name'] : '';
    $trans_dept     = !empty($clean_dept) ? $clean_dept : ($orig_data['department'] ?? '');

    try {
        // ONLY update if this IP address exists in the pre-defined 2,032 IP pool!
        // NEVER insert new lines into the ips table.
        $check_stmt = $pdo->prepare("SELECT id FROM ips WHERE ip_address = ? LIMIT 1");
        $check_stmt->execute([$clean_ip]);
        $existing_ip_id = $check_stmt->fetchColumn();

        if ($existing_ip_id) {
            $update = $pdo->prepare("
                UPDATE ips SET
                    mac_address    = CASE WHEN :mac != '' THEN :mac ELSE ips.mac_address END,
                    hostname       = CASE WHEN :hostname != '' THEN :hostname ELSE ips.hostname END,
                    control_number = CASE WHEN :ctrl != '' THEN :ctrl ELSE ips.control_number END,
                    location       = CASE WHEN :loc != '' THEN :loc ELSE ips.location END,
                    om_name        = CASE WHEN :om_name != '' THEN :om_name ELSE ips.om_name END,
                    department     = CASE WHEN :dept != '' THEN :dept ELSE ips.department END,
                    status         = 'active',
                    device_type    = 'Workstation',
                    last_seen      = NOW()
                WHERE id = :id
            ");

            $update->execute([
                ':id'        => $existing_ip_id,
                ':mac'       => $trans_mac,
                ':hostname'  => $trans_hostname,
                ':ctrl'      => $trans_ctrl,
                ':loc'       => $trans_loc,
                ':om_name'   => $trans_om,
                ':dept'      => $trans_dept,
            ]);

            logAudit($pdo, 'sync_ip', "Updated existing IP $clean_ip (ID: $existing_ip_id, MAC: $trans_mac, Hostname: $trans_hostname, Ctrl: $trans_ctrl, Loc: $trans_loc, OM: $trans_om, Status: active, Type: Workstation)", 'ip');
        } else {
            // IP address does not exist in the 2,032 IP inventory pool: DO NOT insert!
            logAudit($pdo, 'sync_ip_skip', "IP $clean_ip not found in 2,032 IP pool for Control #: $clean_ctrl. Skipped inserting new line.", 'ip');
        }
    } catch (Exception $e) {
        error_log("syncComputerIpToIpModule error for IP $clean_ip: " . $e->getMessage());
    }
}

/**
 * Sync Satellite Computer IP to its corresponding Satellite IP Address Table
 * Satellite slug: 'jasmc_st', 'jasmc_vt', or 'doh_jbl_cmc'
 * Target table: ips_jasmc_st, ips_jasmc_vt, ips_doh_jbl_cmc
 */
function syncSatelliteComputerIpToIpModule($pdo, $satellite_slug, $ip, $mac, $control_number, $location = '', $department = '', $old_ip = null, $end_user = null)
{
    $table = "ips_" . $satellite_slug;
    $valid_tables = ['ips_jasmc_st', 'ips_jasmc_vt', 'ips_doh_jbl_cmc'];
    if (!in_array($table, $valid_tables)) {
        return;
    }

    $clean_ip   = trim((string)$ip);
    $clean_mac  = strtoupper(str_replace(':', '-', trim((string)$mac)));
    $clean_ctrl = trim((string)$control_number);
    $clean_loc  = trim((string)$location);
    $clean_dept = trim((string)$department);
    $clean_user = trim((string)$end_user);
    $clean_old_ip = !empty($old_ip) ? trim((string)$old_ip) : '';

    // If old_ip not explicitly passed, check if control_number has an existing IP in the satellite table
    if (empty($clean_old_ip) && !empty($clean_ctrl)) {
        try {
            $stmt = $pdo->prepare("SELECT ip_address FROM $table WHERE control_number = ? AND ip_address != ? LIMIT 1");
            $stmt->execute([$clean_ctrl, $clean_ip]);
            $found_old = $stmt->fetchColumn();
            if ($found_old && filter_var($found_old, FILTER_VALIDATE_IP)) {
                $clean_old_ip = $found_old;
            }
        } catch (Exception $e) {}
    }

    // 1. If old IP changed, release old IP in satellite table
    if (!empty($clean_old_ip) && $clean_old_ip !== $clean_ip && filter_var($clean_old_ip, FILTER_VALIDATE_IP)) {
        try {
            $rel = $pdo->prepare("
                UPDATE $table
                SET status = 'available',
                    device_type = 'Other',
                    mac_address = NULL,
                    hostname = NULL,
                    control_number = NULL,
                    location = NULL,
                    om_name = NULL,
                    department = NULL,
                    description = NULL,
                    remarks = NULL,
                    last_seen = NOW()
                WHERE ip_address = ?
            ");
            $rel->execute([$clean_old_ip]);
            logAudit($pdo, 'release_satellite_ip', "[$satellite_slug] Released previous IP $clean_old_ip for Control #: $clean_ctrl", 'satellite_ip');
        } catch (Exception $e) {
            error_log("Failed to release old satellite IP $clean_old_ip: " . $e->getMessage());
        }
    }

    // Release any other row in this satellite holding the control_number
    if (!empty($clean_ctrl)) {
        try {
            $rel_other = $pdo->prepare("
                UPDATE $table
                SET status = 'available',
                    device_type = 'Other',
                    mac_address = NULL,
                    hostname = NULL,
                    control_number = NULL,
                    location = NULL,
                    om_name = NULL,
                    department = NULL,
                    description = NULL,
                    remarks = NULL,
                    last_seen = NOW()
                WHERE control_number = ? AND ip_address != ?
            ");
            $rel_other->execute([$clean_ctrl, $clean_ip]);
        } catch (Exception $e) {}
    }

    if (empty($clean_ip) || !filter_var($clean_ip, FILTER_VALIDATE_IP)) {
        return;
    }

    // 2. Insert or update in satellite IP table
    try {
        $check = $pdo->prepare("SELECT id FROM $table WHERE ip_address = ? LIMIT 1");
        $check->execute([$clean_ip]);
        $existing_id = $check->fetchColumn();

        if ($existing_id) {
            $up = $pdo->prepare("
                UPDATE $table SET
                    mac_address    = CASE WHEN :mac != '' THEN :mac ELSE mac_address END,
                    control_number = CASE WHEN :ctrl != '' THEN :ctrl ELSE control_number END,
                    location       = CASE WHEN :loc != '' THEN :loc ELSE location END,
                    om_name        = CASE WHEN :om != '' THEN :om ELSE om_name END,
                    department     = CASE WHEN :dept != '' THEN :dept ELSE department END,
                    status         = 'active',
                    device_type    = 'Workstation',
                    last_seen      = NOW()
                WHERE id = :id
            ");
            $up->execute([
                ':id'   => $existing_id,
                ':mac'  => $clean_mac,
                ':ctrl' => $clean_ctrl,
                ':loc'  => $clean_loc,
                ':om'   => $clean_user,
                ':dept' => $clean_dept
            ]);
            logAudit($pdo, 'sync_satellite_ip', "[$satellite_slug] Updated existing IP $clean_ip for Control #: $clean_ctrl", 'satellite_ip');
        } else {
            $ins = $pdo->prepare("
                INSERT INTO $table (
                    ip_address, mac_address, control_number, location, om_name, department, status, device_type, last_seen
                ) VALUES (
                    :ip, :mac, :ctrl, :loc, :om, :dept, 'active', 'Workstation', NOW()
                )
            ");
            $ins->execute([
                ':ip'   => $clean_ip,
                ':mac'  => $clean_mac,
                ':ctrl' => $clean_ctrl,
                ':loc'  => $clean_loc,
                ':om'   => $clean_user,
                ':dept' => $clean_dept
            ]);
            logAudit($pdo, 'sync_satellite_ip', "[$satellite_slug] Inserted new IP $clean_ip for Control #: $clean_ctrl", 'satellite_ip');
        }
    } catch (Exception $e) {
        error_log("syncSatelliteComputerIpToIpModule error for IP $clean_ip in $table: " . $e->getMessage());
    }
}

/**
 * Release Satellite Computer IP
 */
function releaseSatelliteComputerIp($pdo, $satellite_slug, $control_number, $ip = null)
{
    $table = "ips_" . $satellite_slug;
    $valid_tables = ['ips_jasmc_st', 'ips_jasmc_vt', 'ips_doh_jbl_cmc'];
    if (!in_array($table, $valid_tables)) {
        return;
    }
    try {
        if (!empty($ip)) {
            $stmt = $pdo->prepare("UPDATE $table SET status = 'available', device_type = 'Other', mac_address = NULL, hostname = NULL, control_number = NULL, location = NULL, om_name = NULL, department = NULL, description = NULL, remarks = NULL, last_seen = NOW() WHERE ip_address = ?");
            $stmt->execute([$ip]);
        } elseif (!empty($control_number)) {
            $stmt = $pdo->prepare("UPDATE $table SET status = 'available', device_type = 'Other', mac_address = NULL, hostname = NULL, control_number = NULL, location = NULL, om_name = NULL, department = NULL, description = NULL, remarks = NULL, last_seen = NOW() WHERE control_number = ?");
            $stmt->execute([$control_number]);
        }
    } catch (Exception $e) {}
}

/**
 * Common Status Color Helpers
 */
// Routers & Switches
function getStatusColor($status)
{
    switch (strtolower($status)) {
        case 'active':
            return 'text-[#10b981]';
        case 'maintenance':
            return 'text-[#fbbf24]';
        case 'inactive':
            return 'text-slate-500';
        default:
            return 'text-slate-400';
    }
}
function getStatusDotClass($status)
{
    switch (strtolower($status)) {
        case 'active':
            return 'bg-[#10b981] shadow-lg shadow-[#10b981]/50';
        case 'maintenance':
            return 'bg-[#fbbf24] shadow-lg shadow-[#fbbf24]/50';
        case 'inactive':
            return 'bg-slate-500';
        default:
            return 'bg-slate-400';
    }
}

// Switches (Aliases for compatibility)
function getSwitchStatusColor($status)
{
    return getStatusColor($status);
}
function getSwitchStatusDotClass($status)
{
    return getStatusDotClass($status);
}

// IP Addresses
function getIpStatusColor($status)
{
    switch (strtolower($status)) {
        case 'active':
            return 'text-[#10b981]';
        case 'reserved':
            return 'text-blue-500';
        case 'offline':
            return 'text-slate-500';
        case 'available':
            return 'text-cyan-400';
        case 'conflict':
            return 'text-[#fa6238]'; // legacy fallback
        default:
            return 'text-slate-400';
    }
}
function getIpDotClass($status)
{
    switch (strtolower($status)) {
        case 'active':
            return 'bg-[#10b981] shadow-lg shadow-[#10b981]/50';
        case 'reserved':
            return 'bg-blue-500';
        case 'offline':
            return 'bg-slate-500';
        case 'available':
            return 'bg-cyan-400';
        case 'conflict':
            return 'bg-[#fa6238] animate-pulse'; // legacy fallback
        default:
            return 'bg-slate-400';
    }
}

/**
 * Check User Module Access
 */
function canAccessModule($module)
{
    // If not logged in or no permissions set, default allow (or handle in requireLogin)
    if (!isset($_SESSION['module_access']) || $_SESSION['module_access'] === null) {
        return true;
    }

    $allowed_modules = json_decode($_SESSION['module_access'], true);
    if (!is_array($allowed_modules)) {
        return true;
    }

    // Admin always has access to settings, but maybe not other things if strictly restricted?
    // Let's assume the session list is the source of truth.

    return in_array($module, $allowed_modules);
}
?>