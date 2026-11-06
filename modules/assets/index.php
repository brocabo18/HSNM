<?php
require_once '../../config.php';
requireLogin();

$success_msg = $error_msg = '';

if (isset($_GET['sync_done']) && $_GET['sync_done'] == '1') {
    $a_cnt = (int)($_GET['assets'] ?? 0);
    $m_cnt = (int)($_GET['maint'] ?? 0);
    $success_msg = "Successfully synchronized $a_cnt computer records to Asset Inventory" . ($m_cnt > 0 ? " and seeded $m_cnt historical PMS records to Maintenance." : ".");
}

// ── Excel Export ──────────────────────────────────────────────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    $where = '1=1'; $params = [];
    if (!empty($_GET['ids'])) { $ids = array_filter(explode(',', $_GET['ids']), 'is_numeric'); if ($ids) { $where = 'id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')'; $params = array_values($ids); } }
    $rows = $pdo->prepare("SELECT * FROM assets WHERE $where ORDER BY id DESC"); $rows->execute($params); $items = $rows->fetchAll();
    $xmlEsc = fn($v) => htmlspecialchars((string)$v, ENT_XML1|ENT_QUOTES,'UTF-8');
    $ss=[]; $ssi=function(string $s) use(&$ss):int{ $k=array_search($s,$ss,true); if($k===false){$ss[]=$s;$k=count($ss)-1;} return $k; };
    $hdrs=['Control ID','Department','End User','Source','MR/ICS','Brand/Model','Serial Number','PTR Number','Status','Processor','Memory','Storage','GPU','OS Version','OS Licensed','IP Address','MAC Address','MS Office','MS Office Licensed','Anti-Virus','Scan Date','Cost','Remarks','Encoded By'];
    $cols=[]; for($i=0;$i<count($hdrs);$i++) $cols[]=$i<26?chr(65+$i):'A'.chr(65+$i-26);
    $sxml='<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
    $sxml.='<row r="1">'; foreach($hdrs as $ci=>$h) $sxml.='<c r="'.$cols[$ci].'1" t="s"><v>'.$ssi($h).'</v></c>'; $sxml.='</row>';
    foreach($items as $ri=>$it){ $r=$ri+2; $sxml.="<row r=\"$r\">"; $vals=[$it['control_id']??'',$it['department']??'',$it['end_user']??'',$it['source']??'',$it['mr_ics']??'',$it['brand_model']??'',$it['serial_number']??'',$it['ptr_number']??'',$it['status']??'',$it['processor']??'',$it['memory']??'',$it['storage']??'',$it['gpu']??'',$it['os_version']??'',$it['os_licensed']?'Yes':'No',$it['ip_address']??'',$it['mac_address']??'',$it['ms_office']??'',$it['ms_office_licensed']?'Yes':'No',$it['antivirus']??'',$it['scan_date']??'',$it['cost']??'',$it['remarks']??'',$it['encoded_by']??'']; foreach($vals as $ci=>$v) $sxml.='<c r="'.$cols[$ci].$r.'" t="s"><v>'.$ssi((string)$v).'</v></c>'; $sxml.='</row>'; }
    $sxml.='</sheetData></worksheet>';
    $ssxml='<?xml version="1.0" encoding="UTF-8" standalone="yes"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="'.count($ss).'" uniqueCount="'.count($ss).'">'; foreach($ss as $s) $ssxml.='<si><t xml:space="preserve">'.$xmlEsc($s).'</t></si>'; $ssxml.='</sst>';
    $styles='<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts><fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/></cellXfs></styleSheet>';
    $wb='<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Assets" sheetId="1" r:id="rId1"/></sheets></workbook>';
    $wbr='<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>';
    $rr='<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>';
    $ct='<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>';
    $tmp=tempnam(sys_get_temp_dir(),'assets_').'.xlsx'; $z=new ZipArchive(); $z->open($tmp,ZipArchive::CREATE|ZipArchive::OVERWRITE);
    $z->addFromString('[Content_Types].xml',$ct); $z->addFromString('_rels/.rels',$rr); $z->addFromString('xl/workbook.xml',$wb); $z->addFromString('xl/_rels/workbook.xml.rels',$wbr); $z->addFromString('xl/worksheets/sheet1.xml',$sxml); $z->addFromString('xl/sharedStrings.xml',$ssxml); $z->addFromString('xl/styles.xml',$styles); $z->close();
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="assets_export_'.date('Y-m-d_His').'.xlsx"');
    header('Content-Length: '.filesize($tmp)); readfile($tmp); unlink($tmp); exit;
}

// ── CSV Template Download ─────────────────────────────────────────────────────
if (isset($_GET['download_template']) && $_GET['download_template'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="asset_inventory_template.csv"');
    $out = fopen('php://output', 'w');
    fputs($out, "\xEF\xBB\xBF");
    fputcsv($out, [
        'Control ID', 'Department', 'End User', 'Source', 'MR/ICS', 'Item Code', 'Cost',
        'Brand/Model', 'Serial Number', 'PTR Number', 'Processor', 'Memory', 'Storage', 'GPU',
        'Monitor', 'Monitor S/N', 'Printer', 'Printer S/N', 'UPS/AVR', 'UPS/AVR S/N',
        'Keyboard/Mouse', 'OS Version', 'OS Licensed', 'IP Address', 'MAC Address',
        'MS Office', 'MS Office Licensed', 'Anti-Virus', 'Scan Date', 'Status', 'Remarks', 'Encoded By'
    ]);
    fputcsv($out, [
        'AST-2026-001', 'IHOMS', 'Juan Dela Cruz', 'Purchased', 'PAR-001', 'ITEM-123', '45000',
        'Dell OptiPlex 7090', 'SN12345678', 'PTR-001', 'Intel Core i7-11700', '16GB DDR4', '512GB NVMe SSD', 'Intel UHD Graphics',
        'Dell 24" P2419H', 'MON-98765', 'Canon PIXMA G3010', 'PRN-54321', 'APC 650VA', 'UPS-11223',
        'Logitech MK120', 'Windows 11 Pro 64-bit', 'Yes', '192.168.1.105', '00:1A:2B:3C:4D:5E',
        'Microsoft Office 2021', 'Yes', 'Athena EPP', date('Y-m-d'), 'Active', 'Operational unit', 'Admin'
    ]);
    fclose($out);
    exit;
}

// ── CSV Export ────────────────────────────────────────────────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $where = '1=1'; $params = [];
    if (!empty($_GET['ids'])) {
        $ids = array_filter(explode(',', $_GET['ids']), 'is_numeric');
        if ($ids) {
            $where = 'id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
            $params = array_values($ids);
        }
    }
    $rows = $pdo->prepare("SELECT * FROM assets WHERE $where ORDER BY id DESC");
    $rows->execute($params);
    $items = $rows->fetchAll();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="assets_export_'.date('Y-m-d_His').'.csv"');
    $out = fopen('php://output', 'w');
    fputs($out, "\xEF\xBB\xBF");
    fputcsv($out, [
        'Control ID', 'Department', 'End User', 'Source', 'MR/ICS', 'Item Code', 'Cost',
        'Brand/Model', 'Serial Number', 'PTR Number', 'Processor', 'Memory', 'Storage', 'GPU',
        'Monitor', 'Monitor S/N', 'Printer', 'Printer S/N', 'UPS/AVR', 'UPS/AVR S/N',
        'Keyboard/Mouse', 'OS Version', 'OS Licensed', 'IP Address', 'MAC Address',
        'MS Office', 'MS Office Licensed', 'Anti-Virus', 'Scan Date', 'Status', 'Remarks', 'Encoded By'
    ]);
    foreach ($items as $it) {
        fputcsv($out, [
            $it['control_id'] ?? '',
            $it['department'] ?? '',
            $it['end_user'] ?? '',
            $it['source'] ?? '',
            $it['mr_ics'] ?? '',
            $it['item_code'] ?? '',
            $it['cost'] ?? '',
            $it['brand_model'] ?? '',
            $it['serial_number'] ?? '',
            $it['ptr_number'] ?? '',
            $it['processor'] ?? '',
            $it['memory'] ?? '',
            $it['storage'] ?? '',
            $it['gpu'] ?? '',
            $it['monitor'] ?? '',
            $it['monitor_sn'] ?? '',
            $it['printer'] ?? '',
            $it['printer_sn'] ?? '',
            $it['ups_avr'] ?? '',
            $it['ups_avr_sn'] ?? '',
            $it['keyboard_mouse'] ?? '',
            $it['os_version'] ?? '',
            ($it['os_licensed'] === true || $it['os_licensed'] === 't' || $it['os_licensed'] === 'Yes') ? 'Yes' : 'No',
            $it['ip_address'] ?? '',
            $it['mac_address'] ?? '',
            $it['ms_office'] ?? '',
            ($it['ms_office_licensed'] === true || $it['ms_office_licensed'] === 't' || $it['ms_office_licensed'] === 'Yes') ? 'Yes' : 'No',
            $it['antivirus'] ?? '',
            $it['scan_date'] ?? '',
            $it['status'] ?? 'Active',
            $it['remarks'] ?? '',
            $it['encoded_by'] ?? ''
        ]);
    }
    fclose($out);
    exit;
}

// ── CSRF ──────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $error_msg = 'Security validation failed. Please refresh and try again.';
        $_POST['action'] = 'invalid';
    }
}

// ── Add Asset ─────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_asset') {
    try {
        $stmt = $pdo->prepare("INSERT INTO assets (control_id,department,end_user,source,mr_ics,item_code,cost,brand_model,serial_number,ptr_number,processor,memory,storage,gpu,monitor,monitor_sn,printer,printer_sn,ups_avr,ups_avr_sn,keyboard_mouse,os_version,os_licensed,ip_address,mac_address,ms_office,ms_office_licensed,antivirus,scan_date,status,remarks,encoded_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([
            $_POST['control_id']??'', $_POST['department']??'', $_POST['end_user']??'',
            $_POST['source']??'Purchased', $_POST['mr_ics']??'', $_POST['item_code']??'',
            !empty($_POST['cost']) ? (float)$_POST['cost'] : null,
            $_POST['brand_model']??'', $_POST['serial_number']??'', $_POST['ptr_number']??'',
            $_POST['processor']??'', $_POST['memory']??'', $_POST['storage']??'', $_POST['gpu']??'',
            $_POST['monitor']??'', $_POST['monitor_sn']??'',
            $_POST['printer']??'', $_POST['printer_sn']??'',
            $_POST['ups_avr']??'', $_POST['ups_avr_sn']??'', $_POST['keyboard_mouse']??'',
            $_POST['os_version']??'', isset($_POST['os_licensed']) ? 'true' : 'false',
            $_POST['ip_address']??'', $_POST['mac_address']??'',
            $_POST['ms_office']??'', isset($_POST['ms_office_licensed']) ? 'true' : 'false',
            $_POST['antivirus']??'', !empty($_POST['scan_date']) ? $_POST['scan_date'] : null,
            $_POST['status']??'Active', $_POST['remarks']??'', $_POST['encoded_by']??''
        ]);
        logAudit($pdo, 'add_asset', 'Added asset: ' . ($_POST['control_id']??''), 'asset');
        if (!empty($_POST['ip_address']) || !empty($old_asset['ip_address'])) {
            syncComputerIpToIpModule($pdo, $_POST['ip_address'] ?? '', $_POST['mac_address'] ?? '', $_POST['control_id'] ?? '', $_POST['department'] ?? '', $_POST['department'] ?? '', $old_asset['ip_address'] ?? null);
        }
        $success_msg = 'Asset added successfully.';
    } catch (Exception $e) { $error_msg = 'Error: ' . $e->getMessage(); }
}

// ── Edit Asset ────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit_asset') {
    try {
        $old_asset_stmt = $pdo->prepare("SELECT ip_address, control_id FROM assets WHERE id = ?");
        $old_asset_stmt->execute([(int)$_POST['id']]);
        $old_asset = $old_asset_stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $stmt = $pdo->prepare("UPDATE assets SET control_id=?,department=?,end_user=?,source=?,mr_ics=?,item_code=?,cost=?,brand_model=?,serial_number=?,ptr_number=?,processor=?,memory=?,storage=?,gpu=?,monitor=?,monitor_sn=?,printer=?,printer_sn=?,ups_avr=?,ups_avr_sn=?,keyboard_mouse=?,os_version=?,os_licensed=?,ip_address=?,mac_address=?,ms_office=?,ms_office_licensed=?,antivirus=?,scan_date=?,status=?,remarks=?,encoded_by=?,updated_at=NOW() WHERE id=?");
        $stmt->execute([
            $_POST['control_id']??'', $_POST['department']??'', $_POST['end_user']??'',
            $_POST['source']??'Purchased', $_POST['mr_ics']??'', $_POST['item_code']??'',
            !empty($_POST['cost']) ? (float)$_POST['cost'] : null,
            $_POST['brand_model']??'', $_POST['serial_number']??'', $_POST['ptr_number']??'',
            $_POST['processor']??'', $_POST['memory']??'', $_POST['storage']??'', $_POST['gpu']??'',
            $_POST['monitor']??'', $_POST['monitor_sn']??'',
            $_POST['printer']??'', $_POST['printer_sn']??'',
            $_POST['ups_avr']??'', $_POST['ups_avr_sn']??'', $_POST['keyboard_mouse']??'',
            $_POST['os_version']??'', isset($_POST['os_licensed']) ? 'true' : 'false',
            $_POST['ip_address']??'', $_POST['mac_address']??'',
            $_POST['ms_office']??'', isset($_POST['ms_office_licensed']) ? 'true' : 'false',
            $_POST['antivirus']??'', !empty($_POST['scan_date']) ? $_POST['scan_date'] : null,
            $_POST['status']??'Active', $_POST['remarks']??'', $_POST['encoded_by']??'',
            $_POST['id']
        ]);
        logAudit($pdo, 'update_asset', 'Updated asset ID: ' . ($_POST['id']??''), 'asset', $_POST['id']);
        if (!empty($_POST['ip_address']) || !empty($old_asset['ip_address'])) {
            syncComputerIpToIpModule($pdo, $_POST['ip_address'] ?? '', $_POST['mac_address'] ?? '', $_POST['control_id'] ?? '', $_POST['department'] ?? '', $_POST['department'] ?? '', $old_asset['ip_address'] ?? null);
        }
        $success_msg = 'Asset updated successfully.';
    } catch (Exception $e) { $error_msg = 'Error: ' . $e->getMessage(); }
}

// ── Delete Asset ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_asset') {
    try {
        $id = (int)$_POST['id'];
        $del_snap = $pdo->prepare("SELECT ip_address, control_id FROM assets WHERE id = ?");
        $del_snap->execute([$id]);
        $del_asset = $del_snap->fetch(PDO::FETCH_ASSOC);

        $pdo->prepare("DELETE FROM assets WHERE id=?")->execute([$id]);
        if ($del_asset && !empty($del_asset['ip_address'])) {
            releaseComputerIpInIpModule($pdo, $del_asset['ip_address'], $del_asset['control_id'] ?? '');
        }
        logAudit($pdo, 'delete_asset', "Deleted asset ID: $id", 'asset', $id);
        $success_msg = 'Asset deleted.';
    } catch (Exception $e) { $error_msg = 'Error: ' . $e->getMessage(); }
}

// ── Import Assets CSV ─────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'import_assets_csv') {
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
                $header_map = [];
                foreach ($raw_headers as $idx => $hdr) {
                    $cleaned = strtolower(trim(preg_replace('/[^a-zA-Z0-9]/', '', $hdr)));
                    $header_map[$idx] = $cleaned;
                }

                $alias_to_col = [
                    'controlid' => 'control_id', 'controlno' => 'control_id', 'controlnumber' => 'control_id', 'ihomscontrolnumber' => 'control_id',
                    'department' => 'department', 'dept' => 'department', 'location' => 'department',
                    'enduser' => 'end_user', 'user' => 'end_user', 'employee' => 'end_user',
                    'source' => 'source', 'funds' => 'source',
                    'mrics' => 'mr_ics', 'mrpar' => 'mr_ics', 'mr' => 'mr_ics', 'ics' => 'mr_ics',
                    'itemcode' => 'item_code',
                    'cost' => 'cost', 'unitcost' => 'cost', 'price' => 'cost',
                    'brandmodel' => 'brand_model', 'brand' => 'brand_model', 'model' => 'brand_model', 'systemunit' => 'brand_model',
                    'serialnumber' => 'serial_number', 'sn' => 'serial_number', 'systemunitsn' => 'serial_number',
                    'ptrnumber' => 'ptr_number', 'ptr' => 'ptr_number',
                    'processor' => 'processor', 'cpu' => 'processor',
                    'memory' => 'memory', 'ram' => 'memory',
                    'storage' => 'storage', 'hdd' => 'storage', 'ssd' => 'storage',
                    'gpu' => 'gpu', 'videocard' => 'gpu',
                    'monitor' => 'monitor',
                    'monitorsn' => 'monitor_sn',
                    'printer' => 'printer',
                    'printersn' => 'printer_sn',
                    'upsavr' => 'ups_avr', 'avrups' => 'ups_avr', 'ups' => 'ups_avr', 'avr' => 'ups_avr',
                    'upsavrsn' => 'ups_avr_sn', 'avrupssn' => 'ups_avr_sn',
                    'keyboardmouse' => 'keyboard_mouse', 'keyboard' => 'keyboard_mouse', 'mouse' => 'keyboard_mouse',
                    'osversion' => 'os_version', 'os' => 'os_version',
                    'oslicensed' => 'os_licensed', 'license' => 'os_licensed', 'licensed' => 'os_licensed',
                    'ipaddress' => 'ip_address', 'ip' => 'ip_address',
                    'macaddress' => 'mac_address', 'mac' => 'mac_address',
                    'msoffice' => 'ms_office', 'office' => 'ms_office', 'microsoftoffice' => 'ms_office',
                    'msofficelicensed' => 'ms_office_licensed',
                    'antivirus' => 'antivirus', 'av' => 'antivirus', 'endpointsecure' => 'antivirus',
                    'scandate' => 'scan_date', 'date' => 'scan_date',
                    'status' => 'status',
                    'remarks' => 'remarks',
                    'encodedby' => 'encoded_by'
                ];

                $imported = 0;
                $updated = 0;
                $skipped = 0;

                while (($row = fgetcsv($handle)) !== FALSE) {
                    $row = array_map(function($val) {
                        $val = trim((string)$val);
                        if (!mb_check_encoding($val, 'UTF-8')) {
                            return mb_convert_encoding($val, 'UTF-8', 'Windows-1252');
                        }
                        return $val;
                    }, $row);
                    if (empty(array_filter($row, fn($v) => $v !== ''))) continue;

                    $record = [];
                    foreach ($row as $idx => $val) {
                        $hdr_clean = $header_map[$idx] ?? '';
                        if (isset($alias_to_col[$hdr_clean])) {
                            $col_name = $alias_to_col[$hdr_clean];
                            if ($col_name === 'keyboard_mouse' && !empty($record['keyboard_mouse'])) {
                                $record['keyboard_mouse'] .= ' / ' . trim($val);
                            } else {
                                $record[$col_name] = trim($val);
                            }
                        }
                    }

                    // Fallback if positional Computer Inventory CSV
                    if (empty($record['control_id']) && isset($row[5]) && count($row) >= 30) {
                        $record['department'] = trim(($row[0]??'') . ' ' . ($row[1]??'') . ' ' . ($row[2]??''));
                        $record['end_user'] = trim($row[3] ?? '');
                        $record['mr_ics'] = trim($row[4] ?? '');
                        $record['control_id'] = trim($row[5] ?? '');
                        $record['brand_model'] = trim($row[6] ?? '');
                        $record['serial_number'] = trim($row[7] ?? '');
                        $record['monitor'] = trim($row[8] ?? '');
                        $record['monitor_sn'] = trim($row[9] ?? '');
                        $record['keyboard_mouse'] = trim(($row[10]??'') . ' / ' . ($row[12]??''));
                        $record['printer'] = trim($row[14] ?? '');
                        $record['printer_sn'] = trim($row[15] ?? '');
                        $record['ups_avr'] = trim($row[18] ?? '');
                        $record['ups_avr_sn'] = trim($row[19] ?? '');
                        $record['processor'] = trim($row[20] ?? '');
                        $record['memory'] = trim($row[21] ?? '');
                        $record['storage'] = trim($row[22] ?? '');
                        $record['os_version'] = trim($row[23] ?? '');
                        $record['os_licensed'] = trim($row[25] ?? '');
                        $record['ms_office'] = trim($row[26] ?? '');
                        $record['ip_address'] = trim($row[28] ?? '');
                        $record['mac_address'] = trim($row[29] ?? '');
                        $ep = trim($row[30] ?? '');
                        $record['antivirus'] = (stripos($ep, 'y') !== false) ? 'Athena EPP' : ($ep ?: 'None');
                        $record['status'] = 'Active';
                        $record['encoded_by'] = trim($row[32] ?? '');
                        $record['remarks'] = trim($row[33] ?? '');
                    }

                    $ctrl = $record['control_id'] ?? '';
                    if (empty($ctrl)) {
                        $skipped++;
                        continue;
                    }

                    $os_lic = (!empty($record['os_licensed']) && in_array(strtolower($record['os_licensed']), ['1','y','yes','true','licensed','t'])) ? 'true' : 'false';
                    $ms_lic = (!empty($record['ms_office_licensed']) && in_array(strtolower($record['ms_office_licensed']), ['1','y','yes','true','licensed','t'])) ? 'true' : 'false';

                    $av = $record['antivirus'] ?? '';
                    if (in_array(strtolower($av), ['yes', 'y', '1', 'true'])) {
                        $av = 'Athena EPP';
                    }

                    $cost_clean = isset($record['cost']) ? preg_replace('/[^0-9.]/', '', $record['cost']) : '';
                    $cost_val = ($cost_clean !== '' && is_numeric($cost_clean)) ? (float)$cost_clean : null;
                    $scan_dt = !empty($record['scan_date']) ? date('Y-m-d', strtotime($record['scan_date'])) : null;

                    $chk = $pdo->prepare("SELECT id FROM assets WHERE control_id = ? LIMIT 1");
                    $chk->execute([$ctrl]);
                    $existing_asset_id = $chk->fetchColumn();

                    try {
                        if ($existing_asset_id) {
                            $upd = $pdo->prepare("
                                UPDATE assets SET
                                    department = COALESCE(NULLIF(?,''), department),
                                    end_user = COALESCE(NULLIF(?,''), end_user),
                                    source = COALESCE(NULLIF(?,''), source),
                                    mr_ics = COALESCE(NULLIF(?,''), mr_ics),
                                    item_code = COALESCE(NULLIF(?,''), item_code),
                                    cost = COALESCE(?, cost),
                                    brand_model = COALESCE(NULLIF(?,''), brand_model),
                                    serial_number = COALESCE(NULLIF(?,''), serial_number),
                                    ptr_number = COALESCE(NULLIF(?,''), ptr_number),
                                    processor = COALESCE(NULLIF(?,''), processor),
                                    memory = COALESCE(NULLIF(?,''), memory),
                                    storage = COALESCE(NULLIF(?,''), storage),
                                    gpu = COALESCE(NULLIF(?,''), gpu),
                                    monitor = COALESCE(NULLIF(?,''), monitor),
                                    monitor_sn = COALESCE(NULLIF(?,''), monitor_sn),
                                    printer = COALESCE(NULLIF(?,''), printer),
                                    printer_sn = COALESCE(NULLIF(?,''), printer_sn),
                                    ups_avr = COALESCE(NULLIF(?,''), ups_avr),
                                    ups_avr_sn = COALESCE(NULLIF(?,''), ups_avr_sn),
                                    keyboard_mouse = COALESCE(NULLIF(?,''), keyboard_mouse),
                                    os_version = COALESCE(NULLIF(?,''), os_version),
                                    os_licensed = ?,
                                    ip_address = COALESCE(NULLIF(?,''), ip_address),
                                    mac_address = COALESCE(NULLIF(?,''), mac_address),
                                    ms_office = COALESCE(NULLIF(?,''), ms_office),
                                    ms_office_licensed = ?,
                                    antivirus = COALESCE(NULLIF(?,''), antivirus),
                                    scan_date = COALESCE(?, scan_date),
                                    status = COALESCE(NULLIF(?,''), status),
                                    remarks = COALESCE(NULLIF(?,''), remarks),
                                    encoded_by = COALESCE(NULLIF(?,''), encoded_by),
                                    updated_at = NOW()
                                WHERE id = ?
                            ");
                            $upd->execute([
                                $record['department'] ?? '',
                                $record['end_user'] ?? '',
                                $record['source'] ?? '',
                                $record['mr_ics'] ?? '',
                                $record['item_code'] ?? '',
                                $cost_val,
                                $record['brand_model'] ?? '',
                                $record['serial_number'] ?? '',
                                $record['ptr_number'] ?? '',
                                $record['processor'] ?? '',
                                $record['memory'] ?? '',
                                $record['storage'] ?? '',
                                $record['gpu'] ?? '',
                                $record['monitor'] ?? '',
                                $record['monitor_sn'] ?? '',
                                $record['printer'] ?? '',
                                $record['printer_sn'] ?? '',
                                $record['ups_avr'] ?? '',
                                $record['ups_avr_sn'] ?? '',
                                $record['keyboard_mouse'] ?? '',
                                $record['os_version'] ?? '',
                                $os_lic,
                                $record['ip_address'] ?? '',
                                $record['mac_address'] ?? '',
                                $record['ms_office'] ?? '',
                                $ms_lic,
                                $av,
                                $scan_dt,
                                $record['status'] ?? '',
                                $record['remarks'] ?? '',
                                $record['encoded_by'] ?? '',
                                $existing_asset_id
                            ]);
                            $updated++;
                        } else {
                            $ins = $pdo->prepare("
                                INSERT INTO assets (
                                    control_id, department, end_user, source, mr_ics, item_code, cost,
                                    brand_model, serial_number, ptr_number, processor, memory, storage,
                                    gpu, monitor, monitor_sn, printer, printer_sn, ups_avr, ups_avr_sn,
                                    keyboard_mouse, os_version, os_licensed, ip_address, mac_address,
                                    ms_office, ms_office_licensed, antivirus, scan_date, status, remarks, encoded_by
                                ) VALUES (
                                    ?, ?, ?, ?, ?, ?, ?,
                                    ?, ?, ?, ?, ?, ?,
                                    ?, ?, ?, ?, ?, ?, ?,
                                    ?, ?, ?, ?, ?,
                                    ?, ?, ?, ?, ?, ?, ?
                                )
                            ");
                            $ins->execute([
                                $ctrl,
                                $record['department'] ?? '',
                                $record['end_user'] ?? '',
                                $record['source'] ?? 'Purchased',
                                $record['mr_ics'] ?? '',
                                $record['item_code'] ?? '',
                                $cost_val,
                                $record['brand_model'] ?? '',
                                $record['serial_number'] ?? '',
                                $record['ptr_number'] ?? '',
                                $record['processor'] ?? '',
                                $record['memory'] ?? '',
                                $record['storage'] ?? '',
                                $record['gpu'] ?? '',
                                $record['monitor'] ?? '',
                                $record['monitor_sn'] ?? '',
                                $record['printer'] ?? '',
                                $record['printer_sn'] ?? '',
                                $record['ups_avr'] ?? '',
                                $record['ups_avr_sn'] ?? '',
                                $record['keyboard_mouse'] ?? '',
                                $record['os_version'] ?? '',
                                $os_lic,
                                $record['ip_address'] ?? '',
                                $record['mac_address'] ?? '',
                                $record['ms_office'] ?? '',
                                $ms_lic,
                                $av,
                                $scan_dt,
                                $record['status'] ?? 'Active',
                                $record['remarks'] ?? '',
                                $record['encoded_by'] ?? ''
                            ]);
                            $imported++;
                        }

                        if (!empty($record['ip_address'])) {
                            syncComputerIpToIpModule($pdo, $record['ip_address'], $record['mac_address'] ?? '', $ctrl, $record['department'] ?? '');
                        }

                    } catch (Exception $e) {
                        $skipped++;
                    }
                }
                fclose($handle);

                $msg_parts = ["Imported $imported new assets"];
                if ($updated > 0) $msg_parts[] = "$updated existing assets updated";
                if ($skipped > 0) $msg_parts[] = "$skipped records skipped";
                $success_msg = implode(', ', $msg_parts) . '.';
                logAudit($pdo, 'import_csv', "Imported $imported new assets, updated $updated existing assets", 'asset');
            }
        } else {
            $error_msg = 'Unable to open uploaded CSV file.';
        }
    }
}

// ── Fetch / Filters / Pagination ──────────────────────────────────────────────
$is_ajax    = isset($_GET['ajax']);
$search     = $_GET['search'] ?? '';
$status_f   = $_GET['status'] ?? 'All';
$sort_by    = $_GET['sort']   ?? 'id_desc';
$limit_p    = $_GET['limit']  ?? '15';
$page       = max(1, (int)($_GET['page'] ?? 1));
$limit      = ($limit_p === 'all') ? 999999 : (int)$limit_p;
$offset     = ($page - 1) * $limit;

$where = ['1=1']; $params = [];
if ($search) {
    $where[] = "(control_id ILIKE ? OR brand_model ILIKE ? OR serial_number ILIKE ? OR end_user ILIKE ? OR department ILIKE ? OR ip_address ILIKE ? OR ptr_number ILIKE ?)";
    $sp = "%$search%"; for ($i=0;$i<7;$i++) $params[] = $sp;
}
if ($status_f !== 'All') { $where[] = "status = ?"; $params[] = $status_f; }
$where_sql = implode(' AND ', $where);
$order = match($sort_by) { 'id_asc'=>'id ASC','user_asc'=>'end_user ASC','dept_asc'=>'department ASC', default=>'id DESC' };

$total = $pdo->prepare("SELECT COUNT(*) FROM assets WHERE $where_sql"); $total->execute($params); $total_items = $total->fetchColumn();
$total_pages = max(1, ceil($total_items / $limit));
$page_params = "&search=".urlencode($search)."&status=".urlencode($status_f)."&sort=".urlencode($sort_by)."&limit=".urlencode($limit_p);
$stmt = $pdo->prepare("SELECT * FROM assets WHERE $where_sql ORDER BY $order LIMIT $limit OFFSET $offset"); $stmt->execute($params);
$assets = $stmt->fetchAll();

if (!$is_ajax) {
    $total_assets   = (int)($pdo->query("SELECT COUNT(*) FROM assets")->fetchColumn() ?: 0);
    $active_count   = (int)($pdo->query("SELECT COUNT(*) FROM assets WHERE status='Active'")->fetchColumn() ?: 0);
    $maint_count    = (int)($pdo->query("SELECT COUNT(*) FROM assets WHERE status='Maintenance'")->fetchColumn() ?: 0);
    $retired_count  = (int)($pdo->query("SELECT COUNT(*) FROM assets WHERE status='Retired'")->fetchColumn() ?: 0);
    $total_cost     = (float)($pdo->query("SELECT COALESCE(SUM(cost), 0) FROM assets")->fetchColumn() ?: 0);

    $staff_users = $pdo->query("SELECT full_name FROM users WHERE full_name IS NOT NULL AND TRIM(full_name)!='' ORDER BY full_name")->fetchAll(PDO::FETCH_COLUMN);

    $page_title = 'Asset Inventory';
    require_once '../../includes/header.php';
    require_once '../../includes/sidebar.php';
}
?>

<?php if (!$is_ajax): ?>
<div class="p-4 md:p-8 flex-1 overflow-y-auto custom-scrollbar relative z-10 bg-slate-50 dark:bg-background-dark">
    <!-- Page Header -->
    <!-- Page Header -->
    <div class="mb-6 flex flex-col md:flex-row items-start md:items-end justify-between gap-4 no-print">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <span class="px-2 py-0.5 rounded text-[11px] font-bold tracking-wider uppercase bg-amber-500/10 text-amber-500 border border-amber-500/20">Asset Management</span>
            </div>
            <h2 class="text-2xl font-bold text-slate-900 dark:text-white">Asset Inventory Registry</h2>
            <p class="text-xs text-slate-400 mt-0.5">Real-time status of computer units, hardware specs, and equipment lifecycle.</p>
        </div>
        <div class="no-print flex items-center gap-2 flex-wrap">
            <button onclick="toggleModal('addAssetModal')" class="flex items-center gap-1.5 px-3.5 h-9 rounded-lg bg-primary hover:bg-primary-hover text-white text-xs font-bold transition-all shadow-sm">
                <span class="material-symbols-outlined text-[16px]">add</span> Add Asset
            </button>
            <button onclick="toggleModal('importCsvModal')" class="flex items-center gap-1.5 px-3 h-9 rounded-lg bg-amber-500 hover:bg-amber-600 text-white text-xs font-bold transition-all shadow-sm" title="Import assets from CSV">
                <span class="material-symbols-outlined text-[16px]">upload_file</span> Import CSV
            </button>
            <button onclick="exportExcelSelected()" class="flex items-center gap-1.5 px-3 h-9 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold transition-all shadow-sm" title="Export Excel (Selected or All)">
                <span class="material-symbols-outlined text-[16px]">table_view</span> Export Excel
            </button>
            <button onclick="exportCsvSelected()" class="flex items-center gap-1.5 px-3 h-9 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold transition-all shadow-sm" title="Export CSV (Selected or All)">
                <span class="material-symbols-outlined text-[16px]">download</span> Export CSV
            </button>
            <a href="<?= BASE_URL ?>/run_computers_to_assets_sync.php" class="flex items-center gap-1.5 px-3 h-9 rounded-lg bg-violet-600 hover:bg-violet-700 text-white text-xs font-bold transition-all shadow-sm" title="Sync Computers to Asset Inventory & Maintenance">
                <span class="material-symbols-outlined text-[16px]">sync</span> Sync Computers
            </a>
            <button onclick="exportPdfTable()" class="flex items-center gap-1.5 px-3 h-9 rounded-lg bg-slate-700 hover:bg-slate-800 text-white text-xs font-bold transition-all shadow-sm" title="Export selected assets or table to PDF">
                <span class="material-symbols-outlined text-[16px]">picture_as_pdf</span> Export PDF
            </button>
            <button onclick="printSelected()" class="flex items-center gap-1.5 px-3 h-9 rounded-lg bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-[#232b3d] text-xs font-bold transition-all shadow-sm" title="Print Computer Unit Inventory Form for selected assets">
                <span class="material-symbols-outlined text-[16px]">print</span> Print Form
            </button>
        </div>
    </div>

    <!-- Notification Alerts -->
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
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Total Units</span>
                <span class="material-symbols-outlined text-primary text-base">computer</span>
            </div>
            <p class="text-2xl font-bold mt-1 text-slate-900 dark:text-white"><?= number_format($total_assets) ?></p>
            <span class="text-[10px] text-slate-400">Total cataloged assets</span>
        </div>
        <div class="bg-white dark:bg-[#161d2b] p-4 rounded-xl border border-slate-200 dark:border-[#232b3d] shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Active</span>
                <span class="material-symbols-outlined text-emerald-500 text-base">check_circle</span>
            </div>
            <p class="text-2xl font-bold mt-1 text-emerald-500"><?= number_format($active_count) ?></p>
            <span class="text-[10px] text-slate-400">Operational in production</span>
        </div>
        <div class="bg-white dark:bg-[#161d2b] p-4 rounded-xl border border-slate-200 dark:border-[#232b3d] shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Maintenance</span>
                <span class="material-symbols-outlined text-amber-500 text-base">build</span>
            </div>
            <p class="text-2xl font-bold mt-1 text-amber-500"><?= number_format($maint_count) ?></p>
            <span class="text-[10px] text-slate-400">Under repair or PMS</span>
        </div>
        <div class="bg-white dark:bg-[#161d2b] p-4 rounded-xl border border-slate-200 dark:border-[#232b3d] shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Retired</span>
                <span class="material-symbols-outlined text-slate-400 text-base">archive</span>
            </div>
            <p class="text-2xl font-bold mt-1 text-slate-700 dark:text-slate-300"><?= number_format($retired_count) ?></p>
            <span class="text-[10px] text-slate-400">Decommissioned items</span>
        </div>
        <div class="bg-white dark:bg-[#161d2b] p-4 rounded-xl border border-slate-200 dark:border-[#232b3d] shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Valuation</span>
                <span class="material-symbols-outlined text-purple-500 text-base">payments</span>
            </div>
            <p class="text-2xl font-bold mt-1 text-purple-400">₱<?= number_format($total_cost, 2) ?></p>
            <span class="text-[10px] text-slate-400">Total recorded cost</span>
        </div>
    </div>

    <!-- Filter / Control Bar -->
    <div class="bg-white dark:bg-[#161d2b] p-3 rounded-xl border border-slate-200 dark:border-[#232b3d] shadow-sm mb-4 no-print">
        <form method="GET" class="flex flex-wrap items-center gap-2">
            <div class="relative flex-1 min-w-[200px]">
                <span class="material-symbols-outlined absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400 text-sm">search</span>
                <input type="text" name="search" id="searchInput" value="<?= htmlspecialchars($search) ?>" placeholder="Search Control ID, Brand/Model, S/N, User, Dept, IP, PTR..." class="w-full pl-9 pr-3 py-1.5 text-xs rounded-lg bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:border-primary" autocomplete="off">
            </div>
            <select name="status" class="px-2.5 py-1.5 text-xs rounded-lg bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-700 dark:text-slate-300 focus:outline-none focus:border-primary">
                <option value="All" <?= $status_f==='All'?'selected':'' ?>>All Status</option>
                <option value="Active" <?= $status_f==='Active'?'selected':'' ?>>Active</option>
                <option value="Maintenance" <?= $status_f==='Maintenance'?'selected':'' ?>>Maintenance</option>
                <option value="Retired" <?= $status_f==='Retired'?'selected':'' ?>>Retired</option>
            </select>
            <select name="sort" class="px-2.5 py-1.5 text-xs rounded-lg bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-700 dark:text-slate-300 focus:outline-none focus:border-primary">
                <option value="id_desc" <?= $sort_by==='id_desc'?'selected':'' ?>>Newest First</option>
                <option value="id_asc" <?= $sort_by==='id_asc'?'selected':'' ?>>Oldest First</option>
                <option value="user_asc" <?= $sort_by==='user_asc'?'selected':'' ?>>User (A-Z)</option>
                <option value="dept_asc" <?= $sort_by==='dept_asc'?'selected':'' ?>>Department (A-Z)</option>
            </select>
            <select name="limit" class="px-2.5 py-1.5 text-xs rounded-lg bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-700 dark:text-slate-300 focus:outline-none focus:border-primary">
                <option value="15" <?= $limit_p=='15'?'selected':'' ?>>15</option>
                <option value="50" <?= $limit_p=='50'?'selected':'' ?>>50</option>
                <option value="100" <?= $limit_p=='100'?'selected':'' ?>>100</option>
                <option value="all" <?= $limit_p=='all'?'selected':'' ?>>All</option>
            </select>
            <button type="submit" class="px-3.5 py-1.5 text-xs font-bold rounded-lg bg-primary hover:bg-primary-hover text-white transition-all shadow-sm">
                Filter
            </button>
            <?php if ($search || $status_f !== 'All' || $sort_by !== 'id_desc' || $limit_p !== '15'): ?>
                <a href="index.php" class="px-2.5 py-1.5 text-xs font-medium text-red-500 hover:text-red-600 transition-colors">
                    Clear
                </a>
            <?php endif; ?>

            <?php if ($total_pages > 1): ?>
                <div class="ml-auto flex items-center gap-1.5 text-xs text-slate-500 dark:text-slate-400">
                    <span>Page <?= $page ?> of <?= $total_pages ?></span>
                    <a href="?page=<?= max(1, $page - 1) ?><?= $page_params ?>"
                        class="p-1 rounded bg-slate-100 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-600 dark:text-slate-300 <?= $page <= 1 ? 'pointer-events-none opacity-40' : 'hover:bg-slate-200 dark:hover:bg-[#232b3d]' ?>">
                        <span class="material-symbols-outlined text-sm block">chevron_left</span>
                    </a>
                    <a href="?page=<?= min($total_pages, $page + 1) ?><?= $page_params ?>"
                        class="p-1 rounded bg-slate-100 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-600 dark:text-slate-300 <?= $page >= $total_pages ? 'pointer-events-none opacity-40' : 'hover:bg-slate-200 dark:hover:bg-[#232b3d]' ?>">
                        <span class="material-symbols-outlined text-sm block">chevron_right</span>
                    </a>
                </div>
            <?php endif; ?>
        </form>
    </div>
<?php endif; ?>

    <!-- Table -->
    <div id="asset-table-container" class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] rounded-2xl overflow-hidden shadow-sm transition-opacity duration-200">
        <div class="overflow-x-auto custom-scrollbar">
            <table class="w-full text-left border-collapse" id="mainTable">
                <thead>
                    <tr class="border-b border-slate-200 dark:border-[#232b3d] bg-white dark:bg-[#1a2130]">
                        <th class="no-print px-3 py-2 w-8 text-center"><input type="checkbox" onclick="toggleAll(this)" class="rounded h-3 w-3 text-primary"></th>
                        <th class="px-3 py-2 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">Control ID</th>
                        <th class="px-3 py-2 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">Brand / Model</th>
                        <th class="px-3 py-2 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">Serial #</th>
                        <th class="px-3 py-2 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">PTR #</th>
                        <th class="px-3 py-2 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">Department</th>
                        <th class="px-3 py-2 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">End User</th>
                        <th class="px-3 py-2 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">Status</th>
                        <th class="px-3 py-2 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">IP Address</th>
                        <th class="no-print px-3 py-2 text-[10px] font-bold text-slate-500 uppercase text-right whitespace-nowrap">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-[#232b3d]/50">
                    <?php if (empty($assets)): ?>
                        <tr>
                            <td colspan="10" class="px-6 py-12 text-center text-slate-400 text-sm">
                                <div class="flex flex-col items-center justify-center gap-2">
                                    <span class="material-symbols-outlined text-4xl text-slate-300 dark:text-slate-600">inventory_2</span>
                                    <p class="font-medium text-slate-700 dark:text-slate-200">No assets found<?= $search ? ' matching "'.htmlspecialchars($search).'"' : ' in the registry.' ?></p>
                                    <p class="text-xs text-slate-500 max-w-md"><?= $search ? 'Try checking for typos or searching for a different keyword.' : 'You can add individual assets or populate the registry immediately from your 872 computer inventory records.' ?></p>
                                    <?php if (!$search): ?>
                                    <div class="flex items-center gap-2 mt-2">
                                        <a href="<?= BASE_URL ?>/run_computers_to_assets_sync.php" class="px-4 py-2 bg-blue-600 text-white rounded-xl text-xs font-bold hover:bg-blue-700 transition-colors flex items-center gap-1.5 shadow-sm">
                                            <span class="material-symbols-outlined text-[16px]">sync</span> Sync Computers to Assets
                                        </a>
                                        <button onclick="toggleModal('addAssetModal')" class="px-4 py-2 bg-primary text-white rounded-xl text-xs font-bold hover:bg-primary/90 transition-colors shadow-sm">
                                            + Add Asset Manually
                                        </button>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($assets as $a): ?>
                        <?php
                        $sc = match($a['status']??'Active') {
                            'Active'      => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-400',
                            'Maintenance' => 'bg-amber-50 text-amber-700 dark:bg-amber-900/20 dark:text-amber-400',
                            'Retired'     => 'bg-slate-100 text-slate-500 dark:bg-slate-800/30 dark:text-slate-400',
                            default       => 'bg-slate-100 text-slate-500',
                        };
                        ?>
                        <tr class="hover:bg-slate-50 dark:hover:bg-white/5 transition-colors text-[11px] group">
                            <td class="no-print px-3 py-2 text-center"><input type="checkbox" class="item-checkbox h-3 w-3 rounded text-primary" value="<?= $a['id'] ?>"></td>
                            <td class="px-3 py-2 font-mono text-primary font-bold cursor-pointer whitespace-nowrap hover:underline" onclick='openEditModal(<?= htmlspecialchars(json_encode($a), ENT_QUOTES) ?>)'><?= htmlspecialchars($a['control_id']??'-') ?></td>
                            <td class="px-3 py-2 whitespace-nowrap">
                                <div class="font-bold text-slate-800 dark:text-white"><?= htmlspecialchars($a['brand_model']??'-') ?></div>
                                <div class="text-[10px] text-slate-400"><?= htmlspecialchars(implode(' / ', array_filter([$a['processor']??'', $a['memory']??'', $a['storage']??'']))) ?></div>
                            </td>
                            <td class="px-3 py-2 font-mono text-slate-500 whitespace-nowrap"><?= htmlspecialchars($a['serial_number']??'-') ?></td>
                            <td class="px-3 py-2 text-slate-500 whitespace-nowrap"><?= htmlspecialchars($a['ptr_number']??'-') ?></td>
                            <td class="px-3 py-2 text-slate-600 dark:text-slate-400 whitespace-nowrap"><?= htmlspecialchars($a['department']??'-') ?></td>
                            <td class="px-3 py-2 font-medium text-slate-800 dark:text-white whitespace-nowrap"><?= htmlspecialchars($a['end_user']??'Unassigned') ?></td>
                            <td class="px-3 py-2 whitespace-nowrap">
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold border <?= $sc ?> border-current/20">
                                    <span class="w-1.5 h-1.5 rounded-full bg-current"></span><?= htmlspecialchars($a['status']??'Active') ?>
                                </span>
                            </td>
                            <td class="px-3 py-2 font-mono text-slate-500 whitespace-nowrap"><?= htmlspecialchars($a['ip_address']??'-') ?></td>
                            <td class="no-print px-3 py-2 text-right flex items-center justify-end gap-1">
                                <button onclick="printSingle(<?= $a['id'] ?>)" class="p-1 rounded-lg text-slate-400 hover:text-indigo-500 hover:bg-indigo-500/10 transition-colors" title="Print Inventory Form">
                                    <span class="material-symbols-outlined text-[15px]">print</span>
                                </button>
                                <button onclick='openEditModal(<?= htmlspecialchars(json_encode($a), ENT_QUOTES) ?>)' class="p-1 rounded-lg text-slate-400 hover:text-primary hover:bg-primary/10 transition-colors" title="Edit">
                                    <span class="material-symbols-outlined text-[15px]">edit</span>
                                </button>
                                <a href="<?= BASE_URL ?>/modules/maintenance/?asset_id=<?= $a['id'] ?>" class="p-1 rounded-lg text-slate-400 hover:text-blue-500 hover:bg-blue-500/10 transition-colors" title="View Maintenance">
                                    <span class="material-symbols-outlined text-[15px]">build</span>
                                </a>
                                <button onclick='confirmDelete(<?= $a['id'] ?>, "<?= htmlspecialchars($a['control_id']??'Asset') ?>")' class="p-1 rounded-lg text-slate-400 hover:text-red-500 hover:bg-red-500/10 transition-colors" title="Delete">
                                    <span class="material-symbols-outlined text-[15px]">delete</span>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3 border-t border-slate-200 dark:border-[#232b3d] flex items-center justify-between text-xs text-slate-400">
            <span>Showing <?= count($assets) ?> of <?= number_format($total_items) ?> assets</span>
            <?php if ($total_pages > 1): ?>
            <div class="flex gap-1">
                <a href="?page=<?= max(1,$page-1) ?><?= $page_params ?>" class="px-3 py-1 border border-slate-200 dark:border-[#232b3d] rounded hover:bg-slate-100 dark:hover:bg-[#232b3d] <?= $page<=1?'opacity-40 pointer-events-none':'' ?>">&#8249;</a>
                <span class="px-3 py-1 bg-primary text-white rounded"><?= $page ?></span>
                <a href="?page=<?= min($total_pages,$page+1) ?><?= $page_params ?>" class="px-3 py-1 border border-slate-200 dark:border-[#232b3d] rounded hover:bg-slate-100 dark:hover:bg-[#232b3d] <?= $page>=$total_pages?'opacity-40 pointer-events-none':'' ?>">&#8250;</a>
            </div>
            <?php endif; ?>
        </div>
        <template id="ajax-top-pagination">
            <?php if ($total_pages > 1): ?>
                <span class="text-xs text-slate-400">Page <?= $page ?>/<?= $total_pages ?></span>
                <div class="flex h-9 bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] rounded-xl overflow-hidden">
                    <a href="?page=<?= max(1,$page-1) ?><?= $page_params ?>" class="px-3 flex items-center text-slate-400 hover:text-primary border-r border-slate-200 dark:border-[#232b3d] <?= $page<=1?'pointer-events-none opacity-40':'' ?>"><span class="material-symbols-outlined text-[17px]">chevron_left</span></a>
                    <a href="?page=<?= min($total_pages,$page+1) ?><?= $page_params ?>" class="px-3 flex items-center text-slate-400 hover:text-primary <?= $page>=$total_pages?'pointer-events-none opacity-40':'' ?>"><span class="material-symbols-outlined text-[17px]">chevron_right</span></a>
                </div>
            <?php endif; ?>
        </template>
    </div>
<?php if ($is_ajax) { exit; } ?>
</div>

<!-- ── Add Modal ─────────────────────────────────────────────────────────────── -->
<div id="addAssetModal" class="fixed inset-0 bg-black/60 z-50 hidden items-center justify-center p-4" onclick="if(event.target===this)toggleModal('addAssetModal')">
    <div class="bg-white dark:bg-[#1a2130] rounded-2xl shadow-2xl w-full max-w-5xl max-h-[92vh] overflow-y-auto custom-scrollbar">
        <div class="p-5 border-b border-slate-200 dark:border-[#232b3d] flex items-center justify-between sticky top-0 bg-white dark:bg-[#1a2130] z-10">
            <h3 class="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2"><span class="material-symbols-outlined text-primary">inventory_2</span> Add Asset</h3>
            <button onclick="toggleModal('addAssetModal')" class="text-slate-400 hover:text-white"><span class="material-symbols-outlined">close</span></button>
        </div>
        <form method="POST" class="p-5 space-y-5">
            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
            <input type="hidden" name="action" value="add_asset">
            <?php include __DIR__ . '/_asset_form.php'; ?>
            <div class="flex justify-end gap-3 pt-4 border-t border-slate-200 dark:border-[#232b3d]">
                <button type="button" onclick="toggleModal('addAssetModal')" class="px-5 py-2 rounded-xl text-sm font-bold text-slate-500 hover:bg-slate-100 dark:hover:bg-[#232b3d]">Cancel</button>
                <button type="submit" class="px-6 py-2 rounded-xl text-sm font-bold bg-primary text-white hover:bg-primary/90">Save Asset</button>
            </div>
        </form>
    </div>
</div>

<!-- ── Edit Modal ────────────────────────────────────────────────────────────── -->
<div id="editAssetModal" class="fixed inset-0 bg-black/60 z-50 hidden items-center justify-center p-4" onclick="if(event.target===this)toggleModal('editAssetModal')">
    <div class="bg-white dark:bg-[#1a2130] rounded-2xl shadow-2xl w-full max-w-5xl max-h-[92vh] overflow-y-auto custom-scrollbar">
        <div class="p-5 border-b border-slate-200 dark:border-[#232b3d] flex items-center justify-between sticky top-0 bg-white dark:bg-[#1a2130] z-10">
            <h3 class="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2"><span class="material-symbols-outlined text-primary">edit</span> Edit Asset</h3>
            <button onclick="toggleModal('editAssetModal')" class="text-slate-400 hover:text-white"><span class="material-symbols-outlined">close</span></button>
        </div>
        <form method="POST" class="p-5 space-y-5" id="editAssetForm">
            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
            <input type="hidden" name="action" value="edit_asset">
            <input type="hidden" name="id" id="edit_id">
            <?php include __DIR__ . '/_asset_form.php'; ?>
            <div class="flex justify-end gap-3 pt-4 border-t border-slate-200 dark:border-[#232b3d]">
                <button type="button" onclick="toggleModal('editAssetModal')" class="px-5 py-2 rounded-xl text-sm font-bold text-slate-500 hover:bg-slate-100 dark:hover:bg-[#232b3d]">Cancel</button>
                <button type="submit" class="px-6 py-2 rounded-xl text-sm font-bold bg-primary text-white hover:bg-primary/90">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Modal -->
<div id="deleteModal" class="fixed inset-0 bg-black/60 z-50 hidden items-center justify-center p-4">
    <div class="bg-white dark:bg-[#1a2130] rounded-2xl shadow-2xl w-full max-w-md p-6">
        <h3 class="text-base font-bold text-slate-900 dark:text-white mb-2">Delete Asset</h3>
        <p class="text-sm text-slate-500 mb-6">Delete <strong id="deleteLabel"></strong>? This cannot be undone.</p>
        <form method="POST" class="flex justify-end gap-3">
            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
            <input type="hidden" name="action" value="delete_asset">
            <input type="hidden" name="id" id="deleteId">
            <button type="button" onclick="toggleModal('deleteModal')" class="px-5 py-2 rounded-xl text-sm font-bold text-slate-500 hover:bg-slate-100 dark:hover:bg-[#232b3d]">Cancel</button>
            <button type="submit" class="px-5 py-2 rounded-xl text-sm font-bold bg-red-500 text-white hover:bg-red-600">Delete</button>
        </form>
    </div>
</div>

<!-- Import CSV Modal -->
<div id="importCsvModal" class="fixed inset-0 bg-black/60 z-50 hidden items-center justify-center p-4">
    <div class="bg-white dark:bg-[#1a2130] rounded-2xl shadow-2xl w-full max-w-lg p-6">
        <div class="flex items-center justify-between border-b border-slate-200 dark:border-[#232b3d] pb-3 mb-4">
            <h3 class="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
                <span class="material-symbols-outlined text-amber-600">upload_file</span> Import Assets from CSV
            </h3>
            <button type="button" onclick="toggleModal('importCsvModal')" class="text-slate-400 hover:text-slate-600 dark:hover:text-white">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>

        <form method="POST" enctype="multipart/form-data" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
            <input type="hidden" name="action" value="import_assets_csv">

            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Select CSV File</label>
                <input type="file" name="csv_file" accept=".csv" required
                    class="w-full text-sm text-slate-500 file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-amber-500/10 file:text-amber-600 hover:file:bg-amber-500/20 file:cursor-pointer border border-slate-200 dark:border-[#232b3d] rounded-xl p-2 bg-slate-50 dark:bg-[#101622]">
            </div>

            <div class="p-3 bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] rounded-xl text-xs space-y-1 text-slate-600 dark:text-slate-400">
                <p class="font-bold text-slate-700 dark:text-slate-300 flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-[16px] text-amber-500">info</span> Supported Formats:
                </p>
                <p>&bull; Standard Asset Inventory CSV format or Computer Inventory CSV format.</p>
                <p>&bull; Existing assets matching Control ID will be updated automatically.</p>
                <p>&bull; IP &amp; MAC addresses will automatically register in the IP Module as Active.</p>
                <div class="pt-2">
                    <a href="?download_template=csv" class="inline-flex items-center gap-1 font-bold text-emerald-500 hover:text-emerald-400 hover:underline">
                        <span class="material-symbols-outlined text-[14px]">download</span> Download CSV Template
                    </a>
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-3 border-t border-slate-200 dark:border-[#232b3d]">
                <button type="button" onclick="toggleModal('importCsvModal')" class="px-5 py-2 rounded-xl text-xs font-bold text-slate-500 hover:bg-slate-100 dark:hover:bg-[#232b3d]">Cancel</button>
                <button type="submit" class="px-5 py-2 rounded-xl text-xs font-bold bg-amber-600 text-white hover:bg-amber-700 flex items-center gap-1.5 shadow-sm">
                    <span class="material-symbols-outlined text-[16px]">upload</span> Upload &amp; Import
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleModal(id){const e=document.getElementById(id);e.classList.toggle('hidden');e.classList.toggle('flex');}
function toggleAll(s){document.querySelectorAll('.item-checkbox').forEach(c=>c.checked=s.checked);}

function exportCsvSelected(){
    const ids=[...document.querySelectorAll('.item-checkbox:checked')].map(c=>c.value);
    window.location.href=ids.length?'?export=csv&ids='+ids.join(','):'?export=csv';
}
function exportExcelSelected(){
    const ids=[...document.querySelectorAll('.item-checkbox:checked')].map(c=>c.value);
    window.location.href=ids.length?'?export=excel&ids='+ids.join(','):'?export=excel';
}

function exportPdfTable(){
    const checkboxes = document.querySelectorAll('.item-checkbox:checked');
    const allRows = document.querySelectorAll('tbody tr');
    
    if (checkboxes.length === 0) {
        if (!confirm('No assets selected. Do you want to export all visible assets in the table to PDF?')) {
            return;
        }
        document.body.classList.remove('print-filtered');
        allRows.forEach(tr => tr.classList.remove('print-row-selected'));
    } else {
        document.body.classList.add('print-filtered');
        allRows.forEach(tr => {
            const cb = tr.querySelector('.item-checkbox');
            if (cb && cb.checked) {
                tr.classList.add('print-row-selected');
            } else {
                tr.classList.remove('print-row-selected');
            }
        });
    }

    setTimeout(() => {
        window.print();
    }, 300);
}

window.addEventListener('afterprint', () => {
    document.body.classList.remove('print-filtered');
    document.querySelectorAll('tbody tr').forEach(tr => tr.classList.remove('print-row-selected'));
});

function printSelected(){
    const ids=[...document.querySelectorAll('.item-checkbox:checked')].map(c=>c.value);
    if(ids.length===0){
        alert('Please select at least one asset to print.');
        return;
    }
    window.open('print.php?ids=' + ids.join(','), '_blank');
}
function printSingle(id){
    window.open('print.php?ids=' + id, '_blank');
}
function confirmDelete(id,label){document.getElementById('deleteId').value=id;document.getElementById('deleteLabel').textContent=label;toggleModal('deleteModal');}
function openEditModal(item){
    const f=document.getElementById('editAssetForm');
    document.getElementById('edit_id').value=item.id;
    ['control_id','department','end_user','source','mr_ics','item_code','cost','brand_model','serial_number','ptr_number','processor','memory','storage','gpu','monitor','monitor_sn','printer','printer_sn','ups_avr','ups_avr_sn','keyboard_mouse','os_version','ip_address','mac_address','ms_office','antivirus','scan_date','status','remarks','encoded_by'].forEach(k=>{
        const el=f.querySelector(`[name="${k}"]`);if(el)el.value=item[k]??'';
    });
    const osl=f.querySelector('[name="os_licensed"]'); if(osl) osl.checked=(item.os_licensed===true||item.os_licensed==='t');
    const msl=f.querySelector('[name="ms_office_licensed"]'); if(msl) msl.checked=(item.ms_office_licensed===true||item.ms_office_licensed==='t');
    toggleModal('editAssetModal');
}

// ── Live Search ─────────────────────────────────────────────────────────────
(function() {
    let searchTimeout = null;
    let searchController = null;
    const searchInput = document.getElementById('searchInput');
    if (!searchInput) return;

    const form = searchInput.form || searchInput.closest('form');

    function performSearch() {
        const container = document.getElementById('asset-table-container');
        if (container) container.classList.add('opacity-50');

        if (searchController) {
            searchController.abort();
        }
        searchController = new AbortController();

        const formData = form ? new FormData(form) : new FormData();
        if (!form) {
            formData.set('search', searchInput.value);
        }
        const params = new URLSearchParams(formData);
        params.set('page', '1');
        params.set('ajax', '1');

        fetch(window.location.pathname + '?' + params.toString(), {
            signal: searchController.signal
        })
        .then(response => {
            if (!response.ok) throw new Error('Network response was not ok');
            return response.text();
        })
        .then(html => {
            if (html.includes('<!DOCTYPE html>')) {
                console.warn('Full page received instead of partial.');
                return;
            }

            const currentContainer = document.getElementById('asset-table-container');
            if (currentContainer) {
                currentContainer.outerHTML = html;
            }

            const topPagination = document.getElementById('top-pagination');
            const ajaxTopPagination = document.getElementById('ajax-top-pagination');
            if (topPagination) {
                topPagination.innerHTML = ajaxTopPagination ? ajaxTopPagination.innerHTML : '';
            }

            params.delete('ajax');
            const newUrl = window.location.pathname + (params.toString() ? '?' + params.toString() : '');
            window.history.replaceState({}, '', newUrl);
        })
        .catch(err => {
            if (err.name !== 'AbortError') {
                console.error('Search error:', err);
                const currentContainer = document.getElementById('asset-table-container');
                if (currentContainer) currentContainer.classList.remove('opacity-50');
            }
        });
    }

    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(performSearch, 300);
    });

    searchInput.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            if (this.value !== '') {
                this.value = '';
                clearTimeout(searchTimeout);
                performSearch();
            }
        }
    });

    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            clearTimeout(searchTimeout);
            performSearch();
        });
    }
})();
</script>

<!-- Print Styles for Assets Table Export -->
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
    form, .grid, .fixed, #importCsvModal, #addAssetModal, #editAssetModal, #deleteModal,
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
