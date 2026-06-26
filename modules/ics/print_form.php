<?php
require_once '../../config.php';
requireLogin();

$ids = $_GET['ids'] ?? '';
if (empty($ids)) {
    die("No items selected.");
}

$idArray = explode(',', $ids);
$placeholders = implode(',', array_fill(0, count($idArray), '?'));

try {
    $stmt = $pdo->prepare("SELECT * FROM ics_inventory WHERE id IN ($placeholders)");
    $stmt->execute($idArray);
    $items = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Error fetching items: " . $e->getMessage());
}

if (empty($items)) {
    die("Items not found.");
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Custodian Slip</title>
    <link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>/assets/favicon.svg">
    <!-- Use Tailwind for consistency but custom styles for precise print layout -->
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            @page {
                size: A4 portrait;
                margin: 0.5in;
            }

            body {
                background: white;
            }

            .page-break {
                page-break-after: always;
            }
        }

        body {
            font-family: "Times New Roman", Serif;
            font-size: 11pt;
            background: #f3f4f6;
            /* Light gray bg for screen view */
            padding: 2rem;
        }

        .sheet {
            background: white;
            max-width: 8.5in;
            margin: 0 auto;
            padding: 0.5in;
            /* 0.5in margin equivalent */
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            margin-bottom: 2rem;
        }

        table,
        th,
        td {
            border: 1px solid black;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 4px 8px;
            vertical-align: top;
        }

        .no-border-top {
            border-top: none;
        }

        .no-border-bottom {
            border-bottom: none;
        }

        .text-center {
            text-align: center;
        }

        .font-bold {
            font-weight: bold;
        }

        .italic {
            font-style: italic;
        }

        .underline {
            text-decoration: underline;
        }
    </style>
</head>

<body>

    <?php foreach ($items as $index => $item): ?>
        <div class="sheet <?= ($index < count($items) - 1) ? 'page-break' : '' ?>">
            <!-- Header -->
            <div class="mb-4">
                <div class="text-xs text-right mb-4 italic">Appendix 59</div>
                <h1 class="text-xl font-bold text-center uppercase mb-6">INVENTORY CUSTODIAN SLIP</h1>

                <div class="flex justify-between items-end mb-2">
                    <div class="w-2/3">
                        <div class="font-bold">Entity Name: JOSE B. LINGAD MEMORIAL GEN. HOSPITAL</div>
                        <div class="flex items-end mt-1">
                            <span>Fund Cluster :</span>
                            <div class="border-b border-black flex-1 ml-2"></div>
                        </div>
                    </div>
                    <div class="w-1/3 text-right">
                        <span class="font-bold">ICS No : </span>
                        <span class="border-b border-black inline-block min-w-[150px] text-center">
                            <?= htmlspecialchars($item['ics_no']) ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Main Table -->
            <table class="w-full mb-8">
                <thead>
                    <tr>
                        <th rowspan="2" class="w-16">Quantity</th>
                        <th rowspan="2" class="w-16">Unit</th>
                        <th colspan="2" class="w-48">Amount</th>
                        <th rowspan="2">Description</th>
                        <th rowspan="2" class="w-32">Inventory<br>Item No.</th>
                        <th rowspan="2" class="w-24">Estimated<br>Useful Life</th>
                    </tr>
                    <tr>
                        <th>Unit Cost</th>
                        <th>Total Cost</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Single Item Row -->
                    <tr class="h-[400px]">
                        <td class="text-center">1</td>
                        <td class="text-center">UNIT</td>
                        <td class="text-right">
                            <?= number_format($item['price'], 2) ?>
                        </td>
                        <td class="text-right">
                            <?= number_format($item['price'], 2) ?>
                        </td>
                        <td>
                            <div class="whitespace-pre-line text-sm leading-relaxed">
                                <?php if ($item['item_code']): ?>
                                    <div class="font-bold">
                                        <?= htmlspecialchars($item['item_code']) ?>
                                    </div>
                                <?php endif; ?>
                                <div class="font-bold uppercase">
                                    <?= htmlspecialchars($item['model']) ?>
                                </div>
                                <div>SN:
                                    <?= htmlspecialchars($item['serial_number']) ?>
                                </div>
                                <?php if ($item['date_acquired']): ?>
                                    <div>DATE ACQUIRED:
                                        <?= date('M. d, Y', strtotime($item['date_acquired'])) ?>
                                    </div>
                                <?php endif; ?>
                                <?php if ($item['po_number']): ?>
                                    <div>PO#:
                                        <?= htmlspecialchars($item['po_number']) ?>
                                    </div>
                                <?php endif; ?>
                                <?php if ($item['iar']): ?>
                                    <div>IAR:
                                        <?= htmlspecialchars($item['iar']) ?>
                                    </div>
                                <?php endif; ?>
                                <?php if ($item['department']): ?>
                                    <div>LOCATION:
                                        <?= htmlspecialchars($item['department']) ?>
                                    </div>
                                <?php endif; ?>
                                <?php if ($item['supplier']): ?>
                                    <div>SUPPLIER:
                                        <?= htmlspecialchars($item['supplier']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="text-center">
                            <?= htmlspecialchars($item['inventory_item_no']) ?>
                        </td>
                        <td class="text-center">5 YEARS</td>
                    </tr>
                </tbody>
            </table>

            <!-- Signatories -->
            <div class="border border-black flex">
                <!-- Received From -->
                <div class="w-1/2 p-2 border-r border-black">
                    <div class="mb-12 font-bold">Received from:</div>

                    <div class="text-center">
                        <div class="font-bold underline uppercase">MELISSA S. SOLIMAN</div>
                        <div class="text-sm">Signature Over Printed Name</div>

                        <div class="mt-4 font-bold">SAO-Materials Management Unit</div>
                        <div class="text-sm">Position/Office</div>

                        <div class="mt-8 border-b border-black w-3/4 mx-auto"></div>
                        <div class="text-sm">Date</div>
                    </div>
                </div>

                <!-- Received By -->
                <div class="w-1/2 p-2">
                    <div class="mb-12 font-bold">Received by:</div>

                    <div class="text-center">
                        <div class="font-bold underline uppercase">
                            <?= htmlspecialchars($item['user_accountable']) ?>
                        </div>
                        <div class="text-sm">Signature Over Printed Name</div>

                        <div class="mt-4 font-bold text-transparent">.</div> <!-- Spacer/Placeholder for Position -->
                        <div class="text-sm">Position/Office</div>

                        <div class="mt-8 border-b border-black w-3/4 mx-auto"></div>
                        <div class="text-sm">Date</div>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <script>
        // Auto-print when loaded if on desktop, or just be ready
        window.onload = function () {
            // Optional: window.print();
        }
    </script>

</body>

</html>