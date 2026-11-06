<?php
require_once '../../config.php';
requireLogin();

// â”€â”€ Module config â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$MODULE_TABLE  = 'computers_doh_jbl_cmc';
$MODULE_LABEL  = 'DOH-JBL-CMC';
$MODULE_SLUG   = 'doh_jbl_cmc';
$MODULE_PATH   = BASE_URL . '/modules/doh_jbl_cmc/';

$success_msg = $error_msg = '';

// â”€â”€ Excel Export â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    $where_sql = '1=1'; $params = [];
    if (isset($_GET['ids']) && !empty($_GET['ids'])) {
        $ids = array_filter(explode(',', $_GET['ids']), 'is_numeric');
        if (!empty($ids)) { $placeholders = implode(',', array_fill(0, count($ids), '?')); $where_sql = "id IN ($placeholders)"; $params = array_values($ids); }
    }
    $stmt = $pdo->prepare("SELECT * FROM $MODULE_TABLE WHERE $where_sql ORDER BY id DESC");
    $stmt->execute($params);
    $items = $stmt->fetchAll();

    $xmlEsc = fn($v) => htmlspecialchars((string)$v, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    $sharedStrings = [];
    $ssi = function(string $s) use (&$sharedStrings): int {
        $key = array_search($s, $sharedStrings, true);
        if ($key === false) { $sharedStrings[] = $s; $key = count($sharedStrings) - 1; }
        return $key;
    };

    $headers = ['Department','End User','MR/PAR','Status','Control Number','System Unit','System Unit S/N','Monitor','Monitor S/N','Mouse','Mouse S/N','Keyboard','Keyboard S/N','Printer','Printer S/N','Scanner','Scanner S/N','AVR/UPS','AVR/UPS S/N','Processor','Memory','Storage','OS','OS Product Key','License','Microsoft Office','MS Office Email','IP Address','MAC Address','Endpoint Secure','Firewall','Checked By','Encoded By','Remarks'];
    $colLetters = [];
    for ($i = 0; $i < count($headers); $i++) { $colLetters[] = $i < 26 ? chr(65+$i) : 'A'.chr(65+$i-26); }

    $rows = [];
    $headerRow = []; foreach ($headers as $h) { $headerRow[] = ['t'=>'s','v'=>$ssi($h)]; }
    $rows[] = $headerRow;
    foreach ($items as $item) {
        $rows[] = [
            ['t'=>'s','v'=>$ssi($item['department']       ?? '')],
            ['t'=>'s','v'=>$ssi($item['end_user']         ?? '')],
            ['t'=>'s','v'=>$ssi($item['mr_par']           ?? '')],
            ['t'=>'s','v'=>$ssi($item['status']           ?? '')],
            ['t'=>'s','v'=>$ssi($item['control_number']   ?? '')],
            ['t'=>'s','v'=>$ssi($item['system_unit']      ?? '')],
            ['t'=>'s','v'=>$ssi($item['system_unit_sn']   ?? '')],
            ['t'=>'s','v'=>$ssi($item['monitor']          ?? '')],
            ['t'=>'s','v'=>$ssi($item['monitor_sn']       ?? '')],
            ['t'=>'s','v'=>$ssi($item['mouse']            ?? '')],
            ['t'=>'s','v'=>$ssi($item['mouse_sn']         ?? '')],
            ['t'=>'s','v'=>$ssi($item['keyboard']         ?? '')],
            ['t'=>'s','v'=>$ssi($item['keyboard_sn']      ?? '')],
            ['t'=>'s','v'=>$ssi($item['printer']          ?? '')],
            ['t'=>'s','v'=>$ssi($item['printer_sn']       ?? '')],
            ['t'=>'s','v'=>$ssi($item['scanner']          ?? '')],
            ['t'=>'s','v'=>$ssi($item['scanner_sn']       ?? '')],
            ['t'=>'s','v'=>$ssi($item['avr_ups']          ?? '')],
            ['t'=>'s','v'=>$ssi($item['avr_ups_sn']       ?? '')],
            ['t'=>'s','v'=>$ssi($item['processor']        ?? '')],
            ['t'=>'s','v'=>$ssi($item['memory']           ?? '')],
            ['t'=>'s','v'=>$ssi($item['storage']          ?? '')],
            ['t'=>'s','v'=>$ssi($item['os']               ?? '')],
            ['t'=>'s','v'=>$ssi($item['os_product_key']   ?? '')],
            ['t'=>'s','v'=>$ssi($item['license']          ?? '')],
            ['t'=>'s','v'=>$ssi($item['microsoft_office'] ?? '')],
            ['t'=>'s','v'=>$ssi($item['ms_office_email']  ?? '')],
            ['t'=>'s','v'=>$ssi($item['ip_address']       ?? '')],
            ['t'=>'s','v'=>$ssi($item['mac_address']      ?? '')],
            ['t'=>'s','v'=>$ssi($item['endpoint_secure']  ?? 'N')],
            ['t'=>'s','v'=>$ssi($item['firewall']         ?? 'N')],
            ['t'=>'s','v'=>$ssi($item['checked_by']       ?? '')],
            ['t'=>'s','v'=>$ssi($item['encoded_by']       ?? '')],
            ['t'=>'s','v'=>$ssi($item['remarks']          ?? '')],
        ];
    }

    $sheetXml  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
    $sheetXml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
    foreach ($rows as $ri => $row) {
        $rIdx = $ri + 1;
        $sheetXml .= "<row r=\"$rIdx\">";
        foreach ($row as $ci => $cell) {
            $ref = $colLetters[$ci] . $rIdx;
            $sheetXml .= $cell['t'] === 's' ? "<c r=\"$ref\" t=\"s\"><v>{$cell['v']}</v></c>" : "<c r=\"$ref\"><v>{$cell['v']}</v></c>";
        }
        $sheetXml .= '</row>';
    }
    $sheetXml .= '</sheetData></worksheet>';

    $ssXml  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
    $ssXml .= '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="'.count($sharedStrings).'" uniqueCount="'.count($sharedStrings).'">';
    foreach ($sharedStrings as $s) { $ssXml .= '<si><t xml:space="preserve">'.$xmlEsc($s).'</t></si>'; }
    $ssXml .= '</sst>';

    $stylesXml  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts><fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/></cellXfs></styleSheet>';
    $workbookXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="'.$MODULE_LABEL.'" sheetId="1" r:id="rId1"/></sheets></workbook>';
    $wbRels  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>';
    $rootRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>';
    $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>';

    $tmpFile = tempnam(sys_get_temp_dir(), $MODULE_SLUG.'_') . '.xlsx';
    $zip = new ZipArchive();
    $zip->open($tmpFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('[Content_Types].xml', $contentTypes);
    $zip->addFromString('_rels/.rels', $rootRels);
    $zip->addFromString('xl/workbook.xml', $workbookXml);
    $zip->addFromString('xl/_rels/workbook.xml.rels', $wbRels);
    $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
    $zip->addFromString('xl/sharedStrings.xml', $ssXml);
    $zip->addFromString('xl/styles.xml', $stylesXml);
    $zip->close();

    $filename = $MODULE_SLUG . '_export_' . date('Y-m-d_His') . '.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    header('Content-Length: ' . filesize($tmpFile));
    readfile($tmpFile);
    unlink($tmpFile);
    exit;
}

// â”€â”€ CSV Export â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="'.$MODULE_SLUG.'_export_' . date('Y-m-d_His') . '.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Department','End User','MR/PAR','Status','Control Number','System Unit','System Unit S/N','Monitor','Monitor S/N','Mouse','Mouse S/N','Keyboard','Keyboard S/N','Printer','Printer S/N','Scanner','Scanner S/N','AVR/UPS','AVR/UPS S/N','Processor','Memory','Storage','OS','OS Product Key','License','Microsoft Office','MS Office Email','IP Address','MAC Address','Endpoint Secure','Firewall','Checked By','Encoded By','Remarks']);
    $where_sql = '1=1'; $params = [];
    if (isset($_GET['ids']) && !empty($_GET['ids'])) {
        $ids = array_filter(explode(',', $_GET['ids']), 'is_numeric');
        if (!empty($ids)) { $placeholders = implode(',', array_fill(0, count($ids), '?')); $where_sql = "id IN ($placeholders)"; $params = array_values($ids); }
    }
    $stmt = $pdo->prepare("SELECT * FROM $MODULE_TABLE WHERE $where_sql ORDER BY id DESC");
    $stmt->execute($params);
    while ($row = $stmt->fetch()) {
        fputcsv($output, [$row['department']??'',$row['end_user']??'',$row['mr_par']??'',$row['status']??'',$row['control_number']??'',$row['system_unit']??'',$row['system_unit_sn']??'',$row['monitor']??'',$row['monitor_sn']??'',$row['mouse']??'',$row['mouse_sn']??'',$row['keyboard']??'',$row['keyboard_sn']??'',$row['printer']??'',$row['printer_sn']??'',$row['scanner']??'',$row['scanner_sn']??'',$row['avr_ups']??'',$row['avr_ups_sn']??'',$row['processor']??'',$row['memory']??'',$row['storage']??'',$row['os']??'',$row['os_product_key']??'',$row['license']??'',$row['microsoft_office']??'',$row['ms_office_email']??'',$row['ip_address']??'',$row['mac_address']??'',$row['endpoint_secure']??'N',$row['firewall']??'N',$row['checked_by']??'',$row['encoded_by']??'',$row['remarks']??'']);
    }
    fclose($output);
    exit;
}

// ── CSV Template Download ─────────────────────────────────────────────────────
if (isset($_GET['download_template']) && $_GET['download_template'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="'.$MODULE_SLUG.'_template.csv"');
    $output = fopen('php://output', 'w');
    fputs($output, "\xEF\xBB\xBF");
    fputcsv($output, ['Department','End User','MR/PAR','Status','Control Number','System Unit','System Unit S/N','Monitor','Monitor S/N','Mouse','Mouse S/N','Keyboard','Keyboard S/N','Printer','Printer S/N','Scanner','Scanner S/N','AVR/UPS','AVR/UPS S/N','Processor','Memory','Storage','OS','OS Product Key','License','Microsoft Office','MS Office Email','IP Address','MAC Address','Endpoint Secure','Firewall','Checked By','Encoded By','Remarks']);
    fputcsv($output, [
        $MODULE_LABEL . ' - Main', 'Juan Dela Cruz', 'PAR-001', 'Working', $MODULE_SLUG . '-PC001', 'Dell OptiPlex 7090', 'SN12345678',
        'Dell 24"', 'MON1234', 'Logitech Optical', 'MS1234', 'Logitech USB', 'KB1234', 'Canon PIXMA G3010', 'PRN1234',
        'None', 'None', 'APC 650VA', 'UPS1234', 'Intel Core i5-11400', '16GB DDR4', '512GB SSD', 'Windows 11 Pro 64-bit',
        'XXXXX-XXXXX-XXXXX-XXXXX', 'Yes', 'Microsoft Office 2021', 'user@hospital.gov.ph', '192.168.1.50', '00:1A:2B:3C:4D:5E',
        'Athena EPP', 'Yes', 'IHOMS Staff', 'Admin', 'Active workstation'
    ]);
    fclose($output);
    exit;
}

// â”€â”€ CSRF â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $error_msg = "Security validation failed. Please refresh the page and try again.";
        $_POST['action'] = 'invalid';
    }
}

// ── Import CSV ────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (($_POST['action'] ?? '') === 'import_csv' || (isset($_FILES['csv_file']) && ($_POST['action'] ?? '') !== 'invalid'))) {
    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        $error_msg = 'Please upload a valid CSV file.';
    } else {
        $file_path = $_FILES['csv_file']['tmp_name'];
        if (($handle = fopen($file_path, 'r')) !== FALSE) {
            $bom = fread($handle, 3);
            if ($bom !== "\xEF\xBB\xBF") {
                rewind($handle);
            }

            $raw_headers = fgetcsv($handle);
            if ($raw_headers === FALSE) {
                $error_msg = 'The uploaded CSV file is empty.';
                fclose($handle);
            } else {
                $imported = 0;
                $updated = 0;
                $skipped = 0;

                $first_hdr = strtolower(trim($raw_headers[0] ?? ''));
                $is_new_format = ($first_hdr === 'building');

                $header_map = [];
                foreach ($raw_headers as $idx => $hdr) {
                    $header_map[$idx] = strtolower(trim(preg_replace('/[^a-zA-Z0-9]/', '', $hdr)));
                }

                $alias_to_col = [
                    'department' => 'department', 'dept' => 'department', 'location' => 'department',
                    'enduser' => 'end_user', 'user' => 'end_user', 'employee' => 'end_user',
                    'mrpar' => 'mr_par', 'mr' => 'mr_par', 'par' => 'mr_par', 'mrics' => 'mr_par',
                    'status' => 'status',
                    'controlnumber' => 'control_number', 'controlno' => 'control_number', 'controlid' => 'control_number',
                    'systemunit' => 'system_unit', 'brandmodel' => 'system_unit', 'brand' => 'system_unit', 'model' => 'system_unit',
                    'systemunitsn' => 'system_unit_sn', 'serialnumber' => 'system_unit_sn', 'sn' => 'system_unit_sn',
                    'monitor' => 'monitor',
                    'monitorsn' => 'monitor_sn',
                    'mouse' => 'mouse',
                    'mousesn' => 'mouse_sn',
                    'keyboard' => 'keyboard',
                    'keyboardsn' => 'keyboard_sn',
                    'printer' => 'printer',
                    'printersn' => 'printer_sn',
                    'scanner' => 'scanner',
                    'scannersn' => 'scanner_sn',
                    'avrups' => 'avr_ups', 'upsavr' => 'avr_ups', 'avr' => 'avr_ups', 'ups' => 'avr_ups',
                    'avrupssn' => 'avr_ups_sn', 'upsavrsn' => 'avr_ups_sn',
                    'processor' => 'processor', 'cpu' => 'processor',
                    'memory' => 'memory', 'ram' => 'memory',
                    'storage' => 'storage', 'hdd' => 'storage', 'ssd' => 'storage',
                    'os' => 'os', 'osversion' => 'os',
                    'osproductkey' => 'os_product_key', 'productkey' => 'os_product_key',
                    'license' => 'license',
                    'microsoftoffice' => 'microsoft_office', 'msoffice' => 'microsoft_office', 'office' => 'microsoft_office',
                    'msofficeemail' => 'ms_office_email', 'officeemail' => 'ms_office_email',
                    'ipaddress' => 'ip_address', 'ip' => 'ip_address',
                    'macaddress' => 'mac_address', 'mac' => 'mac_address',
                    'endpointsecure' => 'endpoint_secure', 'antivirus' => 'endpoint_secure',
                    'firewall' => 'firewall',
                    'checkedby' => 'checked_by',
                    'encodedby' => 'encoded_by',
                    'remarks' => 'remarks',
                    'remarksdate' => 'remarks_date'
                ];

                while (($row = fgetcsv($handle)) !== FALSE) {
                    $row = array_map(function($val) {
                        $val = trim((string)$val);
                        if (!mb_check_encoding($val, 'UTF-8')) {
                            return mb_convert_encoding($val, 'UTF-8', 'Windows-1252');
                        }
                        return $val;
                    }, $row);
                    if (empty(array_filter($row, fn($v) => $v !== ''))) continue;
                    $col_count = count($row);

                    $record = [];
                    if ($is_new_format && $col_count >= 30) {
                        $imp_bld = trim($row[0] ?? '');
                        $imp_flr = trim($row[1] ?? '');
                        $imp_dep = trim($row[2] ?? '');
                        $record['department'] = trim("$imp_bld $imp_flr $imp_dep");
                        $record['end_user'] = trim($row[3] ?? '');
                        $record['mr_par'] = trim($row[4] ?? '');
                        $record['control_number'] = trim($row[5] ?? '');
                        $record['system_unit'] = trim($row[6] ?? '');
                        $record['system_unit_sn'] = trim($row[7] ?? '');
                        $record['monitor'] = trim($row[8] ?? '');
                        $record['monitor_sn'] = trim($row[9] ?? '');
                        $record['mouse'] = trim($row[10] ?? '');
                        $record['mouse_sn'] = trim($row[11] ?? '');
                        $record['keyboard'] = trim($row[12] ?? '');
                        $record['keyboard_sn'] = trim($row[13] ?? '');
                        $record['printer'] = trim($row[14] ?? '');
                        $record['printer_sn'] = trim($row[15] ?? '');
                        $record['scanner'] = trim($row[16] ?? '');
                        $record['scanner_sn'] = trim($row[17] ?? '');
                        $record['avr_ups'] = trim($row[18] ?? '');
                        $record['avr_ups_sn'] = trim($row[19] ?? '');
                        $record['processor'] = trim($row[20] ?? '');
                        $record['memory'] = trim($row[21] ?? '');
                        $record['storage'] = trim($row[22] ?? '');
                        $record['os'] = trim($row[23] ?? '');
                        $record['os_product_key'] = trim($row[24] ?? '');
                        $record['license'] = trim($row[25] ?? 'N');
                        $record['microsoft_office'] = trim($row[26] ?? '');
                        $record['ms_office_email'] = trim($row[27] ?? '');
                        $record['ip_address'] = trim($row[28] ?? '');
                        $record['mac_address'] = trim($row[29] ?? '');
                        $record['endpoint_secure'] = trim($row[30] ?? 'N');
                        $has_fw = ($col_count >= 35);
                        $fw_off = $has_fw ? 1 : 0;
                        $record['firewall'] = $has_fw ? trim($row[31] ?? 'N') : 'N';
                        $record['checked_by'] = trim($row[31 + $fw_off] ?? '');
                        $record['encoded_by'] = trim($row[32 + $fw_off] ?? '');
                        $record['remarks'] = trim($row[33 + $fw_off] ?? '');
                        $record['status'] = 'Working';
                    } elseif (isset($header_map[4]) && $header_map[4] === 'controlnumber') {
                        $record['department'] = trim($row[0] ?? '');
                        $record['end_user'] = trim($row[1] ?? '');
                        $record['mr_par'] = trim($row[2] ?? '');
                        $record['status'] = trim($row[3] ?? 'Working');
                        $record['control_number'] = trim($row[4] ?? '');
                        $record['system_unit'] = trim($row[5] ?? '');
                        $record['system_unit_sn'] = trim($row[6] ?? '');
                        $record['monitor'] = trim($row[7] ?? '');
                        $record['monitor_sn'] = trim($row[8] ?? '');
                        $record['mouse'] = trim($row[9] ?? '');
                        $record['mouse_sn'] = trim($row[10] ?? '');
                        $record['keyboard'] = trim($row[11] ?? '');
                        $record['keyboard_sn'] = trim($row[12] ?? '');
                        $record['printer'] = trim($row[13] ?? '');
                        $record['printer_sn'] = trim($row[14] ?? '');
                        $record['scanner'] = trim($row[15] ?? '');
                        $record['scanner_sn'] = trim($row[16] ?? '');
                        $record['avr_ups'] = trim($row[17] ?? '');
                        $record['avr_ups_sn'] = trim($row[18] ?? '');
                        $record['processor'] = trim($row[19] ?? '');
                        $record['memory'] = trim($row[20] ?? '');
                        $record['storage'] = trim($row[21] ?? '');
                        $record['os'] = trim($row[22] ?? '');
                        $record['os_product_key'] = trim($row[23] ?? '');
                        $record['license'] = trim($row[24] ?? 'N');
                        $record['microsoft_office'] = trim($row[25] ?? '');
                        $record['ms_office_email'] = trim($row[26] ?? '');
                        $record['ip_address'] = trim($row[27] ?? '');
                        $record['mac_address'] = trim($row[28] ?? '');
                        $record['endpoint_secure'] = trim($row[29] ?? 'N');
                        $record['firewall'] = trim($row[30] ?? 'N');
                        $record['checked_by'] = trim($row[31] ?? '');
                        $record['encoded_by'] = trim($row[32] ?? '');
                        $record['remarks'] = trim($row[33] ?? '');
                    } else {
                        foreach ($row as $idx => $val) {
                            $h_key = $header_map[$idx] ?? '';
                            if (isset($alias_to_col[$h_key])) {
                                $record[$alias_to_col[$h_key]] = trim($val);
                            }
                        }
                    }

                    $ctrl = $record['control_number'] ?? '';
                    if (empty($ctrl)) {
                        continue;
                    }

                    $chk = $pdo->prepare("SELECT id FROM $MODULE_TABLE WHERE control_number = ? LIMIT 1");
                    $chk->execute([$ctrl]);
                    $existing_id = $chk->fetchColumn();

                    $st_val = !empty($record['status']) ? $record['status'] : 'Working';
                    $lic_val = (!empty($record['license']) && in_array(strtolower($record['license']), ['y','yes','1','true'])) ? 'Y' : 'N';
                    $ep_val = (!empty($record['endpoint_secure']) && in_array(strtolower($record['endpoint_secure']), ['y','yes','1','true'])) ? 'Y' : 'N';
                    $fw_val = (!empty($record['firewall']) && in_array(strtolower($record['firewall']), ['y','yes','1','true'])) ? 'Y' : 'N';

                    try {
                        if ($existing_id) {
                            $stmt = $pdo->prepare("
                                UPDATE $MODULE_TABLE SET
                                    department = COALESCE(NULLIF(?,''), department),
                                    end_user = COALESCE(NULLIF(?,''), end_user),
                                    mr_par = COALESCE(NULLIF(?,''), mr_par),
                                    status = ?,
                                    system_unit = COALESCE(NULLIF(?,''), system_unit),
                                    system_unit_sn = COALESCE(NULLIF(?,''), system_unit_sn),
                                    monitor = COALESCE(NULLIF(?,''), monitor),
                                    monitor_sn = COALESCE(NULLIF(?,''), monitor_sn),
                                    mouse = COALESCE(NULLIF(?,''), mouse),
                                    mouse_sn = COALESCE(NULLIF(?,''), mouse_sn),
                                    keyboard = COALESCE(NULLIF(?,''), keyboard),
                                    keyboard_sn = COALESCE(NULLIF(?,''), keyboard_sn),
                                    printer = COALESCE(NULLIF(?,''), printer),
                                    printer_sn = COALESCE(NULLIF(?,''), printer_sn),
                                    scanner = COALESCE(NULLIF(?,''), scanner),
                                    scanner_sn = COALESCE(NULLIF(?,''), scanner_sn),
                                    avr_ups = COALESCE(NULLIF(?,''), avr_ups),
                                    avr_ups_sn = COALESCE(NULLIF(?,''), avr_ups_sn),
                                    processor = COALESCE(NULLIF(?,''), processor),
                                    memory = COALESCE(NULLIF(?,''), memory),
                                    storage = COALESCE(NULLIF(?,''), storage),
                                    os = COALESCE(NULLIF(?,''), os),
                                    os_product_key = COALESCE(NULLIF(?,''), os_product_key),
                                    license = ?,
                                    microsoft_office = COALESCE(NULLIF(?,''), microsoft_office),
                                    ms_office_email = COALESCE(NULLIF(?,''), ms_office_email),
                                    ip_address = COALESCE(NULLIF(?,''), ip_address),
                                    mac_address = COALESCE(NULLIF(?,''), mac_address),
                                    endpoint_secure = ?,
                                    firewall = ?,
                                    checked_by = COALESCE(NULLIF(?,''), checked_by),
                                    encoded_by = COALESCE(NULLIF(?,''), encoded_by),
                                    remarks = COALESCE(NULLIF(?,''), remarks)
                                WHERE id = ?
                            ");
                            $stmt->execute([
                                $record['department'] ?? '',
                                $record['end_user'] ?? '',
                                $record['mr_par'] ?? '',
                                $st_val,
                                $record['system_unit'] ?? '',
                                $record['system_unit_sn'] ?? '',
                                $record['monitor'] ?? '',
                                $record['monitor_sn'] ?? '',
                                $record['mouse'] ?? '',
                                $record['mouse_sn'] ?? '',
                                $record['keyboard'] ?? '',
                                $record['keyboard_sn'] ?? '',
                                $record['printer'] ?? '',
                                $record['printer_sn'] ?? '',
                                $record['scanner'] ?? '',
                                $record['scanner_sn'] ?? '',
                                $record['avr_ups'] ?? '',
                                $record['avr_ups_sn'] ?? '',
                                $record['processor'] ?? '',
                                $record['memory'] ?? '',
                                $record['storage'] ?? '',
                                $record['os'] ?? '',
                                $record['os_product_key'] ?? '',
                                $lic_val,
                                $record['microsoft_office'] ?? '',
                                $record['ms_office_email'] ?? '',
                                $record['ip_address'] ?? '',
                                $record['mac_address'] ?? '',
                                $ep_val,
                                $fw_val,
                                $record['checked_by'] ?? '',
                                $record['encoded_by'] ?? '',
                                $record['remarks'] ?? '',
                                $existing_id
                            ]);
                            $updated++;
                        } else {
                            $stmt = $pdo->prepare("
                                INSERT INTO $MODULE_TABLE (
                                    department, end_user, mr_par, status, control_number, system_unit, system_unit_sn,
                                    monitor, monitor_sn, mouse, mouse_sn, keyboard, keyboard_sn, printer, printer_sn,
                                    scanner, scanner_sn, avr_ups, avr_ups_sn, processor, memory, storage, os,
                                    os_product_key, license, microsoft_office, ms_office_email, ip_address,
                                    mac_address, endpoint_secure, firewall, checked_by, encoded_by, remarks
                                ) VALUES (
                                    ?, ?, ?, ?, ?, ?, ?,
                                    ?, ?, ?, ?, ?, ?, ?, ?,
                                    ?, ?, ?, ?, ?, ?, ?, ?,
                                    ?, ?, ?, ?, ?,
                                    ?, ?, ?, ?, ?, ?
                                )
                            ");
                            $stmt->execute([
                                $record['department'] ?? '',
                                $record['end_user'] ?? '',
                                $record['mr_par'] ?? '',
                                $st_val,
                                $ctrl,
                                $record['system_unit'] ?? '',
                                $record['system_unit_sn'] ?? '',
                                $record['monitor'] ?? '',
                                $record['monitor_sn'] ?? '',
                                $record['mouse'] ?? '',
                                $record['mouse_sn'] ?? '',
                                $record['keyboard'] ?? '',
                                $record['keyboard_sn'] ?? '',
                                $record['printer'] ?? '',
                                $record['printer_sn'] ?? '',
                                $record['scanner'] ?? '',
                                $record['scanner_sn'] ?? '',
                                $record['avr_ups'] ?? '',
                                $record['avr_ups_sn'] ?? '',
                                $record['processor'] ?? '',
                                $record['memory'] ?? '',
                                $record['storage'] ?? '',
                                $record['os'] ?? '',
                                $record['os_product_key'] ?? '',
                                $lic_val,
                                $record['microsoft_office'] ?? '',
                                $record['ms_office_email'] ?? '',
                                $record['ip_address'] ?? '',
                                $record['mac_address'] ?? '',
                                $ep_val,
                                $fw_val,
                                $record['checked_by'] ?? '',
                                $record['encoded_by'] ?? '',
                                $record['remarks'] ?? ''
                            ]);
                            $imported++;
                        }

                        if (!empty($record['ip_address'])) {
                            syncSatelliteComputerIpToIpModule($pdo, $MODULE_SLUG, $record['ip_address'], $record['mac_address'] ?? '', $ctrl, $record['department'] ?? '', $record['department'] ?? '', null, $record['end_user'] ?? '');
                        }

                    } catch (Exception $e) {
                        $skipped++;
                    }
                }
                fclose($handle);

                $msg_parts = ["Imported $imported new computers"];
                if ($updated > 0) $msg_parts[] = "$updated existing computers updated";
                if ($skipped > 0) $msg_parts[] = "$skipped records skipped";
                $success_msg = implode(', ', $msg_parts) . '.';
                logAudit($pdo, 'import_csv', "[$MODULE_LABEL] Imported $imported new computers, updated $updated existing computers", 'computer');
            }
        } else {
            $error_msg = 'Unable to open uploaded CSV file.';
        }
    }
}

// â”€â”€ Add â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_computer') {
    $encoded_by = trim($_POST['encoded_by'] ?? '');
    $checked_by = trim($_POST['checked_by'] ?? '');
    if (empty($encoded_by) || empty($checked_by)) {
        $error_msg = "Error: 'Encoded By' and 'Checked By' are required fields.";
    } else {
        try {
            $printer_val = isset($_POST['printer']) && is_array($_POST['printer']) ? implode(', ', $_POST['printer']) : ($_POST['printer'] ?? '');
            $scanner_val = isset($_POST['scanner']) && is_array($_POST['scanner']) ? implode(', ', $_POST['scanner']) : ($_POST['scanner'] ?? '');
            $avr_ups_val = isset($_POST['avr_ups']) && is_array($_POST['avr_ups']) ? implode(', ', $_POST['avr_ups']) : ($_POST['avr_ups'] ?? '');
            $stmt = $pdo->prepare("INSERT INTO $MODULE_TABLE (department, end_user, mr_par, status, control_number, system_unit, system_unit_sn, monitor, monitor_sn, mouse, mouse_sn, keyboard, keyboard_sn, printer, printer_sn, scanner, scanner_sn, avr_ups, avr_ups_sn, processor, memory, storage, os, os_product_key, license, microsoft_office, ms_office_email, ip_address, mac_address, endpoint_secure, firewall, checked_by, encoded_by, remarks, remarks_date) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([
                $_POST['department'] ?? '',
                $_POST['end_user'] ?? '',
                $_POST['mr_par'] ?? '',
                $_POST['status'] ?? 'Working',
                $_POST['control_number'] ?? '',
                $_POST['system_unit'] ?? '',
                $_POST['system_unit_sn'] ?? '',
                $_POST['monitor'] ?? '',
                $_POST['monitor_sn'] ?? '',
                $_POST['mouse'] ?? '',
                $_POST['mouse_sn'] ?? '',
                $_POST['keyboard'] ?? '',
                $_POST['keyboard_sn'] ?? '',
                $printer_val,
                $_POST['printer_sn'] ?? '',
                $scanner_val,
                $_POST['scanner_sn'] ?? '',
                $avr_ups_val,
                $_POST['avr_ups_sn'] ?? '',
                $_POST['processor'] ?? '',
                $_POST['memory'] ?? '',
                $_POST['storage'] ?? '',
                $_POST['os'] ?? '',
                $_POST['os_product_key'] ?? '',
                $_POST['license'] ?? 'N',
                $_POST['microsoft_office'] ?? '',
                $_POST['ms_office_email'] ?? '',
                $_POST['ip_address'] ?? '',
                $_POST['mac_address'] ?? '',
                $_POST['endpoint_secure'] ?? 'N',
                $_POST['firewall'] ?? 'N',
                $checked_by,
                $encoded_by,
                $_POST['remarks'] ?? '',
                !empty($_POST['remarks_date']) ? $_POST['remarks_date'] : null,
            ]);
            logAudit($pdo, 'add_computer', "[$MODULE_LABEL] Added computer control#: " . ($_POST['control_number'] ?? ''), 'computer');
            syncSatelliteComputerIpToIpModule($pdo, $MODULE_SLUG, $_POST['ip_address'] ?? '', $_POST['mac_address'] ?? '', $_POST['control_number'] ?? '', $_POST['department'] ?? '', $_POST['department'] ?? '', null, $_POST['end_user'] ?? '');
            $success_msg = "Computer added successfully.";
        } catch (Exception $e) {
            $error_msg = "Error adding computer: " . $e->getMessage();
        }
    }
}

// â”€â”€ Edit â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit_computer') {
    $encoded_by = trim($_POST['encoded_by'] ?? '');
    $checked_by = trim($_POST['checked_by'] ?? '');
    if (empty($encoded_by) || empty($checked_by)) {
        $error_msg = "Error: 'Encoded By' and 'Checked By' are required fields.";
    } else {
        try {
            $snap = $pdo->prepare("SELECT ip_address, control_number FROM $MODULE_TABLE WHERE id = ?");
            $snap->execute([$_POST['id']]);
            $old_comp = $snap->fetch(PDO::FETCH_ASSOC) ?: [];
            $printer_val = isset($_POST['printer']) && is_array($_POST['printer']) ? implode(', ', $_POST['printer']) : ($_POST['printer'] ?? '');
            $scanner_val = isset($_POST['scanner']) && is_array($_POST['scanner']) ? implode(', ', $_POST['scanner']) : ($_POST['scanner'] ?? '');
            $avr_ups_val = isset($_POST['avr_ups']) && is_array($_POST['avr_ups']) ? implode(', ', $_POST['avr_ups']) : ($_POST['avr_ups'] ?? '');
            $stmt = $pdo->prepare("UPDATE $MODULE_TABLE SET department=?,end_user=?,mr_par=?,status=?,control_number=?,system_unit=?,system_unit_sn=?,monitor=?,monitor_sn=?,mouse=?,mouse_sn=?,keyboard=?,keyboard_sn=?,printer=?,printer_sn=?,scanner=?,scanner_sn=?,avr_ups=?,avr_ups_sn=?,processor=?,memory=?,storage=?,os=?,os_product_key=?,license=?,microsoft_office=?,ms_office_email=?,ip_address=?,mac_address=?,endpoint_secure=?,firewall=?,checked_by=?,encoded_by=?,remarks=?,remarks_date=? WHERE id=?");
            $stmt->execute([
                $_POST['department'] ?? '',
                $_POST['end_user'] ?? '',
                $_POST['mr_par'] ?? '',
                $_POST['status'] ?? 'Working',
                $_POST['control_number'] ?? '',
                $_POST['system_unit'] ?? '',
                $_POST['system_unit_sn'] ?? '',
                $_POST['monitor'] ?? '',
                $_POST['monitor_sn'] ?? '',
                $_POST['mouse'] ?? '',
                $_POST['mouse_sn'] ?? '',
                $_POST['keyboard'] ?? '',
                $_POST['keyboard_sn'] ?? '',
                $printer_val,
                $_POST['printer_sn'] ?? '',
                $scanner_val,
                $_POST['scanner_sn'] ?? '',
                $avr_ups_val,
                $_POST['avr_ups_sn'] ?? '',
                $_POST['processor'] ?? '',
                $_POST['memory'] ?? '',
                $_POST['storage'] ?? '',
                $_POST['os'] ?? '',
                $_POST['os_product_key'] ?? '',
                $_POST['license'] ?? 'N',
                $_POST['microsoft_office'] ?? '',
                $_POST['ms_office_email'] ?? '',
                $_POST['ip_address'] ?? '',
                $_POST['mac_address'] ?? '',
                $_POST['endpoint_secure'] ?? 'N',
                $_POST['firewall'] ?? 'N',
                $checked_by,
                $encoded_by,
                $_POST['remarks'] ?? '',
                !empty($_POST['remarks_date']) ? $_POST['remarks_date'] : null,
                $_POST['id'],
            ]);
            logAudit($pdo, 'update_computer', "[$MODULE_LABEL] Updated computer ID: " . ($_POST['id'] ?? '') . " control#: " . ($_POST['control_number'] ?? ''), 'computer', $_POST['id']);
            syncSatelliteComputerIpToIpModule($pdo, $MODULE_SLUG, $_POST['ip_address'] ?? '', $_POST['mac_address'] ?? '', $_POST['control_number'] ?? '', $_POST['department'] ?? '', $_POST['department'] ?? '', $old_comp['ip_address'] ?? null, $_POST['end_user'] ?? '');
            $success_msg = "Computer updated successfully.";
        } catch (Exception $e) {
            $error_msg = "Error updating computer: " . $e->getMessage();
        }
    }
}

// â”€â”€ Delete â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_computer') {
    try {
        $id = $_POST['id'];
        $del_snap = $pdo->prepare("SELECT ip_address, control_number FROM $MODULE_TABLE WHERE id = ?");
        $del_snap->execute([$id]);
        $del_comp = $del_snap->fetch(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("DELETE FROM $MODULE_TABLE WHERE id = ?");
        $stmt->execute([$id]);
        if ($del_comp && !empty($del_comp['ip_address'])) {
            releaseSatelliteComputerIp($pdo, $MODULE_SLUG, $del_comp['control_number'] ?? '', $del_comp['ip_address'] ?? '');
        }
        logAudit($pdo, 'delete_computer', "[$MODULE_LABEL] Deleted computer ID: $id", 'computer', $id);
        $success_msg = "Computer deleted successfully.";
    } catch (Exception $e) {
        $error_msg = "Error deleting computer: " . $e->getMessage();
    }
}

// â”€â”€ Fetch / Filters / Pagination â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$search_term      = $_GET['search']     ?? '';
$status_filter    = $_GET['status']     ?? 'All';
$sort_by          = $_GET['sort']       ?? 'id_desc';
$page             = max(1, (int)($_GET['page']  ?? 1));
$limit_param      = $_GET['limit']      ?? '15';
$limit            = ($limit_param === 'all') ? 999999 : (int)$limit_param;
$offset           = ($page - 1) * $limit;

$where_clauses = ['1=1'];
$params = [];

if ($search_term) {
    $where_clauses[] = "(control_number ILIKE ? OR end_user ILIKE ? OR department ILIKE ? OR ip_address ILIKE ? OR mac_address ILIKE ? OR system_unit ILIKE ? OR processor ILIKE ? OR mr_par ILIKE ? OR remarks ILIKE ?)";
    $sp = "%$search_term%";
    for ($i = 0; $i < 9; $i++) $params[] = $sp;
}
if ($status_filter !== 'All' && !empty($status_filter)) {
    $where_clauses[] = "status = ?";
    $params[] = $status_filter;
}

$where_sql = implode(' AND ', $where_clauses);
$order_by = match($sort_by) {
    'id_asc'    => 'id ASC',
    'user_asc'  => 'end_user ASC',
    'user_desc' => 'end_user DESC',
    'dept_asc'  => 'department ASC',
    'dept_desc' => 'department DESC',
    default     => 'id DESC',
};

$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM $MODULE_TABLE WHERE $where_sql");
$count_stmt->execute($params);
$total_items = $count_stmt->fetchColumn();
$total_pages = ceil($total_items / $limit);
$page_params = "&search=" . urlencode($search_term) . "&status=" . urlencode($status_filter) . "&sort=" . urlencode($sort_by) . "&limit=" . urlencode($limit_param);

$stmt = $pdo->prepare("SELECT * FROM $MODULE_TABLE WHERE $where_sql ORDER BY $order_by LIMIT $limit OFFSET $offset");
$stmt->execute($params);
$computers = $stmt->fetchAll();

$total_computers = (int)($pdo->query("SELECT COUNT(*) FROM $MODULE_TABLE")->fetchColumn() ?: 0);
$working_count = (int)($pdo->query("SELECT COUNT(*) FROM $MODULE_TABLE WHERE status = 'Working' OR status IS NULL OR status = ''")->fetchColumn() ?: 0);
$maint_count = (int)($pdo->query("SELECT COUNT(*) FROM $MODULE_TABLE WHERE status = 'Maintenance'")->fetchColumn() ?: 0);
$with_ip_count = (int)($pdo->query("SELECT COUNT(*) FROM $MODULE_TABLE WHERE ip_address IS NOT NULL AND ip_address != ''")->fetchColumn() ?: 0);

$departments = $pdo->query("SELECT DISTINCT department FROM $MODULE_TABLE WHERE department IS NOT NULL AND department != '' ORDER BY department")->fetchAll(PDO::FETCH_COLUMN);

$staff_users = $pdo->query("SELECT full_name FROM users WHERE username NOT IN ('Editor_Test','Viewer_Test','admin') AND full_name IS NOT NULL AND TRIM(full_name) != '' ORDER BY full_name")->fetchAll(PDO::FETCH_COLUMN);

$printer_options = ['MAGICCARD D ID','CANON PIXMA G1010','CANON PIXMA G2010','CANON PIXMA G3010','CANON LBP 2900','CANON LBP 6030','EPSON LX-310','EPSON LQ-310','EPSON L120','EPSON L121','EPSON L220','EPSON L360','EPSON L565','EPSON L3110','EPSON L3210','EPSON L4150','EPSON L5190','EPSON L5290','EPSON L8050','EPSON L805','EPSON L15150','EPSON L18150','HP LASERJET M428FDN','HP SMART TANK 500','BROTHER DCP-T420W','BROTHER DCP-T700W','BROTHER MFC-T810W','ZEBRA GC420T'];
$scanner_options = ['HP SCANJET PRO 4500 FNW1','HP SCANJET PRO 4600 FNW1','HP SCANJET PRO N4000 SNW1','HP SCANJET PRO N4000 S4','HP SCANJET PRO 7000 S3','HP SCANJET PRO 3000 S4','HP SCANJET PRO 3000 S3','HP SCANJET PRO 2000 S2','HP LASERJET PRO N4500','HP SMART TANK 500','CANON LIDE 110','CANON PIXMA G1010','CANON PIXMA G2010','CANON PIXMA G3010','EPSON ES-60W','EPSON L565','EPSON L3110','EPSON L3210','EPSON L4150','EPSON L5190','EPSON L5290','EPSON L8050','EPSON L15150','EPSON L18150','BROTHER DCP-T420W','BROTHER DCP-T700W','BROTHER MFC-T810W'];
$avr_ups_options = ['APC','AWD','DELKIN AVR','EATON','ECO POWER','GENERIC AVR','ICUTE AVR','INTEX','KEBOS','KSTAR','LOGIC SUPREME','MICROPULSE','PANTHER AVR','PAWERGUARD PLUS','PROTEC AVR','SECURE AVR','SOLOMEC','STAVOL AVR'];

$page_title = "$MODULE_LABEL Computer Inventory";
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
?>

<div class="p-4 md:p-8 flex-1 overflow-y-auto custom-scrollbar relative z-10 bg-slate-50 dark:bg-background-dark">
    <header class="-mt-4 -mx-4 md:-mt-8 md:-mx-8 px-4 md:px-8 pt-4 md:pt-8 pb-4 mb-6 flex flex-col gap-3 sticky top-0 bg-slate-50 dark:bg-background-dark z-20 border-b border-slate-200 dark:border-[#232b3d]">
        <?php 
        $active_comp_tab = 'doh_jbl_cmc';
        require __DIR__ . '/../../includes/satellite_computer_tabs.php';
        ?>
        <div class="flex flex-col md:flex-row items-start md:items-end justify-between gap-4 w-full">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <span class="px-2 py-0.5 rounded text-[11px] font-bold tracking-wider uppercase bg-amber-500/10 text-amber-500 border border-amber-500/20">Satellite Computer</span>
                </div>
                <h2 class="text-2xl font-bold text-slate-900 dark:text-white"><?= $MODULE_LABEL ?> Computer Inventory</h2>
                <p class="text-xs text-slate-400 mt-0.5">Satellite computer inventory for DOH-JBL Children's Medical Center.</p>
            </div>
            <div class="no-print flex items-center gap-2 flex-wrap">
                <button onclick="toggleModal('addComputerModal')" class="flex items-center gap-1.5 px-3.5 h-9 rounded-lg bg-primary hover:bg-primary-hover text-white text-xs font-bold transition-all shadow-sm">
                    <span class="material-symbols-outlined text-[16px]">add</span> Add Computer
                </button>
                <button onclick="toggleModal('importModal')" class="flex items-center gap-1.5 px-3 h-9 rounded-lg bg-amber-500 hover:bg-amber-600 text-white text-xs font-bold transition-all shadow-sm" title="Import computers from CSV">
                    <span class="material-symbols-outlined text-[16px]">upload_file</span> Import CSV
                </button>
                <a href="?export=excel" class="flex items-center gap-1.5 px-3 h-9 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold transition-all shadow-sm">
                    <span class="material-symbols-outlined text-[16px]">table_view</span> Export Excel
                </a>
                <button onclick="exportSelected()" class="flex items-center gap-1.5 px-3 h-9 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold transition-all shadow-sm">
                    <span class="material-symbols-outlined text-[16px]">download</span> Export CSV
                </button>
                <button onclick="printData()" class="flex items-center gap-1.5 px-3 h-9 rounded-lg bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-[#232b3d] text-xs font-bold transition-all shadow-sm">
                    <span class="material-symbols-outlined text-[16px]">print</span> Print
                </button>
            </div>
        </div>
    </header>

    <?php if ($success_msg): ?>
        <div class="mb-4 p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-500 text-xs font-semibold flex items-center justify-between no-print">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-sm">check_circle</span>
                <?= htmlspecialchars($success_msg) ?>
            </div>
            <button onclick="this.parentElement.remove()" class="text-emerald-500/60 hover:text-emerald-500"><span class="material-symbols-outlined text-sm">close</span></button>
        </div>
    <?php endif; ?>
    <?php if ($error_msg): ?>
        <div class="mb-4 p-3 rounded-xl bg-red-500/10 border border-red-500/20 text-red-500 text-xs font-semibold flex items-center justify-between no-print">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-sm">error</span>
                <?= htmlspecialchars($error_msg) ?>
            </div>
            <button onclick="this.parentElement.remove()" class="text-red-500/60 hover:text-red-500"><span class="material-symbols-outlined text-sm">close</span></button>
        </div>
    <?php endif; ?>

    <!-- Stats Cards Grid -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 mb-6 no-print">
        <div class="bg-white dark:bg-[#161d2b] p-4 rounded-xl border border-slate-200 dark:border-[#232b3d] shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Total Computers</span>
                <span class="material-symbols-outlined text-primary text-base">computer</span>
            </div>
            <p class="text-2xl font-bold mt-1 text-slate-900 dark:text-white"><?= number_format($total_computers) ?></p>
            <span class="text-[10px] text-slate-400">Satellite inventory pool</span>
        </div>
        <div class="bg-white dark:bg-[#161d2b] p-4 rounded-xl border border-slate-200 dark:border-[#232b3d] shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Working</span>
                <span class="material-symbols-outlined text-emerald-500 text-base">check_circle</span>
            </div>
            <p class="text-2xl font-bold mt-1 text-emerald-500"><?= number_format($working_count) ?></p>
            <span class="text-[10px] text-slate-400">Operational units</span>
        </div>
        <div class="bg-white dark:bg-[#161d2b] p-4 rounded-xl border border-slate-200 dark:border-[#232b3d] shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Maintenance</span>
                <span class="material-symbols-outlined text-amber-500 text-base">build</span>
            </div>
            <p class="text-2xl font-bold mt-1 text-amber-500"><?= number_format($maint_count) ?></p>
            <span class="text-[10px] text-slate-400">Under repair / check</span>
        </div>
        <div class="bg-white dark:bg-[#161d2b] p-4 rounded-xl border border-slate-200 dark:border-[#232b3d] shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Departments</span>
                <span class="material-symbols-outlined text-purple-500 text-base">corporate_fare</span>
            </div>
            <p class="text-2xl font-bold mt-1 text-purple-400"><?= number_format(count($departments)) ?></p>
            <span class="text-[10px] text-slate-400">Active areas</span>
        </div>
        <div class="bg-white dark:bg-[#161d2b] p-4 rounded-xl border border-slate-200 dark:border-[#232b3d] shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">IP Configured</span>
                <span class="material-symbols-outlined text-blue-500 text-base">lan</span>
            </div>
            <p class="text-2xl font-bold mt-1 text-blue-400"><?= number_format($with_ip_count) ?></p>
            <span class="text-[10px] text-slate-400">Network mapped</span>
        </div>
    </div>

    <!-- Filter / Control Bar -->
    <div class="bg-white dark:bg-[#161d2b] p-3 rounded-xl border border-slate-200 dark:border-[#232b3d] shadow-sm mb-4 no-print">
        <form method="GET" class="flex flex-wrap items-center gap-2">
            <div class="relative flex-1 min-w-[200px]">
                <span class="material-symbols-outlined absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400 text-sm">search</span>
                <input type="text" name="search" value="<?= htmlspecialchars($search_term) ?>" placeholder="Search user, control#, IP..." class="w-full pl-9 pr-3 py-1.5 text-xs rounded-lg bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:border-primary">
            </div>
            <select name="status" class="px-2.5 py-1.5 text-xs rounded-lg bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-700 dark:text-slate-300 focus:outline-none focus:border-primary">
                <option value="All" <?= $status_filter === 'All' ? 'selected' : '' ?>>All Status</option>
                <option value="Working"     <?= $status_filter === 'Working'     ? 'selected' : '' ?>>Working</option>
                <option value="Maintenance" <?= $status_filter === 'Maintenance' ? 'selected' : '' ?>>Maintenance</option>
                <option value="Transferred" <?= $status_filter === 'Transferred' ? 'selected' : '' ?>>Transferred</option>
                <option value="Condemned"   <?= $status_filter === 'Condemned'   ? 'selected' : '' ?>>Condemned</option>
            </select>
            <select name="sort" class="px-2.5 py-1.5 text-xs rounded-lg bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-700 dark:text-slate-300 focus:outline-none focus:border-primary">
                <option value="id_desc"  <?= $sort_by === 'id_desc'  ? 'selected' : '' ?>>Newest First</option>
                <option value="id_asc"   <?= $sort_by === 'id_asc'   ? 'selected' : '' ?>>Oldest First</option>
                <option value="user_asc" <?= $sort_by === 'user_asc' ? 'selected' : '' ?>>User (A-Z)</option>
                <option value="dept_asc" <?= $sort_by === 'dept_asc' ? 'selected' : '' ?>>Location (A-Z)</option>
            </select>
            <select name="limit" class="px-2.5 py-1.5 text-xs rounded-lg bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-700 dark:text-slate-300 focus:outline-none focus:border-primary">
                <option value="15"  <?= $limit_param == '15'  ? 'selected' : '' ?>>15</option>
                <option value="50"  <?= $limit_param == '50'  ? 'selected' : '' ?>>50</option>
                <option value="100" <?= $limit_param == '100' ? 'selected' : '' ?>>100</option>
                <option value="all" <?= $limit_param == 'all' ? 'selected' : '' ?>>All</option>
            </select>
            <button type="submit" class="px-3.5 py-1.5 text-xs font-bold rounded-lg bg-primary hover:bg-primary-hover text-white transition-all shadow-sm">
                Filter
            </button>
            <?php if ($search_term || $status_filter !== 'All' || $sort_by !== 'id_desc' || $limit_param !== '15'): ?>
                <a href="index.php" class="px-2.5 py-1.5 text-xs font-medium text-red-500 hover:text-red-600 transition-colors">
                    Clear
                </a>
            <?php endif; ?>

            <?php if ($total_pages > 1): ?>
                <div class="ml-auto flex items-center gap-1.5 text-xs text-slate-500 dark:text-slate-400">
                    <span>Page <?= $page ?> of <?= $total_pages ?></span>
                    <a href="?page=<?= max(1, $page-1) ?><?= $page_params ?>" class="p-1 rounded bg-slate-100 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-600 dark:text-slate-300 <?= $page<=1?'pointer-events-none opacity-40':'hover:bg-slate-200 dark:hover:bg-[#232b3d]' ?>"><span class="material-symbols-outlined text-sm block">chevron_left</span></a>
                    <a href="?page=<?= min($total_pages, $page+1) ?><?= $page_params ?>" class="p-1 rounded bg-slate-100 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-600 dark:text-slate-300 <?= $page>=$total_pages?'pointer-events-none opacity-40':'hover:bg-slate-200 dark:hover:bg-[#232b3d]' ?>"><span class="material-symbols-outlined text-sm block">chevron_right</span></a>
                </div>
            <?php endif; ?>
        </form>
    </div>

    <!-- Table -->
    <div class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] rounded-2xl overflow-hidden shadow-sm">
        <div class="overflow-x-auto custom-scrollbar">
            <table class="w-full text-left border-collapse" id="mainTable">
                <thead>
                    <tr class="bg-white dark:bg-[#1a2130] border-b border-slate-200 dark:border-[#232b3d]">
                        <th class="no-print px-2 py-1 w-8 text-center"><input type="checkbox" onclick="toggleAll(this)" class="rounded h-3 w-3 text-primary"></th>
                        <th class="px-2 py-1 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">Control #</th>
                        <th class="px-2 py-1 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">Department</th>
                        <th class="px-2 py-1 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">End User</th>
                        <th class="px-2 py-1 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">MR/PAR</th>
                        <th class="px-2 py-1 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">Status</th>
                        <th class="px-2 py-1 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">IP Address</th>
                        <th class="px-2 py-1 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">Endpoint<br>Secure</th>
                        <th class="px-2 py-1 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">Firewall</th>
                        <th class="px-2 py-1 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">Checked By</th>
                        <th class="px-2 py-1 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">Encoded By</th>
                        <th class="px-2 py-1 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">Remarks</th>
                        <th class="no-print px-2 py-1 text-[10px] font-bold text-slate-500 uppercase text-right whitespace-nowrap">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-[#232b3d]/50">
                    <?php if (empty($computers)): ?>
                        <tr><td colspan="13" class="px-6 py-12 text-center text-slate-500">No computers found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($computers as $item): ?>
                        <tr class="hover:bg-slate-50 dark:hover:bg-white/5 transition-colors text-[11px]" data-item='<?= htmlspecialchars(json_encode($item), ENT_QUOTES, "UTF-8") ?>'>
                            <td class="no-print px-2 py-1 text-center"><input type="checkbox" name="item_id[]" value="<?= $item['id'] ?>" class="item-checkbox rounded h-3 w-3 text-primary"></td>
                            <td class="px-2 py-1 font-mono text-primary whitespace-nowrap cursor-pointer hover:bg-primary/10 transition-colors" onclick='openEditModal(<?= htmlspecialchars(json_encode($item), ENT_QUOTES, "UTF-8") ?>)' title="Click to edit"><?= htmlspecialchars($item['control_number'] ?: '-') ?></td>
                            <td class="px-2 py-1 text-slate-600 dark:text-slate-400 whitespace-nowrap"><?= htmlspecialchars($item['department'] ?: '-') ?></td>
                            <td class="px-2 py-1 font-medium text-slate-900 dark:text-white whitespace-nowrap"><?= htmlspecialchars($item['end_user'] ?: '-') ?></td>
                            <td class="px-2 py-1 text-slate-600 dark:text-slate-400 whitespace-nowrap"><?= htmlspecialchars($item['mr_par'] ?: '-') ?></td>
                            <td class="px-2 py-1 whitespace-nowrap">
                                <?php
                                $sv = $item['status'] ?? 'Working';
                                $sc = match($sv) {
                                    'Working'     => 'inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
                                    'Maintenance' => 'inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
                                    'Transferred' => 'inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
                                    'Condemned'   => 'inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
                                    default       => 'inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-600',
                                };
                                ?>
                                <span class="<?= $sc ?>"><?= htmlspecialchars($sv) ?></span>
                            </td>
                            <td class="px-2 py-1 font-mono text-slate-600 dark:text-slate-400 whitespace-nowrap"><?= htmlspecialchars($item['ip_address'] ?: '-') ?></td>
                            <td class="px-2 py-1 text-center whitespace-nowrap"><span class="<?= ($item['endpoint_secure']??'N')==='Y'?'text-emerald-500 font-bold':'text-slate-500' ?>"><?= ($item['endpoint_secure']??'N')==='Y'?'Yes':'No' ?></span></td>
                            <td class="px-2 py-1 text-center whitespace-nowrap"><span class="<?= ($item['firewall']??'N')==='Y'?'inline-flex items-center gap-1 text-blue-500 font-bold':'text-slate-500' ?>"><?php if(($item['firewall']??'N')==='Y'): ?><span class="material-symbols-outlined text-[13px]">security</span> Yes<?php else: ?>No<?php endif; ?></span></td>
                            <td class="px-2 py-1 text-slate-600 dark:text-slate-400 whitespace-nowrap"><?= htmlspecialchars($item['checked_by'] ?: '-') ?></td>
                            <td class="px-2 py-1 text-slate-600 dark:text-slate-400 whitespace-nowrap"><?= htmlspecialchars($item['encoded_by'] ?: '-') ?></td>
                            <td class="px-2 py-1 text-slate-500 truncate max-w-[140px] whitespace-nowrap" title="<?= htmlspecialchars($item['remarks'] ?? '') ?>"><?= htmlspecialchars($item['remarks'] ?: '-') ?></td>
                            <td class="no-print px-2 py-1 text-right flex items-center justify-end gap-1 whitespace-nowrap">
                                <button onclick='openEditModal(<?= htmlspecialchars(json_encode($item), ENT_QUOTES, "UTF-8") ?>)' class="p-1 rounded-lg text-slate-400 hover:text-primary hover:bg-primary/10 transition-colors" title="Edit"><span class="material-symbols-outlined text-[16px]">edit</span></button>
                                <button onclick='confirmDelete(<?= $item['id'] ?>, "<?= htmlspecialchars($item['control_number']) ?>")' class="p-1 rounded-lg text-slate-400 hover:text-red-500 hover:bg-red-500/10 transition-colors" title="Delete"><span class="material-symbols-outlined text-[16px]">delete</span></button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- â”€â”€ Add Modal â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ -->
<!-- Import Modal -->
<div id="importModal" class="fixed inset-0 bg-black/60 z-50 hidden items-center justify-center p-4" onclick="if(event.target===this)toggleModal('importModal')">
    <div class="bg-white dark:bg-[#1a2130] rounded-2xl shadow-2xl w-full max-w-lg p-6">
        <div class="flex items-center justify-between border-b border-slate-200 dark:border-[#232b3d] pb-3 mb-4">
            <h3 class="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
                <span class="material-symbols-outlined text-amber-600">upload_file</span> Import <?= $MODULE_LABEL ?> from CSV
            </h3>
            <button type="button" onclick="toggleModal('importModal')" class="text-slate-400 hover:text-slate-600 dark:hover:text-white">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>

        <form method="POST" enctype="multipart/form-data" class="space-y-4">
            <?= getCsrfInput() ?>
            <input type="hidden" name="action" value="import_csv">

            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Select CSV File</label>
                <input type="file" name="csv_file" accept=".csv" required
                    class="w-full text-sm text-slate-500 file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-amber-500/10 file:text-amber-600 hover:file:bg-amber-500/20 file:cursor-pointer border border-slate-200 dark:border-[#232b3d] rounded-xl p-2 bg-slate-50 dark:bg-[#101622]">
            </div>

            <div class="p-3 bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] rounded-xl text-xs space-y-1 text-slate-600 dark:text-slate-400">
                <p class="font-bold text-slate-700 dark:text-slate-300 flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-[16px] text-amber-500">info</span> Supported Formats:
                </p>
                <p>&bull; <?= $MODULE_LABEL ?> CSV export format (34 columns) or Main Computer Inventory format (35 columns).</p>
                <p>&bull; Existing computers matching Control Number will be updated.</p>
                <p>&bull; IP &amp; MAC addresses will automatically register in the IP Module as Active.</p>
                <div class="pt-2">
                    <a href="?download_template=csv" class="inline-flex items-center gap-1 font-bold text-emerald-500 hover:text-emerald-400 hover:underline">
                        <span class="material-symbols-outlined text-[14px]">download</span> Download CSV Template
                    </a>
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-3 border-t border-slate-200 dark:border-[#232b3d]">
                <button type="button" onclick="toggleModal('importModal')" class="px-5 py-2 rounded-xl text-xs font-bold text-slate-500 hover:bg-slate-100 dark:hover:bg-[#232b3d]">Cancel</button>
                <button type="submit" class="px-5 py-2 rounded-xl text-xs font-bold bg-amber-600 text-white hover:bg-amber-700 flex items-center gap-1.5 shadow-sm">
                    <span class="material-symbols-outlined text-[16px]">upload</span> Upload &amp; Import
                </button>
            </div>
        </form>
    </div>
</div>

<div id="addComputerModal" class="fixed inset-0 bg-black/60 z-50 hidden items-center justify-center p-4" onclick="if(event.target===this)toggleModal('addComputerModal')">
    <div class="bg-white dark:bg-[#1a2130] rounded-2xl shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-y-auto custom-scrollbar">
        <div class="p-6 border-b border-slate-200 dark:border-[#232b3d] flex items-center justify-between">
            <h3 class="text-lg font-bold text-slate-900 dark:text-white">Add Computer &mdash; <?= $MODULE_LABEL ?></h3>
            <button onclick="toggleModal('addComputerModal')" class="text-slate-400 hover:text-white"><span class="material-symbols-outlined">close</span></button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
            <input type="hidden" name="action" value="add_computer">
            <?php include __DIR__ . '/../../modules/computers/_form_fields.php'; ?>
            <div class="flex justify-end gap-3 pt-4 border-t border-slate-200 dark:border-[#232b3d]">
                <button type="button" onclick="toggleModal('addComputerModal')" class="px-6 py-2 rounded-xl text-sm font-bold text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-[#232b3d] transition-colors">Cancel</button>
                <button type="submit" class="px-6 py-2 rounded-xl text-sm font-bold bg-primary text-white hover:bg-primary/90 transition-colors">Add Computer</button>
            </div>
        </form>
    </div>
</div>

<!-- â”€â”€ Edit Modal â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ -->
<div id="editComputerModal" class="fixed inset-0 bg-black/60 z-50 hidden items-center justify-center p-4" onclick="if(event.target===this)toggleModal('editComputerModal')">
    <div class="bg-white dark:bg-[#1a2130] rounded-2xl shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-y-auto custom-scrollbar">
        <div class="p-6 border-b border-slate-200 dark:border-[#232b3d] flex items-center justify-between">
            <h3 class="text-lg font-bold text-slate-900 dark:text-white">Edit Computer &mdash; <?= $MODULE_LABEL ?></h3>
            <button onclick="toggleModal('editComputerModal')" class="text-slate-400 hover:text-white"><span class="material-symbols-outlined">close</span></button>
        </div>
        <form method="POST" class="p-6 space-y-4" id="editComputerForm">
            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
            <input type="hidden" name="action" value="edit_computer">
            <input type="hidden" name="id" id="edit_id">
            <?php include __DIR__ . '/../../modules/computers/_form_fields.php'; ?>
            <div class="flex justify-end gap-3 pt-4 border-t border-slate-200 dark:border-[#232b3d]">
                <button type="button" onclick="toggleModal('editComputerModal')" class="px-6 py-2 rounded-xl text-sm font-bold text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-[#232b3d] transition-colors">Cancel</button>
                <button type="submit" class="px-6 py-2 rounded-xl text-sm font-bold bg-primary text-white hover:bg-primary/90 transition-colors">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirm Modal -->
<div id="deleteModal" class="fixed inset-0 bg-black/60 z-50 hidden items-center justify-center p-4">
    <div class="bg-white dark:bg-[#1a2130] rounded-2xl shadow-2xl w-full max-w-md p-6">
        <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-2">Delete Computer</h3>
        <p class="text-sm text-slate-500 mb-6">Are you sure you want to delete <strong id="deleteItemLabel"></strong>? This cannot be undone.</p>
        <form method="POST" class="flex justify-end gap-3">
            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
            <input type="hidden" name="action" value="delete_computer">
            <input type="hidden" name="id" id="deleteItemId">
            <button type="button" onclick="toggleModal('deleteModal')" class="px-6 py-2 rounded-xl text-sm font-bold text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-[#232b3d] transition-colors">Cancel</button>
            <button type="submit" class="px-6 py-2 rounded-xl text-sm font-bold bg-red-500 text-white hover:bg-red-600 transition-colors">Delete</button>
        </form>
    </div>
</div>

<script>
function toggleModal(id) {
    const el = document.getElementById(id);
    el.classList.toggle('hidden');
    el.classList.toggle('flex');
}
function toggleAll(source) {
    document.querySelectorAll('.item-checkbox').forEach(cb => cb.checked = source.checked);
}
function exportSelected() {
    const ids = [...document.querySelectorAll('.item-checkbox:checked')].map(cb => cb.value);
    const url = ids.length > 0 ? '?export=csv&ids=' + ids.join(',') : '?export=csv';
    window.location.href = url;
}
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
    }, 300);
}

window.addEventListener('afterprint', () => {
    document.body.classList.remove('print-filtered');
    document.querySelectorAll('tbody tr').forEach(tr => tr.classList.remove('print-row-selected'));
});
function confirmDelete(id, label) {
    document.getElementById('deleteItemId').value = id;
    document.getElementById('deleteItemLabel').textContent = label;
    toggleModal('deleteModal');
}
function openEditModal(item) {
    const f = document.getElementById('editComputerForm');
    document.getElementById('edit_id').value = item.id;
    const setVal = (name, val) => {
        const el = f.querySelector(`[name="${name}"]`);
        if (el) el.value = val ?? '';
    };
    ['department','end_user','mr_par','status','control_number','system_unit','system_unit_sn',
     'monitor','monitor_sn','mouse','mouse_sn','keyboard','keyboard_sn','printer_sn','scanner_sn',
     'avr_ups_sn','processor','memory','storage','os','os_product_key','license','microsoft_office',
     'ms_office_email','ip_address','mac_address','endpoint_secure','firewall','checked_by','encoded_by',
     'remarks','remarks_date'].forEach(k => setVal(k, item[k]));
    // Multi-select: printer, scanner, avr_ups
    ['printer','scanner','avr_ups'].forEach(k => {
        const sel = f.querySelector(`[name="${k}[]"]`) || f.querySelector(`select[name="${k}"]`);
        if (sel && item[k]) {
            const vals = item[k].split(',').map(v => v.trim());
            [...sel.options].forEach(o => o.selected = vals.includes(o.value));
        }
    });
    toggleModal('editComputerModal');
}
</script>

<!-- Print Styles -->
<style>
@media print {
    @page {
        size: landscape;
        margin: 8mm;
    }
    body {
        background: #fff !important;
        color: #000 !important;
        zoom: 75%;
    }
    aside, .sidebar, header .no-print, .no-print,
    form, .grid, .fixed, #importModal, #addComputerModal, #editComputerModal, #deleteModal,
    .border-b.sticky {
        display: none !important;
    }
    body.print-filtered tbody tr:not(.print-row-selected) {
        display: none !important;
    }
    th.no-print, td.no-print,
    th:first-child, td:first-child,
    th:last-child, td:last-child {
        display: none !important;
    }
    .overflow-x-auto, .overflow-hidden, .custom-scrollbar, .rounded-2xl {
        overflow: visible !important;
        height: auto !important;
        border-radius: 0 !important;
        box-shadow: none !important;
        border: none !important;
    }
    table {
        width: 100% !important;
        border-collapse: collapse !important;
    }
    th, td {
        border: 1px solid #cbd5e1 !important;
        color: #000 !important;
        padding: 4px 6px !important;
        font-size: 9.5px !important;
    }
    th {
        background: #f1f5f9 !important;
        font-weight: bold !important;
    }
}
</style>
<?php require_once '../../includes/footer.php'; ?>

