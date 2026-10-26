<?php
require 'config.php';
$stmt = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public'");
print_r($stmt->fetchAll(PDO::FETCH_COLUMN));
