<?php
require 'config.php';
$tables = ['office_licenses', 'ics_inventory', 'printers', 'queueing_tvs', 'ihoms_links', 'firewall_status'];
foreach($tables as $t) {
    $stmt = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = '$t'");
    echo "$t: " . implode(', ', $stmt->fetchAll(PDO::FETCH_COLUMN)) . "\n";
}
