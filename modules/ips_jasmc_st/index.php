<?php
require_once __DIR__ . '/../../config.php';
requireLogin();

$MODULE_TABLE     = 'ips_jasmc_st';
$MODULE_LABEL     = 'JASMC-ST';
$MODULE_FULL_NAME = 'JASMC &mdash; Sto. Tomas';
$MODULE_SLUG      = 'ips_jasmc_st';
$SATELLITE_KEY    = 'jasmc_st';
$MODULE_PATH      = BASE_URL . '/modules/ips_jasmc_st/';
$COMPUTERS_TABLE  = 'computers_jasmc_st';
$COMPUTERS_PATH   = BASE_URL . '/modules/jasmc_st/';

require_once __DIR__ . '/../ips/_satellite_ip_template.php';
