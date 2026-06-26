<?php
require_once '../../config.php';
requireLogin();

// --- Auto-generate next Printer No. helper ---
function getNextPrinterNo($pdo)
{
    $stmt = $pdo->query("SELECT printer_no FROM printers ORDER BY id DESC LIMIT 1");
    $last = $stmt->fetchColumn();
    if (!$last) {
        return 'PRI-OS-001';
    }
    // Extract numeric part from e.g. PRI-OS-042
    if (preg_match('/PRI-OS-(\d+)$/', $last, $m)) {
        $next = (int) $m[1] + 1;
        return 'PRI-OS-' . str_pad($next, 3, '0', STR_PAD_LEFT);
    }
    return 'PRI-OS-001';
}

// --- Excel Export (xlsx via SpreadsheetML + ZipArchive) ---
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    $stmt = $pdo->query("SELECT * FROM printers ORDER BY id ASC");
    $items = $stmt->fetchAll();

    // Lists that drive dropdowns in the Add Printer form
    $buildings = ['ACIS','Bayanihan','Bio Safety','Capiz','Dietary','Frontline','HOPSS','Isolation','Lingap Baga','Medicine','OB-Gyne/Pedia','OPD','Orthopaedics','Surgery','Trauma','Wellness'];
    $floors    = ['GF','2F','3F','4F','5F','6F','7F'];

    // Helper: parse "Building Floor Department" back to its parts
    $parse_location = function(string $loc) use ($buildings, $floors): array {
        $remaining = $loc;
        $foundBuilding = '';
        $foundFloor    = '';
        foreach ($buildings as $b) {
            if (str_starts_with($remaining, $b)) {
                $foundBuilding = $b;
                $remaining     = ltrim(substr($remaining, strlen($b)));
                break;
            }
        }
        foreach ($floors as $f) {
            if (str_starts_with($remaining, $f)) {
                $foundFloor = $f;
                $remaining  = ltrim(substr($remaining, strlen($f)));
                break;
            }
        }
        return [$foundBuilding, $foundFloor, $remaining];
    };

    // ---- Build SpreadsheetML XML ----
    $xmlEsc = fn($v) => htmlspecialchars((string)$v, ENT_XML1 | ENT_QUOTES, 'UTF-8');

    // Shared strings
    $sharedStrings = [];
    $ssi = function(string $s) use (&$sharedStrings): int {
        $key = array_search($s, $sharedStrings, true);
        if ($key === false) { $sharedStrings[] = $s; $key = count($sharedStrings) - 1; }
        return $key;
    };

    // Column headers
    $headers = ['No.','Printer No.','Building','Floor','Department','Person Responsible','Date Issued','Signature','Remarks'];
    $rows = [];
    // header row
    $headerRow = [];
    foreach ($headers as $h) { $headerRow[] = ['t'=>'s','v'=>$ssi($h)]; }
    $rows[] = $headerRow;

    // data rows
    $rowNum = 1;
    foreach ($items as $item) {
        [$building, $floor, $dept] = $parse_location($item['location'] ?? '');
        $dateVal = '';
        if (!empty($item['date_issued'])) {
            // Excel date serial (days since 1899-12-30)
            $ts = strtotime($item['date_issued']);
            $dateVal = ($ts !== false) ? floor(($ts / 86400) + 25569) : '';
        }
        $row = [
            ['t'=>'n','v'=>$rowNum++],
            ['t'=>'s','v'=>$ssi($item['printer_no'] ?? '')],
            ['t'=>'s','v'=>$ssi($building)],
            ['t'=>'s','v'=>$ssi($floor)],
            ['t'=>'s','v'=>$ssi($dept)],
            ['t'=>'s','v'=>$ssi($item['person_responsible'] ?? '')],
            ['t'=>(!empty($dateVal) ? 'date' : 's'),'v'=>(!empty($dateVal) ? $dateVal : $ssi(''))],
            ['t'=>'s','v'=>$ssi($item['signature'] ?? '')],
            ['t'=>'s','v'=>$ssi($item['remarks'] ?? '')],
        ];
        $rows[] = $row;
    }

    // worksheet XML
    $cols = ['A','B','C','D','E','F','G','H','I'];
    $sheetXml  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
    $sheetXml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"';
    $sheetXml .= ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
    $sheetXml .= '<sheetData>';
    foreach ($rows as $ri => $row) {
        $rIdx = $ri + 1;
        $sheetXml .= "<row r=\"$rIdx\">";
        foreach ($row as $ci => $cell) {
            $colLetter = $cols[$ci];
            $ref = $colLetter . $rIdx;
            if ($cell['t'] === 's') {
                $sheetXml .= "<c r=\"$ref\" t=\"s\"><v>{$cell['v']}</v></c>";
            } elseif ($cell['t'] === 'date') {
                $sheetXml .= "<c r=\"$ref\" s=\"1\"><v>{$cell['v']}</v></c>";
            } else {
                $sheetXml .= "<c r=\"$ref\"><v>{$cell['v']}</v></c>";
            }
        }
        $sheetXml .= '</row>';
    }
    $sheetXml .= '</sheetData>';

    // Data validation for Building (column C) and Floor (column D)
    $totalDataRows = count($items) + 1; // +1 for header
    $lastRow = max($totalDataRows + 50, 200); // extend validation range
    $buildingFormula = '"' . implode(',', $buildings) . '"';
    $floorFormula    = '"' . implode(',', $floors) . '"';
    $sheetXml .= '<dataValidations count="2">';
    $sheetXml .= '<dataValidation type="list" allowBlank="1" showDropDown="0" sqref="C2:C' . $lastRow . '">';
    $sheetXml .= '<formula1>' . $xmlEsc($buildingFormula) . '</formula1>';
    $sheetXml .= '</dataValidation>';
    $sheetXml .= '<dataValidation type="list" allowBlank="1" showDropDown="0" sqref="D2:D' . $lastRow . '">';
    $sheetXml .= '<formula1>' . $xmlEsc($floorFormula) . '</formula1>';
    $sheetXml .= '</dataValidation>';
    $sheetXml .= '</dataValidations>';
    $sheetXml .= '</worksheet>';

    // shared strings XML
    $ssXml  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
    $ssXml .= '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . count($sharedStrings) . '" uniqueCount="' . count($sharedStrings) . '">';
    foreach ($sharedStrings as $s) {
        $ssXml .= '<si><t xml:space="preserve">' . $xmlEsc($s) . '</t></si>';
    }
    $ssXml .= '</sst>';

    // styles XML (cell format index 1 = date)
    $stylesXml  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
    $stylesXml .= '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
    $stylesXml .= '<numFmts count="1"><numFmt numFmtId="164" formatCode="YYYY-MM-DD"/></numFmts>';
    $stylesXml .= '<fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts>';
    $stylesXml .= '<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>';
    $stylesXml .= '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>';
    $stylesXml .= '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>';
    $stylesXml .= '<cellXfs count="2">';
    $stylesXml .= '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>';
    $stylesXml .= '<xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>';
    $stylesXml .= '</cellXfs>';
    $stylesXml .= '</styleSheet>';

    // workbook XML
    $workbookXml  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
    $workbookXml .= '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
    $workbookXml .= '<sheets><sheet name="Printers" sheetId="1" r:id="rId1"/></sheets>';
    $workbookXml .= '</workbook>';

    // relationships
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

    // Write to temp zip
    $tmpFile = tempnam(sys_get_temp_dir(), 'printers_') . '.xlsx';
    $zip = new ZipArchive();
    $zip->open($tmpFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('[Content_Types].xml',          $contentTypes);
    $zip->addFromString('_rels/.rels',                   $rootRels);
    $zip->addFromString('xl/workbook.xml',               $workbookXml);
    $zip->addFromString('xl/_rels/workbook.xml.rels',    $wbRels);
    $zip->addFromString('xl/worksheets/sheet1.xml',      $sheetXml);
    $zip->addFromString('xl/sharedStrings.xml',          $ssXml);
    $zip->addFromString('xl/styles.xml',                 $stylesXml);
    $zip->close();

    $filename = 'printers_export_' . date('Y-m-d_His') . '.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($tmpFile));
    readfile($tmpFile);
    unlink($tmpFile);
    exit;
}

// --- Handle Actions ---
$success_msg = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $error_msg = "Security validation failed. Please refresh and try again.";

    } elseif ($_POST['action'] === 'add_printer') {
        $printer_no = trim($_POST['printer_no'] ?? '');
        // Merge location fields
        $building = trim($_POST['building'] ?? '');
        $floor = trim($_POST['floor'] ?? '');
        $dept_input = trim($_POST['department'] ?? '');
        $location = trim("$building $floor $dept_input");
        $person_responsible = trim($_POST['person_responsible'] ?? '');
        $date_issued = $_POST['date_issued'] ?? null;
        if (empty($date_issued))
            $date_issued = null;
        $signature = trim($_POST['signature'] ?? '');
        $remarks = trim($_POST['remarks'] ?? '');

        if (empty($printer_no)) {
            $error_msg = "Printer No. is required.";
        } else {
            // Check duplicate
            $chk = $pdo->prepare("SELECT COUNT(*) FROM printers WHERE printer_no = ?");
            $chk->execute([$printer_no]);
            if ($chk->fetchColumn() > 0) {
                $error_msg = "Printer No. '$printer_no' already exists.";
            } else {
                try {
                    $stmt = $pdo->prepare("INSERT INTO printers (printer_no, location, person_responsible, date_issued, signature, remarks) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$printer_no, $location, $person_responsible, $date_issued, $signature, $remarks]);
                    $new_id = $pdo->lastInsertId();
                    logAudit($pdo, 'add_printer', "Added Printer | No: $printer_no | Location: $location | Person: $person_responsible", 'printers', $new_id);
                    /* logChangelog($pdo, 'feature', 'Printers', "Added Printer: $printer_no", "Location: $location | Person: $person_responsible"); removed */
                    $success_msg = "Printer added successfully.";
                } catch (Exception $e) {
                    $error_msg = "Error adding printer: " . $e->getMessage();
                }
            }
        }

    } elseif ($_POST['action'] === 'edit_printer') {
        $id = (int) ($_POST['id'] ?? 0);
        $printer_no = trim($_POST['printer_no'] ?? '');
        // Merge location fields
        $building = trim($_POST['building'] ?? '');
        $floor = trim($_POST['floor'] ?? '');
        $dept_input = trim($_POST['department'] ?? '');
        $location = trim("$building $floor $dept_input");
        $person_responsible = trim($_POST['person_responsible'] ?? '');
        $date_issued = $_POST['date_issued'] ?? null;
        if (empty($date_issued))
            $date_issued = null;
        $signature = trim($_POST['signature'] ?? '');
        $remarks = trim($_POST['remarks'] ?? '');

        if ($id && !empty($printer_no)) {
            $chk = $pdo->prepare("SELECT COUNT(*) FROM printers WHERE printer_no = ? AND id != ?");
            $chk->execute([$printer_no, $id]);
            if ($chk->fetchColumn() > 0) {
                $error_msg = "Printer No. '$printer_no' already exists.";
            } else {
                try {
                    // Snapshot old record for field-level diff
                    $snap_pr = $pdo->prepare("SELECT * FROM printers WHERE id = ?");
                    $snap_pr->execute([$id]);
                    $old_pr = $snap_pr->fetch(PDO::FETCH_ASSOC) ?: [];

                    $stmt = $pdo->prepare("UPDATE printers SET printer_no=?, location=?, person_responsible=?, date_issued=?, signature=?, remarks=?, updated_at=NOW() WHERE id=?");
                    $stmt->execute([$printer_no, $location, $person_responsible, $date_issued, $signature, $remarks, $id]);

                    $pr_labels = [
                        'printer_no' => 'Printer No', 'location' => 'Location',
                        'person_responsible' => 'Person Responsible', 'date_issued' => 'Date Issued',
                        'signature' => 'Signature', 'remarks' => 'Remarks',
                    ];
                    $new_pr = [
                        'printer_no' => $printer_no, 'location' => $location,
                        'person_responsible' => $person_responsible, 'date_issued' => $date_issued,
                        'signature' => $signature, 'remarks' => $remarks,
                    ];
                    $pr_changes = [];
                    foreach ($pr_labels as $f => $lbl) {
                        $o = trim((string)($old_pr[$f] ?? '')); $n = trim((string)($new_pr[$f] ?? ''));
                        if ($o !== $n) $pr_changes[] = "{$lbl}: \"{$o}\" → \"{$n}\"";
                    }
                    $pr_detail = empty($pr_changes)
                        ? "Updated Printer (no field changes) | ID: $id | No: $printer_no"
                        : "Updated Printer | No: $printer_no | " . implode(' | ', $pr_changes);
                    logAudit($pdo, 'update_printer', $pr_detail, 'printers', $id);
                    /* logChangelog removed */
                    $success_msg = "Printer updated successfully.";

                } catch (Exception $e) {
                    $error_msg = "Error updating printer: " . $e->getMessage();
                }
            }
        }

    } elseif ($_POST['action'] === 'delete_printer') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id) {
            try {
                $snap = $pdo->prepare("SELECT * FROM printers WHERE id = ?");
                $snap->execute([$id]);
                $del = $snap->fetch(PDO::FETCH_ASSOC);
                $del_detail = "Deleted Printer ID $id";
                if ($del) {
                    $del_detail = "Deleted Printer | ID: $id | No: {$del['printer_no']} | Location: {$del['location']} | Person: {$del['person_responsible']}";
                }
                $stmt = $pdo->prepare("DELETE FROM printers WHERE id = ?");
                $stmt->execute([$id]);
                logAudit($pdo, 'delete_printer', $del_detail, 'printers', $id);
                /* if ($del) logChangelog($pdo, 'bugfix', 'Printers', "Removed Printer: ...", ...); removed — data changes not tracked in changelog */
                $success_msg = "Printer deleted successfully.";
            } catch (Exception $e) {
                $error_msg = "Error deleting printer: " . $e->getMessage();
            }
        }

    } elseif ($_POST['action'] === 'import_csv') {
        if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === 0) {
            try {
                $file = fopen($_FILES['csv_file']['tmp_name'], 'r');
                $header = fgetcsv($file); // Skip header row
                $imported = 0;
                $duplicates = 0;
                $errors = [];

                while (($row = fgetcsv($file)) !== false) {
                    if (count($row) < 2)
                        continue;

                    $printer_no = trim($row[1] ?? '');
                    if (empty($printer_no))
                        continue;

                    // Skip duplicates
                    $chk = $pdo->prepare("SELECT COUNT(*) FROM printers WHERE printer_no = ?");
                    $chk->execute([$printer_no]);
                    if ($chk->fetchColumn() > 0) {
                        $duplicates++;
                        continue;
                    }

                    try {
                        // New column layout: No. | Printer No. | Building | Floor | Department | Person Responsible | Date Issued | Signature | Remarks
                        $building           = trim($row[2] ?? '');
                        $floor              = trim($row[3] ?? '');
                        $dept               = trim($row[4] ?? '');
                        $location           = trim("$building $floor $dept");
                        $person_responsible = trim($row[5] ?? '');
                        $date_raw           = trim($row[6] ?? '');
                        $date_issued        = !empty($date_raw) ? $date_raw : null;
                        $signature          = trim($row[7] ?? '');
                        $remarks            = trim($row[8] ?? '');

                        $ins = $pdo->prepare("INSERT INTO printers (printer_no, location, person_responsible, date_issued, signature, remarks) VALUES (?, ?, ?, ?, ?, ?)");
                        $ins->execute([$printer_no, $location, $person_responsible, $date_issued, $signature, $remarks]);
                        $imported++;
                    } catch (Exception $e) {
                        $errors[] = "Row " . ($imported + $duplicates + 1) . ": " . $e->getMessage();
                    }
                }

                fclose($file);

                $msg_parts = ["Imported $imported printers"];
                if ($duplicates > 0)
                    $msg_parts[] = "$duplicates duplicates skipped";
                if (!empty($errors))
                    $msg_parts[] = count($errors) . " failed";

                $success_msg = implode(", ", $msg_parts) . ".";
                logAudit($pdo, 'import_printers', "Imported $imported printers (Skipped $duplicates)", 'printers', null);
            } catch (Exception $e) {
                $error_msg = "Error importing CSV: " . $e->getMessage();
            }
        } else {
            $error_msg = "Please upload a valid CSV file.";
        }
    }
}

// --- Filtering & Sorting ---
$search_term = $_GET['search'] ?? '';
$sort_by = $_GET['sort'] ?? 'id_asc';

$where_clauses = ["1=1"];
$params = [];

if ($search_term) {
    $where_clauses[] = "(printer_no ILIKE ? OR location ILIKE ? OR person_responsible ILIKE ? OR remarks ILIKE ?)";
    $term = "%$search_term%";
    for ($i = 0; $i < 4; $i++)
        $params[] = $term;
}

$where_sql = implode(' AND ', $where_clauses);
$order_by = "id ASC";
switch ($sort_by) {
    case 'id_desc':
        $order_by = "id DESC";
        break;
    case 'date_asc':
        $order_by = "date_issued ASC NULLS LAST";
        break;
    case 'date_desc':
        $order_by = "date_issued DESC NULLS LAST";
        break;
    case 'office_asc':
        $order_by = "location ASC";
        break;
}

// --- Pagination ---
$page = max(1, (int) ($_GET['page'] ?? 1));
$is_ajax = isset($_GET['ajax']);
$limit_param = $_GET['limit'] ?? '50';
$limit = ($limit_param === 'all') ? 999999 : (int) $limit_param;
$offset = ($page - 1) * $limit;

$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM printers WHERE $where_sql");
$count_stmt->execute($params);
$total_items = $count_stmt->fetchColumn();
$total_pages = $limit < 999999 ? ceil($total_items / $limit) : 1;

$sql = "SELECT t.*, last_edit.username AS last_edited_by, last_edit.created_at AS last_edited_at
    FROM printers t
    LEFT JOIN LATERAL (
        SELECT u.username, al.created_at
        FROM audit_logs al
        LEFT JOIN users u ON al.user_id = u.id
        WHERE al.resource_type = 'printers' AND al.resource_id::text = t.id::text
        ORDER BY al.created_at DESC
        LIMIT 1
    ) last_edit ON true
    WHERE $where_sql ORDER BY $order_by LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$printers = $stmt->fetchAll();

// Row offset for "No." column
$row_offset = $offset;

$next_printer_no = getNextPrinterNo($pdo);

$page_title = "Canon LBP 2900 Printers";
if (!$is_ajax) {
    require_once '../../includes/header.php';
    require_once '../../includes/sidebar.php';
}
?>

<?php if (!$is_ajax): ?>
    <div class="p-4 md:p-8 flex-1 overflow-y-auto custom-scrollbar relative z-10 bg-slate-50 dark:bg-background-dark">
        <header
            class="-mt-4 -mx-4 md:-mt-8 md:-mx-8 px-4 md:px-8 pt-4 md:pt-8 pb-6 mb-8 flex flex-col md:flex-row items-start md:items-end justify-between gap-4 sticky top-0 bg-slate-50 dark:bg-background-dark z-20 border-b border-slate-200 dark:border-[#232b3d]">
            <div>
                <h2 class="text-2xl font-bold text-slate-900 dark:text-white">Canon LBP 2900 Printers</h2>
                <p class="text-sm text-slate-500 mt-1">Printer Inventory &amp; Issuance Records</p>
            </div>
            <div class="no-print flex items-center gap-2 md:gap-3 flex-wrap">
                <a href="?export=excel"
                    class="flex items-center justify-center rounded-lg h-9 px-4 bg-blue-600 text-white hover:bg-blue-700 transition-colors text-xs font-bold no-print">
                    <span class="material-symbols-outlined text-[18px] mr-2">download</span> Export Excel
                </a>
                <button onclick="toggleModal('importModal')"
                    class="flex items-center justify-center rounded-lg h-9 px-4 bg-amber-600 text-white hover:bg-amber-700 transition-colors text-xs font-bold no-print">
                    <span class="material-symbols-outlined text-[18px] mr-2">upload</span> Import CSV
                </button>
                <button onclick="printData()"
                    class="flex items-center justify-center rounded-lg h-9 px-4 bg-slate-800 dark:bg-slate-700 text-white hover:bg-slate-700 dark:hover:bg-slate-600 transition-colors text-xs font-bold no-print">
                    <span class="material-symbols-outlined text-[18px] mr-2">print</span> Print
                </button>
                <button onclick="toggleModal('addPrinterModal')"
                    class="flex items-center justify-center rounded-lg h-9 px-4 bg-primary text-white hover:bg-primary/90 transition-colors text-xs font-bold shadow-lg shadow-primary/20 no-print">
                    <span class="material-symbols-outlined text-[18px] mr-2">add</span> Add Printer
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

        <!-- Control Bar -->
        <div class="no-print mb-6 flex gap-3">
            <form method="GET" class="flex items-center gap-3 flex-wrap w-full">
                <div class="relative group">
                    <span
                        class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-[18px] text-slate-500">search</span>
                    <input type="text" name="search" id="searchInput" value="<?= htmlspecialchars($search_term) ?>"
                        placeholder="Search Printer No, Location, Person..."
                        class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] text-slate-700 dark:text-slate-200 text-xs rounded-xl pl-9 pr-4 py-2 focus:ring-2 focus:ring-primary w-64">
                </div>

                <select name="sort" onchange="this.form.submit()"
                    class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] text-slate-400 text-xs rounded-xl px-4 py-2">
                    <option value="id_asc" <?= $sort_by === 'id_asc' ? 'selected' : '' ?>>No. (Oldest First)</option>
                    <option value="id_desc" <?= $sort_by === 'id_desc' ? 'selected' : '' ?>>No. (Newest First)</option>
                    <option value="date_asc" <?= $sort_by === 'date_asc' ? 'selected' : '' ?>>Date Issued (Asc)</option>
                    <option value="date_desc" <?= $sort_by === 'date_desc' ? 'selected' : '' ?>>Date Issued (Desc)</option>
                    <option value="office_asc" <?= $sort_by === 'office_asc' ? 'selected' : '' ?>>Location (A-Z)</option>
                </select>

                <!-- Pagination Controls -->
                <div class="flex items-center gap-2 ml-auto">
                    <?php if ($total_pages > 1): ?>
                        <span class="text-xs text-slate-500 mr-2">Page
                            <?= $page ?> of
                            <?= $total_pages ?>
                        </span>
                        <div
                            class="flex h-9 bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] rounded-xl overflow-hidden mr-2">
                            <a href="?page=<?= max(1, $page - 1) ?>&search=<?= urlencode($search_term) ?>&limit=<?= $limit_param ?>&sort=<?= $sort_by ?>"
                                class="px-3 flex items-center justify-center hover:bg-white/5 text-slate-400 hover:text-slate-900 dark:hover:text-white transition-colors border-r border-slate-200 dark:border-[#232b3d] <?= $page <= 1 ? 'pointer-events-none opacity-50' : '' ?>">
                                <span class="material-symbols-outlined text-[18px]">chevron_left</span>
                            </a>
                            <a href="?page=<?= min($total_pages, $page + 1) ?>&search=<?= urlencode($search_term) ?>&limit=<?= $limit_param ?>&sort=<?= $sort_by ?>"
                                class="px-3 flex items-center justify-center hover:bg-white/5 text-slate-400 hover:text-slate-900 dark:hover:text-white transition-colors <?= $page >= $total_pages ? 'pointer-events-none opacity-50' : '' ?>">
                                <span class="material-symbols-outlined text-[18px]">chevron_right</span>
                            </a>
                        </div>
                    <?php endif; ?>
                    <select name="limit" onchange="this.form.submit()"
                        class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] text-slate-400 text-xs rounded-xl px-4 py-2">
                        <option value="50" <?= $limit_param == '50' ? 'selected' : '' ?>>Show: 50</option>
                        <option value="100" <?= $limit_param == '100' ? 'selected' : '' ?>>Show: 100</option>
                        <option value="all" <?= $limit_param == 'all' ? 'selected' : '' ?>>Show: All</option>
                    </select>
                </div>
            </form>
        </div>

        <!-- Table -->
    <?php endif; ?>

    <div id="printers-table-container"
        class="bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] rounded-2xl overflow-hidden shadow-sm transition-opacity duration-200">
        <div class="overflow-x-auto custom-scrollbar">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-white dark:bg-[#1a2130] border-b border-slate-200 dark:border-[#232b3d]">
                        <th class="no-print px-3 py-3 w-8 text-center text-slate-500">
                            <input type="checkbox" onclick="toggleAll(this)"
                                class="rounded border-slate-300 dark:border-[#232b3d] bg-slate-50 dark:bg-[#101622] text-primary focus:ring-primary h-3 w-3">
                        </th>
                        <th class="px-4 py-3 text-[10px] font-bold text-slate-500 uppercase w-12 text-center">No.</th>
                        <th class="px-4 py-3 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">Printer
                            No.</th>
                        <th class="px-4 py-3 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">Location
                        </th>
                        <th class="px-4 py-3 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">Person
                            Responsible</th>
                        <th class="px-4 py-3 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">Date
                            Issued</th>
                        <th class="px-4 py-3 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">Signature
                        </th>
                        <th class="px-4 py-3 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">Remarks
                        </th>
                        <th class="no-print px-4 py-3 text-[10px] font-bold text-slate-500 uppercase whitespace-nowrap">Last Edited By</th>
                        <th
                            class="px-4 py-3 text-[10px] font-bold text-slate-500 uppercase text-right whitespace-nowrap no-print">
                            Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#232b3d]/50">
                    <?php if (empty($printers)): ?>
                        <tr>
                            <td colspan="9" class="px-6 py-12 text-center text-slate-500">No printers found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($printers as $i => $item): ?>
                            <tr class="hover:bg-white/5 dark:hover:bg-white/5 hover:bg-slate-50 transition-colors text-xs" data-item='<?= htmlspecialchars(json_encode($item), ENT_QUOTES, "UTF-8") ?>'>
                                <td class="no-print px-3 py-3 text-center">
                                    <input type="checkbox"
                                        class="item-checkbox rounded border-slate-300 dark:border-[#232b3d] bg-slate-50 dark:bg-[#101622] text-primary focus:ring-primary h-3 w-3"
                                        value="<?= $item['id'] ?>">
                                </td>
                                <td class="px-4 py-3 text-slate-500 text-center">
                                    <?= $row_offset + $i + 1 ?>
                                </td>
                                <td class="px-4 py-3 font-medium text-slate-900 dark:text-white whitespace-nowrap">
                                    <a href="#" onclick='openEditModal(<?= json_encode($item) ?>)'
                                        class="text-primary hover:underline font-bold">
                                        <?= htmlspecialchars($item['printer_no']) ?>
                                    </a>
                                </td>
                                <td class="px-4 py-3 text-slate-600 dark:text-slate-400 whitespace-nowrap">
                                    <?= htmlspecialchars($item['location'] ?: '-') ?>
                                </td>
                                <td class="px-4 py-3 text-slate-700 dark:text-slate-300">
                                    <?= htmlspecialchars($item['person_responsible'] ?: '-') ?>
                                </td>
                                <td class="px-4 py-3 text-slate-600 dark:text-slate-400 whitespace-nowrap">
                                    <?= $item['date_issued'] ? date('M d, Y', strtotime($item['date_issued'])) : '-' ?>
                                </td>
                                <td class="px-4 py-3 text-slate-600 dark:text-slate-400">
                                    <?= htmlspecialchars($item['signature'] ?: '-') ?>
                                </td>
                                <td class="px-4 py-3 text-slate-600 dark:text-slate-400">
                                    <?= htmlspecialchars($item['remarks'] ?: '-') ?>
                                </td>
                                <td class="no-print px-4 py-3 whitespace-nowrap">
                                    <?php if (!empty($item['last_edited_by'])): ?>
                                        <div class="text-[10px] font-semibold text-slate-700 dark:text-slate-300"><?= htmlspecialchars($item['last_edited_by']) ?></div>
                                        <div class="text-[9px] text-slate-400"><?= date('M d, Y H:i', strtotime($item['last_edited_at'])) ?></div>
                                    <?php else: ?>
                                        <span class="text-slate-400 text-[10px]">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 text-right flex items-center justify-end gap-1 no-print">
                                    <button onclick='openEditModal(<?= json_encode($item) ?>)'
                                        class="p-1 hover:bg-primary/10 hover:text-primary text-slate-500 rounded-lg">
                                        <span class="material-symbols-outlined text-[18px]">edit</span>
                                    </button>
                                    <form method="POST" onsubmit="return confirm('Delete this printer record?');"
                                        style="display:inline;">
                                        <?= getCsrfInput() ?>
                                        <input type="hidden" name="action" value="delete_printer">
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
    </div>

    <!-- Add Printer Modal -->
    <div id="addPrinterModal" class="hidden fixed inset-0 z-50 overflow-y-auto" role="dialog">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 bg-background-dark/80 backdrop-blur-sm" onclick="toggleModal('addPrinterModal')">
            </div>
            <div
                class="relative bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] rounded-2xl max-w-lg w-full p-4 sm:p-6 shadow-2xl">
                <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-6">Add Printer</h3>
                <form method="POST">
                    <?= getCsrfInput() ?>
                    <input type="hidden" name="action" value="add_printer">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="flex flex-col col-span-2">
                            <label class="text-[10px] text-slate-500 mb-1 ml-1">Printer No.</label>
                            <input type="text" name="printer_no" id="add_printer_no"
                                value="<?= htmlspecialchars($next_printer_no) ?>" required placeholder="e.g. PRI-OS-001"
                                class="bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5 text-xs w-full font-mono">
                        </div>
                        <!-- Building Dropdown -->
                        <div class="flex flex-col">
                            <label class="text-[10px] text-slate-500 mb-1 ml-1">Building</label>
                            <select name="building"
                                class="bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5 text-xs w-full">
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
                        <!-- Floor Dropdown -->
                        <div class="flex flex-col">
                            <label class="text-[10px] text-slate-500 mb-1 ml-1">Floor</label>
                            <select name="floor"
                                class="bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5 text-xs w-full">
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
                        <!-- Department -->
                        <div class="flex flex-col col-span-2">
                            <label class="text-[10px] text-slate-500 mb-1 ml-1">Department</label>
                            <input type="text" name="department" placeholder="Department / Section"
                                class="bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5 text-xs w-full">
                        </div>
                        <div class="flex flex-col">
                            <label class="text-[10px] text-slate-500 mb-1 ml-1">Person Responsible</label>
                            <input type="text" name="person_responsible" placeholder="Full Name"
                                class="bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5 text-xs w-full">
                        </div>
                        <div class="flex flex-col">
                            <label class="text-[10px] text-slate-500 mb-1 ml-1">Date Issued</label>
                            <input type="date" name="date_issued"
                                class="bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5 text-xs w-full">
                        </div>
                        <div class="flex flex-col">
                            <label class="text-[10px] text-slate-500 mb-1 ml-1">Signature</label>
                            <input type="text" name="signature" placeholder="Signature (optional)"
                                class="bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5 text-xs w-full">
                        </div>
                        <div class="flex flex-col col-span-2">
                            <label class="text-[10px] text-slate-500 mb-1 ml-1">Remarks</label>
                            <input type="text" name="remarks" placeholder="Remarks (optional)"
                                class="bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5 text-xs w-full">
                        </div>
                    </div>
                    <div class="mt-6 flex justify-end gap-3">
                        <button type="button" onclick="toggleModal('addPrinterModal')"
                            class="px-4 py-2 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white text-xs">Cancel</button>
                        <button type="submit" class="px-6 py-2 bg-primary text-white font-bold rounded-xl text-xs">Save
                            Printer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Printer Modal -->
    <div id="editPrinterModal" class="hidden fixed inset-0 z-50 overflow-y-auto" role="dialog">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 bg-background-dark/80 backdrop-blur-sm" onclick="toggleModal('editPrinterModal')">
            </div>
            <div
                class="relative bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] rounded-2xl max-w-lg w-full p-4 sm:p-6 shadow-2xl">
                <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-6">Edit Printer</h3>
                <form method="POST">
                    <?= getCsrfInput() ?>
                    <input type="hidden" name="action" value="edit_printer">
                    <input type="hidden" name="id" id="edit_id">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="flex flex-col col-span-2">
                            <label class="text-[10px] text-slate-500 mb-1 ml-1">Printer No.</label>
                            <input type="text" name="printer_no" id="edit_printer_no" required
                                class="bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5 text-xs w-full font-mono">
                        </div>
                        <!-- Building Dropdown -->
                        <div class="flex flex-col">
                            <label class="text-[10px] text-slate-500 mb-1 ml-1">Building</label>
                            <select name="building" id="edit_building"
                                class="bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5 text-xs w-full">
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
                        <!-- Floor Dropdown -->
                        <div class="flex flex-col">
                            <label class="text-[10px] text-slate-500 mb-1 ml-1">Floor</label>
                            <select name="floor" id="edit_floor"
                                class="bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5 text-xs w-full">
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
                        <!-- Department -->
                        <div class="flex flex-col col-span-2">
                            <label class="text-[10px] text-slate-500 mb-1 ml-1">Department</label>
                            <input type="text" name="department" id="edit_department" placeholder="Department / Section"
                                class="bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5 text-xs w-full">
                        </div>
                        <div class="flex flex-col">
                            <label class="text-[10px] text-slate-500 mb-1 ml-1">Person Responsible</label>
                            <input type="text" name="person_responsible" id="edit_person_responsible"
                                class="bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5 text-xs w-full">
                        </div>
                        <div class="flex flex-col">
                            <label class="text-[10px] text-slate-500 mb-1 ml-1">Date Issued</label>
                            <input type="date" name="date_issued" id="edit_date_issued"
                                class="bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5 text-xs w-full">
                        </div>
                        <div class="flex flex-col">
                            <label class="text-[10px] text-slate-500 mb-1 ml-1">Signature</label>
                            <input type="text" name="signature" id="edit_signature"
                                class="bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5 text-xs w-full">
                        </div>
                        <div class="flex flex-col col-span-2">
                            <label class="text-[10px] text-slate-500 mb-1 ml-1">Remarks</label>
                            <input type="text" name="remarks" id="edit_remarks"
                                class="bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-900 dark:text-white rounded-xl px-4 py-2.5 text-xs w-full">
                        </div>
                    </div>
                    <div class="mt-6 flex justify-end gap-3">
                        <button type="button" onclick="toggleModal('editPrinterModal')"
                            class="px-4 py-2 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white text-xs">Cancel</button>
                        <button type="submit" class="px-6 py-2 bg-primary text-white font-bold rounded-xl text-xs">Save
                            Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <style>
        @media print {

            /* Selective row printing */
            body.print-filtered tbody tr:not(.print-row-selected) {
                display: none !important;
            }

            /* Hide checkbox column */
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

            /* Hide control bar (search, filters, pagination) */
            .mb-6.flex.gap-3 {
                display: none !important;
            }

            /* Hide last column (actions) */
            td:last-child,
            th:last-child {
                display: none !important;
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

    <!-- Import CSV Modal -->
    <div id="importModal" class="hidden fixed inset-0 z-50 overflow-y-auto" role="dialog">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 bg-background-dark/80 backdrop-blur-sm" onclick="toggleModal('importModal')"></div>
            <div
                class="relative bg-white dark:bg-[#1a2130] border border-slate-200 dark:border-[#232b3d] rounded-2xl max-w-lg w-full p-4 sm:p-6 shadow-2xl">
                <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-4">Import Printers from CSV / Excel</h3>
                <p class="text-sm text-slate-400 mb-2">Upload a CSV file matching the exported column layout:</p>
                <div class="mb-4 rounded-xl bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] px-4 py-2 text-[11px] text-slate-500 font-mono leading-relaxed">
                    No. &nbsp;|&nbsp; Printer No. &nbsp;|&nbsp; <span class="text-amber-500">Building</span> &nbsp;|&nbsp; <span class="text-amber-500">Floor</span> &nbsp;|&nbsp; Department &nbsp;|&nbsp; Person Responsible &nbsp;|&nbsp; Date Issued &nbsp;|&nbsp; Signature &nbsp;|&nbsp; Remarks
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <?= getCsrfInput() ?>
                    <input type="hidden" name="action" value="import_csv">
                    <div class="mb-4">
                        <input type="file" name="csv_file" accept=".csv,.xlsx" required
                            class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] text-slate-700 dark:text-white rounded-xl px-4 py-2.5 text-sm file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-amber-600 file:text-white hover:file:bg-amber-700">
                    </div>
                    <div class="flex justify-end gap-3">
                        <button type="button" onclick="toggleModal('importModal')"
                            class="px-4 py-2 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white text-xs">Cancel</button>
                        <button type="submit" class="px-6 py-2 bg-amber-600 text-white font-bold rounded-xl text-xs">Import</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openEditModal(item) {
            document.getElementById('edit_id').value = item.id;
            document.getElementById('edit_printer_no').value = item.printer_no || '';

            // Parse location back into building / floor / department
            // Location stored as "Building Floor Department"
            const loc = item.location || '';
            const buildings = ['ACIS', 'Bayanihan', 'Bio Safety', 'Capiz', 'Dietary', 'Frontline', 'HOPSS', 'Isolation', 'Lingap Baga', 'Medicine', 'OB-Gyne/Pedia', 'OPD', 'Orthopaedics', 'Surgery', 'Trauma', 'Wellness'];
            const floors = ['GF', '2F', '3F', '4F', '5F', '6F', '7F'];
            let foundBuilding = '', foundFloor = '', remaining = loc;

            for (const b of buildings) {
                if (remaining.startsWith(b)) {
                    foundBuilding = b;
                    remaining = remaining.slice(b.length).trim();
                    break;
                }
            }
            for (const f of floors) {
                if (remaining.startsWith(f)) {
                    foundFloor = f;
                    remaining = remaining.slice(f.length).trim();
                    break;
                }
            }

            document.getElementById('edit_building').value = foundBuilding;
            document.getElementById('edit_floor').value = foundFloor;
            document.getElementById('edit_department').value = remaining;

            document.getElementById('edit_person_responsible').value = item.person_responsible || '';
            // date_issued may be stored as "YYYY-MM-DD HH:MM:SS" — slice to just the date part
            document.getElementById('edit_date_issued').value = (item.date_issued || '').slice(0, 10);
            document.getElementById('edit_signature').value = item.signature || '';
            document.getElementById('edit_remarks').value = item.remarks || '';
            toggleModal('editPrinterModal');
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

            // Use selective filtering only if SOME (not all) rows are checked
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
            }, 150);
        }

        // Live Search
        const searchInput = document.getElementById('searchInput');
        let searchTimeout;
        if (searchInput) {
            searchInput.addEventListener('input', function () {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    const searchParams = new URLSearchParams(window.location.search);
                    if (this.value) {
                        searchParams.set('search', this.value);
                    } else {
                        searchParams.delete('search');
                    }
                    searchParams.delete('page');
                    window.location.search = searchParams.toString();
                }, 400);
            });
        }
    </script>

    <?php require_once '../../includes/footer.php'; ?>
<?php endif; ?>