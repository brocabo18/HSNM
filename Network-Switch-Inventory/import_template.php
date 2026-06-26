<?php
/**
 * CSV Template Generator
 * Provides a downloadable CSV template with correct headers
 */

session_start();
require_once 'auth.php';

// Require authentication
requireAuth();

$filename = "switch_import_template.csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

// Add BOM for Excel
fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

// Write header row
fputcsv($output, [
    'Switch ID',
    'Model',
    'Manufacturer',
    'Serial Number',
    'IP Address',
    'MAC Address',
    'Building Location',
    'Floor',
    'Ports',
    'Port Details',
    'Status',
    'Personnel',
    'Remarks'
]);

// Write example row
fputcsv($output, [
    'SW-EXAMPLE-01',
    'Catalyst 9300',
    'Cisco Systems',
    'SN12345678',
    '192.168.1.10',
    '00:1A:2B:3C:4D:5E',
    'Capiz',
    'GF',
    '24 ports',
    '24x1G PoE+, 4x10G SFP+',
    'Active',
    'John Doe',
    'Main core switch'
]);

fclose($output);
exit;
