<?php
require_once '../../config.php';
requireLogin();

$success_msg = $error_msg = '';
$is_ajax = isset($_GET['ajax']);

// Excel Export (.xlsx with Building/Floor dropdowns)
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    $buildings = ['ACIS','Bayanihan','Bio Safety','Capiz','Dietary','Frontline','HOPSS','Isolation','Lingap Baga','Medicine','OB-Gyne/Pedia','OPD','Orthopaedics','Surgery','Trauma','Wellness'];
    $floors    = ['GF','2F','3F','4F','5F','6F','7F'];

    // Parse stored "Building Floor Department" string
    $parse_loc = function(string $loc) use ($buildings, $floors): array {
        $rem = $loc; $b = ''; $f = '';
        foreach ($buildings as $bld) { if (str_starts_with($rem, $bld)) { $b = $bld; $rem = ltrim(substr($rem, strlen($bld))); break; } }
        foreach ($floors as $fl)    { if (str_starts_with($rem, $fl))  { $f = $fl;  $rem = ltrim(substr($rem, strlen($fl)));  break; } }
        return [$b, $f, $rem];
    };

    // IDs filter (selective export)
    $where_sql = '1=1'; $params = [];
    if (isset($_GET['ids']) && !empty($_GET['ids'])) {
        $ids = array_filter(explode(',', $_GET['ids']), 'is_numeric');
        if (!empty($ids)) { $placeholders = implode(',', array_fill(0, count($ids), '?')); $where_sql = "id IN ($placeholders)"; $params = array_values($ids); }
    }
    $stmt = $pdo->prepare("SELECT * FROM computers WHERE $where_sql ORDER BY id DESC");
    $stmt->execute($params);
    $items = $stmt->fetchAll();

    $xmlEsc = fn($v) => htmlspecialchars((string)$v, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    $sharedStrings = [];
    $ssi = function(string $s) use (&$sharedStrings): int {
        $key = array_search($s, $sharedStrings, true);
        if ($key === false) { $sharedStrings[] = $s; $key = count($sharedStrings) - 1; }
        return $key;
    };

    $headers = ['Building','Floor','Department','End User','MR/PAR','Control Number','System Unit','System Unit S/N','Monitor','Monitor S/N','Mouse','Mouse S/N','Keyboard','Keyboard S/N','Printer','Printer S/N','Scanner','Scanner S/N','AVR/UPS','AVR/UPS S/N','Processor','Memory','Storage','OS','OS Product Key','License','Microsoft Office','MS Office Email','IP Address','MAC Address','Endpoint Secure','Firewall','Checked By','Encoded By','Remarks'];
    // Column letters A..AI (35 cols)
    $colLetters = [];
    for ($i = 0; $i < 35; $i++) { $colLetters[] = $i < 26 ? chr(65+$i) : 'A'.chr(65+$i-26); }

    $rows = [];
    $headerRow = []; foreach ($headers as $h) { $headerRow[] = ['t'=>'s','v'=>$ssi($h)]; }
    $rows[] = $headerRow;

    foreach ($items as $item) {
        [$bld, $fl, $dept] = $parse_loc($item['department'] ?? '');
        $rows[] = [
            ['t'=>'s','v'=>$ssi($bld)],
            ['t'=>'s','v'=>$ssi($fl)],
            ['t'=>'s','v'=>$ssi($dept)],
            ['t'=>'s','v'=>$ssi($item['end_user']       ?? '')],
            ['t'=>'s','v'=>$ssi($item['mr_par']          ?? '')],
            ['t'=>'s','v'=>$ssi($item['control_number']  ?? '')],
            ['t'=>'s','v'=>$ssi($item['system_unit']     ?? '')],
            ['t'=>'s','v'=>$ssi($item['system_unit_sn']  ?? '')],
            ['t'=>'s','v'=>$ssi($item['monitor']         ?? '')],
            ['t'=>'s','v'=>$ssi($item['monitor_sn']      ?? '')],
            ['t'=>'s','v'=>$ssi($item['mouse']           ?? '')],
            ['t'=>'s','v'=>$ssi($item['mouse_sn']        ?? '')],
            ['t'=>'s','v'=>$ssi($item['keyboard']        ?? '')],
            ['t'=>'s','v'=>$ssi($item['keyboard_sn']     ?? '')],
            ['t'=>'s','v'=>$ssi($item['printer']         ?? '')],
            ['t'=>'s','v'=>$ssi($item['printer_sn']      ?? '')],
            ['t'=>'s','v'=>$ssi($item['scanner']         ?? '')],
            ['t'=>'s','v'=>$ssi($item['scanner_sn']      ?? '')],
            ['t'=>'s','v'=>$ssi($item['avr_ups']         ?? '')],
            ['t'=>'s','v'=>$ssi($item['avr_ups_sn']      ?? '')],
            ['t'=>'s','v'=>$ssi($item['processor']       ?? '')],
            ['t'=>'s','v'=>$ssi($item['memory']          ?? '')],
            ['t'=>'s','v'=>$ssi($item['storage']         ?? '')],
            ['t'=>'s','v'=>$ssi($item['os']              ?? '')],
            ['t'=>'s','v'=>$ssi($item['os_product_key']  ?? $item['ms_office_key'] ?? '')],
            ['t'=>'s','v'=>$ssi($item['license']         ?? '')],
            ['t'=>'s','v'=>$ssi($item['microsoft_office']?? '')],
            ['t'=>'s','v'=>$ssi($item['ms_office_email'] ?? '')],
            ['t'=>'s','v'=>$ssi($item['ip_address']      ?? '')],
            ['t'=>'s','v'=>$ssi($item['mac_address']     ?? '')],
            ['t'=>'s','v'=>$ssi($item['endpoint_secure'] ?? 'N')],
            ['t'=>'s','v'=>$ssi($item['firewall']        ?? 'N')],
            ['t'=>'s','v'=>$ssi($item['checked_by']      ?? '')],
            ['t'=>'s','v'=>$ssi($item['encoded_by']      ?? '')],
            ['t'=>'s','v'=>$ssi($item['remarks']         ?? '')],
        ];
    }

    // Build sheet XML
    $sheetXml  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
    $sheetXml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
    $sheetXml .= '<sheetData>';
    foreach ($rows as $ri => $row) {
        $rIdx = $ri + 1;
        $sheetXml .= "<row r=\"$rIdx\">";
        foreach ($row as $ci => $cell) {
            $ref = $colLetters[$ci] . $rIdx;
            $sheetXml .= $cell['t'] === 's'
                ? "<c r=\"$ref\" t=\"s\"><v>{$cell['v']}</v></c>"
                : "<c r=\"$ref\"><v>{$cell['v']}</v></c>";
        }
        $sheetXml .= '</row>';
    }
    $sheetXml .= '</sheetData>';

    // Dropdown validation: col A = Building, col B = Floor
    $lastRow = max(count($items) + 51, 200);
    $buildingFormula = '"' . implode(',', $buildings) . '"';
    $floorFormula    = '"' . implode(',', $floors) . '"';
    $sheetXml .= '<dataValidations count="2">';
    $sheetXml .= '<dataValidation type="list" allowBlank="1" showDropDown="0" sqref="A2:A'.$lastRow.'">';
    $sheetXml .= '<formula1>'.$xmlEsc($buildingFormula).'</formula1></dataValidation>';
    $sheetXml .= '<dataValidation type="list" allowBlank="1" showDropDown="0" sqref="B2:B'.$lastRow.'">';
    $sheetXml .= '<formula1>'.$xmlEsc($floorFormula).'</formula1></dataValidation>';
    $sheetXml .= '</dataValidations>';
    $sheetXml .= '</worksheet>';

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
    $workbookXml .= '<sheets><sheet name="Computers" sheetId="1" r:id="rId1"/></sheets></workbook>';

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

    $tmpFile = tempnam(sys_get_temp_dir(), 'computers_') . '.xlsx';
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

    $filename = 'computers_export_' . date('Y-m-d_His') . '.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    header('Content-Length: ' . filesize($tmpFile));
    readfile($tmpFile);
    unlink($tmpFile);
    exit;
}

// CSV Export

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="computers_export_' . date('Y-m-d_His') . '.csv"');

    $output = fopen('php://output', 'w');
    fputcsv($output, [
        'Building',
        'Floor',
        'Department',
        'End User',
        'MR/PAR',
        'Control Number',
        'System Unit',
        'System Unit S/N',
        'Monitor',
        'Monitor S/N',
        'Mouse',
        'Mouse S/N',
        'Keyboard',
        'Keyboard S/N',
        'Printer',
        'Printer S/N',
        'Scanner',
        'Scanner S/N',
        'AVR/UPS',
        'AVR/UPS S/N',
        'Processor',
        'Memory',
        'Storage',
        'OS',
        'OS Product Key',
        'License',
        'Microsoft Office',
        'MS Office Email',
        'IP Address',
        'MAC Address',
        'Endpoint Secure',
        'Firewall',
        'Checked By',
        'Encoded By',
        'Remarks'
    ]);

    // Building/Floor parse helpers
    $buildings = ['ACIS','Bayanihan','Bio Safety','Capiz','Dietary','Frontline','HOPSS','Isolation','Lingap Baga','Medicine','OB-Gyne/Pedia','OPD','Orthopaedics','Surgery','Trauma','Wellness'];
    $floors    = ['GF','2F','3F','4F','5F','6F','7F'];

    // Check for specific IDs to export (selective export from checkboxes)
    $where_sql = '1=1';
    $params    = [];
    if (isset($_GET['ids']) && !empty($_GET['ids'])) {
        $ids = array_filter(explode(',', $_GET['ids']), 'is_numeric');
        if (!empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $where_sql    = "id IN ($placeholders)";
            $params       = array_values($ids);
        }
    }

    $stmt = $pdo->prepare("SELECT * FROM computers WHERE $where_sql ORDER BY id DESC");
    $stmt->execute($params);
    while ($row = $stmt->fetch()) {
        // Parse the stored location string back into Building, Floor, Department
        $loc = $row['department'] ?? '';
        $exp_building = '';
        $exp_floor    = '';
        $exp_dept     = $loc;
        foreach ($buildings as $b) {
            if (stripos($exp_dept, $b) === 0) {
                $exp_building = $b;
                $exp_dept = trim(substr($exp_dept, strlen($b)));
                break;
            }
        }
        foreach ($floors as $f) {
            if (strpos($exp_dept, $f) === 0) {
                $exp_floor = $f;
                $exp_dept  = trim(substr($exp_dept, strlen($f)));
                break;
            }
        }

        fputcsv($output, [
            escapeCsvField($exp_building),
            escapeCsvField($exp_floor),
            escapeCsvField($exp_dept),
            escapeCsvField($row['end_user']),
            escapeCsvField($row['mr_par']),
            escapeCsvField($row['control_number']),
            escapeCsvField($row['system_unit']),
            escapeCsvField($row['system_unit_sn']),
            escapeCsvField($row['monitor']),
            escapeCsvField($row['monitor_sn']),
            escapeCsvField($row['mouse']),
            escapeCsvField($row['mouse_sn']),
            escapeCsvField($row['keyboard']),
            escapeCsvField($row['keyboard_sn']),
            escapeCsvField($row['printer']),
            escapeCsvField($row['printer_sn']),
            escapeCsvField($row['scanner']),
            escapeCsvField($row['scanner_sn']),
            escapeCsvField($row['avr_ups']),
            escapeCsvField($row['avr_ups_sn']),
            escapeCsvField($row['processor']),
            escapeCsvField($row['memory']),
            escapeCsvField($row['storage']),
            escapeCsvField($row['os']),
            escapeCsvField($row['os_product_key'] ?? $row['ms_office_key'] ?? ''),
            escapeCsvField($row['license']),
            escapeCsvField($row['microsoft_office']),
            escapeCsvField($row['ms_office_email'] ?? ''),
            escapeCsvField($row['ip_address']),
            escapeCsvField($row['mac_address']),
            escapeCsvField($row['endpoint_secure'] ?? 'N'),
            escapeCsvField($row['firewall']        ?? 'N'),
            escapeCsvField($row['checked_by']),
            escapeCsvField($row['encoded_by']),
            escapeCsvField($row['remarks'])
        ]);
    }
    fclose($output);
    exit;
}

// CSRF check for all POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        // Special case for file upload which might not have action yet in same way
        if (!(isset($_FILES['csv_file']) && !isset($_POST['action']))) {
            $error_msg = "Security validation failed. Please refresh the page and try again.";
            // Skip processing other POST blocks
            $_POST['action'] = 'invalid';
        }
    }
}

// CSV Import
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    // Re-verify specifically for file upload
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $error_msg = "Security validation failed. Please refresh the page and try again.";
    } else {
        $file = $_FILES['csv_file']['tmp_name'];
        if (($handle = fopen($file, 'r')) !== false) {
            $imported   = 0;
            $updated    = 0;
            $skipped    = 0;
            $duplicates = 0;
            $errors     = [];

            // Read header to auto-detect format
            $header = fgetcsv($handle);
            $header = array_map('trim', $header ?? []);

            // New format: starts with Building, Floor, Department (34 cols)
            // Old format: starts with Location (32 cols)
            $is_new_format = (isset($header[0]) && strtolower($header[0]) === 'building');

            while (($row = fgetcsv($handle)) !== false) {
                $col_count = count($row);

                if ($is_new_format) {
                    // New 35-col format: Building(0), Floor(1), Department(2), End User(3), ...
                    if ($col_count < 32) continue; // too few columns, skip silently

                    $imp_building = trim($row[0] ?? '');
                    $imp_floor    = trim($row[1] ?? '');
                    $imp_dept     = trim($row[2] ?? '');
                    $imp_location = trim("$imp_building $imp_floor $imp_dept");

                    $control_number = trim($row[5] ?? '');
                    // Detect if this is the NEW 35-col format (with Firewall col at 31)
                    // or old 34-col format (no Firewall col)
                    $has_firewall_col = ($col_count >= 35);
                    $fw_offset = $has_firewall_col ? 1 : 0; // offset for cols after endpoint_secure
                    $data = [
                        $imp_location,   // department (combined location)
                        $row[3]  ?? '',  // end_user
                        $row[4]  ?? '',  // mr_par
                        $row[5]  ?? '',  // control_number
                        $row[6]  ?? '',  // system_unit
                        $row[7]  ?? '',  // system_unit_sn
                        $row[8]  ?? '',  // monitor
                        $row[9]  ?? '',  // monitor_sn
                        $row[10] ?? '',  // mouse
                        $row[11] ?? '',  // mouse_sn
                        $row[12] ?? '',  // keyboard
                        $row[13] ?? '',  // keyboard_sn
                        $row[14] ?? '',  // printer
                        $row[15] ?? '',  // printer_sn
                        $row[16] ?? '',  // scanner
                        $row[17] ?? '',  // scanner_sn
                        $row[18] ?? '',  // avr_ups
                        $row[19] ?? '',  // avr_ups_sn
                        $row[20] ?? '',  // processor
                        $row[21] ?? '',  // memory
                        $row[22] ?? '',  // storage
                        $row[23] ?? '',  // os
                        $row[24] ?? '',  // os_product_key
                        $row[25] ?? '',  // license
                        $row[26] ?? '',  // microsoft_office
                        $row[27] ?? '',  // ms_office_email
                        $row[28] ?? '',  // ip_address
                        $row[29] ?? '',  // mac_address
                        $row[30] ?? 'N', // endpoint_secure
                        $has_firewall_col ? ($row[31] ?? 'N') : 'N', // firewall
                        $row[31 + $fw_offset] ?? '',  // checked_by
                        $row[32 + $fw_offset] ?? '',  // encoded_by
                        $row[33 + $fw_offset] ?? '',  // remarks
                    ];
                } else {
                    // Old 32-col format: Location(0), End User(1), MR/PAR(2), Control Number(3), ...
                    if ($col_count < 30) continue; // too few columns, skip silently

                    $control_number = trim($row[3] ?? '');
                    $data = [
                        $row[0]  ?? '',  // department (Location — already combined)
                        $row[1]  ?? '',  // end_user
                        $row[2]  ?? '',  // mr_par
                        $row[3]  ?? '',  // control_number
                        $row[4]  ?? '',  // system_unit
                        $row[5]  ?? '',  // system_unit_sn
                        $row[6]  ?? '',  // monitor
                        $row[7]  ?? '',  // monitor_sn
                        $row[8]  ?? '',  // mouse
                        $row[9]  ?? '',  // mouse_sn
                        $row[10] ?? '',  // keyboard
                        $row[11] ?? '',  // keyboard_sn
                        $row[12] ?? '',  // printer
                        $row[13] ?? '',  // printer_sn
                        $row[14] ?? '',  // scanner
                        $row[15] ?? '',  // scanner_sn
                        $row[16] ?? '',  // avr_ups
                        $row[17] ?? '',  // avr_ups_sn
                        $row[18] ?? '',  // processor
                        $row[19] ?? '',  // memory
                        $row[20] ?? '',  // storage
                        $row[21] ?? '',  // os
                        $row[22] ?? '',  // os_product_key
                        $row[23] ?? '',  // license
                        $row[24] ?? '',  // microsoft_office
                        $row[25] ?? '',  // ms_office_email
                        $row[26] ?? '',  // ip_address
                        $row[27] ?? '',  // mac_address
                        $row[28] ?? 'N', // endpoint_secure
                        'N',             // firewall (not in old format)
                        $row[29] ?? '',  // checked_by
                        $row[30] ?? '',  // encoded_by
                        $row[31] ?? '',  // remarks
                    ];
                }

                $existing_id = false;
                if (!empty($control_number)) {
                    $check_stmt = $pdo->prepare("SELECT id FROM computers WHERE control_number = ? LIMIT 1");
                    $check_stmt->execute([$control_number]);
                    $existing_id = $check_stmt->fetchColumn();
                }

                try {
                    if ($existing_id) {
                        $update_sql = "UPDATE computers SET department=?, end_user=?, mr_par=?, control_number=?, system_unit=?, system_unit_sn=?, monitor=?, monitor_sn=?, mouse=?, mouse_sn=?, keyboard=?, keyboard_sn=?, printer=?, printer_sn=?, scanner=?, scanner_sn=?, avr_ups=?, avr_ups_sn=?, processor=?, memory=?, storage=?, os=?, os_product_key=?, license=?, microsoft_office=?, ms_office_email=?, ip_address=?, mac_address=?, endpoint_secure=?, firewall=?, checked_by=?, encoded_by=?, remarks=? WHERE id=?";
                        $update_data = $data;
                        $update_data[] = $existing_id;
                        $stmt = $pdo->prepare($update_sql);
                        $stmt->execute($update_data);
                        $updated++;
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO computers (department, end_user, mr_par, control_number, system_unit, system_unit_sn, monitor, monitor_sn, mouse, mouse_sn, keyboard, keyboard_sn, printer, printer_sn, scanner, scanner_sn, avr_ups, avr_ups_sn, processor, memory, storage, os, os_product_key, license, microsoft_office, ms_office_email, ip_address, mac_address, endpoint_secure, firewall, checked_by, encoded_by, remarks) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute($data);
                        $imported++;
                    }

                    // ── Sync firewall_status from CSV firewall column (index 29) ──
                    $fw_csv_val = $data[29] ?? 'N'; // firewall column
                    $fw_csv_status = ($fw_csv_val === 'Y') ? 'Yes' : 'No';
                    $fw_csv_ctrl = $control_number;
                    if (!empty($fw_csv_ctrl)) {
                        $fw_csv_sync = $pdo->prepare("
                            INSERT INTO firewall_status (control_number, status, last_edited_by, updated_at)
                            VALUES (?, ?, ?, NOW())
                            ON CONFLICT (control_number) DO UPDATE
                                SET status = EXCLUDED.status,
                                    last_edited_by = EXCLUDED.last_edited_by,
                                    updated_at = NOW()
                        ");
                        $fw_csv_sync->execute([$fw_csv_ctrl, $fw_csv_status, $_SESSION['username'] ?? 'csv_import']);
                    }

                } catch (Exception $e) {
                    $skipped++;
                    if (count($errors) < 3) { // capture first 3 errors for diagnosis
                        $errors[] = "Row near ctrl#" . htmlspecialchars($control_number) . ": " . $e->getMessage();
                    }
                }

            }
            fclose($handle);

            $fmt_label  = $is_new_format ? 'new (Building/Floor/Dept)' : 'old (Location)';
            $msg_parts  = ["Imported $imported new computers [$fmt_label format]"];
            if ($updated > 0) $msg_parts[] = "$updated computers updated";
            if ($skipped > 0) $msg_parts[] = "$skipped failed";
            $success_msg = implode(', ', $msg_parts) . '.';
            if (!empty($errors)) {
                $error_msg = "Sample errors: " . implode(' | ', $errors);
            }
            $audit_msg = "Imported $imported new computers";
            if ($updated > 0) $audit_msg .= " and updated $updated existing computers";
            logAudit($pdo, 'import_csv', $audit_msg, 'computer');
        }
    }
}

// Add Computer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_computer') {
    $control_number = $_POST['control_number'] ?? '';
    $encoded_by = trim($_POST['encoded_by'] ?? '');
    $checked_by = trim($_POST['checked_by'] ?? '');

    if (empty($encoded_by) || empty($checked_by)) {
        $error_msg = "Error: 'Encoded By' and 'Checked By' are required fields.";
    } else {
        // Check Duplicates
        $dups = [];

        // Control Number
        $check = $pdo->prepare("SELECT end_user FROM computers WHERE control_number = ? LIMIT 1");
        $check->execute([$control_number]);
        $existing = $check->fetch(PDO::FETCH_ASSOC);
        if ($existing) {
            $dups[] = "Control Number '$control_number' (assigned to {$existing['end_user']})";
        }

        // IP Address
        $ip = $_POST['ip_address'] ?? '';
        // Special IP values that are allowed to appear on multiple records
        $ip_exempt = ['dhcp', 'obtain', 'wifi', 'n/a'];
        if (!empty($ip) && !in_array(strtolower(trim($ip)), $ip_exempt)) {
            $check = $pdo->prepare("SELECT end_user, control_number FROM computers WHERE ip_address = ? LIMIT 1");
            $check->execute([$ip]);
            $existing = $check->fetch(PDO::FETCH_ASSOC);
            if ($existing) {
                $ctx = $existing['end_user'] . " (Control #: " . $existing['control_number'] . ")";
                $dups[] = "IP Address '$ip' (assigned to $ctx)";
            }
        }

        // MAC Address
        $mac = $_POST['mac_address'] ?? '';
        if (!empty($mac)) {
            $check = $pdo->prepare("SELECT end_user, control_number FROM computers WHERE mac_address = ? LIMIT 1");
            $check->execute([$mac]);
            $existing = $check->fetch(PDO::FETCH_ASSOC);
            if ($existing) {
                $ctx = $existing['end_user'] . " (Control #: " . $existing['control_number'] . ")";
                $dups[] = "MAC Address '$mac' (assigned to $ctx)";
            }
        }

        if (!empty($dups)) {
            $error_msg = "Error: Duplicate found for " . implode(' and ', $dups) . ".";
        } else {
            try {
                // Join multi-select arrays into comma-separated strings
                $printer_val = isset($_POST['printer']) && is_array($_POST['printer']) ? implode(', ', $_POST['printer']) : ($_POST['printer'] ?? '');
                $scanner_val = isset($_POST['scanner']) && is_array($_POST['scanner']) ? implode(', ', $_POST['scanner']) : ($_POST['scanner'] ?? '');
                $avr_ups_val = isset($_POST['avr_ups']) && is_array($_POST['avr_ups']) ? implode(', ', $_POST['avr_ups']) : ($_POST['avr_ups'] ?? '');
                // Merge Location Fields (Building + Floor + Department)
                $building_val = $_POST['building'] ?? '';
                $floor_val    = $_POST['floor'] ?? '';
                $dept_text    = $_POST['department'] ?? '';
                $location_val = trim("$building_val $floor_val $dept_text");
                $stmt = $pdo->prepare("INSERT INTO computers (department, end_user, mr_par, control_number, system_unit, system_unit_sn, monitor, monitor_sn, mouse, mouse_sn, keyboard, keyboard_sn, printer, printer_sn, scanner, scanner_sn, avr_ups, avr_ups_sn, processor, memory, storage, os, os_product_key, license, microsoft_office, ms_office_email, ip_address, mac_address, endpoint_secure, firewall, checked_by, encoded_by, remarks, remarks_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $location_val,
                    $_POST['end_user'],
                    $_POST['mr_par'],
                    $_POST['control_number'],
                    $_POST['system_unit'],
                    $_POST['system_unit_sn'],
                    $_POST['monitor'],
                    $_POST['monitor_sn'],
                    $_POST['mouse'],
                    $_POST['mouse_sn'],
                    $_POST['keyboard'],
                    $_POST['keyboard_sn'],
                    $printer_val,
                    $_POST['printer_sn'],
                    $scanner_val,
                    $_POST['scanner_sn'],
                    $avr_ups_val,
                    $_POST['avr_ups_sn'],
                    $_POST['processor'],
                    $_POST['memory'],
                    $_POST['storage'],
                    $_POST['os'],
                    $_POST['os_product_key'],
                    $_POST['license'],
                    $_POST['microsoft_office'],
                    $_POST['ms_office_email'] ?? '',
                    $_POST['ip_address'],
                    $_POST['mac_address'],
                    $_POST['endpoint_secure'] ?? 'N',
                    $_POST['firewall'] ?? 'N',
                    $_POST['checked_by'],
                    $_POST['encoded_by'],
                    $_POST['remarks'],
                    !empty($_POST['remarks_date']) ? $_POST['remarks_date'] : null,
                ]);
                $computer_id = $pdo->lastInsertId();
                $dept = $location_val;
                $end_user = $_POST['end_user'];
                $ctrl_num = $_POST['control_number'];
                $sys_ip = $_POST['ip_address'];
                $sys_mac = $_POST['mac_address'];
                $sys_unit = $_POST['system_unit'];
                $add_comp_detail = "Added Computer | Control #: $ctrl_num | User: $end_user | Location: $dept | System Unit: $sys_unit | IP: $sys_ip | MAC: $sys_mac | OS: {$_POST['os']} | Processor: {$_POST['processor']} | Memory: {$_POST['memory']} | Storage: {$_POST['storage']}";

                // ── Sync firewall_status when firewall=Y on add ──────────────
                $fw_val_add = $_POST['firewall'] ?? 'N';
                try {
                    $fw_sync = $pdo->prepare("
                        INSERT INTO firewall_status (control_number, status, last_edited_by, updated_at)
                        VALUES (?, ?, ?, NOW())
                        ON CONFLICT (control_number) DO UPDATE
                            SET status = EXCLUDED.status,
                                last_edited_by = EXCLUDED.last_edited_by,
                                updated_at = NOW()
                    ");
                    $fw_status_val = ($fw_val_add === 'Y') ? 'Yes' : 'No';
                    $fw_sync->execute([$ctrl_num, $fw_status_val, $_SESSION['username'] ?? '']);
                } catch (Exception $fw_e) {
                    error_log("Firewall status sync (add) failed: " . $fw_e->getMessage());
                }
                if (!empty($_POST['remarks']))
                    $add_comp_detail .= " | Remarks: {$_POST['remarks']}";
                logAudit($pdo, 'add_computer', $add_comp_detail, 'computer', $computer_id);
                /* logChangelog($pdo, 'feature', 'Computer Inventory', "Added Computer: $ctrl_num ($end_user)", "Department: $dept | System Unit: $sys_unit | IP: $sys_ip"); removed */
                $success_msg = "Computer added successfully.";

                // Sync MS Office version + email to Office Licenses
                $ms_office_email   = $_POST['ms_office_email'] ?? '';
                $ms_office_version = $_POST['microsoft_office'] ?? '';
                if (!empty($_POST['control_number'])) {
                    try {
                        $sync_stmt = $pdo->prepare("UPDATE office_licenses SET email = ?, ms_office_version = ? WHERE control_number = ?");
                        $sync_stmt->execute([$ms_office_email, $ms_office_version, $_POST['control_number']]);
                        $synced = $sync_stmt->rowCount();
                        if ($synced > 0) {
                            logAudit($pdo, 'sync_office', "Synced MS Office version + email to $synced license(s) for " . $_POST['control_number'], 'sync');
                        }
                    } catch (Exception $sync_e) {
                        error_log("Office sync to licenses failed: " . $sync_e->getMessage());
                    }
                }

                // Auto-create Office License if specified
                $ms_office = $_POST['microsoft_office'] ?? '';
                if (!empty($ms_office)) {
                    try {
                        $stmt_office = $pdo->prepare("INSERT INTO office_licenses (control_number, department, ms_office_version, product_key, email, remarks) VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt_office->execute([
                            $_POST['control_number'],
                            $location_val,
                            $ms_office,
                            '', // Office Product Key (Manual Entry required)
                            $ms_office_email, // Include email if provided
                            "Auto-generated from Computer Inventory (ID: $computer_id)"
                        ]);
                        // Optional: Log this auto-creation
                        // logAudit($pdo, 'add_license', "Auto-created license for " . $_POST['control_number'], 'office', $pdo->lastInsertId());
                    } catch (Exception $e) {
                        // Fail silently for office sync to avoid blocking computer add, or append to warning
                        // $error_msg .= " (Office sync failed: " . $e->getMessage() . ")";
                    }
                }
            } catch (Exception $e) {
                $error_msg = "Error adding computer: " . $e->getMessage();
            }
        }
    }
}

// Edit Computer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_computer') {
    $id = $_POST['id'] ?? 0;
    $control_number = $_POST['control_number'] ?? '';
    $ip = $_POST['ip_address'] ?? '';
    $mac = $_POST['mac_address'] ?? '';
    $encoded_by = trim($_POST['encoded_by'] ?? '');
    $checked_by = trim($_POST['checked_by'] ?? '');

    if (empty($encoded_by) || empty($checked_by)) {
        $error_msg = "Error: 'Encoded By' and 'Checked By' are required fields.";
    } else {
        // Check Duplicates
        $dups = [];

        // Control Number
        $check = $pdo->prepare("SELECT end_user FROM computers WHERE control_number = ? AND id != ? LIMIT 1");
        $check->execute([$control_number, $id]);
        $existing = $check->fetch(PDO::FETCH_ASSOC);
        if ($existing) {
            $dups[] = "Control Number '$control_number' (assigned to {$existing['end_user']})";
        }

        // IP Address
        // Special IP values that are allowed to appear on multiple records
        $ip_exempt = ['dhcp', 'obtain', 'wifi', 'n/a'];
        if (!empty($ip) && !in_array(strtolower(trim($ip)), $ip_exempt)) {
            $check = $pdo->prepare("SELECT end_user, control_number FROM computers WHERE ip_address = ? AND id != ? LIMIT 1");
            $check->execute([$ip, $id]);
            $existing = $check->fetch(PDO::FETCH_ASSOC);
            if ($existing) {
                $ctx = $existing['end_user'] . " (Control #: " . $existing['control_number'] . ")";
                $dups[] = "IP Address '$ip' (assigned to $ctx)";
            }
        }

        // MAC Address
        if (!empty($mac)) {
            $check = $pdo->prepare("SELECT end_user, control_number FROM computers WHERE mac_address = ? AND id != ? LIMIT 1");
            $check->execute([$mac, $id]);
            $existing = $check->fetch(PDO::FETCH_ASSOC);
            if ($existing) {
                $ctx = $existing['end_user'] . " (Control #: " . $existing['control_number'] . ")";
                $dups[] = "MAC Address '$mac' (assigned to $ctx)";
            }
        }
        if (!empty($mac)) {
            $check = $pdo->prepare("SELECT COUNT(*) FROM computers WHERE mac_address = ? AND id != ?");
            $check->execute([$mac, $id]);
            if ($check->fetchColumn() > 0)
                $dups[] = "MAC Address '$mac'";
        }

        if (!empty($dups)) {
            $error_msg = "Error: Duplicate found for " . implode(' and ', $dups) . ".";
        } else {
            try {
                // Join multi-select arrays into comma-separated strings
                $printer_val = isset($_POST['printer']) && is_array($_POST['printer']) ? implode(', ', $_POST['printer']) : ($_POST['printer'] ?? '');
                $scanner_val = isset($_POST['scanner']) && is_array($_POST['scanner']) ? implode(', ', $_POST['scanner']) : ($_POST['scanner'] ?? '');
                $avr_ups_val = isset($_POST['avr_ups']) && is_array($_POST['avr_ups']) ? implode(', ', $_POST['avr_ups']) : ($_POST['avr_ups'] ?? '');
                // Merge Location Fields (Building + Floor + Department)
                $building_val = $_POST['building'] ?? '';
                $floor_val    = $_POST['floor'] ?? '';
                $dept_text    = $_POST['department'] ?? '';
                $location_val = trim("$building_val $floor_val $dept_text");

                // ── Snapshot: fetch old record for field-level diff ──────────
                $snap = $pdo->prepare("SELECT * FROM computers WHERE id = ?");
                $snap->execute([$_POST['id']]);
                $old_comp = $snap->fetch(PDO::FETCH_ASSOC) ?: [];

                $stmt = $pdo->prepare("UPDATE computers SET department=?, end_user=?, mr_par=?, control_number=?, system_unit=?, system_unit_sn=?, monitor=?, monitor_sn=?, mouse=?, mouse_sn=?, keyboard=?, keyboard_sn=?, printer=?, printer_sn=?, scanner=?, scanner_sn=?, avr_ups=?, avr_ups_sn=?, processor=?, memory=?, storage=?, os=?, os_product_key=?, license=?, microsoft_office=?, ms_office_email=?, ip_address=?, mac_address=?, endpoint_secure=?, firewall=?, checked_by=?, encoded_by=?, remarks=?, remarks_date=? WHERE id=?");
                $stmt->execute([
                    $location_val,
                    $_POST['end_user'],
                    $_POST['mr_par'],
                    $_POST['control_number'],
                    $_POST['system_unit'],
                    $_POST['system_unit_sn'],
                    $_POST['monitor'],
                    $_POST['monitor_sn'],
                    $_POST['mouse'],
                    $_POST['mouse_sn'],
                    $_POST['keyboard'],
                    $_POST['keyboard_sn'],
                    $printer_val,
                    $_POST['printer_sn'],
                    $scanner_val,
                    $_POST['scanner_sn'],
                    $avr_ups_val,
                    $_POST['avr_ups_sn'],
                    $_POST['processor'],
                    $_POST['memory'],
                    $_POST['storage'],
                    $_POST['os'],
                    $_POST['os_product_key'],
                    $_POST['license'],
                    $_POST['microsoft_office'],
                    $_POST['ms_office_email'] ?? '',
                    $_POST['ip_address'],
                    $_POST['mac_address'],
                    $_POST['endpoint_secure'] ?? 'N',
                    $_POST['firewall'] ?? 'N',
                    $_POST['checked_by'],
                    $_POST['encoded_by'],
                    $_POST['remarks'],
                    !empty($_POST['remarks_date']) ? $_POST['remarks_date'] : null,
                    $_POST['id']
                ]);

                // ── Build field-level diff for audit trail ───────────────────
                $field_labels = [
                    'department'     => 'Location',
                    'end_user'       => 'End User',
                    'mr_par'         => 'MR/PAR',
                    'control_number' => 'Control #',
                    'system_unit'    => 'System Unit',
                    'system_unit_sn' => 'System Unit S/N',
                    'monitor'        => 'Monitor',
                    'monitor_sn'     => 'Monitor S/N',
                    'mouse'          => 'Mouse',
                    'mouse_sn'       => 'Mouse S/N',
                    'keyboard'       => 'Keyboard',
                    'keyboard_sn'    => 'Keyboard S/N',
                    'printer'        => 'Printer',
                    'printer_sn'     => 'Printer S/N',
                    'scanner'        => 'Scanner',
                    'scanner_sn'     => 'Scanner S/N',
                    'avr_ups'        => 'AVR/UPS',
                    'avr_ups_sn'     => 'AVR/UPS S/N',
                    'processor'      => 'Processor',
                    'memory'         => 'RAM',
                    'storage'        => 'Storage',
                    'os'             => 'OS',
                    'os_product_key' => 'OS Product Key',
                    'license'        => 'License',
                    'microsoft_office'=> 'MS Office',
                    'ms_office_email' => 'MS Office Email',
                    'ip_address'     => 'IP Address',
                    'mac_address'    => 'MAC Address',
                    'endpoint_secure'=> 'Endpoint Secure',
                    'firewall'       => 'Firewall',
                    'checked_by'     => 'Checked By',
                    'encoded_by'     => 'Encoded By',
                    'remarks'        => 'Remarks',
                    'remarks_date'   => 'Remarks Date',
                ];
                $new_comp = [
                    'department'      => $location_val,
                    'end_user'        => $_POST['end_user'],
                    'mr_par'          => $_POST['mr_par'],
                    'control_number'  => $_POST['control_number'],
                    'system_unit'     => $_POST['system_unit'],
                    'system_unit_sn'  => $_POST['system_unit_sn'],
                    'monitor'         => $_POST['monitor'],
                    'monitor_sn'      => $_POST['monitor_sn'],
                    'mouse'           => $_POST['mouse'],
                    'mouse_sn'        => $_POST['mouse_sn'],
                    'keyboard'        => $_POST['keyboard'],
                    'keyboard_sn'     => $_POST['keyboard_sn'],
                    'printer'         => $printer_val,
                    'printer_sn'      => $_POST['printer_sn'],
                    'scanner'         => $scanner_val,
                    'scanner_sn'      => $_POST['scanner_sn'],
                    'avr_ups'         => $avr_ups_val,
                    'avr_ups_sn'      => $_POST['avr_ups_sn'],
                    'processor'       => $_POST['processor'],
                    'memory'          => $_POST['memory'],
                    'storage'         => $_POST['storage'],
                    'os'              => $_POST['os'],
                    'os_product_key'  => $_POST['os_product_key'],
                    'license'         => $_POST['license'],
                    'microsoft_office'=> $_POST['microsoft_office'],
                    'ms_office_email' => $_POST['ms_office_email'] ?? '',
                    'ip_address'      => $_POST['ip_address'],
                    'mac_address'     => $_POST['mac_address'],
                    'endpoint_secure' => $_POST['endpoint_secure'] ?? 'N',
                    'firewall'        => $_POST['firewall'] ?? 'N',
                    'checked_by'      => $_POST['checked_by'],
                    'encoded_by'      => $_POST['encoded_by'],
                    'remarks'         => $_POST['remarks'],
                    'remarks_date'    => !empty($_POST['remarks_date']) ? $_POST['remarks_date'] : '',
                ];
                $comp_changes = [];
                foreach ($field_labels as $field => $label) {
                    $o = trim((string)($old_comp[$field] ?? ''));
                    $n = trim((string)($new_comp[$field] ?? ''));
                    if ($o !== $n) {
                        $comp_changes[] = "{$label}: \"{$o}\" → \"{$n}\"";
                    }
                }
                $upd_comp_detail = empty($comp_changes)
                    ? "Updated Computer (no field changes) | ID: {$_POST['id']} | Control #: {$_POST['control_number']}"
                    : "Updated Computer | Control #: {$_POST['control_number']} | User: {$_POST['end_user']} | " . implode(' | ', $comp_changes);
                logAudit($pdo, 'update_computer', $upd_comp_detail, 'computer', $_POST['id']);
                /* logChangelog($pdo, 'enhancement', 'Computer Inventory', "Updated Computer: {$_POST['control_number']} ({$_POST['end_user']})", "Dept: {$_POST['department']} | IP: {$_POST['ip_address']} | OS: {$_POST['os']}"); removed */
                $success_msg = "Computer updated successfully.";

                // ── Sync firewall_status when firewall field changes on edit ──
                $fw_new = $_POST['firewall'] ?? 'N';
                $fw_old = $old_comp['firewall'] ?? 'N';
                $ctrl_edit = $_POST['control_number'];
                if ($fw_new !== $fw_old || $fw_new === 'Y') {
                    try {
                        $fw_sync_upd = $pdo->prepare("
                            INSERT INTO firewall_status (control_number, status, last_edited_by, updated_at)
                            VALUES (?, ?, ?, NOW())
                            ON CONFLICT (control_number) DO UPDATE
                                SET status = EXCLUDED.status,
                                    last_edited_by = EXCLUDED.last_edited_by,
                                    updated_at = NOW()
                        ");
                        $fw_status_upd = ($fw_new === 'Y') ? 'Yes' : 'No';
                        $fw_sync_upd->execute([$ctrl_edit, $fw_status_upd, $_SESSION['username'] ?? '']);
                        if ($fw_new !== $fw_old) {
                            logAudit($pdo, 'sync_firewall_status', "Firewall status synced to '$fw_status_upd' for Control #: $ctrl_edit (firewall changed from '$fw_old' to '$fw_new')", 'firewall');
                        }
                    } catch (Exception $fw_e2) {
                        error_log("Firewall status sync (edit) failed: " . $fw_e2->getMessage());
                    }
                }

                // Sync MS Office version + email to Office Licenses (UPSERT)
                $ms_office_email   = $_POST['ms_office_email'] ?? '';
                $ms_office_version = $_POST['microsoft_office'] ?? '';
                if (!empty($_POST['control_number'])) {
                    try {
                        // Update any existing office_licenses record for this computer
                        $sync_stmt = $pdo->prepare("UPDATE office_licenses SET email = ?, ms_office_version = ?, department = ? WHERE control_number = ?");
                        $sync_stmt->execute([$ms_office_email, $ms_office_version, $location_val, $_POST['control_number']]);
                        $synced = $sync_stmt->rowCount();
                        if ($synced > 0) {
                            logAudit($pdo, 'sync_office', "Synced MS Office version + email to $synced license(s) for " . $_POST['control_number'], 'sync');
                        } elseif (!empty($ms_office_version)) {
                            // No record found — auto-create one so the Office module stays in sync
                            $ins_sync = $pdo->prepare("INSERT INTO office_licenses (control_number, department, ms_office_version, email, remarks) VALUES (?, ?, ?, ?, ?)");
                            $ins_sync->execute([
                                $_POST['control_number'],
                                $location_val,
                                $ms_office_version,
                                $ms_office_email,
                                'Auto-created from Computer Inventory (edit)'
                            ]);
                            logAudit($pdo, 'sync_office', "Auto-created office license for " . $_POST['control_number'], 'sync');
                        }
                    } catch (Exception $sync_e) {
                        error_log("Office sync to licenses failed: " . $sync_e->getMessage());
                    }
                }
            } catch (Exception $e) {
                $error_msg = "Error updating computer: " . $e->getMessage();
            }
        }
    }
}

// Delete Computer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_computer') {
    try {
        $id = $_POST['id'];
        $snap = $pdo->prepare("SELECT * FROM computers WHERE id = ?");
        $snap->execute([$id]);
        $del_c = $snap->fetch(PDO::FETCH_ASSOC);
        $del_comp_detail = "Deleted Computer ID $id";
        if ($del_c) {
            $del_comp_detail = "Deleted Computer | ID: $id | Control #: {$del_c['control_number']} | User: {$del_c['end_user']} | Dept: {$del_c['department']} | System Unit: {$del_c['system_unit']} | IP: {$del_c['ip_address']} | MAC: {$del_c['mac_address']} | OS: {$del_c['os']}";
            if ($del_c['remarks'])
                $del_comp_detail .= " | Remarks: {$del_c['remarks']}";
        }
        $stmt = $pdo->prepare("DELETE FROM computers WHERE id = ?");
        $stmt->execute([$id]);
        logAudit($pdo, 'delete_computer', $del_comp_detail, 'computer', $id);
        /* if ($del_c) logChangelog($pdo, 'bugfix', 'Computer Inventory', "Removed Computer: ...", ...); removed — data changes not tracked in changelog */
        $success_msg = "Computer deleted successfully.";
    } catch (Exception $e) {
        $error_msg = "Error deleting computer: " . $e->getMessage();
    }
}

// Check if AJAX request
$is_ajax = isset($_GET['ajax']);

// Fetch Computers with Search, Filter, Sort, and Pagination
$search_term = $_GET['search'] ?? '';
$department_filter = $_GET['department'] ?? 'All';
$printer_filter = $_GET['printer'] ?? 'All';
$memory_filter = $_GET['memory'] ?? 'All';
$os_filter = $_GET['os'] ?? 'All';
$ms_office_filter = $_GET['ms_office'] ?? 'All';
$checked_by_filter = $_GET['checked_by'] ?? 'All';
$sort_by = $_GET['sort'] ?? 'id_desc';

$where_clauses = ["1=1"];
$params = [];

if ($search_term) {
    // Search across ALL text fields
    $where_clauses[] = "(
        control_number ILIKE ? OR 
        end_user ILIKE ? OR 
        department ILIKE ? OR 
        ip_address ILIKE ? OR 
        mac_address ILIKE ? OR
        system_unit ILIKE ? OR 
        system_unit_sn ILIKE ? OR
        monitor ILIKE ? OR 
        monitor_sn ILIKE ? OR
        mouse ILIKE ? OR 
        mouse_sn ILIKE ? OR
        keyboard ILIKE ? OR 
        keyboard_sn ILIKE ? OR
        printer ILIKE ? OR 
        printer_sn ILIKE ? OR
        scanner ILIKE ? OR 
        scanner_sn ILIKE ? OR
        avr_ups ILIKE ? OR 
        avr_ups_sn ILIKE ? OR
        processor ILIKE ? OR 
        memory ILIKE ? OR 
        storage ILIKE ? OR 
        os ILIKE ? OR 
        os_product_key ILIKE ? OR 
        microsoft_office ILIKE ? OR
        ms_office_email ILIKE ? OR
        mr_par ILIKE ? OR
        checked_by ILIKE ? OR
        encoded_by ILIKE ? OR
        remarks ILIKE ?
    )";
    $search_param = "%$search_term%";
    for ($i = 0; $i < 30; $i++)
        $params[] = $search_param;
}

if ($department_filter !== 'All' && !empty($department_filter)) {
    $where_clauses[] = "department = ?";
    $params[] = $department_filter;
}

if ($printer_filter !== 'All' && !empty($printer_filter)) {
    $where_clauses[] = "printer ILIKE ?";
    $params[] = '%' . $printer_filter . '%';
}

if ($memory_filter !== 'All' && !empty($memory_filter)) {
    $where_clauses[] = "memory = ?";
    $params[] = $memory_filter;
}

if ($os_filter !== 'All' && !empty($os_filter)) {
    $where_clauses[] = "os = ?";
    $params[] = $os_filter;
}

if ($ms_office_filter !== 'All' && !empty($ms_office_filter)) {
    $where_clauses[] = "TRIM(microsoft_office) ILIKE TRIM(?)";
    $params[] = $ms_office_filter;
}

if ($checked_by_filter !== 'All' && !empty($checked_by_filter)) {
    $where_clauses[] = "checked_by = ?";
    $params[] = $checked_by_filter;
}

$where_sql = implode(' AND ', $where_clauses);

// Sorting
$order_by = "id DESC"; // Default
switch ($sort_by) {
    case 'id_asc':
        $order_by = "id ASC";
        break;
    case 'user_asc':
        $order_by = "end_user ASC";
        break;
    case 'user_desc':
        $order_by = "end_user DESC";
        break;
    case 'dept_asc':
        $order_by = "department ASC";
        break;
    case 'dept_desc':
        $order_by = "department DESC";
        break;
    case 'id_desc':
    default:
        $order_by = "id DESC";
        break;
}

// Pagination
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1)
    $page = 1;
$limit_param = $_GET['limit'] ?? '50';
$limit = ($limit_param === 'all') ? 999999 : (int) $limit_param;
$offset = ($page - 1) * $limit;

$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM computers WHERE $where_sql");
$count_stmt->execute($params);
$total_items = $count_stmt->fetchColumn();
$total_pages = ceil($total_items / $limit);
$page_params = "&search=" . urlencode($search_term) . "&department=" . urlencode($department_filter) . "&printer=" . urlencode($printer_filter) . "&memory=" . urlencode($memory_filter) . "&os=" . urlencode($os_filter) . "&ms_office=" . urlencode($ms_office_filter) . "&checked_by=" . urlencode($checked_by_filter) . "&sort=" . urlencode($sort_by) . "&limit=" . urlencode($limit_param);

$sql = "SELECT c.*, last_edit.username AS last_edited_by, last_edit.created_at AS last_edited_at
    FROM computers c
    LEFT JOIN LATERAL (
        SELECT u.username, al.created_at
        FROM audit_logs al
        LEFT JOIN users u ON al.user_id = u.id
        WHERE al.resource_type = 'computer' AND al.resource_id::text = c.id::text
        ORDER BY al.created_at DESC
        LIMIT 1
    ) last_edit ON true
    WHERE $where_sql ORDER BY $order_by LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$computers = $stmt->fetchAll();

// Stats
$total_computers = $pdo->query("SELECT COUNT(*) FROM computers")->fetchColumn();

// Get unique departments
$departments = $pdo->query("SELECT DISTINCT department FROM computers WHERE department IS NOT NULL AND department != '' ORDER BY department")->fetchAll(PDO::FETCH_COLUMN);

// Get unique printers - unnest comma-separated values so individual models appear
$printers = $pdo->query("
    SELECT DISTINCT TRIM(val) AS printer
    FROM computers, unnest(string_to_array(printer, ',')) AS val
    WHERE printer IS NOT NULL AND printer != ''
    ORDER BY printer
")->fetchAll(PDO::FETCH_COLUMN);

// Get unique memory values
$memories = $pdo->query("SELECT DISTINCT memory FROM computers WHERE memory IS NOT NULL AND memory != '' ORDER BY memory")->fetchAll(PDO::FETCH_COLUMN);
// Get unique OS values
$os_values = $pdo->query("SELECT DISTINCT os FROM computers WHERE os IS NOT NULL AND os != '' ORDER BY os")->fetchAll(PDO::FETCH_COLUMN);

// Get unique MS Office values
$ms_office_values = $pdo->query("SELECT DISTINCT microsoft_office FROM computers WHERE microsoft_office IS NOT NULL AND TRIM(microsoft_office) != '' ORDER BY microsoft_office")->fetchAll(PDO::FETCH_COLUMN);

// Get unique Checked By values (for filter dropdown - kept for backward compat)
$checked_by_values = $pdo->query("SELECT DISTINCT checked_by FROM computers WHERE checked_by IS NOT NULL AND checked_by != '' ORDER BY checked_by")->fetchAll(PDO::FETCH_COLUMN);

// Get users for Checked By / Encoded By dropdowns (exclude test/system accounts)
$staff_users = $pdo->query("
    SELECT full_name FROM users
    WHERE username NOT IN ('Editor_Test', 'Viewer_Test', 'admin')
      AND full_name IS NOT NULL AND TRIM(full_name) != ''
    ORDER BY full_name
")->fetchAll(PDO::FETCH_COLUMN);

// Predefined lists for multi-select dropdowns
$printer_options = [
    'MAGICCARD D ID', 'CANON PIXMA G1010', 'CANON PIXMA G2010', 'CANON LBP 2900', 'CANON LBP 6030',
    'EPSON LX-310', 'EPSON LQ-310', 'EPSON L120', 'EPSON L121', 'EPSON L220', 'EPSON L360', 'EPSON L565',
    'EPSON L3110', 'EPSON L3210', 'EPSON L4150', 'EPSON L5190', 'EPSON L5290',
    'EPSON L8050', 'EPSON L805', 'EPSON L15150', 'EPSON L18150',
    'HP LASERJET M428FDN', 'HP SMART TANK 500',
    'BROTHER DCP-T420W', 'BROTHER DCP-T700W', 'BROTHER MFC-T810W', 'ZEBRA GC420T',
];
$scanner_options = [
    'HP SCANJET PRO 4500 FNW1', 'HP SCANJET PRO 4600 FNW1', 'HP SCANJET PRO N4000 SNW1',
    'HP SCANJET PRO N4000 S4', 'HP SCANJET PRO 7000 S3', 'HP SCANJET PRO 3000 S4',
    'HP SCANJET PRO 3000 S3', 'HP SCANJET PRO 2000 S2', 'HP LASERJET PRO N4500',
    'HP SMART TANK 500', 'CANON LIDE 110', 'CANON PIXMA G1010', 'CANON PIXMA G2010', 'EPSON ES-60W',
    'EPSON L565', 'EPSON L3110', 'EPSON L3210', 'EPSON L4150', 'EPSON L5190',
    'EPSON L5290', 'EPSON L8050', 'EPSON L15150', 'EPSON L18150',
    'BROTHER DCP-T420W', 'BROTHER DCP-T700W', 'BROTHER MFC-T810W',
];
$avr_ups_options = [
    'APC', 'AWD', 'DELKIN AVR', 'EATON', 'ECO POWER', 'GENERIC AVR',
    'ICUTE AVR', 'INTEX', 'KEBOS', 'KSTAR', 'LOGIC SUPREME', 'MICROPULSE',
    'PANTHER AVR', 'PAWERGUARD PLUS', 'PROTEC AVR', 'SECURE AVR', 'SOLOMEC', 'STAVOL AVR',
];

// If AJAX request, only render table content
if ($is_ajax) {
    ob_start();
}

if (!$is_ajax) {
    $page_title = "Computer Inventory";
    require_once '../../includes/header.php';
    require_once '../../includes/sidebar.php';
}
?>

<?php if (!$is_ajax): ?>
    <div class="p-4 md:p-8 flex-1 overflow-y-auto custom-scrollbar relative z-10 bg-slate-50 dark:bg-background-dark">
        <header
            class="-mt-4 -mx-4 md:-mt-8 md:-mx-8 px-4 md:px-8 pt-4 md:pt-8 pb-6 mb-8 flex flex-col md:flex-row items-start md:items-end justify-between gap-4 sticky top-0 bg-slate-50 dark:bg-background-dark z-20 border-b border-slate-200 dark:border-[#232b3d]">
            <div>
                <h2 class="text-2xl font-bold text-slate-900 dark:text-white">Computer Inventory</h2>
                <p class="text-sm text-slate-500 mt-1">Manage computer systems and peripherals.</p>
            </div>
            <div class="no-print flex items-center gap-2 md:gap-3 flex-wrap">
                <a href="?export=excel" id="exportExcelBtn"
                    class="flex items-center justify-center rounded-lg h-9 px-4 bg-emerald-600 text-white hover:bg-emerald-700 transition-colors text-xs font-bold">
                    <span class="material-symbols-outlined text-[18px] mr-2">table_view</span> Export Excel
                </a>
                <button onclick="exportSelected()"
                    class="flex items-center justify-center rounded-lg h-9 px-4 bg-blue-600 text-white hover:bg-blue-700 transition-colors text-xs font-bold">
                    <span class="material-symbols-outlined text-[18px] mr-2">download</span> Export CSV
                </button>
                <button onclick="toggleModal('importModal')"
                    class="flex items-center justify-center rounded-lg h-9 px-4 bg-amber-600 text-white hover:bg-amber-700 transition-colors text-xs font-bold">
                    <span class="material-symbols-outlined text-[18px] mr-2">upload</span> Import CSV
                </button>
                <button onclick="printData()"
                    class="flex items-center justify-center rounded-lg h-9 px-4 bg-slate-600 text-white hover:bg-slate-700 transition-colors text-xs font-bold">
                    <span class="material-symbols-outlined text-[18px] mr-2">print</span> Print
                </button>
                <button onclick="toggleModal('addComputerModal')"
                    class="flex items-center justify-center rounded-lg h-9 px-4 bg-primary text-white hover:bg-primary/90 transition-colors text-xs font-bold shadow-lg shadow-primary/20">
                    <span class="material-symbols-outlined text-[18px] mr-2">add</span> Add Computer
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
                <div class="text-slate-500 text-xs font-bold uppercase tracking-wider mb-1">Total Computers</div>
                <div class="text-2xl font-bold text-slate-900 dark:text-white">
                    <?= number_format($total_computers) ?>
                </div>
            </div>
            <div class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] p-4 rounded-xl">
                <div class="text-slate-500 text-xs font-bold uppercase tracking-wider mb-1">Showing</div>
                <div class="text-2xl font-bold text-emerald-500">
                    <?= number_format($total_items) ?>
                </div>
            </div>
            <div class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] p-4 rounded-xl">
                <div class="text-slate-500 text-xs font-bold uppercase tracking-wider mb-1">Locations</div>
                <div class="text-2xl font-bold text-slate-700 dark:text-slate-200">
                    <?= number_format(count($departments)) ?>
                </div>
            </div>
        </div>

        <!-- Control Bar -->
        <div class="no-print mb-6" id="search-controls">
            <form method="GET" class="flex flex-col gap-2">
                <!-- Row 1: Sort + Pagination -->
                <div class="flex items-center gap-2 flex-wrap justify-end">
                    <select name="sort" onchange="this.form.submit()"
                        class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] text-slate-400 text-xs rounded-xl px-4 py-2 focus:ring-2 focus:ring-primary">
                        <option value="id_desc" <?= $sort_by === 'id_desc' ? 'selected' : '' ?>>Newest First</option>
                        <option value="id_asc" <?= $sort_by === 'id_asc' ? 'selected' : '' ?>>Oldest First</option>
                        <option value="user_asc" <?= $sort_by === 'user_asc' ? 'selected' : '' ?>>User (A-Z)</option>
                        <option value="user_desc" <?= $sort_by === 'user_desc' ? 'selected' : '' ?>>User (Z-A)</option>
                        <option value="dept_asc" <?= $sort_by === 'dept_asc' ? 'selected' : '' ?>>Location (A-Z)</option>
                        <option value="dept_desc" <?= $sort_by === 'dept_desc' ? 'selected' : '' ?>>Location (Z-A)</option>
                    </select>
                    <?php if ($total_pages > 1): ?>
                        <span class="text-xs text-slate-500">Page
                            <?= $page ?> of
                            <?= $total_pages ?>
                        </span>
                        <div
                            class="flex h-9 bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] rounded-xl overflow-hidden">
                            <a href="?page=<?= max(1, $page - 1) ?><?= $page_params ?>"
                                class="px-3 flex items-center justify-center hover:bg-white/5 text-slate-400 hover:text-slate-900 dark:hover:text-white transition-colors border-r border-slate-200 dark:border-[#232b3d] <?= $page <= 1 ? 'pointer-events-none opacity-50' : '' ?>">
                                <span class="material-symbols-outlined text-[18px]">chevron_left</span>
                            </a>
                            <a href="?page=<?= min($total_pages, $page + 1) ?><?= $page_params ?>"
                                class="px-3 flex items-center justify-center hover:bg-white/5 text-slate-400 hover:text-slate-900 dark:hover:text-white transition-colors <?= $page >= $total_pages ? 'pointer-events-none opacity-50' : '' ?>">
                                <span class="material-symbols-outlined text-[18px]">chevron_right</span>
                            </a>
                        </div>
                    <?php endif; ?>
                    <select name="limit" onchange="this.form.submit()"
                        class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] text-slate-400 text-xs rounded-xl px-4 py-2 focus:ring-2 focus:ring-primary">
                        <option value="50" <?= $limit_param == '50' ? 'selected' : '' ?>>Show: 50</option>
                        <option value="100" <?= $limit_param == '100' ? 'selected' : '' ?>>Show: 100</option>
                        <option value="200" <?= $limit_param == '200' ? 'selected' : '' ?>>Show: 200</option>
                        <option value="500" <?= $limit_param == '500' ? 'selected' : '' ?>>Show: 500</option>
                        <option value="all" <?= $limit_param == 'all' ? 'selected' : '' ?>>Show: All</option>
                    </select>
                </div>
                <!-- Row 2: Category + Value filter -->
                <div class="flex items-center gap-2 flex-wrap">
                    <!-- Search -->
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-[18px] text-slate-500">search</span>
                        <input type="text" name="search" id="searchInput" value="<?= htmlspecialchars($search_term) ?>"
                            placeholder="Search user, IP, control #..."
                            class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] text-slate-700 dark:text-slate-200 text-xs rounded-xl pl-9 pr-4 py-2 focus:ring-2 focus:ring-primary focus:border-transparent w-56">
                    </div>

                    <!-- Hidden inputs carry all active filter values on submit -->
                    <input type="hidden" name="department" id="hid_department" value="<?= htmlspecialchars($department_filter) ?>">
                    <input type="hidden" name="printer"    id="hid_printer"    value="<?= htmlspecialchars($printer_filter) ?>">
                    <input type="hidden" name="memory"     id="hid_memory"     value="<?= htmlspecialchars($memory_filter) ?>">
                    <input type="hidden" name="os"         id="hid_os"         value="<?= htmlspecialchars($os_filter) ?>">
                    <input type="hidden" name="ms_office"  id="hid_ms_office"  value="<?= htmlspecialchars($ms_office_filter) ?>">
                    <input type="hidden" name="checked_by" id="hid_checked_by" value="<?= htmlspecialchars($checked_by_filter) ?>">

                    <?php
                    // Detect which filter category is currently active
                    $active_cat_key = '';
                    $active_cat_val = '';
                    if ($department_filter  !== 'All' && $department_filter  !== '') { $active_cat_key = 'department'; $active_cat_val = $department_filter; }
                    elseif ($printer_filter !== 'All' && $printer_filter     !== '') { $active_cat_key = 'printer';    $active_cat_val = $printer_filter; }
                    elseif ($memory_filter  !== 'All' && $memory_filter      !== '') { $active_cat_key = 'memory';     $active_cat_val = $memory_filter; }
                    elseif ($os_filter      !== 'All' && $os_filter          !== '') { $active_cat_key = 'os';         $active_cat_val = $os_filter; }
                    elseif ($ms_office_filter!=='All' && $ms_office_filter   !== '') { $active_cat_key = 'ms_office';  $active_cat_val = $ms_office_filter; }
                    elseif ($checked_by_filter!=='All'&& $checked_by_filter  !== '') { $active_cat_key = 'checked_by'; $active_cat_val = $checked_by_filter; }

                    // Build options data for JS
                    $filter_options = [
                        'department' => $departments,
                        'printer'    => $printers,
                        'memory'     => $memories,
                        'os'         => $os_values,
                        'ms_office'  => $ms_office_values,
                        'checked_by' => $checked_by_values,
                    ];
                    ?>

                    <!-- Step 1: Category dropdown -->
                    <select id="filter_cat_select"
                        onchange="onCatChange(this.value)"
                        class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] text-slate-600 dark:text-slate-300 text-xs rounded-xl px-4 py-2 focus:ring-2 focus:ring-primary">
                        <option value="">Filter by...</option>
                        <option value="department"  <?= $active_cat_key==='department' ?'selected':'' ?>>Location</option>
                        <option value="printer"     <?= $active_cat_key==='printer'   ?'selected':'' ?>>Printer</option>
                        <option value="memory"      <?= $active_cat_key==='memory'    ?'selected':'' ?>>Memory</option>
                        <option value="os"          <?= $active_cat_key==='os'        ?'selected':'' ?>>OS</option>
                        <option value="ms_office"   <?= $active_cat_key==='ms_office' ?'selected':'' ?>>MS Office</option>
                        <option value="checked_by"  <?= $active_cat_key==='checked_by'?'selected':'' ?>>Checked By</option>
                    </select>

                    <!-- Step 2: Value dropdown (hidden until category chosen) -->
                    <select id="filter_val_select"
                        onchange="applyFilterVal(this.value)"
                        class="<?= $active_cat_key ? '' : 'hidden' ?> bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] text-slate-600 dark:text-slate-300 text-xs rounded-xl px-4 py-2 focus:ring-2 focus:ring-primary max-w-[200px]">
                    </select>

                    <!-- Active filter badge + clear -->
                    <?php if ($active_cat_key): ?>
                    <div class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-primary/10 border border-primary/30 text-primary text-xs font-semibold rounded-xl">
                        <span class="material-symbols-outlined text-[13px]">filter_alt</span>
                        <?= htmlspecialchars($active_cat_val) ?>
                        <a href="?search=<?= urlencode($search_term) ?>&sort=<?= urlencode($sort_by) ?>&limit=<?= urlencode($limit_param) ?>"
                           class="ml-1 text-primary/60 hover:text-red-500 transition-colors" title="Clear filter">
                            <span class="material-symbols-outlined text-[14px]">close</span>
                        </a>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Embed all filter options as JSON for JS -->
                <script>
                const _filterOpts = <?= json_encode($filter_options) ?>;
                const _activeCat  = <?= json_encode($active_cat_key) ?>;
                const _activeVal  = <?= json_encode($active_cat_val) ?>;

                function onCatChange(cat) {
                    const valSel = document.getElementById('filter_val_select');
                    if (!cat) { valSel.classList.add('hidden'); return; }

                    // Populate value dropdown
                    const opts = _filterOpts[cat] || [];
                    valSel.innerHTML = '<option value="All">All</option>';
                    opts.forEach(function(v) {
                        const opt = document.createElement('option');
                        opt.value = v;
                        opt.textContent = v;
                        if (cat === _activeCat && v === _activeVal) opt.selected = true;
                        valSel.appendChild(opt);
                    });

                    // Reset the previous category's hidden input to 'All'
                    ['department','printer','memory','os','ms_office','checked_by'].forEach(function(k) {
                        if (k !== cat) {
                            const h = document.getElementById('hid_' + k);
                            if (h) h.value = 'All';
                        }
                    });

                    valSel.classList.remove('hidden');
                }

                function applyFilterVal(val) {
                    const cat = document.getElementById('filter_cat_select').value;
                    if (!cat) return;
                    const hid = document.getElementById('hid_' + cat);
                    if (hid) hid.value = val;
                    const form = document.querySelector('#search-controls form');
                    if (form) form.submit();
                }

                // On page load: if a filter is active, populate the value dropdown
                document.addEventListener('DOMContentLoaded', function() {
                    if (_activeCat) { onCatChange(_activeCat); }
                });
                </script>
            </form>
        </div>

    <?php endif; // End non-AJAX header ?>

    <!-- Data Table -->
    <div id="computer-table-container"
        class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] rounded-2xl overflow-hidden shadow-sm transition-opacity duration-200">
        <div class="overflow-x-auto custom-scrollbar">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-white dark:bg-[#1a2130] border-b border-slate-200 dark:border-[#232b3d]">
                        <th
                            class="sticky-col-1 no-print px-2 py-1 w-8 text-center text-slate-500 bg-white dark:bg-[#1a2130] z-20">
                            <input type="checkbox" onclick="toggleAll(this)"
                                class="rounded border-slate-200 dark:border-[#232b3d] bg-slate-50 dark:bg-[#101622] text-primary focus:ring-primary h-3 w-3">
                        </th>
                        <th
                            class="px-2 py-1 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap min-w-[90px]">
                            Control #</th>
                        <th
                            class="px-2 py-1 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap min-w-[110px]">
                            Location</th>
                        <th class="px-2 py-1 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">
                            End User</th>
                        <th class="px-2 py-1 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">
                            MR/PAR</th>
                        <th class="px-2 py-1 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">
                            System Unit<br>S/N</th>
                        <th class="px-2 py-1 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">
                            Monitor<br>S/N</th>
                        <th class="px-2 py-1 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">
                            Mouse<br>S/N</th>
                        <th class="px-2 py-1 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">
                            Keyboard<br>S/N</th>
                        <th class="px-2 py-1 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">
                            Printer<br>S/N</th>
                        <th class="px-2 py-1 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">
                            Scanner<br>S/N</th>
                        <th class="px-2 py-1 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">
                            AVR/UPS<br>S/N</th>
                        <th class="px-2 py-1 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">
                            Processor</th>
                        <th class="px-2 py-1 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">
                            Memory</th>
                        <th class="px-2 py-1 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">
                            Storage</th>
                        <th class="px-2 py-1 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">
                            OS</th>
                        <th class="px-2 py-1 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">
                            OS Key</th>
                        <th class="px-2 py-1 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">
                            License</th>
                        <th class="px-2 py-1 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">
                            MS Office</th>
                        <th class="px-2 py-1 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">
                            MS Office<br>Email</th>
                        <th class="px-2 py-1 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">
                            IP Address</th>
                        <th class="px-2 py-1 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">
                            MAC</th>
                        <th class="px-2 py-1 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">
                            Endpoint<br>Secure</th>
                        <th class="px-2 py-1 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">
                            Firewall</th>
                        <th class="px-2 py-1 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">
                            Checked By</th>
                        <th class="px-2 py-1 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">
                            Encoded By</th>
                        <th class="px-2 py-1 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">
                            Remarks</th>
                        <th class="no-print px-2 py-1 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">
                            Last Edited By</th>
                        <th
                            class="px-2 py-1 text-[10px] font-bold text-slate-500 uppercase text-right whitespace-nowrap">
                            Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#232b3d]/50">
                    <?php if (empty($computers)): ?>
                        <tr>
                            <td colspan="28" class="px-6 py-12 text-center text-slate-500">No computers found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($computers as $item): ?>
                            <tr class="hover:bg-white/5 transition-colors text-[11px]" data-item='<?= htmlspecialchars(json_encode($item), ENT_QUOTES, "UTF-8") ?>'>
                                <td class="no-print px-2 py-1 text-center">
                                    <input type="checkbox" name="item_id[]" value="<?= $item['id'] ?>"
                                        class="item-checkbox rounded border-slate-200 dark:border-[#232b3d] bg-slate-50 dark:bg-[#101622] text-primary focus:ring-primary h-3 w-3">
                                </td>
                                <td class="px-2 py-1 font-mono text-primary whitespace-nowrap cursor-pointer hover:bg-primary/10 hover:text-primary-dark transition-colors min-w-[90px]"
                                    onclick='openEditModal(<?= htmlspecialchars(json_encode($item), ENT_QUOTES, "UTF-8") ?>)'
                                    title="Click to edit">
                                    <?= htmlspecialchars($item['control_number'] ?: '-') ?>
                                </td>
                                <td class="px-2 py-1 text-slate-600 dark:text-slate-400 whitespace-nowrap min-w-[110px]">
                                    <?= htmlspecialchars($item['department'] ?: '-') ?>
                                </td>
                                <td class="px-2 py-1 font-medium text-slate-900 dark:text-white whitespace-nowrap">
                                    <?= htmlspecialchars($item['end_user'] ?: '-') ?>
                                </td>
                                <td class="px-2 py-1 text-slate-600 dark:text-slate-400 whitespace-nowrap">
                                    <?= htmlspecialchars($item['mr_par'] ?: '-') ?>
                                </td>
                                <td class="px-2 py-1 text-slate-600 dark:text-slate-400 whitespace-nowrap">
                                    <div>
                                        <?= htmlspecialchars($item['system_unit'] ?: '-') ?>
                                    </div>
                                    <div class="text-[9px] text-slate-500 font-mono">
                                        <?= htmlspecialchars($item['system_unit_sn'] ?: '-') ?>
                                    </div>
                                </td>
                                <td class="px-2 py-1 text-slate-600 dark:text-slate-400 whitespace-nowrap">
                                    <div>
                                        <?= htmlspecialchars($item['monitor'] ?: '-') ?>
                                    </div>
                                    <div class="text-[9px] text-slate-500 font-mono">
                                        <?= htmlspecialchars($item['monitor_sn'] ?: '-') ?>
                                    </div>
                                </td>
                                <td class="px-2 py-1 text-slate-600 dark:text-slate-400 whitespace-nowrap">
                                    <div>
                                        <?= htmlspecialchars($item['mouse'] ?: '-') ?>
                                    </div>
                                    <div class="text-[9px] text-slate-500 font-mono">
                                        <?= htmlspecialchars($item['mouse_sn'] ?: '-') ?>
                                    </div>
                                </td>
                                <td class="px-2 py-1 text-slate-600 dark:text-slate-400 whitespace-nowrap">
                                    <div>
                                        <?= htmlspecialchars($item['keyboard'] ?: '-') ?>
                                    </div>
                                    <div class="text-[9px] text-slate-500 font-mono">
                                        <?= htmlspecialchars($item['keyboard_sn'] ?: '-') ?>
                                    </div>
                                </td>
                                <td class="px-2 py-1 text-slate-600 dark:text-slate-400">
                                    <div class="flex flex-col gap-0.5">
                                        <?php if (!empty($item['printer'])): ?>
                                            <?php foreach(array_filter(array_map('trim', explode(',', $item['printer']))) as $ptag): ?>
                                                <span class="inline-block bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300 text-[9px] font-semibold px-1.5 py-0.5 rounded whitespace-nowrap"><?= htmlspecialchars($ptag) ?></span>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <span class="text-slate-400">-</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-[9px] text-slate-500 font-mono mt-0.5">
                                        <?= htmlspecialchars($item['printer_sn'] ?: '-') ?>
                                    </div>
                                </td>
                                <td class="px-2 py-1 text-slate-600 dark:text-slate-400">
                                    <div class="flex flex-col gap-0.5">
                                        <?php if (!empty($item['scanner'])): ?>
                                            <?php foreach(array_filter(array_map('trim', explode(',', $item['scanner']))) as $stag): ?>
                                                <span class="inline-block bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300 text-[9px] font-semibold px-1.5 py-0.5 rounded whitespace-nowrap"><?= htmlspecialchars($stag) ?></span>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <span class="text-slate-400">-</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-[9px] text-slate-500 font-mono mt-0.5">
                                        <?= htmlspecialchars($item['scanner_sn'] ?: '-') ?>
                                    </div>
                                </td>
                                <td class="px-2 py-1 text-slate-600 dark:text-slate-400">
                                    <div class="flex flex-col gap-0.5">
                                        <?php if (!empty($item['avr_ups'])): ?>
                                            <?php foreach(array_filter(array_map('trim', explode(',', $item['avr_ups']))) as $atag): ?>
                                                <span class="inline-block bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300 text-[9px] font-semibold px-1.5 py-0.5 rounded whitespace-nowrap"><?= htmlspecialchars($atag) ?></span>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <span class="text-slate-400">-</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-[9px] text-slate-500 font-mono mt-0.5">
                                        <?= htmlspecialchars($item['avr_ups_sn'] ?: '-') ?>
                                    </div>
                                </td>
                                <td class="px-2 py-1 text-slate-600 dark:text-slate-400 whitespace-nowrap">
                                    <?= htmlspecialchars($item['processor'] ?: '-') ?>
                                </td>
                                <td class="px-2 py-1 text-slate-600 dark:text-slate-400 whitespace-nowrap">
                                    <?= htmlspecialchars($item['memory'] ?: '-') ?>
                                </td>
                                <td class="px-2 py-1 text-slate-600 dark:text-slate-400 whitespace-nowrap">
                                    <?= htmlspecialchars($item['storage'] ?: '-') ?>
                                </td>
                                <td class="px-2 py-1 text-slate-600 dark:text-slate-400 whitespace-nowrap">
                                    <?= htmlspecialchars($item['os'] ?: '-') ?>
                                </td>
                                <td class="px-2 py-1 text-[10px] font-mono text-slate-500 truncate max-w-[100px] whitespace-nowrap"
                                    title="<?= htmlspecialchars($item['os_product_key'] ?? $item['ms_office_key'] ?? '') ?>">
                                    <?= htmlspecialchars(($item['os_product_key'] ?? $item['ms_office_key'] ?? '') ?: '-') ?>
                                </td>
                                <td
                                    class="px-2 py-1 text-center <?= $item['license'] === 'Y' ? 'text-[#10b981]' : 'text-slate-500' ?> whitespace-nowrap">
                                    <?= $item['license'] ?>
                                </td>
                                <td class="px-2 py-1 text-slate-600 dark:text-slate-400 whitespace-nowrap">
                                    <?= htmlspecialchars($item['microsoft_office'] ?: '-') ?>
                                </td>
                                <td class="px-2 py-1 text-slate-600 dark:text-slate-400 whitespace-nowrap">
                                    <?= htmlspecialchars($item['ms_office_email'] ?? '-') ?>
                                </td>
                                <td class="px-2 py-1 font-mono text-slate-600 dark:text-slate-400 whitespace-nowrap">
                                    <?= htmlspecialchars($item['ip_address'] ?: '-') ?>
                                </td>
                                <td class="px-2 py-1 text-[10px] font-mono text-slate-500 whitespace-nowrap">
                                    <?= htmlspecialchars($item['mac_address'] ?: '-') ?>
                                </td>
                                <td class="px-2 py-1 text-center whitespace-nowrap">
                                    <span
                                        class="<?= ($item['endpoint_secure'] ?? 'N') === 'Y' ? 'text-emerald-500 font-bold' : 'text-slate-500' ?>">
                                        <?= ($item['endpoint_secure'] ?? 'N') === 'Y' ? 'Yes' : 'No' ?>
                                    </span>
                                </td>
                                <td class="px-2 py-1 text-center whitespace-nowrap">
                                    <span class="<?= ($item['firewall'] ?? 'N') === 'Y' ? 'inline-flex items-center gap-1 text-blue-500 font-bold' : 'text-slate-500' ?>">
                                        <?php if (($item['firewall'] ?? 'N') === 'Y'): ?>
                                        <span class="material-symbols-outlined text-[13px]">security</span> Yes
                                        <?php else: ?>No<?php endif; ?>
                                    </span>
                                </td>
                                <td class="px-2 py-1 text-slate-600 dark:text-slate-400 whitespace-nowrap">
                                    <?= htmlspecialchars($item['checked_by'] ?: '-') ?>
                                </td>
                                <td class="px-2 py-1 text-slate-600 dark:text-slate-400 whitespace-nowrap">
                                    <?= htmlspecialchars($item['encoded_by'] ?: '-') ?>
                                </td>
                                <td class="px-2 py-1 text-slate-500 truncate max-w-[140px] whitespace-nowrap"
                                    title="<?= htmlspecialchars((!empty($item['remarks_date']) ? date('M d, Y', strtotime($item['remarks_date'])) . ' — ' : '') . ($item['remarks'] ?? '')) ?>">
                                    <?php if (!empty($item['remarks_date'])): ?>
                                        <span class="text-[10px] font-semibold text-primary/80"><?= date('M d, Y', strtotime($item['remarks_date'])) ?></span>
                                        <?php if (!empty($item['remarks'])): ?> &mdash; <?php endif; ?>
                                    <?php endif; ?>
                                    <?= htmlspecialchars($item['remarks'] ?: (!empty($item['remarks_date']) ? '' : '-')) ?>
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
                                    <button
                                        onclick='openEditModal(<?= htmlspecialchars(json_encode($item), ENT_QUOTES, "UTF-8") ?>)'
                                        class="p-1 hover:bg-primary/10 hover:text-primary text-slate-500 rounded-lg">
                                        <span class="material-symbols-outlined text-[18px]">edit</span>
                                    </button>
                                    <form method="POST" onsubmit="return confirm('Delete this computer?');">
                                        <?= getCsrfInput() ?>
                                        <input type="hidden" name="action" value="delete_computer">
                                        <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                        <button class="p-1 hover:bg-red-500/10 hover:text-red-500 text-slate-500 rounded-lg">
                                            <span class="material-symbols-outlined text-[18px]">delete</span>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if (!$is_ajax): ?>
        <!-- Bottom Pagination -->
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
                        <a href="?page=1<?= $page_params ?>"
                            class="p-2 hover:bg-white/5 text-slate-400 hover:text-slate-900 dark:hover:text-white rounded-lg transition-colors <?= $page <= 1 ? 'pointer-events-none opacity-50' : '' ?>"
                            title="First Page">
                            <span class="material-symbols-outlined text-[18px]">first_page</span>
                        </a>
                    <?php endif; ?>

                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page - 1 ?><?= $page_params ?>"
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
                            <a href="?page=<?= $i ?><?= $page_params ?>"
                                class="w-8 h-8 flex items-center justify-center rounded-lg text-xs font-bold transition-colors <?= $i === $page ? 'bg-primary text-white' : 'text-slate-400 hover:bg-white/5 hover:text-slate-900 dark:text-white' ?>">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>
                    </div>

                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?= $page + 1 ?><?= $page_params ?>"
                            class="p-2 hover:bg-white/5 text-slate-400 hover:text-slate-900 dark:hover:text-white rounded-lg transition-colors">
                            <span class="material-symbols-outlined text-[18px]">chevron_right</span>
                        </a>
                    <?php else: ?>
                        <button disabled class="p-2 text-slate-700 cursor-not-allowed">
                            <span class="material-symbols-outlined text-[18px]">chevron_right</span>
                        </button>
                    <?php endif; ?>

                    <?php if ($total_pages > 1): ?>
                        <a href="?page=<?= $total_pages ?><?= $page_params ?>"
                            class="p-2 hover:bg-white/5 text-slate-400 hover:text-slate-900 dark:hover:text-white rounded-lg transition-colors <?= $page >= $total_pages ? 'pointer-events-none opacity-50' : '' ?>"
                            title="Last Page">
                            <span class="material-symbols-outlined text-[18px]">last_page</span>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="px-6 py-4 border-t border-slate-200 dark:border-[#232b3d] bg-white dark:bg-[#1a2130]">
                <div class="text-xs text-slate-500">
                    Showing <span class="font-bold text-slate-900 dark:text-white">
                        <?= number_format($total_items) ?>
                    </span> result(s)
                </div>
            </div>
        <?php endif; ?>
    <?php endif; // End !$is_ajax ?>
</div>

<?php if ($is_ajax): ?>
    <?php
    $content = ob_get_clean();
    echo $content;
    exit; // Stop further output
?>
<?php else: ?>
    </div> <!-- End computer-table-container wrapper -->
    </div> <!-- End main container -->
<?php endif; ?>

<!-- Import CSV Modal -->
<div id="importModal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-background-dark/80 backdrop-blur-sm" onclick="toggleModal('importModal')"></div>
        <div
            class="relative bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] rounded-2xl max-w-md w-full p-4 sm:p-6 shadow-2xl">
            <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-2">Import Computers from CSV</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 mb-4">
                Expected columns (35 total):
                <span class="font-semibold text-slate-700 dark:text-slate-300">
                    Building, Floor, Department, End User, MR/PAR, Control Number, System Unit, System Unit S/N,
                    Monitor, Monitor S/N, Mouse, Mouse S/N, Keyboard, Keyboard S/N, Printer, Printer S/N,
                    Scanner, Scanner S/N, AVR/UPS, AVR/UPS S/N, Processor, Memory, Storage, OS, OS Product Key,
                    License, Microsoft Office, MS Office Email, IP Address, MAC Address, Endpoint Secure,
                    <strong>Firewall</strong>, Checked By, Encoded By, Remarks
                </span>
            </p>
            <form method="POST" enctype="multipart/form-data">
                <?= getCsrfInput() ?>
                <input type="file" name="csv_file" accept=".csv" required
                    class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5 mb-4">
                <div class="flex justify-end gap-3">
                    <button type="button" onclick="toggleModal('importModal')"
                        class="px-4 py-2 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white">Cancel</button>
                    <button type="submit" class="px-6 py-2 bg-primary text-white font-bold rounded-xl">Import</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Computer Modal -->
<div id="addComputerModal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-background-dark/80 backdrop-blur-sm" onclick="toggleModal('addComputerModal')">
        </div>
        <div
            class="relative bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] rounded-2xl max-w-6xl w-full p-4 sm:p-6 shadow-2xl max-h-[90vh] overflow-y-auto custom-scrollbar">
            <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-6">Add New Computer</h3>
            <form method="POST">
                <?= getCsrfInput() ?>
                <input type="hidden" name="action" value="add_computer">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <!-- Location Fields -->
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
                    <input type="text" name="department" placeholder="Department/Section"
                        class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                    <input type="text" name="end_user" placeholder="End User"
                        class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                    <input type="text" name="mr_par" placeholder="MR/PAR"
                        class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                    <input type="text" name="control_number" placeholder="Control Number"
                        class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">


                    <!-- System Unit -->
                    <input type="text" name="system_unit" placeholder="System Unit"
                        class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                    <input type="text" name="system_unit_sn" placeholder="System Unit S/N"
                        class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">

                    <!-- Peripherals -->
                    <input type="text" name="monitor" placeholder="Monitor"
                        class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                    <input type="text" name="monitor_sn" placeholder="Monitor S/N"
                        class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                    <input type="text" name="mouse" placeholder="Mouse"
                        class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                    <input type="text" name="mouse_sn" placeholder="Mouse S/N"
                        class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                    <input type="text" name="keyboard" placeholder="Keyboard"
                        class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                    <input type="text" name="keyboard_sn" placeholder="Keyboard S/N"
                        class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                    <!-- Printer Multi-Select -->
                    <div class="w-full">
                        <div class="multi-select-dropdown" id="add-printer-dropdown">
                            <div class="multi-select-trigger" onclick="toggleMultiSelect('add-printer-dropdown')">
                                <span class="multi-select-label">-- Select Printer(s) --</span>
                                <span class="material-symbols-outlined multi-select-arrow">expand_more</span>
                            </div>
                            <div class="multi-select-panel">
                                <?php foreach ($printer_options as $opt): ?>
                                <label class="multi-select-option">
                                    <input type="checkbox" name="printer[]" value="<?= htmlspecialchars($opt) ?>">
                                    <span><?= htmlspecialchars($opt) ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <input type="text" name="printer_sn" placeholder="Printer S/N"
                        class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                    <!-- Scanner Multi-Select -->
                    <div class="w-full">
                        <div class="multi-select-dropdown" id="add-scanner-dropdown">
                            <div class="multi-select-trigger" onclick="toggleMultiSelect('add-scanner-dropdown')">
                                <span class="multi-select-label">-- Select Scanner(s) --</span>
                                <span class="material-symbols-outlined multi-select-arrow">expand_more</span>
                            </div>
                            <div class="multi-select-panel">
                                <?php foreach ($scanner_options as $opt): ?>
                                <label class="multi-select-option">
                                    <input type="checkbox" name="scanner[]" value="<?= htmlspecialchars($opt) ?>">
                                    <span><?= htmlspecialchars($opt) ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <input type="text" name="scanner_sn" placeholder="Scanner S/N"
                        class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                    <!-- AVR/UPS Multi-Select -->
                    <div class="w-full">
                        <div class="multi-select-dropdown" id="add-avr-dropdown">
                            <div class="multi-select-trigger" onclick="toggleMultiSelect('add-avr-dropdown')">
                                <span class="multi-select-label">-- Select AVR/UPS --</span>
                                <span class="material-symbols-outlined multi-select-arrow">expand_more</span>
                            </div>
                            <div class="multi-select-panel">
                                <?php foreach ($avr_ups_options as $opt): ?>
                                <label class="multi-select-option">
                                    <input type="checkbox" name="avr_ups[]" value="<?= htmlspecialchars($opt) ?>">
                                    <span><?= htmlspecialchars($opt) ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <input type="text" name="avr_ups_sn" placeholder="AVR/UPS S/N"
                        class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">

                    <!-- Specs -->
                    <input type="text" name="processor" placeholder="Processor"
                        class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                    <input type="text" name="memory" placeholder="Memory (e.g., 8GB DDR4)"
                        class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                    <input type="text" name="storage" placeholder="Storage (e.g., 256GB SSD)"
                        class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                    <select name="os"
                        class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                        <option value="">-- Select Operating System --</option>
                        <option value="WINDOWS 11 HOME SINGLE LANGUAGE">WINDOWS 11 HOME SINGLE LANGUAGE</option>
                        <option value="WINDOWS 11 PROFESSIONAL">WINDOWS 11 PROFESSIONAL</option>
                        <option value="WINDOWS 10 HOME SINGLE LANGUAGE">WINDOWS 10 HOME SINGLE LANGUAGE</option>
                        <option value="WINDOWS 10 PROFESSIONAL">WINDOWS 10 PROFESSIONAL</option>
                        <option value="WINDOWS 10 ENTERPRISE">WINDOWS 10 ENTERPRISE</option>
                        <option value="WINDOWS 10 EDUCATION">WINDOWS 10 EDUCATION</option>
                        <option value="WINDOWS 7 ULTIMATE">WINDOWS 7 ULTIMATE</option>
                        <option value="WINDOWS 7 PROFESSIONAL">WINDOWS 7 PROFESSIONAL</option>
                    </select>
                    <input type="text" name="os_product_key" placeholder="OS Product Key"
                        class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">

                    <!-- Software -->
                    <select name="license"
                        class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                        <option value="N">License: No</option>
                        <option value="Y">License: Yes</option>
                    </select>
                    <select name="microsoft_office"
                        class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                        <option value="">-- Select Microsoft Office --</option>
                        <option value="MICROSOFT 365">MICROSOFT 365</option>
                        <option value="PROFESSIONAL PLUS 2024">PROFESSIONAL PLUS 2024</option>
                        <option value="LTSC 2024">LTSC 2024</option>
                        <option value="HOME &amp; STUDENT 2024">HOME &amp; STUDENT 2024</option>
                        <option value="HOME BUSINESS 2024">HOME BUSINESS 2024</option>
                        <option value="HOME 2024">HOME 2024</option>
                        <option value="STANDARD 2024">STANDARD 2024</option>
                        <option value="PROFESSIONAL PLUS 2021">PROFESSIONAL PLUS 2021</option>
                        <option value="LTSC 2021">LTSC 2021</option>
                        <option value="HOME &amp; STUDENT 2021">HOME &amp; STUDENT 2021</option>
                        <option value="HOME BUSINESS 2021">HOME BUSINESS 2021</option>
                        <option value="STANDARD 2021">STANDARD 2021</option>
                        <option value="PROFESSIONAL PLUS 2019">PROFESSIONAL PLUS 2019</option>
                        <option value="LTSC 2019">LTSC 2019</option>
                        <option value="HOME &amp; STUDENT 2019">HOME &amp; STUDENT 2019</option>
                        <option value="HOME BUSINESS 2019">HOME BUSINESS 2019</option>
                        <option value="STANDARD 2019">STANDARD 2019</option>
                        <option value="PROFESSIONAL PLUS 2016">PROFESSIONAL PLUS 2016</option>
                        <option value="LTSC 2016">LTSC 2016</option>
                        <option value="HOME &amp; STUDENT 2016">HOME &amp; STUDENT 2016</option>
                        <option value="HOME BUSINESS 2016">HOME BUSINESS 2016</option>
                        <option value="STANDARD 2016">STANDARD 2016</option>
                        <option value="PROFESSIONAL PLUS 2013">PROFESSIONAL PLUS 2013</option>
                        <option value="MONDO 2016">MONDO 2016</option>
                        <option value="WPS">WPS</option>
                    </select>
                    <input type="email" name="ms_office_email" placeholder="MS Office Email"
                        class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">

                    <!-- Network -->
                    <input type="text" name="ip_address" placeholder="IP Address"
                        class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                    <input type="text" name="mac_address" placeholder="MAC Address"
                        class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">

                    <!-- Security -->
                    <select name="endpoint_secure"
                        class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                        <option value="N">Endpoint Secure: No</option>
                        <option value="Y">Endpoint Secure: Yes</option>
                    </select>
                    <select name="firewall"
                        class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                        <option value="N">Firewall: No</option>
                        <option value="Y">Firewall: Yes</option>
                    </select>

                    <!-- Tracking -->
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Checked By</label>
                        <select name="checked_by" required
                            class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                            <option value="">-- Select Checked By --</option>
                            <?php foreach ($staff_users as $u): ?>
                                <option value="<?= htmlspecialchars($u) ?>"><?= htmlspecialchars($u) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Encoded By</label>
                        <select name="encoded_by" required
                            class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                            <option value="">-- Select Encoded By --</option>
                            <?php foreach ($staff_users as $u): ?>
                                <option value="<?= htmlspecialchars($u) ?>"><?= htmlspecialchars($u) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Date (Remarks Date) -->
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Date</label>
                        <input type="date" name="remarks_date"
                            class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                    </div>

                    <!-- Remarks -->
                    <div class="col-span-2">
                        <label class="block text-xs font-medium text-slate-400 mb-1">Remarks</label>
                        <textarea name="remarks" placeholder="Remarks" rows="2"
                            class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5 resize-none"></textarea>
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" onclick="toggleModal('addComputerModal')"
                        class="px-4 py-2 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white">Cancel</button>
                    <button type="submit" class="px-6 py-2 bg-primary text-white font-bold rounded-xl">Save
                        Computer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Computer Modal -->
<div id="editComputerModal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-background-dark/80 backdrop-blur-sm" onclick="toggleModal('editComputerModal')">
        </div>
        <div
            class="relative bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] rounded-2xl max-w-6xl w-full p-4 sm:p-6 shadow-2xl max-h-[90vh] overflow-y-auto custom-scrollbar">
            <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-6">Edit Computer</h3>
            <form method="POST">
                <?= getCsrfInput() ?>
                <input type="hidden" name="action" value="edit_computer">
                <input type="hidden" name="id" id="edit_id">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div><label class="block text-xs font-medium text-slate-400 mb-1">Building</label>
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
                    <div><label class="block text-xs font-medium text-slate-400 mb-1">Floor</label>
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
                    <div><label class="block text-xs font-medium text-slate-400 mb-1">Department/Section</label><input
                            type="text" name="department" id="edit_department"
                            class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                    </div>

                    <div><label class="block text-xs font-medium text-slate-400 mb-1">End User</label><input type="text"
                            name="end_user" id="edit_end_user"
                            class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                    </div>
                    <div><label class="block text-xs font-medium text-slate-400 mb-1">MR/PAR</label><input type="text"
                            name="mr_par" id="edit_mr_par"
                            class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                    </div>
                    <div><label class="block text-xs font-medium text-slate-400 mb-1">Control Number</label><input
                            type="text" name="control_number" id="edit_control_number"
                            class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                    </div>
                    <div><label class="block text-xs font-medium text-slate-400 mb-1">System Unit</label><input
                            type="text" name="system_unit" id="edit_system_unit"
                            class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                    </div>
                    <div><label class="block text-xs font-medium text-slate-400 mb-1">System Unit S/N</label><input
                            type="text" name="system_unit_sn" id="edit_system_unit_sn"
                            class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                    </div>
                    <div><label class="block text-xs font-medium text-slate-400 mb-1">Monitor</label><input type="text"
                            name="monitor" id="edit_monitor"
                            class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                    </div>
                    <div><label class="block text-xs font-medium text-slate-400 mb-1">Monitor S/N</label><input
                            type="text" name="monitor_sn" id="edit_monitor_sn"
                            class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                    </div>
                    <div><label class="block text-xs font-medium text-slate-400 mb-1">Mouse</label><input type="text"
                            name="mouse" id="edit_mouse"
                            class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                    </div>
                    <div><label class="block text-xs font-medium text-slate-400 mb-1">Mouse S/N</label><input
                            type="text" name="mouse_sn" id="edit_mouse_sn"
                            class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                    </div>
                    <div><label class="block text-xs font-medium text-slate-400 mb-1">Keyboard</label><input type="text"
                            name="keyboard" id="edit_keyboard"
                            class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                    </div>
                    <div><label class="block text-xs font-medium text-slate-400 mb-1">Keyboard S/N</label><input
                            type="text" name="keyboard_sn" id="edit_keyboard_sn"
                            class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                    </div>
                    <div><label class="block text-xs font-medium text-slate-400 mb-1">Printer</label>
                        <div class="multi-select-dropdown" id="edit-printer-dropdown">
                            <div class="multi-select-trigger" onclick="toggleMultiSelect('edit-printer-dropdown')">
                                <span class="multi-select-label" id="edit-printer-label">-- Select Printer(s) --</span>
                                <span class="material-symbols-outlined multi-select-arrow">expand_more</span>
                            </div>
                            <div class="multi-select-panel">
                                <?php foreach ($printer_options as $opt): ?>
                                <label class="multi-select-option">
                                    <input type="checkbox" name="printer[]" value="<?= htmlspecialchars($opt) ?>" class="edit-printer-cb">
                                    <span><?= htmlspecialchars($opt) ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <div><label class="block text-xs font-medium text-slate-400 mb-1">Printer S/N</label><input
                            type="text" name="printer_sn" id="edit_printer_sn"
                            class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                    </div>
                    <div><label class="block text-xs font-medium text-slate-400 mb-1">Scanner</label>
                        <div class="multi-select-dropdown" id="edit-scanner-dropdown">
                            <div class="multi-select-trigger" onclick="toggleMultiSelect('edit-scanner-dropdown')">
                                <span class="multi-select-label" id="edit-scanner-label">-- Select Scanner(s) --</span>
                                <span class="material-symbols-outlined multi-select-arrow">expand_more</span>
                            </div>
                            <div class="multi-select-panel">
                                <?php foreach ($scanner_options as $opt): ?>
                                <label class="multi-select-option">
                                    <input type="checkbox" name="scanner[]" value="<?= htmlspecialchars($opt) ?>" class="edit-scanner-cb">
                                    <span><?= htmlspecialchars($opt) ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <div><label class="block text-xs font-medium text-slate-400 mb-1">Scanner S/N</label><input
                            type="text" name="scanner_sn" id="edit_scanner_sn"
                            class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                    </div>
                    <div><label class="block text-xs font-medium text-slate-400 mb-1">AVR/UPS</label>
                        <div class="multi-select-dropdown" id="edit-avr-dropdown">
                            <div class="multi-select-trigger" onclick="toggleMultiSelect('edit-avr-dropdown')">
                                <span class="multi-select-label" id="edit-avr-label">-- Select AVR/UPS --</span>
                                <span class="material-symbols-outlined multi-select-arrow">expand_more</span>
                            </div>
                            <div class="multi-select-panel">
                                <?php foreach ($avr_ups_options as $opt): ?>
                                <label class="multi-select-option">
                                    <input type="checkbox" name="avr_ups[]" value="<?= htmlspecialchars($opt) ?>" class="edit-avr-cb">
                                    <span><?= htmlspecialchars($opt) ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <div><label class="block text-xs font-medium text-slate-400 mb-1">AVR/UPS S/N</label><input
                            type="text" name="avr_ups_sn" id="edit_avr_ups_sn"
                            class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                    </div>
                    <div><label class="block text-xs font-medium text-slate-400 mb-1">Processor</label><input
                            type="text" name="processor" id="edit_processor"
                            class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                    </div>
                    <div><label class="block text-xs font-medium text-slate-400 mb-1">Memory</label><input type="text"
                            name="memory" id="edit_memory"
                            class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                    </div>
                    <div><label class="block text-xs font-medium text-slate-400 mb-1">Storage</label><input type="text"
                            name="storage" id="edit_storage"
                            class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                    </div>
                    <div><label class="block text-xs font-medium text-slate-400 mb-1">OS</label>
                        <select name="os" id="edit_os"
                            class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                            <option value="">-- Select Operating System --</option>
                            <option value="WINDOWS 11 HOME SINGLE LANGUAGE">WINDOWS 11 HOME SINGLE LANGUAGE</option>
                            <option value="WINDOWS 11 PROFESSIONAL">WINDOWS 11 PROFESSIONAL</option>
                            <option value="WINDOWS 10 HOME SINGLE LANGUAGE">WINDOWS 10 HOME SINGLE LANGUAGE</option>
                            <option value="WINDOWS 10 PROFESSIONAL">WINDOWS 10 PROFESSIONAL</option>
                            <option value="WINDOWS 10 ENTERPRISE">WINDOWS 10 ENTERPRISE</option>
                            <option value="WINDOWS 10 EDUCATION">WINDOWS 10 EDUCATION</option>
                            <option value="WINDOWS 7 ULTIMATE">WINDOWS 7 ULTIMATE</option>
                            <option value="WINDOWS 7 PROFESSIONAL">WINDOWS 7 PROFESSIONAL</option>
                        </select>
                    </div>
                    <div><label class="block text-xs font-medium text-slate-400 mb-1">OS Product Key</label><input
                            type="text" name="os_product_key" id="edit_os_product_key"
                            class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                    </div>
                    <div><label class="block text-xs font-medium text-slate-400 mb-1">License</label><select
                            name="license" id="edit_license"
                            class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                            <option value="N">No</option>
                            <option value="Y">Yes</option>
                        </select></div>
                    <div><label class="block text-xs font-medium text-slate-400 mb-1">Microsoft Office</label>
                        <select name="microsoft_office" id="edit_microsoft_office"
                            class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                            <option value="">-- Select Microsoft Office --</option>
                            <option value="MICROSOFT 365">MICROSOFT 365</option>
                            <option value="PROFESSIONAL PLUS 2024">PROFESSIONAL PLUS 2024</option>
                            <option value="LTSC 2024">LTSC 2024</option>
                            <option value="HOME &amp; STUDENT 2024">HOME &amp; STUDENT 2024</option>
                            <option value="HOME BUSINESS 2024">HOME BUSINESS 2024</option>
                            <option value="HOME 2024">HOME 2024</option>
                            <option value="STANDARD 2024">STANDARD 2024</option>
                            <option value="PROFESSIONAL PLUS 2021">PROFESSIONAL PLUS 2021</option>
                            <option value="LTSC 2021">LTSC 2021</option>
                            <option value="HOME &amp; STUDENT 2021">HOME &amp; STUDENT 2021</option>
                            <option value="HOME BUSINESS 2021">HOME BUSINESS 2021</option>
                            <option value="STANDARD 2021">STANDARD 2021</option>
                            <option value="PROFESSIONAL PLUS 2019">PROFESSIONAL PLUS 2019</option>
                            <option value="LTSC 2019">LTSC 2019</option>
                            <option value="HOME &amp; STUDENT 2019">HOME &amp; STUDENT 2019</option>
                            <option value="HOME BUSINESS 2019">HOME BUSINESS 2019</option>
                            <option value="STANDARD 2019">STANDARD 2019</option>
                            <option value="PROFESSIONAL PLUS 2016">PROFESSIONAL PLUS 2016</option>
                            <option value="LTSC 2016">LTSC 2016</option>
                            <option value="HOME &amp; STUDENT 2016">HOME &amp; STUDENT 2016</option>
                            <option value="HOME BUSINESS 2016">HOME BUSINESS 2016</option>
                            <option value="STANDARD 2016">STANDARD 2016</option>
                            <option value="PROFESSIONAL PLUS 2013">PROFESSIONAL PLUS 2013</option>
                            <option value="MONDO 2016">MONDO 2016</option>
                            <option value="WPS">WPS</option>
                        </select>
                    </div>
                    <div><label class="block text-xs font-medium text-slate-400 mb-1">MS Office Email</label><input
                            type="email" name="ms_office_email" id="edit_ms_office_email"
                            class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                    </div>
                    <div><label class="block text-xs font-medium text-slate-400 mb-1">IP Address</label><input
                            type="text" name="ip_address" id="edit_ip_address"
                            class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                    </div>
                    <div><label class="block text-xs font-medium text-slate-400 mb-1">MAC Address</label><input
                            type="text" name="mac_address" id="edit_mac_address"
                            class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                    </div>
                    <div><label class="block text-xs font-medium text-slate-400 mb-1">Endpoint Secure</label><select
                            name="endpoint_secure" id="edit_endpoint_secure"
                            class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                            <option value="N">No</option>
                            <option value="Y">Yes</option>
                        </select></div>
                    <div><label class="block text-xs font-medium text-slate-400 mb-1">Firewall</label><select
                            name="firewall" id="edit_firewall"
                            class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                            <option value="N">No</option>
                            <option value="Y">Yes</option>
                        </select></div>
                    <div><label class="block text-xs font-medium text-slate-400 mb-1">Checked By</label>
                        <select name="checked_by" id="edit_checked_by" required
                            class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                            <option value="">-- Select Checked By --</option>
                            <?php foreach ($staff_users as $u): ?>
                                <option value="<?= htmlspecialchars($u) ?>"><?= htmlspecialchars($u) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div><label class="block text-xs font-medium text-slate-400 mb-1">Encoded By</label>
                        <select name="encoded_by" id="edit_encoded_by" required
                            class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                            <option value="">-- Select Encoded By --</option>
                            <?php foreach ($staff_users as $u): ?>
                                <option value="<?= htmlspecialchars($u) ?>"><?= htmlspecialchars($u) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Date (Remarks Date) -->
                    <div><label class="block text-xs font-medium text-slate-400 mb-1">Date</label>
                        <input type="date" name="remarks_date" id="edit_remarks_date"
                            class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5">
                    </div>

                    <!-- Remarks -->
                    <div class="col-span-2"><label class="block text-xs font-medium text-slate-400 mb-1">Remarks</label>
                        <textarea name="remarks" id="edit_remarks" rows="2"
                            class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5 resize-none"></textarea>
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" onclick="toggleModal('editComputerModal')"
                        class="px-4 py-2 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white">Cancel</button>
                    <button type="submit" class="px-6 py-2 bg-primary text-white font-bold rounded-xl">Update
                        Computer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>


    function openEditModal(item) {
        document.getElementById('edit_id').value = item.id;

        // Parse stored location (department column) → Building, Floor, Department
        const locationStr = item.department || '';
        let building = '';
        let floor = '';
        let dept = locationStr;
        const buildings = ['ACIS', 'Bayanihan', 'Bio Safety', 'Capiz', 'Dietary', 'Frontline', 'HOPSS', 'Isolation', 'Lingap Baga', 'Medicine', 'OB-Gyne/Pedia', 'OPD', 'Orthopaedics', 'Surgery', 'Trauma', 'Wellness'];
        const floors = ['GF', '2F', '3F', '4F', '5F', '6F', '7F'];
        for (const b of buildings) {
            if (dept.startsWith(b)) { building = b; dept = dept.substring(b.length).trim(); break; }
        }
        for (const f of floors) {
            if (dept.startsWith(f)) { floor = f; dept = dept.substring(f.length).trim(); break; }
        }
        document.getElementById('edit_building').value = building;
        document.getElementById('edit_floor').value = floor;
        document.getElementById('edit_department').value = dept;
        document.getElementById('edit_end_user').value = item.end_user || '';

        document.getElementById('edit_mr_par').value = item.mr_par || '';
        document.getElementById('edit_control_number').value = item.control_number || '';
        document.getElementById('edit_system_unit').value = item.system_unit || '';
        document.getElementById('edit_system_unit_sn').value = item.system_unit_sn || '';
        document.getElementById('edit_monitor').value = item.monitor || '';
        document.getElementById('edit_monitor_sn').value = item.monitor_sn || '';
        document.getElementById('edit_mouse').value = item.mouse || '';
        document.getElementById('edit_mouse_sn').value = item.mouse_sn || '';
        document.getElementById('edit_keyboard').value = item.keyboard || '';
        document.getElementById('edit_keyboard_sn').value = item.keyboard_sn || '';
        // Multi-select: Printer
        setMultiSelectValues('edit-printer-dropdown', 'edit-printer-label', 'edit-printer-cb', item.printer || '', '-- Select Printer(s) --');
        document.getElementById('edit_printer_sn').value = item.printer_sn || '';
        // Multi-select: Scanner
        setMultiSelectValues('edit-scanner-dropdown', 'edit-scanner-label', 'edit-scanner-cb', item.scanner || '', '-- Select Scanner(s) --');
        document.getElementById('edit_scanner_sn').value = item.scanner_sn || '';
        // Multi-select: AVR/UPS
        setMultiSelectValues('edit-avr-dropdown', 'edit-avr-label', 'edit-avr-cb', item.avr_ups || '', '-- Select AVR/UPS --');
        document.getElementById('edit_avr_ups_sn').value = item.avr_ups_sn || '';
        document.getElementById('edit_processor').value = item.processor || '';
        document.getElementById('edit_memory').value = item.memory || '';
        document.getElementById('edit_storage').value = item.storage || '';
        document.getElementById('edit_os').value = item.os || '';
        document.getElementById('edit_os_product_key').value = item.os_product_key || item.ms_office_key || '';
        document.getElementById('edit_license').value = item.license || 'N';
        document.getElementById('edit_microsoft_office').value = item.microsoft_office || '';
        document.getElementById('edit_ms_office_email').value = item.ms_office_email || '';
        document.getElementById('edit_ip_address').value = item.ip_address || '';
        document.getElementById('edit_mac_address').value = item.mac_address || '';
        document.getElementById('edit_endpoint_secure').value = item.endpoint_secure || 'N';
        document.getElementById('edit_firewall').value = item.firewall || 'N';
        document.getElementById('edit_checked_by').value = item.checked_by || '';
        document.getElementById('edit_encoded_by').value = item.encoded_by || '';
        document.getElementById('edit_remarks').value = item.remarks || '';
        document.getElementById('edit_remarks_date').value = item.remarks_date ? item.remarks_date.substring(0, 10) : '';
        toggleModal('editComputerModal');
    }

    // Live AJAX Search
    function attachSearchListener() {
        const searchInput = document.getElementById('searchInput');
        if (!searchInput) return;

        let debounceTimer;

        searchInput.addEventListener('input', function (e) {
            const searchTerm = e.target.value;

            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                if (searchTerm.trim() === '') {
                    // Empty search → redirect to clear results properly
                    const searchParams = new URLSearchParams(window.location.search);
                    searchParams.delete('search');
                    searchParams.set('page', '1');
                    window.location.href = '?' + searchParams.toString();
                } else {
                    fetchResults(searchTerm);
                }
            }, 300); // 300ms debounce
        });

        // Escape key clears the search box and resets view
        searchInput.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && this.value !== '') {
                this.value = '';
                const searchParams = new URLSearchParams(window.location.search);
                searchParams.delete('search');
                searchParams.set('page', '1');
                window.location.href = '?' + searchParams.toString();
            }
        });
    }

    attachSearchListener();

    function fetchResults(term) {
        // Get the container dynamically every time because it might have been replaced
        const container = document.getElementById('computer-table-container');
        if (!container) return; // Guard clause

        // Add opacity to indicate loading
        container.classList.add('opacity-50');

        // Construct URL with current filter states
        // Construct URL cleanly
        const currentUrl = new URL(window.location.protocol + '//' + window.location.host + window.location.pathname);
        const searchParams = new URLSearchParams(window.location.search);

        searchParams.set('search', term);
        searchParams.set('page', '1');
        searchParams.set('ajax', '1');

        const fetchUrl = currentUrl.pathname + '?' + searchParams.toString();

        fetch(fetchUrl)
            .then(response => {
                if (!response.ok) throw new Error('Network response was not ok');
                return response.text();
            })
            .then(html => {
                if (html.includes('<!DOCTYPE html>')) {
                    console.warn('Full page received. Reloading...');
                    // window.location.reload(); 
                    return;
                }

                // We need to re-query the container just in case
                const currentContainer = document.getElementById('computer-table-container');
                if (currentContainer) {
                    currentContainer.outerHTML = html;
                }

                // Update Browser URL without reloading
                searchParams.delete('ajax');
                window.history.replaceState({}, '', '?' + searchParams.toString());
            })
            .catch(err => {
                console.error('Search failed', err);
                const currentContainer = document.getElementById('computer-table-container');
                if (currentContainer) currentContainer.classList.remove('opacity-50');
            });
    }

    function toggleAll(source) {
        const checkboxes = document.querySelectorAll('.item-checkbox');
        for (let i = 0; i < checkboxes.length; i++) {
            checkboxes[i].checked = source.checked;
        }
    }

    function printData() {
        const checkboxes = document.querySelectorAll('.item-checkbox:checked');
        const rows = document.querySelectorAll('tbody tr');

        // Use filtering only if SOME but NOT ALL are selected
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

    function exportSelected() {
        const checkboxes = document.querySelectorAll('.item-checkbox:checked');
        const selectedIds = Array.from(checkboxes).map(cb => cb.value);

        if (selectedIds.length > 0) {
            // Export selected
            window.location.href = '?export=csv&ids=' + selectedIds.join(',');
        } else {
            // Export all (default behavior)
            // Optional: Ask for confirmation "No items selected. Export all?"
            if (confirm('No items selected. Do you want to export ALL computers?')) {
                window.location.href = '?export=csv';
            }
        }
    }

    // =============================================
    // Multi-Select Dropdown Helpers
    // =============================================

    function toggleMultiSelect(dropdownId) {
        const dd = document.getElementById(dropdownId);
        if (!dd) return;
        const isOpen = dd.classList.contains('open');
        // Close all open dropdowns first
        document.querySelectorAll('.multi-select-dropdown.open').forEach(d => d.classList.remove('open'));
        if (!isOpen) dd.classList.add('open');
    }

    // Update label text when checkboxes change
    function updateMultiSelectLabel(dropdownId, labelId, placeholder) {
        const dd = document.getElementById(dropdownId);
        if (!dd) return;
        const checked = Array.from(dd.querySelectorAll('input[type=checkbox]:checked')).map(cb => cb.value);
        const label = document.getElementById(labelId) || dd.querySelector('.multi-select-label');
        if (label) label.textContent = checked.length > 0 ? checked.join(', ') : placeholder;
    }

    // Attach change listeners to multi-select dropdowns (for Add modal)
    document.querySelectorAll('.multi-select-dropdown').forEach(dd => {
        dd.querySelectorAll('input[type=checkbox]').forEach(cb => {
            cb.addEventListener('change', () => {
                const label = dd.querySelector('.multi-select-label');
                const checked = Array.from(dd.querySelectorAll('input[type=checkbox]:checked')).map(c => c.value);
                if (label) label.textContent = checked.length > 0 ? checked.join(', ') : label.dataset.placeholder || '--';
            });
        });
    });

    // Set initial placeholder text
    document.querySelectorAll('.multi-select-dropdown').forEach(dd => {
        const label = dd.querySelector('.multi-select-label');
        if (label) label.dataset.placeholder = label.textContent;
    });

    // Set multi-select values from a comma-separated string (for Edit modal)
    function setMultiSelectValues(dropdownId, labelId, cbClass, valueStr, placeholder) {
        const dd = document.getElementById(dropdownId);
        if (!dd) return;
        const values = valueStr ? valueStr.split(',').map(v => v.trim()).filter(Boolean) : [];
        dd.querySelectorAll('input[type=checkbox]').forEach(cb => {
            cb.checked = values.includes(cb.value);
        });
        const label = labelId ? document.getElementById(labelId) : dd.querySelector('.multi-select-label');
        if (label) label.textContent = values.length > 0 ? values.join(', ') : placeholder;
    }

    // Close dropdowns when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.multi-select-dropdown')) {
            document.querySelectorAll('.multi-select-dropdown.open').forEach(d => d.classList.remove('open'));
        }
    });

    // Wire up change events for edit-modal checkboxes
    ['printer', 'scanner', 'avr'].forEach(type => {
        const dd = document.getElementById('edit-' + type + '-dropdown');
        if (!dd) return;
        dd.querySelectorAll('input[type=checkbox]').forEach(cb => {
            cb.addEventListener('change', () => {
                const label = document.getElementById('edit-' + type + '-label');
                const placeholder = type === 'avr' ? '-- Select AVR/UPS --' : (type === 'printer' ? '-- Select Printer(s) --' : '-- Select Scanner(s) --');
                const checked = Array.from(dd.querySelectorAll('input[type=checkbox]:checked')).map(c => c.value);
                if (label) label.textContent = checked.length > 0 ? checked.join(', ') : placeholder;
            });
        });
    });

</script>


<!-- Print Styles -->
<style>
    @media print {
        @page {
            size: landscape;
            margin: 10mm;
        }

        body {
            zoom: 75%;
            /* Shrink to fit */
        }

        /* Hide logic for selective printing */
        body.print-filtered tbody tr:not(.print-row-selected) {
            display: none !important;
        }

        /* Hide specific no-print elements (checkboxes) */
        .no-print {
            display: none !important;
        }

        /* Hide first column (checkboxes) - Legacy fallback */
        th:first-child,
        td:first-child {
            display: none !important;
        }

        /* Hide sidebar */
        aside,
        .sidebar {
            display: none !important;
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
        .mb-6.flex.gap-3,
        #search-controls {
            display: none !important;
        }

        /* Hide action buttons in table */
        td:last-child,
        th:last-child {
            display: none !important;
        }

        /* Hide bottom pagination */
        .px-6.py-4.border-t {
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

        /* Show only main content */
        body {
            background: white !important;
            height: auto !important;
            overflow: visible !important;
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

<!-- Multi-Select Dropdown Styles -->
<style>
    /* Multi-Select Dropdown */
    .multi-select-dropdown {
        position: relative;
        width: 100%;
    }
    .multi-select-trigger {
        display: flex;
        align-items: center;
        justify-content: space-between;
        width: 100%;
        background: rgb(248 250 252);
        border: 1px solid rgb(226 232 240);
        color: rgb(15 23 42);
        border-radius: 0.75rem;
        padding: 0.625rem 1rem;
        cursor: pointer;
        user-select: none;
        font-size: 0.875rem;
        min-height: 44px;
    }
    html.dark .multi-select-trigger {
        background: #101622;
        border-color: #232b3d;
        color: white;
    }
    .multi-select-label {
        flex: 1;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        font-size: 0.8rem;
    }
    .multi-select-arrow {
        font-size: 18px;
        color: #94a3b8;
        transition: transform 0.2s;
        flex-shrink: 0;
    }
    .multi-select-dropdown.open .multi-select-arrow {
        transform: rotate(180deg);
    }
    .multi-select-panel {
        display: none;
        position: absolute;
        top: calc(100% + 4px);
        left: 0;
        right: 0;
        background: white;
        border: 1px solid rgb(226 232 240);
        border-radius: 0.75rem;
        overflow-y: auto;
        max-height: 200px;
        z-index: 9999;
        box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        padding: 4px 0;
    }
    html.dark .multi-select-panel {
        background: #1a2130;
        border-color: #232b3d;
    }
    .multi-select-dropdown.open .multi-select-panel {
        display: block;
    }
    .multi-select-option {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 6px 12px;
        cursor: pointer;
        font-size: 0.775rem;
        color: rgb(51 65 85);
        transition: background 0.1s;
    }
    html.dark .multi-select-option {
        color: rgb(203 213 225);
    }
    .multi-select-option:hover {
        background: rgb(241 245 249);
    }
    html.dark .multi-select-option:hover {
        background: rgba(255,255,255,0.05);
    }
    .multi-select-option input[type=checkbox] {
        accent-color: var(--color-primary, #6366f1);
        width: 13px;
        height: 13px;
        flex-shrink: 0;
    }
</style>







/* Sticky Columns CSS for Module Tables */
<style>
    /* First column: Checkbox - sticky */
    thead tr th:nth-child(1),
    tbody tr td:nth-child(1) {
        position: sticky !important;
        left: 0;
        z-index: 20;
        background-color: white;
    }

    html.dark thead tr th:nth-child(1),
    html.dark tbody tr td:nth-child(1) {
        background-color: #1a2130;
    }
</style>

<?php require_once '../../includes/footer.php'; ?>
