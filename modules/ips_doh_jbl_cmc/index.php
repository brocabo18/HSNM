<?php
require_once __DIR__ . '/../../config.php';
requireLogin();

$MODULE_TABLE     = 'ips_doh_jbl_cmc';
$MODULE_LABEL     = 'DOH-JBL-CMC';
$MODULE_FULL_NAME = 'DOH-JBL Children\'s Medical Center';
$MODULE_SLUG      = 'ips_doh_jbl_cmc';
$SATELLITE_KEY    = 'doh_jbl_cmc';
$MODULE_PATH      = BASE_URL . '/modules/ips_doh_jbl_cmc/';
$COMPUTERS_TABLE  = 'computers_doh_jbl_cmc';
$COMPUTERS_PATH   = BASE_URL . '/modules/doh_jbl_cmc/';

require_once __DIR__ . '/../ips/_satellite_ip_template.php';
