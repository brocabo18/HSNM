<?php
require_once __DIR__ . '/../../config.php';
requireLogin();

$MODULE_TABLE     = 'ips_jasmc_vt';
$MODULE_LABEL     = 'JASMC-VT';
$MODULE_FULL_NAME = 'JASMC &mdash; Victoria Tarlac';
$MODULE_SLUG      = 'ips_jasmc_vt';
$SATELLITE_KEY    = 'jasmc_vt';
$MODULE_PATH      = BASE_URL . '/modules/ips_jasmc_vt/';
$COMPUTERS_TABLE  = 'computers_jasmc_vt';
$COMPUTERS_PATH   = BASE_URL . '/modules/jasmc_vt/';

require_once __DIR__ . '/../ips/_satellite_ip_template.php';
