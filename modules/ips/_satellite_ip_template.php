<?php
/**
 * Shared Satellite IP Address Module Controller & View Template
 * Included by:
 *   - modules/ips_jasmc_st/index.php
 *   - modules/ips_jasmc_vt/index.php
 *   - modules/ips_doh_jbl_cmc/index.php
 *
 * Required variables from caller:
 *   $MODULE_TABLE     - e.g. 'ips_jasmc_st'
 *   $MODULE_LABEL     - e.g. 'JASMC-ST'
 *   $MODULE_FULL_NAME - e.g. 'JASMC &mdash; Sto. Tomas'
 *   $MODULE_SLUG      - e.g. 'ips_jasmc_st'
 *   $SATELLITE_KEY    - e.g. 'jasmc_st'
 *   $MODULE_PATH      - e.g. BASE_URL . '/modules/ips_jasmc_st/'
 *   $COMPUTERS_TABLE  - e.g. 'computers_jasmc_st'
 *   $COMPUTERS_PATH   - e.g. BASE_URL . '/modules/jasmc_st/'
 */

if (!defined('BASE_URL')) {
    exit('Direct access not permitted.');
}

// ── 1. Excel Export (.xlsx) ───────────────────────────────────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    try {
        $stmt = $pdo->prepare("SELECT * FROM $MODULE_TABLE ORDER BY id ASC");
        $stmt->execute();
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $xmlEsc = fn($v) => htmlspecialchars((string)$v, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $sharedStrings = [];
        $ssi = function(string $s) use (&$sharedStrings): int {
            $key = array_search($s, $sharedStrings, true);
            if ($key === false) { $sharedStrings[] = $s; $key = count($sharedStrings) - 1; }
            return $key;
        };

        $headers = ['Department', 'Location', 'IP Address', 'MAC Address', 'Hostname', 'Control Number', 'End User / OM Name', 'Status', 'Device Type', 'Description', 'Remarks', 'Last Seen'];
        $cols = ['A','B','C','D','E','F','G','H','I','J','K','L'];

        $rows = [];
        $headerRow = [];
        foreach ($headers as $h) { $headerRow[] = ['t'=>'s','v'=>$ssi($h)]; }
        $rows[] = $headerRow;

        foreach ($items as $item) {
            $rows[] = [
                ['t'=>'s','v'=>$ssi($item['department']     ?? '')],
                ['t'=>'s','v'=>$ssi($item['location']       ?? '')],
                ['t'=>'s','v'=>$ssi($item['ip_address']     ?? '')],
                ['t'=>'s','v'=>$ssi($item['mac_address']    ?? '')],
                ['t'=>'s','v'=>$ssi($item['hostname']       ?? '')],
                ['t'=>'s','v'=>$ssi($item['control_number'] ?? '')],
                ['t'=>'s','v'=>$ssi($item['om_name']        ?? '')],
                ['t'=>'s','v'=>$ssi($item['status']         ?? '')],
                ['t'=>'s','v'=>$ssi($item['device_type']    ?? '')],
                ['t'=>'s','v'=>$ssi($item['description']    ?? '')],
                ['t'=>'s','v'=>$ssi($item['remarks']        ?? '')],
                ['t'=>'s','v'=>$ssi($item['last_seen']      ?? '')],
            ];
        }

        $sheetXml  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $sheetXml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
        $sheetXml .= '<sheetData>';
        foreach ($rows as $ri => $row) {
            $rIdx = $ri + 1;
            $sheetXml .= "<row r=\"$rIdx\">";
            foreach ($row as $ci => $cell) {
                $ref = $cols[$ci] . $rIdx;
                $sheetXml .= "<c r=\"$ref\" t=\"s\"><v>{$cell['v']}</v></c>";
            }
            $sheetXml .= '</row>';
        }
        $sheetXml .= '</sheetData></worksheet>';

        $ssXml  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $ssXml .= '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="'.count($sharedStrings).'" uniqueCount="'.count($sharedStrings).'">';
        foreach ($sharedStrings as $s) { $ssXml .= '<si><t xml:space="preserve">'.$xmlEsc($s).'</t></si>'; }
        $ssXml .= '</sst>';

        $stylesXml  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $stylesXml .= '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
        $stylesXml .= '<fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts>';
        $stylesXml .= '<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>';
        $stylesXml .= '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>';
        $stylesXml .= '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>';
        $stylesXml .= '<cellXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/></cellXfs>';
        $stylesXml .= '</styleSheet>';

        $workbookXml  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $workbookXml .= '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
        $workbookXml .= '<sheets><sheet name="'.$MODULE_LABEL.' IPs" sheetId="1" r:id="rId1"/></sheets></workbook>';

        $wbRels  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $wbRels .= '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
        $wbRels .= '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>';
        $wbRels .= '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>';
        $wbRels .= '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
        $wbRels .= '</Relationships>';

        $rootRels  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $rootRels .= '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
        $rootRels .= '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>';
        $rootRels .= '</Relationships>';

        $contentTypes  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $contentTypes .= '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">';
        $contentTypes .= '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>';
        $contentTypes .= '<Default Extension="xml" ContentType="application/xml"/>';
        $contentTypes .= '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>';
        $contentTypes .= '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        $contentTypes .= '<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>';
        $contentTypes .= '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
        $contentTypes .= '</Types>';

        $tmpFile = tempnam(sys_get_temp_dir(), 'ips_') . '.xlsx';
        $zip = new ZipArchive();
        $zip->open($tmpFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml',       $contentTypes);
        $zip->addFromString('_rels/.rels',               $rootRels);
        $zip->addFromString('xl/workbook.xml',           $workbookXml);
        $zip->addFromString('xl/_rels/workbook.xml.rels',$wbRels);
        $zip->addFromString('xl/worksheets/sheet1.xml',  $sheetXml);
        $zip->addFromString('xl/sharedStrings.xml',      $ssXml);
        $zip->addFromString('xl/styles.xml',             $stylesXml);
        $zip->close();

        $filename = strtolower($MODULE_LABEL) . '_ips_export_' . date('Y-m-d_His') . '.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="'.$filename.'"');
        header('Content-Length: ' . filesize($tmpFile));
        readfile($tmpFile);
        unlink($tmpFile);
        exit;
    } catch (Exception $e) {
        header('HTTP/1.1 500 Internal Server Error');
        echo 'Export error: ' . htmlspecialchars($e->getMessage());
        exit;
    }
}

// ── 3. CSV Export (.csv) ──────────────────────────────────────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    try {
        $stmt = $pdo->prepare("SELECT * FROM $MODULE_TABLE ORDER BY id ASC");
        $stmt->execute();
        $ips = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $filename = strtolower($MODULE_LABEL) . '_ips_export_' . date('Y-m-d_His') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $output = fopen('php://output', 'w');
        fputs($output, "\xEF\xBB\xBF");
        fputcsv($output, ['ID', 'Department', 'Location', 'IP Address', 'MAC Address', 'Hostname', 'Control Number', 'End User', 'Status', 'Device Type', 'Description', 'Remarks', 'Last Seen']);

        foreach ($ips as $ip) {
            fputcsv($output, [
                $ip['id'],
                $ip['department']     ?? '',
                $ip['location']       ?? '',
                $ip['ip_address']     ?? '',
                $ip['mac_address']    ?? '',
                $ip['hostname']       ?? '',
                $ip['control_number'] ?? '',
                $ip['om_name']        ?? '',
                $ip['status']         ?? '',
                $ip['device_type']    ?? '',
                $ip['description']    ?? '',
                $ip['remarks']        ?? '',
                $ip['last_seen']      ?? '',
            ]);
        }
        fclose($output);
        exit;
    } catch (Exception $e) {
        header('HTTP/1.1 500 Internal Server Error');
        echo 'Export error: ' . htmlspecialchars($e->getMessage());
        exit;
    }
}

// ── 4. CSV Template Download ──────────────────────────────────────────────────
if (isset($_GET['download_template']) && $_GET['download_template'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="'.$SATELLITE_KEY.'_ips_template.csv"');
    $output = fopen('php://output', 'w');
    fputs($output, "\xEF\xBB\xBF");
    fputcsv($output, ['IP Address', 'MAC Address', 'Hostname', 'Control Number', 'Department', 'Location', 'End User', 'Status', 'Device Type', 'Description', 'Remarks']);
    fputcsv($output, ['192.170.10.100', '00-1A-2B-3C-4D-5E', 'SATELLITE-PC01', $MODULE_LABEL.'-PC01', $MODULE_LABEL.' / ADMIN', $MODULE_LABEL.' GF', 'Juan Dela Cruz', 'active', 'Workstation', 'Admin PC', 'In service']);
    fclose($output);
    exit;
}

// ── 5. AJAX Ping Handler ──────────────────────────────────────────────────────
if (isset($_GET['ajax_ping'])) {
    header('Content-Type: application/json');
    $ip = trim($_GET['ajax_ping']);
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid IP address']);
        exit;
    }
    // Ping on Windows: -n 1 -w 1000
    $cmd = "ping -n 1 -w 1000 " . escapeshellarg($ip);
    exec($cmd, $ping_output, $ping_result);
    $is_online = ($ping_result === 0);

    // Update status in satellite table
    if ($is_online) {
        $up = $pdo->prepare("UPDATE $MODULE_TABLE SET status = 'active', last_seen = NOW() WHERE ip_address = ?");
        $up->execute([$ip]);
    } else {
        $up = $pdo->prepare("UPDATE $MODULE_TABLE SET status = CASE WHEN status = 'available' THEN 'available' ELSE 'offline' END WHERE ip_address = ?");
        $up->execute([$ip]);
    }
    echo json_encode([
        'status'  => 'success',
        'online'  => $is_online,
        'ip'      => $ip,
        'message' => $is_online ? "IP $ip is ONLINE" : "IP $ip is OFFLINE / unreachable"
    ]);
    exit;
}

// ── 6. Form Handlers (POST) ───────────────────────────────────────────────────
$success_msg = $error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $error_msg = "Security validation failed. Please refresh the page and try again.";
    } elseif ($_POST['action'] === 'add_ip') {
        $ip_addr   = trim($_POST['ip_address'] ?? '');
        $mac_addr  = strtoupper(str_replace(':', '-', trim($_POST['mac_address'] ?? '')));
        $hostname  = trim($_POST['hostname'] ?? '');
        $control_num = trim($_POST['control_number'] ?? '');
        $dept      = trim($_POST['department'] ?? '');
        $loc       = trim($_POST['location'] ?? '');
        $om_name   = trim($_POST['om_name'] ?? '');
        $status    = trim($_POST['status'] ?? 'available');
        $dev_type  = trim($_POST['device_type'] ?? 'Workstation');
        $desc      = trim($_POST['description'] ?? '');
        $remarks   = trim($_POST['remarks'] ?? '');

        if (empty($ip_addr) || !filter_var($ip_addr, FILTER_VALIDATE_IP)) {
            $error_msg = "Please enter a valid IPv4 address.";
        } else {
            // Check uniqueness in satellite table
            $check = $pdo->prepare("SELECT COUNT(*) FROM $MODULE_TABLE WHERE ip_address = ?");
            $check->execute([$ip_addr]);
            if ($check->fetchColumn() > 0) {
                $error_msg = "IP Address $ip_addr already exists in $MODULE_LABEL IP inventory.";
            } else {
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO $MODULE_TABLE (
                            ip_address, mac_address, hostname, control_number, department, location, om_name, status, device_type, description, remarks, last_seen
                        ) VALUES (
                            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW()
                        )
                    ");
                    $stmt->execute([$ip_addr, $mac_addr, $hostname, $control_num, $dept, $loc, $om_name, $status, $dev_type, $desc, $remarks]);

                    // Sync to Satellite Computer Inventory if control number provided
                    if (!empty($control_num)) {
                        $comp_check = $pdo->prepare("SELECT id FROM $COMPUTERS_TABLE WHERE control_number = ? LIMIT 1");
                        $comp_check->execute([$control_num]);
                        $comp_id = $comp_check->fetchColumn();
                        if ($comp_id) {
                            $comp_up = $pdo->prepare("UPDATE $COMPUTERS_TABLE SET ip_address = ?, mac_address = CASE WHEN ? != '' THEN ? ELSE mac_address END WHERE id = ?");
                            $comp_up->execute([$ip_addr, $mac_addr, $mac_addr, $comp_id]);
                        }
                    }

                    logAudit($pdo, 'add_satellite_ip', "[$MODULE_LABEL] Added IP $ip_addr (Ctrl: $control_num, Status: $status, Type: $dev_type)", 'satellite_ip');
                    $success_msg = "IP address $ip_addr added successfully.";
                } catch (Exception $e) {
                    $error_msg = "Error adding IP: " . $e->getMessage();
                }
            }
        }
    } elseif ($_POST['action'] === 'edit_ip') {
        $id        = (int)($_POST['id'] ?? 0);
        $ip_addr   = trim($_POST['ip_address'] ?? '');
        $mac_addr  = strtoupper(str_replace(':', '-', trim($_POST['mac_address'] ?? '')));
        $hostname  = trim($_POST['hostname'] ?? '');
        $control_num = trim($_POST['control_number'] ?? '');
        $dept      = trim($_POST['department'] ?? '');
        $loc       = trim($_POST['location'] ?? '');
        $om_name   = trim($_POST['om_name'] ?? '');
        $status    = trim($_POST['status'] ?? 'available');
        $dev_type  = trim($_POST['device_type'] ?? 'Workstation');
        $desc      = trim($_POST['description'] ?? '');
        $remarks   = trim($_POST['remarks'] ?? '');

        if (!$id || empty($ip_addr) || !filter_var($ip_addr, FILTER_VALIDATE_IP)) {
            $error_msg = "Invalid IP ID or address.";
        } else {
            // Check uniqueness if IP changed
            $check = $pdo->prepare("SELECT id FROM $MODULE_TABLE WHERE ip_address = ? AND id != ?");
            $check->execute([$ip_addr, $id]);
            if ($check->fetchColumn()) {
                $error_msg = "IP Address $ip_addr is already in use by another record.";
            } else {
                try {
                    $old_stmt = $pdo->prepare("SELECT * FROM $MODULE_TABLE WHERE id = ?");
                    $old_stmt->execute([$id]);
                    $old_row = $old_stmt->fetch(PDO::FETCH_ASSOC);

                    $stmt = $pdo->prepare("
                        UPDATE $MODULE_TABLE SET
                            ip_address     = ?,
                            mac_address    = ?,
                            hostname       = ?,
                            control_number = ?,
                            department     = ?,
                            location       = ?,
                            om_name        = ?,
                            status         = ?,
                            device_type    = ?,
                            description    = ?,
                            remarks        = ?,
                            last_seen      = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$ip_addr, $mac_addr, $hostname, $control_num, $dept, $loc, $om_name, $status, $dev_type, $desc, $remarks, $id]);

                    // Sync to Satellite Computer Inventory if control number exists
                    if (!empty($control_num)) {
                        $comp_check = $pdo->prepare("SELECT id FROM $COMPUTERS_TABLE WHERE control_number = ? LIMIT 1");
                        $comp_check->execute([$control_num]);
                        $comp_id = $comp_check->fetchColumn();
                        if ($comp_id) {
                            $comp_up = $pdo->prepare("UPDATE $COMPUTERS_TABLE SET ip_address = ?, mac_address = CASE WHEN ? != '' THEN ? ELSE mac_address END WHERE id = ?");
                            $comp_up->execute([$ip_addr, $mac_addr, $mac_addr, $comp_id]);
                        }
                    }

                    logAudit($pdo, 'edit_satellite_ip', "[$MODULE_LABEL] Updated IP $ip_addr (ID: $id, Ctrl: $control_num, Status: $status, Type: $dev_type)", 'satellite_ip', $id);
                    $success_msg = "IP address $ip_addr updated successfully.";
                } catch (Exception $e) {
                    $error_msg = "Error updating IP: " . $e->getMessage();
                }
            }
        }
    } elseif ($_POST['action'] === 'delete_ip') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            try {
                $stmt = $pdo->prepare("SELECT ip_address, control_number FROM $MODULE_TABLE WHERE id = ?");
                $stmt->execute([$id]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($row) {
                    $pdo->prepare("DELETE FROM $MODULE_TABLE WHERE id = ?")->execute([$id]);

                    // Release in computer table if it held this IP
                    if (!empty($row['control_number'])) {
                        $pdo->prepare("UPDATE $COMPUTERS_TABLE SET ip_address = NULL WHERE control_number = ? AND ip_address = ?")
                            ->execute([$row['control_number'], $row['ip_address']]);
                    }

                    logAudit($pdo, 'delete_satellite_ip', "[$MODULE_LABEL] Deleted IP {$row['ip_address']} (ID: $id)", 'satellite_ip', $id);
                    $success_msg = "IP address {$row['ip_address']} deleted successfully.";
                }
            } catch (Exception $e) {
                $error_msg = "Error deleting IP: " . $e->getMessage();
            }
        }
    } elseif ($_POST['action'] === 'batch_delete') {
        $ids = array_filter(array_map('intval', explode(',', $_POST['selected_ids'] ?? '')));
        if (!empty($ids)) {
            try {
                $ph = implode(',', array_fill(0, count($ids), '?'));
                $del_stmt = $pdo->prepare("DELETE FROM $MODULE_TABLE WHERE id IN ($ph)");
                $del_stmt->execute($ids);
                $count = $del_stmt->rowCount();
                logAudit($pdo, 'batch_delete_satellite_ip', "[$MODULE_LABEL] Bulk deleted $count IPs", 'satellite_ip');
                $success_msg = "Successfully deleted $count IP addresses.";
            } catch (Exception $e) {
                $error_msg = "Error deleting selected IPs: " . $e->getMessage();
            }
        }
    } elseif ($_POST['action'] === 'batch_update_status') {
        $ids = array_filter(array_map('intval', explode(',', $_POST['selected_ids'] ?? '')));
        $new_status = trim($_POST['batch_status'] ?? '');
        if (!empty($ids) && in_array($new_status, ['active', 'available', 'offline', 'maintenance', 'reserved'])) {
            try {
                $ph = implode(',', array_fill(0, count($ids), '?'));
                $params = array_merge([$new_status], $ids);
                $up_stmt = $pdo->prepare("UPDATE $MODULE_TABLE SET status = ?, last_seen = NOW() WHERE id IN ($ph)");
                $up_stmt->execute($params);
                $count = $up_stmt->rowCount();
                logAudit($pdo, 'batch_update_satellite_ip_status', "[$MODULE_LABEL] Updated status to $new_status for $count IPs", 'satellite_ip');
                $success_msg = "Updated status to " . ucfirst($new_status) . " for $count IP addresses.";
            } catch (Exception $e) {
                $error_msg = "Error updating status: " . $e->getMessage();
            }
        }
    } elseif ($_POST['action'] === 'import_csv') {
        if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
            $handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
            if ($handle !== false) {
                // Strip UTF-8 BOM if present
                $bom = fread($handle, 3);
                if ($bom !== "\xEF\xBB\xBF") {
                    rewind($handle);
                }

                $header = fgetcsv($handle);
                if ($header !== false) {
                    $header_map = [];
                    foreach ($header as $idx => $col_name) {
                        $col_clean = strtolower(trim(preg_replace('/[^a-zA-Z0-9_]/', '_', $col_name)));
                        $header_map[$col_clean] = $idx;
                    }

                    $imported = 0;
                    $updated = 0;
                    $skipped = 0;

                    while (($row = fgetcsv($handle)) !== false) {
                        if (empty(array_filter($row))) continue;

                        $get = fn($keys) => (function() use ($keys, $header_map, $row) {
                            foreach ((array)$keys as $k) {
                                if (isset($header_map[$k]) && isset($row[$header_map[$k]])) {
                                    return trim($row[$header_map[$k]]);
                                }
                            }
                            return '';
                        })();

                        $ip_val   = $get(['ip_address', 'ip', 'ipaddress']);
                        $mac_val  = strtoupper(str_replace(':', '-', $get(['mac_address', 'mac', 'macaddress'])));
                        $ctrl_val = $get(['control_number', 'control', 'control_no', 'control_id']);
                        $dept_val = $get(['department', 'dept']);
                        $loc_val  = $get(['location', 'loc']) ?: $dept_val;
                        $user_val = $get(['end_user', 'om_name', 'user_name', 'user', 'om']);
                        $host_val = $get(['hostname', 'host']);
                        $stat_val = strtolower($get(['status'])) ?: 'active';
                        $dev_val  = $get(['device_type', 'type']) ?: 'Workstation';
                        $desc_val = $get(['description', 'desc']);
                        $rem_val  = $get(['remarks', 'remark']);

                        if (empty($ip_val) || !filter_var($ip_val, FILTER_VALIDATE_IP)) {
                            $skipped++;
                            continue;
                        }

                        if (!in_array($stat_val, ['active', 'available', 'offline', 'maintenance', 'reserved'])) {
                            $stat_val = 'active';
                        }

                        try {
                            // Upsert into satellite table
                            $check = $pdo->prepare("SELECT id FROM $MODULE_TABLE WHERE ip_address = ?");
                            $check->execute([$ip_val]);
                            $existing_id = $check->fetchColumn();

                            if ($existing_id) {
                                $up = $pdo->prepare("
                                    UPDATE $MODULE_TABLE SET
                                        mac_address    = CASE WHEN ? != '' THEN ? ELSE mac_address END,
                                        hostname       = CASE WHEN ? != '' THEN ? ELSE hostname END,
                                        control_number = CASE WHEN ? != '' THEN ? ELSE control_number END,
                                        department     = CASE WHEN ? != '' THEN ? ELSE department END,
                                        location       = CASE WHEN ? != '' THEN ? ELSE location END,
                                        om_name        = CASE WHEN ? != '' THEN ? ELSE om_name END,
                                        status         = ?,
                                        device_type    = ?,
                                        description    = CASE WHEN ? != '' THEN ? ELSE description END,
                                        remarks        = CASE WHEN ? != '' THEN ? ELSE remarks END,
                                        last_seen      = NOW()
                                    WHERE id = ?
                                ");
                                $up->execute([
                                    $mac_val, $mac_val, $host_val, $host_val, $ctrl_val, $ctrl_val,
                                    $dept_val, $dept_val, $loc_val, $loc_val, $user_val, $user_val,
                                    $stat_val, $dev_val, $desc_val, $desc_val, $rem_val, $rem_val, $existing_id
                                ]);
                                $updated++;
                            } else {
                                $ins = $pdo->prepare("
                                    INSERT INTO $MODULE_TABLE (
                                        ip_address, mac_address, hostname, control_number, department, location, om_name, status, device_type, description, remarks, last_seen
                                    ) VALUES (
                                        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW()
                                    )
                                ");
                                $ins->execute([$ip_val, $mac_val, $host_val, $ctrl_val, $dept_val, $loc_val, $user_val, $stat_val, $dev_val, $desc_val, $rem_val]);
                                $imported++;
                            }

                            // Sync computer inventory if control number present
                            if (!empty($ctrl_val)) {
                                $comp_up = $pdo->prepare("UPDATE $COMPUTERS_TABLE SET ip_address = ?, mac_address = CASE WHEN ? != '' THEN ? ELSE mac_address END WHERE control_number = ?");
                                $comp_up->execute([$ip_val, $mac_val, $mac_val, $ctrl_val]);
                            }
                        } catch (Exception $e) {
                            $skipped++;
                        }
                    }
                    fclose($handle);
                    logAudit($pdo, 'import_satellite_csv', "[$MODULE_LABEL] Imported $imported new, updated $updated, skipped $skipped records", 'satellite_ip');
                    $success_msg = "CSV Processed: $imported new IPs imported, $updated updated" . ($skipped > 0 ? ", $skipped skipped" : "") . ".";
                }
            } else {
                $error_msg = "Unable to open uploaded CSV file.";
            }
        } else {
            $error_msg = "Please select a valid CSV file to upload.";
        }
    }
}

// ── 7. Filtering, Searching & Pagination ──────────────────────────────────────
$search      = trim($_GET['search'] ?? '');
if (strpos($search, ':') !== false) {
    $search = str_replace(':', '-', $search);
}
$status_f    = trim($_GET['status'] ?? 'All');
$dev_type_f  = trim($_GET['device_type'] ?? 'All');
$dept_f      = trim($_GET['department'] ?? 'All');
$sort_col    = trim($_GET['sort'] ?? 'id_asc');
$limit       = isset($_GET['limit']) ? ($_GET['limit'] === 'all' ? 1000 : max(5, (int)$_GET['limit'])) : 15;
$page        = max(1, (int)($_GET['page'] ?? 1));
$offset      = ($page - 1) * $limit;

$where_clauses = ['1=1'];
$params = [];

if ($search !== '') {
    $where_clauses[] = "(
        ip_address ILIKE ? OR 
        mac_address ILIKE ? OR 
        hostname ILIKE ? OR 
        control_number ILIKE ? OR 
        om_name ILIKE ? OR 
        department ILIKE ? OR 
        location ILIKE ? OR
        remarks ILIKE ?
    )";
    $term = "%$search%";
    for ($i = 0; $i < 8; $i++) $params[] = $term;
}

if ($status_f !== 'All') {
    $where_clauses[] = "status = ?";
    $params[] = $status_f;
}

if ($dev_type_f !== 'All') {
    $where_clauses[] = "device_type = ?";
    $params[] = $dev_type_f;
}

if ($dept_f !== 'All') {
    $where_clauses[] = "department = ?";
    $params[] = $dept_f;
}

$where_sql = implode(' AND ', $where_clauses);

// Sort order
$order_by = match ($sort_col) {
    'ip_asc'       => 'ip_address ASC',
    'ip_desc'      => 'ip_address DESC',
    'ctrl_asc'     => 'control_number ASC',
    'dept_asc'     => 'department ASC',
    'status_asc'   => 'status ASC',
    'last_seen'    => 'last_seen DESC NULLS LAST',
    'id_desc'      => 'id DESC',
    default        => 'id ASC',
};

// Total count matching query
$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM $MODULE_TABLE WHERE $where_sql");
$count_stmt->execute($params);
$total_records = (int)$count_stmt->fetchColumn();
$total_pages = max(1, ceil($total_records / $limit));

// Fetch records
$sql = "SELECT * FROM $MODULE_TABLE WHERE $where_sql ORDER BY $order_by LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Overall Stats for Cards
$stat_total     = (int)$pdo->query("SELECT COUNT(*) FROM $MODULE_TABLE")->fetchColumn();
$stat_active    = (int)$pdo->query("SELECT COUNT(*) FROM $MODULE_TABLE WHERE status = 'active'")->fetchColumn();
$stat_available = (int)$pdo->query("SELECT COUNT(*) FROM $MODULE_TABLE WHERE status = 'available'")->fetchColumn();
$stat_offline   = (int)$pdo->query("SELECT COUNT(*) FROM $MODULE_TABLE WHERE status = 'offline'")->fetchColumn();
$stat_workstn   = (int)$pdo->query("SELECT COUNT(*) FROM $MODULE_TABLE WHERE device_type = 'Workstation'")->fetchColumn();

// Unique departments for filter dropdown
$departments = $pdo->query("SELECT DISTINCT department FROM $MODULE_TABLE WHERE department IS NOT NULL AND department != '' ORDER BY department")->fetchAll(PDO::FETCH_COLUMN);

// ── 8. Render View ────────────────────────────────────────────────────────────
$page_title = "$MODULE_LABEL IP Addresses";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="p-4 md:p-8 flex-1 overflow-y-auto custom-scrollbar relative z-10 bg-slate-50 dark:bg-background-dark">
    <?php 
    $active_ip_tab = $SATELLITE_KEY;
    require __DIR__ . '/../../includes/satellite_ip_tabs.php';
    ?>

    <!-- Header -->
    <header class="mb-6 flex flex-col md:flex-row items-start md:items-end justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <span class="px-2 py-0.5 rounded text-[11px] font-bold tracking-wider uppercase bg-amber-500/10 text-amber-500 border border-amber-500/20">Satellite IP</span>
                <h2 class="text-2xl font-bold text-slate-900 dark:text-white"><?= $MODULE_LABEL ?> IP Address Inventory</h2>
            </div>
            <p class="text-xs text-slate-500 mt-1 flex items-center gap-2">
                <span>Satellite IP addresses for <?= $MODULE_FULL_NAME ?>.</span>
                <span class="text-slate-300 dark:text-slate-600">&bull;</span>
                <a href="<?= $COMPUTERS_PATH ?>" class="text-primary hover:underline font-semibold inline-flex items-center gap-1">
                    <span>View <?= $MODULE_LABEL ?> Computer Inventory</span>
                    <span class="material-symbols-outlined text-[13px]">arrow_forward</span>
                </a>
            </p>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            <button onclick="openModal('addIpModal')"
                class="flex items-center gap-1.5 rounded-lg h-9 px-3.5 bg-primary hover:bg-primary-hover text-white text-xs font-bold transition-all shadow-sm">
                <span class="material-symbols-outlined text-[17px]">add</span>
                <span>Add IP</span>
            </button>
            <button onclick="openModal('importModal')"
                class="flex items-center gap-1.5 rounded-lg h-9 px-3 bg-amber-500 hover:bg-amber-600 text-white text-xs font-bold transition-all shadow-sm">
                <span class="material-symbols-outlined text-[17px]">upload_file</span>
                <span>Import CSV</span>
            </button>
            <a href="?export=excel"
                class="flex items-center gap-1.5 rounded-lg h-9 px-3 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold transition-all shadow-sm">
                <span class="material-symbols-outlined text-[17px]">table_chart</span>
                <span>Export Excel</span>
            </a>
            <a href="?export=csv"
                class="flex items-center gap-1.5 rounded-lg h-9 px-3 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold transition-all shadow-sm">
                <span class="material-symbols-outlined text-[17px]">download</span>
                <span>Export CSV</span>
            </a>
            <button onclick="window.print()"
                class="flex items-center gap-1.5 rounded-lg h-9 px-3 bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] text-slate-700 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white transition-all text-xs font-bold shadow-sm">
                <span class="material-symbols-outlined text-[17px]">print</span>
                <span>Print</span>
            </button>
        </div>
    </header>

    <!-- Alerts -->
    <?php if ($success_msg): ?>
        <div class="mb-5 p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-500 text-xs font-semibold flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-[18px]">check_circle</span>
                <span><?= htmlspecialchars($success_msg) ?></span>
            </div>
            <button onclick="this.parentElement.remove()" class="text-emerald-500 hover:text-emerald-400">
                <span class="material-symbols-outlined text-[16px]">close</span>
            </button>
        </div>
    <?php endif; ?>
    <?php if ($error_msg): ?>
        <div class="mb-5 p-3 rounded-xl bg-red-500/10 border border-red-500/20 text-red-500 text-xs font-semibold flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-[18px]">error</span>
                <span><?= htmlspecialchars($error_msg) ?></span>
            </div>
            <button onclick="this.parentElement.remove()" class="text-red-500 hover:text-red-400">
                <span class="material-symbols-outlined text-[16px]">close</span>
            </button>
        </div>
    <?php endif; ?>

    <!-- Stats Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 mb-6">
        <div class="bg-white dark:bg-[#161d2b] p-4 rounded-xl border border-slate-200 dark:border-[#232b3d] shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-slate-500 dark:text-slate-400 text-xs font-medium">Total <?= $MODULE_LABEL ?> IPs</span>
                <span class="material-symbols-outlined text-primary text-[18px]">dns</span>
            </div>
            <div class="text-2xl font-bold text-slate-800 dark:text-white mt-1"><?= number_format($stat_total) ?></div>
            <span class="text-[10px] text-slate-400">Satellite inventory pool</span>
        </div>
        <div class="bg-white dark:bg-[#161d2b] p-4 rounded-xl border border-slate-200 dark:border-[#232b3d] shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-slate-500 dark:text-slate-400 text-xs font-medium">Active IPs</span>
                <span class="material-symbols-outlined text-emerald-500 text-[18px]">check_circle</span>
            </div>
            <div class="text-2xl font-bold text-emerald-500 mt-1"><?= number_format($stat_active) ?></div>
            <span class="text-[10px] text-slate-400">Assigned & online</span>
        </div>
        <div class="bg-white dark:bg-[#161d2b] p-4 rounded-xl border border-slate-200 dark:border-[#232b3d] shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-slate-500 dark:text-slate-400 text-xs font-medium">Available IPs</span>
                <span class="material-symbols-outlined text-blue-500 text-[18px]">radio_button_unchecked</span>
            </div>
            <div class="text-2xl font-bold text-blue-500 mt-1"><?= number_format($stat_available) ?></div>
            <span class="text-[10px] text-slate-400">Ready for assignment</span>
        </div>
        <div class="bg-white dark:bg-[#161d2b] p-4 rounded-xl border border-slate-200 dark:border-[#232b3d] shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-slate-500 dark:text-slate-400 text-xs font-medium">Workstations</span>
                <span class="material-symbols-outlined text-indigo-500 text-[18px]">computer</span>
            </div>
            <div class="text-2xl font-bold text-indigo-500 mt-1"><?= number_format($stat_workstn) ?></div>
            <span class="text-[10px] text-slate-400">Connected workstations</span>
        </div>
        <div class="bg-white dark:bg-[#161d2b] p-4 rounded-xl border border-slate-200 dark:border-[#232b3d] shadow-sm col-span-2 sm:col-span-1">
            <div class="flex items-center justify-between">
                <span class="text-slate-500 dark:text-slate-400 text-xs font-medium">Offline / Other</span>
                <span class="material-symbols-outlined text-slate-400 text-[18px]">power_off</span>
            </div>
            <div class="text-2xl font-bold text-slate-600 dark:text-slate-300 mt-1"><?= number_format($stat_offline) ?></div>
            <span class="text-[10px] text-slate-400">Unreachable / maintenance</span>
        </div>
    </div>

    <!-- Control Bar (Search, Filters, Sort) -->
    <div class="bg-white dark:bg-[#161d2b] p-3 rounded-xl border border-slate-200 dark:border-[#232b3d] shadow-sm mb-4">
        <form method="GET" class="flex flex-wrap items-center gap-2">
            <!-- Live Search -->
            <div class="relative flex-1 min-w-[220px]">
                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-[18px]">search</span>
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search IP, MAC, Ctrl#, User, Dept..."
                    class="w-full pl-9 pr-3 py-1.5 text-xs rounded-lg bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-800 dark:text-slate-200 focus:outline-none focus:border-primary">
            </div>

            <!-- Status Filter -->
            <select name="status" onchange="this.form.submit()"
                class="px-2.5 py-1.5 text-xs rounded-lg bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-700 dark:text-slate-300 focus:outline-none focus:border-primary">
                <option value="All" <?= $status_f === 'All' ? 'selected' : '' ?>>All Status</option>
                <option value="active" <?= $status_f === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="available" <?= $status_f === 'available' ? 'selected' : '' ?>>Available</option>
                <option value="offline" <?= $status_f === 'offline' ? 'selected' : '' ?>>Offline</option>
                <option value="maintenance" <?= $status_f === 'maintenance' ? 'selected' : '' ?>>Maintenance</option>
                <option value="reserved" <?= $status_f === 'reserved' ? 'selected' : '' ?>>Reserved</option>
            </select>

            <!-- Device Type Filter -->
            <select name="device_type" onchange="this.form.submit()"
                class="px-2.5 py-1.5 text-xs rounded-lg bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-700 dark:text-slate-300 focus:outline-none focus:border-primary">
                <option value="All" <?= $dev_type_f === 'All' ? 'selected' : '' ?>>All Devices</option>
                <option value="Workstation" <?= $dev_type_f === 'Workstation' ? 'selected' : '' ?>>Workstation</option>
                <option value="Server" <?= $dev_type_f === 'Server' ? 'selected' : '' ?>>Server</option>
                <option value="Printer" <?= $dev_type_f === 'Printer' ? 'selected' : '' ?>>Printer</option>
                <option value="Switch" <?= $dev_type_f === 'Switch' ? 'selected' : '' ?>>Switch</option>
                <option value="Router" <?= $dev_type_f === 'Router' ? 'selected' : '' ?>>Router</option>
                <option value="Access Point" <?= $dev_type_f === 'Access Point' ? 'selected' : '' ?>>Access Point</option>
                <option value="Other" <?= $dev_type_f === 'Other' ? 'selected' : '' ?>>Other</option>
            </select>

            <!-- Department Filter -->
            <?php if (!empty($departments)): ?>
                <select name="department" onchange="this.form.submit()"
                    class="px-2.5 py-1.5 text-xs rounded-lg bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-700 dark:text-slate-300 focus:outline-none focus:border-primary max-w-[180px]">
                    <option value="All" <?= $dept_f === 'All' ? 'selected' : '' ?>>All Depts</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?= htmlspecialchars($d) ?>" <?= $dept_f === $d ? 'selected' : '' ?>><?= htmlspecialchars($d) ?></option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>

            <!-- Sort -->
            <select name="sort" onchange="this.form.submit()"
                class="px-2.5 py-1.5 text-xs rounded-lg bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-700 dark:text-slate-300 focus:outline-none focus:border-primary">
                <option value="id_asc" <?= $sort_col === 'id_asc' ? 'selected' : '' ?>>ID (Asc)</option>
                <option value="id_desc" <?= $sort_col === 'id_desc' ? 'selected' : '' ?>>ID (Desc)</option>
                <option value="ip_asc" <?= $sort_col === 'ip_asc' ? 'selected' : '' ?>>IP Address (Asc)</option>
                <option value="ip_desc" <?= $sort_col === 'ip_desc' ? 'selected' : '' ?>>IP Address (Desc)</option>
                <option value="ctrl_asc" <?= $sort_col === 'ctrl_asc' ? 'selected' : '' ?>>Control #</option>
                <option value="dept_asc" <?= $sort_col === 'dept_asc' ? 'selected' : '' ?>>Department</option>
                <option value="last_seen" <?= $sort_col === 'last_seen' ? 'selected' : '' ?>>Last Seen</option>
            </select>

            <!-- Per page -->
            <select name="limit" onchange="this.form.submit()"
                class="px-2 py-1.5 text-xs rounded-lg bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-700 dark:text-slate-300 focus:outline-none focus:border-primary">
                <option value="15" <?= $limit == 15 ? 'selected' : '' ?>>15</option>
                <option value="25" <?= $limit == 25 ? 'selected' : '' ?>>25</option>
                <option value="50" <?= $limit == 50 ? 'selected' : '' ?>>50</option>
                <option value="100" <?= $limit == 100 ? 'selected' : '' ?>>100</option>
                <option value="all" <?= $limit >= 1000 ? 'selected' : '' ?>>All</option>
            </select>

            <button type="submit" class="px-3 py-1.5 rounded-lg bg-slate-200 dark:bg-[#232b3d] hover:bg-slate-300 dark:hover:bg-[#2e384e] text-slate-700 dark:text-slate-300 text-xs font-bold transition-all">Filter</button>
            <?php if ($search !== '' || $status_f !== 'All' || $dev_type_f !== 'All' || $dept_f !== 'All'): ?>
                <a href="<?= $MODULE_PATH ?>" class="px-2.5 py-1.5 rounded-lg bg-red-500/10 text-red-500 hover:bg-red-500/20 text-xs font-semibold">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Table -->
    <div class="bg-white dark:bg-[#161d2b] rounded-xl border border-slate-200 dark:border-[#232b3d] shadow-sm overflow-hidden mb-4">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="border-b border-slate-200 dark:border-[#232b3d] bg-slate-50/75 dark:bg-[#101622]/50 text-slate-500 dark:text-slate-400 uppercase font-semibold text-[10px] tracking-wider">
                        <th class="p-3 w-8">
                            <input type="checkbox" id="selectAllCheckbox" onchange="toggleSelectAll(this)"
                                class="rounded bg-slate-100 dark:bg-[#101622] border-slate-300 dark:border-[#232b3d] text-primary focus:ring-0">
                        </th>
                        <th class="p-3">IP Address</th>
                        <th class="p-3">MAC Address</th>
                        <th class="p-3">Hostname</th>
                        <th class="p-3">Control #</th>
                        <th class="p-3">Department / Location</th>
                        <th class="p-3">End User</th>
                        <th class="p-3">Device Type</th>
                        <th class="p-3">Status</th>
                        <th class="p-3">Last Seen</th>
                        <th class="p-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-[#232b3d]/60 text-slate-700 dark:text-slate-300">
                    <?php if (empty($records)): ?>
                        <tr>
                            <td colspan="11" class="p-8 text-center text-slate-400">
                                <div class="flex flex-col items-center justify-center gap-2">
                                    <span class="material-symbols-outlined text-[36px] text-slate-500">search_off</span>
                                    <p class="font-semibold text-sm">No IP addresses found</p>
                                    <p class="text-xs text-slate-500">Try adjusting your filters or import your computer inventory.</p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($records as $r): ?>
                            <?php
                            $status_class = match (strtolower($r['status'] ?? '')) {
                                'active'      => 'bg-emerald-500/10 text-emerald-500 border border-emerald-500/20',
                                'available'   => 'bg-slate-500/10 text-slate-400 border border-slate-500/20',
                                'offline'     => 'bg-red-500/10 text-red-400 border border-red-500/20',
                                'maintenance' => 'bg-amber-500/10 text-amber-500 border border-amber-500/20',
                                'reserved'    => 'bg-purple-500/10 text-purple-400 border border-purple-500/20',
                                default       => 'bg-slate-500/10 text-slate-400 border border-slate-500/20',
                            };
                            ?>
                            <tr class="hover:bg-slate-50/50 dark:hover:bg-[#1a2130]/50 transition-colors" id="row-<?= $r['id'] ?>">
                                <td class="p-3">
                                    <input type="checkbox" name="row_select[]" value="<?= $r['id'] ?>" onchange="updateSelectedCount()"
                                        class="row-checkbox rounded bg-slate-100 dark:bg-[#101622] border-slate-300 dark:border-[#232b3d] text-primary focus:ring-0">
                                </td>
                                <td class="p-3 font-mono font-bold text-slate-900 dark:text-white">
                                    <div class="flex items-center gap-1.5">
                                        <span class="size-2 rounded-full <?= $r['status'] === 'active' ? 'bg-emerald-500' : ($r['status'] === 'available' ? 'bg-slate-400' : 'bg-red-500') ?>" id="dot-<?= $r['id'] ?>"></span>
                                        <span><?= htmlspecialchars($r['ip_address']) ?></span>
                                    </div>
                                </td>
                                <td class="p-3 font-mono text-slate-500 dark:text-slate-400 text-[11px]"><?= htmlspecialchars($r['mac_address'] ?: '—') ?></td>
                                <td class="p-3 text-slate-600 dark:text-slate-300"><?= htmlspecialchars($r['hostname'] ?: '—') ?></td>
                                <td class="p-3 font-mono font-semibold text-primary">
                                    <?php if (!empty($r['control_number'])): ?>
                                        <a href="<?= $COMPUTERS_PATH ?>?search=<?= urlencode($r['control_number']) ?>" title="View in computer inventory" class="hover:underline">
                                            <?= htmlspecialchars($r['control_number']) ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-slate-400">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-3">
                                    <div class="font-medium text-slate-800 dark:text-slate-200"><?= htmlspecialchars($r['department'] ?: '—') ?></div>
                                    <?php if (!empty($r['location']) && $r['location'] !== $r['department']): ?>
                                        <div class="text-[10px] text-slate-400"><?= htmlspecialchars($r['location']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="p-3"><?= htmlspecialchars($r['om_name'] ?: '—') ?></td>
                                <td class="p-3">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-slate-100 dark:bg-[#101622] text-slate-600 dark:text-slate-400 border border-slate-200 dark:border-[#232b3d]">
                                        <?= htmlspecialchars($r['device_type'] ?: 'Workstation') ?>
                                    </span>
                                </td>
                                <td class="p-3">
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider <?= $status_class ?>" id="status-badge-<?= $r['id'] ?>">
                                        <?= htmlspecialchars($r['status'] ?: 'available') ?>
                                    </span>
                                </td>
                                <td class="p-3 text-slate-400 text-[11px]">
                                    <?= !empty($r['last_seen']) ? date('M j, Y H:i', strtotime($r['last_seen'])) : '—' ?>
                                </td>
                                <td class="p-3 text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        <button onclick="pingSingleIp('<?= htmlspecialchars($r['ip_address']) ?>', <?= $r['id'] ?>)" title="Ping IP"
                                            class="size-7 rounded flex items-center justify-center text-slate-400 hover:text-emerald-500 hover:bg-emerald-500/10 transition-colors">
                                            <span class="material-symbols-outlined text-[16px]">sensors</span>
                                        </button>
                                        <button onclick="openEditModal(<?= htmlspecialchars(json_encode($r)) ?>)" title="Edit IP"
                                            class="size-7 rounded flex items-center justify-center text-slate-400 hover:text-blue-500 hover:bg-blue-500/10 transition-colors">
                                            <span class="material-symbols-outlined text-[16px]">edit</span>
                                        </button>
                                        <button onclick="openDeleteModal(<?= $r['id'] ?>, '<?= htmlspecialchars($r['ip_address']) ?>')" title="Delete IP"
                                            class="size-7 rounded flex items-center justify-center text-slate-400 hover:text-red-500 hover:bg-red-500/10 transition-colors">
                                            <span class="material-symbols-outlined text-[16px]">delete</span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="p-3 border-t border-slate-200 dark:border-[#232b3d] flex items-center justify-between flex-wrap gap-2 text-xs">
                <span class="text-slate-500">Showing <?= number_format($offset + 1) ?> - <?= number_format(min($offset + $limit, $total_records)) ?> of <?= number_format($total_records) ?> IPs</span>
                <div class="flex items-center gap-1">
                    <?php if ($page > 1): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="px-2.5 py-1 rounded bg-slate-100 dark:bg-[#1a2130] text-slate-600 dark:text-slate-300 hover:bg-primary hover:text-white transition-colors">&larr; Prev</a>
                    <?php endif; ?>
                    <?php for ($p = max(1, $page - 2); $p <= min($total_pages, $page + 2); $p++): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $p])) ?>"
                           class="px-2.5 py-1 rounded <?= $p === $page ? 'bg-primary text-white font-bold' : 'bg-slate-100 dark:bg-[#1a2130] text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-[#232b3d]' ?>">
                            <?= $p ?>
                        </a>
                    <?php endfor; ?>
                    <?php if ($page < $total_pages): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="px-2.5 py-1 rounded bg-slate-100 dark:bg-[#1a2130] text-slate-600 dark:text-slate-300 hover:bg-primary hover:text-white transition-colors">Next &rarr;</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Floating Bulk Bar -->
<div id="floatingBulkBar" class="fixed bottom-6 left-1/2 -translate-x-1/2 z-30 hidden bg-slate-900/90 dark:bg-[#101622]/95 backdrop-blur text-white px-5 py-2.5 rounded-full shadow-2xl border border-slate-700 dark:border-[#232b3d] flex items-center gap-4 text-xs">
    <span class="font-bold"><span id="selectedCountText">0</span> selected</span>
    <button onclick="bulkSetStatus('available')" class="px-2.5 py-1 rounded-full bg-slate-800 hover:bg-slate-700 text-slate-200 font-semibold transition-colors">Set Available</button>
    <button onclick="bulkSetStatus('active')" class="px-2.5 py-1 rounded-full bg-emerald-600 hover:bg-emerald-500 text-white font-semibold transition-colors">Set Active</button>
    <button onclick="bulkDelete()" class="px-2.5 py-1 rounded-full bg-red-600 hover:bg-red-500 text-white font-semibold transition-colors">Delete</button>
    <button onclick="clearSelection()" class="text-slate-400 hover:text-white"><span class="material-symbols-outlined text-[16px]">close</span></button>
</div>

<!-- ── MODALS ── -->

<!-- Add IP Modal -->
<div id="addIpModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm hidden">
    <div class="bg-white dark:bg-[#161d2b] border border-slate-200 dark:border-[#232b3d] rounded-2xl max-w-lg w-full shadow-2xl overflow-hidden flex flex-col max-h-[90vh]">
        <div class="p-5 border-b border-slate-200 dark:border-[#232b3d] flex items-center justify-between">
            <h3 class="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
                <span class="material-symbols-outlined text-primary text-[20px]">add_circle</span>
                <span>Add <?= $MODULE_LABEL ?> IP Address</span>
            </h3>
            <button onclick="closeModal('addIpModal')" class="text-slate-400 hover:text-white">
                <span class="material-symbols-outlined text-[20px]">close</span>
            </button>
        </div>
        <form method="POST" class="p-5 overflow-y-auto space-y-3 text-xs">
            <?= getCsrfInput() ?>
            <input type="hidden" name="action" value="add_ip">

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">IP Address <span class="text-red-500">*</span></label>
                    <input type="text" name="ip_address" required placeholder="192.170.10.x"
                        class="w-full px-3 py-2 rounded-lg bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-800 dark:text-slate-200 font-mono">
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">MAC Address</label>
                    <input type="text" name="mac_address" placeholder="00-11-22-33-44-55"
                        class="w-full px-3 py-2 rounded-lg bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-800 dark:text-slate-200 font-mono">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Control Number</label>
                    <input type="text" name="control_number" placeholder="<?= $MODULE_LABEL ?>-PC01"
                        class="w-full px-3 py-2 rounded-lg bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-800 dark:text-slate-200 font-mono uppercase">
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Hostname</label>
                    <input type="text" name="hostname" placeholder="e.g. WORKSTATION-01"
                        class="w-full px-3 py-2 rounded-lg bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-800 dark:text-slate-200">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Department</label>
                    <input type="text" name="department" placeholder="<?= $MODULE_LABEL ?> / ADMIN"
                        class="w-full px-3 py-2 rounded-lg bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-800 dark:text-slate-200">
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Location</label>
                    <input type="text" name="location" placeholder="<?= $MODULE_LABEL ?> GF"
                        class="w-full px-3 py-2 rounded-lg bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-800 dark:text-slate-200">
                </div>
            </div>

            <div class="grid grid-cols-3 gap-3">
                <div>
                    <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">End User</label>
                    <input type="text" name="om_name" placeholder="Full name"
                        class="w-full px-3 py-2 rounded-lg bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-800 dark:text-slate-200">
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Device Type</label>
                    <select name="device_type" class="w-full px-2.5 py-2 rounded-lg bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-800 dark:text-slate-200">
                        <option value="Workstation" selected>Workstation</option>
                        <option value="Server">Server</option>
                        <option value="Printer">Printer</option>
                        <option value="Switch">Switch</option>
                        <option value="Router">Router</option>
                        <option value="Access Point">Access Point</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Status</label>
                    <select name="status" class="w-full px-2.5 py-2 rounded-lg bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-800 dark:text-slate-200">
                        <option value="active" selected>Active</option>
                        <option value="available">Available</option>
                        <option value="offline">Offline</option>
                        <option value="maintenance">Maintenance</option>
                        <option value="reserved">Reserved</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Remarks</label>
                <textarea name="remarks" rows="2" placeholder="Optional notes..."
                    class="w-full px-3 py-2 rounded-lg bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-800 dark:text-slate-200"></textarea>
            </div>

            <div class="pt-3 border-t border-slate-200 dark:border-[#232b3d] flex justify-end gap-2">
                <button type="button" onclick="closeModal('addIpModal')"
                    class="px-4 py-2 rounded-lg bg-slate-100 dark:bg-[#232b3d] text-slate-600 dark:text-slate-300 font-bold hover:bg-slate-200">Cancel</button>
                <button type="submit"
                    class="px-4 py-2 rounded-lg bg-primary hover:bg-primary-hover text-white font-bold shadow-sm">Add IP</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit IP Modal -->
<div id="editIpModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm hidden">
    <div class="bg-white dark:bg-[#161d2b] border border-slate-200 dark:border-[#232b3d] rounded-2xl max-w-lg w-full shadow-2xl overflow-hidden flex flex-col max-h-[90vh]">
        <div class="p-5 border-b border-slate-200 dark:border-[#232b3d] flex items-center justify-between">
            <h3 class="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
                <span class="material-symbols-outlined text-blue-500 text-[20px]">edit</span>
                <span>Edit <?= $MODULE_LABEL ?> IP Address</span>
            </h3>
            <button onclick="closeModal('editIpModal')" class="text-slate-400 hover:text-white">
                <span class="material-symbols-outlined text-[20px]">close</span>
            </button>
        </div>
        <form method="POST" class="p-5 overflow-y-auto space-y-3 text-xs">
            <?= getCsrfInput() ?>
            <input type="hidden" name="action" value="edit_ip">
            <input type="hidden" name="id" id="edit_id">

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">IP Address <span class="text-red-500">*</span></label>
                    <input type="text" name="ip_address" id="edit_ip" required
                        class="w-full px-3 py-2 rounded-lg bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-800 dark:text-slate-200 font-mono">
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">MAC Address</label>
                    <input type="text" name="mac_address" id="edit_mac"
                        class="w-full px-3 py-2 rounded-lg bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-800 dark:text-slate-200 font-mono">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Control Number</label>
                    <input type="text" name="control_number" id="edit_ctrl"
                        class="w-full px-3 py-2 rounded-lg bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-800 dark:text-slate-200 font-mono uppercase">
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Hostname</label>
                    <input type="text" name="hostname" id="edit_hostname"
                        class="w-full px-3 py-2 rounded-lg bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-800 dark:text-slate-200">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Department</label>
                    <input type="text" name="department" id="edit_dept"
                        class="w-full px-3 py-2 rounded-lg bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-800 dark:text-slate-200">
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Location</label>
                    <input type="text" name="location" id="edit_loc"
                        class="w-full px-3 py-2 rounded-lg bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-800 dark:text-slate-200">
                </div>
            </div>

            <div class="grid grid-cols-3 gap-3">
                <div>
                    <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">End User</label>
                    <input type="text" name="om_name" id="edit_user"
                        class="w-full px-3 py-2 rounded-lg bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-800 dark:text-slate-200">
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Device Type</label>
                    <select name="device_type" id="edit_dev_type" class="w-full px-2.5 py-2 rounded-lg bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-800 dark:text-slate-200">
                        <option value="Workstation">Workstation</option>
                        <option value="Server">Server</option>
                        <option value="Printer">Printer</option>
                        <option value="Switch">Switch</option>
                        <option value="Router">Router</option>
                        <option value="Access Point">Access Point</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Status</label>
                    <select name="status" id="edit_status" class="w-full px-2.5 py-2 rounded-lg bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-800 dark:text-slate-200">
                        <option value="active">Active</option>
                        <option value="available">Available</option>
                        <option value="offline">Offline</option>
                        <option value="maintenance">Maintenance</option>
                        <option value="reserved">Reserved</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Remarks</label>
                <textarea name="remarks" id="edit_remarks" rows="2"
                    class="w-full px-3 py-2 rounded-lg bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-800 dark:text-slate-200"></textarea>
            </div>

            <div class="pt-3 border-t border-slate-200 dark:border-[#232b3d] flex justify-end gap-2">
                <button type="button" onclick="closeModal('editIpModal')"
                    class="px-4 py-2 rounded-lg bg-slate-100 dark:bg-[#232b3d] text-slate-600 dark:text-slate-300 font-bold hover:bg-slate-200">Cancel</button>
                <button type="submit"
                    class="px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-700 text-white font-bold shadow-sm">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Modal -->
<div id="deleteModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm hidden">
    <div class="bg-white dark:bg-[#161d2b] border border-slate-200 dark:border-[#232b3d] rounded-2xl max-w-sm w-full p-5 shadow-2xl">
        <div class="flex items-center gap-3 text-red-500 mb-3">
            <span class="material-symbols-outlined text-[28px]">warning</span>
            <h3 class="text-base font-bold text-slate-900 dark:text-white">Delete IP Address</h3>
        </div>
        <p class="text-xs text-slate-500 dark:text-slate-400 mb-5">
            Are you sure you want to delete IP address <strong id="deleteIpLabel" class="text-slate-800 dark:text-white font-mono"></strong>? This will remove it from <?= $MODULE_LABEL ?> IP inventory.
        </p>
        <form method="POST" class="flex justify-end gap-2">
            <?= getCsrfInput() ?>
            <input type="hidden" name="action" value="delete_ip">
            <input type="hidden" name="id" id="delete_id">
            <button type="button" onclick="closeModal('deleteModal')" class="px-3 py-1.5 rounded-lg bg-slate-100 dark:bg-[#232b3d] text-slate-600 dark:text-slate-300 text-xs font-bold">Cancel</button>
            <button type="submit" class="px-3 py-1.5 rounded-lg bg-red-600 hover:bg-red-700 text-white text-xs font-bold">Delete IP</button>
        </form>
    </div>
</div>

<!-- Import Modal -->
<div id="importModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm hidden">
    <div class="bg-white dark:bg-[#161d2b] border border-slate-200 dark:border-[#232b3d] rounded-2xl max-w-md w-full shadow-2xl overflow-hidden">
        <div class="p-5 border-b border-slate-200 dark:border-[#232b3d] flex items-center justify-between">
            <h3 class="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
                <span class="material-symbols-outlined text-amber-500 text-[20px]">upload_file</span>
                <span>Import <?= $MODULE_LABEL ?> IPs from CSV</span>
            </h3>
            <button onclick="closeModal('importModal')" class="text-slate-400 hover:text-white">
                <span class="material-symbols-outlined text-[20px]">close</span>
            </button>
        </div>
        <form method="POST" enctype="multipart/form-data" class="p-5 space-y-4 text-xs">
            <?= getCsrfInput() ?>
            <input type="hidden" name="action" value="import_csv">

            <div>
                <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Select CSV File</label>
                <input type="file" name="csv_file" accept=".csv" required
                    class="w-full px-3 py-2 rounded-lg bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-800 dark:text-slate-200 file:mr-3 file:py-1 file:px-3 file:rounded file:border-0 file:bg-primary file:text-white file:text-xs file:font-semibold">
            </div>

            <div class="p-3 rounded-lg bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-500 space-y-1 text-[11px]">
                <p class="font-bold text-slate-700 dark:text-slate-300 flex items-center gap-1">
                    <span class="material-symbols-outlined text-[14px] text-primary">info</span>
                    <span>Supported Formats:</span>
                </p>
                <ul class="list-disc list-inside space-y-0.5">
                    <li>Standard IP Inventory CSV or <?= $MODULE_LABEL ?> Computer Inventory CSV.</li>
                    <li>IP addresses are automatically isolated to <?= $MODULE_LABEL ?>.</li>
                    <li>Existing IPs matching address will be updated.</li>
                </ul>
                <div class="pt-2">
                    <a href="?download_template=csv" class="text-primary hover:underline font-semibold inline-flex items-center gap-1">
                        <span class="material-symbols-outlined text-[13px]">download</span>
                        <span>Download Sample CSV Template</span>
                    </a>
                </div>
            </div>

            <div class="pt-3 border-t border-slate-200 dark:border-[#232b3d] flex justify-end gap-2">
                <button type="button" onclick="closeModal('importModal')"
                    class="px-4 py-2 rounded-lg bg-slate-100 dark:bg-[#232b3d] text-slate-600 dark:text-slate-300 font-bold">Cancel</button>
                <button type="submit"
                    class="px-4 py-2 rounded-lg bg-amber-500 hover:bg-amber-600 text-white font-bold shadow-sm">Upload & Import</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(id) {
    document.getElementById(id).classList.remove('hidden');
}
function closeModal(id) {
    document.getElementById(id).classList.add('hidden');
}
function openEditModal(data) {
    document.getElementById('edit_id').value        = data.id || '';
    document.getElementById('edit_ip').value        = data.ip_address || '';
    document.getElementById('edit_mac').value       = data.mac_address || '';
    document.getElementById('edit_ctrl').value      = data.control_number || '';
    document.getElementById('edit_hostname').value  = data.hostname || '';
    document.getElementById('edit_dept').value      = data.department || '';
    document.getElementById('edit_loc').value       = data.location || '';
    document.getElementById('edit_user').value      = data.om_name || '';
    document.getElementById('edit_dev_type').value  = data.device_type || 'Workstation';
    document.getElementById('edit_status').value    = data.status || 'available';
    document.getElementById('edit_remarks').value   = data.remarks || '';
    openModal('editIpModal');
}
function openDeleteModal(id, ip) {
    document.getElementById('delete_id').value = id;
    document.getElementById('deleteIpLabel').textContent = ip;
    openModal('deleteModal');
}
function pingSingleIp(ip, id) {
    const dot = document.getElementById('dot-' + id);
    const badge = document.getElementById('status-badge-' + id);
    if (dot) dot.className = 'size-2 rounded-full bg-amber-400 animate-pulse';

    fetch('?ajax_ping=' + encodeURIComponent(ip))
        .then(r => r.json())
        .then(res => {
            if (res.online) {
                if (dot) dot.className = 'size-2 rounded-full bg-emerald-500';
                if (badge) {
                    badge.className = 'px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-emerald-500/10 text-emerald-500 border border-emerald-500/20';
                    badge.textContent = 'active';
                }
            } else {
                if (dot) dot.className = 'size-2 rounded-full bg-red-500';
                if (badge) {
                    badge.className = 'px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-red-500/10 text-red-400 border border-red-500/20';
                    badge.textContent = 'offline';
                }
            }
        })
        .catch(err => {
            if (dot) dot.className = 'size-2 rounded-full bg-slate-400';
        });
}

// Multi-select & Bulk Actions
function toggleSelectAll(master) {
    document.querySelectorAll('.row-checkbox').forEach(cb => cb.checked = master.checked);
    updateSelectedCount();
}
function updateSelectedCount() {
    const selected = document.querySelectorAll('.row-checkbox:checked');
    const bar = document.getElementById('floatingBulkBar');
    const text = document.getElementById('selectedCountText');
    if (selected.length > 0) {
        text.textContent = selected.length;
        bar.classList.remove('hidden');
    } else {
        bar.classList.add('hidden');
    }
}
function clearSelection() {
    document.querySelectorAll('.row-checkbox').forEach(cb => cb.checked = false);
    const master = document.getElementById('selectAllCheckbox');
    if (master) master.checked = false;
    updateSelectedCount();
}
function getSelectedIds() {
    return Array.from(document.querySelectorAll('.row-checkbox:checked')).map(cb => cb.value).join(',');
}
function bulkSetStatus(status) {
    const ids = getSelectedIds();
    if (!ids) return;
    if (!confirm('Change status to ' + status + ' for ' + ids.split(',').length + ' IP(s)?')) return;
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `
        <?= getCsrfInput() ?>
        <input type="hidden" name="action" value="batch_update_status">
        <input type="hidden" name="selected_ids" value="${ids}">
        <input type="hidden" name="batch_status" value="${status}">
    `;
    document.body.appendChild(form);
    form.submit();
}
function bulkDelete() {
    const ids = getSelectedIds();
    if (!ids) return;
    if (!confirm('Are you sure you want to DELETE ' + ids.split(',').length + ' IP address(es)?')) return;
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `
        <?= getCsrfInput() ?>
        <input type="hidden" name="action" value="batch_delete">
        <input type="hidden" name="selected_ids" value="${ids}">
    `;
    document.body.appendChild(form);
    form.submit();
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
