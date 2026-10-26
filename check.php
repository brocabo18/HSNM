<?php
require 'config.php';
$stmt = $pdo->query('SELECT COUNT(*) FROM routers');
echo 'Routers count: ' . $stmt->fetchColumn() . "\n";
