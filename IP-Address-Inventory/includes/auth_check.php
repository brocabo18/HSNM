<?php
/**
 * Authentication Check Middleware
 * Include this file at the top of protected pages
 */

require_once __DIR__ . '/../config/config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('/IP-Address-Inventory/login.php');
}

// Check session timeout
if (!checkSessionTimeout()) {
    redirect('/IP-Address-Inventory/login.php?timeout=1');
}
