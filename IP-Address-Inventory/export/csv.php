<?php
require_once '../config/config.php';

// Determine export type
$type = $_GET['type'] ?? 'inventory';
$filename = '';
$data = [];
$headers = [];

switch ($type) {
    case 'inventory':
        $filename = 'ip_inventory_' . date('Y-m-d') . '.csv';
        $headers = ['IP Address', 'Hostname', 'MAC Address', 'Status', 'Device Type', 'Location', 'VLAN', 'Last Seen', 'Notes'];

        $stmt = $pdo->query("
            SELECT ip_address, hostname, mac_address, status, device_type, location, vlan_id, last_seen, notes
            FROM ip_inventory
            ORDER BY INET_ATON(ip_address)
        ");
        $data = $stmt->fetchAll(PDO::FETCH_NUM);
        break;

    case 'logs':
        $filename = 'audit_logs_' . date('Y-m-d') . '.csv';
        $headers = ['Timestamp', 'User', 'Action', 'Entity Type', 'Entity ID', 'Description', 'IP Address'];

        $stmt = $pdo->query("
            SELECT created_at, username, action, entity_type, entity_id, description, ip_address
            FROM audit_logs
            ORDER BY created_at DESC
        ");
        $data = $stmt->fetchAll(PDO::FETCH_NUM);
        break;

    case 'subnets':
        $filename = 'subnets_' . date('Y-m-d') . '.csv';
        $headers = ['Name', 'Network', 'CIDR', 'Gateway', 'VLAN ID', 'Total IPs', 'Used IPs', 'Description'];

        $stmt = $pdo->query("
            SELECT s.name, s.network, s.cidr, s.gateway, s.vlan_id, s.total_ips,
                   COUNT(i.id) as used_ips, s.description
            FROM subnets s
            LEFT JOIN ip_inventory i ON s.id = i.subnet_id AND i.status IN ('active', 'reserved', 'static')
            GROUP BY s.id
        ");
        $data = $stmt->fetchAll(PDO::FETCH_NUM);
        break;

    case 'full_report':
        $filename = 'full_report_' . date('Y-m-d') . '.csv';
        $headers = ['Section', 'Metric', 'Value'];

        // Get all statistics
        $stats = [];

        // IP counts
        $stmt = $pdo->query("SELECT COUNT(*) FROM ip_inventory WHERE status = 'active'");
        $stats[] = ['IP Statistics', 'Active IPs', $stmt->fetchColumn()];

        $stmt = $pdo->query("SELECT COUNT(*) FROM ip_inventory WHERE status = 'reserved'");
        $stats[] = ['IP Statistics', 'Reserved IPs', $stmt->fetchColumn()];

        $stmt = $pdo->query("SELECT COUNT(*) FROM ip_inventory WHERE status = 'conflict'");
        $stats[] = ['IP Statistics', 'Conflicts', $stmt->fetchColumn()];

        // Subnet counts
        $stmt = $pdo->query("SELECT COUNT(*) FROM subnets");
        $stats[] = ['Subnets', 'Total Subnets', $stmt->fetchColumn()];

        // Recent activity
        $stmt = $pdo->query("SELECT COUNT(*) FROM audit_logs WHERE created_at >= NOW() - INTERVAL '24 hour'");
        $stats[] = ['Activity', 'Actions (24h)', $stmt->fetchColumn()];

        $data = $stats;
        break;

    default:
        die('Invalid export type');
}

// Set headers for download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// Open output stream
$output = fopen('php://output', 'w');

// Write headers
fputcsv($output, $headers);

// Write data
foreach ($data as $row) {
    fputcsv($output, $row);
}

fclose($output);

// Log export action
logAudit('export', 'report', null, "Exported $type report");

exit;
