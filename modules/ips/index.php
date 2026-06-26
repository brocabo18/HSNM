<?php
require_once '../../config.php';
requireLogin();

// Excel Export (.xlsx with Building/Floor dropdowns)
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    try {
        $buildings = ['ACIS','Bayanihan','Bio Safety','Capiz','Dietary','Frontline','HOPSS','Isolation','Lingap Baga','Medicine','OB-Gyne/Pedia','OPD','Orthopaedics','Surgery','Trauma','Wellness'];
        $floors    = ['GF','2F','3F','4F','5F','6F','7F'];

        $parse_loc = function(string $loc) use ($buildings, $floors): array {
            $rem = $loc; $b = ''; $f = '';
            foreach ($buildings as $bld) { if (str_starts_with($rem, $bld)) { $b = $bld; $rem = ltrim(substr($rem, strlen($bld))); break; } }
            foreach ($floors as $fl)    { if (str_starts_with($rem, $fl))  { $f = $fl;  $rem = ltrim(substr($rem, strlen($fl)));  break; } }
            return [$b, $f, $rem];
        };

        $stmt = $pdo->prepare("SELECT * FROM ips ORDER BY id ASC");
        $stmt->execute();
        $items = $stmt->fetchAll();

        $xmlEsc = fn($v) => htmlspecialchars((string)$v, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $sharedStrings = [];
        $ssi = function(string $s) use (&$sharedStrings): int {
            $key = array_search($s, $sharedStrings, true);
            if ($key === false) { $sharedStrings[] = $s; $key = count($sharedStrings) - 1; }
            return $key;
        };

        // 13 columns: A=Building, B=Floor, C=Location(Dept), D=IP, E=MAC, F=Hostname, G=Control#, H=OM Name, I=Status, J=Device Type, K=Description, L=Remarks, M=Last Seen
        $headers = ['Building','Floor','Department','IP Address','MAC Address','Hostname','Control Number','OM Name','Status','Device Type','Description','Remarks','Last Seen'];
        $cols = ['A','B','C','D','E','F','G','H','I','J','K','L','M'];

        $rows = [];
        $headerRow = []; foreach ($headers as $h) { $headerRow[] = ['t'=>'s','v'=>$ssi($h)]; }
        $rows[] = $headerRow;

        foreach ($items as $item) {
            [$bld, $fl, $dept] = $parse_loc($item['location'] ?? '');
            $rows[] = [
                ['t'=>'s','v'=>$ssi($bld)],
                ['t'=>'s','v'=>$ssi($fl)],
                ['t'=>'s','v'=>$ssi($dept)],
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
                $sheetXml .= $cell['t'] === 's'
                    ? "<c r=\"$ref\" t=\"s\"><v>{$cell['v']}</v></c>"
                    : "<c r=\"$ref\"><v>{$cell['v']}</v></c>";
            }
            $sheetXml .= '</row>';
        }
        $sheetXml .= '</sheetData>';

        // Dropdown: col A = Building, col B = Floor
        $lastRow = max(count($items) + 51, 200);
        $buildingFormula = '"' . implode(',', $buildings) . '"';
        $floorFormula    = '"' . implode(',', $floors) . '"';
        $sheetXml .= '<dataValidations count="2">';
        $sheetXml .= '<dataValidation type="list" allowBlank="1" showDropDown="0" sqref="A2:A'.$lastRow.'">';
        $sheetXml .= '<formula1>'.$xmlEsc($buildingFormula).'</formula1></dataValidation>';
        $sheetXml .= '<dataValidation type="list" allowBlank="1" showDropDown="0" sqref="B2:B'.$lastRow.'">';
        $sheetXml .= '<formula1>'.$xmlEsc($floorFormula).'</formula1></dataValidation>';
        $sheetXml .= '</dataValidations></worksheet>';

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
        $workbookXml .= '<sheets><sheet name="IP Addresses" sheetId="1" r:id="rId1"/></sheets></workbook>';

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

        $filename = 'ips_export_' . date('Y-m-d_His') . '.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="'.$filename.'"');
        header('Content-Length: ' . filesize($tmpFile));
        readfile($tmpFile);
        unlink($tmpFile);
    } catch (Exception $e) {
        header('HTTP/1.1 500 Internal Server Error');
        echo 'Export error: ' . htmlspecialchars($e->getMessage());
    }
    exit;
}

// Handle CSV Export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    try {
        // Safe sort: try inet cast but fall back gracefully for non-standard IPs
        $stmt = $pdo->prepare("SELECT * FROM ips ORDER BY id ASC");
        $stmt->execute();
        $ips = $stmt->fetchAll();

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="ips_export_' . date('Y-m-d_His') . '.csv"');

        $output = fopen('php://output', 'w');
        fputcsv($output, ['ID', 'IP Address', 'MAC Address', 'Hostname', 'Control Number', 'Location', 'OM Name', 'Status', 'Device Type', 'Description', 'Remarks', 'Subnet ID', 'Last Seen']);

        foreach ($ips as $ip) {
            fputcsv($output, [
                escapeCsvField($ip['id']),
                escapeCsvField($ip['ip_address']),
                escapeCsvField($ip['mac_address']),
                escapeCsvField($ip['hostname']),
                escapeCsvField($ip['control_number']),
                escapeCsvField($ip['location']),
                escapeCsvField($ip['om_name']),
                escapeCsvField($ip['status']),
                escapeCsvField($ip['device_type']),
                escapeCsvField($ip['description']),
                escapeCsvField($ip['remarks']),
                escapeCsvField($ip['subnet_id']),
                escapeCsvField($ip['last_seen'])
            ]);
        }

        fclose($output);
    } catch (Exception $e) {
        header('HTTP/1.1 500 Internal Server Error');
        echo 'Export error: ' . htmlspecialchars($e->getMessage());
    }
    exit;
}


// Handle Actions
$success_msg = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // CSRF verification
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $error_msg = "Security validation failed. Please refresh the page and try again.";
    } elseif ($_POST['action'] === 'import_csv') {
        if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === 0) {
            try {
                $file = fopen($_FILES['csv_file']['tmp_name'], 'r');
                $header = fgetcsv($file); // Skip header
                $imported = 0;
                $duplicates = 0;
                $errors = [];

                while (($row = fgetcsv($file)) !== false) {
                    if (count($row) < 2)
                        continue;

                    $ip = $row[1] ?? '';
                    if (!empty($ip)) {
                        $check = $pdo->prepare("SELECT COUNT(*) FROM ips WHERE ip_address = ?");
                        $check->execute([$ip]);
                        if ($check->fetchColumn() > 0) {
                            $duplicates++;
                            continue;
                        }
                    }

                    try {
                        // Validate subnet_id exists, set to null if invalid
                        $subnet_id = !empty($row[10]) ? $row[10] : null;
                        if ($subnet_id !== null) {
                            $check = $pdo->prepare("SELECT id FROM subnets WHERE id = ?");
                            $check->execute([$subnet_id]);
                            if (!$check->fetch()) {
                                $subnet_id = null; // Subnet doesn't exist, set to null
                            }
                        }

                        $control_number = $row[4] ?? '';
                        $device_type = $row[8] ?? '';

                        // Auto-set Device Type if Control Number starts with jbl-
                        if (stripos(trim($control_number), 'jbl-') === 0) {
                            $device_type = 'Workstation';
                        }

                        $stmt = $pdo->prepare("INSERT INTO ips (ip_address, mac_address, hostname, control_number, location, om_name, status, device_type, description, remarks, subnet_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([
                            $ip, // ip_address
                            $row[2] ?? '', // mac_address
                            $row[3] ?? '', // hostname
                            $control_number, // control_number
                            $row[5] ?? '', // location (mapped from department column in CSV)
                            $row[6] ?? '', // om_name
                            $row[7] ?? 'offline', // status
                            $device_type, // device_type
                            $row[9] ?? '', // description
                            $row[10] ?? '', // remarks
                            $subnet_id // subnet_id (validated or null)
                        ]);
                        $imported++;
                    } catch (Exception $e) {
                        $errors[] = "Row " . ($imported + $duplicates + 1) . ": " . $e->getMessage();
                    }
                }

                fclose($file);

                $msg_parts = ["Imported $imported IPs"];
                if ($duplicates > 0)
                    $msg_parts[] = "$duplicates duplicates skipped";
                if (!empty($errors))
                    $msg_parts[] = count($errors) . " failed";

                $success_msg = implode(", ", $msg_parts) . ".";
                logAudit($pdo, 'import_ips', "Imported $imported IPs (Skipped $duplicates)", 'ip', null);
            } catch (Exception $e) {
                $error_msg = "Error importing CSV: " . $e->getMessage();
            }
        } else {
            $error_msg = "Please upload a valid CSV file.";
        }
    } elseif ($_POST['action'] === 'add_ip') {
        $ip = $_POST['ip_address'] ?? '';
        $mac = strtoupper(str_replace(':', '-', $_POST['mac_address'] ?? ''));
        $subnet_id = $_POST['subnet_id'] ?? null;
        if ($subnet_id === '') {
            $subnet_id = null;
        }
        $hostname = $_POST['hostname'] ?? '';
        $control_num = $_POST['control_number'] ?? '';

        // Merge Location Fields
        $building = $_POST['building'] ?? '';
        $floor = $_POST['floor'] ?? '';
        $dept_input = $_POST['department'] ?? '';
        $location = trim("$building $floor $dept_input");

        $om = $_POST['om_name'] ?? '';
        $status = $_POST['status'] ?? 'active';
        $desc = $_POST['description'] ?? '';
        $type = $_POST['device_type'] ?? 'Other';
        $remarks = $_POST['remarks'] ?? '';

        if ($ip) {
            // Check Duplicates
            $dupe_errors = [];

            // Special IP values that are allowed to appear on multiple records
            $ip_exempt = ['dhcp', 'obtain', 'wifi', 'n/a'];

            // Check IP - Allow duplicate for special/non-routable values
            if (!in_array(strtolower(trim($ip)), $ip_exempt)) {
                $c = $pdo->prepare("SELECT hostname, control_number FROM ips WHERE ip_address = ? LIMIT 1");
                $c->execute([$ip]);
                $existing = $c->fetch(PDO::FETCH_ASSOC);
                if ($existing) {
                    $ctx = $existing['hostname'] ?: $existing['control_number'] ?: 'Unknown';
                    $dupe_errors[] = "IP Address '$ip' (assigned to $ctx)";
                }
            }

            // Check MAC
            if (!empty($mac)) {
                $c = $pdo->prepare("SELECT hostname, control_number FROM ips WHERE mac_address = ? LIMIT 1");
                $c->execute([$mac]);
                $existing = $c->fetch(PDO::FETCH_ASSOC);
                if ($existing) {
                    $ctx = $existing['hostname'] ?: $existing['control_number'] ?: 'Unknown';
                    $dupe_errors[] = "MAC Address '$mac' (assigned to $ctx)";
                }
            }

            if (!empty($dupe_errors)) {
                $error_msg = "Error: Duplicate found for " . implode(' and ', $dupe_errors) . ".";
            } else {
                try {
                    // Auto-set Device Type if Control Number starts with jbl-
                    if (stripos(trim($control_num), 'jbl-') === 0) {
                        $type = 'Workstation';
                    }

                    $stmt = $pdo->prepare("INSERT INTO ips (ip_address, mac_address, hostname, control_number, location, om_name, status, description, remarks, device_type, subnet_id, last_seen) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                    $stmt->execute([$ip, $mac, $hostname, $control_num, $location, $om, $status, $desc, $remarks, $type, $subnet_id]);
                    $new_ip_id = $pdo->lastInsertId();
                    $add_detail = "Added IP | IP: $ip | MAC: $mac | Hostname: $hostname | Control #: $control_num | Location: $location | OM: $om | Status: $status | Type: $type";
                    if ($desc)
                        $add_detail .= " | Desc: $desc";
                    if ($remarks)
                        $add_detail .= " | Remarks: $remarks";
                    logAudit($pdo, 'add_ip', $add_detail, 'ip', $new_ip_id);
                    /* logChangelog($pdo, 'feature', 'IP Address Inventory', "Added IP: $ip ($hostname)", "Control #: $control_num | Location: $location | Status: $status | Type: $type"); removed */
                    $success_msg = "IP added successfully.";
                } catch (Exception $e) {
                    $error_msg = "Error adding IP: " . $e->getMessage();
                }
            }
        }
    } elseif ($_POST['action'] === 'edit_ip') {
        $id = $_POST['id'] ?? 0;
        $ip = $_POST['ip_address'] ?? '';
        $mac = strtoupper(str_replace(':', '-', $_POST['mac_address'] ?? ''));
        $hostname = $_POST['hostname'] ?? '';
        $control_num = $_POST['control_number'] ?? '';

        // Merge Location Fields
        $building = $_POST['building'] ?? '';
        $floor = $_POST['floor'] ?? '';
        $dept_input = $_POST['department'] ?? '';
        $location = trim("$building $floor $dept_input");

        $om = $_POST['om_name'] ?? '';
        $status = $_POST['status'] ?? 'active';
        $desc = $_POST['description'] ?? '';
        $type = $_POST['device_type'] ?? 'Other';
        $remarks = $_POST['remarks'] ?? '';

        if ($id) {
            // Check Duplicates (excluding current ID)
            $dupe_errors = [];

            // Special IP values that are allowed to appear on multiple records
            $ip_exempt = ['dhcp', 'obtain', 'wifi', 'n/a'];

            // Check IP - Allow duplicate for special/non-routable values
            if (!in_array(strtolower(trim($ip)), $ip_exempt)) {
                $c = $pdo->prepare("SELECT hostname, control_number FROM ips WHERE ip_address = ? AND id != ? LIMIT 1");
                $c->execute([$ip, $id]);
                $existing = $c->fetch(PDO::FETCH_ASSOC);
                if ($existing) {
                    $ctx = $existing['hostname'] ?: $existing['control_number'] ?: 'Unknown';
                    $dupe_errors[] = "IP Address '$ip' (assigned to $ctx)";
                }
            }

            // Check MAC
            if (!empty($mac)) {
                $c = $pdo->prepare("SELECT hostname, control_number FROM ips WHERE mac_address = ? AND id != ? LIMIT 1");
                $c->execute([$mac, $id]);
                $existing = $c->fetch(PDO::FETCH_ASSOC);
                if ($existing) {
                    $ctx = $existing['hostname'] ?: $existing['control_number'] ?: 'Unknown';
                    $dupe_errors[] = "MAC Address '$mac' (assigned to $ctx)";
                }
            }

            if (!empty($dupe_errors)) {
                $error_msg = "Error: Duplicate found for " . implode(' and ', $dupe_errors) . ".";
            } else {
                try {
                    // Fetch old data
                    $stmt_old = $pdo->prepare("SELECT * FROM ips WHERE id = ?");
                    $stmt_old->execute([$id]);
                    $old_data = $stmt_old->fetch(PDO::FETCH_ASSOC);

                    // Auto-set Device Type if Control Number starts with jbl-
                    if (stripos(trim($control_num), 'jbl-') === 0) {
                        $type = 'Workstation';
                    }

                    $stmt = $pdo->prepare("UPDATE ips SET ip_address=?, mac_address=?, hostname=?, control_number=?, location=?, om_name=?, status=?, description=?, remarks=?, device_type=? WHERE id=?");
                    if ($stmt->execute([$ip, $mac, $hostname, $control_num, $location, $om, $status, $desc, $remarks, $type, $id])) {
                        // Track changes
                        $changes = [];
                        if ($old_data['ip_address'] !== $ip)
                            $changes[] = "IP: '{$old_data['ip_address']}' -> '$ip'";
                        if ($old_data['mac_address'] !== $mac)
                            $changes[] = "MAC: '{$old_data['mac_address']}' -> '$mac'";
                        if ($old_data['hostname'] !== $hostname)
                            $changes[] = "Hostname: '{$old_data['hostname']}' -> '$hostname'";
                        if ($old_data['status'] !== $status)
                            $changes[] = "Status: '{$old_data['status']}' -> '$status'";
                        if ($old_data['location'] !== $location)
                            $changes[] = "Location: '{$old_data['location']}' -> '$location'";
                        if ($old_data['control_number'] !== $control_num)
                            $changes[] = "Control #: '{$old_data['control_number']}' -> '$control_num'";
                        if ($old_data['om_name'] !== $om)
                            $changes[] = "OM Name: '{$old_data['om_name']}' -> '$om'";
                        if ($old_data['device_type'] !== $type)
                            $changes[] = "Device Type: '{$old_data['device_type']}' -> '$type'";
                        if ($old_data['description'] !== $desc)
                            $changes[] = "Description: '{$old_data['description']}' -> '$desc'";
                        if (($old_data['remarks'] ?? '') !== $remarks)
                            $changes[] = "Remarks updated";

                        $log_details = empty($changes) ? "Updated IP details (minor changes)" : "Updated fields: " . implode(', ', $changes);
                        logAudit($pdo, 'update_ip', $log_details, 'ip', $id);
                        /* logChangelog($pdo, 'enhancement', 'IP Address Inventory', "Updated IP: $ip", $log_details); removed — data changes not tracked in changelog */

                        $success_msg = "IP updated successfully.";
                    } else {
                        $error_msg = "Failed to update IP.";
                    }
                } catch (Exception $e) {
                    $error_msg = "Error updating IP: " . $e->getMessage();
                }
            }
        }
    } elseif ($_POST['action'] === 'delete_ip') {
        $id = $_POST['id'] ?? 0;
        if ($id) {
            try {
                // Snapshot record before deleting
                $snap = $pdo->prepare("SELECT * FROM ips WHERE id = ?");
                $snap->execute([$id]);
                $del_record = $snap->fetch(PDO::FETCH_ASSOC);
                $del_detail = "Deleted IP ID $id";
                if ($del_record) {
                    $del_detail = "Deleted IP | ID: $id | IP: {$del_record['ip_address']} | MAC: {$del_record['mac_address']} | Hostname: {$del_record['hostname']} | Control #: {$del_record['control_number']} | Location: {$del_record['location']} | OM: {$del_record['om_name']} | Status: {$del_record['status']} | Type: {$del_record['device_type']}";
                    if ($del_record['description'])
                        $del_detail .= " | Desc: {$del_record['description']}";
                    if ($del_record['remarks'])
                        $del_detail .= " | Remarks: {$del_record['remarks']}";
                }
                $stmt = $pdo->prepare("DELETE FROM ips WHERE id = ?");
                $stmt->execute([$id]);
                logAudit($pdo, 'delete_ip', $del_detail, 'ip', $id);
                /* if ($del_record) logChangelog($pdo, 'bugfix', 'IP Address Inventory', "Removed IP: {$del_record['ip_address']}", ...); removed — data changes not tracked in changelog */
                $success_msg = "IP deleted successfully.";
            } catch (Exception $e) {
                $error_msg = "Error deleting IP: " . $e->getMessage();
            }
        }
    } elseif ($_POST['action'] === 'ping_ip') {
        $ip = $_POST['ip_address'] ?? '';
        if ($ip) {
            // Determine OS and set count flag
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                $cmd = "ping -n 4 " . escapeshellarg($ip);
            } else {
                $cmd = "ping -c 4 " . escapeshellarg($ip);
            }

            // Execute Ping
            $output = [];
            $return_var = 0;
            exec($cmd, $output, $return_var);
            $output_str = implode("\n", $output);

            // Check Ping Success
            $ping_success = (stripos($output_str, 'TTL=') !== false);

            // --- ADVANCED MAC SCANNER (Run regardless of ping result) ---
            $mac_found = false;
            $mac = '';
            $output_str .= "\n\n[System] Starting Advanced MAC Scan...";

            // Method 1: ARP (Standard, Local Subnet)
            $arp_cmd = "arp -a " . escapeshellarg($ip);
            $arp_output = [];
            exec($arp_cmd, $arp_output);
            $arp_str = implode("\n", $arp_output);

            if (preg_match('/([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})/', $arp_str, $matches)) {
                $mac = strtoupper(str_replace(':', '-', $matches[0]));
                $mac_found = true;
                $output_str .= "\n[System] ✓ MAC found via ARP: $mac";
            }

            // Method 2: NBTSTAT (NetBIOS, Windows Networks)
            if (!$mac_found && strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                $nbt_cmd = "nbtstat -A " . escapeshellarg($ip);
                $nbt_output = [];
                exec($nbt_cmd, $nbt_output);
                $nbt_str = implode("\n", $nbt_output);

                if (preg_match('/MAC Address\s*=\s*([0-9A-Fa-f-]{17})/i', $nbt_str, $matches)) {
                    $mac = strtoupper(str_replace(':', '-', $matches[1]));
                    $mac_found = true;
                    $output_str .= "\n[System] ✓ MAC found via NBTSTAT: $mac";
                }
            }

            // Method 3: GETMAC (RPC, Admin Access)
            if (!$mac_found && strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                $getmac_cmd = "getmac /s " . escapeshellarg($ip) . " /nh /fo csv 2>&1";
                $getmac_output = [];
                exec($getmac_cmd, $getmac_output);
                $getmac_str = implode("\n", $getmac_output);

                if (preg_match('/"([0-9A-Fa-f-]{17})"/', $getmac_str, $matches)) {
                    $mac = strtoupper(str_replace(':', '-', $matches[1]));
                    $mac_found = true;
                    $output_str .= "\n[System] ✓ MAC found via GETMAC: $mac";
                }
            }

            if (!$mac_found) {
                $output_str .= "\n[System] ⚠ Could not retrieve MAC address via ARP, NBTSTAT, or GETMAC.";
            }

            // --- DECIDE STATUS & UPDATE DB ---
            if ($ping_success || $mac_found) {
                // Device is ALIVE
                try {
                    // Check for OM Name to auto-tag
                    $check_stmt = $pdo->prepare("SELECT om_name FROM ips WHERE ip_address = ?");
                    $check_stmt->execute([$ip]);
                    $om_name = $check_stmt->fetchColumn();

                    // 1. Update Status
                    $status_sql = "UPDATE ips SET status = 'active', last_seen = NOW()";
                    if (!empty($om_name)) {
                        $status_sql .= ", device_type = 'Workstation'";
                    }
                    $status_sql .= " WHERE ip_address = ?";
                    $pdo->prepare($status_sql)->execute([$ip]);

                    $reason = $ping_success ? "Ping Reply" : "MAC Found";
                    $output_str .= "\n[System] Status updated to 'Active' ($reason).";
                    logAudit($pdo, 'ping_update', "IP $ip set to Active ($reason)", 'ip', null);

                    // 2. Update MAC if found
                    if ($mac_found) {
                        $pdo->prepare("UPDATE ips SET mac_address = ? WHERE ip_address = ?")->execute([$mac, $ip]);
                        logAudit($pdo, 'mac_update', "MAC for $ip updated to $mac", 'ip', null);
                    }

                    // 3. Hostname Scan
                    $hostname_cmd = "nslookup " . escapeshellarg($ip);
                    $hostname_output = [];
                    exec($hostname_cmd, $hostname_output);
                    $hostname_str = implode("\n", $hostname_output);

                    if (preg_match('/Name:\s+(.+)$/m', $hostname_str, $hostname_matches)) {
                        $hostname = trim($hostname_matches[1]);
                        $hostname = rtrim($hostname, '.');
                        // Validate hostname
                        if (!empty($hostname) && $hostname !== $ip) {
                            $pdo->prepare("UPDATE ips SET hostname = ? WHERE ip_address = ?")->execute([$hostname, $ip]);
                            $output_str .= "\n[System] Hostname updated: $hostname";
                        }
                    }

                } catch (Exception $e) {
                    $output_str .= "\n[System] Error updating DB: " . $e->getMessage();
                }

            } else {
                // Device is OFFLINE
                try {
                    $pdo->prepare("UPDATE ips SET status = 'offline' WHERE ip_address = ?")->execute([$ip]);
                    logAudit($pdo, 'ping_update', "IP $ip set to Offline (No Ping, No MAC)", 'ip', null);
                    $output_str .= "\n[System] Status updated to 'Offline' (Unreachable).";
                } catch (Exception $e) {
                    $output_str .= "\n[System] Error: " . $e->getMessage();
                }
            }

            // Fetch latest data to return for UI update
            $stmt_latest = $pdo->prepare("SELECT * FROM ips WHERE ip_address = ?");
            $stmt_latest->execute([$ip]);
            $latest_data = $stmt_latest->fetch(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'output' => $output_str, 'data' => $latest_data]);
            exit;
        } else {
            echo json_encode(['success' => false, 'error' => 'Invalid IP address']);
            exit;
        }

    } elseif ($_POST['action'] === 'arp_scan') {
        // Execute ARP command
        $arp_cmd = "arp -a";
        $arp_output = [];
        exec($arp_cmd, $arp_output);

        $updates = [];
        $new_entries = [];
        $errors = [];

        foreach ($arp_output as $line) {
            // Parse ARP output for IP and MAC address
            // Windows format: "  192.168.1.1      00-11-22-33-44-55     dynamic"
            // Linux format: "192.168.1.1 (192.168.1.1) at 00:11:22:33:44:55 [ether] on eth0"

            // Try Windows format first
            if (preg_match('/\s*([0-9.]+)\s+([0-9A-Fa-f]{2}[:-][0-9A-Fa-f]{2}[:-][0-9A-Fa-f]{2}[:-][0-9A-Fa-f]{2}[:-][0-9A-Fa-f]{2}[:-][0-9A-Fa-f]{2})/', $line, $matches)) {
                $ip = trim($matches[1]);
                $mac = strtoupper(str_replace(':', '-', $matches[2])); // Standardize to XX-XX-XX-XX-XX-XX

                // Check if IP exists in database
                try {
                    $check_stmt = $pdo->prepare("SELECT id, mac_address FROM ips WHERE ip_address = ?");
                    $check_stmt->execute([$ip]);
                    $existing = $check_stmt->fetch(PDO::FETCH_ASSOC);

                    if ($existing) {
                        // Update existing record
                        $old_mac = $existing['mac_address'];
                        if ($old_mac !== $mac) {
                            $update_stmt = $pdo->prepare("UPDATE ips SET mac_address = ? WHERE ip_address = ?");
                            $update_stmt->execute([$mac, $ip]);
                            $updates[] = "Updated $ip: MAC changed from $old_mac to $mac";
                            logAudit($pdo, 'arp_update', "MAC for $ip updated to $mac via ARP scan", 'ip', $existing['id']);
                        }
                    } else {
                        // New entry found in ARP but not in database
                        $new_entries[] = "Found new device: $ip ($mac) - Not in inventory";
                    }
                } catch (Exception $e) {
                    $errors[] = "Error processing $ip: " . $e->getMessage();
                }
            }
        }

        // Prepare result summary
        $result_summary = [];
        $result_summary[] = "ARP Scan Results:";
        $result_summary[] = "=================";
        $result_summary[] = "";

        if (!empty($updates)) {
            $result_summary[] = "Updated Entries (" . count($updates) . "):";
            foreach ($updates as $update) {
                $result_summary[] = "  ✓ " . $update;
            }
            $result_summary[] = "";
        }

        if (!empty($new_entries)) {
            $result_summary[] = "New Devices Found (" . count($new_entries) . "):";
            foreach ($new_entries as $entry) {
                $result_summary[] = "  ℹ " . $entry;
            }
            $result_summary[] = "";
        }

        if (!empty($errors)) {
            $result_summary[] = "Errors (" . count($errors) . "):";
            foreach ($errors as $error) {
                $result_summary[] = "  ✗ " . $error;
            }
            $result_summary[] = "";
        }

        if (empty($updates) && empty($new_entries) && empty($errors)) {
            $result_summary[] = "No changes detected. All MAC addresses are up to date.";
        }

        $result_summary[] = "";
        $result_summary[] = "Raw ARP Output:";
        $result_summary[] = "===============";
        $result_summary = array_merge($result_summary, $arp_output);

        echo json_encode(['success' => true, 'output' => implode("\n", $result_summary), 'updates' => count($updates)]);
        exit;
    } elseif ($_POST['action'] === 'batch_update_device_types') {
        try {
            $stmt = $pdo->query("UPDATE ips SET device_type = 'Workstation' WHERE control_number LIKE 'jbl-%' AND (device_type != 'Workstation' OR device_type IS NULL OR device_type = '')");
            $count = $stmt->rowCount();

            if ($count > 0) {
                logAudit($pdo, 'batch_update_device_types', "Auto-set Device Type to 'Workstation' for $count IPs with JBL- control number", 'system', null);
                $success_msg = "Successfully updated Device Type for $count records.";
            } else {
                $success_msg = "No records needed updating.";
            }
        } catch (Exception $e) {
            $error_msg = "Error updating Device Types: " . $e->getMessage();
        }
    } elseif ($_POST['action'] === 'batch_update_available_status') {
        // Auto-set status to 'available' for records where all key identity fields are empty,
        // and convert any stale 'conflict' status to 'available'.
        try {
            $empty_fields_sql = "
                UPDATE ips
                SET status = 'available'
                WHERE (
                    (status = 'conflict')
                    OR (
                        status NOT IN ('active', 'reserved')
                        AND (mac_address IS NULL OR mac_address = '')
                        AND (hostname IS NULL OR hostname = '')
                        AND (control_number IS NULL OR control_number = '')
                        AND (location IS NULL OR location = '')
                        AND (om_name IS NULL OR om_name = '')
                        AND (remarks IS NULL OR remarks = '')
                    )
                )
            ";
            $stmt = $pdo->query($empty_fields_sql);
            $count = $stmt->rowCount();

            if ($count > 0) {
                logAudit($pdo, 'batch_update_available_status', "Auto-set status to 'available' for $count IP records (empty identity fields or conflict status)", 'system', null);
                $success_msg = "Updated $count record(s) to 'Available' status.";
            } else {
                $success_msg = "No records needed updating. All statuses are correct.";
            }
        } catch (Exception $e) {
            $error_msg = "Error updating Available status: " . $e->getMessage();
        }
    }
}

// Fetch Subnets
$subnets = $pdo->query("SELECT * FROM subnets ORDER BY name ASC")->fetchAll();

// Stats
$total_ips = $pdo->query("SELECT COUNT(*) FROM ips")->fetchColumn();
$active_ips = $pdo->query("SELECT COUNT(*) FROM ips WHERE status = 'active'")->fetchColumn();
$available_ips = $pdo->query("SELECT COUNT(*) FROM ips WHERE status = 'available'")->fetchColumn();

// Filters
$search_term = $_GET['search'] ?? '';
$filter_subnet = $_GET['subnet'] ?? 'All';
$filter_status = $_GET['status'] ?? 'All';
$sort_by = $_GET['sort'] ?? 'ip_asc';

$where_clauses = ["1=1"];
$params = [];

if ($search_term) {
    // using ILIKE for case-insensitive search in PostgreSQL
    $where_clauses[] = "(ip_address ILIKE ? OR hostname ILIKE ? OR mac_address ILIKE ? OR control_number ILIKE ? OR location ILIKE ? OR om_name ILIKE ? OR status ILIKE ? OR device_type ILIKE ? OR description ILIKE ? OR remarks ILIKE ?)";
    $params[] = "%$search_term%";
    $params[] = "%$search_term%";
    $params[] = "%$search_term%";
    $params[] = "%$search_term%";
    $params[] = "%$search_term%";
    $params[] = "%$search_term%";
    $params[] = "%$search_term%";
    $params[] = "%$search_term%";
    $params[] = "%$search_term%";
    $params[] = "%$search_term%";
}

if ($filter_subnet !== 'All' && !empty($filter_subnet)) {
    $where_clauses[] = "subnet_id = ?";
    $params[] = $filter_subnet;
}

if ($filter_status !== 'All' && !empty($filter_status)) {
    $where_clauses[] = "status = ?";
    $params[] = $filter_status;
}

$where_sql = implode(' AND ', $where_clauses);

// Sorting
$order_by = "CASE WHEN ip_address ~ '^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$' THEN LPAD(split_part(ip_address,'.',1),3,'0')||'.'||LPAD(split_part(ip_address,'.',2),3,'0')||'.'||LPAD(split_part(ip_address,'.',3),3,'0')||'.'||LPAD(split_part(ip_address,'.',4),3,'0') ELSE ip_address END ASC NULLS LAST"; // Default
switch ($sort_by) {
    case 'ip_desc':
        $order_by = "CASE WHEN ip_address ~ '^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$' THEN LPAD(split_part(ip_address,'.',1),3,'0')||'.'||LPAD(split_part(ip_address,'.',2),3,'0')||'.'||LPAD(split_part(ip_address,'.',3),3,'0')||'.'||LPAD(split_part(ip_address,'.',4),3,'0') ELSE ip_address END DESC NULLS LAST";
        break;
    case 'hostname_asc':
        $order_by = "hostname ASC";
        break;
    case 'hostname_desc':
        $order_by = "hostname DESC";
        break;
    case 'status_asc':
        $order_by = "status ASC";
        break;
    case 'status_desc':
        $order_by = "status DESC";
        break;
    case 'location_asc':
        $order_by = "location ASC";
        break;
    case 'location_desc':
        $order_by = "location DESC";
        break;
    case 'ip_asc':
    default:
        // Use CASE to safe-cast: only cast if it looks like an IP
        $order_by = "CASE WHEN ip_address ~ '^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$' THEN LPAD(split_part(ip_address,'.',1),3,'0')||'.'||LPAD(split_part(ip_address,'.',2),3,'0')||'.'||LPAD(split_part(ip_address,'.',3),3,'0')||'.'||LPAD(split_part(ip_address,'.',4),3,'0') ELSE ip_address END ASC NULLS LAST";
        break;
}

// Pagination
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1)
    $page = 1;
$limit_param = $_GET['limit'] ?? '50';
$limit = ($limit_param === 'all') ? 999999 : (int) $limit_param;
$offset = ($page - 1) * $limit;

$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM ips WHERE $where_sql");
$count_stmt->execute($params);
$total_items = $count_stmt->fetchColumn();
$total_pages = ceil($total_items / $limit);

$sql = "SELECT * FROM ips WHERE $where_sql ORDER BY $order_by LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$ips = $stmt->fetchAll();

// Fetch last-edit info in a separate batch query to avoid PostgreSQL planner issues
if (!empty($ips)) {
    $ids = array_map('strval', array_column($ips, 'id'));
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $edit_stmt = $pdo->prepare(
        "SELECT DISTINCT ON (al.resource_id) al.resource_id, u.username, al.created_at
         FROM audit_logs al
         LEFT JOIN users u ON al.user_id = u.id
         WHERE al.resource_type = 'ip' AND al.resource_id IN ($ph)
         ORDER BY al.resource_id, al.created_at DESC"
    );
    $edit_stmt->execute($ids);
    $last_edits = [];
    foreach ($edit_stmt->fetchAll() as $row) {
        $last_edits[$row['resource_id']] = $row;
    }
    foreach ($ips as &$ip) {
        $edit = $last_edits[(string)$ip['id']] ?? null;
        $ip['last_edited_by'] = $edit['username'] ?? null;
        $ip['last_edited_at'] = $edit['created_at'] ?? null;
    }
    unset($ip);
}

// AJAX Search Handler
if (isset($_GET['ajax_search'])) {
    if (empty($ips)) {
        echo '<tr><td colspan="11" class="px-6 py-12 text-center text-slate-500">No IPs found.</td></tr>';
    } else {
        foreach ($ips as $item) {
            $rowId = 'row-' . str_replace('.', '-', $item['ip_address']);
            $ip = htmlspecialchars($item['ip_address']);
            $mac = htmlspecialchars($item['mac_address'] ?: '-');
            $hostname = htmlspecialchars($item['hostname'] ?: '-');
            $control = htmlspecialchars($item['control_number'] ?: '-');
            $location = htmlspecialchars($item['location'] ?: '-');
            $om = htmlspecialchars($item['om_name'] ?: '-');
            $status = ucfirst(htmlspecialchars($item['status']));
            $type = htmlspecialchars($item['device_type'] ?: 'Other');
            $remarks = htmlspecialchars($item['remarks'] ?: '-');
            $remarksFull = htmlspecialchars($item['remarks'] ?? '');
            $itemId = $item['id'];

            // Status Dot
            $dotClass = getIpDotClass($item['status']);
            $statusColor = getIpStatusColor($item['status']);

            // JSON for edit
            $jsonItem = htmlspecialchars(json_encode($item), ENT_QUOTES, 'UTF-8');
            $csrf = getCsrfInput();

            $jsonItemAjax = htmlspecialchars(json_encode($item), ENT_QUOTES, 'UTF-8');
            echo "
            <tr id=\"$rowId\" class=\"hover:bg-white/5 transition-colors text-[11px]\" data-item='$jsonItemAjax'>
                <td class=\"no-print px-2 py-1 text-center\">
                    <input type=\"checkbox\" name=\"item_id[]\" value=\"$itemId\"
                        class=\"item-checkbox rounded border-slate-200 dark:border-[#232b3d] bg-slate-50 dark:bg-[#101622] text-primary focus:ring-primary h-3 w-3\">
                </td>
                <td class=\"px-2 py-1 font-mono font-medium text-slate-900 dark:text-white whitespace-nowrap cursor-pointer hover:bg-primary/10 hover:text-primary transition-colors\"
                    onclick='openEditModal($jsonItem)' title=\"Click to edit\">$ip</td>
                <td class=\"col-mac px-2 py-1 text-[10px] font-mono text-slate-400 whitespace-nowrap\">$mac</td>
                <td class=\"col-hostname px-2 py-1 text-slate-400 whitespace-nowrap\">$hostname</td>
                <td class=\"px-2 py-1 text-[10px] text-slate-400 whitespace-nowrap\">$control</td>
                <td class=\"px-2 py-1 text-slate-400 whitespace-nowrap\">$location</td>
                <td class=\"px-2 py-1 text-[10px] text-slate-400 whitespace-nowrap\">$om</td>
                <td class=\"col-status px-2 py-1 whitespace-nowrap\">
                    <div class=\"flex items-center gap-1.5\">
                        <div class=\"status-dot size-1.5 rounded-full $dotClass\"></div>
                        <span class=\"status-text font-medium $statusColor\">$status</span>
                    </div>
                </td>
                <td class=\"px-2 py-1 text-slate-400 whitespace-nowrap\">$type</td>
                <td class=\"px-2 py-1 text-slate-500 truncate max-w-[120px] whitespace-nowrap\" title=\"$remarksFull\">$remarks</td>";
            $lastEditedBy = htmlspecialchars($item['last_edited_by'] ?? '');
            $lastEditedAt = !empty($item['last_edited_at']) ? date('M d, Y H:i', strtotime($item['last_edited_at'])) : '';
            echo '<td class="no-print px-2 py-1 whitespace-nowrap">';
            if ($lastEditedBy) {
                echo "<div class='text-[10px] font-semibold text-slate-700 dark:text-slate-300'>$lastEditedBy</div><div class='text-[9px] text-slate-400'>$lastEditedAt</div>";
            } else {
                echo "<span class='text-slate-400 text-[10px]'>—</span>";
            }
            echo '</td>';
            echo "
                <td class=\"px-2 py-1 text-right flex items-center justify-end gap-1 whitespace-nowrap\">
                    <button onclick=\"pingIP('$ip')\" class=\"p-1 hover:bg-emerald-500/10 hover:text-emerald-500 text-slate-500 rounded-lg\" title=\"Ping IP\">
                        <span class=\"material-symbols-outlined text-[18px]\">network_ping</span>
                    </button>
                    <button onclick='openEditModal($jsonItem)' class=\"p-1 hover:bg-primary/10 hover:text-primary text-slate-500 rounded-lg\">
                        <span class=\"material-symbols-outlined text-[18px]\">edit</span>
                    </button>
                    <form method=\"POST\" onsubmit=\"return confirm('Delete IP?');\" style=\"display:inline;\">
                        $csrf
                        <input type=\"hidden\" name=\"action\" value=\"delete_ip\">
                        <input type=\"hidden\" name=\"id\" value=\"$itemId\">
                        <button class=\"p-1 hover:bg-red-500/10 hover:text-red-500 text-slate-500 rounded-lg\">
                            <span class=\"material-symbols-outlined text-[18px]\">delete</span>
                        </button>
                    </form>
                </td>
            </tr>";
        }
    }
    exit;
}

$page_title = "IP Address Inventory";
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
?>

<div class="p-4 md:p-8 flex-1 overflow-y-auto custom-scrollbar relative z-10 bg-slate-50 dark:bg-background-dark">
    <header
        class="-mt-4 -mx-4 md:-mt-8 md:-mx-8 px-4 md:px-8 pt-4 md:pt-8 pb-6 mb-8 flex flex-col md:flex-row items-start md:items-end justify-between gap-4 sticky top-0 bg-slate-50 dark:bg-background-dark z-20 border-b border-slate-200 dark:border-[#232b3d]">
        <div>
            <h2 class="text-2xl font-bold text-slate-900 dark:text-white">IP Inventory</h2>
            <p class="text-sm text-slate-500 mt-1">Manage IP addresses and subnets.</p>
        </div>
        <div class="no-print flex items-center gap-2 md:gap-3 flex-wrap">
            <a href="?export=excel"
                class="flex items-center justify-center rounded-lg h-9 px-4 bg-emerald-600 text-white hover:bg-emerald-700 transition-colors text-xs font-bold">
                <span class="material-symbols-outlined text-[18px] mr-2">table_view</span> Export Excel
            </a>
            <a href="?export=csv"
                class="flex items-center justify-center rounded-lg h-9 px-4 bg-blue-600 text-white hover:bg-blue-700 transition-colors text-xs font-bold">
                <span class="material-symbols-outlined text-[18px] mr-2">download</span> Export CSV
            </a>
            <form method="POST"
                onsubmit="return confirm('Run batch check to set Device Type to Workstation for all JBL- control numbers?');">
                <?= getCsrfInput() ?>
                <input type="hidden" name="action" value="batch_update_device_types">
                <button type="submit"
                    class="flex items-center justify-center rounded-lg h-9 px-4 bg-purple-600 text-white hover:bg-purple-700 transition-colors text-xs font-bold">
                    <span class="material-symbols-outlined text-[18px] mr-2">sync_alt</span> Sync Types
                </button>
            </form>
            <form method="POST"
                onsubmit="return confirm('Batch update: set status to Available for all IPs with empty identity fields, and convert any Conflict status to Available?');">
                <?= getCsrfInput() ?>
                <input type="hidden" name="action" value="batch_update_available_status">
                <button type="submit"
                    class="flex items-center justify-center rounded-lg h-9 px-4 bg-cyan-600 text-white hover:bg-cyan-700 transition-colors text-xs font-bold">
                    <span class="material-symbols-outlined text-[18px] mr-2">dns</span> Sync Available
                </button>
            </form>
            <button onclick="runArpScan()"
                class="flex items-center justify-center rounded-lg h-9 px-4 bg-teal-600 text-white hover:bg-teal-700 transition-colors text-xs font-bold">
                <span class="material-symbols-outlined text-[18px] mr-2">router</span> ARP Scan
            </button>
            <button onclick="toggleModal('importModal')"
                class="flex items-center justify-center rounded-lg h-9 px-4 bg-amber-600 text-white hover:bg-amber-700 transition-colors text-xs font-bold">
                <span class="material-symbols-outlined text-[18px] mr-2">upload</span> Import CSV
            </button>
            <button onclick="printData()"
                class="flex items-center justify-center rounded-lg h-9 px-4 bg-slate-600 text-white hover:bg-slate-700 transition-colors text-xs font-bold">
                <span class="material-symbols-outlined text-[18px] mr-2">print</span> Print
            </button>
            <button onclick="toggleModal('addIpModal')"
                class="flex items-center justify-center rounded-lg h-9 px-4 bg-primary text-white hover:bg-primary/90 transition-colors text-xs font-bold shadow-lg shadow-primary/20">
                <span class="material-symbols-outlined text-[18px] mr-2">add</span> Add IP
            </button>
        </div>
    </header>

    <?php if ($success_msg): ?>
        <div
            class="mb-6 bg-emerald-500/10 border border-emerald-500/20 text-emerald-500 p-4 rounded-xl text-sm font-medium flex items-center gap-3">
            <span class="material-symbols-outlined">check_circle</span>
            <?= htmlspecialchars($success_msg) ?>
        </div>
    <?php endif; ?>
    <?php if ($error_msg): ?>
        <div
            class="mb-6 bg-red-500/10 border border-red-500/20 text-red-500 p-4 rounded-xl text-sm font-medium flex items-center gap-3">
            <span class="material-symbols-outlined">error</span>
            <?= htmlspecialchars($error_msg) ?>
        </div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
        <div class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] p-4 rounded-xl">
            <div class="text-slate-500 text-xs font-bold uppercase tracking-wider mb-1">Total IPs</div>
            <div class="text-2xl font-bold text-slate-900 dark:text-white">
                <?= number_format($total_ips) ?>
            </div>
        </div>
        <div class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] p-4 rounded-xl">
            <div class="text-slate-500 text-xs font-bold uppercase tracking-wider mb-1">Active IPs</div>
            <div class="text-2xl font-bold text-emerald-500">
                <?= number_format($active_ips) ?>
            </div>
        </div>
        <div class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] p-4 rounded-xl">
            <div class="text-slate-500 text-xs font-bold uppercase tracking-wider mb-1">Available</div>
            <div class="text-2xl font-bold text-cyan-400">
                <?= number_format($available_ips) ?>
            </div>
        </div>
    </div>

    <!-- Control Bar -->
    <div class="no-print mb-6 flex gap-3">
        <form method="GET" class="flex items-center gap-3 flex-wrap w-full">
            <div class="relative group">
                <span
                    class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-[18px] text-slate-500">search</span>
                <input type="text" name="search" id="searchInput" value="<?= htmlspecialchars($search_term) ?>"
                    placeholder="Search..."
                    class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] text-slate-700 dark:text-slate-200 text-xs rounded-xl pl-9 pr-4 py-2 focus:ring-2 focus:ring-primary focus:border-transparent w-64">
            </div>
            <input type="hidden" name="subnet" id="hid_subnet" value="<?= htmlspecialchars($filter_subnet) ?>">
            <input type="hidden" name="status" id="hid_status" value="<?= htmlspecialchars($filter_status) ?>">
            <?php
            $sn_map=[]; $sn_js=[];
            foreach($subnets as $sn){
                $lbl=$sn['name'].' ('.$sn['cidr'].')';
                $sn_map[$sn['id']]=$lbl;
                $sn_js[]=['val'=>(string)$sn['id'],'label'=>$lbl];
            }
            $ip_ac=''; $ip_av=''; $ip_al='';
            if($filter_subnet!=='All'&&$filter_subnet!==''){$ip_ac='subnet';$ip_av=$filter_subnet;$ip_al=$sn_map[$filter_subnet]??$filter_subnet;}
            elseif($filter_status!=='All'&&$filter_status!==''){$ip_ac='status';$ip_av=$filter_status;$ip_al=ucfirst($filter_status);}
            ?>
            <select id="ip_cat" onchange="ipOnCat(this.value)"
                class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] text-slate-600 dark:text-slate-300 text-xs rounded-xl px-4 py-2 focus:ring-2 focus:ring-primary">
                <option value="">Filter by...</option>
                <option value="subnet" <?= $ip_ac==='subnet'?'selected':'' ?>>Subnet</option>
                <option value="status" <?= $ip_ac==='status'?'selected':'' ?>>Status</option>
            </select>
            <select id="ip_val" onchange="ipApply(this.value)"
                class="<?= $ip_ac?'':'hidden' ?> bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] text-slate-600 dark:text-slate-300 text-xs rounded-xl px-4 py-2 focus:ring-2 focus:ring-primary max-w-[220px]">
            </select>
            <?php if($ip_ac): ?>
            <div class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-primary/10 border border-primary/30 text-primary text-xs font-semibold rounded-xl">
                <span class="material-symbols-outlined text-[13px]">filter_alt</span>
                <?= htmlspecialchars($ip_al) ?>
                <a href="?search=<?= urlencode($search_term) ?>&sort=<?= urlencode($sort_by) ?>&limit=<?= urlencode($limit_param) ?>" class="ml-1 text-primary/60 hover:text-red-500" title="Clear">
                    <span class="material-symbols-outlined text-[14px]">close</span>
                </a>
            </div>
            <?php endif; ?>
            <script>
            const _ipO={
                subnet:<?= json_encode($sn_js) ?>,
                status:[{val:'active',label:'Active'},{val:'available',label:'Available'},{val:'reserved',label:'Reserved'},{val:'offline',label:'Offline'}]
            };
            const _ipAC=<?= json_encode($ip_ac) ?>,_ipAV=<?= json_encode($ip_av) ?>;
            function ipOnCat(c){
                const v=document.getElementById('ip_val');
                if(!c){v.classList.add('hidden');return;}
                ['subnet','status'].forEach(function(k){if(k!==c){const h=document.getElementById('hid_'+k);if(h)h.value='All';}});
                v.innerHTML='<option value="All">All</option>';
                (_ipO[c]||[]).forEach(function(o){
                    const val=typeof o==='object'?o.val:o,lbl=typeof o==='object'?o.label:o;
                    const e=document.createElement('option');e.value=val;e.textContent=lbl;
                    if(c===_ipAC&&val===_ipAV)e.selected=true;v.appendChild(e);
                });
                v.classList.remove('hidden');
            }
            function ipApply(val){
                const c=document.getElementById('ip_cat').value;if(!c)return;
                document.getElementById('hid_'+c).value=val;
                document.getElementById('ip_cat').closest('form').submit();
            }
            document.addEventListener('DOMContentLoaded',function(){if(_ipAC)ipOnCat(_ipAC);});
            </script>

            <select name="sort" onchange="this.form.submit()"
                class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] text-slate-400 text-xs rounded-xl px-4 py-2 focus:ring-2 focus:ring-primary">
                <option value="ip_asc" <?= $sort_by === 'ip_asc' ? 'selected' : '' ?>>IP (Asc)</option>
                <option value="ip_desc" <?= $sort_by === 'ip_desc' ? 'selected' : '' ?>>IP (Desc)</option>
                <option value="hostname_asc" <?= $sort_by === 'hostname_asc' ? 'selected' : '' ?>>Hostname (A-Z)</option>
                <option value="hostname_desc" <?= $sort_by === 'hostname_desc' ? 'selected' : '' ?>>Hostname (Z-A)</option>
                <option value="status_asc" <?= $sort_by === 'status_asc' ? 'selected' : '' ?>>Status (A-Z)</option>
                <option value="status_desc" <?= $sort_by === 'status_desc' ? 'selected' : '' ?>>Status (Z-A)</option>
                <option value="location_asc" <?= $sort_by === 'location_asc' ? 'selected' : '' ?>>Location (A-Z)</option>
                <option value="location_desc" <?= $sort_by === 'location_desc' ? 'selected' : '' ?>>Location (Z-A)</option>
            </select>

            <!-- Top Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="flex items-center gap-2 ml-auto">
                    <span class="text-xs text-slate-500 mr-2">Page
                        <?= $page ?> of
                        <?= $total_pages ?>
                    </span>
                    <div
                        class="flex h-9 bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] rounded-xl overflow-hidden mr-2">
                        <a href="?page=<?= max(1, $page - 1) ?>&search=<?= urlencode($search_term) ?>&subnet=<?= urlencode($filter_subnet) ?>&status=<?= urlencode($filter_status) ?>"
                            class="px-3 flex items-center justify-center hover:bg-white/5 text-slate-400 hover:text-slate-900 dark:hover:text-white transition-colors border-r border-slate-200 dark:border-[#232b3d] <?= $page <= 1 ? 'pointer-events-none opacity-50' : '' ?>">
                            <span class="material-symbols-outlined text-[18px]">chevron_left</span>
                        </a>
                        <a href="?page=<?= min($total_pages, $page + 1) ?>&search=<?= urlencode($search_term) ?>&subnet=<?= urlencode($filter_subnet) ?>&status=<?= urlencode($filter_status) ?>"
                            class="px-3 flex items-center justify-center hover:bg-white/5 text-slate-400 hover:text-slate-900 dark:hover:text-white transition-colors <?= $page >= $total_pages ? 'pointer-events-none opacity-50' : '' ?>">
                            <span class="material-symbols-outlined text-[18px]">chevron_right</span>
                        </a>
                    </div>
                    <select name="limit" onchange="this.form.submit()"
                        class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] text-slate-400 text-xs rounded-xl px-4 py-2 focus:ring-2 focus:ring-primary">
                        <option value="50" <?= $limit_param == '50' ? 'selected' : '' ?>>Show: 50</option>
                        <option value="100" <?= $limit_param == '100' ? 'selected' : '' ?>>Show: 100</option>
                        <option value="200" <?= $limit_param == '200' ? 'selected' : '' ?>>Show: 200</option>
                        <option value="500" <?= $limit_param == '500' ? 'selected' : '' ?>>Show: 500</option>
                        <option value="all" <?= $limit_param == 'all' ? 'selected' : '' ?>>Show: All</option>
                    </select>
                </div>
            <?php else: ?>
                <select name="limit" onchange="this.form.submit()"
                    class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] text-slate-400 text-xs rounded-xl px-4 py-2 focus:ring-2 focus:ring-primary ml-auto">
                    <option value="50" <?= $limit_param == '50' ? 'selected' : '' ?>>Show: 50</option>
                    <option value="100" <?= $limit_param == '100' ? 'selected' : '' ?>>Show: 100</option>
                    <option value="200" <?= $limit_param == '200' ? 'selected' : '' ?>>Show: 200</option>
                    <option value="500" <?= $limit_param == '500' ? 'selected' : '' ?>>Show: 500</option>
                    <option value="all" <?= $limit_param == 'all' ? 'selected' : '' ?>>Show: All</option>
                </select>
            <?php endif; ?>
        </form>
    </div>

    <!-- Table -->
    <div
        class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] rounded-2xl overflow-hidden shadow-sm">
        <div class="overflow-x-auto custom-scrollbar">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-white dark:bg-[#1a2130] border-b border-slate-200 dark:border-[#232b3d]">
                        <th
                            class="sticky-col-1 no-print px-2 py-1 w-8 text-center text-slate-500 bg-white dark:bg-[#1a2130] z-20">
                            <input type="checkbox" onclick="toggleAll(this)"
                                class="rounded border-slate-200 dark:border-[#232b3d] bg-slate-50 dark:bg-[#101622] text-primary focus:ring-primary h-3 w-3">
                        </th>
                        <th class="px-2 py-1 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">IP
                            Address</th>
                        <th class="px-2 py-1 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">MAC
                            Address</th>
                        <th class="px-2 py-1 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">
                            Hostname
                        </th>
                        <th class="px-2 py-1 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">
                            Control
                            Number</th>
                        <th class="px-2 py-1 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">
                            Location</th>
                        <th class="px-2 py-1 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">OM
                            Name
                        </th>
                        <th class="px-2 py-1 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">
                            Status
                        </th>
                        <th class="px-2 py-1 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">Type
                        </th>
                        <th class="px-2 py-1 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">
                            Remarks
                        </th>
                        <th class="no-print px-2 py-1 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">Last Edited By</th>
                        <th
                            class="px-2 py-1 text-[10px] font-bold text-slate-500 uppercase text-right whitespace-nowrap">
                            Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#232b3d]/50">
                    <?php if (empty($ips)): ?>
                        <tr>
                            <td colspan="11" class="px-6 py-12 text-center text-slate-500">No IPs found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($ips as $item): ?>
                            <tr id="row-<?= str_replace('.', '-', $item['ip_address']) ?>"
                                class="hover:bg-white/5 transition-colors text-[11px]"
                                data-item='<?= htmlspecialchars(json_encode($item), ENT_QUOTES, "UTF-8") ?>'>
                                <td class="no-print px-2 py-1 text-center">
                                    <input type="checkbox"
                                        class="item-checkbox rounded border-slate-200 dark:border-[#232b3d] bg-slate-50 dark:bg-[#101622] text-primary focus:ring-primary h-3 w-3"
                                        value="<?= $item['id'] ?>">
                                </td>
                                <td class="px-2 py-1 font-mono font-medium text-slate-900 dark:text-white whitespace-nowrap cursor-pointer hover:bg-primary/10 hover:text-primary transition-colors"
                                    onclick='openEditModal(<?= htmlspecialchars(json_encode($item), ENT_QUOTES, 'UTF-8') ?>)'
                                    title="Click to edit">
                                    <?= htmlspecialchars($item['ip_address']) ?>
                                </td>
                                <td
                                    class="col-mac px-2 py-1 text-[10px] font-mono text-slate-600 dark:text-slate-400 whitespace-nowrap">
                                    <?= htmlspecialchars($item['mac_address'] ?: '-') ?>
                                </td>
                                <td class="col-hostname px-2 py-1 text-slate-600 dark:text-slate-400 whitespace-nowrap">
                                    <?= htmlspecialchars($item['hostname'] ?: '-') ?>
                                </td>
                                <td class="px-2 py-1 text-[10px] text-slate-600 dark:text-slate-400 whitespace-nowrap">
                                    <?= htmlspecialchars($item['control_number'] ?: '-') ?>
                                </td>
                                <td class="px-2 py-1 text-slate-600 dark:text-slate-400 whitespace-nowrap">
                                    <?= htmlspecialchars($item['location'] ?: '-') ?>
                                </td>
                                <td class="px-2 py-1 text-[10px] text-slate-600 dark:text-slate-400 whitespace-nowrap">
                                    <?= htmlspecialchars($item['om_name'] ?: '-') ?>
                                </td>
                                <td class="col-status px-2 py-1 whitespace-nowrap">
                                    <div class="flex items-center gap-1.5">
                                        <div class="status-dot size-1.5 rounded-full <?= getIpDotClass($item['status']) ?>">
                                        </div>
                                        <span class="status-text font-medium <?= getIpStatusColor($item['status']) ?>">
                                            <?= ucfirst(htmlspecialchars($item['status'])) ?>
                                        </span>
                                    </div>
                                </td>
                                <td class="px-2 py-1 text-slate-600 dark:text-slate-400 whitespace-nowrap">
                                    <?= htmlspecialchars($item['device_type'] ?: 'Other') ?>
                                </td>
                                <td class="px-2 py-1 text-slate-500 truncate max-w-[120px] whitespace-nowrap"
                                    title="<?= htmlspecialchars($item['remarks'] ?? '') ?>">
                                    <?= htmlspecialchars($item['remarks'] ?: '-') ?>
                                </td>
                                <td class="no-print px-2 py-1 whitespace-nowrap">
                                    <?php if (!empty($item['last_edited_by'])): ?>
                                        <div class="text-[10px] font-semibold text-slate-700 dark:text-slate-300"><?= htmlspecialchars($item['last_edited_by']) ?></div>
                                        <div class="text-[9px] text-slate-400"><?= date('M d, Y H:i', strtotime($item['last_edited_at'])) ?></div>
                                    <?php else: ?>
                                        <span class="text-slate-400 text-[10px]">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-2 py-1 text-right flex items-center justify-end gap-1 whitespace-nowrap">
                                    <button onclick="pingIP('<?= htmlspecialchars($item['ip_address']) ?>')"
                                        class="p-1 hover:bg-emerald-500/10 hover:text-emerald-500 text-slate-500 rounded-lg"
                                        title="Ping IP">
                                        <span class="material-symbols-outlined text-[18px]">network_ping</span>
                                    </button>
                                    <button
                                        onclick='openEditModal(<?= htmlspecialchars(json_encode($item), ENT_QUOTES, 'UTF-8') ?>)'
                                        class="p-1 hover:bg-primary/10 hover:text-primary text-slate-500 rounded-lg"><span
                                            class="material-symbols-outlined text-[18px]">edit</span></button>
                                    <form method="POST" onsubmit="return confirm('Delete IP?');">
                                        <?= getCsrfInput() ?>
                                        <input type="hidden" name="action" value="delete_ip">
                                        <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                        <button
                                            class="p-1 hover:bg-red-500/10 hover:text-red-500 text-slate-500 rounded-lg"><span
                                                class="material-symbols-outlined text-[18px]">delete</span></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div
                class="px-6 py-4 border-t border-slate-200 dark:border-[#232b3d] flex items-center justify-between bg-white dark:bg-[#1a2130]">
                <div class="text-xs text-slate-500">
                    Showing <span class="font-bold text-slate-900 dark:text-white">
                        <?= $offset + 1 ?>
                    </span> to <span class="font-bold text-slate-900 dark:text-white">
                        <?= min($offset + $limit, $total_items) ?>
                    </span> of <span class="font-bold text-slate-900 dark:text-white">
                        <?= $total_items ?>
                    </span> results
                </div>
                <div class="flex items-center gap-2">
                    <?php if ($total_pages > 1): ?>
                        <a href="?page=1&search=<?= urlencode($search_term) ?>&subnet=<?= urlencode($filter_subnet) ?>&status=<?= urlencode($filter_status) ?>&limit=<?= $limit ?>"
                            class="p-2 hover:bg-white/5 text-slate-400 hover:text-slate-900 dark:hover:text-white rounded-lg transition-colors <?= $page <= 1 ? 'pointer-events-none opacity-50' : '' ?>"
                            title="First Page">
                            <span class="material-symbols-outlined text-[18px]">first_page</span>
                        </a>
                    <?php endif; ?>

                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search_term) ?>&subnet=<?= urlencode($filter_subnet) ?>&status=<?= urlencode($filter_status) ?>&limit=<?= $limit ?>"
                            class="p-2 hover:bg-white/5 text-slate-400 hover:text-slate-900 dark:hover:text-white rounded-lg transition-colors">
                            <span class="material-symbols-outlined text-[18px]">chevron_left</span>
                        </a>
                    <?php else: ?>
                        <button disabled class="p-2 text-slate-700 cursor-not-allowed">
                            <span class="material-symbols-outlined text-[18px]">chevron_left</span>
                        </button>
                    <?php endif; ?>

                    <div class="flex items-center gap-1">
                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <a href="?page=<?= $i ?>&search=<?= urlencode($search_term) ?>&subnet=<?= urlencode($filter_subnet) ?>&status=<?= urlencode($filter_status) ?>&limit=<?= $limit ?>"
                                class="w-8 h-8 flex items-center justify-center rounded-lg text-xs font-bold transition-colors <?= $i === $page ? 'bg-primary text-white' : 'text-slate-400 hover:bg-white/5 hover:text-slate-900 dark:text-white' ?>">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>
                    </div>

                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search_term) ?>&subnet=<?= urlencode($filter_subnet) ?>&status=<?= urlencode($filter_status) ?>&limit=<?= $limit ?>"
                            class="p-2 hover:bg-white/5 text-slate-400 hover:text-slate-900 dark:hover:text-white rounded-lg transition-colors">
                            <span class="material-symbols-outlined text-[18px]">chevron_right</span>
                        </a>
                    <?php else: ?>
                        <button disabled class="p-2 text-slate-700 cursor-not-allowed">
                            <span class="material-symbols-outlined text-[18px]">chevron_right</span>
                        </button>
                    <?php endif; ?>

                    <?php if ($total_pages > 1): ?>
                        <a href="?page=<?= $total_pages ?>&search=<?= urlencode($search_term) ?>&subnet=<?= urlencode($filter_subnet) ?>&status=<?= urlencode($filter_status) ?>&limit=<?= $limit ?>"
                            class="p-2 hover:bg-white/5 text-slate-400 hover:text-slate-900 dark:hover:text-white rounded-lg transition-colors <?= $page >= $total_pages ? 'pointer-events-none opacity-50' : '' ?>"
                            title="Last Page">
                            <span class="material-symbols-outlined text-[18px]">last_page</span>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
</div>
</div>

</div>
</div>

<!-- Floating Bulk Action Bar -->
<div id="bulkActionBar"
    class="no-print hidden fixed bottom-6 left-1/2 -translate-x-1/2 z-40 flex items-center gap-3 bg-[#1a2130] border border-[#232b3d] shadow-2xl rounded-2xl px-5 py-3">
    <span class="text-xs text-slate-400 font-medium" id="bulkCount">0 selected</span>
    <div class="w-px h-5 bg-[#232b3d]"></div>
    <button onclick="openBulkEditModal()"
        class="flex items-center gap-1.5 px-3 py-1.5 bg-primary text-white text-xs font-bold rounded-lg hover:bg-primary/90 transition-colors">
        <span class="material-symbols-outlined text-[16px]">edit</span> Edit Selected
    </button>
    <button onclick="deselectAll()"
        class="flex items-center gap-1.5 px-3 py-1.5 bg-slate-700 text-slate-300 text-xs font-bold rounded-lg hover:bg-slate-600 transition-colors">
        <span class="material-symbols-outlined text-[16px]">close</span> Deselect All
    </button>
</div>

<!-- Ping Modal -->
<div id="pingModal" class="hidden fixed inset-0 z-50 overflow-y-auto" role="dialog">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-background-dark/80 backdrop-blur-sm" onclick="closePingModal()"></div>
        <div
            class="relative bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] rounded-2xl max-w-2xl w-full p-4 sm:p-6 shadow-2xl">
            <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-4 flex items-center gap-2">
                <span class="material-symbols-outlined text-emerald-500">network_ping</span>
                Ping Result
            </h3>
            <div id="pingOutput"
                class="bg-black/50 rounded-xl p-4 font-mono text-xs text-emerald-500 h-64 overflow-y-auto whitespace-pre-wrap">
            </div>
            <div class="flex justify-end gap-3 mt-6">
                <button type="button" onclick="closePingModal()"
                    class="px-4 py-2 text-sm font-medium text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:text-white transition-colors">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Import Modal -->
<div id="importModal" class="hidden fixed inset-0 z-50 overflow-y-auto" role="dialog">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-background-dark/80 backdrop-blur-sm" onclick="toggleModal('importModal')"></div>
        <div
            class="relative bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] rounded-2xl max-w-lg w-full p-4 sm:p-6 shadow-2xl">
            <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-4">Import IPs from CSV</h3>
            <p class="text-sm text-slate-400 mb-4">Upload a CSV file with columns: IP Address, MAC Address, Hostname,
                Control Number, Department, OM Name, Status, Device Type, Description, Remarks, Subnet ID</p>
            <form method="POST" enctype="multipart/form-data">
                <?= getCsrfInput() ?>
                <input type="hidden" name="action" value="import_csv">
                <div class="mb-4">
                    <input type="file" name="csv_file" accept=".csv" required
                        class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-white rounded-xl px-4 py-2.5 text-sm file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-primary file:text-slate-900 dark:text-white hover:file:bg-primary/90">
                </div>
                <div class="flex justify-end gap-3">
                    <button type="button" onclick="toggleModal('importModal')"
                        class="px-4 py-2 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white">Cancel</button>
                    <button type="submit" class="px-6 py-2 bg-primary text-white font-bold rounded-xl">Import</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Modal - same as before -->
<div id="addIpModal" class="hidden fixed inset-0 z-50 overflow-y-auto" role="dialog">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-background-dark/80 backdrop-blur-sm" onclick="toggleModal('addIpModal')"></div>
        <div
            class="relative bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] rounded-2xl max-w-2xl w-full p-4 sm:p-6 shadow-2xl max-h-[90vh] overflow-y-auto custom-scrollbar">
            <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-6">Add IP Address</h3>
            <form method="POST">
                <?= getCsrfInput() ?>
                <input type="hidden" name="action" value="add_ip">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <input type="text" name="ip_address" placeholder="IP Address" required
                        class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                    <input type="text" name="mac_address" placeholder="MAC Address"
                        class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                    <input type="text" name="hostname" placeholder="Hostname"
                        class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                    <input type="text" name="control_number" placeholder="Control Number"
                        class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                    <!-- Building Dropdown -->
                    <select name="building"
                        class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                        <option value="">Select Building</option>
                        <option value="ACIS">ACIS</option>
                        <option value="Bayanihan">Bayanihan</option>
                        <option value="Bio Safety">Bio Safety</option>
                        <option value="Capiz">Capiz</option>
                        <option value="Dietary">Dietary</option>
                        <option value="Frontline">Frontline</option>
                        <option value="HOPSS">HOPSS</option>
                        <option value="Isolation">Isolation</option>
                        <option value="Lingap Baga">Lingap Baga</option>
                        <option value="Medicine">Medicine</option>
                        <option value="OB-Gyne/Pedia">OB-Gyne/Pedia</option>
                        <option value="OPD">OPD</option>
                        <option value="Orthopaedics">Orthopaedics</option>
                        <option value="Surgery">Surgery</option>
                        <option value="Trauma">Trauma</option>
                        <option value="Wellness">Wellness</option>
                    </select>
                    <!-- Floor Dropdown -->
                    <select name="floor"
                        class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                        <option value="">Select Floor</option>
                        <option value="GF">GF</option>
                        <option value="2F">2F</option>
                        <option value="3F">3F</option>
                        <option value="4F">4F</option>
                        <option value="5F">5F</option>
                        <option value="6F">6F</option>
                        <option value="7F">7F</option>
                    </select>
                    <input type="text" name="department" placeholder="Department"
                        class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                    <input type="text" name="om_name" placeholder="OM Name"
                        class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                    <select name="subnet_id"
                        class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                        <option value="">Select Subnet (Optional)</option>
                        <?php foreach ($subnets as $subnet): ?>
                            <option value="<?= $subnet['id'] ?>">
                                <?= htmlspecialchars($subnet['name']) ?>
                                (
                                <?= htmlspecialchars($subnet['cidr']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <select name="status"
                        class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                        <option value="active">Active</option>
                        <option value="available">Available</option>
                        <option value="reserved">Reserved</option>
                        <option value="offline">Offline</option>
                    </select>
                    <select name="device_type"
                        class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                        <option value="HCI">HCI</option>
                        <option value="Server">Server</option>
                        <option value="Workstation">Workstation</option>
                        <option value="Outsourced">Outsourced</option>
                        <option value="Printer">Printer</option>
                        <option value="LED">LED</option>
                        <option value="Router">Router</option>
                        <option value="Switch">Switch</option>
                        <option value="Access Point">Access Point</option>
                        <option value="Laptop">Laptop</option>
                        <option value="Firewall">Firewall</option>
                        <option value="Other">Other</option>
                    </select>
                    <textarea name="description" placeholder="Description" rows="2"
                        class="col-span-2 w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5 resize-none h-16"></textarea>
                    <textarea name="remarks" placeholder="Remarks" rows="2"
                        class="col-span-2 w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5 resize-none h-16"></textarea>
                </div>
                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" onclick="toggleModal('addIpModal')"
                        class="px-4 py-2 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white">Cancel</button>
                    <button type="submit" class="px-6 py-2 bg-primary text-white font-bold rounded-xl">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Modal - same as before -->
<div id="editIpModal" class="hidden fixed inset-0 z-50 overflow-y-auto" role="dialog">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-background-dark/80 backdrop-blur-sm" onclick="toggleModal('editIpModal')"></div>
        <div
            class="relative bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] rounded-2xl max-w-2xl w-full p-4 sm:p-6 shadow-2xl max-h-[90vh] overflow-y-auto custom-scrollbar">
            <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-6">Edit IP Address</h3>
            <form method="POST">
                <?= getCsrfInput() ?>
                <input type="hidden" name="action" value="edit_ip">
                <input type="hidden" name="id" id="edit_id">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">IP Address</label>
                        <input type="text" name="ip_address" id="edit_ip_address" required
                            class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">MAC Address</label>
                        <input type="text" name="mac_address" id="edit_mac_address"
                            class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Hostname</label>
                        <input type="text" name="hostname" id="edit_hostname"
                            class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Control Number</label>
                        <input type="text" name="control_number" id="edit_control_number"
                            class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Building</label>
                        <select name="building" id="edit_building"
                            class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                            <option value="">Select Building</option>
                            <option value="ACIS">ACIS</option>
                            <option value="Bayanihan">Bayanihan</option>
                            <option value="Bio Safety">Bio Safety</option>
                            <option value="Capiz">Capiz</option>
                            <option value="Dietary">Dietary</option>
                            <option value="Frontline">Frontline</option>
                            <option value="HOPSS">HOPSS</option>
                            <option value="Isolation">Isolation</option>
                            <option value="Lingap Baga">Lingap Baga</option>
                            <option value="Medicine">Medicine</option>
                            <option value="OB-Gyne/Pedia">OB-Gyne/Pedia</option>
                            <option value="OPD">OPD</option>
                            <option value="Orthopaedics">Orthopaedics</option>
                            <option value="Surgery">Surgery</option>
                            <option value="Trauma">Trauma</option>
                            <option value="Wellness">Wellness</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Floor</label>
                        <select name="floor" id="edit_floor"
                            class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                            <option value="">Select Floor</option>
                            <option value="GF">GF</option>
                            <option value="2F">2F</option>
                            <option value="3F">3F</option>
                            <option value="4F">4F</option>
                            <option value="5F">5F</option>
                            <option value="6F">6F</option>
                            <option value="7F">7F</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Department</label>
                        <input type="text" name="department" id="edit_department"
                            class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">OM Name</label>
                        <input type="text" name="om_name" id="edit_om_name"
                            class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Status</label>
                        <select name="status" id="edit_status"
                            class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                            <option value="active">Active</option>
                            <option value="available">Available</option>
                            <option value="reserved">Reserved</option>
                            <option value="offline">Offline</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Device Type</label>
                        <select name="device_type" id="edit_device_type"
                            class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                            <option value="HCI">HCI</option>
                            <option value="Server">Server</option>
                            <option value="Workstation">Workstation</option>
                            <option value="Outsourced">Outsourced</option>
                            <option value="Printer">Printer</option>
                            <option value="LED">LED</option>
                            <option value="Router">Router</option>
                            <option value="Switch">Switch</option>
                            <option value="Access Point">Access Point</option>
                            <option value="Laptop">Laptop</option>
                            <option value="Firewall">Firewall</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="col-span-2">
                        <label class="block text-xs font-medium text-slate-400 mb-1">Description</label>
                        <textarea name="description" id="edit_description" rows="2"
                            class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5 resize-none h-16"></textarea>
                    </div>
                    <div class="col-span-2">
                        <label class="block text-xs font-medium text-slate-400 mb-1">Remarks</label>
                        <textarea name="remarks" id="edit_remarks" rows="2"
                            class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5 resize-none h-16"></textarea>
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" onclick="toggleModal('editIpModal')"
                        class="px-4 py-2 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white">Cancel</button>
                    <button type="submit" class="px-6 py-2 bg-primary text-white font-bold rounded-xl">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Bulk Edit Modal -->
<div id="bulkEditModal" class="hidden fixed inset-0 z-50 overflow-y-auto" role="dialog">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-background-dark/80 backdrop-blur-sm" onclick="closeBulkEditModal()"></div>
        <div
            class="relative bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] rounded-2xl max-w-2xl w-full p-4 sm:p-6 shadow-2xl max-h-[90vh] overflow-y-auto custom-scrollbar">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="text-lg font-bold text-slate-900 dark:text-white">Edit Selected IP</h3>
                    <p class="text-xs text-slate-400 mt-0.5" id="bulkEditCounter">Record 1 of 1</p>
                </div>
                <div class="flex items-center gap-2">
                    <button onclick="bulkEditPrev()" id="bulkPrevBtn"
                        class="p-1.5 rounded-lg bg-slate-100 dark:bg-[#232b3d] text-slate-500 hover:text-primary disabled:opacity-40 disabled:cursor-not-allowed transition-colors">
                        <span class="material-symbols-outlined text-[18px]">chevron_left</span>
                    </button>
                    <button onclick="bulkEditNext()" id="bulkNextBtn"
                        class="p-1.5 rounded-lg bg-slate-100 dark:bg-[#232b3d] text-slate-500 hover:text-primary disabled:opacity-40 disabled:cursor-not-allowed transition-colors">
                        <span class="material-symbols-outlined text-[18px]">chevron_right</span>
                    </button>
                    <button onclick="closeBulkEditModal()" class="p-1.5 rounded-lg bg-slate-100 dark:bg-[#232b3d] text-slate-500 hover:text-red-500 transition-colors">
                        <span class="material-symbols-outlined text-[18px]">close</span>
                    </button>
                </div>
            </div>
            <form method="POST" id="bulkEditForm">
                <?= getCsrfInput() ?>
                <input type="hidden" name="action" value="edit_ip">
                <input type="hidden" name="id" id="bulk_edit_id">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">IP Address</label>
                        <input type="text" name="ip_address" id="bulk_edit_ip_address" required
                            class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">MAC Address</label>
                        <input type="text" name="mac_address" id="bulk_edit_mac_address"
                            class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Hostname</label>
                        <input type="text" name="hostname" id="bulk_edit_hostname"
                            class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Control Number</label>
                        <input type="text" name="control_number" id="bulk_edit_control_number"
                            class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Building</label>
                        <select name="building" id="bulk_edit_building"
                            class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                            <option value="">Select Building</option>
                            <option value="ACIS">ACIS</option>
                            <option value="Bayanihan">Bayanihan</option>
                            <option value="Bio Safety">Bio Safety</option>
                            <option value="Capiz">Capiz</option>
                            <option value="Dietary">Dietary</option>
                            <option value="Frontline">Frontline</option>
                            <option value="HOPSS">HOPSS</option>
                            <option value="Isolation">Isolation</option>
                            <option value="Lingap Baga">Lingap Baga</option>
                            <option value="Medicine">Medicine</option>
                            <option value="OB-Gyne/Pedia">OB-Gyne/Pedia</option>
                            <option value="OPD">OPD</option>
                            <option value="Orthopaedics">Orthopaedics</option>
                            <option value="Surgery">Surgery</option>
                            <option value="Trauma">Trauma</option>
                            <option value="Wellness">Wellness</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Floor</label>
                        <select name="floor" id="bulk_edit_floor"
                            class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                            <option value="">Select Floor</option>
                            <option value="GF">GF</option>
                            <option value="2F">2F</option>
                            <option value="3F">3F</option>
                            <option value="4F">4F</option>
                            <option value="5F">5F</option>
                            <option value="6F">6F</option>
                            <option value="7F">7F</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Department</label>
                        <input type="text" name="department" id="bulk_edit_department"
                            class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">OM Name</label>
                        <input type="text" name="om_name" id="bulk_edit_om_name"
                            class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Status</label>
                        <select name="status" id="bulk_edit_status"
                            class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                            <option value="active">Active</option>
                            <option value="available">Available</option>
                            <option value="reserved">Reserved</option>
                            <option value="offline">Offline</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Device Type</label>
                        <select name="device_type" id="bulk_edit_device_type"
                            class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                            <option value="HCI">HCI</option>
                            <option value="Server">Server</option>
                            <option value="Workstation">Workstation</option>
                            <option value="Outsourced">Outsourced</option>
                            <option value="Printer">Printer</option>
                            <option value="LED">LED</option>
                            <option value="Router">Router</option>
                            <option value="Switch">Switch</option>
                            <option value="Access Point">Access Point</option>
                            <option value="Laptop">Laptop</option>
                            <option value="Firewall">Firewall</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="col-span-2">
                        <label class="block text-xs font-medium text-slate-400 mb-1">Description</label>
                        <textarea name="description" id="bulk_edit_description" rows="2"
                            class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5 resize-none h-16"></textarea>
                    </div>
                    <div class="col-span-2">
                        <label class="block text-xs font-medium text-slate-400 mb-1">Remarks</label>
                        <textarea name="remarks" id="bulk_edit_remarks" rows="2"
                            class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5 resize-none h-16"></textarea>
                    </div>
                </div>
                <div class="mt-6 flex justify-between items-center">
                    <div class="flex gap-2">
                        <button type="button" onclick="bulkEditPrev()" id="bulkPrevBtn2"
                            class="flex items-center gap-1 px-4 py-2 bg-slate-100 dark:bg-[#232b3d] text-slate-600 dark:text-slate-300 text-sm font-medium rounded-xl hover:bg-slate-200 dark:hover:bg-[#2d3748] disabled:opacity-40 disabled:cursor-not-allowed transition-colors">
                            <span class="material-symbols-outlined text-[16px]">chevron_left</span> Prev
                        </button>
                        <button type="button" onclick="bulkEditNext()" id="bulkNextBtn2"
                            class="flex items-center gap-1 px-4 py-2 bg-slate-100 dark:bg-[#232b3d] text-slate-600 dark:text-slate-300 text-sm font-medium rounded-xl hover:bg-slate-200 dark:hover:bg-[#2d3748] disabled:opacity-40 disabled:cursor-not-allowed transition-colors">
                            Next <span class="material-symbols-outlined text-[16px]">chevron_right</span>
                        </button>
                    </div>
                    <div class="flex gap-3">
                        <button type="button" onclick="closeBulkEditModal()"
                            class="px-4 py-2 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white">Cancel</button>
                        <button type="submit" class="px-6 py-2 bg-primary text-white font-bold rounded-xl">Update</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>


    function openEditModal(item) {
        document.getElementById('edit_id').value = item.id;
        document.getElementById('edit_ip_address').value = item.ip_address;
        document.getElementById('edit_mac_address').value = item.mac_address || '';
        document.getElementById('edit_hostname').value = item.hostname || '';
        document.getElementById('edit_control_number').value = item.control_number || '';

        // Parse Location to fill Building, Floor, Department
        const locationStr = item.location || '';
        let building = '';
        let floor = '';
        let dept = locationStr;

        const buildings = ['ACIS', 'Bayanihan', 'Bio Safety', 'Capiz', 'Dietary', 'Frontline', 'HOPSS', 'Isolation', 'Lingap Baga', 'Medicine', 'OB-Gyne/Pedia', 'OPD', 'Orthopaedics', 'Surgery', 'Trauma', 'Wellness'];
        const floors = ['GF', '2F', '3F', '4F', '5F', '6F', '7F'];

        // Simple parsing logic
        // 1. Check if starts with Building
        for (const b of buildings) {
            if (dept.startsWith(b)) {
                building = b;
                dept = dept.substring(b.length).trim();
                break;
            }
        }
        // 2. Check if next part is Floor
        for (const f of floors) {
            if (dept.startsWith(f)) {
                floor = f;
                dept = dept.substring(f.length).trim();
                break;
            }
        }

        document.getElementById('edit_building').value = building;
        document.getElementById('edit_floor').value = floor;
        document.getElementById('edit_department').value = dept;
        document.getElementById('edit_om_name').value = item.om_name || '';
        document.getElementById('edit_status').value = item.status;
        document.getElementById('edit_device_type').value = item.device_type || '';
        document.getElementById('edit_description').value = item.description || '';
        document.getElementById('edit_remarks').value = item.remarks || '';
        toggleModal('editIpModal');
    }

    // Live Search Sync
    let searchTimeout;
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('input', function () {
            clearTimeout(searchTimeout);
            const form = searchInput.form;

            searchTimeout = setTimeout(() => {
                const params = new URLSearchParams(new FormData(form));
                params.set('ajax_search', '1'); // Use set to ensure no duplicates

                fetch(window.location.pathname + '?' + params.toString())
                    .then(response => {
                        if (!response.ok) throw new Error('Network response was not ok');
                        return response.text();
                    })
                    .then(html => {
                        // Safety check: if response is full page (contains <html), don't replace
                        if (html.includes('<!DOCTYPE html>')) {
                            console.warn('Received full page instead of partial. Reloading.');
                            // Optional: window.location.reload();
                            return;
                        }

                        document.querySelector('tbody').innerHTML = html;

                        // Update URL
                        const urlParams = new URLSearchParams(new FormData(form));
                        // Don't include ajax_search in the browser URL
                        window.history.replaceState({}, '', '?' + urlParams.toString());
                    })
                    .catch(err => console.error('Search error:', err));
            }, 300);
        });

        // Cursor logic removed as page no longer reloads
    }
    function filterSubnets() {
        const search = document.getElementById('subnetSearch').value.toLowerCase();
        const options = document.querySelectorAll('#subnetOptions label');
        options.forEach(option => {
            const text = option.textContent.toLowerCase();
            option.style.display = text.includes(search) ? 'flex' : 'none';
        });
    }

    function pingIP(ip) {
        const modal = document.getElementById('pingModal');
        const outputDiv = document.getElementById('pingOutput');

        modal.classList.remove('hidden');
        outputDiv.innerHTML = "Pinging " + ip + "...\n";

        const formData = new FormData();
        formData.append('action', 'ping_ip');
        formData.append('ip_address', ip);
        formData.append('csrf_token', '<?= generateCsrfToken() ?>');

        fetch('', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    outputDiv.textContent = data.output;

                    // Live Update DOM if data returned
                    if (data.data) {
                        const rowId = 'row-' + ip.replace(/\./g, '-');
                        const row = document.getElementById(rowId);
                        if (row) {
                            // Update MAC
                            const macCell = row.querySelector('.col-mac');
                            if (macCell) macCell.textContent = data.data.mac_address || '-';

                            // Update Hostname
                            const hostCell = row.querySelector('.col-hostname');
                            if (hostCell) hostCell.textContent = data.data.hostname || '-';

                            // Update Status
                            const statusCell = row.querySelector('.col-status');
                            if (statusCell) {
                                let dotClass = 'bg-slate-500';
                                let textClass = 'text-slate-500';
                                const status = data.data.status.toLowerCase();

                                if (status === 'active') {
                                    dotClass = 'bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.4)]';
                                    textClass = 'text-emerald-500';
                                } else if (status === 'reserved') {
                                    dotClass = 'bg-blue-500';
                                    textClass = 'text-blue-500';
                                } else if (status === 'offline') {
                                    dotClass = 'bg-slate-500';
                                    textClass = 'text-slate-500';
                                } else if (status === 'conflict') {
                                    dotClass = 'bg-red-500';
                                    textClass = 'text-red-500';
                                }

                                statusCell.innerHTML = `
                                    <div class="flex items-center gap-1.5">
                                        <div class="status-dot size-1.5 rounded-full ${dotClass}"></div>
                                        <span class="status-text font-medium ${textClass}">${status.charAt(0).toUpperCase() + status.slice(1)}</span>
                                    </div>
                                `;
                            }
                        }
                    }
                } else {
                    outputDiv.textContent = "Error: " + (data.error || 'Unknown error');
                }
            })
            .catch(error => {
                outputDiv.textContent = "Request failed: " + error;
            });
    }

    function closePingModal() {
        document.getElementById('pingModal').classList.add('hidden');
        document.getElementById('pingOutput').textContent = '';
        location.reload(); // Refresh to show updated status/MAC
    }

    function runArpScan() {
        const modal = document.getElementById('pingModal');
        const outputDiv = document.getElementById('pingOutput');
        const modalTitle = modal.querySelector('h3');

        // Update modal title
        modalTitle.innerHTML = '<span class="material-symbols-outlined text-cyan-500">router</span> ARP Scan Results';

        modal.classList.remove('hidden');
        outputDiv.innerHTML = "Running ARP scan...\nScanning network for active devices...\n";

        const formData = new FormData();
        formData.append('action', 'arp_scan');
        formData.append('csrf_token', '<?= generateCsrfToken() ?>');

        fetch('', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    outputDiv.textContent = data.output;
                } else {
                    outputDiv.textContent = "Error: " + (data.error || 'Unknown error');
                }
            })
            .catch(error => {
                outputDiv.textContent = "Request failed: " + error;
            });
    }
    // ---- Bulk Selection Bar ----
    function updateBulkBar() {
        const checked = document.querySelectorAll('.item-checkbox:checked');
        const bar = document.getElementById('bulkActionBar');
        const countLabel = document.getElementById('bulkCount');
        if (checked.length > 0) {
            bar.classList.remove('hidden');
            countLabel.textContent = checked.length + ' selected';
        } else {
            bar.classList.add('hidden');
        }
    }

    function deselectAll() {
        document.querySelectorAll('.item-checkbox').forEach(cb => cb.checked = false);
        // Also uncheck the master checkbox
        const master = document.querySelector('thead input[type="checkbox"]');
        if (master) master.checked = false;
        updateBulkBar();
    }

    function toggleAll(source) {
        const checkboxes = document.querySelectorAll('.item-checkbox');
        for (let i = 0; i < checkboxes.length; i++) {
            checkboxes[i].checked = source.checked;
        }
        updateBulkBar();
    }

    // Delegate checkbox change events on tbody (handles both static and AJAX-injected rows)
    document.querySelector('tbody').addEventListener('change', function(e) {
        if (e.target && e.target.classList.contains('item-checkbox')) {
            updateBulkBar();
        }
    });

    // ---- Bulk Edit Modal ----
    let bulkItems = [];
    let bulkIndex = 0;

    const BUILDINGS = ['ACIS','Bayanihan','Bio Safety','Capiz','Dietary','Frontline','HOPSS','Isolation','Lingap Baga','Medicine','OB-Gyne/Pedia','OPD','Orthopaedics','Surgery','Trauma','Wellness'];
    const FLOORS = ['GF','2F','3F','4F','5F','6F','7F'];

    function parseLocation(locationStr) {
        let rem = locationStr || '';
        let building = '', floor = '';
        for (const b of BUILDINGS) {
            if (rem.startsWith(b)) { building = b; rem = rem.substring(b.length).trim(); break; }
        }
        for (const f of FLOORS) {
            if (rem.startsWith(f)) { floor = f; rem = rem.substring(f.length).trim(); break; }
        }
        return { building, floor, dept: rem };
    }

    function populateBulkForm(item) {
        document.getElementById('bulk_edit_id').value = item.id;
        document.getElementById('bulk_edit_ip_address').value = item.ip_address || '';
        document.getElementById('bulk_edit_mac_address').value = item.mac_address || '';
        document.getElementById('bulk_edit_hostname').value = item.hostname || '';
        document.getElementById('bulk_edit_control_number').value = item.control_number || '';
        const loc = parseLocation(item.location || '');
        document.getElementById('bulk_edit_building').value = loc.building;
        document.getElementById('bulk_edit_floor').value = loc.floor;
        document.getElementById('bulk_edit_department').value = loc.dept;
        document.getElementById('bulk_edit_om_name').value = item.om_name || '';
        document.getElementById('bulk_edit_status').value = item.status || 'active';
        document.getElementById('bulk_edit_device_type').value = item.device_type || '';
        document.getElementById('bulk_edit_description').value = item.description || '';
        document.getElementById('bulk_edit_remarks').value = item.remarks || '';

        const total = bulkItems.length;
        document.getElementById('bulkEditCounter').textContent = 'Record ' + (bulkIndex + 1) + ' of ' + total;
        document.getElementById('bulkPrevBtn').disabled = bulkIndex === 0;
        document.getElementById('bulkNextBtn').disabled = bulkIndex === total - 1;
        document.getElementById('bulkPrevBtn2').disabled = bulkIndex === 0;
        document.getElementById('bulkNextBtn2').disabled = bulkIndex === total - 1;
    }

    function openBulkEditModal() {
        const checkedRows = document.querySelectorAll('.item-checkbox:checked');
        if (checkedRows.length === 0) {
            alert('No items selected, or rows are missing data-item attributes.');
            return;
        }

        bulkItems = [];
        let missingData = false;
        checkedRows.forEach(cb => {
            const tr = cb.closest('tr');
            const raw = tr ? tr.getAttribute('data-item') : null;
            if (!raw) { missingData = true; return; }
            try {
                bulkItems.push(JSON.parse(raw));
            } catch(e) {
                missingData = true;
            }
        });

        if (bulkItems.length === 0) {
            alert('No items selected, or rows are missing data-item attributes.');
            return;
        }

        bulkIndex = 0;
        populateBulkForm(bulkItems[bulkIndex]);
        document.getElementById('bulkEditModal').classList.remove('hidden');
    }

    function closeBulkEditModal() {
        document.getElementById('bulkEditModal').classList.add('hidden');
        bulkItems = [];
        bulkIndex = 0;
    }

    function bulkEditPrev() {
        if (bulkIndex > 0) {
            bulkIndex--;
            populateBulkForm(bulkItems[bulkIndex]);
        }
    }

    function bulkEditNext() {
        if (bulkIndex < bulkItems.length - 1) {
            bulkIndex++;
            populateBulkForm(bulkItems[bulkIndex]);
        }
    }

    // Auto-advance to next after saving, or close if last
    document.getElementById('bulkEditForm').addEventListener('submit', function() {
        // Update the in-memory item so going back shows unsaved next record correctly
        // (the page will reload after POST anyway, so just let it submit)
    });

    function printData() {
        const checkboxes = document.querySelectorAll('.item-checkbox:checked');
        const rows = document.querySelectorAll('tbody tr');

        if (checkboxes.length > 0 && checkboxes.length < rows.length) {
            document.body.classList.add('print-filtered');
            rows.forEach(tr => {
                const cb = tr.querySelector('.item-checkbox');
                if (cb && cb.checked) {
                    tr.classList.add('print-row-selected');
                } else {
                    tr.classList.remove('print-row-selected');
                }
            });
        } else {
            document.body.classList.remove('print-filtered');
            rows.forEach(tr => tr.classList.remove('print-row-selected'));
        }

        setTimeout(() => {
            window.print();
        }, 500);
    }
</script>

<!-- Print Styles -->
<style>
    @media print {
        .no-print {
            display: none !important;
        }

        /* Hide sidebar */
        aside,
        .sidebar {
            display: none !important;
        }

        /* Reset overflow for containers to prevent cutoff */
        .overflow-x-auto,
        .overflow-hidden,
        .custom-scrollbar,
        .rounded-2xl {
            overflow: visible !important;
            height: auto !important;
            border-radius: 0 !important;
            box-shadow: none !important;
        }

        /* Hide header buttons */
        header .flex.items-center.gap-3 {
            display: none !important;
        }

        /* Hide stats cards */
        .grid.grid-cols-1.md\:grid-cols-3.gap-4.mb-8 {
            display: none !important;
        }

        /* Hide control bar (search, filters, pagination) */
        .mb-6.flex.gap-3 {
            display: none !important;
        }

        /* Hide action buttons in table */
        td:last-child,
        th:last-child {
            display: none !important;
        }

        /* Hide bottom pagination */
        .px-6.py-4.border-t,
        .border-t.border-\[\#232b3d\] {
            display: none !important;
        }

        /* Show only main content */
        body {
            background: white !important;
        }

        .p-8 {
            padding: 20px !important;
        }

        /* Keep title visible */
        header h2,
        header p {
            color: black !important;
        }

        /* Table styling for print */
        table {
            width: 100% !important;
            border-collapse: collapse !important;
        }

        th,
        td {
            border: 1px solid #ddd !important;
            padding: 8px !important;
            color: black !important;
            background: white !important;
        }

        th {
            background-color: #f2f2f2 !important;
            font-weight: bold !important;
        }

        /* Remove hover effects */
        tr:hover {
            background: transparent !important;
        }
    }
</style>



<?php require_once '../../includes/footer.php'; ?>